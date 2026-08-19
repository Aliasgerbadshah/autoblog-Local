---
title: "How Browser Colour Management Changes What Your Users Actually See"
published: false
description: "Browsers convert colour before it reaches the screen. Tagged images, untagged images, CSS colours and canvas all follow different rules — here's the model that explains the bugs."
tags: css, webdev, frontend, design
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#08161C` | `#5500FF` | `#FF5724` | `#38FF59` |
| :--: | :--: | :--: | :--: |
| ![near-black swatch #08161C](https://placehold.co/120x56/08161C/08161C.png) | ![vivid indigo swatch #5500FF](https://placehold.co/120x56/5500FF/5500FF.png) | ![vivid red swatch #FF5724](https://placehold.co/120x56/FF5724/FF5724.png) | ![vivid green swatch #38FF59](https://placehold.co/120x56/38FF59/38FF59.png) |

> 🎨 **Palette in play** — [Coral Breeze Edition](https://colorfiind.com/palette/coral-breeze-edition) · `#08161C` `#5500FF` `#FF5724` `#38FF59`

Here's a bug report that will eventually land in your queue: *"the hero image doesn't match the background colour, but only on my MacBook."*

&nbsp;

The CSS is `background: #FF5724`. The image was exported from the same design file with the same value. On most machines they blend seamlessly. On a wide-gamut display, there's a visible seam.

&nbsp;

Nothing is broken. You're watching **colour management** do its job — on one of them and not the other.

&nbsp;

![Section divider bar in vivid indigo](https://placehold.co/1000x8/5500FF/5500FF.png)

## 🧠 The model in one paragraph

Every piece of content on a page has a colour space, either declared or assumed. Before compositing, the browser converts everything into the display's space using **relative colorimetric** intent — roughly "keep what fits, clamp what doesn't to the nearest thing the screen can show".

&nbsp;

The bugs all come from one place: **what the browser assumes when the content doesn't say.**

&nbsp;

![Section divider bar in vivid red](https://placehold.co/1000x8/FF5724/FF5724.png)

## 📋 What gets assumed, per content type

| Content | Declared how | If it says nothing |
| :-- | :-- | :-- |
| CSS colour (`#hex`, `rgb()`, `hsl()`) | always sRGB by definition | — sRGB, no ambiguity |
| CSS `color(display-p3 …)`, `oklch()` | explicitly wide / perceptual | — |
| `<img>` with embedded ICC profile | the profile | — |
| `<img>` **without** a profile | — | **assumed sRGB** and converted |
| `<video>` | container/stream metadata | assumed Rec. 709 (≈ sRGB primaries) |
| `<canvas>` 2D | `colorSpace` context option | **sRGB** — clamps everything drawn into it |
| WebGL / WebGPU | drawing buffer config | sRGB unless configured |

Two rows in that table cause almost every real-world problem.

&nbsp;

![Section divider bar in vivid green](https://placehold.co/1000x8/38FF59/38FF59.png)

## 🐛 Bug #1 — the untagged wide-gamut export

Somebody exports a hero image from a design tool with "Display P3" as the document profile, and the export drops the profile — either the tool's "smallest file size" option, or an image CDN stripping metadata to save bytes.

&nbsp;

The browser now sees raw numbers with no profile, assumes sRGB, and converts sRGB → display. But those numbers *were* P3. The result: **desaturated, slightly shifted colour**, most noticeable in saturated reds and greens.

```console
$ # what the file should say
$ exiftool hero.jpg | grep -i profile
Profile Description             : Display P3

$ # what a stripped file says
$ exiftool hero-cdn.jpg | grep -i profile
(nothing)                      ← browser will assume sRGB and be wrong
```

**Fixes, in order of preference**

1. Export web images as **sRGB with the profile embedded** — smallest surprise, works everywhere.
2. If the image must be P3, keep the profile and configure your CDN to preserve ICC data.
3. Verify after the CDN, not before. This is the step everyone skips.

```bash
# a CI check worth having
for f in dist/images/*.{jpg,png,avif}; do
  if ! exiftool -icc_profile:all "$f" | grep -q .; then
    echo "❌ $f has no colour profile"; exit 1
  fi
done
```

> 🚧 **Trap for AI-generated imagery** — assets from image models are frequently untagged, and some pipelines write odd or inconsistent profiles. Normalise every generated asset to sRGB with an embedded profile *before* it enters your repo, or your hero images will drift from your CSS in exactly the way described above.

&nbsp;

![Section divider bar in vivid indigo](https://placehold.co/1000x8/5500FF/5500FF.png)

## 🐛 Bug #2 — canvas clamps and you never notice

A 2D canvas is sRGB by default. Draw a P3 image into it and it is **clamped to sRGB on the way in** — permanently, for that canvas.

```js
// the default: everything drawn here is clamped to sRGB
const ctx = canvas.getContext('2d');

// opt in to wide gamut
const wide = canvas.getContext('2d', { colorSpace: 'display-p3' });

// same for pixel data
const data = new ImageData(w, h, { colorSpace: 'display-p3' });
```

If half your UI shows a photo via `<img>` and the other half draws the same photo into a canvas — an editor, a cropper, a colour picker — the two will not match on a wide-gamut screen until you opt the canvas in.

&nbsp;

Worse, colour-picking tools built on canvas will report **clamped** values. Your user picks a vivid P3 green and your picker hands you the sRGB approximation.

&nbsp;

![Section divider bar in vivid red](https://placehold.co/1000x8/FF5724/FF5724.png)

## 🐛 Bug #3 — the seam between CSS and imagery

Back to the original report. The CSS colour is sRGB and gets converted to the display space; the image, if tagged P3, keeps more of its saturation. Side by side, they don't line up.

&nbsp;

**Fix:** put both in the same space at the boundary.

```css
.hero {
  background: #FF5724;                                     /* sRGB baseline */
}

@media (color-gamut: p3) {
  .hero {
    background: color(display-p3 0.9258 0.3891 0.2210);    /* matches the P3 asset */
  }
}
```

Those coordinates are `#FF5724` converted exactly, so on an sRGB screen nothing changes and on a P3 screen the CSS follows the image instead of lagging behind it.

&nbsp;

![Section divider bar in vivid green](https://placehold.co/1000x8/38FF59/38FF59.png)

## 🎨 How much headroom is actually out there?

Running this palette through a gamut check makes the stakes concrete — every one of these saturated colours is already at or beyond the sRGB boundary:

| Colour | sRGB | Chroma +15% | Verdict |
| :-- | :-- | :-- | :-- |
| ![vivid indigo swatch #5500FF](https://placehold.co/70x26/5500FF/5500FF.png) `#5500FF` | at the edge | `#5A00FF` | 🚫 clipped — P3 shows more |
| ![vivid red swatch #FF5724](https://placehold.co/70x26/FF5724/FF5724.png) `#FF5724` | at the edge | `#FF4300` | 🚫 clipped — P3 shows more |
| ![vivid green swatch #38FF59](https://placehold.co/70x26/38FF59/38FF59.png) `#38FF59` | at the edge | `#00FF34` | 🚫 clipped — P3 shows more |

"Clipped" is the browser doing relative-colorimetric mapping for you: it keeps the hue and lightness roughly, and pulls the chroma back to the nearest colour the display can produce. That's why your neon greens look slightly different on every machine — each display clamps to a different boundary.

&nbsp;

![Section divider bar in vivid indigo](https://placehold.co/1000x8/5500FF/5500FF.png)

## 🧪 How to debug a colour-management problem

```console
$ # 1 · is the asset tagged?
    exiftool -icc_profile:all image.jpg

$ # 2 · what does the browser think it is?
    DevTools → Network → the image → Preview, compare against the file in a colour-managed viewer

$ # 3 · is the display wide-gamut?
    matchMedia('(color-gamut: p3)').matches

$ # 4 · is my canvas clamping?
    ctx.getContextAttributes().colorSpace        // "srgb" or "display-p3"

$ # 5 · take the screen out of the equation
    compare pixel values, not appearances — screenshots are usually re-encoded to sRGB
```

Point 5 is why so many of these bugs survive review: the screenshot in the ticket has already been flattened, so the reviewer literally cannot see the problem.

&nbsp;

{% details 🧰 Normalising every image in a build step %}

```bash
# ImageMagick: convert to sRGB and embed the profile
magick input.png -profile sRGB.icc -strip -profile sRGB.icc output.png

# sharp, in a Node pipeline
await sharp(input)
  .toColorspace('srgb')
  .withMetadata({ icc: 'srgb' })     // keep the profile, drop everything else
  .toFile(output);
```

Note the order in the ImageMagick line: convert **into** sRGB first, then strip other metadata, then re-attach the profile. Stripping before converting is how images lose their meaning.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in vivid red](https://placehold.co/1000x8/FF5724/FF5724.png)

## 🧾 The checklist

1. Everything has a colour space — declared, or assumed by the browser.
2. Untagged images are assumed sRGB. If they weren't sRGB, they now render wrong.
3. Export web imagery as sRGB **with** the profile embedded; verify after the CDN.
4. Normalise AI-generated and third-party assets before they enter the repo.
5. Canvas clamps to sRGB unless you pass `colorSpace: 'display-p3'`.
6. Match CSS to imagery at any visible seam, per gamut.
7. Debug with file metadata and pixel values — screenshots lie.

The one thing that never surprises you is a plain sRGB hex from a published palette; that's why I keep the CSS side boring and let the imagery be the interesting part. [ColorFiind](https://colorfiind.com) is where I grab those.

&nbsp;

{% cta https://colorfiind.com %} Grab a dependable sRGB palette {% endcta %}

&nbsp;

*Ever chased a colour bug that turned out to be a stripped ICC profile? How long did it take to find?* 👇
