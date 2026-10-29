## [0.17.0-alpha] - 2026-10-29

### New Features & Improvements
- Added a website privacy audit and private policy drafts, with optional analytics, personalization and embed choices. Public legal-policy publication remains gated pending school privacy contacts and approved practices.

### Bug Fixes
- Student Organization pages and ordinary editing now support hosted databases that have not yet received the archive-column migration, preventing a missing `archived_at` column from causing HTTP 500. Archiving still requires the documented migration.
- College Programs facility uploads, deletions, replacements, renames, reordering, and image positioning now stay in private drafts and restore after refresh. Only an explicit Publish action changes the public page; Content Editor changes retain Super Admin approval.
- Admin sessions now retain server-side data for at least the configured inactivity timeout and use a browser-session cookie, preventing shorter PHP defaults from expiring the login early.
- Editor “Click to edit” hints and Reposition controls now align inside the visible photo crop and follow moving cards instead of appearing detached above or beside them.
- Facility photo names now save to a private draft on Enter without opening Publish confirmation. Escape cancels the rename, drafts survive refresh, and publishing remains an explicit action.
- Tuition and Fees PDF management now scrolls within the available screen space and stays below the editor toolbar, keeping Publish accessible as documents are added and the toolbar wraps.
- Program pages now use a compact phone seal layout with the rotating text ring outside the logo, improved heading wrapping, and program badges centered in a single column on phones.
- News and Announcements category panels now fit the sidebar on laptop and mobile screens, wrap long names, and update their available height when the window resizes or scrolls.
- Removed the duplicate department card description field from the program page preview; descriptions remain editable directly on department cards.
- College department program cards now publish only edited text fields, preserving stored descriptions during heading or image changes while still allowing descriptions to be intentionally cleared.
- College Programs “Why Choose” reasons now add, edit, reorder, delete, and publish in place without reloading the editor. Pending reasons stay in drafts, and failed saves remain available for retry.
- College Programs facility slide popups now keep Reposition and Delete clear and clickable. The floating image control is hidden while a popup is open, and facility repositioning uses the popup’s responsive action row.