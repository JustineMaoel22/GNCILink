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

Existing page-section submissions remain in the original text review queue. Existing pending news/announcements/events can still be opened by Super Admin in their original editors and published or archived there; no old content is automatically approved or deleted. New proposals use the shared queue. The database approval history is separate from the current public record, so editing a published entry does not hide or overwrite it while the proposal waits.

## Security and testing

The request boundary runs at session initialization, including handlers that previously only checked `isLoggedIn()`. It enforces role-specific routes, fresh account status/role, mandatory password change, mutation methods and CSRF before staging. Menus reflect the same policy; hiding a menu is not the authorization control. Own-submission/media reads are checked separately. Original source permalinks, Facebook credentials, public links and public templates are retained.

`node tests/rbac-http-test.cjs` uses a separate synthetic database and temporary multi-worker Docker server, exercises real approval replay and all login paths, and removes test resources afterward. It checks menus/routes, own content, forged publishing, pending isolation, rejection/reason/resubmission, single-use approval, original author ownership, snapshots, user creation/role changes, CSRF, audit events and mandatory passwords. It sends no email or Meta requests. No production migration is executed by tests.

Back up the submission tables for review history. Before rollback, resolve pending proposals and first-login requirements; retain the new role values/tables unless a separate, explicitly planned data migration is approved. No destructive rollback SQL is supplied.
