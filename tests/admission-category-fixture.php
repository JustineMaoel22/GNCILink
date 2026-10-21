<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$records = json_decode($argv[1] ?? '[]', true, 8, JSON_THROW_ON_ERROR);
foreach ($records as $record) {
    if (!in_array($record['page'] ?? '', ['college-admission-requirements','basic-education-admission-requirements'],true)
        || !preg_match('/^(?:(?:casa|montessori|jhs-regular|shs-regular)_)?custom_[a-f0-9]{16}$/D', $record['key'] ?? '')) throw new RuntimeException('Invalid test category');
    $query = getDB()->prepare("SELECT ps.section_id,ps.title FROM page_sections ps JOIN pages p ON p.page_id=ps.page_id WHERE p.slug=? AND ps.section_key=?");
    $query->execute([$record['page'],$record['key']]);
    $row = $query->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (!str_starts_with($row['title'],'ADMISSIONTEST-')) throw new RuntimeException('Refusing to delete non-test category');
        getDB()->prepare('DELETE FROM page_sections WHERE section_id=?')->execute([$row['section_id']]);
    }
}
echo "Temporary admission categories removed.\n";
