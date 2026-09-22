#!/usr/bin/env python3
"""
Phase 1: Image assessment (diagnostic, read-only).

Per image, computes:
  - Geometric: dimensions, aspect, EXIF orientation, format, mode, size
  - Photometric: brightness mean, dynamic range, per-channel cast, HSV saturation
  - Noise: high-pass stddev, Laplacian variance (blur proxy)
  - Compression: format, JPEG blockiness heuristic

Then classifies damage and recommends downstream operators.

Outputs:
  - _assess/report.json   (machine-readable, full per-image data)
  - _assess/report.md     (human-readable summary)
"""

import sys
import json
import logging
from pathlib import Path
from collections import Counter

import numpy as np
from PIL import Image, ImageOps, ImageFilter

# ---- paths -------------------------------------------------------------------
ROOT = Path(__file__).resolve().parent
SRC = ROOT.parent / "cms-media"
ASSESS = ROOT / "_assess"
ASSESS.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-5s  %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger("assess")

# ---- heuristics (tunable thresholds) -----------------------------------------
# Photometric damage
TH_BRIGHT_LOW = 80           # mean brightness < this -> underexposed
TH_BRIGHT_HIGH = 200         # mean brightness > this -> overexposed (mild)
TH_P95_HIGH = 245            # 95th pct brightness -> blown highlights
TH_DR_LOW = 90               # dynamic range p5-95 < this -> low contrast (faded)
TH_CAST = 12                 # max channel deviation from gray mean -> color cast
TH_SAT_LOW = 50              # mean HSV saturation < this -> low saturation (faded)

# Noise / blur
TH_NOISE_HIGH = 28.0         # high-pass stddev > this -> noisy
TH_LAP_LOW = 40.0            # Laplacian variance < this -> blurry
TH_LAP_VERYLOW = 15.0        # very blurry

# Compression
TH_BLOCKY = 8.0              # JPEG blockiness heuristic

# Face-heavy filename patterns (conservative default: skip AI for these)
FACE_HEAVY_PATTERNS = [
    "trustee", "journal-awards", "events-slide", "journal-community",
    "journal-cultural", "journal-pooja", "journal-kalyanam",
]

SUPPORTED_EXT = {".jpg", ".jpeg", ".png", ".webp", ".gif"}


# ---- helpers -----------------------------------------------------------------
def rgb_to_hsv_np(rgb: np.ndarray) -> np.ndarray:
    """Vectorized RGB [0..255] -> HSV [0..255]; S,V are 0..255, H is 0..255."""
    r, g, b = rgb[..., 0] / 255.0, rgb[..., 1] / 255.0, rgb[..., 2] / 255.0
    maxc = np.max(rgb / 255.0, axis=-1)
    minc = np.min(rgb / 255.0, axis=-1)
    v = maxc
    diff = maxc - minc
    s = np.where(maxc == 0, 0, diff / np.where(maxc == 0, 1, maxc))
    # Hue
    rc = np.where(diff == 0, 0, (maxc - r) / np.where(diff == 0, 1, diff))
    gc = np.where(diff == 0, 0, (maxc - g) / np.where(diff == 0, 1, diff))
    bc = np.where(diff == 0, 0, (maxc - b) / np.where(diff == 0, 1, diff))
    h = np.where(r == maxc, bc - gc,
         np.where(g == maxc, 2.0 + rc - bc, 4.0 + gc - rc))
    h = (h / 6.0) % 1.0
    hsv = np.stack([h * 255.0, s * 255.0, v * 255.0], axis=-1)
    return hsv


def jpeg_blockiness(gray: np.ndarray) -> float:
    """Heuristic: average gradient difference across 8x8 block boundaries
    vs average gradient inside blocks. Higher => more blocky."""
    h, w = gray.shape
    # Vertical block boundaries (every 8 columns)
    diffs_v = []
    for x in range(8, w - 1, 8):
        d = np.abs(gray[:, x].astype(np.float32) - gray[:, x - 1].astype(np.float32))
        diffs_v.append(d.mean())
    # Horizontal block boundaries
    diffs_h = []
    for y in range(8, h - 1, 8):
        d = np.abs(gray[y, :].astype(np.float32) - gray[y - 1, :].astype(np.float32))
        diffs_h.append(d.mean())
    # Inside-block gradients
    inside_v = []
    for x in range(0, w - 9, 8):
        d = np.abs(gray[:, x + 1].astype(np.float32) - gray[:, x].astype(np.float32))
        inside_v.append(d.mean())
    inside_h = []
    for y in range(0, h - 9, 8):
        d = np.abs(gray[y + 1, :].astype(np.float32) - gray[y, :].astype(np.float32))
        inside_h.append(d.mean())

    boundary = (np.mean(diffs_v) + np.mean(diffs_h)) / 2.0 if (diffs_v and diffs_h) else 0.0
    inside = (np.mean(inside_v) + np.mean(inside_h)) / 2.0 if (inside_v and inside_h) else 1.0
    if inside <= 0:
        return 0.0
    return float(boundary / inside)


# ---- core assessment ---------------------------------------------------------
def assess_one(path: Path) -> dict:
    raw = Image.open(path)
    exif = raw.getexif()
    orientation = int(exif.get(0x0112, 1)) if exif else 1
    fmt = raw.format or path.suffix.lstrip(".").upper()
    raw_size = path.stat().st_size

    # Apply EXIF orientation before any pixel analysis
    im = ImageOps.exif_transpose(raw).convert("RGB")
    w, h = im.size
    aspect = w / h

    arr = np.asarray(im, dtype=np.uint8)
    gray = arr.mean(axis=2)

    # Photometric
    brightness = float(gray.mean())
    p5, p50, p95 = np.percentile(gray, [5, 50, 95])
    dynamic_range = float(p95 - p5)

    chan_mean = arr.reshape(-1, 3).mean(axis=0)  # R, G, B
    gray_mean = chan_mean.mean()
    color_cast = (chan_mean - gray_mean).tolist()  # deviation from neutral

    hsv = rgb_to_hsv_np(arr)
    sat_mean = float(hsv[..., 1].mean())

    # Noise (high-pass stddev) - use FIND_EDGES
    edges = np.asarray(im.filter(ImageFilter.FIND_EDGES), dtype=np.float32)
    noise_score = float(edges.std())

    # Blur proxy (Laplacian variance, 3x3)
    lap_kernel = ImageFilter.Kernel(
        size=(3, 3),
        kernel=[0, -1, 0, -1, 4, -1, 0, -1, 0],
        scale=1,
    )
    lap = np.asarray(im.convert("L").filter(lap_kernel), dtype=np.float32)
    lap_var = float(lap.var())

    # Compression
    is_jpeg = path.suffix.lower() in (".jpg", ".jpeg") or fmt in ("JPEG", "MPO")
    blockiness = jpeg_blockiness(gray) if is_jpeg else 0.0

    # Classification
    damage = []
    if brightness < TH_BRIGHT_LOW:
        damage.append("underexposed")
    if brightness > TH_BRIGHT_HIGH or p95 > TH_P95_HIGH:
        damage.append("overexposed")
    if dynamic_range < TH_DR_LOW:
        damage.append("low_contrast_faded")
    if sat_mean < TH_SAT_LOW:
        damage.append("low_saturation")
    if max(abs(c) for c in color_cast) > TH_CAST:
        damage.append("color_cast")
    if noise_score > TH_NOISE_HIGH:
        damage.append("noisy")
    if lap_var < TH_LAP_VERYLOW:
        damage.append("very_blurry")
    elif lap_var < TH_LAP_LOW:
        damage.append("blurry")
    if is_jpeg and blockiness > TH_BLOCKY:
        damage.append("jpeg_artifacted")
    if not damage:
        damage.append("ok")

    # Filename heuristic: face-heavy?
    name_lower = path.name.lower()
    face_heavy = any(pat in name_lower for pat in FACE_HEAVY_PATTERNS)

    # Recommendation
    rec_phase3 = []
    if "underexposed" in damage or "overexposed" in damage or "low_contrast_faded" in damage:
        rec_phase3.append("exposure_gamma")
    if "low_contrast_faded" in damage:
        rec_phase3.append("gentle_contrast")
    if "low_saturation" in damage or "low_contrast_faded" in damage:
        rec_phase3.append("saturation_gentle")
    if "color_cast" in damage:
        rec_phase3.append("white_balance")
    if "overexposed" in damage:
        rec_phase3.append("highlight_recovery")

    rec_phase4 = []
    if "noisy" in damage:
        rec_phase4.append("denoise")
    if "jpeg_artifacted" in damage:
        rec_phase4.append("jpeg_deblock")

    # AI detail restoration: only if blurry AND not face-heavy (conservative)
    ai_detail_ok = ("blurry" in damage or "very_blurry" in damage) and not face_heavy

    return {
        "src": path.name,
        "geometric": {
            "width": int(w),
            "height": int(h),
            "aspect_ratio": round(aspect, 3),
            "exif_orientation": orientation,
            "orientation_normalized": orientation != 1,
            "format": fmt,
            "mode": "RGB",
            "file_bytes": int(raw_size),
            "megapixels": round((w * h) / 1_000_000, 2),
        },
        "photometric": {
            "brightness_mean": round(brightness, 1),
            "brightness_p5": round(float(p5), 1),
            "brightness_p50": round(float(p50), 1),
            "brightness_p95": round(float(p95), 1),
            "dynamic_range_p5_95": round(dynamic_range, 1),
            "channel_mean_rgb": [round(float(c), 1) for c in chan_mean],
            "color_cast_rgb": [round(float(c), 1) for c in color_cast],
            "saturation_mean": round(sat_mean, 1),
        },
        "noise_blur": {
            "noise_score_highpass_stddev": round(noise_score, 2),
            "laplacian_variance": round(lap_var, 2),
        },
        "compression": {
            "is_jpeg": bool(is_jpeg),
            "blockiness_8x8": round(blockiness, 3),
        },
        "face_heavy_filename": bool(face_heavy),
        "damage": damage,
        "recommendation": {
            "phase3_photometric": rec_phase3,
            "phase4_noise_artifacts": rec_phase4,
            "phase5_ai_detail": ai_detail_ok,
            "ai_blocked_reason": ("face_heavy_filename" if face_heavy and (("blurry" in damage) or ("very_blurry" in damage)) else None),
        },
    }


def discover() -> list[Path]:
    files = [p for p in sorted(SRC.iterdir())
             if p.is_file() and p.suffix.lower() in SUPPORTED_EXT]
    return files


def write_md_report(records: list[dict], summary: dict, path: Path) -> None:
    lines = []
    lines.append("# Image Assessment Report")
    lines.append("")
    lines.append(f"**Source**: `{SRC}`  ")
    lines.append(f"**Total**: {summary['total']} images  ")
    lines.append("")
    lines.append("## Damage distribution")
    lines.append("")
    lines.append("| Damage | Count | % |")
    lines.append("|--------|------:|--:|")
    for dmg, n in summary["by_damage"].most_common():
        pct = round(100 * n / summary["total"], 1)
        lines.append(f"| `{dmg}` | {n} | {pct}% |")
    lines.append("")
    lines.append("## Downstream needs")
    lines.append("")
    lines.append("| Need | Count |")
    lines.append("|------|------:|")
    lines.append(f"| Phase 3 (photometric) | {summary['needs_phase3']} |")
    lines.append(f"| Phase 4 (denoise/deblock) | {summary['needs_phase4']} |")
    lines.append(f"| Phase 5 (AI detail, not face-heavy) | {summary['ai_detail_eligible']} |")
    lines.append(f"| Face-heavy (AI blocked) | {summary['face_heavy_count']} |")
    lines.append(f"| Orientation needs normalize | {summary['orientation_needs']} |")
    lines.append("")
    lines.append("## Per-image summary")
    lines.append("")
    lines.append("| File | Size | MP | Bright | DR | Cast | Noise | Blur | Damage | Face |")
    lines.append("|------|-----:|---:|-------:|---:|-----:|------:|-----:|--------|:----:|")
    for r in records:
        g = r["geometric"]
        p = r["photometric"]
        n = r["noise_blur"]
        face = "Y" if r["face_heavy_filename"] else "n"
        cast = p["color_cast_rgb"]
        cast_str = f"{cast[0]:+.0f}/{cast[1]:+.0f}/{cast[2]:+.0f}"
        lines.append(
            f"| `{r['src']}` | {g['width']}×{g['height']} | {g['megapixels']} | "
            f"{p['brightness_mean']:.0f} | {p['dynamic_range_p5_95']:.0f} | {cast_str} | "
            f"{n['noise_score_highpass_stddev']:.1f} | {n['laplacian_variance']:.1f} | "
            f"{', '.join(r['damage'])} | {face} |"
        )
    lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")


def main() -> int:
    inputs = discover()
    log.info("Discovered %d images in %s", len(inputs), SRC)

    records = []
    for i, p in enumerate(inputs, 1):
        try:
            r = assess_one(p)
        except Exception as e:
            log.error("[%d/%d] FAIL %s : %s", i, len(inputs), p.name, e)
            continue
        records.append(r)
        log.info("[%d/%d] %s  damage=%s  need3=%s need4=%s ai=%s",
                 i, len(inputs), p.name, r["damage"],
                 r["recommendation"]["phase3_photometric"],
                 r["recommendation"]["phase4_noise_artifacts"],
                 r["recommendation"]["phase5_ai_detail"])

    # Aggregate
    damage_counter = Counter()
    for r in records:
        for d in r["damage"]:
            damage_counter[d] += 1

    summary = {
        "total": len(records),
        "by_damage": damage_counter,
        "needs_phase3": sum(1 for r in records if r["recommendation"]["phase3_photometric"]),
        "needs_phase4": sum(1 for r in records if r["recommendation"]["phase4_noise_artifacts"]),
        "ai_detail_eligible": sum(1 for r in records if r["recommendation"]["phase5_ai_detail"]),
        "face_heavy_count": sum(1 for r in records if r["face_heavy_filename"]),
        "orientation_needs": sum(1 for r in records if r["geometric"]["orientation_normalized"]),
    }

    json_path = ASSESS / "report.json"
    md_path = ASSESS / "report.md"

    json_path.write_text(
        json.dumps({"summary": {k: (v if not hasattr(v, "most_common") else dict(v.most_common()))
                                for k, v in summary.items()},
                    "files": records},
                   indent=2),
        encoding="utf-8",
    )
    write_md_report(records, summary, md_path)

    log.info("=" * 60)
    log.info("Wrote %s (%d files)", json_path, len(records))
    log.info("Wrote %s", md_path)
    log.info("Total: %d | needs Phase3: %d | needs Phase4: %d | AI-eligible: %d | face-heavy: %d",
             summary["total"], summary["needs_phase3"], summary["needs_phase4"],
             summary["ai_detail_eligible"], summary["face_heavy_count"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
