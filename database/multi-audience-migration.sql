-- GNC Multi-Audience Migration
-- Enables multi-department and multi-program selection for Announcements and Events.
-- Additive and safe: does not modify or drop any existing columns or data.

CREATE TABLE IF NOT EXISTS `announcement_departments` (
  `announcement_id` int NOT NULL,
  `dept_id` int NOT NULL,
  PRIMARY KEY (`announcement_id`, `dept_id`),
  KEY `idx_ann_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcement_programs` (
  `announcement_id` int NOT NULL,
  `program_code` varchar(20) NOT NULL,
  PRIMARY KEY (`announcement_id`, `program_code`),
  KEY `idx_ann_prog` (`program_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_departments` (
  `event_id` int NOT NULL,
  `dept_id` int NOT NULL,
  PRIMARY KEY (`event_id`, `dept_id`),
  KEY `idx_evt_dept` (`dept_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_programs` (
  `event_id` int NOT NULL,
  `program_code` varchar(20) NOT NULL,
  PRIMARY KEY (`event_id`, `program_code`),
  KEY `idx_evt_prog` (`program_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

