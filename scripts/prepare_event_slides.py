"""Prepare the 8 slideshow images for the events page hero.

For each of the 8 source images:
  1. Physically rotate if needed (09 and 18 — stored 90° rotated).
  2. Resize so longest edge <= 1920 (hero) — matching MANIFEST.md spec.
  3. Save as webp (quality=82, method=6) into the existing optimized
     directory storage/app/public/cms-media/, with a deterministic name.

Writes an INDEX.md mapping slide N -> file.
"""
from PIL import Image
import os
import shutil

ROOT = "/Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel"
SRC = os.path.join(ROOT, "storage/app/public/cms-media-originals")
NEW = os.path.join(ROOT, "storage/app/public/cms-media")
DST = NEW  # write alongside the existing optimized files

os.makedirs(DST, exist_ok=True)

# (slide_id, source_path, rotate, hero_webp_name)
# rotate: 0 = no rotation, 90 = rotate 90° clockwise (fixes CCW-stored images)
slides = [
    ("s1-ganesh-chaturthi",     "49f8d3d7-1a57-473b-9978-36fda745bdc0.JPG",  0,   "events-slide-1-ganesh-chaturthi.webp"),
    ("s2-varalakshmi",          "4aabf6d1-2712-4722-b9ec-1cecfbb281c3.JPG",  0,   "events-slide-2-varalakshmi.webp"),
    ("s3-sandhyavandanam",      "68cfd1ef-7cc9-4547-b82b-b7bc14d7ed6f.JPG",  0,   "events-slide-3-sandhyavandanam.webp"),
    ("s4-nivedanam",            "8af17d99-6b22-4d29-979b-9a1d89505c2a.JPG",  0,   "events-slide-4-nivedanam.webp"),
    ("s5-kumbhabhishekam",      "908ed207-411e-43a2-8f8e-1092f662609a.JPG",  0,   "events-slide-5-kumbhabhishekam.webp"),
    ("s6-kalyanam",             "b7fc0a19-0055-47d5-abc9-b96ab83b5472.JPG",  0,   "events-slide-6-kalyanam.webp"),
    ("s7-cultural-evenings",    "edited-photo-09.jpg",                       -90, "events-slide-7-cultural-evenings.webp"),
    ("s8-brahmotsavam",         "edited-photo-18.jpg",                       -90, "events-slide-8-brahmotsavam.webp"),
]

results = []
for sid, src_name, rotate, out_name in slides:
    src_path = os.path.join(NEW if src_name.upper().endswith(".JPG") else SRC, src_name)
    if not os.path.exists(src_path):
        # try other case
        alt = os.path.join(SRC, src_name)
        if os.path.exists(alt):
            src_path = alt
        else:
            print(f"MISSING: {src_name}")
            continue
    out_path = os.path.join(DST, out_name)

    with Image.open(src_path) as im:
        if rotate:
            im = im.rotate(-rotate, expand=True)  # -90 = 90° clockwise
        # Resize so longest edge <= 1920
        longest = max(im.size)
        if longest > 1920:
            ratio = 1920 / longest
            new_size = (int(im.size[0] * ratio), int(im.size[1] * ratio))
            im = im.resize(new_size, Image.Resampling.LANCZOS)
        # Save as webp — quality=82 method=6 (matches MANIFEST spec)
        im.save(out_path, "WEBP", quality=82, method=6)
        final_w, final_h = im.size

    kb = os.path.getsize(out_path) / 1024
    print(f"{sid:30s}  {src_name:50s}  {final_w}x{final_h}  {kb:.1f} KB  -> {out_name}")
    results.append((sid, out_name, final_w, final_h, kb))

# Write INDEX.md
with open(os.path.join(DST, "EVENTS-SLIDES.md"), "w") as f:
    f.write("# Events Hero Slideshow — Optimized Images\n\n")
    f.write("Pipeline: Pillow 11.3, WebP quality=82 method=6, longest-edge <=1920.\n")
    f.write("Source: storage/app/public/cms-media-originals/ (originals preserved).\n\n")
    f.write("| # | Slide | File | Size | KB |\n")
    f.write("|--:|---|---|---|--:|\n")
    for i, (sid, name, w, h, kb) in enumerate(results, 1):
        f.write(f"| {i} | `{sid}` | `{name}` | {w}x{h} | {kb:.1f} |\n")
print("\nWrote EVENTS-SLIDES.md")
