# Database normalization and PHP compatibility

Implemented 2026-11-06. This change normalizes two confirmed repeating lists. It
does not certify every CMS table as 3NF or remove fields based on similar names.

Shared personnel identities are a separate additive migration documented in
[personnel-normalization.md](personnel-normalization.md). The local database has
both list normalization and shared personnel enabled; the legacy list columns
remain retained for compatibility/recovery. No finalize/drop step was performed.

| Former storage | Relational storage | Key / relationship |
| --- | --- | --- |
| `admin_offices.services`, one service per line | `admin_office_services(office_id, display_order, service_name)` | Composite primary key; parent FK with `ON DELETE CASCADE` |
| `student_achievements.achievers`, one name per line | `student_achievement_achievers(achievement_id, display_order, achiever_name)` | Composite primary key; parent FK with `ON DELETE CASCADE` |

Entries are owned by their parent and retain order and duplicate labels. Achiever
names are display names, not unique student identities: identical names must not
be merged into a shared person record. Blank lines and surrounding whitespace
are removed as in the existing display logic. Parent IDs, photos, publication
states, archive status, and URLs do not change.

`includes/normalized-content-lists.php` adapts relational rows to the existing
newline text payload used by forms and previews. Administration rendering,
achievement listings/details/featured cards/editor previews, and office chatbot
retrieval use it. Saves update the parent and children atomically, using savepoints
inside existing approval/editor transactions. Database errors are propagated,
rather than reporting success with only half a save completed. Child rows are
batch-read without `GROUP_CONCAT`, avoiding its truncation limit.

## Deployment

Use the PHP CLI against the intended environment. No migration runs on HTTP
requests. The flags below acknowledge operational prerequisites; the script
cannot verify a backup's restorability or enforce a site-wide write freeze.

1. Back up the full database and uploaded files outside the web root, and verify
   restoration on an isolated database. Preserve the previous PHP revision.
2. Stop web, admin, import, scheduled and other database writers. Deploy all
   changed PHP files together. Do not allow old and new workers to write during
   cutover, or keep long-running PHP workers connected across the migration.
3. Inspect status (read only):

   ```sh
   php database/migrate-normalized-content.php
   ```

4. Backfill and enable normalized reads/writes:

   ```sh
   php database/migrate-normalized-content.php --apply --maintenance --backup-verified
   ```

   Backfill and the `content_migrations` marker commit together after ordered
   values have been compared for every parent. A failed backfill leaves the old
   readers active; empty child tables may remain from the DDL step. Rerunning
   apply after success preserves newer child edits.

5. Test admin add/edit/clear/archive/delete, public office services, achievement
   names in cards/dialogs/editor preview, and chatbot office retrieval.
6. Once verified, while writes remain stopped, remove the old physical columns:

   ```sh
   php database/migrate-normalized-content.php --finalize --maintenance --backup-verified
   ```

   Until finalize, old columns are retained solely as pre-migration snapshots;
   updated PHP reads/writes only child rows. These snapshots become stale and
   must never be used as a fallback once the migration marker is present. Empty
   lists remain empty. Finalize is restartable if interrupted between its two
   `ALTER TABLE` operations. MySQL DDL implicitly commits; a database transaction
   cannot undo those column removals.

7. Start fresh web/background workers and resume traffic. Restrict the application
   DB user to its normal permissions; schema changes belong to maintenance CLI.

## Rollback

With writes stopped, run the compatible migration code **before** reverting PHP:

```sh
php database/migrate-normalized-content.php --rollback --maintenance --backup-verified
```

This recreates removed legacy columns, rebuilds them from **current** ordered
child rows (including edits made since deployment), and removes the marker in
one data transaction. It leaves child tables available for recovery. Deploy the
previous PHP revision and restart workers only after rollback succeeds. Applying
again later replaces child rows from the then-current legacy values. Rollback
restores normalized display text, not original blank lines/line endings; retain
the original backup if exact original bytes are required.

## Retained structures and boundaries

- Announcement/category and audience junction tables already model many-to-many
  selections. Existing primary/legacy columns and synchronization stay intact
  for public filters, calendars, approvals and other compatibility readers.
  Their removal is a separate application-wide migration, not part of this change.
- `news.category` is used for news-specific classifications; similarity to
  `category_id` alone is insufficient evidence to merge them.
- Media ID plus image path supports existing fallback images. Different image
  roles (cover, thumbnail, hero, seal) also remain separate.
- CMS JSON/rich text, drafts, layout settings and import/approval payloads are
  documents or workflow snapshots. They are not automatically normalization
  defects and are not decomposed here.
- `offices` and `admin_offices` remain separate directory paths. The active
  `admin_office_services` child references `admin_offices`. The old, empty
  physical `office_services` child was removed in the separately reviewed local
  [table cleanup](unused-table-cleanup.md); the logical list kind with that name
  still maps to `admin_office_services`.
- Foreign-key integrity for other tables is unchanged. Do not add broad cascade
  rules or delete orphan records just to make an ER diagram more connected.

## Verification

```sh
php tests/normalized-content-test.php
```

The integration test requires local MySQL with permission to create an isolated
schema. It copies only table definitions, inserts synthetic fixtures, and removes
only its randomly named test database. It exercises the legacy schema, verified
backfill, idempotence, Unicode/order/duplicate preservation, empty/omitted lists,
parent/child failure atomicity, nested transactions, foreign keys, post-finalize
create/read/update/delete, archival, chatbot retrieval, rollback, and reapply.
It does not call email, Facebook publishing or upload deletion on real assets.

The change does not itself deploy a migration to the original or hosted database.
