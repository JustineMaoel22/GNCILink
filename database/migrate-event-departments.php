<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
if (!$db->query("SHOW COLUMNS FROM events LIKE 'dept_id'")->fetch()) {
    $db->exec('ALTER TABLE events ADD COLUMN dept_id INT DEFAULT NULL, ADD INDEX idx_events_department (dept_id)');
}
echo "Event department storage is ready. Existing events are unchanged.\n";
