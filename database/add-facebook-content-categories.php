<?php
/** Add missing Facebook choices without renaming/deleting categories or posts. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../admin/admin-functions.php';
$db = getDB();
$db->beginTransaction();
try {
    $names = array_unique(array_merge(array_values(getAnnouncementCategoryLabels()), array_values(getNewsCategories())));
    $existing = array_column(getFacebookPostCategories($db), 'category_name');
    $insert = $db->prepare('INSERT INTO categories(category_name,category_color,description) VALUES(?,?,?)');
    $added = 0;
    foreach ($names as $name) {
        if (in_array($name, $existing, true)) continue;
        $insert->execute([$name, '#226437', 'Website content category: ' . $name]);
        $added++;
    }
    if (count(getFacebookPostCategories($db)) !== count($names)) throw new RuntimeException('Facebook categories could not be verified.');
    $db->commit();
    echo "Verified all 13 Facebook content categories; added $added missing categories. Existing IDs and assignments preserved.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
