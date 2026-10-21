<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec("CREATE TABLE IF NOT EXISTS image_display_settings (
    image_path VARCHAR(500) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    position_x DECIMAL(7,4) NOT NULL DEFAULT 50,
    position_y DECIMAL(7,4) NOT NULL DEFAULT 50,
    zoom DECIMAL(5,4) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Image display settings ready. Original image files are unchanged.\n";
