# Topic queue

Source: the 100-topic colour list. Five posts per batch.

## ✅ Batch 1 — done (06–10)

| # | Topic | File | Cover prompt |
| :-: | :-- | :-- | :-: |
| 1 | CSS color-mix(): Dynamic Color Variations | `06-css-color-mix-dynamic-variations.md` | ✅ |
| 2 | CSS Relative Color Syntax | `07-css-relative-color-syntax.md` | ✅ |
| 3 | light-dark() in CSS | `08-light-dark-css-theme-aware.md` | ✅ |
| 4 | HWB Color Explained | `09-hwb-color-explained.md` | ✅ |
| 5 | Display-P3 vs sRGB | `10-display-p3-vs-srgb-wide-gamut.md` | ✅ |

## ⏭️ Batch 2 — next up (topics 6–10)

| # | Topic | Primary keyword | Audience |
| :-: | :-- | :-- | :-- |
| 6 | How to Design for Wide-Gamut Screens Without Breaking Older Displays | same | Product designers |
| 7 | Color-Gamut Media Queries: Better Experiences for P3 Displays | Color-Gamut Media Queries | E-commerce teams |
| 8 | Why the Same HEX Color Looks Different on Different Monitors | same | Data designers |
| 9 | How Browser Color Management Changes Website Appearance | same | AI-assisted designers |
| 10 | A Designer's Guide to ICC Color Profiles | ICC Color Profiles | Print/packaging designers |

## House rules for every post

- ~1,200–1,500 words, practical, code-heavy
- palette from the site, credited and linked
- every claim with a number is computed with `tools/colorspaces.py` or `tools/palette_kit.py`
- no `cover_image:` in front matter — prompt goes in `COVER-PROMPTS.md`
- `format_md.py --airy` then `lint_md.py` before it ships
