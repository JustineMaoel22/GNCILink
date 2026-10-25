<?php
// Exercise the existing program save flow; every database change is rolled back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/program-page-public.php';
$db = getDB();
$program = getProgramPageBySlug('bael');
if (!$program) throw new RuntimeException('BAEL fixture is required.');
$_SESSION['user_id'] = (int)$db->query('SELECT user_id FROM users ORDER BY user_id LIMIT 1')->fetchColumn();
$data = [
    'program_name' => $program['program_name'], 'description' => $program['description'],
    'dept_id' => $program['dept_id'], 'theme' => 'custom', 'theme_custom' => $program['theme_accent'],
    'core_values_enabled' => $program['core_enabled'], 'is_published' => $program['published'],
];
foreach ($program['content'] as $key => $value) $data['c_' . $key] = $value;
$data['c_vision_icon'] = 'bi:bullseye';
$data['c_mission_icon'] = 'material:school';
$db->beginTransaction();
try {
    saveProgramPageDetails((int)$program['program_id'], $data);
    $saved = getProgramPageBySlug('bael');
    foreach (['vision_icon' => 'bi:bullseye', 'mission_icon' => 'material:school'] as $key => $spec) {
        if ($saved['content'][$key] !== $spec) throw new RuntimeException('Icon did not persist: ' . $key);
    }
    if (!str_contains(programPageIcon($saved['content']['vision_icon'], 'template-vm-icon-glyph'), 'bi-bullseye')
        || !str_contains(programPageIcon($saved['content']['mission_icon'], 'template-vm-icon-glyph'), '>school</span>')) {
        throw new RuntimeException('Public program icon renderer did not use saved icons.');
    }
    foreach ($program['content'] as $key => $value) {
        if (in_array($key, ['vision_icon', 'mission_icon'], true)) continue;
        if ($saved['content'][$key] !== $value) throw new RuntimeException('Unrelated program content changed: ' . $key);
    }
} finally { $db->rollBack(); }
echo "PASS: both program statement icons persist and render through the existing save flow; other content is preserved; writes rolled back.\n";
