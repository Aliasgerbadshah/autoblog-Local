#!/usr/bin/env python3
"""
format_md.py — normalise spacing and section rhythm in the DEV.to articles.

    python3 tools/format_md.py            # format every article
    python3 tools/format_md.py 03-*.md    # or specific files

What it does (idempotent — safe to run repeatedly):

  * exactly one blank line between blocks, never three or more
  * a blank line before and after every heading, table, list, quote,
    code fence and standalone image
  * before every `##` topic heading: a breathing spacer plus a full-width
    colour divider bar, cycling through the article's own palette
  * with `--airy`: an extra blank line between consecutive paragraphs too
  * no trailing whitespace, file ends with a single newline

The spacer is a paragraph containing `&nbsp;` — DEV renders it as an empty
line, which is the only reliable way to add vertical air in its Markdown.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from palette_kit import color_name, hsl, norm  # noqa: E402

ROOT = Path(__file__).resolve().parent.parent

SPACER = "&nbsp;"
DIVIDER = re.compile(r"^!\[[^\]]*\]\(https://placehold\.co/1000x\d+/[0-9A-Fa-f]{6}/[0-9A-Fa-f]{6}\.png\)$")
SWATCH_HEX = re.compile(r"placehold\.co/\d+x\d+/([0-9A-Fa-f]{6})/")
BLOCK_START = re.compile(r"^(#{1,6} |\||> |[-*] |\d+\. |!\[|```|<)")


def palette_of(text: str) -> list[str]:
    """The article's own colours, most saturated first, de-duplicated."""
    seen: list[str] = []
    for hexcode in SWATCH_HEX.findall(text):
        h = norm(hexcode)
        if h not in seen:
            seen.append(h)
    vivid = [h for h in seen if hsl(h)[1] >= 45 and 18 <= hsl(h)[2] <= 82]
    return vivid or seen or ["00F5D4"]


def divider_for(hexcode: str) -> str:
    return (f"![Section divider bar in {color_name(hexcode)}]"
            f"(https://placehold.co/1000x8/{hexcode}/{hexcode}.png)")


def add_paragraph_air(body: str) -> str:
    """Insert a spacer between two consecutive prose paragraphs."""
    blocks: list[tuple[str, str]] = []          # (kind, text)
    buf: list[str] = []
    infence = False
    for line in body.split("\n"):
        if line.strip().startswith("```"):
            infence = not infence
            buf.append(line)
            if not infence:
                blocks.append(("code", "\n".join(buf)))
                buf = []
            continue
        if infence:
            buf.append(line)
            continue
        if not line.strip():
            if buf:
                text = "\n".join(buf)
                kind = "prose" if not BLOCK_START.match(buf[0].strip()) else "block"
                if buf[0].strip() == SPACER:
                    kind = "spacer"
                blocks.append((kind, text))
                buf = []
            continue
        buf.append(line)
    if buf:
        kind = "prose" if not BLOCK_START.match(buf[0].strip()) else "block"
        blocks.append((kind, "\n".join(buf)))

    out: list[str] = []
    for i, (kind, text) in enumerate(blocks):
        if (kind == "prose" and i and blocks[i - 1][0] == "prose"):
            out.append(SPACER)
        out.append(text)
    return "\n\n".join(out)


def format_text(text: str, airy: bool = False) -> str:
    palette = palette_of(text)

    # split off front matter so it is never touched
    fm = ""
    m = re.match(r"^---\n[\s\S]*?\n---\n", text)
    if m:
        fm, text = m.group(0), text[m.end():]

    # strip previous spacers and dividers so the pass is idempotent
    kept: list[str] = []
    infence = False
    for line in text.split("\n"):
        if line.strip().startswith("```"):
            infence = not infence
            kept.append(line)
            continue
        if not infence and (line.strip() == SPACER or DIVIDER.match(line.strip())):
            continue
        kept.append(line.rstrip())

    out: list[str] = []
    infence = False
    div = 0

    def blank() -> None:
        if out and out[-1] != "":
            out.append("")

    for line in kept:
        stripped = line.strip()

        if stripped.startswith("```"):
            if not infence:
                blank()
            out.append(line)
            infence = not infence
            if not infence:
                out.append("")
            continue

        if infence:
            out.append(line)
            continue

        if not stripped:
            blank()
            continue

        if stripped.startswith("## "):                 # topic heading
            blank()
            out.append(SPACER)
            out.append("")
            out.append(divider_for(palette[div % len(palette)]))
            div += 1
            out.append("")
            out.append(stripped)
            out.append("")
            continue

        if re.match(r"^#{1,6} ", stripped):             # any other heading
            blank()
            out.append(stripped)
            out.append("")
            continue

        if BLOCK_START.match(stripped):
            if out and out[-1] != "" and not BLOCK_START.match(out[-1].strip()):
                blank()
            out.append(line)
            continue

        if out and out[-1] != "" and BLOCK_START.match(out[-1].strip()) \
                and not out[-1].strip().startswith(("|", "- ", "* ", "> ")) \
                and not re.match(r"^\d+\. ", out[-1].strip()):
            blank()
        out.append(line)

    body = "\n".join(out)
    body = re.sub(r"\n{3,}", "\n\n", body)
    if airy:
        body = add_paragraph_air(body)
    body = body.strip("\n") + "\n"
    return fm + "\n" + body


def main() -> int:
    args = [a for a in sys.argv[1:] if not a.startswith("-")]
    airy = "--airy" in sys.argv                 # extra blank line between paragraphs
    targets = [Path(a) for a in args] or sorted(ROOT.glob("[0-9]*.md"))
    for f in targets:
        before = f.read_text()
        after = format_text(before, airy=airy)
        f.write_text(after)
        n_div = after.count("placehold.co/1000x8/")
        print(f"✔ {f.name}: {n_div} section dividers, "
              f"{after.count(SPACER)} spacers")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
