-- Back up the hosted database, then select it in phpMyAdmin before importing.
-- Matches migrate-community-activities.php. Existing page content is preserved.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `community_activity_page` (
  `page_id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `content` MEDIUMTEXT NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `community_activity_page` (`page_id`, `content`)
VALUES (1, JSON_OBJECT(
  'texts', JSON_OBJECT(
    'hero_title', 'Community Extension Activities',
    'hero_description', 'Explore GNCI’s community initiatives that inspire service and create positive change.',
    'heading_first', 'Community',
    'heading_accent', 'Extension',
    'heading_last', 'Activities',
    'intro', CONCAT('Learning becomes meaningful when it improves the lives around us.', CHAR(10), 'Our students turn curiosity, compassion, and teamwork into lasting community action.'),
    'list_heading', 'Ways we serve',
    'empty_title', 'No activities available yet',
    'empty_description', 'Community extension activities will appear here when they are published.'
  ),
  'hero_image', '/assets/images/admission-header.png',
  'activities', JSON_ARRAY()
));

-- Expected result: one row with page_id = 1 and valid_content = 1.
SELECT `page_id`, JSON_VALID(`content`) AS `valid_content`
FROM `community_activity_page` WHERE `page_id` = 1;
