# Admin access and content approvals

## Roles

| Capability | Super Admin | Content Editor | The Mirror |
|---|---|---|---|
| News/announcements | Direct manage/publish | Create/edit/delete proposals | Create/edit own proposals |
| Website editor, calendar, settings, other content tools | Direct manage | Access and submit proposed writes | No access |
| Approve/reject | All users | No | No |
| Submission status/reason | All submissions | Own submissions | Own submissions |
| Create accounts / assign roles | Yes | No | No |
| All-user activity logs | Yes | No | No |

Only Super Admin can publish directly, approve, reject or manage accounts/roles. The Mirror is limited to News, Announcements, its own submission status, and the mandatory password-change/logout routes. Content Editor receives the existing content tools, but its content writes are intercepted for review. Public site navigation is not an admin privilege. Facebook publishing still requires `publish_announcement`, so only Super Admin can send outbound posts.

Saving a role change in Users requires the acting Super Admin's current password
on every save. The confirmation names the account and selected role. The server
verifies the password, fresh account/credential status and rate limit inside the
existing role-change transaction; missing or incorrect passwords leave roles
unchanged. Self-demotion and last-active-Super-Admin protection remain in place.
Passwords are cleared from the dialog on cancel/close and are never stored in a
confirmation token or session. `tests/user-roles-test.php` uses temporary tables
to verify these protections without changing real accounts.

Deleting news, announcements or events also requires the acting user's current
password in the existing deletion dialog. Confirmation is checked before either
deleting content or queuing a Content Editor deletion proposal. Passwords are
removed from the POST data before approval payloads are stored. Approval retains
the existing reviewer password confirmation and one-use execution worker; it
never reuses or stores the submitting editor's password. Repeated incorrect
confirmation attempts are rate limited and audited. Ownership, permissions and
CSRF checks remain in place, including Events' database-backed CSRF tokens.

News, announcements and events now also provide selection checkboxes, **Select
all on this page**, a selected count and **Delete selected**. Selection covers
only loaded, visible records with deletion permission; pagination resets it and
filtering removes hidden rows from the selection. Facebook feed posts are not
included. Batches contain at most 500 distinct records and require one current
password confirmation for the whole batch.

The server validates and locks every selected record before deletion. A missing
record, insufficient permission or audit/database failure rolls back the entire
batch. Individual delete actions retain their existing behavior. Content Editor
batches become one approval request containing the exact selected IDs; approval
revalidates the selection before applying it. Events always dispatches by its
`ajax_action`, preventing a conflicting `action` field from bypassing password or
approval checks. No database migration is needed.

Verification: `tests/content-bulk-delete-test.php` deletes temporary rows only and
checks exact selection, authorization, missing records and rollback.
`tests/content-bulk-actions-browser-test.cjs` checks Select all, mixed selection,
hidden rows, selected amounts, password handling and desktop/mobile dialogs for
all three lists. Browser deletion requests are intercepted or rejected; no real
website records are deleted by the tests.
Local verification passed: 30 bulk-deletion checks, 25 existing deletion-password
checks, 27 publishing-confirmation checks, and browser checks across all three
lists at desktop and mobile widths. PHP/JavaScript syntax and whitespace checks
also passed.

Existing `Content Admin` records map to Content Editor permissions, and `Office Staff` records map to The Mirror permissions. Their database values are retained to preserve records; new assignments offer only the three current roles. A Super Admin can explicitly reassign legacy accounts. Roles/account status refresh on each request so an existing session cannot retain an old elevated role.

## Migration and rollout

Back up and verify restore, then import `sql/rbac-migration.sql`. It expands the role enum without deleting/converting users and adds `rbac_submissions` plus chunked payload storage. Install `sql/first-login-password-migration.sql` for automatic temporary passwords and first-login changes. Neither script runs automatically. Existing published content and account IDs remain intact. Pending editor writes fail closed if submission storage is missing.

Deploy the new RBAC/submission helpers, guarded admin functions and authentication, navigation, Users/Logs/Submissions pages, content-editor draft handler and JS together. Compatibility redirects retain the previously linked `/admin/approvals/`, `/admin/users/`, `/admin/logs/` and `/admin/dashboard/` paths.

## Submission workflow

Non-Super Admin POSTs to allowlisted content handlers become private proposals instead of calling live persistence. Staged POST values and validated uploads are stored as chunked payloads. Draft intent stays private; other proposals are Pending. Ownership is checked before staging news/announcement operations. The Mirror cannot edit another author or submit delete/archive/pin actions. Requests cannot implicitly publish to Facebook; captured publishing destinations are restricted to website only.

The My Submissions screen shows each author's Draft/Pending/Approved/Rejected status and reason. Super Admin sees all users' proposals, escaped proposed text, submitted media and review controls. Rejection requires a reason. Drafts/rejected requests can be revised and resubmitted; news/announcement revisions reopen the original create/edit form with the proposed values. Previously staged uploads are retained unless replaced. Revisions are checked when reviewing, and reviewed requests cannot run twice. Important operations are written to activity logs without payload secrets.

The visual editor uses its existing serializable private snapshots, including media, for whole-page proposals. Editors submit the checkpoint without running publish callbacks. Super Admin opens that immutable saved canvas and uses **Approve and publish**, which applies the existing adapters/validation and records approval after success. A snapshot never replaces the public page just by being opened. Preview routes are limited to public website paths. The individual page-text review workflow remains available to Super Admin in Content Editor → Review.

## Applying ordinary form proposals

The reviewer confirms before application. The server marks the request Processing and executes the allowlisted original handler using a random, one-use, short-lived reviewer session on a loopback HTTP request. The existing authorization, CSRF, input, upload and persistence checks run again; staged requests do not execute arbitrary PHP or SQL. The worker credential is never returned to a browser, and its session is deleted after a completed response. Newly approved news/announcements/events retain the original submitter as their owner.

PHP cURL and a reachable loopback origin are required. Default `RBAC_INTERNAL_ORIGIN=http://127.0.0.1` fits the local Apache container. If hosting uses another internal port/scheme, configure a loopback HTTP(S) origin in the backend; arbitrary external origins and redirect-following are rejected. A multi-worker server is required: a single-worker PHP development server cannot synchronously call itself. Shared hosting must support loopback calls/session storage across workers. Validate this before rollout.

Successful handlers record Approved. Unconfirmed responses/timeouts become Uncertain (or remain Processing after a crash) and cannot be retried automatically. Check actual content before submitting a replacement request. Authentication failures can be Failed. Original handlers may perform several writes or file operations; application does not promise an atomic rollback across all legacy handlers. Visual-page publishing likewise uses the existing multi-request adapter. Super Admin approval is required before these operations begin, and errors remain visible for review.

## Existing pending content

Existing page-section submissions and newer proposals appear together in Approvals, sharing search, filters, counts and pagination. Page-text reviews show current and proposed text in the same review dialog; Super Admin can approve with password confirmation or request changes with feedback. Section decisions lock the current row and verify its content version, preventing stale or repeated decisions. My Submissions only shows the signed-in author's items. Old Content Editor review links redirect to the combined pending queue. Dashboard totals and the Approvals badge include pending page text. Existing records retain their original storage and IDs, distinguished by source; no migration or automatic publishing is performed. Section records expose their latest review state, with audit confirmation required before labelling a cleared section approved. Existing pending news/announcements/events can still be opened in their editors and published or archived there. The database proposal history remains separate from current public content.

## Security and testing

The request boundary runs at session initialization, including handlers that previously only checked `isLoggedIn()`. It enforces role-specific routes, fresh account status/role, mandatory password change, mutation methods and CSRF before staging. Menus reflect the same policy; hiding a menu is not the authorization control. Own-submission/media reads are checked separately. Original source permalinks, Facebook credentials, public links and public templates are retained.

`node tests/rbac-http-test.cjs` uses a separate synthetic database and temporary multi-worker Docker server, exercises real approval replay and all login paths, and removes test resources afterward. It checks menus/routes, own content, forged publishing, pending isolation, rejection/reason/resubmission, single-use approval, original author ownership, snapshots, user creation/role changes, CSRF, audit events and mandatory passwords. It sends no email or Meta requests. No production migration is executed by tests.

Back up the submission tables for review history. Before rollback, resolve pending proposals and first-login requirements; retain the new role values/tables unless a separate, explicitly planned data migration is approved. No destructive rollback SQL is supplied.
