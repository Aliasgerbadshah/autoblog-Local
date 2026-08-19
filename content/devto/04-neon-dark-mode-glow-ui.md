---
title: "Neon Dark Mode That Doesn't Hurt: Building a Glow UI from a good palette library's Neon Palettes"
published: false
description: "Neon palettes look incredible in a screenshot and burn your retinas in production. The CSS system that keeps the glow and fixes the pain."
tags: css, webdev, darkmode, design
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,
     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

![near-black swatch #09090B in a divider rule](https://placehold.co/250x110/09090B/09090B.png)![vivid pink swatch #FF007F in a divider rule](https://placehold.co/250x110/FF007F/FF007F.png)![vivid teal swatch #00E5FF in a divider rule](https://placehold.co/250x110/00E5FF/00E5FF.png)![vivid lime swatch #B8F500 in a divider rule](https://placehold.co/250x110/B8F500/B8F500.png)

> 🎨 **Palette in play** — **Maple Drift** · `#09090B` `#FF007F` `#00E5FF` `#B8F500`

Every neon theme starts the same way. You find a palette like this one, you build a landing page in an hour, it looks like the future, and three days later you notice you have been squinting at your own product.

The colors are not the problem. **The way they are applied is.** a good palette library's neon category is full of palettes with the exact structure you want — one near-black base plus three high-luminance signals — and that structure is a rule waiting to be written down.

![near-black swatch #09090B in a divider rule](https://placehold.co/250x12/09090B/09090B.png)![vivid pink swatch #FF007F in a divider rule](https://placehold.co/250x12/FF007F/FF007F.png)![vivid teal swatch #00E5FF in a divider rule](https://placehold.co/250x12/00E5FF/00E5FF.png)![vivid lime swatch #B8F500 in a divider rule](https://placehold.co/250x12/B8F500/B8F500.png)

## ⚡ 1 · Read the structure, not the vibe

Every good neon palette is `1 base + 3 signals`:

| Swatch | HEX | Luminance | On base | Grade | Role |
| :----: | :-- | :-------- | :------ | :---: | :--- |
| ![near-black swatch #09090B](https://placehold.co/110x44/09090B/09090B.png) | `#09090B` | 0.003 | — | — | canvas |
| ![vivid pink swatch #FF007F](https://placehold.co/110x44/FF007F/FF007F.png) | `#FF007F` | 0.228 | **5.27:1** | ✅ AA | accent / brand |
| ![vivid teal swatch #00E5FF](https://placehold.co/110x44/00E5FF/00E5FF.png) | `#00E5FF` | 0.633 | **12.93:1** | ✅ AAA | primary / links |
| ![vivid lime swatch #B8F500](https://placehold.co/110x44/B8F500/B8F500.png) | `#B8F500` | 0.755 | **15.25:1** | ✅ AAA | success / highlight |

Two things fall out of that table immediately:

1. The two bright signals are **more readable than most light-mode text**. They are your primary and success colors, not decoration.
2. The pink is the weakest at 5.27:1 — fine for headings and buttons, risky for 14px body copy on a glowing background.

Three more neon palettes with the same skeleton:

| Palette | Base | Signals |
| :-- | :-- | :-- |
| **Neon Diner Harmony** | ![navy swatch #0F172A](https://placehold.co/54x24/0F172A/0F172A.png) `#0F172A` | ![vivid teal swatch #00F5D4](https://placehold.co/54x24/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5](https://placehold.co/54x24/F15BB5/F15BB5.png)![indigo swatch #9B5DE5](https://placehold.co/54x24/9B5DE5/9B5DE5.png) |
| **Clay Flare Spectrum** | ![deep indigo swatch #10002B](https://placehold.co/54x24/10002B/10002B.png) `#10002B` | ![deep indigo swatch #240046](https://placehold.co/54x24/240046/240046.png)![vivid pink swatch #FF006E](https://placehold.co/54x24/FF006E/FF006E.png)![vivid teal swatch #00F5D4](https://placehold.co/54x24/00F5D4/00F5D4.png) |
| **Pine Field Gallery** | ![near-black swatch #03071E](https://placehold.co/54x24/03071E/03071E.png) `#03071E` | ![dark red swatch #DC2F02](https://placehold.co/54x24/DC2F02/DC2F02.png)![vivid orange swatch #F48C06](https://placehold.co/54x24/F48C06/F48C06.png)![vivid orange swatch #FFBA08](https://placehold.co/54x24/FFBA08/FFBA08.png) |

## 🩹 2 · Why it hurts — halation, and the 3 fixes

Saturated bright-on-black text bleeds at the edges for a lot of eyes — astigmatism especially. This is **halation**, and it is why "pure white on pure black" is a bad default even though it scores 21:1.

Three fixes, all cheap:

**`fix-1.css`** ⌁ *never pure black, never pure white*

```diff
- --bg:  #000000;
- --ink: #FFFFFF;
+ --bg:  #09090B;   /* the palette's base, not absolute black */
+ --ink: #E7E9EE;   /* 16.4:1 — plenty, without the buzz      */
```

**`fix-2.css`** ⌁ *body text is never a neon*

```css
:root {
  --ink:        #E7E9EE;   /* all paragraphs                */
  --ink-muted:  #9AA3B2;   /* captions, timestamps          */
  --neon-cyan:  #00E5FF;   /* links, focus, primary actions */
  --neon-lime:  #B8F500;   /* success, active nav, badges   */
  --neon-pink:  #FF007F;   /* brand, headings, one CTA      */
}

p, li, td { color: var(--ink); }                 /* boring on purpose */
a         { color: var(--neon-cyan); }
```

**`fix-3.css`** ⌁ *the glow lives in the shadow, not in the text*

```css
.neon-card {
  background: color-mix(in srgb, var(--neon-cyan) 6%, #101014);
  border: 1px solid color-mix(in srgb, var(--neon-cyan) 35%, transparent);
  border-radius: 14px;
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--neon-cyan) 18%, transparent),
    0 14px 40px -18px var(--neon-cyan);
}
```

> 🧪 **Lab note** — `color-mix()` is the whole trick. One neon token generates its own tint, border, glow and hover state, so a palette swap is still a one-line change. Supported in every current browser; `rgb(0 229 255 / 6%)` is the fallback.

![near-black swatch #09090B in a divider rule](https://placehold.co/250x12/09090B/09090B.png)![vivid pink swatch #FF007F in a divider rule](https://placehold.co/250x12/FF007F/FF007F.png)![vivid teal swatch #00E5FF in a divider rule](https://placehold.co/250x12/00E5FF/00E5FF.png)![vivid lime swatch #B8F500 in a divider rule](https://placehold.co/250x12/B8F500/B8F500.png)

## 🧱 3 · The full theme

**`neon.css`** ⌁ *complete, copy-paste-able*

```css
:root[data-theme='neon'] {
  /* canvas — three steps of near-black */
  --bg-base:     #09090B;
  --bg-raised:   #101014;
  --bg-overlay:  #17171D;

  /* type */
  --ink:         #E7E9EE;
  --ink-muted:   #9AA3B2;

  /* signals, straight from Maple Drift */
  --neon-cyan:   #00E5FF;
  --neon-lime:   #B8F500;
  --neon-pink:   #FF007F;

  /* semantic aliases — components use these only */
  --primary:     var(--neon-cyan);
  --primary-ink: #04252B;
  --success:     var(--neon-lime);
  --success-ink: #14200B;
  --brand:       var(--neon-pink);
  --brand-ink:   #1A0010;

  --focus-ring:  0 0 0 3px color-mix(in srgb, var(--neon-cyan) 55%, transparent);
  --radius:      12px;
}

body {
  background:
    radial-gradient(1200px 600px at 15% -10%, color-mix(in srgb, var(--neon-pink) 10%, transparent), transparent),
    radial-gradient(900px 500px at 90% 0%, color-mix(in srgb, var(--neon-cyan) 10%, transparent), transparent),
    var(--bg-base);
  color: var(--ink);
}

.btn-primary {
  background: var(--primary);
  color: var(--primary-ink);
  border-radius: var(--radius);
  padding: 0.7rem 1.4rem;
  font-weight: 650;
}
.btn-primary:hover { box-shadow: 0 10px 30px -12px var(--primary); }

:where(a, button, input, select, textarea):focus-visible {
  outline: none;
  box-shadow: var(--focus-ring);
}

.badge-success {
  background: color-mix(in srgb, var(--success) 14%, transparent);
  color: var(--success);
  border: 1px solid color-mix(in srgb, var(--success) 40%, transparent);
  border-radius: 999px;
  padding: 0.2rem 0.7rem;
  font-size: 0.8rem;
}
```

Text-on-neon pairings, pre-solved:

| Background | Ink | Ratio |
| :-- | :-- | :-- |
| ![vivid teal swatch #00E5FF](https://placehold.co/70x26/00E5FF/00E5FF.png) `#00E5FF` | `#04252B` | **10.46:1** ✅ |
| ![vivid lime swatch #B8F500](https://placehold.co/70x26/B8F500/B8F500.png) `#B8F500` | `#14200B` | **12.97:1** ✅ |
| ![vivid pink swatch #FF007F](https://placehold.co/70x26/FF007F/FF007F.png) `#FF007F` | `#1A0010` | **5.29:1** ✅ |

> 🚧 **Trap** — light text on `#FF007F` maxes out at **3.43:1**; the pink is simply too luminous to carry white type. Dark ink on pink is the only combination that passes AA, and it looks better anyway.

## 🎬 4 · Motion: glow responsibly

A pulsing glow is the signature move of a neon UI and the fastest way to make someone close the tab. Gate it.

```css
@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--neon-pink) 45%, transparent); }
  50%      { box-shadow: 0 0 26px 6px color-mix(in srgb, var(--neon-pink) 22%, transparent); }
}

.live-dot { animation: pulse 2.4s ease-in-out infinite; }

@media (prefers-reduced-motion: reduce) {
  .live-dot {
    animation: none;
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--neon-pink) 50%, transparent);
  }
}
```

{% details 🌗 Add a "dim the neon" user setting (people will thank you) %}

```css
:root[data-intensity='calm'] {
  --neon-cyan: #58C7D8;
  --neon-lime: #A8C965;
  --neon-pink: #D45C92;
}
```

```js
const intensity = localStorage.getItem('intensity') ?? 'full';
document.documentElement.dataset.intensity = intensity;

document.querySelector('#calm').addEventListener('change', (e) => {
  const value = e.target.checked ? 'calm' : 'full';
  document.documentElement.dataset.intensity = value;
  localStorage.setItem('intensity', value);
});
```

Same tokens, desaturated by roughly 45%. Two lines of JS, one extra CSS block, and your neon theme becomes usable for an eight-hour workday.

{% enddetails %}

## 🧪 5 · Ship-gate it in CI

Neon themes drift. Someone adds `color: #FF007F` to a 13px label at 2am. Catch it automatically:

```js
// contrast.test.js
import { contrast } from './contrast.js';

const BG = '#09090B';
const tokens = {
  ink:   '#E7E9EE',
  cyan:  '#00E5FF',
  lime:  '#B8F500',
  pink:  '#FF007F',
};

describe('neon theme on base', () => {
  for (const [name, hex] of Object.entries(tokens)) {
    it(`${name} clears 4.5:1`, () => {
      expect(contrast(hex, BG)).toBeGreaterThanOrEqual(4.5);
    });
  }
});
```

```console
$ npm test -- contrast

 PASS  ./contrast.test.js
  neon theme on base
    ✓ ink clears 4.5:1   (16.38)
    ✓ cyan clears 4.5:1  (12.93)
    ✓ lime clears 4.5:1  (15.25)
    ✓ pink clears 4.5:1  (5.27)

Tests: 4 passed, 4 total
```

## 🧾 The checklist

- Pick a neon palette with a **real base color**, not `#000`.
- Body copy is always the neutral ink. Neons are for links, states, borders and one CTA.
- Put the glow in `box-shadow` and tinted backgrounds, never in the letterforms.
- Give every neon an `-ink` partner and store it as a token.
- Respect `prefers-reduced-motion`, and consider a "calm" intensity toggle.
- Assert contrast in CI so the theme can't rot.


*Building something dark and loud? Drop your base + three signals in the comments.* 👇
