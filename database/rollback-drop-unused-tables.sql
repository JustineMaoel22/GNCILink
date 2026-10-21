-- ============================================================
-- ROLLBACK: Restore tables removed in gnc_admin (51).sql
-- Run this if the cleanup caused any issues.
-- ============================================================

-- Re-create office_services (was empty; linked to the `offices` table)
CREATE TABLE IF NOT EXISTS `office_services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `office_id` int NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `procedure` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`service_id`),
  KEY `fk_svc_office` (`office_id`),
  CONSTRAINT `fk_svc_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Re-create program_sections (was empty; linked to the `programs` table)
CREATE TABLE IF NOT EXISTS `program_sections` (
  `section_id` int NOT NULL AUTO_INCREMENT,
  `program_id` int NOT NULL,
  `section_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` json DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_id`),
  KEY `idx_program_sections_order` (`program_id`,`display_order`),
  CONSTRAINT `fk_program_sections_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Rollback complete: office_services and program_sections restored.' AS status;

