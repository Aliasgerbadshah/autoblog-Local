# HOW TO USE THIS PACK

Five ready-to-publish DEV.to articles about ColorFiind, plus the tooling that made them.
Three things you can do with it — pick the one you need.

---

## Getting the files

Three ways, use whichever works:

| Where | How |
| :-- | :-- |
| **Preview server** | start it (below) and open **`/downloads`** — every `.md` plus the ZIP, one click each |
| **GitHub** | branch `arena/01a01974-autoblog-local` → `content/devto/` → open a file → **Download raw** |
| **Local disk** | the files are simply in `content/devto/`; `devto-posts.zip` bundles them |

---

## A. Read / review the posts (2 minutes)

**Option 1 — the preview app (recommended).** It renders the Markdown exactly the way DEV will:
cover image, tags, colour swatches, highlighted code blocks, collapsibles, CTA buttons.

```bash
python3 content/devto/tools/build_preview.py   # only needed after editing a .md
python3 content/devto/tools/serve.py           # preview + downloads on :8080
```

Open <http://localhost:8080/> → pick an article in the left sidebar → 🌗 toggles light/dark.
The pages are plain pre-built HTML (no JS, no CDN), so they render even on a locked-down network —
only the swatch images need internet.

**Option 2 — just open the `.md` files** in VS Code (`Ctrl/Cmd + Shift + V` for the built-in preview)
or straight on GitHub. Swatches render there too, because they are images.

---

## B. Publish one to DEV.to (5 minutes per post)

1. Open <https://dev.to/new>.
2. Make sure you are in the **Markdown** editor
   (Settings → Customization → Editor version → "rich + markdown" if you want separate title/tag fields).
3. Open e.g. `01-choose-a-color-palette.md`, select **everything including the `---` front matter**, paste it in.
4. **Cover image:** there is deliberately **no `cover_image:`** in the front matter — generate or design
   your own 1000×420 image (prompts for every post are in `COVER-PROMPTS.md`), drag it into the DEV
   editor, and paste the URL it returns into a new `cover_image:` line.

5. Click **Preview** in DEV and check the swatch bands and code blocks.
6. Change `published: false` → `published: true`, hit **Publish**.

**Publishing all five as a series:** add this line to each front matter before publishing —

```yaml
series: "Color for Developers"
```

DEV then shows "Part 1 of 5" navigation on every post automatically.

**Cross-posting?** If these also live on your own site, add
`canonical_url: https://your-site.com/the-original` so Google credits the original.

**Suggested order & spacing:** 01 → 02 → 03 → 04 → 05, one every 3–4 days.

---

## C. Make a new post for a different palette

1. Pick a palette on <https://colorfiind.com> and copy its 4 HEX codes and slug.
2. Add it to `data/palettes.json`:

   ```json
   { "slug": "topaz-studio-moment", "name": "Topaz Studio Moment",
     "colors": ["#081C09", "#00E6FF", "#FF24FF", "#FFF238"], "group": "neon" }
   ```

3. Generate the visuals and the Markdown:

   ```bash
   cd content/devto/tools

   # 1000x420 cover + 1200x260 strip into ../assets
   python3 palette_kit.py all --from-json ../data/palettes.json --out ../assets

   # or just the markdown for one palette (band + swatch table + CSS vars)
   python3 palette_kit.py swatches --name "Topaz Studio Moment" \
     --slug topaz-studio-moment --colors 081C09 00E6FF FF24FF FFF238
   ```

4. Copy `00-swatch-deck-theme-kit.md` as your skeleton, paste the generated blocks in, write the words.

Other generator commands:

| Command | Output |
| :-- | :-- |
| `palette_kit.py css --colors ...` | `:root` custom properties + semantic layer |
| `palette_kit.py tailwind --colors ...` | `tailwind.config.js` colour block |
| `palette_kit.py tokens --colors ...` | W3C design-tokens JSON |
| `palette_kit.py contrast --colors ...` | full WCAG contrast matrix (✅ / 🟡 / 🚫) |
| `palette_kit.py cover --colors ...` | 1000×420 PNG cover |
| `palette_kit.py strip --colors ...` | 1200×260 PNG palette strip |

Needs Python 3.9+ (stdlib only) and ImageMagick for the PNG commands.

---

## Backlinks to your site

Each post carries a handful of contextual links to **colorfiind.com** — placed where they actually help
the reader, never as a wall of links:

| Placement | Example |
| :-- | :-- |
| Palette credit under the opening swatches | `[Maple Drift](https://colorfiind.com/palette/maple-drift)` |
| Named palettes inside comparison tables | `[Neon Diner Harmony](…/palette/neon-diner-harmony)` |
| Category / season references in prose | `[neon category](…/category/neon)` |
| One `{% cta %}` button before the sign-off | *Browse 34,000+ colour palettes* |

`data/links.json` holds the hex→palette-URL map, so the same palette always links to the same page.
The linter reports how many links each post has and complains if a post has none — or more than ~10,
which starts to read as spam to both readers and DEV's moderation.

## Formatting & spacing

```bash
python3 content/devto/tools/format_md.py          # section dividers + tidy spacing
python3 content/devto/tools/format_md.py --airy   # …plus a blank line between paragraphs
```

`format_md.py` enforces the reading rhythm: one blank line between every block, and before each `##`
topic a spacer plus a full-width colour bar that cycles through the article's own palette. Run it with
`--airy` for extra space between paragraphs, or without to tighten things back up — it's idempotent, so
you can flip between the two freely.

> DEV's Markdown has no way to set margins, so the spacer is a paragraph containing `&nbsp;`.
> That is the one reliable trick for adding vertical air.

## Before you paste: run the linter

```bash
python3 content/devto/tools/lint_md.py          # report problems
python3 content/devto/tools/lint_md.py --fix    # auto-add missing image alt text
```

It catches exactly what DEV's editor complains about: empty `![]()` alt text, more than 4 tags,
over-long descriptions, unbalanced Liquid tags, and a stray `cover_image:` you forgot to remove.

## Rules to keep when you edit the posts

- **Never hand-edit a contrast number.** Change a colour → re-run
  `python3 palette_kit.py contrast --colors ...` and paste the new value.
- **Swatches must stay images.** DEV strips `<style>` and `style=""`; the colour comes from
  `https://placehold.co/210x110/HEX/HEX.png` (same hex twice = solid block, no label).
- **Four swatch images on one line with no spaces** = a seamless band. Add a space and it breaks.
- **Max 4 tags**, comma separated, no `#`.
- **Every image needs alt text** — `![](url)` triggers DEV's accessibility warning. `lint_md.py --fix` fills them in.
- **No `cover_image:` in the file** — you upload the cover yourself.
- Keep long code inside `{% details %} … {% enddetails %}` so the article stays readable.
