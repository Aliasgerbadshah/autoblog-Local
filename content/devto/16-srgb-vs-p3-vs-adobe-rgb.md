---
title: "sRGB vs Display P3 vs Adobe RGB: Which One Should You Actually Work In?"
published: false
description: "Three colour spaces, three jobs. A decision table for web work, a look at what each one holds, and the conversion mistakes that quietly ruin brand colour."
tags: css, webdev, design, frontend
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#36F26E` | `#3BD8F7` | `#A428E2` | `#EF684D` |
| :--: | :--: | :--: | :--: |
| ![vivid green swatch #36F26E](https://placehold.co/120x56/36F26E/36F26E.png) | ![vivid cyan swatch #3BD8F7](https://placehold.co/120x56/3BD8F7/3BD8F7.png) | ![purple swatch #A428E2](https://placehold.co/120x56/A428E2/A428E2.png) | ![vivid red swatch #EF684D](https://placehold.co/120x56/EF684D/EF684D.png) |

> 🎨 **Palette in play** — [Dahlia Meadow Daybreak](https://colorfiind.com/palette/dahlia-meadow-daybreak) · `#36F26E` `#3BD8F7` `#A428E2` `#EF684D`

Three colour spaces come up constantly, and most advice about them is either "always use sRGB" or a colour-science lecture. Neither helps when you're staring at an export dialog.

&nbsp;

Here's the short version: **they solve different problems, and only one of them is a delivery format for the web.**

&nbsp;

![Section divider bar in vivid green](https://placehold.co/1000x8/36F26E/36F26E.png)

## 📊 What each one actually holds

| | sRGB | Display P3 | Adobe RGB (1998) |
| :-- | :-- | :-- | :-- |
| Born | 1996, for CRTs | 2015, from cinema's DCI-P3 | 1998, for print prepress |
| Coverage of visible colour | ~35% | ~45% | ~50% |
| Strongest in | nothing in particular | **reds and greens** | **cyans and greens** |
| Gamma | ~2.2 (piecewise) | same curve as sRGB | 2.19921875 |
| White point | D65 | D65 | D65 |
| Native to | the web, everything | Apple hardware, modern phones | print workflows |
| Web delivery? | ✅ the default | ✅ via `color()` + tagged images | 🚫 **no** |

That last row is the one that matters most. Adobe RGB is a **working** space, not a delivery space. Ship an Adobe RGB file to the web and any viewer that ignores the profile treats those numbers as sRGB — and the image goes flat and desaturated.

&nbsp;

![Section divider bar in vivid cyan](https://placehold.co/1000x8/3BD8F7/3BD8F7.png)

## 🔬 The same colour, three ways

Here is that palette's green expressed in each space. Same perceptual colour, different numbers, because each space's primaries sit in a different place:

| Space | `#36F26E` expressed as |
| :-- | :-- |
| sRGB | `#36F26E` → `rgb(54 242 110)` |
| Display P3 | `color(display-p3 0.4708 0.9356 0.4922)` |
| Adobe RGB | `(0.5599 0.9474 0.4655)` |

Notice the numbers move *inward* for the wider spaces. That's the tell: a wider space needs less of each primary to hit the same colour. And it's exactly why a mis-tagged file looks wrong — feed `0.5599` to something expecting sRGB and you get a duller green.

&nbsp;

The interesting question is the reverse: what can each space hold that sRGB cannot?

| Colour | In sRGB | Display P3 | Adobe RGB |
| :-- | :-- | :-- | :-- |
| Saturated cinema green | clipped | ✅ comfortably | ✅ comfortably |
| Vivid coral / warm red | clipped | ✅ notably better | 🟡 slightly better |
| Deep cyan | clipped | 🟡 slightly better | ✅ notably better |
| Everyday UI colour | ✅ fine | ✅ fine | ✅ fine |

P3 was built from cinema primaries and is strongest in reds. Adobe RGB was built for CMYK prepress and is strongest in cyans and greens — because those are the colours offset printing struggles to reach and photographers needed to preserve. **Neither is "bigger" in a way that makes it universally better.**

&nbsp;

![Section divider bar in purple](https://placehold.co/1000x8/A428E2/A428E2.png)

## 🧭 The decision table

| What you're doing | Work in | Deliver as |
| :-- | :-- | :-- |
| Web UI, CSS colours | sRGB | sRGB (it's the default; no tagging needed) |
| Web imagery, general | sRGB | sRGB, **profile embedded** |
| Web hero art for Apple-heavy traffic | Display P3 | tagged P3 + sRGB fallback via `<picture>` |
| App icons, marketing assets | Display P3 | per-platform export, tagged |
| Photography you'll edit later | Adobe RGB or ProPhoto | **convert** to sRGB or P3 on export |
| Anything going to print | Adobe RGB | the printer's CMYK profile |
| Social media | anything | **always sRGB, 8-bit, tagged** |

If you remember one line: **edit wide, deliver appropriately, never deliver Adobe RGB to a browser.**

&nbsp;

![Section divider bar in vivid red](https://placehold.co/1000x8/EF684D/EF684D.png)

## 🎨 In CSS, specifically

CSS colours are sRGB unless you say otherwise. There's no Adobe RGB option in CSS at all — and that's fine, because it was never a display space.

```css
.brand {
  /* sRGB — the contract, understood everywhere */
  background: #A428E2;
}

@media (color-gamut: p3) {
  .brand {
    /* the same purple, in P3 coordinates */
    background: color(display-p3 0.5919 0.1991 0.8544);
  }
}
```

Or skip the branching and describe the colour perceptually, letting the browser map it to whatever the display supports:

```css
.brand {
  background: oklch(0.564 0.258 310.4);   /* = #A428E2, richer on P3 screens */
}
```

`oklch()` is not a fourth colour space competing with the three above — it's a way of *describing* colour that isn't tied to any device's primaries. That's why it's the best default for a design system that has to serve both narrow and wide screens.

&nbsp;

![Section divider bar in vivid green](https://placehold.co/1000x8/36F26E/36F26E.png)

## 🚨 The four conversion mistakes

**1 · Assign vs Convert.** *Assign* relabels the numbers — the appearance changes. *Convert* changes the numbers to preserve the appearance. Assigning Adobe RGB to an sRGB file is the classic "why did it get so saturated?" moment.

```console
$ # convert (right): appearance preserved
  magick in.tif -profile AdobeRGB1998.icc -profile sRGB.icc out.jpg

$ # assign (usually wrong for delivery): numbers kept, appearance changes
  magick in.tif -set profile:icc sRGB.icc out.jpg
```

**2 · Exporting "Same as source".** The single most common cause of muddy uploads. Your working space leaks into a delivery file.

&nbsp;

**3 · Stripping the profile to save bytes.** An untagged file is assumed sRGB. If it isn't sRGB, it's now wrong — and a P3 hero going out untagged is the bug that fills support queues.

&nbsp;

**4 · Round-tripping.** sRGB → Adobe RGB → sRGB does not return you to where you started; every conversion clips and quantises. Keep one master in the widest space and export from it each time.

&nbsp;

{% details 🧪 Checking what you actually shipped %}

```bash
# what profile is in the delivered file?
exiftool -ProfileDescription -ColorSpace dist/hero.jpg
#   Profile Description : sRGB IEC61966-2.1
#   Color Space         : sRGB                ← good

# batch audit: fail the build on anything that isn't sRGB
find dist -name '*.jpg' -exec sh -c '
  p=$(exiftool -s3 -ProfileDescription "$1")
  case "$p" in *sRGB*) ;; *) echo "❌ $1 → ${p:-untagged}";; esac
' _ {} \;
```

Run it after your image pipeline, not before. Optimisers are the usual culprit.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in vivid cyan](https://placehold.co/1000x8/3BD8F7/3BD8F7.png)

## 🖥️ "But my monitor is Adobe RGB"

Then your monitor covers a wide gamut, and colour management maps sRGB content into it correctly — **as long as your OS and browser are colour-managed and the monitor has an accurate profile.** Two things follow:

- Your screen is a *bad* proxy for your users'. Everything looks more saturated to you than to most of your audience.
- Don't "fix" it by editing in Adobe RGB and exporting as-is. That's the desaturation bug, delivered at scale.

Keep a second, boring sRGB display — or at minimum, check work in a colour-managed browser with the gamut emulated.

&nbsp;

![Section divider bar in purple](https://placehold.co/1000x8/A428E2/A428E2.png)

## 🧾 The checklist

1. **sRGB** is the web's delivery format. Everything else converts *into* it or ships alongside it.
2. **Display P3** is a delivery space for wide-gamut screens — tag it and provide a fallback.
3. **Adobe RGB** is a working/print space. Never deliver it to a browser.
4. Wider ≠ better: P3 wins on reds, Adobe RGB on cyans.
5. Convert, don't assign, and never export "same as source".
6. In CSS, use plain hex for the baseline and `oklch()` or `color(display-p3 …)` for the enhancement.
7. Audit the *delivered* files; your pipeline probably strips something.

For UI colour, none of this is a reason to avoid a plain hex palette — the palettes on [ColorFiind](https://colorfiind.com) are sRGB, which is exactly what you want as the baseline before any enhancement.

&nbsp;

{% cta https://colorfiind.com %} Start from an sRGB palette {% endcta %}

&nbsp;

*What's your working space, and does your export preset actually convert out of it? Worth checking today.* 👇
