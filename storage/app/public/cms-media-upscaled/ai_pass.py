#!/usr/bin/env python3
"""
AI-only final pass: applies Real-ESRGAN to images flagged by the assessment
as AI-eligible, with strict SSIM guard. Reads from canonical/, writes back
to canonical/ (overwriting in place).

Per user directive: AI runs only on non-face-heavy, blurry images. The SSIM
guard (>= 0.90) rejects AI output if it drifts from the deterministic
pre-AI state — keeping the conservative output when it would otherwise
hallucinate.
"""

import sys
import json
import time
import subprocess
import tempfile
import logging
from pathlib import Path

import numpy as np
from PIL import Image, ImageOps, ImageEnhance, ImageFilter

ROOT = Path(__file__).resolve().parent
ASSESS = ROOT / "_assess"
DST = ROOT / "canonical"
LOG_FILE = ROOT / "_logs" / "ai_pass.log"
LOG_FILE.parent.mkdir(parents=True, exist_ok=True)
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.FileHandler(LOG_FILE, mode="w"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("ai_pass")

BIN = ROOT / "_bin" / "realesrgan-ncnn-vulkan"
MODELS = ROOT / "_bin" / "models"
AI_MODEL_NAME = "realesrgan-x4plus"
AI_SCALE = 2
AI_TTA = False
SSIM_FLOOR = 0.90


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


def ai_upscale(im: Image.Image):
    if not BIN.exists():
        return None
    with tempfile.TemporaryDirectory() as td:
        tin = Path(td) / "in.png"
        tout = Path(td) / "out.png"
        im.save(tin, "PNG")
        cmd = [str(BIN), "-i", str(tin), "-o", str(tout),
               "-n", AI_MODEL_NAME, "-s", str(AI_SCALE), "-f", "png",
               "-m", str(MODELS)]
        if AI_TTA:
            cmd.append("-x")
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
        except subprocess.TimeoutExpired:
            return None
        if r.returncode != 0 or not tout.exists():
            return None
        return Image.open(tout).convert("RGB")


def main() -> int:
    if not ASSESS.exists():
        log.error("Assessment missing")
        return 1
    report = json.loads((ASSESS / "report.json").read_text())
    eligible = [r for r in report["files"] if r["recommendation"]["phase5_ai_detail"]]
    log.info("AI-eligible from assessment: %d", len(eligible))
    for r in eligible:
        log.info("  - %s  (face_heavy=%s)", r["src"], r["face_heavy_filename"])

    if not eligible:
        log.info("Nothing to do.")
        return 0

    # Original cms-media source path (so we start from the unprocessed image)
    SRC = ROOT.parent / "cms-media"

    used = 0
    rejected = 0
    skipped = 0
    t_start = time.time()
    for rec in eligible:
        name = rec["src"]
        src_path = SRC / name
        if not src_path.exists():
            log.warning("Source missing: %s", src_path)
            skipped += 1
            continue

        # Apply full Phase 2/3/4 to get the deterministic pre-AI state
        try:
            raw = Image.open(src_path)
            im = ImageOps.exif_transpose(raw).convert("RGB")
        except Exception as e:
            log.error("open failed: %s : %s", name, e)
            skipped += 1
            continue

        # Phase 2 geometric
        w, h = im.size
        if max(w, h) < 1600:
            s = 1600 / max(w, h)
            im = im.resize((int(round(w * s)), int(round(h * s))), Image.LANCZOS)

        # Phase 3 minimal (only WB/sat from assessment)
        arr = np.asarray(im, dtype=np.float32)
        rec3 = rec["recommendation"]["phase3_photometric"]
        if "white_balance" in rec3:
            chan = arr.reshape(-1, 3).mean(axis=0)
            target = float(chan.mean())
            if target > 0:
                scale = np.clip(target / chan, 0.85, 1.18)
                arr = np.clip(arr * scale, 0, 255).astype(np.float32)
        im = Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8))

        # Phase 4 denoise
        if "denoise" in rec["recommendation"]["phase4_noise_artifacts"]:
            im = im.filter(ImageFilter.GaussianBlur(radius=0.6))

        pre_ai = im.copy()
        ai_out = ai_upscale(im)
        if ai_out is None:
            log.warning("AI failed for %s, keeping deterministic", name)
            rejected += 1
            continue
        s = ssim(ai_out, pre_ai.resize(ai_out.size, Image.LANCZOS))
        if s < SSIM_FLOOR:
            log.warning("AI rejected for %s (SSIM %.3f < %.2f)", name, s, SSIM_FLOOR)
            rejected += 1
            continue

        # AI accepted — apply final sharpen and overwrite canonical
        ai_out = ai_out.filter(ImageFilter.UnsharpMask(
            radius=1.2, percent=55, threshold=3
        ))
        out_path = DST / (Path(name).stem + ".jpg")
        ai_out.save(out_path, "JPEG", quality=92, optimize=True, progressive=True)
        used += 1
        log.info("AI ACCEPTED for %s  SSIM=%.3f  size=%s  -> %s",
                 name, s, pre_ai.size, ai_out.size)

    log.info("=" * 50)
    log.info("DONE  ai_used=%d  ai_rejected=%d  skipped=%d  elapsed=%.1fs",
             used, rejected, skipped, time.time() - t_start)
    return 0


if __name__ == "__main__":
    sys.exit(main())
