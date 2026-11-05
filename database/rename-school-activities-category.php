<?php
/** Rename the existing category in place, retaining its ID and assignments. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$db->beginTransaction();
try {
    $check = $db->prepare('SELECT category_id FROM categories WHERE category_name=?');
    $check->execute(['Curricular & Co-Curricular']);
    $oldIds = $check->fetchAll(PDO::FETCH_COLUMN);
    $check->execute(['School Activities']);
    $existingIds = $check->fetchAll(PDO::FETCH_COLUMN);
    if ($oldIds && $existingIds) {
        throw new RuntimeException('Both category names already exist. No records changed; review the categories before renaming.');
    }
    if ($oldIds) {
        $db->prepare('UPDATE categories SET category_name=?,description=? WHERE category_name=?')
            ->execute(['School Activities', 'Curricular and co-curricular school activities and events', 'Curricular & Co-Curricular']);
    } elseif (!$existingIds) {
        $db->prepare('INSERT INTO categories (category_name,category_color,description) VALUES (?,?,?)')
            ->execute(['School Activities', '#2E6F9E', 'Curricular and co-curricular school activities and events']);
    }
    $db->commit();
    echo "School Activities category is available. Category assignments preserved.\n";
} catch (Throwable $error) {
    if ($db->inTransaction()) $db->rollBack();
    throw $error;
}
