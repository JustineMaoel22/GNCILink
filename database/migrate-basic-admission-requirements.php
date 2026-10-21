<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/basic-admission-requirements.php';
$db = getDB();
$db->beginTransaction();
try {
    $slug = 'basic-education-admission-requirements';
    $query = $db->prepare('SELECT page_id FROM pages WHERE slug = ?');
    $query->execute([$slug]);
    $id = $query->fetchColumn();
    if (!$id) {
        $db->prepare("INSERT INTO pages (title,slug,description,page_type,display_order,status) VALUES (?,?,?,'sections',20,'active')")
            ->execute(['Basic Education Admission Requirements', $slug, 'Enrollment requirements for Basic Education applicants.']);
        $id = $db->lastInsertId();
    }
    $check = $db->prepare('SELECT section_id FROM page_sections WHERE page_id = ? AND section_key = ?');
    $insert = $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order) VALUES (?,?,?,?,'published',?)");
    $order = 0;
    foreach (basicAdmissionSectionDefaults() as $key => $section) {
        ++$order;
        $check->execute([$id, $key]);
        if (!$check->fetchColumn()) $insert->execute([$id, $key, $section['title'], $section['content'], $order]);
    }
    $db->commit();
    echo "Basic Education admission requirements registered with 14 editable sections. Existing edits preserved.\n";
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
