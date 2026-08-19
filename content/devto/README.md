# ColorFiind → DEV.to blog pack

Ready-to-publish DEV.to articles about [colorfiind.com](https://colorfiind.com), written in DEV-flavoured
Markdown with **visible colour swatches**, a consistent visual theme, and code blocks styled to look
different from one another.

```
content/devto/
├── 00-swatch-deck-theme-kit.md      ← the reusable theme (reference sheet)
├── 01-choose-a-color-palette.md  ← how to choose a palette that survives production
├── 02-hex-to-design-tokens.md       ← palette → CSS vars / Tailwind / tokens
├── 03-seasonal-palettes-ui-themes.md← soft summer vs deep winter theming
├── 04-neon-dark-mode-glow-ui.md     ← neon palettes without eye strain
├── 05-automate-palette-workflow.md  ← one script → every format (py/node/php)
├── assets/                          ← 1000×420 covers + 1200×260 strips (PNG)
├── COVER-PROMPTS.md                 ← image-generation prompt for each post
├── devto-posts.zip                  ← all .md files, ready to download
├── data/palettes.json               ← the palettes used across the posts
├── preview/                         ← generated static preview (open index.html)
├── index.html                       ← redirect into the preview
└── tools/palette_kit.py             ← generator for swatches, tokens, contrast, covers
```

---

## 1. Preview locally

```bash
python3 content/devto/tools/build_preview.py            # rebuild after editing any .md
python3 -m http.server 8080 --bind 0.0.0.0 --directory content/devto
# open http://localhost:8080/
```

The preview is **pre-rendered static HTML** — no JavaScript, no CDN, nothing to install. Only the
colour swatches need internet (they load from placehold.co).

It shows the front matter as a DEV-style header, simulates the Liquid tags (`details`, `cta`, `katex`,
`embed`), highlights every code block and labels it with its language, and has a light/dark toggle.
Hex codes inside code blocks even get a little colour chip.

## 2. Publish an article

1. Open <https://dev.to/new>.
2. Switch to the **Markdown** editor (Settings → Customization → "rich + markdown" if you prefer split fields).
3. Paste the whole `.md` file, front matter included.
4. Create your own **1000 × 420** cover (see `COVER-PROMPTS.md` for a ready-made generation prompt per
   post), drag it into the editor, and paste the returned URL into a new `cover_image:` line.
5. Flip `published: false` → `true`.

Front matter used in every file:

```yaml
---
title: ...
published: false        # flip when you're happy with the preview
description: ...        # 120-160 chars, shows in feed + OG card
tags: css, webdev, ...  # max 4, comma separated, no '#'
# cover_image: ...     ← intentionally absent: upload your own 1000x420 in the editor
# canonical_url: https://colorfiind.com/blog/...   ← add if you cross-post
# series: "Color for Developers"                   ← optional, links the 5 posts
---
```

> Publishing all five? Add `series: "Color for Developers"` to each one and DEV will build the
> series navigation automatically.

## 3. How the colours stay visible

DEV.to sanitizes `<style>` blocks, `style=""` attributes and `<script>`, so colour cannot come from CSS.
Every swatch in these posts is therefore an **image**:

```markdown
![#00F5D4](https://placehold.co/210x110/00F5D4/00F5D4.png)
```

`placehold.co/{W}x{H}/{bg}/{textColor}.png` — passing the same hex twice hides the default size label and
leaves a solid block. Four of them on **one line with no spaces** butt together into a seamless palette band.
Thin versions (`250x12`) are used as coloured section rules instead of `---`.

Fallbacks if you ever need them: `dummyimage.com/210x110/00f5d4/00f5d4.png`, or shields.io badges
(`img.shields.io/badge/00F5D4-00F5D4?style=for-the-badge&labelColor=00F5D4`).

## 4. How the code blocks get different looks

DEV highlights fenced blocks with Rouge, so the *language tag* is the styling lever. The posts
deliberately rotate through them, each introduced by a bold monospace "tab" line:

| Fence | Look | Used for |
| :-- | :-- | :-- |
| `css` / `scss` | property/value colouring | theme definitions |
| `js` / `ts` | keyword + string colouring | logic, configs |
| `diff` | red/green line highlighting | before → after refactors |
| `console` | flat, terminal-ish | commands and output |
| `json` / `jsonc` | key/value colouring | design tokens |
| `yaml` | front matter, CI | GitHub Actions |
| `php` / `python` | server-side variants | the generator scripts |

Long or optional code is folded into `{% details %}` collapsibles so the article keeps its rhythm.

## 5. Regenerate assets or add a palette

Add an entry to `data/palettes.json`, then:

```bash
cd content/devto/tools

# covers + strips for every palette
python3 palette_kit.py all --from-json ../data/palettes.json --out ../assets

# markdown for one palette (band + table + CSS vars)
python3 palette_kit.py swatches --name "Neon Diner Harmony" \
  --slug neon-diner-harmony --colors 0F172A 00F5D4 F15BB5 9B5DE5

# other outputs
python3 palette_kit.py css      --colors 0F172A 00F5D4 F15BB5 9B5DE5
python3 palette_kit.py tailwind --colors 0F172A 00F5D4 F15BB5 9B5DE5
python3 palette_kit.py tokens   --slug neon-diner-harmony --colors 0F172A 00F5D4 F15BB5 9B5DE5
python3 palette_kit.py contrast --colors 0F172A 00F5D4 F15BB5 9B5DE5
```

Requirements: Python 3.9+ (stdlib only) and ImageMagick (`convert`) for the PNGs.

## 6. Fact-checking note

Every hex code, palette name and site section referenced in the posts was taken from live ColorFiind
pages (palette, category and season URLs). Every contrast ratio quoted in the articles was computed
with `palette_kit.py` using the WCAG relative-luminance formula — if you edit a colour, re-run
`palette_kit.py contrast` and update the numbers.
