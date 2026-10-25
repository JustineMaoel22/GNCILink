# Authentication and password recovery

## Deployment

1. Back up the database. Import `sql/first-login-password-migration.sql` if not already applied, then import `sql/auth-security-migration.sql`. The new migration creates three tables and preserves accounts and content. Deploy the migration before the new authentication code: login fails closed while its required tables are missing.
2. In the existing backend `.env`, configure `APP_URL` for your deployment and `AUTH_PUBLIC_URL=https://your-real-domain.example`. Reset links use this configured origin, never the request Host header. The admin application must be installed at `/admin`.
3. Configure the existing `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` (`tls` for STARTTLS/587 or `ssl` for implicit TLS/465), and `MAIL_FROM_NAME`. Optional `MAIL_FROM_ADDRESS` must be a sender your provider allows. Install the existing Composer dependencies (including PHPMailer) on the host. Do not put credentials in public files or source control.
4. Enable a valid HTTPS certificate. HTTP admin GET requests redirect to the configured HTTPS origin; HTTP POST requests are rejected. Local development permits HTTP only when APP_URL names localhost or a loopback address. Production must never use a localhost APP_URL. When using a reverse proxy, set `AUTH_TRUSTED_PROXIES` to the comma-separated exact IPs of your trusted proxy servers, and configure those servers to replace X-Forwarded-Proto. Do not trust arbitrary forwarded headers.
5. PHP needs PDO MySQL, cURL with a working CA certificate store, and PHPMailer. Allow outbound SMTP and HTTPS to `api.pwnedpasswords.com`. Never disable TLS certificate verification. Configure SPF/DKIM/DMARC with your mail provider and check its delivery logs.
6. Request a reset for a controlled real account on the hosted site; verify inbox delivery, expiry, reset, notification and normal login. SMTP acceptance does not guarantee inbox delivery. Local isolated tests cannot prove hosted SMTP connectivity or inbox delivery.

The new session-version requirement signs out legacy sessions lacking a version. Everyone can sign in again normally. The existing three-role permission policy and first-login change requirement remain enforced.

## Behavior

- Five incorrect account passwords lock that account for 15 minutes. Requests during the lock do not extend it. A separate 30-attempt/15-minute IP limit protects login attempts, including unknown accounts. Wrong credentials, inactive accounts, and locked accounts receive `Invalid username/email or password`.
- OTP verification allows five attempts per account per 15 minutes across sessions. Resends require POST and CSRF, allow one per minute and at most three sends per 15 minutes. They never reset verification attempts. Pending OTP sessions carry the credential version and cannot survive password reset.
- Recovery requests allow three per email and ten per IP in 15 minutes. Reset submissions allow twenty attempts per IP in 15 minutes. Rate limits live in MySQL and survive browser/session changes.
- Reset links contain a 32-byte cryptographic random token. Only its SHA-256 hash is stored. A new request replaces the previous token; expiration uses the database clock. On opening a link, the token is kept in the server session and the browser redirects to a clean URL. Configure web-server/proxy/access/APM logs to omit query strings on `/admin/reset-password.php` so the first incoming link is not recorded.
- Reset completion locks the token and account in a transaction, changes the password hash, consumes the token, clears required-change/OTP/trust state, and increments the credential version. Existing login sessions are refused on their next request. The browser returns to login without automatic authentication.
- Forgot Password always displays the same response for existing/unknown accounts and delivery failures. Delivery errors are recorded in the Super Admin security log and sanitized server logs. No passwords are emailed. Reset confirmation email failures are logged; they do not undo an already-completed password reset.
- Logout links open a confirmation form. Only a CSRF-validated POST ends the session, revokes the remembered-device token, and expires cookies.

## Passwords and sessions

New account temporary passwords remain 12 random characters, displayed once and stored only as hashes. User-chosen passwords allow passphrases and special characters, require 12 Unicode characters, and have a 72-byte maximum to avoid bcrypt truncation. Common patterns are rejected locally. Passwords are also checked against Have I Been Pwned using only the first five SHA-1 hash characters and padded responses; the password and full hash are never sent. SHA-1 is used only for this lookup; stored passwords use `password_hash()`. If the safety service is unavailable, changes/resets wait until it is available. The strength meter is guidance, not a replacement for backend checks.

Session IDs rotate on authentication and first-login password changes. Cookies use HttpOnly and SameSite=Lax; hosted sessions require Secure. Strict session mode and cookie-only session IDs are enabled. Idle sessions expire according to SESSION_TIMEOUT (default 30 minutes).

Security events include timestamp, account when known, action and direct peer IP, without submitted emails, passwords or tokens in descriptions. Super Admin can view the latest 200 in Activity Logs. Behind a proxy the recorded IP is the proxy peer; deploy a trusted server-level real-IP configuration if individual-client rate limits are needed. Apply a retention policy appropriate to the organization. Expired rows can be pruned periodically with `DELETE FROM auth_rate_limits WHERE expires_at < NOW()` and `DELETE FROM auth_password_resets WHERE expires_at < NOW()`; security log retention is an administrator decision.

References: [PHP session security](https://www.php.net/manual/en/session.security.ini.php), [Have I Been Pwned range API](https://haveibeenpwned.com/API/v3#SearchingPwnedPasswordsByRange).

## Rollback

Restore the previous application release if necessary; retain the additive tables and password hashes. Do not restore an old password database over new password changes. Reverting authentication code can also revert session revocation and recovery protections, so disable recovery routes during a rollback.

## Verification

Run `php tests/first-login-password-test.php` and `node tests/rbac-http-test.cjs` (Docker and Chrome required). The latter creates an isolated schema and synthetic accounts, runs real authentication/recovery handlers and browser checks, and removes the test resources. The SMTP integration uses a local protocol receiver, never a real recipient. The isolated password breach check supplies deterministic compromised/clean/unavailable responses; it does not test live service availability. Screenshots in `cache/admin-design-audit/` contain empty password fields. Hosted SMTP delivery and outbound breach-service connectivity require a deployment check.
