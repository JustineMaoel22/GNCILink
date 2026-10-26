# Account settings and password changes

All three roles can open Settings from the sidebar or the topbar gear, then choose Change Password under Account Security. The Mirror sees only account security. Existing website-settings permissions and approval behavior for Content Editor and Super Admin remain unchanged; The Mirror cannot submit website-settings updates.

`admin/account-password.php` requires an authenticated account that has completed the mandatory password-change step. POST requests require CSRF and use the session user ID, ignoring any submitted account ID. The current password is verified under an account row lock. Validation, compromised-password checks, account lockout, hashing, credential-version invalidation and reset-token removal reuse the first-login password helper. Successful changes rotate the current session ID and CSRF token, retain the current session, and revoke other sessions and all stored browser trust. Passwords are never rendered back into the form or added to logs. The original mandatory first-login page and gate remain in place.

Deploy these files together:

- `admin/settings.php` and new `admin/account-password.php`
- `includes/first-login-password.php` and `includes/admin-rbac.php`
- `components/header-admin.php`
- `includes/admin-onboarding.php` for updated Settings guidance

No new migration is required. The existing first-login password and authentication-security schemas must already be installed.

Verification: `node tests/rbac-http-test.cjs` runs the isolated authentication/permissions suite, including `tests/account-password-http.cjs`. It covers all roles, own-account targeting, current password, CSRF, confirmation, strength and reuse rejection, session rotation/revocation and remembered-token revocation. Browser checks exercise mobile/desktop form bounds and show-password controls. No real account or production database is changed.
