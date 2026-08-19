---
title: "The Swatch Deck — a reusable DEV.to theme kit for color posts"
published: false
description: "Copy-paste building blocks for colour-heavy DEV.to articles: swatches that actually render, tabbed code blocks, collapsibles and contrast tables."
tags: writing, markdown, css, design
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,
     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

<!--
  ┌───────────────────────────────────────────────────────────────┐
  │  SWATCH DECK — theme kit v1                                   │
  │  Every article in this series uses these blocks in this order │
  │  Colour values: any palette source you like               │
  └───────────────────────────────────────────────────────────────┘
  This file is a reference sheet, not a post you have to publish.
-->

![navy swatch #0F172A in a divider rule](https://placehold.co/250x14/0F172A/0F172A.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x14/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5 in a divider rule](https://placehold.co/250x14/F15BB5/F15BB5.png)![indigo swatch #9B5DE5 in a divider rule](https://placehold.co/250x14/9B5DE5/9B5DE5.png)

DEV.to strips `<style>` tags and inline `style=""` attributes, so you cannot paint a div pink and call it a day. What you *can* do is let **images carry the color** and let **fenced code blocks carry the texture**. That is the whole trick behind this theme.

## 🧱 The eight blocks

| # | Block | What it does | Renders on DEV? |
| :-: | :-- | :-- | :-: |
| 1 | Cover band | 1000×420 PNG, palette + title | ✅ |
| 2 | Palette band | 4 touching swatch images = one bar | ✅ |
| 3 | Color rule | thin band instead of `---` | ✅ |
| 4 | Swatch table | color + HEX + RGB + HSL + contrast | ✅ |
| 5 | Code tab | filename line above a fenced block | ✅ |
| 6 | Diff block | red/green "before → after" | ✅ |
| 7 | Terminal block | a `console` fence for a shell look | ✅ |
| 8 | Collapsible | the *details* liquid tag for long code | ✅ |
| — | `<style>`, inline style attributes, `<script>` | sanitized away | 🚫 |

![navy swatch #0F172A in a divider rule](https://placehold.co/250x8/0F172A/0F172A.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x8/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5 in a divider rule](https://placehold.co/250x8/F15BB5/F15BB5.png)![indigo swatch #9B5DE5 in a divider rule](https://placehold.co/250x8/9B5DE5/9B5DE5.png)

## 1 · Palette band

Four images on **one line with no spaces between them** butt up against each other and read as a single bar:

```markdown
![navy swatch #0F172A](https://placehold.co/210x110/0F172A/0F172A.png)![vivid teal swatch #00F5D4](https://placehold.co/210x110/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5](https://placehold.co/210x110/F15BB5/F15BB5.png)![indigo swatch #9B5DE5](https://placehold.co/210x110/9B5DE5/9B5DE5.png)
```

Result:

![navy swatch #0F172A](https://placehold.co/210x110/0F172A/0F172A.png)![vivid teal swatch #00F5D4](https://placehold.co/210x110/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5](https://placehold.co/210x110/F15BB5/F15BB5.png)![indigo swatch #9B5DE5](https://placehold.co/210x110/9B5DE5/9B5DE5.png)

The URL shape is `placehold.co/{W}x{H}/{background}/{textColor}.png`. Passing the **same hex twice** hides the auto-generated size label, so you get a clean solid block. `dummyimage.com/210x110/0f172a/0f172a.png` is a drop-in fallback if placehold.co is ever down.

## 2 · Color rule

A 10–14px band replaces the boring `---` divider and keeps the article's palette in the reader's eye:

```markdown
![navy swatch #0F172A in a divider rule](https://placehold.co/250x12/0F172A/0F172A.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x12/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5 in a divider rule](https://placehold.co/250x12/F15BB5/F15BB5.png)![indigo swatch #9B5DE5 in a divider rule](https://placehold.co/250x12/9B5DE5/9B5DE5.png)
```

## 3 · Swatch table

| Swatch | HEX | RGB | HSL | On `#0F172A` | Role |
| :----: | :-- | :-- | :-- | :----------- | :--- |
| ![navy swatch #0F172A](https://placehold.co/110x44/0F172A/0F172A.png) | `#0F172A` | `15, 23, 42` | `222° 47% 11%` | — | Base |
| ![vivid teal swatch #00F5D4](https://placehold.co/110x44/00F5D4/00F5D4.png) | `#00F5D4` | `0, 245, 212` | `172° 100% 48%` | **12.76:1** ✅ | Primary |
| ![vivid magenta swatch #F15BB5](https://placehold.co/110x44/F15BB5/F15BB5.png) | `#F15BB5` | `241, 91, 181` | `324° 84% 65%` | **5.87:1** ✅ | Accent |
| ![indigo swatch #9B5DE5](https://placehold.co/110x44/9B5DE5/9B5DE5.png) | `#9B5DE5` | `155, 93, 229` | `267° 72% 63%` | **4.33:1** 🟡 | Support |

## 4 · Code tabs — five looks for five jobs

Give each block a **tab line** right above it. The bold monospace filename plus an em-dash caption reads like an editor tab and visually separates one code look from the next.

**`tokens.css`** ⌁ *the source of truth*

```css
:root {
  --color-surface: #0F172A;
  --color-primary: #00F5D4;
}
```

**`tailwind.config.js`** ⌁ *the framework mirror*

```js
module.exports = {
  theme: { extend: { colors: { palette: { surface: '#0F172A' } } } },
};
```

**`patch`** ⌁ *before → after, in red and green*

```diff
- background: #1e1e1e;
- color: #cccccc;
+ background: var(--color-surface);
+ color: var(--color-primary);
```

**`terminal`** ⌁ *no syntax highlighting, all vibe*

```console
$ npx palette-tokens 0F172A 00F5D4 F15BB5 9B5DE5
✔ wrote tokens.css   (4 custom properties)
✔ wrote tokens.json  (4 design tokens)
```

**`data`** ⌁ *machine-readable*

```jsonc
{
  // one palette, four tokens
  "surface": "#0F172A",
  "primary": "#00F5D4"
}
```

## 5 · Liquid tags worth using

{% details 💡 Click to expand a collapsible %}

Long snippets, alternate framework versions and "the boring full config" belong in here so the article keeps its rhythm.

```scss
$surface: #0F172A;
$primary: #00F5D4;
```

{% enddetails %}

```liquid
{% details Summary text %} hidden content {% enddetails %}

{% embed https://codepen.io/your/pen %}
{% katex inline %} L = 0.2126R + 0.7152G + 0.0722B {% endkatex %}
```

## 6 · Front matter template

```yaml
---
title: "Your title — max ~60 chars for the card"
published: false
description: "One sentence, 120-160 chars, shows up in the feed and in OG cards."
tags: css, webdev, design, beginners
canonical_url: https://your-site.com/original-post
series: "Color for Developers"
---
```

> 🧪 **Lab note** — `tags` is a **maximum of four**, comma separated, no `#`. `cover_image` looks best at exactly **1000×420**. Keep `published: false` until the preview looks right, then flip it.

![navy swatch #0F172A in a divider rule](https://placehold.co/250x12/0F172A/0F172A.png)![vivid teal swatch #00F5D4 in a divider rule](https://placehold.co/250x12/00F5D4/00F5D4.png)![vivid magenta swatch #F15BB5 in a divider rule](https://placehold.co/250x12/F15BB5/F15BB5.png)![indigo swatch #9B5DE5 in a divider rule](https://placehold.co/250x12/9B5DE5/9B5DE5.png)

## 7 · Generate all of it

Every band, table and token block in this series came out of one script:

```console
$ python3 tools/palette_kit.py swatches \
    --name "Neon Diner Harmony" --slug neon-diner-harmony \
    --colors 0F172A 00F5D4 F15BB5 9B5DE5
```


