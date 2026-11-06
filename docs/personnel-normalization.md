# Shared personnel identities

`personnel` owns a person's canonical `full_name`. The following placements hold a nullable `personnel_id` foreign key referencing it:

| Placement | Foreign-key column | Compatibility name |
| --- | --- | --- |
| `admin_people` | `personnel_id` | `name` |
| `admin_offices` | `personnel_id` | `head_name` |
| `departments` | `personnel_id` | `dean_name` |
| `homepage_president` | `personnel_id` | `name` |

Each existing named placement starts with its own identity. The migration never merges matching names or guesses from photographs. Unnamed placements remain unassigned. Names need not be unique: different people may have the same name.

Roles, biographies, office contact details, photographs, cropping, ordering, archives, publication state and public URLs stay on their existing placements. Those values can legitimately differ by section. The source of a shared name is `personnel`, with existing name columns retained as compatibility mirrors for older readers. This is an additive migration; no old columns or records are dropped. It does not claim to remove every physical duplication in the schema.

## Editing and review

Use `/admin/personnel.php`, also linked from Administration's More settings panel, to review identities. Expand a profile, choose an existing person, compare their names/photos, explicitly confirm the assignment and save. Only Super Admin can link identities. Content Editors can view the directory and must request a review. A link uses the selected identity's name; it does not copy or replace photographs or contact details. Select **Create a separate person** to undo an incorrect association. Old, unused identity rows are retained for recovery and audit.

Once linked, editing a name through any supported personnel save updates the canonical name and compatibility mirrors for every linked placement in a single transaction. A failed parent save rolls back the entire change, including the shared name and audit entry. Old media cleanup is deferred until the enclosing transaction has ended and references are checked again, so a rollback cannot delete a previously published portrait. Optional names can still be cleared: that placement is unassigned rather than erasing the shared identity. The old portrait checks still require a name when keeping a portrait.

Normal create/edit handlers keep their existing payloads and permissions. New placements create separate identities. Super Admin linking is authenticated, authorized, CSRF-protected and audited, and uses the existing publication-password confirmation because it can change a public name. Name changes are audited centrally as well as through the original profile activity. Refresh other open editors after an identity link or shared rename to avoid submitting an older name.

After migration, face comparison excludes other placements with the **same personnel ID**, instead of assuming equal names establish identity. Same-name people with different IDs remain candidates for screening. If one person already appears across several sections, a Super Admin should review and link those placements. No embeddings are stored by normalization or by the existing one-shot face worker.

## Local deployment

Read-only status:

```sh
php database/migrate-personnel.php
php database/migrate-normalized-content.php
```

1. Verify the intended host/database. Back up the entire database and uploaded assets outside the web root, and restore-test the database in an isolated schema. Preserve compatible application code. Stop all web, admin, import and background writers. Do not run old and new PHP writers across cutover.
2. Deploy `includes/personnel.php`, `includes/personnel-saves.php`, modified readers/save functions/face checker, the review page and its link together.
3. While writes are stopped, run:

   ```sh
   php database/migrate-personnel.php --apply --maintenance --backup-verified
   ```

   DDL is additive and precedes the transaction because MySQL implicitly commits schema changes. Backfill, exact-name verification and the `shared-personnel-v1` marker commit together. Partial DDL can remain after failure; existing readers remain active until the marker commits. Re-running apply does not merge identities or overwrite reviewed links. Foreign keys restrict deletion of referenced identities.

4. Check counts, every named placement's reference, canonical/mirror equality, role restrictions, editor/public readers and face checks. Restart fresh web/background workers and resume traffic only after verification.

For this repository's local Docker setup, `scripts/backup-local-normalization.py --directory <private-outside-web-root-path>` stops `web` and `phpmyadmin`, dumps the configured database, restores it into its own randomly named schema, compares every base table's exact row count, data hash and schema hash, and verifies an upload ZIP against the source files. It removes only its generated restore-test schema. Successful backup leaves those services stopped. A backup failure resumes the unchanged services. It requires local Docker database host `db`, a backup directory that does not already exist, and either no scheduled events in the source schema or the MySQL event scheduler OFF. It never changes the global scheduler setting or prints dump contents or credentials. Protect the private backups, which contain account and other private data.

While services are stopped, use `docker compose run --rm --no-deps -T --user www-data web php database/migrate-personnel.php --apply --maintenance --backup-verified`. Start the existing containers with `docker compose start web phpmyadmin` after verification. Stopping those containers covers this project's known writers; separately stop any external client/import jobs.

The earlier office-service and achievement-name migration is independent. If already enabled, keep its data as-is. Its retained legacy columns must not be used as authoritative lists. Removing them is a separate destructive finalize step, not required to enable personnel identities.

## Rollback

With writers stopped and a verified backup, run the compatible code:

```sh
php database/migrate-personnel.php --rollback --maintenance --backup-verified
```

Rollback copies **current** canonical names into the placement mirrors and removes only the activation marker. It keeps all identity rows, references, reviewed links and photos. The compatible PHP then uses legacy names again. Reapply verifies existing links and names before re-enabling normalized readers; it does not create replacement identities for existing references. Never restore an old backup over newer data without a separate reviewed recovery decision.

## Verification

```sh
docker compose exec -T --user www-data web php tests/personnel-normalization-test.php
docker compose exec -T --user www-data web php tests/normalized-content-test.php
```

The tests create isolated, randomly named local schemas with synthetic records and remove only their own test databases. They cover backfill, equal-name separation, idempotence, reviewed linking/unlinking, shared names, transaction failure and outer rollback, canonical readers, authorization, foreign keys, new profiles, department/president saves, identity-based face exclusions, rollback and reapply. They never copy original records, publish fixture content, send messages or remove real media. `node tests/personnel-directory-browser-test.cjs` checks the real review UI and denied requests without changing any identities.

## Local deployment record — 2026-11-06

- List normalization was already enabled. The legacy service/name list columns were retained; no destructive finalize step ran.
- Personnel normalization was applied to the local Docker database: 54 identities were seeded, one for each named placement, with no inferred merges.
- The backup at `%LOCALAPPDATA%/GNCIBackups/personnel-20261106-619189d5/manifest.json` records a verified database restore and verified upload archive. Every original column value across all 72 pre-existing base tables was verified unchanged after migration. Original `updated_at` values are explicitly preserved during backfill.
- Restore schema comparison canonicalizes redundant per-column `CHARACTER SET` declarations when the named collation already specifies the charset; it still checks the collation and all remaining schema details.
- The local website and phpMyAdmin were resumed after verification. No hosted database was migrated.
