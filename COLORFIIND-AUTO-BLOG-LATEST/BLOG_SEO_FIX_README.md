# Blog Indexing + Sitemap Fix — Upload Guide

## What was wrong (4 bugs)

1. **`/blog/sitemap.xml` was a 404** — `updateRSS()` called a method
   (`absolutePostUrl()`) that did not exist, so PHP died before
   `updateSitemap()` ever ran. No sitemap file was ever created.
2. **Every article had a broken canonical** (`/blog/blog/posts/...`, double
   `/blog/`) because `site_url` was concatenated with a path that already
   started with `/blog/`. Google will not index pages whose canonical is a 404.
3. **Blog list page 1 had no "Next" link** — crawlers could never reach page 2+
   or your older posts.
4. **The main site's "Blog" menu opens the wrong page** — it points at
   `/blogs`, which just renders the palette homepage. The real list is `/blog/`.
   There is also no root `robots.txt`, so Google never discovers the sitemap.

## What the fix does

- Sitemap + RSS are now served **live from the database**
  (`blog/sitemap.php`, `blog/rss.php` + `blog/.htaccess` rewrites), with
  correct absolute URLs, plus category/tag archive URLs included in the sitemap.
  Static `sitemap.xml`/`rss.xml` files are still written on every publish as a
  backup — to **both** `sub_apps/blog/` and the live `public_html/blog/`.
- Canonicals, OG URLs, JSON-LD and share links now use the fixed
  `absoluteUrl()` helper (no more `/blog/blog/...`).
- Already-published article files are **auto-repaired on the next blog visit**
  (one-time, then a `.seo_repair_v1.done` flag stops it re-running).
- Listing pagination now shows Previous + page numbers + Next from page 1,
  with correct per-view canonicals (search views are `noindex`).
- New `/blogs` → `/blog/` 301 redirect shim, and a ready-made root
  `robots.txt` declaring both sitemaps.

## Upload steps (Hostinger File Manager)

> Enable **"Show Hidden Files"** first, or `.htaccess` files won't upload.

**A. Into `public_html/blog/`** (overwrite existing):

- `blog/.htaccess` (NEW — the sitemap/category/tag/pagination rewrites)
- `blog/sitemap.php` (NEW)
- `blog/rss.php` (NEW)
- `blog/index.php` (pagination + canonicals + one-time repair)
- `blog/includes/publisher.php` (URL + sitemap/RSS fixes)
- `blog/templates/article.html` (category link encoding)

**B. New folder `public_html/blogs/`** (create it, upload both files):

- `blogs/index.php` → 301 redirects `/blogs*` to `/blog/*`
- `blogs/.htaccess`

**C. Root `public_html/robots.txt`** (NEW file — safe, none exists today):

- Rename `ROOT-robots.txt` → `robots.txt` and upload to `public_html/`.

**D. Into `public_html/sub_apps/blog/`** (only if that copy still exists —
keeps the dashboard-side copy in sync):

- Same 6 files as step A.

## Verify (open in browser after upload)

1. `https://colorfiind.com/blog/sitemap.xml` → shows XML with your posts
   (no more "This Page Does Not Exist").
2. `https://colorfiind.com/blog/rss.xml` → shows RSS feed.
3. `https://colorfiind.com/blogs` → redirects to `/blog/` list.
4. `https://colorfiind.com/robots.txt` → shows both Sitemap: lines.
5. `https://colorfiind.com/blog/` → page 1 now shows **Next →** + page numbers.
6. Open any article → View Source → `rel="canonical"` must be
   `https://colorfiind.com/blog/posts/...` (single `/blog/`).

## After upload — do this in Google Search Console (important)

1. **Sitemaps** → Add `https://colorfiind.com/blog/sitemap.xml` → Submit.
2. **URL Inspection** → paste 2–3 article URLs → **Request Indexing**.
3. Keep the daily posts flowing — with a live sitemap + fixed canonicals,
   new posts are typically discovered within days.

## Permanent menu fix (main website code, separate from this app)

Change the main site's "Blog" menu link from `/blogs` to
`https://colorfiind.com/blog/`. The `public_html/blogs/` shim above already
covers visitors until then.
