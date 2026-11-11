<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database/migrate-event-categories.php';
$db = getDB();
$original = $db->query('SELECT DATABASE()')->fetchColumn();
$schema = 'gnci_event_categories_test_' . bin2hex(random_bytes(6));
if (!preg_match('/^gnci_event_categories_test_[a-f0-9]{12}$/D', $schema)) throw new RuntimeException('Invalid test schema.');
$db->exec("CREATE DATABASE `$schema`");
function eventCategoryAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
try {
    $db->exec("USE `$schema`");
    $db->exec('CREATE TABLE categories(category_id INT PRIMARY KEY,category_name VARCHAR(150),category_color VARCHAR(7),description TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $db->exec("INSERT INTO categories(category_id,category_name,category_color) VALUES(1,'School Event','#1F5E2C'),(2,'Alumni','#123456'),(3,'Holiday','#EABA3B'),(4,'Achievements','#abcdef')");
    $db->exec('CREATE TABLE events(event_id INT PRIMARY KEY,category_id INT NULL,CONSTRAINT fk_evt_cat FOREIGN KEY(category_id) REFERENCES categories(category_id) ON DELETE SET NULL) ENGINE=InnoDB');
    $db->exec('INSERT INTO events VALUES(1,1),(2,2),(3,NULL)');
    $before = $db->query('SELECT * FROM events ORDER BY event_id')->fetchAll();
    $shared = $db->query('SELECT * FROM categories ORDER BY category_id')->fetchAll();
    migrateEventCategories($db, '--apply');
    migrateEventCategories($db, '--apply');
    eventCategoryAssert(eventCategoryTable($db) === 'event_categories', 'Dedicated table was not activated.');
    eventCategoryAssert($before === $db->query('SELECT * FROM events ORDER BY event_id')->fetchAll(), 'Event assignments changed.');
    eventCategoryAssert($shared === $db->query('SELECT * FROM categories ORDER BY category_id')->fetchAll(), 'Shared categories changed.');
    eventCategoryAssert(count(eventCategoryChoices($db)) === 5, 'Expected five selectable event types.');
    $legacy = $db->query('SELECT c.category_name,c.category_color FROM events e JOIN event_categories c ON c.category_id=e.category_id WHERE event_id=2')->fetch();
    eventCategoryAssert($legacy['category_name'] === 'Alumni' && $legacy['category_color'] === '#123456', 'Legacy labels/colors lost.');
    validateEventCategory($db, 2, 2);
    foreach ([4, 999] as $invalid) {
        try { validateEventCategory($db, $invalid); throw new RuntimeException('Invalid category accepted.'); }
        catch (InvalidArgumentException $expected) {}
    }
    validateEventCategory($db, 1);
    validateEventCategory($db, 2); // Alumni is available for new events too.
    validateEventCategory($db, null);
    migrateEventCategories($db, '--rollback');
    eventCategoryAssert(eventCategoryTable($db) === 'categories', 'Rollback failed.');
    eventCategoryAssert($before === $db->query('SELECT * FROM events ORDER BY event_id')->fetchAll(), 'Rollback changed events.');
    echo "PASS: dedicated categories, preserved IDs/labels/colors, event validation, idempotent apply and rollback.\n";
} finally {
    $db->exec('USE `' . str_replace('`', '``', $original) . '`');
    // Only the freshly created, strictly validated test schema is removed.
    $db->exec("DROP DATABASE `$schema`");
}
