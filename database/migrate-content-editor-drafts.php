<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$db->exec('CREATE TABLE IF NOT EXISTS content_editor_drafts (
    draft_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    page_key CHAR(64) NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY owner_page (user_id,page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS content_editor_draft_chunks (
    draft_id BIGINT UNSIGNED NOT NULL,
    chunk_no INT UNSIGNED NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    PRIMARY KEY (draft_id,chunk_no),
    CONSTRAINT editor_draft_chunks_parent FOREIGN KEY (draft_id) REFERENCES content_editor_drafts(draft_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Content Editor draft tables are ready. Existing content was not changed.\n";
