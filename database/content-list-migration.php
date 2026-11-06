<?php
// Maintenance library: never execute migrations from HTTP requests.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/normalized-content-lists.php';

function contentListLegacyColumnExists(PDO $db, array $definition): bool {
    $statement = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    $statement->execute([$definition['parent'], $definition['legacy']]);
    return (bool)$statement->fetchColumn();
}

function contentListMigrationApply(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Run migrations outside application transactions.');
    if (contentListsEnabled($db, true)) return;
    // DDL is deliberately separate: MySQL implicitly commits CREATE/ALTER TABLE.
    $db->exec("CREATE TABLE IF NOT EXISTS content_migrations (
        migration_key VARCHAR(100) NOT NULL PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS admin_office_services (
        office_id INT NOT NULL,
        display_order INT UNSIGNED NOT NULL,
        service_name TEXT NOT NULL,
        PRIMARY KEY (office_id, display_order),
        CONSTRAINT normalized_office_services_parent FOREIGN KEY (office_id)
            REFERENCES admin_offices(office_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS student_achievement_achievers (
        achievement_id INT UNSIGNED NOT NULL,
        display_order INT UNSIGNED NOT NULL,
        achiever_name TEXT NOT NULL,
        PRIMARY KEY (achievement_id, display_order),
        CONSTRAINT normalized_achievement_achievers_parent FOREIGN KEY (achievement_id)
            REFERENCES student_achievements(achievement_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    contentListTransaction($db, static function () use ($db): void {
        foreach (['office_services', 'achievement_achievers'] as $kind) {
            $definition = contentListDefinition($kind);
            if (!contentListLegacyColumnExists($db, $definition)) {
                throw new RuntimeException('A required source column is missing; no migration marker was written.');
            }
            $rows = $db->query("SELECT {$definition['id']}, {$definition['legacy']} FROM {$definition['parent']} ORDER BY {$definition['id']} FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) contentListReplace($db, $kind, (int)$row[$definition['id']], (string)$row[$definition['legacy']]);
            // Compare ordered values for every parent before activating the new readers.
            $statement = $db->prepare("SELECT {$definition['value']} FROM {$definition['table']} WHERE {$definition['id']}=? ORDER BY display_order");
            foreach ($rows as $row) {
                $statement->execute([$row[$definition['id']]]);
                if ($statement->fetchAll(PDO::FETCH_COLUMN) !== contentListLines((string)$row[$definition['legacy']])) {
                    throw new RuntimeException('List backfill verification failed.');
                }
            }
        }
        $db->prepare('INSERT INTO content_migrations (migration_key) VALUES (?)')->execute([GNCI_CONTENT_LISTS_MIGRATION]);
    });
    contentListsEnabled($db, true);
}

/** Only after deploying compatible PHP, verified backup, maintenance and smoke checks. */
function contentListMigrationFinalize(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Run migrations outside application transactions.');
    if (!contentListsEnabled($db, true)) throw new RuntimeException('Apply and verify the list migration first.');
    foreach (['office_services', 'achievement_achievers'] as $kind) {
        $definition = contentListDefinition($kind);
        if (contentListLegacyColumnExists($db, $definition)) {
            $db->exec("ALTER TABLE {$definition['parent']} DROP COLUMN {$definition['legacy']}");
        }
    }
}

/** Recreate legacy text from CURRENT child rows, including edits made since migration. */
function contentListMigrationRollback(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Run migrations outside application transactions.');
    if (!contentListsEnabled($db, true)) return;
    foreach (['office_services', 'achievement_achievers'] as $kind) {
        $definition = contentListDefinition($kind);
        if (!contentListLegacyColumnExists($db, $definition)) {
            $db->exec("ALTER TABLE {$definition['parent']} ADD COLUMN {$definition['legacy']} TEXT NULL");
        }
    }
    contentListTransaction($db, static function () use ($db): void {
        foreach (['office_services', 'achievement_achievers'] as $kind) {
            $definition = contentListDefinition($kind);
            $parents = $db->query("SELECT {$definition['id']} FROM {$definition['parent']} ORDER BY {$definition['id']} FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
            $parents = contentListsHydrate($db, $kind, $parents);
            $update = $db->prepare("UPDATE {$definition['parent']} SET {$definition['legacy']}=?,updated_at=updated_at WHERE {$definition['id']}=?");
            foreach ($parents as $row) $update->execute([$row[$definition['legacy']], $row[$definition['id']]]);
        }
        $db->prepare('DELETE FROM content_migrations WHERE migration_key=?')->execute([GNCI_CONTENT_LISTS_MIGRATION]);
    });
    contentListsEnabled($db, true);
    // Retain child tables for recovery. Reapply replaces their rows from legacy text.
}
