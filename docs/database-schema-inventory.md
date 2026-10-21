# Database schema and PHP dependency inventory

Audit date: 2026-10-20 (Asia/Manila). Source: `database/gnc_admin (51).sql`, export header 2026-10-20 02:26 AM, session time zone UTC. Local MySQL: 8.0.46.

Export SHA-256: `4f46ab31cb167769b0a93db889b82b0f8bf07d3166631e01a8f62d0c5cce4ead`.

Scanned 131 application/migration PHP files, excluding vendor and tests. Found 42 exported tables. The local metadata snapshot contains the same table set. Table rows in that snapshot are InnoDB estimates, not exact counts.

This is a textual dependency inventory: matches can include comments, fallback paths, and dynamically selected table names. Absence of a match does not prove a table or column can be removed. No data values from INSERT statements are reproduced. Every table is retained in this proposal.

See [optimization plan](database-optimization-plan.md), [local metadata](database-audit-local-metadata.tsv), and [baseline plans](database-audit-baseline.txt).

## Overview

| Table | Columns | PHP files mentioning table | Outbound foreign keys |
|---|---:|---:|---:|
| `activity_logs` | 7 | 2 | 1 |
| `admin_offices` | 15 | 2 | 0 |
| `admin_people` | 9 | 2 | 0 |
| `admin_sections` | 7 | 2 | 0 |
| `announcements` | 15 | 21 | 3 |
| `announcement_images` | 5 | 3 | 2 |
| `basic_education_content` | 4 | 2 | 0 |
| `categories` | 5 | 22 | 0 |
| `content_migrations` | 2 | 1 | 0 |
| `csrf_tokens` | 5 | 2 | 0 |
| `departments` | 25 | 40 | 2 |
| `department_page_content` | 4 | 3 | 1 |
| `events` | 12 | 22 | 3 |
| `faqs` | 7 | 2 | 1 |
| `fb_api_call_log` | 2 | 1 | 0 |
| `fb_posts_cache` | 10 | 7 | 2 |
| `hero_slides` | 18 | 2 | 1 |
| `homepage_president` | 11 | 2 | 2 |
| `homepage_text` | 5 | 1 | 0 |
| `media_library` | 7 | 13 | 1 |
| `news` | 16 | 21 | 3 |
| `news_images` | 5 | 1 | 2 |
| `offices` | 8 | 10 | 0 |
| `office_services` | 7 | 0 | 1 |
| `pages` | 10 | 54 | 0 |
| `page_sections` | 18 | 6 | 2 |
| `page_visits` | 6 | 4 | 0 |
| `page_visits_daily` | 3 | 1 | 0 |
| `programs` | 15 | 38 | 3 |
| `program_items` | 12 | 3 | 1 |
| `program_sections` | 10 | 0 | 1 |
| `site_visitors` | 4 | 2 | 0 |
| `slug_history` | 5 | 4 | 0 |
| `student_achievements` | 14 | 4 | 0 |
| `student_achievement_photos` | 5 | 3 | 1 |
| `student_organizations` | 15 | 4 | 3 |
| `student_org_categories` | 4 | 3 | 0 |
| `student_org_gallery` | 4 | 3 | 2 |
| `student_org_page_content` | 3 | 3 | 0 |
| `tuition_fee_content` | 3 | 3 | 0 |
| `tuition_fee_documents` | 6 | 3 | 0 |
| `users` | 17 | 9 | 0 |

## activity_logs

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_user` (`user_id`);
ALTER TABLE `activity_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=936;
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 153, 171
- `chatbot/inc/retriever.php`: 10

## admin_offices

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `admin_offices` (
  `office_id` int NOT NULL,
  `section_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'heads-of-student-services',
  `office_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `head_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `head_role` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int DEFAULT NULL,
  `image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hours` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `services` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'one service per line',
  `display_order` int NOT NULL DEFAULT '0',
  `archived_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `admin_offices`
  ADD PRIMARY KEY (`office_id`),
  ADD KEY `idx_admin_offices_section` (`section_key`,`display_order`);
ALTER TABLE `admin_offices`
  MODIFY `office_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2750, 3875, 3921, 3999, 4269, 4298, 4310, 4315, 4338, 4380, 4392, 4402, 4420, 4489
- `chatbot/inc/retriever.php`: 570

## admin_people

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `admin_people` (
  `person_id` int NOT NULL,
  `section_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `media_id` int DEFAULT NULL,
  `image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `archived_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `admin_people`
  ADD PRIMARY KEY (`person_id`),
  ADD KEY `idx_admin_people_section` (`section_key`,`display_order`);
ALTER TABLE `admin_people`
  MODIFY `person_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2749, 3873, 3921, 3979, 4172, 4198, 4205, 4210, 4227, 4349, 4361, 4371, 4419, 4490
- `chatbot/inc/retriever.php`: 541

## admin_sections

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `admin_sections` (
  `section_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `layout` enum('people','offices') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'people',
  `display_order` int NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `admin_sections`
  ADD PRIMARY KEY (`section_key`);
```

Application/migration PHP references:

- `admin/admin-functions.php`: 3872, 3953, 4057, 4138, 4148
- `chatbot/inc/retriever.php`: 541

## announcements

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `announcements` (
  `announcement_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `program` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ALL',
  `dept_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `featured_image` int DEFAULT NULL,
  `status` enum('draft','pending','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `fk_ann_cat` (`category_id`),
  ADD KEY `fk_ann_user` (`user_id`),
  ADD KEY `idx_announcements_program` (`program`),
  ADD KEY `idx_announcements_pinned` (`is_pinned`,`pinned_at`),
  ADD KEY `idx_announcements_dept` (`dept_id`);
ALTER TABLE `announcements`
  MODIFY `announcement_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `announcement-article.php`: 131, 183, 187, 203
- `index.php`: 14, 21, 217, 346, 398, 949, 958, 1001, 1235
- `admin/admin-dash.php`: 40, 44, 50
- `admin/admin-functions.php`: 190, 202, 256, 281, 320, 330, 375, 385, 412, 438, 456, 473, 490, 506, 515, 564, 683, 687, 698, 704, 710, 732, 735, 737, 754, 756, 772, 1249, 1259, 1376, 1389, 1463, 1477, 1569, 1570, 1588, 1598, 1640, 2427, 2727, 2734, 3519
- `admin/announcement.php`: 35, 36, 39, 181, 193, 198, 201, 208, 361, 362
- `admin/content-edit.php`: 115, 122, 193, 276, 376, 393, 505, 534, 536, 547, 800
- `admin/department-page-content.php`: 28
- `chatbot/chatbot.php`: 111, 140
- `components/bulletin-board.php`: 37, 45, 108, 120, 146, 227, 248, 259, 260, 264, 267, 271
- `components/department-media-manager.php`: 15, 420, 762
- `components/department-public.php`: 9, 181, 193, 204
- `components/header-admin.php`: 124
- `components/program-page-manager.php`: 6, 429
- `components/recommendation-catalog.php`: 45
- `pages/news-and-announcement.php`: 47, 154, 252, 283, 626, 780
- `pages/academics/college/program.php`: 10
- `chatbot/inc/knowledge.php`: 5
- `chatbot/inc/prompt.php`: 8, 12
- `chatbot/inc/retriever.php`: 8, 133, 212, 272, 294, 295, 296, 298, 299, 376, 378, 382, 395, 399, 415, 420, 441, 640
- `admin/action/create-announcement.php`: 112, 192
- `admin/action/page-content-handler.php`: 657

## announcement_images

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `announcement_images` (
  `image_id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `announcement_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_annimg_announcement` (`announcement_id`),
  ADD KEY `fk_annimg_media` (`media_id`);
ALTER TABLE `announcement_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
ALTER TABLE `announcement_images`
  ADD CONSTRAINT `fk_annimg_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_annimg_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `announcement-article.php`: 169, 172
- `admin/admin-functions.php`: 605, 613, 618, 640, 654, 659
- `admin/action/create-announcement.php`: 259, 272

## basic_education_content

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `basic_education_content` (
  `page_key` varchar(60) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE `basic_education_content`
  ADD PRIMARY KEY (`page_key`,`field_key`);
```

Application/migration PHP references:

- `admin/basic-education-content.php`: 62, 150, 162
- `database/basic-education-program-migration.php`: 24

## categories

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);
ALTER TABLE `categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
```

Application/migration PHP references:

- `announcement-article.php`: 134
- `index.php`: 22, 555, 605, 1066
- `admin/admin-functions.php`: 189, 492, 517, 769, 770, 1334, 1350, 1445
- `admin/announcement.php`: 50, 52, 444, 548, 986
- `admin/backfill-fb-categorizer.php`: 4
- `admin/content-edit.php`: 348, 356
- `admin/events.php`: 217, 255, 265, 269, 271, 276, 411, 593
- `admin/news.php`: 13, 145
- `components/student-organizations-manager.php`: 52, 81
- `components/tuition-fee-documents.php`: 6, 14, 28, 54, 56, 60, 63, 64, 70
- `config/fb-categorizer.php`: 7, 107
- `database/migrate-student-organizations.php`: 31
- `includes/student-achievements.php`: 20, 34
- `pages/news-and-announcement.php`: 46, 107, 155, 190, 225
- `pages/admission/tuition-fees.php`: 52, 57
- `pages/student life/student-achievement.php`: 11, 146, 149
- `pages/student life/student organization and leadership program/dynamic-org.php`: 52
- `pages/student life/student organization and leadership program/student-org.php`: 7, 46, 48, 49, 114
- `chatbot/inc/retriever.php`: 382, 395, 430, 449
- `admin/action/create-announcement.php`: 92
- `admin/action/student-organizations-handler.php`: 35, 37
- `admin/action/tuition-fees-handler.php`: 16, 26

## content_migrations

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `content_migrations` (
  `migration_key` varchar(100) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE `content_migrations`
  ADD PRIMARY KEY (`migration_key`);
```

Application/migration PHP references:

- `database/basic-education-program-migration.php`: 4, 15

## csrf_tokens

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `csrf_tokens` (
  `token_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `csrf_tokens`
  ADD PRIMARY KEY (`token_id`);
ALTER TABLE `csrf_tokens`
  MODIFY `token_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=354;
```

Application/migration PHP references:

- `admin/events.php`: 21, 26, 35
- `chatbot/inc/retriever.php`: 10

## departments

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `departments` (
  `dept_id` int NOT NULL,
  `dept_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department_type` enum('college','basic-education') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'college',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `faculty_head` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_id` int DEFAULT NULL,
  `dean_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dean_media_id` int DEFAULT NULL,
  `dean_phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_office` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dean_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `about_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hero_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `office_building_id` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_floor` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_room` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_details` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `uq_departments_slug` (`slug`),
  ADD KEY `fk_dept_logo` (`media_id`),
  ADD KEY `fk_dept_dean_media` (`dean_media_id`);
ALTER TABLE `departments`
  MODIFY `dept_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_dean_media` FOREIGN KEY (`dean_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dept_logo` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `announcement-article.php`: 135
- `index.php`: 512, 621, 1070, 1073
- `admin/admin-functions.php`: 261, 267, 493, 518, 2393, 2413, 2415, 2424, 2425, 2426, 2431, 2549, 2728, 2741, 2747, 2813, 2833, 2839, 2846, 3101, 3113, 3197, 3216, 3261, 3267, 3275, 3319, 3322, 3327, 3343, 3346, 3418, 3424, 3427, 3466, 3481, 3488, 3503, 3533, 3547, 3570, 3571, 3578, 3760, 4599, 4634, 4654, 4819, 4911, 5478
- `admin/announcement.php`: 58, 60, 233, 452, 453
- `admin/basic-education-content.php`: 175
- `admin/content-edit.php`: 74, 83, 86, 112, 178, 213, 214, 220, 236, 364
- `components/basic-edu-media-manager.php`: 9, 43
- `components/calendar-filters.php`: 5, 8, 11, 17, 21, 22
- `components/department-media-manager.php`: 5, 17, 73, 77, 80, 102
- `components/department-page.php`: 324, 408
- `components/department-public.php`: 5, 9, 24, 74, 83, 93, 94, 96, 107
- `components/navbar.php`: 91, 98, 126, 169, 184
- `components/program-page-public.php`: 208
- `components/recommendation-catalog.php`: 19, 36, 41, 68, 69
- `database/basic-education-program-migration.php`: 30
- `database/migrate-department-page-content.php`: 10
- `database/migrate-office-locations.php`: 6, 9, 24, 25
- `includes/office-location.php`: 94, 96
- `pages/news-and-announcement.php`: 93
- `pages/academics/college/college-department.php`: 2
- `pages/academics/college/college-dept.php`: 3, 5, 12, 19, 25, 30, 31, 61, 138, 139, 145, 165
- `pages/academics/graduate-school/graduate-school.php`: 6
- `pages/academics/college/programs/bael.php`: 76, 78
- `pages/academics/college/programs/beed.php`: 65, 67
- `pages/academics/college/programs/bsa.php`: 66, 68
- `pages/academics/college/programs/bsais.php`: 67, 69
- `pages/academics/college/programs/bsba-fm.php`: 32, 34
- `pages/academics/college/programs/bsce.php`: 66, 68
- `pages/academics/college/programs/bscs.php`: 65, 67
- `pages/academics/college/programs/bsed.php`: 65, 67
- `pages/academics/college/programs/bshm.php`: 67, 69
- `pages/academics/college/programs/bsit.php`: 66, 68
- `pages/academics/college/programs/bsmls.php`: 91, 93
- `pages/academics/college/programs/bsn.php`: 34, 36
- `pages/academics/college/programs/bsph.php`: 34, 36
- `pages/academics/college/programs/bstm.php`: 67, 69
- `chatbot/inc/knowledge.php`: 57
- `chatbot/inc/prompt.php`: 8
- `chatbot/inc/retriever.php`: 29, 94, 97, 132, 178, 180, 221, 256, 276, 318, 332, 360, 373
- `admin/action/page-review-handler.php`: 51

## department_page_content

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `department_page_content` (
  `dept_id` int NOT NULL,
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `department_page_content`
  ADD PRIMARY KEY (`dept_id`,`field_key`);
ALTER TABLE `department_page_content`
  ADD CONSTRAINT `department_page_content_department` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `admin/department-page-content.php`: 36, 72
- `database/basic-education-program-migration.php`: 28
- `database/migrate-department-page-content.php`: 4

## events

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `events` (
  `event_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `program` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ALL',
  `user_id` int DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `media_id` int DEFAULT NULL,
  `status` enum('draft','pending','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_evt_cat` (`category_id`),
  ADD KEY `fk_evt_user` (`user_id`),
  ADD KEY `fk_evt_media` (`media_id`);
ALTER TABLE `events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;
ALTER TABLE `events`
  ADD CONSTRAINT `fk_evt_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evt_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `event.php`: 4, 19
- `index.php`: 550, 554, 570, 594, 601, 605, 1049, 1070, 1073, 1086, 1104, 1109, 1141, 1170, 1179, 1181
- `admin/admin-dash.php`: 67, 72, 79, 138
- `admin/admin-functions.php`: 1577, 1579, 2404, 2406, 2727, 2735, 5542
- `admin/content-edit.php`: 115, 122, 128, 193, 276, 376, 407, 413, 415, 428, 431, 436, 505, 534, 536, 547, 800
- `admin/department-page-content.php`: 28
- `admin/events.php`: 5, 16, 56, 110, 127, 150, 168, 172, 208, 216, 225, 229, 230, 248, 254, 261, 308, 418, 459, 519, 525, 844, 886, 1150
- `chatbot/chatbot.php`: 111, 140
- `chatbot/widget.php`: 10, 62
- `components/bulletin-board.php`: 43, 49, 146, 155, 228, 235, 237, 238, 239, 248, 306, 307, 311, 314, 318
- `components/department-media-manager.php`: 641
- `components/header-admin.php`: 92, 126
- `components/navbar.php`: 317, 419
- `components/program-page-manager.php`: 7, 429, 544, 605, 646
- `components/recommendation-catalog.php`: 60
- `pages/news-and-announcement.php`: 125, 129, 453, 454, 461, 462, 531, 878, 879, 882, 896
- `pages/academics/college/program.php`: 10, 238, 241
- `pages/academics/college/programs/bshm.php`: 157, 322
- `pages/academics/college/programs/bstm.php`: 108, 119, 138, 148, 158, 168, 255, 276
- `chatbot/inc/knowledge.php`: 5, 55, 74
- `chatbot/inc/prompt.php`: 8, 12
- `chatbot/inc/retriever.php`: 8, 44, 134, 212, 273, 294, 295, 296, 350, 445, 449, 467, 481, 483, 486, 488, 489

## faqs

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `faqs` (
  `faq_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `fk_faq_cat` (`category_id`);
ALTER TABLE `faqs`
  MODIFY `faq_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `faqs`
  ADD CONSTRAINT `fk_faq_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `components/navbar.php`: 308, 405
- `chatbot/inc/retriever.php`: 662

## fb_api_call_log

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `fb_api_call_log` (
  `id` int NOT NULL,
  `called_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE `fb_api_call_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `called_at` (`called_at`);
ALTER TABLE `fb_api_call_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=477;
```

Application/migration PHP references:

- `index.php`: 105, 114, 122, 123

## fb_posts_cache

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `fb_posts_cache` (
  `fb_post_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_media_id` int DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `category_locked` tinyint(1) NOT NULL DEFAULT '0',
  `permalink_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_at` datetime DEFAULT NULL,
  `created_time` datetime NOT NULL,
  `fetched_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `fb_posts_cache`
  ADD PRIMARY KEY (`fb_post_id`),
  ADD KEY `fk_fbcache_media` (`image_media_id`),
  ADD KEY `idx_fbcache_pinned` (`is_pinned`,`pinned_at`),
  ADD KEY `idx_fbcache_category` (`category_id`);
ALTER TABLE `fb_posts_cache`
  ADD CONSTRAINT `fk_fbcache_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fbcache_media` FOREIGN KEY (`image_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `announcement-article.php`: 208
- `index.php`: 95, 157, 165, 222, 451
- `admin/admin-functions.php`: 712, 1348, 1382, 1392, 1394, 1408, 1410, 1431, 1451, 1453, 1522, 2736
- `admin/backfill-fb-categorizer.php`: 25, 26
- `config/fb-categorizer.php`: 131, 134
- `pages/news-and-announcement.php`: 223, 332
- `chatbot/inc/retriever.php`: 430, 655

## hero_slides

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `hero_slides` (
  `slide_id` int NOT NULL,
  `media_type` enum('image','video') NOT NULL DEFAULT 'image',
  `media_path` varchar(255) NOT NULL,
  `media_path_mobile` varchar(255) DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `title` varchar(100) DEFAULT NULL,
  `subtitle` varchar(150) DEFAULT NULL,
  `btn1_text` varchar(30) DEFAULT NULL,
  `btn1_link` varchar(255) DEFAULT NULL,
  `btn2_text` varchar(30) DEFAULT NULL,
  `btn2_link` varchar(255) DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `status` enum('published','draft') NOT NULL DEFAULT 'published',
  `show_on_mobile` tinyint(1) NOT NULL DEFAULT '1',
  `show_gradient` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`slide_id`),
  ADD KEY `fk_hero_slides_user` (`created_by`);
ALTER TABLE `hero_slides`
  MODIFY `slide_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `hero_slides`
  ADD CONSTRAINT `fk_hero_slides_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `index.php`: 483
- `admin/admin-functions.php`: 1792, 1811, 1835, 1839, 1862, 1898, 1933, 1950, 1962, 1981, 1987, 2001

## homepage_president

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `homepage_president` (
  `id` tinyint UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `org` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eyebrow` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `heading` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `greeting` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `media_id` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `homepage_president`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_president_media` (`media_id`),
  ADD KEY `fk_president_user` (`updated_by`);
ALTER TABLE `homepage_president`
  ADD CONSTRAINT `fk_president_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_president_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `index.php`: 631, 648
- `admin/admin-functions.php`: 2748, 3587, 3596, 3679, 3697

## homepage_text

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `homepage_text` (
  `text_key` varchar(60) NOT NULL,
  `value` text,
  `media_id` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
ALTER TABLE `homepage_text`
  ADD PRIMARY KEY (`text_key`);
```

Application/migration PHP references:

- `admin/admin-functions.php`: 5522, 5526, 5581, 5691, 5695, 5741, 5742, 5753, 5757, 5765, 5767, 5779

## media_library

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `media_library` (
  `media_id` int NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `media_library`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `fk_media_user` (`created_by`);
ALTER TABLE `media_library`
  MODIFY `media_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=301;
ALTER TABLE `media_library`
  ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `announcement-article.php`: 132, 173, 188, 209
- `event.php`: 4
- `index.php`: 23, 57, 88, 158, 179, 216, 223, 267, 278, 332, 338, 399, 426, 452, 649
- `news-article.php`: 149
- `admin/admin-functions.php`: 4, 282, 332, 336, 482, 494, 506, 519, 573, 641, 798, 802, 849, 853, 880, 952, 966, 1115, 1127, 1150, 1174, 1301, 1349, 1478, 1501, 1523, 2122, 2426, 2537, 2691, 2703, 2715, 2726, 2773, 2777, 2834, 2835, 3076, 3522, 3597, 3728, 3761, 3810, 3877, 3980, 4000, 4539, 4580, 4587, 4600, 4601, 4744, 5140, 5202, 5523, 5581
- `admin/student-organizations.php`: 55, 93, 98
- `components/department-public.php`: 10, 140, 194
- `components/program-page-public.php`: 209, 210, 211, 212, 251
- `components/recommendation-catalog.php`: 20, 36, 49, 60
- `database/basic-education-program-migration.php`: 56, 62
- `database/migrate-student-organizations.php`: 18, 19, 25
- `pages/news-and-announcement.php`: 156, 191, 224, 284, 309, 333
- `chatbot/inc/retriever.php`: 170

## news

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `news` (
  `news_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Others',
  `featured_image` int DEFAULT NULL,
  `status` enum('draft','pending','published','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_pinned` tinyint(1) NOT NULL DEFAULT '0',
  `pinned_at` datetime DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `academic_year` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `news`
  ADD PRIMARY KEY (`news_id`),
  ADD KEY `fk_news_cat` (`category_id`),
  ADD KEY `fk_news_user` (`user_id`),
  ADD KEY `fk_news_media` (`featured_image`),
  ADD KEY `idx_news_pinned` (`is_pinned`,`pinned_at`);
ALTER TABLE `news`
  MODIFY `news_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_news_media` FOREIGN KEY (`featured_image`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_news_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `announcement-article.php`: 17, 21, 272
- `index.php`: 56, 75, 80, 346, 355, 425, 438, 445, 949, 950, 951, 952, 953, 958, 965, 1001, 1005, 1014, 1015, 1021, 1023, 1025, 1026, 1027, 1028, 1030, 1032, 1034, 1035, 1042, 1043, 1403, 1409, 1577
- `news-article.php`: 5, 6, 17, 18, 21, 148, 156, 211
- `news.php`: 3, 5, 12, 16
- `admin/admin-dash.php`: 50, 54, 56, 63, 137, 619, 628
- `admin/admin-functions.php`: 698, 704, 711, 732, 771, 797, 798, 837, 847, 875, 915, 938, 944, 959, 965, 975, 981, 1001, 1011, 1035, 1038, 1043, 1044, 1050, 1055, 1057, 1060, 1064, 1065, 1068, 1075, 1079, 1083, 1089, 1106, 1109, 1115, 1125, 1134, 1140, 1148, 1157, 1163, 1172, 1211, 1217, 1223, 1225, 1228, 1233, 1237, 1242, 1248, 1254, 1255, 1256, 1259, 1262, 1264, 1267, 1273, 1278, 1279, 1281, 1283, 1286, 1296, 1376, 1389, 1463, 1500, 1508, 1516, 1573, 1574, 1590, 1600, 1640, 1647, 2427, 2727, 2737, 5542
- `admin/announcement.php`: 106, 131
- `admin/content-edit.php`: 127, 247, 395, 398, 402, 403, 405, 427, 431, 435, 505
- `admin/department-page-content.php`: 28
- `admin/news.php`: 4, 7, 104, 126, 141, 166, 182, 185, 187, 221, 240, 253, 261, 265, 268, 286, 291, 311, 336, 409, 412, 416, 420, 490, 491, 576, 625, 665, 749, 790, 791, 795, 802, 804, 805, 806, 810, 811, 850, 866
- `components/bulletin-board.php`: 109, 230, 233
- `components/footer.php`: 212
- `components/header-admin.php`: 87, 125
- `components/navbar.php`: 13, 35, 36, 47, 325, 428
- `components/page-analytics.php`: 77
- `components/recommendation-catalog.php`: 45, 46, 47, 48
- `pages/news-and-announcement.php`: 41, 42, 189, 198, 199, 206, 209, 252, 257, 308, 319, 326, 383, 430, 444, 523, 531, 603, 615, 616, 621, 626, 710, 713, 756
- `chatbot/inc/knowledge.php`: 56, 73
- `chatbot/inc/prompt.php`: 8, 14
- `chatbot/inc/retriever.php`: 8, 44, 135, 274, 295, 492, 497, 505, 648
- `admin/action/create-news.php`: 4, 7, 16, 24, 29, 43, 45, 49, 60, 75, 87, 91, 115, 130, 134, 139, 213, 232, 234, 236, 240, 248, 253

## news_images

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `news_images` (
  `image_id` int NOT NULL,
  `news_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `news_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_newsimg_news` (`news_id`),
  ADD KEY `fk_newsimg_media` (`media_id`);
ALTER TABLE `news_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `news_images`
  ADD CONSTRAINT `fk_newsimg_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_newsimg_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 916, 924, 929, 951, 965, 972, 1217, 2738

## offices

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `offices` (
  `office_id` int NOT NULL,
  `office_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_hours` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `offices`
  ADD PRIMARY KEY (`office_id`);
ALTER TABLE `offices`
  MODIFY `office_id` int NOT NULL AUTO_INCREMENT;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2727, 2739, 3939, 3941, 3966, 3969, 3997, 4004, 4008, 4282, 4478, 4488
- `admin/content-edit.php`: 304, 353
- `chatbot/chatbot.php`: 111, 140
- `chatbot/widget.php`: 62
- `components/administration-manager.php`: 247, 288, 300, 332, 444, 453, 456, 467, 475, 476
- `pages/about/administration.php`: 2, 61, 62, 368, 370, 375, 377, 465, 468, 469
- `pages/academics/graduate-school/programs/ma-public-admin.php`: 224
- `chatbot/inc/knowledge.php`: 5, 41
- `chatbot/inc/prompt.php`: 8
- `chatbot/inc/retriever.php`: 139, 143, 281, 292, 293, 295, 554, 561, 572, 604, 606, 608, 620

## office_services

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `office_services` (
  `service_id` int NOT NULL,
  `office_id` int NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `procedure` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `office_services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `fk_svc_office` (`office_id`);
ALTER TABLE `office_services`
  MODIFY `service_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `office_services`
  ADD CONSTRAINT `fk_svc_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

No literal PHP references found. Retain pending external integration, scheduled task, and feature ownership review.

## pages

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `pages` (
  `page_id` int NOT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `page_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `pages`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `uniq_pages_slug` (`slug`);
ALTER TABLE `pages`
  MODIFY `page_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;
```

Application/migration PHP references:

- `announcement-article.php`: 17
- `index.php`: 1043
- `news-article.php`: 17
- `admin/admin-dash.php`: 134, 141, 534
- `admin/admin-functions.php`: 248, 2000, 2012, 2015, 2026, 2078, 2102, 2122, 2139, 2147, 2169, 2194, 2222, 2268, 2353, 2377, 2384, 2796, 2869, 2886, 2888, 2959, 3790, 3889, 5425, 5478, 5558, 5559, 5560
- `admin/content-edit.php`: 8, 79, 80, 81, 82, 83, 84, 85, 86, 92, 100, 118, 144, 212, 214, 218, 227, 230, 232, 235, 241, 357, 367
- `admin/photo-check.php`: 159
- `admin/rich-text.php`: 90, 122
- `admin/student-organizations.php`: 3
- `chatbot/widget.php`: 8
- `components/bulletin-board.php`: 233, 273
- `components/department-media-manager.php`: 97, 946
- `components/department-page.php`: 31
- `components/department-public.php`: 3, 5, 6, 174, 177
- `components/footer.php`: 59, 67, 92
- `components/loading-feedback.php`: 1
- `components/navbar.php`: 15, 60, 68, 70, 71, 72, 73, 78, 83, 91, 92, 101, 102, 284, 285, 286, 287, 288, 304, 305, 306, 307, 316, 320, 325, 328, 373, 374, 375, 376, 377, 401, 402, 403, 404, 418, 422, 428, 435
- `components/page-analytics.php`: 3, 66, 73, 76, 150
- `components/pdf-viewer.php`: 100, 101
- `components/popular-pages.php`: 6, 10, 12, 15
- `components/program-page-manager.php`: 264
- `components/program-page-public.php`: 4
- `components/program-pdf-manager.php`: 17
- `components/recommendation-catalog.php`: 29, 32, 38, 40, 41, 42, 67, 68, 69, 70, 71, 76, 77, 80
- `components/recommendations.php`: 8
- `includes/student-achievements.php`: 28, 30, 34
- `pages/news-and-announcement.php`: 788
- `pages/admission/tuition-fees.php`: 69
- `pages/student life/student-achievement.php`: 11, 75, 178, 179, 181, 184, 213
- `pages/student life/student organization and leadership program/student-org.php`: 10, 11, 71, 73, 74
- `pages/academics/basic-edu/basic-edu-dept.php`: 105
- `pages/academics/college/college-dept.php`: 25, 27
- `pages/academics/college/program.php`: 5, 47, 193, 195, 196, 198, 286
- `pages/academics/graduate-school/programs/ma-business-admin.php`: 61
- `pages/academics/graduate-school/programs/ma-educ.php`: 61
- `pages/academics/graduate-school/programs/ma-public-admin.php`: 62
- `pages/academics/graduate-school/programs/phd-educ-mgt.php`: 62
- `pages/academics/college/programs/bael.php`: 76, 78
- `pages/academics/college/programs/beed.php`: 65, 67
- `pages/academics/college/programs/bsa.php`: 66, 68
- `pages/academics/college/programs/bsais.php`: 67, 69
- `pages/academics/college/programs/bsba-fm.php`: 32, 34
- `pages/academics/college/programs/bsce.php`: 66, 68
- `pages/academics/college/programs/bscs.php`: 65, 67
- `pages/academics/college/programs/bsed.php`: 65, 67
- `pages/academics/college/programs/bshm.php`: 67, 69
- `pages/academics/college/programs/bsit.php`: 66, 68
- `pages/academics/college/programs/bsmls.php`: 91, 93
- `pages/academics/college/programs/bsn.php`: 34, 36
- `pages/academics/college/programs/bsph.php`: 34, 36
- `pages/academics/college/programs/bstm.php`: 67, 69
- `chatbot/inc/knowledge.php`: 56, 57, 58, 59, 63
- `chatbot/inc/retriever.php`: 30, 53, 56, 515, 675
- `admin/action/program-page-handler.php`: 36, 49

## page_sections

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `page_sections` (
  `section_id` int NOT NULL,
  `page_id` int DEFAULT NULL,
  `section_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `draft_content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','published') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `review_status` enum('none','pending','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `submitted_by` int DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `media_id` int DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `page_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uniq_page_section_key` (`page_id`,`section_key`),
  ADD KEY `fk_ps_page` (`page_id`),
  ADD KEY `fk_page_sections_user` (`updated_by`),
  ADD KEY `idx_page_sections_review` (`review_status`);
ALTER TABLE `page_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;
ALTER TABLE `page_sections`
  ADD CONSTRAINT `fk_page_sections_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ps_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`page_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2036, 2055, 2056, 2077, 2101, 2122, 2139, 2147, 2155, 2168, 2175, 2193, 2204, 2221, 2241, 2242, 2250, 2267, 2299, 2310, 2315, 2325, 2327, 2339, 2341, 2352, 2378, 2740
- `admin/rich-text.php`: 89
- `components/header-admin.php`: 101
- `pages/about/vision-mission.php`: 5
- `chatbot/inc/retriever.php`: 9, 515
- `admin/action/page-review-handler.php`: 124, 139

## page_visits

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `page_visits` (
  `page_id` int UNSIGNED NOT NULL,
  `page_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `visit_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_visited` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `page_visits`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `uq_page_url` (`page_url`),
  ADD KEY `idx_visit_count` (`visit_count`);
ALTER TABLE `page_visits`
  MODIFY `page_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=859;
```

Application/migration PHP references:

- `components/footer.php`: 59
- `components/page-analytics.php`: 6, 24, 115, 127, 170, 186
- `components/popular-pages.php`: 14
- `components/recommendation-catalog.php`: 84

## page_visits_daily

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `page_visits_daily` (
  `page_id` int UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `views` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `page_visits_daily`
  ADD PRIMARY KEY (`page_id`,`visit_date`),
  ADD KEY `idx_visit_date` (`visit_date`);
```

Application/migration PHP references:

- `components/page-analytics.php`: 7, 36, 132, 188, 211

## programs

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `programs` (
  `program_id` int NOT NULL,
  `program_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `image_media_id` int DEFAULT NULL,
  `theme_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_color_light` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dept_id` int DEFAULT NULL,
  `pdf_media_id` int DEFAULT NULL,
  `program_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT '1',
  `core_values_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `hero_media_id` int DEFAULT NULL,
  `seal_media_id` int DEFAULT NULL,
  `page_content` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `uq_programs_code` (`program_code`),
  ADD UNIQUE KEY `uq_programs_slug` (`slug`),
  ADD KEY `idx_programs_dept` (`dept_id`),
  ADD KEY `idx_programs_pdf` (`pdf_media_id`),
  ADD KEY `idx_programs_image` (`image_media_id`);
ALTER TABLE `programs`
  MODIFY `program_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_programs_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_programs_image` FOREIGN KEY (`image_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_programs_pdf` FOREIGN KEY (`pdf_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `index.php`: 513, 615, 620, 1064, 1069, 1070, 1077, 1088, 1089, 1091, 1170, 1249, 1323, 1608
- `admin/admin-functions.php`: 202, 206, 216, 2393, 2397, 2431, 2563, 2578, 2742, 2743, 2744, 2745, 2887, 2897, 2929, 2937, 2959, 3019, 3724, 3728, 3731, 3734, 3742, 3759, 3765, 3772, 3835, 3848, 4564, 4572, 4598, 4686, 4689, 4704, 4712, 4731, 4753, 4767, 4827, 4837, 4844, 4858, 4965, 4989, 5377, 5469, 5478, 5559
- `admin/announcement.php`: 151, 234, 462
- `admin/basic-education-content.php`: 3, 35
- `admin/content-edit.php`: 74, 84, 104, 178, 188, 199, 222, 229, 298, 301, 304, 353, 372, 373, 494, 561, 636
- `admin/department-page-content.php`: 15, 97, 113, 122
- `admin/events.php`: 506, 603
- `chatbot/chatbot.php`: 111, 140
- `chatbot/widget.php`: 62
- `components/calendar-filters.php`: 10, 12, 16, 21
- `components/department-media-manager.php`: 15, 51, 93, 142, 285, 297, 420, 762
- `components/department-page.php`: 21, 152, 170, 270, 271, 272, 273, 276, 279, 280, 410, 411, 439
- `components/department-public.php`: 9, 125, 134, 139, 158, 167, 168, 177
- `components/navbar.php`: 29, 92, 134, 141
- `components/program-page-manager.php`: 12, 32, 34, 212, 214, 220, 496
- `components/program-page-public.php`: 132, 207, 216, 301, 317
- `components/program-pdf-manager.php`: 22, 26, 120
- `components/program-public.php`: 25
- `components/recommendation-catalog.php`: 19, 23, 29, 69
- `components/tuition-fee-documents.php`: 35
- `database/basic-education-program-migration.php`: 13, 16, 67, 70, 79, 85, 87
- `database/migrate-basic-education-programs.php`: 9
- `pages/contact-us.php`: 215
- `pages/news-and-announcement.php`: 92, 465, 466, 467, 469, 470, 471, 472, 473, 474, 485, 501, 694, 824, 828, 831, 833, 841, 842, 844, 856
- `pages/admission/scholarship.php`: 47
- `pages/academics/college/college-dept.php`: 84
- `pages/academics/college/program.php`: 14
- `pages/academics/graduate-school/graduate-school.php`: 6
- `pages/academics/graduate-school/programs/ma-educ.php`: 211, 222, 234
- `pages/academics/graduate-school/programs/ma-public-admin.php`: 224, 246, 257
- `pages/academics/graduate-school/programs/phd-educ-mgt.php`: 102, 154, 174, 225, 236, 248, 259
- `pages/academics/college/programs/beed.php`: 280
- `pages/academics/college/programs/bsed.php`: 257, 271, 286
- `pages/academics/college/programs/bsmls.php`: 12
- `chatbot/inc/knowledge.php`: 5, 77
- `chatbot/inc/prompt.php`: 8, 11, 12, 21
- `chatbot/inc/retriever.php`: 111, 131, 170, 185, 212, 275, 300, 301, 322, 366
- `admin/action/page-review-handler.php`: 51

## program_items

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `program_items` (
  `item_id` int NOT NULL,
  `program_id` int NOT NULL,
  `item_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subtitle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `icon` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `letter` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_media_id` int DEFAULT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `program_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_program_items_order` (`program_id`,`item_type`,`display_order`);
ALTER TABLE `program_items`
  MODIFY `item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;
ALTER TABLE `program_items`
  ADD CONSTRAINT `fk_program_items_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2746, 3025, 3043, 3053, 5090, 5099, 5105, 5112, 5113, 5235, 5325, 5334, 5344, 5399, 5407
- `components/program-page-manager.php`: 33
- `components/program-page-public.php`: 250

## program_sections

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `program_sections` (
  `section_id` int NOT NULL,
  `program_id` int NOT NULL,
  `section_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` json DEFAULT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `program_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `idx_program_sections_order` (`program_id`,`display_order`);
ALTER TABLE `program_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT;
ALTER TABLE `program_sections`
  ADD CONSTRAINT `fk_program_sections_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

No literal PHP references found. Retain pending external integration, scheduled task, and feature ownership review.

## site_visitors

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `site_visitors` (
  `id` int UNSIGNED NOT NULL,
  `visitor_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `visit_date` date NOT NULL,
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `site_visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitor_day` (`visit_date`,`visitor_hash`),
  ADD KEY `idx_visitor` (`visitor_hash`);
ALTER TABLE `site_visitors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=860;
```

Application/migration PHP references:

- `components/page-analytics.php`: 8, 196
- `components/visitor-counter.php`: 12, 30, 57

## slug_history

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `slug_history` (
  `id` int NOT NULL,
  `type` enum('department','program') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_slug` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `slug_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug_history_type_slug` (`type`,`old_slug`);
ALTER TABLE `slug_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 4837
- `components/department-public.php`: 168
- `components/program-page-public.php`: 310, 316
- `database/basic-education-program-migration.php`: 79

## student_achievements

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_achievements` (
  `achievement_id` int UNSIGNED NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `program_organization` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category_label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `academic_year` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `thumbnail_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `achievers` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_achievements`
  ADD PRIMARY KEY (`achievement_id`),
  ADD KEY `achievement_listing` (`status`,`academic_year`,`sort_order`),
  ADD KEY `achievement_featured` (`status`,`is_featured`,`sort_order`);
ALTER TABLE `student_achievements`
  MODIFY `achievement_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;
```

Application/migration PHP references:

- `database/migrate-student-achievements.php`: 4, 21, 23, 24, 26, 33
- `includes/student-achievements.php`: 19, 20, 25, 31, 42, 87, 90, 109
- `pages/student life/student-achievement.php`: 13, 18
- `admin/action/student-achievements-handler.php`: 22, 30, 31, 76

## student_achievement_photos

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_achievement_photos` (
  `photo_id` int UNSIGNED NOT NULL,
  `achievement_id` int UNSIGNED NOT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_achievement_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `achievement_photo_order` (`achievement_id`,`sort_order`,`photo_id`);
ALTER TABLE `student_achievement_photos`
  MODIFY `photo_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;
ALTER TABLE `student_achievement_photos`
  ADD CONSTRAINT `student_achievement_photos_ibfk_1` FOREIGN KEY (`achievement_id`) REFERENCES `student_achievements` (`achievement_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `database/migrate-student-achievements.php`: 27
- `includes/student-achievements.php`: 51, 109
- `admin/action/student-achievements-handler.php`: 57, 61, 66, 71, 74

## student_organizations

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_organizations` (
  `org_id` int NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `about_text` text COLLATE utf8mb4_unicode_ci,
  `vision` text COLLATE utf8mb4_unicode_ci,
  `mission` text COLLATE utf8mb4_unicode_ci,
  `facebook_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_media_id` int DEFAULT NULL,
  `cover_media_id` int DEFAULT NULL,
  `status` enum('draft','published') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_organizations`
  ADD PRIMARY KEY (`org_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `org_public_order` (`status`,`display_order`,`org_id`),
  ADD KEY `org_category` (`category_id`),
  ADD KEY `logo_media_id` (`logo_media_id`),
  ADD KEY `cover_media_id` (`cover_media_id`);
ALTER TABLE `student_organizations`
  MODIFY `org_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=262;
ALTER TABLE `student_organizations`
  ADD CONSTRAINT `student_organizations_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `student_org_categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_organizations_ibfk_2` FOREIGN KEY (`logo_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_organizations_ibfk_3` FOREIGN KEY (`cover_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2751, 2752
- `admin/student-organizations.php`: 92, 168, 170, 173, 194, 201, 205
- `database/migrate-student-organizations.php`: 9, 24
- `admin/action/student-organizations-handler.php`: 14, 18

## student_org_categories

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_org_categories` (
  `category_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_org_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);
ALTER TABLE `student_org_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;
```

Application/migration PHP references:

- `admin/student-organizations.php`: 86, 92
- `database/migrate-student-organizations.php`: 5, 17
- `admin/action/student-organizations-handler.php`: 31, 33, 34, 37

## student_org_gallery

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_org_gallery` (
  `image_id` int NOT NULL,
  `org_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_org_gallery`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `gallery_order` (`org_id`,`display_order`,`image_id`),
  ADD KEY `media_id` (`media_id`);
ALTER TABLE `student_org_gallery`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;
ALTER TABLE `student_org_gallery`
  ADD CONSTRAINT `student_org_gallery_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `student_organizations` (`org_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_org_gallery_ibfk_2` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE;
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2753
- `admin/student-organizations.php`: 98, 176, 178
- `database/migrate-student-organizations.php`: 21

## student_org_page_content

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `student_org_page_content` (
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `student_org_page_content`
  ADD PRIMARY KEY (`field_key`);
```

Application/migration PHP references:

- `admin/admin-functions.php`: 2754
- `admin/student-organizations.php`: 55, 69, 79, 110
- `database/migrate-student-organizations.php`: 27

## tuition_fee_content

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `tuition_fee_content` (
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `tuition_fee_content`
  ADD PRIMARY KEY (`field_key`);
```

Application/migration PHP references:

- `components/tuition-fee-documents.php`: 45
- `database/migrate-tuition-fees.php`: 7
- `admin/action/tuition-fees-handler.php`: 23, 61

## tuition_fee_documents

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `tuition_fee_documents` (
  `document_id` int NOT NULL,
  `category_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `tuition_fee_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `file_path` (`file_path`);
ALTER TABLE `tuition_fee_documents`
  MODIFY `document_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
```

Application/migration PHP references:

- `components/tuition-fee-documents.php`: 59
- `database/migrate-tuition-fees.php`: 6, 11, 20
- `admin/action/tuition-fees-handler.php`: 32, 36, 58, 59, 65

## users

Exported structure (including subsequent indexes, auto-increment settings, and constraints):

```sql
CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Super Admin','Content Admin','Content Editor','Office Staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Content Editor',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `otp_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_verified` tinyint(1) NOT NULL DEFAULT '0',
  `login_attempts` int NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `remember_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token_expires_at` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
```

Application/migration PHP references:

- `announcement-article.php`: 133
- `admin/admin-dash.php`: 45, 57, 73
- `admin/admin-functions.php`: 49, 172, 491, 516, 1116, 1126, 1149, 1173, 1605, 1607, 2269, 2270, 2354
- `admin/events.php`: 256
- `components/header-admin.php`: 143
- `components/navbar.php`: 544
- `chatbot/inc/retriever.php`: 10
- `admin/auth/auth_handler.php`: 65, 99, 108, 150, 211, 244, 289, 303, 354, 463, 493
- `admin/auth/verify-otp.php`: 37, 58, 66
