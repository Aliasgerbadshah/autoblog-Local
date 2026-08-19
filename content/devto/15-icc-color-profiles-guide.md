---
title: "A Designer's Guide to ICC Colour Profiles (Without the Colour Science Degree)"
published: false
description: "What an ICC profile actually contains, which one to use when, what the four rendering intents really do, and how to hand files to a printer without a colour surprise."
tags: design, webdev, printdesign, workflow
---

<!-- Cover image: upload your own 1000x420 image in the DEV editor,

     then paste the URL it returns into a new `cover_image:` line above.
     A ready-made generation prompt for this post is in COVER-PROMPTS.md. -->

| `#75A1C7` | `#D9ABB7` | `#EADEB8` | `#C5AFDE` |
| :--: | :--: | :--: | :--: |
| ![cyan swatch #75A1C7](https://placehold.co/120x56/75A1C7/75A1C7.png) | ![light pink swatch #D9ABB7](https://placehold.co/120x56/D9ABB7/D9ABB7.png) | ![light yellow swatch #EADEB8](https://placehold.co/120x56/EADEB8/EADEB8.png) | ![light indigo swatch #C5AFDE](https://placehold.co/120x56/C5AFDE/C5AFDE.png) |

> 🎨 **Palette in play** — [Bluebell Mood Horizon](https://colorfiind.com/palette/bluebell-mood-horizon) · `#75A1C7` `#D9ABB7` `#EADEB8` `#C5AFDE`

An ICC profile is not a filter, a look, or a setting that makes colours nicer. It is a **translation dictionary** for one device.

&nbsp;

That's the whole concept. Everything else — the four rendering intents, the v2/v4 argument, why your blue printed purple — follows from it, and you can learn the useful parts in ten minutes.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 📖 What's actually inside a profile

A profile answers one question: *for this device, what real-world colour do these numbers produce?*

&nbsp;

It does that by mapping device numbers to a device-independent reference space — the **Profile Connection Space**, either CIE XYZ or Lab. Convert from one device to another and you go via the PCS:

```console
your file (Adobe RGB)  →  PCS (Lab)  →  the press (FOGRA51 CMYK)
        ^source profile        ^          ^destination profile
                        device-independent
```

Two profiles per conversion, always: one to interpret the source, one to target the destination. If either is missing or wrong, the result is wrong — confidently and silently.

| Profile class | What it describes | You meet it as |
| :-- | :-- | :-- |
| **Colour space** (`spac`) | an abstract space, not a device | sRGB, Adobe RGB, Display P3, ProPhoto |
| **Display** (`mntr`) | a specific monitor | what calibration writes |
| **Output** (`prtr`) | a printer + ink + paper combination | FOGRA51, GRACoL, your vendor's file |
| **Input** (`scnr`) | a camera or scanner | rarely handled by hand |

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 🎯 Which one to use, and when

| Situation | Profile | Why |
| :-- | :-- | :-- |
| Anything for the web | **sRGB IEC61966-2.1** | the web's assumed default; anything else risks being misread |
| Wide-gamut web imagery | **Display P3** | tag it, always, and provide an sRGB fallback |
| Photo editing you'll output later | **Adobe RGB** or **ProPhoto** | keeps gamut you'd otherwise throw away early |
| European offset, coated stock | **FOGRA51 / PSO Coated v3** | current European standard |
| North American offset | **GRACoL 2013** | the equivalent over there |
| Newsprint / uncoated | vendor's profile | tiny gamut; never guess this one |
| A specific press | **whatever your printer sends you** | beats every generic profile |

The rule that saves the most grief: **ask the print vendor for their profile before you start**, not after the proof looks wrong.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 🔀 The four rendering intents, in plain terms

When a colour can't be reproduced by the destination, the intent decides what happens instead.

| Intent | What it does | Use for |
| :-- | :-- | :-- |
| **Perceptual** | compresses the *whole* gamut so relationships survive; everything shifts a little | photographs, gradients, big gamut → small gamut |
| **Relative colorimetric** | keeps in-gamut colours exact, clips out-of-gamut ones to the nearest match; maps source white to paper white | logos, brand colours, most print work |
| **Absolute colorimetric** | like relative, but keeps the *source* white — simulating another paper's tint | proofing only |
| **Saturation** | prioritises vividness over accuracy | charts and business graphics, rarely else |

Two practical defaults:

- **Relative colorimetric with Black Point Compensation** for general work, and for anything where a specific brand colour must be right.
- **Perceptual** when converting rich photography into a genuinely small gamut like uncoated stock or newsprint, where clipping would flatten the shadows.

> 🧪 **Lab note** — Black Point Compensation maps the source black to the destination black instead of clipping it. Without it, shadow detail disappears into a flat dark patch. It effectively only applies to the colorimetric intents.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 💜 Why your blue prints purple

This is the single most common packaging surprise, and this palette demonstrates it nicely. `#75A1C7` is a calm, slightly greyed blue on screen. RGB blues live in a region that CMYK inks simply cannot reach: process cyan plus magenta cannot produce the *brightness* of emitted blue light, so the nearest printable colour sits darker and more toward violet.

&nbsp;

Your options, in order:

1. **Soft-proof before you commit.** Photoshop/Illustrator/InDesign → *View → Proof Setup* → the destination profile, then *Proof Colors*. Turn on the gamut warning to see which areas will shift.
2. **Pull the colour into gamut yourself** in the source file, so you choose the compromise rather than the algorithm.
3. **Use a spot colour** for a brand blue that must be exact. That's what spot inks are for.
4. **Adjust the design** so the blue doesn't sit next to a colour it will suddenly resemble.

Pastels like the rest of this palette convert far more happily — a warm cream `#EADEB8` or a soft blush `#D9ABB7` are well inside CMYK's reach. Saturated blues, bright oranges and vivid greens are the risky ones.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 🌐 The web half of the workflow

Print people know profiles; web people often assume they don't apply. They do:

- **CSS colours are sRGB by definition.** No profile is embedded and none is needed.
- **Images carry their own profile.** Untagged images are assumed sRGB by browsers — fine if they really are sRGB, wrong if they aren't.
- **Export web assets as sRGB with the profile embedded.** The few kilobytes are worth it.
- **CDNs and optimisers strip profiles.** Check the delivered file, not the source file.

```bash
# what profile is actually in the file that shipped?
exiftool -icc_profile:all -ProfileDescription dist/hero.jpg
#   Profile Description : sRGB IEC61966-2.1     ← good

# convert and tag in one step
magick source.tif -profile AdobeRGB1998.icc -profile sRGB.icc -quality 88 dist/hero.jpg
```

That second command reads the file *as* Adobe RGB and converts it *into* sRGB. Order matters: assigning a profile reinterprets the numbers, converting changes them so the appearance is preserved. **Assign is a relabel; convert is a translation.** Mixing them up is how images end up oversaturated or flat.

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 🎨 A packaging handoff that doesn't come back

```console
$ # the file you send should be able to answer all of these
  1. What profile is embedded?            → the destination CMYK, or tagged RGB if the vendor prefers
  2. Which rendering intent was used?      → relative colorimetric + BPC, unless photography said otherwise
  3. Are brand colours spot or process?    → spot for exact-match, process for everything else
  4. Total ink coverage within limits?     → typically ≤ 300% coated, ≤ 260% uncoated — ask
  5. Is black text 100% K only?            → not a four-colour mush that misregisters
  6. Do die lines stay as spot colours?    → yes, and set to overprint
  7. Was it soft-proofed and signed off?   → screenshot of the soft proof in the handoff notes
```

Answer those seven and most reprints disappear.

&nbsp;

{% details 🧾 Building a small colour-handoff spec for a brand %}

```markdown
## Brand colour: Bluebell #75A1C7

| Medium            | Value                                  | Notes                        |
| ----------------- | -------------------------------------- | ---------------------------- |
| Web / sRGB        | #75A1C7                                | CSS default, no profile      |
| Wide gamut        | color(display-p3 0.4952 0.6266 0.7666) | same colour, P3 coordinates  |
| Coated offset     | ask vendor → FOGRA51 conversion        | rel. colorimetric + BPC      |
| Uncoated          | expect a duller result; consider spot  | soft-proof before sign-off   |
| Spot (if critical)| specify a Pantone match                | for logo use only            |
```

One table per brand colour, stored with the brand guidelines. It ends the "which blue is the real blue" conversation permanently.

&nbsp;

{% enddetails %}

&nbsp;

![Section divider bar in light yellow](https://placehold.co/1000x8/EADEB8/EADEB8.png)

## 🧾 The checklist

1. A profile is a translation dictionary for one device — nothing more.
2. Every conversion uses two: source and destination.
3. Web = sRGB, tagged. Print = the vendor's profile, always ask.
4. Default to **relative colorimetric + BPC**; use perceptual for photos into small gamuts.
5. **Assign** relabels, **convert** translates. Know which one you're doing.
6. Soft-proof with gamut warning before any print commitment.
7. Saturated blues, oranges and greens are your CMYK risk list — pastels are safe.

Starting from a palette that already lives comfortably in both worlds saves a conversion argument later; sets like the pastels on [ColorFiind](https://colorfiind.com) convert to CMYK with far fewer surprises than a screen-native neon.

&nbsp;

{% cta https://colorfiind.com %} Browse print-friendly palettes {% endcta %}

&nbsp;

*What's the worst colour shift you've had come back from a printer? Mine was a "rich navy" that arrived as denim.* 👇
