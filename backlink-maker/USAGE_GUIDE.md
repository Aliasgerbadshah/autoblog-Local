# 📖 How to Use AutoBacklink — Step by Step

Set this up once. After that: auto sites post by themselves, and manual sites take ~1 minute each with the **Copy & Paste Studio**.

---

## STEP 1 — Install it on Hostinger

1. **hPanel → Websites → Manage** (your subdomain, e.g. `backlinks.yourdomain.com`)
2. **File Manager** (or FTP) → open the subdomain's document root (usually `public_html/`)
3. **Upload the `backlink-maker` folder** (unzipped), replacing the old one
4. Right-click the `packages` folder → **Permissions** → **777** (everything else unchanged)
5. **hPanel → Advanced → Cron Jobs** → add this ONE line (replace YOURUSERNAME):
   ```
   */30 * * * *
   php /home/YOURUSERNAME/public_html/backlink-maker/cron/backlink-30min.php
   ```
   This runs the software every 30 minutes, so each website's own **timer** (Site Panels) fires at the exact hour you choose.
   (You can keep the old daily cron instead — pacing still works, but timers are then approximate.)

## STEP 2 — First visit (one time only)

1. Open your subdomain in the browser
2. **Setup screen** → create username + password → done
3. Next visits: just the login page

## STEP 3 — API Keys (paste your AI keys)

Open the **API Keys** panel:

1. **Chat API** (writes the content): pick provider, paste key, model name (e.g. `gemini-2.5-flash-lite`) → **Save** → **Test** (should say ✅)
2. **Image API** (creates the image for each post): same idea
   (Same keys/providers AutoBlog uses — paste the same ones.)

> **No keys yet?** Everything still works in *template mode* (ready-made content + fallback image). Real AI content starts the moment you save a key.

## STEP 4 — Auto Runner (the brain, one time)

Open the **Auto Runner** panel:

1. **MAIN SITE URL** — the site that RECEIVES the backlinks (e.g. `https://yourwebsite.com`). Every post links to it.
2. **ANCHOR TEXT POOL** — 4–8 phrases, one per line (your brand name, your keyword, "their complete guide", "this resource"…). Rotated automatically, never repeated twice in a row on the same site.
3. **YOUR OWN TOPICS** (optional) — topics you specifically want, one per line. Used first; then fresh ones are invented (never repeated).
4. **Save** (the old global "posts per day / run time" settings are now per-site — see Site Panels)

## STEP 5 — Add your backlink websites

Open **Backlink Websites** → form on the right:

| Field | What to enter |
|---|---|
| Name | Anything you remember ("My Wix Blog", "DesignDirectory") |
| Site URL | `https://that-site.com` |
| Type | Blog / Directory / Forum / Q&A / Social / Review |
| Posting mode | **Manual** = you paste (use Copy Studio) · **Auto via API** = software posts itself (only sites you OWN) |
| Niche | What the site is about (helps content + topic matching) |
| Every N days | Minimum gap between posts (default 7) |
| Account notes | Your handle there, which section to post in |

**If Auto via API**, pick the platform:

- **Wix Blog (+Community)** → see the full Wix walkthrough in STEP 6 below
- **Blogger**: Blog ID (blogger.com/about) + Client ID + Client Secret + Refresh Token
- **WordPress**: site URL + username + Application Password (WP admin → Users → Profile → Application Passwords)
- **Ghost**: site URL + Admin API key
- **Hashnode**: Publication ID + Personal Access Token — ⚠️ **Hashnode made API posting paid (Pro) in May 2026**. If you don't have Pro: keep Hashnode in **Manual mode** and use the **Copy & Paste Studio** (STEP 7).
- **Webhook**: your Make/Zapier URL

## STEP 6 — Wix setup (the new one, ~10 minutes)

Wix lets us **auto-post blog drafts/posts AND auto-comment in your community (Wix Groups)**.

1. **Create a Wix app** (one time):
   - Go to **dev.wix.com** (Wix Developer) with your Wix account
   - **Create a new app** (any name, e.g. "my-auto")
   - Open the app → **OAuth** tab → copy the **App ID** (= Client ID) and the **App secret key** (= Client Secret)
2. **Find your Site ID**: open your Wix dashboard — the URL looks like
   `wix.com/dashboard/<THIS-PART-is-your-site-id>/...` — copy that part.
3. **In Backlink Websites** → Edit your Wix site → Platform: **Wix Blog (+Community)**:
   - **Site ID** ← the UUID from step 2
   - **OAuth Client ID / Client Secret** ← from step 1
   - (Instance ID: only if Wix asks for it — it's in Dev Center → your app → **Instances**)
   - **Save**
4. **Community (Groups)**: make sure your Wix site has the **Groups/Communities app** installed (site editor → Apps). Leave "Community / Group" on **auto** — the software picks the first group and remembers it — or click **↻ Load groups** to choose.
5. **Test it**:
   - **🔌 Test connection** → should say ✅ Blog API reachable + your groups listed
   - **🧪 Create a DRAFT post** → open Wix → **Blog → Posts → Drafts** — you should see "AutoBacklink draft test…". If you see it, auto-posting works.
   - **🔎 Find topics + prepare our comments** → each topic shows as a link + the question + *our* prepared answer (editable) + **📩 Post this comment** button. **Nothing posts until you click.**

The software handles all the Wix details automatically: tokens expire every 4 hours (auto-refreshed), images are imported into Wix Media first, and the post content uses Wix's own rich format (with a fallback).

## STEP 7 — Site Panels (one panel per website) ⭐ New tab

Open **🎛 Site Panels** — every website gets its own card:

**Blog box**
- **Mode**: ⚡ Full auto (posts as soon as it's due) · ⏰ Scheduled (posts at the timer time) · 📋 Manual (you paste from Copy Studio)
- **Posts per day** (1–5) + **Minimum gap in days** (the gap wins if it's bigger — safety)
- **Post time (HH:MM)** — with the 30-min cron, it posts at that exact hour
- **🧪 Draft Test** (Wix) + **🔌 Test connection**

**Community box (Wix sites)**
- **Enable** switch → auto commenting starts
- **Comments per day** (1–3, bot-safe) + **Comment time**
- **Group** picker (auto = first group)
- **🔎 Find topics + prepare our comments** — see the question, edit our answer, click Post
- **🚀 Post one comment now** — picks the best-matching topic, writes a useful answer with your link, posts it

**Copy & Paste Studio (top of the tab)** — for sites without a working API (Hashnode etc.):
1. Pick your site → **✨ Generate post now**
2. You get the **complete article**: title + image + body with your link inside
3. **Copy Title / Copy Body (rich) / Copy Body (plain) / Copy Markdown (best for Hashnode) / Download Image**
4. Paste into the site, publish, then **✅ I pasted it — I have the live URL** → paste the URL → the backlink is tracked in **Link Health** (dofollow check included)

## STEP 8 — Test it

1. **Auto Runner → ▶ Run now** (bypasses timers: does everything that's due right now)
2. Watch **Overview** (jobs + run log) and **Site Panels** (last posted / last comment)
3. Wix: open **Blog → Drafts/Posts** and your **Groups** feed to see the results

## 📅 Your daily routine after setup

- **Wix (blog + community)**: nothing. It posts at its timer and comments 1–3×/day by itself.
- **Other auto sites (Blogger/WordPress/Ghost)**: nothing.
- **Manual sites** (~1 min each): **Site Panels → Copy Studio** → generate → copy → paste → mark posted.

## 🧠 How the system thinks

- **Max 10 posts/day** total across all sites; each site follows its OWN pace (posts/day + minimum gap)
- Each post: 300–500 words, **exactly ONE link** to your main site, placed naturally
- Different angle every post (listicle / review / how-to / roundup / trend / FAQ) → nothing looks copy-pasted
- Community comments: **useful, 1–3 sentences, one natural mention of your site** — never the same topic twice, 1–3/day so it reads human, not bot
- **Link Health**: red nofollow/dead = we adjust that site

## ❓ Troubleshooting

| Problem | Fix |
|---|---|
| Nothing posts | Site Panels: site ON? Mode not "Manual"? Timer time reached? → **Run now** |
| Wix test: "OAuth token failed" | Wrong Client ID/Secret — Dev Center → your app → **OAuth** tab (not "API Keys") |
| Wix test: HTTP 403 | App has no permission for that site — install the app on the site in Dev Center (it grants Blog/Community access) |
| Wix draft test fails with "memberId" | Auto-detect of your member was blocked — set **member_id** manually in the site credentials |
| Community: "Could not list topics / endpoints tried:…" | Send me that exact text — the topic path isn't fully documented by Wix; the output tells me which path to pin for your site |
| Community: no groups found | Install the **Groups** app on your Wix site (editor → Apps) |
| "Skipped (Wix keys not saved)" in run log | Expected — add the Wix OAuth keys in Backlink Websites first |
| Hashnode "API returned a web page" | That's the **paid wall** (Pro only since May 2026) — use Manual mode + Copy Studio, or upgrade to Pro |
| Cron didn't run | hPanel → Cron Jobs: path + username correct? Check Overview → run log |
| Image is a plain gradient | Image API key not saved — API Keys → Test |
