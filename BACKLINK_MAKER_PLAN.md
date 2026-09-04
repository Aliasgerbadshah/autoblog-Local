# 🎯 Backlink Maker — Standalone Software (Full Plan)
**Separate software in `backlink-maker/` — NOT merged with AutoBlog. Own DB, own login, own dashboard.**

Goal: every day, this software creates **short backlink content + 1 image per post**, then:
- **Lane A (API sites):** auto-posts it by itself (Blogger / WordPress / Ghost / Webhook).
- **Lane B (Manual sites):** prepares a ready-to-paste **content package** (title + body + image file + step-by-step instructions) so you only have to copy, paste, upload image, and hit submit.

**Your decisions (locked):**
- ✅ Standalone software, new folder `backlink-maker/`, zero merge with autoblog
- ✅ Mixed target list (you will send the site list)
- ✅ 4–10 backlinks per day
- ✅ Build begins once you send the target list

---

## 1. Project structure (all new, self-contained)

```
backlink-maker/                     ← upload this whole folder (subfolder or subdomain)
├── .htaccess                       # routing + security (blocks includes/, cron/, *.db)
├── index.php                       # Router + API endpoints + dashboard shell
├── login.php / logout.php          # Single-admin login (password_hash, session)
├── includes/
│   ├── config.php                  # APP URL, DB path, cron time, safety limits
│   ├── database.php                # Own SQLite DB: backlink_maker.db (auto-created)
│   ├── auth.php                    # Single admin (or multi-user later)
│   ├── ai_client.php               # Chat (Gemini/OpenAI/Anthropic) + Image (HF/OpenAI/Gemini)
│   ├── publishers.php              # Blogger API, WordPress REST, Ghost Admin API, Webhook
│   └── maker.php                   # Core engine: rotation, content, images, packages
├── cron/
│   └── daily.php                   # Hostinger cron: 0 6 * * * php /home/USER/public_html/backlink-maker/cron/daily.php
├── templates/
│   └── dashboard.html              # The Backlink Maker dashboard (dark UI)
├── packages/                       # Generated images + HTML files (chmod 777)
│   └── 2026-09-04/{slug}/image.jpg + post.html + copy.txt
├── targets_template.csv            # Template to fill your target list
└── README.txt                      # How to fill the CSV + deployment steps
```

**Stack:** plain PHP 8 + SQLite + cURL + DOMDocument. 100% Hostinger shared-hosting compatible.
Same proven patterns as your autoblog app (which you already run on Hostinger) — but **no shared files, no shared DB**.

---

## 2. Target platforms — which ones have APIs? (honest table)

| Target type | Public posting API? | Lane | Notes |
|---|---|---|---|
| **Blogger** (your own, 1 or many) | ✅ Blogger API v3 | **API** | Each blog = one credential set in this software's vault |
| **WordPress** (yours, or guest blog with app-password access) | ✅ REST API + Application Password | **API** | |
| **Ghost blog** | ✅ Admin API (simple key) | **API** | |
| **Anything with a webhook** (Make/Zapier → CMS, Sheets…) | ✅ | **API** | |
| **Discourse forum** | ✅ Posts API with key | **API** | Only if your targets are Discourse |
| **Medium / Substack** | ❌ No public API (ToS + account risk) | **Manual** | Copy-paste package |
| **Quora / Reddit** | ⚠️ Auto-posting = ban risk | **Manual** | Human-paced, 1–2/week max |
| **Business directories** (Google Business, Bing Places, Yelp, BBB, local/niche) | ❌ | **Manual** | Profile + website link (NAP/citations — valuable even if nofollow) |
| **Forums / communities** | ❌ Mostly | **Manual** | Profile link or real contribution post |
| **Review sites / niche portals** | ❌ Rarely | **Manual** | Where the site gives you an account |
| **Blog comments** | ❌ | ❌ Skip | Google discounts comment links; spam flag risk |

**Bottom line:** API lane = your Blogger network + WordPress + (optional) Ghost/webhooks —
full control, dofollow, fully automatic. Everything else = manual lane (your daily copy-paste).

---

## 3. How a day works

```
Hostinger cron — daily at 06:00 (configurable in config.php)
        │
        ▼
cron/daily.php
        │
        ├─ 1. Pick DUE targets — round-robin, each target has min_interval_days
        │      (default 7: never post to the same site twice within a week)
        │      Target order: most-oldest-last-posted first
        │
        ├─ 2. For each target (default 5/day, max 10 to respect Hostinger cron limits):
        │      a) Pick a FRESH unique topic (built-in dedup, never repeats)
        │      b) Pick a CONTENT ANGLE (rotates — §4)
        │      c) Chat API writes 300–500 word post with ONE contextual link
        │         to your site + rotating anchor text
        │      d) Image API generates 1 image (1200×675, no text/logos)
        │      e) Image downloaded → packages/{date}/{slug}/image.jpg
        │         (real file, so manual sites get a downloadable image)
        │         + post.html + copy.txt (title+body+anchor for one-click copy)
        │
        ├─ 3. Lane A (api targets): auto-publish via Blogger/WP/Ghost/Webhook
        │      → live URL saved → status "Published"
        ├─ 4. Lane B (manual targets): status "Manual Ready" + per-site instructions
        │
        └─ 5. Email digest (optional, Brevo/SMTP): "Today: 3 auto-posted ✅, 2 waiting 📋"
```

Your daily routine: open dashboard → **Manual Queue** → for each item:
Copy Title → Copy Body → Download Image → paste into the site per the printed
instructions → click **Mark as posted** + paste the live URL → done.
Every posted URL (auto or manual) is tracked in the **Link Health** section:
re-checked weekly, dofollow/nofollow detected, dead links flagged.

---

## 4. Content rules (what the generated backlink post looks like)

- **Length:** 300–500 words, 1–2 H2s, 1 image. Short = fits forum/directory/blog posts.
- **Exactly 1 link** to your main site, placed in the first or second third,
  justified by context (the post is genuinely about a topic your site covers).
- **Anchor rotation pool** (configurable in dashboard; weighted random; never
  repeats the last anchor used on the same target):
  brand name · primary keyword · partial match · "this guide" · naked URL (rare)
- **Angle rotation** (one per day, so no two posts look identical):
  1. "Best of" listicle (5 items, your site featured with context)
  2. Honest mini-review
  3. 3-step how-to snippet
  4. Resource roundup
  5. Trend/observation note
  6. Use-case / "how I use X"
  7. Single-question FAQ answer
- **Image:** 1 per post, editorial style, descriptive alt text. Fallback:
  gradient placeholder if image API fails (job still proceeds).
- **Dedup:** every title/keyword is recorded; the maker never repeats one.

---

## 5. Database (own file: `backlink_maker.db`, auto-created on first login)

```sql
-- settings (single row per site)
CREATE TABLE IF NOT EXISTS settings (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  main_site_url TEXT,            -- the site you want backlinks TO
  anchor_pool TEXT,              -- JSON array of rotating anchors
  daily_count INTEGER DEFAULT 5, -- targets per day
  daily_time TEXT DEFAULT '06:00',
  email_digest INTEGER DEFAULT 0,
  smtp_json TEXT,                -- optional email (Brevo key / SMTP)
  updated_at TEXT
);

-- your target sites
CREATE TABLE IF NOT EXISTS targets (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  site_url TEXT NOT NULL,
  target_type TEXT DEFAULT 'blog',      -- blog|directory|forum|qa|social|review|other
  publish_mode TEXT DEFAULT 'manual',   -- api|manual
  platform TEXT,                        -- api only: blogger|wordpress|ghost|webhook
  credential_json TEXT,                 -- api only: per-site credentials (base64)
  niche TEXT,
  min_interval_days INTEGER DEFAULT 7,
  is_active INTEGER DEFAULT 1,
  account_notes TEXT,                   -- your saved handle/steps for manual sites
  last_posted_at TEXT,
  post_count INTEGER DEFAULT 0,
  created_at TEXT
);

-- one row per generated/posted backlink
CREATE TABLE IF NOT EXISTS jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  target_id INTEGER NOT NULL,
  run_date TEXT NOT NULL,
  angle TEXT,
  topic TEXT, title TEXT, anchor_text TEXT,
  content_html TEXT, content_text TEXT,
  image_url TEXT, image_file TEXT,
  instructions TEXT,                    -- per-site step list (manual lane)
  status TEXT DEFAULT 'Queued',         -- Queued|Content Ready|Auto Posting|Published
                                        -- |Failed|Manual Ready|Manual Posted|Dead
  published_url TEXT,
  is_dofollow INTEGER,                  -- filled by weekly link re-check
  last_verified_at TEXT,
  error_message TEXT,
  created_at TEXT, posted_at TEXT
);

-- dedup memory
CREATE TABLE IF NOT EXISTS used_topics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT, keyword TEXT, used_at TEXT
);
```

---

## 6. Dashboard (single page, tabs)

| Tab | What you see |
|---|---|
| **Overview** | Cards: targets active · posted today · auto ✅ · waiting manual 📋 · dofollow live links · failed |
| **Targets** | Table of all sites: name, URL, type, mode, niche, last posted, posts count, enable toggle. **Add Target** form + **CSV Import/Export** |
| **Run Now** | [▶ Run today's batch] button + live log (same "run cron now" pattern you already have) |
| **Manual Queue** ⭐ | Per item: site + topic + [Copy Title] [Copy Body HTML] [Copy Body Text] [Copy Anchor] [⬇ Image] [Open Package] + printed instructions + **[Mark as posted] (paste URL)** |
| **Link Health** | Every posted URL, status, dofollow badge, last check; [Re-check all] |
| **Settings** | Main site URL, anchor pool editor, daily count/time, Chat API + Image API credentials, optional email digest |

**Manual-package instructions are auto-generated per site type**, e.g. for a directory:
1. Open {site_url} → 2. Log in (your saved note: {account_notes}) → 3. Create business profile
→ 4. Paste title & body → 5. Upload image.jpg → 6. Put {main_site_url} in the website field
→ 7. Submit → 8. Copy your profile URL → 9. Paste it in "Mark as posted".

---

## 7. API feasibility = your target list decides the build

Once you send the list, each site gets classified automatically:
- It's a Blogger/WordPress/Ghost site you control → **API lane** (you provide credentials per site)
- Everything else → **Manual lane** (instructions tailored to its type)

If your list contains a platform type I haven't listed above, I'll either add a
small connector (if it has a real API) or route it to the manual lane.

---

## 8. Deployment on your Hostinger (subfolder — simplest)

1. Upload the `backlink-maker/` folder to `public_html/backlink-maker/`
   (or a subdomain `backlinks.yourdomain.com` pointing to that folder — same thing)
2. Permissions:
   ```
   chmod 755 backlink-maker/
   chmod 644 backlink-maker/*.php  backlink-maker/includes/*.php  backlink-maker/cron/*.php
   chmod 777 backlink-maker/packages/
   ```
3. One cron job (hPanel → Advanced → Cron Jobs):
   ```
   0 6 * * * php /home/YOURUSERNAME/public_html/backlink-maker/cron/daily.php
   ```
4. Open `https://yourdomain.com/backlink-maker/` → create admin account →
   paste your Chat + Image API keys in Settings → import `targets.csv` → Run Now.

No Python, no new services. Same requirements as your current autoblog (PHP 8.0+, PDO SQLite, cURL).

---

## 9. Build phases

| Phase | What |
|---|---|
| **1 — Core** | Folder scaffold, DB, login, Settings tab, Targets tab + CSV import, `maker.php` engine (rotation + dedup + content + image download), `cron/daily.php`, Run Now |
| **2 — Manual Queue + Link Health** | The copy-paste UI, image download, per-site instructions, Mark-as-posted, weekly re-check, dofollow detection |
| **3 — Lane A publishers** | Blogger / WordPress / Ghost / Webhook connectors + per-site credential forms (built around YOUR actual list) |
| **4 — Polish** | Email digest, export report (CSV of all backlinks), per-target schedule (Mon/Thu), bulk mark-posted |

---

## 10. SEO safety rules (built into the engine)

- 4–10 total per day, but **≥7 days between two posts on the same site** (per-target interval)
- Different angle + different topic + rotating anchor on every post (no duplicate fingerprints)
- Skip comment-only links entirely; directories get profile-style presence
- Reddit/Quora flagged as "manual, low frequency" targets — the dashboard even
  warns if you mark them posted too often
- Weekly Link Health re-check so you see exactly what's live and dofollow

---

## 11. Next step

**Send me your target list** (any format — paste it in chat, a CSV, or a screenshot).
For each site it helps to know:
1. Name + URL
2. Do you already have an account there? (username/handle if yes)
3. Can you post full posts there, or only profile/short fields?
4. Is it a site YOU control (Blogger/WP)? → then we set up its API instead of manual

I'll then set every site into the right lane and build Phase 1+2 tailored to your list.
