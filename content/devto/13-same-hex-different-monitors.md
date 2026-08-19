---
title: "Why the Same HEX Colour Looks Different on Every Monitor"
published: false
description: "One hex value, five screens, five colours. Here's the chain of gamut, calibration, white point and ambient light — and how to build data visualisations that survive it."
tags: css, design, dataviz, webdev
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#232E43` | `#49627F` | `#7F95B1` | `#E8E9E5` |
| :--: | :--: | :--: | :--: |
| ![navy swatch #232E43](https://placehold.co/120x56/232E43/232E43.png) | ![dark blue swatch #49627F](https://placehold.co/120x56/49627F/49627F.png) | ![muted blue swatch #7F95B1](https://placehold.co/120x56/7F95B1/7F95B1.png) | ![light grey swatch #E8E9E5](https://placehold.co/120x56/E8E9E5/E8E9E5.png) |

> 🎨 **Palette in play** — [Winter Beach Wave](https://colorfiind.com/palette/winter-beach-wave-colorway) · `#232E43` `#49627F` `#7F95B1` `#E8E9E5`

You send a dashboard screenshot to a colleague. They reply: "why is the alert series brown?"

&nbsp;

It isn't brown on your screen. Nothing is broken, nobody made a mistake, and both of you are looking at exactly `#DC2F02`. A hex code is not a colour — it's **an instruction to a display**, and the display gets a lot of say in how it's carried out.

&nbsp;

For anyone building charts, heatmaps or status colours, this is not trivia. It decides whether your encoding survives contact with real screens.

&nbsp;

![Section divider bar in navy](https://placehold.co/1000x8/232E43/232E43.png)

## ⛓️ The chain from hex to eyeball

Six things happen between your CSS and someone's retina. Each one can shift the result.

| # | Stage | What varies | Can you control it? |
| :-: | :-- | :-- | :-- |
| 1 | **Colour space of the value** | sRGB unless stated otherwise | ✅ yes |
| 2 | **Panel gamut** | sRGB, P3, or a partial mix | 🚫 no |
| 3 | **Calibration** | how far the panel drifts from its spec | 🚫 no |
| 4 | **White point** | 6500K vs 7500K+ on cheap panels | 🚫 no |
| 5 | **OS colour management** | conversion, night-shift, HDR mode | 🚫 mostly no |
| 6 | **Ambient light** | daylight vs a dim room | 🚫 absolutely not |

You control step one. Steps two to six are the user's world. Good colour engineering means **choosing values whose meaning survives all five uncontrolled steps**.

&nbsp;

![Section divider bar in dark blue](https://placehold.co/1000x8/49627F/49627F.png)

## 🔍 What actually goes wrong

**Gamut stretch.** `#7F95B1` is defined in sRGB. Send it untagged to a wide-gamut panel with colour management off — common on Windows and Android — and the hardware maps those numbers onto *its* wider primaries. Everything becomes more saturated than intended. Your muted slate becomes a chalky periwinkle.

&nbsp;

**White point drift.** Your MacBook targets D65. A cheap TN panel might run noticeably cooler. Every colour picks up a blue cast, and near-neutrals like `#E8E9E5` — which is a hair warm at `oklch(0.932 0.005 117.9)` — flip to reading as slightly cold.

&nbsp;

**Contrast is unreliable at the extremes.** A dark grey at 8% lightness and one at 12% are visibly different on a good IPS panel and can be *identical* on a laptop at 40% brightness in a sunny room. If your chart distinguishes two series by dark shades of the same hue, half your users see one series.

&nbsp;

**Night-shift filters.** A large share of users have a warmth filter running all evening. Nothing you specify survives it, and it hits blues hardest — which is exactly what dashboards are made of.

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/7F95B1/7F95B1.png)

## 🛡️ Encoding that survives all of it

The fix isn't to fight the hardware. It's to encode information in the channel that degrades most gracefully.

&nbsp;

**Lightness is robust. Hue is fragile. Saturation is the most fragile of all.**

&nbsp;

That single sentence rewrites how you build a chart palette:

```diff
- /* four series separated by hue only, all similar lightness */
- --series-1: #4C72B0;   /* L 0.52 */
- --series-2: #55A868;   /* L 0.63 */
- --series-3: #C44E52;   /* L 0.55 */
- --series-4: #8172B2;   /* L 0.56 */

+ /* four series separated by lightness first, hue second */
+ --series-1: #232E43;   /* L 0.30 */
+ --series-2: #49627F;   /* L 0.49 */
+ --series-3: #7F95B1;   /* L 0.66 */
+ --series-4: #E8E9E5;   /* L 0.93 */
```

The palette above is a ready-made example: four steps that are ~0.18–0.27 apart in OKLCH lightness. On a perfectly calibrated P3 monitor they read as a cool blue ramp. On a washed-out laptop in daylight they *still* read as four distinct steps, because lightness ordering survives almost everything — including greyscale printing and most forms of colour vision deficiency.

&nbsp;

Measured against the lightest step:

| Series | On `#E8E9E5` | Grade |
| :-- | :-- | :-- |
| ![navy swatch #232E43](https://placehold.co/70x26/232E43/232E43.png) `#232E43` | **11.16:1** | ✅ AAA |
| ![dark blue swatch #49627F](https://placehold.co/70x26/49627F/49627F.png) `#49627F` | **5.16:1** | ✅ AA |
| ![muted blue swatch #7F95B1](https://placehold.co/70x26/7F95B1/7F95B1.png) `#7F95B1` | **2.52:1** | 🚫 fills and strokes only |

That third row is honest information: `#7F95B1` is a perfectly good area fill or gridline, and a bad choice for a data label.

&nbsp;

![Section divider bar in light grey](https://placehold.co/1000x8/E8E9E5/E8E9E5.png)

## 🧰 Four defensive techniques

**1 · Redundant encoding.** Every colour distinction gets a second cue — a dash pattern, a marker shape, a direct label, an icon:

```css
.series-1 { stroke: var(--series-1); stroke-dasharray: none; }
.series-2 { stroke: var(--series-2); stroke-dasharray: 6 3; }
.series-3 { stroke: var(--series-3); stroke-dasharray: 2 3; }
```

**2 · Direct labelling beats a legend.** A legend forces the reader to match colours across space, which is precisely the task an inaccurate display makes hard. Put the label at the end of the line.

&nbsp;

**3 · Build ramps in OKLCH, with even lightness steps.**

```css
:root {
  --ramp-1: oklch(0.30 0.041 263);
  --ramp-2: oklch(0.49 0.056 253);
  --ramp-3: oklch(0.66 0.049 255);
  --ramp-4: oklch(0.93 0.005 118);
}
```

Even steps in OKLCH lightness are even steps to the eye — on any panel. Even steps in HSL lightness are not.

&nbsp;

**4 · Never encode meaning in saturation alone.** "More saturated = more urgent" is invisible on an uncalibrated screen and on a printout.

&nbsp;

![Section divider bar in navy](https://placehold.co/1000x8/232E43/232E43.png)

## 🖨️ The two-minute test suite

```console
$ # 1 · greyscale — the fastest proxy for "any bad display"
    DevTools → Rendering → Emulate vision deficiencies → Achromatopsia

$ # 2 · reduce brightness to ~30% and step back a metre
    if two series merge, they will merge for someone else too

$ # 3 · print it to a black-and-white printer
    still the most brutal and most honest test there is

$ # 4 · check the numbers, don't trust your monitor
    #232E43 vs #E8E9E5 → 11.16:1
    #7F95B1 vs #E8E9E5 →  2.52:1
```

If a chart passes the greyscale test, monitor variance stops being your problem.

&nbsp;

{% details 🧑‍🔬 A quick script to audit an existing chart palette %}

```js
import { contrast, oklchLightness } from './color-utils.js';

export function auditPalette(colors, background) {
  const rows = colors.map((c) => ({
    color: c,
    L: +oklchLightness(c).toFixed(3),
    onBg: contrast(c, background),
  }));

  // flag any two series closer than 0.12 in lightness
  const collisions = [];
  for (let i = 0; i < rows.length; i++) {
    for (let j = i + 1; j < rows.length; j++) {
      if (Math.abs(rows[i].L - rows[j].L) < 0.12) collisions.push([rows[i].color, rows[j].color]);
    }
  }
  return { rows, collisions };
}

auditPalette(['#232E43', '#49627F', '#7F95B1', '#E8E9E5'], '#FFFFFF');
// collisions: []  ← every series is at least 0.12 apart in lightness
```

Run it in CI and a well-meaning "let's make series 3 a nicer blue" PR can't quietly break the encoding.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in dark blue](https://placehold.co/1000x8/49627F/49627F.png)

## 🎯 What about matching a brand colour exactly?

Sometimes you genuinely need "this must be *our* red". Then:

- Tag your images with an ICC profile and export as sRGB.
- Specify CSS colours knowing they're interpreted as sRGB by default.
- Accept that a screen match is approximate — pick the colour that's *robust*, not the one that's perfect on the art director's monitor.
- For physical products, the screen is a preview, never a proof. That's what printed samples are for.

&nbsp;

![Section divider bar in muted blue](https://placehold.co/1000x8/7F95B1/7F95B1.png)

## 🧾 The checklist

1. A hex is an instruction, not a colour. Five of six links in the chain are outside your control.
2. Encode with **lightness first**, hue second, saturation never alone.
3. Keep chart series ≥ 0.12 apart in OKLCH lightness.
4. Add a redundant cue — dash, shape, direct label — to every colour distinction.
5. Test in greyscale, at low brightness, and on paper.
6. Measure contrast numerically; your monitor is not a measuring device.

Palettes that are built as ordered ramps — rather than four colours that merely look nice together — are the ones that hold up. [ColorFiind](https://colorfiind.com) labels each palette's contrast and tone structure, which makes picking a ramp-shaped one much faster.

&nbsp;

{% cta https://colorfiind.com %} Find a ramp-shaped palette for charts {% endcta %}

&nbsp;

*What's the worst "it looked fine on my screen" bug you've shipped? Mine was a light grey that was pure white on half the team's laptops.* 👇
