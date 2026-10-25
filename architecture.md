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
- Maintain a single source of truth for published content and visual editing.
- Editing UI should update actual persisted content, not a separate hardcoded preview.
- Separate draft, validation, preview, and publish concerns when the existing data model supports them.
- Warn about incomplete required or recommended content before publishing; allow the admin to continue editing or explicitly publish where policy permits.
- Apply identical authorization and input validation to visual-editor and conventional admin endpoints.

## Data and database changes
- Inventory impacted tables, foreign keys, indexes, code references, and backups first.
- Prefer additive migrations and phased cleanup. Never drop tables or columns simply because they look unused.
- Supply a rollback strategy, identify irreversible operations, and obtain explicit approval before destructive production work.
- Use InnoDB transactions for multi-record atomic changes where supported.
- Preserve MySQL/MariaDB compatibility with the real hosting environment.
- Document any required environment variables and schema migrations.

## Frontend architecture
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
