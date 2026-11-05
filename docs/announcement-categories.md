# Multiple announcement categories

Create/Edit Announcement uses the existing searchable selection component.
Choose multiple categories, remove individual tags, or clear the selection for
an uncategorized announcement. Existing single-category announcements retain
their category when opened for editing. Validation errors and requested revisions
restore the selected tags.

The first selected category remains in `announcements.category_id` for legacy
readers. All selections are stored in `announcement_categories`, which has a
unique announcement/category key. Server-side normalization rejects malformed
or unknown IDs. Record and category changes are saved atomically. Deleting an
announcement removes its category links through foreign-key cascade.

The admin list, public announcement filters/counts, article labels, and
Campus/Alumni galleries use every selected category without duplicating a post.
Archiving or restoring an announcement preserves its tags. Drafts and approval
requests retain the full category array; reviewers see category names. Existing
authentication, ownership checks, CSRF gates, and publishing permissions apply.
Facebook delivery behavior is unchanged; cached Facebook posts still have their
own independent category control.

Before deploying the updated application, back up the database and run:

```sh
php database/migrate-announcement-categories.php
```

Alternatively, import `database/announcement-categories-migration.sql`. This
additive, rerunnable migration creates the category-link table and backfills
legacy assignments. It does not replace existing categories or announcements.
Without the table, legacy reads/single-category writes still work and attempts
to save multiple categories report the required setup step.

Deploy the announcement category helper, form and action changes, shared admin
functions, list/approval changes, public feed/article changes, and the updated
Campus Events feed together. No new application dependencies are required.
To roll back application code, retain the new table and category assignments;
older readers can continue using the primary category.

Verification:

```sh
php tests/announcement-categories-test.php
php tests/announcement-view-test.php
php tests/approval-view-test.php
php tests/alumni-recent-events-test.php
```

The category integration test checks create/read/edit/clear, secondary-category
filters, Alumni feed inclusion, archive preservation, readable approval fields,
invalid IDs, and cascade cleanup inside a transaction that is rolled back.
