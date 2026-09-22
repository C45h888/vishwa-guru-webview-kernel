#!/usr/bin/env python3
"""
Restore v3: diagnostic-driven photographic restoration with conservative AI.

Pipeline per image (reads _assess/report.json for per-image damage profile):
  Phase 2 - Geometric normalization (Lanczos to target long edge)
  Phase 3 - Photometric restoration (exposure gamma, white balance, gentle contrast/saturation)
  Phase 4 - Noise reduction (light Gaussian denoise only)
  Phase 5 - AI detail restoration (Real-ESRGAN, NO TTA, conservative)
  Phase 6 - SSIM guard (reject AI output if it drifts from pre-AI)
  Final  - Restrained UnsharpMask (radius 1.2, percent 55, threshold 3) — always
  Export - JPEG q=92

CLI:
  python3 restore_v3.py --batch-start 0 --batch-size 8 [--force]

Reads cms-media/, writes cms-media-upscaled/canonical/.
By default skips existing canonical files; --force overwrites.
"""

import sys
import json
import time
import argparse
import subprocess
import tempfile
import logging
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps, ImageEnhance, ImageFilter

# ---- paths -------------------------------------------------------------------
ROOT = Path(__file__).resolve().parent
SRC = ROOT.parent / "cms-media"
ASSESS = ROOT / "_assess"
DST = ROOT / "canonical"
DST.mkdir(parents=True, exist_ok=True)

LOG_FILE = ROOT / "_logs" / "restore_v3.log"
LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.FileHandler(LOG_FILE, mode="w"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("restore_v3")

# ---- config ------------------------------------------------------------------
TARGET_LONG_EDGE = 1600
MAX_CONTRAST = 1.10
MAX_SATURATION = 1.18
MAX_BRIGHTNESS = 1.08
DENOISE_RADIUS = 0.6
UNSHARP_RADIUS = 1.2
UNSHARP_PERCENT = 55
UNSHARP_THRESHOLD = 3
JPEG_QUALITY = 92

# Conservative AI settings (user-specified: TTA off, low scale)
SSIM_FLOOR = 0.90
AI_SCALE = 2
AI_TTA = False
AI_ENABLED = True
BIN = ROOT / "_bin" / "realesrgan-ncnn-vulkan"
MODELS = ROOT / "_bin" / "models"
AI_MODEL_NAME = "realesrgan-x4plus"

SUPPORTED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


# ---- helpers -----------------------------------------------------------------
def ssim(a: Image.Image, b: Image.Image) -> float:
    ag = np.asarray(a.convert("L"), dtype=np.float32)
    bg = np.asarray(b.convert("L"), dtype=np.float32)
    C1 = (0.01 * 255) ** 2
    C2 = (0.03 * 255) ** 2
    def blur(x):
        return np.asarray(
            Image.fromarray(x.astype(np.uint8)).filter(ImageFilter.BoxBlur(5)),
            dtype=np.float32,
        )
    mu_a = blur(ag); mu_b = blur(bg)
    mu_a2 = mu_a * mu_a; mu_b2 = mu_b * mu_b; mu_ab = mu_a * mu_b
    sigma_a2 = blur(ag * ag) - mu_a2
    sigma_b2 = blur(bg * bg) - mu_b2
    sigma_ab = blur(ag * bg) - mu_ab
    num = (2 * mu_ab + C1) * (2 * sigma_ab + C2)
    den = (mu_a2 + mu_b2 + C1) * (sigma_a2 + sigma_b2 + C2)
    return float((num / den).mean())


def geometric_normalize(im: Image.Image) -> Image.Image:
    w, h = im.size
    if max(w, h) < TARGET_LONG_EDGE:
        scale = TARGET_LONG_EDGE / max(w, h)
        im = im.resize((int(round(w * scale)), int(round(h * scale))), Image.LANCZOS)
    return im


def apply_exposure_gamma(arr: np.ndarray) -> np.ndarray:
    cur = float(arr.mean())
    if cur < 110:
        target = 130
    elif cur > 180:
        target = 155
    else:
        return arr
    if cur <= 1:
        return arr
    gamma = np.log(target / 255.0) / np.log(cur / 255.0)
    gamma = float(np.clip(gamma, 0.85, 1.20))
    out = 255.0 * (arr / 255.0) ** gamma
    return np.clip(out, 0, 255).astype(np.float32)


def apply_white_balance(arr: np.ndarray) -> np.ndarray:
    chan = arr.reshape(-1, 3).mean(axis=0)
    target = float(chan.mean())
    if target <= 0:
        return arr
    scale = np.clip(target / chan, 0.85, 1.18)
    out = arr * scale
    return np.clip(out, 0, 255).astype(np.float32)


def ai_upscale(im: Image.Image):
    if not AI_ENABLED or not BIN.exists():
        return None
    with tempfile.TemporaryDirectory() as td:
        tin = Path(td) / "in.png"
        tout = Path(td) / "out.png"
        im.save(tin, "PNG")
        cmd = [
            str(BIN), "-i", str(tin), "-o", str(tout),
            "-n", AI_MODEL_NAME, "-s", str(AI_SCALE), "-f", "png",
            "-m", str(MODELS),
        ]
        if AI_TTA:
            cmd.append("-x")
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
        except subprocess.TimeoutExpired:
            log.error("AI timeout")
            return None
        if r.returncode != 0 or not tout.exists():
            log.error("AI failed: %s", (r.stderr or r.stdout or "").strip()[-200:])
            return None
        return Image.open(tout).convert("RGB")


def restore_one(src: Path, record: dict, dst: Path) -> dict:
    t0 = time.time()
    try:
        raw = Image.open(src)
        im = ImageOps.exif_transpose(raw).convert("RGB")
    except Exception as e:
        return {"src": src.name, "ok": False, "err": f"open: {e}"}

    original_size = im.size
    rec3 = record["recommendation"]["phase3_photometric"]
    rec4 = record["recommendation"]["phase4_noise_artifacts"]
    ai_eligible = bool(record["recommendation"]["phase5_ai_detail"])

    im = geometric_normalize(im)

    arr = np.asarray(im, dtype=np.float32)
    applied_3 = []
    if "exposure_gamma" in rec3:
        arr = apply_exposure_gamma(arr); applied_3.append("exposure_gamma")
    if "white_balance" in rec3:
        arr = apply_white_balance(arr); applied_3.append("white_balance")
    im = Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8))

    if "gentle_contrast" in rec3:
        im = ImageEnhance.Contrast(im).enhance(min(MAX_CONTRAST, 1.06))
        applied_3.append("gentle_contrast")
    if "saturation_gentle" in rec3:
        im = ImageEnhance.Color(im).enhance(min(MAX_SATURATION, 1.10))
        applied_3.append("saturation_gentle")

    applied_4 = []
    if "denoise" in rec4:
        im = im.filter(ImageFilter.GaussianBlur(radius=DENOISE_RADIUS))
        applied_4.append("denoise")
    if "jpeg_deblock" in rec4:
        applied_4.append("jpeg_deblock_skip")

    pre_ai = im.copy()
    ai_used = False
    ai_rejected = False
    ssim_score = None
    if ai_eligible:
        ai_out = ai_upscale(im)
        if ai_out is not None:
            ssim_score = ssim(ai_out, pre_ai.resize(ai_out.size, Image.LANCZOS))
            if ssim_score >= SSIM_FLOOR:
                im = ai_out
                ai_used = True
            else:
                log.warning("AI rejected (SSIM %.3f < %.2f) for %s", ssim_score, SSIM_FLOOR, src.name)
                ai_rejected = True

    im = im.filter(ImageFilter.UnsharpMask(
        radius=UNSHARP_RADIUS, percent=UNSHARP_PERCENT, threshold=UNSHARP_THRESHOLD
    ))

    out_path = dst / (src.stem + ".jpg")
    try:
        im.save(out_path, "JPEG", quality=JPEG_QUALITY, optimize=True, progressive=True)
    except Exception as e:
        return {"src": src.name, "ok": False, "err": f"save: {e}"}

    return {
        "src": src.name, "ok": True, "out": out_path.name,
        "original_size": list(original_size), "final_size": list(im.size),
        "applied_phase3": applied_3, "applied_phase4": applied_4,
        "ai_used": ai_used, "ai_rejected": ai_rejected,
        "ssim": round(ssim_score, 3) if ssim_score is not None else None,
        "in_bytes": src.stat().st_size, "out_bytes": out_path.stat().st_size,
        "elapsed": time.time() - t0,
    }


def main() -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--batch-start", type=int, default=0)
    p.add_argument("--batch-size", type=int, default=8)
    p.add_argument("--force", action="store_true",
                   help="overwrite existing canonical files (refit)")
    args = p.parse_args()

    report_path = ASSESS / "report.json"
    if not report_path.exists():
        log.error("Assessment missing: %s", report_path)
        return 1
    report = json.loads(report_path.read_text())
    records_by_src = {r["src"]: r for r in report["files"]}

    inputs = sorted([p for p in SRC.iterdir()
                     if p.is_file() and p.suffix.lower() in SUPPORTED_EXT])
    total = len(inputs)
    start = max(0, args.batch_start)
    end = min(total, start + args.batch_size)
    batch = inputs[start:end]
    log.info("Batch: %d-%d of %d | force=%s | AI=%s | TTA=%s | SSIM_floor=%.2f",
             start + 1, end, total, args.force,
             "on" if AI_ENABLED and BIN.exists() else "off", AI_TTA, SSIM_FLOOR)

    ok = fail = ai_used = 0
    audit = []
    failed = []
    t_start = time.time()
    last_assess_at = 0
    for i, src in enumerate(batch, start=start + 1):
        rec = records_by_src.get(src.name)
        if rec is None:
            log.warning("[%d/%d] SKIP %s (no assessment)", i, total, src.name)
            continue
        out_path = DST / (src.stem + ".jpg")
        if out_path.exists() and not args.force:
            log.info("[%d/%d] SKIP %s (already exists, use --force to refit)", i, total, src.name)
            ok += 1
            continue
        log.info("[%d/%d] RUN  %s  need3=%s need4=%s ai=%s",
                 i, total, src.name,
                 rec["recommendation"]["phase3_photometric"],
                 rec["recommendation"]["phase4_noise_artifacts"],
                 rec["recommendation"]["phase5_ai_detail"])
        res = restore_one(src, rec, DST)
        if res["ok"]:
            ok += 1
            audit.append(res)
            if res["ai_used"]:
                ai_used += 1
            log.info("        ok   %sx%s -> %sx%s  in=%d out=%d  p3=%s p4=%s ai=%s ssim=%s  %.2fs",
                     res["original_size"][0], res["original_size"][1],
                     res["final_size"][0], res["final_size"][1],
                     res["in_bytes"], res["out_bytes"],
                     res["applied_phase3"], res["applied_phase4"],
                     res["ai_used"], res["ssim"], res["elapsed"])
        else:
            fail += 1
            failed.append(res)
            log.error("        FAIL %s : %s", res["src"], res["err"])

        # Periodic self-assessment every 8 images
        done_since_assess = (i - start) - last_assess_at
        if done_since_assess >= 8:
            last_assess_at = (i - start)
            dt = time.time() - t_start
            rate = (i - start) / dt if dt > 0 else 0
            remaining = total - i
            eta = remaining / rate if rate > 0 else 0
            log.info("=" * 60)
            log.info("SELF-ASSESS  @ %d/%d (%.0f%%)  ok=%d fail=%d ai=%d  "
                     "elapsed=%.1fs rate=%.2f img/s  eta=%.0fs",
                     i, total, 100 * i / total, ok, fail, ai_used, dt, rate, eta)
            log.info("=" * 60)

    (ROOT / "_logs" / f"restore_v3.batch_{start+1}-{end}.audit.json").write_text(
        json.dumps({"batch": [start + 1, end], "summary": {
            "ok": ok, "fail": fail, "ai_used": ai_used,
            "elapsed": time.time() - t_start,
        }, "files": audit}, indent=2),
        encoding="utf-8",
    )

    dt = time.time() - t_start
    log.info("=" * 60)
    log.info("DONE  ok=%d fail=%d ai_used=%d  elapsed=%.1fs  (batch %d-%d of %d)",
             ok, fail, ai_used, dt, start + 1, end, total)
    if failed:
        log.info("Failures:")
        for f in failed:
            log.info("  %s -- %s", f["src"], f["err"])
    return 0 if fail == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
