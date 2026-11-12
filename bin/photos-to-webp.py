#!/usr/bin/env python3
"""Convert a client's photos to web-ready WebP files before they go into the Media Library.

Rule (from Nuvista, 2026-11): never upload a client's phone or camera originals. This turns a
folder of HEIC, DNG, JPG, PNG or TIFF photos into WebP files that are:
  - rotated the right way up (the phone's orientation flag is applied, then dropped),
  - at most 2048 px on the long side (WordPress makes the smaller sizes from it),
  - quality 80, and re-saved smaller (1920 px, quality 74, 68, 62) until under 450 KB,
  - stripped of EXIF and GPS data (phone photos carry the client's home location).

Usage (macOS; HEIC and DNG are read through the built-in `sips`, the rest with Pillow):
  python3 -I bin/photos-to-webp.py "Website Assets" webp-out
  python3 -I bin/photos-to-webp.py "Website Assets" webp-out --max 2400 --limit-kb 600

It prints one line per photo and writes webp-out/manifest.csv (source file, WebP file, size,
width, height) to keep track of which original each upload came from: store the source name in
the attachment's `_es_photo_src` meta when importing, so a better photo can replace it later.
Videos are skipped (compress them separately). Needs Pillow: `python3 -m pip install pillow`.
"""

import argparse
import csv
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

try:
    from PIL import Image, ImageOps
except ImportError:
    sys.exit("Pillow is missing: python3 -m pip install pillow")

PILLOW = {".jpg", ".jpeg", ".png", ".tif", ".tiff", ".webp", ".bmp"}
VIA_SIPS = {".heic", ".heif", ".dng", ".cr2", ".cr3", ".nef", ".arw"}


def slug(name):
    out = "".join(c.lower() if c.isalnum() else "-" for c in name)
    while "--" in out:
        out = out.replace("--", "-")
    return out.strip("-") or "photo"


def open_photo(src, tmp):
    """Pillow image for any supported file; HEIC and raw files go through sips first."""
    if src.suffix.lower() in VIA_SIPS:
        if not shutil.which("sips"):
            raise RuntimeError("needs macOS sips for " + src.suffix)
        jpg = Path(tmp) / (src.stem + ".jpg")
        subprocess.run(["sips", "-s", "format", "jpeg", "-s", "formatOptions", "best", str(src), "--out", str(jpg)],
                       check=True, capture_output=True)
        src = jpg
    img = Image.open(src)
    img = ImageOps.exif_transpose(img)
    if img.mode not in ("RGB", "RGBA"):
        img = img.convert("RGBA" if "A" in img.getbands() else "RGB")
    return img


def save_webp(img, dest, max_px, limit):
    steps = [(max_px, 80), (min(max_px, 1920), 74), (min(max_px, 1920), 68), (min(max_px, 1920), 62)]
    for px, q in steps:
        out = img.copy()
        out.thumbnail((px, px), Image.LANCZOS)
        out.save(dest, "WEBP", quality=q, method=6)  # no exif= argument: metadata is not written
        if dest.stat().st_size <= limit:
            break
    return out.size


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("source", type=Path)
    ap.add_argument("dest", type=Path)
    ap.add_argument("--max", type=int, default=2048, help="long side in px (default 2048)")
    ap.add_argument("--limit-kb", type=int, default=450, help="target file size (default 450)")
    a = ap.parse_args()

    files = sorted(p for p in a.source.rglob("*") if p.is_file() and p.suffix.lower() in PILLOW | VIA_SIPS)
    if not files:
        sys.exit("No photos found in " + str(a.source))
    a.dest.mkdir(parents=True, exist_ok=True)
    rows, used, before, after = [], set(), 0, 0
    with tempfile.TemporaryDirectory() as tmp:
        for src in files:
            name = slug(src.stem)
            n = 2
            while name in used:
                name, n = f"{slug(src.stem)}-{n}", n + 1
            used.add(name)
            dest = a.dest / (name + ".webp")
            try:
                w, h = save_webp(open_photo(src, tmp), dest, a.max, a.limit_kb * 1024)
            except Exception as e:  # keep going: one bad file should not stop the batch
                print(f"SKIP {src.name}: {e}")
                continue
            size = dest.stat().st_size
            before, after = before + src.stat().st_size, after + size
            rows.append([str(src.relative_to(a.source)), dest.name, size, w, h])
            print(f"{src.name} -> {dest.name} {w}x{h} {size // 1024} KB")
    with open(a.dest / "manifest.csv", "w", newline="") as f:
        csv.writer(f).writerows([["source", "webp", "bytes", "width", "height"]] + rows)
    print(f"\n{len(rows)} photos: {before / 1e6:.0f} MB -> {after / 1e6:.1f} MB "
          f"(average {after // max(len(rows), 1) // 1024} KB). Manifest: {a.dest / 'manifest.csv'}")


if __name__ == "__main__":
    main()
