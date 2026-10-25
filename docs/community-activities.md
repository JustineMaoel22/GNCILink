# Community Extension Activities

The existing public page remains `/pages/student%20life/community-activity.php`. Its header markup, colors, typography, spacing and banner artwork are preserved. The banner path is now stored in the database instead of hardcoded in CSS. The activity area beneath it follows the reference's large shared image and numbered green list, stacking on mobile. Activities are display-only on the public page: hovering, focusing or clicking does not change the image or open a dialog.

## Manage content

Open **Content Editor → Community Extension Activities** under Student life. It is also listed in All content. Navigation now links directly to the existing page.

- Edit page headings and introductions inline. Page options manage the banner image and empty-state text.
- Use **Add activity** on the page or toolbar. Enter a title, description and category inline. Categories are freely authored; there is no predefined list.
- Click the large shared picture to upload or replace it. Activities have no individual photo controls. JPEG, PNG, WebP and GIF use the existing image preparation/framing pipeline and a 5 MB saved-image limit, with server MIME and image-signature checks.
- Select activity text or its row to move it earlier/later or delete it. Deletion and order changes remain staged until publication.
- **Save Draft** stores private text, image, category, deletion and order changes in the existing per-account draft system. Reloading restores those changes without modifying the public page.
- **Publish** uses the shared confirmation modal and success message. Missing content is advisory: **Publish Anyway** allows empty activity titles, descriptions, categories and page headings. The handler still validates types, lengths, IDs, uploads and revisions, and publishes atomically. Failed saves retain edits for retry.

No activities, categories or sample photographs are seeded. When the list is empty, its database-managed empty state is shown. A missing shared image uses a neutral unavailable-image state. The collection supports up to 100 activities as a payload safeguard; it is not tied to a fixed number or specific activity names.

The shared photo is stored as `showcase_image` in the existing JSON document and uploaded through the same field name. Older records reuse their first nonempty activity photo, which is retained independently when activities are reordered or removed. Existing per-activity image paths and older staged uploads remain supported for recovery and draft compatibility, without exposing individual photo controls. No migration or destructive cleanup is required.

## Community Impact news

Below the activity showcase, **Community Impact** displays published News articles whose Type is **Community Extension Activity**. In Create News, select this type, add the article text and cover image, and publish using the existing workflow. Draft, pending, archived and unrelated news never appear in this feed. Editing an article's type or publication status updates its inclusion automatically.

The stored category code is `Community Extension` (19 characters), preserving compatibility with the existing `news.category VARCHAR(20)` column. The full label appears in the news editor. No category-table insert or schema migration is needed.

The closing **Join the Mission** banner sits below Community Impact and follows the Student Organization closing banner: dark green photo overlay, gold decorative circles, serif heading and ambient circle motion. Its heading and description are editable inline, and selecting the background provides the existing image replacement and framing controls. Text and background changes use the Community Activities draft/publish workflow. Existing page records receive compatible defaults without a migration; published background paths still come only from stored content or validated uploads.

The section follows the current Campus Events card design: green image overlays, three/two/one responsive columns, six cards per page, hover lift and zoom, staggered/scroll reveals and reduced-motion support. Cards open the normal news article page. Pagination loads the feed without reloading the page and supports Back/Forward; ordinary links still work without JavaScript. The metadata shows the article's publication date, matching Campus Events.

In the Content Editor, edit the section heading inline. Manage articles through **News**, filtering by **Community Extension Activity**. Creating an article from that filtered view selects the Community Extension Activity type automatically. Selecting a card provides the shared post editing toolbar. Editing the main activity list and its draft/publish behavior remains independent of the news publishing workflow. Older page records/drafts receive the new heading and empty-message defaults without altering their saved content until publication.

## Deploy

Deploy the updated public template/CSS, public script, backend helper and handler, Content Editor registration/adapter, draft authorization and navigation changes together. Run once using the hosting database configuration:

```sh
php database/migrate-community-activities.php
```

This additive migration creates `community_activity_page` with one JSON document containing the page's editable copy, banner image path and ordered activity records. It copies the current header into initial editable data and creates an empty activities array. Re-running it preserves existing content. It has been run locally in Docker; hosting still needs it.

The existing editor-draft and image-framing migrations must also be installed. Publishing requires `manage_page_content`, login and CSRF verification. Client-provided image URLs are ignored: existing paths come from stored content and new paths only from validated uploads. All rendered text is escaped; public pages never load private drafts.

The previously undone `community_extension_page` table, if present, is not read or modified by this feature. Replaced/deleted published image files are retained to protect existing references and recovery; failed new uploads are removed. Roll back the feature files together if necessary and retain the table for recovery. No destructive migration is required.

## Verification

- `docker compose exec -T web php tests/community-activities-test.php`: isolated SQLite create/edit/category/reorder/delete, empty collection, public data source, invalid fields/uploads, forged image paths, revision conflicts and atomic failure.
- `node tests/community-activities-browser-test.cjs`: original banner, inline management, image replacement, reordering/deletion, draft recovery, shared publish confirmation/failure/retry, authorization/CSRF, public detail dialogs and responsive screenshots at 1440/820/390px. Six activities render through the real PHP template using a temporary MySQL table; publication is intercepted.
- `node tests/community-activities-http-test.cjs`: real local create/edit/reorder/delete, PNG upload/replacement/serving, invalid image bytes, escaping, stale writes, authorization/CSRF and empty-state restoration. The write lifecycle only runs on an empty page; it restores the original content and removes generated images.
- PHP/JavaScript syntax checks and existing Content Editor publishing/image-replacement regressions.

Test fixture PHP files are CLI-only. The feature has not been deployed to hosting or tested on physical mobile devices.
