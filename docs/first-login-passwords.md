# Temporary passwords and first login

## Setup

Back up the database, then import `sql/first-login-password-migration.sql` **once**. It adds `must_change_password` (default 0), `password_changed_at` and `credential_version`. Existing accounts keep their passwords and do not receive a forced change. New user creation fails safely if these fields are missing. Install `sql/rbac-migration.sql` too if the three-role access system is not installed. No migration runs automatically.

Deploy `includes/first-login-password.php`, `includes/admin-rbac.php`, `admin/admin-functions.php`, `admin/users.php`, `admin/change-password.php`, and both authentication files together. Use HTTPS and protect session storage and the database. The recovery flow reuses PHPMailer; follow the authentication security setup linked below.

## Super Admin experience

Open Users, enter name/email and select Super Admin, Content Editor or The Mirror. There is no password input. A random 12-character temporary password is generated using PHP's cryptographically secure `random_int()`, with uppercase, lowercase, digit and symbol classes. Easily confused characters such as I, O, l, 0 and 1 are omitted. The database receives only `password_hash()` output and a required-change flag.

The successful creation response shows a modal with **Copy Password**. Share it privately with the account owner. The password exists only in that request's memory and the authorized response; it is not saved in sessions, logs, URL parameters or a retrieval endpoint. The creation page sends `Cache-Control: no-store` and removes the temporary value from the DOM when the modal closes. Later GET requests cannot show it again. A failed create never reveals a password for an existing account. Passwords are not emailed.

This is intentionally a one-time display. It cannot prevent a recipient from saving/copying their own displayed password. Browser clipboard contents are controlled by the administrator's device.

## New user experience

The existing login and OTP flow remains. After authentication (including remembered/trusted devices), a flagged account is directed to Change Password. The screen has current temporary password, new password and confirmation fields, a show/hide checkbox, validation messages and Sign out. It has no admin navigation.

The new password must contain at least 12 Unicode characters, fit within 72 bytes, and contain no control characters. Unique passphrases and special characters are supported. Common and compromised passwords are rejected. Confirmation must match and the password must differ from the temporary password. Incorrect current-password attempts use the account lockout settings.

After a successful change, the user sees a success message and a Continue link to the role's home: The Mirror goes to Announcements, other roles to Dashboard. Future authenticated logins go to the normal role home.

## Backend enforcement

- Every authenticated admin request refreshes role, active state and the required-change flag from the database. Missing/forged browser flags cannot grant access. Navigation requests redirect to Change Password; privileged POST/action requests are rejected before any handler can write.
- All feature permission checks return false while a password change is required. Change Password and Sign out remain available.
- The current password is checked with `password_verify()` under a row lock. A successful update atomically replaces its hash, clears the required flag, stamps the change time, increments the credential version, and revokes stored OTP and trusted-device tokens. The old temporary password then fails verification.
- The current session ID and CSRF token rotate. Other versioned authenticated sessions are rejected on their next request. Legacy sessions without a credential version must sign in again.
- Session cookies use HttpOnly and SameSite=Lax. Secure is enabled for HTTPS requests or an HTTPS backend APP_URL; configure that URL correctly behind a TLS-terminating proxy. Lax preserves the existing Facebook OAuth callback flow.
- The trusted-device, OTP API and OTP form authentication paths all carry the database flag and credential version into the session. Temporary accounts are not issued new remembered-device tokens before completion.
- Password creation/change actions are audited without secret values. A failed change leaves the required flag set and the previous hash intact; a completed change is recorded as `PASSWORD_CHANGED`.

## Verification

Run `node tests/rbac-http-test.cjs` with Docker and Chrome available. It creates a uniquely named isolated database and temporary multi-worker PHP server, seeds synthetic users/OTP/trusted-device tokens, tests the real handlers for all three roles, and removes its test resources afterward. It sends only synthetic emails to a local SMTP test receiver, makes no Facebook requests and does not change existing site accounts/content.

Coverage includes generated password classes, one-time output, hash-only storage, all three login paths, direct URL/API bypass attempts, current/mismatch/strength/reuse/CSRF failures, session and remember-token revocation, invalidation of the temporary password, and subsequent normal login. Browser checks exercise the creation confirmation, Copy Password, DOM scrubbing, show/hide controls and mobile layout. Screenshot output contains an empty password form, not generated credentials.

Rollback should preserve the added columns and credential hashes. Removing the backend gate while accounts still have `must_change_password=1` would let them continue using temporary passwords; plan account remediation before reverting the code. Do not reset existing account passwords or remove records as part of rollout.

Password policy and recovery were expanded: see [authentication-security.md](authentication-security.md). User-chosen passwords now accept long passphrases and are checked for compromised passwords. The auth-security migration is also required.
