#!/usr/bin/env python3
"""
Photographic restoration pipeline (Pillow/PIL only).

Pipeline per image (matches user's reference workflow verbatim):
  1. EXIF orientation correction (ImageOps.exif_transpose)
  2. 2x Lanczos resampling (deterministic math, no AI hallucination)
  3. Contrast x1.06 (mild global)
  4. Color saturation x1.10
  5. Brightness x1.02
  6. UnsharpMask(radius=1.2, percent=55, threshold=3)
  7. JPEG export q=92, optimize, progressive

Reads from cms-media/, writes to cms-media-upscaled/restored/.
Resume-friendly: skips outputs that already exist.
"""

import sys
import time
import logging
from pathlib import Path

from PIL import Image, ImageOps, ImageEnhance, ImageFilter

# ---- paths -------------------------------------------------------------------
ROOT = Path(__file__).resolve().parent
SRC = ROOT.parent / "cms-media"
DST = ROOT / "restored"
LOG_DIR = ROOT / "_logs"
LOG_FILE = LOG_DIR / "restore.log"

DST.mkdir(parents=True, exist_ok=True)
LOG_DIR.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.FileHandler(LOG_FILE, mode="w"), logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("restore")

# ---- params (locked from user spec — do not tune) ---------------------------
LANCZOS_FACTOR = 2
CONTRAST = 1.06
SATURATION = 1.10
BRIGHTNESS = 1.02
UNSHARP_RADIUS = 1.2
UNSHARP_PERCENT = 55
UNSHARP_THRESHOLD = 3
JPEG_QUALITY = 92
SUPPORTED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


def restore_one(src: Path, dst: Path) -> dict:
    t0 = time.time()
    try:
        im = Image.open(src)
        im = ImageOps.exif_transpose(im)  # orientation normalization
        im = im.convert("RGB")
    except Exception as e:
        return {"src": src.name, "ok": False, "err": f"open/transpose: {e}"}

    w, h = im.size
    im = im.resize((w * LANCZOS_FACTOR, h * LANCZOS_FACTOR), Image.LANCZOS)

    im = ImageEnhance.Contrast(im).enhance(CONTRAST)
    im = ImageEnhance.Color(im).enhance(SATURATION)
    im = ImageEnhance.Brightness(im).enhance(BRIGHTNESS)
    im = im.filter(
        ImageFilter.UnsharpMask(
            radius=UNSHARP_RADIUS, percent=UNSHARP_PERCENT, threshold=UNSHARP_THRESHOLD
        )
    )

    out_path = dst / (src.stem + ".jpg")
    try:
        im.save(out_path, "JPEG", quality=JPEG_QUALITY, optimize=True, progressive=True)
    except Exception as e:
        return {"src": src.name, "ok": False, "err": f"save: {e}"}

    return {
        "src": src.name,
        "ok": True,
        "out": out_path.name,
        "in_bytes": src.stat().st_size,
        "out_bytes": out_path.stat().st_size,
        "in_size": (w, h),
        "out_size": im.size,
        "elapsed": time.time() - t0,
    }


def discover_inputs() -> list[Path]:
    files = []
    for p in sorted(SRC.iterdir()):
        if p.is_file() and p.suffix.lower() in SUPPORTED_EXT:
            files.append(p)
    return files


def main() -> int:
    if not SRC.exists():
        log.error("Source dir missing: %s", SRC)
        return 1

    inputs = discover_inputs()
    log.info("Discovered %d originals in %s", len(inputs), SRC.name)
    log.info(
        "Output -> %s  (Lanczos 2x, contrast=%.2f sat=%.2f bri=%.2f, "
        "unsharp r=%.1f p=%d t=%d, jpeg q=%d)",
        DST, CONTRAST, SATURATION, BRIGHTNESS,
        UNSHARP_RADIUS, UNSHARP_PERCENT, UNSHARP_THRESHOLD, JPEG_QUALITY,
    )

    ok = 0
    fail = 0
    failed = []
    t_start = time.time()
    for i, src in enumerate(inputs, 1):
        out_path = DST / (src.stem + ".jpg")
        if out_path.exists():
            log.info("[%d/%d] SKIP  %s (already exists)", i, len(inputs), src.name)
            ok += 1
            continue

        log.info("[%d/%d] RUN   %s", i, len(inputs), src.name)
        res = restore_one(src, DST)
        if res["ok"]:
            ok += 1
            log.info(
                "        ok    %s -> %s   %sx%s -> %s   in=%d out=%d   %.2fs",
                res["src"], res["out"],
                f"{res['in_size'][0]}x{res['in_size'][1]}",
                f"{res['out_size'][0]}x{res['out_size'][1]}",
                res["in_bytes"], res["out_bytes"], res["elapsed"],
            )
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
