#!/usr/bin/env python3
"""
Orchestrate Real-ESRGAN ncnn-vulkan 2x upscale + Pillow polish over a directory
of images. Reads from cms-media/, writes final .webp to cms-media-upscaled/final/.

Pipeline per image:
  1. Run Real-ESRGAN binary on input -> tmp PNG at 2x
  2. Pillow post-pass: saturation x1.15, contrast x1.05, mild unsharp mask
  3. Save as webp q=85 with same basename (.webp extension)

Resume-friendly: skips images whose final output already exists.
Per-image errors are caught and logged; one bad image does not kill the run.
"""

import os
import sys
import time
import shutil
import subprocess
import tempfile
import logging
from pathlib import Path

from PIL import Image, ImageEnhance, ImageFilter

# ---- paths -------------------------------------------------------------------
ROOT = Path(__file__).resolve().parent
SRC = ROOT.parent / "cms-media"
DST = ROOT / "final"
LOG_DIR = ROOT / "_logs"
BIN = ROOT / "_bin" / "realesrgan-ncnn-vulkan"
MODELS = ROOT / "_bin" / "models"

DST.mkdir(parents=True, exist_ok=True)
LOG_DIR.mkdir(parents=True, exist_ok=True)

# ---- logging -----------------------------------------------------------------
LOG_FILE = LOG_DIR / "run.log"
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.FileHandler(LOG_FILE, mode="w"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("upscale")

# ---- config ------------------------------------------------------------------
SCALE = 2
MODEL_NAME = "realesrgan-x4plus"        # photo model
SATURATION = 1.15                        # mild bump
CONTRAST = 1.05                          # very mild
UNSHARP_RADIUS = 1.5
UNSHARP_PERCENT = 110
UNSHARP_THRESHOLD = 2
WEBP_QUALITY = 85
SUPPORTED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


def upscale_one(src: Path, dst: Path) -> dict:
    """Run Real-ESRGAN + Pillow polish on a single image. Returns a result dict."""
    t0 = time.time()
    with tempfile.TemporaryDirectory() as td:
        tmp_png = Path(td) / "sr.png"
        cmd = [
            str(BIN),
            "-i", str(src),
            "-o", str(tmp_png),
            "-n", MODEL_NAME,
            "-s", str(SCALE),
            "-f", "png",
            "-m", str(MODELS),
        ]
        try:
            r = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
        except subprocess.TimeoutExpired:
            return {"src": src.name, "ok": False, "err": "timeout"}

        if r.returncode != 0 or not tmp_png.exists():
            err = (r.stderr or r.stdout or "").strip().splitlines()[-1] if (r.stderr or r.stdout) else "no-output"
            return {"src": src.name, "ok": False, "err": f"sr-failed: {err[:120]}"}

        try:
            im = Image.open(tmp_png).convert("RGB")
        except Exception as e:
            return {"src": src.name, "ok": False, "err": f"pillow-open: {e}"}

        # Pillow polish
        im = ImageEnhance.Color(im).enhance(SATURATION)
        im = ImageEnhance.Contrast(im).enhance(CONTRAST)
        im = im.filter(
            ImageFilter.UnsharpMask(
                radius=UNSHARP_RADIUS, percent=UNSHARP_PERCENT, threshold=UNSHARP_THRESHOLD
            )
        )

        # Save as .webp with parallel filename
        final_path = dst / (src.stem + ".webp")
        try:
            im.save(final_path, "WEBP", quality=WEBP_QUALITY, method=6)
        except Exception as e:
            return {"src": src.name, "ok": False, "err": f"pillow-save: {e}"}

    sz_in = src.stat().st_size
    sz_out = final_path.stat().st_size
    return {
        "src": src.name,
        "ok": True,
        "out": final_path.name,
        "in_bytes": sz_in,
        "out_bytes": sz_out,
        "elapsed": time.time() - t0,
    }


def discover_inputs() -> list[Path]:
    files = []
    for p in sorted(SRC.iterdir()):
        if not p.is_file():
            continue
        if p.suffix.lower() in SUPPORTED_EXT:
            files.append(p)
    return files


def main() -> int:
    if not BIN.exists():
        log.error("Binary missing: %s", BIN)
        return 1
    if not SRC.exists():
        log.error("Source dir missing: %s", SRC)
        return 1

    inputs = discover_inputs()
    log.info("Discovered %d images in %s", len(inputs), SRC.name)
    log.info("Output -> %s", DST)
    log.info("Scale=%d model=%s sat=%.2f con=%.2f q=%d",
             SCALE, MODEL_NAME, SATURATION, CONTRAST, WEBP_QUALITY)

    ok = 0
    fail = 0
    failed = []
    t_start = time.time()
    for i, src in enumerate(inputs, 1):
        final_path = DST / (src.stem + ".webp")
        if final_path.exists():
            log.info("[%d/%d] SKIP  %s (already exists)", i, len(inputs), src.name)
            ok += 1
            continue

        log.info("[%d/%d] RUN   %s", i, len(inputs), src.name)
        res = upscale_one(src, DST)
        if res["ok"]:
            ok += 1
            log.info("        ok    -> %s  in=%d out=%d  %.1fs",
                     res["out"], res["in_bytes"], res["out_bytes"], res["elapsed"])
        else:
            fail += 1
            failed.append(res)
            log.error("        FAIL  %s : %s", res["src"], res["err"])

    dt = time.time() - t_start
    log.info("=" * 60)
    log.info("DONE  ok=%d  fail=%d  elapsed=%.1fs", ok, fail, dt)
    if failed:
        log.info("Failures:")
        for f in failed:
            log.info("  %s  --  %s", f["src"], f["err"])
    return 0 if fail == 0 else 2


if __name__ == "__main__":
    sys.exit(main())
