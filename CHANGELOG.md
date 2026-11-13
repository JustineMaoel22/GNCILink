## [0.21.7] - 11-13-2026

### Added
- Added multiple manual categories to Facebook Posts using the existing searchable checkbox selector, with Automatic and Uncategorized options.
- Added the additive Facebook category migration (`database/migrate-facebook-post-categories.php`), preserving existing assignments and the primary category for compatibility.
- Added server-side Announcement edit conflict protection. Older tabs cannot overwrite newer text, categories, audiences, or gallery changes; rejected edits retain entered text for review.

### Changed
- Increased the admin session timeout from 30 minutes to 1 hour of inactivity, including environment overrides and the application default.
- Removed the editing-form preview buttons from Announcements and News.
- Removed the Facebook destination details and optional attachment-link field from the Announcement form.
- Replaced the visible main/cover-picture removal checkbox with an × button on the photo in Announcements and News. Added removal buttons to newly selected News gallery photos.

### Bug Fixes
- Fixed Administration saves incorrectly reporting that the editor switched pages. All four section tabs now share the collection draft identity used by conflict protection.
- Fixed restored Administration drafts moving section headings and descriptions below the cards when switching tabs.
- Fixed unsupported picture selections, including dropped PDFs, reaching image previews. Only JPG, PNG, GIF, and WebP selections are allowed; rejected replacements retain previously selected valid pictures and upload files.
- Fixed cancelling a replacement picture's positioning dialog with Cancel or Escape clearing the earlier upload while leaving its thumbnail visible.
- Fixed News gallery selections replacing earlier pictures when adding photos one at a time. Selections now accumulate up to the existing 10-picture limit.
- Fixed photo removal leaving stale thumbnails or queued upload files, and prevented rejected files from restoring intentionally removed pictures.
- Fixed the dashboard's Upcoming events count comparing Manila event times against the database timezone instead of the current Manila time.
- Fixed the Facebook category editor defaulting automatic posts to a manual category when opened and saved unchanged.
- Fixed reversed date filters leaving controls and browser history inconsistent with the normalized range used for results.
- Fixed Facebook category filters and public feeds ignoring secondary categories, including Campus and Alumni galleries, without duplicating posts.
- Fixed Facebook posts tagged with News categories such as Achievements being excluded from the public News tab and named category filters. Sidebar counts now include those posts.
