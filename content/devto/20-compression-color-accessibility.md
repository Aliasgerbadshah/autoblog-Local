---
title: "How Image Compression Quietly Breaks Colour Accessibility"
published: false
description: "Compression doesn't just soften edges — it moves colours, and it moves them most where text sits on images. Here's how to keep contrast ratios intact after encoding."
tags: accessibility, webdev, design, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#7C1E3A` | `#FFFFFF` | `#176457` | `#452F73` |
| :--: | :--: | :--: | :--: |
| ![dark pink swatch #7C1E3A](https://placehold.co/120x56/7C1E3A/7C1E3A.png) | ![off-white swatch #FFFFFF](https://placehold.co/120x56/FFFFFF/FFFFFF.png) | ![deep teal swatch #176457](https://placehold.co/120x56/176457/176457.png) | ![dark indigo swatch #452F73](https://placehold.co/120x56/452F73/452F73.png) |

> 🎨 **Palette in play** — [Deep Winter Reef Couture](https://colorfiind.com/palette/deep-winter-reef-couture) · `#7C1E3A` `#FFFFFF` `#176457` `#452F73`

Accessibility audits check contrast against the values in your design file. Users experience contrast against the pixels their browser actually decoded. Between those two things sits an encoder that was asked to make the file smaller, and it does that by **changing colours**.

&nbsp;

Most of the time the change is too small to matter. In three specific situations it is enough to push a passing combination into failure — and those situations are common.

&nbsp;

![Section divider bar in dark pink](https://placehold.co/1000x8/7C1E3A/7C1E3A.png)

## 📊 The starting point: values that pass comfortably

This palette is deliberately high-contrast, which is exactly what you want for text over imagery:

| Text | Background | Ratio | Grade |
| :--: | :--: | :-- | :-- |
| ![off-white swatch #FFFFFF](https://placehold.co/70x26/FFFFFF/FFFFFF.png) `#FFFFFF` | ![dark pink swatch #7C1E3A](https://placehold.co/70x26/7C1E3A/7C1E3A.png) `#7C1E3A` | **9.98:1** | ✅ AAA |
| ![off-white swatch #FFFFFF](https://placehold.co/70x26/FFFFFF/FFFFFF.png) `#FFFFFF` | ![deep teal swatch #176457](https://placehold.co/70x26/176457/176457.png) `#176457` | **7.01:1** | ✅ AAA |
| ![off-white swatch #FFFFFF](https://placehold.co/70x26/FFFFFF/FFFFFF.png) `#FFFFFF` | ![dark indigo swatch #452F73](https://placehold.co/70x26/452F73/452F73.png) `#452F73` | **11.01:1** | ✅ AAA |

Plenty of headroom. Now put that white text **inside a JPEG** instead of in HTML, and watch what compression does to it.

&nbsp;

![Section divider bar in deep teal](https://placehold.co/1000x8/176457/176457.png)

## 🔨 The three ways compression eats contrast

### 1 · Ringing around text

JPEG and other DCT-based codecs work in 8×8 blocks. A hard edge — white letterform against a dark background — produces high-frequency information that quantisation can't represent exactly, so the decoder reconstructs it with overshoot: faint dark halos inside the light stroke and light halos outside it.

&nbsp;

The consequence for accessibility: the *effective* colour of a thin white stroke is no longer `#FFFFFF`. Sample the middle of a 2px stroke in a heavily compressed JPEG and you may find `#E4E0E2` — which drops white-on-burgundy from **9.98:1** to **7.63:1**. Still fine here. Start from a combination at 4.6:1 and the same effect puts you under the line.

### 2 · Chroma subsampling shifting the colour, not the brightness

At 4:2:0, colour is stored at quarter resolution. Around coloured text this produces fringing — and if your foreground and background differ mainly in **hue** rather than **lightness**, subsampling attacks exactly the channel carrying the distinction.

&nbsp;

This is the strongest argument for a rule you already know: **contrast should come from lightness, not hue.** A design that satisfies WCAG through a big lightness gap is robust to compression. One that scrapes past on a saturated-hue difference is not.

### 3 · Banding in gradients behind text

A hero with a subtle dark gradient behind white text can band after encoding. Each band is a slightly different background value, so the contrast ratio varies across the headline — and the audit, which sampled one point, never saw it.

&nbsp;

![Section divider bar in dark pink](https://placehold.co/1000x8/7C1E3A/7C1E3A.png)

## 🛡️ The fix that solves all three at once

**Don't put text inside the image.**

```html
<!-- ❌ text baked into the JPEG: contrast depends on the encoder -->
<img src="hero-with-headline.jpg" alt="Winter collection — now available">

<!-- ✅ text in HTML over the image: contrast is exact, selectable, translatable -->
<figure class="hero">
  <img src="hero.avif" alt="" width="1600" height="900">
  <figcaption class="hero__text">Winter collection — now available</figcaption>
</figure>
```

```css
.hero { position: relative; isolation: isolate; }

.hero img { display: block; inline-size: 100%; block-size: auto; }

.hero__text {
  position: absolute;
  inset-block-end: 0;
  padding: 2rem;
  color: #FFFFFF;
  font-size: clamp(1.5rem, 4vw, 3rem);
  font-weight: 650;
  /* a scrim guarantees the ratio regardless of what the photo does */
  background: linear-gradient(to top, rgb(124 30 58 / 0.92), rgb(124 30 58 / 0));
}
```

The scrim is the important part: it pins the *effective* background to a value you chose — here `#7C1E3A` at 92%, giving white text a computable ratio — instead of whatever the photograph happens to contain after compression.

&nbsp;

Benefits beyond contrast: the text is selectable, translatable, searchable, resizable and readable by a screen reader without duplicating it in `alt`.

&nbsp;

![Section divider bar in deep teal](https://placehold.co/1000x8/176457/176457.png)

## 🎛️ When the text really must be in the image

Social cards, email headers, some ad formats. Then:

```console
$ # 1 · lossless or near-lossless for anything with text
  magick card.png -define png:compression-level=9 card-out.png
  magick card.png -quality 95 -sampling-factor 4:4:4 card-out.jpg

$ # 2 · never lossy WebP for text (it is always 4:2:0)
  cwebp -lossless card.png -o card.webp

$ # 3 · give the text more headroom than the audit requires
  design at ≥ 7:1 so a compression loss of ~1.5 points still clears 4.5:1

$ # 4 · check the compressed file, not the source
```

That fourth line is the whole discipline. Audit the artefact you ship.

&nbsp;

![Section divider bar in dark pink](https://placehold.co/1000x8/7C1E3A/7C1E3A.png)

## 🧪 Measuring contrast on the file you actually serve

```js
// contrast-after-encode.mjs — sample the real pixels of the delivered image
import sharp from 'sharp';

const luminance = ([r, g, b]) => {
  const f = (c) => {
    const s = c / 255;
    return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
};

const ratio = (a, b) => {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return +((hi + 0.05) / (lo + 0.05)).toFixed(2);
};

// average a small patch so a single ringing pixel doesn't skew the result
async function patch(file, left, top, size = 6) {
  const { data } = await sharp(file)
    .extract({ left, top, width: size, height: size })
    .raw().toBuffer({ resolveWithObject: true });
  const px = [0, 1, 2].map((c) => {
    let sum = 0;
    for (let i = c; i < data.length; i += 3) sum += data[i];
    return Math.round(sum / (data.length / 3));
  });
  return px;
}

const fg = await patch('dist/card.jpg', 120, 300);   // inside a letter stroke
const bg = await patch('dist/card.jpg', 60, 300);    // background beside it

console.log('after encoding:', ratio(fg, bg));       // the number that matters
```

Wire that into CI for your social-card generator and a quality-setting change can never silently break a contrast requirement.

&nbsp;

{% details ♿ The rest of the accessibility angle %}

&nbsp;

Compression interacts with more than contrast:

- **Colour-only meaning gets worse.** If a chart image distinguishes series by hue and 4:2:0 smears those hues together, the encoding has amplified an existing accessibility failure. Redundant encoding — shape, pattern, direct labels — protects against both.
- **Banding is a comfort issue.** Visible banding in large flat areas is unpleasant for people with light sensitivity or migraine triggers. Noise-dithering a gradient is an accessibility improvement, not just a cosmetic one.
- **Alt text stops being optional** when text lives inside an image. If the headline is in the JPEG and the `alt` is empty, that headline does not exist for a screen-reader user.
- **Zoom.** Baked-in text pixelates at 200% zoom; HTML text reflows. WCAG asks for the latter.

{% enddetails %}

&nbsp;

![Section divider bar in deep teal](https://placehold.co/1000x8/176457/176457.png)

## 🧾 The checklist

1. Contrast must come from a **lightness** difference — that's the part compression preserves best.
2. Keep text in HTML over images, with a scrim that fixes the effective background.
3. If text must be baked in: lossless, or JPEG q95 at 4:4:4. Never lossy WebP.
4. Design to ≥ 7:1 for text over imagery so encoding losses stay clear of the limit.
5. Measure contrast on the **delivered** file, averaged over a patch.
6. Dither gradients to avoid banding — it's a comfort issue, not just a visual one.
7. Never rely on hue alone; compression degrades hue distinctions first.

High-contrast palettes make all of this easier, because they start with headroom to lose — the deep-winter style sets on [ColorFiind](https://colorfiind.com) are built around exactly that kind of lightness separation.

&nbsp;

{% cta https://colorfiind.com/season/winter/deep-winter-color-palette %} Browse high-contrast palettes {% endcta %}

&nbsp;

*Have you ever measured contrast on the compressed file rather than the design? The gap surprises most people.* 👇
