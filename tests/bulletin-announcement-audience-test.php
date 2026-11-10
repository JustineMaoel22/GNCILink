<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('CURRENT_PROGRAM', 'BSCE');
require_once __DIR__ . '/../components/bulletin-board.php';
$db = getDB();
// Connection-local fixtures shadow live tables; no published records change.
$db->exec('CREATE TEMPORARY TABLE programs (program_code VARCHAR(20) PRIMARY KEY, dept_id INT, slug VARCHAR(100) NULL, created_at DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE announcements (announcement_id INT PRIMARY KEY, title VARCHAR(100), content TEXT, slug VARCHAR(100), status VARCHAR(20), program VARCHAR(20), dept_id INT NULL, published_at DATETIME NULL, created_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE announcement_programs (announcement_id INT, program_code VARCHAR(20))');
$db->exec('CREATE TEMPORARY TABLE announcement_departments (announcement_id INT, dept_id INT)');
$db->exec("INSERT INTO programs (program_code,dept_id) VALUES ('BSCE',1),('BAEL',2),('BEEd',2),('BSEd',2),('BSIT',3)");
$insert = $db->prepare("INSERT INTO announcements VALUES (?, ?, '<p>Notice</p>', ?, ?, ?, ?, '2026-11-09 12:00:00', '2026-11-09 12:00:00')");
foreach ([
    [1, 'School-wide', 'published', 'ALL', null],
    [2, 'Three CASE programs', 'published', 'ALL', 2],
    [3, 'Engineering department', 'published', 'ALL', 1],
    [4, 'Multiple departments', 'published', 'ALL', null],
    [5, 'Multiple programs without departments', 'published', 'ALL', null],
    [6, 'Legacy Civil Engineering', 'published', 'BSCE', 1],
    [7, 'Legacy other program', 'published', 'BAEL', 2],
    [8, 'Draft for everyone', 'draft', 'ALL', null],
    [9, 'Legacy CASE department', 'published', 'ALL', 2],
    [10, 'Selected programs override stale legacy code', 'published', 'BSCE', null],
    [11, 'Selected departments override stale legacy department', 'published', 'ALL', 1],
    [12, 'Program match cannot bypass department', 'published', 'BSCE', 2],
] as [$id, $title, $status, $program, $dept]) {
    $insert->execute([$id, $title, 'notice-' . $id, $status, $program, $dept]);
}
$db->exec("INSERT INTO announcement_programs VALUES (2,'BAEL'),(2,'BEEd'),(2,'BSEd'),(5,'BAEL'),(5,'BEEd'),(10,'BAEL')");
$db->exec('INSERT INTO announcement_departments VALUES (2,2),(3,1),(4,1),(4,3),(11,2)');
$checks = 0;
foreach ([
    'BSCE' => [1,3,4,6],
    'BAEL' => [1,2,5,7,9,10,11],
    'BEEd' => [1,2,5,9,11],
    'BSEd' => [1,2,9,11],
    'BSIT' => [1,4],
    'UNKNOWN' => [1],
] as $program => $expected) {
    $rows = gnc_get_global_announcements($program, 50);
    $actual = array_map('intval', array_column($rows, 'announcement_id'));
    sort($actual);
    if ($actual !== $expected) throw new RuntimeException('Incorrect bulletin audience for ' . $program . ': ' . json_encode($actual));
    if (array_unique(array_column($rows, 'excerpt')) !== ['Notice']) throw new RuntimeException('Excerpt regression');
    $checks++;
}
if (count(gnc_get_global_announcements('BAEL', 2)) !== 2) throw new RuntimeException('Limit regression');
echo ($checks + 1) . " bulletin audience checks passed. No persistent data changed.\n";
