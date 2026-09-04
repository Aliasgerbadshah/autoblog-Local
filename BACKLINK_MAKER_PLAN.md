# 🎯 AutoBacklink — The Plan (Final)
**Standalone backlink software — separate from AutoBlog, deployed on its own subdomain.**

> Status: ✅ **software is being built right now in the `backlink-maker/` folder** —
> this plan matches exactly what is being coded.

---

## 1. What it is

A completely separate program with its **own login, own database, own dashboard**
(zero shared files with AutoBlog). It does ONE job: **automatically create and place
backlinks to your main website every day.**

It uses the same "brain" AutoBlog uses for creating blogs:
- **Chat API** (Gemini / OpenAI / HuggingFace / OpenRouter / Anthropic / Pollinations) writes the content
- **Image API** (HuggingFace / Gemini / OpenAI / Pollinations) generates the image
- Same anti-AI cleanup so text reads human

…but it creates **short backlink posts** (300–500 words, 1 link) instead of full blogs.

## 2. Your dashboard — the panels

| Panel | What you do there |
|---|---|
| **Overview** | Stats: active websites, posts today, live backlinks, waiting for you, run log |
| **Backlink Websites** | ⭐ The panel you asked for — *Add Website* form: name, URL, type (blog/directory/forum/QA/social/review), mode (**Auto-API** or **Manual**), credentials (Blogger OAuth / WP app-password / Ghost key / webhook) if API, niche, "post at least every N days", your account notes. All filled in the dashboard — **no CSV** |
| **Auto Runner** | Your **main website URL** (receives the links), **anchor text pool** (rotates automatically), your own topics (optional), **posts per day**, **run time**, ▶ Run Now |
| **Paste Queue** | For sites without API: each package has **Copy Title / Copy Body (HTML) / Copy Body (Text) / Copy Anchor / ⬇ Download Image** + per-site step-by-step instructions + **✅ Mark as posted** (paste the live URL) |
| **Link Health** | Every posted link tracked: dofollow/nofollow badge, dead-link detection, re-check button |
| **API Keys** | Chat API + Image API panels (same providers AutoBlog uses) + test buttons |

## 3. How a day works (fully automatic)

```
Hostinger cron (daily, e.g. 06:00)
  │
  ├─ 1. Pick the websites that are DUE (each site has its own minimum interval,
  │      default 7 days — so the same site never gets two posts close together)
  │
  ├─ 2. For each site:
  │      • pick a FRESH topic (your custom topics first → then AI-generated →
  │        never repeated — built-in dedup memory)
  │      • pick a content ANGLE (7 rotating styles: listicle, review, 3-step
  │        how-to, resource roundup, trend note, use-case, FAQ — so no two posts
  │        look identical)
  │      • Chat API writes 300–500 words with EXACTLY ONE natural link to your
  │        main site + rotating anchor (never repeats the last anchor on that site)
  │      • anti-AI cleanup applied (same as AutoBlog)
  │      • Image API generates 1 image → saved as a real file you can download
  │
  ├─ 3. LANE A — sites with API (Blogger/WordPress/Ghost/Webhook):
  │         posted BY ITSELF → URL recorded automatically
  │
  ├─ 4. LANE B — sites without API (directories, forums, QA, Medium…):
  │         package ready in the Paste Queue → you copy, paste, upload image,
  │         submit (~1 minute each) → click "Mark as posted"
  │
  └─ 5. Every posted link (both lanes) enters Link Health tracking
         (re-checked: dofollow/nofollow + alive/dead)
```

**Your daily work: ~1 minute per manual site. Everything else is automatic.**

## 4. Honest automation truth (important)

| Site type | Can software post by itself? | Why |
|---|---|---|
| Your Blogger blogs | ✅ YES (Blogger API) | Official API, 100% auto |
| Your WordPress sites | ✅ YES (REST API + app password) | Official API, 100% auto |
| Ghost blogs | ✅ YES (Admin API) | Official API, 100% auto |
| Anything with a webhook (Make/Zapier) | ✅ YES | Any destination |
| Directories / forums / Quora / Reddit / Medium | ❌ No one has an API for these | Auto-browsing them = CAPTCHAs + **account bans**. So the software does everything EXCEPT the final paste (that's the ~1 min/site) |

So: the more of your own Blogger/WordPress sites you add (Lane A), the less you
touch. You told me you don't have your own sites yet → **all your sites start in
Lane B (paste queue)**, and the moment you create a Blogger blog, you add it as an
API site and it becomes fully automatic.

## 5. SEO safety (built into the engine)

- Max 10 posts/day hard cap, your pick is 4–10 (we'll start with 5)
- Each site: minimum interval between posts (default 7 days) — round-robin order
- Every post: different topic + different angle + rotating anchor → no duplicate fingerprints
- Exactly ONE link per post, placed naturally in the first/second third
- Anchor pool rotates and never repeats on the same site
- No comment-spam — the system is built for profile/post placements that survive
- Link Health shows you which links are actually dofollow before you scale up

## 6. Deployment on Hostinger (subdomain)

1. Create the subdomain (e.g. `backlinks.yourdomain.com`) and upload this
   **`backlink-maker/` folder** as its document root (or put it in
   `public_html/backlink-maker/`)
2. Permissions: `chmod 777 packages/` (rest stays 644/755)
3. One cron job (hPanel → Advanced → Cron Jobs), matching your run time:
   ```
   0 6 * * * php /home/YOURUSERNAME/public_html/backlink-maker/cron/daily.php
   ```
4. Open the subdomain → **Setup** (create admin login) → **API Keys** (paste your
   Chat + Image keys — the same ones AutoBlog uses) → **Backlink Websites**
   (add your sites) → **Auto Runner** (main site URL + anchors + schedule) → done.
   It now runs every day by itself.

## 7. What I need from you (to go live)

1. **Your main website URL** — the site that receives the backlinks
   *(enter in Auto Runner, or tell me and I'll pre-fill)*
2. **Anchor phrases** — 4–8 phrases you're happy to be linked with
   *(or I'll draft from your site: brand name, main keyword, "their guide"…)*
3. **Your target websites** — as you get them, just tell me the name + URL +
   "do you have an account there?" — I'll set each one in the right lane
   *(or add them yourself in the Backlink Websites panel — it's all form-based)*
4. **Chat + Image API keys** — same keys as AutoBlog's vault (paste in API Keys panel)

## 8. Build status

| Part | Status |
|---|---|
| Folder + structure (`backlink-maker/`) | ✅ done |
| Own database (settings, targets, jobs, topic memory, run log) | ✅ done |
| Login + setup + session security | ✅ done |
| API Keys panel (Chat + Image, all AutoBlog providers, test buttons) | ✅ done |
| Backlink Websites panel (all fields, per-site credentials, intervals) | ✅ done |
| Auto Runner panel (main URL, anchors, topics, schedule, Run Now, log) | ✅ done |
| Content engine (7 angles, anchor rotation, topic dedup, anti-AI, template fallback) | ✅ done |
| Paste Queue (copy buttons, image download, instructions, mark-as-posted) | ✅ done |
| Link Health (dofollow check, re-check) | ✅ done |
| Publishers (Blogger / WordPress / Ghost / Webhook) | ✅ done |
| Daily cron + "Run Now" | ✅ done |
| Live preview for you to click through | 🔄 in progress (this session) |
| Deployment to your subdomain | ⏳ after you confirm the preview |
