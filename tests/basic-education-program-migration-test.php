<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../database/basic-education-program-migration.php';
function migrationCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$db = getDB();
prepareBasicEducationProgramMigration($db);
$db->beginTransaction();
try {
    $db->exec("DELETE FROM content_migrations WHERE migration_key = 'basic-education-shared-programs-v1'");
    $dept = $db->query("SELECT dept_id FROM departments WHERE slug = 'hs-monte'")->fetchColumn();
    migrationCheck((bool)$dept, 'Test department exists');
    $seed = $db->prepare('INSERT INTO basic_education_content (page_key, field_key, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)');
    $marker = 'Migration test ' . bin2hex(random_bytes(5));
    $imagePath = '/assets/images/maam-nazal.png';
    foreach (['text_06' => $marker, 'text_07' => 'Saved program description', 'image_01' => $imagePath,
        'text_08' => '', 'text_09' => 'Description without heading', 'image_02' => '', 'text_20' => 'Original leadership introduction'] as $key => $value) {
        $seed->execute(['hs-monte', $key, $value]);
    }
    $override = $db->prepare('INSERT INTO department_page_content (dept_id, field_key, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content)');
    $override->execute([$dept, 'leadership_intro', 'Newer editor value']);
    $result = migrateBasicEducationPrograms($db);
    migrationCheck($result['programs'] >= 2, 'Populated cards migrate, including cards without a heading');
    $find = $db->prepare('SELECT p.*, m.file_path FROM programs p LEFT JOIN media_library m ON m.media_id = p.image_media_id WHERE p.dept_id = ? AND p.program_name = ?');
    $find->execute([$dept, $marker]); $program = $find->fetch(PDO::FETCH_ASSOC);
    migrationCheck($program && $program['description'] === 'Saved program description' && $program['file_path'] === $imagePath, 'Program heading, description and image path are preserved');
    $findField = $db->prepare('SELECT content FROM department_page_content WHERE dept_id = ? AND field_key = ?');
    $findField->execute([$dept, 'leadership_intro']);
    migrationCheck($findField->fetchColumn() === 'Newer editor value', 'Migration never overwrites newer editor content');
    $db->prepare('DELETE FROM programs WHERE program_id = ?')->execute([$program['program_id']]);
    $repeat = migrateBasicEducationPrograms($db);
    migrationCheck($repeat['already_applied'] && $repeat['programs'] === 0, 'Rerunning the migration never duplicates or restores deleted programs');
    $find->execute([$dept, $marker]);
    migrationCheck(!$find->fetch(), 'Deleted migrated programs remain deleted');
    $source = $db->prepare('SELECT content FROM basic_education_content WHERE page_key = ? AND field_key = ?');
    $source->execute(['hs-monte', 'text_06']);
    migrationCheck($source->fetchColumn() === $marker, 'Original source rows are retained');
} finally { $db->rollBack(); }
echo "PASS: migration preserves card text and images, retains source rows, respects newer edits and safely skips repeated runs; test writes rolled back.\n";
