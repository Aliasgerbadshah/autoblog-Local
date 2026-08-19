---
title: "Preparing Colour-Critical Assets for Retina and High-Gamut Displays"
published: false
description: "2x assets, subtle gradients and brand colours all break in different ways on modern screens. Here's an export pipeline that survives retina, P3 and 8-bit banding."
tags: webdev, design, ux, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#92A3BA` | `#96A7BE` | `#F2FAFF` | `#D7E9F9` |
| :--: | :--: | :--: | :--: |
| ![muted blue swatch #92A3BA](https://placehold.co/120x56/92A3BA/92A3BA.png) | ![muted blue swatch #96A7BE](https://placehold.co/120x56/96A7BE/96A7BE.png) | ![pale cyan swatch #F2FAFF](https://placehold.co/120x56/F2FAFF/F2FAFF.png) | ![pale cyan swatch #D7E9F9](https://placehold.co/120x56/D7E9F9/D7E9F9.png) |

> 🎨 **Palette in play** — [Lagoon Cool Summer Edition](https://colorfiind.com/palette/lagoon-cool-summer-edition) · `#92A3BA` `#96A7BE` `#F2FAFF` `#D7E9F9`

A soft, low-contrast palette like this one is a stress test disguised as a mood board. Two of its colours — `#92A3BA` and `#96A7BE` — are **four RGB values apart**. On a retina screen with a good panel they're two distinct greys-blue. Compressed, resized, or rendered on a cheap laptop, they're the same colour.

&nbsp;

Colour-critical asset prep is mostly about protecting differences that small from the four things that eat them: resampling, 8-bit quantisation, compression and gamut mapping.

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/92A3BA/92A3BA.png)

## 🔍 Why "retina" is a colour problem, not just a sharpness problem

Everyone remembers to export at 2x. Fewer people notice what the *downscale* does.

&nbsp;

When a browser renders your 2x asset at 1x on a standard display, it averages pixels. Averaging happens in whatever space the compositor works in — and averaging gamma-encoded sRGB values is not the same as averaging light. A fine two-colour pattern that should blend to a mid-tone comes out darker than it should.

```console
$ # the classic demonstration: 50/50 black and white stripes
  averaged in gamma-encoded sRGB  → #808080   (looks too dark)
  averaged in linear light        → #BCBCBC   (what your eye expects)
```

Practical consequences:

- **Fine detail loses saturation** when scaled down. Thin coloured lines go grey.
- **Two similar tones merge.** Exactly the `#92A3BA` / `#96A7BE` problem above.
- **Logos with hairline strokes** shift colour at 1x even though the file is "correct".

The fix isn't clever CSS. It's **designing the asset so the difference survives**: minimum 2–3% lightness separation between adjacent tones, and no detail thinner than 2 device pixels carrying colour meaning.

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/96A7BE/96A7BE.png)

## 📐 The export matrix

| Asset type | Format | Density | Colour space | Notes |
| :-- | :-- | :-- | :-- | :-- |
| UI icons, logos | SVG | vector | sRGB in CSS | colour lives in `fill`, not the file |
| Photography | AVIF + JPEG fallback | 1x/2x via `srcset` | sRGB tagged | never Adobe RGB |
| Brand hero art | AVIF, optionally P3 | 1x/2x | tagged P3 + sRGB fallback | see `<picture>` below |
| Flat graphics with text | PNG or lossless WebP | 2x | sRGB tagged | avoid 4:2:0 chroma subsampling |
| Subtle gradients | SVG or CSS gradient | vector | sRGB | do **not** ship as 8-bit raster |

That last row saves the most pain, and it deserves its own section.

&nbsp;

![Section divider bar in pale cyan](https://placehold.co/1000x8/F2FAFF/F2FAFF.png)

## 🌫️ Banding: the 8-bit ceiling

Take the two pale colours from this palette. `#F2FAFF` → `#D7E9F9` is a gorgeous, barely-there gradient. Measure the distance and the problem appears:

```console
$ # channel deltas from #F2FAFF to #D7E9F9
  R: 27 steps   G: 17 steps   B: 6 steps
```

Six steps in blue. Stretch that across 1200 pixels of hero background and 8-bit colour gives you **six visible bands** — one every 200 pixels. No amount of JPEG quality fixes it, because the information was never there.

&nbsp;

Three fixes, in order of preference:

&nbsp;

**1 · Don't ship it as a raster.** A CSS gradient is computed at render time and can be dithered by the browser:

```css
.hero {
  background-image: linear-gradient(160deg, #F2FAFF, #D7E9F9);
}
```

**2 · Add noise.** A 1–2% noise layer breaks up the bands and costs almost nothing after compression:

```css
.hero {
  background-image:
    url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg"><filter id="n"><feTurbulence baseFrequency="0.8" numOctaves="3"/><feColorMatrix values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.035 0"/></filter><rect width="100%" height="100%" filter="url(%23n)"/></svg>'),
    linear-gradient(160deg, #F2FAFF, #D7E9F9);
}
```

**3 · Interpolate in a better space.** Gradients in OKLCH avoid the grey dip in the middle and distribute steps more evenly:

```css
.hero {
  background-image: linear-gradient(in oklch 160deg, #F2FAFF, #D7E9F9);
}
```

&nbsp;

![Section divider bar in pale cyan](https://placehold.co/1000x8/D7E9F9/D7E9F9.png)

## 🖼️ Serving retina and wide gamut together

`<picture>` handles both axes without JavaScript. Gamut is chosen by media query, density by `srcset`:

```html
<picture>
  <!-- wide-gamut displays, tagged Display P3 -->
  <source
    media="(color-gamut: p3)"
    type="image/avif"
    srcset="hero-p3.avif 1x, hero-p3@2x.avif 2x">

  <!-- everyone else, tagged sRGB -->
  <source
    type="image/avif"
    srcset="hero-srgb.avif 1x, hero-srgb@2x.avif 2x">

  <img
    src="hero-srgb.jpg"
    srcset="hero-srgb.jpg 1x, hero-srgb@2x.jpg 2x"
    width="1200" height="630"
    alt="Sea fog over a harbour at dawn"
    decoding="async">
</picture>
```

Four files instead of one. Worth it for a hero; overkill for a thumbnail. **Pick your colour-critical assets deliberately** — usually the hero, the product shot and the logo lockup, and nothing else.

> 🚧 **Trap** — `srcset` with `w` descriptors plus `sizes` is better for responsive layout images, but for a fixed-size hero the `1x/2x` form is clearer and avoids the browser picking a smaller file on a slow connection, which is exactly when banding shows up worst.

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/92A3BA/92A3BA.png)

## 🎨 Keeping brand colour exact across the asset boundary

The place brand colour breaks is where a raster asset meets a CSS colour — a logo PNG on a coloured band, an illustration on a card.

```css
.brand-band {
  /* the CSS side */
  background: #92A3BA;
}

@media (color-gamut: p3) {
  .brand-band {
    /* exact same colour, P3 coordinates — matches a P3-tagged asset */
    background: color(display-p3 0.5851 0.6371 0.7209);
  }
}
```

And the asset side:

- Export the logo as **SVG** wherever possible; then it inherits `currentColor` or a CSS variable and can never drift.
- If it must be a raster, export it **tagged**, in the same space as the CSS enhancement.
- Never place an untagged asset on a colour-managed background and expect a seam-free join.

{% details 🔧 A build step that catches the common failures %}

```bash
#!/usr/bin/env bash
# check-assets.sh — run after the image pipeline, before deploy
set -euo pipefail
fail=0

for f in dist/images/*.{jpg,png,avif,webp}; do
  [ -e "$f" ] || continue

  # 1 · must carry a colour profile
  if ! exiftool -s3 -ProfileDescription "$f" | grep -q .; then
    echo "❌ untagged: $f"; fail=1
  fi

  # 2 · must not be Adobe RGB
  if exiftool -s3 -ProfileDescription "$f" | grep -qi 'adobe'; then
    echo "❌ Adobe RGB in delivery: $f"; fail=1
  fi

  # 3 · flat graphics should not be 4:2:0
  case "$f" in
    *ui-*|*logo-*|*diagram-*)
      if exiftool -s3 -YCbCrSubSampling "$f" | grep -q '2 2'; then
        echo "⚠️  4:2:0 subsampling on a flat graphic: $f"; fail=1
      fi;;
  esac
done

exit $fail
```

Three checks, thirty seconds, and it catches the majority of "why does this look wrong on the new MacBooks" tickets before they exist.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/96A7BE/96A7BE.png)

## 🧪 The review pass

```console
$ # 1 · view every colour-critical asset at 1x AND 2x
      DevTools → device pixel ratio 1, then 2, then 3

$ # 2 · look for banding on a dark room, full-brightness screen
      subtle gradients hide in daylight and appear at night

$ # 3 · check the seam between asset and CSS colour
      zoom to 400% at the boundary — any line means a space mismatch

$ # 4 · verify the small tonal differences survived
      #92A3BA vs #96A7BE — still two colours after export?

$ # 5 · confirm tags on the delivered files, not the source
      exiftool -ProfileDescription dist/images/*.avif
```

&nbsp;

![Section divider bar in pale cyan](https://placehold.co/1000x8/F2FAFF/F2FAFF.png)

## 🧾 The checklist

1. Design with **≥ 2–3% lightness separation**; anything closer will merge somewhere.
2. Never carry colour meaning in detail thinner than 2 device pixels.
3. Ship subtle gradients as CSS or SVG, not 8-bit rasters — and add noise or use `in oklch`.
4. Use `<picture>` to serve gamut and density together, for hero assets only.
5. Logos as SVG so brand colour comes from CSS and can't drift.
6. Tag every raster, never deliver Adobe RGB, avoid 4:2:0 on flat graphics.
7. Automate the audit on the **delivered** files.

Low-contrast palettes are the hardest to prepare well, which is a good reason to know a palette's contrast structure before you commit to it — [ColorFiind](https://colorfiind.com) labels each set's contrast character alongside the HEX codes.

&nbsp;

{% cta https://colorfiind.com %} Check a palette's contrast before you build {% endcta %}

&nbsp;

*What's the smallest colour difference you've tried to ship? Did it survive the export?* 👇
