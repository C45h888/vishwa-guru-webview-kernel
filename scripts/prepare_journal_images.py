"""Optimize the 29 remaining event-grade images for the journal.

Output: webp files in storage/app/public/cms-media/ with deterministic names:
  journal-{category}-{nn}-{slug}.webp

Slides 1, 2, 3 (the slideshow) are already done. This script handles the
other 29 instances.

Pipeline matches storage/app/public/cms-media/MANIFEST.md:
  Pillow 11.3, WebP quality=82 method=6, longest-edge <=1600px (journal cards).
"""
from PIL import Image
import os

ROOT = "/Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel"
SRC = os.path.join(ROOT, "storage/app/public/cms-media-originals")
NEW = os.path.join(ROOT, "storage/app/public/cms-media")
DST = NEW

os.makedirs(DST, exist_ok=True)

# (slide_id, source_path, rotate, output_name)
# rotate: 0 = no rotation, -90 = rotate 90° CW (to fix CCW-stored pixels)
slides = [
    # Festivals (4 instances; 2 already in slideshow, optimize the other 2 fresh)
    ("journal-festivals-01-girija-kalyana",     "edited-photo-02.jpg",                       0,  "journal-festivals-01-girija-kalyana.webp"),
    ("journal-festivals-03-ganesh-chaturthi",   "49f8d3d7-1a57-473b-9978-36fda745bdc0.JPG",  0,  "journal-festivals-03-ganesh-chaturthi.webp"),

    # Cultural Evenings / Dance (6 instances; 1 already in slideshow, optimize 5 more)
    ("journal-cultural-01-bharatanatyam-tribute",  "edited-photo-10.jpg",                  0,  "journal-cultural-01-bharatanatyam-tribute.webp"),
    ("journal-cultural-02-bharatanatyam-guru",     "edited-photo-15.jpg",                  0,  "journal-cultural-02-bharatanatyam-guru.webp"),
    ("journal-cultural-03-bharatanatyam-hyderabad", "edited-photo-28.jpg",                  0,  "journal-cultural-03-bharatanatyam-hyderabad.webp"),
    ("journal-cultural-04-literary-tribute",       "edited-photo-08.jpg",                  0,  "journal-cultural-04-literary-tribute.webp"),
    ("journal-cultural-05-sangam-collage",          "edited-photo-16.jpg",                  0,  "journal-cultural-05-sangam-collage.webp"),

    # Daily Pooja / Sacred Rituals (5 instances)
    ("journal-pooja-01-sandhyavandanam",         "68cfd1ef-7cc9-4547-b82b-b7bc14d7ed6f.JPG",  0,  "journal-pooja-01-sandhyavandanam.webp"),
    ("journal-pooja-02-nivedanam",               "8af17d99-6b22-4d29-979b-9a1d89505c2a.JPG",  0,  "journal-pooja-02-nivedanam.webp"),
    ("journal-pooja-03-procession",              "edited-photo-21.jpg",                       0,  "journal-pooja-03-procession.webp"),
    ("journal-pooja-04-sacred-post",             "edited-photo-24.jpg",                       0,  "journal-pooja-04-sacred-post.webp"),
    ("journal-pooja-05-temple-entrance",         "edited-photo-26.jpg",                       0,  "journal-pooja-05-temple-entrance.webp"),

    # Kalyanam / Weddings (3 instances)
    ("journal-kalyanam-01",                      "b7fc0a19-0055-47d5-abc9-b96ab83b5472.JPG",  0,  "journal-kalyanam-01.webp"),
    ("journal-kalyanam-02",                      "edited-photo-06.jpg",                       0,  "journal-kalyanam-02.webp"),
    ("journal-kalyanam-03",                      "edited-photo-04.jpg",                       0,  "journal-kalyanam-03.webp"),

    # Kumbhabhishekam (1 instance)
    ("journal-kumbhabhishekam-01",               "908ed207-411e-43a2-8f8e-1092f662609a.JPG",  0,  "journal-kumbhabhishekam-01.webp"),

    # Award / Felicitation (6 instances)
    ("journal-awards-01-chaganti",               "edited-photo-07.jpg",                       0,  "journal-awards-01-chaganti.webp"),
    ("journal-awards-02-vivekananda",            "edited-photo-11.jpg",                       0,  "journal-awards-02-vivekananda.webp"),
    ("journal-awards-03-shishya-celebration",    "edited-photo-19.jpg",                       0,  "journal-awards-03-shishya-celebration.webp"),
    ("journal-awards-04-sarvabhouma",            "edited-photo-20.jpg",                       0,  "journal-awards-04-sarvabhouma.webp"),
    ("journal-awards-05-south-indian",           "edited-photo-25.jpg",                       0,  "journal-awards-05-south-indian.webp"),
    ("journal-awards-06-ganap-sachidananda",     "home-page-header.png",                      0,  "journal-awards-06-ganap-sachidananda.webp"),

    # Community / Seva (6 instances)
    ("journal-community-01-village-group",       "edited-photo-03.jpg",                       0,  "journal-community-01-village-group.webp"),
    ("journal-community-02-ceremony-with-priest", "edited-photo-17.jpg",                       0,  "journal-community-02-ceremony-with-priest.webp"),
    ("journal-community-03-ashram-boys",         "edited-photo-14.jpg",                       0,  "journal-community-03-ashram-boys.webp"),
    ("journal-community-04-ashram-adults",       "edited-photo-22.jpg",                       0,  "journal-community-04-ashram-adults.webp"),
    ("journal-community-05-villagers",           "edited-photo-23.jpg",                       0,  "journal-community-05-villagers.webp"),
    ("journal-community-06-booklets",            "edited-photo-27.jpg",                       0,  "journal-community-06-booklets.webp"),

    # Indoor Public Gatherings (1 instance)
    ("journal-indoor-01",                        "edited-photo-copy.jpg",                     0,  "journal-indoor-01.webp"),
]

results = []
for sid, src_name, rotate, out_name in slides:
    # Look in originals first, fall back to cms-media/ for the new UUID JPGs
    candidates = [
        os.path.join(SRC, src_name),
        os.path.join(NEW, src_name),
    ]
    src_path = next((p for p in candidates if os.path.exists(p)), None)
    if src_path is None:
        print(f"MISSING: {src_name}")
        continue
    out_path = os.path.join(DST, out_name)

    with Image.open(src_path) as im:
        if rotate:
            im = im.rotate(rotate, expand=True)
        longest = max(im.size)
        if longest > 1600:
            ratio = 1600 / longest
            new_size = (int(im.size[0] * ratio), int(im.size[1] * ratio))
            im = im.resize(new_size, Image.Resampling.LANCZOS)
        im.save(out_path, "WEBP", quality=82, method=6)
        final_w, final_h = im.size

    kb = os.path.getsize(out_path) / 1024
    print(f"{sid:50s}  {src_name:50s}  {final_w}x{final_h}  {kb:.1f} KB")
    results.append((sid, out_name, final_w, final_h, kb))

print(f"\nProcessed {len(results)} images")
