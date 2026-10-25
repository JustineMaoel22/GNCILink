<?php
if (PHP_SAPI !== 'cli') { http_response_code(404);exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec('CREATE TABLE IF NOT EXISTS text_layout_settings (
    page_key CHAR(64) NOT NULL PRIMARY KEY,
    settings MEDIUMTEXT NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
echo "Text layout storage is ready. Existing content was not changed.\n";
