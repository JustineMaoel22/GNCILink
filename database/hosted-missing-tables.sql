-- Select your hosted database in phpMyAdmin before importing this file.
-- Back up the hosted database first. This adds schema only, without copying rows.
-- utf8mb4_unicode_ci also supports hosts without MySQL 8's 0900 collation.

CREATE TABLE IF NOT EXISTS `basic_enrollment_steps` (
  `step_id` char(32) NOT NULL,
  `title` varchar(150) NOT NULL,
  `location` varchar(150) NOT NULL DEFAULT '',
  `instruction` text NOT NULL,
  `illustration` varchar(255) NOT NULL DEFAULT '',
  `display_order` int NOT NULL DEFAULT 0,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`step_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_activity_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_extension_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `enrollment_illustrations` (
  `image_key` varchar(40) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  PRIMARY KEY (`image_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faq_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guard the column and index additions so the script can be imported again.
SET @gnci_schema_sql = IF(
  EXISTS (SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'dept_id'),
  'SELECT ''events.dept_id already exists'' AS migration_status',
  'ALTER TABLE `events` ADD COLUMN `dept_id` int DEFAULT NULL'
);
PREPARE gnci_schema_stmt FROM @gnci_schema_sql;
EXECUTE gnci_schema_stmt;
DEALLOCATE PREPARE gnci_schema_stmt;

SET @gnci_schema_sql = IF(
  EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_department'),
  'SELECT ''idx_events_department already exists'' AS migration_status',
  'ALTER TABLE `events` ADD INDEX `idx_events_department` (`dept_id`)'
);
PREPARE gnci_schema_stmt FROM @gnci_schema_sql;
EXECUTE gnci_schema_stmt;
DEALLOCATE PREPARE gnci_schema_stmt;
