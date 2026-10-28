<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$db = getDB();
$db->exec("CREATE TABLE IF NOT EXISTS student_org_categories (
    category_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE,
    display_order INT NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS student_organizations (
    org_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(180) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL, category_id INT NULL, description TEXT NULL, about_text TEXT NULL,
    vision TEXT NULL, mission TEXT NULL, facebook_url VARCHAR(500) NULL,
    logo_media_id INT NULL, cover_media_id INT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft', archived_at DATETIME NULL DEFAULT NULL, display_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX org_public_order (status, display_order, org_id), INDEX org_category (category_id),
    FOREIGN KEY (category_id) REFERENCES student_org_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (logo_media_id) REFERENCES media_library(media_id) ON DELETE SET NULL,
    FOREIGN KEY (cover_media_id) REFERENCES media_library(media_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS student_org_gallery (
    image_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, org_id INT NOT NULL, media_id INT NOT NULL,
    display_order INT NOT NULL DEFAULT 0, INDEX gallery_order (org_id,display_order,image_id),
    FOREIGN KEY (org_id) REFERENCES student_organizations(org_id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media_library(media_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db->exec("CREATE TABLE IF NOT EXISTS student_org_page_content (
    field_key VARCHAR(80) NOT NULL PRIMARY KEY, content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Student organization tables ready. Existing records preserved; no organizations or categories seeded.\n";
