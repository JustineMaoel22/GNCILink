<?php
/**
 * Additive migration for multi-department and multi-program targeting.
 * CLI only. Existing records retain single assignments without modification.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../config/config.php';
$db = getDB();

$db->exec("
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
");

echo "Multi-audience storage tables are ready. Announcements and Events support multi-selection.\n";

