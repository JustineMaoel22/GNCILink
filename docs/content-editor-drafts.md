# Save Draft in the Content Editor

## Use

Edit the page preview and choose **Save draft** (or Ctrl/Cmd+S). The current admin's pending changes are stored on the server, privately, for that page/subpage. Reopening the same editor restores them, including selected files. Visitors keep seeing published content. Drafts also survive signing out or switching browsers when the same account opens the same page.

**Publish** continues using the existing validation, permission checks and publish handlers. Content Editors retain **Submit for approval** on text pages. Saving a private checkpoint alone does not submit it for review. Successful publication clears the checkpoint; a failed/partial publication retains the remaining working state. **Discard draft** removes the private checkpoint and reloads the published version. Cancel applies to changes made since the last saved checkpoint.

Coverage includes homepage text/slides/media; page sections, history and contact text; admission categories and enrollment steps; administration edits; college/basic/graduate departments and programs; organizations; achievements; sports; tuition/PDF schedules; and the payment guide. Incomplete pending content may be saved privately; publication still validates it.

For program banner/facility media, click a thumbnail in the slide strip, then **Replace slide**. The new file is previewed and can be saved as a draft; Publish uploads it into the same slide slot, preserving its ID, name, description and order. Banner slides accept the existing image/video formats; facilities accept images. Publish or discard pending replacements before using the strip's immediate rename, reorder, add or delete actions.

Program pages no longer render the form sidebar or its More settings button. Their uniform photos use a third strip tab (also opened by Manage uniform photos on the page). The curriculum button opens an upload/remove popup; PDF selections wait for Save Draft/Publish. Page options retains visibility, Core Values, department, color, seal/fallback-banner media, PDF access and deletion. Program names and text remain editable directly on the page. These controls reuse the existing authorized program handlers. The standalone program manager component remains available to any other existing caller.

Save Draft checkpoints the pending changes in the visual page preview. Existing independent **More settings** managers, embedded News/Announcements/Events forms, and actions that already create/delete records immediately retain their existing save behavior. Saving a draft cannot undo an action already committed by those tools. This feature does not convert those actions into staged transactions or add auto-save.

Drafts are private to an account and page route, not shared approval submissions. Review restored changes before publication if another administrator may have updated the live page in the meantime. The revision check prevents two tabs from silently overwriting the same account's saved checkpoint; it is not a lock on live content edited by other administrators.

## Hosting setup

Deploy these files together:

- `admin/content-edit.php`
- `assets/js/content-edit.js`
- `assets/js/editor-drafts.js` (new)
- `admin/action/editor-draft-handler.php` (new)
- `includes/content-editor-drafts.php` (new)

Import `sql/content-editor-drafts-migration.sql` in the hosting database control panel, or run `php database/migrate-content-editor-drafts.php` if PHP CLI is available. Both are idempotent and only add `content_editor_drafts` and `content_editor_draft_chunks`; no existing content table is altered. The migration was applied to the local development database, not the hosted database.

Without the tables, the editor reports that draft storage is unavailable and disables Save Draft; existing publishing still works. Normal hosting upload/post and memory limits apply. The checkpoint limit is 96 MiB of encoded JSON, including media. Oversized or failed saves report an error and keep the in-memory work. Existing per-file/type rules still apply on Publish. Store large media changes in smaller checkpoints if the hosting limits are lower.

The tables contain private JSON/media data, not executable uploads. Files are encoded into the checkpoint and only sent through the normal upload handler when publishing. SQL payloads are split into 256 KiB chunks to support modest MySQL packet limits. Requests require a signed-in editor, page authorization and a valid CSRF token; load responses are private/no-store. Restored rich text is sanitized with the existing editor sanitizer. Draft media is not added to the public media library by Save Draft.

To roll back the UI, restore the previous PHP/JavaScript files together. The additive draft tables may remain for recovery; do not drop them without backing up any drafts admins want to keep.

## Verification

- `docker compose exec -T web php tests/content-editor-drafts-test.php`: chunked storage and large-state round trip, account/page isolation, revisions, stale writes/deletes, discard and malformed state.
- `node tests/content-editor-drafts-browser-test.cjs`: local authenticated browser tests of 18 page routes, save/reload/restore/discard, draft text absent from public responses, selected image/PDF recovery, new organization/achievement/sport recovery, two additional department/admission ordering flows, media deduplication, and anonymous/missing-CSRF rejection. Failed/successful publish responses were stubbed to verify checkpoint retention/cleanup without writing live content. Draft controls were checked at 1440 px and 390 px widths and screenshots inspected.
- `node tests/home-background-editor-test.cjs` and `node tests/history-background-editor-test.cjs`: existing preview/publish/failure behavior remains covered.
- PHP syntax and JavaScript syntax checks run on changed runtime files.

Browser tests use an isolated `draft_test=browser-regression` page-route namespace. They save and discard private drafts; publication responses are intercepted in the publish lifecycle check so no published content is changed. Actual hosted deployment and publication must be checked after installing the migration and files.
