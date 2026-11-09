# cPanel deployment readiness

Target: shared hosting with direct HTTPS, confirmed 2026-11-09.

## Completed in the repository

- HTTPS detection trusts native server settings. Forwarded HTTPS is accepted only
  from an explicitly configured proxy; direct cPanel hosting needs none.
- A forged localhost Host cannot enable development mode on a production APP_URL.
  Arbitrary Host headers cannot determine password-reset destinations.
- Private directories, configuration, test/cache artifacts and database dumps are
  denied before routing on every hostname. PHP can still include these files and
  CLI maintenance scripts still work. HTTP access to test fixtures is intentionally
  unavailable.
- Uploads use the static-file handler; executable and active HTML/JS extensions,
  including double extensions, are denied. Image/PDF/video URLs remain available.
- Security headers permit same-origin editor frames and restrict external framing,
  embedded objects and base URLs. This is a minimal CSP compatible with existing
  scripts, not a complete script-source policy.
- Shared-host PHP configuration disables public error output and enables private
  logging. Docker enables Apache headers; updated PHP ini settings require a
  container rebuild there. cPanel PHP-FPM may cache `.user.ini` for several minutes.

## Before switching the live site

1. Back up the current database and uploads using cPanel Backup/Backup Wizard or
   the hosting provider's backup service. Keep a copy outside `public_html` and
   off the hosting account. Configure automated daily backups and retention with
   the provider; a manual backup alone is not an automated backup policy.
2. Restore the backup into a separate staging database and directory. Compare
   table counts, verify representative published content and uploaded files, and
   record the restore date/result. Never import a test restore over the live DB.
   The repository's `scripts/backup-local-normalization.py` is a local Docker
   maintenance helper that stops services; it is not a cPanel backup solution.
3. Use a dedicated database user limited to this site's database. Apply required
   migrations using a separate maintenance credential, then use the least
   privileges the runtime needs. Some legacy code attempts additive schema setup;
   pre-apply migrations so normal requests need no CREATE/ALTER privileges.
4. Deploy all dotfiles, particularly root `.htaccess`, `.user.ini` and
   `uploads/.htaccess`. Keep the host's generated PHP handler directives. Do not
   deploy local Docker services or phpMyAdmin as production services.
5. Set the production configuration privately, preserving credentials:

   ```dotenv
   APP_URL=https://gncipampanga.com/admin
   AUTH_PUBLIC_URL=https://gncipampanga.com
   AUTH_TRUSTED_PROXIES=
   REQUEST_TRUSTED_PROXIES=
   REQUEST_CLIENT_IP_HEADER=
   REQUEST_LIMIT_DIR=/home/YOUR_CPANEL_ACCOUNT/private/gnci-request-limits
   ```

   Create that private directory for the PHP account (0700 where supported).
   Leave ADMIN_PWA_ORIGIN unset unless the separate admin domain is configured.
6. Enable AutoSSL and cPanel's Force HTTPS Redirect for the site. Verify both
   `www` and bare-domain certificates and redirects. These account settings cannot
   be enabled by editing this repository. Confirm Apache/LiteSpeed honors the
   supplied rules and response headers without 500 errors.
7. Select PHP 8.2+ with PDO MySQL, cURL, mbstring, fileinfo, GD, EXIF, ZIP and Imagick.
   Personnel face comparison also requires Python, its installed dependencies and
   models, and `proc_open`. Shared hosting may not support that runtime: verify it
   with the provider before enabling personnel uploads; do not bypass validation.
8. Run `php scripts/check-deployment.php` using the host's actual PHP executable.
   It reads configuration/schema/grants without printing credentials or migrating
   anything. Check web PHP settings separately using cPanel MultiPHP INI Editor.
9. Verify SMTP TLS/credentials and SPF/DKIM/DMARC. Use a controlled account to test
   login, OTP delivery/resend, password recovery/expiry, remembered-device logout,
   and session revocation after a password change. Confirm actual inbox delivery.
10. On staging, test role restrictions, draft/save/approve/publish, image and PDF
    upload/replace/delete, calendar imports, chatbot replies and consent, contact
    email, Facebook publishing and provider failures. Confirm mobile/PWA and
    editor preview frames still work. Avoid publishing test posts to a real Page.
11. Check private logs and 429/503 rates during a limited launch; verify disk space,
    log rotation, backup schedules and a documented rollback to the prior release.

## Verification scope

The local checks exercise native/trusted/spoofed HTTPS detection, hostile Host
headers, private-path denial on three hostnames, executable/double-extension upload
denial, static upload availability and public routes. Existing domain tests cover
editor presence, publishing confirmation and image processing separately.

Executed locally on 2026-11-09: 9 transport checks, 37 Apache HTTP checks,
89 admin-origin checks, 249 browser-route checks, all 31 anonymous admin-handler
rejections, 15 image-optimization checks, 18 profile-picture checks, 22 editor
presence checks and 27 publishing-confirmation checks passed. PHP syntax and
diff whitespace checks passed. The read-only preflight flagged the expected
local deployment gaps: localhost origins, current Docker PHP error settings
(new ini values need a rebuild), privileged local DB user and no persistent
private limiter directory. It confirmed required schema/runtime checks available
in the local container; this does not establish their availability in cPanel.

No cPanel account changes, production migrations, real emails, paid provider calls,
production backup restoration or live publishing were performed. Deployment approval
depends on completing the host-specific steps above; local tests cannot certify
production availability or backup recoverability.
