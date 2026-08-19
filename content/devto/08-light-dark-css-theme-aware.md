---
title: "light-dark() in CSS: One Line Instead of Two Theme Blocks"
published: false
description: "light-dark() puts both theme values in a single declaration. Here's how to convert an existing dark mode to it, wire a three-way toggle, and avoid the color-scheme trap."
tags: css, webdev, darkmode, design
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#0D1C1A` | `#162C46` | `#BC22BF` | `#B2F1B1` |
| :--: | :--: | :--: | :--: |
| ![near-black swatch #0D1C1A](https://placehold.co/120x56/0D1C1A/0D1C1A.png) | ![navy swatch #162C46](https://placehold.co/120x56/162C46/162C46.png) | ![magenta swatch #BC22BF](https://placehold.co/120x56/BC22BF/BC22BF.png) | ![light green swatch #B2F1B1](https://placehold.co/120x56/B2F1B1/B2F1B1.png) |

> 🎨 **Palette in play** — [Aurora Whisper Moment](https://colorfiind.com/palette/aurora-whisper-moment) · `#0D1C1A` `#162C46` `#BC22BF` `#B2F1B1`

Every dark mode implementation has the same shape: a block of tokens, then a media query that redefines most of them. The two blocks drift. Somebody adds `--border-subtle` to the light block on Tuesday and nobody adds it to the dark block until a user files a bug about an invisible divider.

&nbsp;

`light-dark()` collapses the pair into one declaration, so the two values live on the same line and can't get separated.

&nbsp;

![Section divider bar in navy](https://placehold.co/1000x8/162C46/162C46.png)

## ✍️ Before and after

```diff
  :root {
-   --page:    #FFFFFF;
-   --ink:     #0D1C1A;
-   --accent:  #BC22BF;
-   --border:  #E4E7EC;
+   color-scheme: light dark;
+   --page:    light-dark(#FFFFFF, #0D1C1A);
+   --ink:     light-dark(#0D1C1A, #EAF2EF);
+   --accent:  light-dark(#8E1A91, #BC22BF);
+   --border:  light-dark(#E4E7EC, #22384F);
  }
-
- @media (prefers-color-scheme: dark) {
-   :root {
-     --page:   #0D1C1A;
-     --ink:    #EAF2EF;
-     --accent: #BC22BF;
-     --border: #22384F;
-   }
- }
```

Twenty-one lines become five, and adding a new token means writing both of its values in the same breath.

&nbsp;

![Section divider bar in dark purple](https://placehold.co/1000x8/BC22BF/BC22BF.png)

## ⚠️ The one thing everybody misses

**`light-dark()` does nothing without `color-scheme`.** The function doesn't read the OS preference directly — it reads the *used* colour scheme of the element, which `color-scheme` establishes.

```css
:root {
  color-scheme: light dark;   /* ← without this, light-dark() always returns the light value */
}
```

Declaring `color-scheme: light dark` also tells the browser to restyle form controls, scrollbars, spellcheck underlines and the address bar to match. That is a genuine bonus: it fixes the classic "beautiful dark theme, blinding white `<select>`" bug that no amount of custom CSS ever quite solved.

&nbsp;

![Section divider bar in light green](https://placehold.co/1000x8/B2F1B1/B2F1B1.png)

## 🎚️ A three-way toggle, in twelve lines of CSS

The real payoff is the user override. Because `color-scheme` cascades, forcing a theme is a single property on one element:

```css
:root            { color-scheme: light dark; }   /* follow the OS */
:root[data-theme='light'] { color-scheme: light; }
:root[data-theme='dark']  { color-scheme: dark; }

body {
  background: light-dark(#F7FAF9, #0D1C1A);
  color:      light-dark(#0D1C1A, #EAF2EF);
}

.card {
  background: light-dark(#FFFFFF, #162C46);
  border: 1px solid light-dark(#E4E7EC, #22384F);
  box-shadow: light-dark(0 1px 3px rgb(0 0 0 / 0.10), 0 1px 3px rgb(0 0 0 / 0.55));
}

.badge {
  background: light-dark(#B2F1B1, #143C2A);
  color:      light-dark(#0D3B24, #B2F1B1);
}
```

```js
// theme-toggle.js — three states, no class juggling
const root = document.documentElement;
const saved = localStorage.getItem('theme');          // 'light' | 'dark' | null
if (saved) root.dataset.theme = saved;

document.querySelector('#theme').addEventListener('change', (e) => {
  const value = e.target.value;                        // 'system' | 'light' | 'dark'
  if (value === 'system') {
    delete root.dataset.theme;
    localStorage.removeItem('theme');
  } else {
    root.dataset.theme = value;
    localStorage.setItem('theme', value);
  }
});
```

Notice what's missing: no `classList.toggle('dark')` on every component, no second stylesheet, no `prefers-color-scheme` listener. The `color-scheme` property is the switch, and every `light-dark()` in the document follows it.

&nbsp;

![Section divider bar in dark purple](https://placehold.co/1000x8/8E1A91/8E1A91.png)

## 🧮 Which values actually change?

Converting a theme is a good moment to audit. Not every token needs two values:

| Token | Light | Dark | Two values needed? |
| :-- | :-- | :-- | :-: |
| `--page` | `#F7FAF9` ![off-white swatch #F7FAF9](https://placehold.co/50x20/F7FAF9/F7FAF9.png) | `#0D1C1A` ![near-black swatch #0D1C1A](https://placehold.co/50x20/0D1C1A/0D1C1A.png) | ✅ |
| `--ink` | `#0D1C1A` ![near-black swatch #0D1C1A](https://placehold.co/50x20/0D1C1A/0D1C1A.png) | `#EAF2EF` ![off-white swatch #EAF2EF](https://placehold.co/50x20/EAF2EF/EAF2EF.png) | ✅ |
| `--accent` | `#8E1A91` ![deep magenta swatch #8E1A91](https://placehold.co/50x20/8E1A91/8E1A91.png) | `#BC22BF` ![magenta swatch #BC22BF](https://placehold.co/50x20/BC22BF/BC22BF.png) | ✅ contrast flips |
| `--success` | `#0D3B24` ![deep green swatch #0D3B24](https://placehold.co/50x20/0D3B24/0D3B24.png) | `#B2F1B1` ![light green swatch #B2F1B1](https://placehold.co/50x20/B2F1B1/B2F1B1.png) | ✅ |
| `--radius`, `--space` | `12px` | `12px` | 🚫 not a colour |

The accent row is the important one. `#BC22BF` on the dark page is **3.44:1** — fine for large text and icons. The same magenta on white is **5.10:1**, but its *darker* sibling `#8E1A91` reaches **7.76:1**, which is what you actually want for body-sized links in light mode. **One brand colour, two shipped values.**

> 🚧 **Trap** — `light-dark()` only takes `<color>` values. You cannot swap an image, a shadow spec, a gradient or a border-width with it. Shadows work only because a shadow's *colour* can be a `light-dark()` — the full `box-shadow` value above swaps because both variants are written out in full.

&nbsp;

![Section divider bar in navy](https://placehold.co/1000x8/162C46/162C46.png)

## 🤝 It composes with the other Level 5 functions

This is where modern CSS colour stops feeling like separate features:

```css
:root {
  color-scheme: light dark;
  --brand: light-dark(#8E1A91, #BC22BF);

  /* derived once, correct in both themes */
  --brand-hover:   oklch(from var(--brand) calc(l - 0.08) c h);
  --brand-surface: color-mix(in oklch, Canvas, var(--brand) 8%);
  --brand-border:  color-mix(in oklch, var(--brand) 35%, transparent);
}
```

`--brand` picks the right base for the current scheme; relative colour syntax derives the hover state from whichever one won; `color-mix()` tints the *current* canvas. Three functions, one source of truth, both themes.

&nbsp;

{% details 🧯 Fallback for browsers without light-dark() %}

```css
:root {
  color-scheme: light dark;

  /* fallback: the old two-block approach */
  --page: #FFFFFF;
  --ink:  #0D1C1A;
}

@media (prefers-color-scheme: dark) {
  :root { --page: #0D1C1A; --ink: #EAF2EF; }
}

/* progressive enhancement: overrides the above where supported */
@supports (color: light-dark(#fff, #000)) {
  :root {
    --page: light-dark(#FFFFFF, #0D1C1A);
    --ink:  light-dark(#0D1C1A, #EAF2EF);
  }
}
```

Slightly more code than either approach alone, but it degrades cleanly and you delete the fallback block later without touching components.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in dark purple](https://placehold.co/1000x8/BC22BF/BC22BF.png)

## 🧪 How to test it properly

```console
$ # 1. DevTools → Rendering → Emulate prefers-color-scheme
$ # 2. Then flip the OS setting too — they can disagree
$ # 3. Force the override and confirm color-scheme actually changed:
document.documentElement.dataset.theme = 'dark';
getComputedStyle(document.documentElement).colorScheme;   // "dark"
$ # 4. Check a native control, not just your own components:
$ #    <select>, <input type="date">, the scrollbar, a checkbox
```

Step 4 catches the most bugs. Custom components almost always get themed; native controls only follow if `color-scheme` is set correctly.

&nbsp;

![Section divider bar in light green](https://placehold.co/1000x8/B2F1B1/B2F1B1.png)

## 🧾 The checklist

1. Set `color-scheme: light dark` on `:root` first — `light-dark()` is inert without it.
2. Convert token by token; delete the `prefers-color-scheme` block only when it's empty.
3. Override with `color-scheme` on a data attribute, not by toggling classes on components.
4. Re-check contrast in **both** values — a brand colour rarely works unchanged in both themes.
5. Remember it's colours only. Shadows swap because their colour does.
6. Test native form controls and scrollbars, not only your own CSS.

Building a two-theme palette from scratch is the slow part. Browsing paired light and dark sets — like the [dark](https://colorfiind.com/category/dark) and [light](https://colorfiind.com/category/light) categories on [ColorFiind](https://colorfiind.com) — gives you both halves of each `light-dark()` pair in one go.

&nbsp;

{% cta https://colorfiind.com/category/dark %} Find a dark palette to pair {% endcta %}

&nbsp;

*How many tokens does your dark mode redefine? If it's more than fifteen, `light-dark()` will pay for itself this afternoon.* 👇
