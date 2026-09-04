# 🎯 AutoBacklink — Standalone Backlink Software

**Completely separate from AutoBlog** — own login, own database (`backlink_maker.db`),
own dashboard. Uses the same AI approach (Chat API writes content, Image API creates
the image) but is built for one job only: **automatic backlinks**.

## What it does every day (automatically)

1. Picks which of your backlink websites are due (each site has its own interval, e.g. "min 7 days between posts")
2. Picks a fresh unique topic (custom topics you add first, then AI-generated, never repeated)
3. Writes a 300–500 word guest-style post with **exactly one natural link** to your main site (rotating anchor text — never repeats on the same site)
4. Generates **1 image** per post (AI if Image API configured, fallback gradient otherwise) and saves it as a real file
5. **API sites** (your Blogger/WordPress/Ghost/Webhook) → posted by itself, URL recorded
6. **Manual sites** (directories, forums, Q&A…) → a ready package in the **Paste Queue**: Copy Title / Copy Body / Copy Anchor / Download Image / per-site instructions → you paste, upload, submit → click "Mark as posted"
7. Every posted link is tracked in **Link Health** (dofollow/nofollow, dead links) — re-checkable anytime

## Dashboard panels

| Panel | Purpose |
|---|---|
| Overview | Stats, today's run, system status, run log |
| Backlink Websites | Add/edit every target site: name, URL, type, mode (Auto-API / Manual), credentials, niche, frequency, account notes |
| Auto Runner | Your main site URL, anchor text pool, your own topics, posts/day, run time, Run Now |
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
3. Create the cron job (hPanel → Advanced → Cron Jobs) — match the hour to your run time:
   ```
   0 6 * * * php /home/YOURUSERNAME/public_html/backlink-maker/cron/daily.php
   ```
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
includes/content_engine.php  topics, angles, anchors, post generation, dedup
includes/publishers.php Blogger / WordPress / Ghost / Webhook
includes/maker.php      daily run orchestration + link verifier
cron/daily.php          the daily cron
templates/dashboard.html  the whole UI
packages/               generated images + package files (writable)
```
