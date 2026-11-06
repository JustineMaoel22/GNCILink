<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/content-list-migration.php';

$options = getopt('', ['apply', 'finalize', 'rollback', 'maintenance', 'backup-verified']);
$modes = array_intersect(['apply', 'finalize', 'rollback'], array_keys($options));
if (count($modes) > 1) { fwrite(STDERR, "Choose one operation.\n"); exit(1); }
if ($modes && (!isset($options['maintenance']) || !isset($options['backup-verified']))) {
    fwrite(STDERR, "Stop web/background writes and verify a restorable backup first. Then pass --maintenance --backup-verified. See docs/database-normalization.md.\n");
    exit(1);
}
$db = getDB();
$lock = 'gnci-content-lists-' . substr(hash('sha256', (string)$db->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
$locked = false;
try {
    if ($modes) {
        $statement = $db->prepare('SELECT GET_LOCK(?, 0)'); $statement->execute([$lock]);
        $locked = (int)$statement->fetchColumn() === 1;
        if (!$locked) throw new RuntimeException('Another content-list migration is running.');
        $mode = reset($modes);
        if ($mode === 'apply') contentListMigrationApply($db);
        elseif ($mode === 'finalize') contentListMigrationFinalize($db);
        else contentListMigrationRollback($db);
        echo "Operation completed: $mode.\n";
    }
    echo 'Normalized lists: ' . (contentListsEnabled($db, true) ? 'enabled' : 'not applied') . ".\n";
    foreach (['office_services', 'achievement_achievers'] as $kind) {
        $definition = contentListDefinition($kind);
        echo $definition['parent'] . '.' . $definition['legacy'] . ': ' . (contentListLegacyColumnExists($db, $definition) ? 'legacy column retained' : 'legacy column removed') . ".\n";
    }
} catch (Throwable $e) {
    // Do not print SQL errors or connection details to the console.
    fwrite(STDERR, "Content-list migration failed. Keep writes stopped and inspect the private PHP error log.\n");
    error_log('Content-list migration: ' . $e->getMessage());
    exit(1);
} finally {
    if ($locked) { $statement = $db->prepare('SELECT RELEASE_LOCK(?)'); $statement->execute([$lock]); }
}
