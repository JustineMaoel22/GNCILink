# Reviewed local table cleanup

Date: 2026-11-06. Scope: the local Docker database only. The current website, maintenance code, migration files, archived schema inventory and live table metadata were inspected. Searches included literal names and the dynamic table maps used by the application. Empty tables were not treated as unused solely because they contain no records.

## Removed tables

| Table | Original rows | Reason |
| --- | ---: | --- |
| `office_services` | 0 | Old child table of the legacy `offices` directory. No runtime query or active table map uses it. The logical list kind named `office_services` maps to **`admin_office_services`**, which remains active. |
| `program_sections` | 0 | Old unused child table of `programs`. Current program content uses `programs`, `program_items` and the existing page-content/editor structures. |
| `community_extension_page` | 1 | Abandoned earlier community feature. Current public/editor code uses `community_activity_page`. The legacy row was archived and its exact restore verified before removal. |

No incoming foreign keys used these tables. The initial local inventory found no views, triggers, routines or scheduled events in the application schema. The cleanup additionally checks non-system schemas for stored SQL references and refuses hidden definitions, fresh runtime references, incoming foreign keys or unexpected data. Outgoing foreign keys from the two empty child tables do not make their parent tables dependent on them; `offices` and `programs` were retained.

The database went from **73 to 70 base tables**. Every remaining table's original row count, data hash and schema hash matched the verified pre-cleanup backup while application writers were stopped. Eight affected feature-output hashes also matched: Administration, departments, programs, the president, the current community page and three chatbot retrieval cases. Only the chatbot prompt's current-minute header was excluded from feature hashing.

## Important retained tables

- `admin_office_services` contains the active normalized office-service records. It is distinct from the removed `office_services` table.
- `offices` remains part of the chatbot's legacy directory reader even though it is currently empty.
- `faqs`, gallery tables, audience junctions, enrollment tables, tuition documents, slug history and password-reset tables support existing features even when empty.
- `faq_page` stores FAQ page text, separately from individual FAQ entries.
- Shared `personnel`, normalized achievement names, workflow drafts/submissions, migration flags, authentication/audit tables, analytics and Facebook state remain intact.
- No compatibility columns, live content, uploads, media records or current community activities were removed.

This establishes compatibility with the checked repository and local configuration. Untracked external integrations or future code changes require a new review; it is not a claim that an empty table can always be removed.

## Backup and recovery

Private backup directory:

```text
%LOCALAPPDATA%/GNCIBackups/unused-tables-20261106-8d0986fc/
```

It contains a full `database.sql`, verified `uploads.zip`, verification `manifest.json`, affected-feature baseline, and a separate `obsolete-tables.json` archive with its checksum and restore-verification receipt. The database backup was restored into an isolated schema before cleanup. The exact per-table archive was independently restored and compared in another isolated schema. Private archives include legacy data; keep them outside the web root and version control.

The current rollback uses `database/migrate-unused-tables.php --rollback`, not the historical schema-only `rollback-drop-unused-tables.sql`. It recreates missing reviewed tables and restores their archived rows, including the old community record. It never replaces different existing data. Current `community_activity_page` data and newer records in retained tables remain untouched.

For local Docker recovery, first pause known writers and create a fresh verified backup. Mount the private archive directory outside the application root, then run:

```sh
docker compose run --rm --no-deps -T --user www-data \
  --volume <private-backup-directory>:/maintenance-backup \
  web php database/migrate-unused-tables.php --rollback \
  --maintenance --backup-verified --archive=/maintenance-backup/obsolete-tables.json
```

Resume `web` and `phpmyadmin` after verification. A full database restore is a separate recovery operation and must not overwrite newer data without review.

## Reusing the maintenance tools

`scripts/audit-unused-tables.php` is read-only. `database/migrate-unused-tables.php` without flags only reports whether the three reviewed tables exist. `--prepare` validates dependencies, saves the rollback archive and proves its restore in a generated temporary schema. `--apply` requires that receipt to match the current archive. Both require maintenance/backup acknowledgements and a private archive path outside the web root. The command refuses non-Docker database hosts. MySQL DDL commits implicitly; the private archive provides rollback, not a database transaction.

`database/hosted-missing-tables.sql` no longer creates the abandoned community table on new setups. Historical exports remain unchanged and can recreate old tables if deliberately imported. The hosted database was not modified by this cleanup.

## Checks run

```sh
docker compose exec -T --user www-data web php tests/unused-table-cleanup-test.php
node tests/table-cleanup-smoke-test.cjs
```

The isolated integration test passed 22 checks covering active office services and editing, program reading, the current community page, chatbot office retrieval, dependency/data guards, idempotence, exact rollback, archive tampering and refusal to overwrite newer data. It creates synthetic fixtures and drops only its own generated test schema. The HTTP smoke test passed for five public pages and three authenticated editor shells without submitting content. The existing list-normalization suite passed 43 checks, and the shared-personnel suite passed 27 checks against the cleaned source schema using their isolated fixtures.
