#!/usr/bin/env python3
"""
serve.py — preview server with proper download support.

    python3 tools/serve.py            # http://localhost:8080
    python3 tools/serve.py 9000       # custom port

Adds two things on top of `python -m http.server`:

  /download/<file>   sends the file with Content-Disposition: attachment,
                     so the browser saves it instead of displaying it
  /downloads         a plain page listing every .md and the .zip bundle
"""

from __future__ import annotations

import functools
import html
import io
import sys
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent          # content/devto

TITLES = {
    "00-swatch-deck-theme-kit.md": "Theme kit (reference sheet)",
    "01-choose-a-color-palette.md": "How to Pick a Color Palette That Survives Production",
    "02-hex-to-design-tokens.md": "From HEX to Design Tokens",
    "03-seasonal-palettes-ui-themes.md": "Seasonal Color Analysis for Developers",
    "04-neon-dark-mode-glow-ui.md": "Neon Dark Mode That Doesn't Hurt",
    "05-automate-palette-workflow.md": "Automate Your Color Workflow",
    "06-css-color-mix-dynamic-variations.md": "CSS color-mix(): Every Variation From One Token",
    "07-css-relative-color-syntax.md": "CSS Relative Color Syntax: A Scale From One Base",
    "08-light-dark-css-theme-aware.md": "light-dark() in CSS",
    "09-hwb-color-explained.md": "HWB Color Explained",
    "10-display-p3-vs-srgb-wide-gamut.md": "Display-P3 vs sRGB",
    "11-wide-gamut-without-breaking-displays.md": "Wide-Gamut Without Breaking Older Displays",
    "12-color-gamut-media-queries.md": "Color-Gamut Media Queries",
    "13-same-hex-different-monitors.md": "Why the Same HEX Looks Different",
    "14-browser-color-management.md": "How Browser Colour Management Works",
    "15-icc-color-profiles-guide.md": "A Designer's Guide to ICC Profiles",
    "16-srgb-vs-p3-vs-adobe-rgb.md": "sRGB vs Display P3 vs Adobe RGB",
    "17-retina-high-gamut-assets.md": "Colour-Critical Assets for Retina",
    "18-social-media-color-management.md": "Colour Management for Social Media",
    "19-png-vs-jpeg-color-differences.md": "Why PNG and JPEG Exports Differ",
    "20-compression-color-accessibility.md": "Compression and Colour Accessibility",
    "COVER-PROMPTS.md": "Cover image prompts (one per post)",
    "HOW-TO-USE.md": "How to use this pack",
    "README.md": "Pack readme",
}

PAGE = """<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Downloads — DEV.to posts</title><style>
body{{margin:0;background:#0B0F1A;color:#E7E9EE;
font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}}
.wrap{{max-width:820px;margin:0 auto;padding:34px 20px 60px}}
h1{{font-size:30px;margin:0 0 6px}}
p.sub{{color:#94A3B8;margin:0 0 26px}}
.rule{{display:flex;height:8px;border-radius:99px;overflow:hidden;margin-bottom:26px}}
.rule i{{flex:1}}
a.item{{display:flex;gap:14px;align-items:center;text-decoration:none;color:#E7E9EE;
background:#111827;border:1px solid #1F2A44;border-radius:12px;padding:14px 16px;margin-bottom:10px}}
a.item:hover{{border-color:#00F5D4}}
.name{{font-weight:650}} .file{{color:#94A3B8;font-size:13px;font-family:ui-monospace,monospace}}
.grow{{flex:1}}
.dl{{background:#00F5D4;color:#08121A;font-weight:750;border-radius:99px;padding:6px 14px;font-size:14px}}
.zip{{border-color:#F15BB5}}
.back{{display:inline-block;margin-top:22px;color:#00F5D4}}
</style></head><body><div class="wrap">
<h1>⬇ Download the posts</h1>
<p class="sub">Click any row to save the file. Markdown files are ready to paste straight into the DEV.to editor.</p>
<div class="rule"><i style="background:#0F172A"></i><i style="background:#00F5D4"></i>
<i style="background:#F15BB5"></i><i style="background:#9B5DE5"></i></div>
{rows}
<a class="back" href="/preview/index.html">← back to the preview</a>
</div></body></html>"""


class Handler(SimpleHTTPRequestHandler):
    def do_GET(self):                                   # noqa: N802
        if self.path.rstrip("/") in ("/downloads", "/downloads.html"):
            return self._downloads_page()
        if self.path.startswith("/download/"):
            return self._download(self.path[len("/download/"):])
        return super().do_GET()

    def do_HEAD(self):                                  # noqa: N802
        if self.path.rstrip("/") in ("/downloads", "/downloads.html"):
            return self._downloads_page(head_only=True)
        if self.path.startswith("/download/"):
            return self._download(self.path[len("/download/"):], head_only=True)
        return super().do_HEAD()

    # ---------------------------------------------------------------- helpers
    def _downloads_page(self, head_only: bool = False):
        rows = []
        for f in sorted(ROOT.glob("*.md")):
            title = TITLES.get(f.name, f.stem)
            kb = f.stat().st_size // 1024
            rows.append(
                f'<a class="item" href="/download/{f.name}">'
                f'<span><span class="name">{html.escape(title)}</span><br>'
                f'<span class="file">{f.name} · {kb} KB</span></span>'
                f'<span class="grow"></span><span class="dl">Download</span></a>'
            )
        zip_path = ROOT / "devto-posts.zip"
        if zip_path.exists():
            kb = zip_path.stat().st_size // 1024
            rows.insert(0,
                        f'<a class="item zip" href="/download/devto-posts.zip">'
                        f'<span><span class="name">Everything as one ZIP</span><br>'
                        f'<span class="file">devto-posts.zip · {kb} KB</span></span>'
                        f'<span class="grow"></span><span class="dl">Download</span></a>')
        body = PAGE.format(rows="\n".join(rows)).encode()
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        if not head_only:
            self.wfile.write(body)

    def _download(self, name: str, head_only: bool = False):
        safe = Path(name).name
        target = ROOT / safe
        if not target.is_file():
            self.send_error(404, "no such file")
            return
        data = target.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", "application/octet-stream")
        self.send_header("Content-Disposition", f'attachment; filename="{safe}"')
        self.send_header("Content-Length", str(len(data)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        if not head_only:
            self.wfile.write(data)

    def log_message(self, fmt, *args):                  # quieter logs
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))


def main() -> None:
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
    handler = functools.partial(Handler, directory=str(ROOT))
    with ThreadingHTTPServer(("0.0.0.0", port), handler) as httpd:
        print(f"preview   http://0.0.0.0:{port}/")
        print(f"downloads http://0.0.0.0:{port}/downloads")
        httpd.serve_forever()


if __name__ == "__main__":
    main()
