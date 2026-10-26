# Admin onboarding and Help / User Guide

The shared admin sidebar now includes Help / User Guide for Super Admin, Content Editor and The Mirror. It contains instructions for features allowed by the user's current permissions and a button to restart the interactive guide. All three roles use their role-specific Dashboard as their landing page.

The mandatory password-change page welcomes new users. Its Continue link leads to their existing workspace, where the tutorial automatically opens if their account has neither completed nor skipped it. The walkthrough explains the real controls without clicking them, creating records, navigating away or publishing anything. It highlights sidebar links, topbar shortcuts and the current page's create button when present. Next and Back change steps; Finish and Skip save per-user status. Escape is treated as Skip. Progress remains pending if the request fails, and the dialog offers a retry.

Status belongs to the database account, not a browser session. Finishing or skipping suppresses automatic display across later logins and devices. Existing users without a status also receive the guide once when they next open their normal workspace. Role changes update Help and replay instructions without clearing saved status. Embedded editors and accounts that must change passwords do not receive an automatic guide.

## Deployment

Back up the database, then import `sql/admin-onboarding-migration.sql`, or run `php database/migrate-admin-onboarding.php` using a deployment CLI. This creates only `admin_onboarding` and preserves all existing accounts and content. The migration is repeatable and never runs automatically. Without the table, authentication and editing keep working, Help remains available, and automatic onboarding stays disabled.

Deploy the following together:

- `includes/admin-onboarding.php` and the updated `includes/admin-rbac.php`
- `admin/help.php`, `admin/action/onboarding-handler.php`, and the updated `admin/change-password.php`
- `components/admin-onboarding.php`, and the updated shared admin header/footer
- `assets/js/admin-onboarding.js` and `assets/css/admin-onboarding.css`

The handler accepts only authenticated, fully set-up users with a supported role, requires POST and CSRF validation, validates the status, and always uses the session's user ID. A submitted user ID cannot change another account's progress. No role, password, content or publication status is modified.

To roll back, remove the shared component inclusion and Help link and revert the route/page additions. Keep the additive table to preserve recorded choices for a later deployment; no destructive rollback is required.

## Verification

Run `node tests/onboarding-http-test.cjs` with the local Docker services and Chrome available. It creates a temporary isolated database/server, applies the migration there, verifies role-filtered instructions, automatic/manual activation, completion and skip across sessions, CSRF/invalid/anonymous/cross-user protection and the mandatory password gate. Browser checks cover Next, Back, Finish, Skip, saved progress, failed-save retry, highlighting, and desktop/tablet/mobile bounds. Test resources are removed afterward; no real user or production table is changed.
