## [0.21.6] - 11-13-2026


### Bug Fixes
- Fixed Administration editor saves incorrectly reporting that the editor switched pages. All four section tabs now share the same draft and conflict-check identity.
- Fixed restored Administration drafts moving the section heading and description below the cards when switching tabs. The heading and description now remain above the card grid.
- Protected the Default Admin account from deletion. The Users page displays "Protected account" instead of Delete, and server-side validation blocks direct deletion requests.
- Locked the Default Admin role. The Users page displays a fixed role, and server-side validation blocks direct role-change requests.
