<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/scholarship-categories-migration.php';
require_once __DIR__ . '/../includes/editor-presence.php';
$options = getopt('', ['apply','rollback','archive:']);
if (isset($options['apply'],$options['rollback'])) { fwrite(STDERR,"Choose apply or rollback.\n"); exit(1); }
$db = getDB();
$locked = false;
try {
    if (isset($options['apply']) || isset($options['rollback'])) {
        editorPresenceLock($db); $locked = true;
        if (isset($options['apply'])) {
            if (!scholarshipCategoriesEnabled($db)) {
                if (isset($options['archive'])) {
                    $path = (string)$options['archive'];
                    $directory = realpath(dirname($path)); $root = realpath(__DIR__.'/..');
                    if (!$directory || $directory === $root || str_starts_with($directory.DIRECTORY_SEPARATOR,$root.DIRECTORY_SEPARATOR)) throw new RuntimeException('Archive must be outside the web root.');
                    $snapshot = ['format'=>'gnci-scholarship-categories-v1','schema'=>$db->query('SELECT DATABASE()')->fetchColumn(),'categories'=>scholarshipLegacyCategories($db),'legacy'=>scholarshipStoredValues('scholarship_',$db)];
                    $json = json_encode($snapshot,JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                    $mask = umask(0077);
                    try { $file = fopen($path,'x'); } finally { umask($mask); }
                    if (!$file) throw new RuntimeException('Could not create a new private archive.');
                    try { if (fwrite($file,$json) !== strlen($json) || !fflush($file)) throw new RuntimeException('Incomplete archive.'); }
                    finally { fclose($file); }
                    if (json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR) !== $snapshot) throw new RuntimeException('Archive verification failed.');
                    echo "Verified category snapshot: $path\n";
                }
                $db->exec('CREATE TABLE IF NOT EXISTS content_migrations (migration_key VARCHAR(100) NOT NULL PRIMARY KEY,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                $db->exec(file_get_contents(__DIR__ . '/scholarship-categories.sql'));
                // Reapply after a deliberate rollback only when retained rows match the mirror.
                $retained = $db->query('SELECT category_id AS id,category_name AS label FROM scholarship_categories ORDER BY display_order,category_id')->fetchAll(PDO::FETCH_ASSOC);
                if ($retained) {
                    if (scholarshipValidateCategories($retained) !== scholarshipLegacyCategories($db)) throw new RuntimeException('Retained categories differ from legacy data; review before reapplying.');
                    $db->beginTransaction();
                    try {
                        $db->prepare('INSERT INTO content_migrations (migration_key) VALUES (?)')->execute(['scholarship-categories-v1']);
                        $db->commit();
                    } catch (Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
                } else scholarshipCategoryMigrationSeed($db);
            }
        } else scholarshipCategoryMigrationRollback($db);
    }
    echo 'Dedicated scholarship categories: ' . (scholarshipCategoriesEnabled($db) ? 'enabled' : 'not applied') . ".\n";
    echo count(scholarshipCategories($db)) . " categories available. Existing entries and PDF paths preserved.\n";
} catch (Throwable $error) {
    error_log('Scholarship category migration: ' . $error->getMessage());
    fwrite(STDERR,"Scholarship category migration stopped. Inspect the private PHP log; existing content is retained.\n");
    exit(1);
} finally { if ($locked) editorPresenceUnlock($db); }
