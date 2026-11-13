# Multiple Facebook post categories

Facebook Posts uses the existing searchable checkbox selector for manual categories.
Automatic mode keeps the existing caption classifier; an empty manual selection
means Uncategorized. Category edits affect the website's cached post only and do
not send changes to Facebook. Existing Super Admin permission and CSRF checks apply.

Deploy the category helper, admin page/handler/scripts, public feed changes, and
migration together. Run `php database/migrate-facebook-post-categories.php` once
on each deployment. This adds `fb_post_categories` and backfills existing manual
assignments without changing the cache's primary category or automatic/manual mode.
The migration can be rerun safely. It has been applied to the local Docker database.

Manual saves update the primary category and child tags in one transaction.
The primary category remains available to older readers. Automatic saves clear
manual child rows. Synchronization already preserves manually locked primary
categories; it leaves the child tags intact. Category filters, the visitor feed,
and Campus/Alumni galleries match any selected category without duplicating posts.
Facebook tags corresponding to News categories also participate in the public
News tab, its named category filters and sidebar counts. Facebook posts retain
their existing Announcement visibility; All shows each cached post once.
Before the migration, legacy single-category reads and saves continue to work;
multiple selections return a setup error rather than silently discarding tags.

To roll back application code, leave the additive table in place. Older code can
read the primary category. Additional tags remain stored for a later upgrade.
Do not remove the table without backing up its additional assignments.

Verification:

- `docker compose exec -T web php tests/facebook-post-categories-test.php`
- `docker compose exec -T web php tests/facebook-posts-test.php`
- `node tests/facebook-events-search-browser-test.cjs`

PHP checks shadow the cache, child tags and activity records with temporary tables.
Browser save/pin responses are mocked; no real category or pin changes are made.
