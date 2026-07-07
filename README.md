# Google Login for GetSimpleCMS-CE

Adds a **"Sign in with Google"** option to the GetSimple admin login page, linked to specific existing user accounts. It does not create accounts, does not touch core user files, and does not replace password login — it's an additional door in, opened only for accounts an admin has explicitly linked.

---

## Requirements

- GetSimpleCMS-CE 3.3.22+
- PHP with `SQLite3` and `cURL` extensions enabled (both are standard on most hosts)
- HTTPS on your live domain (Google requires it for the OAuth redirect URI on anything other than `localhost`)
- A Google Cloud project with an OAuth 2.0 Web application client

---

## Installation

1. Unzip the plugin package so the following sit **side by side** inside your `plugins/` folder:
   ```
   plugins/
   ├── GoogleLogin.php
   └── GoogleLogin/
       └── lang/
           ├── en_US.php
           ├── es_ES.php
           └── .htaccess
   ```
2. Log in to the GS admin panel. The plugin will appear under **Settings → Google Login**.
3. Follow the **Help** tab inside the plugin for the Google Cloud Console walkthrough (also summarized below).
4. Paste your Client ID and Client Secret into the **OAuth Settings** tab and enable the plugin.
5. Go to the **User Mapping** tab and enter the Google email address for each GetSimple account that should be allowed to use Google sign-in. Leave a user's field blank to keep them on password-only login.

---

## Setting up the Google Cloud OAuth client

1. Go to [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials).
2. Create or select a project, then configure the **OAuth consent screen** (may appear as *Google Auth Platform → Branding*): app name, support email, authorized domain. "Testing" mode is fine — only accounts you link in the User Mapping tab can ever successfully sign in regardless of consent screen status.
3. Go to **Credentials → Create Credentials → OAuth client ID**, choose **Web application**.
4. Under **Authorized redirect URIs**, add the exact URI shown in the plugin's Settings/Help tab:
   ```
   https://yourdomain.com/admin/index.php?gl_action=callback
   ```
   (replace `admin` with your actual `$GSADMIN` folder name if you've renamed it)
5. Click **Create**. Copy the **Client ID** and **Client Secret** into the plugin's OAuth Settings tab.

> **Local development note:** Google requires HTTPS for this redirect URI on any real domain. Testing on a plain-HTTP local environment (e.g. Laragon) requires a separate OAuth client configured with an `http://localhost` redirect URI.

---

## How it works

- The **Settings** tab stores your Client ID/Secret and an enabled flag in the plugin's own SQLite database (`data/other/GoogleLogin.db`) — nothing is written to `gsconfig.php` or core files.
- The **User Mapping** tab reads existing usernames straight from `data/users/*.xml` and lets you attach a Google email to each one, stored in the same SQLite database.
- On the login page, a **"Sign in with Google"** button appears above the password form (hooked via `index-login`).
- Clicking it starts a standard OAuth 2.0 authorization-code flow. On return, the plugin:
  1. Verifies the CSRF `state` parameter
  2. Exchanges the authorization code for a token
  3. Calls Google's userinfo endpoint and confirms the email is `email_verified`
  4. Looks up that email in the plugin's mapping table
  5. If matched, sets `$USR` to the linked GetSimple username and calls GS's own `create_cookie()` — the exact same session mechanism a normal password login uses
  6. If not matched (or any step fails), redirects back to the login page with a plain-language error, and no session is ever created

All of this is gated to only run on the actual admin login page (`common` hook + `get_filename_id() === 'index'` check), so the OAuth callback logic never executes anywhere else on the site.

---

## Security notes

- **The Google-email-to-username mapping is the entire security boundary.** Once an email matches, the linked account is logged in with no password check — so only link Google accounts you trust to have equivalent access to that GetSimple user's password.
- The OAuth `state` value is a short-lived (10 minute), single-purpose CSRF token, separate from GS's own long-lived login cookie mechanism.
- Only `email_verified: true` responses from Google are accepted.
- This plugin does not currently coexist with `gs_2fa` — an account should use one or the other, not both, since Google sign-in bypasses the password step that a 2FA challenge would otherwise follow.

---

## Uninstalling

Deactivating/removing the plugin removes the login button and blocks the OAuth callback route. To fully clean up, also delete `data/other/GoogleLogin.db`, which contains your stored Client ID/Secret and the email mapping table.

---

## i18n

Included: `en_US`, `es_ES`. All UI strings are in `GoogleLogin/lang/`, namespaced under `GoogleLogin/lang_*` keys.

---

## Author

risingisland — https://ko-fi.com/ericmontgomery
