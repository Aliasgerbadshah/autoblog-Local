---
title: "HWB Color Explained: The Notation That Thinks in Tints and Shades"
published: false
description: "HWB describes a colour as a pure hue plus how much white and black you added. That maps to how designers actually build tint and shade ramps — here's when to use it."
tags: css, design, designsystems, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#FAF2E0` | `#EDB9A1` | `#85CDD6` | `#BAD7AD` |
| :--: | :--: | :--: | :--: |
| ![pale orange swatch #FAF2E0](https://placehold.co/120x56/FAF2E0/FAF2E0.png) | ![light red swatch #EDB9A1](https://placehold.co/120x56/EDB9A1/EDB9A1.png) | ![light cyan swatch #85CDD6](https://placehold.co/120x56/85CDD6/85CDD6.png) | ![light green swatch #BAD7AD](https://placehold.co/120x56/BAD7AD/BAD7AD.png) |

> 🎨 **Palette in play** — [Mint Garden Tone](https://colorfiind.com/palette/mint-garden-tone) · `#FAF2E0` `#EDB9A1` `#85CDD6` `#BAD7AD`

Ask a painter how they made a colour and they won't say "hue 187, saturation 50%, lightness 68%". They'll say: *"that teal, with a bit of white in it."*

&nbsp;

That sentence is HWB. It has been in CSS since 2022, it is supported everywhere, and almost nobody uses it — mostly because nobody explains what it's *for*.

&nbsp;

![Section divider bar in light orange](https://placehold.co/1000x8/EDB9A1/EDB9A1.png)

## 🧪 The notation

```css
hwb(187 52% 16%)
/*  ^hue  ^whiteness  ^blackness   [/ alpha] */
```

- **Hue** — the same 0–360 wheel as HSL. 0 red, 120 green, 240 blue.
- **Whiteness** — how much white is mixed into the pure hue.
- **Blackness** — how much black is mixed in.

`hwb(187 0% 0%)` is the pure, undiluted hue. Add whiteness and you get a **tint**. Add blackness and you get a **shade**. Add both and you get a **tone**. That's the entire vocabulary of traditional colour mixing, expressed in three numbers.

&nbsp;

![Section divider bar in teal](https://placehold.co/1000x8/85CDD6/85CDD6.png)

## 🪜 Ramps you can read at a glance

Take the teal from that palette — `#85CDD6` is `hwb(187 52% 16%)`. Here is its family, and notice that you can predict every swatch from its numbers:

&nbsp;

**Tints — add white**

| CSS | Result | Swatch |
| :-- | :-- | :--: |
| `hwb(187 0% 0%)` | `#00E1FF` | ![vivid cyan swatch #00E1FF](https://placehold.co/120x34/00E1FF/00E1FF.png) |
| `hwb(187 20% 0%)` | `#33E7FF` | ![light cyan swatch #33E7FF](https://placehold.co/120x34/33E7FF/33E7FF.png) |
| `hwb(187 40% 0%)` | `#66EDFF` | ![light cyan swatch #66EDFF](https://placehold.co/120x34/66EDFF/66EDFF.png) |
| `hwb(187 60% 0%)` | `#99F3FF` | ![light cyan swatch #99F3FF](https://placehold.co/120x34/99F3FF/99F3FF.png) |

**Shades — add black**

| CSS | Result | Swatch |
| :-- | :-- | :--: |
| `hwb(187 0% 20%)` | `#00B4CC` | ![cyan swatch #00B4CC](https://placehold.co/120x34/00B4CC/00B4CC.png) |
| `hwb(187 0% 40%)` | `#008799` | ![dark cyan swatch #008799](https://placehold.co/120x34/008799/008799.png) |
| `hwb(187 0% 60%)` | `#005A66` | ![deep cyan swatch #005A66](https://placehold.co/120x34/005A66/005A66.png) |

**Tones — add both**

| CSS | Result | Swatch |
| :-- | :-- | :--: |
| `hwb(187 20% 20%)` | `#33BACC` | ![cyan swatch #33BACC](https://placehold.co/120x34/33BACC/33BACC.png) |
| `hwb(187 30% 30%)` | `#4CA7B2` | ![muted cyan swatch #4CA7B2](https://placehold.co/120x34/4CA7B2/4CA7B2.png) |
| `hwb(187 45% 45%)` | `#73898C` | ![mid grey swatch #73898C](https://placehold.co/120x34/73898C/73898C.png) |

Three columns of numbers, and every one of them is guessable without opening a colour picker. Try that with hex.

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00E1FF/00E1FF.png)

## 🧮 The rule nobody tells you

When whiteness and blackness sum to 100% or more, the hue is irrelevant and CSS returns grey — specifically `w / (w + b)` grey:

```css
hwb(187 70% 70%)   /* → #808080, a plain 50% grey */
hwb(20  60% 60%)   /* → #808080, exactly the same grey */
```

This is a feature, not a bug: it means a tone ramp degrades gracefully into neutrals instead of producing nonsense. It also gives you a neat trick — **a greyscale scale that keeps a hue "in reserve"**:

```css
:root {
  --neutral-100: hwb(187 92%  4%);
  --neutral-300: hwb(187 70% 18%);
  --neutral-500: hwb(187 45% 45%);   /* the greying point */
  --neutral-700: hwb(187 20% 62%);
  --neutral-900: hwb(187  6% 82%);
}
```

Those neutrals carry a faint teal cast that ties them to the brand — the thing greys made from pure `#808080` never do.

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/33E7FF/33E7FF.png)

## 🆚 Where HSL misleads you

HSL's `l` is not perceived lightness, it's a geometric midpoint. That produces the classic trap:

| Colour | HSL | Perceived lightness (OKLCH L) | Relative luminance |
| :-- | :-- | :-- | :-- |
| ![vivid blue swatch #0000FF](https://placehold.co/60x24/0000FF/0000FF.png) `#0000FF` | `hsl(240 100% 50%)` | **0.45** | 0.072 |
| ![vivid yellow swatch #FFFF00](https://placehold.co/60x24/FFFF00/FFFF00.png) `#FFFF00` | `hsl(60 100% 50%)` | **0.97** | 0.928 |

Same "lightness" in HSL. One is nearly black to the eye, the other nearly white — a 12.9× difference in luminance.

&nbsp;

HWB doesn't fix that (it's the same underlying model), but it never *claims* to. `hwb(240 0% 0%)` says "pure blue, nothing added", which is honest. `hsl(240 100% 50%)` says "50% lightness", which is a lie that leads people to build ramps by nudging `l`.

&nbsp;

**The distinction that matters:**

| Question | Notation |
| :-- | :-- |
| "How much white/black did I add?" | **HWB** — describes the recipe |
| "How light does this look?" | **OKLCH** — describes the perception |
| "What do the screen's channels do?" | RGB / hex — describes the hardware |

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/66EDFF/66EDFF.png)

## 🎯 Where HWB earns its place

**1 · Author-facing tokens in a design system.** When designers read the CSS, `hwb(19 63% 7%)` tells them "that peach is mostly white" at a glance. `#EDB9A1` tells them nothing.

&nbsp;

**2 · Deriving a family from one hue.** Lock the hue, vary two numbers:

```css
:root {
  --hue-brand: 187;
  --brand:         hwb(var(--hue-brand)  0%  0%);
  --brand-tint:    hwb(var(--hue-brand) 45%  0%);
  --brand-shade:   hwb(var(--hue-brand)  0% 35%);
  --brand-tone:    hwb(var(--hue-brand) 25% 25%);
}
```

Change `--hue-brand` to `19` and the whole family becomes peach, holding its internal relationships exactly.

&nbsp;

**3 · Relative colour syntax with HWB channels.** `from` works here too:

```css
--softer: hwb(from var(--brand) h calc(w + 25%) b);   /* 25% more white */
--deeper: hwb(from var(--brand) h w calc(b + 20%));   /* 20% more black */
```

**4 · Explaining colour to non-designers.** "Add 20% white" survives a stakeholder meeting. "Reduce chroma to 0.08" does not.

&nbsp;

![Section divider bar in light teal](https://placehold.co/1000x8/99F3FF/99F3FF.png)

## 🚫 Where it doesn't belong

- **Perceptually even ramps.** Equal whiteness steps are not equal *perceived* steps. Use OKLCH for a 9-step scale where each stop must feel one notch apart.
- **Wide-gamut colour.** HWB is an sRGB notation. It cannot express anything outside it — that's `oklch()` or `color(display-p3 …)` territory.
- **Contrast targeting.** Nothing in `w` or `b` tells you the WCAG ratio. Always measure the pairs you ship:

```console
$ contrast "#005A66" "#FFFFFF"     7.91:1   AAA ✓  body text
$ contrast "#00B4CC" "#FFFFFF"     2.50:1   fail   surfaces only
$ contrast "#99F3FF" "#0D1C1A"    13.87:1   AAA ✓  dark-mode text
```

Two of those come from the same hue with only the blackness changed. The notation is intuitive; the accessibility maths still isn't.

&nbsp;

{% details 🧰 Converting hex to HWB in twelve lines %}

```js
export function hexToHwb(hex) {
  const [r, g, b] = hex.replace('#', '').match(/../g).map((h) => parseInt(h, 16) / 255);
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
  let h = 0;
  if (d) {
    if (max === r) h = ((g - b) / d) % 6;
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
  }
  return {
    h: Math.round((h * 60 + 360) % 360),
    w: Math.round(min * 100),
    b: Math.round((1 - max) * 100),
  };
}

hexToHwb('#85CDD6');   // { h: 187, w: 52, b: 16 }
hexToHwb('#FAF2E0');   // { h: 42,  w: 88, b: 2  }
```

Whiteness is just the minimum channel; blackness is one minus the maximum. That's why the model is so easy to reason about.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in dark teal](https://placehold.co/1000x8/00B4CC/00B4CC.png)

## 🧾 The checklist

1. Read HWB as a recipe: **pure hue + this much white + this much black**.
2. Use it for author-facing tokens and hue-locked families.
3. Remember `w + b ≥ 100%` collapses to grey — useful for tinted neutrals.
4. Don't build perceptually even scales with it; that's OKLCH's job.
5. It's sRGB-only, so it can't describe wide-gamut colour.
6. Measure contrast separately, always.

Starting from a palette whose hues already work together makes hue-locked families much easier — [ColorFiind](https://colorfiind.com) publishes each palette's HEX codes, and converting them to HWB takes the twelve lines above.

&nbsp;

{% cta https://colorfiind.com %} Browse palettes to build families from {% endcta %}

&nbsp;

*Have you shipped HWB in production, or is it still the notation you scroll past in MDN?* 👇
