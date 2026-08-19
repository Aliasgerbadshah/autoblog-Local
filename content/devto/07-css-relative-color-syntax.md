---
title: "CSS Relative Color Syntax: Build a Whole Scale From One Base Colour"
published: false
description: "The from keyword lets CSS take a colour apart, change one channel and put it back together. Here's how to generate ramps, states and complements from a single token."
tags: css, webdev, ux, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#F5F7F9` | `#E3DFF1` | `#E0AED7` | `#994129` |
| :--: | :--: | :--: | :--: |
| ![off-white swatch #F5F7F9](https://placehold.co/120x56/F5F7F9/F5F7F9.png) | ![pale indigo swatch #E3DFF1](https://placehold.co/120x56/E3DFF1/E3DFF1.png) | ![light magenta swatch #E0AED7](https://placehold.co/120x56/E0AED7/E0AED7.png) | ![dark red swatch #994129](https://placehold.co/120x56/994129/994129.png) |

> 🎨 **Palette in play** — [Quartz Drift](https://colorfiind.com/palette/quartz-drift-palette) · `#F5F7F9` `#E3DFF1` `#E0AED7` `#994129`

For years, generating a colour ramp meant reaching for Sass, a JavaScript colour library, or a designer's patience. Relative colour syntax does it in the stylesheet, at runtime, with no build step — and unlike `color-mix()`, it lets you touch **one channel at a time**.

&nbsp;

![Section divider bar in light magenta](https://placehold.co/1000x8/E0AED7/E0AED7.png)

## 🔑 The `from` keyword

```css
oklch(from var(--brand) calc(l + 0.15) c h)
/*    ^origin colour     ^new L         ^keep C  ^keep H */
```

Any colour function accepts an origin colour after `from`. Inside, the origin's channels are available as single-letter variables. You either pass a channel straight through, or replace it with a calculation.

| Function | Channels you can reference |
| :-- | :-- |
| `rgb(from …)` | `r` `g` `b` `alpha` |
| `hsl(from …)` | `h` `s` `l` `alpha` |
| `hwb(from …)` | `h` `w` `b` `alpha` |
| `lab(from …)` | `l` `a` `b` `alpha` |
| `lch(from …)` | `l` `c` `h` `alpha` |
| `oklab(from …)` | `l` `a` `b` `alpha` |
| `oklch(from …)` | `l` `c` `h` `alpha` |

The origin gets converted into the function's space first, so you can read a hex and write OKLCH — which is exactly what makes this useful.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/994129/994129.png)

## 🪜 A nine-step ramp from one colour

Take the anchor of that palette, `#994129`. In OKLCH it is `oklch(0.4883 0.124 36.16)` — lightness 49%, modest chroma, a warm orange-red hue.

&nbsp;

Shift only lightness and the hue stays honest:

&nbsp;

**`ramp.css`** ⌁ *one token, nine steps*

```css
:root {
  --clay: #994129;

  --clay-100: oklch(from var(--clay) calc(l + 0.40) calc(c * 0.35) h);
  --clay-200: oklch(from var(--clay) calc(l + 0.30) calc(c * 0.55) h);
  --clay-300: oklch(from var(--clay) calc(l + 0.20) calc(c * 0.80) h);
  --clay-400: oklch(from var(--clay) calc(l + 0.10) c h);
  --clay-500: var(--clay);
  --clay-600: oklch(from var(--clay) calc(l - 0.08) c h);
  --clay-700: oklch(from var(--clay) calc(l - 0.14) calc(c * 0.9) h);
  --clay-800: oklch(from var(--clay) calc(l - 0.20) calc(c * 0.8) h);
  --clay-900: oklch(from var(--clay) calc(l - 0.26) calc(c * 0.7) h);
}
```

What the browser resolves those to:

| Step | Result | Swatch | Good for |
| :-- | :-- | :--: | :-- |
| 200 | `#DC7D63` | ![light red swatch #DC7D63](https://placehold.co/90x30/DC7D63/DC7D63.png) | hover on light surfaces |
| 300 | `#BA5F46` | ![red swatch #BA5F46](https://placehold.co/90x30/BA5F46/BA5F46.png) | badges, chart fills |
| 500 | `#994129` | ![dark red swatch #994129](https://placehold.co/90x30/994129/994129.png) | the brand itself |
| 600 | `#7F2910` | ![burgundy swatch #7F2910](https://placehold.co/90x30/7F2910/7F2910.png) | hover, pressed states |

Compare that to a ramp built by nudging HSL lightness, where the mid-tones slide toward grey and the dark end turns muddy. Because OKLCH lightness is perceptually uniform, **equal steps look equal**.

> 🧪 **Lab note** — reduce chroma as you approach both ends. Very light and very dark colours can't hold much saturation, and if you don't taper it the browser gamut-clips for you — usually less gracefully than you would.

&nbsp;

![Section divider bar in red](https://placehold.co/1000x8/DC7D63/DC7D63.png)

## 🎯 The patterns worth memorising

**`patterns.css`** ⌁ *every one of these is a one-liner*

```css
/* transparency without rgba() gymnastics */
--overlay:     rgb(from var(--brand) r g b / 55%);

/* hover: a touch darker, same personality */
--hover:       oklch(from var(--brand) calc(l - 0.08) c h);

/* muted text: same hue, most of the chroma removed */
--muted:       oklch(from var(--brand) l calc(c * 0.3) h);   /* → #735951 */

/* complement: rotate the hue half a turn */
--complement:  oklch(from var(--brand) l c calc(h + 180));   /* → #006F8C */

/* focus ring: brighter, punchier, semi-transparent */
--ring:        oklch(from var(--brand) 0.72 calc(c * 1.2) h / 0.5);

/* a guaranteed-readable ink for text sitting ON the brand */
--ink:         oklch(from var(--brand) clamp(0, calc((0.62 - l) * 1000), 1) 0 h);
```

That last one is the party trick: it snaps to black or white depending on whether the origin is lighter or darker than 62% lightness, entirely in CSS.

&nbsp;

![Section divider bar in red](https://placehold.co/1000x8/BA5F46/BA5F46.png)

## 🧩 Relative colour vs `color-mix()`

They overlap, but they answer different questions.

| Use | Reach for |
| :-- | :-- |
| "10% darker, same hue" | **relative colour** — you're editing one channel |
| "somewhere between these two brand colours" | **`color-mix()`** — you're blending two inputs |
| "same colour at 40% alpha" | either; `rgb(from … / 40%)` reads clearer |
| "tint the page background with the brand" | **`color-mix()`** — two real colours involved |
| "rotate the hue for a complement" | **relative colour** — mixing can't rotate hue |
| "desaturate to grey" | **relative colour** — `calc(c * 0)` is exact |

In practice, a design system uses both: relative colour to generate each family's ramp, `color-mix()` to blend a family into a surface.

&nbsp;

![Section divider bar in burgundy](https://placehold.co/1000x8/7F2910/7F2910.png)

## 🎨 Runtime theming, for real

Because the origin can be a custom property, one attribute swaps an entire theme:

```css
[data-brand='clay']  { --brand: #994129; }
[data-brand='orchid']{ --brand: #E0AED7; }
[data-brand='indigo']{ --brand: #E3DFF1; }

.card {
  background: oklch(from var(--brand) calc(l + 0.38) calc(c * 0.25) h);
  border: 1px solid oklch(from var(--brand) calc(l + 0.18) calc(c * 0.6) h);
  color: oklch(from var(--brand) calc(l - 0.28) c h);
}
```

```js
// white-label switching in one line
document.documentElement.dataset.brand = tenant.brand;   // 'clay' | 'orchid' | 'indigo'
```

No rebuild, no extra stylesheet per tenant, no 400-line token file. The card derives everything from whatever `--brand` currently is.

&nbsp;

{% details 🧰 The same ramp as a Sass mixin, for comparison %}

```scss
// the old way — build-time only, no runtime theming
@use 'sass:color';

@function ramp($base, $step) {
  @return color.adjust($base, $lightness: $step * 6%);
}

:root {
  --clay-300: #{ramp(#994129, 2)};
  --clay-700: #{ramp(#994129, -2)};
}
```

It works, but the values are frozen at build time and `color.adjust()` operates in HSL, so the steps are perceptually uneven.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in light magenta](https://placehold.co/1000x8/E0AED7/E0AED7.png)

## 🚧 Gotchas

**Channels are unitless numbers in `calc()`.** In `oklch()`, `l` is 0–1 and `h` is degrees. Write `calc(l + 0.1)`, not `calc(l + 10%)`. In `hsl()`, `s` and `l` carry percentages, so there you *do* write `calc(s - 20%)`.

&nbsp;

**Out-of-gamut results get clipped.** `oklch(from #994129 calc(l - 0.16) c h)` wants a chroma sRGB can't reach and lands on `#660E00`. Taper chroma at the extremes, or accept the browser's mapping.

&nbsp;

**`none` propagates.** If the origin has a missing component — common after conversions — it stays missing in the output. Be explicit when it matters.

&nbsp;

**Don't nest ten deep.** Each derivation is cheap, but a chain of six relative colours referencing each other is unreadable at 3am. One hop from the base, two at most.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/994129/994129.png)

## 🧾 The checklist

1. One base token per colour family; derive the rest.
2. Work in `oklch()` so lightness steps look even to the eye.
3. Taper chroma toward both ends of a ramp to avoid gamut clipping.
4. Relative colour edits **one channel**; `color-mix()` blends **two colours**. Use both.
5. Remember the units rule: unitless in OKLCH, percentages in HSL.
6. Theme by swapping the base custom property, not by shipping a second stylesheet.

A ramp is only as good as the colour it starts from. If you need a base with a defensible hue, [ColorFiind](https://colorfiind.com) lists palettes with the HEX codes and CSS variables already written — grab one and let relative colour syntax do the rest.

&nbsp;

{% cta https://colorfiind.com %} Pick a base colour for your ramp {% endcta %}

&nbsp;

*Still generating ramps in JavaScript? Show me the function and I'll write the CSS that replaces it.* 👇
