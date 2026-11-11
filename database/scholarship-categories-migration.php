<?php
require_once __DIR__ . '/../components/scholarship-content.php';

/** Seed under the editor write lock, after DDL and outside another transaction. */
function scholarshipCategoryMigrationSeed(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Migration must own its transaction.');
    if (scholarshipCategoriesEnabled($db)) return;
    $categories = scholarshipLegacyCategories($db);
    // Validate existing assignments before activating the dedicated category reader.
    scholarshipCatalog($db);
    if ((int)$db->query('SELECT COUNT(*) FROM scholarship_categories')->fetchColumn() !== 0) {
        throw new RuntimeException('Unmarked scholarship category table contains data; review before migration.');
    }
    $db->beginTransaction();
    try {
        $save = $db->prepare('INSERT INTO scholarship_categories (category_id,category_name,display_order) VALUES (?,?,?)');
        foreach (array_values($categories) as $order=>$category) $save->execute([$category['id'],$category['label'],$order]);
        $db->prepare('INSERT INTO content_migrations (migration_key) VALUES (?)')->execute(['scholarship-categories-v1']);
        if (scholarshipCategories($db) !== $categories) throw new RuntimeException('Category migration verification failed.');
        // Establish a rollback mirror even when the original page used defaults.
        scholarshipSaveCategories($db,$categories);
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}

/** Keep the new table and current categories; switch readers back to the mirror. */
function scholarshipCategoryMigrationRollback(PDO $db): void {
    if ($db->inTransaction()) throw new LogicException('Rollback must own its transaction.');
    if (!scholarshipCategoriesEnabled($db)) return;
    $db->beginTransaction();
    try {
        scholarshipSaveCategories($db,scholarshipCategories($db));
        $db->prepare('DELETE FROM content_migrations WHERE migration_key = ?')->execute(['scholarship-categories-v1']);
        $db->commit();
    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
}
