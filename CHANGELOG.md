## [0.19.0] - 11-4-2026

### Major Features/Improvements
- Added Department and Academic Program multi-selection to Create/Edit Announcements and Add/Edit Events in the Admin Panel.
  - Allows selecting one, multiple, or all departments and academic programs.
  - Checkboxes beside each item, Select All / Deselect All convenience buttons, and real-time search filtering.
  - Selected options appear as removable tags in the input box.
  - Multi-department and multi-program display badges in management tables and synchronized audience filtering across both admin and public calendars/bulletins.
- Improved the Admin Panel UI design.
- Added Alumni page.

### Minor Additions/Improvements
- Redesigned the Admin sign-in flow with a shared branded layout, clearer form feedback, accessible verification-code entry, and a smaller campus background image while preserving existing authentication controls.
- Redesigned News management with paginated search, status/category/date filters, recently updated sorting, saved and unsaved article previews, labeled actions, contributor draft choices, and shared pinned-content management. Preserves role permissions and approval workflow.
- Redesigned Facebook Posts management with cached caption search, category/slideshow/date filters, pagination, accessible previews, responsive post cards, and shared slideshow management. Category and pin changes save in place with CSRF checks, existing role permissions, and activity logging.
- Added short public and admin browser URLs with redirects from existing links, preserving query parameters, CMS page identities, and server-side permissions.
- Redesigned Create User with inline validation, role guidance, compact account-setup instructions, and a masked temporary-password confirmation with show/copy controls and close protection.
- Applied the same grouped modal layout, persistent save bar, inline validation, and unsaved-change protection to Create/Edit News, preserving author/category fields, galleries, and approval rules.
- Applied the event modal's grouped layout, persistent save bar, accessible audience controls, inline validation, and unsaved-change protection to Create/Edit Announcement, preserving rich text, galleries, and Facebook publishing.
- Redesigned the Events & Calendar event form with grouped fields, single-day/multi-day scheduling, searchable audience selection, inline validation, visibility-specific save actions, and unsaved-change protection.
- Fixed event audience persistence when creating and editing events, and added server-side date/time range validation.
- You can now set distinct font sizes per device view.
- You can now change your profile picture.

### Bug Fixes
- Fixed short department URLs showing “Department not found” because the department slug was read before URL normalization; both old and short department/program paths now identify their records directly.
- Fixed an issue where Banner Slides did not reflect background image changes made directly through the Visual Editor instead of the Banner Slides section.

### Removes
- Removed floating animations in icons
