# ⚡ AutoBlog & Backlink Master Suite — Hostinger PHP Edition

> Complete PHP port of the AutoBlog SaaS platform, designed for **Hostinger Shared Hosting** (no Python required).

---

## 🌟 What This Is

This is a **100% PHP rewrite** of the original Python/Flask AutoBlog application. Every single feature from the original has been replicated in PHP so you can run it on Hostinger shared hosting without Python support.

---

## 🚀 Quick Deploy

1. **Upload** all files to `public_html/` on your Hostinger hosting
2. **Edit** `includes/config.php` — set `APP_BASE_URL` to your domain
3. **Set permissions** — `published_posts/` needs write access (777)
4. **Set up cron jobs** in Hostinger cPanel (see below)
5. **Visit** `https://yourdomain.com/register.php` to create your account

---

## 📋 Cron Jobs (Hostinger cPanel → Advanced → Cron Jobs)

```
*/5 * * * * php /home/yourusername/public_html/cron/scheduler.php
* * * * * php /home/yourusername/public_html/cron/approval_timer.php
```

---

## ✅ All Features Replicated

| Feature | Status |
|---------|--------|
| User Registration & Login with OTP | ✅ |
| 5 Workspace Slots | ✅ |
| API Vault (Blogger, Brevo, WordPress, DataForSEO, Chat, Image) | ✅ |
| AI Chat Provider (Gemini, OpenAI, Anthropic, HuggingFace, OpenRouter, ZenMux) | ✅ |
| AI Image Provider (HuggingFace, OpenAI, Gemini) | ✅ |
| 1000+ Word Article Generation with Anti-AI Sanitizer | ✅ |
| Local Magazine Publishing (HTML) | ✅ |
| Blogger REST API Publishing (with OAuth refresh) | ✅ |
| WordPress REST API Publishing | ✅ |
| Webhook Publishing (Zapier/Make.com) | ✅ |
| Domain Auditor & Keyword Research | ✅ |
| DataForSEO Integration | ✅ |
| AI Research Roadmap | ✅ |
| Demo Campaign Mode (no API tokens needed) | ✅ |
| Email Approval Workflow | ✅ |
| HTML Preview Generation | ✅ |
| Content Plans (Create, Approve, Schedule) | ✅ |
| Blog Scheduler with Campaign | ✅ |
| Backlink Watchdog (Verify, Audit, Dofollow) | ✅ |
| Social Auto-Poster (Facebook, Instagram, Pinterest) | ✅ |
| Outreach Pitch Generator | ✅ |
| Syndication Tag Generator | ✅ |
| Dark/Light Theme | ✅ |
| Scheduled Queue (Background Processing via Cron) | ✅ |

---

## 📂 Full Deployment Guide

See [HOSTINGER_DEPLOY.md](HOSTINGER_DEPLOY.md) for complete step-by-step instructions.
