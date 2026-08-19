---
title: "Colour Management for Social Media: Why Your Exports Change Colour"
published: false
description: "Your brand orange goes muddy on Instagram and cool on LinkedIn. Here's what platforms actually do to uploads, and the export preset that survives all of them."
tags: design, webdev, socialmedia, branding
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#EB9C8E` | `#F3DA91` | `#9ADFD3` | `#A3B4EB` |
| :--: | :--: | :--: | :--: |
| ![light red swatch #EB9C8E](https://placehold.co/120x56/EB9C8E/EB9C8E.png) | ![light yellow swatch #F3DA91](https://placehold.co/120x56/F3DA91/F3DA91.png) | ![light teal swatch #9ADFD3](https://placehold.co/120x56/9ADFD3/9ADFD3.png) | ![light blue swatch #A3B4EB](https://placehold.co/120x56/A3B4EB/A3B4EB.png) |

> 🎨 **Palette in play** — [Honeydew Collection Daydream](https://colorfiind.com/palette/honeydew-collection-daydream) · `#EB9C8E` `#F3DA91` `#9ADFD3` `#A3B4EB`

You approve a carousel in Figma. It goes up on Instagram. The coral is now salmon, the mint has gone grey, and someone in the brand channel asks whether the file got compressed twice.

&nbsp;

It probably did — but compression isn't why the colour moved. **Colour space is.** And the fix is one export setting that almost nobody has checked since they set up their preset.

&nbsp;

![Section divider bar in light red](https://placehold.co/1000x8/EB9C8E/EB9C8E.png)

## 🔁 What happens to an upload

Every platform runs some version of the same pipeline:

```console
your file → strip/normalise metadata → convert to sRGB → resize → re-encode (lossy) → serve
```

Two of those steps change colour:

&nbsp;

**Convert to sRGB.** If your file is tagged correctly, this is a faithful conversion and you barely notice. If your file is **untagged**, the platform assumes it's already sRGB and simply reinterprets the numbers. Upload an untagged Adobe RGB or Display P3 export and you get the classic washed-out result — the numbers were never sRGB, but now they're treated as if they were.

&nbsp;

**Re-encode.** Lossy compression on top of your lossy export. Subtle gradients suffer most, which is why soft, filmic palettes like the one above are more fragile on social than punchy ones.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/F3DA91/F3DA91.png)

## 🎯 The single setting that fixes most of it

**Export sRGB, 8-bit, with the profile embedded.**

| Tool | Where |
| :-- | :-- |
| Lightroom Classic | Export → File Settings → **Colour Space: sRGB** |
| Photoshop | Edit → Convert to Profile → sRGB IEC61966-2.1, then Export As with **Embed Colour Profile** ticked |
| Figma | Export → set the file's colour profile to sRGB in Document Colour Profile |
| Affinity / Capture One | Export dialog → ICC profile → sRGB |
| Canva | exports sRGB by default — the risk is your *imported* assets |

The trap in every one of those tools is a setting called **"Same as source"** or **"Document profile"**. It leaks your wide working space into a delivery file. If your preset says that, that's your bug.

> 🚧 **Trap** — Photoshop's legacy *Save for Web* strips the profile by default. The file is sRGB and untagged, which usually works but fails the moment a platform or browser guesses differently. Use **Export As** with *Embed Colour Profile*.

&nbsp;

![Section divider bar in light teal](https://placehold.co/1000x8/9ADFD3/9ADFD3.png)

## 📉 Why coral and mint suffer most

The colours that shift most are the ones sitting near a gamut boundary or in a hue region where conversion is least forgiving — warm reds, oranges, and desaturated teals.

&nbsp;

Look at what happens to this palette's coral when it's *misinterpreted* rather than converted:

| Colour | Correctly tagged sRGB | Same numbers, if they were really P3 |
| :-- | :-- | :-- |
| ![light red swatch #EB9C8E](https://placehold.co/70x26/EB9C8E/EB9C8E.png) `#EB9C8E` | coral, as designed | duller, pinker, "sun-faded" |
| ![light teal swatch #9ADFD3](https://placehold.co/70x26/9ADFD3/9ADFD3.png) `#9ADFD3` | soft mint | greyer, closer to sage |

That "sun-faded coral, sage instead of mint" description is precisely what brand teams report. It isn't the compression. It's a profile mismatch that happened at export.

&nbsp;

![Section divider bar in light blue](https://placehold.co/1000x8/A3B4EB/A3B4EB.png)

## 📱 Platform-by-platform reality

| Platform | Behaviour worth knowing |
| :-- | :-- |
| **Instagram** | Converts to sRGB and re-encodes aggressively. P3 uploads are inconsistent — treat wide gamut as unreliable here. Export at the exact target size so it doesn't resize for you. |
| **X / Twitter** | Re-encodes hard; PNG under a size threshold survives better for flat graphics with text. |
| **LinkedIn** | Kind to PNGs, rough on large JPEGs; text-heavy carousels do best as PNG. |
| **Facebook** | Strips profiles historically; sRGB tagged is the safe path. |
| **Pinterest** | Long-lived assets, so banding and artefacts stay visible for years — export generously. |
| **WhatsApp / messaging** | Heaviest recompression of all. Assume anything shared this way will lose subtle tone. |

The pattern: **flat graphics with text → PNG. Photographs → high-quality JPEG at the platform's native size.**

&nbsp;

![Section divider bar in light red](https://placehold.co/1000x8/EB9C8E/EB9C8E.png)

## 📏 Export at the target size, not larger

Counter-intuitive but consistently true: uploading a 4000px master makes things *worse*. The platform resizes with its own algorithm and re-encodes at its own quality. Do the resize yourself, in a tool you control.

```console
$ # square post, done properly
  1080 × 1080, JPEG quality 85, sRGB tagged, no output sharpening

$ # portrait
  1080 × 1350

$ # LinkedIn / X flat graphic with text
  PNG-24, sRGB tagged, 1200 × 675
```

Quality 85 rather than 100 is deliberate: an enormous file invites more aggressive recompression, and the visible result is often worse than a well-made 85.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/F3DA91/F3DA91.png)

## 🛠️ A repeatable pipeline

```bash
#!/usr/bin/env bash
# social-export.sh master.tif → correctly-tagged platform assets
set -euo pipefail
src="$1"; out="${2:-dist/social}"
mkdir -p "$out"

# photograph → square + portrait, sRGB tagged, quality 85
for spec in "1080x1080:square" "1080x1350:portrait"; do
  size="${spec%%:*}"; name="${spec##*:}"
  magick "$src" \
    -profile /usr/share/color/icc/sRGB.icc \
    -resize "${size}^" -gravity center -extent "$size" \
    -quality 85 -sampling-factor 4:2:0 \
    -strip -profile /usr/share/color/icc/sRGB.icc \
    "$out/${name}.jpg"
done

# flat graphic with text → PNG, no subsampling at all
magick "$src" -profile /usr/share/color/icc/sRGB.icc \
  -resize 1200x675 -strip -profile /usr/share/color/icc/sRGB.icc \
  "$out/card.png"

exiftool -s3 -ProfileDescription "$out"/*.jpg "$out"/*.png
```

Note the order: **convert into sRGB → resize → strip other metadata → re-attach the profile.** Stripping before converting is the mistake that produces untagged, wrongly-interpreted files.

&nbsp;

{% details 🎨 Keeping brand colour honest across the whole system %}

&nbsp;

Give every brand colour a row per medium and store it with the guidelines:

```markdown
## Brand coral

| Medium              | Value                                  |
| ------------------- | -------------------------------------- |
| Web / CSS           | #EB9C8E                                |
| Wide-gamut CSS      | color(display-p3 0.8769 0.6254 0.5699) |
| Social exports      | #EB9C8E, sRGB tagged, 8-bit            |
| Print (coated)      | convert via vendor profile, rel. col. + BPC |
```

When someone asks "is this the right coral?", the answer is a lookup instead of an argument.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in light teal](https://placehold.co/1000x8/9ADFD3/9ADFD3.png)

## 🧪 Verifying, in two minutes

```console
$ # 1 · what did you actually export?
  exiftool -ProfileDescription -ColorSpace -BitsPerSample post.jpg

$ # 2 · upload one test image to a private/close-friends post

$ # 3 · download it back and compare pixel values, not appearances
  magick compare -metric RMSE original.jpg downloaded.jpg diff.png

$ # 4 · check on an Android phone as well as an iPhone
  the two render wide-gamut content very differently
```

Step 3 is the one people skip, and it's the only step that gives you a number instead of an opinion.

&nbsp;

![Section divider bar in light blue](https://placehold.co/1000x8/A3B4EB/A3B4EB.png)

## 🧾 The checklist

1. Export **sRGB, 8-bit, profile embedded** — every time, every platform.
2. Kill "Same as source" in your presets.
3. Resize to the platform's native dimensions yourself.
4. JPEG ~85 for photographs; PNG for flat graphics with text.
5. Don't rely on P3 surviving a social pipeline.
6. Convert → resize → strip → re-tag, in that order.
7. Test by downloading your own post back and comparing.

Brand palettes that are defined in sRGB from the start avoid this entire category of problem — which is why I keep the canonical values as plain hex, the way palettes are published on [ColorFiind](https://colorfiind.com), and treat everything wider as an enhancement.

&nbsp;

{% cta https://colorfiind.com %} Define your palette in sRGB first {% endcta %}

&nbsp;

*Which platform mangles your brand colour worst? I'll bet it's the one you export to least often.* 👇
