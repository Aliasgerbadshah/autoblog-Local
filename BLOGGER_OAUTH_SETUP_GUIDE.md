# Blogger OAuth 2.0 Setup — Step-by-Step Guide

This guide will walk you through creating OAuth 2.0 credentials for the Blogger API v3 and obtaining all 4 values you need for AutoBlog:

| # | Value | Where You Get It |
|---|-------|-----------------|
| 1 | **Client ID** | Google Cloud Console |
| 2 | **Client Secret** | Google Cloud Console |
| 3 | **Refresh Token** | OAuth 2.0 Playground |
| 4 | **Blog ID** | Blogger API or your blog URL |

---

## PHASE 1 — Create a Google Cloud Project & Enable Blogger API

### Step 1: Go to Google Cloud Console
1. Open 👉 **https://console.cloud.google.com/**
2. Sign in with your **Google account** (the same one that owns your Blogger blog)
3. If you see a project selector popup, close it for now

### Step 2: Create a New Project
1. Click the project dropdown at the **top left** (next to "Google Cloud")
2. Click **"NEW PROJECT"**
3. Project name: type **`AutoBlog`** (or any name you like)
4. Click **"CREATE"**
5. Wait ~10 seconds, then select the new project from the dropdown

### Step 3: Enable the Blogger API v3
1. Go to 👉 **https://console.cloud.google.com/apis/library/blogger.googleapis.com**
2. Make sure your new project is selected (top left dropdown)
3. Click **"ENABLE"**
4. Wait until it says "API enabled" ✅

---

## PHASE 2 — Create OAuth 2.0 Credentials

### Step 4: Go to Credentials Page
1. Go to 👉 **https://console.cloud.google.com/apis/credentials**
2. Make sure your project is selected

### Step 5: Configure OAuth Consent Screen (Required First)
1. Click **"OAuth consent screen"** in the left sidebar
2. Choose **"External"** (since this is a personal project, not a Google Workspace org)
3. Click **"CREATE"**

4. Fill in the form:
   - **App name**: `AutoBlog`
   - **User support email**: Your email
   - **App logo**: Leave blank
   - **Application home page**: Leave blank
   - **Application privacy policy link**: Leave blank
   - **Application terms of service link**: Leave blank
   - **Authorized domains**: Leave blank
   - **Developer contact email**: Your email
5. Click **"SAVE AND CONTINUE"**

6. **Scopes page** — Click **"ADD OR REMOVE SCOPES"**
   - In the filter box, type **`blogger`**
   - Check ✅ **`https://www.googleapis.com/auth/blogger`** — **THIS IS THE FULL SCOPE (read + write)**
   - ⚠️ Do NOT select `blogger.readonly` — that will only let you READ, not PUBLISH
   - Click **"UPDATE"**
   - You should see `Blogger API v3 .../auth/blogger` in the table
   - Click **"SAVE AND CONTINUE"**

7. **Test users page** — Click **"ADD USERS"**
   - Add your own Google email address
   - Click **"ADD"**
   - Click **"SAVE AND CONTINUE"**

8. **Summary page** — Click **"BACK TO DASHBOARD"**

> **Note**: Your app will be in "Testing" mode. This is fine — it works for up to 100 test users. You don't need to publish it.

### Step 6: Create OAuth 2.0 Client ID
1. Go back to 👉 **https://console.cloud.google.com/apis/credentials**
2. Click **"+ CREATE CREDENTIALS"** at the top
3. Choose **"OAuth client ID"**

4. Fill in:
   - **Application type**: Select **"Web application"**
   - **Name**: `AutoBlog Web Client`

5. **Authorized redirect URIs** — This is critical! Click **"+ ADD URI"** and add exactly these 2 URIs:
   ```
   https://developers.google.com/oauthplayground
   ```
   ```
   https://developers.google.com"  
   ```
   > ⚠️ The redirect URI **must** be `https://developers.google.com/oauthplayground` — this is where the OAuth Playground sends the authorization code. If this is wrong, you'll get a `redirect_uri_mismatch` error.

6. Click **"CREATE"**

### Step 7: Copy Your Client ID and Client Secret
After clicking CREATE, a dialog appears showing:

- **Your Client ID** — looks like:
  ```
  123456789012-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com
  ```
- **Your Client Secret** — looks like:
  ```
  GOCSPX-xxxxxxxxxxxxxxxxxxxxx
  ```

📋 **COPY BOTH VALUES** — Paste them into a text file. You'll need them in Phase 3.

> You can always find them later at: Credentials page → Click your client name → View Client ID & Secret

---

## PHASE 3 — Get Your Refresh Token (Using OAuth 2.0 Playground)

This is the most important step. The Refresh Token is what lets AutoBlog publish posts without you logging in each time.

### Step 8: Open OAuth 2.0 Playground
1. Open 👉 **https://developers.google.com/oauthplayground**

### Step 9: Configure Playground to Use YOUR Client ID
1. Click the ⚙️ **gear icon** (top right corner)
2. Check ✅ **"Use your own OAuth credentials"**
3. Paste your **Client ID** from Step 7
4. Paste your **Client Secret** from Step 7
5. Click **"Close"**

### Step 10: Select the Blogger Scope
1. In the left panel "Select the OAuth 2.0 Scopes", find and expand **"Blogger API v3"**
2. Check ✅ **`https://www.googleapis.com/auth/blogger`**
   - ⚠️ **CRITICAL**: Select `auth/blogger` NOT `auth/blogger.readonly`
   - The **readonly** scope will give you a refresh token that can only READ — publishing will fail with 403!
3. You should see the scope appear in the input box at the right:
   ```
   https://www.googleapis.com/auth/blogger
   ```
4. Click **"Authorize APIs"**

### Step +0: Consent Screen
1. You'll see Google's consent screen
2. It may show "This app isn't verified" — click **"Continue"** (or "Advanced" → "Go to AutoBlog")
3. Click **"Allow"** to grant Blogger access
4. You'll be redirected back to the OAuth Playground

### Step 12: Exchange Authorization Code for Tokens
1. Back on the OAuth Playground, click **"Exchange authorization code for tokens"**
2. You'll see two tokens appear:
   - **Access token** — Short-lived (expires in ~1 hour) — You do NOT need to copy this
   - **Refresh token** — Long-lived (works forever unless you revoke it) — **📋 COPY THIS!**

The Refresh Token looks like:
```
1//0gxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

> ⚠️ **IMPORTANT**: If the Refresh token field shows `(not set)` or is empty, it means you already authorized this app before and Google is reusing the consent. To force a new refresh token:
> 1. Go to 👉 **https://myaccount.google.com/permissions**
> 2. Find "AutoBlog" and **Remove Access**
> 3. Go back to OAuth Playground and repeat from Step 10

---

## PHASE 4 — Get Your Blog ID

### Step 13: Find Your Blog ID

**Option A — From Blogger Dashboard:**
1. Go to 👉 **https://www.blogger.com/**
2. Open your blog
3. Look at the URL in your browser — it looks like:
   ```
   https://www.blogger.com/blog/posts/XXXXXXXXXX
   ```
4. The **XXXXXXXXXX** number is your **Blog ID** — 📋 Copy it

**Option B — From API:**
1. Go to 👉 **https://developers.google.com/oauthplayground**
2. After getting your tokens (Step 12), in the "Request URI" field, enter:
   ```
   https://www.googleapis.com/blogger/v3/blogs/byurl?url=https://YOUR-BLOG-NAME.blogspot.com/
   ```
3. Click **"Send the request"**
4. The response JSON will contain `"id": "XXXXXXXXXX"` — that's your Blog ID

---

## PHASE 5 — Enter Everything in AutoBlog

### Step 14: Open AutoBlog Vault Settings
1. Log in to your AutoBlog dashboard at **https://apps.colorfiind.com**
2. Go to **Settings** → **Vault** (or the Blogger section)
3. Enter the 4 values:

| Field | Value |
|-------|-------|
| **Blog ID** | The number from Step 13 |
| **OAuth Client ID** | The Client ID from Step 7 |
| **OAuth Client Secret** | The Client Secret from Step 7 |
| **OAuth Refresh Token** | The Refresh Token from Step 12 |

4. Click **"Save"** or **"Test Connection"**

### Step 15: Test the Connection
1. Click **"Test Connection"** in the Vault settings
2. You should see: ✅ **"Connected successfully! Blog: [Your Blog Name]"**
3. If you see an error, check the Troubleshooting section below

---

## TROUBLESHOOTING

### ❌ "redirect_uri_mismatch"
- Go back to **Google Cloud Console → Credentials → Your OAuth Client**
- Make sure `https://developers.google.com/oauthplayground` is in **Authorized redirect URIs**
- Re-authorize in OAuth Playground

### ❌ "invalid_client" or "Bad Request"
- You copied the Client ID or Client Secret incorrectly
- Go to Credentials → Click your client → Copy again exactly

### ❌ 403 Forbidden when publishing
- **Your Refresh Token was created with read-only scope!**
- Go to 👉 **https://myaccount.google.com/permissions** → Remove "AutoBlog" access
- Go back to OAuth Playground → Repeat Steps 10–12
- Make sure you select **`auth/blogger`** NOT `auth/blogger.readonly`

### ❌ 401 Unauthorized
- Refresh token has been revoked or expired
- Repeat Phase 3 to get a new refresh token

### ❌ 404 Blog Not Found
- Wrong Blog ID — verify using Step 13

### ❌ "This app isn't verified" warning
- This is normal for apps in "Testing" mode
- Click "Advanced" → "Go to AutoBlog (unsafe)" → "Allow"

---

## QUICK REFERENCE — All URLs You Need

| Purpose | URL |
|---------|-----|
| Google Cloud Console | https://console.cloud.google.com/ |
| Enable Blogger API | https://console.cloud.google.com/apis/library/blogger.googleapis.com |
| Credentials Page | https://console.cloud.google.com/apis/credentials |
| OAuth Playground | https://developers.google.com/oauthplayground |
| Google Permissions (revoke) | https://myaccount.google.com/permissions |
| Blogger Dashboard | https://www.blogger.com/ |

---

## SUMMARY — What You'll Have at the End

```
✅ Client ID:      123456789012-abc...apps.googleusercontent.com
✅ Client Secret:  GOCSPX-xxxxxxxxxx
✅ Refresh Token:  1//0gxxxxxxxxxxxxxxxxx
✅ Blog ID:        1234567890123456789
```

Paste all 4 into AutoBlog Vault → Save → Test Connection → You're done! 🎉
