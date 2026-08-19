---
title: "Display-P3 vs sRGB: When Wide-Gamut Colour Is Worth It (and When It Isn't)"
published: false
description: "Most screens can show colours your CSS never asks for. Here's how to enhance progressively with color(display-p3), and why accessibility work stays firmly in sRGB."
tags: css, accessibility, webdev, design
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#081C09` | `#00E6FF` | `#FF24FF` | `#FFF238` |
| :--: | :--: | :--: | :--: |
| ![near-black swatch #081C09](https://placehold.co/120x56/081C09/081C09.png) | ![vivid cyan swatch #00E6FF](https://placehold.co/120x56/00E6FF/00E6FF.png) | ![vivid magenta swatch #FF24FF](https://placehold.co/120x56/FF24FF/FF24FF.png) | ![vivid yellow swatch #FFF238](https://placehold.co/120x56/FFF238/FFF238.png) |

> 🎨 **Palette in play** — [Topaz Studio Moment](https://colorfiind.com/palette/topaz-studio-moment) · `#081C09` `#00E6FF` `#FF24FF` `#FFF238`

Every Apple device since 2016, most flagship Android phones and a growing pile of laptop panels can display noticeably more colour than sRGB describes. If your CSS only ever says `#00E6FF`, you are asking those screens to render inside a box roughly **25% smaller** than the one they're capable of.

&nbsp;

Whether you should care depends entirely on what the colour is doing — and the answer is different for a brand hero and for body text.

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00E6FF/00E6FF.png)

## 📦 What a gamut actually is

A gamut is the set of colours a system can represent. sRGB was standardised for CRT monitors in 1996; Display P3 uses wider red and green primaries, covering roughly **45% of visible colour against sRGB's ~35%**.

| Gamut | Coverage of visible colour | Where you meet it | CSS |
| :-- | :-- | :-- | :-- |
| sRGB | ~35% | the web's baseline, every display | `#hex`, `rgb()`, `hsl()`, `hwb()` |
| Display P3 | ~45% | Apple devices 2016+, modern phones and laptops | `color(display-p3 r g b)` |
| Rec. 2020 | ~75% | HDR video, cinema; few consumer panels | `color(rec2020 r g b)` |

The values inside `color()` are **0–1 floats**, not 0–255. `color(display-p3 1 0 0)` is P3's reddest red — a colour sRGB literally cannot express.

&nbsp;

![Section divider bar in vivid magenta](https://placehold.co/1000x8/FF24FF/FF24FF.png)

## 🎨 What your existing colours look like in P3

Converting sRGB into P3 coordinates doesn't change the colour — it's the same appearance, described in a wider space:

| sRGB hex | Same colour in P3 |
| :-- | :-- |
| `#00E6FF` | `color(display-p3 0.4107 0.8886 0.9856)` |
| `#FF24FF` | `color(display-p3 0.9191 0.2484 0.9680)` |
| `#FFF238` | `color(display-p3 0.9913 0.9508 0.3771)` |

Notice the numbers pull *inward* — `#00E6FF` needs 41% red in P3 to look identical, because P3's primaries sit further out. The interesting move is going the other way: pushing those numbers back toward 0 and 1 to reach colours sRGB can't hold.

&nbsp;

![Section divider bar in vivid yellow](https://placehold.co/1000x8/FFF238/FFF238.png)

## 🪜 Progressive enhancement, the only sane pattern

Declare sRGB first, then enhance. Browsers on sRGB screens keep the fallback; wide-gamut screens get the richer version.

&nbsp;

**`brand.css`** ⌁ *fallback first, always*

```css
.cta {
  background: #00E6FF;                              /* every display */
}

@media (color-gamut: p3) {
  .cta {
    background: color(display-p3 0.15 0.92 1);      /* richer cyan */
  }
}

@media (color-gamut: rec2020) {
  .cta {
    background: color(rec2020 0.12 0.94 1);         /* richer still */
  }
}
```

The `color-gamut` values are cumulative: a P3 display also matches `srgb`, and a Rec. 2020 display matches all three. Order them narrow → wide and let the cascade decide.

&nbsp;

Two supporting queries worth knowing:

```css
/* does the BROWSER understand the syntax? (separate question from the display) */
@supports (color: color(display-p3 1 1 1)) { /* … */ }

/* the shorthand fallback — older parsers drop the line they can't read */
.cta {
  background: #00E6FF;
  background: color(display-p3 0.15 0.92 1);
}
```

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00E6FF/00E6FF.png)

## 🌈 Or skip the media query entirely: use OKLCH

`oklch()` describes colour perceptually, not per-device, and the browser gamut-maps it to whatever the display can do. Specify a chroma beyond sRGB and P3 screens simply show more of it:

```css
.badge {
  background: oklch(0.75 0.25 145);   /* sRGB clips to ≈ #00D32C; P3 shows the real thing */
}
```

| Chroma at `oklch(0.75 … 145)` | Fits in sRGB? |
| :-- | :-- |
| `0.20` → `#45CD55` | ✅ inside sRGB |
| `0.25` → `#00D32C` | 🚫 clipped — P3 shows more |
| `0.30` → `#00D900` | 🚫 clipped — P3 shows much more |

This is the lowest-effort path to wide gamut: **write OKLCH, let the browser handle both worlds.** You lose precise control over the sRGB fallback, which is why brand-critical colours still deserve the explicit media-query version.

&nbsp;

![Section divider bar in vivid magenta](https://placehold.co/1000x8/FF24FF/FF24FF.png)

## ✅ When it's worth it

- **Brand and marketing surfaces** — hero sections, gradients, launch pages. A P3 cyan genuinely looks better and users notice, even if they can't name why.
- **Product photography and e-commerce** — a P3 image next to an sRGB-clipped swatch makes the swatch look wrong. Match the pipeline end to end.
- **Data visualisation with many categories** — a wider gamut buys you more distinguishable hues before two series start looking alike.
- **Games, media, creative tools** — your users are on colour-accurate hardware and expect it to be used.

&nbsp;

![Section divider bar in vivid yellow](https://placehold.co/1000x8/FFF238/FFF238.png)

## 🚫 When it isn't

- **Body text and UI chrome.** No reader has ever wished paragraph text were more saturated.
- **Anything where the sRGB fallback isn't already right.** P3 is an enhancement, not a fix for a bad palette.
- **When the team has no way to preview it.** If everyone's on sRGB monitors, you'll ship colours nobody has looked at.
- **Colour as the only signal.** Wider gamut increases *saturation*, not distinguishability for colourblind users. That's a hue-and-lightness problem.

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00E6FF/00E6FF.png)

## ♿ The accessibility part nobody mentions

This matters more than the aesthetics, so read it twice:

&nbsp;

**WCAG contrast is defined on sRGB relative luminance.** When you write a P3 colour, the ratio you must satisfy is still computed from its sRGB interpretation. A more saturated P3 red is not "more contrasty" — and tools disagree about how to handle out-of-gamut values.

&nbsp;

Practical rules:

1. **Pick and verify the sRGB fallback first.** It's the colour that has a defined contrast ratio.
2. **Keep the P3 version perceptually close.** Same hue, same lightness, more chroma. Not "P3 lets me go lighter".
3. **Never let the P3 enhancement change which text colour is readable.** If white works on the fallback, it must work on the enhancement.

```css
:root {
  --danger: #C1121F;                         /* verified: 6.22:1 with white ✓ AA */
}

@media (color-gamut: p3) {
  :root {
    --danger: color(display-p3 0.69 0.14 0.15);   /* same lightness, richer red */
  }
}
```

> 🚧 **Trap** — oversaturated colour is a real comfort issue. Fully saturated P3 on a large surface is more fatiguing than the sRGB equivalent, and users with light sensitivity or migraine triggers feel it first. Use the extra gamut on accents and imagery, not on full-page backgrounds.

&nbsp;

![Section divider bar in vivid magenta](https://placehold.co/1000x8/FF24FF/FF24FF.png)

## 🔍 How to test without buying a monitor

```console
$ # 1. Does this display support P3?
   matchMedia('(color-gamut: p3)').matches        // true on modern Apple hardware

$ # 2. Force the browser to pretend otherwise
   Chrome DevTools → Rendering → Emulate CSS media feature color-gamut

$ # 3. Screenshot caveat
   Most screenshot tools convert to sRGB, so your P3 enhancement
   will look identical in the PR — verify on the device, not in the image.

$ # 4. Sanity check in the console
   CSS.supports('color', 'color(display-p3 1 0 0)')   // browser-side support
```

Point 3 catches teams out constantly: the enhancement is invisible in design review because the review artefact is an sRGB PNG.

&nbsp;

{% details 🧰 Converting sRGB to Display-P3 coordinates in JS %}

```js
const toLinear = (c) => (c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
const toGamma  = (c) => (c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055);

const SRGB_TO_XYZ = [[0.4124564, 0.3575761, 0.1804375],
                     [0.2126729, 0.7151522, 0.0721750],
                     [0.0193339, 0.1191920, 0.9503041]];
const XYZ_TO_P3   = [[ 2.4934969, -0.9313836, -0.4027108],
                     [-0.8294890,  1.7626641,  0.0236247],
                     [ 0.0358458, -0.0761724,  0.9568845]];

const mul = (m, v) => m.map((row) => row.reduce((s, k, i) => s + k * v[i], 0));

export function hexToP3(hex) {
  const rgb = hex.replace('#', '').match(/../g).map((h) => parseInt(h, 16) / 255);
  const p3 = mul(XYZ_TO_P3, mul(SRGB_TO_XYZ, rgb.map(toLinear)));
  return p3.map((c) => +toGamma(Math.min(1, Math.max(0, c))).toFixed(4));
}

hexToP3('#00E6FF');   // [0.4107, 0.8886, 0.9856]
```

Run your palette through it once, paste the results into the P3 media query, and you have an exact-match baseline to push outward from.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in vivid yellow](https://placehold.co/1000x8/FFF238/FFF238.png)

## 🧾 The checklist

1. Ship a correct **sRGB** colour first. P3 is enhancement, never foundation.
2. Enhance inside `@media (color-gamut: p3)`, ordered narrow → wide.
3. Or write `oklch()` and let the browser gamut-map — cheapest path by far.
4. Verify **contrast on the sRGB value**; WCAG maths lives there.
5. Keep the P3 variant the same hue and lightness — richer, not lighter.
6. Reserve saturation for accents and imagery; large surfaces stay calm.
7. Test on real hardware — screenshots flatten the difference to nothing.

A palette that's already balanced in sRGB is the right starting point for all of this; [ColorFiind](https://colorfiind.com) gives you the HEX codes, and the twelve-line converter above gives you the P3 coordinates to enhance them with.

&nbsp;

{% cta https://colorfiind.com %} Start from a solid sRGB palette {% endcta %}

&nbsp;

*Are you shipping any P3 colour today, or waiting until the design team has the hardware to review it?* 👇
