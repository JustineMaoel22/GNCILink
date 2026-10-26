# Password-confirmed user deletion

Super Admin can select Delete in Admin → Users. The confirmation dialog identifies the account and requires the acting Super Admin's current password. The target user's password cannot authorize deletion. Cancel and Escape close the dialog and clear the password input. Passwords are not rendered again on errors or written to logs.

The server requires authentication, active Super Admin status, delete-user permission, matching session credential version, CSRF, current-password verification and an account that is not locked. Five deletion-password attempts per 15 minutes are allowed per acting account. Self-deletion and deletion of the last active Super Admin are blocked.

Deletion runs in a transaction. Existing content is retained through nullable author relationships. Unexpected cascade relationships with records belonging to the target account block deletion rather than destroying related content. Recovery tokens and optional onboarding/draft records are removed. Unapproved submissions are rejected so they cannot later publish under a deleted account. Approved content and audit history remain. Existing sessions are denied on their next request, and password/trusted-device authentication cannot find the deleted account.

Deploy together:

- Updated `admin/users.php`
- New `includes/user-deletion.php`
- New `assets/js/user-deletion.js`

No migration is required. Existing authentication and approval schemas must already be installed. No real accounts are deleted during implementation or testing.

Verification: `node tests/user-deletion-http-test.cjs` creates synthetic accounts in an isolated database. It tests required/incorrect passwords, target-password rejection, CSRF, other roles, anonymous requests, self-deletion, rate limiting, unsafe cascading records, preservation of published content, rejected pending submissions, removed recovery tokens, audit logging and revoked access. Browser tests cover the dialog, cancellation, password clearing and mobile/desktop bounds. Test resources are cleaned up afterward.
