-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Nov 06, 2026 at 09:51 AM
-- Server version: 8.0.46
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gnc_admin`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_offices`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `admin_office_services`
--

CREATE TABLE `admin_office_services` (
  `office_id` int NOT NULL,
  `display_order` int UNSIGNED NOT NULL,
  `service_name` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_onboarding`
--

CREATE TABLE `admin_onboarding` (
  `user_id` int NOT NULL,
  `status` varchar(16) NOT NULL,
  `finished_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_people`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `admin_sections`
--

CREATE TABLE `admin_sections` (
  `section_key` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `layout` enum('people','offices') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'people',
  `display_order` int NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_settings`
--

CREATE TABLE `admin_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `announcement_categories`
--

CREATE TABLE `announcement_categories` (
  `announcement_id` int NOT NULL,
  `category_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_departments`
--

CREATE TABLE `announcement_departments` (
  `announcement_id` int NOT NULL,
  `dept_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_facebook_deliveries`
--

CREATE TABLE `announcement_facebook_deliveries` (
  `announcement_id` int NOT NULL,
  `page_id` varchar(40) NOT NULL,
  `state` varchar(20) NOT NULL DEFAULT 'ready',
  `post_id` varchar(100) DEFAULT NULL,
  `post_url` varchar(255) DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  `attempted_by` int NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_images`
--

CREATE TABLE `announcement_images` (
  `image_id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcement_programs`
--

CREATE TABLE `announcement_programs` (
  `announcement_id` int NOT NULL,
  `program_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_password_resets`
--

CREATE TABLE `auth_password_resets` (
  `user_id` int NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_rate_limits`
--

CREATE TABLE `auth_rate_limits` (
  `bucket_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `attempts` int UNSIGNED NOT NULL DEFAULT '1',
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_security_logs`
--

CREATE TABLE `auth_security_logs` (
  `log_id` bigint UNSIGNED NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `basic_education_content`
--

CREATE TABLE `basic_education_content` (
  `page_key` varchar(60) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `basic_enrollment_steps`
--

CREATE TABLE `basic_enrollment_steps` (
  `step_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `instruction` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `illustration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `display_order` int NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_import_batches`
--

CREATE TABLE `calendar_import_batches` (
  `token` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series_id` int NOT NULL,
  `user_id` int NOT NULL,
  `base_revision` int NOT NULL,
  `state` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preview',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preview_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `approved_json` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `calendar_action` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_import_series`
--

CREATE TABLE `calendar_import_series` (
  `series_id` int NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_year` smallint NOT NULL,
  `revision` int NOT NULL DEFAULT '0',
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `calendar_import_sources`
--

CREATE TABLE `calendar_import_sources` (
  `event_id` int NOT NULL,
  `series_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_activity_page`
--

CREATE TABLE `community_activity_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_extension_page`
--

CREATE TABLE `community_extension_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` mediumtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_editor_drafts`
--

CREATE TABLE `content_editor_drafts` (
  `draft_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `page_key` char(64) NOT NULL,
  `revision` int UNSIGNED NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_editor_draft_chunks`
--

CREATE TABLE `content_editor_draft_chunks` (
  `draft_id` bigint UNSIGNED NOT NULL,
  `chunk_no` int UNSIGNED NOT NULL,
  `payload` mediumblob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_migrations`
--

CREATE TABLE `content_migrations` (
  `migration_key` varchar(100) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `csrf_tokens`
--

CREATE TABLE `csrf_tokens` (
  `token_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `department_page_content`
--

CREATE TABLE `department_page_content` (
  `dept_id` int NOT NULL,
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollment_illustrations`
--

CREATE TABLE `enrollment_illustrations` (
  `image_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

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
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dept_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `audience_group` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_departments`
--

CREATE TABLE `event_departments` (
  `event_id` int NOT NULL,
  `dept_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_programs`
--

CREATE TABLE `event_programs` (
  `event_id` int NOT NULL,
  `program_code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_publishing_connection`
--

CREATE TABLE `facebook_publishing_connection` (
  `connection_id` tinyint UNSIGNED NOT NULL,
  `page_id` varchar(40) NOT NULL,
  `page_name` varchar(255) NOT NULL,
  `environment` varchar(10) NOT NULL DEFAULT 'test',
  `enabled` tinyint NOT NULL DEFAULT '0',
  `connected_by` int NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `facebook_publishing_requests`
--

CREATE TABLE `facebook_publishing_requests` (
  `request_key` char(64) NOT NULL,
  `user_id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `faq_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `question` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faq_page`
--

CREATE TABLE `faq_page` (
  `page_id` tinyint UNSIGNED NOT NULL,
  `content` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fb_api_call_log`
--

CREATE TABLE `fb_api_call_log` (
  `id` int NOT NULL,
  `called_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fb_posts_cache`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `hero_slides`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `homepage_president`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `homepage_text`
--

CREATE TABLE `homepage_text` (
  `text_key` varchar(60) NOT NULL,
  `value` text,
  `media_id` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `image_display_settings`
--

CREATE TABLE `image_display_settings` (
  `image_path` varchar(500) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `position_x` decimal(7,4) NOT NULL DEFAULT '50.0000',
  `position_y` decimal(7,4) NOT NULL DEFAULT '50.0000',
  `zoom` decimal(5,4) NOT NULL DEFAULT '1.0000',
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_library`
--

CREATE TABLE `media_library` (
  `media_id` int NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `news_images`
--

CREATE TABLE `news_images` (
  `image_id` int NOT NULL,
  `news_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `offices`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `office_services`
--

CREATE TABLE `office_services` (
  `service_id` int NOT NULL,
  `office_id` int NOT NULL,
  `service_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `procedure` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `page_sections`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `page_visits`
--

CREATE TABLE `page_visits` (
  `page_id` int UNSIGNED NOT NULL,
  `page_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `visit_count` int UNSIGNED NOT NULL DEFAULT '0',
  `last_visited` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `page_visits_daily`
--

CREATE TABLE `page_visits_daily` (
  `page_id` int UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `views` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `program_items`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `program_sections`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `rbac_submissions`
--

CREATE TABLE `rbac_submissions` (
  `submission_id` int NOT NULL,
  `user_id` int NOT NULL,
  `kind` varchar(30) NOT NULL,
  `route` varchar(150) NOT NULL,
  `target_id` int DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `revision` int NOT NULL DEFAULT '1',
  `reason` text,
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rbac_submission_chunks`
--

CREATE TABLE `rbac_submission_chunks` (
  `submission_id` int NOT NULL,
  `chunk_no` int NOT NULL,
  `payload` mediumblob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `responsive_background_settings`
--

CREATE TABLE `responsive_background_settings` (
  `page_key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_key` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `selector` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `settings` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_id` int DEFAULT NULL,
  `updated_by` int NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_visitors`
--

CREATE TABLE `site_visitors` (
  `id` int UNSIGNED NOT NULL,
  `visitor_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `visit_date` date NOT NULL,
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slug_history`
--

CREATE TABLE `slug_history` (
  `id` int NOT NULL,
  `type` enum('department','program') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_slug` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_achievements`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `student_achievement_achievers`
--

CREATE TABLE `student_achievement_achievers` (
  `achievement_id` int UNSIGNED NOT NULL,
  `display_order` int UNSIGNED NOT NULL,
  `achiever_name` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_achievement_photos`
--

CREATE TABLE `student_achievement_photos` (
  `photo_id` int UNSIGNED NOT NULL,
  `achievement_id` int UNSIGNED NOT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_organizations`
--

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
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_org_categories`
--

CREATE TABLE `student_org_categories` (
  `category_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_org_gallery`
--

CREATE TABLE `student_org_gallery` (
  `image_id` int NOT NULL,
  `org_id` int NOT NULL,
  `media_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_org_page_content`
--

CREATE TABLE `student_org_page_content` (
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `text_layout_settings`
--

CREATE TABLE `text_layout_settings` (
  `page_key` char(64) NOT NULL,
  `settings` mediumtext NOT NULL,
  `updated_by` bigint UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tuition_fee_content`
--

CREATE TABLE `tuition_fee_content` (
  `field_key` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tuition_fee_documents`
--

CREATE TABLE `tuition_fee_documents` (
  `document_id` int NOT NULL,
  `category_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('Super Admin','Content Admin','Content Editor','Office Staff','The Mirror') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Content Editor',
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
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` datetime DEFAULT NULL,
  `credential_version` int UNSIGNED NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_log_user` (`user_id`);

--
-- Indexes for table `admin_offices`
--
ALTER TABLE `admin_offices`
  ADD PRIMARY KEY (`office_id`),
  ADD KEY `idx_admin_offices_section` (`section_key`,`display_order`);

--
-- Indexes for table `admin_office_services`
--
ALTER TABLE `admin_office_services`
  ADD PRIMARY KEY (`office_id`,`display_order`);

--
-- Indexes for table `admin_onboarding`
--
ALTER TABLE `admin_onboarding`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `admin_people`
--
ALTER TABLE `admin_people`
  ADD PRIMARY KEY (`person_id`),
  ADD KEY `idx_admin_people_section` (`section_key`,`display_order`);

--
-- Indexes for table `admin_sections`
--
ALTER TABLE `admin_sections`
  ADD PRIMARY KEY (`section_key`);

--
-- Indexes for table `admin_settings`
--
ALTER TABLE `admin_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `fk_ann_cat` (`category_id`),
  ADD KEY `fk_ann_user` (`user_id`),
  ADD KEY `idx_announcements_program` (`program`),
  ADD KEY `idx_announcements_pinned` (`is_pinned`,`pinned_at`),
  ADD KEY `idx_announcements_dept` (`dept_id`);

--
-- Indexes for table `announcement_categories`
--
ALTER TABLE `announcement_categories`
  ADD PRIMARY KEY (`announcement_id`,`category_id`),
  ADD KEY `idx_announcement_category` (`category_id`,`announcement_id`);

--
-- Indexes for table `announcement_departments`
--
ALTER TABLE `announcement_departments`
  ADD PRIMARY KEY (`announcement_id`,`dept_id`),
  ADD KEY `idx_ann_dept` (`dept_id`);

--
-- Indexes for table `announcement_facebook_deliveries`
--
ALTER TABLE `announcement_facebook_deliveries`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_annimg_announcement` (`announcement_id`),
  ADD KEY `fk_annimg_media` (`media_id`);

--
-- Indexes for table `announcement_programs`
--
ALTER TABLE `announcement_programs`
  ADD PRIMARY KEY (`announcement_id`,`program_code`),
  ADD KEY `idx_ann_prog` (`program_code`);

--
-- Indexes for table `auth_password_resets`
--
ALTER TABLE `auth_password_resets`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`);

--
-- Indexes for table `auth_rate_limits`
--
ALTER TABLE `auth_rate_limits`
  ADD PRIMARY KEY (`bucket_hash`),
  ADD KEY `auth_rate_expiry` (`expires_at`);

--
-- Indexes for table `auth_security_logs`
--
ALTER TABLE `auth_security_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `auth_log_time` (`created_at`),
  ADD KEY `auth_log_user` (`user_id`);

--
-- Indexes for table `basic_education_content`
--
ALTER TABLE `basic_education_content`
  ADD PRIMARY KEY (`page_key`,`field_key`);

--
-- Indexes for table `basic_enrollment_steps`
--
ALTER TABLE `basic_enrollment_steps`
  ADD PRIMARY KEY (`step_id`);

--
-- Indexes for table `calendar_import_batches`
--
ALTER TABLE `calendar_import_batches`
  ADD PRIMARY KEY (`token`),
  ADD KEY `series_id` (`series_id`),
  ADD KEY `user_id` (`user_id`,`state`);

--
-- Indexes for table `calendar_import_series`
--
ALTER TABLE `calendar_import_series`
  ADD PRIMARY KEY (`series_id`);

--
-- Indexes for table `calendar_import_sources`
--
ALTER TABLE `calendar_import_sources`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `series_id` (`series_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `community_activity_page`
--
ALTER TABLE `community_activity_page`
  ADD PRIMARY KEY (`page_id`);

--
-- Indexes for table `community_extension_page`
--
ALTER TABLE `community_extension_page`
  ADD PRIMARY KEY (`page_id`);

--
-- Indexes for table `content_editor_drafts`
--
ALTER TABLE `content_editor_drafts`
  ADD PRIMARY KEY (`draft_id`),
  ADD UNIQUE KEY `owner_page` (`user_id`,`page_key`);

--
-- Indexes for table `content_editor_draft_chunks`
--
ALTER TABLE `content_editor_draft_chunks`
  ADD PRIMARY KEY (`draft_id`,`chunk_no`);

--
-- Indexes for table `content_migrations`
--
ALTER TABLE `content_migrations`
  ADD PRIMARY KEY (`migration_key`);

--
-- Indexes for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  ADD PRIMARY KEY (`token_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`dept_id`),
  ADD UNIQUE KEY `uq_departments_slug` (`slug`),
  ADD KEY `fk_dept_logo` (`media_id`),
  ADD KEY `fk_dept_dean_media` (`dean_media_id`);

--
-- Indexes for table `department_page_content`
--
ALTER TABLE `department_page_content`
  ADD PRIMARY KEY (`dept_id`,`field_key`);

--
-- Indexes for table `enrollment_illustrations`
--
ALTER TABLE `enrollment_illustrations`
  ADD PRIMARY KEY (`image_key`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_evt_cat` (`category_id`),
  ADD KEY `fk_evt_user` (`user_id`),
  ADD KEY `fk_evt_media` (`media_id`),
  ADD KEY `idx_events_department` (`dept_id`);

--
-- Indexes for table `event_departments`
--
ALTER TABLE `event_departments`
  ADD PRIMARY KEY (`event_id`,`dept_id`),
  ADD KEY `idx_evt_dept` (`dept_id`);

--
-- Indexes for table `event_programs`
--
ALTER TABLE `event_programs`
  ADD PRIMARY KEY (`event_id`,`program_code`),
  ADD KEY `idx_evt_prog` (`program_code`);

--
-- Indexes for table `facebook_publishing_connection`
--
ALTER TABLE `facebook_publishing_connection`
  ADD PRIMARY KEY (`connection_id`);

--
-- Indexes for table `facebook_publishing_requests`
--
ALTER TABLE `facebook_publishing_requests`
  ADD PRIMARY KEY (`request_key`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `fk_faq_cat` (`category_id`);

--
-- Indexes for table `faq_page`
--
ALTER TABLE `faq_page`
  ADD PRIMARY KEY (`page_id`);

--
-- Indexes for table `fb_api_call_log`
--
ALTER TABLE `fb_api_call_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `called_at` (`called_at`);

--
-- Indexes for table `fb_posts_cache`
--
ALTER TABLE `fb_posts_cache`
  ADD PRIMARY KEY (`fb_post_id`),
  ADD KEY `fk_fbcache_media` (`image_media_id`),
  ADD KEY `idx_fbcache_pinned` (`is_pinned`,`pinned_at`),
  ADD KEY `idx_fbcache_category` (`category_id`);

--
-- Indexes for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD PRIMARY KEY (`slide_id`),
  ADD KEY `fk_hero_slides_user` (`created_by`);

--
-- Indexes for table `homepage_president`
--
ALTER TABLE `homepage_president`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_president_media` (`media_id`),
  ADD KEY `fk_president_user` (`updated_by`);

--
-- Indexes for table `homepage_text`
--
ALTER TABLE `homepage_text`
  ADD PRIMARY KEY (`text_key`);

--
-- Indexes for table `image_display_settings`
--
ALTER TABLE `image_display_settings`
  ADD PRIMARY KEY (`image_path`);

--
-- Indexes for table `media_library`
--
ALTER TABLE `media_library`
  ADD PRIMARY KEY (`media_id`),
  ADD KEY `fk_media_user` (`created_by`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`news_id`),
  ADD KEY `fk_news_cat` (`category_id`),
  ADD KEY `fk_news_user` (`user_id`),
  ADD KEY `fk_news_media` (`featured_image`),
  ADD KEY `idx_news_pinned` (`is_pinned`,`pinned_at`);

--
-- Indexes for table `news_images`
--
ALTER TABLE `news_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_newsimg_news` (`news_id`),
  ADD KEY `fk_newsimg_media` (`media_id`);

--
-- Indexes for table `offices`
--
ALTER TABLE `offices`
  ADD PRIMARY KEY (`office_id`);

--
-- Indexes for table `office_services`
--
ALTER TABLE `office_services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `fk_svc_office` (`office_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `uniq_pages_slug` (`slug`);

--
-- Indexes for table `page_sections`
--
ALTER TABLE `page_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uniq_page_section_key` (`page_id`,`section_key`),
  ADD KEY `fk_ps_page` (`page_id`),
  ADD KEY `fk_page_sections_user` (`updated_by`),
  ADD KEY `idx_page_sections_review` (`review_status`);

--
-- Indexes for table `page_visits`
--
ALTER TABLE `page_visits`
  ADD PRIMARY KEY (`page_id`),
  ADD UNIQUE KEY `uq_page_url` (`page_url`),
  ADD KEY `idx_visit_count` (`visit_count`);

--
-- Indexes for table `page_visits_daily`
--
ALTER TABLE `page_visits_daily`
  ADD PRIMARY KEY (`page_id`,`visit_date`),
  ADD KEY `idx_visit_date` (`visit_date`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `uq_programs_code` (`program_code`),
  ADD UNIQUE KEY `uq_programs_slug` (`slug`),
  ADD KEY `idx_programs_dept` (`dept_id`),
  ADD KEY `idx_programs_pdf` (`pdf_media_id`),
  ADD KEY `idx_programs_image` (`image_media_id`);

--
-- Indexes for table `program_items`
--
ALTER TABLE `program_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_program_items_order` (`program_id`,`item_type`,`display_order`);

--
-- Indexes for table `program_sections`
--
ALTER TABLE `program_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `idx_program_sections_order` (`program_id`,`display_order`);

--
-- Indexes for table `rbac_submissions`
--
ALTER TABLE `rbac_submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `rbac_owner_status` (`user_id`,`status`),
  ADD KEY `rbac_review_status` (`status`,`created_at`);

--
-- Indexes for table `rbac_submission_chunks`
--
ALTER TABLE `rbac_submission_chunks`
  ADD PRIMARY KEY (`submission_id`,`chunk_no`);

--
-- Indexes for table `responsive_background_settings`
--
ALTER TABLE `responsive_background_settings`
  ADD PRIMARY KEY (`page_key`,`element_key`,`device`),
  ADD KEY `media_id` (`media_id`);

--
-- Indexes for table `site_visitors`
--
ALTER TABLE `site_visitors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_visitor_day` (`visit_date`,`visitor_hash`),
  ADD KEY `idx_visitor` (`visitor_hash`);

--
-- Indexes for table `slug_history`
--
ALTER TABLE `slug_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_slug_history_type_slug` (`type`,`old_slug`);

--
-- Indexes for table `student_achievements`
--
ALTER TABLE `student_achievements`
  ADD PRIMARY KEY (`achievement_id`),
  ADD KEY `achievement_listing` (`status`,`academic_year`,`sort_order`),
  ADD KEY `achievement_featured` (`status`,`is_featured`,`sort_order`);

--
-- Indexes for table `student_achievement_achievers`
--
ALTER TABLE `student_achievement_achievers`
  ADD PRIMARY KEY (`achievement_id`,`display_order`);

--
-- Indexes for table `student_achievement_photos`
--
ALTER TABLE `student_achievement_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `achievement_photo_order` (`achievement_id`,`sort_order`,`photo_id`);

--
-- Indexes for table `student_organizations`
--
ALTER TABLE `student_organizations`
  ADD PRIMARY KEY (`org_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `org_public_order` (`status`,`display_order`,`org_id`),
  ADD KEY `org_category` (`category_id`),
  ADD KEY `logo_media_id` (`logo_media_id`),
  ADD KEY `cover_media_id` (`cover_media_id`);

--
-- Indexes for table `student_org_categories`
--
ALTER TABLE `student_org_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `student_org_gallery`
--
ALTER TABLE `student_org_gallery`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `gallery_order` (`org_id`,`display_order`,`image_id`),
  ADD KEY `media_id` (`media_id`);

--
-- Indexes for table `student_org_page_content`
--
ALTER TABLE `student_org_page_content`
  ADD PRIMARY KEY (`field_key`);

--
-- Indexes for table `text_layout_settings`
--
ALTER TABLE `text_layout_settings`
  ADD PRIMARY KEY (`page_key`);

--
-- Indexes for table `tuition_fee_content`
--
ALTER TABLE `tuition_fee_content`
  ADD PRIMARY KEY (`field_key`);

--
-- Indexes for table `tuition_fee_documents`
--
ALTER TABLE `tuition_fee_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD UNIQUE KEY `file_path` (`file_path`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_offices`
--
ALTER TABLE `admin_offices`
  MODIFY `office_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_people`
--
ALTER TABLE `admin_people`
  MODIFY `person_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_images`
--
ALTER TABLE `announcement_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_security_logs`
--
ALTER TABLE `auth_security_logs`
  MODIFY `log_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendar_import_series`
--
ALTER TABLE `calendar_import_series`
  MODIFY `series_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_editor_drafts`
--
ALTER TABLE `content_editor_drafts`
  MODIFY `draft_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  MODIFY `token_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `faq_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fb_api_call_log`
--
ALTER TABLE `fb_api_call_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `slide_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_library`
--
ALTER TABLE `media_library`
  MODIFY `media_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `news_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news_images`
--
ALTER TABLE `news_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `offices`
--
ALTER TABLE `offices`
  MODIFY `office_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `office_services`
--
ALTER TABLE `office_services`
  MODIFY `service_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `page_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `page_visits`
--
ALTER TABLE `page_visits`
  MODIFY `page_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_items`
--
ALTER TABLE `program_items`
  MODIFY `item_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_sections`
--
ALTER TABLE `program_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rbac_submissions`
--
ALTER TABLE `rbac_submissions`
  MODIFY `submission_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_visitors`
--
ALTER TABLE `site_visitors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `slug_history`
--
ALTER TABLE `slug_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_achievements`
--
ALTER TABLE `student_achievements`
  MODIFY `achievement_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_achievement_photos`
--
ALTER TABLE `student_achievement_photos`
  MODIFY `photo_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_organizations`
--
ALTER TABLE `student_organizations`
  MODIFY `org_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_org_categories`
--
ALTER TABLE `student_org_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_org_gallery`
--
ALTER TABLE `student_org_gallery`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tuition_fee_documents`
--
ALTER TABLE `tuition_fee_documents`
  MODIFY `document_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_office_services`
--
ALTER TABLE `admin_office_services`
  ADD CONSTRAINT `normalized_office_services_parent` FOREIGN KEY (`office_id`) REFERENCES `admin_offices` (`office_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_categories`
--
ALTER TABLE `announcement_categories`
  ADD CONSTRAINT `fk_announcement_category_post` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_announcement_category_tag` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD CONSTRAINT `fk_annimg_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_annimg_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE;

--
-- Constraints for table `content_editor_draft_chunks`
--
ALTER TABLE `content_editor_draft_chunks`
  ADD CONSTRAINT `editor_draft_chunks_parent` FOREIGN KEY (`draft_id`) REFERENCES `content_editor_drafts` (`draft_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_dean_media` FOREIGN KEY (`dean_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dept_logo` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;

--
-- Constraints for table `department_page_content`
--
ALTER TABLE `department_page_content`
  ADD CONSTRAINT `department_page_content_department` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_evt_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evt_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `faqs`
--
ALTER TABLE `faqs`
  ADD CONSTRAINT `fk_faq_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `fb_posts_cache`
--
ALTER TABLE `fb_posts_cache`
  ADD CONSTRAINT `fk_fbcache_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fbcache_media` FOREIGN KEY (`image_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;

--
-- Constraints for table `hero_slides`
--
ALTER TABLE `hero_slides`
  ADD CONSTRAINT `fk_hero_slides_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `homepage_president`
--
ALTER TABLE `homepage_president`
  ADD CONSTRAINT `fk_president_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_president_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `media_library`
--
ALTER TABLE `media_library`
  ADD CONSTRAINT `fk_media_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `fk_news_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_news_media` FOREIGN KEY (`featured_image`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_news_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `news_images`
--
ALTER TABLE `news_images`
  ADD CONSTRAINT `fk_newsimg_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_newsimg_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`news_id`) ON DELETE CASCADE;

--
-- Constraints for table `office_services`
--
ALTER TABLE `office_services`
  ADD CONSTRAINT `fk_svc_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`office_id`) ON DELETE CASCADE;

--
-- Constraints for table `page_sections`
--
ALTER TABLE `page_sections`
  ADD CONSTRAINT `fk_page_sections_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ps_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`page_id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `fk_programs_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_programs_image` FOREIGN KEY (`image_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_programs_pdf` FOREIGN KEY (`pdf_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;

--
-- Constraints for table `program_items`
--
ALTER TABLE `program_items`
  ADD CONSTRAINT `fk_program_items_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_sections`
--
ALTER TABLE `program_sections`
  ADD CONSTRAINT `fk_program_sections_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_achievement_achievers`
--
ALTER TABLE `student_achievement_achievers`
  ADD CONSTRAINT `normalized_achievement_achievers_parent` FOREIGN KEY (`achievement_id`) REFERENCES `student_achievements` (`achievement_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_achievement_photos`
--
ALTER TABLE `student_achievement_photos`
  ADD CONSTRAINT `student_achievement_photos_ibfk_1` FOREIGN KEY (`achievement_id`) REFERENCES `student_achievements` (`achievement_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_organizations`
--
ALTER TABLE `student_organizations`
  ADD CONSTRAINT `student_organizations_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `student_org_categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_organizations_ibfk_2` FOREIGN KEY (`logo_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_organizations_ibfk_3` FOREIGN KEY (`cover_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;

--
-- Constraints for table `student_org_gallery`
--
ALTER TABLE `student_org_gallery`
  ADD CONSTRAINT `student_org_gallery_ibfk_1` FOREIGN KEY (`org_id`) REFERENCES `student_organizations` (`org_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_org_gallery_ibfk_2` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
