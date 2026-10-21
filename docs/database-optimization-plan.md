# Database audit and proposed optimization plan

Date: 2026-10-20, Asia/Manila. Status: **analysis completed; implementation and deployment pending**.

## Scope and evidence

Reviewed the current working tree, including uncommitted features, rather than only committed PHP. No existing application files were changed. No database DDL, inserts, updates, deletes, restores, or production deployment were performed. No new complete database backup has been created or restore-tested during this analysis. Backup verification is a prerequisite to the implementation phase.

Evidence:

- [Full exported schema and PHP reference inventory](database-schema-inventory.md): all 42 tables, 365 columns, indexes, auto-increment definitions, and 38 foreign keys, with application/migration PHP references from 131 files. The inventory includes the export's exact schema definitions, without record values.
- [Local MySQL metadata](database-audit-local-metadata.tsv): MySQL 8.0.46, all tables InnoDB; table and column name sets match `database/gnc_admin (51).sql`. This verifies names, not a complete equality comparison of every default, collation, index, or constraint. Exported DDL and live metadata are both retained for review.
- [Read-only baseline](database-audit-baseline.txt): exact counts for selected tables, aggregate duplicate checks, and execution plans collected from the local Docker database `gnc-link-v2-db-1`, schema `gnc_admin`.

The export identifies its creation time as 2026-10-20 02:26 AM and sets the SQL session time zone to UTC. Its SHA-256 is recorded in the inventory. It is an existing snapshot, not a certified fresh backup. Production schema, traffic, slow-query history, external jobs, and restore reliability remain unverified. Docker Compose specifies MySQL 8.0 and the Dockerfile PHP 8.2; runtime PHP version should be verified before testing.

## Findings and retention decisions

**No table or column is approved for removal. No tables were removed or consolidated; no indexes or relationships were changed.** The database is small: the metadata snapshot reports 1,015,808 allocated data bytes plus 1,032,192 index bytes (about 1.95 MiB total, excluding server/redo/undo storage). Table deletion offers little demonstrated benefit.

| Area | Tables | Finding and proposal |
|---|---|---|
| Authentication and auditing | `users`, `csrf_tokens`, `activity_logs` | Active login, OTP, lockout, events CSRF, and admin logging dependencies. Preserve records. Add targeted indexes after backup validation. Token expiry cleanup needs a separate approved retention policy. |
| News, announcements, events | `announcements`, `announcement_images`, `news`, `news_images`, `events`, `categories` | Active admin, public articles, calendars, bulletin boards, recommendations, and chatbot. Empty image galleries still support uploads. Preserve all. |
| Academic pages | `departments`, `programs`, `program_items`, `program_sections`, `basic_education_content`, `department_page_content`, `content_migrations`, `slug_history` | Editors, dynamic rendering, migrations, and redirects use these models. `program_sections` has no literal PHP references, but is linked to `programs`; retain pending feature ownership review. Empty migration/redirect tables still support workflows. |
| Visual editor and homepage | `pages`, `page_sections`, `hero_slides`, `homepage_text`, `homepage_president`, `media_library` | Preserve drafts, published content, review metadata, media links, and editor settings. Avoid restructuring JSON or key/value content in this phase. |
| Administration and offices | `admin_sections`, `admin_people`, `admin_offices`, `offices`, `office_services` | `offices` is queried by the chatbot legacy directory fallback (`chatbot/inc/retriever.php:572`). It is not unused. `office_services` has no literal PHP references but depends on `offices`; retain pending external/future-feature review. |
| Facebook and FAQs | `fb_posts_cache`, `fb_api_call_log`, `faqs` | Cache retrieval, API accounting, and chatbot FAQ queries are present. Retain even when empty. |
| Analytics | `page_visits`, `page_visits_daily`, `site_visitors` | Different measures: per-page totals, daily page views, and unique visitor/day records. They are not interchangeable duplicate tables. |
| Student content | `student_achievements`, `student_achievement_photos`, `student_organizations`, `student_org_categories`, `student_org_gallery`, `student_org_page_content` | Active recently added features. Existing listing/gallery indexes already support several important query patterns. Preserve. |
| Tuition | `tuition_fee_content`, `tuition_fee_documents` | Existing editor/public document feature. Empty tables are intentional and must remain. |

Potential column redundancy requires further investigation, not removal:

- `news.category` and `news.category_id` are not proven equivalent: the news editor filters the string category while relational categories support other content models. Keep both.
- Media IDs and legacy image paths coexist intentionally; for example `admin_offices` rendering uses `COALESCE(m.file_path, o.image_path)`. Removing fallback paths could hide existing images.
- `page_sections.content` and `draft_content` implement publishing separation. Approval fields implement review state. Preserve them.
- Program JSON, program items, department text fields, and department key/value content serve overlapping but distinct rendering/editor paths. No normalization rewrite until field-level ownership and migration equivalence are demonstrated.
- No column has been established as unused. Generic `SELECT *`, JSON payloads, dynamic SQL identifiers, fallback readers, and external integrations prevent deletion decisions based on string search alone.

## Measured baseline and proposed indexes

These are single-run local observations, in milliseconds. They are query-plan evidence, not statistically reliable latency measurements. No after-migration measurements exist because nothing has been applied.

| Query | Exact local rows | Observed plan / elapsed root time | Proposed candidate |
|---|---:|---|---|
| Events CSRF verification, absent synthetic token | 353 tokens | Scans 353 rows; about 0.154 ms | `csrf_tokens(user_id, token, expires_at)` |
| Latest activity logs, 50 rows | 850 logs | Scans 850 logs, joins users, sorts; about 0.873 ms | `activity_logs(created_at)`; verify whether join strategy uses it |
| Published news by absent synthetic slug | 1 news record | Full scan; about 0.0139 ms | `news(slug)` |
| Program bulletin upcoming events | 13 events | Full scan plus sort; about 0.0607 ms | `events(status, start_date)`; compare with `(status, program, start_date)` for exact-program calendar workloads |

Source queries: `admin/events.php:35`, `admin/admin-functions.php:179`, `admin/admin-functions.php:1142`, and `components/bulletin-board.php:151`. The news baseline projects the ID only; repeat the complete production SELECT with its joins during staging benchmarks. Exact counts and the metadata estimates differ because InnoDB estimates are approximate and the site can continue writing during this read-only audit; this is not evidence of lost data.

Priority 1, after verified backup and staging restore:

1. Add the CSRF verification index and activity timestamp index; validate complete query plans and insert overhead.
2. Add nonunique `news(slug)` and `announcements(slug)` lookup indexes. Aggregate local checks found zero duplicate-slug groups, but production must be checked separately. Do not introduce uniqueness without checking collation equivalence, URL behavior, and concurrent slug allocation.
3. Benchmark `events(status, start_date)` on realistic event volume. Program `OR 'ALL'` filtering may still require extra filtering; an index with program before date does not necessarily deliver globally ordered results across both programs.

Priority 2, only when query volume justifies additional indexes:

- `announcements(dept_id, status, created_at)` for `publicDepartmentAnnouncements()` in `components/department-public.php:184`.
- `announcements(status, created_at)` and `news(status, created_at)` for filtered admin lists; consider `(user_id, created_at)` separately for Content Editor ownership lists. Do not add all combinations speculatively.
- `fb_posts_cache(created_time)` for recent feed retrieval in `admin/admin-functions.php:1351` and the chatbot.
- Index on `csrf_tokens(expires_at)` only if approved expiry maintenance needs it. Locally 351 of 353 tokens were expired at observation time. Expired tokens are cleanup candidates; **no token records were deleted**.

Keep current FK-supporting and unique indexes. `page_sections.fk_ps_page(page_id)` overlaps the leading column of `uniq_page_section_key(page_id, section_key)`, making it a redundancy candidate. Retain it initially; confirm InnoDB constraint support and query plans before any separately approved removal. Do not remove indexes merely because they appear overlapping.

Use named, versioned index migrations with schema preconditions, exact index-definition checks, and inverse operations that remove only indexes created by that migration. MySQL DDL can implicitly commit, so a surrounding transaction is not a complete rollback mechanism. See [MySQL implicit commit documentation](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html).

`ORDER BY COALESCE(published_at, created_at)` and leading-wildcard `%term%` searches need separate treatment: ordinary indexes on the underlying dates or text do not automatically eliminate sorting or substring scans. Preserve publication fallback behavior; test expression indexing only if supported and measurably useful on the verified runtime. FULLTEXT changes matching semantics, so it is not a transparent replacement for the existing search.

## Relationships and schema maintenance

There are 38 existing exported foreign keys. Potential missing links include `announcements.featured_image`, `page_sections.media_id`, program hero/seal media, administration media/section keys, and `page_visits_daily.page_id`. These are investigation candidates rather than immediate constraint additions.

Before adding any relationship: run an orphan count, check matching types/signedness/collations, identify every write/delete path, choose explicit deletion behavior, and verify the change on staging. Preserve or repair orphan records only under an approved data policy; never delete them to make a constraint pass. `slug_history.target_id` is polymorphic and should not receive a naive FK to a single target table.

The PHP backend contains runtime schema creation and modification in `components/page-analytics.php`, `components/visitor-counter.php`, `admin/basic-education-content.php`, and `admin/admin-functions.php` (homepage text and program item type fallback). Plan to move these to versioned installation/deployment migrations after compatibility tests. A request-time `CREATE TABLE IF NOT EXISTS` can still affect transaction boundaries; migrations should run before feature requests and before using a restricted application DB account. This is a later, separate compatibility change.

## Proposed automatic backup and rollback system

This is the design for implementation, not an installed or tested service.

### Backup creation and validation

1. Automatically create a fresh complete backup before every migration or approved restore. Use a dedicated privileged maintenance worker; do not grant dump/restore privileges to normal public requests. Select the runtime database explicitly rather than assuming the Compose name is production.
2. Store backups **outside the Apache document root, outside OneDrive/project sync, and outside source control**, under restricted filesystem permissions. The repository's `database/` folder is inside the mounted web root; do not store new sensitive dumps there. Review existing dump accessibility separately without moving or deleting user files in this task.
3. Use immutable filenames such as `gnc_admin_20261020T120000Z_<random-id>.sql`, created exclusively without overwriting. Write to a unique partial file, verify process success, then atomically finalize it. Record timestamps in UTC and display Asia/Manila in the admin view. Encrypt stored backup data and protect encryption keys separately.
4. Use a version-compatible `mysqldump` with explicit structure and data, views, `--routines`, `--triggers`, `--events`, `--hex-blob`, `--quick`, and an appropriate transaction/locking strategy. All observed local tables are InnoDB, so `--single-transaction` is suitable only with concurrent DDL blocked. Recheck production engines, GTID settings, required privileges, and consistency conditions first. Do not include database-selection directives that could redirect a staging import into the original schema. See [mysqldump documentation](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html) and [stored program options](https://dev.mysql.com/doc/refman/8.0/en/mysqldump-stored-programs.html).
5. Freeze application writes when collecting a comparable row/data manifest; separate counts collected at different times do not prove dump consistency. Capture schema version, server version, encoding/collation, SQL mode, object list, per-table counts, and deterministic chunked data hashes from a consistent source. Keep original bytes immutable.
6. Record size and SHA-256; protect manifest authenticity with a separately secured signature/HMAC or equivalent trusted catalog. A checksum alone proves neither provenance nor restorability.
7. Restore into an isolated MySQL test instance with no production credentials or network side effects. Disable event scheduling while validating, inspect DEFINER/security context, and validate all objects, row counts, data hashes, auto-increment state, and FK orphans. Reenabling FK checks alone does not validate records imported while checks were disabled. A dump header or successful export is insufficient. Mark usable only after a successful restore drill and application checks.
8. Preserve the original database and all original/pre-change backups throughout validation. No automatic retention deletion during this project. Uploaded files are separate from MySQL; preserve a coordinated media snapshot and application revision for full website recovery.

### Administrator control and restore execution

- Add a dedicated `manage_database_backups` permission granted only to the existing **Super Admin** role. No database-maintenance permission currently exists in `getPermissions()`. On every maintenance request, re-read the user's active status and current role from `users`; a session role alone is insufficient.
- Require recent reauthentication, POST, CSRF validation, and explicit typed confirmation naming the backup and destination database. Display: **“Restoring this backup replaces database structure and records with the selected version. Newer changes may be lost. A backup of the current database will be preserved first.”** Never initiate restore on page load, GET, an application error, or a scheduler.
- Select only immutable, validated backup IDs from the trusted catalog. Reject arbitrary paths and SQL uploads; enforce containment after canonical path resolution and reject symlinks. Recheck integrity and provenance before restoration.
- Acquire a single maintenance lock, reject concurrent jobs, enter maintenance mode, and stop web/API/background writers, including analytics, Facebook refresh, and migrations. Recheck authorization and destination immediately before execution.
- Create and validate a complete **pre-restore backup of the current database** before proceeding. Abort if this fails. Record the initiating administrator and approval in an external append-only maintenance log, because restoring the application DB can erase its own audit records.
- Restore the chosen backup to a fresh replacement database/isolated instance first and validate it. Do not overlay SQL onto a populated database: that can retain tables absent from the backup or leave mixed state after a partial import. For definers/routines/views with schema-qualified names, use a separately isolated instance or a verified schema-aware process, not blind string replacement.
- After validation and the still-valid explicit approval, switch the configured database target under maintenance mode, reconnect workers, clear derived caches, invalidate sessions, and run smoke checks. MySQL does not provide a general atomic whole-database rename; do not promise transactional cutover. Keep the previous database intact. If validation or cutover fails, leave maintenance mode engaged and report the failure; do not automatically restore or switch versions without administrator approval.
- Track job states and failures durably; fail closed on interruption, corruption, missing objects, disk exhaustion, or timeout. A web request should enqueue the privileged worker, not execute an unbounded shell restore inline. Use structured process arguments and protected credentials, never shell interpolation of user input or passwords in command arguments.

## Staging verification and performance acceptance

Use a separate database/server cloned from a fresh validated backup. Point only the staging app to it; production credentials must be unavailable to the test runner. Disable real email, Facebook writes, scheduled jobs, and other external side effects. Do not run existing integration tests against the original database: they can create, edit, or delete content.

| Feature group | Required checks |
|---|---|
| Login/authentication | Each role; correct/incorrect password; OTP expiry; lockout; logout; remember token; inactive user; permission denial |
| Content CRUD | Announcements/news/events create, edit, publish, archive, delete, pin; categories; authors; media/gallery ordering; slug lookup |
| Dashboard/review | Counts, ownership restrictions, pending approvals, accept/reject, drafts remain private |
| Editors | Basic education, department/program pages, rich text, visual editor draft/publish, media/PDF upload, ordering, archived records |
| Public pages | Dynamic department/program URLs, legacy redirects, articles, calendar filters, bulletin boards, navigation, homepage |
| Other DB consumers | Chatbot FAQ/office/content retrieval, recommendations, Facebook cache reads, analytics counters, achievements, organizations, tuition documents |
| Maintenance authorization | Unauthenticated/inactive/lower-role denial, revoked role, stale session, CSRF, reauthentication, missing confirmation |
| Backup/restore failures | Truncated/tampered dump, wrong schema/server, path traversal, disk full, concurrent jobs, interrupted import, failed pre-restore backup; original DB remains intact |
| Restore correctness | Structure AND records restored, extra newer tables absent from restored target, deleted rows recovered, newer rows removed only after approval, media available, sessions invalidated |

Existing tests cover many editors and public consumers under `tests/`, including calendar filters, rich text, basic education migration, organizations, achievements, and tuition. Review their setup and cleanup before selecting commands. Login, complete backup/restore, and failure-injection coverage must be added where absent. No feature suite was run in this analysis, and feature compatibility has not yet been certified.

Before and after each candidate migration, run identical full queries and representative page requests on the same staging dataset (including realistic growth data). Warm up, then collect at least 30 samples per scenario: median/p95 latency, rows examined, chosen index, sort/temp work, total DB calls, and write cost. Include published/unpublished, exact-program/ALL, authorship filters, and searched lists. Keep changes only with useful measured benefit and passing compatibility checks. Small-table full scans can be optimal. Do not manufacture a speedup percentage from the single-run baseline.

Validate backup/restore separately by changing a fixture's structure and data, restoring it, and checking the complete expected prior state. Run PHP lint using the deployed PHP version, review PHP/MySQL errors, compare all original data manifests, and check FK orphans explicitly.

## Deployment and rollback procedure

1. Finish fresh backup creation and successful isolated restore drill. Keep the original database unchanged.
2. Implement the maintenance worker and restricted administrator controls; test the failure cases above on isolated databases.
3. Apply only selected additive index migrations to staging; run compatibility and before/after benchmarks. Produce the final migration list, inverse migration list, backup identifier, restore evidence, and measured report.
4. Present any proposed table/column/index removal, record cleanup, or new restrictive constraint separately for explicit approval. None is proposed for execution now. Audit external tools, cron jobs, stored objects, and future-feature ownership before recommending deletion.
5. Once staging passes and deployment is authorized, create and validate another fresh pre-deployment backup, schedule maintenance, apply verified migrations with bounded lock waits, and monitor errors and latency. Never trial migrations directly on production.
6. For index-only regression, prefer the reviewed inverse index migration; it preserves newer data. For full recovery, use the administrator-confirmed restore workflow above, including a fresh pre-restore backup and explicit warning about newer data. Never automatically restore an old snapshot over live data.

The first implementation batch should be the backup/restore foundation plus CSRF/log/slug index experiments on staging. Defer removals, record purges, normalization changes, and new constraints until separately justified and approved.
