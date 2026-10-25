<?php
// CLI-only local DB regression check; all edits are rolled back.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/basic-admission-requirements.php';
function admissionRegistrationCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
$db = getDB();
$stmt = $db->prepare('SELECT page_id FROM pages WHERE slug = ? AND status = ?');
$stmt->execute(['basic-education-admission-requirements', 'active']);
$id = $stmt->fetchColumn();
admissionRegistrationCheck((bool)$id, 'Local admission page is registered');
$db->beginTransaction();
try {
    // Reproduce an older hosting DB that has requirements and headings but no tab-label rows.
    $delete = $db->prepare('DELETE FROM page_sections WHERE page_id = ? AND section_key = ?');
    foreach (array_keys(basicAdmissionPresentationDefaults()) as $key) {
        if (str_starts_with($key, 'school_label_')) $delete->execute([$id, $key]);
    }
    ensureBasicAdmissionHeaderSections();
    $read = $db->prepare('SELECT section_id, content, draft_content, status FROM page_sections WHERE page_id = ? AND section_key = ?');
    foreach (basicAdmissionPresentationDefaults() as $key => $default) {
        $read->execute([$id, $key]);
        $row = $read->fetch(PDO::FETCH_ASSOC);
        admissionRegistrationCheck((bool)$row, 'Editable record registered: ' . $key);
        if (str_starts_with($key, 'school_label_')) {
            admissionRegistrationCheck($row['content'] === $default['content'] && $row['status'] === 'published', 'New labels are published with unescaped defaults');
        }
    }
    $db->prepare('UPDATE page_sections SET content = ?, draft_content = ? WHERE page_id = ? AND section_key = ?')
        ->execute(['Custom Montessori & school', 'Unpublished custom label', $id, 'school_label_montessori']);
    $read->execute([$id, 'school_label_montessori']);
    $before = $read->fetch(PDO::FETCH_ASSOC);
    ensureBasicAdmissionHeaderSections();
    $read->execute([$id, 'school_label_montessori']);
    admissionRegistrationCheck($read->fetch(PDO::FETCH_ASSOC) === $before, 'Repeated registration preserves the existing ID, published label, and draft');
    $count = $db->prepare('SELECT COUNT(*) FROM page_sections WHERE page_id = ? AND section_key = ?');
    foreach (array_keys(basicAdmissionPresentationDefaults()) as $key) {
        $count->execute([$id, $key]);
        admissionRegistrationCheck((int)$count->fetchColumn() === 1, 'Repeated registration does not duplicate fields');
    }
} finally {
    $db->rollBack();
}
echo "PASS: missing hosted tab-label records are registered; existing labels and drafts are preserved; all test edits rolled back.\n";
