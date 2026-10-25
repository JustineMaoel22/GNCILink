<?php
/** Add the new announcement categories without duplicating existing records. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
$db->beginTransaction();
try {
    $insert = $db->prepare('INSERT INTO categories (category_name, category_color, description)
        SELECT ?, ?, ? WHERE NOT EXISTS (SELECT 1 FROM categories WHERE category_name = ?)');
    $check = $db->prepare('SELECT COUNT(*) FROM categories WHERE category_name = ?');
    foreach ([
        ['Job Opportunities', '#2E6F9E', 'Job openings, hiring announcements, and career opportunities'],
        ['Scholarship', '#7B4FA3', 'Scholarship opportunities, applications, and financial assistance'],
    ] as [$name, $color, $description]) {
        $insert->execute([$name, $color, $description, $name]);
        $check->execute([$name]);
        if ((int)$check->fetchColumn() < 1 || normalizeNewsCategory($name) !== $name) {
            throw new RuntimeException('Category unavailable for news or announcements: ' . $name);
        }
    }
    $db->commit();
    echo "Verified: Job Opportunities and Scholarship are available for news and announcements.\n";
} catch (Throwable $error) {
    $db->rollBack();
    throw $error;
}
