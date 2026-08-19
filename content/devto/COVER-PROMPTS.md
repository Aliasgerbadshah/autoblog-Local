# Cover image prompts

DEV.to covers are **1000 × 420 px**. Nothing is set in the front matter — upload your own image in the
editor, then paste the URL DEV returns into a new `cover_image:` line.

Each prompt below is written to work in Midjourney / DALL·E / Ideogram / Firefly / Leonardo.
Ideogram and DALL·E handle the text-in-image versions best; for Midjourney append `--ar 1000:420`.

> **Tip:** if the generator mangles the text, generate the artwork *without* any text and add the title
> yourself in Canva/Figma — or just run `python3 tools/palette_kit.py cover --name "Your Title"
> --colors AABBCC ...` which renders a clean typographic cover locally.

---

## 01 — How to Pick a Color Palette That Survives Contact With Production

**Prompt**

> A wide 1000x420 banner illustration, flat vector editorial style. Four large vertical colour bands in
> deep purple #3C0D4F, vivid red #F22C33, soft lime #C6EEA0 and bright blue #1681DF, slightly tilted like
> paint chips being sorted by hand. A thin white measuring ruler overlays the bands, suggesting inspection
> and measurement. Clean off-white background on the left third for text space, generous negative space,
> crisp edges, no gradients, no photorealism, no text.

**Alt text to use on DEV:** `Four large colour chips being measured with a ruler, suggesting a palette being tested`

---

## 02 — From HEX to Design Tokens: One Palette, Six Formats, Five Minutes

**Prompt**

> A wide 1000x420 technical illustration, flat vector, dark navy #0F172A background. On the left, four
> colour swatches in mint #00F5D4, magenta #F15BB5 and violet #9B5DE5 feeding into a stylised pipeline of
> arrows that split into six small document cards labelled only by shape (CSS brackets, curly braces,
> a config gear, a JSON node tree). Neon mint glow lines connect the stages. Minimal, developer-poster
> aesthetic, lots of negative space, no readable text.

**Alt text:** `Colour swatches flowing through a pipeline into six file-format cards`

---

## 03 — Seasonal Color Analysis for Developers: Soft Summer UI vs Deep Winter Dark Mode

**Prompt**

> A wide 1000x420 split-screen illustration, flat vector. Left half: soft, hazy, low-contrast dusty mauve
> #D8C7D8 and slate blue #8FA1B3, a calm misty morning mood with a simple UI card outline. Right half:
> cool, high-contrast jewel tones — burgundy #6D0F2B, emerald #004D40, royal blue #0B3D91, deep violet
> #2A1458 — on near-black, sharp icy geometric shapes. A crisp vertical seam divides the two halves.
> Editorial poster style, no text, no faces.

**Alt text:** `Split banner: hazy pastel half on the left, dark jewel-toned half on the right`

---

## 04 — Neon Dark Mode That Doesn't Hurt: Building a Glow UI

**Prompt**

> A wide 1000x420 cinematic illustration, near-black #09090B background. Three neon light tubes in hot
> pink #FF007F, electric cyan #00E5FF and acid lime #B8F500 arranged as horizontal streaks with soft
> realistic bloom and subtle reflection on a dark glass surface. One tube is dimmed, hinting at
> "turning down the glow". Moody, minimal, synthwave-adjacent but restrained, plenty of dark negative
> space on the left, no text.

**Alt text:** `Three neon light tubes glowing against a near-black glass surface, one dimmed`

---

## 05 — Automate Your Color Workflow: One Script, Any Palette

**Prompt**

> A wide 1000x420 flat vector illustration on deep indigo #10002B. A stylised terminal window on the left
> with abstract command lines, emitting a conveyor belt to the right carrying four colour chips in
> #240046, hot pink #FF006E and mint #00F5D4, which drop into small labelled output trays. Robot-arm
> silhouette optional. Clean isometric-lite, technical poster style, soft mint glow accents, no readable
> text.

**Alt text:** `A terminal window feeding colour chips along a conveyor belt into output trays`

---

## 00 — The Swatch Deck theme kit *(reference sheet, optional to publish)*

**Prompt**

> A wide 1000x420 flat vector illustration: a designer's swatch fan deck opened in an arc, each blade a
> different saturated colour, laid on a dark charcoal desk next to a stylised code bracket symbol.
> Top-down view, soft shadow, editorial minimal style, no text.

**Alt text:** `An opened colour swatch fan deck beside a code bracket symbol`

---

## Reusable prompt template for future posts

Give me the topic and I'll fill this in for you — or edit it yourself:

```text
A wide 1000x420 banner illustration for a developer blog post about {TOPIC}.
Flat vector editorial style, {MOOD} mood.
Main visual: {CENTRAL METAPHOR}.
Colour palette: {HEX 1}, {HEX 2}, {HEX 3} on a {light/dark} background.
Composition: keep the left third relatively empty for a title overlay.
Crisp edges, generous negative space, no photorealism, no logos, no text.
```

Negative prompt (if your tool supports one):

```text
text, watermark, logo, ui screenshot, cluttered, busy background, lens flare, jpeg artifacts, extra limbs
```
