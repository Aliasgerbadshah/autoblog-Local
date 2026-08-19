#!/usr/bin/env python3
"""
new_post.py — scaffold a new DEV.to article from _TEMPLATE.md.

    python3 tools/new_post.py "Why Your CSS Grid Breaks on Safari" \
        --tags css,webdev,safari,frontend \
        --palette 0F172A 00F5D4 F15BB5 9B5DE5 \
        --slug neon-diner-harmony

Creates `NN-slugified-title.md` with the front matter, the opening swatch table,
section dividers in the palette's own colours, and a stub for the cover prompt.
Then: write the body, run format_md.py and lint_md.py, done.
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from palette_kit import alt_text, color_name, hsl, norm, slugify  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
SITE = "https://colorfiind.com"


def next_number() -> str:
    used = [int(p.name[:2]) for p in ROOT.glob("[0-9][0-9]-*.md") if p.name[:2].isdigit()]
    return f"{max(used) + 1:02d}" if used else "01"


def swatch_table(colors: list[str]) -> str:
    hexes = [norm(c) for c in colors]
    head = "| " + " | ".join(f"`#{c}`" for c in hexes) + " |"
    sep = "| " + " | ".join([":--:"] * len(hexes)) + " |"
    body = "| " + " | ".join(
        f"![{alt_text(c)}](https://placehold.co/120x56/{c}/{c}.png)" for c in hexes) + " |"
    return f"{head}\n{sep}\n{body}"


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("title")
    ap.add_argument("--tags", default="css,webdev,design,beginners")
    ap.add_argument("--description", default="")
    ap.add_argument("--palette", nargs="*", default=["0F172A", "00F5D4", "F15BB5", "9B5DE5"])
    ap.add_argument("--slug", default="", help="palette slug on the site, for the credit link")
    ap.add_argument("--name", default="", help="palette display name")
    args = ap.parse_args()

    tags = [t.strip() for t in args.tags.split(",") if t.strip()][:4]
    hexes = [norm(c) for c in args.palette]
    filename = f"{next_number()}-{slugify(args.title)[:48].strip('-')}.md"
    target = ROOT / filename
    if target.exists():
        print(f"refusing to overwrite {filename}")
        return 1

    template = (ROOT / "_TEMPLATE.md").read_text()

    # front matter
    template = re.sub(r'^title: .*$', f'title: "{args.title}"', template, count=1, flags=re.M)
    template = re.sub(r'^tags: .*$', f'tags: {", ".join(tags)}', template, count=1, flags=re.M)
    if args.description:
        template = re.sub(r'^description: .*$', f'description: "{args.description}"',
                          template, count=1, flags=re.M)

    # swatch table + credit
    template = re.sub(r"\| `#HEX1`[\s\S]*?HEX4\.png\) \|", swatch_table(hexes), template, count=1)
    name = args.name or "Palette Name"
    credit = (f"> 🎨 **Palette in play** — [{name}]({SITE}/palette/{args.slug or 'slug'}) · "
              + " ".join(f"`#{c}`" for c in hexes))
    template = re.sub(r"^> 🎨 \*\*Palette in play\*\*.*$", credit, template, count=1, flags=re.M)

    # dividers cycle through the palette's most saturated colours
    vivid = [h for h in hexes if hsl(h)[1] >= 45 and 18 <= hsl(h)[2] <= 82] or hexes
    for i in range(1, 5):
        c = vivid[(i - 1) % len(vivid)]
        template = template.replace(
            f"![Section divider bar in colour](https://placehold.co/1000x8/HEX{i}/HEX{i}.png)",
            f"![Section divider bar in {color_name(c)}](https://placehold.co/1000x8/{c}/{c}.png)")

    target.write_text(template)
    print(f"✔ {filename}")
    print("  next: write the body, then")
    print("        python3 tools/format_md.py --airy && python3 tools/lint_md.py")
    print(f"  cover prompt: add a section for '{args.title}' to COVER-PROMPTS.md")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
