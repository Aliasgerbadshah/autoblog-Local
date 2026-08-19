---
title: "Automate Your Color Workflow: One Script, Any Palette → Tokens, Swatches & README Badges"
published: false
description: "One script turns four hex codes into CSS variables, Tailwind config, design tokens, a contrast report and README swatches. Python, Node and PHP versions."
tags: python, css, productivity, webdev
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,
     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

![deep indigo swatch #10002B in a divider rule](https://placehold.co/250x110/10002B/10002B.png)![deep indigo swatch #240046 in a divider rule](https://placehold.co/250x110/240046/240046.png)![vivid pink swatch #FF006E in a divider rule](https://placehold.co/250x110/FF006E/FF006E.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x110/00F5D4/00F5D4.png)

> 🎨 **Palette in play** — **Clay Flare Spectrum** · `#10002B` `#240046` `#FF006E` `#00F5D4`

Here is a thing I did by hand far too many times: open a palette, copy four hexes, paste them into a CSS file, retype them into `tailwind.config.js`, retype them *again* into a tokens file, then paste each one into a contrast checker, then build a swatch table for the README.

Twelve minutes. Per palette. With at least one typo.

So I wrote the script. This post is the script, in three languages, plus the CI job that keeps it honest.

![deep indigo swatch #10002B in a divider rule](https://placehold.co/250x12/10002B/10002B.png)![deep indigo swatch #240046 in a divider rule](https://placehold.co/250x12/240046/240046.png)![vivid pink swatch #FF006E in a divider rule](https://placehold.co/250x12/FF006E/FF006E.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x12/00F5D4/00F5D4.png)

## 🎯 What "done" looks like

```console
$ palette-kit all --name "Clay Flare Spectrum" \
    --slug clay-flare-spectrum \
    --colors 10002B 240046 FF006E 00F5D4

✔ theme.css              4 custom properties + semantic layer
✔ tailwind.colors.js     framework mirror
✔ tokens.json            W3C design tokens
✔ contrast.md            16-pair WCAG matrix
✔ swatches.md            markdown that renders colour anywhere
✔ cover.png              1000x420 article cover
```

Six artifacts, one command, zero retyping.

## 🧮 1 · The only math you need

Everything — contrast grades, "is this text readable", "should the label be black or white" — comes out of relative luminance:

{% katex %}
L = 0.2126R + 0.7152G + 0.0722B
{% endkatex %}

…where each channel is linearised, and contrast between two colors is:

{% katex %}
\text{ratio} = \frac{L_{\text{lighter}} + 0.05}{L_{\text{darker}} + 0.05}
{% endkatex %}

**`contrast.py`** ⌁ *35 lines, no dependencies*

```python
def _channel(c: int) -> float:
    s = c / 255
    return s / 12.92 if s <= 0.03928 else ((s + 0.055) / 1.055) ** 2.4


def luminance(hex_code: str) -> float:
    h = hex_code.lstrip("#")
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * _channel(r) + 0.7152 * _channel(g) + 0.0722 * _channel(b)


def contrast(a: str, b: str) -> float:
    la, lb = sorted((luminance(a), luminance(b)), reverse=True)
    return round((la + 0.05) / (lb + 0.05), 2)


def grade(ratio: float) -> str:
    return ("AAA" if ratio >= 7 else
            "AA" if ratio >= 4.5 else
            "AA Large" if ratio >= 3 else "fail")


def readable_ink(bg: str) -> str:
    """Black or white text for any background — the one-liner you keep."""
    return "#000000" if luminance(bg) > 0.42 else "#FFFFFF"
```

```console
>>> contrast("#FF006E", "#10002B"), grade(5.18)
(5.18, 'AA')
>>> readable_ink("#00F5D4")
'#000000'
```

## 🖨️ 2 · Emit every format from one list

**`palette_kit.py`** ⌁ *the generator core*

```python
NAMES = ["surface", "primary", "accent", "support"]


def css_vars(name, colors):
    body = "\n".join(f"  --color-{k}: {c};" for k, c in zip(NAMES, colors))
    return f"/* {name} */\n:root {{\n{body}\n}}"


def tailwind(colors):
    body = "\n".join(f"        {k}: '{c}'," for k, c in zip(NAMES, colors))
    return ("module.exports = {\n  theme: { extend: { colors: {\n"
            f"      palette: {{\n{body}\n      }},\n"
            "  } } },\n};")


def tokens(name, slug, colors):
    import json
    return json.dumps({
        "palette": {
            "$description": f"{name} ({slug})",
            **{k: {"$type": "color", "$value": c} for k, c in zip(NAMES, colors)},
        }
    }, indent=2)
```

## 🖼️ 3 · The swatch trick that renders everywhere

READMEs, DEV.to posts, GitHub issues, Notion — none of them let you set a background color. All of them render images. So generate the color *as* an image:

```python
SWATCH = "https://placehold.co/{w}x{h}/{hex}/{hex}.png"


def band(colors, w=210, h=110):
    """Touching images on one line = a seamless palette bar."""
    return "".join(
        f"![{c}]({SWATCH.format(w=w, h=h, hex=c.lstrip('#'))})" for c in colors
    )


def table(colors):
    rows = ["| Swatch | HEX | Contrast vs white | Grade |",
            "| :----: | :-- | :---------------- | :---- |"]
    for c in colors:
        r = contrast(c, "#FFFFFF")
        rows.append(
            f"| ![]({SWATCH.format(w=110, h=44, hex=c.lstrip('#'))}) "
            f"| `{c}` | {r}:1 | {grade(r)} |"
        )
    return "\n".join(rows)
```

Passing the same hex as background **and** text color hides placehold.co's default size label, leaving a clean block. Output:

![deep indigo swatch #10002B](https://placehold.co/210x110/10002B/10002B.png)![deep indigo swatch #240046](https://placehold.co/210x110/240046/240046.png)![vivid pink swatch #FF006E](https://placehold.co/210x110/FF006E/FF006E.png)![vivid teal swatch #00F5D4](https://placehold.co/210x110/00F5D4/00F5D4.png)

| Swatch | HEX | Contrast vs white | Grade |
| :----: | :-- | :---------------- | :---- |
| ![deep indigo swatch #10002B](https://placehold.co/110x44/10002B/10002B.png) | `#10002B` | 19.87:1 | AAA |
| ![deep indigo swatch #240046](https://placehold.co/110x44/240046/240046.png) | `#240046` | 18.05:1 | AAA |
| ![vivid pink swatch #FF006E](https://placehold.co/110x44/FF006E/FF006E.png) | `#FF006E` | 3.83:1 | AA Large |
| ![vivid teal swatch #00F5D4](https://placehold.co/110x44/00F5D4/00F5D4.png) | `#00F5D4` | 1.40:1 | fail |

{% details 🏷️ Prefer shields.io badges? Here's that variant %}

```python
BADGE = "https://img.shields.io/badge/{label}-{hex}?style=for-the-badge&labelColor={hex}"

def badges(colors):
    return " ".join(
        f"![{c}]({BADGE.format(label=c.lstrip('#'), hex=c.lstrip('#'))})"
        for c in colors
    )
```

Badges give you the hex printed on the chip; `placehold.co` gives you a clean block. Use badges in READMEs, blocks in articles.

{% enddetails %}

![deep indigo swatch #10002B in a divider rule](https://placehold.co/250x12/10002B/10002B.png)![deep indigo swatch #240046 in a divider rule](https://placehold.co/250x12/240046/240046.png)![vivid pink swatch #FF006E in a divider rule](https://placehold.co/250x12/FF006E/FF006E.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x12/00F5D4/00F5D4.png)

## 🟨 4 · The Node version

Same idea, drop it in any JS project as `scripts/palette.mjs`:

```js
#!/usr/bin/env node
import { writeFileSync } from 'node:fs';

const NAMES = ['surface', 'primary', 'accent', 'support'];
const hex = (c) => '#' + c.replace('#', '').toUpperCase();
const colors = process.argv.slice(2).map(hex);

const lum = (c) => {
  const [r, g, b] = c.slice(1).match(/../g).map((h) => {
    const v = parseInt(h, 16) / 255;
    return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

const contrast = (a, b) => {
  const [x, y] = [lum(a), lum(b)].sort((m, n) => n - m);
  return +((x + 0.05) / (y + 0.05)).toFixed(2);
};

const pairs = NAMES.map((n, i) => [n, colors[i]]).filter(([, v]) => v);

writeFileSync('theme.css',
  `:root {\n${pairs.map(([n, v]) => `  --color-${n}: ${v};`).join('\n')}\n}\n`);

writeFileSync('tokens.json', JSON.stringify(
  Object.fromEntries(pairs.map(([n, v]) => [n, { $type: 'color', $value: v }])), null, 2));

writeFileSync('swatches.md',
  pairs.map(([, v]) =>
    `![${v}](https://placehold.co/210x110/${v.slice(1)}/${v.slice(1)}.png)`).join('') + '\n');

console.table(pairs.map(([name, value]) => ({
  name, value, onWhite: contrast(value, '#FFFFFF'), onBlack: contrast(value, '#000000'),
})));
```

```console
$ node scripts/palette.mjs 10002B 240046 FF006E 00F5D4
┌─────────┬───────────┬───────────┬──────────┬─────────┐
│ (index) │   name    │   value   │ onWhite  │ onBlack │
├─────────┼───────────┼───────────┼──────────┼─────────┤
│    0    │ 'surface' │ '#10002B' │  19.87   │  1.06   │
│    1    │ 'primary' │ '#240046' │  18.05   │  1.16   │
│    2    │ 'accent'  │ '#FF006E' │   3.83   │  5.48   │
│    3    │ 'support' │ '#00F5D4' │   1.40   │  15.01  │
└─────────┴───────────┴───────────┴──────────┴─────────┘
```

That `onWhite` / `onBlack` split is the whole light-vs-dark-mode decision in one glance.

## 🐘 5 · The PHP version

For the WordPress / Laravel / plain-PHP crowd, and for build steps that already run on a LAMP box:

```php
<?php
declare(strict_types=1);

function luminance(string $hex): float {
    $hex = ltrim($hex, '#');
    [$r, $g, $b] = array_map(
        static function (string $part): float {
            $c = hexdec($part) / 255;
            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        },
        str_split($hex, 2)
    );
    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function contrast(string $a, string $b): float {
    $l = [luminance($a), luminance($b)];
    rsort($l);
    return round(($l[0] + 0.05) / ($l[1] + 0.05), 2);
}

function cssVars(string $name, array $colors): string {
    $keys = ['surface', 'primary', 'accent', 'support'];
    $out  = "/* {$name} */\n:root {\n";
    foreach ($colors as $i => $hex) {
        $out .= sprintf("  --color-%s: %s;\n", $keys[$i] ?? "extra-{$i}", $hex);
    }
    return $out . "}\n";
}

$palette = ['#10002B', '#240046', '#FF006E', '#00F5D4'];
file_put_contents('theme.css', cssVars('Clay Flare Spectrum', $palette));
printf("accent on surface: %.2f:1\n", contrast('#FF006E', '#10002B'));
```

```console
$ php palette.php
accent on surface: 5.18:1
```

## 🤖 6 · Keep it honest in CI

Colors rot. A designer tweaks one token, a junior hardcodes a hex, and six weeks later your "accessible" theme is not. Fail the build instead:

```yaml
# .github/workflows/contrast.yml
name: contrast

on: [push, pull_request]

jobs:
  audit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with:
          python-version: '3.12'
      - name: Audit theme contrast
        run: python3 tools/palette_kit.py contrast --from-json data/palettes.json
      - name: Fail if any text pair is below AA
        run: python3 tools/audit.py --min 4.5 --pairs data/text-pairs.json
```

```diff
  # data/text-pairs.json — the pairs you actually ship
  [
    { "fg": "#E7E9EE", "bg": "#10002B", "use": "body" },
    { "fg": "#00F5D4", "bg": "#10002B", "use": "links" },
-   { "fg": "#7A5AA8", "bg": "#240046", "use": "labels" },
+   { "fg": "#B9A6E0", "bg": "#240046", "use": "labels" }
  ]
```

*(`#7A5AA8` on `#240046` is 3.31:1 — a fail for label text. Lightening the violet to `#B9A6E0` takes it to 8.25:1.)*

## 🧾 The checklist

1. Store the palette **once**, in JSON, with its source URL next to it.
2. Generate CSS, Tailwind and tokens — never retype a hex.
3. Generate swatch markdown too; your README should show the colors, not list them.
4. Compute `onWhite` / `onBlack` for every color to decide light vs dark mode roles.
5. Put a contrast assertion in CI with the pairs you really ship.
6. Regenerate, don't edit. The script is the source of truth; the artifacts are build output.

The full generator used for this series — bands, tables, contrast matrices and 1000×420 cover art — lives in the repo linked below. Point it at any palette and it prints a post's worth of markdown.


*What's the most tedious part of your color workflow? Tell me and I'll add a flag for it.* 👇
