<?php
/** Rename the shared category in place so existing announcement assignments remain intact. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$update = $db->prepare('UPDATE categories SET category_name = ? WHERE category_name = ?');
$update->execute(['Suspension', 'Emergency']);
$check = $db->prepare('SELECT COUNT(*) FROM categories WHERE category_name = ?');
$check->execute(['Suspension']);
if ((int)$check->fetchColumn() < 1) throw new RuntimeException('Suspension category is missing.');
$check->execute(['Emergency']);
if ((int)$check->fetchColumn() !== 0) throw new RuntimeException('Emergency category still exists.');
echo "Verified: Suspension replaces Emergency; category IDs, colors and assignments retained.\n";
