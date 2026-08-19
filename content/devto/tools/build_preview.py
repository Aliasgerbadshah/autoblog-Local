#!/usr/bin/env python3
"""
build_preview.py — render the DEV.to markdown files into a self-contained
static preview site (no CDN, no JavaScript frameworks, stdlib only).

    python3 tools/build_preview.py            # writes preview/*.html
    python3 -m http.server 8080 --directory content/devto/preview

Supports the exact markdown subset used by the articles: front matter, GFM
tables, fenced code with per-language highlighting, images, links, blockquotes,
lists, hr, and DEV's Liquid tags (details / cta / katex / embed).
"""

from __future__ import annotations

import html
import json
import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent                      # content/devto
OUT = ROOT / "preview"

ARTICLES = [
    ("00-swatch-deck-theme-kit.md", "Reusable theme kit"),
    ("01-colorfiind-developer-tour.md", "Feature tour"),
    ("02-hex-to-design-tokens.md", "Tokens pipeline"),
    ("03-seasonal-palettes-ui-themes.md", "Seasonal themes"),
    ("04-neon-dark-mode-glow-ui.md", "Neon dark mode"),
    ("05-automate-palette-workflow.md", "Automation"),
]

# --------------------------------------------------------------- highlighting

KEYWORDS = {
    "js": r"\b(const|let|var|function|return|import|export|from|default|if|else|for|of|in|new|class|extends|async|await|try|catch|throw|typeof|null|undefined|true|false|this|=>)\b",
    "ts": r"\b(const|let|var|function|return|import|export|from|type|interface|as|keyof|if|else|for|of|in|new|class|async|await|null|undefined|true|false|=>)\b",
    "python": r"\b(def|return|import|from|as|if|elif|else|for|in|while|try|except|raise|class|lambda|with|None|True|False|and|or|not|yield)\b",
    "php": r"\b(function|return|declare|strict_types|array|foreach|as|if|else|static|use|echo|printf|new|class|public|private|const|null|true|false|string|float|int)\b",
    "bash": r"\b(cd|npm|npx|node|python3|php|git|echo|export|run|test|open|tree|curl)\b",
    "yaml": r"^\s*[-\w./]+(?=:)",
    "json": r'"(?:[^"\\]|\\.)*"(?=\s*:)',
    "scss": r"[@$][\w-]+",
    "css": r"@[\w-]+",
    "markdown": r"^#{1,6} .*",
    "liquid": r"\{%[^%]*%\}",
}
ALIASES = {"jsonc": "json", "html": "xml", "console": "bash", "text": "bash"}

TOKEN = re.compile(
    r"(?P<comment>/\*[\s\S]*?\*/|//[^\n]*|(?<![\w:])#(?![0-9a-fA-F]{3,8}\b)[^\n]*)"
    r"|(?P<string>\"(?:[^\"\\\n]|\\.)*\"|'(?:[^'\\\n]|\\.)*'|`(?:[^`\\]|\\.)*`)"
    r"|(?P<color>#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b)"
    r"|(?P<number>\b\d+(?:\.\d+)?(?:px|rem|em|ch|%|s|ms)?\b)"
    r"|(?P<tag>&lt;/?[a-zA-Z][\w-]*)"
)


def highlight(code: str, lang: str) -> str:
    lang = ALIASES.get(lang, lang)
    esc = html.escape(code)

    if lang == "diff":
        out = []
        for line in esc.split("\n"):
            cls = ("add" if line.startswith("+") else
                   "del" if line.startswith("-") else
                   "hunk" if line.startswith("@@") else "")
            out.append(f'<span class="d-{cls}">{line}</span>' if cls else line)
        return "\n".join(out)

    if lang == "bash":
        out = []
        for line in esc.split("\n"):
            if line.startswith("$"):
                out.append(f'<span class="sh-prompt">$</span>'
                           f'<span class="sh-cmd">{line[1:]}</span>')
            elif line.lstrip().startswith(("✔", "✓", "PASS")):
                out.append(f'<span class="sh-ok">{line}</span>')
            else:
                out.append(f'<span class="sh-out">{line}</span>')
        return "\n".join(out)

    def repl(m: re.Match) -> str:
        kind = m.lastgroup
        text = m.group()
        if kind == "color":
            return (f'<span class="t-color">'
                    f'<i class="chip" style="background:{text}"></i>{text}</span>')
        return f'<span class="t-{kind}">{text}</span>'

    esc = TOKEN.sub(repl, esc)

    kw = KEYWORDS.get(lang)
    if kw:
        flags = re.M if lang in ("yaml", "markdown") else 0
        esc = re.sub(
            kw,
            lambda m: (m.group() if "<span" in m.group()
                       else f'<span class="t-kw">{m.group()}</span>'),
            esc, flags=flags,
        )

    if lang in ("css", "scss"):
        esc = re.sub(r"^(\s*)([-\w]+)(\s*:)", r'\1<span class="t-prop">\2</span>\3',
                     esc, flags=re.M)
    return esc


# ------------------------------------------------------------------- markdown

INLINE_CODE = re.compile(r"`([^`]+)`")
IMG = re.compile(r"!\[([^\]]*)\]\(([^)\s]+)\)")
LINK = re.compile(r"\[([^\]]+)\]\(([^)\s]+)\)")
BOLD = re.compile(r"\*\*([^*]+)\*\*")
ITALIC = re.compile(r"(?<![\w*])\*([^*\n]+)\*(?![\w*])")


def inline(text: str) -> str:
    holes: list[str] = []

    def stash(hpayload: str) -> str:
        holes.append(hpayload)
        return f"\x00{len(holes) - 1}\x00"

    text = INLINE_CODE.sub(
        lambda m: stash(f"<code>{html.escape(m.group(1))}</code>"), text)
    text = IMG.sub(
        lambda m: stash(f'<img src="{m.group(2)}" alt="{html.escape(m.group(1))}" />'),
        text)
    text = LINK.sub(
        lambda m: stash(f'<a href="{m.group(2)}" target="_blank" rel="noopener">'
                        f"{html.escape(m.group(1))}</a>"), text)
    text = html.escape(text)
    text = BOLD.sub(r"<strong>\1</strong>", text)
    text = ITALIC.sub(r"<em>\1</em>", text)
    for i, payload in enumerate(holes):
        text = text.replace(f"\x00{i}\x00", payload)
    return text


def liquid(md: str) -> str:
    md = re.sub(r"\{%\s*(?:details|spoiler|collapsible)\s+(.*?)%\}",
                lambda m: f"<details class='liquid' open><summary>"
                          f"{inline(m.group(1).strip())}</summary>", md)
    md = re.sub(r"\{%\s*end(?:details|spoiler|collapsible)\s*%\}", "</details>", md)
    md = re.sub(r"\{%\s*cta\s+(\S+)\s*%\}([\s\S]*?)\{%\s*endcta\s*%\}",
                lambda m: f"<a class='cta' href='{m.group(1)}' target='_blank' "
                          f"rel='noopener'>{html.escape(m.group(2).strip())}</a>", md)
    md = re.sub(r"\{%\s*katex(?:\s+inline)?\s*%\}([\s\S]*?)\{%\s*endkatex\s*%\}",
                lambda m: f"<div class='katex'>{html.escape(m.group(1).strip())}</div>",
                md)
    md = re.sub(r"\{%\s*embed\s+(\S+)\s*%\}",
                lambda m: f"<p class='embed'>▶ embed: {m.group(1)}</p>", md)
    return md


def front_matter(text: str) -> tuple[dict, str]:
    m = re.match(r"^---\n([\s\S]*?)\n---\n?", text)
    if not m:
        return {}, text
    data = {}
    for line in m.group(1).split("\n"):
        if ":" in line:
            k, v = line.split(":", 1)
            data[k.strip()] = v.strip().strip('"')
    return data, text[m.end():]


def render(md: str) -> str:
    md = re.sub(r"<!--[\s\S]*?-->", "", md)

    # 1. pull fenced code out of harm's way
    blocks: list[str] = []

    def keep(m: re.Match) -> str:
        lang = (m.group(1) or "text").strip()
        body = m.group(2)
        blocks.append(
            f'<pre class="code lang-{lang}" data-lang="{lang}">'
            f"<code>{highlight(body, lang)}</code></pre>"
        )
        return f"\n\x01{len(blocks) - 1}\x01\n"

    md = re.sub(r"```(\w*)\n([\s\S]*?)```", keep, md)
    md = liquid(md)

    out: list[str] = []
    lines = md.split("\n")
    i = 0
    while i < len(lines):
        line = lines[i]
        stripped = line.strip()

        if not stripped:
            i += 1
            continue

        if re.fullmatch(r"\x01\d+\x01", stripped):
            out.append(blocks[int(stripped.strip("\x01"))])
            i += 1
            continue

        if stripped.startswith("<"):                       # raw html passthrough
            out.append(stripped)
            i += 1
            continue

        if re.match(r"^#{1,6} ", stripped):                 # heading
            level = len(stripped) - len(stripped.lstrip("#"))
            out.append(f"<h{level}>{inline(stripped[level:].strip())}</h{level}>")
            i += 1
            continue

        if re.fullmatch(r"(-{3,}|\*{3,})", stripped):       # hr
            out.append("<hr />")
            i += 1
            continue

        if stripped.startswith("|") and i + 1 < len(lines) and \
                re.match(r"^\|[\s:\-|]+\|$", lines[i + 1].strip()):
            head = [c.strip() for c in stripped.strip("|").split("|")]
            i += 2
            body = []
            while i < len(lines) and lines[i].strip().startswith("|"):
                body.append([c.strip() for c in lines[i].strip().strip("|").split("|")])
                i += 1
            thead = "".join(f"<th>{inline(c)}</th>" for c in head)
            rows = "".join(
                "<tr>" + "".join(f"<td>{inline(c)}</td>" for c in r) + "</tr>"
                for r in body)
            out.append(f"<div class='tw'><table><thead><tr>{thead}</tr></thead>"
                       f"<tbody>{rows}</tbody></table></div>")
            continue

        if stripped.startswith(">"):                        # blockquote
            buf = []
            while i < len(lines) and lines[i].strip().startswith(">"):
                buf.append(lines[i].strip().lstrip(">").strip())
                i += 1
            out.append(f"<blockquote><p>{inline(' '.join(buf))}</p></blockquote>")
            continue

        if re.match(r"^[-*] ", stripped) or re.match(r"^\d+\. ", stripped):
            ordered = bool(re.match(r"^\d+\. ", stripped))
            items = []
            while i < len(lines):
                s = lines[i].strip()
                if re.match(r"^[-*] ", s):
                    items.append(re.sub(r"^[-*] ", "", s))
                elif re.match(r"^\d+\. ", s):
                    items.append(re.sub(r"^\d+\. ", "", s))
                elif s and items and not s.startswith(("|", "#", ">", "\x01", "<")):
                    items[-1] += " " + s
                else:
                    break
                i += 1
            tag = "ol" if ordered else "ul"
            out.append(f"<{tag}>" + "".join(f"<li>{inline(x)}</li>" for x in items)
                       + f"</{tag}>")
            continue

        buf = []                                            # paragraph
        while i < len(lines) and lines[i].strip() and \
                not re.match(r"^(#{1,6} |[-*] |\d+\. |\||>|<|\x01)", lines[i].strip()) and \
                not re.fullmatch(r"(-{3,}|\*{3,})", lines[i].strip()):
            buf.append(lines[i].strip())
            i += 1
        if buf:
            joined = " ".join(buf)
            cls = " class='band'" if joined.count("<img") == 0 and \
                joined.count("![") >= 2 else ""
            out.append(f"<p{cls}>{inline(joined)}</p>")
        else:
            i += 1
    return "\n".join(out)


# ----------------------------------------------------------------------- page

CSS = """
:root{--bg:#0B0F1A;--panel:#111827;--panel2:#0F172A;--line:#1F2A44;--ink:#E7E9EE;
--muted:#94A3B8;--primary:#00F5D4;--accent:#F15BB5;--support:#9B5DE5;--radius:14px}
html[data-theme=light]{--bg:#F5F7F9;--panel:#fff;--panel2:#EFF2F6;--line:#DDE3EC;
--ink:#1B2333;--muted:#5B6478;--primary:#0E7C86;--accent:#B4276F;--support:#6D3FBF}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);
font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
a{color:var(--primary)}
header.top{position:sticky;top:0;z-index:20;display:flex;gap:14px;align-items:center;padding:12px 18px;
background:var(--bg);border-bottom:1px solid var(--line)}
.brand{font-weight:800}.brand span{color:var(--primary)}
.rule{display:flex;height:6px;width:150px;border-radius:99px;overflow:hidden}.rule i{flex:1}
.spacer{flex:1}
.btn{font:inherit;cursor:pointer;border:1px solid var(--line);background:var(--panel);color:var(--ink);
border-radius:99px;padding:6px 14px;text-decoration:none}
.btn:hover{border-color:var(--primary)}
.layout{display:grid;grid-template-columns:290px minmax(0,1fr);gap:26px;padding:22px;align-items:start}
@media(max-width:900px){.layout{grid-template-columns:1fr}}
nav.files{position:sticky;top:70px;background:var(--panel);border:1px solid var(--line);
border-radius:var(--radius);padding:12px;display:flex;flex-direction:column;gap:6px}
nav.files h2{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);margin:6px 8px 8px}
nav.files a{display:block;padding:9px 11px;border-radius:10px;text-decoration:none;color:var(--ink);
font-size:14px;border:1px solid transparent}
nav.files a:hover{background:var(--panel2)}
nav.files a.active{background:var(--panel2);border-color:var(--primary);color:var(--primary)}
nav.files small{display:block;color:var(--muted);font-size:11.5px}
article{background:var(--panel);border:1px solid var(--line);border-radius:var(--radius);
padding-bottom:48px;max-width:840px;width:100%;overflow:hidden}
.cover{width:100%;display:block;border-bottom:1px solid var(--line)}
.meta{padding:22px clamp(18px,4vw,44px) 0}
.meta h1{font-size:clamp(26px,4vw,40px);line-height:1.18;margin:.2em 0 .3em}
.tags{display:flex;flex-wrap:wrap;gap:8px}
.tag{font-size:12.5px;color:var(--muted);border:1px solid var(--line);border-radius:6px;padding:2px 8px}
.desc{color:var(--muted);font-size:15px}
.fm{font-size:12px;color:var(--muted);border-top:1px dashed var(--line);
border-bottom:1px dashed var(--line);padding:8px 0;margin-bottom:6px}
.post{padding:0 clamp(18px,4vw,44px)}
.post img{max-width:100%;vertical-align:top}
.post p.band{line-height:0;margin:1.2em 0}
.post h2{margin-top:1.9em;font-size:26px;border-bottom:1px solid var(--line);padding-bottom:.25em}
.post h3{margin-top:1.5em;font-size:20px}
.post blockquote{margin:1.4em 0;padding:.9em 1.1em;background:var(--panel2);
border-left:4px solid var(--accent);border-radius:0 10px 10px 0}
.post blockquote p{margin:0}
.tw{overflow-x:auto;margin:1.3em 0}
.post table{border-collapse:collapse;width:100%;font-size:14.5px}
.post th,.post td{border:1px solid var(--line);padding:8px 11px;text-align:left;vertical-align:middle}
.post th{background:var(--panel2)}
.post td img{vertical-align:middle}
.post :not(pre)>code{background:var(--panel2);padding:.15em .4em;border-radius:6px;font-size:.9em;
font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
pre.code{background:#0d1117;border:1px solid var(--line);border-radius:12px;padding:18px;
overflow-x:auto;position:relative;margin:1.3em 0}
pre.code code{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13.5px;
line-height:1.62;color:#C9D1D9;white-space:pre}
pre.code::before{content:attr(data-lang);position:absolute;top:0;right:0;font-size:10.5px;
letter-spacing:.12em;text-transform:uppercase;color:#0B0F1A;background:var(--primary);
padding:2px 10px;border-radius:0 11px 0 10px;font-family:ui-monospace,monospace}
pre.lang-diff{border-color:#F15BB5}
pre.lang-console,pre.lang-bash{background:#05070d;border-color:#00F5D4}
.t-comment{color:#6E7A8A;font-style:italic}
.t-string{color:#A5D6FF}.t-number{color:#F0A868}.t-kw{color:#FF7B9C}
.t-prop{color:#9B5DE5;font-weight:600}.t-tag{color:#7EE787}
.t-color{color:#FFD479}
.chip{display:inline-block;width:.78em;height:.78em;margin-right:.35em;border-radius:3px;
border:1px solid rgba(255,255,255,.35);vertical-align:-1px}
.d-add{color:#7EE787;display:block;background:rgba(46,160,67,.15)}
.d-del{color:#FF9492;display:block;background:rgba(248,81,73,.14)}
.d-hunk{color:#79C0FF;display:block}
.sh-prompt{color:#F15BB5;font-weight:700}.sh-cmd{color:#E7E9EE}
.sh-ok{color:#7EE787;display:block}.sh-out{color:#9DF7E4;display:block}
details.liquid{background:var(--panel2);border:1px solid var(--line);border-radius:12px;
padding:10px 16px;margin:1.4em 0}
details.liquid summary{cursor:pointer;font-weight:650}
.cta{display:block;text-align:center;margin:1.8em 0;padding:14px 20px;border-radius:12px;
background:linear-gradient(90deg,var(--primary),var(--support));color:#08121A;font-weight:750;
text-decoration:none}
.katex{background:var(--panel2);border:1px dashed var(--line);border-radius:10px;padding:12px 16px;
font-family:ui-monospace,monospace;color:var(--primary);margin:1.2em 0}
.embed{color:var(--muted);font-family:ui-monospace,monospace;font-size:13px}
.note{max-width:840px;color:var(--muted);font-size:13px;margin-top:14px}
.post hr{border:0;border-top:1px solid var(--line);margin:2em 0}
"""

PAGE = """<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>{title} — DEV preview</title>
<style>{css}</style>
</head>
<body>
<header class="top">
  <div class="brand">Swatch<span>Deck</span> · DEV.to preview</div>
  <div class="rule"><i style="background:#0F172A"></i><i style="background:#00F5D4"></i>
    <i style="background:#F15BB5"></i><i style="background:#9B5DE5"></i></div>
  <div class="spacer"></div>
  <a class="btn" href="{raw}" target="_blank">View raw .md</a>
  <button class="btn" onclick="var d=document.documentElement;
    d.dataset.theme=d.dataset.theme==='dark'?'light':'dark'">🌗 Theme</button>
</header>
<div class="layout">
  <nav class="files"><h2>Articles</h2>{nav}</nav>
  <div>
    <article>
      {cover}
      <div class="meta">
        <div class="tags">{tags}</div>
        <h1>{title}</h1>
        <p class="desc">{description}</p>
        <div class="fm">published: <b>{published}</b> · tags: {ntags}/4 · cover_image: {hascover}</div>
      </div>
      <div class="post">{body}</div>
    </article>
    <p class="note">Static local preview — Liquid tags are simulated, DEV renders them natively.
    Colour swatches load from placehold.co, so they need internet access.</p>
  </div>
</div>
</body>
</html>
"""


def build() -> None:
    OUT.mkdir(exist_ok=True)
    nav_items = []
    for md_name, sub in ARTICLES:
        label = md_name.removeprefix(md_name[:3]).removesuffix(".md").replace("-", " ")
        nav_items.append((md_name.replace(".md", ".html"), label, sub))

    for md_name, _ in ARTICLES:
        raw = (ROOT / md_name).read_text()
        fm, body_md = front_matter(raw)
        nav = "".join(
            f'<a href="{href}" class="{"active" if href == md_name.replace(".md", ".html") else ""}">'
            f"{label}<small>{sub}</small></a>"
            for href, label, sub in nav_items
        )
        tags = "".join(
            f'<span class="tag">#{t.strip()}</span>'
            for t in fm.get("tags", "").split(",") if t.strip()
        )
        cover = (f'<img class="cover" src="{fm["cover_image"]}" alt="cover" />'
                 if fm.get("cover_image") else "")
        page = PAGE.format(
            css=CSS, nav=nav, tags=tags, cover=cover,
            title=html.escape(fm.get("title", md_name)),
            description=html.escape(fm.get("description", "")),
            published=fm.get("published", "false"),
            ntags=len([t for t in fm.get("tags", "").split(",") if t.strip()]),
            hascover="set" if fm.get("cover_image") else "none",
            raw=f"../{md_name}",
            body=render(body_md),
        )
        (OUT / md_name.replace(".md", ".html")).write_text(page)
        print(f"✔ preview/{md_name.replace('.md', '.html')}")

    index = OUT / "index.html"
    index.write_text(
        (OUT / ARTICLES[1][0].replace(".md", ".html")).read_text()
        .replace('href="../', 'href="../')
    )
    print("✔ preview/index.html (→ article 01)")


if __name__ == "__main__":
    build()
