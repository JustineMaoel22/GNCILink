<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
// Session-local fixture: no persistent content or category records are changed.
$db->exec('CREATE TEMPORARY TABLE categories(category_id INT PRIMARY KEY,category_name VARCHAR(100),category_color VARCHAR(20))');
$insert = $db->prepare('INSERT INTO categories VALUES(?,?,?)');
$names = ['Alumni', 'Suspension', 'School Event', 'Scholarship', 'School Activities', 'Reminder', 'Job Opportunities', 'Holiday', 'Other', 'School Events', 'Achievements', 'Athletics and Sports', 'Campus Events', 'Community Extension Activity', 'Student Organization'];
foreach ($names as $index => $name) $insert->execute([$index + 1, $name, '#094024']);
$announcements = getAnnouncementCategories($db);
if (array_column($announcements, 'category_name') !== ['Holiday', 'Job Opportunities', 'Reminder', 'Scholarship', 'School Activities', 'School Events', 'Suspension']) throw new RuntimeException('Announcement choices or order differ.');
if ($announcements[5]['category_id'] !== 3) throw new RuntimeException('Legacy School Event ID must be retained.');
$news = getNewsCategories();
$facebook = getFacebookPostCategories($db);
$expectedFacebook = array_merge(array_column($announcements, 'category_name'), array_values($news));
sort($expectedFacebook, SORT_NATURAL | SORT_FLAG_CASE);
if (array_column($facebook, 'category_name') !== $expectedFacebook) throw new RuntimeException('Facebook must include all 13 choices alphabetically without duplicates.');
if (array_column($facebook, 'category_id', 'category_name')['School Events'] !== 3) throw new RuntimeException('Facebook must retain the legacy event ID.');
if (array_values($news) !== ['Achievements', 'Alumni', 'Athletics and Sports', 'Campus Events', 'Community Extension Activity', 'Student Organization']) throw new RuntimeException('News choices or order differ.');
foreach (array_keys($news) as $code) if (normalizeNewsCategory($code) !== $code) throw new RuntimeException('Valid news choice rejected.');
foreach ([null, '', 'Others', 'School News', 'Academic', 'Updates', 'Job Opportunities', 'Scholarship', 'invalid'] as $code) {
    try { normalizeNewsCategory($code); throw new RuntimeException('Invalid or retired choice accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
echo "PASS: exact alphabetical category choices, preserved event ID, accepted News codes and rejected retired/invalid choices. No persistent data changed.\n";
