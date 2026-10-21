<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/tuition-fee-documents.php';
$db = getDB();
$hadDocuments = (bool)$db->query("SHOW TABLES LIKE 'tuition_fee_documents'")->fetchColumn();
$db->exec("CREATE TABLE IF NOT EXISTS tuition_fee_content (
    field_key VARCHAR(80) PRIMARY KEY, content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS tuition_fee_documents (
    document_id INT AUTO_INCREMENT PRIMARY KEY, category_key VARCHAR(40) NOT NULL,
    title VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL UNIQUE,
    file_size INT NOT NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
// Preserve schedules added before the editor integration; repeat runs are safe.
foreach ($hadDocuments ? [] : tuitionFeeFolderCategories() as $key => $category) {
    foreach ($category['documents'] as $doc) {
        $file = dirname(__DIR__) . rawurldecode($doc['url']);
        $db->prepare('INSERT IGNORE INTO tuition_fee_documents (category_key,title,file_path,file_size) VALUES (?,?,?,?)')
            ->execute([$key, $doc['title'], $doc['url'], filesize($file)]);
    }
}
echo "Tuition fee tables ready. Existing schedules preserved.\n";
