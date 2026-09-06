# 🎯 AutoBacklink — Standalone Backlink Software

**Completely separate from AutoBlog** — own login, own database (`backlink_maker.db`),
own dashboard. Uses the same AI approach (Chat API writes content, Image API creates
the image) but is built for one job only: **automatic backlinks**.

## What it does (automatically)

1. Each backlink website has its **own pace + timer** (Site Panels): posts/day (1–5), minimum gap, post time, mode (full auto / scheduled / manual-copy)
2. Picks a fresh unique topic (custom topics you add first, then AI-generated, never repeated)
3. Writes a 300–500 word guest-style post with **exactly one natural link** to your main site (rotating anchor text — never repeats on the same site)
4. Generates **1 image** per post (AI if Image API configured, fallback otherwise) and saves it as a real file
5. **API sites** (your Wix / Blogger / WordPress / Ghost / Webhook) → posted by itself, URL recorded (Wix: image import + rich format + draft-test)
6. **Wix Community (Groups)** → finds niche-related topics, writes a useful 1–3 sentence comment with your link, posts it **1–3×/day** (bot-safe), never the same topic twice
7. **Manual / no-API sites** (Hashnode is paid-API-only since May 2026 → use this) → **Copy & Paste Studio** generates the complete article (title + image + body with link) with copy buttons — you paste, then mark posted with the live URL
8. Every posted link is tracked in **Link Health** (dofollow/nofollow, dead links) — re-checkable anytime

## Dashboard panels

| Panel | Purpose |
|---|---|
| Overview | Stats, today's run, system status, run log |
| Backlink Websites | Add/edit every target site: name, URL, type, mode (Auto-API / Manual), credentials (incl. Wix OAuth), niche, frequency, account notes, 🔌 connection test |
| 🎛 Site Panels | **One card per website**: blog mode / posts-per-day / minimum gap / post-time timer, draft test (Wix), 🔌 test — plus Wix **Community** box (enable, 1–3 comments/day, time, group picker, topic explorer with editable answers) — plus the **Copy & Paste Studio** |
| Auto Runner | Your main site URL, anchor text pool, your own topics, cron line, Run Now |
| Paste Queue | Manual-lane packages with one-click copy buttons + image download + instructions |
| Link Health | All posted links, dofollow status, re-check |
| API Keys | Chat API + Image API (Gemini, OpenAI, HuggingFace, OpenRouter, Anthropic, Pollinations, custom) |

## Deploy on Hostinger (subdomain)

1. Point your subdomain (e.g. `backlinks.yourdomain.com`) to this folder
   (hPanel → Websites → your subdomain → document root → this folder,
   OR upload this whole folder to `public_html/backlink-maker/`)
2. Permissions:
   ```
   chmod 755 . 
   chmod 644 *.php .htaccess includes/*.php cron/*.php
   chmod 777 packages/
   ```
3. Create the cron job (hPanel → Advanced → Cron Jobs) — **recommended: every 30 min** so each site's own timer fires exactly:
   ```
   */30 * * * * php /home/YOURUSERNAME/public_html/backlink-maker/cron/backlink-30min.php
   ```
   (the old daily `cron/daily.php` still works; timers are then approximate)
4. Open the subdomain → create your admin account → API Keys panel (paste your Chat + Image keys — same keys AutoBlog uses) → Backlink Websites panel (add your sites) → Auto Runner (set your main site URL + anchors) → done. It runs every day by itself.

## Files

```
index.php               router + API
login.php / setup.php   auth pages (setup = first run)
logout via /logout
includes/config.php     base URL auto-detect, limits
includes/database.php   own SQLite schema
includes/auth.php       single-admin auth
includes/helpers.php    HTTP/files/text helpers (sandbox-aware)
includes/ai_client.php  Chat + Image client (same providers as AutoBlog) + anti-AI sanitizer
includes/content_engine.php  topics, angles, anchors, post + community-comment generation, dedup, relevance
includes/publishers.php     Wix (Blog v3 + Groups, OAuth, Ricos) / Blogger / WordPress / Ghost / Hashnode / Webhook + connection tests
includes/maker.php          per-site run orchestration (pace + timers + community lane) + Copy Studio + link verifier
cron/backlink-30min.php     recommended cron (per-site timers fire exactly)
cron/daily.php              legacy daily cron
templates/dashboard.html    the whole UI (incl. Site Panels + Copy & Paste Studio)
packages/                   generated images + package files (writable)
```
