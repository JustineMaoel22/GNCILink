<?php
/** Additive upgrade for installations that already migrated event categories. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/event-categories.php';
$db = getDB();
if (eventCategoryTable($db) !== 'event_categories') throw new RuntimeException('Run migrate-event-categories.php --apply first.');
$db->beginTransaction();
try {
    $find = $db->prepare('SELECT category_id FROM event_categories WHERE category_name=? FOR UPDATE');
    $find->execute(['Alumni']);
    if ($find->fetchColumn()) {
        $db->prepare('UPDATE event_categories SET is_active=1 WHERE category_name=?')->execute(['Alumni']);
    } else {
        $db->prepare('INSERT INTO event_categories(category_name,category_color,is_active) VALUES(?,?,1)')->execute(['Alumni','#6F42C1']);
    }
    $db->commit();
    echo "Alumni is available in Events. Existing IDs and colors preserved.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
