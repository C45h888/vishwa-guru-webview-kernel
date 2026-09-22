#!/usr/bin/env python3
"""
Phase 2-6 orchestrator: diagnostic-driven photographic restoration.

Reads assessment report.json, then per image:
  Phase 2 - Geometric normalization (EXIF, Lanczos to target long edge)
  Phase 3 - Photometric restoration (exposure gamma, white balance, gentle contrast/saturation)
  Phase 4 - Noise/artifacts (light Gaussian denoise only — deblock skipped for safety)
  Phase 5 - AI detail restoration (Real-ESRGAN with TTA, only when eligible)
  Phase 6 - Compare/reject (SSIM guard on AI output; fall back to pre-AI if rejected)
  Final  - Restrained UnsharpMask (radius 1.2, percent 55, threshold 3) — always
  Export - JPEG q=92, optimize, progressive

Reads from cms-media/, writes to cms-media-upscaled/restored_v2/.
"""

import sys
import json
import time
import shutil
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
DST = ROOT / "restored_v2"
DST.mkdir(parents=True, exist_ok=True)
ASSESS.mkdir(parents=True, exist_ok=True)

LOG_FILE = ROOT / "_logs" / "restore_v2.log"
LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.FileHandler(LOG_FILE, mode="w"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("restore_v2")

# ---- config ------------------------------------------------------------------
TARGET_LONG_EDGE = 1600

# Photometric ceiling (anti-stylization guardrail)
MAX_CONTRAST = 1.10
MAX_SATURATION = 1.18
MAX_BRIGHTNESS = 1.08

# Denoise
DENOISE_RADIUS = 0.6

# Final sharpen (matches reference workflow exactly)
UNSHARP_RADIUS = 1.2
UNSHARP_PERCENT = 55
UNSHARP_THRESHOLD = 3

JPEG_QUALITY = 92

# AI guardrails
SSIM_FLOOR = 0.85            # reject AI output if SSIM vs pre-AI < this
AI_SCALE = 2
AI_TTA = True
BIN = ROOT / "_bin" / "realesrgan-ncnn-vulkan"
MODELS = ROOT / "_bin" / "models"
AI_MODEL_NAME = "realesrgan-x4plus"

SUPPORTED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


# ---- helpers -----------------------------------------------------------------
def ssim(a: Image.Image, b: Image.Image) -> float:
    """Single-scale SSIM (Pillow-only, approximate). Returns mean SSIM in [-1, 1].
    Operates on luminance (gray) for speed."""
    ag = np.asarray(a.convert("L"), dtype=np.float32)
    bg = np.asarray(b.convert("L"), dtype=np.float32)
    # 11x11 gaussian window, simplified
    C1 = (0.01 * 255) ** 2
    C2 = (0.03 * 255) ** 2
    # Use simple box-filter approximation (11x11)
    from PIL import ImageFilter
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
    """Phase 2: EXIF (already done by caller) + Lanczos to TARGET_LONG_EDGE if smaller."""
    w, h = im.size
    if max(w, h) < TARGET_LONG_EDGE:
        scale = TARGET_LONG_EDGE / max(w, h)
        nw, nh = int(round(w * scale)), int(round(h * scale))
        im = im.resize((nw, nh), Image.LANCZOS)
    return im


def apply_exposure_gamma(arr: np.ndarray) -> np.ndarray:
    """Tune gamma toward target brightness 120-140 (gentle, never stylize)."""
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


def apply_highlight_recovery(arr: np.ndarray) -> np.ndarray:
    """Soft knee for highlights: compress values above 220 toward 235."""
    out = arr.copy()
    mask = out > 200
    # remap [200, 255] -> [200, 235] with smooth ease
    hi = out[mask]
    if hi.size == 0:
        return out
    t = (hi - 200.0) / 55.0  # 0..1
    # smoothstep-style compression
    compressed = 200.0 + 35.0 * (1 - (1 - t) ** 2)
    out[mask] = compressed
    return out


def apply_white_balance(arr: np.ndarray) -> np.ndarray:
    """Gray-world: scale channels so their means equal the global mean.
    Cap per-channel scale to [0.85, 1.18] to prevent runaway corrections."""
    chan = arr.reshape(-1, 3).mean(axis=0)
    target = float(chan.mean())
    if target <= 0:
        return arr
    scale = target / chan
    scale = np.clip(scale, 0.85, 1.18)
    out = arr * scale
    return np.clip(out, 0, 255).astype(np.float32)


def ai_upscale(im: Image.Image):
    """Run Real-ESRGAN with TTA. Returns upscaled image or None on failure."""
    if not BIN.exists():
        log.error("AI binary missing: %s", BIN)
        return None
    with tempfile.TemporaryDirectory() as td:
        tin = Path(td) / "in.png"
        tout = Path(td) / "out.png"
        im.save(tin, "PNG")
        cmd = [
            str(BIN),
            "-i", str(tin),
            "-o", str(tout),
            "-n", AI_MODEL_NAME,
            "-s", str(AI_SCALE),
            "-f", "png",
            "-m", str(MODELS),
        ]
        if AI_TTA:
            cmd.append("-x")
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
        except subprocess.TimeoutExpired:
            return None
        if r.returncode != 0 or not tout.exists():
            err = (r.stderr or r.stdout or "").strip().splitlines()[-1] if (r.stderr or r.stdout) else "no-output"
            log.error("AI failed: %s", err[:200])
            return None
        return Image.open(tout).convert("RGB")


# ---- per-image driver --------------------------------------------------------
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

    # Phase 2
    im = geometric_normalize(im)

    # Phase 3 — operate on float32 array, in safe order
    arr = np.asarray(im, dtype=np.float32)
    applied_3 = []
    if "exposure_gamma" in rec3:
        arr = apply_exposure_gamma(arr)
        applied_3.append("exposure_gamma")
    if "highlight_recovery" in rec3:
        arr = apply_highlight_recovery(arr)
        applied_3.append("highlight_recovery")
    if "white_balance" in rec3:
        arr = apply_white_balance(arr)
        applied_3.append("white_balance")
    im = Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8))

    if "gentle_contrast" in rec3:
        im = ImageEnhance.Contrast(im).enhance(min(MAX_CONTRAST, 1.06))
        applied_3.append("gentle_contrast")
    if "saturation_gentle" in rec3:
        im = ImageEnhance.Color(im).enhance(min(MAX_SATURATION, 1.10))
        applied_3.append("saturation_gentle")

    # Phase 4 — denoise
    applied_4 = []
    if "denoise" in rec4:
        im = im.filter(ImageFilter.GaussianBlur(radius=DENOISE_RADIUS))
        applied_4.append("denoise")
    # jpeg_deblock: skipped (no safe Pillow-only path); logged if requested
    if "jpeg_deblock" in rec4:
        applied_4.append("jpeg_deblock_skip")

    # Phase 5 — AI
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

    # Final restrained unsharp (always)
    im = im.filter(ImageFilter.UnsharpMask(
        radius=UNSHARP_RADIUS, percent=UNSHARP_PERCENT, threshold=UNSHARP_THRESHOLD
    ))

    out_path = dst / (src.stem + ".jpg")
    try:
        im.save(out_path, "JPEG", quality=JPEG_QUALITY, optimize=True, progressive=True)
    except Exception as e:
        return {"src": src.name, "ok": False, "err": f"save: {e}"}

    return {
        "src": src.name,
        "ok": True,
        "out": out_path.name,
        "original_size": list(original_size),
        "final_size": list(im.size),
        "applied_phase3": applied_3,
        "applied_phase4": applied_4,
        "ai_used": ai_used,
        "ai_rejected": ai_rejected,
        "ssim": round(ssim_score, 3) if ssim_score is not None else None,
        "in_bytes": src.stat().st_size,
        "out_bytes": out_path.stat().st_size,
        "elapsed": time.time() - t0,
    }


def main() -> int:
    report_path = ASSESS / "report.json"
    if not report_path.exists():
        log.error("Assessment missing: %s (run assess.py first)", report_path)
        return 1
    report = json.loads(report_path.read_text())
    records_by_src = {r["src"]: r for r in report["files"]}

    inputs = sorted([p for p in SRC.iterdir()
                     if p.is_file() and p.suffix.lower() in SUPPORTED_EXT])
    log.info("Discovered %d originals", len(inputs))
    log.info("Target long edge: %d px | SSIM floor: %.2f | AI: %s (TTA)",
             TARGET_LONG_EDGE, SSIM_FLOOR, "enabled" if BIN.exists() else "DISABLED (binary missing)")

    ok = fail = ai_used = 0
    failed = []
    audit = []
    t_start = time.time()
    for i, src in enumerate(inputs, 1):
        rec = records_by_src.get(src.name)
        if rec is None:
            log.warning("[%d/%d] SKIP %s (no assessment)", i, len(inputs), src.name)
            continue
        out_path = DST / (src.stem + ".jpg")
        if out_path.exists():
            log.info("[%d/%d] SKIP %s (already exists)", i, len(inputs), src.name)
            ok += 1
            continue

        log.info("[%d/%d] RUN  %s  need3=%s need4=%s ai=%s",
                 i, len(inputs), src.name,
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

    # Audit JSON
    (ROOT / "_logs" / "restore_v2.audit.json").write_text(
        json.dumps({"summary": {"ok": ok, "fail": fail, "ai_used": ai_used,
                                "elapsed": time.time() - t_start},
                    "files": audit}, indent=2),
        encoding="utf-8",
    )

    dt = time.time() - t_start
    log.info("=" * 60)
    log.info("DONE  ok=%d fail=%d ai_used=%d  elapsed=%.1fs", ok, fail, ai_used, dt)
    if failed:
        log.info("Failures:")
        for f in failed:
            log.info("  %s -- %s", f["src"], f["err"])
    return 0 if fail == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
