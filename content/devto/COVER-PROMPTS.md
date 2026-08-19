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

&nbsp;

## 06 — CSS color-mix(): Create Every Colour Variation From One Token

**Palette:** `#003566` deep navy · `#0077B6` cyan-blue · `#D0006F` magenta · `#C1121F` red

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector editorial
style on a clean off-white #F7F9FB background.

SUBJECT: two large circles of liquid paint — one cyan-blue #0077B6 on the left, one magenta #D0006F
on the right — flowing toward each other and overlapping in the centre, where the intersection forms
a smooth blended violet. Radiating out from the blend, five smaller circles step away in even
increments, each a slightly different tint and shade of the blend, arranged in a gentle arc like
generated variants. The liquid has clean vector edges, not photographic splashes.

COMPOSITION: the two source circles sit right of centre; the arc of derived variants sweeps to the
lower right. The left third is empty off-white space for a title overlay. All key shapes inside the
central 900x380 safe area. Balanced, airy, plenty of white space.

LIGHT: flat even illustration lighting, no cast shadows, very subtle soft inner shading on each circle
to suggest depth without realism.

STYLE: modern editorial vector illustration, crisp geometric edges, matte finish, high colour
fidelity, Swiss-poster restraint, minimal detail.

DO NOT INCLUDE: text, letters, numbers, percentage signs, logos, watermarks, UI elements, code.
```

**Alt text:** `Two circles of cyan and magenta paint overlapping into a blend, with derived variant circles arcing away`

&nbsp;

## 07 — CSS Relative Color Syntax: Build a Whole Scale From One Base Colour

**Palette:** `#994129` clay · `#E0AED7` orchid · `#E3DFF1` pale indigo · `#F5F7F9` off-white

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector editorial
style on a soft off-white #F5F7F9 background.

SUBJECT: a single large clay-red #994129 sphere on the left acting as a source. From it, a horizontal
row of nine smaller spheres extends to the right, each one a precisely stepped variation — the first
few progressively lighter and softer toward pale peach, the last few progressively deeper toward dark
burgundy. Thin elegant connector lines link the source sphere to each derived sphere, like a lineage
diagram. Even spacing, mathematical regularity.

COMPOSITION: the source sphere sits at roughly one-third from the left and is clearly the largest;
the derived row runs along the horizontal centre line to the right edge. Upper-left area stays clean
for a title overlay. All spheres inside the central 900x380 safe area.

LIGHT: soft top-left studio light, gentle contact shadow under each sphere, subtle matte shading —
enough to read as spheres, not glossy 3D renders.

STYLE: refined vector infographic, design-system documentation aesthetic, precise geometry, consistent
1.5px connector lines, muted sophisticated palette.

DO NOT INCLUDE: text, numbers, labels, logos, watermarks, code, gradients meshes, human hands.
```

**Alt text:** `One large clay-red sphere connected by thin lines to nine progressively lighter and darker spheres`

&nbsp;

## 08 — light-dark() in CSS: One Line Instead of Two Theme Blocks

**Palette:** `#0D1C1A` near-black teal · `#162C46` navy · `#BC22BF` magenta · `#B2F1B1` mint

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector style, split
into two halves by a single clean diagonal seam running from the top-centre to the bottom-centre-left.

LEFT HALF — light theme: a soft off-white #F7FAF9 surface holding a simple abstract UI card outline in
pale grey, with a small magenta #8E1A91 accent shape and a mint #B2F1B1 badge.
RIGHT HALF — dark theme: the exact same abstract UI card composition, mirrored in tone, on a
near-black teal #0D1C1A surface with a navy #162C46 card, a brighter magenta #BC22BF accent and the
same mint badge.

The key idea: identical layout, two skins, meeting at one crisp seam — as if a single switch flipped
half the image.

COMPOSITION: the seam is slightly diagonal for energy, about 12 degrees off vertical. Each half holds
its card in the same relative position so the mirroring is obvious. Title space in the upper-left of
the light half. All elements inside the central 900x380 safe area.

LIGHT: flat illustration lighting on both halves; the dark half slightly glows around its magenta
accent, the light half has soft neutral shadows.

STYLE: clean flat vector UI illustration, rounded corners, consistent stroke weights, no realistic
textures, product-marketing polish.

DO NOT INCLUDE: readable text, letters, real UI screenshots, logos, watermarks, toggle switch icons
with words, faces.
```

**Alt text:** `Identical abstract UI card shown in a light theme and a dark theme, split by a diagonal seam`

&nbsp;

## 09 — HWB Color Explained: The Notation That Thinks in Tints and Shades

**Palette:** `#00E1FF` pure teal-cyan · `#85CDD6` soft teal · `#FAF2E0` cream · `#EDB9A1` peach

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector illustration
with subtle paper texture, on a warm cream #FAF2E0 background.

SUBJECT: a painter's colour-mixing metaphor rendered geometrically. In the centre, a single pure
teal-cyan #00E1FF square. To its left, three squares showing the same hue progressively mixed with
white, becoming paler and paler. To its right, three squares showing the same hue progressively mixed
with black, becoming deeper and deeper. Above the row, two small tilted containers pour thin ribbons
of white paint and black paint down toward the respective sides, making the cause visible.

COMPOSITION: the seven-square row sits along the lower two-thirds, centred slightly right; the pouring
containers occupy the upper right. Upper-left corner is clean cream space for a title overlay. All
elements inside the central 900x380 safe area.

LIGHT: flat, even, no cast shadows; the paint ribbons have gentle soft shading to read as liquid.

STYLE: warm editorial vector illustration, matte finish, subtle grain, precise geometric squares
contrasted with two organic paint ribbons, art-supply-shop charm without being twee.

DO NOT INCLUDE: text, numbers, percentage labels, logos, watermarks, brushes with brand names, hands.
```

**Alt text:** `A row of squares in one teal hue, getting paler to the left and deeper to the right, with white and black paint pouring in`

&nbsp;

## 10 — Display-P3 vs sRGB: When Wide-Gamut Colour Is Worth It

**Palette:** `#081C09` near-black · `#00E6FF` cyan · `#FF24FF` magenta · `#FFF238` yellow

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, on a deep near-black
#081C09 background, flat vector with luminous accents.

SUBJECT: two nested chromaticity-style shapes representing colour gamuts. A smaller triangle in muted
grey-white outline sits inside a noticeably larger triangle whose edges glow in cyan #00E6FF, magenta
#FF24FF and yellow #FFF238. The area between the two triangles is filled with a rich saturated colour
wash, visually showing the extra colour the larger gamut can reach. Small luminous dots scatter across
the outer band like sample points, a few of them sitting just outside the inner triangle.

COMPOSITION: the nested triangles occupy the right two-thirds, tilted very slightly; the left third
stays dark and empty for a title overlay. All shapes inside the central 900x380 safe area. Strong
figure-ground contrast, scientific but beautiful.

LIGHT: the triangle edges are self-luminous with soft bloom; the background is deep matte black with
a faint vignette; no external light source.

STYLE: precise data-visualisation aesthetic crossed with a science poster, thin glowing strokes,
clean vector geometry, premium and technical.

DO NOT INCLUDE: text, axis labels, numbers, logos, watermarks, photographs of monitors, people.
```

**Alt text:** `A small grey triangle nested inside a larger glowing triangle, showing the extra colour a wider gamut reaches`

&nbsp;

&nbsp;

## 11 — Ship Wide-Gamut Colour Without Breaking Every Older Display

**Palette:** `#03071E` midnight navy · `#DC2F02` flame · `#F48C06` amber · `#FFBA08` gold

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector editorial
style on a deep midnight navy #03071E background.

SUBJECT: two side-by-side rectangular display panels shown at a slight three-quarter angle, like
monitors on a desk. The left panel is older and thicker-bezelled, showing a simple abstract composition
in slightly muted flame #DC2F02 and amber #F48C06. The right panel is thin and modern, showing the
exact same composition but in noticeably richer, more saturated versions of the same colours plus gold
#FFBA08. A soft luminous arc connects the two panels, suggesting the same design gracefully adapting
rather than breaking.

COMPOSITION: the two panels sit in the right two-thirds, the older one slightly smaller and further
back. The left third stays dark and empty for a title overlay. All elements inside the central 900x380
safe area. Clear visual pairing, generous negative space.

LIGHT: soft ambient light from the upper left; both panels emit a gentle glow matching their content;
subtle contact shadows beneath each panel.

STYLE: clean flat vector illustration with light dimensional shading, product-marketing polish,
consistent stroke weights, no photorealistic reflections.

DO NOT INCLUDE: text, UI screenshots, readable interface elements, brand logos, watermarks, cables,
people.
```

**Alt text:** `An older monitor and a modern monitor showing the same design, the modern one in richer colour`

&nbsp;

## 12 — Color-Gamut Media Queries: Better Product Colour for P3 Displays

**Palette:** `#BDE0FE` powder · `#CDB4DB` lilac · `#FFC8DD` blush · `#A8DADC` mint

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector e-commerce
illustration on a clean white #FDFDFD background.

SUBJECT: a horizontal row of four large circular fabric swatches in powder blue #BDE0FE, lilac
#CDB4DB, blush pink #FFC8DD and mint #A8DADC, each with a subtle woven-textile texture and a small
metal eyelet at the top, like a retail colour sample ring. A thin elegant metal ring threads through
all four. In front of the row, a simple rounded rectangle representing a phone screen displays the same
four circles, slightly more vivid, implying the digital rendering matching the physical samples.

COMPOSITION: the swatch ring occupies the right two-thirds, angled gently; the phone shape overlaps the
lower centre. The left third stays clean white for a title overlay. All elements inside the central
900x380 safe area.

LIGHT: bright soft studio light from above, delicate contact shadows under the swatches, subtle sheen
on the metal ring; airy and premium retail feel.

STYLE: refined flat vector with soft textile texture, boutique e-commerce aesthetic, muted pastel
palette, plenty of white space.

DO NOT INCLUDE: text, price tags with numbers, brand logos, watermarks, hands, faces, hangers.
```

**Alt text:** `Four pastel fabric swatches on a metal ring beside a phone showing the same four colours`

&nbsp;

## 13 — Why the Same HEX Colour Looks Different on Every Monitor

**Palette:** `#232E43` navy · `#49627F` steel blue · `#7F95B1` slate · `#E8E9E5` off-white

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector editorial
style on an off-white #E8E9E5 background.

SUBJECT: five simplified monitor shapes of different sizes and ages arranged in a loose row, each one
displaying the same single filled rectangle — but each rendered in a visibly different version of the
same blue, ranging from washed-out pale slate #7F95B1 through steel blue #49627F to deep navy #232E43,
with one screen slightly too warm and one slightly too cool. Above the row, a single small blue square
labelled by nothing acts as the "source of truth", with thin lines fanning down to each screen.

COMPOSITION: the row of screens runs along the lower two-thirds; the source square sits upper centre
right. The left third remains clean off-white for a title overlay. All elements inside the central
900x380 safe area.

LIGHT: flat even illustration lighting; each screen has a faint internal glow in its own colour cast;
minimal soft shadows below the monitors.

STYLE: precise editorial infographic vector, thin consistent lines, muted analytical palette,
documentation-diagram clarity.

DO NOT INCLUDE: text, labels, numbers, logos, watermarks, desks with clutter, people, cables.
```

**Alt text:** `One blue square fanning out to five monitors, each displaying a slightly different version of it`

&nbsp;

## 14 — How Browser Colour Management Changes What Your Users See

**Palette:** `#08161C` near-black teal · `#5500FF` electric violet · `#FF5724` coral · `#38FF59` lime

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, flat vector technical
illustration on a very dark teal-black #08161C background.

SUBJECT: a colour conversion pipeline shown as a horizontal flow. On the left, three image tiles enter
the flow — one clearly tagged with a small profile marker icon, one untagged and slightly faded. They
pass through a central translucent prism or lens shape that splits and re-aligns the light. On the
right, the tiles emerge onto a stylised screen shape, the tagged one rendering in vivid electric violet
#5500FF, coral #FF5724 and lime #38FF59, the untagged one visibly duller and shifted.

COMPOSITION: strict left-to-right flow along the horizontal centre. The prism sits at the middle. The
left third above the incoming tiles stays dark and open for a title overlay. All elements inside the
central 900x380 safe area.

LIGHT: the prism and the vivid output glow with soft bloom against the near-black background; the
untagged path is lit more dimly to reinforce the difference; no external light source.

STYLE: precise technical vector diagram meets neon poster, thin luminous strokes, high contrast,
premium developer-documentation aesthetic.

DO NOT INCLUDE: text, file names, readable icons with words, logos, watermarks, photographs, people.
```

**Alt text:** `Two image tiles passing through a prism, one emerging vivid and one emerging dull and shifted`

&nbsp;

## 15 — A Designer's Guide to ICC Colour Profiles

**Palette:** `#75A1C7` bluebell · `#D9ABB7` blush · `#EADEB8` cream · `#C5AFDE` lilac

```text
A wide horizontal banner illustration, 1000x420 pixels, aspect ratio 50:21, warm flat vector
illustration with subtle paper grain on a cream #F7F3EA background.

SUBJECT: a print-studio still life, top-down. On the left, a printed colour test strip on textured
paper showing four bands in bluebell #75A1C7, blush #D9ABB7, cream #EADEB8 and lilac #C5AFDE. On the
right, a simplified tablet screen showing the same four bands slightly brighter. Between them sits a
small colorimeter puck and a folded paper swatch book, tying screen and paper together. One band on the
printed strip is subtly different from its on-screen counterpart, hinting at gamut shift.

COMPOSITION: printed strip lower left of centre, screen upper right, measuring device between them.
The left third is calm cream paper for a title overlay. All elements inside the central 900x380 safe
area. Balanced flat-lay arrangement, generous space.

LIGHT: soft diffuse overhead studio light, gentle paper texture shadows, matte finish everywhere, no
glare on the screen.

STYLE: warm editorial vector flat-lay, print-workshop atmosphere, precise geometry with tactile paper
texture, muted sophisticated palette.

DO NOT INCLUDE: text, printed labels, numbers, brand logos, watermarks, hands, faces.
```

**Alt text:** `A printed colour test strip beside a tablet showing the same four colours, with a colorimeter between them`

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
