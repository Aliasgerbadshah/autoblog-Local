#!/usr/bin/env python3
"""
palette_kit.py — turn a ColorFiind palette into DEV-ready assets.

Given a palette (name + HEX codes) it can emit:

  swatches   Markdown swatch band + table that renders colour on DEV.to
             (uses placehold.co images, no hosting required)
  css        :root custom properties
  tailwind   tailwind.config.js theme.extend.colors block
  tokens     design-tokens JSON
  contrast   WCAG contrast matrix for every pair
  cover      1000x420 PNG cover image for the DEV front matter (needs ImageMagick)
  strip      1200x260 PNG palette strip (needs ImageMagick)

Examples
--------
  python3 palette_kit.py swatches --name "Neon Diner Harmony" \
      --slug neon-diner-harmony --colors 0F172A 00F5D4 F15BB5 9B5DE5

  python3 palette_kit.py all --from-json ../data/palettes.json --out ../assets

Everything is stdlib only; PNG generation shells out to ImageMagick `convert`.
"""

from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
from pathlib import Path

PLACEHOLDER = "https://placehold.co/{w}x{h}/{hex}/{hex}.png"

# ---------------------------------------------------------------- colour math


def norm(hex_code: str) -> str:
    """'#3c0d4f' / '3c0d4f' -> '3C0D4F'."""
    h = hex_code.strip().lstrip("#").upper()
    if len(h) == 3:
        h = "".join(c * 2 for c in h)
    if len(h) != 6 or any(c not in "0123456789ABCDEF" for c in h):
        raise ValueError(f"not a hex colour: {hex_code!r}")
    return h


def rgb(hex_code: str) -> tuple[int, int, int]:
    h = norm(hex_code)
    return int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)


def relative_luminance(hex_code: str) -> float:
    def channel(c: int) -> float:
        s = c / 255
        return s / 12.92 if s <= 0.03928 else ((s + 0.055) / 1.055) ** 2.4

    r, g, b = (channel(c) for c in rgb(hex_code))
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contrast(a: str, b: str) -> float:
    la, lb = relative_luminance(a), relative_luminance(b)
    hi, lo = max(la, lb), min(la, lb)
    return round((hi + 0.05) / (lo + 0.05), 2)


def wcag_grade(ratio: float) -> str:
    if ratio >= 7:
        return "AAA"
    if ratio >= 4.5:
        return "AA"
    if ratio >= 3:
        return "AA Large"
    return "fail"


def readable_ink(hex_code: str) -> str:
    """Black or white text for a given background."""
    return "000000" if relative_luminance(hex_code) > 0.42 else "FFFFFF"


def hsl(hex_code: str) -> tuple[int, int, int]:
    r, g, b = (c / 255 for c in rgb(hex_code))
    mx, mn = max(r, g, b), min(r, g, b)
    l = (mx + mn) / 2
    if mx == mn:
        return 0, 0, round(l * 100)
    d = mx - mn
    s = d / (2 - mx - mn) if l > 0.5 else d / (mx + mn)
    if mx == r:
        h = ((g - b) / d) % 6
    elif mx == g:
        h = (b - r) / d + 2
    else:
        h = (r - g) / d + 4
    return round(h * 60), round(s * 100), round(l * 100)


HUES = [
    (15, "red"), (45, "orange"), (70, "yellow"), (95, "lime"), (150, "green"),
    (190, "teal"), (210, "cyan"), (250, "blue"), (275, "indigo"),
    (300, "purple"), (330, "magenta"), (346, "pink"), (361, "red"),
]


def color_name(hex_code: str) -> str:
    """Human-readable description of a colour, for image alt text."""
    h, s, l = hsl(hex_code)
    if l < 8:
        return "near-black"
    if l > 92 and s < 30:
        return "off-white"
    if s < 14:
        if l < 35:
            return "dark grey"
        if l < 65:
            return "mid grey"
        if l < 92:
            return "light grey"
        return "off-white"

    hue = next(name for edge, name in HUES if h < edge)

    if l < 30:                                   # named dark colours read better
        if hue in ("pink", "magenta", "red"):
            return "burgundy"
        if hue in ("blue", "indigo"):
            return "navy" if hue == "blue" else "deep indigo"
        if hue in ("green", "teal"):
            return f"deep {hue}"
        return f"deep {hue}"
    if l > 90 and s < 45:
        return f"pale {hue}"
    if l < 45:
        return f"dark {hue}"
    if l > 88:
        return f"pale {hue}"
    if l > 72:
        return f"light {hue}"
    if s > 82:
        return f"vivid {hue}"
    if s < 30:
        return f"muted {hue}"
    return hue


def alt_text(hex_code: str, palette: str = "") -> str:
    """Alt text DEV's accessibility linter is happy with."""
    h = norm(hex_code)
    label = f"{color_name(h)} swatch #{h}"
    return f"{label} from {palette}" if palette else label


def slugify(name: str) -> str:
    return "-".join(
        "".join(ch.lower() if ch.isalnum() else " " for ch in name).split()
    )


# ---------------------------------------------------------------- generators


def band(colors: list[str], w: int = 120, h: int = 56, alt: str = "") -> str:
    """A palette band that survives DEV.to.

    DEV renders article images as block elements, so images on one line stack
    vertically. A one-row table forces them side by side and gives you the hex
    codes as a header for free.
    """
    hexes = [norm(c) for c in colors]
    head = "| " + " | ".join(f"`#{c}`" for c in hexes) + " |"
    sep = "| " + " | ".join([":--:"] * len(hexes)) + " |"
    body = "| " + " | ".join(
        f"![{alt_text(c)}]({PLACEHOLDER.format(w=w, h=h, hex=c)})" for c in hexes
    ) + " |"
    return f"{head}\n{sep}\n{body}"


def divider(colors: list[str], w: int = 1000, h: int = 10) -> str:
    """Full-width coloured rule — one image, never several."""
    best = max((norm(c) for c in colors), key=lambda c: hsl(c)[1])
    return (f"![Section divider bar in {color_name(best)}]"
            f"({PLACEHOLDER.format(w=w, h=h, hex=best)})")


ROLES = ["Base / background", "Primary", "Accent", "Support"]


def swatch_table(colors: list[str]) -> str:
    rows = [
        "| Swatch | HEX | RGB | HSL | Contrast vs `#FFFFFF` | Suggested role |",
        "| :----: | :-- | :-- | :-- | :--------------------- | :------------- |",
    ]
    for i, c in enumerate(colors):
        h = norm(c)
        r, g, b = rgb(h)
        hh, ss, ll = hsl(h)
        ratio = contrast(h, "FFFFFF")
        rows.append(
            f"| ![{alt_text(h)}]({PLACEHOLDER.format(w=110, h=44, hex=h)}) | `#{h}` | "
            f"`{r}, {g}, {b}` | `{hh}° {ss}% {ll}%` | **{ratio}:1** "
            f"({wcag_grade(ratio)}) | {ROLES[i] if i < len(ROLES) else 'Extra'} |"
        )
    return "\n".join(rows)


def css_vars(name: str, colors: list[str]) -> str:
    lines = [f"/* {name} — colorfiind.com */", ":root {"]
    for i, c in enumerate(colors, start=1):
        lines.append(f"  --palette-color-{i}: #{norm(c)};")
    lines.append("}")
    return "\n".join(lines)


def semantic_css(name: str, colors: list[str]) -> str:
    keys = ["surface", "primary", "accent", "support"]
    lines = [f"/* {name} — semantic layer */", ":root {"]
    for key, c in zip(keys, colors):
        lines.append(f"  --color-{key}: #{norm(c)};")
    ink = readable_ink(colors[0])
    lines.append(f"  --color-ink: #{ink};")
    lines.append("}")
    return "\n".join(lines)


def tailwind(name: str, colors: list[str]) -> str:
    keys = ["surface", "primary", "accent", "support"]
    body = "\n".join(
        f"          {k}: '#{norm(c)}'," for k, c in zip(keys, colors)
    )
    return (
        "// tailwind.config.js — %s\n"
        "module.exports = {\n"
        "  theme: {\n"
        "    extend: {\n"
        "      colors: {\n"
        "        palette: {\n%s\n        },\n"
        "      },\n"
        "    },\n"
        "  },\n"
        "};" % (name, body)
    )


def tokens(name: str, slug: str, colors: list[str]) -> str:
    keys = ["surface", "primary", "accent", "support"]
    data = {
        "$schema": "https://design-tokens.github.io/community-group/format/",
        "palette": {
            "$description": f"{name} — https://colorfiind.com/palette/{slug}",
            **{
                k: {"$type": "color", "$value": f"#{norm(c)}"}
                for k, c in zip(keys, colors)
            },
        },
    }
    return json.dumps(data, indent=2)


def contrast_matrix(colors: list[str]) -> str:
    head = "| ↓ text / bg → | " + " | ".join(f"`#{norm(c)}`" for c in colors) + " |"
    sep = "| :-- |" + " :--: |" * len(colors)
    rows = [head, sep]
    for a in colors:
        cells = []
        for b in colors:
            if norm(a) == norm(b):
                cells.append("—")
            else:
                r = contrast(a, b)
                mark = "✅" if r >= 4.5 else ("🟡" if r >= 3 else "🚫")
                cells.append(f"{mark} {r}")
        rows.append(f"| `#{norm(a)}` | " + " | ".join(cells) + " |")
    return "\n".join(rows)


# ---------------------------------------------------------------- PNG output


def _im(args: list[str]) -> None:
    subprocess.run(["convert", *args], check=True)


def _band_args(colors: list[str], width: int, height: int,
               font: str, pointsize: int, label_offset: int) -> list[str]:
    """ImageMagick args that build one horizontal band of labelled swatches."""
    n = len(colors)
    cw = width // n
    args: list[str] = []
    for i, c in enumerate(colors):
        h = norm(c)
        w = width - cw * (n - 1) if i == n - 1 else cw
        args += [
            "(", "-size", f"{w}x{height}", f"xc:#{h}",
            "-font", font, "-pointsize", str(pointsize),
            "-fill", f"#{readable_ink(h)}", "-gravity", "south",
            "-annotate", f"+0+{label_offset}", f"#{h}", ")",
        ]
    args += ["+append"]
    return args


def strip_png(name: str, colors: list[str], out: Path,
              width: int = 1200, height: int = 260) -> Path:
    out.parent.mkdir(parents=True, exist_ok=True)
    args = _band_args(colors, width, height, "DejaVu-Sans-Bold", 34, 28)
    _im([*args, str(out)])
    return out


def cover_png(name: str, slug: str, colors: list[str], out: Path,
              kicker: str = "colorfiind.com", subtitle: str = "") -> Path:
    """1000x420 DEV cover: palette band on top, dark caption bar below."""
    out.parent.mkdir(parents=True, exist_ok=True)
    W, H, band_h = 1000, 420, 250
    dark = "#0B0F1A"
    args = _band_args(colors, W, band_h, "DejaVu-Sans-Mono", 26, 18)
    args += [
        "-background", dark, "-gravity", "north", "-extent", f"{W}x{H}",
        "-font", "DejaVu-Sans-Bold", "-pointsize", "44", "-fill", "#FFFFFF",
        "-gravity", "northwest", "-annotate", f"+44+{band_h + 46}", name,
        "-font", "DejaVu-Sans", "-pointsize", "23", "-fill", "#8FA1B3",
        "-annotate", f"+46+{band_h + 112}",
        subtitle or f"{kicker}/palette/{slug}",
        str(out),
    ]
    _im(args)
    return out


# ---------------------------------------------------------------- cli


def render_markdown(name: str, slug: str, colors: list[str]) -> str:
    parts = [
        f"### {name}",
        "",
        band(colors),
        "",
        divider(colors),
        "",
        swatch_table(colors),
        "",
        "```css",
        css_vars(name, colors),
        "```",
        "",
    ]
    return "\n".join(parts)


def load_palettes(path: Path) -> list[dict]:
    data = json.loads(path.read_text())
    return data["palettes"] if isinstance(data, dict) else data


def main() -> int:
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("command", choices=[
        "swatches", "css", "tailwind", "tokens", "contrast",
        "cover", "strip", "all",
    ])
    p.add_argument("--name", default="Untitled Palette")
    p.add_argument("--slug", default="")
    p.add_argument("--colors", nargs="*", default=[])
    p.add_argument("--from-json", default="")
    p.add_argument("--out", default="assets")
    args = p.parse_args()

    out_dir = Path(args.out)

    if args.from_json:
        palettes = load_palettes(Path(args.from_json))
    else:
        if not args.colors:
            p.error("give --colors 0F172A 00F5D4 ... or --from-json file.json")
        palettes = [{
            "name": args.name,
            "slug": args.slug or slugify(args.name),
            "colors": args.colors,
        }]

    for pal in palettes:
        name, slug, colors = pal["name"], pal["slug"], pal["colors"]
        cmd = args.command
        if cmd in ("swatches", "all"):
            print(render_markdown(name, slug, colors))
        if cmd == "css":
            print(css_vars(name, colors), "\n")
            print(semantic_css(name, colors), "\n")
        if cmd == "tailwind":
            print(tailwind(name, colors), "\n")
        if cmd == "tokens":
            print(tokens(name, slug, colors), "\n")
        if cmd == "contrast":
            print(f"### {name}\n")
            print(contrast_matrix(colors), "\n")
        if cmd in ("cover", "all"):
            f = cover_png(name, slug, colors, out_dir / f"cover-{slug}.png")
            print(f"# wrote {f}", file=sys.stderr)
        if cmd in ("strip", "all"):
            f = strip_png(name, colors, out_dir / f"strip-{slug}.png")
            print(f"# wrote {f}", file=sys.stderr)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
