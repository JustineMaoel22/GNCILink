# Student Organizations

Run `php database/migrate-student-organizations.php` once on each environment.
The additive migration can be repeated and does not seed example organizations
or categories, overwrite records, or modify existing CMS tables.

Existing installations also need `php database/migrate-student-org-archive.php`
before using organization archiving. Public pages and ordinary organization
editing remain compatible with databases that do not yet have `archived_at`;
archive requests report the required migration instead of failing with a SQL error.
Deploy the updated `admin/student-organizations.php` for this compatibility fix.
No database changes are performed by public page requests.

Open **Content Editor → Student Organizations Management**. Use **Add
Organization** in the top bar or the dashed card on the page. Type the name and
summary directly on the new card, and click its cover/logo to upload pictures.
Use **Edit organization page** to save the card as unpublished and open its full
visual page. Edit the overview, vision, mission, Facebook URL, and gallery there.
Click a card or its text for category, ordering, deletion, and Save unpublished
controls. Use **Manage categories** next to the filters for category options.
New organizations remain unpublished until Publish is chosen. The existing
Preview button previews unsaved visual edits. The shared completeness warning
offers Keep Editing and Publish Anyway. Names remain structural requirements.

Below the directory, the Leadership and Capacity Building section includes an
editable training banner and topics, retreat text and photo, and closing banner.
Click the text or an image area in the Visual Editor and Publish to save it.
The image toolbar can replace or reset each photo. Bundled campus photos are
defaults; upload the actual training/retreat photos through the editor.
Text and media references use `student_org_page_content`; uploaded images use
`media_library`. No additional migration is needed.

All organization editing takes place on the visual canvas and small contextual
popovers; the former management form drawer is no longer mounted.
Deleting categories keeps organizations as Uncategorized. Deleting an
organization removes its gallery relationships; shared media is retained until
no CMS record references it. Replacing photos uses existing MIME/size-validated
uploads and media-library references. Each image may be at most 5 MB, and each
organization may have up to ten gallery images (the server's total request
limit also applies).

Public listing and detail routes use the existing `student-org.php` and
`dynamic-org.php?slug=…` files. Navigation points to the listing. Filtering is
database-driven, resets the page selection, and paginates six published records
per page. All saved categories appear in both the editor and the public filters,
including empty categories. Only the editor shows unpublished organizations.
Missing or unpublished detail routes
return HTTP 404 to visitors. Draft previews and metadata require the existing
`manage_page_content` permission. Slugs stay stable when names change.

All mutation requests are POST-only, check administrator permissions and CSRF,
validate fields and relationships, and use prepared SQL. Media changes are
cleaned up if a save fails. The four `student_org_*`/`student_organizations`
tables are the sole source of organization data. No JSON configuration or
hardcoded organization/category lists are used.

Validation:

- `php tests/student-org-hosting-schema-test.php legacy` and the same command
  with `current` check both schema versions using connection-local temporary
  MySQL tables. Persistent records are unchanged.

- `php tests/student-organizations-test.php` exercises database operations in a
  transaction and rolls back its changes.
- `php tests/student-organizations-render-test.php` checks the Content Editor.
- `node tests/student-organizations-browser-test.cjs` runs the local site in
  headless Chrome, exercises real uploads and publication, and removes its
  temporary fixtures/uploads/session in `finally`. It requires Docker Compose
  and the local web service on port 8080.
