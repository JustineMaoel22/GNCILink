## [0.21.3] - 11-9-2026

### Minor Improvements/Additions
- Improved import calendar UI.
- Matched News and Facebook Posts pinned badges to the announcement style, with an “On homepage slideshow” label and gold active pin buttons. Added a gold left border to pinned announcement rows on desktop and mobile.
- Removed the confirmation dialog when pinning or unpinning announcements; archive and restore confirmations remain.
- Added search without page refreshes to admin News, Announcements and Facebook Posts, and the visitor News & Announcements feed. Search keeps typing focus while filters, pagination and browser history update in place; Events retains its instant local filtering.
- Widened the visitor search field to display the full placeholder, added a green hover state to the Sort button, and removed redundant search-result and loading notices. Search errors still provide a retry option.

### Bug Fixes
- Fixed duplicate-face checking continuing when the AI image checker was disabled. The setting now controls both duplicate-face and AI image checks during uploads and profile saves, while keeping file type, size, and image safety validation active.
