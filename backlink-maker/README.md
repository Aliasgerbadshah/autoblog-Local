# 🎯 Backlink Maker — How to fill your target list

This folder will hold a **standalone** backlink maker (separate from AutoBlog —
own login, own database, own dashboard).

## Step 1: fill `targets_template.csv`

One row per site. Keep the header line exactly as-is.

| Column | Meaning | Example |
|---|---|---|
| `name` | Short label for you | `My Blogger 2`, `Design Directory IN` |
| `site_url` | The site's URL | `https://designs-inside.in` |
| `type` | blog / directory / forum / qa / social / review / other | `directory` |
| `publish_mode` | `api` (auto-post) or `manual` (you copy-paste) | `manual` |
| `platform` | only for api: blogger / wordpress / ghost / webhook — leave blank for manual | `blogger` |
| `niche` | what the site is about (helps content fit) | `interior design` |
| `min_interval_days` | min days between two posts on this site (default 7) | `7` |
| `account_notes` | your saved username/handle or special notes | `logged in as aliasger@…, post in /community` |

### Example rows

```
name,site_url,type,publish_mode,platform,niche,min_interval_days,account_notes
My Blog 2,https://myblog2.blogspot.com,blog,api,blogger,web services,7,OAuth in vault
My WP Site,https://mysite.com,blog,api,wordpress,digital marketing,7,app password in vault
Design Directory,https://designs-inside.in,directory,manual,,interior design,7,handle: aliasger — profile tab
WebDev Forum,https://webdev-talk.com,forum,manual,,web development,10,member since 2024
```

## Step 2: send me the list

Paste it here (or attach the CSV). I will:
1. Put every site in the right lane (API auto-post vs manual copy-paste)
2. Build the software in this folder tailored to your exact list
3. Give you the 3-line Hostinger deploy steps (folder upload + 1 cron job)

## What the software does each day (06:00)

- Picks the next due targets (round-robin, respects min_interval_days)
- Writes a fresh 300–500 word post with ONE natural link to your main site
  (rotating anchor text — you set the pool in Settings)
- Generates 1 image and saves it as a real file for you
- API sites → posted automatically, URL recorded
- Manual sites → a ready package: Copy Title / Copy Body / Download Image /
  step-by-step instructions → you paste & submit → click "Mark as posted"
- Every live link is re-checked weekly (dofollow/nofollow + dead detection)
