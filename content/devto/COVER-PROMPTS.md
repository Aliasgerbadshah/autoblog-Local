# Cover image prompts — full spec pack

Everything an image model needs: exact canvas, composition zones, subject, lighting, palette with hex
codes, style, negative prompt and per-model parameters.

---

## Technical spec (applies to every prompt)

| Property | Value | Why |
| :-- | :-- | :-- |
| **Canvas** | **1000 × 420 px** | DEV's cover slot, exact |
| **Aspect ratio** | **50:21** (≈ 2.381:1) | what to pass as `--ar` |
| **Safe area** | keep the key subject inside the **middle 900 × 380** | DEV crops slightly on some feeds |
| **Title zone** | leave the **left third (0–330 px)** visually calm | so a title overlay stays readable |
| **Format** | PNG (or JPG ≥ 85% quality) | |
| **File size** | under **1 MB** | DEV compresses above this |
| **Text in image** | avoid — most models mangle it | add the title later in Figma/Canva |
| **Contrast** | keep the focal subject ≥ 3:1 against its background | thumbnails are small |

If your generator can't do 50:21 directly, render **16:9** and crop to 1000 × 420, or render
**1024 × 432** and downscale.

**Universal negative prompt** — paste into any model that supports one:

```text
text, letters, words, typography, watermark, signature, logo, brand marks, UI screenshot,
browser chrome, cluttered composition, busy background, low contrast, muddy colours, blurry,
jpeg artifacts, noise, distorted shapes, extra limbs, human faces, stock-photo look, drop shadows
on everything, gradient mesh soup
```

**Model parameters**

| Model | What to append / set |
| :-- | :-- |
| Midjourney v6 | `--ar 50:21 --style raw --stylize 250 --quality 2 --no text, watermark, logo` |
| DALL·E 3 | prompt as-is, add: *"wide banner, 1000x420 pixels, no text anywhere in the image"* |
| Ideogram | set aspect 16:9 → crop; it is the safest model **if you do want text** |
| Flux / SDXL | 1024×432, steps 30–40, CFG 4–6, sampler DPM++ 2M Karras, then downscale to 1000×420 |
| Firefly | Aspect: Widescreen; Content type: Art; Visual intensity: medium |

---

&nbsp;

## 01 — How to Pick a Color Palette That Survives Contact With Production

**Palette:** `#3C0D4F` deep purple · `#F22C33` vivid red · `#C6EEA0` soft lime · `#1681DF` bright blue · `#FFFFFF` paper

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector editorial
style with subtle paper grain.

SUBJECT: four large rectangular paint chips — deep purple #3C0D4F, vivid red #F22C33, soft lime
#C6EEA0 and bright blue #1681DF — arranged as a slightly fanned, overlapping stack on a clean
off-white #FAFAF7 surface, as if a designer just laid them out by hand. One chip is lifted slightly
above the others and casts a soft, short shadow. A thin, precise white measuring ruler with fine tick
marks lies diagonally across the chips, implying inspection and testing rather than decoration.

COMPOSITION: chips occupy the right two-thirds of the frame, angled about 8 degrees; the left third is
almost empty off-white space reserved for a title overlay. Everything important sits inside the
central 900x380 safe area. Generous negative space, strong asymmetric balance.

LIGHT: soft, even, diffuse studio light from the upper left; very subtle contact shadows only; no
dramatic highlights, no glow, no reflections.

STYLE: modern editorial vector illustration, crisp geometric edges, matte finish, high colour
fidelity, print-poster quality, minimal detail. Inspired by Swiss design and colour-swatch fan decks.

DO NOT INCLUDE: any text, letters, numbers, logos, watermarks, UI elements or human hands.
```

**Alt text:** `Four overlapping paint chips in purple, red, lime and blue with a ruler laid across them`

---

&nbsp;

## 02 — From HEX to Design Tokens: One Palette, Six Formats, Five Minutes

**Palette:** `#0F172A` midnight navy · `#00F5D4` mint · `#F15BB5` magenta · `#9B5DE5` violet

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector technical
diagram style on a dark midnight navy #0F172A background.

SUBJECT: a left-to-right transformation pipeline. On the left, three glowing colour discs in mint
#00F5D4, magenta #F15BB5 and violet #9B5DE5 sit stacked as an input. Thin luminous mint lines flow
right from them, splitting into six evenly spaced abstract output cards arranged in two rows of three.
Each card is a simple rounded rectangle in a slightly lighter navy #16213E with a small distinct
geometric glyph on it — curly braces, angle brackets, a gear, a node tree, a stack of layers, a
document corner — suggesting six file formats. No readable text on the cards.

COMPOSITION: input on the far left is small and calm; the pipeline widens to the right. Left third
kept dark and uncluttered for a title overlay. All elements inside the central 900x380 safe area.
Clear horizontal flow, generous spacing between cards, strong left-to-right reading direction.

LIGHT: dark scene lit by the mint accent lines themselves; soft bloom around the glowing lines,
subtle rim light on card edges; deep clean shadows, no lens flare.

STYLE: precise technical vector illustration, developer-poster aesthetic, thin consistent 2px line
weights, flat fills with minimal gradients, high contrast between the mint lines and the navy
background.

DO NOT INCLUDE: text, code characters that read as words, logos, watermarks, screenshots, people.
```

**Alt text:** `Colour discs flowing along glowing lines into six abstract file-format cards on a dark background`

---

&nbsp;

## 03 — Seasonal Color Analysis for Developers: Soft Summer UI vs Deep Winter Dark Mode

**Palette left:** `#D8C7D8` dusty mauve · `#8FA1B3` slate blue · `#E6DDE3` fog
**Palette right:** `#6D0F2B` burgundy · `#004D40` emerald · `#0B3D91` royal blue · `#2A1458` deep violet on `#050505`

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, split exactly down the
middle into two contrasting halves with one crisp vertical seam.

LEFT HALF — soft summer: a hazy, low-contrast, misty morning atmosphere in dusty mauve #D8C7D8, slate
blue #8FA1B3 and fog grey #E6DDE3. Soft gradient bands like layered mist over calm water, edges
blurred and gentle, muted and desaturated, quiet and airy.

RIGHT HALF — deep winter: sharp, high-contrast faceted geometric crystal shards on near-black #050505,
their facets coloured burgundy #6D0F2B, emerald #004D40, royal blue #0B3D91 and deep violet #2A1458,
like cut jewels catching cold light. Crisp edges, clean specular highlights, precise angles.

COMPOSITION: the vertical seam sits exactly at the horizontal centre; the two halves meet with no
blending, making the contrast the subject of the image. Keep both halves calm near the top edge so a
title can overlay either side. Everything inside the central 900x380 safe area.

LIGHT: left half — flat, diffuse, overcast daylight. Right half — directional cold light from the
upper right creating sharp specular glints on the crystal facets.

STYLE: editorial poster illustration, half painterly-soft and half hard-edged vector, deliberate
contrast in both colour temperature and rendering technique.

DO NOT INCLUDE: text, labels, arrows, logos, watermarks, faces, recognisable objects or brands.
```

**Alt text:** `Split banner: hazy pastel mist on the left, sharp dark jewel-toned crystals on the right`

---

&nbsp;

## 04 — Neon Dark Mode That Doesn't Hurt: Building a Glow UI

**Palette:** `#09090B` near-black · `#FF007F` hot pink · `#00E5FF` electric cyan · `#B8F500` acid lime

```text
A wide horizontal banner image, 1000x420 pixels, aspect ratio 50:21, cinematic dark scene on a
near-black #09090B background.

SUBJECT: three long horizontal neon light tubes stretching across the frame at slightly different
depths — hot pink #FF007F in front, electric cyan #00E5FF in the middle, acid lime #B8F500 behind.
They rest on a dark glossy glass surface that reflects them as soft vertical smears. The pink tube is
fully lit, the cyan tube is lit, and the lime tube is visibly dimmed to about 30 percent brightness,
suggesting intensity being turned down. Fine dust particles catch the light.

COMPOSITION: tubes run left to right with a slight perspective converging toward the right; the left
third is mostly empty darkness for a title overlay, lit only by ambient spill. Key elements inside the
central 900x380 safe area. Strong horizontal rhythm, deep negative space.

LIGHT: the tubes are the only light sources; realistic bloom and halation around them, soft falloff
into deep black, gentle reflections on the glass, no ambient fill light. Controlled and moody rather
than blown out.

STYLE: photoreal product-photography lighting fused with minimal graphic design; restrained
synthwave, high dynamic range, clean and premium — not a busy cyberpunk street scene.

DO NOT INCLUDE: text, signage, letters, logos, city scenes, people, lens flare streaks, heavy grain.
```

**Alt text:** `Three neon tubes — pink, cyan and dimmed lime — glowing on a dark reflective glass surface`

---

&nbsp;

## 05 — Automate Your Color Workflow: One Script, Any Palette

**Palette:** `#10002B` deep indigo · `#240046` violet · `#FF006E` hot pink · `#00F5D4` mint

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat isometric-lite vector
style on a deep indigo #10002B background.

SUBJECT: an automated colour factory. On the left, a simple rounded terminal window in violet #240046
with abstract mint #00F5D4 lines standing in for command output. From its right edge, a conveyor belt
runs across the frame carrying four glossy colour chips — deep indigo, violet #240046, hot pink
#FF006E and mint #00F5D4 — spaced evenly. At the right end the chips drop into three small open
output trays. A minimal mechanical arm silhouette hovers above the belt, mid-motion.

COMPOSITION: strict left-to-right flow along a horizontal belt at the lower two-thirds of the frame;
the upper left is open indigo space for a title overlay. Slight isometric tilt, about 15 degrees.
All key elements inside the central 900x380 safe area.

LIGHT: soft mint rim lighting from the right, subtle glow beneath the chips, gentle ambient occlusion
under the belt; no harsh shadows.

STYLE: clean technical vector illustration, developer-tool marketing aesthetic, consistent line
weights, flat fills with restrained soft shading, playful but precise.

DO NOT INCLUDE: text, code words, logos, watermarks, robot faces, humans, background clutter.
```

**Alt text:** `A terminal window feeding colour chips along a conveyor belt into output trays`

---

&nbsp;

## 00 — The Swatch Deck theme kit *(reference sheet — optional to publish)*

**Palette:** `#0F172A` navy · `#00F5D4` mint · `#F15BB5` magenta · `#9B5DE5` violet on charcoal

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, top-down flat-lay vector
illustration on a dark charcoal #1A1A1E desk surface.

SUBJECT: a designer's swatch fan deck opened in a wide arc, each blade a different saturated colour
including navy #0F172A, mint #00F5D4, magenta #F15BB5 and violet #9B5DE5, the blades fanning from a
single rivet on the left. Beside the fan, a simple geometric code-bracket symbol cut from matte metal
rests on the desk, connecting design and code.

COMPOSITION: the fan arc sweeps from lower left to upper right, occupying the right two-thirds; the
left third stays as clean dark desk space for a title. Everything inside the central 900x380 safe
area. Balanced, calm, product-photography-like arrangement.

LIGHT: soft top-down studio light, gentle contact shadows under each blade, slight sheen on the metal
bracket, no harsh reflections.

STYLE: refined flat vector with subtle depth, editorial product illustration, matte finish, precise
geometry, premium minimal look.

DO NOT INCLUDE: text, numbers on the blades, logos, watermarks, hands, background props.
```

**Alt text:** `An opened colour swatch fan deck beside a metal code-bracket symbol on a dark desk`

---

&nbsp;

## Sample renders

Two of these prompts run through an image model, unedited, resized to 1000×420 —
`assets/cover-samples/`:

| Post | File |
| :-- | :-- |
| 01 Choose a palette | `sample-01-choose-a-palette.png` |
| 04 Neon dark mode | `sample-04-neon-dark-mode.png` |

Both came back with the correct hex colours, an empty left third for the title, and no text —
which is the whole point of writing the prompt this way. Use them as-is or as a reference for
what "correct" looks like.

&nbsp;

## Master template for any future post

Fill the five brackets and you get a prompt at the same level of detail:

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, {STYLE: flat vector
editorial / technical diagram / cinematic photoreal / isometric-lite} on a {BACKGROUND COLOUR + HEX}
background.

SUBJECT: {CENTRAL METAPHOR for the topic — one clear object or scene, described in 2-3 sentences,
naming each element's colour with its hex code}.

COMPOSITION: {where things sit}. Left third kept calm and uncluttered for a title overlay. All key
elements inside the central 900x380 safe area. Generous negative space, clear reading direction.

LIGHT: {direction, softness, whether the subject is self-lit}. {Shadow behaviour}. No lens flare.

STYLE: {2-3 style anchors}, consistent line weights, {matte or glossy} finish, high colour fidelity,
poster quality.

DO NOT INCLUDE: text, letters, logos, watermarks, UI screenshots, faces, background clutter.
```

Then append the universal negative prompt and your model's parameters from the table at the top.

**Metaphor bank** — pick one that matches the topic instead of defaulting to abstract shapes:

| Topic type | Metaphor that reads well at thumbnail size |
| :-- | :-- |
| Choosing / comparing | paint chips, fan deck, weighing scale, two-way split frame |
| Pipelines / conversion | conveyor belt, funnel, branching arrows, assembly line |
| Performance / speed | stopwatch, race lanes, compressed spring, speed lines |
| Debugging / errors | magnifying glass over a tangle, single red thread in a weave |
| Architecture / structure | stacked layers, blueprint grid, scaffolding, isometric blocks |
| Accessibility / contrast | eye chart, light meter, dial between two extremes |
| Automation / tooling | robot arm, gears meshing, self-drawing line |
| Security | vault door, layered shields, keyhole of light |
| Data / analytics | glowing bar terrain, node constellation, sankey ribbons |
| Getting started / basics | seedling in a grid, first domino, open toolbox |
