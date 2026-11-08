# GNCI Website — Architecture Guidelines

## Purpose
This document describes the intended architectural principles for the GNCI school website. **The repository is the source of truth**: inspect the actual files, routes, database schema, and hosting configuration before claiming a component or feature exists. Do not invent paths, tables, frameworks, or commands.

## Technology context
- Likely stack: PHP, MySQL/MariaDB, HTML, CSS, and JavaScript.
- Deployment may use shared PHP hosting; do not assume Node, Composer, shell access, or rewrite rules are available in production.
- Confirm PHP version, database driver, directory layout, environment config, and routing before implementation.

## Separation of responsibilities
1. **Presentation:** reusable page templates/components, styles, client-side interaction, accessibility.
2. **Request handling:** routing, input validation, authorization, CSRF checks, response handling.
3. **Domain logic:** programs, departments, administration, athletics, organizations, office locations and publishing rules.
4. **Persistence:** parameterized queries, schema constraints, migrations and transactional changes.
5. **Integrations:** chatbot/API providers and external services isolated behind explicit interfaces.

Do not mix SQL, permission logic, and markup unnecessarily. Refactor in small backward-compatible steps instead of rewriting working sections.

## Dynamic content rules
- Department, program, graduate-school, student-organization, office, administrator, facility, and sports content must be database-driven where supported by the existing product.
- Never hardcode an expanding collection when the admin already manages it.
- Preserve existing entity IDs, links, ordering semantics, and editability.
- New entries must render through the same template as existing entries.
- Graduate School should remain at the bottom of academic department navigation regardless of new departments, if that navigation exists.
- Only show optional sections, such as a curriculum PDF, if valid content has been published. Do not display placeholder sections as a substitute for missing content unless requested.

## Visual editor and CMS

The Content Editor uses page-scoped heartbeat presence and server-side version
checks through `includes/editor-presence.php`. MySQL named locks serialize guarded
writes; short leases protect multi-request publishing. Private drafts and approval
snapshots retain their content baseline. Presence metadata uses additive tables;
see `docs/content-editor-presence.md` for deployment and shared-content conflict
boundaries.
- Maintain a single source of truth for published content and visual editing.
- Editing UI should update actual persisted content, not a separate hardcoded preview.
- Separate draft, validation, preview, and publish concerns when the existing data model supports them.
- Warn about incomplete required or recommended content before publishing; allow the admin to continue editing or explicitly publish where policy permits.
- Apply identical authorization and input validation to visual-editor and conventional admin endpoints.

## Data and database changes

The reviewed local cleanup removed only the unused `office_services`, `program_sections` and abandoned `community_extension_page` tables, with verified private rollback archives. Active normalized office services use `admin_office_services`; the current community feature uses `community_activity_page`. `database/migrate-unused-tables.php` rechecks code, data and SQL dependencies before any deletion. See `docs/unused-table-cleanup.md` for the audit, verification and recovery steps. Empty feature/workflow tables remain in place.

Shared personnel names use `personnel`, referenced by `personnel_id` on Administration people, office heads, departments and the homepage president. `includes/personnel.php` and `includes/personnel-saves.php` coordinate canonical names, compatibility mirrors and atomic profile saves after the `shared-personnel-v1` migration. Existing placements start with separate identities; `/admin/personnel.php` lets Super Admins review and link them explicitly. Photos and office/role details remain placement-specific. See `docs/personnel-normalization.md` for deployment, rollback and verification.

Office service lists and achievement display-name lists have a versioned
normalization migration in `database/migrate-normalized-content.php`.
`includes/normalized-content-lists.php` preserves existing editor payloads while
reading/writing ordered child rows after migration. Parent/list saves share a
transaction; old-schema readers remain available before migration. See
`docs/database-normalization.md` for deploy, finalize, rollback and test commands.

Announcement topic tags use `announcement_categories` through
`includes/announcement-categories.php`. The legacy `announcements.category_id`
retains the primary tag. Announcement saves synchronize both atomically, and
admin/public category filters and the Campus/Alumni galleries match any selected
tag without duplicating the announcement. See `docs/announcement-categories.md`
for the additive migration and verification commands.

Shared raster uploads are preprocessed in `includes/image-upload.php`: with PHP Imagick enabled, large images fit within 1920 × 1080 without cropping, retaining format, transparency, animation and photo-check metadata. Existing Administration images can be optimized using the CLI-only `scripts/optimize-administration-images.php`, with verified original backups and unchanged URLs. See `docs/image-optimization.md` for setup and recovery.

Personnel portraits additionally use `includes/person-photo-check.php` and the local YuNet/SFace worker in `scripts/person-face-check.py`. The upload precheck and personnel saves share the comparison policy; strong matches require a short-lived, audited Super Admin review or replacement. Face features are not persisted. See `docs/personnel-photo-check.md` for required Python/model configuration, identity matching limitations, thresholds and verification.
- Inventory impacted tables, foreign keys, indexes, code references, and backups first.
- Prefer additive migrations and phased cleanup. Never drop tables or columns simply because they look unused.
- Supply a rollback strategy, identify irreversible operations, and obtain explicit approval before destructive production work.
- Use InnoDB transactions for multi-record atomic changes where supported.
- Preserve MySQL/MariaDB compatibility with the real hosting environment.
- Document any required environment variables and schema migrations.

## Frontend architecture

- The shared navbar reads `student_email_url` from the existing `admin_settings` table through `admin/settings-functions.php`. Settings → Student Email validates and saves the URL with existing settings permissions, CSRF protection, auditing and approval routing. Desktop/mobile buttons share the value; the previous Microsoft URL remains the fallback. No schema migration is needed.
- Super Admin can set `page_suggestion_delay_seconds` in Settings (integer 5–3600, default 60). The shared recommendations dialog passes this value to its page-load timer; existing feedback and open-dialog checks still apply. Saves require Super Admin authorization and CSRF, use the existing settings table, and are audited.

- Visitor PWA metadata is added by `includes/pwa.php` through the existing browser-URL HTML buffer. `/service-worker.js` maps to `service-worker.php`; its worker uses an explicit public-route allowlist and server-issued public cache opt-in. Admin/auth routes, editor previews, APIs, uploads, session-bearing pages and form submissions are excluded. Offline storage is versioned; updates are checked quietly and use normal worker activation after old app tabs close, with no update popup or forced page reload. See `docs/pwa.md` for deployment, cache boundaries and verification.
- The admin PWA has its own `admin-manifest.json` identity, `/admin/login` start URL and `/admin/` scope. Its separate `/admin/service-worker.js` is network-only and stores no private pages. Administrative navigation uses canonical `/admin/` aliases; old root aliases redirect compatibly, and original PHP files still execute for RBAC/CMS identity. Scoped updates do not activate the other app's worker. Embedded editors omit PWA controls. App identities do not establish account/storage isolation on the shared origin.

- Contact numbers share the parser/formatter in `includes/contact-number.php` and the admin controls in `assets/js/contact-number.js`. Existing phone columns and Contact Us section fields store canonical numbers with optional extensions; recognizable legacy values remain readable without a migration. Office/department saves and Contact Us section validation enforce the same rules on the server. See `docs/contact-numbers.md` for formats, compatibility and tests.
- Browser URLs are mapped explicitly in `includes/browser-routes.php` and the matching Apache rules in `.htaccess`. `includes/browser-urls.php` redirects legacy GET/HEAD URLs, renders short navigation links, and restores the original request route internally for CMS identities. Apache continues to execute the original PHP entry point, so file-based RBAC checks retain their existing boundary. POST/action endpoints and visual-editor preview URLs keep their existing submission behavior.
- Favor shared navbar, footer, buttons, cards, modal, form, and responsive layout components.
- Keep layout/design tokens centralized; consult `designsystem.md`.
- Ensure the public website and admin editor do not unintentionally leak styles or scripts into each other.
- Make media crop/reposition behavior predictable and preserve uploaded image aspect ratios.

## APIs and chatbot
- Keep secret API keys server-side.
- Validate provider outputs before rendering them as HTML.
- Apply rate limits and sensible timeouts to external requests.
- Give the chatbot only approved/published information; do not expose draft/admin/private records.

## Implementation workflow
1. Map relevant files, routes, data flow, and dependencies.
2. Describe the smallest implementation that meets the request.
3. Preserve existing behavior and public URLs unless change is explicitly requested.
4. Modify only required files; avoid unrelated redesigns or new files.
5. Test both success and failure paths, especially hosted-PHP constraints.
6. Report changed files, database actions, regression risks, and verification results.

## Documentation maintenance
Update this document only after verifying architectural facts in the repository. Refer to `security.md` for controls and `designsystem.md` for UI rules.
