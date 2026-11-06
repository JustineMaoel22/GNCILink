## [0.20.1] - 11-5-2026

### Database
- Removed three verified unused local tables (`office_services`, `program_sections`, and abandoned `community_extension_page`) after restore-tested backups; preserved all remaining records and schemas and added guarded cleanup/rollback tools.
- Enabled shared personnel identities across Administration people, office heads, department heads and the homepage president, with explicit Super Admin linking and atomic shared-name updates.
- Verified existing office-service and achievement-name normalization. Retained legacy compatibility columns and preserved existing content, photos and URLs.
- Added an additive migration for Content Editor presence and content-version metadata, without changing existing content tables.

### Minor Improvements/Additions
- Moved the profile and logout to the header.
- Added page-specific active viewers and editors with profile pictures or initials, Viewing/Editing status, automatic updates and inactive-user expiry.
- Added editing-conflict warnings, server-side version checks, publishing locks and private recovery drafts to protect pending Content Editor changes.
- Improved AI image validation.
- Added Content Editor undo and redo for text, formatting, images and layout, with keyboard shortcuts, toolbar button states and up to 50 changes per preview session.
- Added contact-number formatting and validation for mobile, landline and extension fields; the public footer now uses the published Contact Us phone number.
- Added Select all, individual selection, selected-item counts and password-confirmed bulk deletion for news, announcements and events. Selection covers the current page's visible records; Content Editor requests retain the approval workflow.
- Moved selection checkboxes to the left of news/announcement thumbnails and beside event titles.
- Made row action buttons icon-only in News, Announcements, Events and Facebook Posts, with hover/focus labels and accessible names.
- Combined page-text submissions and newer proposals in the Approvals queue, with shared filters, previews, counts and version-checked review decisions.
- Included completed page/subpage publications in dashboard Recent Content and added pagination to daily visitor totals.
- Added browser-specific admin font-size preferences and shared HTML/plain-text authentication email templates.
- Enabled database-managed vision and mission sections on Basic Education department pages.

### Security
- Required the acting Super Admin's current password when saving user-role changes, with rate limiting and audit records.
- Required the acting user's current password before deleting or requesting deletion of news, announcements and events, including bulk selections. Confirmation passwords are excluded from stored approval payloads.
- Added current-password confirmation for Super Admin publishing and approval actions that publish content.
- Validated every bulk-deletion record and made deletion/auditing transactional so a missing record, permission failure or database error prevents partial deletion.
- Prevented conflicting Events action fields from bypassing deletion-password or approval checks.

### Fixes
- Fixed undo restoring stale image-loading states that hid loaded images and homepage program backgrounds; preserved uploaded-image previews during undo/redo.
- Removed page-wipe and card fade-in animations when switching Administration categories while preserving category navigation and office-card interactions.

### Removes
- Removed rate us widget.
- Removed unnecessary add events button.
- Removed existing-image Reposition controls from management lists; repositioning remains available in the Content Editor.

### Deployment Notes
- Active-user presence and conflict protection require `database/migrate-editor-presence.php` or `sql/editor-presence-migration.sql`. The migration was applied locally; hosted deployments still need to install it.
