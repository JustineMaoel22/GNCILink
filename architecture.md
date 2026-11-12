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

Event category reads use `includes/event-categories.php`. After the CLI migration
in `database/migrate-event-categories.php`, the Events category foreign key points
to `event_categories`; that foreign key activates the new reader for admin,
calendar imports, approval summaries and public event feeds. Existing category
IDs and assignments are preserved, with non-event topics retained as inactive
legacy entries. News and Announcements keep their original storage. See
`docs/event-categories.md` for deployment, backup and rollback.

Calendar import review workspaces use an additive `review` object inside the existing `calendar_import_batches.preview_json`. Owner-scoped save/resume/list/discard actions share the Events permissions and CSRF checks, preserve the original extraction and event snapshots, and use saved versions plus conditional writes to reject stale tabs. Saved workspaces remain available for 30 days after the last save; final confirmation retains category/audience validation, approval routing and event/revision conflict checks. No workspace schema migration is required. See `docs/calendar-import.md`.

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

Shared raster uploads are preprocessed in `includes/image-upload.php`: with PHP Imagick enabled, large images fit within 1920 × 1080 without cropping, retaining format, transparency, animation and photo-check metadata. Without Imagick, GD provides a memory-checked fallback for static JPG/PNG/WebP, retaining transparency and embedded provenance metadata; animations requiring resizing are refused rather than flattened. Existing Administration images can be optimized using either processor through the CLI-only `scripts/optimize-administration-images.php`, with verified original backups and unchanged URLs. See `docs/image-optimization.md` for setup and recovery.

Personnel portraits additionally use `includes/person-photo-check.php` and the local YuNet/SFace worker in `scripts/person-face-check.py`. The upload precheck and personnel saves share a page-specific comparison policy (Administration section, individual department, or homepage), exclude only the current entry, and honor the AI image checker setting; when enabled, strong matches require a short-lived, audited Super Admin review or replacement. Turning it off skips face comparison and AI validation while retaining file safety checks. Face features are not persisted. See `docs/personnel-photo-check.md` for required Python/model configuration, identity matching limitations, thresholds and verification.
- Inventory impacted tables, foreign keys, indexes, code references, and backups first.
- Prefer additive migrations and phased cleanup. Never drop tables or columns simply because they look unused.
- Supply a rollback strategy, identify irreversible operations, and obtain explicit approval before destructive production work.
- Use InnoDB transactions for multi-record atomic changes where supported.
- Preserve MySQL/MariaDB compatibility with the real hosting environment.
- Document any required environment variables and schema migrations.

## Frontend architecture

- Announcement and News audience editors share department-local program rules through `includes/content-audience.php`: an empty program selection under a selected department targets that department, and specific programs restrict only their own department. Announcement storage retains its existing lists and legacy mirrors; News adds `news_departments` and `news_programs` via `database/migrate-news-audience.php`. News saves are atomic with audience writes, and approval payloads retain explicit empty selections. The visitor feed, filtered pinned carousel and program-page announcement bulletins apply the same scopes. Existing untargeted News behavior is preserved. See `docs/content-audiences.md` for deployment, rollback and checks.

- Scholarship Opportunities shares the Tuition and Fees sidebar and PDF viewer styles. After `database/migrate-scholarship-categories.php --apply`, ordered categories use the dedicated `scholarship_categories` table through `components/scholarship-content.php`; stable category IDs keep existing entry links intact. Scholarship opportunities and PDF references retain their JSON format in `tuition_fee_content`. Category rows and their compatibility JSON mirror are saved in the same transaction as the catalog; the `scholarship-categories-v1` marker activates the new reader, with legacy readers available before migration or after rollback. The public content panel is a PDF view; existing scholarship text, awards, requirements and photos remain stored. The scholarship handler keeps the existing publishing/approval boundary and saves the catalog atomically. See `docs/scholarship-workspace.md` for storage compatibility and verification.

- The visitor News & Announcements feed uses `assets/js/visitor-news-search.js` to fetch its existing published GET page for search, sort, type/category/audience filters and pagination. Result regions and filter URLs update without replacing the search input, navigation, footer or chatbot. Image skeletons and pinned carousels are initialized for the new results; article links retain normal navigation. Requests are cancelled and versioned so stale results cannot overwrite newer searches, with retry feedback on failure and server-rendered links/forms as the no-JavaScript fallback. Visual-editor previews retain normal navigation. Verify with `node tests/visitor-news-search-browser-test.cjs`.

- News, Announcements and Facebook Posts use `assets/js/admin-content-search.js` for background search, filters, pagination and browser history. The existing authenticated GET pages remain the data source and no-JavaScript fallback. Only list results, counts, pagination and preview data are refreshed; editors and search controls stay mounted. Row actions use delegated listeners, and the existing bulk-selection controller retains its table node. Superseded requests are aborted and ignored; failures retain prior results with a retry action. Facebook pin/category writes cancel pending searches and refresh filtered results through the same loader. Events continues to filter its loaded rows locally without search requests or navigation. Run `node tests/admin-content-search-browser-test.cjs` and `node tests/facebook-events-search-browser-test.cjs` for search/action regression checks without changing content.

- The shared navbar reads `student_email_url` from the existing `admin_settings` table through `admin/settings-functions.php`. Settings → Student Email validates and saves the URL with existing settings permissions, CSRF protection, auditing and approval routing. Desktop/mobile buttons share the value; the previous Microsoft URL remains the fallback. No schema migration is needed.
- Super Admin can set `page_suggestion_delay_seconds` in Settings (integer 5–3600, default 60). The shared recommendations dialog passes this value to its page-load timer; existing feedback and open-dialog checks still apply. Saves require Super Admin authorization and CSRF, use the existing settings table, and are audited.

- Visitor PWA metadata is added by `includes/pwa.php` through the existing browser-URL HTML buffer. `/service-worker.js` maps to `service-worker.php`; its worker uses an explicit public-route allowlist and server-issued public cache opt-in. Admin/auth routes, editor previews, APIs, uploads, session-bearing pages and form submissions are excluded. Offline storage is versioned; updates are checked quietly and use normal worker activation after old app tabs close, with no update popup or forced page reload. See `docs/pwa.md` for deployment, cache boundaries and verification.
- The admin PWA has its own `admin-manifest.json` identity, `/admin/login` start URL and `/admin/` scope. Its separate `/admin/service-worker.js` is network-only and stores no private pages. Administrative navigation uses canonical `/admin/` aliases; old root aliases redirect compatibly, and original PHP files still execute for RBAC/CMS identity. Scoped updates do not activate the other app's worker. Embedded editors omit PWA controls. App identities do not establish account/storage isolation on the shared origin.
- For independent Android installation, configure `ADMIN_PWA_ORIGIN=https://admin.gncipampanga.com` only after DNS/HTTPS and a shared project document root are ready. `includes/admin-origin.php` validates that fixed destination, moves GET/HEAD admin bookmarks and password-reset links to the separate origin, preserves POST/API/worker/preview requests, and keeps host-only sessions. The admin hostname supports the same `/admin/` routes and same-origin editor previews; public links go to the visitor origin, which retains its original PWA. Without the setting the existing login remains unchanged. See `docs/pwa.md` for cPanel setup and local two-host checks.

- Contact numbers share the parser/formatter in `includes/contact-number.php` and the admin controls in `assets/js/contact-number.js`. Existing phone columns and Contact Us section fields store canonical numbers with optional extensions; recognizable legacy values remain readable without a migration. Office/department saves and Contact Us section validation enforce the same rules on the server. See `docs/contact-numbers.md` for formats, compatibility and tests.
- Browser URLs are mapped explicitly in `includes/browser-routes.php` and the matching Apache rules in `.htaccess`. `includes/browser-urls.php` redirects legacy GET/HEAD URLs, renders short navigation links, and restores the original request route internally for CMS identities. Apache continues to execute the original PHP entry point, so file-based RBAC checks retain their existing boundary. POST/action endpoints and visual-editor preview URLs keep their existing submission behavior.
- Favor shared navbar, footer, buttons, cards, modal, form, and responsive layout components.
- Keep layout/design tokens centralized; consult `designsystem.md`.
- Ensure the public website and admin editor do not unintentionally leak styles or scripts into each other.
- Make media crop/reposition behavior predictable and preserve uploaded image aspect ratios.

## APIs and chatbot
- Direct-cPanel deployment hardening and the read-only `scripts/check-deployment.php` preflight are documented in `docs/cpanel-deployment.md`. Root rules deny private artifacts on every hostname; `uploads/.htaccess` enforces static media handling. Authentication trusts forwarded HTTPS only from explicitly configured proxy peers.
- Shared endpoint/IP/user budgets run through `includes/request-protection.php` at configuration/session bootstrap. Provider-attempt quotas also apply at outbound call sites. See `docs/api-request-protection.md` for endpoint inventory, limits, single-host storage and proxy setup, failure behavior and verification.
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
