# AutoBlog & Backlink Master Suite - Hostinger Deployment Guide

## 🚀 Complete Deployment Instructions for Hostinger Shared Hosting

This is the PHP version of the AutoBlog SaaS, fully converted from Python/Flask to run on Hostinger shared hosting without Python.

---

## 📂 File Structure (Upload to `public_html/`)

```
public_html/
├── .htaccess                 # URL routing + security
├── index.php                 # Main dashboard + API router
├── login.php                 # Login page
├── register.php              # Registration page
├── logout.php                # Logout handler
├── includes/
│   ├── config.php            # App configuration (APP_BASE_URL auto-detected!)
│   ├── database.php          # SQLite database init
│   ├── session.php           # Session management
│   ├── auth.php              # SecurityVault class
│   ├── autoblog_engine.php   # Content generation + publishing
│   ├── backlink_engine.php   # Backlink verification
│   ├── social_engine.php     # Social media auto-poster
│   ├── ai_provider.php       # AI Chat & Image API client
│   ├── research_agent.php    # Website crawler + keyword research
│   ├── anti_ai_sanitizer.php # Anti-AI text sanitizer
│   ├── helpers.php           # Utility functions
│   └── mailer.php            # Brevo email sender
├── templates/
│   └── index.html            # Dashboard template
├── cron/
│   ├── scheduler.php         # Scheduled publishing cron job
│   └── approval_timer.php    # Approval timer cron job
├── published_posts/          # Generated blog articles (auto-created)
└── seo_autoblog.db           # SQLite database (auto-created on first visit)
```

---

## 📋 Step-by-Step Deployment

### Step 1: Upload Files

1. Log into your **Hostinger hPanel**
2. Go to **File Manager**
3. Navigate to `public_html/`
4. **IMPORTANT**: Delete any existing files in `public_html/` first (or move them to a backup)
5. Upload ALL files from the ZIP, maintaining the folder structure
6. **IMPORTANT**: Make sure `.htaccess` is uploaded (it may be hidden by default — enable "Show Hidden Files" in File Manager)

### Step 2: Set File Permissions

In Hostinger File Manager or via SSH:

```bash
chmod 755 public_html/
chmod 644 public_html/*.php
chmod 644 public_html/.htaccess
chmod 755 public_html/includes/
chmod 644 public_html/includes/*.php
chmod 755 public_html/templates/
chmod 755 public_html/cron/
chmod 644 public_html/cron/*.php
chmod 777 public_html/published_posts/
```

**CRITICAL**: The `public_html/` directory itself must be writable (755) so PHP can create the `seo_autoblog.db` database file.

### Step 3: APP_BASE_URL is Auto-Detected!

**No manual configuration needed!** The app automatically detects your domain from the server headers. If you need to override it, set the `APP_BASE_URL` environment variable in Hostinger.

### Step 4: Set Up Cron Jobs

1. Go to **Hostinger hPanel** → **Advanced** → **Cron Jobs**
2. Add these cron jobs (dashboard lives in `public_html/sub_apps/`):

**Auto Blog daily worker (writes HTML + hands posts to Blogger scheduler):**
```
*/5 * * * * php /home/yourusername/public_html/sub_apps/cron/daily_autoblog.php
```

**Scheduler (due queue + website scheduled posts):**
```
*/5 * * * * php /home/yourusername/public_html/sub_apps/cron/scheduler.php
```

**Approval Timer (Human Article Writer only — emails + HTML after approve):**
```
*/5 * * * * php /home/yourusername/public_html/sub_apps/cron/approval_timer.php
```

Replace `yourusername` with your actual Hostinger username.

**How to check cron is working:** open the **Auto Blog** tab. Click **Run Auto Cron Now**. **Last cron run** must change to the current time. Approve in Human Article Writer is a different path — that writes HTML in the same click and does not update Last cron run.

### Step 5: Access Your Application

1. Open your browser and go to: `https://yourdomain.com/register.php`
2. Create your admin account
3. Log in at: `https://yourdomain.com/login.php`
4. You'll be redirected to the main dashboard

---

## 🔧 Features (All Replicated from Python Version)

| Feature | Status | Notes |
|---------|--------|-------|
| User Registration & Login | ✅ | With password hashing |
| Brevo Email OTP Verification | ✅ | Two-step login security |
| 5 Workspace Slots | ✅ | Per-user multi-site management |
| Slot Profile Settings | ✅ | Domain, word count, destination |
| API Vault | ✅ | Blogger, Brevo, WordPress, DataForSEO, Chat, Image |
| AI Chat Provider (Gemini, OpenAI, Anthropic, etc.) | ✅ | Multi-provider support |
| AI Image Provider (HuggingFace, OpenAI, Gemini) | ✅ | Multi-provider support |
| Content Generation (1000+ word articles) | ✅ | Anti-AI sanitizer included |
| Local Magazine Publishing | ✅ | HTML files with index |
| Blogger REST API Publishing | ✅ | With OAuth refresh |
| WordPress REST API Publishing | ✅ | With Application Passwords |
| Webhook Publishing | ✅ | Zapier/Make.com integration |
| Domain Auditor & SerpAPI | ✅ | Website crawling + keyword research |
| DataForSEO Integration | ✅ | Keyword volume, SERP, competitor |
| AI Research Roadmap | ✅ | Chat-based research + email approval |
| Demo Campaign Mode | ✅ | No API tokens needed |
| Approval Workflow (Email) | ✅ | Approve/Disapprove via email links |
| HTML Preview Generation | ✅ | For approved articles |
| HTML Approve/Disapprove in Dashboard | ✅ | Same as draft approval |
| Content Plans | ✅ | Create, approve, schedule |
| Blog Scheduler | ✅ | Campaign scheduling with calendar + posting times |
| Backlink Watchdog | ✅ | Verify, audit, dofollow check |
| Social Auto-Poster | ✅ | Facebook, Instagram, Pinterest |
| Outreach Pitch Generator | ✅ | Guest post outreach emails |
| Syndication Tag Generator | ✅ | Canonical tags for cross-posting |
| Dark/Light Theme Toggle | ✅ | Same UI as original |
| Dashboard Stats | ✅ | Posts, backlinks, social shares |
| Scheduled Queue | ✅ | Cancel, manage |
| Cron Job Background Processing | ✅ | Replaces Python threading |
| 9:16 YouTube Thumbnail | ✅ | First image after H1, gradient placeholder fallback |
| Mobile-Optimized HTML | ✅ | Responsive @media queries |
| Image Validation | ✅ | Broken images removed, onerror handler |

---

## 🔑 Key Differences from Python Version

| Python (Original) | PHP (Hostinger) |
|-------------------|-----------------|
| Flask Web Server | Plain PHP + .htaccess |
| `threading.Thread` | Cron Jobs (cPanel) |
| `requests` library | PHP cURL |
| `BeautifulSoup` | PHP DOMDocument |
| `feedparser` | PHP SimpleXML |
| `werkzeug` password hashing | PHP `password_hash()` |
| Flask sessions | PHP sessions |
| `sqlite3` module | PHP PDO SQLite |
| `base64` encoding | PHP `base64_encode/decode` |
| `secrets.token_urlsafe` | PHP `bin2hex(random_bytes())` |

---

## 🔒 Security Notes

1. The `.htaccess` file blocks direct access to `includes/`, `cron/`, and `.db` files
2. Passwords are hashed with `password_hash()` (bcrypt)
3. API credentials are stored base64-encoded in the database
4. Session management uses PHP's native secure sessions
5. `APP_BASE_URL` is auto-detected from `$_SERVER['HTTP_HOST']`

---

## 🐛 Troubleshooting

### "500 Internal Server Error"
- **Most common cause**: `.htaccess` syntax error. Make sure `.htaccess` was uploaded correctly (enable "Show Hidden Files")
- Check file permissions (755 for directories, 644 for files)
- Check that PHP version is 8.0+ in Hostinger hPanel
- Try removing `.htaccess` temporarily to see if the site loads without it

### "Blank White Page"
- Check PHP error log in Hostinger (hPanel → Advanced → PHP → Error Log)
- May be a PHP syntax error — check that all files were uploaded completely
- Make sure PHP version is 8.0+ (required for `str_starts_with()`)

### "SQLite database not found"
- Ensure `public_html/` directory has write permissions (755)
- The database is auto-created on first page load
- Check PHP error log for specific database errors

### "Cron jobs not running"
- Verify the cron path uses your actual Hostinger username
- Check Hostinger cron job logs
- Test manually: `php /home/username/public_html/cron/scheduler.php`

### "Brevo emails not sending"
- Verify your Brevo API key in the API Vault (Settings page)
- Check that the sender email is verified in Brevo
- The default Brevo key may have expired — generate a new one from Brevo dashboard

### "Images not showing in blogs"
- Only 1 mandatory thumbnail image is placed after the H1 tag (9:16 ratio)
- If the AI Image API fails, a gradient placeholder is shown
- Broken image URLs are automatically removed from blog content
- Verify your Image API key is set in the API Vault

---

## 📞 Support

For issues specific to this Hostinger deployment, check:
1. Hostinger's PHP version (requires PHP 8.0+)
2. PHP extensions: PDO SQLite, cURL, JSON, DOM, SimpleXML
3. Hostinger's cron job documentation
