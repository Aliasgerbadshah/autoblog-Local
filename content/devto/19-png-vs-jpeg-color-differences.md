---
title: "Why PNG and JPEG Exports Look Different From Your Design"
published: false
description: "Same artboard, two exports, two colours. Chroma subsampling, 8-bit quantisation and profile handling explain nearly every mismatch — and which format to pick when."
tags: design, webdev, designsystems, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#432C1E` | `#82462A` | `#C99235` | `#27564D` |
| :--: | :--: | :--: | :--: |
| ![deep orange swatch #432C1E](https://placehold.co/120x56/432C1E/432C1E.png) | ![dark orange swatch #82462A](https://placehold.co/120x56/82462A/82462A.png) | ![orange swatch #C99235](https://placehold.co/120x56/C99235/C99235.png) | ![deep teal swatch #27564D](https://placehold.co/120x56/27564D/27564D.png) |

> 🎨 **Palette in play** — [Autumn Beach Wave Colorway](https://colorfiind.com/palette/autumn-beach-wave-colorway) · `#432C1E` `#82462A` `#C99235` `#27564D`

Export the same frame twice — once PNG, once JPEG — drop both into the same page, and they don't match. The JPEG's amber is slightly off, the edges of the label text have a faint coloured haze, and a flat background that should be one solid value is now several.

&nbsp;

Nothing went wrong. The two formats make fundamentally different promises, and only one of them promises to give back exactly what you put in.

&nbsp;

![Section divider bar in dark orange](https://placehold.co/1000x8/82462A/82462A.png)

## ⚖️ What each format actually guarantees

| | PNG | JPEG | WebP | AVIF |
| :-- | :-- | :-- | :-- | :-- |
| Lossless option | ✅ always | 🚫 never | ✅ yes | ✅ yes |
| Full colour per pixel | ✅ 4:4:4 | 🚫 usually 4:2:0 | 🚫 lossy mode is 4:2:0 | ✅ supports 4:4:4 |
| Alpha | ✅ | 🚫 | ✅ | ✅ |
| Bit depth | 8 or 16 | 8 | 8 | 8/10/12 |
| ICC profile | ✅ | ✅ | ✅ | ✅ |
| Best for | flat graphics, text, UI | photographs | either | either, smallest |

Two rows explain most mismatches: **lossless vs lossy**, and **chroma subsampling**.

&nbsp;

![Section divider bar in orange](https://placehold.co/1000x8/C99235/C99235.png)

## 🌫️ Chroma subsampling — the invisible colour blur

JPEG doesn't store RGB. It converts to Y′CbCr — one brightness channel and two colour channels — then throws away three-quarters of the colour data. That's **4:2:0**: colour sampled at half resolution horizontally and vertically, one colour sample per four pixels.

&nbsp;

Your eye is far more sensitive to brightness detail than colour detail, so for photographs this is nearly free. For graphics it is not:

| Content | 4:2:0 result |
| :-- | :-- |
| Photograph of a beach | indistinguishable from 4:4:4 |
| Amber `#C99235` text on deep teal `#27564D` | visible colour fringing on every letter |
| Thin coloured rules and borders | smeared, slightly wrong colour |
| Flat brand-colour block | fine in the middle, unstable at the edges |

That second row is why your carefully-set label text looks slightly furry in the JPEG and crisp in the PNG. The luminance edge is intact; the colour edge has been averaged across four pixels.

```console
$ # force full colour resolution when you must use JPEG
  magick card.png -quality 92 -sampling-factor 4:4:4 card.jpg

$ # check what an existing file used
  exiftool -YCbCrSubSampling card.jpg
  #   Y Cb Cr Sub Sampling : YCbCr4:2:0 (2 2)   ← the fringing culprit
```

Note that **lossy WebP is always 4:2:0** — there's no 4:4:4 mode. If you're replacing PNGs with WebP for a text-heavy graphic, use *lossless* WebP or AVIF, not lossy WebP.

&nbsp;

![Section divider bar in dark orange](https://placehold.co/1000x8/82462A/82462A.png)

## 🎨 Why the flat background changed colour

A solid `#82462A` block should export as `#82462A`. In a JPEG it often doesn't, for two compounding reasons:

&nbsp;

**Block-based compression.** JPEG works in 8×8 pixel blocks. In a flat area, quantisation nudges values by one or two — invisible individually, but if you sample the pixel with a colour picker to match it in CSS, you get `#824529` and a seam appears where the image meets the CSS colour.

&nbsp;

**Repeated saves.** Every re-encode moves it further. A file that's been through a design tool, a review tool and an optimiser has been quantised three times.

&nbsp;

The rule that follows: **never eyedropper a colour out of a JPEG to define a token.** Take brand values from the source of truth, not from a compressed artefact.

```diff
- /* sampled from the exported hero.jpg — subtly wrong, and it drifts */
- --brand-clay: #824529;

+ /* taken from the palette definition — stable forever */
+ --brand-clay: #82462A;
```

&nbsp;

![Section divider bar in orange](https://placehold.co/1000x8/C99235/C99235.png)

## 🧮 The PNG trap nobody expects: indexed colour

PNG is lossless, but "PNG-8" is lossless *after* it has thrown away colours. Indexed PNGs store a palette of at most 256 entries. Export a smooth gradient or a photo as PNG-8 and the optimiser quantises — usually with dithering, which adds visible speckle.

| Variant | What it is | Use for |
| :-- | :-- | :-- |
| PNG-24 | full 8-bit-per-channel colour | anything with gradients or photos |
| PNG-8 | ≤256 colour palette | flat graphics with few colours; logos |
| PNG-8 + dither | palette plus noise to fake extra colours | avoid for brand colour work |

For a four-colour brand graphic like this palette, PNG-8 is genuinely fine and dramatically smaller. For anything with a gradient, PNG-24 or SVG.

&nbsp;

![Section divider bar in dark orange](https://placehold.co/1000x8/82462A/82462A.png)

## 🧭 So which format, when?

```console
$ decide:
  vector artwork, logo, icon          → SVG        (colour from CSS, never drifts)
  flat graphic, UI, text in image     → PNG-24 / lossless WebP / AVIF 4:4:4
  few flat colours, no gradient       → PNG-8      (small, exact)
  photograph                          → AVIF, JPEG fallback at q82-88
  photograph with text overlaid       → keep the text in HTML, not the image
  screenshot with UI text             → PNG        (never lossy)
  needs transparency                  → PNG / WebP / AVIF (not JPEG)
```

The line worth internalising: **if it contains text or hard colour edges, it should not be lossy at 4:2:0.**

&nbsp;

![Section divider bar in orange](https://placehold.co/1000x8/C99235/C99235.png)

## 🏷️ Profiles differ between the two exports too

Design tools handle metadata inconsistently across formats. It's common for a PNG export to carry the document profile while the JPEG export from the same dialog carries none — or vice versa. An untagged file is assumed sRGB, so if your document wasn't sRGB, only one of the two exports is right.

```bash
# compare what the two exports actually carry
exiftool -ProfileDescription -ColorSpace -YCbCrSubSampling card.png card.jpg
```

Do this once for each export preset your team uses. It takes a minute and permanently answers "why do these two look different".

&nbsp;

{% details 🔬 A quick numeric diff between two exports %}

```bash
# how far apart are they, really?
magick compare -metric RMSE card.png card.jpg null: 2>&1
#   1843.2 (0.0281)   ← ~2.8% average error; fine for a photo, bad for a brand block

# where the differences are
magick compare card.png card.jpg diff.png    # bright pixels = mismatch

# sample one flat area from both
magick card.png -crop 1x1+400+300 -format '%[pixel:p{0,0}]' info:
magick card.jpg -crop 1x1+400+300 -format '%[pixel:p{0,0}]' info:
```

If the flat-area samples differ, you have your answer, and it's quantisation — not your eyes.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in dark orange](https://placehold.co/1000x8/82462A/82462A.png)

## 🧾 The checklist

1. PNG is exact; JPEG is an approximation with the colour channel downsampled.
2. Text and hard colour edges → lossless, 4:4:4. Photographs → lossy is fine.
3. Lossy WebP is always 4:2:0 — use lossless WebP or AVIF for graphics.
4. Never sample a brand colour out of a compressed export.
5. Watch for PNG-8 quantising your gradients.
6. Verify that *both* export presets embed the same profile.
7. Where possible, keep text in HTML and colour in CSS — then neither can drift.

Keeping the canonical hex values somewhere authoritative is what makes all of this a non-issue; that's the role a published palette plays — [ColorFiind](https://colorfiind.com) lists each set's exact codes so the source of truth isn't a screenshot.

&nbsp;

{% cta https://colorfiind.com %} Keep your palette's source of truth {% endcta %}

&nbsp;

*Ever matched a CSS background to a JPEG and got a visible seam? That's quantisation, and now you know where to look.* 👇
