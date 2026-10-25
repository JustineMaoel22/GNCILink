<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS basic_enrollment_steps (
    step_id CHAR(32) NOT NULL PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    location VARCHAR(150) NOT NULL DEFAULT '',
    instruction TEXT NOT NULL,
    illustration VARCHAR(255) NOT NULL DEFAULT '',
    display_order INT NOT NULL DEFAULT 0,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS enrollment_illustrations (
    image_key VARCHAR(40) NOT NULL PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->beginTransaction();
try {
    $query = $db->prepare('SELECT page_id FROM pages WHERE slug = ?');
    $query->execute(['enrollment-procedures']); $id = $query->fetchColumn();
    if (!$id) {
        $db->prepare("INSERT INTO pages (title,slug,description,page_type,display_order,status) VALUES ('Enrollment Procedures','enrollment-procedures','College and Basic Education enrollment procedures','sections',8,'active')")->execute();
        $id = $db->lastInsertId();
    }
    $check = $db->prepare('SELECT 1 FROM page_sections WHERE page_id = ? AND section_key = ?');
    $insert = $db->prepare("INSERT INTO page_sections (page_id,section_key,title,content,status,display_order) VALUES (?,?,?,?,'published',?)");
    $order = 0;
    foreach (basicEnrollmentDefaults() as $key => $field) {
        $check->execute([$id, $key]);
        if (!$check->fetchColumn()) $insert->execute([$id, $key, $field['title'], $field['content'], ++$order]);
    }
    $db->commit();
    echo count(basicEnrollmentDefaults()) . " Enrollment Procedures fields registered. Existing content and drafts preserved.\n";
} catch (Throwable $error) { $db->rollBack(); throw $error; }
