---
title: "Color-Gamut Media Queries: Better Product Colour for P3 Displays"
published: false
description: "A complete guide to @media (color-gamut) — how the values cascade, how to combine it with dark mode, and why e-commerce colour accuracy is worth the extra block."
tags: css, webdev, ecommerce, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#BDE0FE` | `#CDB4DB` | `#FFC8DD` | `#A8DADC` |
| :--: | :--: | :--: | :--: |
| ![light cyan swatch #BDE0FE](https://placehold.co/120x56/BDE0FE/BDE0FE.png) | ![light purple swatch #CDB4DB](https://placehold.co/120x56/CDB4DB/CDB4DB.png) | ![pale pink swatch #FFC8DD](https://placehold.co/120x56/FFC8DD/FFC8DD.png) | ![light teal swatch #A8DADC](https://placehold.co/120x56/A8DADC/A8DADC.png) |

> 🎨 **Palette in play** — [Summer Gallery](https://colorfiind.com/palette/summer-gallery) · `#BDE0FE` `#CDB4DB` `#FFC8DD` `#A8DADC`

"The blouse looked mint online and arrived sage."

&nbsp;

Colour is one of the top drivers of returns in apparel and home goods, and a chunk of it is not photography — it's the last few centimetres of the pipeline, where a colour the studio measured carefully gets squeezed into whatever box the shopper's screen provides.

&nbsp;

`@media (color-gamut)` is the CSS tool for that last stretch. It's a small feature with one genuinely confusing behaviour, so let's do it properly.

&nbsp;

![Section divider bar in light cyan](https://placehold.co/1000x8/BDE0FE/BDE0FE.png)

## 🧾 The syntax, and the part that confuses everyone

```css
@media (color-gamut: srgb)    { /* ~every colour display in existence */ }
@media (color-gamut: p3)      { /* Display-P3 capable or wider */ }
@media (color-gamut: rec2020) { /* Rec. 2020 capable or wider */ }
```

**The values are cumulative, not exclusive.** A P3 display matches `srgb` *and* `p3`. A Rec. 2020 display matches all three. There is no "sRGB only" query.

&nbsp;

That has one practical consequence, and it's the bug I see most:

```diff
- /* WRONG — the srgb block wins on a P3 display because it comes last */
- @media (color-gamut: p3)   { .swatch { background: color(display-p3 0.4 0.85 0.78); } }
- @media (color-gamut: srgb) { .swatch { background: #A8DADC; } }

+ /* RIGHT — narrow first, wide last, let the cascade upgrade */
+ .swatch { background: #A8DADC; }
+ @media (color-gamut: p3)   { .swatch { background: color(display-p3 0.4 0.85 0.78); } }
+ @media (color-gamut: rec2020) { .swatch { background: color(rec2020 0.38 0.86 0.79); } }
```

Order narrow → wide. Same rule as `min-width` breakpoints.

&nbsp;

You rarely need `(color-gamut: srgb)` at all — a plain declaration outside any query is the sRGB case, and it also covers browsers that don't understand the feature.

&nbsp;

![Section divider bar in light purple](https://placehold.co/1000x8/CDB4DB/CDB4DB.png)

## 🛍️ Why an e-commerce team should care

The colours that suffer most in sRGB are exactly the ones product teams argue about: saturated teals, corals, emeralds and true reds. Here's the palette above converted precisely to P3 — these are the coordinates that reproduce the *same* colour, before you push anything:

| Product swatch | sRGB | Display-P3 coordinates |
| :-- | :-- | :-- |
| ![light cyan swatch #BDE0FE](https://placehold.co/70x28/BDE0FE/BDE0FE.png) Powder | `#BDE0FE` | `color(display-p3 0.7680 0.8743 0.9844)` |
| ![light purple swatch #CDB4DB](https://placehold.co/70x28/CDB4DB/CDB4DB.png) Lilac | `#CDB4DB` | `color(display-p3 0.7877 0.7094 0.8479)` |
| ![pale pink swatch #FFC8DD](https://placehold.co/70x28/FFC8DD/FFC8DD.png) Blush | `#FFC8DD` | `color(display-p3 0.9663 0.7927 0.8635)` |
| ![light teal swatch #A8DADC](https://placehold.co/70x28/A8DADC/A8DADC.png) Mint | `#A8DADC` | `color(display-p3 0.6990 0.8493 0.8591)` |

For pastels the gain is modest. Do the same exercise with a saturated coral or emerald and the P3 version is visibly closer to the physical sample — which is the entire business case.

&nbsp;

**Where it pays in a store:**

- **Swatch chips next to the photo.** If the chip is CSS and the photo is a P3 image, they should live in the same space or the mismatch reads as a defect.
- **"Colour: Sage" filters.** Ten green variants need to stay distinguishable; extra gamut buys separation.
- **Brand-critical packaging shots.** The hero colour is the product.

**Where it doesn't:** price, body copy, chrome, empty states. Don't spend review time there.

&nbsp;

![Section divider bar in pale pink](https://placehold.co/1000x8/FFC8DD/FFC8DD.png)

## 🎯 The pattern for a product swatch component

**`swatches.css`** ⌁ *data attributes + one enhancement block*

```css
.swatch {
  inline-size: 2.25rem;
  aspect-ratio: 1;
  border-radius: 50%;
  border: 1px solid rgb(0 0 0 / 0.12);
  background: var(--swatch);
}

[data-colour='powder'] { --swatch: #BDE0FE; }
[data-colour='lilac']  { --swatch: #CDB4DB; }
[data-colour='blush']  { --swatch: #FFC8DD; }
[data-colour='mint']   { --swatch: #A8DADC; }

@media (color-gamut: p3) {
  [data-colour='powder'] { --swatch: color(display-p3 0.7680 0.8743 0.9844); }
  [data-colour='lilac']  { --swatch: color(display-p3 0.7877 0.7094 0.8479); }
  [data-colour='blush']  { --swatch: color(display-p3 0.9663 0.7927 0.8635); }
  [data-colour='mint']   { --swatch: color(display-p3 0.6990 0.8493 0.8591); }
}

.swatch[aria-pressed='true'] {
  outline: 2px solid CanvasText;
  outline-offset: 3px;
}
```

Two blocks, no per-component duplication, and the selected state uses an outline rather than a colour change — because **colour alone must never be the only signal**, gamut or no gamut.

&nbsp;

![Section divider bar in light teal](https://placehold.co/1000x8/A8DADC/A8DADC.png)

## 🔀 Combining it with everything else

Media features compose, and this is where the pattern earns its keep:

```css
/* wide gamut AND dark mode */
@media (color-gamut: p3) and (prefers-color-scheme: dark) {
  :root { --accent: color(display-p3 0.45 0.88 0.85); }
}

/* respect the user who wants less intensity */
@media (color-gamut: p3) and (prefers-contrast: less) {
  :root { --accent: #A8DADC; }          /* fall back to the calmer sRGB value */
}

/* HDR-capable screens, a separate question from gamut */
@media (dynamic-range: high) {
  .hero { background-image: image-set('hero-hdr.avif' type('image/avif')); }
}
```

`dynamic-range` is about brightness range; `color-gamut` is about how many hues. A screen can be one without the other.

&nbsp;

![Section divider bar in light cyan](https://placehold.co/1000x8/BDE0FE/BDE0FE.png)

## 🧑‍💻 Reading it from JavaScript

Useful for analytics — "what share of my traffic can even see the enhancement?" — and for canvas work:

```js
const p3 = window.matchMedia('(color-gamut: p3)');

analytics.track('display_gamut', {
  gamut: p3.matches ? 'p3' : 'srgb',
  hdr: matchMedia('(dynamic-range: high)').matches,
});

// canvas has to opt in explicitly; it clamps to sRGB otherwise
const ctx = canvas.getContext('2d', {
  colorSpace: p3.matches ? 'display-p3' : 'srgb',
});

// gamut can change when a laptop is plugged into an external monitor
p3.addEventListener('change', (e) => rerenderSwatches(e.matches));
```

That last listener matters more than it sounds. Drag a browser window from a MacBook screen to a cheap external monitor and the match result flips mid-session.

&nbsp;

{% details 📊 Measuring whether it moved the needle %}

```js
// tag the gamut on colour-related events so returns data can be sliced by it
const gamut = matchMedia('(color-gamut: p3)').matches ? 'p3' : 'srgb';

analytics.track('variant_selected', { sku, colour: 'mint', gamut });
analytics.track('return_initiated', { sku, reason: 'colour_mismatch', gamut });
```

If colour-related returns are meaningfully lower on P3 sessions after you ship the enhancement, you have your answer — and a number to take to whoever funds the work.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in light purple](https://placehold.co/1000x8/CDB4DB/CDB4DB.png)

## 🚧 Four traps

**1 · The cascade order bug.** Covered above; it silently disables your enhancement.

&nbsp;

**2 · Assuming P3 means "accurate".** A wide-gamut panel that is uncalibrated is wide *and* wrong. Enhancement improves the ceiling, not the floor.

&nbsp;

**3 · Photos and CSS drifting apart.** Product images are usually tagged sRGB. If your CSS chips jump to P3 and the photo doesn't, the chip stops matching the garment.

&nbsp;

**4 · Reviewing only on Apple hardware.** Your design team's screens are the least representative sample of your customers' screens.

&nbsp;

![Section divider bar in pale pink](https://placehold.co/1000x8/FFC8DD/FFC8DD.png)

## 🧾 The checklist

1. Plain declaration = sRGB. Add `p3`, then `rec2020`, in that order.
2. Never use `(color-gamut: srgb)` to *undo* an enhancement — it matches P3 screens too.
3. Convert exactly first; push chroma only if the product genuinely needs it.
4. Keep CSS swatches and product photography in the same colour space.
5. Selection state uses shape or outline, never colour alone.
6. Listen for `change` — the gamut can flip when a monitor is plugged in.
7. Tag analytics with the gamut so you can prove the impact.

Getting the sRGB baseline right is still the biggest win, and starting from a palette that's already balanced saves the argument — [ColorFiind](https://colorfiind.com) publishes each set with its HEX codes ready to convert.

&nbsp;

{% cta https://colorfiind.com %} Browse palettes for your product swatches {% endcta %}

&nbsp;

*Do you know what share of your traffic is on a P3 display? It's a five-line analytics change to find out.* 👇
