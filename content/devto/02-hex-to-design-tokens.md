---
title: "From HEX to Design Tokens: One Palette, Six Formats, Five Minutes"
published: false
description: "Turn four hex codes into CSS custom properties, a semantic layer, Tailwind config, SCSS, JSON design tokens and a working dark mode — without retyping a colour."
tags: css, tailwindcss, webdev, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#0F172A` | `#00F5D4` | `#F15BB5` | `#9B5DE5` |
| :--: | :--: | :--: | :--: |
| ![navy swatch #0F172A](https://placehold.co/120x56/0F172A/0F172A.png) | ![vivid teal swatch #00F5D4](https://placehold.co/120x56/00F5D4/00F5D4.png) | ![vivid magenta swatch #F15BB5](https://placehold.co/120x56/F15BB5/F15BB5.png) | ![indigo swatch #9B5DE5](https://placehold.co/120x56/9B5DE5/9B5DE5.png) |

> 🎨 **Palette in play** — midnight, mint, magenta, violet · `#0F172A` `#00F5D4` `#F15BB5` `#9B5DE5`

Four hex codes is not a theme. A theme is four hex codes that survived a rename, a framework, a dark mode toggle and a designer asking "can we make the accent 10% lighter everywhere?"

&nbsp;

This post is the pipeline I run every time I pull a palette from anywhere. Same four colors, six formats, and the important part — **the naming step in the middle that makes all the rest boring**.

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00F5D4/00F5D4.png)

## 📐 The pipeline

```console
$ palette → raw vars → semantic names → framework config → tokens.json → dark mode
   1 min     10 sec      2 min           1 min             30 sec       auto
```

| Swatch | HEX | On `#0F172A` | Grade | Job |
| :----: | :-- | :----------- | :---: | :-- |
| ![navy swatch #0F172A](https://placehold.co/110x44/0F172A/0F172A.png) | `#0F172A` | — | — | surface |
| ![vivid teal swatch #00F5D4](https://placehold.co/110x44/00F5D4/00F5D4.png) | `#00F5D4` | **12.76:1** | ✅ AAA | primary |
| ![vivid magenta swatch #F15BB5](https://placehold.co/110x44/F15BB5/F15BB5.png) | `#F15BB5` | **5.87:1** | ✅ AA | accent |
| ![indigo swatch #9B5DE5](https://placehold.co/110x44/9B5DE5/9B5DE5.png) | `#9B5DE5` | **4.33:1** | 🟡 AA Large | support |

&nbsp;

![Section divider bar in vivid magenta](https://placehold.co/1000x8/F15BB5/F15BB5.png)

## 1️⃣ Layer one — the raw palette

Write the raw palette down exactly once. **Copy it verbatim and do not touch it again.** It is your receipt: it says exactly which palette this theme came from.

&nbsp;

**`styles/palette.css`** ⌁ *raw, never referenced by components*

```css
/* Palette: midnight, mint, magenta, violet
 */
:root {
  --palette-color-1: #0F172A;
  --palette-color-2: #00F5D4;
  --palette-color-3: #F15BB5;
  --palette-color-4: #9B5DE5;
}
```

&nbsp;

![Section divider bar in indigo](https://placehold.co/1000x8/9B5DE5/9B5DE5.png)

## 2️⃣ Layer two — the semantic layer

This is where the value is. Raw hexes describe *what a color is*; semantic tokens describe *what it does*. Components only ever see layer two, which is why swapping palettes later costs one file instead of forty.

&nbsp;

**`styles/theme.css`** ⌁ *the only layer components import*

```css
:root {
  /* surfaces */
  --color-surface:      var(--palette-color-1);
  --color-surface-alt:  #16213E;
  --color-elevated:     #1B2740;

  /* text */
  --color-ink:          #F8FAFC;
  --color-ink-muted:    #94A3B8;

  /* brand */
  --color-primary:      var(--palette-color-2);
  --color-primary-ink:  #04231D;   /* text ON primary */
  --color-accent:       var(--palette-color-3);
  --color-support:      var(--palette-color-4);

  /* state */
  --color-focus:        var(--palette-color-2);
  --color-danger:       #FF4D6D;

  /* elevation + motion, so the theme owns the whole feel */
  --shadow-glow:        0 0 0 1px color-mix(in srgb, var(--color-primary) 40%, transparent),
                        0 8px 30px -12px var(--color-primary);
  --radius:             12px;
}
```

> 🧪 **Lab note** — `--color-primary-ink` is the token people forget. Every brand color needs a partner that is readable *on top of it*. `#00F5D4` is bright: white text on it is **1.4:1** (invisible), while `#04231D` on it is **11.88:1**. Store the answer once instead of rediscovering it in every button.

&nbsp;

![Section divider bar in light magenta](https://placehold.co/1000x8/E0AED7/E0AED7.png)

## 3️⃣ Layer three — the component layer

Components consume semantics and nothing else. Notice there is not a single hex below.

&nbsp;

**`components/button.css`** ⌁ *zero hardcoded colors*

```css
.btn {
  background: var(--color-primary);
  color: var(--color-primary-ink);
  border: 0;
  border-radius: var(--radius);
  padding: 0.75rem 1.5rem;
  font-weight: 650;
  transition: filter 150ms ease, box-shadow 150ms ease;
}

.btn:hover   { filter: brightness(1.08); box-shadow: var(--shadow-glow); }
.btn:focus-visible {
  outline: 3px solid var(--color-focus);
  outline-offset: 3px;
}

.btn--accent  { background: var(--color-accent);  color: #1A0B12; }
.btn--support { background: var(--color-support); color: #FFFFFF; }
.btn--ghost {
  background: transparent;
  color: var(--color-primary);
  box-shadow: inset 0 0 0 1.5px currentColor;
}
```

Here is the refactor that usually happens at this point, in the format that makes it obvious:

```diff
  .pricing-card__badge {
-   background: #F15BB5;
-   color: #ffffff;
-   border: 1px solid #9B5DE5;
-   border-radius: 12px;
+   background: var(--color-accent);
+   color: #1A0B12;
+   border: 1px solid var(--color-support);
+   border-radius: var(--radius);
  }
```

Two wins in four lines: the palette became swappable, **and** the white-on-pink text (3.04:1 — a fail for body copy) became near-black on pink at **6.27:1**.

&nbsp;

![Section divider bar in dark red](https://placehold.co/1000x8/994129/994129.png)

## 4️⃣ Tailwind, SCSS, and everything else

Same four colors, wearing different clothes.

&nbsp;

**`tailwind.config.js`** ⌁ *semantic names all the way down*

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./src/**/*.{html,js,jsx,ts,tsx,vue,svelte}'],
  theme: {
    extend: {
      colors: {
        surface: { DEFAULT: '#0F172A', alt: '#16213E', elevated: '#1B2740' },
        primary: { DEFAULT: '#00F5D4', ink: '#04231D' },
        accent:  '#F15BB5',
        support: '#9B5DE5',
      },
      boxShadow: {
        glow: '0 8px 30px -12px #00F5D4',
      },
    },
  },
};
```

```html
<button class="bg-primary text-primary-ink hover:shadow-glow rounded-xl px-6 py-3 font-semibold">
  Deploy
</button>
```

{% details 🎁 SCSS, Styled Components, CSS-in-JS and Sass maps %}

```scss
// _palette.scss — Neon Diner Harmony
$surface: #0F172A;
$primary: #00F5D4;
$accent:  #F15BB5;
$support: #9B5DE5;

$theme: (
  'surface': $surface,
  'primary': $primary,
  'accent':  $accent,
  'support': $support,
);

@function theme($key) {
  @return map-get($theme, $key);
}

.badge { background: theme('accent'); }
```

```ts
// theme.ts — typed, so a typo is a build error
export const theme = {
  surface: '#0F172A',
  primary: '#00F5D4',
  accent:  '#F15BB5',
  support: '#9B5DE5',
} as const;

export type ThemeColor = keyof typeof theme;
```

{% enddetails %}

&nbsp;

![Section divider bar in vivid teal](https://placehold.co/1000x8/00F5D4/00F5D4.png)

## 5️⃣ tokens.json — the format that outlives your framework

If design tooling is anywhere near your project, export W3C-style design tokens. Style Dictionary, Figma plugins and Storybook all speak this.

&nbsp;

**`tokens/palette.tokens.json`** ⌁ *the portable version*

```json
{
  "$schema": "https://design-tokens.github.io/community-group/format/",
  "palette": {
    "$description": "Neon Diner Harmony",
    "surface": { "$type": "color", "$value": "#0F172A" },
    "primary": { "$type": "color", "$value": "#00F5D4" },
    "accent":  { "$type": "color", "$value": "#F15BB5" },
    "support": { "$type": "color", "$value": "#9B5DE5" }
  }
}
```

```console
$ npx style-dictionary build
css
  ✔ build/css/variables.css
ios
  ✔ build/ios/StyleDictionaryColor.h
android
  ✔ build/android/colors.xml
```

&nbsp;

![Section divider bar in vivid magenta](https://placehold.co/1000x8/F15BB5/F15BB5.png)

## 6️⃣ Light mode for free

Because components never touch hexes, an entire second theme is one media query. Swap the neon palette for a light one — here a soft quartz set — and keep every semantic name identical.

| `#F5F7F9` | `#E3DFF1` | `#E0AED7` | `#994129` |
| :--: | :--: | :--: | :--: |
| ![off-white swatch #F5F7F9](https://placehold.co/120x56/F5F7F9/F5F7F9.png) | ![pale indigo swatch #E3DFF1](https://placehold.co/120x56/E3DFF1/E3DFF1.png) | ![light magenta swatch #E0AED7](https://placehold.co/120x56/E0AED7/E0AED7.png) | ![dark red swatch #994129](https://placehold.co/120x56/994129/994129.png) |

```css
:root {
  color-scheme: dark light;
}

@media (prefers-color-scheme: light) {
  :root {
    --color-surface:     #F5F7F9;
    --color-surface-alt: #E3DFF1;
    --color-ink:         #241B2F;
    --color-ink-muted:   #5B5468;
    --color-primary:     #994129;   /* 6.22:1 on #F5F7F9 ✅ */
    --color-primary-ink: #FFFFFF;
    --color-accent:      #E0AED7;
    --color-support:     #7A5AA8;
  }
}

/* user override beats the OS */
[data-theme='light'] { /* …same block… */ }
```

```js
// theme-toggle.js
const KEY = 'theme';
const saved = localStorage.getItem(KEY);
if (saved) document.documentElement.dataset.theme = saved;

document.querySelector('#toggle').addEventListener('click', () => {
  const next = document.documentElement.dataset.theme === 'light' ? 'dark' : 'light';
  document.documentElement.dataset.theme = next;
  localStorage.setItem(KEY, next);
});
```

> 🚧 **Trap** — do not reuse the same accent across both modes without re-checking it. `#00F5D4` is **12.76:1** on the dark surface and **1.4:1** on white. Same color, opposite verdict. Each mode needs its own contrast pass.

&nbsp;

![Section divider bar in indigo](https://placehold.co/1000x8/9B5DE5/9B5DE5.png)

## 🤖 Automate the boring 90%

Everything above is deterministic, so it can be a script. Feed it a palette, get every format out:

```js
#!/usr/bin/env node
// palette-to-tokens.mjs — node palette-to-tokens.mjs 0F172A 00F5D4 F15BB5 9B5DE5
import { writeFileSync } from 'node:fs';

const NAMES = ['surface', 'primary', 'accent', 'support'];
const colors = process.argv.slice(2).map((c) => '#' + c.replace('#', '').toUpperCase());
const entries = NAMES.map((n, i) => [n, colors[i]]).filter(([, v]) => v);

const css = `:root {\n${entries.map(([n, v]) => `  --color-${n}: ${v};`).join('\n')}\n}\n`;
const tokens = Object.fromEntries(entries.map(([n, v]) => [n, { $type: 'color', $value: v }]));
const tw = `module.exports = { theme: { extend: { colors: ${JSON.stringify(
  Object.fromEntries(entries), null, 2)} } } };\n`;

writeFileSync('theme.css', css);
writeFileSync('tokens.json', JSON.stringify({ palette: tokens }, null, 2));
writeFileSync('tailwind.colors.js', tw);
console.log('✔ theme.css  ✔ tokens.json  ✔ tailwind.colors.js');
```

```console
$ node palette-to-tokens.mjs 0F172A 00F5D4 F15BB5 9B5DE5
✔ theme.css  ✔ tokens.json  ✔ tailwind.colors.js
```

&nbsp;

![Section divider bar in light magenta](https://placehold.co/1000x8/E0AED7/E0AED7.png)

## 🧾 The checklist

1. Write the raw palette into `palette.css` once. Keep the source link in a comment.
2. Never let a component import layer one.
3. Every brand color gets an `-ink` partner that is readable on top of it.
4. Export `tokens.json` the moment a designer is involved.
5. Re-run contrast per mode, not per color.
6. Script the conversion — you will do it again next project.

Next up: seasonal palettes as a theming system — why *soft summer* makes a beautiful reading app and *deep winter* makes the best dark mode you have shipped.
