## [0.21.2] - 11-9-2026

### Added
- Added a chatbot message allowance counter showing remaining messages (for example, "20 of 20 messages available") and when more messages become available. The counter updates automatically and keeps the existing limit when starting a new conversation.
### Minor Improvements/Additions
- Added endpoint-specific IP/user quotas, provider budgets, request deadlines, abuse logging and friendly cooldown messages across public and admin API flows. Deduplicated recommendation and chatbot allowance requests; bounded editor retries and Facebook feed refreshes. See `docs/api-request-protection.md` for limits and verification.

### Bug Fixes
- Hardened direct-cPanel HTTPS detection, host-independent private-file restrictions and upload execution isolation; added deployment checks and a cPanel rollout guide.
- Fixed the admin Logout link to open its confirmation dialog inside the current page after URL rewriting.
- Required CSRF-protected POSTs for photo diagnostics and Facebook recategorization; hardened chatbot throttle failures, analytics input, and Facebook image downloads.
