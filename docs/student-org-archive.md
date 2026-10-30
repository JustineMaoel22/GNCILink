Student organization archives
=============================

Run `php database/migrate-student-org-archive.php` once before deploying these changes.
The repeatable migration adds a nullable `archived_at` column without changing any
existing content, media, order, or publication status. Fresh installations also
include this column through `database/migrate-student-organizations.php`.

For hosting with phpMyAdmin only, check `SHOW COLUMNS FROM student_organizations LIKE 'archived_at';` first. If the column is absent, apply this additive statement to the intended database after taking a backup:

```sql
ALTER TABLE student_organizations ADD COLUMN archived_at DATETIME NULL DEFAULT NULL;
```

Archive-only and restore-only saves update only `archived_at`. Existing text,
publication status, image references and gallery rows are retained without running
the content/gallery upload path. Requests that also edit content or upload/remove
images still use the full existing validation. No files are needed to archive or
restore an organization. Authentication, CSRF, approval and audit logging are unchanged.

Missing archive storage now returns an explicit setup message (HTTP 409) naming
the migration, rather than the unrelated generic upload message. Other unexpected
archive/restore failures identify the operation; database details remain in server
logs. The application does not run migrations automatically on a hosted database.

Select an organization card and choose **Archive organization**, or use page
options on its detail page. The archive stays pending in the preview. **Save draft**
keeps it private across reloads; **Publish** applies it. Selecting Restore before
publishing cancels a pending archive.

The **Archived** link above the admin directory lists archived organizations.
Choose **Restore organization**, then Publish to restore it. Published organizations
become public again; unpublished organizations remain unpublished. Archiving never
deletes organization text, images, gallery entries, IDs, or URLs. Visitors cannot
open archived detail pages or access the archived directory through query parameters.

Rollback: revert the application changes and retain the added column. Dropping it
would discard archive history and requires a separate reviewed database operation.

Verification:

```sh
docker compose exec -T --user www-data web php tests/student-org-archive-handler-test.php legacy
docker compose exec -T --user www-data web php tests/student-org-archive-handler-test.php current
docker compose exec -T --user www-data web php tests/student-org-archive-handler-test.php blocked
docker compose exec -T --user www-data web php tests/student-org-archive-approval-test.php
node tests/student-org-archive-browser-test.cjs
```

Handler tests use connection-local temporary tables, including legacy content,
eleven existing gallery references and a simulated database constraint failure.
The approval test creates and removes one temporary organization and its approval
submissions, retaining audit history and all existing media. Hosting schema
compatibility is simulated locally; production deployment still needs its own check.
