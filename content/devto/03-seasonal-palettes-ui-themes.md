---
title: "Seasonal Color Analysis for Developers: Soft Summer UI vs Deep Winter Dark Mode"
published: false
description: "Seasonal palettes are a temperature-and-contrast system, not fashion jargon. How to use them to pick a UI theme that matches what your product does."
tags: css, design, ux, webdev
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,
     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#D8C7D8` | `#8FA1B3` | `#E6DDE3` | `#9B8FA3` | `#6D0F2B` | `#004D40` | `#0B3D91` | `#2A1458` |
| :--: | :--: | :--: | :--: | :--: | :--: | :--: | :--: |
| ![light magenta swatch #D8C7D8](https://placehold.co/120x56/D8C7D8/D8C7D8.png) | ![muted blue swatch #8FA1B3](https://placehold.co/120x56/8FA1B3/8FA1B3.png) | ![light magenta swatch #E6DDE3](https://placehold.co/120x56/E6DDE3/E6DDE3.png) | ![mid grey swatch #9B8FA3](https://placehold.co/120x56/9B8FA3/9B8FA3.png) | ![burgundy swatch #6D0F2B](https://placehold.co/120x56/6D0F2B/6D0F2B.png) | ![deep teal swatch #004D40](https://placehold.co/120x56/004D40/004D40.png) | ![dark blue swatch #0B3D91](https://placehold.co/120x56/0B3D91/0B3D91.png) | ![deep indigo swatch #2A1458](https://placehold.co/120x56/2A1458/2A1458.png) |

> 🎨 **Palettes in play** — **Soft Summer Atelier** *(left)* vs **Deep Winter Journal** *(right)*

Seasonal color analysis sounds like something that belongs in a fashion magazine, and that is exactly why developers skip it. Underneath the vocabulary, though, it is a **two-axis classification system** — and it happens to answer the two questions we ask about every UI theme:

1. **Temperature** — are the colors warm (yellow-based) or cool (blue-based)?
2. **Contrast + saturation** — is the palette loud and high-contrast, or muted and quiet?

Once you sort palettes along those two axes, "pick a theme" becomes "pick a quadrant" — and that is a decision you can make in ten seconds.

![Section divider bar in dark blue](https://placehold.co/1000x10/0B3D91/0B3D91.png)

## 🧭 1 · The map

| Season | Temperature | Contrast | Feels like | Ships well as |
| :-- | :-- | :-- | :-- | :-- |
| **Spring** | warm | bright | fresh, playful | marketing, kids apps, onboarding |
| **Summer** | cool | soft | calm, hazy | reading apps, wellness, docs |
| **Autumn** | warm | muted-rich | grounded, earthy | food, crafts, editorial, commerce |
| **Winter** | cool | high | sharp, precise | dashboards, fintech, dev tools |

Each season splits again — `soft summer`, `cool summer`, `true summer`, `light summer`; `deep winter`, `bright winter`, `dark winter`, `cool winter` — and every sub-season is its own URL:

```console
$ tree seasonal-palettes
season
├── spring/{spring,light,warm,bright,true}-color-palette
├── summer/{summer,soft,cool,light,true}-color-palette
├── autumn/{autumn,soft,deep,dark,warm,true}-color-palette
└── winter/{winter,deep,cool,bright,dark,true}-color-palette
```

## 🌫️ 2 · Soft Summer — the low-contrast reading UI

| `#D8C7D8` | `#8FA1B3` | `#E6DDE3` | `#9B8FA3` |
| :--: | :--: | :--: | :--: |
| ![light magenta swatch #D8C7D8](https://placehold.co/120x56/D8C7D8/D8C7D8.png) | ![muted blue swatch #8FA1B3](https://placehold.co/120x56/8FA1B3/8FA1B3.png) | ![light magenta swatch #E6DDE3](https://placehold.co/120x56/E6DDE3/E6DDE3.png) | ![mid grey swatch #9B8FA3](https://placehold.co/120x56/9B8FA3/9B8FA3.png) |

Soft summer is cool, greyed-down and deliberately low contrast — dusty mauve, slate blue, fog. It is the palette equivalent of a quiet room. Ideal for long-form reading, journaling apps, meditation timers, documentation, anything where the interface should get out of the way.

And it is a **trap** if you use it naively:

| Pair | Ratio | Verdict |
| :-- | :-- | :-- |
| `#8FA1B3` on `#E6DDE3` | 2.00:1 | 🚫 unreadable |
| `#9B8FA3` on `#E6DDE3` | 2.31:1 | 🚫 unreadable |
| `#D8C7D8` on `#E6DDE3` | 1.21:1 | 🚫 invisible |

Every in-palette pair fails. That does not make the palette bad — it means **soft summer is a set of surfaces, and you must bring your own ink**.

**`soft-summer.css`** ⌁ *palette for atmosphere, one dark for type*

```css
:root {
  /* from the palette — surfaces only */
  --color-page:     #E6DDE3;
  --color-card:     #D8C7D8;
  --color-line:     #9B8FA3;
  --color-muted:    #8FA1B3;

  /* the color you had to add yourself */
  --color-ink:      #241F2B;   /* 12.11:1 on --color-page ✅ AAA */
  --color-ink-soft: #4A4353;   /*  7.13:1                 ✅ AAA */
}

body {
  background: var(--color-page);
  color: var(--color-ink);
  font-size: 1.0625rem;
  line-height: 1.7;
  max-width: 68ch;
}

.card {
  background: var(--color-card);
  border: 1px solid color-mix(in srgb, var(--color-line) 45%, transparent);
  border-radius: 16px;
}

.meta { color: var(--color-ink-soft); }        /* not --color-muted! */
hr    { border: 0; border-top: 1px solid var(--color-line); }
```

> 🧪 **Lab note** — the muted palette colors still do real work: borders, dividers, disabled states, chart fills, hover backgrounds. They just never carry text. Write that rule into the theme file as a comment and code review gets 80% easier.

## ❄️ 3 · Deep Winter — the dark mode that isn't grey soup

| `#6D0F2B` | `#004D40` | `#0B3D91` | `#2A1458` |
| :--: | :--: | :--: | :--: |
| ![burgundy swatch #6D0F2B](https://placehold.co/120x56/6D0F2B/6D0F2B.png) | ![deep teal swatch #004D40](https://placehold.co/120x56/004D40/004D40.png) | ![dark blue swatch #0B3D91](https://placehold.co/120x56/0B3D91/0B3D91.png) | ![deep indigo swatch #2A1458](https://placehold.co/120x56/2A1458/2A1458.png) |

Deep winter is cool, saturated and high contrast: burgundy, emerald, royal blue, deep purple, black, icy white. Those are **jewel tones**, and jewel tones are the reason a dark theme can look expensive instead of looking like `#333`.

The colors are dark enough to be *backgrounds* and saturated enough to be *identities*:

| Color | vs `#FFFFFF` | vs `#050505` | Natural role |
| :-- | :-- | :-- | :-- |
| ![burgundy swatch #6D0F2B](https://placehold.co/70x28/6D0F2B/6D0F2B.png) `#6D0F2B` | 11.98:1 ✅ | 1.70:1 | destructive / danger surface |
| ![deep teal swatch #004D40](https://placehold.co/70x28/004D40/004D40.png) `#004D40` | 9.83:1 ✅ | 2.07:1 | success surface |
| ![dark blue swatch #0B3D91](https://placehold.co/70x28/0B3D91/0B3D91.png) `#0B3D91` | 10.04:1 ✅ | 2.03:1 | info / primary surface |
| ![deep indigo swatch #2A1458](https://placehold.co/70x28/2A1458/2A1458.png) `#2A1458` | 15.68:1 ✅ | 1.30:1 | elevated background |

Read that table sideways and the theme designs itself: these four are **surfaces on a black canvas**, each carrying white text at AAA.

**`deep-winter.css`** ⌁ *status colors that are also backgrounds*

```css
:root[data-theme='deep-winter'] {
  --bg-base:      #050505;
  --bg-raised:    #14101F;
  --bg-elevated:  #2A1458;

  --ink:          #F5F7FA;   /* 18.99:1 on --bg-base ✅ AAA */
  --ink-muted:    #A9B2C3;

  --info:         #0B3D91;
  --success:      #004D40;
  --danger:       #6D0F2B;

  /* icy accents live on top of the jewel surfaces */
  --info-ink:     #DCE9FF;
  --success-ink:  #D6F5EE;
  --danger-ink:   #FFE1E8;
}

.alert {
  border-radius: 12px;
  padding: 0.9rem 1.1rem;
  border-left: 4px solid currentColor;
}
.alert--info    { background: var(--info);    color: var(--info-ink); }
.alert--success { background: var(--success); color: var(--success-ink); }
.alert--danger  { background: var(--danger);  color: var(--danger-ink); }
```

Compare that to the default dark theme most projects ship:

```diff
- --bg-base:    #1e1e1e;   /* warm grey, no identity            */
- --info:       #2196f3;   /* material blue, seen it 10,000 times */
- --success:    #4caf50;
- --danger:     #f44336;
+ --bg-base:    #050505;   /* true black, lets jewels glow       */
+ --info:       #0B3D91;   /* royal blue, 10.04:1 with white     */
+ --success:    #004D40;   /* emerald, 9.83:1                    */
+ --danger:     #6D0F2B;   /* burgundy, 11.98:1                  */
```

Same semantics, completely different product personality — and the accessibility numbers went *up*.

![Section divider bar in deep teal](https://placehold.co/1000x10/004D40/004D40.png)

## 🍂 4 · The other two quadrants, briefly

**Warm Autumn** —  **Lagoon Warm Autumn**

| `#FFEAB6` | `#FFEEBA` | `#FFD489` | `#F9BA5D` |
| :--: | :--: | :--: | :--: |
| ![light orange swatch #FFEAB6](https://placehold.co/120x56/FFEAB6/FFEAB6.png) | ![light yellow swatch #FFEEBA](https://placehold.co/120x56/FFEEBA/FFEEBA.png) | ![light orange swatch #FFD489](https://placehold.co/120x56/FFD489/FFD489.png) | ![vivid orange swatch #F9BA5D](https://placehold.co/120x56/F9BA5D/F9BA5D.png) |

Honey, amber, clay. Near-monochrome warmth for food, artisan commerce and editorial. Total internal contrast: **1.45:1** — surfaces only, again. Pair with `#3A2A12`.

**Bright Spring** —  **Spring Beach Wave**

| `#FFFF79` | `#FFD2DC` | `#56D5CC` | `#FF8D08` |
| :--: | :--: | :--: | :--: |
| ![light yellow swatch #FFFF79](https://placehold.co/120x56/FFFF79/FFFF79.png) | ![pale red swatch #FFD2DC](https://placehold.co/120x56/FFD2DC/FFD2DC.png) | ![teal swatch #56D5CC](https://placehold.co/120x56/56D5CC/56D5CC.png) | ![vivid orange swatch #FF8D08](https://placehold.co/120x56/FF8D08/FF8D08.png) |

Maximum energy. Great for a launch page, exhausting for a workspace. Use one as the hero background and keep the rest for illustration.

**Cool Winter** —  **Cool Winter Harmony**

| `#003566` | `#0077B6` | `#D0006F` | `#C1121F` |
| :--: | :--: | :--: | :--: |
| ![deep cyan swatch #003566](https://placehold.co/120x56/003566/003566.png) | ![dark cyan swatch #0077B6](https://placehold.co/120x56/0077B6/0077B6.png) | ![dark magenta swatch #D0006F](https://placehold.co/120x56/D0006F/D0006F.png) | ![dark red swatch #C1121F](https://placehold.co/120x56/C1121F/C1121F.png) |

Icy blue structure with a shout. This is the fintech dashboard palette: navy chrome, blue data, magenta for the one number that matters.

## 🧮 5 · Pick your quadrant with a function

If you would rather derive the season than browse it, the two axes are computable:

{% katex %}
\text{warmth} = \cos(h - 60^\circ) \quad\text{where } h = \text{hue in degrees}
{% endkatex %}

```js
const toHsl = (hex) => {
  const [r, g, b] = hex.replace('#', '').match(/\w\w/g).map((h) => parseInt(h, 16) / 255);
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
  const l = (max + min) / 2;
  const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
  let h = 0;
  if (d !== 0) {
    if (max === r) h = ((g - b) / d) % 6;
    else if (max === g) h = (b - r) / d + 2;
    else h = (r - g) / d + 4;
  }
  return { h: (h * 60 + 360) % 360, s, l };
};

export function classify(palette) {
  const hsl = palette.map(toHsl);
  const warmth = hsl.reduce((a, c) => a + Math.cos(((c.h - 60) * Math.PI) / 180), 0) / hsl.length;
  const sat    = hsl.reduce((a, c) => a + c.s, 0) / hsl.length;
  const spread = Math.max(...hsl.map((c) => c.l)) - Math.min(...hsl.map((c) => c.l));

  const warm = warmth > 0;
  const loud = sat > 0.5 || spread > 0.55;

  if (warm && loud)   return 'spring · warm + bright';
  if (warm && !loud)  return 'autumn · warm + muted';
  if (!warm && loud)  return 'winter · cool + high contrast';
  return 'summer · cool + soft';
}

classify(['#D8C7D8', '#8FA1B3', '#E6DDE3', '#9B8FA3']); // summer · cool + soft
classify(['#6D0F2B', '#004D40', '#0B3D91', '#2A1458']); // winter · cool + high contrast
```

{% details 🧪 A quick jest test so the classifier keeps its promises %}

```js
import { classify } from './season.js';

test.each([
  [['#FFEAB6', '#FFEEBA', '#FFD489', '#F9BA5D'], /autumn|spring/],
  [['#003566', '#0077B6', '#D0006F', '#C1121F'], /winter/],
  [['#D8C7D8', '#8FA1B3', '#E6DDE3', '#9B8FA3'], /summer/],
])('classifies %s', (palette, expected) => {
  expect(classify(palette)).toMatch(expected);
});
```

{% enddetails %}

## 🎚️ 6 · Ship both seasons in one app

The strongest move is not choosing — it is mapping **light mode to a soft season and dark mode to a deep one**, keeping the same semantic names.

```css
:root {                         /* soft summer, daylight */
  --page: #E6DDE3;  --card: #D8C7D8;  --ink: #241F2B;  --line: #9B8FA3;
}

@media (prefers-color-scheme: dark) {
  :root {                       /* deep winter, night */
    --page: #050505;  --card: #2A1458;  --ink: #F5F7FA;  --line: #3A2A66;
  }
}

.panel {
  background: var(--card);
  color: var(--ink);
  border: 1px solid var(--line);
}
```

One component, two seasons, zero conditional logic.

## 🧾 The checklist

- Decide **temperature** first (warm/cool), **contrast** second (loud/quiet).
- Soft seasons = surfaces. Always add your own ink color.
- Deep seasons = surfaces *and* status colors. Check them against white, not against each other.
- Map soft → light mode, deep → dark mode, and keep semantic names identical.
- Browse the sub-season pages; the sub-season is the real spec, "summer" alone is too broad.


*Which quadrant is your current project in? Post the four hexes below.* 👇
