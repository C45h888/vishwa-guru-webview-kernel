# CMS Media — Optimized Manifest

> Generated 2026-07-31 10:19:12 UTC after R pass (image optimization)
> Pipeline: Pillow 11.3, WebP quality=82, method=6, longest-edge ≤1600px (≤1920px for hero)

## Scope

- **Source**: `storage/app/public/cms-media-originals/` (50139KB, original JPG/PNG, preserved as backup)
- **Optimized**: `storage/app/public/cms-media/` (4314KB, WebP, 91.4% reduction)
- **Files**: 31 (30 edited-photo + 1 home-page-header)
- **Naming**: collision-safe — `edited-photo.jpg.webp` and `edited-photo.png.webp` both preserved

## What this pass DID

1. Backed up originals to `cms-media-originals/` (49 MB preserved)
2. Converted all 31 files: JPG → WebP, PNG → WebP
3. Resized so longest edge ≤ 1600 px (1920 px for hero)
4. Stripped EXIF metadata (Pillow save with default settings)
5. Updated file_assets: storage_path, mime_type, file_size_bytes, file_hash_sha256
6. Updated cms_media_assets: width, height
7. Archived 2 dance recital duplicates (edited-photo-06, edited-photo-28)

## What this pass did NOT do (deferred)

- S — Smart aspect-ratio cropping (deferred to next pass; OpenCV install needed)
- U — Privacy/consent review (governance gate)
- V — Photo-to-slot re-curation (current JSONB refs still work)
- W — CMS admin UI (Phase 4 per AGENTS.md)
- X — SiteHeader logo slot
- Y — Mandala SVG re-optimization

## Files

| # | Original | Optimized | Orig KB | Opt KB | Orig SHA-256 | Opt SHA-256 |
|---:|---|---|---:|---:|---|---|
| 1 | `edited-photo-02.jpg` | `edited-photo-02.jpg.webp` | 1780 | 115 | `4de844eeb697f02a…` | `63686099d317c0dc…` |
| 2 | `edited-photo-03.jpg` | `edited-photo-03.jpg.webp` | 1502 | 77 | `faf2edd3d7e3c55b…` | `67b9671e0a17479a…` |
| 3 | `edited-photo-04.jpg` | `edited-photo-04.jpg.webp` | 1644 | 90 | `587bd081415a3fd8…` | `8c4455a7f0b3f911…` |
| 4 | `edited-photo-05.jpg` | `edited-photo-05.jpg.webp` | 1601 | 76 | `021f72381da59f0d…` | `6143b8fc050ddce8…` |
| 5 | `edited-photo-06.jpg` | `edited-photo-06.jpg.webp` | 1617 | 108 | `dfd86754ce4ae144…` | `fab94155fb68d860…` |
| 6 | `edited-photo-07.jpg` | `edited-photo-07.jpg.webp` | 2292 | 114 | `83140a31b5bfe0e3…` | `604e34fa265f3c51…` |
| 7 | `edited-photo-08.jpg` | `edited-photo-08.jpg.webp` | 2358 | 116 | `1eea5dc41be38738…` | `cac7d00e17e6ae63…` |
| 8 | `edited-photo-09.jpg` | `edited-photo-09.jpg.webp` | 974 | 179 | `34a942743cdf128b…` | `815721ce2991bc43…` |
| 9 | `edited-photo-10.jpg` | `edited-photo-10.jpg.webp` | 916 | 132 | `dfc7ae4c24971eef…` | `dd935f36e2ef40a0…` |
| 10 | `edited-photo-11.jpg` | `edited-photo-11.jpg.webp` | 896 | 146 | `f7280826c1ce58a8…` | `0011bf2a2ef64e96…` |
| 11 | `edited-photo-12.jpg` | `edited-photo-12.jpg.webp` | 710 | 116 | `936ee10242a9b666…` | `0d987c458d4ad780…` |
| 12 | `edited-photo-13.jpg` | `edited-photo-13.jpg.webp` | 852 | 174 | `b5528eda53cad8bb…` | `24e560693ab82094…` |
| 13 | `edited-photo-14.jpg` | `edited-photo-14.jpg.webp` | 911 | 132 | `7c2447a47eead8df…` | `5a97dc618b33927f…` |
| 14 | `edited-photo-15.jpg` | `edited-photo-15.jpg.webp` | 947 | 182 | `c876a191cac050e2…` | `448cfed294b00b44…` |
| 15 | `edited-photo-16.jpg` | `edited-photo-16.jpg.webp` | 2138 | 182 | `3bd2a3f4ac584420…` | `595aaa8c6a446b6d…` |
| 16 | `edited-photo-17.jpg` | `edited-photo-17.jpg.webp` | 804 | 100 | `76c38b7207a1f8fd…` | `5b5114ebbdf2cde3…` |
| 17 | `edited-photo-18.jpg` | `edited-photo-18.jpg.webp` | 1863 | 121 | `fea08118d31958ec…` | `ac11ee0d4d6a3d52…` |
| 18 | `edited-photo-19.jpg` | `edited-photo-19.jpg.webp` | 2127 | 181 | `d73300f81ce90260…` | `f694b58683eec8d9…` |
| 19 | `edited-photo-20.jpg` | `edited-photo-20.jpg.webp` | 2425 | 118 | `33b4cf4061fdbbab…` | `e4c7a98a52e43fbe…` |
| 20 | `edited-photo-21.jpg` | `edited-photo-21.jpg.webp` | 923 | 198 | `e0ae9611c4f22b32…` | `1d486d6546350c7e…` |
| 21 | `edited-photo-22.jpg` | `edited-photo-22.jpg.webp` | 952 | 138 | `da12471bd8a331f9…` | `c296b94b6a853322…` |
| 22 | `edited-photo-23.jpg` | `edited-photo-23.jpg.webp` | 667 | 160 | `c25c7f915ac197dd…` | `0a47cbde051c77d0…` |
| 23 | `edited-photo-24.jpg` | `edited-photo-24.jpg.webp` | 868 | 202 | `2bf7414de7f6cbaf…` | `143b3938ba3c4ebd…` |
| 24 | `edited-photo-25.jpg` | `edited-photo-25.jpg.webp` | 585 | 150 | `bfb8cb5f9a30d10a…` | `abb64c1bed14f62d…` |
| 25 | `edited-photo-26.jpg` | `edited-photo-26.jpg.webp` | 671 | 168 | `98fc76d76855ef45…` | `1432d66632b52a75…` |
| 26 | `edited-photo-27.jpg` | `edited-photo-27.jpg.webp` | 1057 | 218 | `e718c1400ffe021d…` | `5fc9238524900ee5…` |
| 27 | `edited-photo-28.jpg` | `edited-photo-28.jpg.webp` | 967 | 173 | `32e4810656eab4f1…` | `e6c78fde53ce888a…` |
| 28 | `edited-photo-copy.jpg` | `edited-photo-copy.jpg.webp` | 1666 | 83 | `5d9a0e2c067c2596…` | `143a182ddfc1c863…` |
| 29 | `edited-photo.jpg` | `edited-photo.jpg.webp` | 3137 | 157 | `221bb8d8b22a2054…` | `4bdc47d4d15e5e1a…` |
| 30 | `edited-photo.png` | `edited-photo.png.webp` | 3504 | 102 | `48c038b5443e7b41…` | `09b6601efebe1866…` |
| 31 | `home-page-header.png` | `home-page-header.png.webp` | 6767 | 94 | `c8c15ef1a7f7d2af…` | `79a88ed3e17f9c5b…` |

**Total: 31 files, 50139KB → 4314KB (91.4% reduction)**
