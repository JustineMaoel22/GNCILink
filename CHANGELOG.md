## [0.21.4] - 11-10-2026

### Minor Improvements/Additions
- Updated admin login to lock an account for 15 minutes after 10 incorrect password attempts, with a live minutes-and-seconds countdown. Attempts during the lock do not extend the waiting period.
- Updated chatbot question suggestions to use general questions on every page about GNC programs, enrollment, tuition and fees, and scholarships.
- Removed the Module filter from Activity Logs and arranged User, From date and To date in three columns on desktop.
- Standardized missing-image placeholders for public Announcement, News and Facebook cards, pinned posts and related-post thumbnails: a light-gray background with a centered gray image symbol, also shared by Campus Events and Alumni post feeds.
- Clarified Events audience selection: selecting a department automatically selects its programs, with manual adjustments available afterward. Added browser verification against the Events form for department selection, combined program audiences and reset behavior.
- Added Alumni as a selectable Events category, preserving its existing category ID and color. Existing installations can enable it with `database/add-alumni-event-category.php`.
- Added a dedicated `event_categories` catalog and migration preserving existing event category IDs, names and colors. Events, calendar imports, approvals and public calendar feeds use the event catalog; new selections are School Event, School Activities, Holiday and Suspension, with older assignments retained as legacy categories. See `docs/event-categories.md` for deployment and rollback.
- Added required short codes when creating college departments and an Edit department short code option for existing departments. Code edits are saved on Publish without changing department URLs; department and program breadcrumbs show saved codes, with initials for older departments without a code.
- Added a visible Delete department toolbar action. Deletion remains pending until Publish and can be canceled; newly created departments start hidden until explicitly shown on the website.
- Added Short code and Full program name fields to the department page's Add Program form. New program cards remain in the preview until Publish; supplied codes use the existing server validation and duplicate checks.
- Removed obsolete fallback-banner controls from Program options. Programs without banner slides use the cream background.
- Added dedicated `scholarship_categories` storage for category IDs, names and display order, with migration of existing categories, atomic publishing and a synchronized compatibility mirror for rollback. Scholarship entries and PDF paths are preserved.
- Rebuilt Manage Scholarships as a responsive management dialog with searchable category accordions, scholarship counts, readable titles, PDF status, three-dot action menus and focused add/edit forms. Added drag-handle reordering for categories and scholarships with touch support and accessible Move up/Move down actions; existing publishing and approval behavior is preserved.
- Simplified the scholarship form to category, title and PDF upload; removed unused Overview, Eligibility, Benefits, Application process, Additional information and Learn more inputs while preserving previously saved values.
- Redesigned Scholarship Opportunities to match Tuition and Fees with searchable category navigation and a PDF content panel with download/open controls. Removed text, photos and award cards from the public document view while retaining stored records. Added admin category/opportunity editing, ordering and deletion with the existing publishing/approval workflow.
- Added Tuition and Fees category creation, reordering, and deletion in the admin Content Editor. Changes go live on Publish, with stored PDFs retained when deleting a category and at least one category required.
- Added a visible “Category icon” label above the fee category icon dropdown.

### Bug Fixes
- Fixed login retries after a lockout being interrupted by the anonymous session inactivity timeout. Signed-in and pending OTP sessions still expire; login requests that receive a session-expiry redirect now open the fresh login form instead of showing a generic error.
- Removed the duplicate bottom "This service is busy" banner from admin login requests; the login form retains its warning and live countdown.
- Corrected local chatbot rate-limit counter ownership after a command-line diagnostic created a root-owned counter. Limits and counts were preserved; the complete programs-question request was verified successfully.
- Updated authentication email configuration for cPanel mailbox SMTP: removed the Gmail default and app-password normalization, honor the configured server/port and exact mailbox password, and added explicit SMTP/local-mail transport selection with sender/header validation. Updated deployment checks and configuration examples; PHPMailer remains the SMTP client library.
- Fixed loading overlays covering missing-image placeholders in visitor homepage posts and pinned posts on the News & Announcements page. Empty image slots now render their placeholder immediately.
- Fixed Bulletin Board View all events links losing the originating program filter. Calendar program filters now honor explicit multi-program and multi-department audiences, preserve school-wide events and apply audience-group restrictions.
- Fixed program Bulletin Board events ignoring multi-program and multi-department audiences. Explicit selections now override legacy ALL values, both audience dimensions must match, and ongoing/today's all-day published events remain visible.
- Fixed the department short-code form opening near the page heading; it now opens below Page options.
- Fixed department breadcrumbs displaying URL slugs such as CONURSING instead of department short names.
- Fixed domain-only links such as `youtube.com` in News and Announcements opening as local 404 pages. Editor links, previews and server saves add HTTPS; article rendering also corrects older saved links while preserving internal paths and unsafe-scheme filtering.
- Fixed garbled ellipsis characters in Content Editor publishing, saving and loading labels.
- Anchored scholarship Add/Rename category dialogs to Manage scholarships so they stay below the editor toolbar instead of jumping to the upper-left corner.
- Made the scholarship PDF upload field accessible in a scrolling editor form and added direct Upload/Replace PDF controls on the selected scholarship and in Manage scholarships.
