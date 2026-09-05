# 📖 How to Use AutoBacklink — Step by Step

Follow these steps once to set up. After that, your daily work is ~1 minute per manual site.

---

## STEP 1 — Install it on Hostinger

1. **hPanel → Websites → Manage** (your subdomain, e.g. `backlinks.yourdomain.com`)
2. Go to **File Manager** (or upload via FTP) and open the subdomain's document root
   (usually `public_html/` for a subdomain that has its own folder)
3. **Upload the `backlink-maker` folder** (unzipped) there
4. In File Manager: right-click the `packages` folder → **Permissions** → set to **777**
   (everything else stays as it is)
5. **hPanel → Advanced → Cron Jobs** → add:
   ```
   0 6 * * *
   php /home/YOURUSERNAME/public_html/backlink-maker/cron/daily.php
   ```
   (replace YOURUSERNAME with your Hostinger username; change `0 6` to your preferred hour:minute — must match the time you set in the Auto Runner panel)

## STEP 2 — First visit (one time only)

1. Open your subdomain in the browser
2. You'll see the **Setup screen** → create your username + password → done
3. Next visits: just the login page

## STEP 3 — API Keys (paste your AI keys)

Open the **API Keys** panel:

1. **Chat API** (writes the content):
   - Provider: pick what you use (Google Gemini / OpenAI / HuggingFace / Pollinations…)
   - Paste the API key, enter the model name (e.g. `gemini-2.5-flash-lite`)
   - Click **Save** → click **Test** (should say ✅ connection successful)
2. **Image API** (creates the image for each post): same idea
   - (These are the same keys/providers AutoBlog uses — you can paste the same ones)

> **No keys yet?** The software still works in *template mode* (ready-made content + fallback image) so you can test everything. Real AI content starts the moment you save a key.

## STEP 4 — Auto Runner (the brain settings, one time only)

Open the **Auto Runner** panel:

1. **MAIN SITE URL** — the website that RECEIVES the backlinks (e.g. `https://yourwebsite.com`) — every post links to it
2. **ANCHOR TEXT POOL** — 4–8 phrases, one per line. Example:
   ```
   YourBrandName
   your main keyword phrase
   their complete guide
   this resource
   ```
   (The software rotates them automatically and never repeats one twice in a row on the same site.)
3. **YOUR OWN TOPICS** (optional) — topics you specifically want covered, one per line. These are used FIRST, then the AI invents fresh ones (never repeating any topic ever).
4. **POSTS PER DAY** (start with 5) and **RUN TIME** (e.g. 06:00)
5. Click **Save**

⚠️ The cron job from Step 1 is what makes it run daily — without it, you must press **Run Now** each day.

## STEP 5 — Add your backlink websites

Open the **Backlink Websites** panel → the form on the right:

| Field | What to enter |
|---|---|
| Name | Anything you remember (e.g. "My Blogger 2", "DesignDirectory") |
| Site URL | `https://that-site.com` |
| Type | Blog / Directory / Forum / Q&A / Social / Review |
| Posting mode | **Manual** = you paste (99% of sites) · **Auto via API** = software posts by itself (only if you OWN the site: Blogger/WordPress/Ghost/Webhook) |
| Niche | What that site is about (helps content fit) |
| Every N days | Minimum days between posts on that site (default 7) |
| Account notes | Your handle/username there, which section to post in — shown in the instructions |

Click **Save** → repeat for every website. Toggle the switch to pause/resume any site.

**If you choose Auto via API**, fill the credentials:
- **Blogger**: Blog ID (from blogger.com/about) + Client ID + Client Secret + Refresh Token (Google OAuth)
- **WordPress**: site URL + username + Application Password (WP admin → Users → Profile → Application Passwords → Generate)
- **Ghost**: site URL + Admin API key
- **Hashnode**: Publication ID (shown in your Hashnode dashboard URL) + Personal Access Token (hashnode.com → your avatar → Settings → Developer). Note: Hashnode's API is for sites you OWN a Hashnode blog on — Hashnode is NOT a Ghost site, don't pick Ghost for it.
- **Webhook**: the URL (Make/Zapier hook)

## STEP 6 — Test it (one time only)

1. In **Auto Runner** → press **▶ Run now**
2. Open **Paste Queue** — you should see 1 package per due website:
   - 📋 **Copy Title** → paste into the site's title field
   - 📋 **Copy Body (HTML)** → paste into the site's editor (use **Plain Text** version if the editor can't take HTML)
   - 🖼 **Download Image** → upload it on that site (place it at the top)
   - 📖 *Instructions* (expand) — exact steps for that site's type, with your saved notes
   - **✅ Mark as posted** → paste the LIVE URL of the post you just published
3. Open **Link Health** — your link now appears there (dofollow status checks after deployment)

## 📅 Your daily routine (after setup)

- **Automatic sites**: nothing. The cron posts them every day by itself.
- **Manual sites** (~1 min each): open **Paste Queue** → for each package: copy title → copy body → upload image → submit on that site → **Mark as posted** + paste the URL.
- That's it. The system picks new sites each day (never posts to the same site more often than its interval, never repeats topics or anchors).

## 🧠 Things to know (how the system thinks)

- **Max 10 posts/day** hard limit; you choose 1–10
- Each post: 300–500 words, **exactly ONE link** to your main site, placed naturally
- Every post gets a different angle (listicle / review / how-to / roundup / trend / use-case / FAQ) → nothing looks copy-pasted
- **Link Health**: red `nofollow` or dead links = tell me, and we adjust that site
- If a site starts rejecting posts (CAPTCHAs, spam filters), toggle it OFF in Backlink Websites — don't force it

## ❓ Troubleshooting

| Problem | Fix |
|---|---|
| Nothing appears in Paste Queue | Check Backlink Websites: sites ON? Interval passed? → press **Run Now** |
| "Chat API" test fails | Wrong key/provider/model — re-check the API Keys panel |
| Image is a plain gradient | Image API key not saved (or failed) — check API Keys → Test |
| Blogger auto-post fails | OAuth credentials wrong/expired — re-generate the refresh token |
| Cron didn't run | hPanel → Cron Jobs: correct path? username? Then check Overview → run log |
