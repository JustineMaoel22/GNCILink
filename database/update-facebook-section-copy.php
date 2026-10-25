<?php
/** Apply the shared Facebook section wording to existing editor content. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';

$db = getDB();
$title = 'Connect with Us';
$description = 'If you have concerns or need assistance feel free to message our page.';
$db->beginTransaction();
try {
    foreach ([
        ['department_page_content', 'facebook_title', $title],
        ['department_page_content', 'facebook_intro', $description],
        ['student_org_page_content', 'facebook_title', $title],
        ['student_org_page_content', 'facebook_description', $description],
    ] as [$table, $key, $value]) {
        try {
            $stmt = $db->prepare("UPDATE {$table} SET content = ? WHERE field_key = ?");
            $stmt->execute([$value, $key]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '42S02') throw $error;
        }
    }
    try {
        $stmt = $db->prepare("UPDATE basic_education_content
            SET content = JSON_SET(content, '$.texts.facebook_title', ?, '$.texts.facebook_description', ?)
            WHERE page_key = 'athletics-sports' AND field_key = 'page' AND JSON_VALID(content)");
        $stmt->execute([$title, $description]);
    } catch (PDOException $error) {
        if ($error->getCode() !== '42S02') throw $error;
    }
    $db->commit();
    echo "Facebook section headings and descriptions updated.\n";
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
