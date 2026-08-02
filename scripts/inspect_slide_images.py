"""Inspect orientation of all 8 candidate slide images."""
from PIL import Image, ImageOps
import os

ROOT = "/Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel"
SRC = os.path.join(ROOT, "storage/app/public/cms-media-originals")
NEW = os.path.join(ROOT, "storage/app/public/cms-media")

slides = [
    "edited-photo-09.jpg",
    "edited-photo-18.jpg",
]
for f in sorted(os.listdir(NEW)):
    if f.lower().endswith((".jpg", ".jpeg")):
        slides.append(os.path.join("cms-media", f))

for s in slides:
    if s.startswith("cms-media/"):
        path = os.path.join(NEW, s.split("/", 1)[1])
    else:
        path = os.path.join(SRC, s)
    if not os.path.exists(path):
        print(f"MISSING: {path}")
        continue
    with Image.open(path) as im:
        exif = im.getexif()
        orientation = exif.get(0x0112) if exif else None
        try:
            ifd = exif.get_ifd(0x8769) if exif else {}
            if 0x0112 in ifd:
                orientation = ifd[0x0112]
        except Exception:
            pass
        w, h = im.size
        with Image.open(path) as im2:
            im2 = ImageOps.exif_transpose(im2)
            upright_w, upright_h = im2.size
        rotated = (upright_w, upright_h) != (w, h)
        needs_rotate = rotated or (orientation is not None and orientation != 1)
        print(f"{os.path.basename(s):35s}  raw={w}x{h}  upright={upright_w}x{upright_h}  exif={orientation}  needs_fix={needs_rotate}")
