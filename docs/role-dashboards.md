# Role dashboards

All three admin roles now land on `/admin/admin-dash.php` after login and the required first-login password change. The Mirror also has a Dashboard sidebar link. Mandatory password changes remain enforced before dashboard access.

The existing layout is reused. Content Editor retains website content totals and analytics permitted by its role, plus content editing shortcuts. The Mirror sees only its own announcement/news counts and recent posts, and cannot access events, website editing, analytics, users or activity logs. Its Announcements list still allows read-only browsing of other authors as previously requested.

Both roles see their own pending-submission count and recent submissions, with links to My Submissions for drafts, approval status and feedback. Counts and records are filtered by the session user ID in prepared SQL. User IDs from query strings are not used. Quick actions reflect existing permissions. Super Admin keeps the existing dashboard and activity panel; approval shortcuts lead to the actual submissions queue.

Deploy `includes/role-dashboard.php`, updated `admin/admin-dash.php`, `includes/admin-rbac.php`, `components/header-admin.php`, `admin/change-password.php`, and `includes/admin-onboarding.php` together. No new database migration is required. The existing submissions schema supplies workflow data. Existing onboarding completion/skip choices are preserved; replay Help / User Guide to see the Dashboard instructions.

Tests: `node tests/rbac-http-test.cjs` covers role routes, own statistics and proposals, forbidden shortcuts, authentication and approvals in an isolated database. `node tests/onboarding-http-test.cjs` covers dashboard onboarding and responsive widths for all roles.
