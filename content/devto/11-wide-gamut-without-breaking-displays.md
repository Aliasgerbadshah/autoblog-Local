---
title: "Ship Wide-Gamut Colour Without Breaking Every Older Display"
published: false
description: "A P3 enhancement that looks incredible on your MacBook can look broken on a five-year-old office monitor. Here's the layering order, the QA matrix and the traps."
tags: css, webdev, design, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#03071E` | `#DC2F02` | `#F48C06` | `#FFBA08` |
| :--: | :--: | :--: | :--: |
| ![near-black swatch #03071E](https://placehold.co/120x56/03071E/03071E.png) | ![dark red swatch #DC2F02](https://placehold.co/120x56/DC2F02/DC2F02.png) | ![vivid orange swatch #F48C06](https://placehold.co/120x56/F48C06/F48C06.png) | ![vivid orange swatch #FFBA08](https://placehold.co/120x56/FFBA08/FFBA08.png) |

> 🎨 **Palette in play** — [Pine Field Gallery](https://colorfiind.com/palette/pine-field-gallery) · `#03071E` `#DC2F02` `#F48C06` `#FFBA08`

Wide-gamut colour has a specific failure mode, and it isn't "old browsers see nothing". It's this: the designer builds on a P3 MacBook, everything sings, and three weeks later a customer on a 2019 office monitor reports that the primary button and the danger button now look like the same orange.

&nbsp;

Nothing crashed. The enhancement simply collapsed two distinct colours into one on a narrower screen.

&nbsp;

Here's how to add wide-gamut colour so the degraded version is still a *designed* version.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/DC2F02/DC2F02.png)

## 🧱 Rule one: the sRGB value is the product

Treat P3 the way you treat a hover state — an improvement on top of something that already works.

```css
.btn-primary {
  background: #F48C06;                                   /* the product */
}

@media (color-gamut: p3) {
  .btn-primary {
    background: color(display-p3 0.9017 0.5690 0.2094);  /* the enhancement */
  }
}
```

Those P3 numbers are `#F48C06` converted exactly — same colour, wider container. That's your **baseline**, the safe starting point you then push outward from. Pushing before you've matched is how the two-oranges bug happens.

&nbsp;

![Section divider bar in vivid orange](https://placehold.co/1000x8/F48C06/F48C06.png)

## 📏 Rule two: enhance chroma, never lightness or hue

The three channels behave very differently when a display can't keep up.

| You change | On a P3 screen | On an sRGB screen | Risk |
| :-- | :-- | :-- | :-- |
| **Chroma** (saturation) | richer | clips to the nearest sRGB colour | 🟢 low — same hue, same lightness |
| **Lightness** | brighter | clips, contrast ratio shifts | 🔴 high — can break WCAG |
| **Hue** | different colour | different colour, differently | 🔴 high — brand drift |

So the safe enhancement is: **same hue, same lightness, more chroma.** In OKLCH that's mechanical:

```css
:root {
  --flame: #DC2F02;                                     /* oklch(0.581 0.213 33.1) */
}

@media (color-gamut: p3) {
  :root {
    --flame: oklch(0.581 0.26 33.1);                    /* +22% chroma, nothing else */
  }
}
```

&nbsp;

![Section divider bar in vivid orange](https://placehold.co/1000x8/FFBA08/FFBA08.png)

## 🧮 Rule three: check that your colours stay *distinguishable*

This is the check nobody runs. Two colours that are clearly different in P3 can converge when clipped into sRGB. Test the pairs, not the individual colours:

```js
// gamut-collapse.test.js — do these stay different after sRGB clipping?
import { clipToSrgb, deltaE } from './color-utils.js';

const p3Pairs = [
  ['oklch(0.68 0.26 36)',  'oklch(0.74 0.24 61)'],   // flame vs amber
  ['oklch(0.83 0.20 81)',  'oklch(0.74 0.24 61)'],   // gold vs amber
];

for (const [a, b] of p3Pairs) {
  const distance = deltaE(clipToSrgb(a), clipToSrgb(b));
  it(`${a} stays distinct from ${b}`, () => {
    expect(distance).toBeGreaterThan(10);              // ~10 ΔE = comfortably different
  });
}
```

If the assertion fails, your two P3 colours are relying on gamut you can't guarantee. Pull them apart in **lightness** instead — lightness survives clipping.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/DC2F02/DC2F02.png)

## 🎛️ Rule four: one enhancement layer, not three

The most common mess I see is P3 declared in five places: a media query, an `@supports`, a shorthand fallback, an inline style, and a JS check. They disagree, and debugging is miserable.

&nbsp;

Do it once, at the token layer:

&nbsp;

**`tokens.css`** ⌁ *the only place P3 appears*

```css
:root {
  /* sRGB — the contract */
  --ink:     #03071E;
  --flame:   #DC2F02;
  --amber:   #F48C06;
  --gold:    #FFBA08;
}

@media (color-gamut: p3) {
  :root {
    /* same hue, same lightness, more chroma */
    --flame: oklch(0.581 0.260 33.1);
    --amber: oklch(0.736 0.205 61.1);
    --gold:  oklch(0.831 0.200 81.4);
  }
}
```

Every component keeps using `var(--flame)` and never knows a gamut question exists.

```diff
  .alert--danger {
-   background: #DC2F02;
-   /* plus a duplicated @media block down here somewhere */
+   background: var(--flame);
  }
```

&nbsp;

![Section divider bar in vivid orange](https://placehold.co/1000x8/F48C06/F48C06.png)

## 🖼️ Don't forget the images

CSS is the easy half. Images carry their own colour space, and this is where "broken on old displays" usually comes from:

- **Export web images as sRGB with the profile embedded.** An untagged Display-P3 JPEG is interpreted as sRGB by browsers and renders desaturated and wrong.
- **If you ship P3 images, tag them and provide an sRGB fallback** with `<picture>`:

```html
<picture>
  <source srcset="hero-p3.avif"  type="image/avif" media="(color-gamut: p3)">
  <source srcset="hero-srgb.avif" type="image/avif">
  <img src="hero-srgb.jpg" alt="Product on a workbench" width="1200" height="630">
</picture>
```

- **Watch your image CDN.** Many pipelines strip ICC profiles to save bytes. A P3 file that arrives untagged is a P3 file being displayed as sRGB — the exact failure this post is about.

> 🚧 **Trap** — a P3 photo next to an sRGB-defined CSS background will visibly mismatch on a wide-gamut screen, even though they "match" in your design file. Pick one space per surface.

&nbsp;

![Section divider bar in vivid orange](https://placehold.co/1000x8/FFBA08/FFBA08.png)

## 🧪 The QA matrix that actually catches things

You do not need a lab. You need four checks:

```console
$ # 1 · sRGB emulation (catches the collapse bug)
    DevTools → Rendering → Emulate CSS media feature color-gamut: srgb

$ # 2 · Real narrow-gamut hardware, once per release
    any 2018-2021 office monitor, a budget Android, a Windows laptop

$ # 3 · Forced colours / high contrast mode
    Windows High Contrast — P3 enhancements must simply drop away

$ # 4 · Contrast, measured on the sRGB values only
    #DC2F02 on white → 4.72:1  ✓ AA
    #F48C06 on white → 2.44:1  ✗ decorative or large text only
    #FFBA08 on #03071E → 11.66:1 ✓ AAA
```

That last block is the point of the whole exercise: the amber fails on white regardless of gamut. Wide colour makes it *prettier*, not more readable — and if you only ever review on a P3 screen, the extra vibrance can trick you into thinking it's fine.

&nbsp;

{% details 🧯 Supporting genuinely ancient browsers %}

```css
.badge {
  background: #F48C06;                                  /* everyone */
  background: color(display-p3 0.9 0.57 0.21);          /* dropped by old parsers */
}

/* or gate it explicitly */
@supports (color: color(display-p3 1 1 1)) {
  @media (color-gamut: p3) {
    .badge { background: color(display-p3 0.94 0.58 0.18); }
  }
}
```

`@supports` asks whether the **browser** can parse the syntax; `@media (color-gamut: p3)` asks whether the **display** can show it. They're different questions and you occasionally need both.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/DC2F02/DC2F02.png)

## 🧾 The checklist

1. Ship a complete, verified **sRGB** theme first. P3 is a layer on top.
2. Convert exactly, then push **chroma only** — never lightness or hue.
3. Test that colour *pairs* stay distinguishable after sRGB clipping.
4. Put the enhancement in one token block; components stay gamut-agnostic.
5. Export images as tagged sRGB, or ship both with `<picture>`.
6. Verify your CDN isn't stripping ICC profiles.
7. Measure contrast on the sRGB values, and review on a narrow-gamut screen at least once.

A palette that already reads clearly in sRGB degrades gracefully by definition — that's why I start from published sets like the ones on [ColorFiind](https://colorfiind.com) rather than pushing sliders on a P3 monitor until it looks exciting.

&nbsp;

{% cta https://colorfiind.com %} Start from an sRGB-safe palette {% endcta %}

&nbsp;

*Have you shipped a P3 enhancement that looked wrong on someone else's screen? What gave it away?* 👇
