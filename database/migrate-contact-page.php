<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/contact-page-content.php';
$db = getDB();
$db->beginTransaction();
try {
    $query = $db->prepare('SELECT page_id FROM pages WHERE slug = ?');
    $query->execute(['contact-us']); $id = $query->fetchColumn();
    if (!$id) {
        $db->prepare("INSERT INTO pages (title,slug,description,page_type,display_order,status) VALUES ('Contact Us','contact-us','School contacts and directory','sections',8,'active')")->execute();
        $id = $db->lastInsertId();
    }
    $check = $db->prepare('SELECT 1 FROM page_sections WHERE page_id = ? AND section_key = ?');
    $insert = $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order) VALUES (?,?,?,?,'published',?)");
    $order = 0;
    foreach (contactPageDefaults() as $key => $field) {
        $check->execute([$id, $key]);
        if (!$check->fetchColumn()) $insert->execute([$id, $key, $field['title'], $field['content'], ++$order]);
    }
    $db->commit();
    echo count(contactPageDefaults()) . " Contact Us fields registered. Existing content and drafts preserved.\n";
} catch (Throwable $error) { $db->rollBack(); throw $error; }
