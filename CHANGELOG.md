## [0.21.5] - 11-10-2026


### Bug Fixes
- Protected the Default Admin account from deletion. The Users page displays "Protected account" instead of Delete, and server-side validation blocks direct deletion requests.
- Locked the Default Admin role. The Users page displays a fixed role, and server-side validation blocks direct role-change requests.
