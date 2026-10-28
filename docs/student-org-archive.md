Student organization archives
=============================

Run `php database/migrate-student-org-archive.php` once before deploying these changes.
The repeatable migration adds a nullable `archived_at` column without changing any
existing content, media, order, or publication status. Fresh installations also
include this column through `database/migrate-student-organizations.php`.

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
