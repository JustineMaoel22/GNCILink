<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function cardCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$db = getDB();
$dept = $db->query("SELECT dept_id FROM departments WHERE slug = 'cased'")->fetchColumn();
$other = $db->query("SELECT dept_id FROM departments WHERE slug = 'graduate-school'")->fetchColumn();
$basic = $db->query("SELECT dept_id FROM departments WHERE slug = 'hs-monte'")->fetchColumn();
cardCheck((bool)($dept && $other && $basic), 'Test departments exist');
$_SESSION['user_id'] = (int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();
$db->beginTransaction();
try {
    $data = ['dept_id' => $dept, 'program_name' => 'Card Test Program', 'description' => 'First description'];
    $first = saveDepartmentProgramCard($data);
    $second = saveDepartmentProgramCard($data);
    cardCheck($first['program_id'] !== $second['program_id'], 'Blank cards create separate programs with unique codes');
    $read = $db->prepare('SELECT * FROM programs WHERE program_id = ?');
    $read->execute([$first['program_id']]); $before = $read->fetch(PDO::FETCH_ASSOC);
    $data['program_id'] = $first['program_id'];
    $data['program_name'] = 'Updated heading'; $data['description'] = '';
    saveDepartmentProgramCard($data);
    $read->execute([$first['program_id']]); $after = $read->fetch(PDO::FETCH_ASSOC);
    cardCheck($after['program_name'] === 'Updated heading' && $after['description'] === '', 'Card text saves and descriptions can be cleared');
    foreach ($before as $key => $value) {
        if (!in_array($key, ['program_name', 'description', 'updated_at'], true)) cardCheck($after[$key] === $value, 'Card editing preserves program field ' . $key);
    }
    $invalid = [array_replace($data, ['dept_id' => $other]), array_replace($data, ['program_name' => '']), array_replace($data, ['program_name' => []]),
        array_replace($data, ['description' => str_repeat('a', 5001)]), array_replace($data, ['dept_id' => $basic]), array_replace($data, ['program_id' => -1])];
    foreach ($invalid as $input) {
        $rejected = false;
        try { saveDepartmentProgramCard($input); } catch (Exception $e) { $rejected = true; }
        cardCheck($rejected, 'Invalid or cross-department card changes are rejected');
    }
    $basicCard = saveDepartmentProgramCard(['dept_id' => $basic, 'program_name' => 'Basic Education Test Program', 'description' => 'Shared card workflow']);
    $basicUpdate = saveDepartmentProgramCard(['dept_id' => $basic, 'program_id' => $basicCard['program_id'], 'program_name' => 'Edited Basic Education Program', 'description' => '']);
    cardCheck($basicUpdate['program_id'] === $basicCard['program_id'], 'Basic Education can create and edit programs through the shared helper');
} finally { $db->rollBack(); }
echo "PASS: inline cards create and update programs, preserve full-page settings, generate unique codes and reject invalid changes; writes rolled back.\n";
