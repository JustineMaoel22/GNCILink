<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/event-categories.php';
function migrateEventCategories(PDO $db, string $mode = '--check'): void {
if (!in_array($mode, ['--check', '--apply', '--rollback'], true)) throw new InvalidArgumentException('Use --check, --apply or --rollback.');
$constraints = $db->query("SELECT CONSTRAINT_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events' AND COLUMN_NAME='category_id' AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
if (count($constraints) !== 1 || !in_array($constraints[0]['REFERENCED_TABLE_NAME'], ['categories','event_categories'], true)) throw new RuntimeException('Expected one known Events category foreign key. No changes made.');
$source = $constraints[0]['REFERENCED_TABLE_NAME'];
if ($mode === '--check') {
    echo "Events currently reference $source. Run --apply during a maintenance window after a database backup.\n";
    return;
}
$target = $mode === '--apply' ? 'event_categories' : 'categories';
if ($source === $target) { echo "Events already reference $target. No changes made.\n"; return; }
if ($mode === '--apply') {
    $backupPath = tempnam(sys_get_temp_dir(), 'gnci-event-categories-');
    if ($backupPath === false) throw new RuntimeException('Could not create the category migration backup.');
    chmod($backupPath, 0600);
    $backup = json_encode([
        'database' => $db->query('SELECT DATABASE()')->fetchColumn(),
        'events_schema' => $db->query('SHOW CREATE TABLE events')->fetch(PDO::FETCH_NUM)[1],
        'categories_schema' => $db->query('SHOW CREATE TABLE categories')->fetch(PDO::FETCH_NUM)[1],
        'events' => $db->query('SELECT * FROM events')->fetchAll(PDO::FETCH_ASSOC),
        'categories' => $db->query('SELECT * FROM categories')->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
    if (file_put_contents($backupPath, $backup, LOCK_EX) !== strlen($backup)) throw new RuntimeException('Could not write the category migration backup.');
    echo "Private pre-migration snapshot: $backupPath\n";
    $db->exec(file_get_contents(__DIR__ . '/event-categories.sql'));
    if ((int)$db->query('SELECT COUNT(*) FROM event_categories')->fetchColumn() !== 0) throw new RuntimeException('Unactivated event_categories contains data. Review before retrying; no existing rows overwritten.');
    $db->beginTransaction();
    try {
        // IDs also preserve pending approval/import snapshots and old event assignments.
        $db->exec("INSERT INTO event_categories (category_id,category_name,category_color,description,is_active,created_at) SELECT category_id,category_name,category_color,description,CASE WHEN category_name IN ('School Event','School Activities','Holiday','Suspension','Alumni') THEN 1 ELSE 0 END,created_at FROM categories");
        $find = $db->prepare('SELECT category_id FROM event_categories WHERE category_name=?');
        $insert = $db->prepare('INSERT INTO event_categories(category_name,category_color) VALUES(?,?)');
        foreach (['School Event'=>'#1F5E2C','School Activities'=>'#2E6F9E','Holiday'=>'#EABA3B','Suspension'=>'#B3312D','Alumni'=>'#6F42C1'] as $name=>$color) {
            $find->execute([$name]);
            if (!$find->fetchColumn()) $insert->execute([$name,$color]);
        }
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}
// Rollback refuses mismatched IDs instead of changing category meanings or deleting data.
$missing = (int)$db->query("SELECT COUNT(*) FROM events e LEFT JOIN $target c ON c.category_id=e.category_id WHERE e.category_id IS NOT NULL AND c.category_id IS NULL")->fetchColumn();
if ($missing) throw new RuntimeException('Target catalog lacks assigned category IDs. No foreign key changed.');
if ($mode === '--rollback') {
    $changed = (int)$db->query('SELECT COUNT(*) FROM event_categories ec JOIN categories c ON c.category_id=ec.category_id WHERE BINARY ec.category_name<>BINARY c.category_name')->fetchColumn();
    if ($changed) throw new RuntimeException('Category meanings differ. Review mappings before rollback.');
}
$constraint = '`' . str_replace('`', '``', $constraints[0]['CONSTRAINT_NAME']) . '`';
$newConstraint = $target === 'event_categories' ? 'fk_evt_event_category' : 'fk_evt_cat';
$db->exec("ALTER TABLE events DROP FOREIGN KEY $constraint, ADD CONSTRAINT $newConstraint FOREIGN KEY (category_id) REFERENCES $target(category_id) ON DELETE SET NULL");
echo "Events now reference $target. Event IDs, assignments and shared categories were preserved.\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) migrateEventCategories(getDB(), $argv[1] ?? '--check');
