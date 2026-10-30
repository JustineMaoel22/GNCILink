# Word calendar imports

Events → **Import Calendar** accepts `.docx` files up to 5 MB and 1,500 activities per upload. The existing Events editor, categories, category colors, public calendar and role permissions are reused. No AI service receives the document.

## Deployment

Department group choices (**All Basic Education**, **All College**) require the additive `php database/migrate-event-audience-groups.php` migration after a database backup. It adds `events.audience_group` with default `all`, preserving existing audiences. Deploy `includes/event-audience.php`, `includes/calendar-import.php`, `assets/js/calendar-import.js`, `admin/events.php`, `admin/partials/calendar-import-review.php`, `components/calendar-filters.php` and `components/bulletin-board.php` together. Choices remain disabled until the column exists. Each group uses one event; All College excludes Graduate School. Roll back group use by first reassigning group events to specific departments or all departments; do not drop the column while group events remain.

Back up the intended database, then run the additive, repeatable migration:

```sh
php database/migrate-calendar-import.php
```

Alternatively, apply `database/calendar-import-migration.sql` once through phpMyAdmin. Check first whether `events.description` or the three `calendar_import_*` tables already exist; the raw SQL is for a first installation. Existing events are preserved. The feature shows a setup message until its storage exists.

Hosting needs PHP 8.2+, PDO MySQL, mbstring, Fileinfo, DOM/libxml and ZIP (`ZipArchive`). The Dockerfile installs ZIP; rebuild and recreate the web container when deploying locally. Approval storage from the existing `sql/rbac-migration.sql` must already be configured. No Composer package or command-line Word converter is needed. The reader uses PHP's [libzip interface](https://www.php.net/manual/en/class.ziparchive.php) and [DOM XML parser](https://www.php.net/manual/en/domdocument.loadxml.php).

Deploy `admin/events.php`, `admin/submissions.php`, both new import partials, the review partial, `assets/js/calendar-import.js`, `includes/calendar-docx.php`, `includes/calendar-import.php`, `includes/content-submissions.php`, and `event.php` together.

## Preparing the document

Use a table headed **Date**, **Activity**, and optionally **Description**, **Time** and **Location**. Event / Event Title, Activities / Title, Details / Remarks, Schedule and Venue / Place are also recognized. Location is optional and editable in every preview row (up to 500 characters). Imported locations appear in the existing Events editor and public calendar event details. Revised documents without a location column preserve the existing event location. Dates can include:

- `January 15, 2026`, `Jan. 15`, `15 January 2026` or `2026-01-15`.
- `Feb. 9–13`, `Dec. 21–Jan. 3`, or `2026-02-09 - 2026-02-13`.
- A month heading, such as `July 2026`, followed by day numbers in the table. A new year is inferred when month headings roll from December to January.
- Times such as `9:00 am - 10:30 am` or `09:00–10:30`. Explicit times at the end of date/title cells are recognized too.

Each separate paragraph/line in an Activity cell becomes an event on that date. A blank Date cell continues the previous table date. Paragraph calendars may separate their date and activity with a tab, colon, or spaced dash. Header/footer text, scanned images, floating text boxes, nested tables, complex merged layouts, discontiguous date lists and ambiguous numeric dates may require manual correction. Document text that cannot be confidently parsed stays in the preview as a warning; the reader never silently assigns an exact date to ambiguous numeric dates. The administrator supplies a starting year for dates that omit it; explicit years take precedence. Use explicit years when a school-year document has no month headings.

## Review and publication

Program dropdowns include College and Graduate School programs only. For Basic Education activities, choose the appropriate department and leave Program on all programs.

Department detection matches existing department names, their compact slugs and acronyms derived from their names in activity titles/descriptions. For example, **CBA Days** on **Nov. 1–3** selects **College of Business Administration**, retains the date range and leaves Program on all programs. Shared acronyms or multiple matching departments are flagged for review. Unrecognized abbreviations need manual assignment; administrators can correct every detected assignment. Revised uploads preserve existing department/program corrections.

Use **Edit** on an activity to choose its Department and optional Program. The bulk toolbar applies these assignments to selected activities across pages; its button shows the affected selection count. Program choices are limited to the selected department. All departments / all programs keeps an activity school-wide. These assignments are separate from the event-type category (Holiday, School Event, etc.), survive revised imports and appear in approval review. Department targeting uses the existing `events.dept_id` field; if unavailable, run `php database/migrate-event-departments.php`. No additional import schema change is required.

1. **Upload document:** choose a new calendar and name/year, or select the original calendar for a revision. Upload and extract. The filename and extraction status remain visible.
2. **Review activities:** use the compact table (20 rows per page by default; 10/20/50/100 available). Search by title; combine month, start year, category, status and warning filters. Sort by date, title or category. Search and filters cover the entire import. Change categories directly or open **Edit** for titles, locations, dates, times, descriptions, source text and existing-event targets.
3. **Verify & approve:** checkbox selection is separate from approval. Select visible rows or all filtered matches, assign categories, approve or exclude in bulk. **Approve all without warnings** skips exclusions, possible removals and unresolved warnings. Bulk approval skips critical errors and warnings that have not been reviewed. In the expanded editor, **Mark reviewed** acknowledges corrected details without approving; **Approve activity** explicitly approves a valid row. Editing approved details returns that row to pending. Only approved rows are sent to the server; internal duplicate event/date or update-target selections are blocked.
4. **Confirm import:** review totals and counts of additions, updates and archives. Pending and excluded rows are left out. Content Editors submit one batch to the existing Approvals queue. Super Admin imports are saved as drafts and must be published separately through Events. The workspace never automatically publishes events. Approvals shows the selected event details before applying an editor's batch.

The summary and footer actions stay visible while scrolling. On mobile, Filters and Bulk actions expand on demand, and compact activity cards keep controls within the screen. Edits, categories, selections and approval statuses remain in memory across pages, filters and steps. Closing or replacing a review prompts before discarding it; leaving or refreshing the page triggers the browser's unsaved-change warning. Review drafts are not persisted across reloads, so no Save Draft control is shown for an unfinished workspace. Confirming a Super Admin import persists its approved activities as actual event drafts.

Drafts and imported events appear in the normal Events list, which now includes all events so hundreds of imported activities remain editable. Manually creating, editing, deleting and publishing events still uses the existing workflow. Descriptions are also editable there and appear on the public event detail page. The existing calendar queries display only published events, including every date of a multi-day span.

## Archive or delete an imported calendar

Open **Events → Import Calendar → Manage imported calendars**, select a calendar, and choose **Archive Calendar** or **Delete Calendar**. The preview lists all affected imported events. Tick the review confirmation before applying the action. Content Editor requests remain pending until Super Admin approves them through the existing Approvals page.

Archive marks the imported calendar and all its events as archived, hiding those events while retaining their records. Archived calendars remain listed in management and can be deleted; they cannot receive revised-document imports. Deletion permanently removes the linked event records and removes the calendar from the selection lists. Calendar metadata, batch snapshots and audit logs are retained as history. Manually created events and events belonging to other imported calendars are preserved. There is no automatic undo for deletion; use Archive to retain records. Concurrent event changes or new imports invalidate the operation's preview rather than deleting or hiding content that was not reviewed.

Existing import installations must rerun `php database/migrate-calendar-import.php` to add `calendar_import_series.status` and `calendar_import_batches.calendar_action`. The migration is additive and does not archive or delete any existing events. Code rollback should leave these fields in place.

## Revised calendars

Select the original calendar when uploading a revision. A unique matching title proposes an update; a unique same-date match proposes a possible rename. Ambiguous matches are flagged for review, with a selector restricted to events from that imported calendar. Existing category selections are kept even when automatic classification suggests something different. Administrators can explicitly change them in the preview.

Unmatched existing activities are labelled **Possibly removed**, excluded by default. Approving them archives the event after approval; it does not delete it. New activities are inserted only after confirmation/approval. Exact title/start-time duplicates are blocked across manual and imported records. Similar titles or dates produce warnings for manual review. Manually created events cannot be chosen as update targets.

A batch records the original calendar revision and event snapshots. Concurrent edits or another applied revision cause an error instead of overwriting newer content. Re-upload to compare fresh data. Confirmation is idempotent. Rejected imports can be re-uploaded as a new proposal from the same original calendar; the rejection reason remains in My Submissions. Cancellation discards the preview without changing events; unused previews expire after one day. Upload files are read from PHP temporary storage and are not retained. Preview/source text and reviewed batch data remain in the database for revision and approval review; include them in the site's normal database retention policy.

## Security and rollback

The parser validates extension, upload origin, MIME, ZIP structure and size. It limits entries and expanded sizes, rejects macros/embedded programs and XML entities/DTDs, reads only the document XML with network access disabled, and never extracts archive paths to disk. Endpoints require login, event create/edit permissions, ownership of the preview and the existing Events CSRF token. SQL uses prepared statements; previews render document content as text. Publication revalidates category IDs, snapshots and duplicate constraints inside a transaction. Import, individual updates, approval and publication use the existing activity logs.

For code rollback, restore the prior files and leave the additive storage in place. Existing imported events remain normal event records unless deliberately deleted through the reviewed calendar deletion action. No automatic database rollback is provided.

## Verification

```sh
docker compose exec -T --user www-data web php tests/calendar-docx-test.php
docker compose exec -T --user www-data web php tests/calendar-import-http-test.php
node tests/calendar-import-browser-test.cjs
```

The HTTP test requires active Super Admin and Content Editor accounts and tests actual upload, isolation, draft persistence, category preservation, revisions, public rendering, approval, whole-calendar archiving/deletion, stale-preview rejection and protection of manual events. It removes its temporary calendar/event/submission fixtures; audit records remain. The browser test uses a 527-activity extraction fixture to verify pagination, combined global filters, sort, page changes, bulk category edits, approval/exclusion, invalid-row rejection, final confirmation data, safe rendering and discard protection on desktop/mobile. It also checks the existing archive/delete review flow without saving events. Preview screenshots are written under `cache/calendar-import-audit/`.
