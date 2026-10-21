<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec("CREATE TABLE IF NOT EXISTS admin_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
getDB()->exec("INSERT IGNORE INTO admin_settings (setting_key, setting_value) VALUES ('ai_image_checker_enabled', '1')");
echo "Admin settings ready. Existing choices preserved; AI image checker defaults to On.\n";
