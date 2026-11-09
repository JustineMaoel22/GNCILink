## [0.21.3] - 11-9-2026

### Minor Improvements/Additions
- Centered public page headers below 1032px while retaining desktop compositions at 1032px and wider and preserving saved CMS content.
- Improved import calendar UI.
- Matched News and Facebook Posts pinned badges to the announcement style, with an “On homepage slideshow” label and gold active pin buttons. Added a gold left border to pinned announcement rows on desktop and mobile.
- Removed the confirmation dialog when pinning or unpinning announcements; archive and restore confirmations remain.
- Added search without page refreshes to admin News, Announcements and Facebook Posts, and the visitor News & Announcements feed. Search keeps typing focus while filters, pagination and browser history update in place; Events retains its instant local filtering.
- Widened the visitor search field to display the full placeholder, added a green hover state to the Sort button, and removed redundant search-result and loading notices. Search errors still provide a retry option.

### Bug Fixes
- Fixed positioned department header spacing boxes intercepting clicks on editable “College of” text in the Content Editor.
- Added GD fallback when Imagick is unavailable so static JPG, PNG and WebP uploads fit within 1920 × 1080 even below 5 MB, retaining transparency and photo metadata. Added memory checks and enabled the same fallback for the existing Administration image optimizer.
- Fixed duplicate-face checking continuing when the AI image checker was disabled. The setting now controls both duplicate-face and AI image checks during uploads and profile saves, while keeping file type, size, and image safety validation active.
