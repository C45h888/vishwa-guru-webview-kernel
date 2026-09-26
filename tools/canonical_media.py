#!/usr/bin/env python3
"""
Canonical image materializer for the Temple Trust media surface.

Deterministic rule (per user decision): every canonical file is named by a PURE
function cleanName(stem) derived from its identity stem. No matching manifest is
needed to find a file — the seeder recomputes cleanName(stem) and gets the same
name every time. A manifest is written for AUDIT only.

cleanName(filename):
  stem      = basename with ALL trailing image extensions removed
              ('edited-photo-07.jpg.jpg' -> 'edited-photo-07')
  variants  = [full-stem, stem-with-trailing-crop-aspect-stripped]
  resolved  = the first variant that some source file 'owns'; else the collapsed one
              (a real base-vs-crop pair stays distinct; an isolated crop merges to base)
  canonical = REGISTRY.get(resolved, resolved)   # identity stems that share bytes
                                                   -> canonical semantic name
  ext       = '.png' if source is png else '.jpg'

The same identity stem yields the same name on every run, so the DB and the files
always agree. Extension is one lossless copy ('.jpg' default).

Usage:
  python3 tools/canonical_media.py materialize [--source DIR]
  python3 tools/canonical_media.py manifest   [--source DIR]
"""

import hashlib
import json
import re
import shutil
import sys
from pathlib import Path

REPO = Path(__file__).resolve().parents[1]
PUB = REPO / "storage" / "app" / "public"
DEFAULT_SOURCE = PUB / "cms-media-upscaled" / "canonical"
OUT = PUB / "cms-media"
AUDIT_DIR = PUB / "cms-media-upscaled" / "_logs"

IMG_EXTS = ("jpg", "jpeg", "png", "webp")
CROP_ASPECTS = ("1x1", "3x4", "4x3", "16x9", "9x16", "3x2", "2x3", "4x5", "5x4", "1x2", "2x1")

# ── Deterministic REGISTRY ────────────────────────────────────────────────────
# identity stem  ->  canonical semantic name (no extension).
# The 22 edited-photo-NN stems below are byte-identical to a semantic journal-* /
# events-* image (verified by content hash); the semantic name is canonical and
# the edited-photo alias merges onto it. Grouped by subject for auditability.
REGISTRY = {
    # byte-identity merges (verified by content hash): edited-photo-NN == a semantic image
    "edited-photo-06": "journal-kalyanam-02",
    "edited-photo-07": "journal-awards-01-chaganti",
    "edited-photo-08": "journal-cultural-04-literary-tribute",
    "edited-photo-10": "journal-cultural-01-bharatanatyam-tribute",
    "edited-photo-11": "journal-awards-02-vivekananda",
    "edited-photo-14": "journal-community-03-ashram-boys",
    "edited-photo-15": "journal-cultural-02-bharatanatyam-guru",
    "edited-photo-17": "journal-community-02-ceremony-with-priest",
    "edited-photo-19": "journal-awards-03-shishya-celebration",
    "edited-photo-20": "journal-awards-04-sarvabhouma",
    "edited-photo-21": "journal-pooja-03-procession",
    "edited-photo-23": "journal-community-05-villagers",
    "edited-photo-24": "journal-pooja-04-sacred-post",
    "edited-photo-26": "journal-pooja-05-temple-entrance",
    "edited-photo-27": "journal-community-06-booklets",
    "edited-photo-28": "journal-cultural-03-bharatanatyam-hyderabad",
    # campus / content
    "gaushala": "campus-gaushala",
    "land": "campus-land",
    "welfare": "campus-welfare",
    "home-page-header": "hero-home",
    "trustee-photo": "trustee-portrait",
    "trustee-photo-3x4": "trustee-portrait-3x4",
    "logo": "trust-logo",
    # generated / new assets (renamed to semantic)
    "img-generated-hero-pass-03": "hero-sustain",
    "img-generated-mandapa-hero": "hero-seva",
    "img-generated-mandapa-mobile": "hero-mandapa-mobile",
    "img-generated-pillar-hall-hero": "hero-lineage",
    "chatgpt-image-22-sept-2026-17-26-39": "gallery-dance-girls-a",
    "dance-girls": "gallery-dance-girls-b",
    "chatgpt-image-22-sept-2026-17-33-48": "gallery-dance-girls-c",
    "49f8d3d7-1a57-473b-9978-36fda745bdc0": "portrait-01",
    "4aabf6d1-2712-4722-b9ec-1cecfbb281c3": "portrait-02",
    "68cfd1ef-7cc9-4547-b82b-b7bc14d7ed6f": "portrait-03",
    "8af17d99-6b22-4d29-979b-9a1d89505c2a": "portrait-04",
    "908ed207-411e-43a2-8f8e-1092f662609a": "portrait-05",
    "b7fc0a19-0055-47d5-abc9-b96ab83b5472": "portrait-06",
    "screenshot-2026-09-22-at-7-10-44-am": "reference-01",
    "screenshot-2026-09-22-at-7-10-50-am": "reference-02",
    "screenshot-2026-09-22-at-7-10-57-am": "reference-03",
    "screenshot-2026-09-22-at-7-11-11-am": "reference-04",
    "screenshot-2026-09-22-at-7-11-18-am": "reference-05",
}


def identity_stem(filename: str) -> str:
    """Original basename -> stem with redundant image-extension tokens removed.

    Strips '.<imgext>' occurring at the end OR immediately before a crop token:
      'edited-photo-07.jpg.jpg'      -> 'edited-photo-07'
      'home-page-header.png.jpg'     -> 'home-page-header'
      'edited-photo-03.jpg.1x1.jpg'  -> 'edited-photo-03-1x1'
      'trustee-photo.jpg.3x4.jpg'    -> 'trustee-photo-3x4'
    A doubled extension is redundant and collapses to the base identity stem.
    """
    s = filename.lower()
    # remove every redundant '.<imgext>' token (end or mid, e.g. before a crop)
    s = re.sub(r"\.((?:" + "|".join(IMG_EXTS) + r"))", "", s)
    return s.rstrip(".")


def _normalise(stem: str) -> str:
    s = re.sub(r"[^a-z0-9]+", "-", stem.lower())
    return re.sub(r"-{2,}", "-", s).strip("-")


def _stem_variants(norm: str):
    """Yield candidate stems: full-fidelity first, then crop-token collapsed."""
    yield "full", norm
    crop = norm
    for a in CROP_ASPECTS:
        if crop.endswith("-" + a):
            crop = crop[: -(len(a) + 1)]
            break
    if crop != norm:
        yield "crop", crop


def _resolve(norm: str, owned: set) -> str:
    """Deterministically pick the target clean stem for one normalised stem.

    Prefer the FULL-fidelity stem whenever a source file owns it (a real
    base-vs-crop pair stays distinct); otherwise fall back to the collapsed crop
    stem so an isolated variant merges to its base name. Inputs derive solely
    from the source stem set (owned), so this is deterministic.
    """
    variants = list(_stem_variants(norm))
    for _mode, stem in variants:
        if stem in owned:
            return stem
    return variants[-1][1]


def cleanName(filename: str, owned: set) -> str:
    """filename -> canonical filename. Deterministic given the source stem set."""
    norm = _normalise(identity_stem(filename))
    stem = _resolve(norm, owned)
    canonical_stem = REGISTRY.get(stem, stem)
    ext = ".png" if filename.lower().endswith(".png") else ".jpg"
    return canonical_stem + ext


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(1 << 16), b""):
            h.update(chunk)
    return h.hexdigest()


def materialise(source: Path, out: Path, do_copy: bool = True):
    out.mkdir(parents=True, exist_ok=True)
    audit = {}
    by_target = {}
    files = [f for f in sorted(source.iterdir()) if f.is_file()]
    owned = {_normalise(identity_stem(f.name)) for f in files}
    for f in files:
        target = cleanName(f.name, owned)
        rec = {"source": f.name, "target": target, "sha256": sha256(f),
               "bytes": f.stat().st_size, "merged_into": None}
        by_target.setdefault(target, []).append((f, rec))
        audit[f.name] = rec

    final_audit = {}
    for target, entries in sorted(by_target.items()):
        hashes = {r["sha256"] for _, r in entries}
        if len(entries) > 1 and len(hashes) > 1:
            # real collision: distinct bytes mapping to one name. Deterministically
            # disambiguate by appending the source's inner-extension token (the
            # '<base>.<ext>' name part), e.g. edited-photo.jpg vs edited-photo.png.
            disambiguated = {}
            for f, r in entries:
                suffix = _inner_ext_token(f.name)
                t2 = target if suffix is None else _stem_of(target) + "-" + suffix + _ext_of(target)
                r["target"] = t2
                disambiguated.setdefault(t2, []).append((f, r))
            for t2, ents2 in disambiguated.items():
                h2 = {r["sha256"] for _, r in ents2}
                if len(ents2) > 1 and len(h2) > 1:
                    raise SystemExit(f"COLLISION unresolved for {t2}: {[f.name for f, _ in ents2]}")
                if do_copy:
                    shutil.copy2(ents2[0][0], out / t2)
                for _, r in ents2[1:]:
                    r["merged_into"] = t2
        else:
            if do_copy:
                shutil.copy2(entries[0][0], out / target)
            for _, r in entries[1:]:
                r["merged_into"] = target
        for _, r in entries:
            final_audit[r["source"]] = r
    return final_audit


def _ext_of(target: str) -> str:
    return "." + target.rsplit(".", 1)[-1]


def _stem_of(target: str) -> str:
    return target.rsplit(".", 1)[0]


def _inner_ext_token(filename: str):
    """Return the middle '<ext>' of '<base>.<ext>.<ext2>' if <base> is bare, else None."""
    s = filename.lower()
    m = re.search(r"\.((?:" + "|".join(IMG_EXTS) + r"))$", s)
    if not m:
        return None
    base = s[: m.start()]
    m2 = re.search(r"\.((?:" + "|".join(IMG_EXTS) + r"))$", base)
    if m2 and "." not in base[: m2.start()]:
        return base[m2.start() + 1:]
    return None


def main(argv):
    cmd = argv[1] if len(argv) > 1 else "materialize"
    source = DEFAULT_SOURCE
    if "--source" in argv:
        source = Path(argv[argv.index("--source") + 1])

    if cmd == "materialize":
        audit = materialise(source, OUT, do_copy=True)
        AUDIT_DIR.mkdir(parents=True, exist_ok=True)
        man = AUDIT_DIR / "canonical_manifest.json"
        man.write_text(json.dumps(audit, indent=2, sort_keys=True))
        written = sorted({r["target"] for r in audit.values()})
        merged = [n for n, r in audit.items() if r["merged_into"]]
        print(f"source files       : {len(audit)}")
        print(f"canonical (written): {len(written)}")
        print(f"merged aliases     : {len(merged)}")
        print(f"out dir            : {OUT}")
        print(f"manifest           : {man}")
    elif cmd == "manifest":
        audit = materialise(source, OUT, do_copy=False)
        for n, r in sorted(audit.items()):
            tag = "  (merged -> %s)" % r["merged_into"] if r["merged_into"] else ""
            print(f"{n:48} -> {r['target']}{tag}")
    else:
        print("usage: canonical_media.py materialize|manifest [--source DIR]")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
