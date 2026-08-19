---
title: "34,045 Color Palettes, Zero Signups: A Developer's Tour of ColorFiind"
published: false
description: "ColorFiind is a palette browser built for people who ship. Here's the fast path from a random hex craving to copy-ready CSS variables, seasonal themes and downloadable swatch images."
tags: css, webdev, design, beginners
cover_image: https://placehold.co/1000x420/3C0D4F/C6EEA0.png
---

<!-- Cover asset: content/devto/assets/cover-mango-garden-moment.png (1000x420) -->
<!-- Upload it in the DEV editor, then paste the returned URL into cover_image above. -->

![Mango Garden Moment](https://placehold.co/250x110/3C0D4F/3C0D4F.png)![Mango Garden Moment](https://placehold.co/250x110/F22C33/F22C33.png)![Mango Garden Moment](https://placehold.co/250x110/C6EEA0/C6EEA0.png)![Mango Garden Moment](https://placehold.co/250x110/1681DF/1681DF.png)

> 🎨 **Palette in play** — [Mango Garden Moment](https://colorfiind.com/palette/mango-garden-moment) · `#3C0D4F` `#F22C33` `#C6EEA0` `#1681DF`

Picking colors is the part of a side project where an evening quietly disappears. You open a generator, spin the wheel forty times, save three screenshots, and end up shipping the same slate-and-indigo you always ship.

[ColorFiind](https://colorfiind.com) takes a different angle: it is a **browsable library**, not a slot machine. 34,045 named palettes, organised by category and by season, every one of them a real URL you can bookmark, with HEX codes, a downloadable image and a ready-made `:root` block.

This is the developer's tour — what's in there, and how to get from "ooh, that one" to a themed component in about two minutes.

![rule](https://placehold.co/250x12/3C0D4F/3C0D4F.png)![rule](https://placehold.co/250x12/F22C33/F22C33.png)![rule](https://placehold.co/250x12/C6EEA0/C6EEA0.png)![rule](https://placehold.co/250x12/1681DF/1681DF.png)

## 🗺️ 1 · The shape of the site

Four surfaces, and you only ever need to remember the first two.

| Surface | URL pattern | Use it when |
| :-- | :-- | :-- |
| **Home grid** | `colorfiind.com` | You want to browse and get surprised |
| **Palette page** | `/palette/{slug}` | You have picked one and want the codes |
| **Category** | `/category/{pastel\|vintage\|luxury\|retro\|neon\|dark\|light}` | You know the *vibe* |
| **Season** | `/season/{spring\|summer\|autumn\|winter}/...` | You know the *temperature* |

The category list is short on purpose: `pastel`, `vintage`, `luxury`, `retro`, `neon`, `dark`, `light`. Seasons go one level deeper — `soft summer`, `deep winter`, `warm autumn`, `bright spring` and friends — plus a handful of themed sets like `sunset`, `70s`, `christmas`, `brown` and `minecraft`.

```console
$ # the whole IA, as a mental model
colorfiind.com
├── /category/neon
├── /category/pastel
├── /season/winter/deep-winter-color-palette
├── /season/autumn/warm-autumn-color-palette
└── /palette/mango-garden-moment      ← the page that matters
```

## 🔍 2 · What a palette page actually gives you

Take [Mango Garden Moment](https://colorfiind.com/palette/mango-garden-moment). The page is not just four rectangles — it is a small spec sheet:

| Swatch | HEX | RGB | HSL | Contrast vs `#FFFFFF` | Suggested role |
| :----: | :-- | :-- | :-- | :-------------------- | :------------- |
| ![](https://placehold.co/110x44/3C0D4F/3C0D4F.png) | `#3C0D4F` | `60, 13, 79` | `283° 72% 18%` | **15.41:1** ✅ AAA | Base / ink |
| ![](https://placehold.co/110x44/F22C33/F22C33.png) | `#F22C33` | `242, 44, 51` | `358° 88% 56%` | **4.05:1** 🟡 AA Large | Primary |
| ![](https://placehold.co/110x44/C6EEA0/C6EEA0.png) | `#C6EEA0` | `198, 238, 160` | `91° 70% 78%` | **1.30:1** 🚫 | Accent surface |
| ![](https://placehold.co/110x44/1681DF/1681DF.png) | `#1681DF` | `22, 129, 223` | `208° 82% 48%` | **4.01:1** 🟡 AA Large | Link / info |

Alongside the codes, each palette page carries:

- **Mood** — *"Nostalgic mood: muted tones with a warm, aged feeling"*
- **Contrast** — *balanced / high / low*, so you know before you build
- **Main tones** — the dominant color families (`purple, red, green`)
- **Best fit** — *"Heritage design: packaging, cafés, labels, editorial pages"*
- **Pairing tips** — which hex to use for background, text and accents
- **CSS variables** — a `:root` block, already written
- **Related palettes** — twelve neighbours, one click away
- **Copy / Link / Image** — per-color copy, whole-palette copy, and a PNG download

That last row is the one that saves the most time. **Copy** puts the codes on your clipboard, **Image** downloads a shareable swatch card for the design channel, **Link** grabs the permalink for the ticket.

![rule](https://placehold.co/250x12/3C0D4F/3C0D4F.png)![rule](https://placehold.co/250x12/F22C33/F22C33.png)![rule](https://placehold.co/250x12/C6EEA0/C6EEA0.png)![rule](https://placehold.co/250x12/1681DF/1681DF.png)

## ⚡ 3 · The two-minute workflow

**`step 1`** ⌁ *grab the block ColorFiind already wrote for you*

```css
/* straight off the palette page */
:root {
  --palette-color-1: #3C0D4F;
  --palette-color-2: #F22C33;
  --palette-color-3: #C6EEA0;
  --palette-color-4: #1681DF;
}
```

**`step 2`** ⌁ *rename numbers into intentions*

Numbered variables are fine for copying and terrible for maintaining. Give every color a job before it reaches a component:

```diff
  :root {
-   --palette-color-1: #3C0D4F;
-   --palette-color-2: #F22C33;
-   --palette-color-3: #C6EEA0;
-   --palette-color-4: #1681DF;
+   --color-ink:      #3C0D4F;  /* headings, body text        */
+   --color-primary:  #F22C33;  /* buttons, active states     */
+   --color-surface:  #C6EEA0;  /* cards, highlight sections  */
+   --color-link:     #1681DF;  /* links, info, focus rings   */
+   --color-page:     #FFFFFF;
  }
```

**`step 3`** ⌁ *build the component against the names, never the hexes*

```css
.card {
  background: var(--color-surface);
  color: var(--color-ink);
  border: 2px solid var(--color-ink);
  border-radius: 14px;
  padding: 1.5rem;
}

.card__cta {
  background: var(--color-primary);
  color: #fff;
  border-radius: 999px;
  padding: 0.7rem 1.4rem;
}

.card a {
  color: var(--color-link);
  text-underline-offset: 3px;
}
```

`#3C0D4F` on `#C6EEA0` measures **11.84:1** — comfortably AAA, so that card is readable before you even open a contrast checker.

{% details 🧰 The same palette as Tailwind + design tokens %}

```js
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        ink:     '#3C0D4F',
        primary: '#F22C33',
        surface: '#C6EEA0',
        link:    '#1681DF',
      },
    },
  },
};
```

```json
{
  "palette": {
    "$description": "Mango Garden Moment — colorfiind.com/palette/mango-garden-moment",
    "ink":     { "$type": "color", "$value": "#3C0D4F" },
    "primary": { "$type": "color", "$value": "#F22C33" },
    "surface": { "$type": "color", "$value": "#C6EEA0" },
    "link":    { "$type": "color", "$value": "#1681DF" }
  }
}
```

{% enddetails %}

## 🧭 4 · Browsing strategies that beat scrolling

**Strategy A — start from the mood.** You know the app should feel calm and expensive? `/category/luxury` and `/category/vintage`. Should feel like a hackathon at 2am? `/category/neon`.

**Strategy B — start from the season.** Seasonal color analysis is a real system, not just fashion vocabulary. `deep winter` gives you cool, high-contrast jewel tones — the natural home of a dark UI. `soft summer` gives you muted, low-contrast greys and mauves — perfect for a reading app, dangerous for a button.

**Strategy C — start from one palette and walk.** Every palette page has a *Related Palettes* grid. Land on one you like, then walk the neighbourhood until the contrast is right. This is the fastest route to "same feeling, better legibility".

**Strategy D — start from the structure you need.** Look at how the four colors relate before you look at whether they're pretty:

| Palette | Structure | Best used as |
| :-- | :-- | :-- |
| ![](https://placehold.co/70x28/232E43/232E43.png)![](https://placehold.co/70x28/49627F/49627F.png)![](https://placehold.co/70x28/7F95B1/7F95B1.png)![](https://placehold.co/70x28/E8E9E5/E8E9E5.png) [Winter Beach Wave](https://colorfiind.com/palette/winter-beach-wave-colorway) | one hue, four steps | a whole dashboard scale |
| ![](https://placehold.co/70x28/0F172A/0F172A.png)![](https://placehold.co/70x28/00F5D4/00F5D4.png)![](https://placehold.co/70x28/F15BB5/F15BB5.png)![](https://placehold.co/70x28/9B5DE5/9B5DE5.png) [Neon Diner Harmony](https://colorfiind.com/palette/neon-diner-harmony) | dark base + 3 signals | dark mode with status colors |
| ![](https://placehold.co/70x28/F5F7F9/F5F7F9.png)![](https://placehold.co/70x28/E3DFF1/E3DFF1.png)![](https://placehold.co/70x28/E0AED7/E0AED7.png)![](https://placehold.co/70x28/994129/994129.png) [Quartz Drift](https://colorfiind.com/palette/quartz-drift-palette) | 3 lights + 1 anchor | light UI, one strong CTA |
| ![](https://placehold.co/70x28/FFEAB6/FFEAB6.png)![](https://placehold.co/70x28/FFEEBA/FFEEBA.png)![](https://placehold.co/70x28/FFD489/FFD489.png)![](https://placehold.co/70x28/F9BA5D/F9BA5D.png) [Lagoon Warm Autumn](https://colorfiind.com/palette/lagoon-warm-autumn-edition) | near-monochrome | backgrounds only — bring your own ink |

> 🚧 **Trap** — that last row is the classic mistake. A gorgeous four-honey palette has **1.45:1** between its lightest and darkest color. It is a set of *surfaces*, not a UI. Pair it with a near-black text color and it sings; use it for text-on-background and nobody can read your app.

## ♿ 5 · Read the contrast column before you fall in love

Every palette here is a design object, not a compliance certificate. Run the pairs you actually intend to ship:

```js
const luminance = (hex) => {
  const [r, g, b] = hex.match(/\w\w/g).map((h) => {
    const c = parseInt(h, 16) / 255;
    return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  });
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

export const contrast = (a, b) => {
  const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m);
  return +((x + 0.05) / (y + 0.05)).toFixed(2);
};

contrast('#3C0D4F', '#C6EEA0'); // 11.84  → AAA, ship it
contrast('#F22C33', '#FFFFFF'); // 4.05   → large text or bold only
contrast('#C6EEA0', '#FFFFFF'); // 1.30   → decorative surface, never text
```

Rule of thumb that survives code review:

- **4.5:1** — body text. Non-negotiable.
- **3:1** — 24px+ text, or 19px+ bold, plus UI borders and icons.
- **anything below 3:1** — decoration. Backgrounds, dividers, illustrations, gradients.

## 🎯 6 · Three real jobs, three palettes

**Marketing landing page** — you want energy and a loud CTA.
![](https://placehold.co/120x56/FFFF79/FFFF79.png)![](https://placehold.co/120x56/FFD2DC/FFD2DC.png)![](https://placehold.co/120x56/56D5CC/56D5CC.png)![](https://placehold.co/120x56/FF8D08/FF8D08.png) [Spring Beach Wave](https://colorfiind.com/palette/spring-beach-wave-colorway)

**Documentation site** — you want quiet, long-read comfort.
![](https://placehold.co/120x56/D8C7D8/D8C7D8.png)![](https://placehold.co/120x56/8FA1B3/8FA1B3.png)![](https://placehold.co/120x56/E6DDE3/E6DDE3.png)![](https://placehold.co/120x56/9B8FA3/9B8FA3.png) [Soft Summer Atelier](https://colorfiind.com/palette/soft-summer-atelier)

**Developer tool / dark app** — you want signal colors that punch through black.
![](https://placehold.co/120x56/09090B/09090B.png)![](https://placehold.co/120x56/FF007F/FF007F.png)![](https://placehold.co/120x56/00E5FF/00E5FF.png)![](https://placehold.co/120x56/B8F500/B8F500.png) [Maple Drift](https://colorfiind.com/palette/maple-drift)

![rule](https://placehold.co/250x12/3C0D4F/3C0D4F.png)![rule](https://placehold.co/250x12/F22C33/F22C33.png)![rule](https://placehold.co/250x12/C6EEA0/C6EEA0.png)![rule](https://placehold.co/250x12/1681DF/1681DF.png)

## 🧾 The checklist

1. Browse by **category** for vibe, **season** for temperature.
2. Open the palette page and read **Mood / Contrast / Best fit** before the hexes.
3. Copy the `:root` block, then **rename** `--palette-color-3` into `--color-surface`.
4. Check the pairs you will actually ship — 4.5:1 for text, 3:1 for large text and borders.
5. Download the palette **Image** and drop it in the PR description so reviewers see the intent.
6. Bookmark the permalink. Six months later, "what was that green?" has an answer.

Next in this series: turning one of these palettes into a full token pipeline — CSS variables, Tailwind config, JSON tokens and a dark-mode flip, in one pass.

{% cta https://colorfiind.com %} Go find your palette on ColorFiind {% endcta %}

*What's your current project's palette? Drop the four hexes in the comments — I'll tell you which one is secretly failing contrast.* 👇
