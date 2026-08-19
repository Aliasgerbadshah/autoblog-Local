#!/usr/bin/env python3
"""
lint_md.py — check DEV.to markdown files before you paste them into the editor.

    python3 tools/lint_md.py            # lint every article
    python3 tools/lint_md.py --fix      # auto-fix what can be fixed (alt text)

Checks
  1. every image has non-empty, descriptive alt text   (DEV's a11y linter)
  2. front matter present, title/description sane, max 4 tags
  3. no cover_image URL (you upload the cover by hand)
  4. Liquid tags balanced (details / cta / katex)
  5. no stray triple backticks inside tables
  6. no leftover placeholder text
"""

from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from palette_kit import alt_text  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent
IMG = re.compile(r"!\[([^\]]*)\]\((https?://[^)\s]+)\)")
SWATCH = re.compile(r"https?://placehold\.co/\d+x\d+/([0-9A-Fa-f]{6})/")


def fix_alts(text: str) -> tuple[str, int]:
    """Give every swatch image a descriptive alt; returns (text, n_fixed)."""
    fixed = 0

    def repl(m: re.Match) -> str:
        nonlocal fixed
        alt, url = m.group(1), m.group(2)
        swatch = SWATCH.search(url)
        weak = (not alt.strip()
                or alt.strip().lower() in {"swatch", "rule", "image", "cover"}
                or re.fullmatch(r"#?[0-9A-Fa-f]{6}", alt.strip())
                or alt.strip().endswith(" swatch")
                or (bool(swatch) and "#" not in alt))   # palette name alone isn't descriptive
        if swatch and weak:
            fixed += 1
            hexcode = swatch.group(1).upper()
            base = alt_text(hexcode)
            if "/250x1" in url or re.search(r"/\d+x(?:8|10|12|14)/", url):
                base = f"{base} in a divider rule"
            return f"![{base}]({url})"
        if weak and not swatch:
            fixed += 1
            return f"![Illustration]({url})"
        return m.group(0)

    return IMG.sub(repl, text), fixed


def lint(path: Path, fix: bool) -> list[str]:
    text = path.read_text()
    problems: list[str] = []

    if fix:
        text, n = fix_alts(text)
        if n:
            path.write_text(text)
            print(f"  fixed {n} image alt attributes")

    # 1 — alt text
    for alt, url in IMG.findall(text):
        if not alt.strip():
            problems.append(f"empty alt text: {url}")

    # 2 — front matter
    fm_match = re.match(r"^---\n([\s\S]*?)\n---\n", text)
    if not fm_match:
        problems.append("missing front matter")
    else:
        fm = fm_match.group(1)
        tags = [t for t in re.search(r"tags: (.*)", fm).group(1).split(",") if t.strip()] \
            if re.search(r"tags: (.*)", fm) else []
        if len(tags) > 4:
            problems.append(f"{len(tags)} tags — DEV allows 4")
        desc = re.search(r"description: (.*)", fm)
        if desc and len(desc.group(1)) > 190:
            problems.append("description longer than ~190 chars")
        if re.search(r"^cover_image:", fm, re.M):
            problems.append("cover_image is set — upload the cover in the editor instead")

    # 4 — liquid balance
    body = re.sub(r"```[\s\S]*?```", "", text)
    for open_tag, close_tag in (("details", "enddetails"), ("cta", "endcta"),
                                ("katex", "endkatex")):
        o = len(re.findall(r"\{%\s*" + open_tag + r"\b", body))
        c = len(re.findall(r"\{%\s*" + close_tag + r"\b", body))
        if o != c:
            problems.append(f"unbalanced liquid: {o} {open_tag} vs {c} {close_tag}")

    # 4b — images that will stack vertically on DEV
    infence = False
    for n, line in enumerate(text.split("\n"), 1):
        if line.strip().startswith("```"):
            infence = not infence
            continue
        if infence:
            continue
        if line.strip().startswith("|"):
            for cell in line.strip().strip("|").split("|"):
                if cell.count("![") > 1:
                    problems.append(
                        f"line {n}: {cell.count('![')} images in one table cell — "
                        f"they stack vertically on DEV, use one colour per cell")
        elif line.count("![") > 1:
            problems.append(
                f"line {n}: {line.count('![')} images on one line — DEV renders images as "
                f"blocks, so they stack. Use a one-row table (or a single wide bar for rules)")

    # 5 — triple backticks inside a table row
    for line in text.split("\n"):
        if line.strip().startswith("|") and "```" in line:
            problems.append(f"triple backticks inside a table row: {line[:60]}…")

    # 5b — site backlinks (informational, warns only when there are none)
    links = re.findall(r"https://colorfiind\.com[^)\s]*", text)
    if not links:
        problems.append("no site links — add at least one contextual link back to the site")
    elif len(links) > 12:
        problems.append(f"{len(links)} site links — that reads as spam, keep it under ~10")
    else:
        print(f"  ℹ {len(links)} site links ({len(set(links))} unique)")

    # 6 — placeholders
    for bad in ("TODO", "TKTK", "lorem ipsum", "your-cdn"):
        if bad.lower() in text.lower():
            problems.append(f"placeholder text left in file: {bad}")

    return problems


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--fix", action="store_true")
    ap.add_argument("files", nargs="*")
    args = ap.parse_args()

    files = [Path(f) for f in args.files] or sorted(ROOT.glob("[0-9]*.md"))
    failed = 0
    for f in files:
        print(f"\n{f.name}")
        problems = lint(f, args.fix)
        if problems:
            failed += 1
            for p in problems:
                print(f"  ✗ {p}")
        else:
            print("  ✓ clean")
    print()
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
