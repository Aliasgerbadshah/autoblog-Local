# HOW TO USE THIS PACK

Five ready-to-publish DEV.to articles about ColorFiind, plus the tooling that made them.
Three things you can do with it — pick the one you need.

---

## A. Read / review the posts (2 minutes)

**Option 1 — the preview app (recommended).** It renders the Markdown exactly the way DEV will:
cover image, tags, colour swatches, highlighted code blocks, collapsibles, CTA buttons.

```bash
python3 -m http.server 8080 --bind 0.0.0.0 --directory content/devto
```

Open <http://localhost:8080/> → pick an article in the left sidebar → 🌗 toggles light/dark.

**Option 2 — just open the `.md` files** in VS Code (`Ctrl/Cmd + Shift + V` for the built-in preview)
or straight on GitHub. Swatches render there too, because they are images.

---

## B. Publish one to DEV.to (5 minutes per post)

1. Open <https://dev.to/new>.
2. Make sure you are in the **Markdown** editor
   (Settings → Customization → Editor version → "rich + markdown" if you want separate title/tag fields).
3. Open e.g. `01-colorfiind-developer-tour.md`, select **everything including the `---` front matter**, paste it in.
4. **Cover image:** drag the matching file from `assets/` into the editor body — DEV uploads it and gives
   you a URL. Cut that URL, paste it as the `cover_image:` value in the front matter, delete the leftover
   image line from the body.

   | Article | Cover file in `assets/` |
   | :-- | :-- |
   | 01 tour | `cover-mango-garden-moment.png` |
   | 02 tokens | `cover-neon-diner-harmony.png` |
   | 03 seasonal | `cover-deep-winter-journal.png` |
   | 04 neon | `cover-maple-drift.png` |
   | 05 automation | `cover-clay-flare-spectrum.png` |

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

## Rules to keep when you edit the posts

- **Never hand-edit a contrast number.** Change a colour → re-run
  `python3 palette_kit.py contrast --colors ...` and paste the new value.
- **Swatches must stay images.** DEV strips `<style>` and `style=""`; the colour comes from
  `https://placehold.co/210x110/HEX/HEX.png` (same hex twice = solid block, no label).
- **Four swatch images on one line with no spaces** = a seamless band. Add a space and it breaks.
- **Max 4 tags**, comma separated, no `#`.
- Keep long code inside `{% details %} … {% enddetails %}` so the article stays readable.
