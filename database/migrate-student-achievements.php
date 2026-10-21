<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
getDB()->exec("CREATE TABLE IF NOT EXISTS student_achievements (
    achievement_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    program_organization VARCHAR(255) NOT NULL DEFAULT '',
    category_label VARCHAR(100) NOT NULL DEFAULT '',
    academic_year VARCHAR(9) NULL,
    image_path VARCHAR(500) NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX achievement_listing (status,academic_year,sort_order),
    INDEX achievement_featured (status,is_featured,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$db=getDB();
$columns=$db->query('SHOW COLUMNS FROM student_achievements')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('thumbnail_path',$columns,true)) {
    $db->exec('ALTER TABLE student_achievements ADD COLUMN thumbnail_path VARCHAR(500) NULL');
    $db->exec('UPDATE student_achievements SET thumbnail_path=image_path WHERE thumbnail_path IS NULL');
}
if (!in_array('achievers',$columns,true)) $db->exec('ALTER TABLE student_achievements ADD COLUMN achievers TEXT NULL');
$db->exec("CREATE TABLE IF NOT EXISTS student_achievement_photos (
    photo_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    achievement_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (achievement_id) REFERENCES student_achievements(achievement_id) ON DELETE CASCADE,
    INDEX achievement_photo_order (achievement_id,sort_order,photo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Independent student achievements table ready.\n";
