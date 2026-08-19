---
title: "CSS color-mix(): Create Every Colour Variation From One Token"
published: false
description: "Stop hand-picking hover, tint, border and disabled colours. color-mix() derives them all from one brand token — and the colour space you pick changes everything."
tags: css, webdev, frontend, designsystems
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#003566` | `#0077B6` | `#D0006F` | `#C1121F` |
| :--: | :--: | :--: | :--: |
| ![deep cyan swatch #003566](https://placehold.co/120x56/003566/003566.png) | ![dark cyan swatch #0077B6](https://placehold.co/120x56/0077B6/0077B6.png) | ![vivid pink swatch #D0006F](https://placehold.co/120x56/D0006F/D0006F.png) | ![vivid red swatch #C1121F](https://placehold.co/120x56/C1121F/C1121F.png) |

> 🎨 **Palette in play** — [Cool Winter Harmony](https://colorfiind.com/palette/cool-winter-harmony) · `#003566` `#0077B6` `#D0006F` `#C1121F`

Open the colour section of almost any stylesheet and you'll find the same fossil record: a brand colour, then eleven cousins that somebody eyeballed in a picker at 6pm — `--primary-hover`, `--primary-light`, `--primary-border`, `--primary-disabled`. Change the brand colour and all eleven are quietly wrong.

&nbsp;

`color-mix()` removes that entire category of work. One token in, every variant out, recalculated by the browser whenever the base changes.

&nbsp;

![Section divider bar in deep cyan](https://placehold.co/1000x8/003566/003566.png)

## 🧪 The anatomy

```css
color-mix(in oklch, var(--brand), white 30%)
/*        ^space    ^colour 1     ^colour 2 + how much of it */
```

Three things to know and you have the whole function:

- **The colour space is mandatory.** `in srgb`, `in oklch`, `in oklab`, `in hsl`, `in srgb-linear` and friends.
- **Percentages are optional.** With none, it's a 50/50 mix. With one, the other is inferred.
- **`transparent` counts as a colour.** That's how you get alpha variants without touching `rgba()`.

&nbsp;

![Section divider bar in dark cyan](https://placehold.co/1000x8/0077B6/0077B6.png)

## 🎛️ The colour space is not a detail

Same two colours, same 50%, two very different results:

| Mix | Result | Swatch |
| :-- | :-- | :--: |
| `color-mix(in srgb, #D0006F, #0077B6)` | `#683C92` | ![deep purple swatch #683C92](https://placehold.co/120x40/683C92/683C92.png) |
| `color-mix(in oklch, #D0006F, #0077B6)` | `#8551C7` | ![indigo swatch #8551C7](https://placehold.co/120x40/8551C7/8551C7.png) |

sRGB mixing dips through a muddy middle because it averages gamma-encoded numbers, not perceived colour. OKLCH interpolates lightness, chroma and hue separately, so the midpoint stays as vivid as its parents.

&nbsp;

The rule I use: **`in oklch` for anything a human will look at, `in srgb` only when you need to match a legacy value exactly.**

&nbsp;

Tints and shades show the same split:

| Recipe | sRGB | OKLCH |
| :-- | :-- | :-- |
| `#003566` + white 40% | `#6686A3` ![muted cyan swatch #6686A3](https://placehold.co/60x22/6686A3/6686A3.png) | `#6682A3` ![muted cyan swatch #6682A3](https://placehold.co/60x22/6682A3/6682A3.png) |
| `#0077B6` + black 15% | `#00659B` ![dark cyan swatch #00659B](https://placehold.co/60x22/00659B/00659B.png) | `#005F92` ![dark cyan swatch #005F92](https://placehold.co/60x22/005F92/005F92.png) |
| white + `#0077B6` 8% | `#EBF4F9` ![pale cyan swatch #EBF4F9](https://placehold.co/60x22/EBF4F9/EBF4F9.png) | `#EDF4FA` ![pale cyan swatch #EDF4FA](https://placehold.co/60x22/EDF4FA/EDF4FA.png) |

Close on light tints, meaningfully different on shades — and the OKLCH shade is the one that keeps its blue identity instead of drifting grey.

&nbsp;

![Section divider bar in dark magenta](https://placehold.co/1000x8/D0006F/D0006F.png)

## 🧱 The five patterns that cover 90% of a UI

**`theme.css`** ⌁ *one base colour, five derived roles*

```css
:root {
  --brand: #0077B6;

  /* 1 · interaction states */
  --brand-hover:    color-mix(in oklch, var(--brand), black 15%);
  --brand-active:   color-mix(in oklch, var(--brand), black 28%);

  /* 2 · tinted surfaces — mix INTO the page colour, not the brand */
  --brand-surface:  color-mix(in oklch, Canvas, var(--brand) 8%);
  --brand-raised:   color-mix(in oklch, Canvas, var(--brand) 14%);

  /* 3 · borders and rings — alpha, via transparent */
  --brand-border:   color-mix(in oklch, var(--brand) 35%, transparent);
  --brand-ring:     color-mix(in oklch, var(--brand) 55%, transparent);

  /* 4 · disabled — pull chroma out toward grey */
  --brand-disabled: color-mix(in oklch, var(--brand) 40%, #808080);

  /* 5 · text on brand — the partner colour, derived too */
  --brand-ink:      color-mix(in oklch, var(--brand), black 78%);
}
```

Everything downstream now reads as intent:

```css
.btn {
  background: var(--brand);
  color: white;
  border: 1px solid var(--brand-border);
  border-radius: 10px;
  padding: 0.7rem 1.4rem;
}
.btn:hover           { background: var(--brand-hover); }
.btn:active          { background: var(--brand-active); }
.btn:focus-visible   { outline: 3px solid var(--brand-ring); outline-offset: 2px; }
.btn:disabled        { background: var(--brand-disabled); cursor: not-allowed; }

.callout {
  background: var(--brand-surface);
  border-left: 3px solid var(--brand);
}
```

Rebrand day is now a **one-line diff**:

```diff
  :root {
-   --brand: #0077B6;
+   --brand: #D0006F;
  }
```

Hover, active, surface, border, ring, disabled and ink all move with it, in the same relationships.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/C1121F/C1121F.png)

## 🌗 Where it really pays: theme switching

Because `color-mix()` re-evaluates at computed-value time, mixing against a *variable* background gives you both themes from one rule:

```css
:root {
  color-scheme: light dark;
  --page: #FFFFFF;
  --brand: #0077B6;
  --surface: color-mix(in oklch, var(--page), var(--brand) 8%);
}

@media (prefers-color-scheme: dark) {
  :root {
    --page: #0B1220;
    --brand: #4FA8DC;      /* lift the brand so it survives a dark background */
  }
}
```

`--surface` is written once. In light mode it resolves to a pale blue wash; in dark mode it becomes a deep navy tint. No second block of tokens to keep in sync.

> 🧪 **Lab note** — mixing with the CSS system colours `Canvas` and `CanvasText` instead of literal white and black makes your tints follow forced-colors and user stylesheets for free.

&nbsp;

![Section divider bar in indigo](https://placehold.co/1000x8/8551C7/8551C7.png)

## 🚧 Four traps

**1 · Contrast is not preserved by mixing.** A tint that looks safe at 8% can fail at 20%, and a hover shade can pass while the base only scrapes through. Derive, then measure:

```console
$ contrast "#0077B6" "#FFFFFF"      4.87:1   AA ✓ (but only just)
$ contrast "#005F92" "#FFFFFF"      6.88:1   AA ✓  ← the oklch hover shade
```

**2 · Percentages that don't sum to 100 get normalised.** `color-mix(in oklch, red 20%, blue 20%)` is a 50/50 mix at 40% total alpha, not what most people expect. Specify one percentage and let the other be implicit.

&nbsp;

**3 · Animation needs `@property`.** You cannot transition a custom property containing a colour unless it's registered:

```css
@property --btn-bg {
  syntax: '<color>';
  inherits: false;
  initial-value: #0077B6;
}
.btn { transition: --btn-bg 160ms ease; }
```

**4 · Hue interpolation takes the short way round by default.** Mixing two colours 180° apart can land somewhere you didn't intend. Force it when it matters: `in oklch longer hue`.

&nbsp;

![Section divider bar in dark cyan](https://placehold.co/1000x8/00659B/00659B.png)

## 🧯 Fallbacks

`color-mix()` is Baseline across Chrome, Edge, Safari and Firefox, so for most audiences you can just use it. If you support long-tail browsers, the cascade already does the work — declare the static value first:

```css
.btn {
  background: #005F92;                                    /* old browsers stop here */
  background: color-mix(in oklch, var(--brand), black 15%);
}
```

Or gate a whole block:

```css
@supports (color: color-mix(in oklch, red, blue)) {
  :root { --brand-hover: color-mix(in oklch, var(--brand), black 15%); }
}
```

&nbsp;

![Section divider bar in deep cyan](https://placehold.co/1000x8/005F92/005F92.png)

## 🧾 The checklist

1. Keep **one** brand token per colour. Everything else is derived.
2. Mix `in oklch` unless you're matching a legacy hex exactly.
3. For tints, mix the **page colour** with a little brand — not the brand with a lot of white.
4. Use `transparent` as the second colour for borders, rings and overlays.
5. Re-measure contrast after deriving; the maths doesn't know about WCAG.
6. Register custom properties with `@property` before animating them.

If you need a base worth deriving from, a palette library like [ColorFiind](https://colorfiind.com) gives you four colours that already work together — pick one as `--brand` and let `color-mix()` generate the other eleven.

&nbsp;

{% cta https://colorfiind.com %} Find a base colour to build on {% endcta %}

&nbsp;

*Which variant do you still hand-pick? Post the CSS and I'll show you the mix that replaces it.* 👇
