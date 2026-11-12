# Announcement and News audiences

The audience controls share department/program relationships from the CMS. Selecting a new department selects its programs by default. Removing program checkboxes never removes the department itself. Adding or removing another department preserves the remaining departments' program selections.

For each selected department, zero selected programs means the whole department; a subset means only that subset. Selecting every current program also includes the whole department. A department-wide choice does not extend to other departments. With no departments selected, program-only selections restrict the audience to those programs. Clearing both fields retains the existing default: school-wide Announcements and untargeted News. Untargeted News remains hidden when visitors choose a department/program filter, as before.

`includes/content-audience.php` provides department-local matching, validation, summaries and News persistence. Announcement selections remain in `announcement_departments` and `announcement_programs`, with their existing legacy mirrors. News uses `news_departments` and `news_programs`. The forms preserve the exact checked selections, including empty lists, through edit/retry and approval requests. The `audience_present` field distinguishes an explicitly cleared audience from older requests that do not edit audience fields. News content and audience changes share one transaction; archive/restore and old requests leave audience assignments untouched.

The visitor News & Announcements feed and its filtered pinned carousel use the saved lists. Program-page announcement bulletins apply the same per-department interpretation, retaining legacy field precedence. Audiences control placement/filtering of published content; this does not add authentication restrictions to published article URLs. Facebook posting remains a separate destination and does not inherit website audience restrictions.

## Deployment

Back up the database, then run `php database/migrate-news-audience.php` or import `database/news-audience.sql` using the hosting database tool. The migration only adds the two News audience tables with cascading News foreign keys. It does not update existing articles, statuses, departments or programs. Deploy the changed PHP/JS/CSS files together. Before migration, News keeps its existing visibility and shows a setup note instead of editable audience fields; forged audience saves are rejected. No production migration is run by the application.

For code rollback, retain the new tables and their data, and restore the previous code. Old code ignores the News audience tables and reverts to its old untargeted News behavior; account for that visibility change before rolling back. Do not drop the tables to roll back code.

## Verification

- `php tests/content-audience-test.php`: exact audience round trips, zero/all/subset selections, mixed department scopes, defaults, validation, rollback and program bulletin matching. Temporary local MySQL tables only.
- `php tests/content-audience-handler-test.php all`: real create/edit/clear handlers for both content types, invalid selections, CSRF and permission rejection. Temporary tables only; no Facebook requests.
- `node tests/content-audience-admin-browser-test.cjs`: actual create/edit controls, defaults, independent selections, submitted values, reset and mobile/desktop layout. No content submitted.
- `node tests/multi-audience-departments-browser-test.cjs`: shared checkbox/touch/keyboard behavior and independent department program selections.
- Existing public bulletin, visitor search, admin background-search and publishing-transaction tests remain applicable.
