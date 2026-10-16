-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Oct 15, 2026 at 11:53 AM
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

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `created_at`) VALUES
(1, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 12:59:03'),
(2, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 12:59:17'),
(3, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 13:02:27'),
(4, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 13:28:20'),
(5, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-28 13:28:49'),
(6, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-28 13:49:38'),
(7, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 13:50:04'),
(8, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-28 13:50:20'),
(9, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-28 14:05:19'),
(10, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-28 14:05:32'),
(11, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-28 14:06:05'),
(12, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-29 00:34:56'),
(13, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-29 00:38:06'),
(14, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-30 08:23:04'),
(15, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-30 08:23:18'),
(16, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-30 09:19:38'),
(17, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-06-30 09:20:25'),
(18, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-06-30 09:21:09'),
(19, 1, 'CREATE', 'announcements', 1, 'Created announcement: GNC', '2026-06-30 09:47:40'),
(20, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-30 12:39:45'),
(21, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-30 13:22:42'),
(22, 1, 'CREATE', 'Created event: Bday', NULL, NULL, '2026-06-30 13:25:51'),
(23, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-06-30 13:31:41'),
(24, 1, 'DELETE', 'Deleted event: test', NULL, NULL, '2026-06-30 13:31:46'),
(25, 1, 'UPDATE', 'Updated event: Bday', NULL, NULL, '2026-06-30 13:45:41'),
(26, 1, 'DELETE', 'Deleted event: Bday', NULL, NULL, '2026-06-30 13:45:55'),
(27, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-06-30 13:57:25'),
(28, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-06-30 15:21:51'),
(29, 1, 'CREATE', 'announcements', 2, 'Created announcement: Kimberluy', '2026-07-01 00:24:12'),
(30, 1, 'UPDATE', 'announcements', 2, 'Updated announcement: Kimberluy', '2026-07-01 00:27:21'),
(31, 1, 'UPDATE', 'announcements', 2, 'Updated announcement: Kimberluy', '2026-07-01 00:27:32'),
(32, 1, 'CREATE', 'Created event: barilan', NULL, NULL, '2026-07-01 00:29:37'),
(33, 1, 'DELETE', 'Deleted event: barilan', NULL, NULL, '2026-07-01 00:36:28'),
(34, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-01 02:15:00'),
(35, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-01 02:15:34'),
(36, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-01 02:17:26'),
(37, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-01 02:18:11'),
(38, 1, 'CREATE', 'announcements', 3, 'Created announcement: test', '2026-07-01 02:19:16'),
(39, 1, 'DELETE', 'announcements', 2, 'Deleted announcement: Kimberluy', '2026-07-01 02:19:35'),
(40, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-07-01 02:20:37'),
(41, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-01 02:57:08'),
(42, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-01 02:57:55'),
(43, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-07-01 03:30:36'),
(44, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-07-01 03:31:13'),
(45, 1, 'CREATE', 'announcements', 4, 'Created announcement: Test', '2026-07-01 03:34:22'),
(46, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-02 14:08:58'),
(47, 1, 'DELETE', 'Deleted event: test', NULL, NULL, '2026-07-02 14:20:59'),
(48, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-04 12:33:04'),
(49, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-04 13:45:06'),
(50, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 13:48:15'),
(51, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 13:48:22'),
(52, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 14:00:34'),
(53, 1, 'CREATE', 'hero_slides', 4, 'Added a new slideshow slide', '2026-07-04 14:55:28'),
(54, 1, 'DELETE', 'hero_slides', 4, 'Deleted slideshow slide', '2026-07-04 14:58:53'),
(55, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:08:46'),
(56, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:08:57'),
(57, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:17:38'),
(58, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:17:45'),
(59, 1, 'CREATE', 'hero_slides', 5, 'Added a new slideshow slide', '2026-07-04 15:17:56'),
(60, 1, 'DELETE', 'hero_slides', 5, 'Deleted slideshow slide', '2026-07-04 15:18:07'),
(61, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:18:39'),
(62, 1, 'CREATE', 'hero_slides', 6, 'Added a new slideshow slide', '2026-07-04 15:18:52'),
(63, 1, 'DELETE', 'hero_slides', 6, 'Deleted slideshow slide', '2026-07-04 15:18:57'),
(64, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:33:07'),
(65, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-04 15:33:34'),
(66, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-07-04 15:41:40'),
(67, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-04 16:04:47'),
(68, 1, 'CREATE', 'hero_slides', 7, 'Added a new slideshow slide', '2026-07-06 02:53:37'),
(69, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-06 02:54:13'),
(70, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-07-06 02:54:23'),
(71, 1, 'DELETE', 'hero_slides', 7, 'Deleted slideshow slide', '2026-07-06 02:54:34'),
(72, 1, 'CREATE', 'hero_slides', 8, 'Added a new slideshow slide', '2026-07-06 02:56:23'),
(73, 1, 'DELETE', 'hero_slides', 8, 'Deleted slideshow slide', '2026-07-06 02:57:04'),
(74, 1, 'UPDATE', 'Updated event: wag na kayo mag enroll please', NULL, NULL, '2026-07-06 03:01:07'),
(75, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-06 05:23:41'),
(76, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-06 05:24:26'),
(77, 1, 'DELETE', 'Deleted event: wag na kayo mag enroll please', NULL, NULL, '2026-07-06 05:24:47'),
(78, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-06 06:45:09'),
(79, 1, 'DELETE', 'announcements', 3, 'Deleted announcement: test', '2026-07-11 14:09:35'),
(80, 1, 'DELETE', 'announcements', 4, 'Deleted announcement: Test', '2026-07-11 14:09:37'),
(81, 1, 'DELETE', 'announcements', 1, 'Deleted announcement: GNC', '2026-07-11 14:09:39'),
(82, 1, 'CREATE', 'announcements', 5, 'Created announcement: TEST', '2026-07-11 14:10:15'),
(83, 1, 'CREATE', 'announcements', 6, 'Created announcement: GNC', '2026-07-11 14:10:57'),
(84, 1, 'DELETE', 'announcements', 5, 'Deleted announcement: TEST', '2026-07-11 14:11:16'),
(85, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-13 10:10:57'),
(86, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-13 10:46:22'),
(87, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-07-13 10:47:15'),
(88, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-07-13 11:00:31'),
(89, 1, 'UPDATE', 'hero_slides', 2, 'Updated slideshow slide', '2026-07-13 11:23:34'),
(90, 1, 'UPDATE', 'hero_slides', 2, 'Updated slideshow slide', '2026-07-13 11:24:13'),
(91, 1, 'UPDATE', 'hero_slides', 2, 'Updated slideshow slide', '2026-07-13 11:24:15'),
(92, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-07-13 11:49:03'),
(93, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-13 12:22:15'),
(94, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-16 14:04:52'),
(95, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-16 14:05:04'),
(96, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 14:05:11'),
(97, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-16 14:05:23'),
(98, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-16 14:05:34'),
(99, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 14:05:38'),
(100, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-16 14:05:51'),
(101, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-16 14:10:40'),
(102, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-16 14:10:55'),
(103, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 14:11:00'),
(104, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-07-16 14:11:11'),
(105, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-07-16 14:11:25'),
(106, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 14:11:30'),
(107, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 14:11:37'),
(108, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 14:11:42'),
(109, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 14:23:50'),
(110, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-07-16 14:28:32'),
(111, 1, 'CREATE', 'announcements', 7, 'Created announcement: test', '2026-07-16 14:46:53'),
(112, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 15:30:30'),
(113, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 15:34:39'),
(114, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 15:55:26'),
(115, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 15:55:32'),
(116, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 16:07:48'),
(117, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 16:10:29'),
(118, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-07-16 16:17:35'),
(119, 1, 'UPDATE', 'Updated event: test 2', NULL, NULL, '2026-07-16 16:39:12'),
(120, 1, 'UPDATE', 'Updated event: test 2', NULL, NULL, '2026-07-16 16:39:37'),
(121, 1, 'DELETE', 'announcements', 6, 'Deleted announcement: GNC', '2026-07-16 16:44:00'),
(122, 1, 'CREATE', 'announcements', 8, 'Created announcement: test', '2026-07-16 16:45:11'),
(123, 1, 'DELETE', 'announcements', 8, 'Deleted announcement: test', '2026-07-16 16:45:21'),
(124, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 16:48:38'),
(125, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 16:48:43'),
(126, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 16:49:07'),
(127, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-16 23:39:35'),
(128, 1, 'CREATE', 'announcements', 9, 'Created announcement: test', '2026-07-16 23:40:19'),
(129, 1, 'DELETE', 'announcements', 9, 'Deleted announcement: test', '2026-07-16 23:40:26'),
(130, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-16 23:43:53'),
(131, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-17 04:16:07'),
(132, 1, 'CREATE', 'announcements', 10, 'Created announcement: test', '2026-07-17 04:20:12'),
(133, 1, 'DELETE', 'announcements', 10, 'Deleted announcement: test', '2026-07-17 04:20:16'),
(134, 1, 'CREATE', 'announcements', 11, 'Created announcement: test', '2026-07-17 04:21:04'),
(135, 1, 'UPDATE', 'announcements', 11, 'Updated announcement: test', '2026-07-17 04:21:43'),
(136, 1, 'DELETE', 'announcements', 11, 'Deleted announcement: test', '2026-07-17 04:21:48'),
(137, 1, 'CREATE', 'announcements', 12, 'Created announcement: test', '2026-07-17 04:23:46'),
(138, 1, 'UPDATE', 'announcements', 12, 'Updated announcement: test', '2026-07-17 04:23:54'),
(139, 1, 'DELETE', 'announcements', 12, 'Deleted announcement: test', '2026-07-17 04:25:06'),
(140, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-17 04:26:35'),
(141, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-17 04:26:43'),
(142, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-17 05:27:11'),
(143, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-17 05:27:22'),
(144, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-07-17 06:28:51'),
(145, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-07-17 06:29:25'),
(146, 1, 'CREATE', 'announcements', 13, 'Created announcement: july 17 testing', '2026-07-17 06:34:51'),
(147, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-14 13:10:53'),
(148, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-08-16 05:10:03'),
(149, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-08-16 05:10:39'),
(150, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-08-16 05:23:56'),
(151, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-16 06:11:53'),
(152, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-08-16 06:12:09'),
(153, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-08-16 06:12:24'),
(154, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-08-16 06:25:42'),
(155, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-08-16 06:25:53'),
(156, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-08-16 06:26:03'),
(157, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-08-16 06:43:46'),
(158, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-08-16 06:55:08'),
(159, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-08-16 07:06:20'),
(160, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-08-16 07:07:25'),
(161, 1, 'UPDATE', 'hero_slides', 3, 'Updated slideshow slide', '2026-08-16 07:08:53'),
(162, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-08-16 07:43:10'),
(163, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-18 10:55:21'),
(164, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-18 12:45:58'),
(165, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-18 12:46:06'),
(166, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-19 09:51:54'),
(167, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-19 11:44:18'),
(168, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-19 11:44:31'),
(169, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-25 07:37:02'),
(170, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-25 08:15:58'),
(171, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-25 08:16:06'),
(172, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-25 08:54:02'),
(173, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-25 08:54:10'),
(174, 1, 'CREATE', 'news', 1, 'Created news article: test', '2026-08-25 08:57:17'),
(175, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-08-25 09:11:09'),
(176, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-27 09:50:24'),
(177, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-08-27 09:50:32'),
(178, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-27 09:55:16'),
(179, 1, 'UPDATE', 'hero_slides', 2, 'Updated slideshow slide', '2026-08-27 09:55:29'),
(180, 1, 'DELETE', 'announcements', 13, 'Deleted announcement: july 17 testing', '2026-08-27 12:33:52'),
(181, 1, 'DELETE', 'announcements', 7, 'Deleted announcement: test', '2026-08-27 12:33:54'),
(182, 1, 'DELETE', 'news', 1, 'Deleted news article: test', '2026-08-27 12:33:58'),
(183, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-08-30 06:30:27'),
(184, 1, 'CREATE', 'announcements', 14, 'Created announcement: Test', '2026-08-30 06:31:01'),
(185, 1, 'UPDATE', 'announcements', 14, 'Updated announcement: Test', '2026-08-30 06:31:05'),
(186, 1, 'CREATE', 'Created event: test', NULL, NULL, '2026-08-30 07:20:10'),
(187, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:22:57'),
(188, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:26:47'),
(189, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:26:53'),
(190, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:26:57'),
(191, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:27:31'),
(192, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:45:15'),
(193, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:54:35'),
(194, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 07:54:47'),
(195, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-08-30 08:02:04'),
(196, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-01 14:37:06'),
(197, 1, 'DELETE', 'announcements', 14, 'Deleted announcement: Test', '2026-09-01 14:37:11'),
(198, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-09-20 05:12:44'),
(199, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-09-20 05:13:54'),
(200, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-09-21 12:15:02'),
(201, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-09-21 12:15:39'),
(202, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-22 07:43:06'),
(203, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-09-22 07:49:10'),
(204, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/vision', '2026-09-22 07:49:10'),
(205, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-09-22 07:50:05'),
(206, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-09-22 07:50:14'),
(207, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/vision', '2026-09-22 07:50:14'),
(208, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-23 14:14:21'),
(209, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-09-23 14:20:01'),
(210, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/vision', '2026-09-23 14:20:01'),
(211, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-09-23 14:20:25'),
(212, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/vision', '2026-09-23 14:20:25'),
(213, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/mission', '2026-09-23 14:21:02'),
(214, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/mission', '2026-09-23 14:21:02'),
(215, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-24 02:54:25'),
(216, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: added logo', '2026-09-24 02:55:30'),
(217, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-24 03:28:52'),
(218, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: replaced logo', '2026-09-24 03:42:22'),
(219, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: updated Head of Office details, added dean photo', '2026-09-24 04:10:40'),
(220, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: updated Head of Office details, replaced dean photo', '2026-09-24 04:11:06'),
(221, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: added curriculum PDF', '2026-09-24 11:47:44'),
(222, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: removed curriculum PDF', '2026-09-24 12:15:15'),
(223, 1, 'UPDATE', 'programs', 12, 'Bachelor of Science in Accounting Information Systems: added curriculum PDF', '2026-09-24 12:25:27'),
(224, 1, 'UPDATE', 'programs', 12, 'Bachelor of Science in Accounting Information Systems: removed curriculum PDF', '2026-09-24 12:25:57'),
(225, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: added curriculum PDF', '2026-09-24 12:47:23'),
(226, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: removed curriculum PDF', '2026-09-24 12:47:41'),
(227, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: replaced logo', '2026-09-24 12:48:29'),
(228, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: replaced logo', '2026-09-24 12:48:32'),
(229, 1, 'UPDATE', 'departments', 1, 'College of Arts, Sciences, and Education: replaced logo', '2026-09-24 12:49:20'),
(230, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-25 07:48:10'),
(231, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-25 07:51:29'),
(232, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-27 05:05:09'),
(233, 1, 'CREATE', 'announcements', 15, 'Created announcement: No classes', '2026-09-27 05:05:33'),
(234, 1, 'DELETE', 'announcements', 15, 'Deleted announcement: No classes', '2026-09-27 05:05:49'),
(235, 1, 'CREATE', 'announcements', 16, 'Created announcement: No classes', '2026-09-27 05:05:52'),
(236, 1, 'DELETE', 'announcements', 16, 'Deleted announcement: No classes', '2026-09-27 05:06:08'),
(237, 1, 'DELETE', 'hero_slides', 3, 'Deleted slideshow slide', '2026-09-27 05:10:19'),
(238, 1, 'UPDATE', 'departments', 2, 'College of Business Administration: updated Head of Office details, added dean photo', '2026-09-27 05:12:16'),
(239, 1, 'UPDATE', 'departments', 3, 'College of Engineering: updated Head of Office details, added dean photo', '2026-09-27 05:12:41'),
(240, 1, 'UPDATE', 'departments', 4, 'College of Medical Technology and Pharmacy: updated Head of Office details, added dean photo', '2026-09-27 05:12:51'),
(241, 1, 'UPDATE', 'departments', 5, 'College of Accountancy: updated Head of Office details, added dean photo', '2026-09-27 05:13:00'),
(242, 1, 'UPDATE', 'departments', 6, 'College of Nursing: updated Head of Office details, added dean photo', '2026-09-27 05:13:18'),
(243, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: added curriculum PDF', '2026-09-27 05:36:46'),
(244, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-09-27 05:38:15'),
(245, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-27 05:46:16'),
(246, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-09-27 06:20:51'),
(247, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-09-27 06:21:31'),
(248, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-27 10:53:27'),
(249, 1, 'CREATE', 'announcements', 17, 'Created announcement: Test', '2026-09-27 12:41:40'),
(250, 1, 'PIN', 'announcements', 17, 'Pinned announcement: Test', '2026-09-27 12:41:48'),
(251, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-28 12:56:33'),
(252, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1084330121015007', '2026-09-28 13:17:19'),
(253, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:11'),
(254, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:14'),
(255, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:53'),
(256, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:55'),
(257, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:56'),
(258, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1083383604442992', '2026-09-28 14:16:57'),
(259, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-29 11:53:32'),
(260, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1084330121015007', '2026-09-29 11:58:07'),
(261, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1084330121015007', '2026-09-29 11:58:08'),
(262, 1, 'CREATE', 'Created event: Test', NULL, NULL, '2026-09-29 12:23:32'),
(263, 1, 'UPDATE', 'announcements', 17, 'Updated announcement: Test', '2026-09-29 12:33:53'),
(264, 1, 'UPDATE', 'announcements', 17, 'Updated announcement: Test', '2026-09-29 12:34:02'),
(265, 1, 'UPDATE', 'announcements', 17, 'Updated announcement: Test', '2026-09-29 12:34:45'),
(266, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-09-30 13:10:20'),
(267, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1086044574176895', '2026-09-30 13:13:39'),
(268, 1, 'CREATE', 'Created event: Tes', NULL, NULL, '2026-09-30 13:15:48'),
(269, 1, 'UPDATE', 'Updated event: Tes', NULL, NULL, '2026-09-30 13:15:54'),
(270, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (3): 403969556396264_1048796227901730', '2026-09-30 13:59:14'),
(271, 1, 'CREATE', 'announcements', 18, 'Created announcement: tests', '2026-09-30 14:02:00'),
(272, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-09-30 14:02:09'),
(273, 1, 'PIN', 'announcements', 18, 'Pinned announcement: tests', '2026-09-30 14:02:17'),
(274, 1, 'UNPIN', 'announcements', 18, 'Unpinned announcement: tests', '2026-09-30 14:02:18'),
(275, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (3): 403969556396264_1086044574176895', '2026-09-30 14:04:11'),
(276, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-09-30 14:06:37'),
(277, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (auto): 403969556396264_1086044574176895', '2026-09-30 14:19:24'),
(278, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (2): 403969556396264_1085054784275874', '2026-09-30 14:19:34'),
(279, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (2): 403969556396264_1085054784275874', '2026-09-30 14:19:34'),
(280, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (4): 403969556396264_1086044574176895', '2026-09-30 14:21:02'),
(281, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (auto): 403969556396264_1085054784275874', '2026-09-30 14:21:08'),
(282, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (2): 403969556396264_1084562407658445', '2026-09-30 14:21:28'),
(283, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (2): 403969556396264_1084562407658445', '2026-09-30 14:21:52'),
(284, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (3): 403969556396264_1049979204450099', '2026-09-30 15:01:55'),
(285, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (auto): 403969556396264_1049979204450099', '2026-09-30 15:02:04'),
(286, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-01 08:14:08'),
(287, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (1): 403969556396264_1051546377626715', '2026-10-01 09:02:49'),
(288, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (1): 403969556396264_1051546377626715', '2026-10-01 09:02:53'),
(289, 1, 'UPDATE', 'fb_posts_cache', NULL, 'Set Facebook post category (4): 403969556396264_1050841237697229', '2026-10-01 09:06:16'),
(290, 1, 'UPDATE', 'admin_people', 1, 'Administration: updated Ronald Luis S. Goseco, MBA', '2026-10-01 11:05:28'),
(291, 1, 'UPDATE', 'admin_people', 1, 'Administration: updated Ronald Luis S. Goseco, MBA', '2026-10-01 11:06:44'),
(292, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 11:26:16'),
(293, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 11:26:46'),
(294, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 11:29:50'),
(295, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 11:30:05'),
(296, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 11:30:10'),
(297, 1, 'UPDATE', 'hero_slides', NULL, 'Reordered slideshow slides', '2026-10-01 11:49:26'),
(298, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-01 14:21:40'),
(299, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-03 07:31:10'),
(300, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-04 10:50:21'),
(301, 1, 'UPDATE', 'admin_people', 3, 'Administration: archived Atty. Ricardo M. Sampang', '2026-10-04 13:48:33'),
(302, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-05 11:57:37'),
(303, 1, 'UPDATE', 'departments', 7, 'CASA and Grade School Montessori: added logo', '2026-10-05 12:05:37'),
(304, 1, 'UPDATE', 'departments', 7, 'CASA and Grade School Montessori: updated Head of Office details', '2026-10-05 12:05:37'),
(305, 1, 'UPDATE', 'departments', 7, 'CASA and Grade School Montessori: replaced logo', '2026-10-05 12:06:08'),
(306, 1, 'UPDATE', 'departments', 7, 'CASA and Grade School Montessori: replaced logo', '2026-10-05 12:08:48'),
(307, 1, 'UPDATE', 'departments', 7, 'CASA and Grade School Montessori: replaced logo', '2026-10-05 12:08:59'),
(308, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-05 13:12:32'),
(309, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-06 02:11:48'),
(310, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-06 03:40:08'),
(311, 1, 'DELETE', 'departments', 10, 'Deleted department: Senior High School Regular', '2026-10-06 04:09:06'),
(312, 1, 'DELETE', 'departments', 9, 'Deleted department: Junior High School Regular', '2026-10-06 04:09:08'),
(313, 1, 'DELETE', 'departments', 8, 'Deleted department: High School Montessori', '2026-10-06 04:09:10'),
(314, 1, 'DELETE', 'departments', 7, 'Deleted department: CASA and Grade School Montessori', '2026-10-06 04:09:13'),
(315, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts, Sciences, and Education', '2026-10-06 04:10:15'),
(316, 1, 'UPDATE', 'departments', 1, 'Updated details for Test', '2026-10-06 04:10:26'),
(317, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts, Sciences, and Education', '2026-10-06 04:10:57'),
(318, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-06 05:13:19'),
(319, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-06 05:13:36'),
(320, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-06 07:39:44'),
(321, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-10-06 07:57:45'),
(322, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-06 07:57:50'),
(323, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts, Sciences, and Education', '2026-10-06 08:00:55'),
(324, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:16:46'),
(325, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:19:02'),
(326, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:19:55'),
(327, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:24:24'),
(328, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:24:28'),
(329, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: updated Head of Office details', '2026-10-06 08:24:28'),
(330, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:24:36'),
(331, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:25:02'),
(332, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:25:03'),
(333, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-06 08:25:17'),
(334, 1, 'UPDATE', 'departments', 2, 'College of Business Administration: updated Head of Office details', '2026-10-06 08:40:51'),
(335, 1, 'UPDATE', 'departments', 4, 'College of Medical Technology and Pharmacy: updated Head of Office details', '2026-10-06 08:44:03'),
(336, 1, 'UPDATE', 'departments', 5, 'College of Accountancy: updated Head of Office details', '2026-10-06 08:44:06'),
(337, 1, 'UPDATE', 'departments', 6, 'College of Nursing: updated Head of Office details', '2026-10-06 08:44:09'),
(338, 1, 'UPDATE', 'departments', 3, 'College of Engineering: updated Head of Office details', '2026-10-06 08:44:11'),
(339, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-06 13:26:18'),
(340, 1, 'CREATE', 'departments', 11, 'Created department: Test (slug: test)', '2026-10-06 15:41:20'),
(341, 1, 'DELETE', 'departments', 11, 'Deleted department: Test', '2026-10-06 15:41:53'),
(342, 1, 'CREATE', 'departments', 12, 'Created department: Test (slug: test)', '2026-10-06 15:42:08'),
(343, 1, 'DELETE', 'departments', 12, 'Deleted department: Test', '2026-10-06 15:44:08'),
(344, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-07 04:54:32'),
(345, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-07 05:10:21'),
(346, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-07 05:18:20'),
(347, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-07 05:25:28'),
(348, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-07 05:28:32'),
(349, 1, 'UPDATE', 'departments', 6, 'Updated details for College of Nursing', '2026-10-07 05:33:37'),
(350, 1, 'UPDATE', 'departments', 2, 'Updated details for College of Business Administration', '2026-10-07 06:03:42'),
(351, 1, 'UPDATE', 'departments', 3, 'Updated details for College of Engineering', '2026-10-07 06:03:46'),
(352, 1, 'UPDATE', 'departments', 4, 'Updated details for College of Medical Technology and Pharmacy', '2026-10-07 06:03:52'),
(353, 1, 'UPDATE', 'departments', 5, 'Updated details for College of Accountancy', '2026-10-07 06:03:56'),
(354, 1, 'UPDATE', 'departments', 5, 'Updated details for College of Accountancy', '2026-10-07 06:04:51'),
(355, 1, 'UPDATE', 'departments', 5, 'Updated details for College of Accountancy', '2026-10-07 06:04:57'),
(356, 1, 'UPDATE', 'departments', 4, 'Updated details for College of Medical Laboratory Science and Pharmacy', '2026-10-07 06:11:41'),
(357, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-10-07 06:19:54'),
(358, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-07 06:23:33'),
(359, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-10-07 06:28:17'),
(360, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-10-07 06:28:31'),
(361, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-10-07 06:28:35'),
(362, 1, 'UPDATE', 'Updated event: test', NULL, NULL, '2026-10-07 06:28:44'),
(363, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-07 06:35:48'),
(364, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-10-07 06:58:32'),
(365, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-10-07 06:58:36'),
(366, 1, 'UPDATE', 'announcements', 18, 'Updated announcement: tests', '2026-10-07 06:58:45'),
(367, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-07 12:23:40'),
(368, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-08 02:51:50'),
(369, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-08 02:52:35'),
(370, 1, 'CREATE', 'news', 2, 'Created news article: Test', '2026-10-08 03:00:00'),
(371, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 03:00:06'),
(372, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 03:00:17'),
(373, 1, 'PIN', 'news', 2, 'Pinned news article: Test', '2026-10-08 03:00:26'),
(374, 1, 'CREATE', 'Created event: Testing', NULL, NULL, '2026-10-08 03:06:24'),
(375, 1, 'UPDATE', 'Updated event: Testing', NULL, NULL, '2026-10-08 03:06:33'),
(376, 1, 'UPDATE', 'Updated event: Testing', NULL, NULL, '2026-10-08 03:08:30'),
(377, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 03:44:02'),
(378, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 04:13:29'),
(379, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 04:19:52'),
(380, 1, 'CREATE', 'announcements', 19, 'Created announcement: Test', '2026-10-08 04:23:42'),
(381, 1, 'UPDATE', 'announcements', 19, 'Updated announcement: Test', '2026-10-08 04:24:00'),
(382, 1, 'UPDATE', 'announcements', 19, 'Updated announcement: Test', '2026-10-08 04:38:21'),
(383, 1, 'CREATE', 'news', 3, 'Created news article: Test', '2026-10-08 04:41:51'),
(384, 1, 'CREATE', 'news', 4, 'Created news article: Test', '2026-10-08 04:42:38'),
(385, 1, 'UPDATE', 'news', 4, 'Updated news article: Test', '2026-10-08 04:42:44'),
(386, 1, 'CREATE', 'news', 5, 'Created news article: Test', '2026-10-08 04:43:08'),
(387, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-08 13:45:05'),
(388, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-08 13:45:21'),
(389, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 13:45:36'),
(390, 1, 'UPDATE', 'news', 2, 'Updated news article: Test', '2026-10-08 13:47:14'),
(391, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-08 13:59:05'),
(392, 1, 'CREATE', 'Created event: testing', NULL, NULL, '2026-10-08 14:04:38'),
(393, 1, 'CREATE', 'Created event: Halloween', NULL, NULL, '2026-10-08 14:05:15'),
(394, 1, 'CREATE', 'Created event: TEst ulit', NULL, NULL, '2026-10-08 14:05:52'),
(395, 1, 'CREATE', 'Created event: HELLO', NULL, NULL, '2026-10-08 14:08:32'),
(396, 1, 'UPDATE', 'Updated event: TEst ulit', NULL, NULL, '2026-10-08 14:08:39'),
(397, 1, 'UPDATE', 'Updated event: HELLO', NULL, NULL, '2026-10-08 14:18:10'),
(398, 1, 'CREATE', 'Created event: TEST', NULL, NULL, '2026-10-08 14:29:12'),
(399, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-09 10:26:08'),
(400, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-09 10:26:21'),
(401, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: removed curriculum PDF', '2026-10-09 10:30:38'),
(402, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-09 10:44:29'),
(403, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated why list', '2026-10-09 10:47:45'),
(404, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated why list', '2026-10-09 11:18:22'),
(405, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated career list', '2026-10-09 11:19:54'),
(406, 1, 'UPDATE', 'programs', 2, 'Updated program page: Bachelor of Secondary Education', '2026-10-09 11:22:26'),
(407, 1, 'UPDATE', 'programs', 2, 'Updated program page: Bachelor of Secondary Education', '2026-10-09 11:23:48'),
(408, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated badge list', '2026-10-09 11:25:39'),
(409, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated badge list', '2026-10-09 11:26:30'),
(410, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-10-09 12:16:57'),
(411, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-09 12:23:31'),
(412, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-09 15:39:49'),
(413, 1, 'UNPIN', 'news', 2, 'Unpinned news article: Test', '2026-10-09 15:40:07'),
(414, 1, 'DELETE', 'news', 2, 'Deleted news article: Test', '2026-10-09 15:40:10'),
(415, 1, 'DELETE', 'news', 3, 'Deleted news article: Test', '2026-10-09 15:40:11'),
(416, 1, 'DELETE', 'news', 4, 'Deleted news article: Test', '2026-10-09 15:40:13'),
(417, 1, 'DELETE', 'news', 5, 'Deleted news article: Test', '2026-10-09 15:40:15'),
(418, 1, 'CREATE', 'news', 6, 'Created news article: Testing News', '2026-10-09 15:42:01'),
(419, 1, 'UPDATE', 'news', 6, 'Updated news article: Testing News', '2026-10-09 15:42:07'),
(420, 1, 'PIN', 'news', 6, 'Pinned news article: Testing News', '2026-10-09 15:42:20'),
(421, 1, 'DELETE', 'announcements', 19, 'Deleted announcement: Test', '2026-10-09 15:43:10'),
(422, 1, 'UNPIN', 'news', 6, 'Unpinned news article: Testing News', '2026-10-09 15:43:14'),
(423, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1086044574176895', '2026-10-09 15:43:18'),
(424, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1084330121015007', '2026-10-09 15:43:21'),
(425, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1090971760350843', '2026-10-09 15:43:42'),
(426, 1, 'PIN', 'news', 6, 'Pinned news article: Testing News', '2026-10-09 15:43:53'),
(427, 1, 'CREATE', 'news', 7, 'Created news article: News 123', '2026-10-09 15:46:12'),
(428, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_975893022239205', '2026-10-09 15:46:55'),
(429, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-09 16:17:55'),
(430, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-09 16:18:27'),
(431, 1, 'UPDATE', 'hero_slides', 2, 'Updated slideshow slide', '2026-10-09 16:19:46'),
(432, 1, 'LOGOUT', NULL, NULL, 'User logged out', '2026-10-09 16:35:59'),
(433, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-10 03:53:56'),
(434, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-10 05:06:14'),
(435, 1, 'UPDATE', 'departments', 2, 'Updated details for College of Business Administration', '2026-10-10 05:16:28'),
(436, 1, 'UPDATE', 'departments', 2, 'Updated details for College of Business Administration', '2026-10-10 05:18:30'),
(437, 1, 'UPDATE', 'departments', 2, 'Updated details for College of Business Administration', '2026-10-10 05:18:44'),
(438, 1, 'UPDATE', 'departments', 2, 'Updated details for College of Business Administration', '2026-10-10 05:19:29'),
(439, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-10 07:37:46'),
(440, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/vision', '2026-10-11 12:09:11'),
(441, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/vision', '2026-10-11 12:09:11'),
(442, 1, 'UPDATE', 'page_sections', NULL, 'Saved draft for vision-mission/mission', '2026-10-11 12:09:17'),
(443, 1, 'PUBLISH', 'page_sections', NULL, 'Published vision-mission/mission', '2026-10-11 12:09:17'),
(444, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-12 03:53:04'),
(445, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 04:07:54'),
(446, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 04:18:47'),
(447, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced dean photo', '2026-10-12 08:39:06'),
(448, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced dean photo', '2026-10-12 08:39:18'),
(449, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced dean photo', '2026-10-12 08:42:29'),
(450, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced dean photo', '2026-10-12 08:42:55'),
(451, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-12 08:43:12'),
(452, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-12 08:43:42'),
(453, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-12 08:46:11'),
(454, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-12 08:46:17'),
(455, 1, 'UPDATE', 'admin_people', 1, 'Administration: updated Ronald Luis S. Goseco, MBA', '2026-10-12 08:48:09'),
(456, 1, 'CREATE', 'news', 8, 'Created news article: TESTING', '2026-10-12 10:25:10'),
(457, 1, 'PIN', 'news', 8, 'Pinned news article: TESTING', '2026-10-12 10:25:12'),
(458, 1, 'PIN', 'announcements', 18, 'Pinned announcement: tests', '2026-10-12 10:35:00'),
(459, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-12 10:44:58'),
(460, 1, 'UPDATE', 'programs', 1, 'Updated program: Bachelor of Elementary Education', '2026-10-12 10:58:54'),
(461, 1, 'UPDATE', 'programs', 2, 'Updated program: Bachelor of Secondary Education', '2026-10-12 10:58:55'),
(462, 1, 'UPDATE', 'programs', 7, 'Updated program: Bachelor of Science in Hospitality Management', '2026-10-12 11:00:36'),
(463, 1, 'UPDATE', 'programs', 4, 'Updated program: Bachelor of Science in Business Administration major in Financial Management', '2026-10-12 11:00:36'),
(464, 1, 'UPDATE', 'programs', 8, 'Updated program: Bachelor of Science in Tourism Management', '2026-10-12 11:00:36'),
(465, 1, 'UPDATE', 'programs', 6, 'Updated program: Bachelor of Science in Information Technology', '2026-10-12 11:00:36'),
(466, 1, 'UPDATE', 'programs', 9, 'Updated program: Bachelor of Science in Civil Engineering', '2026-10-12 11:00:59'),
(467, 1, 'UPDATE', 'programs', 10, 'Updated program: Bachelor of Science in Medical Laboratory Science', '2026-10-12 11:01:14'),
(468, 1, 'UPDATE', 'programs', 13, 'Updated program: Bachelor of Science in Accountancy', '2026-10-12 11:01:27'),
(469, 1, 'UPDATE', 'programs', 14, 'Updated program: Bachelor of Science in Nursing', '2026-10-12 11:01:38'),
(470, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 11:59:21'),
(471, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:02:47'),
(472, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:03:11'),
(473, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:13:50'),
(474, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:19:56'),
(475, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:20:05'),
(476, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:20:22'),
(477, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 12:21:36'),
(478, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 13:15:34'),
(479, 1, 'UPDATE', 'programs', 1, 'Updated program page: Bachelor of Elementary Education', '2026-10-12 13:15:48'),
(480, 1, 'UPDATE', 'programs', 2, 'Updated program page: Bachelor of Secondary Education', '2026-10-12 13:18:44'),
(481, 1, 'UPDATE', 'programs', 2, 'Updated program page: Bachelor of Secondary Education', '2026-10-12 13:19:35'),
(482, 1, 'UPDATE', 'programs', 2, 'Updated program page: Bachelor of Secondary Education', '2026-10-12 13:20:27'),
(483, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated why list', '2026-10-12 13:21:37'),
(484, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated career list', '2026-10-12 13:24:20'),
(485, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated career list', '2026-10-12 13:24:53'),
(486, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-13 05:41:03'),
(487, 1, 'UNPIN', 'news', 8, 'Unpinned news article: TESTING', '2026-10-13 05:53:09'),
(488, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1097472023034150', '2026-10-13 05:53:13'),
(489, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-13 07:38:00'),
(490, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-13 12:19:59'),
(491, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-13 12:20:34'),
(492, 1, 'UPDATE', 'admin_people', 1, 'Administration: updated Ronald Luis S. Goseco, MBA', '2026-10-13 12:32:45'),
(493, 1, 'UPDATE', 'departments', 1, 'Updated details for Test', '2026-10-13 12:34:33'),
(494, 1, 'UPDATE', 'departments', 1, 'Updated details for Test', '2026-10-13 12:34:51'),
(495, 1, 'UPDATE', 'departments', 1, 'Updated details for College of Arts and Sciences, and Education', '2026-10-13 12:35:14'),
(496, 1, 'UPDATE', 'hero_slides', 1, 'Updated slideshow slide', '2026-10-13 12:36:36'),
(497, 1, 'UPDATE', 'departments', 6, 'Updated details for College of Nursing', '2026-10-13 16:20:50'),
(498, 1, 'UPDATE', 'programs', 10, 'Bachelor of Science in Medical Laboratory Science: updated core value list', '2026-10-13 16:23:52'),
(499, 1, 'UPDATE', 'programs', 10, 'Bachelor of Science in Medical Laboratory Science: updated core value list', '2026-10-13 16:25:06'),
(500, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-14 03:25:16'),
(501, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-14 03:25:31'),
(502, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-14 03:29:05'),
(503, 1, 'UPDATE', 'homepage_president', 1, 'Updated Message from the President', '2026-10-14 03:29:26'),
(504, 1, 'UNPIN', 'announcements', 18, 'Unpinned announcement: tests', '2026-10-14 03:30:55'),
(505, 1, 'PIN', 'announcements', 18, 'Pinned announcement: tests', '2026-10-14 03:30:56'),
(506, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: added curriculum PDF', '2026-10-14 04:37:28'),
(507, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: removed curriculum PDF', '2026-10-14 04:37:44');
INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `table_name`, `record_id`, `description`, `created_at`) VALUES
(508, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: added curriculum PDF', '2026-10-14 04:37:53'),
(509, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated facility list', '2026-10-14 04:52:51'),
(510, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated uniform list', '2026-10-14 05:18:15'),
(511, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-14 09:41:17'),
(512, 1, 'UPDATE', 'homepage_text', NULL, 'Edited homepage text: calendar_heading', '2026-10-14 09:41:57'),
(513, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated hero slide list', '2026-10-14 10:00:30'),
(514, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated hero slide list', '2026-10-14 10:00:37'),
(515, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated hero slide list', '2026-10-14 10:02:39'),
(516, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated hero slide list', '2026-10-14 10:03:38'),
(517, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated hero slide list', '2026-10-14 10:04:06'),
(518, 1, 'CREATE', 'programs', 15, 'Added program: Testing', '2026-10-14 14:09:03'),
(519, 1, 'DELETE', 'programs', 15, 'Deleted program: Testing', '2026-10-14 14:09:38'),
(520, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated hero slide list', '2026-10-14 14:38:28'),
(521, 1, 'UPDATE', 'programs', 2, 'Bachelor of Secondary Education: updated facility list', '2026-10-14 14:38:58'),
(522, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated facility list', '2026-10-14 14:41:41'),
(523, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated facility list', '2026-10-14 14:41:50'),
(524, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated hero slide list', '2026-10-14 14:42:01'),
(525, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated facility list', '2026-10-14 14:42:25'),
(526, 1, 'UPDATE', 'programs', 3, 'Updated program page: Bachelor of Arts in English Language', '2026-10-14 14:45:31'),
(527, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated badge list', '2026-10-14 14:46:56'),
(528, 1, 'UPDATE', 'programs', 3, 'Bachelor of Arts in English Language: updated why list', '2026-10-14 14:48:54'),
(529, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-15 03:42:14'),
(530, 1, 'UPDATE', 'homepage_text', NULL, 'Edited homepage text: news_subheading', '2026-10-15 03:42:32'),
(531, 1, 'UPDATE', 'homepage_text', NULL, 'Edited homepage text: news_subheading', '2026-10-15 03:42:50'),
(532, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_1090971760350843', '2026-10-15 04:14:39'),
(533, 1, 'UNPIN', 'fb_posts_cache', NULL, 'Unpinned Facebook post: 403969556396264_975893022239205', '2026-10-15 04:14:53'),
(534, 1, 'UNPIN', 'news', 6, 'Unpinned news article: Testing News', '2026-10-15 04:15:13'),
(535, 1, 'DELETE', 'news', 6, 'Deleted news article: Testing News', '2026-10-15 04:15:15'),
(536, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1099113709536648', '2026-10-15 04:15:30'),
(537, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1096609066453779', '2026-10-15 04:15:33'),
(538, 1, 'PIN', 'fb_posts_cache', NULL, 'Pinned Facebook post: 403969556396264_1099110659536953', '2026-10-15 04:16:04'),
(539, 1, 'OTP_SENT', NULL, NULL, 'OTP sent to justinemaoel.gnc@gmail.com', '2026-10-15 09:00:38'),
(540, 1, 'LOGIN', NULL, NULL, 'Successful login via OTP', '2026-10-15 09:01:16'),
(541, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-15 09:02:47'),
(542, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-15 09:02:55'),
(543, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-15 10:01:17'),
(544, 1, 'UPDATE', 'programs', 1, 'Bachelor of Elementary Education: updated badge list', '2026-10-15 10:01:23'),
(545, 1, 'UPDATE', 'programs', 9, 'Bachelor of Science in Civil Engineering: updated why list', '2026-10-15 10:02:53'),
(546, 1, 'UPDATE', 'programs', 9, 'Bachelor of Science in Civil Engineering: updated why list', '2026-10-15 10:03:03'),
(547, 1, 'UPDATE', 'programs', 9, 'Updated program page: Bachelor of Science in Civil Engineering', '2026-10-15 10:03:40'),
(548, 1, 'UPDATE', 'programs', 4, 'Bachelor of Science in Business Administration major in Financial Management: updated badge list', '2026-10-15 10:11:59'),
(549, 1, 'UPDATE', 'programs', 4, 'Updated program page: Bachelor of Science in Business Administration major in Financial Management', '2026-10-15 10:12:30'),
(550, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced logo', '2026-10-15 10:13:20'),
(551, 1, 'UPDATE', 'departments', 1, 'College of Arts and Sciences, and Education: replaced logo', '2026-10-15 10:13:43'),
(552, 1, 'UPDATE', 'programs', 9, 'Bachelor of Science in Civil Engineering: updated why list', '2026-10-15 10:14:19'),
(553, 1, 'UPDATE', 'programs', 9, 'Bachelor of Science in Civil Engineering: updated why list', '2026-10-15 10:14:22'),
(554, 1, 'UPDATE', 'programs', 9, 'Bachelor of Science in Civil Engineering: updated why list', '2026-10-15 10:14:24'),
(555, 1, 'LOGIN', NULL, NULL, 'Successful login via trusted device (OTP skipped)', '2026-10-15 11:39:54'),
(556, 1, 'UPDATE', 'programs', 9, 'Updated program page: Bachelor of Science in Civil Engineering', '2026-10-15 11:44:27'),
(557, 1, 'UPDATE', 'programs', 9, 'Updated program page: Bachelor of Science in Civil Engineering', '2026-10-15 11:45:33'),
(558, 1, 'UPDATE', 'programs', 9, 'Updated program page: Bachelor of Science in Civil Engineering', '2026-10-15 11:45:43');

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

--
-- Dumping data for table `admin_offices`
--

INSERT INTO `admin_offices` (`office_id`, `section_key`, `office_name`, `head_name`, `head_role`, `media_id`, `image_path`, `phone`, `email`, `location`, `hours`, `services`, `display_order`, `archived_at`, `updated_at`) VALUES
(1, 'heads-of-student-services', 'Business Office', 'Getor N. Isip, MBA, CPA', 'School Accountant, Chief Financial Officer', NULL, '/assets/images/business-office-head.png', '(045) 900-4473 Loc. 107', 'info@gnc.edu.ph', 'BETA Hall | Floor 2 | Room 200', 'Monday - Friday | 8:00AM - 4:00PM\r\nSaturday | 8:00AM - 3:00PM', 'General Payment Transactions\r\nScholarship and Financial Assistance Processing\r\nStudent Loan Assistance\r\nOnline Payment Processing\r\nDisbursement and Refund Services\r\nPayroll Services\r\nFinancial and Administrative Liaison Services', 1, NULL, '2026-10-01 10:37:29'),
(2, 'heads-of-student-services', 'Registrar Office', 'Julie B. Domingo, MBA', 'Chief Registrar', NULL, '/assets/images/registrar-office-head.png', '(045) 900-4473 Loc. 106', 'registrar@gnc.edu.ph', 'BETA Hall | Floor 2 | Room 200', 'Monday - Friday | 8:00AM - 5:00PM\r\nSaturday | 8:00AM - 3:00PM', 'Enrollment Services\r\nStudent Records Services\r\nAcademic Records and Verification\r\nCertification and Document Services\r\nTransfer and Clearance Services\r\nRecords Request and Processing\r\nStudent Information Assistance', 2, NULL, '2026-10-01 10:37:29'),
(3, 'heads-of-student-services', 'Office of Student Affairs', 'Jewel Mae E. Samera, RPm', 'Directress', NULL, '/assets/images/osa-office-head.png', '(045) 900-4473 Loc. 109', 'osa@gnc.edu.ph', 'Limlingan Hall | Floor 2 | Room 100', 'Monday - Friday | 8:00AM - 4:00PM', '', 3, NULL, '2026-10-01 10:37:29'),
(4, 'heads-of-student-services', 'College Library', 'Edna C. Valencia, MLIS', 'Chief Librarian', NULL, '/assets/images/college-library-head.png', '(045) 900-4473 Loc. 113', 'info@gnc.edu.ph', 'Puno Hall | Floor 2 | Room 200', 'Monday - Saturday | 8:00AM - 5:00PM', 'Library Resource Access\r\nBook Borrowing and Return Services\r\nInternet and Research Assistance\r\nReader Assistance\r\nReferral Services\r\nLibrary Orientation\r\nExternal Researcher Assistance', 4, NULL, '2026-10-01 10:37:29'),
(5, 'heads-of-student-services', 'High School Library', 'Jhelou A. Supan, MLIS, ITS, ICCC, MOS', 'OIC, High School Library', NULL, '/assets/images/high-school-library-head.png', '(045) 900-4473 Loc. 116', 'info@gnc.edu.ph', 'Goseco Hall | Floor 2 | Room 200', 'Monday - Friday | 7:00AM - 5:30PM', 'Library Access and Circulation\r\nResearch and Reference Assistance\r\nComputer and Internet Services\r\nLibrary Orientation and User Registration\r\nReferral and Information Services\r\nLibrary Collection Management\r\nLibrary Resources and Preservation\r\nLibrarian Assistance', 5, NULL, '2026-10-01 10:37:29'),
(6, 'heads-of-student-services', 'Elementary Library', 'Marivic Lansang, MLIS', 'OIC, Elementary Library', NULL, '/assets/images/elementary-library-head.png', '(045) 900-4473', 'info@gnc.edu.ph', 'Banzali Hall | Floor 1 | Room 100', 'Monday - Friday | 7:00AM - 4:00PM', 'Book and Learning Resource Access\r\nBook Borrowing Services\r\nLibrary Assistance\r\nLearner Reading Support', 6, NULL, '2026-10-01 10:37:29'),
(7, 'heads-of-student-services', 'Guidance Office', 'Patria Pilipina D. Capa, MA, RGC, RPm', 'Chief Guidance Counselor', NULL, '/assets/images/guidance-office-head.png', '(045) 900-4473 Loc. 108', 'info@gnc.edu.ph', 'Goseco Hall | Floor 2 | Room 200', 'Monday - Friday | 8:00AM - 5:00PM', 'Admission and Orientation Services\r\nStudent Assessment Services\r\nCounseling and Personal Support\r\nInformation and Referral Services\r\nCareer Guidance Services\r\nSeminars and Development Programs\r\nPeer Support and Leadership Programs\r\nGuidance Program Evaluation', 7, NULL, '2026-10-01 10:37:29'),
(8, 'heads-of-student-services', 'Clinic', 'Karen G. Bernardo, MD, RN, RM, MN, MPHA', 'School Physician', NULL, '/assets/images/clinic-head.png', '(045) 900-4473', 'info@gnc.edu.ph', 'BETA Hall | Floor 1 | Room 100', 'Monday - Friday | 6:30AM - 8:00PM\r\nSaturday | 7:00AM - 8:00PM', 'Student Health Consultation\r\nHealth Assessment and Diagnosis\r\nMedical Referral Services\r\nSports and Medical Examination\r\nMedication and Treatment Assistance', 8, NULL, '2026-10-01 10:37:29'),
(9, 'heads-of-student-services', 'Purchasing Office', 'Richard B. Sicat', 'OIC, Purchasing Office', NULL, '/assets/images/purchasing-office-head.png', '(045) 900-4473 Loc. 123', 'info@gnc.edu.ph', 'Guardhouse | Floor 1 | Room 100', 'Monday - Saturday | 8:00AM - 5:00PM', 'School Supplies and Essentials\r\nUniform and PE Uniform Sales\r\nBooks and Learning Materials\r\nSchool Identification Accessories\r\nOther School-Related Supplies', 9, NULL, '2026-10-01 10:37:29'),
(10, 'heads-of-student-services', 'Security Office', 'Johnny C. Garcia', 'Security-In-Charge', NULL, '/assets/images/security-office-head.png', '(045) 900-4473 Loc. 122', 'info@gnc.edu.ph', 'Puno Hall | Floor 1 | Room 100', 'Monday - Friday | 8:00AM - 5:00PM', 'Campus Security Services\r\nSafety and Welfare Protection\r\nProperty and Asset Protection\r\nVisitor and Access Control\r\nParking Sticker Issuance', 10, NULL, '2026-10-01 10:37:29'),
(11, 'heads-of-student-services', 'MIS Office', 'Michael P. Maglanque, MBA', 'Network Administrator', NULL, '/assets/images/mis-office-head.png', '(045) 900-4473', 'mis@gnc.edu.ph', 'Limlingan Hall | Floor 2 | Room 200', 'Monday - Friday | 8:00AM - 5:00PM', 'ID Printing Services\r\nYearbook Production\r\nComputer and Technical Services\r\nIT and Digital Support\r\nOther Computer-Related Services', 11, NULL, '2026-10-01 10:37:29'),
(12, 'heads-of-student-services', 'Computer Technician Office', 'Harry P. Samonte', 'Head Technician', NULL, '/assets/images/computer-technician-office-head.png', '(045) 900-4473 Loc. 124', 'info@gnc.edu.ph', 'Puno Hall | Floor 4 | Room 401', 'Monday - Friday | 8:00AM - 5:00PM', '', 12, NULL, '2026-10-01 10:37:29'),
(13, 'heads-of-student-services', 'Centralized Laboratory Stockroom', 'Ricardo Antonio L. Libuna', 'Laboratory Technician', NULL, '/assets/images/centralized-laboratory-stockroom-head.png', '(045) 900-4473', 'info@gnc.edu.ph', 'Limlingan Hall | Floor 2 | Room 200', 'Monday - Friday | 8:00AM - 5:00PM', 'Laboratory Supplies and Equipment Management\r\nLaboratory Materials Distribution\r\nStockroom Inventory Management\r\nLaboratory Equipment Maintenance\r\nDepartmental Laboratory Support', 13, NULL, '2026-10-01 10:37:29'),
(14, 'heads-of-student-services', 'Printing Office and NSTP Office', 'Evangeline Gagui', 'Head, Printing Office and NSTP Office', NULL, '/assets/images/printing-nstp-office-head.png', '09485239722', 'info@gnc.edu.ph', 'Goseco Hall | Floor 2 | Room 200', 'Monday - Saturday | 8:00AM - 5:00PM', 'Printing and Reproduction Services\r\nBinding and Document Production\r\nInstructional and Academic Material Production\r\nForms, Reports, and Institutional Document Printing\r\nNSTP Program Management\r\nStudent Development and Support\r\nCommunity Engagement and Service\r\nLeadership and Civic Development', 14, NULL, '2026-10-01 10:37:29'),
(15, 'heads-of-student-services', 'Sports Office', 'Eduardo V. Gozun Jr.', 'GNCI Sports Coordinator', NULL, '/assets/images/sports-office-head.png', '(045) 900-4473', 'info@gnc.edu.ph', 'Tanjangco Hall | Floor 1 | Room 100', 'Monday - Saturday | 9:00AM - 5:00PM', 'Sports Training and Development\r\nAthlete Support and Development\r\nSports Program Management\r\nCompetition Preparation', 15, NULL, '2026-10-01 10:37:29');

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

--
-- Dumping data for table `admin_people`
--

INSERT INTO `admin_people` (`person_id`, `section_key`, `name`, `role`, `media_id`, `image_path`, `display_order`, `archived_at`, `updated_at`) VALUES
(1, 'board-of-trustees', 'Ronald Luis S. Goseco, MBA', 'Chairman', 176, '/uploads/administration/469c8a6f71bad14e721fff3c7fd2aa6f.png', 1, NULL, '2026-10-13 12:32:45'),
(2, 'board-of-trustees', 'Geraldine G. Lim, MBA', 'Vice Chairman', NULL, '/assets/images/vice-chairman-lim.png', 2, NULL, '2026-10-01 10:37:28'),
(3, 'board-of-trustees', 'Atty. Ricardo M. Sampang', 'Trustee', NULL, '/assets/images/trustee-sampang.png', 3, '2026-10-04 13:48:33', '2026-10-04 13:48:33'),
(4, 'board-of-trustees', 'Ms. Estrella A. Beltran', 'Independent Trustee', NULL, '/assets/images/idependent-trustee-beltran.png', 4, NULL, '2026-10-01 10:37:28'),
(5, 'board-of-trustees', 'Dr. Maria Christina A. Limlingan', 'Trustee', NULL, '/assets/images/trustee-limlingan.png', 5, NULL, '2026-10-01 10:37:28'),
(6, 'board-of-trustees', 'Engr. Jose Manuel L. Puno', 'Trustee', NULL, '/assets/images/trustee-puno.png', 6, NULL, '2026-10-01 10:37:28'),
(7, 'board-of-trustees', 'Engr. Jesus S. Nicdao', 'Trustee', NULL, '/assets/images/trustee-nicdao.png', 7, NULL, '2026-10-01 10:37:28'),
(8, 'board-of-trustees', 'Ana Maria Margarita S. Salvador, Ph.D.', 'Trustee', NULL, '/assets/images/trustee-salvador.png', 8, NULL, '2026-10-01 10:37:28'),
(9, 'board-of-trustees', 'Arch. Karina M. Baluyut', 'Independent Trustee', NULL, '/assets/images/independent-trustee-baluyut.png', 9, NULL, '2026-10-01 10:37:28'),
(10, 'board-of-trustees', 'Ms. Maria Ruby C. Carlos', 'Independent Trustee', NULL, '/assets/images/independent-trustee-carlos.png', 10, NULL, '2026-10-01 10:37:28'),
(11, 'board-of-trustees', 'Mary Clarence M. Dela Fuente, MBA', 'Independent Trustee', NULL, '/assets/images/independent-trustee-dela-fuente.png', 11, NULL, '2026-10-01 10:37:28'),
(12, 'corporate-officers', 'Ronald Luis S. Goseco, MBA', 'Chairman', NULL, '/assets/images/chairmain-goseco.png', 1, NULL, '2026-10-01 10:37:28'),
(13, 'corporate-officers', 'Geraldine G. Lim, MBA', 'Vice Chairman', NULL, '/assets/images/vice-chairman-lim.png', 2, NULL, '2026-10-01 10:37:28'),
(14, 'corporate-officers', 'Atty. Ricardo M. Sampang', 'Corporate Secretary', NULL, '/assets/images/trustee-sampang.png', 3, NULL, '2026-10-01 10:37:28'),
(15, 'corporate-officers', 'Getor N. Isip, CPA, MBA', 'Corporate Treasurer', NULL, '/assets/images/corporate-treasurer-isip.png', 4, NULL, '2026-10-01 10:37:28'),
(16, 'board-of-administration', 'Geraldine G. Lim, MBA', 'President', NULL, '/assets/images/vice-chairman-lim.png', 1, NULL, '2026-10-01 10:37:28'),
(17, 'board-of-administration', 'Allen Jan S. Nulud, Ph.D.', 'OIC, Principal, Junior High School Regular Program and OIC, CASA & Grade School Montessori', NULL, '/assets/images/hic-nulud.png', 2, NULL, '2026-10-01 10:37:28'),
(18, 'board-of-administration', 'Lolita D. Nazal, Ed.D.', 'Dean, Graduate School', NULL, '/assets/images/dean-gradschool-nazal.png', 3, NULL, '2026-10-01 10:37:28'),
(19, 'board-of-administration', 'Engr. Ma. Lydia P. Sacdalan', 'OIC, College of Engineering', NULL, '/assets/images/oic-sacdalan.png', 4, NULL, '2026-10-01 10:37:28'),
(20, 'board-of-administration', 'Getor N. Isip, CPA, MBA', 'Dean, College of Accountancy', NULL, '/assets/images/corporate-treasurer-isip.png', 5, NULL, '2026-10-01 10:37:28'),
(21, 'board-of-administration', 'Wilhelmina G. Bernardo, RN, RM, MAN', 'Dean, College of Nursing', NULL, '/assets/images/maam-bernardo.png', 6, NULL, '2026-10-01 10:37:28'),
(22, 'board-of-administration', 'Annaliza V. Maris, MA, LPT', 'Dean, College of Arts, Sciences, and Education', NULL, '/assets/images/maam-maris.png', 7, NULL, '2026-10-01 10:37:28'),
(23, 'board-of-administration', 'Rowena R. Sampang, MBA', 'Dean, College of Business Administration', NULL, '/assets/images/maam-sampang.png', 8, NULL, '2026-10-01 10:37:28'),
(24, 'board-of-administration', 'Juan Paolo S. Capati, RMT, RPh', 'Dean, College of Medical Technology and Pharmacy', NULL, '/assets/images/sir-capati.png', 9, NULL, '2026-10-01 10:37:28'),
(25, 'board-of-administration', 'Rhoda SR. Cayanan, RPh, LPT', 'OIC, Senior High School Department', NULL, '/assets/images/oic-cayanan.png', 10, NULL, '2026-10-01 10:37:28'),
(26, 'board-of-administration', 'Mary Jane C. Dela Cruz, LPT, MA', 'Principal, Junior and Senior High School Montessori', NULL, '/assets/images/principal-dela-cruz.png', 11, NULL, '2026-10-01 10:37:28');

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

--
-- Dumping data for table `admin_sections`
--

INSERT INTO `admin_sections` (`section_key`, `title`, `description`, `layout`, `display_order`, `updated_by`, `updated_at`) VALUES
('board-of-administration', 'Board of Administration', 'The administrators who oversee the day-to-day academic and operational management of the institution.', 'people', 3, NULL, '2026-10-01 10:37:28'),
('board-of-trustees', 'Board of Trustees', 'The governing body responsible for setting the direction, policies, and overall stewardship of the institution.', 'people', 1, NULL, '2026-10-01 10:37:28'),
('corporate-officers', 'Corporate Officers', 'The corporate leadership responsible for guiding the institution\'s governance, financial stewardship, and overall organizational direction.', 'people', 2, NULL, '2026-10-01 10:37:28'),
('heads-of-student-services', 'Heads of Student Services', 'The offices responsible for delivering essential administrative, academic, and student support services that promote a safe, organized, and supportive learning environment.', 'offices', 4, NULL, '2026-10-01 10:37:28');

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

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `category_id`, `program`, `dept_id`, `user_id`, `title`, `slug`, `content`, `featured_image`, `status`, `is_pinned`, `pinned_at`, `published_at`, `created_at`, `updated_at`) VALUES
(18, 4, 'ALL', NULL, 1, 'tests', 'tests', '<p>test</p>', 97, 'published', 1, '2026-10-14 03:30:56', NULL, '2026-09-30 14:02:00', '2026-10-14 03:30:56');

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
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int NOT NULL,
  `category_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `category_color`, `description`, `created_at`) VALUES
(1, 'Holiday', '#EABA3B', 'Official holidays and non-class days', '2026-06-30 13:44:48'),
(2, 'School Event', '#1F5E2C', 'Regular school activities, programs, and events', '2026-06-30 13:44:48'),
(3, 'Emergency', '#B3312D', 'Class suspensions and other emergency announcements', '2026-06-30 13:44:48'),
(4, 'Reminder', '#2E6F9E', 'General reminders and notices', '2026-08-19 10:00:40');

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

--
-- Dumping data for table `csrf_tokens`
--

INSERT INTO `csrf_tokens` (`token_id`, `user_id`, `token`, `created_at`, `expires_at`) VALUES
(1, 1, 'e30b883f9b02d3d47351237c2fb6640d43fc4dfe6c6ef00b8c6ebb38f1a535ae', '2026-06-30 12:39:57', '2026-06-30 13:39:57'),
(2, 1, '58f2a44714a58267ca2c3ada77f275765604e754bc66292905ef2edb08656cb4', '2026-06-30 12:40:06', '2026-06-30 13:40:06'),
(3, 1, '10c7dd40d35ee675c623fc4a9df40db2a5a286106407803de1e7d08e92051034', '2026-06-30 12:40:06', '2026-06-30 13:40:06'),
(4, 1, 'ae50c9dc1ab1dbe60c96d459c4f27961d4b08226d197c2f4ebbcef88fd42dc89', '2026-06-30 12:40:15', '2026-06-30 13:40:15'),
(5, 1, '2d3d1d3b58c0eec457b0fc56d426f50fc1731a24a65fb3be82124ed516933a3d', '2026-06-30 12:40:17', '2026-06-30 13:40:17'),
(6, 1, 'be3e9518daaf51fbeb3e7309d95684bd7f9e4cb751f5113b302bec08a797775d', '2026-06-30 12:40:19', '2026-06-30 13:40:19'),
(7, 1, '364e20446bbade23c02f626488b77ec777d36fbbfb23fa89481e811c4197faf8', '2026-06-30 12:41:57', '2026-06-30 13:41:57'),
(8, 1, '8b0549e1571798870309eb43a1d058dbec417291013d5e5929b3160c4cec0371', '2026-06-30 12:42:16', '2026-06-30 13:42:16'),
(9, 1, 'db8309e62018eeedd09cbd89de4b5ac3f35b81f70c9c45ddd09081296123e19f', '2026-06-30 12:42:18', '2026-06-30 13:42:18'),
(10, 1, '6e2303ab031ce871f3b5b5f62b0dadd3ba24fef554ef73a93f7f926add813fd7', '2026-06-30 12:42:21', '2026-06-30 13:42:21'),
(11, 1, '86757c473ace2e0fdd8cb6290fe44914c5fd04275d7bb96ed01d5ff6173ddbed', '2026-06-30 12:42:23', '2026-06-30 13:42:23'),
(12, 1, '2ef7aafc67239dfbe2450325c5266712eb0cf781297c7eb834ce79e50855026f', '2026-06-30 12:48:29', '2026-06-30 13:48:29'),
(13, 1, 'a214eddced98b84ca36a09c40fcd9fde23a3ade74df80ae8db29c47dda573495', '2026-06-30 12:48:34', '2026-06-30 13:48:34'),
(14, 1, '768a42d435c23c047441ac0dd6068b6e43922576b9e636338feaa0fbc8173896', '2026-06-30 13:17:32', '2026-06-30 14:17:32'),
(15, 1, '0ad82d3de6828df2bf1416d4367049339963d4362ddb4470baf70e9d0d767e42', '2026-06-30 13:20:43', '2026-06-30 14:20:43'),
(16, 1, '3d3c82ec28d5dd9f49dd3b1308f32b3da70a596e7ffdda1409033d382ead0d63', '2026-06-30 13:21:19', '2026-06-30 14:21:19'),
(17, 1, 'b90b725c16d92fe3624dcef958aee48c46113da9e42f77e8fd5e91b0482c7a86', '2026-06-30 13:21:24', '2026-06-30 14:21:24'),
(18, 1, 'e03f3b353624b314e2a69b03d56079e7054c0f99ddbe184f4233a2ce0ee6984f', '2026-06-30 13:21:55', '2026-06-30 14:21:55'),
(19, 1, '36622eb3cf898f93bc38ce29ca5e589838496a6efed1e1f8c991ed3ecb833805', '2026-06-30 13:22:56', '2026-06-30 14:22:56'),
(20, 1, '5582425a4e36c824ce49ee1b9b186e2e52208c743a61a5e8e46c9b1e3752aa2e', '2026-06-30 13:23:59', '2026-06-30 14:23:59'),
(21, 1, 'd49d6b9c732f05dc5e79f89522d8b465d1842e71c80b70e8b0fa32f290c1289b', '2026-06-30 13:25:38', '2026-06-30 14:25:38'),
(22, 1, '8723f80eadcb148f102531fa873de5bafdd2cf697dd5408feaa6a6fec910dde1', '2026-06-30 13:25:51', '2026-06-30 14:25:51'),
(23, 1, 'f8a099781352ff93f6bafc63ff1de389493032473e9aae0e12e956b43f14d062', '2026-06-30 13:31:31', '2026-06-30 14:31:31'),
(24, 1, 'c73f25398ce13a5619c1dc666ff85ee89c35474b74c0007da29007fad3d5489e', '2026-06-30 13:31:41', '2026-06-30 14:31:41'),
(25, 1, 'f55d862c8aaf8f6e61d0eb010f3314aa538a6c5d1ba43d07012204cf51bd4e73', '2026-06-30 13:31:46', '2026-06-30 14:31:46'),
(26, 1, '1c81152b175b5ab45972e9fadbd67ea5e62930a4344e8d9dd120ceca2df4c2c3', '2026-06-30 13:39:48', '2026-06-30 14:39:48'),
(27, 1, 'a68db4244970eae00f0f44385e877f3dfe6963ca8333a36b6e266059c5591a53', '2026-06-30 13:40:47', '2026-06-30 14:40:47'),
(28, 1, '3c99685491144911b661ba0584e1ee154e9ff04caff31df90e42577a1ee7111f', '2026-06-30 13:40:48', '2026-06-30 14:40:48'),
(29, 1, 'ba2d0777d18382ee3264a7cd0d7f6a67322f14642004e43202013e703f749d22', '2026-06-30 13:40:51', '2026-06-30 14:40:51'),
(30, 1, '3c685f8c0342ca9c27d02090ffabe9ba70c19ad1bde87c01df89b91b2046cabb', '2026-06-30 13:45:33', '2026-06-30 14:45:33'),
(31, 1, '1c6ae3fbe7615e51313350a7e2169500ce1a8abad8a74675dc046045701a5a06', '2026-06-30 13:45:41', '2026-06-30 14:45:41'),
(32, 1, '2f8adedda62649d669b79264bf22a6b7e873ce24fc3195a534625ed81b0e66bb', '2026-06-30 13:45:55', '2026-06-30 14:45:55'),
(33, 1, 'ab05e6c6b74b315373262bce4455690c8b95f919af2f801fc6b86406eb9e6f13', '2026-06-30 13:57:14', '2026-06-30 14:57:14'),
(34, 1, '772d847eb41029d17bbaad8f12042618104307cd7333a0b5e8af96b5bb291e09', '2026-06-30 13:57:25', '2026-06-30 14:57:25'),
(35, 1, 'a4c8bd3a11f1479c18ead70df7370956a9248891f4047bc1029f5ee52ef4eb9a', '2026-06-30 13:57:58', '2026-06-30 14:57:58'),
(36, 1, 'ec2de939e9b9324aa187211ee85f95ee96ab0b21f740212a61f2b4b72bad2e4f', '2026-06-30 13:59:24', '2026-06-30 14:59:24'),
(37, 1, '8c68036c6875bd0cfced09d50b3557af12f4cb970109ac60e824d28fb25c82fa', '2026-06-30 13:59:25', '2026-06-30 14:59:25'),
(38, 1, '5619d0f15a314e00d7cc4ed07ed449bbd84108f8756936817be49e63060b9208', '2026-06-30 13:59:27', '2026-06-30 14:59:27'),
(39, 1, '6e9d85df5eb8dcd37f4879204ece338749884ee2d262e781bc3fcfcbd5025054', '2026-06-30 15:22:41', '2026-06-30 16:22:41'),
(40, 1, '212af161ab51f2703d81a44e75747aef4c7975ba0f14494529d8ed1554fb451a', '2026-06-30 15:22:51', '2026-06-30 16:22:51'),
(41, 1, 'eb29a386b1c5b0f972d24a32f6508dca8ed5bec82283a32562161f2571873525', '2026-07-01 00:23:15', '2026-07-01 01:23:15'),
(42, 1, '1a87d4728c91ba1d9132526047b98ab950e30254a108c0567df23ebcc4f7e467', '2026-07-01 00:23:20', '2026-07-01 01:23:20'),
(43, 1, '49e1f0466d8ef8bdd6712a7551bb5043290fbbe436068a84a4c2df189062f5c6', '2026-07-01 00:24:50', '2026-07-01 01:24:50'),
(44, 1, '1ba0c0381e8ee8ee95f5cbecb6d724e97c86cf25d07e9a6238849aa645434cfd', '2026-07-01 00:29:14', '2026-07-01 01:29:14'),
(45, 1, 'd57777007f703419355d46ef35e66882c6bed6f786a35d11a88e2cb9973a926d', '2026-07-01 00:29:37', '2026-07-01 01:29:37'),
(46, 1, '9ef622aa4a96eb171595d9bd216fd9a7fd84fad62c22f71cd420a1ce6bef5d9a', '2026-07-01 00:36:28', '2026-07-01 01:36:28'),
(47, 1, '656477b6e87044a1e63e280f073dcdbe425f34772c57d091813925f57174f61d', '2026-07-01 00:45:25', '2026-07-01 01:45:25'),
(48, 1, 'df535bab8f33f9dde893f52320624a51c7080518b5cc7bd2cd32179e475d5beb', '2026-07-01 00:46:42', '2026-07-01 01:46:42'),
(49, 1, '54661ab92a4e9d37273cf5402834736aa7754c27b92f62e45e1bad2641ffc8ff', '2026-07-01 00:47:27', '2026-07-01 01:47:27'),
(50, 1, '792a641929f43816e887b7121d999ba49afd7e4eaa6b0c5bc8b3afc770bf6c00', '2026-07-01 00:48:54', '2026-07-01 01:48:54'),
(51, 1, '16d5d26dbc90dc4f2d45bb29d0441d5af2cfad18a3d2f5a8cc4efaaaa690cfa3', '2026-07-01 00:49:56', '2026-07-01 01:49:56'),
(52, 1, '4510578cf73786b1138fe46f8e4bdde6afc01378cb05c9125f9996d47714f22f', '2026-07-01 00:49:58', '2026-07-01 01:49:58'),
(53, 1, 'd5742e1d17e7e0275ad55c1d765c49f58cc6fb6039d69722e2250d2c017ed2d6', '2026-07-01 00:49:58', '2026-07-01 01:49:58'),
(54, 1, '7a910270f26fa290a5e2e20fb7e423b769703db8815fc571bd35e534c8c796fc', '2026-07-01 00:49:59', '2026-07-01 01:49:59'),
(55, 1, '854d132b9275f552c370dcb959daa684224fafc0fee0ab9cbbf3ceb113284376', '2026-07-01 00:50:27', '2026-07-01 01:50:27'),
(56, 1, '89205c249959ded387cba942a340e5c1ee71121b485eafc58f2622db3cc21f59', '2026-07-01 00:50:38', '2026-07-01 01:50:38'),
(57, 1, '1af4ffc066bfab43494b8e33bfcf502dd7f90d28bc09f8a92f9109719da11040', '2026-07-01 00:51:45', '2026-07-01 01:51:45'),
(58, 1, '4aa19c3545fa8e277d13e1db98898245d6f420c06abf64d213df5b9ef15a00e6', '2026-07-01 00:52:35', '2026-07-01 01:52:35'),
(59, 1, '0af6f0e01d5f4851223a38f69bade7614b2f3f96f5e8f319077842d56c9c6830', '2026-07-01 00:55:01', '2026-07-01 01:55:01'),
(60, 1, '5628867db22a4106a49a6418cd6ebaa739e3531f3a6dfc40cc8dbe67fc22e9ea', '2026-07-01 00:55:28', '2026-07-01 01:55:28'),
(61, 1, '6569b668e3813cd96b487d35c54bbee1862443344699a914c2943d3e13f15761', '2026-07-01 01:22:49', '2026-07-01 02:22:49'),
(62, 1, '13eabaa6fb4108d19991cd46b6b7317151e59f5222e38505b21b8d62a4fdf10e', '2026-07-01 01:23:02', '2026-07-01 02:23:02'),
(63, 1, 'b5b2e84a6aa8417d782d656450dfef86ce6244017c322a086102be1c7aa7349c', '2026-07-01 01:23:05', '2026-07-01 02:23:05'),
(64, 1, 'faf55a162874dbb78f48dbef0dec9c1349fd98e106dc03e97bdf99bf0c5a86a4', '2026-07-01 01:23:07', '2026-07-01 02:23:07'),
(65, 1, 'cc66afa9e7ecfe18cf65aa8f18eda8737d4554d4b41ef3272c86d1e5b78a17c7', '2026-07-01 02:19:48', '2026-07-01 03:19:48'),
(66, 1, 'f2c46f4fbcd3cfea104144313f59add99732006de91cee0fab771e2d5425b295', '2026-07-01 02:20:37', '2026-07-01 03:20:37'),
(67, 1, 'fd88770a87f19c681aa30694eb51a60b04b83562e06f6626ec6147b200057767', '2026-07-01 03:11:22', '2026-07-01 04:11:22'),
(68, 1, 'e0b97dd90093289bd8d6742a7e0b2f8361bc11705fa44da3cb166f98a27b557a', '2026-07-01 03:27:14', '2026-07-01 04:27:14'),
(69, 1, '8eeb6a4eb1e9fadda186ef10d39c0a5a981de25eb25e74fd614cb0e1caa196ad', '2026-07-01 03:27:51', '2026-07-01 04:27:51'),
(70, 1, 'ef042046295a857706c4f26547bb98f9024c852e37908f03cb617de301b5df0f', '2026-07-01 03:27:54', '2026-07-01 04:27:54'),
(71, 1, 'e035657c0bb3d5769413fc3f9edc3fda38756184bf0f302b002f6b39cf878460', '2026-07-01 03:28:11', '2026-07-01 04:28:11'),
(72, 1, 'ab0232c92ff1ef60b9e3f231e80d4b7c0ac08bb58865a7e618708f649df5c7c8', '2026-07-01 03:30:36', '2026-07-01 04:30:36'),
(73, 1, '29569340ea72c9f8b15adfc7402b5796de242e9afa36ce1e195e9bfad8eee73f', '2026-07-01 03:31:13', '2026-07-01 04:31:13'),
(74, 1, '853cd2d275555f6d71aa3ac88005f48132cb4da2aaa628ffefcfe32c277ff23d', '2026-07-01 04:59:40', '2026-07-01 05:59:40'),
(75, 1, '05cb7b4f4a2f6c3ad97e2b53a1694df448aef9d560e97e90ca3c28fa9f2c7f5d', '2026-07-02 14:20:49', '2026-07-02 15:20:49'),
(76, 1, '4619ca12485674f770484582efeab8d5de6cc975e758bcc5740095115243c827', '2026-07-02 14:20:59', '2026-07-02 15:20:59'),
(77, 1, '5c4f534dbca938438be1e52e8e1f2384267bc17e973390f8d9b28456ea4bdd05', '2026-07-04 11:26:20', '2026-07-04 12:26:20'),
(78, 1, 'b72e42e45a4680cbf8a4d857e17481a61f44d05c8720ebd43cc16a41a51df6bc', '2026-07-04 11:26:23', '2026-07-04 12:26:23'),
(79, 1, '14c305b716b9334c4f40ff892f7eb44911430d9a4ad49e7feab8f092d9b5ad25', '2026-07-04 11:26:25', '2026-07-04 12:26:25'),
(80, 1, '70967ef674c343549b79b41fb3ea494318db4e1774f09ac55e2a0ef83451f79c', '2026-07-04 11:26:48', '2026-07-04 12:26:48'),
(81, 1, 'b09848665509c62e35186d90126302f3436f4a52a2297fefba497070720fe59f', '2026-07-04 11:26:49', '2026-07-04 12:26:49'),
(82, 1, '8c6816dcf992da7bce578013858aeacf848f9262e42c27413800cb5ebd55dbb4', '2026-07-04 11:26:55', '2026-07-04 12:26:55'),
(83, 1, '1771c62679401c6ab2e9aacb80bc8b220acb263d85e7f5d937914000274c9071', '2026-07-04 14:45:21', '2026-07-04 15:45:21'),
(84, 1, 'a35bd2eb30c2bcbf2baaf243d1736c677d05de1f47ebb6640594e5c3b7a22f23', '2026-07-04 14:45:22', '2026-07-04 15:45:22'),
(85, 1, '70d14993d639b0dc1880a446b3227c3548e0b620ce986e321d28b9d63e71fa97', '2026-07-04 14:45:26', '2026-07-04 15:45:26'),
(86, 1, 'f453b82699720ebae595759c738a93168e869d6e8091c6d9d9be48dd7cfbc8aa', '2026-07-04 15:12:50', '2026-07-04 16:12:50'),
(87, 1, 'd5c9425aaafdf625b83a237f2b4accb34a9d00c0d209533363a5c79c7448ea8b', '2026-07-04 15:12:52', '2026-07-04 16:12:52'),
(88, 1, '3da3ce089e8d1ae60bd0956512885cc2ea1156aacd8962c2d75d845122627354', '2026-07-04 15:22:54', '2026-07-04 16:22:54'),
(89, 1, '43518a1fe85b6d39aab698840bcfb91b88a819e8c32bca02f7176b006e6dbce5', '2026-07-04 15:22:56', '2026-07-04 16:22:56'),
(90, 1, 'fd3a0aaf429597556923bcc86ef2f34e3c165d3f065174b4351db738f98fcc9a', '2026-07-04 15:22:59', '2026-07-04 16:22:59'),
(91, 1, '94817e7eb5e32efb98210fb4be0f10e5d4f222420d0c48e4a8a647deed5f0449', '2026-07-04 15:32:17', '2026-07-04 16:32:17'),
(92, 1, 'a996f0f536bc0e27e96216dba1f3865656d9bd2800a3c4c598939bacda70f500', '2026-07-04 15:33:11', '2026-07-04 16:33:11'),
(93, 1, '87f028f388dd26f69b25487104a6f4ba036cade9b1a384f58b11455ec7fb6b10', '2026-07-04 15:33:13', '2026-07-04 16:33:13'),
(94, 1, 'bd3cb60cf5741bfe8fdf4fc8d510ca5bed60822137b06e2ce6035d2f1f9d91cd', '2026-07-04 15:33:29', '2026-07-04 16:33:29'),
(95, 1, 'f7a63550b41104e0ecb7202d1d89307eaf361ed74f965163abbf8c71e26ddcd3', '2026-07-04 15:43:18', '2026-07-04 16:43:18'),
(96, 1, '4f57d40a97bd05fd54ed117c3f40f294b02b05cd3025d175b51d36801768c6a7', '2026-07-04 15:43:30', '2026-07-04 16:43:30'),
(97, 1, '6bd85c4ddf43088c0638e9c9249ba4ede0caa7b0c3feb468932fcc66fa24f875', '2026-07-06 02:58:49', '2026-07-06 03:58:49'),
(98, 1, '2e086f9fa32239a993ea786f23530bf82f3bef8296908666c09b1054401ac79c', '2026-07-06 03:01:07', '2026-07-06 04:01:07'),
(99, 1, 'dc9b0420cb7b0646350e9b1b6c2fa6b812e57deb17532655fb5e809dd2e88050', '2026-07-06 05:24:38', '2026-07-06 06:24:38'),
(100, 1, 'e2ebf9e2bda1d63d6f5059edac0312d698608a7ea1d4461d36243d652f0415a0', '2026-07-06 05:24:47', '2026-07-06 06:24:47'),
(101, 1, '561eaf6a61af9c2b9aba363a57db9ea3872d87ee5e2cbce44306336bb31a5ad6', '2026-07-06 06:46:24', '2026-07-06 07:46:24'),
(102, 1, '6a7896eb22c76f6b7fe7a0be1346f654d433e2a90912488cfeeac6728d58d0c9', '2026-07-06 06:46:31', '2026-07-06 07:46:31'),
(103, 1, '59a2229fbe98bbadb9f09c3452ef7109e5ac69f08a6c239070fa0372e6eaf1ca', '2026-07-11 13:26:41', '2026-07-11 14:26:41'),
(104, 1, 'eed88cf5428b6e9da5deb2c3d55dd1ce957b08bd044c743a7e5c6d2dea17b995', '2026-07-11 14:19:54', '2026-07-11 15:19:54'),
(105, 1, '67e7fb862df2d438c85d7a0c9372f5cd7d53f1f4a28410795d2ece05582ffc48', '2026-07-11 14:20:30', '2026-07-11 15:20:30'),
(106, 1, 'ae4e29333620b318ec759a1ed54d5d94fbf84cb66d03d9cc10cf1f1e707e2480', '2026-07-11 14:20:32', '2026-07-11 15:20:32'),
(107, 1, 'ca53491035840e1fbe07ff0d4381e9eac6f37b57ecbf9656c5660b46254a4f51', '2026-07-13 11:08:06', '2026-07-13 12:08:06'),
(108, 1, '7278f45b432f16a96b0bce220cf40e2db38b15ec527b25b30f58ed922c377301', '2026-07-13 11:08:07', '2026-07-13 12:08:07'),
(109, 1, 'c95f92a3bb504705bfd83f6737ede3a4992c76d039d74539274784329040ac32', '2026-07-13 11:08:11', '2026-07-13 12:08:11'),
(110, 1, 'ce75e1dc6b7b66398326fddc0583e27bf728add590a6a86a9149bccd82563b74', '2026-07-13 11:21:17', '2026-07-13 12:21:17'),
(111, 1, '08b07d11c157f9d5fc12c0d6233f53826e02674e7b7078bba019e7fb7641c478', '2026-07-13 11:21:19', '2026-07-13 12:21:19'),
(112, 1, '24784ddeb79f8d6442b7c8dda1335171fbd4dc71ab3c827bd11c95096792e3a3', '2026-07-13 11:21:48', '2026-07-13 12:21:48'),
(113, 1, '495404f695e09f614b40931a39df7830b601b2cdb5998e52150a944a627f6826', '2026-07-13 11:23:02', '2026-07-13 12:23:02'),
(114, 1, '2e33ce4d79e1c9c58e7d368b7812425d15c4dc04fc1d78666700fb377ac845d2', '2026-07-13 11:51:25', '2026-07-13 12:51:25'),
(115, 1, '010c682f10b1c945069c0df586712add490446562c97d91c819d2070c2b5184f', '2026-07-16 14:27:05', '2026-07-16 15:27:05'),
(116, 1, 'e2ea19eda7effb4079db9cd45eab2ec2f7d007dca385776b491176a7dc9ff88c', '2026-07-16 14:27:08', '2026-07-16 15:27:08'),
(117, 1, '1bced7d8245aa49eae2bf53ca4c4b2f192f7dfc01e32e57a1fb1c5f66ed8ace3', '2026-07-16 14:27:23', '2026-07-16 15:27:23'),
(118, 1, '23128c1624565b192d954961fa46631218e9e15cc2aa2934f40be0eed7a1707d', '2026-07-16 14:27:26', '2026-07-16 15:27:26'),
(119, 1, '96533c052677968544f1fdef2bcd569546f48358f4ca1d2d64e49040382d5b5e', '2026-07-16 14:27:29', '2026-07-16 15:27:29'),
(120, 1, '9e3ee4f26ebdf5e4c9d9a4d345469db68962089bd24b0bb29f98c6da93c6ee83', '2026-07-16 14:27:35', '2026-07-16 15:27:35'),
(121, 1, 'fdfb620871e4f010b97e3e1bd28715d28db34b89f4fe705a9e85b5bddeaeaa60', '2026-07-16 14:27:36', '2026-07-16 15:27:36'),
(122, 1, '502c9f57799974205a381b4de5e06f7edf98e7745b144c33c5e56623888024cb', '2026-07-16 14:27:37', '2026-07-16 15:27:37'),
(123, 1, 'ad5d97e7794771988d13c7627f5eb452948435286d09887e3939c16d0d3e5107', '2026-07-16 14:28:32', '2026-07-16 15:28:32'),
(124, 1, '68ca07d9e27ad5ea3d64f83df7e8250a6abfbdee25e9f86cc8f2c55908fde545', '2026-07-16 14:37:54', '2026-07-16 15:37:54'),
(125, 1, 'd55e48fbe72fa59bc87dfaa8784eec82075309927311187387529960d30c0a75', '2026-07-16 14:45:24', '2026-07-16 15:45:24'),
(126, 1, 'a9b37ffa94261765af0fb2a64a2555ef581f7b73dc3f6c7c97d43130bc28945a', '2026-07-16 14:45:26', '2026-07-16 15:45:26'),
(127, 1, 'debf4733a597dd67436f735f05e1f64739636598f6b64113d5037473c7774a51', '2026-07-16 14:45:51', '2026-07-16 15:45:51'),
(128, 1, '148fd075a2090a5694671df26185da18826bc0ff061d1e558c7ecbabff3baf3a', '2026-07-16 14:48:30', '2026-07-16 15:48:30'),
(129, 1, '66316962f0cde1a5a5fe13a406fd82f6f2269ef0d018c55a4bcba692d6227f8f', '2026-07-16 14:55:58', '2026-07-16 15:55:58'),
(130, 1, '914d336162a2caf502e307c58175082a479bbc989afff3506a479c8d609c34cb', '2026-07-16 14:56:53', '2026-07-16 15:56:53'),
(131, 1, '2677780f0bfa616a0eddf34cebde1309ff22e558202593b5635109ee57f39433', '2026-07-16 15:34:43', '2026-07-16 16:34:43'),
(132, 1, '49e971f3ccbc3838c3c69fe5f9737448f059a32ec252995a06b94cefffde64d1', '2026-07-16 16:17:02', '2026-07-16 17:17:02'),
(133, 1, 'fae0752e2bdf8e2c8d7384053d222c142fa2bb553ed6047c0240a66452c50dff', '2026-07-16 16:17:03', '2026-07-16 17:17:03'),
(134, 1, 'be19fe7d139f4308a87d07e4220f5c6e129682ac2f35343dc84e4866e9e39346', '2026-07-16 16:17:35', '2026-07-16 17:17:35'),
(135, 1, '8c1aae25cb1700f60750a3cc1f2489269935985c8b5b9ea3672005ba10a6f125', '2026-07-16 16:22:16', '2026-07-16 17:22:16'),
(136, 1, '18586887b7ba12f8dde8a73a87146340de90bf43084c9afe45ec323180166a63', '2026-07-16 16:26:12', '2026-07-16 17:26:12'),
(137, 1, '973b63ec8abaffd9160e3c80a18b1786b908b5014df8fb75b466662d5df68793', '2026-07-16 16:38:41', '2026-07-16 17:38:41'),
(138, 1, '7c119af6a5ca5a2fb1ed26cb4a46dfb4ebcd88d171450bc7c88c5f48e823cfd1', '2026-07-16 16:39:12', '2026-07-16 17:39:12'),
(139, 1, 'c9e88f3124c4077b35f1e4f6b339a1c21c3107fc4734789071eceb9a64e2b494', '2026-07-16 16:39:37', '2026-07-16 17:39:37'),
(140, 1, 'fa3e7f30185f772b701fd616a78a99ef4d73dd990bb9e585f4c99cda9ba96211', '2026-07-16 16:43:18', '2026-07-16 17:43:18'),
(141, 1, '9e3fa56dddb7fd0212ff6803c9fe6d9b0fee3f3d5a631ff368a3c227aa5d83f2', '2026-07-17 05:30:55', '2026-07-17 06:30:55'),
(142, 1, '0e948134cdf40794643a0f0865c3ebc950c3bb8c22830776126861a857430943', '2026-07-17 06:33:49', '2026-07-17 07:33:49'),
(143, 1, '0a181317ce406f55b1861d2bd7dc9a25a12e5bf093357a39b82142e408503d19', '2026-07-17 06:40:14', '2026-07-17 07:40:14'),
(144, 1, '5f81173099bcf850dcde18d9ce97a9e4a119980e82d062b983b6699fbe383e43', '2026-08-14 13:11:06', '2026-08-14 14:11:06'),
(145, 1, '1811ec980244b14e06b3ebc5cc94e43e44e8e5bbabd22e1c0985621fbfe4ad7b', '2026-08-16 05:26:53', '2026-08-16 06:26:53'),
(146, 1, 'c01aa82744afb0587014b04954d3aa421167eb543a31c5d879a72a156e09bdbe', '2026-08-16 05:26:59', '2026-08-16 06:26:59'),
(147, 1, 'e5e8340d2ad20296ea6fd17ccc0f8b4671b3518613c09ff5c75a5622df41c7f4', '2026-08-16 05:27:00', '2026-08-16 06:27:00'),
(148, 1, '59025533b2b307cdb7bb2fd5eeb40ac9ed297e523e51192a2a6c1cfc962b6849', '2026-08-18 13:14:16', '2026-08-18 14:14:16'),
(149, 1, '251711305e0bb580c234767f388da43c02518ab2a4d123e7d116a49a3b7144d3', '2026-08-19 10:01:59', '2026-08-19 11:01:59'),
(150, 1, '4dff876d021cbe800d0ee2b7f66d98a4d390b46cd019c89b12a478bcf2fca515', '2026-08-19 10:02:04', '2026-08-19 11:02:04'),
(151, 1, 'e5ea37f170818bd7dec09dfb41689a16017616c4b4c7d08e69df3e425f8900ff', '2026-08-19 10:02:13', '2026-08-19 11:02:13'),
(152, 1, 'edcf3c6a11b0f6c0bfc0cc44272abaca134bf99b38337f8e80294f4a0b11a4aa', '2026-08-19 10:02:16', '2026-08-19 11:02:16'),
(153, 1, '6d939309668d809c56618a97687d552f5e4cd6fe5cb3f69874917935f9e51f69', '2026-08-19 10:03:45', '2026-08-19 11:03:45'),
(154, 1, '88f6b300ee07a09eea3597117e73fe64d3af0edda98cad208ecc95ccd6b597db', '2026-08-19 11:47:11', '2026-08-19 12:47:11'),
(155, 1, 'f412af6c0ae1afdcc2ecd81d11ec1db00b95ab5f1dc3ce0a5e89c02b5f6a2a1d', '2026-08-19 11:47:15', '2026-08-19 12:47:15'),
(156, 1, '5e186cff861b6711640b955e7db1943b1154490df78c74fed0f8b1b2fda8f23a', '2026-08-19 11:47:17', '2026-08-19 12:47:17'),
(157, 1, '4ada56d9dc1d9370480f2ed997393eb7e196941239963ae4f88b11007a1b5649', '2026-08-19 11:47:18', '2026-08-19 12:47:18'),
(158, 1, 'bce20880d97d99ca62672a69ad75e69a417871d761d81fb641c0c5ac00414a15', '2026-08-19 11:47:19', '2026-08-19 12:47:19'),
(159, 1, '52914c7faaf3621099acf690593ec8fee0d53ec308929b649cd88f147194ed62', '2026-08-19 11:47:20', '2026-08-19 12:47:20'),
(160, 1, '72acf26d51afecf937764b630705d8072817c001d46dfe264b893bb48cdf40bc', '2026-08-19 11:47:21', '2026-08-19 12:47:21'),
(161, 1, '0a8e34e9b7a6c0b662d41a7a9ed6b11b463fa322fb13fe51243da443a411b06c', '2026-08-19 11:47:23', '2026-08-19 12:47:23'),
(162, 1, 'a0006d0b23efde7e60447520d42755d7cb5f1db6b6bbd493ef525272bdd27041', '2026-08-19 11:47:33', '2026-08-19 12:47:33'),
(163, 1, '38fef7044fbc0bfa2bbd907161135fa2fec11a87dc9e7aebe9a1b62f90158286', '2026-08-19 11:47:37', '2026-08-19 12:47:37'),
(164, 1, '995cd5b356248ff2a34b391f33018e6f06b07d3792ca19b39e787a125a09c043', '2026-08-19 11:47:44', '2026-08-19 12:47:44'),
(165, 1, '8fde76b01a3d73c2d88c21055c497c3328fcdd6b33de3c32cb89e7773d2790c3', '2026-08-19 11:47:46', '2026-08-19 12:47:46'),
(166, 1, 'ea9312c1533ac90dbef9af2075d15cfb07cb5e29228630540bc87da3ae5a43fc', '2026-08-27 09:50:30', '2026-08-27 10:50:30'),
(167, 1, '0ac19a5d208401838ea3e7a27956dcbadd9328cf0e977b1bac8edee89c687836', '2026-08-27 09:56:21', '2026-08-27 10:56:21'),
(168, 1, 'e6547c675ad52e87588e62eb86922719623d62d0e9c6db6645753b01b089ba87', '2026-08-27 12:33:55', '2026-08-27 13:33:55'),
(169, 1, 'f6b14445b5f05c8ad56ccac8a952f0ed5c4b24e2ccf144e81443b50be2dd862e', '2026-08-30 06:30:35', '2026-08-30 07:30:35'),
(170, 1, 'f3a9888faa6ff1a9adeb922e11b929ad854cad72d11a12826cc405e2bdaa8198', '2026-08-30 06:30:36', '2026-08-30 07:30:36'),
(171, 1, '8b19350463e7cf665f211cb45ed0f9fa6ff1cb93dc22fd44439395ecc11c18e2', '2026-08-30 06:51:31', '2026-08-30 07:51:31'),
(172, 1, 'ec681f2865e7c3119b2fb6a825887d0a13c1650c2882ecf325d33aedeaae5a3c', '2026-08-30 07:07:12', '2026-08-30 08:07:12'),
(173, 1, '6b1426ff260a152a6bf1f866517d7b3fbbf6410138b665833a4cffcc34fc7f56', '2026-08-30 07:07:26', '2026-08-30 08:07:26'),
(174, 1, '87289b90f5ca0ae968058f06cf04608db95834239d904eadf596439979a1a430', '2026-08-30 07:11:05', '2026-08-30 08:11:05'),
(175, 1, '968c9b3ff6059e68ed34d573db388d85f5541c6939742e09e7dab15cdec948cb', '2026-08-30 07:13:41', '2026-08-30 08:13:41'),
(176, 1, 'b66e7447315e21a031a38070a7fd8dc037054157fada504d4dbe17e40a2b1d0c', '2026-08-30 07:20:10', '2026-08-30 08:20:10'),
(177, 1, '1675bdc2686f0284982d702edc8c3e881a4360d2de4d7bbeadc32500d4d53a32', '2026-08-30 07:22:57', '2026-08-30 08:22:57'),
(178, 1, '8b3e7d636f2bc32d2dac2d2da111d9ad63bd7345f861c2dbb10589ca892b2baf', '2026-08-30 07:26:35', '2026-08-30 08:26:35'),
(179, 1, 'ac42614a999d55fef3534763fcd46327df41ec5e915c9db9de3ab3dec5768e57', '2026-08-30 07:26:47', '2026-08-30 08:26:47'),
(180, 1, 'f427a8a5cada044de3578665875165113906d066f742fe674cda80f04d7dd109', '2026-08-30 07:26:53', '2026-08-30 08:26:53'),
(181, 1, '7020ac0146c7b5258144650bb808bd50c5c243039e8a4b944f66a5dce72c069a', '2026-08-30 07:26:58', '2026-08-30 08:26:58'),
(182, 1, 'b27df9576a2f40d062c99da54a866de2a725346ed02fd4332a556270756dbad4', '2026-08-30 07:27:32', '2026-08-30 08:27:32'),
(183, 1, 'cd94f07679de4d89011454b32e4e3fadb11a8eed2e87f072b666859cf2e081c1', '2026-08-30 07:28:34', '2026-08-30 08:28:34'),
(184, 1, 'f3aec5583ad4074992ba6d0cd24596cb7a342869068e01c1431345bbbd07bd92', '2026-08-30 07:29:17', '2026-08-30 08:29:17'),
(185, 1, 'ca92fc03a637c25ac741617ab38992dcb39ad18bc16a6b461504940c6e4ddbae', '2026-08-30 07:30:03', '2026-08-30 08:30:03'),
(186, 1, 'c41c13ab589aa4b2db21f0b771b84bb65b5ae2e6178fecc9691beff5ba72a15e', '2026-08-30 07:30:16', '2026-08-30 08:30:16'),
(187, 1, '28af26e60d8a9d9b6f4aba04878b07716daa5a0ae4a9e068766ab741f39e7f64', '2026-08-30 07:40:06', '2026-08-30 08:40:06'),
(188, 1, '6ff343ed7ea98a1bc74e79dc5adfa7b3db1efee72b9f07057a9cb42789058a14', '2026-08-30 07:45:15', '2026-08-30 08:45:15'),
(189, 1, '6391d2a60641d9cb486746824779920d399ebfb29071c5d45772fd2018bafae1', '2026-08-30 07:45:17', '2026-08-30 08:45:17'),
(190, 1, 'b30e969ebb7dbe427c9e4ca71d4bcd7425df257e6f2a2857839088a24859df0c', '2026-08-30 07:54:35', '2026-08-30 08:54:35'),
(191, 1, '0bf1a96929fb8ed38b046ef110f48a9cbba5964ac1b6af8404c3319bfcd1780d', '2026-08-30 07:54:47', '2026-08-30 08:54:47'),
(192, 1, 'c8a3d7df4a25368f560c3f476e0f5ab5386522709e19a6b57af5e676ee00c4c7', '2026-08-30 07:54:48', '2026-08-30 08:54:48'),
(193, 1, 'ba7b1df63be30992648fd9fe874eb561505d126e87efbad13fe26d3a5bcd01be', '2026-08-30 08:02:04', '2026-08-30 09:02:04'),
(194, 1, '1d79c8bfea371feba5f3a7e1b7a7e90f39f43e59709418ad805940f3809c9f91', '2026-09-21 12:15:50', '2026-09-21 13:15:50'),
(195, 1, 'c494bd5aeeef23e4a261f25968de4117aa517563db9d2e953688bf8e3f423137', '2026-09-21 12:15:51', '2026-09-21 13:15:51'),
(196, 1, '9fe94d84fa36804f1339c8d2c7f390b989ebc5a9ca6d76d134d57d10b50e0748', '2026-09-21 14:27:24', '2026-09-21 15:27:24'),
(197, 1, 'c94398a458c6fca6391e51b89fb78405a3bbedb42f0bbff86f77c6650b61f21b', '2026-09-21 14:27:32', '2026-09-21 15:27:32'),
(198, 1, 'dbd5aa1b650d49d3a0f87f4bd32f0eae9285c9eda4024bd1aa61e4a47fdfa90c', '2026-09-21 14:27:34', '2026-09-21 15:27:34'),
(199, 1, '6784038a3fc1a92da248383b6aa70620218e318abd35f939fcc977b77a4db496', '2026-09-21 14:49:08', '2026-09-21 15:49:08'),
(200, 1, '530b06220629464fdb2f9d19126d91c7dfc94af4cd96d2fb8547d34040f44720', '2026-09-21 14:49:12', '2026-09-21 15:49:12'),
(201, 1, '8a1a11dcbd054565ce4f2ffe0b625f91ff23a702d1085875d3ed908422effd71', '2026-09-21 14:49:13', '2026-09-21 15:49:13'),
(202, 1, 'de93a713ed16ed9289440edde249d17ce1f4b09fa22794bfd4649e6a4d216c6f', '2026-09-24 03:29:35', '2026-09-24 04:29:35'),
(203, 1, 'bf6c813f426b67f072fc952c71a47f457a15096a8d30cbcba62c265de03932c6', '2026-09-24 03:29:39', '2026-09-24 04:29:39'),
(204, 1, '3035fddb577c0014e4f91308b29533bd327008f7e7d14d6c17e29c6d83b0babd', '2026-09-24 04:25:28', '2026-09-24 05:25:28'),
(205, 1, '11684ab1c1ceb07bf86a8c0c6f4c79420945fedc4ea42162ebd4bb2b5165f63a', '2026-09-24 04:25:32', '2026-09-24 05:25:32'),
(206, 1, 'b5df6598b49f23dc702d1c4268c211c1507f10b7c3acb871dd42c191fcb84c3b', '2026-09-24 04:25:32', '2026-09-24 05:25:32'),
(207, 1, 'f9d634c56f367ff884c37551574acd6775c2f2defa16a2a94c13e3975f41afa9', '2026-09-24 04:25:34', '2026-09-24 05:25:34'),
(208, 1, '02ad47b587d2798e6312722aa3e4c091769ae082d3ac540709a01729ce900544', '2026-09-24 04:28:21', '2026-09-24 05:28:21'),
(209, 1, '75511c0c4d9438bd3c90664d23bdd1168251403721a9b714c11723c82ade079b', '2026-09-24 13:08:52', '2026-09-24 14:08:52'),
(210, 1, 'dc1943457be89b988b89300ef4a5c2526bb404711b7457484f783a13154705b4', '2026-09-24 13:09:51', '2026-09-24 14:09:51'),
(211, 1, 'b9020f88ab8f4814ffcbeb19b3d25505dee6842b0c7f9660a2441fd58b33079b', '2026-09-24 13:10:01', '2026-09-24 14:10:01'),
(212, 1, 'c2bb53624ecdeb8cdd9ee47072ecd614d0947dabc14e515f4d507df03417ac1d', '2026-09-25 07:50:14', '2026-09-25 08:50:14'),
(213, 1, 'f8981db9452215b97c7c090e6f4ee8cb0610c24cda713b121126f09ddf4e77f9', '2026-09-25 07:50:35', '2026-09-25 08:50:35'),
(214, 1, '0d44ad592a0cb16e47ff2b009c52d16cf98794b70908391426c6618be5edf507', '2026-09-25 07:50:37', '2026-09-25 08:50:37'),
(215, 1, '3daa2e9148ff75a2400c90c21eba07c672e17f848e113fb9d32a0666c026f568', '2026-09-25 07:50:38', '2026-09-25 08:50:38'),
(216, 1, 'd20e8505b5b66958a786da9327c0dda5e59d2eb1177826f7ee64ec41507a7c1a', '2026-09-25 07:50:40', '2026-09-25 08:50:40'),
(217, 1, 'dde8bd546d6fc94dc057e1cc5cd78dc3cc67790806a1d37bdbe6a9ea0362a664', '2026-09-25 07:55:14', '2026-09-25 08:55:14'),
(218, 1, 'a70509bb8ab4d0a964d7bb7b36db714fd1b20cdbd0bb4f31d8d2be43c54ee348', '2026-09-25 07:55:37', '2026-09-25 08:55:37'),
(219, 1, '5c63a54f71c53fa48a048920e17e3012e4f376f7e2155ba1b8fe3da75dce4a9c', '2026-09-25 07:55:41', '2026-09-25 08:55:41'),
(220, 1, '00f4a62dba60714a4bc72461707a6f1818ff8ed8f6e851d15b173463703fe682', '2026-09-25 07:55:47', '2026-09-25 08:55:47'),
(221, 1, '964605b15c1c446a72bf9eef2c4f5e976bcaa162e2e870244ccb361fc98040d6', '2026-09-25 07:55:49', '2026-09-25 08:55:49'),
(222, 1, '68fdc8191a2c81a27ada8164488110c8bc395051d6cab5d8a86ce449cc9680c4', '2026-09-25 07:56:57', '2026-09-25 08:56:57'),
(223, 1, '71763a5489757897aa76423fc4958c2ba97620cf32b6d3014cfcf23e7476f87a', '2026-09-28 13:06:26', '2026-09-28 14:06:26'),
(224, 1, '1e2750cae60f4ad616f7939fbeb2ee313c4af870e937f9b16b3ba42f8957c212', '2026-09-28 13:06:27', '2026-09-28 14:06:27'),
(225, 1, 'fbddeeb784097fb4767d595b0094071869434acb8a4a4ec1fe803b2e7a82828f', '2026-09-28 13:06:28', '2026-09-28 14:06:28'),
(226, 1, '817759ff3044c2fbba3438ab2bc6b664c117609dd9dafd1d6e784224e3ee7eb4', '2026-09-28 13:10:41', '2026-09-28 14:10:41'),
(227, 1, 'db23c3bd470af790b25d199147de9745dbab1ae7d8de3c139c6ac9b43a1a715b', '2026-09-28 13:10:42', '2026-09-28 14:10:42'),
(228, 1, 'c4a92afdc5d3161b6270fbdc791094438995f1c8d020f1f571db25ed1c5141d6', '2026-09-29 12:01:42', '2026-09-29 13:01:42'),
(229, 1, 'e8aec0de15fe055dc08011146368cb6301f8c4b829de8710a0bb09099f9b5ce7', '2026-09-29 12:23:32', '2026-09-29 13:23:32'),
(230, 1, '7923f9d64d815c76dd5ed445681e803c6404a6651c509ca7bc5b02d0ce18a3c8', '2026-09-30 13:14:03', '2026-09-30 14:14:03'),
(231, 1, 'b8d348650e950914eb9bfe98a1c381158eadb4712e7375f16d24698c6f4be0bc', '2026-09-30 13:15:48', '2026-09-30 14:15:48'),
(232, 1, '2314fa3236ba4a0b610850330f46be72cddd8aa80c34ae04cbafc11614cec6ff', '2026-09-30 13:15:54', '2026-09-30 14:15:54'),
(233, 1, '32877a273256305596894046c40ceeebced946b3d6d62a775b9df9ea2380d065', '2026-09-30 13:20:18', '2026-09-30 14:20:18'),
(234, 1, '2686df4c2836951d2221879bd49111bbfa7e919c378a07d8a7400d36782a45ef', '2026-09-30 13:22:38', '2026-09-30 14:22:38'),
(235, 1, '712a6a696d687ba4bd352394c1f1fb03f3cf896f5ed19ed77559ecc0357980ef', '2026-09-30 13:39:21', '2026-09-30 14:39:21'),
(236, 1, '9d1180badea1b4756fcbc80a501794b72170bd5311ad61c566066512082d09ae', '2026-09-30 13:49:47', '2026-09-30 14:49:47'),
(237, 1, '58ab4b7597246230896fb0d526fc515b19c8ff141111499a3f61e7eb41838e5c', '2026-10-01 08:16:27', '2026-10-01 09:16:27'),
(238, 1, 'da9e6e8d673e944707b433fcbe6dd69635551bfcfc6dc103eaa2e5fb6326a014', '2026-10-01 09:05:09', '2026-10-01 10:05:09'),
(239, 1, '28cba0a1c01ed26d1a95f1941008ac9484ce9f07396f5a054f1f3448dbff9370', '2026-10-01 09:05:12', '2026-10-01 10:05:12'),
(240, 1, '6bb3ae313e2ff776b645dee94941b5dd649b495fc3b4a2f018d10b07ddd17d61', '2026-10-01 11:11:26', '2026-10-01 12:11:26'),
(241, 1, 'b6170cf829042c6384a6e64345ba934a7a1b44f2957ee59fc94831559a81aacc', '2026-10-01 13:04:29', '2026-10-01 14:04:29'),
(242, 1, '3d660cc21eb1a01ce6ff9ee094771c67ccbb3f7ce1f5f1cc45212c69e97dc062', '2026-10-01 13:27:04', '2026-10-01 14:27:04'),
(243, 1, 'd9a69cd608fd94f1deb10004ddc7e08d541e7fb45da82edf67f441ca9b883eca', '2026-10-01 14:15:14', '2026-10-01 15:15:14'),
(244, 1, '9647ed19254f1ff165154312991409d930eb121ecc724d057b77c64677163305', '2026-10-01 14:17:27', '2026-10-01 15:17:27'),
(245, 1, '3779d139d76e0310ee1529b10b0f2be5387878fcb361152f93324b6b746bdb06', '2026-10-01 14:45:38', '2026-10-01 15:45:38'),
(246, 1, 'e3ba90b9bac007b8000d1fc725b90f81bdb90839122f481cd2a173074c7d1724', '2026-10-01 15:23:04', '2026-10-01 16:23:04'),
(247, 1, '2329d1945f25e4663639d3ef633b47e1e7b8695a15723e782264094d4c76d4e7', '2026-10-04 11:59:45', '2026-10-04 12:59:45'),
(248, 1, 'f231bf323794f1b72e2f2f50dfcbccebbedd6c0758592b5a9c578d256e0400f5', '2026-10-05 12:30:02', '2026-10-05 13:30:02'),
(249, 1, '6e1bb95ffde869a398dabc71d080201e06693e788f0ca9691a1577f4f12e5d9a', '2026-10-05 12:30:40', '2026-10-05 13:30:40'),
(250, 1, '2f5581a76f0b28d9153095745bc953888696c58f33ea4cd5a2a50d5a1552d3e8', '2026-10-06 03:44:13', '2026-10-06 04:44:13'),
(251, 1, 'efc0095c13a070803ba0f885cef49f29683e48bffaf4e6b7754f7d68d20012b0', '2026-10-06 03:45:03', '2026-10-06 04:45:03'),
(252, 1, '1c2af3e4a18cc4f6ea584760017f1d6e86b416d23650c05235a76461df01e1f6', '2026-10-06 03:45:05', '2026-10-06 04:45:05'),
(253, 1, '8133ff0a8c187257a663ad5448cf7bc7fdd7a1b96e8c3d29fa92dc4e429553ca', '2026-10-06 05:08:30', '2026-10-06 06:08:30'),
(254, 1, '9453eba437a5458d798716c6892c3a63aaa310a8851e3a70339627091138c91b', '2026-10-06 07:39:47', '2026-10-06 08:39:47'),
(255, 1, '9bb4c7c4e85843db80c63805d43fa192e10addb1f75d805bca8d70a7b3a6a13f', '2026-10-06 08:44:10', '2026-10-06 09:44:10'),
(256, 1, '8fd3882051a0b0b0ef69636f5a0fb7a2c4acd9f8163dadab6b241c7c027b4524', '2026-10-06 13:26:32', '2026-10-06 14:26:32'),
(257, 1, 'f4b311dd2023d459a5741329ec83b275947f71013e4469dcc42d36aee8fdb48e', '2026-10-06 15:44:12', '2026-10-06 16:44:12'),
(258, 1, 'bef6fc623ffd1ff4b0ed41e372676e8e958d84ed86ec5fbe8e6a279465de6079', '2026-10-06 15:48:44', '2026-10-06 16:48:44'),
(259, 1, '71c179495893f332757752a06904cd43d031b9f95050d16d809e5c68db99bb2b', '2026-10-06 15:52:54', '2026-10-06 16:52:54'),
(260, 1, '80bd4c2983f846423689cf7e6cd9536c7a59c051cafbb6028711b7d73bd46a3f', '2026-10-06 15:53:32', '2026-10-06 16:53:32'),
(261, 1, 'aa0be658fa617ffa4007f586ddd778315d0c31099e46c649c1774066395b2d47', '2026-10-06 15:54:47', '2026-10-06 16:54:47'),
(262, 1, 'c80773f2dd65f599d22b3016fd0cef019621dd96abd3d28f825c0974de1a38bc', '2026-10-06 15:55:09', '2026-10-06 16:55:09'),
(263, 1, '48927cadedb5ceb33378949d726e6db4601dae12775ccf193b409b404d7262da', '2026-10-06 15:55:10', '2026-10-06 16:55:10'),
(264, 1, 'ecacfc0fc7dbf12fd968d478d0b47e08260e610187682822ebba1cc8849dd2f3', '2026-10-06 15:55:21', '2026-10-06 16:55:21'),
(265, 1, 'e2b5c08b91e71c51a0b749b9376cd4b516fdbf48ed02de357f56394f011289d3', '2026-10-06 15:55:35', '2026-10-06 16:55:35'),
(266, 1, '12df4b6b35176e68f7360a0413c9b2c1519ceeb2f9efd81575e54cabdf0c245a', '2026-10-06 15:56:39', '2026-10-06 16:56:39'),
(267, 1, '1dc398503ca2aaf114edc0669b30e30841e1e1b904779a95c02ef11152f37d9a', '2026-10-06 15:58:25', '2026-10-06 16:58:25'),
(268, 1, 'a2c467d2c6892778e61541286c20a38bc27d6d80ed2f4fa137cc33a862e3b677', '2026-10-06 16:01:25', '2026-10-06 17:01:25'),
(269, 1, 'f904739972488d63edddc313d5cafc1b659d3dcd6701b37382ef798731908cf3', '2026-10-06 16:10:18', '2026-10-06 17:10:18'),
(270, 1, '6d419b00f68ef32cdbba96a3b4ea29088f93d8d781d303ed35f4e35097a6cdcd', '2026-10-07 05:58:51', '2026-10-07 06:58:51'),
(271, 1, 'ec8919802b0abe0589a7e072ec89318561c76fd49b68bd177cf745d46b7c67be', '2026-10-07 05:59:07', '2026-10-07 06:59:07'),
(272, 1, '620c5d0b471e9034baef8f0a47c8e5ddf053c3900e6876810c709de3273101cd', '2026-10-07 06:15:31', '2026-10-07 07:15:31'),
(273, 1, '1c1c1d42df90ae7b991e27204f41cbdde6f1a90d68f559c924e6d66002a6856f', '2026-10-07 06:27:39', '2026-10-07 07:27:39'),
(274, 1, 'd2bfe2dcb63e13f2dbf3287631e065303b34f3bd7c29a8fa2958158fdf8e4a65', '2026-10-07 06:28:17', '2026-10-07 07:28:17'),
(275, 1, 'e91dd08909e208f076e9442daaea6f5e5edc4122dd48bd714bf4637837203beb', '2026-10-07 06:28:31', '2026-10-07 07:28:31'),
(276, 1, '53dec8548fb348c84bc5ce1496c9453d2d3fc6a20315cfb3eb8be8885f757865', '2026-10-07 06:28:35', '2026-10-07 07:28:35'),
(277, 1, '39c474b90eaa144312b9d467bd7188a9f1b8c7d540e9a8184f147311430e223e', '2026-10-07 06:28:44', '2026-10-07 07:28:44'),
(278, 1, 'd54d8960591b0b3d59d74106230fa19c282205fca02f0977954477ed472196a2', '2026-10-07 06:29:32', '2026-10-07 07:29:32'),
(279, 1, '6961ee1ea8689f10a3aab5d57abf458f163b84615550a551aecfc8f92a22bde5', '2026-10-07 06:41:03', '2026-10-07 07:41:03'),
(280, 1, '9b0c96214a51bb66a458b1ee104402d2009c51e6ffdf942212329be5afd44080', '2026-10-07 06:41:55', '2026-10-07 07:41:55'),
(281, 1, '65ff541db09c34cc2d04a6cdd98d149d4ebc17e9e5a09af6d7c98ce89dd2a054', '2026-10-07 06:44:12', '2026-10-07 07:44:12'),
(282, 1, 'fd68349f44b7bbbc0ef9452ce28ad3e78912afd2a3e5210772011d8a55290ff4', '2026-10-07 06:51:16', '2026-10-07 07:51:16'),
(283, 1, '7e4fe91b591a49b6eda892e97f59cd731d7bb4ad73d244ec4d892a160574c491', '2026-10-07 06:52:44', '2026-10-07 07:52:44'),
(284, 1, '1dd2addcc0444c111dfd34f5c87fc75e68739b29b7eb5b825cb1e2f3c5148f07', '2026-10-07 06:52:47', '2026-10-07 07:52:47'),
(285, 1, 'c5ec1d34cea5cee133e17f53d1be7cb63d95f99e81b33037d526241a17d08ec5', '2026-10-07 06:53:34', '2026-10-07 07:53:34'),
(286, 1, 'f9ac6e978cb0ec8ae299a24b053bf43113073a8b5cdf7f01fa54355d164441d3', '2026-10-07 06:54:49', '2026-10-07 07:54:49'),
(287, 1, '217af596ffbee942a965576c7d33d043ef4ae07711e0cee8ed8366504430271d', '2026-10-07 07:15:18', '2026-10-07 08:15:18'),
(288, 1, 'f3e4b123e522871bd3e0e82bf40e428cdc37aa5ced05074409a93bfd178bc40c', '2026-10-07 07:15:24', '2026-10-07 08:15:24'),
(289, 1, '58778cf59310959e8e429842dba6e44a75bfb407c3553fb8878b072813bc2afa', '2026-10-07 07:31:03', '2026-10-07 08:31:03'),
(290, 1, 'e25c857971b8b1dde0e0770e09d5ed7b96cbdcffd552e798038354310ed75878', '2026-10-07 12:34:24', '2026-10-07 13:34:24'),
(291, 1, '2fab1f95ffe0902e1fc957f717c0ca2b5eee044f0f3121302ba8141fa34433bb', '2026-10-07 12:34:26', '2026-10-07 13:34:26'),
(292, 1, 'dd7b269313629c0cb3eb244f7725399458847ee62b4922aba4294e61da97a967', '2026-10-07 12:34:33', '2026-10-07 13:34:33'),
(293, 1, '0a46ed6281b5fc1711be304fa363cd7e1fc35a7c52db1469933ab912378383e7', '2026-10-07 12:37:02', '2026-10-07 13:37:02'),
(294, 1, 'f50b897f6f918d8466be2588764331f5d3ee7b05d2b3d0b9aa1945893768d55b', '2026-10-07 12:53:45', '2026-10-07 13:53:45'),
(295, 1, 'c8ffe88a70f65fdbd7b920be342e9c32079ae8acf4a8b73a6b0f94d53da910c8', '2026-10-07 12:58:56', '2026-10-07 13:58:56'),
(296, 1, 'cc484abf60a2296ffca61c76053989b519d9119ed15f21678d9bed23f042d878', '2026-10-08 03:04:49', '2026-10-08 04:04:49'),
(297, 1, 'a56e0017161fd2b5cc2e1dc0fd6bf9d9ea918bfb213652dfe8625fa8e57ad591', '2026-10-08 03:05:11', '2026-10-08 04:05:11'),
(298, 1, 'd8a8d67c5d737da6b31397ac816437026b14f5a249c96c876e1238aa0ad956a7', '2026-10-08 03:06:24', '2026-10-08 04:06:24'),
(299, 1, 'eef068e944732e7eab15fda2f5f612ab8561a96a033b3379323f7ff5c8d8f037', '2026-10-08 03:06:33', '2026-10-08 04:06:33'),
(300, 1, 'eceea837b3da9d342fd194f3e988ff62b270f562043ecca693849b79f94576c1', '2026-10-08 03:08:14', '2026-10-08 04:08:14'),
(301, 1, '9e280745f1e887279a36f8b98dd502d6276f56c07b1d7930d39bfa3f2c6742f2', '2026-10-08 03:08:30', '2026-10-08 04:08:30'),
(302, 1, '0b0139b95093fd7555030e74b91b8a4e801fc4556be62faf57d54a6676916d8e', '2026-10-08 03:11:31', '2026-10-08 04:11:31'),
(303, 1, 'c426350de07ac881c1618b67c36fb634bfae3f15872eb271ce9f5e41cdcdea83', '2026-10-08 03:11:33', '2026-10-08 04:11:33'),
(304, 1, 'a3f9200c255d785f2a662bbd9ac9cfebcfc2006943719ae5bd7cecdf080f45c5', '2026-10-08 05:04:18', '2026-10-08 06:04:18'),
(305, 1, 'fb7e8a0d613ff035987ea03f5ac81b99cf777d4777b5c102452e91d40195ca1f', '2026-10-08 05:05:41', '2026-10-08 06:05:41'),
(306, 1, 'a2f432bedc33efcbd70e734e1a158446ce559baf34fbbcf1ad03d5b904fb0b0b', '2026-10-08 05:05:44', '2026-10-08 06:05:44'),
(307, 1, '42218ad583c59a3645639d7defb1f79647c2e3195a531aa70fdcd2dc7946665f', '2026-10-08 05:05:46', '2026-10-08 06:05:46'),
(308, 1, '8789fcbc242da7b365d682e5136e01f276d8715a311f176c900d5e64f043029b', '2026-10-08 05:10:02', '2026-10-08 06:10:02'),
(309, 1, 'c7f2844c9e1a8ed45727c35243dbc42831c295d790ff54768c95c298f994fdf7', '2026-10-08 14:04:06', '2026-10-08 15:04:06'),
(310, 1, '1bc21973c1a2948038a470c2b7bc18006c6efe398a85596b914ac14b11a848cc', '2026-10-08 14:04:38', '2026-10-08 15:04:38'),
(311, 1, '02216b2045f2729df2fd034d6ee0946c0fc688721011426894948bc6167ff22a', '2026-10-08 14:04:54', '2026-10-08 15:04:54'),
(312, 1, '4204a3e7ce0f62fa4a262d19dc0a86fbe512b027b9df903fe0622321cbe1b760', '2026-10-08 14:05:15', '2026-10-08 15:05:15'),
(313, 1, '28d7f667ffd8d9149146516930237c9c3e9391d6969b95e8528408e66e3ac590', '2026-10-08 14:05:18', '2026-10-08 15:05:18'),
(314, 1, '0dc5272f7d20063602015f0374888e67a276cd9ab952671fe1d6c80aec1c709e', '2026-10-08 14:05:52', '2026-10-08 15:05:52'),
(315, 1, '6631ea7bdf1a03ceab554389c84c12215e44a0e76acf2e6e0bbef6275ea1b5b8', '2026-10-08 14:08:00', '2026-10-08 15:08:00'),
(316, 1, '875940a33c57ecb8abc5cf9f5332e48f1e27c8f48c589d9f6d96f995c9c5dc51', '2026-10-08 14:08:32', '2026-10-08 15:08:32'),
(317, 1, '1ffba877fb81a78016d372f499fd8a813317a46537ca5a0086265fc4a33fb8a9', '2026-10-08 14:08:39', '2026-10-08 15:08:39'),
(318, 1, '952412818a73082c435b30e21ee90b7471c6e2b6a6c1f0abf4ec4bcd762590ee', '2026-10-08 14:08:50', '2026-10-08 15:08:50'),
(319, 1, '69ee9ac4352b266481873184079e8a1dd5539a6666c594f41208485120eb9f7d', '2026-10-08 14:18:10', '2026-10-08 15:18:10'),
(320, 1, '8930b01651e94d6910bfe388a3fb2b6cfab9088a67d1b45a91c0ca23696d02a5', '2026-10-08 14:29:12', '2026-10-08 15:29:12'),
(321, 1, '3e9d0eaf4ada01d795d192066b4396a3035b2d238271859e1ed5c7b30d7d4999', '2026-10-09 10:27:11', '2026-10-09 11:27:11'),
(322, 1, 'd9832d50a04d982e55f10e892168ce032cf47e59a627da55b83ab2a703f930f4', '2026-10-09 15:06:26', '2026-10-09 16:06:26'),
(323, 1, 'a0069943d6723178ab02fe87d79f5b606d366b6740fadb59b1e7632cd48e6005', '2026-10-09 15:55:39', '2026-10-09 16:55:39'),
(324, 1, '3ed480642ed41d1cc71db0e707b6083cf16eb9187a4b4867f7ece9ff1395436a', '2026-10-09 15:55:55', '2026-10-09 16:55:55'),
(325, 1, '20c7d387de15bff202f788fec362b30721f0e9d94c219cc0f38026edb322ce80', '2026-10-10 03:54:27', '2026-10-10 04:54:27'),
(326, 1, 'ea6ccbcf1c851a0f2aa5d20c6abd3293a4b9ce70b4cc1b2ea1135654988e46ba', '2026-10-10 03:56:43', '2026-10-10 04:56:43'),
(327, 1, '9fda0fd488e208cf5764986927f8d4d31b73d1016633af3412a6e3a92c10bed2', '2026-10-10 05:15:20', '2026-10-10 06:15:20'),
(328, 1, 'a00f19e6534fc7bfdef170706531366f08799da3ec20642bb9673934a98f7917', '2026-10-10 07:38:48', '2026-10-10 08:38:48'),
(329, 1, 'cf7df54c4939007b50dafc7ea69966c1ef12d5d7e4b30171729835f35dbdda64', '2026-10-10 07:38:53', '2026-10-10 08:38:53'),
(330, 1, '7432b01ec8c69294735f169e5458df2f73e4da9b1d5260ca088eb9e080dc65b0', '2026-10-10 07:43:41', '2026-10-10 08:43:41'),
(331, 1, '5a0e713763176c271de026ef50c072fc396717ea5c2bc915695f3657df1b495e', '2026-10-10 07:43:43', '2026-10-10 08:43:43'),
(332, 1, '1512743851556d7185d5b6b7efd4c908f8d150d8bca0b3719a96b246d4732dc8', '2026-10-10 12:48:09', '2026-10-10 13:48:09'),
(333, 1, 'fc44566bba2051e73a73e63b87447cf4aa5b2a7918eb8a3bc8a4bfee10320863', '2026-10-13 05:53:06', '2026-10-13 06:53:06'),
(334, 1, 'a56de77f3c03dc62157afc3937974a764f0e342fdf3cd3d2f22597cf8b725a6c', '2026-10-13 05:53:45', '2026-10-13 06:53:45'),
(335, 1, 'd4ab3965e2157aed4fcaa76344e86e379c1288891f2c5e1aa0f5ce724de58aa8', '2026-10-13 06:19:29', '2026-10-13 07:19:29'),
(336, 1, '17aa388190d7ebfadba591a6000bdab84f8012b4501c52b69b60fe7c6913320a', '2026-10-13 15:46:55', '2026-10-13 16:46:54'),
(337, 1, 'b1ce8cdfa0739a35ef851e9fd9d7d6471703c2f94ae3e58395a24dc893ef025d', '2026-10-13 15:49:01', '2026-10-13 16:49:01'),
(338, 1, '725bfd50b72b86b229bede5d4618ce548a69feb9664288c44f4a31e487ac0934', '2026-10-14 03:30:49', '2026-10-14 04:30:49'),
(339, 1, '8d8f92e45046884295f5289c6331af5d3eacfad8a934f26c840d3aab9b74c6ef', '2026-10-14 13:05:48', '2026-10-14 14:05:48'),
(340, 1, 'a5e9da0b8cd2e4f4b84b058b1bde5c6fd1a5abc1a4a93d083ea7989381771f9d', '2026-10-14 13:27:22', '2026-10-14 14:27:22'),
(341, 1, 'ab22d5d2964580956e04e2c25abc80a6514bd542a20d43770069e0d360228278', '2026-10-14 13:32:00', '2026-10-14 14:32:00'),
(342, 1, '769a6e43b0537216ce02fda482ccc01c226cda7b06971b644f6650fdca08f4f9', '2026-10-15 04:05:25', '2026-10-15 05:05:25'),
(343, 1, '773076d51e020259e8d9cba3289ab7910532e16f770bbf23b1a05a78cbf38b44', '2026-10-15 09:02:08', '2026-10-15 10:02:08'),
(344, 1, 'fa57f1846f620e016d78f6291a5ef1970c4f6eb2710da07740b918bf9ac73493', '2026-10-15 11:41:18', '2026-10-15 12:41:18');

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
  `archived_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`dept_id`, `dept_name`, `slug`, `department_type`, `description`, `faculty_head`, `media_id`, `dean_name`, `dean_title`, `dean_bio`, `dean_media_id`, `dean_phone`, `dean_email`, `dean_office`, `dean_hours`, `theme_color`, `about_text`, `hero_text`, `facebook_url`, `office_label`, `archived_at`) VALUES
(1, 'College of Arts and Sciences, and Education', 'cased', 'college', 'College of Arts and Sciences, and Education', NULL, 205, 'Annaliza V. Maris, LPT, MA', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 149, '(045) 900-4473 Loc. 120', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 202', 'Monday - Friday8:00 AM - 4:00 PM', '#135E95', 'The college provides a wide range of academic programs and support services designed to foster the holistic development of its students.\r\n\r\nThe college offers: \r\nSpecialized Courses -  enhances professional competence\r\nGeneral Education Subjects - Promotes Intellectual growth across disciplines\r\nProfessional Education courses - strengthen pedagogical knowledge and teaching Skills\r\n\r\nThrough these learning opportunities, the College prepares future educators and professionals to become competent, innovative, and socially responsible individuals who can effectively respond to the needs of their learners, communities, and professions.', NULL, 'https://www.facebook.com/profile.php?id=61553299254607', 'CASED Office', NULL),
(2, 'College of Business Administration', 'cba', 'college', NULL, NULL, 67, 'Rowena R. Sampang, LPT, MBA', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 81, '(045) 900-4473 Loc. 106', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 203', 'Monday - Friday8:00 AM - 4:00 PM', '#FED018', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', NULL, 'https://facebook.com/profile.php?id=61557934893109', 'CBA Office', NULL),
(3, 'College of Engineering', 'coe', 'college', NULL, NULL, 68, 'Engr. Ma. Lydia P. Sacdalan, ME-1, MMEP, ENP, SO-3', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 82, '(045) 900-4473 Loc. 125', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 205', 'Monday - Friday8:00 AM - 4:00 PM', '#FD541B', NULL, NULL, NULL, NULL, NULL),
(4, 'College of Medical Laboratory Science and Pharmacy', 'camp', 'college', NULL, NULL, 69, 'Juan Paolo S. Capati, RMT, RPh', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 83, '(045) 900-4473 Loc. 137', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 206', 'Monday - Friday8:00 AM - 4:00 PM', '#1F5E2C', NULL, NULL, NULL, NULL, NULL),
(5, 'College of Accountancy', 'coa', 'college', NULL, NULL, 70, 'Getor N. Isip, CPA, MBA', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 84, '(045) 900-4473 Loc. 107', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 210', 'Monday - Friday8:00 AM - 4:00 PM', '#FED018', NULL, NULL, NULL, NULL, NULL),
(6, 'College of Nursing', 'conursing', 'college', NULL, NULL, 71, 'Wilhelmina G. Bernardo, MAN, RN, RM', 'Dean', 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.', 85, '(045) 900-4473 Loc. 120', 'info@gnc.edu.ph', 'Limlingan Hall, Floor 2, Room 208', 'Monday - Friday8:00 AM - 4:00 PM', '#FFBDA7', NULL, NULL, 'https://www.facebook.com/gnc.bsnursing', NULL, NULL);

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
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `category_id`, `program`, `user_id`, `title`, `location`, `start_date`, `end_date`, `media_id`, `status`, `created_at`, `updated_at`) VALUES
(3, 3, 'ALL', 1, 'test', 'test', '2026-06-30 09:00:00', NULL, NULL, 'published', '2026-06-30 13:57:25', '2026-10-07 06:28:44'),
(7, 2, 'ALL', 1, 'test', 'test', '2026-07-03 11:30:00', '2026-07-03 11:30:00', NULL, 'published', '2026-07-01 03:31:13', '2026-07-01 03:31:13'),
(8, 2, 'ALL', 1, 'test', 'test', '2026-07-17 09:00:00', NULL, NULL, 'published', '2026-07-16 14:28:32', '2026-07-16 14:28:32'),
(9, 2, 'ALL', 1, 'test 2', 'test 2', '2026-07-18 00:17:00', '2026-07-20 00:17:00', NULL, 'published', '2026-07-16 16:17:35', '2026-07-16 16:39:37'),
(10, 1, 'BEEd', 1, 'test', 'test', '2026-08-30 15:19:00', '2026-08-30 15:19:00', NULL, 'published', '2026-08-30 07:20:10', '2026-08-30 08:02:04'),
(11, 2, 'BEEd', 1, 'Test', 'GNC Court', '2026-09-29 20:23:00', NULL, NULL, 'published', '2026-09-29 12:23:32', '2026-09-29 12:23:32'),
(12, 2, 'ALL', 1, 'Tes', 'test', '2026-09-30 09:00:00', '2026-10-01 21:15:00', NULL, 'published', '2026-09-30 13:15:47', '2026-09-30 13:15:54'),
(13, 2, 'ALL', 1, 'Testing', 'Testing', '2026-10-24 00:00:00', '2026-10-30 00:00:00', NULL, 'published', '2026-10-08 03:06:24', '2026-10-08 03:08:30'),
(14, 2, 'ALL', 1, 'testing', 'test', '2026-11-04 00:00:00', '2026-11-05 00:00:00', NULL, 'published', '2026-10-08 14:04:38', '2026-10-08 14:04:38'),
(15, 2, 'ALL', 1, 'Halloween', 'test', '2026-10-31 00:00:00', '2026-10-31 00:00:00', NULL, 'published', '2026-10-08 14:05:15', '2026-10-08 14:05:15'),
(16, 1, 'ALL', 1, 'TEst ulit', 'test', '2026-11-20 00:00:00', '2026-11-27 00:00:00', NULL, 'published', '2026-10-08 14:05:52', '2026-10-08 14:05:52'),
(17, 3, 'ALL', 1, 'HELLO', 'test', '2026-10-08 00:00:00', '2026-10-08 00:00:00', NULL, 'published', '2026-10-08 14:08:32', '2026-10-08 14:18:09'),
(18, 3, 'ALL', 1, 'TEST', 'test', '2026-10-09 00:00:00', '2026-10-09 00:00:00', NULL, 'published', '2026-10-08 14:29:12', '2026-10-08 14:29:12');

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
-- Table structure for table `fb_api_call_log`
--

CREATE TABLE `fb_api_call_log` (
  `id` int NOT NULL,
  `called_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fb_api_call_log`
--

INSERT INTO `fb_api_call_log` (`id`, `called_at`) VALUES
(404, '2026-10-15 11:41:12');

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

--
-- Dumping data for table `fb_posts_cache`
--

INSERT INTO `fb_posts_cache` (`fb_post_id`, `message`, `image_media_id`, `category_id`, `category_locked`, `permalink_url`, `is_pinned`, `pinned_at`, `created_time`, `fetched_at`) VALUES
('403969556396264_1014317058016314', '📢 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬\n\n𝗖𝗲𝗿𝘁𝗶𝗳𝗶𝗰𝗮𝘁𝗲 𝗼𝗳 𝗥𝗲𝗴𝗶𝘀𝘁𝗿𝗮𝘁𝗶𝗼𝗻 (𝗖𝗢𝗥) 𝗥𝗲𝗹𝗲𝗮𝘀𝗶𝗻𝗴 𝗦𝗰𝗵𝗲𝗱𝘂𝗹𝗲\n\n𝗙𝗼𝗿 𝗙𝗶𝗿𝘀𝘁-𝗬𝗲𝗮𝗿 𝗦𝘁𝘂𝗱𝗲𝗻𝘁𝘀\n- Maaaring i-claim ang inyong Certificate of Registration (COR) sa 𝗗𝗲𝗮𝗻’𝘀 𝗢𝗳𝗳𝗶𝗰𝗲 mula 8:00 AM hanggang 5:00 PM, July 20–25, 2026.\n\n𝗙𝗼𝗿 𝗥𝗲𝘁𝘂𝗿𝗻𝗶𝗻𝗴 (𝗢𝗹𝗱) 𝗦𝘁𝘂𝗱𝗲𝗻𝘁𝘀\n- Maaaring i-claim ang inyong Certificate of Registration (COR) sa 𝗥𝗲𝗴𝗶𝘀𝘁𝗿𝗮𝗿’𝘀 𝗢𝗳𝗳𝗶𝗰𝗲 sa susunod na working day pagkatapos mong mag-enroll. \n\nPlease claim your COR within the designated schedule. Thank you!', 10, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1014317058016314', 0, NULL, '2026-07-07 11:40:33', '2026-07-13 14:37:54'),
('403969556396264_1015656651215688', '🎉𝗚𝗢𝗢𝗗 𝗡𝗘𝗪𝗦, 𝗘𝗙𝗔𝗣 𝗕𝗔𝗕𝗜𝗘𝗦 𝗔𝗧 𝗙𝗨𝗧𝗨𝗥𝗘 𝗘𝗙𝗔𝗣 𝗕𝗔𝗕𝗜𝗘𝗦! 🎉\n\nMay good news tayo! 💙\n\n𝗡𝗮𝗴-𝗲𝘅𝘁𝗲𝗻𝗱 𝗮𝗻𝗴 𝗖𝗮𝗽𝗶𝘁𝗼𝗹 ng renewal period para sa mga hindi pa nakapag-renew ng EFAP for 2nd Semester A.Y. 2025–2026, at pati na rin sa mga bagong EFAP scholars.\n\n🗓️ Renewal Schedule:\n𝗝𝘂𝗹𝘆 𝟭𝟬, 𝟮𝟬𝟮𝟲 – 𝗝𝘂𝗹𝘆 𝟭𝟯, 𝟮𝟬𝟮𝟲\n\n📌 𝗣𝗮𝗿𝗮 𝘀𝗮 𝗺𝗴𝗮 𝗺𝗮𝗴𝗿𝗲-𝗿𝗲𝗻𝗲𝘄 (𝟮𝗻𝗱 𝗦𝗲𝗺𝗲𝘀𝘁𝗲𝗿 𝗔.𝗬. 𝟮𝟬𝟮𝟱–𝟮𝟬𝟮𝟲)\n𝗗𝗮𝗹𝗵𝗶𝗻 𝗮𝗻𝗴 𝗺𝗴𝗮 𝘀𝘂𝗺𝘂𝘀𝘂𝗻𝗼𝗱:\n✅ Xerox ng COR (2nd Semester A.Y. 2025–2026)\n✅ Xerox ng Grades (1st Semester A.Y. 2025–2026)\n✅ Xerox ng School ID (A.Y. 2025–2026)\n✅ ₱100 pesos @ the cashier for certification – magbayad sa Cashier at resibo na mismo ang ipapasa sa Window 3\n\n📌 𝗣𝗮𝗿𝗮 𝘀𝗮 𝗺𝗴𝗮 𝗡𝗘𝗪 𝗘𝗙𝗔𝗣 𝗦𝗰𝗵𝗼𝗹𝗮𝗿𝘀\n𝗗𝗮𝗹𝗵𝗶𝗻 𝗮𝗻𝗴 𝗺𝗴𝗮 𝘀𝘂𝗺𝘂𝘀𝘂𝗻𝗼𝗱:\n✅ COR (2nd Semester A.Y. 2025–2026)\n✅ Xerox ng Grades (1st Semester A.Y. 2025–2026)\n✅ Xerox ng School ID (A.Y. 2025–2026)\n✅ Barangay Indigency\n✅ 1 pc. 2x2 Picture\n✅ Xerox ng Official Receipt ng tuition fee (any receipt ng 2nd Semester A.Y. 2025–2026)\n✅ ₱100 @ the cashier for certification - magbayad sa Cashier at resibo na mismo ang ipapasa sa Window 3\n\n📢 𝗥𝗲𝗺𝗶𝗻𝗱𝗲𝗿: 𝗦𝗶𝗴𝘂𝗿𝗮𝗱𝘂𝗵𝗶𝗻𝗴 𝗸𝘂𝗺𝗽𝗹𝗲𝘁𝗼 𝗮𝗻𝗴 𝗶𝗻𝘆𝗼𝗻𝗴 𝗿𝗲𝗾𝘂𝗶𝗿𝗲𝗺𝗲𝗻𝘁𝘀 𝗯𝗮𝗴𝗼 𝗺𝗮𝗴-𝘀𝘂𝗯𝗺𝗶𝘁 𝘂𝗽𝗮𝗻𝗴 𝗺𝗮𝗶𝘄𝗮𝘀𝗮𝗻 𝗮𝗻𝗴 𝗮𝗯𝗮𝗹𝗮.\n\n𝙄𝙋𝘼𝙎𝘼 𝙎𝘼 𝙒𝙄𝙉𝘿𝙊𝙒 3, 𝙆𝘼𝙔 𝙈𝙎. 𝘿𝘼𝙉𝙄!\n\nThank you, EFAP babies! ✨', 9, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1015656651215688', 0, NULL, '2026-07-09 01:08:16', '2026-07-13 14:37:54'),
('403969556396264_1015694684545218', '📢 𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧 | 𝗧𝗗𝗣 & 𝗧𝗘𝗦 𝗚𝗥𝗔𝗡𝗧𝗘𝗘𝗦\n\nSa mga hindi pa nakakapag-submit ng inyong LandBank Account Number, 𝗸𝘂𝗻𝗴 𝗵𝗶𝗻𝗱𝗶 𝗽𝗮 𝗸𝗮𝘆𝗼 𝗻𝗮𝗸𝗮𝗸𝗮𝗽𝗮𝗴-𝗼𝗽𝗲𝗻 𝗻𝗴 𝗟𝗮𝗻𝗱𝗕𝗮𝗻𝗸 𝗮𝗰𝗰𝗼𝘂𝗻𝘁, 𝗺𝗮𝗮𝗮𝗿𝗶 𝗸𝗮𝘆𝗼𝗻𝗴 𝗽𝘂𝗺𝘂𝗻𝘁𝗮 𝘀𝗮 𝗪𝗶𝗻𝗱𝗼𝘄 𝟯 𝗽𝗮𝗿𝗮 𝗺𝗮𝗸𝘂𝗵𝗮 𝗮𝗻𝗴 𝗶𝗻𝘆𝗼𝗻𝗴 𝗔𝘄𝗮𝗿𝗱 𝗡𝘂𝗺𝗯𝗲𝗿, na kailangan sa pag-open ng account.\n\nPlease process your account 𝗮𝘀 𝘀𝗼𝗼𝗻 𝗮𝘀 𝗽𝗼𝘀𝘀𝗶𝗯𝗹𝗲 para hindi ma-delay ang processing ng inyong scholarship benefits.\n\nAng list na ito ay ang mga pangalan na hindi pa nakakapag-submit ng kanilang Landbank account number.\n\nCheck the list here: \nhttps://docs.google.com/spreadsheets/d/1xhSBuTpssKYghTDotGxpDXF7Hna5Iz-5/edit?usp=sharing&ouid=102001444818481439393&rtpof=true&sd=true\n\nThank you! ✨', 8, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1015694684545218', 0, NULL, '2026-07-09 02:22:21', '2026-07-13 14:37:54'),
('403969556396264_1015705421210811', '📢 𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧 | 𝗧𝗗𝗣 & 𝗧𝗘𝗦 𝗚𝗿𝗮𝗻𝘁𝗲𝗲𝘀 - 𝗠𝗼𝗻𝗶𝘁𝗼𝗿𝗶𝗻𝗴 𝗮𝗻𝗱 𝗢𝗿𝗶𝗲𝗻𝘁𝗮𝘁𝗶𝗼𝗻\n\nGood day, TDP and TES Grantees!\n\nPlease be informed na magkakaroon ng Monitoring and Orientation ang CHED UniFAST sa:\n\n📅 𝗗𝗮𝘁𝗲: TBA\n🕕 𝗧𝗶𝗺𝗲: TBA\n📍 𝗩𝗲𝗻𝘂𝗲: 𝗚𝘆𝗺 𝗧𝗮𝗻𝗷𝗮𝗻𝗰𝗼 𝗛𝗮𝗹𝗹\n👚: 𝗪𝗲𝗮𝗿 𝘆𝗼𝘂𝗿 𝗽𝗿𝗼𝗽𝗲𝗿 𝘀𝗰𝗵𝗼𝗼𝗹 𝘂𝗻𝗶𝗳𝗼𝗿𝗺\n\nLahat ng TDP at TES grantees na nasa attached list ay 𝗿𝗲𝗾𝘂𝗶𝗿𝗲𝗱 𝗻𝗮 𝗱𝘂𝗺𝗮𝗹𝗼.\n\n📌 𝗜𝗺𝗽𝗼𝗿𝘁𝗮𝗻𝘁 𝗥𝗲𝗺𝗶𝗻𝗱𝗲𝗿:\nPakitandaan na dapat alam ninyo ang inyong Landbank Account Number dahil isusulat ninyo ito sa inyong attendance sa araw ng monitoring at orientation.\n\n📋 Kindly check the attached list to confirm your name.\nhttps://drive.google.com/drive/folders/16Q-ivi4YZvNtRF0wonVlJBWe29iU0UtH?usp=sharing\n\nThank you. See you! ✨', 7, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1015705421210811', 0, NULL, '2026-07-09 02:41:52', '2026-07-13 14:37:54'),
('403969556396264_1018449440936409', '📢 ADVISORY ☔️\n\nIn view of the continuous rainfall and in consideration of the safety and welfare of our students, particularly those affected by flooding, GNC will implement online class modality for all levels on Monday, July 13, 2026.\n\nDespite the shift to online learning, all school offices shall remain operational during regular office hours to accommodate important transactions, enrollment, and other essential services.\n\nStudents are advised to coordinate with their respective instructors regarding their online classes and stay updated through the College’s official communication channels.\n\nPlease stay safe and take the necessary precautions.\n\nThank you and stat safe, gncians! ☔️', 13, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1018449440936409', 0, NULL, '2026-07-12 14:28:02', '2026-07-15 13:59:06'),
('403969556396264_1020419287406091', '𝗥𝗘𝗦𝗧 𝗜𝗡 𝗣𝗘𝗔𝗖𝗘 🕊️\n\nIt is with profound sadness that the GNC community mourns the passing of 𝗟𝘂𝘇 𝗗𝗲 𝗟𝗲𝗼𝗻 𝗖𝗮𝗿𝗹𝗼𝘀, our former High School Teacher, Part-Time College Teacher, High School Principal, and Assistant to the President for Academic Affairs.\n\nHer years of dedicated service, leadership, and commitment to education have left a meaningful mark on the GNC community. Her legacy will live on in the lives she touched and the institution she faithfully served.\n\nWe extend our deepest condolences and prayers to her bereaved family and loved ones during this time of sorrow.\n\nMay God\'s infinite love embrace her, and may her soul find eternal peace in His kingdom. 🕊️\n\nRest in peace Ma\'am Luz, your GNC family remembers you with gratitude and love. 🕊️', 17, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1020419287406091', 0, NULL, '2026-07-15 03:05:34', '2026-07-17 06:47:05'),
('403969556396264_1020486924065994', '📣 𝗟𝗔𝗦𝗧 𝗖𝗔𝗟𝗟 𝗙𝗢𝗥 𝗧𝗗𝗣 𝗔𝗡𝗗 𝗧𝗘𝗦 𝗚𝗥𝗔𝗡𝗧𝗘𝗘𝗦\n\nAll TDP and TES grantees 𝘄𝗵𝗼 𝗵𝗮𝘃𝗲 𝗻𝗼𝘁 𝘆𝗲𝘁 𝘀𝘂𝗯𝗺𝗶𝘁𝘁𝗲𝗱 𝘁𝗵𝗲𝗶𝗿 𝗟𝗮𝗻𝗱𝗯𝗮𝗻𝗸 𝗔𝗰𝗰𝗼𝘂𝗻𝘁 𝗡𝘂𝗺𝗯𝗲𝗿 are advised to submit the required information 𝘂𝗻𝘁𝗶𝗹 𝘁𝗼𝗺𝗼𝗿𝗿𝗼𝘄 𝗼𝗻𝗹𝘆 (July 16, 2026).\n\nPlease be informed that 𝗳𝗮𝗶𝗹𝘂𝗿𝗲 𝘁𝗼 𝗽𝗿𝗼𝘃𝗶𝗱𝗲 𝘆𝗼𝘂𝗿 𝗟𝗮𝗻𝗱𝗯𝗮𝗻𝗸 𝗔𝗰𝗰𝗼𝘂𝗻𝘁 𝗡𝘂𝗺𝗯𝗲𝗿 𝗺𝗮𝘆 𝗿𝗲𝘀𝘂𝗹𝘁 𝗶𝗻 𝘆𝗼𝘂𝗿 𝗿𝗲𝗺𝗼𝘃𝗮𝗹 𝗳𝗿𝗼𝗺 𝘁𝗵𝗲 𝗹𝗶𝘀𝘁 𝗼𝗳 𝗴𝗿𝗮𝗻𝘁𝗲𝗲𝘀.\n\nWe strongly encourage all concerned students to comply immediately to avoid any issues with your grant.\n\nPlease be guided accordingly and share this announcement with your fellow grantees.', 16, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1020486924065994', 0, NULL, '2026-07-15 05:10:18', '2026-07-17 06:47:05'),
('403969556396264_1020499394064747', '🌙✨ 𝗔 𝗡𝗘𝗪 𝗝𝗢𝗨𝗥𝗡𝗘𝗬 𝗜𝗦 𝗔𝗕𝗢𝗨𝗧 𝗧𝗢 𝗕𝗘𝗚𝗜𝗡  ⛵🌊\n\nThis year, GNC proudly presents a new celebration created to welcome a new generation of GNCians—\n\n𝗟𝗔𝗬𝗔𝗚 𝗙𝗘𝗦𝗧 𝟮𝟬𝟮𝟲: 𝗧𝘂𝗻𝗴𝗼 𝘀𝗮 𝗛𝗶𝗿𝗮𝘆𝗮 🌊✨\n𝘎𝘕𝘊\'𝘴 𝘍𝘳𝘦𝘴𝘩𝘮𝘦𝘯 𝘒𝘪𝘤𝘬𝘰𝘧𝘧 𝘊𝘦𝘭𝘦𝘣𝘳𝘢𝘵𝘪𝘰𝘯 𝘢𝘯𝘥 𝘔𝘶𝘴𝘪𝘤 𝘍𝘦𝘴𝘵𝘪𝘷𝘢𝘭\n\nNew school. New faces. New stories. And for our College Freshmen and Transferees, 𝘁𝗵𝗶𝘀 𝗶𝘀 𝘄𝗵𝗲𝗿𝗲 𝘆𝗼𝘂𝗿 𝗚𝗡𝗖 𝗷𝗼𝘂𝗿𝗻𝗲𝘆 𝗼𝗳𝗳𝗶𝗰𝗶𝗮𝗹𝗹𝘆 𝘀𝗲𝘁𝘀 𝘀𝗮𝗶𝗹.  ⛵💫\n\nOn 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟭, 𝟮𝟬𝟮𝟲, get ready for a full day of celebration, music, creativity, and unforgettable moments!\n\n☀️  𝗣𝗔𝗥𝗧 𝗜: 𝗙𝗥𝗘𝗦𝗛𝗠𝗘𝗡 𝗞𝗜𝗖𝗞𝗢𝗙𝗙 𝗖𝗘𝗟𝗘𝗕𝗥𝗔𝗧𝗜𝗢𝗡\nWe begin the journey with our 𝗪𝗲𝗹𝗰𝗼𝗺𝗲 𝗪𝗮𝗹𝗸 𝗮𝗻𝗱 𝗞𝗶𝗰𝗸𝗼𝗳𝗳 𝗖𝗲𝗹𝗲𝗯𝗿𝗮𝘁𝗶𝗼𝗻, a special morning dedicated to welcoming our College Freshmen and Transferees into the growing GNC community. 💙\n\n🌙🎶 𝗣𝗔𝗥𝗧 𝗜𝗜: 𝗟𝗔𝗬𝗔𝗚 𝗠𝗨𝗦𝗜𝗖 𝗙𝗘𝗦𝗧𝗜𝗩𝗔𝗟\nAnd when the afternoon comes... we turn up the music! 🎸🔥 𝗢𝗽𝗲𝗻 𝘁𝗼 𝗔𝗟𝗟 𝗚𝗡𝗖 𝗦𝗧𝗨𝗗𝗘𝗡𝗧𝗦, come together for an afternoon of live performances, good vibes, and a celebration made for the GNC community!\n\nBut wait... there\'s more. 👀✨\n\n🛍️ 𝗟𝗔𝗬𝗔𝗚 𝗕𝗔𝗭𝗔𝗔𝗥\n𝗖𝗮𝗹𝗹𝗶𝗻𝗴 𝗮𝗹𝗹 𝗚𝗡𝗖 𝗦𝘁𝘂𝗱𝗲𝗻𝘁 𝗘𝗻𝘁𝗿𝗲𝗽𝗿𝗲𝗻𝗲𝘂𝗿𝘀! Got a small business? Bring your products, showcase your brand, and be part of the Layag experience!\n\n🎸 𝗟𝗔𝗬𝗔𝗚 𝗠𝗨𝗦𝗜𝗖 𝗙𝗘𝗦𝗧𝗜𝗩𝗔𝗟: 𝗖𝗔𝗟𝗟 𝗙𝗢𝗥 𝗕𝗔𝗡𝗗𝗦\nCalling all bands! This is your chance to take the stage, share your sound, and perform live at Layag Fest 2026! 🎤🔥\n\n🎥 𝗟𝗔𝗬𝗔𝗚 𝗩𝗟𝗢𝗚 𝗖𝗢𝗠𝗣𝗘𝗧𝗜𝗧𝗜𝗢𝗡\nCapture the moments. Tell the story. Show us your Layag. Document the celebration through your lens and let us see Layag Fest from your point of view! 📹✨\n\n📌 Registration for the Layag Bazaar, Call for Bands, and Layag Vlog Competition is open until JULY 31, 2026.\n\n𝗦𝗰𝗮𝗻 𝘁𝗵𝗲 𝗤𝗥 𝗰𝗼𝗱𝗲 𝗼𝗿 𝗮𝗰𝗰𝗲𝘀𝘀 𝘁𝗵𝗲 𝗼𝗳𝗳𝗶𝗰𝗶𝗮𝗹 𝗿𝗲𝗴𝗶𝘀𝘁𝗿𝗮𝘁𝗶𝗼𝗻 𝗳𝗼𝗿𝗺 𝘁𝗼 𝗷𝗼𝗶𝗻!\nClick here to register: \nhttps://forms.gle/gj9cBeYFycfq9dSMA\nhttps://forms.gle/gj9cBeYFycfq9dSMA\nhttps://forms.gle/gj9cBeYFycfq9dSMA\n\nIsang bagong yugto. Isang bagong paglalakbay. Isang pangarap na sabay-sabay nating tatahakin. 🌊🌙\n\nThis August, sabay-sabay tayong tumungo sa Hiraya. ⛵✨\n\n#GNC #GuaguaNationalColleges #LayagFest2026 #TungoSaHiraya #GNCIans', 15, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1020499394064747', 0, NULL, '2026-07-15 05:32:31', '2026-07-17 06:47:05'),
('403969556396264_1020616830719670', '𝗔 𝗹𝗲𝗴𝗮𝗰𝘆 𝗼𝗳 𝘀𝗲𝗿𝘃𝗶𝗰𝗲. 𝗔 𝗹𝗶𝗳𝗲 𝗿𝗲𝗺𝗲𝗺𝗯𝗲𝗿𝗲𝗱 𝘄𝗶𝘁𝗵 𝗵𝗼𝗻𝗼𝗿 🕊️\n\nThe GNC community solemnly remembers 𝗔𝘁𝘁𝘆. 𝗥𝗶𝗰𝗮𝗿𝗱𝗼 𝗦𝗮𝗺𝗽𝗮𝗻𝗴, whose years of dedicated service as a 𝗧𝗿𝘂𝘀𝘁𝗲𝗲, 𝗖𝗼𝗿𝗽𝗼𝗿𝗮𝘁𝗲 𝗦𝗲𝗰𝗿𝗲𝘁𝗮𝗿𝘆, 𝗮𝗻𝗱 𝗳𝗼𝗿𝗺𝗲𝗿 𝗖𝗼𝗹𝗹𝗲𝗴𝗲 𝗣𝗿𝗼𝗳𝗲𝘀𝘀𝗼𝗿 have become an enduring part of our institution\'s history.\n\nBeyond the positions he held was a life marked by commitment, wisdom, and steadfast service. His contributions helped shape the community we know today, and the influence of his work will remain in the halls of GNC and in the lives of the people he encountered throughout the years.\n\nAs we mourn his passing, we also honor a life that has given much to our institution. His service will remain part of our story, and his legacy will be remembered with profound gratitude.\n\n𝗔𝘁𝘁𝘆. 𝗦𝗮𝗺𝗽𝗮𝗻𝗴\'𝘀 𝘄𝗮𝗸𝗲 𝗶𝘀 𝗰𝘂𝗿𝗿𝗲𝗻𝘁𝗹𝘆 𝗯𝗲𝗶𝗻𝗴 𝗵𝗲𝗹𝗱 𝗮𝘁 𝘁𝗵𝗲 𝗚𝗡𝗖 𝗖𝗵𝗮𝗽𝗲𝗹, where members of the community may pay their respects and offer prayers for his eternal repose.\n\nHis 𝗙𝘂𝗻𝗲𝗿𝗮𝗹 𝗠𝗮𝘀𝘀 𝘄𝗶𝗹𝗹 𝗯𝗲 𝗵𝗲𝗹𝗱 𝗼𝗻 𝗙𝗿𝗶𝗱𝗮𝘆, 𝗝𝘂𝗹𝘆 𝟭𝟳, 𝟮𝟬𝟮𝟲, 𝗮𝘁 𝟴:𝟬𝟬 𝗔𝗠 𝗮𝘁 𝘁𝗵𝗲 𝗚𝗡𝗖 𝗖𝗵𝗮𝗽𝗲𝗹. Following the Mass, his remains will be laid to rest at 𝗚𝗼𝗹𝗱𝗲𝗻 𝗚𝗮𝘁𝗲 𝗠𝗲𝗺𝗼𝗿𝗶𝗮𝗹 𝗚𝗮𝗿𝗱𝗲𝗻.\n\nThe entire GNC community extends its deepest sympathies and prayers to his bereaved family and loved ones.\n\nRest in eternal peace, Atty. Ricardo Sampang. Your life of service and the legacy you leave behind will forever be remembered. 🤍🕊️', 14, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1020616830719670', 0, NULL, '2026-07-15 08:58:49', '2026-07-17 06:47:05'),
('403969556396264_1021253683989318', 'A great experience for our GNC Men’s Volleyball Team after an exciting tune-up game against the Capital1 Solar Spikers! ✨☀️', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1021253683989318', 0, NULL, '2026-07-16 02:48:22', '2026-07-17 06:47:05'),
('403969556396264_1021286340652719', '🚨 𝗨𝗥𝗚𝗘𝗡𝗧 𝗛𝗜𝗥𝗜𝗡𝗚! 🚨\n\nThe Office of Student Affairs (OSA) is urgently looking for a dedicated 𝗢𝗳𝗳𝗶𝗰𝗲 𝗦𝘁𝗮𝗳𝗳 to join our team.\n\nIf you\'re organized, computer literate, and passionate about working with students, we\'d love to hear from you!\n\n📩 Submit your resume and application letter now to 𝗴𝗻𝗰𝗼𝘀𝗮𝟮𝟬𝟮𝟭@𝗴𝗺𝗮𝗶𝗹.𝗰𝗼𝗺\n\nApplications will be reviewed immediately, so apply today! ✨', 18, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1021286340652719', 0, NULL, '2026-07-16 03:45:23', '2026-07-17 06:47:05'),
('403969556396264_1034687982645888', 'Yoohooo! Dito daw ang tingin 🤯🤣', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1034687982645888', 0, NULL, '2026-08-01 12:16:10', '2026-08-03 13:35:13'),
('403969556396264_1035954229185930', '🌧️📚 Welcome Back, College Students!\n\nIt’s the first day of classes, and we’re excited to welcome you back to Guagua National Colleges for another semester of learning and new experiences.\n\nAs the rainy season continues, please travel safely, bring your umbrella or rain gear, and allow extra time for your commute. Your safety is always our priority.\n\nWishing everyone a productive, successful, and safe first day of classes. Welcome back, and let’s make this semester a great one! 💚💛☔', 26, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1035954229185930', 0, NULL, '2026-08-02 23:23:41', '2026-08-04 11:22:38'),
('403969556396264_1036375882477098', 'Urgent Hiring!\nWe are looking for Nursing Clinical Instructors.\n\nQualifications:\n- Must be a registered nurse with updated PRC License\n- With master\'s degree or presently enrolled in master\'s degree program\n- With relevant experience related to the position\n\nInterested applicants should submit their resumes and application letter to the College of Nursing Office', 27, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1036375882477098', 0, NULL, '2026-08-03 11:48:13', '2026-08-06 07:21:03'),
('403969556396264_1036891585758861', '🎬  𝗘𝗣𝗜𝗦𝗢𝗗𝗘 𝟮 𝗜𝗦 𝗢𝗨𝗧!\n\n𝘕𝘰𝘵 𝘦𝘷𝘦𝘳𝘺𝘰𝘯𝘦 𝘴𝘵𝘢𝘳𝘵𝘴 𝘤𝘰𝘭𝘭𝘦𝘨𝘦 𝘴𝘶𝘳𝘳𝘰𝘶𝘯𝘥𝘦𝘥 𝘣𝘺 𝘧𝘳𝘪𝘦𝘯𝘥𝘴.\n\n𝗦𝗼𝗺𝗲 𝗽𝗲𝗼𝗽𝗹𝗲 𝘀𝘁𝗮𝗿𝘁 𝘄𝗶𝘁𝗵 𝗻𝗼 𝗼𝗻𝗲.\n\n𝘔𝘢𝘺𝘣𝘦 𝘺𝘰𝘶\'𝘳𝘦 𝘢 𝘵𝘳𝘢𝘯𝘴𝘧𝘦𝘳𝘦𝘦. 𝘔𝘢𝘺𝘣𝘦 𝘺𝘰𝘶\'𝘳𝘦 𝘢𝘯 𝘪𝘯𝘵𝘳𝘰𝘷𝘦𝘳𝘵. 𝘖𝘳 𝘮𝘢𝘺𝘣𝘦 𝘺𝘰𝘶\'𝘳𝘦 𝘫𝘶𝘴𝘵 𝘴𝘰𝘮𝘦𝘰𝘯𝘦 𝘸𝘩𝘰\'𝘴 𝘮𝘰𝘳𝘦 𝘤𝘰𝘮𝘧𝘰𝘳𝘵𝘢𝘣𝘭𝘦 𝘰𝘯 𝘺𝘰𝘶𝘳 𝘰𝘸𝘯.\n\n𝘈𝘯𝘥 𝘵𝘩𝘢𝘵\'𝘴 𝘰𝘬𝘢𝘺.\n\nBecause college is also about discovering new things... new experiences, new memories, and sometimes, new people.\n\nYou don\'t have to force yourself to be the loudest person in the room. Just take that first step.\n\nMalay mo, yung isang \"hi\" mo sa LAYAG FEST 2026 might become your favorite college memory.\n\n𝗪𝗵𝗲𝘁𝗵𝗲𝗿 𝘆𝗼𝘂\'𝗿𝗲 𝗮 𝘁𝗿𝗮𝗻𝘀𝗳𝗲𝗿𝗲𝗲, 𝗮𝗻 𝗶𝗻𝘁𝗿𝗼𝘃𝗲𝗿𝘁, 𝗼𝗿 𝘀𝗶𝗺𝗽𝗹𝘆 𝘀𝗼𝗺𝗲𝗼𝗻𝗲 𝘄𝗵𝗼\'𝘀 𝘀𝘁𝗮𝗿𝘁𝗶𝗻𝗴 𝗼𝘃𝗲𝗿—\n\n𝗦𝗮𝗻𝗮𝘆 𝗺𝗮𝗴-𝗶𝘀𝗮? 𝗧𝗿𝗮𝗻𝘀𝗳𝗲𝗿𝗲𝗲? 𝗜𝗻𝘁𝗿𝗼𝘃𝗲𝗿𝘁? 𝗢𝗸𝗮𝘆 𝗹𝗮𝗻𝗴 \'𝘆𝗮𝗻. 𝗞𝗮𝘀𝗶 𝗱𝗶𝘁𝗼 𝘀𝗮 𝗚𝗡𝗖, 𝘄𝗲𝗹𝗰𝗼𝗺𝗲 𝗸𝗮. 💙\n\n🌊 LAYAG FEST 2026: Tungo sa Hiraya is your chance to explore, connect, and create memories that might just shape your college journey.\n\nWe\'ll be here when you\'re ready. 🤍\n\n#LayagFest2026 #TungoSaHiraya #WelcomeToGNC #Episode2\n\n—\n🎵 Music Credits\n\"Drag Path\"\nSong by Twenty One Pilots\n\nAll rights to the music belong to their respective copyright owners. This video is created for promotional and storytelling purposes only. No copyright infringement intended.', 28, NULL, 0, 'https://www.facebook.com/reel/3535750333249821/', 0, NULL, '2026-08-04 01:39:52', '2026-08-06 10:52:24'),
('403969556396264_1038288982285788', '𝐍𝐎 𝐂𝐋𝐀𝐒𝐒𝐄𝐒 𝐀𝐍𝐃 𝐍𝐎 𝐎𝐅𝐅𝐈𝐂𝐄 𝐎𝐏𝐄𝐑𝐀𝐓𝐈𝐎𝐍𝐒 𝐀𝐮𝐠𝐮𝐬𝐭 𝟔, 𝟐𝟎𝟐𝟔 (𝐓𝐡𝐮𝐫𝐬𝐝𝐚𝐲)\n\nPursuant to Memorandum Circular No. 122 issued by the Office of the President, classes at all levels are suspended on Thursday, August 6, 2026, due to the continued heavy rainfall brought by Tropical Storm Maymay and the Southwest Monsoon (Habagat).\n\nIn line with this, there will be no classes and no office operations tomorrow.\n\nTo all our students, faculty, staff, and parents, please stay safe, take this time to rest well, and prioritize your health and well-being. We encourage everyone to remain indoors whenever possible and continue monitoring the College’s official communication channels for further announcements and updates.', 29, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1038288982285788', 0, NULL, '2026-08-05 15:36:35', '2026-08-06 10:52:24'),
('403969556396264_1038865048894848', '📢 PE UNIFORM FITTING & RELEASING (COLLEGE)\n\nAttention, College Students!\n\nAng lahat ng College Students ay maaari nang magtungo sa Purchasing Office upang magpasukat ng kanilang PE Uniform sa mga sumusunod na petsa:\n\n📅 PE Uniform Fitting\nAugust 10–11, 2026\n📍 Purchasing Office\n\nMahalagang Paalala:\n✅ Maaari nang magpasukat ng PE Uniform kahit wala pang resibo ng bayad. Ang Purchasing Office ang magbibigay ng inyong Order Slip, na magsisilbing batayan para sa inyong uniform order.\n\nAng mga hindi pa nakakapagbayad ng ₱850.00 para sa PE Uniform ay maaari pa ring magbayad sa Cashier.\n\n📅 PE Uniform Releasing\nAugust 12–15, 2026\n🕘 9:00 AM – 5:00 PM\n📍 Purchasing Office\n\nThank you! ✨', 31, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1038865048894848', 0, NULL, '2026-08-06 08:07:46', '2026-08-06 10:52:24'),
('403969556396264_1038918485556171', '𝐀𝐃𝐕𝐈𝐒𝐎𝐑𝐘!\n\n𝐒𝐇𝐈𝐅𝐓 𝐓𝐎 𝐎𝐍𝐋𝐈𝐍𝐄 𝐂𝐋𝐀𝐒𝐒𝐄𝐒  \n𝐀𝐮𝐠𝐮𝐬𝐭 𝟕, 𝟐𝟎𝟐𝟔 (𝐅𝐫𝐢𝐝𝐚𝐲)\n\nIn view of the continued inclement weather and for the safety and well-being of everyone, all face-to-face classes on 𝐅𝐫𝐢𝐝𝐚𝐲, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟕, 𝟐𝟎𝟐𝟔, are hereby shifted to 𝐨𝐧𝐥𝐢𝐧𝐞 𝐥𝐞𝐚𝐫𝐧𝐢𝐧𝐠.🌧️\n\nStudents, parents, faculty, and staff are encouraged to continue monitoring the College’s official communication channels for further announcements and updates.✨\n\nThank you and keep safe, GNCians!☔️', 30, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1038918485556171', 0, NULL, '2026-08-06 09:37:58', '2026-08-06 10:52:24'),
('403969556396264_1041429128638440', '𝐔𝐏𝐃𝐀𝐓𝐄: 𝐏𝐮𝐫𝐬𝐮𝐚𝐧𝐭 𝐭𝐨 𝐄𝐱𝐞𝐜𝐮𝐭𝐢𝐯𝐞 𝐎𝐫𝐝𝐞𝐫 𝐍𝐨. 𝟐𝟎𝟐𝟔-𝟎𝟑𝟏 𝐢𝐬𝐬𝐮𝐞𝐝 𝐛𝐲 𝐭𝐡𝐞 𝐌𝐮𝐧𝐢𝐜𝐢𝐩𝐚𝐥𝐢𝐭𝐲 𝐨𝐟 𝐆𝐮𝐚𝐠𝐮𝐚.\n\n𝐖𝐀𝐋𝐀𝐍𝐆 𝐏𝐀𝐒𝐎𝐊!\n\nIn consideration of the continuous rainfall brought by the 𝐒𝐨𝐮𝐭𝐡𝐰𝐞𝐬𝐭 𝐌𝐨𝐧𝐬𝐨𝐨𝐧 (𝐇𝐚𝐛𝐚𝐠𝐚𝐭) and the flooding in several areas, please be informed that:\n\n📌 𝐓𝐨𝐝𝐚𝐲, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟗, 𝟐𝟎𝟐𝟔 (𝐒𝐮𝐧𝐝𝐚𝐲)\nAll Online classes this afternoon are CANCELLED.\n\n📌 𝐓𝐨𝐦𝐨𝐫𝐫𝐨𝐰, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟏𝟎, 𝟐𝟎𝟐𝟔 (𝐌𝐨𝐧𝐝𝐚𝐲)\n𝐒𝐔𝐒𝐏𝐄𝐍𝐒𝐈𝐎𝐍 𝐎𝐅 𝐀𝐋𝐋 𝐌𝐎𝐃𝐀𝐋𝐈𝐓𝐈𝐄𝐒 𝐎𝐅 𝐂𝐋𝐀𝐒𝐒𝐄𝐒 to ensure the safety of our students, especially those affected by flooding and difficult travel conditions.\n\nThe 𝐎𝐫𝐢𝐞𝐧𝐭𝐚𝐭𝐢𝐨𝐧 𝐬𝐜𝐡𝐞𝐝𝐮𝐥𝐞𝐝 𝐭𝐨𝐦𝐨𝐫𝐫𝐨𝐰 𝐰𝐢𝐥𝐥 𝐚𝐥𝐬𝐨 𝐛𝐞 𝐑𝐄𝐒𝐂𝐇𝐄𝐃𝐔𝐋𝐄𝐃 to another date. Further announcements regarding the new schedule will be posted accordingly.\n\nEveryone is advised to stay safe, avoid unnecessary travel, and continue to monitor official announcements and weather updates.\n\nKeep safe, GNCians! 💙', 35, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1041429128638440', 0, NULL, '2026-08-09 07:33:32', '2026-08-12 08:37:35'),
('403969556396264_1042007068580646', '📢 ADVISORY\n\nIn view of the continuous bad weather conditions and rising flood waters in several areas, please be informed that all GNC offices will be CLOSED today, August 10, 2026 (Monday).\n\nThis measure is being implemented to ensure the safety of our students, faculty, and personnel.\n\nOffice operations will resume once conditions permit. Further updates will be announced through the official GNC channels.\n\nPlease stay safe and take necessary precautions. ❤️☔️', 34, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1042007068580646', 0, NULL, '2026-08-09 23:07:03', '2026-08-12 08:37:35'),
('403969556396264_1042378795210140', '𝐀𝐃𝐕𝐈𝐒𝐎𝐑𝐘!\n\nDue to the 𝐜𝐨𝐧𝐭𝐢𝐧𝐮𝐨𝐮𝐬 𝐫𝐚𝐢𝐧𝐟𝐚𝐥𝐥 brought by the 𝐒𝐨𝐮𝐭𝐡𝐰𝐞𝐬𝐭 𝐌𝐨𝐧𝐬𝐨𝐨𝐧 (𝐇𝐚𝐛𝐚𝐠𝐚𝐭) and the presence of flooding in various areas, please be advised of the following class arrangement:\n\n📌 𝐓𝐨𝐦𝐨𝐫𝐫𝐨𝐰, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟏𝟏, 𝟐𝟎𝟐𝟔 (𝐓𝐮𝐞𝐬𝐝𝐚𝐲)\n𝐀𝐋𝐋 𝐂𝐋𝐀𝐒𝐒𝐄𝐒 𝐖𝐈𝐋𝐋 𝐑𝐄𝐒𝐔𝐌𝐄 𝐕𝐈𝐀 𝐎𝐍𝐋𝐈𝐍𝐄 𝐌𝐎𝐃𝐀𝐋𝐈𝐓𝐘.\n\nStudents are advised to coordinate with their respective instructors regarding the schedule and necessary instructions for tomorrow’s online classes.\n\nEveryone is encouraged to stay safe, avoid unnecessary travel, and continue to monitor official announcements and weather updates.\n\nKeep safe, GNCians! 💙', 33, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1042378795210140', 0, NULL, '2026-08-10 11:53:25', '2026-08-12 14:26:04'),
('403969556396264_1043093625138657', '📢 𝐀𝐃𝐕𝐈𝐒𝐎𝐑𝐘: 𝐎𝐍𝐋𝐈𝐍𝐄 𝐂𝐋𝐀𝐒𝐒𝐄𝐒\n\nDue to the continuous rainfall and flooding in several areas, all classes tomorrow, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟏𝟐, 𝟐𝟎𝟐𝟔 (𝐖𝐞𝐝𝐧𝐞𝐬𝐝𝐚𝐲), will be conducted 𝐎𝐍𝐋𝐈𝐍𝐄.\n\nThis measure is being implemented with the safety and well-being of our students as our top priority.\n\nStudents who may be 𝐮𝐧𝐚𝐛𝐥𝐞 𝐭𝐨 𝐚𝐭𝐭𝐞𝐧𝐝 𝐭𝐡𝐞𝐢𝐫 𝐨𝐧𝐥𝐢𝐧𝐞 𝐜𝐥𝐚𝐬𝐬𝐞𝐬 due to extreme weather conditions or other related circumstances are advised to 𝐝𝐢𝐫𝐞𝐜𝐭𝐥𝐲 𝐢𝐧𝐟𝐨𝐫𝐦 𝐭𝐡𝐞𝐢𝐫 𝐫𝐞𝐬𝐩𝐞𝐜𝐭𝐢𝐯𝐞 𝐬𝐮𝐛𝐣𝐞𝐜𝐭 𝐭𝐞𝐚𝐜𝐡𝐞𝐫𝐬.\n\nPlease stay safe, GNCians, and continue to monitor official GNC announcements for further updates.\n\nStay safe and dry! 💙', 32, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1043093625138657', 0, NULL, '2026-08-11 07:30:32', '2026-08-14 13:12:34'),
('403969556396264_1044023491712337', '📣 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬: 𝗢𝗻𝗹𝗶𝗻𝗲 𝗖𝗹𝗮𝘀𝘀 𝗳𝗼𝗿 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟯-𝟭𝟰, 𝟮𝟬𝟮𝟲\n\nIn consideration of our students who are still affected by flooding and the frequently experienced high tide in some areas, 𝗚𝗡𝗖 𝘄𝗶𝗹𝗹 𝗶𝗺𝗽𝗹𝗲𝗺𝗲𝗻𝘁 𝗼𝗻𝗹𝗶𝗻𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗼𝗻 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟯–𝟭𝟰, 𝟮𝟬𝟮𝟲 (𝗧𝗵𝘂𝗿𝘀𝗱𝗮𝘆–𝗙𝗿𝗶𝗱𝗮𝘆).\n\nStudents who may experience difficulties in attending their online classes due to the current situation are advised to communicate directly with their respective subject teachers and inform them of their circumstances so that appropriate consideration may be given.\n\nPlease continue to prioritize your safety and stay updated through GNC’s official channels for further announcements.\n\nThank you and stay safe, GNCians! 💜', 36, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1044023491712337', 0, NULL, '2026-08-12 09:29:57', '2026-08-16 07:58:13'),
('403969556396264_1044187195029300', 'Apply na!', NULL, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1044187195029300', 0, NULL, '2026-08-12 13:35:45', '2026-08-16 07:58:13'),
('403969556396264_1045521134895906', 'Save niyo ito, GNCians! Para in case of emergencies, alam natin ang tatawagan. Stay safe po, everyone! ✨', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1045521134895906', 0, NULL, '2026-08-14 03:05:25', '2026-08-16 07:58:13'),
('403969556396264_1045769208204432', '📣 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬: 𝗢𝗻𝗹𝗶𝗻𝗲 𝗖𝗹𝗮𝘀𝘀𝗲𝘀 𝗳𝗼𝗿 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟱–𝟭𝟲, 𝟮𝟬𝟮𝟲\n\nIn consideration of our students who are still affected by flooding and the recurring high tide in some areas, 𝗚𝗡𝗖 𝘄𝗶𝗹𝗹 𝗶𝗺𝗽𝗹𝗲𝗺𝗲𝗻𝘁 𝗼𝗻𝗹𝗶𝗻𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗼𝗻 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟱–𝟭𝟲, 𝟮𝟬𝟮𝟲 (𝗦𝗮𝘁𝘂𝗿𝗱𝗮𝘆–𝗦𝘂𝗻𝗱𝗮𝘆).\n\nStudents who may have difficulties attending their online classes due to the current situation are advised to communicate directly with their respective subject teachers and inform them of their circumstances for appropriate consideration.\n\nPlease continue to prioritize your safety and stay updated through GNC’s official channels for further announcements.\n\nThank you and stay safe, GNCians!✨', 37, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1045769208204432', 0, NULL, '2026-08-14 10:30:55', '2026-08-16 07:58:13'),
('403969556396264_1047623121352374', '𝐎𝐍𝐋𝐈𝐍𝐄 𝐂𝐋𝐀𝐒𝐒𝐄𝐒 - 𝐀𝐔𝐆𝐔𝐒𝐓 𝟏𝟕, 𝟐𝟎𝟐𝟔\n\nIn consideration of the results of the recent survey conducted to assess our students’ current situation and pursuant to the recent Executive Order No. 2026-035 of the Municipal Government of Guagua, GNC will be implementing ONLINE CLASSES tomorrow, August 17, 2026, for all levels.\n\nWe understand that some, if not most, of our students are living in flood-prone areas, and that traveling to and from school may be difficult and unsafe due to the current conditions.\n\nMore than academic requirements and school activities, your safety and well-being remain our priority. We hope that this temporary shift to online classes will help keep everyone safe while allowing learning to continue.\n\nPlease coordinate with your respective teachers regarding your online class schedules and activities.\n\nStay safe, everyone. Take care of yourselves and your families, and please continue to monitor official announcements and advisories. 💙\n\nGNC cares for you. Stay safe, GNCians!', 41, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1047623121352374', 0, NULL, '2026-08-16 13:04:11', '2026-08-19 12:30:36'),
('403969556396264_1048273411287345', '𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬: 𝗢𝗡𝗟𝗜𝗡𝗘 𝗖𝗟𝗔𝗦𝗦𝗘𝗦 — 𝗔𝗨𝗚𝗨𝗦𝗧 𝟭𝟴, 𝟮𝟬𝟮𝟲\n\nGNC will implement 𝗼𝗻𝗹𝗶𝗻𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝘁𝗼𝗺𝗼𝗿𝗿𝗼𝘄, 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟴, 𝟮𝟬𝟮𝟲, due to the continuous heavy rainfall and to ensure the safety of our students and personnel.\n\nIf you experience any difficulty attending your online classes, please inform your respective teachers.\n\nThank you, and stay safe!', 40, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1048273411287345', 0, NULL, '2026-08-17 07:42:57', '2026-08-21 07:58:53'),
('403969556396264_1048796227901730', '📣 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬: 𝗡𝗢 𝗢𝗙𝗙𝗜𝗖𝗘 𝗪𝗢𝗥𝗞 | 𝗔𝗨𝗚𝗨𝗦𝗧 𝟭𝟴, 𝟮𝟬𝟮𝟲\n\nPlease be advised that office work will be suspended today, August 18, 2026 (Tuesday), in consideration of the prevailing weather conditions and flooding in nearby areas.\n\nEmployees are advised to remain at home and prioritize their safety and well-being. Further announcements regarding the resumption of regular office operations will be released accordingly.\n\nHowever, classes will continue as scheduled through the Online Class Modality. Students who may be unable to attend their online classes due to weather conditions, power interruptions, internet connectivity issues, or other circumstances beyond their control are advised to inform or contact their respective teachers/instructors and make the necessary arrangements regarding missed classes and requirements.\n\nPlease stay safe and keep updated through official announcements. 🙏', 39, 3, 1, 'https://www.facebook.com/1009975871783766/posts/1048796227901730', 0, NULL, '2026-08-17 21:41:00', '2026-08-21 13:25:50'),
('403969556396264_1049153111199375', '📢 𝗢𝗡𝗟𝗜𝗡𝗘 𝗖𝗟𝗔𝗦𝗦𝗘𝗦 — 𝗔𝗨𝗚𝗨𝗦𝗧 𝟭𝟵, 𝟮𝟬𝟮𝟲\n\nDue to the unstable weather conditions and continuous flooding in various areas, 𝗚𝗡𝗖 𝘄𝗶𝗹𝗹 𝗰𝗼𝗻𝘁𝗶𝗻𝘂𝗲 𝘁𝗼 𝗶𝗺𝗽𝗹𝗲𝗺𝗲𝗻𝘁 𝗼𝗻𝗹𝗶𝗻𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝘁𝗼𝗺𝗼𝗿𝗿𝗼𝘄, 𝗔𝘂𝗴𝘂𝘀𝘁 𝟭𝟵, 𝟮𝟬𝟮𝟲.\n\nPlease stay safe and take care, everyone. 💖', 38, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1049153111199375', 0, NULL, '2026-08-18 08:59:39', '2026-08-21 13:25:50'),
('403969556396264_1049979204450099', '𝗢𝗡𝗟𝗜𝗡𝗘 𝗖𝗟𝗔𝗦𝗦 - 𝗔𝗨𝗚𝗨𝗦𝗧 𝟮𝟬, 𝟮𝟬𝟮𝟲 \n\nDue to the unstable weather conditions and continuous flooding in various areas, 𝗼𝗻𝗹𝗶𝗻𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝘄𝗶𝗹𝗹 𝗰𝗼𝗻𝘁𝗶𝗻𝘂𝗲 𝘁𝗼𝗺𝗼𝗿𝗿𝗼𝘄, 𝗔𝘂𝗴𝘂𝘀𝘁 𝟮𝟬, 𝟮𝟬𝟮𝟲, 𝗶𝗻 𝗮𝗹𝗹 𝗹𝗲𝘃𝗲𝗹𝘀.\n\nMeanwhile, 𝗼𝗳𝗳𝗶𝗰𝗲𝘀 𝘄𝗶𝗹𝗹 𝗿𝗲𝗺𝗮𝗶𝗻 𝗼𝗽𝗲𝗻 to accommodate students and clients who need to process or transact in person.\n\nPlease stay safe and take care, everyone. ✨', 42, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1049979204450099', 0, NULL, '2026-08-19 08:41:50', '2026-08-24 12:11:35'),
('403969556396264_1050841237697229', '📢 ENROLLMENT EXTENSION\n\nIn consideration of students who were unable to enroll within the original enrollment period, as well as those who cannot visit the school due to the continuous bad weather and flooding, GNC is extending the enrollment period until August 26, 2026.\n\nFor those who are unable to visit the school, we have also opened a temporary online enrollment option.\n\n📱 Enroll Online:\nhttps://forms.gle/wkypKTMeqXjuwCcc8\n\n🔗 Click the link above or scan the QR code to access the online enrollment form.\n\nDon’t miss the extended enrollment period! Stay safe, gncians! ✨', 43, 4, 1, 'https://www.facebook.com/1009975871783766/posts/1050841237697229', 0, NULL, '2026-08-20 08:08:07', '2026-08-25 08:53:56'),
('403969556396264_1051546377626715', '𝐍𝐢𝐧𝐨𝐲 𝐀𝐪𝐮𝐢𝐧𝐨 𝐃𝐚𝐲 | 𝐀𝐮𝐠𝐮𝐬𝐭 𝟐𝟏, 𝟐𝟎𝟐𝟔 \n\nToday, we remember more than a name in history. We remember a life that became a lasting reminder of courage, purpose, and the power of standing for what one believes in.\n\nHis story reminds us that a meaningful life is not measured only by the years we live, but by the impact we leave behind. Some lives may end, but the meaning they leave behind can continue to live on forever.', 44, 1, 1, 'https://www.facebook.com/1009975871783766/posts/1051546377626715', 0, NULL, '2026-08-21 03:52:44', '2026-08-27 12:42:06'),
('403969556396264_1051725190942167', '📣𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧\n\nPlease be informed that 𝗙𝗮𝗰𝗲-𝘁𝗼-𝗙𝗮𝗰𝗲 𝗖𝗹𝗮𝘀𝘀𝗲𝘀 𝘄𝗶𝗹𝗹 𝗿𝗲𝘀𝘂𝗺𝗲 𝘁𝗼𝗺𝗼𝗿𝗿𝗼𝘄, 𝗔𝘂𝗴𝘂𝘀𝘁 𝟮𝟮, 𝟮𝟬𝟮𝟲.\n\n𝗧𝗵𝗼𝘀𝗲 𝘄𝗵𝗼 𝘄𝗶𝗹𝗹 𝗯𝗲 𝘂𝗻𝗮𝗯𝗹𝗲 𝘁𝗼 𝗮𝘁𝘁𝗲𝗻𝗱 𝘁𝗵𝗲𝗶𝗿 𝗳𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗱𝘂𝗲 𝘁𝗼 𝗳𝗹𝗼𝗼𝗱𝗶𝗻𝗴 𝘀𝗵𝗮𝗹𝗹 𝗯𝗲 𝗲𝘅𝗰𝘂𝘀𝗲𝗱 𝗳𝗿𝗼𝗺 𝗮𝘁𝘁𝗲𝗻𝗱𝗮𝗻𝗰𝗲. They are only required to inform their respective instructors regarding their absence.\n\nStudents may also wear their respective 𝗱𝗲𝗽𝗮𝗿𝘁𝗺𝗲𝗻𝘁 𝘀𝗵𝗶𝗿𝘁𝘀 for tomorrow’s classes.\n\nThank you and Stay Safe!', 45, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1051725190942167', 0, NULL, '2026-08-21 09:23:07', '2026-08-27 12:42:06'),
('403969556396264_1052563940858292', '📣ANNOUNCEMENT\n\nPlease be informed that Face-to-Face Classes will continue tomorrow, August 23, 2026.\n\nStudents may wear their Department Shirt.\n\nStudents who are unable to attend school due to circumstances affecting their travel or safety shall be excused from their classes, provided that they properly communicate with and inform their respective instructors.\n\nThank you and stay safe!', 47, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1052563940858292', 0, NULL, '2026-08-22 09:25:20', '2026-08-27 12:42:06'),
('403969556396264_1053401580774528', '📢 𝘼𝙉𝙉𝙊𝙐𝙉𝘾𝙀𝙈𝙀𝙉𝙏\n\n𝗙𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 will continue tomorrow, 𝗔𝘂𝗴𝘂𝘀𝘁 𝟮𝟰, 𝟮𝟬𝟮𝟲.\n\nStudents may wear their department shirts/civilian clothes and comfortable sandals/clogs (please also bring your ID and COR). Those unable to attend due to travel or safety concerns will be excused but must inform their respective instructors.\n\nPlease stay safe, GNCians! 💚', 46, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1053401580774528', 0, NULL, '2026-08-23 09:42:17', '2026-08-28 09:22:05'),
('403969556396264_1054393764008643', '📢 𝘼𝙉𝙉𝙊𝙐𝙉𝘾𝙀𝙈𝙀𝙉𝙏\n\n𝗙𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 will continue tomorrow, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟐𝟓, 𝟐𝟎𝟐𝟔.\n\nStudents may wear their department shirts/civilian clothes and comfortable sandals/clogs (please also bring your ID and COR). Those unable to attend due to travel or safety concerns will be excused but must inform their respective instructors.\n\nPlease stay safe, GNCians! 💚', 48, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1054393764008643', 0, NULL, '2026-08-24 12:13:21', '2026-08-28 09:22:05'),
('403969556396264_1055223623925657', '📢 𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧\n\nAfter careful assessment and consideration by the Board of Administration, the following 𝗮𝗱𝗱𝗶𝘁𝗶𝗼𝗻𝗮𝗹 𝗺𝗲𝗮𝘀𝘂𝗿𝗲𝘀 𝘀𝗵𝗮𝗹𝗹 𝗯𝗲 𝗶𝗺𝗽𝗹𝗲𝗺𝗲𝗻𝘁𝗲𝗱 𝘁𝗼 𝗳𝘂𝗿𝘁𝗵𝗲𝗿 𝘀𝘂𝗽𝗽𝗼𝗿𝘁 𝗼𝘂𝗿 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀 𝘄𝗵𝗼 𝗮𝗿𝗲 𝘀𝘁𝗶𝗹𝗹 𝗮𝗳𝗳𝗲𝗰𝘁𝗲𝗱 𝗯𝘆 𝘁𝗵𝗲 𝗳𝗹𝗼𝗼𝗱𝗶𝗻𝗴 𝘄𝗵𝗶𝗹𝗲 𝗳𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗰𝗼𝗻𝘁𝗶𝗻𝘂𝗲:\n\nEffective immediately, the following shall be observed:\n\n✅ 𝗡𝗼 𝗾𝘂𝗶𝘇𝘇𝗲𝘀 shall be administered in any subject during this period.\n✅Teachers shall provide 𝗿𝗲𝗰𝗼𝗿𝗱𝗲𝗱 𝗱𝗶𝘀𝗰𝘂𝘀𝘀𝗶𝗼𝗻𝘀/𝗹𝗲𝗰𝘁𝘂𝗿𝗲𝘀 to help students keep up with their lessons and academic requirements despite the current circumstances.\n✅Students who are still directly affected by the flooding shall 𝗰𝗼𝗻𝘁𝗶𝗻𝘂𝗲 𝘁𝗼 𝗯𝗲 𝗲𝘅𝗰𝘂𝘀𝗲𝗱 𝗳𝗿𝗼𝗺 𝗮𝘁𝘁𝗲𝗻𝗱𝗶𝗻𝗴 𝗳𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀, subject to proper coordination with their respective teachers.\n\nThese measures are being implemented to ensure that our students are given sufficient consideration and flexibility while they cope with the effects of the flooding, without compromising their learning and academic progress.\n\nIn addition, 𝗮 𝘀𝘁𝘂𝗱𝗲𝗻𝘁 𝗰𝗼𝗻𝗰𝗲𝗿𝗻𝘀 𝗳𝗼𝗿𝗺 will be released to consolidate reports regarding concerns, requests for consideration, and other issues that students may be experiencing during this period. All information submitted through the form will be treated with strict confidentiality and will be handled appropriately by the concerned offices.\n\nWe encourage everyone to remain safe, stay updated through official announcements, and coordinate with your respective instructors for any concerns regarding your classes and requirements.\n\nPlease be guided accordingly.\n\n𝙏𝙝𝙞𝙨 𝙖𝙣𝙣𝙤𝙪𝙣𝙘𝙚𝙢𝙚𝙣𝙩 𝙖𝙥𝙥𝙡𝙞𝙚𝙨 𝙩𝙤 𝙩𝙝𝙚 𝘾𝙤𝙡𝙡𝙚𝙜𝙚 𝘿𝙚𝙥𝙖𝙧𝙩𝙢𝙚𝙣𝙩 𝙤𝙣𝙡𝙮.', 50, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1055223623925657', 0, NULL, '2026-08-25 11:29:50', '2026-08-28 09:22:05'),
('403969556396264_1057037967077556', '𝐀𝐍𝐍𝐎𝐔𝐍𝐂𝐄𝐌𝐄𝐍𝐓!\n\nPursuant to 𝐄𝐱𝐞𝐜𝐮𝐭𝐢𝐯𝐞 𝐎𝐫𝐝𝐞𝐫 𝐍𝐨. 𝟐𝟎𝟐𝟔-𝟎𝟑𝟕,  issued by the Municipality of Guagua, all classes at GNC will shift to 𝐎𝐧𝐥𝐢𝐧𝐞 𝐂𝐥𝐚𝐬𝐬𝐞𝐬 𝐭𝐨𝐦𝐨𝐫𝐫𝐨𝐰, 𝐀𝐮𝐠𝐮𝐬𝐭 𝟐𝟖, 𝟐𝟎𝟐𝟔, 𝐢𝐧 𝐚𝐥𝐥 𝐥𝐞𝐯𝐞𝐥𝐬 due to the forecasted persistent rainfall brought by the southwest monsoon.\n\nMeanwhile, 𝐨𝐟𝐟𝐢𝐜𝐞𝐬 𝐰𝐢𝐥𝐥 𝐫𝐞𝐦𝐚𝐢𝐧 𝐨𝐩𝐞𝐧 to accommodate students and clients who need to process or transact in person.\n\nPlease stay safe and take care, everyone.', 52, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1057037967077556', 0, NULL, '2026-08-27 14:22:35', '2026-08-30 10:35:37'),
('403969556396264_1057064243741595', 'Congratulations ✨', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1057064243741595', 0, NULL, '2026-08-27 14:58:19', '2026-08-30 10:35:37'),
('403969556396264_1057694423678577', '𝐀𝐍𝐍𝐎𝐔𝐍𝐂𝐄𝐌𝐄𝐍𝐓!\n\nPursuant to 𝐌𝐞𝐦𝐨𝐫𝐚𝐧𝐝𝐮𝐦 𝐂𝐢𝐫𝐜𝐮𝐥𝐚𝐫 𝐍𝐨. 𝟏𝟑𝟐 issued by Malacañang, all classes in the College Department from 5:00 PM onwards are hereby suspended today, August 28, 2026.\n\nPlease stay safe and take care, everyone.', 51, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1057694423678577', 0, NULL, '2026-08-28 09:09:12', '2026-08-30 10:35:37'),
('403969556396264_1057858256995527', '📢 ANNOUNCEMENT\n\nPursuant to Executive Order No. 2026-038 issued by the Municipality of Guagua, online classes at GNC are suspended tomorrow, August 29, 2026, due to the continuing effects of the Enhanced Southwest Monsoon.\n\nEveryone is advised to stay safe and take necessary precautions. 🙏', 54, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1057858256995527', 0, NULL, '2026-08-28 13:13:58', '2026-09-02 10:08:05'),
('403969556396264_1058494193598600', '𝐀𝐍𝐍𝐎𝐔𝐍𝐂𝐄𝐌𝐄𝐍𝐓!\n\nDue to the unstable weather and recent flooding in several areas, and in consideration of everyone’s safety:\n\n• All classes are suspended on August 30, 2026.\n• Face-to-face and online classes are covered by this suspension.\n• Please stay safe and take all necessary precautions.\n\nStay safe, everyone.', 53, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1058494193598600', 0, NULL, '2026-08-29 07:58:36', '2026-09-02 10:56:39'),
('403969556396264_1059914310123255', '𝐍𝐚𝐭𝐢𝐨𝐧𝐚𝐥 𝐇𝐞𝐫𝐨𝐞𝐬 𝐃𝐚𝐲 𝟐𝟎𝟐𝟔\n\nToday, we honor the heroes who shaped our nation and the everyday heroes who continue to serve their communities.\n\nAs Pampanga once again faces widespread flooding, may we continue to uphold the spirit of 𝐛𝐚𝐲𝐚𝐧𝐢𝐡𝐚𝐧, while remembering that 𝐫𝐞𝐬𝐢𝐥𝐢𝐞𝐧𝐜𝐞 𝐬𝐡𝐨𝐮𝐥𝐝 𝐧𝐞𝐯𝐞𝐫 𝐛𝐞 𝐨𝐮𝐫 𝐨𝐧𝐥𝐲 𝐨𝐩𝐭𝐢𝐨𝐧. We deserve safer communities, meaningful action, and 𝐚 𝐟𝐮𝐭𝐮𝐫𝐞 𝐰𝐡𝐞𝐫𝐞 𝐩𝐞𝐨𝐩𝐥𝐞 𝐝𝐨 𝐧𝐨𝐭 𝐡𝐚𝐯𝐞 𝐭𝐨 𝐞𝐧𝐝𝐮𝐫𝐞 𝐭𝐡𝐞 𝐬𝐚𝐦𝐞 𝐬𝐭𝐫𝐮𝐠𝐠𝐥𝐞𝐬 𝐭𝐢𝐦𝐞 𝐚𝐧𝐝 𝐚𝐠𝐚𝐢𝐧.\n\n𝙎𝙖𝙡𝙪𝙙𝙤 𝙨𝙖 𝙢𝙜𝙖 𝙗𝙖𝙮𝙖𝙣𝙞—𝙣𝙤𝙤𝙣, 𝙣𝙜𝙖𝙮𝙤𝙣, 𝙖𝙩 𝙨𝙖 𝙗𝙖𝙬𝙖𝙩 𝙥𝙖𝙣𝙖𝙝𝙤𝙣𝙜 𝙣𝙖𝙣𝙜𝙖𝙣𝙜𝙖𝙞𝙡𝙖𝙣𝙜𝙖𝙣 𝙣𝙜 𝙥𝙖𝙜𝙠𝙖𝙠𝙖𝙞𝙨𝙖. ✨', 57, 1, 0, 'https://www.facebook.com/1009975871783766/posts/1059914310123255', 0, NULL, '2026-08-31 01:00:28', '2026-09-05 09:01:23'),
('403969556396264_1060034363444583', '𝐀𝐍𝐍𝐎𝐔𝐍𝐂𝐄𝐌𝐄𝐍𝐓!\n\nUpdate:(1:36PM)Pursuant to Executive Order No. 2026-039 issued by the Municipality of Guagua, and in consideration of everyone’s safety\n\n• All classes are suspended on September 1-2, 2026.\n• Face-to-face and online classes are covered by this suspension.\n• Please stay safe and take all necessary precautions.\n\nStay safe, everyone.', 56, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1060034363444583', 0, NULL, '2026-08-31 04:47:23', '2026-09-05 09:01:23'),
('403969556396264_1061889839925702', '📣 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬\n\nPursuant to𝗘𝘅𝗲𝗰𝘂𝘁𝗶𝘃𝗲 𝗢𝗿𝗱𝗲𝗿 𝗡𝗼. 𝟮𝟬𝟮𝟲-𝟬𝟰𝟬 issued by the Municipality of Guagua, and in consideration of everyone’s safety:\n\n✅All 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗮𝗿𝗲 𝘀𝘂𝘀𝗽𝗲𝗻𝗱𝗲𝗱 𝗳𝗿𝗼𝗺 𝗦𝗲𝗽𝘁𝗲𝗺𝗯𝗲𝗿 𝟯–𝟲, 𝟮𝟬𝟮𝟲.\n✅The suspension covers both face-to-face and online classes.\n✅Since GNC conducts Sunday classes, 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝘀𝗰𝗵𝗲𝗱𝘂𝗹𝗲𝗱 𝗼𝗻 𝗦𝘂𝗻𝗱𝗮𝘆, 𝗦𝗲𝗽𝘁𝗲𝗺𝗯𝗲𝗿 𝟲, 𝟮𝟬𝟮𝟲 𝗮𝗿𝗲 𝗹𝗶𝗸𝗲𝘄𝗶𝘀𝗲 𝘀𝘂𝘀𝗽𝗲𝗻𝗱𝗲𝗱.\n✅All 𝗼𝗳𝗳𝗶𝗰𝗲 𝗼𝗽𝗲𝗿𝗮𝘁𝗶𝗼𝗻𝘀 𝗮𝗿𝗲 𝗮𝗹𝘀𝗼 𝘀𝘂𝘀𝗽𝗲𝗻𝗱𝗲𝗱 tomorrow. \n\nPlease stay safe and take all necessary precautions. Stay safe, everyone! ✨', 58, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1061889839925702', 0, NULL, '2026-09-02 10:18:31', '2026-09-05 09:01:23'),
('403969556396264_1065225529592133', '📣𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬\n\nFor the period of 𝗦𝗲𝗽𝘁𝗲𝗺𝗯𝗲𝗿 𝟳–𝟵, 𝟮𝟬𝟮𝟲, GNC will implement a  𝗯𝗹𝗲𝗻𝗱𝗲𝗱 𝗹𝗲𝗮𝗿𝗻𝗶𝗻𝗴 𝗺𝗼𝗱𝗮𝗹𝗶𝘁𝘆 consisting of 𝙤𝙣𝙡𝙞𝙣𝙚 𝙖𝙣𝙙 𝙢𝙤𝙙𝙪𝙡𝙖𝙧 𝙘𝙡𝙖𝙨𝙨𝙚𝙨:\n\n✅Students 𝘄𝗵𝗼 𝗰𝗮𝗻 𝗮𝘁𝘁𝗲𝗻𝗱 their online classes may attend as scheduled.\n✅Students who are 𝘂𝗻𝗮𝗯𝗹𝗲 𝘁𝗼 𝗮𝘁𝘁𝗲𝗻𝗱 online classes may accomplish their 𝗺𝗼𝗱𝘂𝗹𝗮𝗿 𝗮𝗰𝘁𝗶𝘃𝗶𝘁𝗶𝗲𝘀 and coordinate with their respective teachers for instructions and requirements.\n✅GNC 𝗼𝗳𝗳𝗶𝗰𝗲𝘀 𝘄𝗶𝗹𝗹 𝗿𝗲𝗺𝗮𝗶𝗻 𝗼𝗽𝗲𝗻 for important transactions and concerns.\n\nPlease stay safe and continue to take necessary precautions.\n\n𝙁𝙤𝙧 𝙛𝙪𝙧𝙩𝙝𝙚𝙧 𝙖𝙣𝙣𝙤𝙪𝙣𝙘𝙚𝙢𝙚𝙣𝙩𝙨 𝙖𝙣𝙙 𝙪𝙥𝙙𝙖𝙩𝙚𝙨, 𝙛𝙤𝙡𝙡𝙤𝙬 𝙩𝙝𝙚 𝙤𝙛𝙛𝙞𝙘𝙞𝙖𝙡 𝙂𝙉𝘾 𝙥𝙖𝙜𝙚 𝙖𝙣𝙙 𝙟𝙤𝙞𝙣 𝙤𝙪𝙧 𝙗𝙧𝙤𝙖𝙙𝙘𝙖𝙨𝙩 𝙘𝙝𝙖𝙣𝙣𝙚𝙡. \n\nStay safe, everyone!', 60, 3, 0, 'https://www.facebook.com/1009975871783766/posts/1065225529592133', 0, NULL, '2026-09-06 08:29:03', '2026-09-09 08:11:10'),
('403969556396264_1066204736160879', '', 62, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1066204736160879', 0, NULL, '2026-09-07 12:41:55', '2026-09-14 05:42:34'),
('403969556396264_1067654666015886', '📣 𝗔𝗗𝗩𝗜𝗦𝗢𝗥𝗬\n\nIn consideration of the safety and well-being of our students and the GNC community, and due to the unstable weather conditions, the following arrangements will be implemented from 𝗦𝗲𝗽𝘁𝗲𝗺𝗯𝗲𝗿 𝟭𝟬–𝟭𝟯, 𝟮𝟬𝟮𝟲:\n\n✅𝗖𝗹𝗮𝘀𝘀𝗲𝘀 𝗳𝗼𝗿 𝗮𝗹𝗹 𝗹𝗲𝘃𝗲𝗹𝘀 𝘄𝗶𝗹𝗹 𝘀𝗵𝗶𝗳𝘁 𝘁𝗼 𝗼𝗻𝗹𝗶𝗻𝗲 𝗹𝗲𝗮𝗿𝗻𝗶𝗻𝗴, while 𝗚𝗡𝗖 𝗼𝗳𝗳𝗶𝗰𝗲𝘀 𝘄𝗶𝗹𝗹 𝗿𝗲𝗺𝗮𝗶𝗻 𝗼𝗽𝗲𝗻 for important transactions and concerns. \n\n✅The 𝗛𝗼𝗺𝗲 𝗚𝗮𝗺𝗲𝘀 𝟮𝟬𝟮𝟲 𝘄𝗶𝗹𝗹 𝗮𝗹𝘀𝗼 𝗯𝗲 𝗿𝗲𝘀𝗰𝗵𝗲𝗱𝘂𝗹𝗲𝗱 to a later date, which will be announced accordingly.\n\n✅𝗙𝗮𝗰𝗲-𝘁𝗼-𝗳𝗮𝗰𝗲 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗮𝗿𝗲 𝗲𝘅𝗽𝗲𝗰𝘁𝗲𝗱 𝘁𝗼 𝗿𝗲𝘀𝘂𝗺𝗲 𝗼𝗻 𝗠𝗼𝗻𝗱𝗮𝘆, September 14, 2026, 𝘂𝗻𝗹𝗲𝘀𝘀 𝘀𝘂𝗽𝗲𝗿𝘀𝗲𝗱𝗲𝗱 𝗯𝘆 𝗳𝘂𝗿𝘁𝗵𝗲𝗿 𝗮𝗻𝗻𝗼𝘂𝗻𝗰𝗲𝗺𝗲𝗻𝘁𝘀 𝗼𝗿 𝗰𝗶𝗿𝗰𝘂𝗺𝘀𝘁𝗮𝗻𝗰𝗲𝘀.\n\nWe encourage everyone to prioritize safety, stay informed, and take the necessary precautions during this difficult time. Please continue to follow the official GNC page and broadcast channel for updates.\n\nYour safety is our priority. Stay safe, GNCians! ✨', 59, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1067654666015886', 0, NULL, '2026-09-09 06:11:31', '2026-09-27 06:21:04'),
('403969556396264_1071140179000668', '', 61, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1071140179000668', 0, NULL, '2026-09-13 08:49:12', '2026-09-27 06:21:04'),
('403969556396264_1071946048920081', 'Congratulations! ✨', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1071946048920081', 0, NULL, '2026-09-14 07:57:39', '2026-09-27 15:23:39'),
('403969556396264_1078492421598777', '𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧: 𝗧𝗗𝗣/𝗧𝗘𝗦 𝗚𝗥𝗔𝗡𝗧𝗘𝗘𝗦 𝗢𝗥𝗜𝗘𝗡𝗧𝗔𝗧𝗜𝗢𝗡 & 𝗠𝗢𝗡𝗜𝗧𝗢𝗥𝗜𝗡𝗚\n\nTo all TDP/TES Grantees, please be informed that there will be an Orientation and Monitoring on:\n📅 𝗗𝗮𝘁𝗲: 𝗢𝗰𝘁𝗼𝗯𝗲𝗿 𝟭, 𝟮𝟬𝟮𝟲 (𝗧𝗵𝘂𝗿𝘀𝗱𝗮𝘆)�\n⏰ 𝗖𝗮𝗹𝗹 𝗧𝗶𝗺𝗲: 𝟳:𝟯𝟬 𝗔𝗠�\n📍 𝗩𝗲𝗻𝘂𝗲: 𝗧𝗮𝗻𝗷𝗮𝗻𝗰𝗼 𝗛𝗮𝗹𝗹\n\n𝗜𝗠𝗣𝗢𝗥𝗧𝗔𝗡𝗧 𝗥𝗘𝗠𝗜𝗡𝗗𝗘𝗥𝗦:\n�✅ May attendance po tayo, kaya ang mga pangalan na nasa listahan below ay REQUIRED ATTENDEES.\n✅ Kung kayo ay GRADUATE, na hindi na required mag attend.\n✅ Kung kayo naman ay NOT ENROLLED THIS SEM AT BALAK PA RIN MAG ENROLL NEXT SEM, maaari kayong pumunta para mag file ng LOA para hindi kayo mawala sa listahan.�\n✅ Please be on time and make sure na makakadalo.�\n✅ Dapat alam/dala ninyo ang inyong LBP Account Number for verification/monitoring purposes.\n\nCheck the list here: https://docs.google.com/spreadsheets/d/1l0v-aSx5Ayp3Vd8ezWZwH-eQplsJ5YA1/edit?usp=sharing&ouid=102001444818481439393&rtpof=true&sd=true\n\nPlease take note of the date and call time. Attendance will be recorded, so please make sure na present and on time. Thank you!', 63, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1078492421598777', 0, NULL, '2026-09-22 02:22:49', '2026-09-28 14:11:57'),
('403969556396264_1079535318161154', '📣 𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧: Uniform Policy Update\n📅 Effective September 24, 2026\n\nStarting tomorrow, 𝗦𝗲𝗽𝘁𝗲𝗺𝗯𝗲𝗿 𝟮𝟰, 𝟮𝟬𝟮𝟲, all students are required to wear their 𝗰𝗼𝗺𝗽𝗹𝗲𝘁𝗲 𝗽𝗿𝗲𝘀𝗰𝗿𝗶𝗯𝗲𝗱 𝘂𝗻𝗶𝗳𝗼𝗿𝗺𝘀.\n\n𝗘𝗫𝗘𝗠𝗣𝗧𝗜𝗢𝗡:\n𝗙𝗶𝗿𝘀𝘁-𝘆𝗲𝗮𝗿 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀 𝗮𝗻𝗱 𝘁𝗿𝗮𝗻𝘀𝗳𝗲𝗿𝗲𝗲𝘀 who do not have their uniforms yet may continue wearing a white T-shirt, pants, and closed shoes as an alternative.\nThey are 𝗿𝗲𝗾𝘂𝗶𝗿𝗲𝗱 𝘁𝗼 𝗯𝗿𝗶𝗻𝗴 𝗮𝗻𝗱 𝗽𝗿𝗲𝘀𝗲𝗻𝘁 𝘁𝗵𝗲𝗶𝗿 𝗦𝘁𝘂𝗱𝗲𝗻𝘁 𝗜𝗗 𝗼𝗿 𝗖𝗢𝗥 at all times for identification.\n\nPlease be guided accordingly. Thank you!', 64, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1079535318161154', 0, NULL, '2026-09-23 07:53:54', '2026-09-28 15:09:12'),
('403969556396264_1083383604442992', '𝟮 𝗗𝗔𝗬𝗦 𝗡𝗔 𝗟𝗔𝗡𝗚, 𝗛𝗢𝗠𝗘 𝗚𝗔𝗠𝗘𝗦 𝟮𝟬𝟮𝟲 𝗡𝗔! 🔥🏆\n\nReady na ba ang jersey mo? 👕\nMay pang-cheer na ba kay crush? 📣👀\nOr baka may budget ka na para libutin lahat ng booths sa Intrams? 😭💸\n\nWhatever your game plan is, GNCians, WE’RE READY FOR YOU! \n\n📅 September 29 – October 3, 2026\n📍 GNC Campus\n\nBring your squad, support your teammates, and let’s make this year’s Home Games unforgettable! 🏆🔥\n\n𝗜𝗠𝗣𝗢𝗥𝗧𝗔𝗡𝗧: 𝗛𝗼𝗺𝗲 𝗚𝗮𝗺𝗲𝘀 𝟮𝟬𝟮𝟲 𝗶𝘀 𝗲𝘅𝗰𝗹𝘂𝘀𝗶𝘃𝗲𝗹𝘆 𝗳𝗼𝗿 𝗚𝗡𝗖 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀. 𝗢𝘂𝘁𝘀𝗶𝗱𝗲𝗿𝘀 𝗮𝗻𝗱 𝗻𝗼𝗻-𝗚𝗡𝗖 𝗶𝗻𝗱𝗶𝘃𝗶𝗱𝘂𝗮𝗹𝘀 𝘄𝗶𝗹𝗹 𝗻𝗼𝘁 𝗯𝗲 𝗮𝗹𝗹𝗼𝘄𝗲𝗱 𝗶𝗻𝘀𝗶𝗱𝗲 𝘁𝗵𝗲 𝗲𝘃𝗲𝗻𝘁 𝗽𝗿𝗲𝗺𝗶𝘀𝗲𝘀.\n\nSee you at the games, GNC! LET’S GOOOO! 🔥🏆\n\n#GNCHomeGames2026 #GNCIntramurals2026 #HomeGames2026', 88, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1083383604442992', 0, NULL, '2026-09-27 09:20:41', '2026-09-28 15:09:12'),
('403969556396264_1084330121015007', '𝗥𝗘𝗔𝗗𝗬 𝗙𝗢𝗥 𝗧𝗛𝗘 𝗖𝗛𝗢𝗚? 👀🔥\n\nAre you ready for the ONE AND ONLY 𝗞𝗘𝗡𝗝𝗜 “𝗖𝗛𝗢𝗚” 𝗠𝗢𝗥𝗔𝗟?! 🏀\n\nCatch him LIVE TOMORROW at 𝗚𝗡𝗖 𝗛𝗼𝗺𝗲 𝗚𝗮𝗺𝗲𝘀 𝟮𝟬𝟮𝟲 𝗮𝘀 𝗼𝘂𝗿 𝗚𝘂𝗲𝘀𝘁 𝗦𝗽𝗲𝗮𝗸𝗲𝗿  as he shares his journey, passion, and words of inspiration to our young and aspiring athletes! 💪🔥\n\nSee you tomorrow, GNCians! 🏀❤️‍🔥\n#GNCHomeGames2026 #ChogMoral #GNCIntramurals2026 #HomeGames2026', 90, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1084330121015007', 0, NULL, '2026-09-28 09:19:34', '2026-09-28 15:09:12'),
('403969556396264_1084562407658445', '🏆  𝗛𝗢𝗠𝗘 𝗚𝗔𝗠𝗘𝗦 𝟮𝟬𝟮𝟲: 𝗧𝗢𝗠𝗢𝗥𝗥𝗢𝗪!  🏆\n\nThe countdown is OVER! 👀🔥 Tomorrow, we officially kick off Home Games 2026 — so here’s your 𝗜𝗻𝘁𝗿𝗮𝗺𝘀 𝗖𝗵𝗲𝗰𝗸𝗹𝗶𝘀𝘁 before you step onto campus! 👇\n\n☑️ 𝗣𝗔𝗥𝗔𝗗𝗘 𝗔𝗦𝗦𝗘𝗠𝗕𝗟𝗬 — 𝟳:𝟬𝟬 𝗔𝗠\n📍 Behind the Gym, along the side of the Gym up to Rizal Quad\n\n☑️  𝗣𝗔𝗥𝗔𝗗𝗘 𝗙𝗜𝗧 𝗖𝗛𝗘𝗖𝗞 👕\nOnly players will join the parade. Make sure you’re wearing your official Home Games 2026 uniform/jersey!\n\n☑️ 𝗛𝗘𝗔𝗗 𝗧𝗢 𝗧𝗛𝗘 𝗖𝗢𝗩𝗘𝗥𝗘𝗗 𝗖𝗢𝗨𝗥𝗧𝗦 🏆\nThe program will commence right after the parade at the Covered Courts.\n\n☑️ 𝗕𝗢𝗢𝗧𝗛-𝗛𝗢𝗣𝗣𝗜𝗡𝗚 👀\nDon’t forget to check out the different booths around the campus!\n\n☑️ 𝗩𝗜𝗦𝗜𝗧 𝗧𝗛𝗘 𝗟𝗔𝗬𝗔𝗚 𝗕𝗔𝗭𝗔𝗔𝗥 🛍️\nSwing by the Layag Bazaar located at the side of the Gym!\n\n☑️ 𝗖𝗛𝗘𝗘𝗥 𝗙𝗢𝗥 𝗬𝗢𝗨𝗥 𝗧𝗘𝗔𝗠𝗠𝗔𝗧𝗘𝗦 📣\nShow up, make some noise, and support your teams! Let’s make Home Games 2026 one to remember!\n\n☑️ 𝗚𝗘𝗧 𝗔 𝗦𝗡𝗔𝗣 𝗪𝗜𝗧𝗛 𝗖𝗛𝗢𝗚 𝗠𝗢𝗥𝗔𝗟𝗘𝗦! 📸🔥\nAnd yes, you definitely don’t want to miss your chance to see and get a photo with our special guest!\n\n☑️ 𝗥𝗘𝗠𝗜𝗡𝗗𝗘𝗥: 𝗚𝗡𝗖 𝗦𝗧𝗨𝗗𝗘𝗡𝗧𝗦 𝗢𝗡𝗟𝗬 🚨\n𝗢𝘂𝘁𝘀𝗶𝗱𝗲𝗿𝘀 𝗮𝗿𝗲 𝗡𝗢𝗧 𝗮𝗹𝗹𝗼𝘄𝗲𝗱 inside the campus. Please bring your GNC ID and cooperate with our security personnel.\n\n🔥 Ready na ba, GNC?\nBring your energy. Wear your colors. And make every moment count. 💙💚💛♥\n\n𝗦𝗘𝗘 𝗬𝗢𝗨 𝗧𝗢𝗠𝗢𝗥𝗥𝗢𝗪 𝗔𝗧 𝗛𝗢𝗠𝗘 𝗚𝗔𝗠𝗘𝗦 𝟮𝟬𝟮𝟲! 🏆🔥\nLet the games begin!', 91, 2, 1, 'https://www.facebook.com/1009975871783766/posts/1084562407658445', 0, NULL, '2026-09-28 14:29:46', '2026-09-28 15:09:12'),
('403969556396264_1084936617621024', '𝗦𝗡𝗔𝗣𝗦 𝗙𝗥𝗢𝗠 𝗧𝗛𝗘 𝗚𝗡𝗖 𝗛𝗢𝗠𝗘 𝗚𝗔𝗠𝗘𝗦 𝟮𝟬𝟮𝟲 𝗣𝗔𝗥𝗔𝗗𝗘', 93, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1084936617621024', 0, NULL, '2026-09-29 00:01:45', '2026-09-29 12:35:04'),
('403969556396264_1085054784275874', 'Thank you, Kenji “Chog” Moral for gracing the opening of our #GNCHomeGames2026 ✨', 92, NULL, 0, 'https://www.facebook.com/reel/1601118451491793/', 0, NULL, '2026-09-29 03:06:43', '2026-10-01 02:18:47'),
('403969556396264_1086044574176895', 'Reminder for tomorrow, TDP/TES grantees! You may wear your department shirts. Thank you! ✨', 94, 4, 1, 'https://www.facebook.com/1009975871783766/posts/1086044574176895', 0, NULL, '2026-09-30 03:17:03', '2026-10-01 04:08:49'),
('403969556396264_1089079430540076', '𝗖𝗛𝗘𝗗 𝗥𝗲𝗴𝗶𝗼𝗻𝗮𝗹 𝗢𝗳𝗳𝗶𝗰𝗲 𝗜𝗜𝗜 𝗮𝗻𝗱 𝗥𝗲𝗴𝗶𝗼𝗻𝗮𝗹 𝗤𝘂𝗮𝗹𝗶𝘁𝘆 𝗔𝘀𝘀𝗲𝘀𝘀𝗺𝗲𝗻𝘁 𝗧𝗲𝗮𝗺 (𝗥𝗤𝗔𝗧) 𝗢𝗻𝘀𝗶𝘁𝗲 𝗠𝗼𝗻𝗶𝘁𝗼𝗿𝗶𝗻𝗴 𝗮𝗻𝗱 𝗘𝘃𝗮𝗹𝘂𝗮𝘁𝗶𝗼𝗻 𝗩𝗶𝘀𝗶𝘁 | 𝗢𝗰𝘁𝗼𝗯𝗲𝗿 𝟭, 𝟮𝟬𝟮𝟲\n\nA meaningful day of learning, evaluation, and continuous improvement. 💙\n\nWe sincerely thank the CHED Regional Office III and the Regional Quality Assessment Team (RQAT) for conducting the onsite monitoring and evaluation visit at GNC on October 1, 2026. 💙\n\nThe team assessed the Teacher Education Program, led by Dean Annaliza V. Maris, Dean of the College of Arts and Sciences and Education; the Graduate School Programs, led by Dean Lolita Nazal, Dean of the Graduate School; and various common areas, including Student Affairs and Services, Medical and Dental Services, Safety and Security, Scholarship, and other offices.\n\nThank you for your valuable time, insights, and recommendations as we continue striving for quality and excellence in education. ✨', 103, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1089079430540076', 0, NULL, '2026-10-03 02:44:41', '2026-10-06 02:11:30'),
('403969556396264_1089091540538865', '𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧 📣\n\nFollowing the recently concluded Home Games 2026, 𝗦𝘂𝗻𝗱𝗮𝘆, 𝗢𝗰𝘁𝗼𝗯𝗲𝗿 𝟰, 𝟮𝟬𝟮𝟲, 𝘄𝗶𝗹𝗹 𝗯𝗲 𝗼𝗯𝘀𝗲𝗿𝘃𝗲𝗱 𝗮𝘀 𝗮 𝗿𝗲𝘀𝘁 𝗱𝗮𝘆 𝗳𝗼𝗿 𝗮𝗹𝗹 𝗚𝗡𝗖 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀, especially the players and participants who took part in the Home Games.\n\n✅No classes will be held on Sunday.\n✅Classes will resume on Monday, October 5, 2026, as we celebrate Teachers’ Day 2026.\n\nRest well, GNC community, and see you on Monday! 💙', 102, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1089091540538865', 0, NULL, '2026-10-03 03:00:06', '2026-10-06 15:44:58'),
('403969556396264_1089122347202451', 'Congratulations, Engineers! ✨', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1089122347202451', 0, NULL, '2026-10-03 03:47:52', '2026-10-08 04:48:58'),
('403969556396264_1089537113827641', '💃🕺 DANCESPORT & DANCE TROUPE AUDITION! 🎶🔥\n\nThink you’ve got the moves? It’s time to step up and show us what you’ve got!\n\n📅 October 8–9, 2026\n⏰ 1:00 PM – 6:00 PM\n📍 GNCI Covered Courts Stage\n\nWhat to prepare:\n🎵 At least a 2-minute impromptu dance\n👕 Wear an outfit appropriate for your performance — no uniforms', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1089537113827641', 0, NULL, '2026-10-03 14:39:26', '2026-10-08 15:52:23'),
('403969556396264_1090971760350843', '𝗛𝗮𝗽𝗽𝘆 𝗧𝗲𝗮𝗰𝗵𝗲𝗿’𝘀 𝗗𝗮𝘆! 👩‍🏫📚💐\n\nToday, we celebrate and honor the dedication, passion, and invaluable contributions of our teachers who continue to inspire and shape lives every day. Thank you for all that you do! ❤️\n\nAs part of the celebration, 𝗰𝗹𝗮𝘀𝘀𝗲𝘀 𝗮𝗿𝗲 𝘀𝘂𝘀𝗽𝗲𝗻𝗱𝗲𝗱 𝗮𝗻𝗱 𝗮𝗹𝗹 𝗼𝗳𝗳𝗶𝗰𝗲𝘀 𝘄𝗶𝗹𝗹 𝗯𝗲 𝗰𝗹𝗼𝘀𝗲𝗱 𝘀𝘁𝗮𝗿𝘁𝗶𝗻𝗴 𝟭𝟮:𝟬𝟬 𝗻𝗼𝗼𝗻 𝘁𝗼𝗱𝗮𝘆 to give everyone the opportunity to join and enjoy the Teacher’s Day celebration.\n\nHappy Teacher’s Day, GNCians!  🎉✨', 104, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1090971760350843', 0, NULL, '2026-10-05 05:10:07', '2026-10-08 15:52:23'),
('403969556396264_1091777606936925', '📢 𝗦𝗖𝗛𝗢𝗟𝗔𝗥𝗦𝗛𝗜𝗣 𝗢𝗣𝗣𝗢𝗥𝗧𝗨𝗡𝗜𝗧𝗜𝗘𝗦 𝗙𝗢𝗥 𝗙𝗘𝗠𝗔𝗟𝗘 𝗦𝗧𝗨𝗗𝗘𝗡𝗧𝗦\n\nThe Soroptimist International of Guagua is currently stationed near the GNC facade to cater to 𝗳𝗲𝗺𝗮𝗹𝗲 𝗦𝗲𝗻𝗶𝗼𝗿 𝗛𝗶𝗴𝗵 𝗦𝗰𝗵𝗼𝗼𝗹 𝗮𝗻𝗱 𝗖𝗼𝗹𝗹𝗲𝗴𝗲 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀 who are interested in available scholarship opportunities.\n\nAmong the programs being offered are:\n𝗟𝗶𝘃𝗲 𝗬𝗼𝘂𝗿 𝗗𝗿𝗲𝗮𝗺 𝗔𝘄𝗮𝗿𝗱𝘀 – an educational cash grant for women who are heads of households, to ort their education or vocational training.\n\n𝗔𝗰𝗰𝗲𝘀𝘀 𝘁𝗼 𝗘𝗱𝘂𝗰𝗮𝘁𝗶𝗼𝗻 𝗦𝗰𝗵𝗼𝗹𝗮𝗿𝘀𝗵𝗶𝗽 – provides monthly support to female students from low-income families pursuing college, vocational, or review courses.\n\nInterested students may visit the 𝗦𝗼𝗿𝗼𝗽𝘁𝗶𝗺𝗶𝘀𝘁 𝗯𝗼𝗼𝘁𝗵 𝗻𝗲𝗮𝗿 𝘁𝗵𝗲 𝗚𝗡𝗖 𝗳𝗮𝗰𝗮𝗱𝗲 𝘁𝗼 𝗶𝗻𝗾𝘂𝗶𝗿𝗲, 𝘀𝗲𝗰𝘂𝗿𝗲 𝗮𝗽𝗽𝗹𝗶𝗰𝗮𝘁𝗶𝗼𝗻 𝗳𝗼𝗿𝗺𝘀, 𝗮𝗻𝗱 𝘀𝘂𝗯𝗺𝗶𝘁 𝘁𝗵𝗲𝗶𝗿 𝗿𝗲𝗾𝘂𝗶𝗿𝗲𝗺𝗲𝗻𝘁𝘀.\n\nThe Soroptimist International of Guagua also paid a courtesy call to GNC President Madam Geraldine G. Lim to formally discuss and coordinate this initiative, strengthening their partnership in providing educational opportunities and empowering young women in the community.\n\nDon’t miss this opportunity, GNCian ladies! 💜', 109, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1091777606936925', 0, NULL, '2026-10-06 02:59:30', '2026-10-09 16:29:02'),
('403969556396264_1093669503414402', 'DANCESPORT & DANCE TROUPE AUDITION! \n\nWe are waiting for you now! Think you’ve got the moves? It’s time to step up and show us what you’ve got!\n\n📅 October 8–9, 2026\n⏰ 1:00 PM – 6:00 PM\n📍 GNCI Covered Courts Stage\n\nWhat to prepare:\n🎵 At least a 2-minute impromptu dance\n👕 Wear an outfit appropriate for your performance — no uniforms', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1093669503414402', 0, NULL, '2026-10-08 06:10:36', '2026-10-11 11:22:56'),
('403969556396264_1094477743333578', '🧠 𝗠𝗘𝗡𝗧𝗔𝗟 𝗛𝗘𝗔𝗟𝗧𝗛 𝗔𝗪𝗔𝗥𝗘𝗡𝗘𝗦𝗦 | 𝗗𝗦𝗪𝗗 𝗪𝗶𝗦𝘂𝗽𝗽𝗼𝗿𝘁 𝗕𝗼𝗼𝘁𝗵\n\nThe Department of Social Welfare and Development (DSWD) Field Office III – Crisis Intervention Section is now stationed near the GNC facade in celebration of World Mental Health Day 2026.\n\nAll GNC students are invited to visit their booth to learn more about mental health awareness, emotional well-being, and available psychosocial support services.\n\n💙 Your mental health matters. Drop by the DSWD WiSupport Booth today!', 125, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1094477743333578', 0, NULL, '2026-10-09 03:47:48', '2026-10-13 05:45:39'),
('403969556396264_1096609066453779', '📢 𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧 | 𝗖𝗘𝗡𝗧𝗥𝗔𝗟 𝗦𝗧𝗨𝗗𝗘𝗡𝗧 𝗖𝗢𝗨𝗡𝗖𝗜𝗟 𝗦𝗨𝗥𝗩𝗘𝗬\n\nAs part of the process of establishing the Central Student Council (CSC) of the College Department, the Office of Student Affairs 𝗶𝗻𝘃𝗶𝘁𝗲𝘀 𝗮𝗹𝗹 𝗰𝗼𝗹𝗹𝗲𝗴𝗲 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀 𝘁𝗼 𝗮𝗻𝘀𝘄𝗲𝗿 𝗮 𝘀𝗵𝗼𝗿𝘁 𝘀𝘂𝗿𝘃𝗲𝘆.\n\nThis survey aims to identify students interested in running for or joining the CSC this school year. It will also help us gain a bigger picture of what the student body expects from the future Student Council, including your needs, concerns, and aspirations for student representation and leadership.\n\n𝗬𝗼𝘂𝗿 𝗿𝗲𝘀𝗽𝗼𝗻𝘀𝗲𝘀 𝘄𝗶𝗹𝗹 𝗵𝗲𝗹𝗽 𝗴𝘂𝗶𝗱𝗲 𝘁𝗵𝗲 𝗳𝗼𝗿𝗺𝗮𝘁𝗶𝗼𝗻 𝗼𝗳 𝗮 𝗰𝗼𝘂𝗻𝗰𝗶𝗹 𝘁𝗵𝗮𝘁 𝗴𝗲𝗻𝘂𝗶𝗻𝗲𝗹𝘆 𝗿𝗲𝗽𝗿𝗲𝘀𝗲𝗻𝘁𝘀 𝘁𝗵𝗲 𝘀𝘁𝘂𝗱𝗲𝗻𝘁𝘀 𝗮𝗻𝗱 𝗿𝗲𝘀𝗽𝗼𝗻𝗱𝘀 𝘁𝗼 𝘁𝗵𝗲𝗶𝗿 𝘃𝗼𝗶𝗰𝗲𝘀.\n\n📝 𝗦𝘂𝗿𝘃𝗲𝘆 𝗟𝗶𝗻𝗸:\nhttps://forms.gle/jH2W8hXTkZyA8Aqe9\nhttps://forms.gle/jH2W8hXTkZyA8Aqe9\nhttps://forms.gle/jH2W8hXTkZyA8Aqe9\n\n𝘛𝘩𝘦 𝘴𝘶𝘳𝘷𝘦𝘺 𝘵𝘢𝘬𝘦𝘴 𝘰𝘯𝘭𝘺 3–5 𝘮𝘪𝘯𝘶𝘵𝘦𝘴 𝘵𝘰 𝘤𝘰𝘮𝘱𝘭𝘦𝘵𝘦. 𝘈𝘭𝘭 𝘳𝘦𝘴𝘱𝘰𝘯𝘴𝘦𝘴 𝘸𝘪𝘭𝘭 𝘣𝘦 𝘬𝘦𝘱𝘵 𝘤𝘰𝘯𝘧𝘪𝘥𝘦𝘯𝘵𝘪𝘢𝘭 𝘪𝘯 𝘢𝘤𝘤𝘰𝘳𝘥𝘢𝘯𝘤𝘦 𝘸𝘪𝘵𝘩 𝘵𝘩𝘦 𝘋𝘢𝘵𝘢 𝘗𝘳𝘪𝘷𝘢𝘤𝘺 𝘈𝘤𝘵 𝘰𝘧 2012.\n\n𝗡𝗢𝗧𝗘: 𝗦𝘁𝘂𝗱𝗲𝗻𝘁𝘀 𝘄𝗵𝗼 𝗵𝗮𝘃𝗲 𝗮𝗹𝗿𝗲𝗮𝗱𝘆 𝗮𝗻𝘀𝘄𝗲𝗿𝗲𝗱 𝘁𝗵𝗲 𝘀𝘂𝗿𝘃𝗲𝘆 𝗮𝗿𝗲 𝗻𝗼 𝗹𝗼𝗻𝗴𝗲𝗿 𝗿𝗲𝗾𝘂𝗶𝗿𝗲𝗱 𝘁𝗼 𝘀𝘂𝗯𝗺𝗶𝘁 𝗮𝗻𝗼𝘁𝗵𝗲𝗿 𝗿𝗲𝘀𝗽𝗼𝗻𝘀𝗲.\n\nYour participation is highly encouraged. Let your voice be heard and help us shape a Student Council that represents YOU! ✨\n\nThank you!', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1096609066453779', 1, '2026-10-15 04:15:33', '2026-10-11 11:53:36', '2026-10-15 11:41:13'),
('403969556396264_1097472023034150', '📣𝗔𝗡𝗡𝗢𝗨𝗡𝗖𝗘𝗠𝗘𝗡𝗧\n\nPlease be informed of the following schedule:\n\n✅Morning Classes (October 14, 2026): Called off to accommodate the First and Second Lecture Series.\n✅Afternoon Classes: Will resume as scheduled.\n\nReminder: All teaching and non-teaching personnel are expected to attend their respective lecture series. Only teacher-coaches participating in the ongoing Zonal Meet are excused.\n\nThank you, and please be guided accordingly. ✨', 155, 4, 0, 'https://www.facebook.com/1009975871783766/posts/1097472023034150', 1, '2026-10-13 05:53:13', '2026-10-12 09:30:58', '2026-10-15 11:41:13'),
('403969556396264_1098322982949054', 'Congratulations! ✨', NULL, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1098322982949054', 0, NULL, '2026-10-13 06:06:08', '2026-10-15 11:41:13');
INSERT INTO `fb_posts_cache` (`fb_post_id`, `message`, `image_media_id`, `category_id`, `category_locked`, `permalink_url`, `is_pinned`, `pinned_at`, `created_time`, `fetched_at`) VALUES
('403969556396264_1099110659536953', '📸 𝗛𝗔𝗣𝗣𝗘𝗡𝗜𝗡𝗚 𝗡𝗢𝗪 | 𝗙𝗶𝗿𝘀𝘁 𝗟𝗲𝗰𝘁𝘂𝗿𝗲 𝗦𝗲𝗿𝗶𝗲𝘀\n\nGuagua National Colleges, Inc. is currently conducting its First Lecture Series on 𝗕𝗮𝘀𝗶𝗰 𝗼𝗳 𝗖𝗮𝗽𝗶𝘁𝗮𝗹 𝗠𝗮𝗿𝗸𝗲𝘁, featuring guest speaker 𝗠𝗿. 𝗟𝘂𝗶𝘀 𝗚𝗲𝗿𝗮𝗿𝗱𝗼 𝗔. 𝗟𝗶𝗺𝗹𝗶𝗻𝗴𝗮𝗻. \n\nHeld at Bulwagang Alumni on October 14, 2026, the lecture brings together the institution’s non-teaching personnel and fourth-year BSBA-FM and BSA students for a meaningful learning session focused on capital markets and financial literacy.\n\nThis initiative reflects GNC’s continuing commitment to professional development, lifelong learning, and the enrichment of knowledge among its academic community.', 178, NULL, 0, 'https://www.facebook.com/1009975871783766/posts/1099110659536953', 1, '2026-10-15 04:16:04', '2026-10-14 02:21:15', '2026-10-15 11:41:13'),
('403969556396264_1099113709536648', '📸 𝗛𝗔𝗣𝗣𝗘𝗡𝗜𝗡𝗚 𝗡𝗢𝗪 | 𝗦𝗲𝗰𝗼𝗻𝗱 𝗟𝗲𝗰𝘁𝘂𝗿𝗲 𝗦𝗲𝗿𝗶𝗲𝘀\n\nGuagua National Colleges, Inc. is simultaneously conducting its Second Lecture Series, featuring a 𝗟𝗶𝘁𝗲𝗿𝗮𝘁𝘂𝗿𝗲 𝗥𝗲𝘃𝗶𝗲𝘄 𝗪𝗼𝗿𝗸𝘀𝗵𝗼𝗽 led by guest speaker 𝗧𝗿𝘂𝘀𝘁𝗲𝗲 𝗠𝗮𝗿𝗶𝗮 𝗖𝗿𝗶𝘀𝘁𝗶𝗻𝗮 𝗔. 𝗟𝗶𝗺𝗹𝗶𝗻𝗴𝗮𝗻.\n\nTaking place at Gozo Hall on October 14, 2026, the session brings together the institution’s teaching personnel and fourth-year BEEd and BSEd students to strengthen their understanding of literature review and enhance their research knowledge and skills.\n\nThrough initiatives like this, GNC continues to promote academic excellence, research development, and continuous professional growth among its educators and future teachers.', 177, 2, 0, 'https://www.facebook.com/1009975871783766/posts/1099113709536648', 1, '2026-10-15 04:15:30', '2026-10-14 02:24:44', '2026-10-15 11:41:13'),
('403969556396264_1595734318713399', 'Batch \"Lumina\" 2029', 124, NULL, 0, 'https://www.facebook.com/1009975871783766/videos/1595734318713399', 0, NULL, '2026-10-09 06:22:33', '2026-10-13 16:20:02'),
('403969556396264_1849893476177384', 'Cheer Dance Competition\nOctober 2, 2026\n\nTheme:\nWith Skills and Character, Sports is Power', 101, 2, 0, 'https://www.facebook.com/1009975871783766/videos/1849893476177384', 0, NULL, '2026-10-02 07:08:10', '2026-10-04 15:34:24'),
('403969556396264_975893022239205', 'Batch \"Lumina\" 2029', 126, NULL, 0, 'https://www.facebook.com/1009975871783766/videos/975893022239205', 0, NULL, '2026-10-09 02:45:51', '2026-10-12 09:22:54');

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

--
-- Dumping data for table `hero_slides`
--

INSERT INTO `hero_slides` (`slide_id`, `media_type`, `media_path`, `media_path_mobile`, `poster_path`, `title`, `subtitle`, `btn1_text`, `btn1_link`, `btn2_text`, `btn2_link`, `display_order`, `status`, `show_on_mobile`, `show_gradient`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'image', '/uploads/hero-slides/5b167fcf014d911cafbf17d00e070e78.png', '/uploads/hero-slides/e4db29ad3d944240ab8d0b55fba20ae2.png', NULL, 'EMPOWERING MIND. INSPIRING FUTURES.', 'A Legacy of academic excellence, character formation, and service to the community since 1918.', 'ENROLL NOW', NULL, 'EXPLORE GNC', '#about', 1, 'published', 1, 1, NULL, '2026-07-04 13:46:58', '2026-10-13 12:36:36'),
(2, 'video', '/uploads/hero-slides/04148a8e0f51b0b9b9bef2ff6341d71b.mp4', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 'published', 1, 1, NULL, '2026-07-04 13:46:58', '2026-10-09 16:19:46');

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

--
-- Dumping data for table `homepage_president`
--

INSERT INTO `homepage_president` (`id`, `name`, `title`, `org`, `eyebrow`, `heading`, `greeting`, `message`, `media_id`, `updated_by`, `updated_at`) VALUES
(1, 'Geraldine G. Lim, MBA', 'PRESIDENT', 'Guagua National Colleges, Inc.', 'A Warm Welcome to', 'Our New Students', 'Welcome GNCians!', '\"Congratulations for choosing Guagua National Colleges, Inc. to take care of you and your education.\r\n\r\nGuagua National Colleges, Inc. boast of 100 plus years of giving quality education.\"', 157, 1, '2026-10-14 03:29:26');

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

--
-- Dumping data for table `homepage_text`
--

INSERT INTO `homepage_text` (`text_key`, `value`, `media_id`, `updated_by`, `updated_at`) VALUES
('calendar_heading', 'School', NULL, 1, '2026-10-14 09:41:57'),
('news_subheading', 'Stay Updated,\nStay Informed', NULL, 1, '2026-10-15 03:42:50');

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

--
-- Dumping data for table `media_library`
--

INSERT INTO `media_library` (`media_id`, `file_name`, `file_type`, `file_size`, `file_path`, `created_by`, `created_at`) VALUES
(1, '3777bcb84f74d77d5057c79dc7c77a0c.jpg', 'jpg', 323927, '/uploads/announcements/3777bcb84f74d77d5057c79dc7c77a0c.jpg', 1, '2026-06-30 09:47:40'),
(2, '1fe723e5241f872a1adb90b1a74eee6a.jpg', 'jpg', 29380, '/uploads/announcements/1fe723e5241f872a1adb90b1a74eee6a.jpg', 1, '2026-07-01 00:24:12'),
(3, 'e362d3d63097931111810c705eadaf50.jpg', 'jpg', 27381, '/uploads/announcements/e362d3d63097931111810c705eadaf50.jpg', 1, '2026-07-01 00:27:21'),
(4, '29b7408071a503d59e6a04580e153cfe.jpg', 'jpg', 27783, '/uploads/announcements/29b7408071a503d59e6a04580e153cfe.jpg', 1, '2026-07-01 00:27:32'),
(5, '574f641a20cb7e91cdfcae7a4ccaf68f.png', 'png', 1598078, '/uploads/announcements/574f641a20cb7e91cdfcae7a4ccaf68f.png', 1, '2026-07-01 02:19:16'),
(6, '5f52255e47cf0fa3ae6c4efe9a3f9fe3.jpg', 'jpg', 337513, '/uploads/announcements/5f52255e47cf0fa3ae6c4efe9a3f9fe3.jpg', 1, '2026-07-01 03:34:22'),
(7, 'fb_403969556396264_1015705421210811.jpg', 'jpg', 55455, '/uploads/facebook/fb_403969556396264_1015705421210811.jpg', NULL, '2026-07-11 14:02:59'),
(8, 'fb_403969556396264_1015694684545218.jpg', 'jpg', 49269, '/uploads/facebook/fb_403969556396264_1015694684545218.jpg', NULL, '2026-07-11 14:02:59'),
(9, 'fb_403969556396264_1015656651215688.jpg', 'jpg', 54477, '/uploads/facebook/fb_403969556396264_1015656651215688.jpg', NULL, '2026-07-11 14:02:59'),
(10, 'fb_403969556396264_1014317058016314.jpg', 'jpg', 44188, '/uploads/facebook/fb_403969556396264_1014317058016314.jpg', NULL, '2026-07-11 14:02:59'),
(11, '3d72e8e53119dc1dc12e827bfb45ac58.png', 'png', 797719, '/uploads/announcements/3d72e8e53119dc1dc12e827bfb45ac58.png', 1, '2026-07-11 14:10:15'),
(12, 'f9cf7f6a2ac699bd8ac81cf49aba2ab0.jpg', 'jpg', 682944, '/uploads/announcements/f9cf7f6a2ac699bd8ac81cf49aba2ab0.jpg', 1, '2026-07-11 14:10:57'),
(13, 'fb_403969556396264_1018449440936409.jpg', 'jpg', 55048, '/uploads/facebook/fb_403969556396264_1018449440936409.jpg', NULL, '2026-07-13 03:28:41'),
(14, 'fb_403969556396264_1020616830719670.jpg', 'jpg', 76974, '/uploads/facebook/fb_403969556396264_1020616830719670.jpg', NULL, '2026-07-15 12:27:59'),
(15, 'fb_403969556396264_1020499394064747.jpg', 'jpg', 86622, '/uploads/facebook/fb_403969556396264_1020499394064747.jpg', NULL, '2026-07-15 12:27:59'),
(16, 'fb_403969556396264_1020486924065994.jpg', 'jpg', 52682, '/uploads/facebook/fb_403969556396264_1020486924065994.jpg', NULL, '2026-07-15 12:27:59'),
(17, 'fb_403969556396264_1020419287406091.jpg', 'jpg', 79707, '/uploads/facebook/fb_403969556396264_1020419287406091.jpg', NULL, '2026-07-15 12:27:59'),
(18, 'fb_403969556396264_1021286340652719.jpg', 'jpg', 60845, '/uploads/facebook/fb_403969556396264_1021286340652719.jpg', NULL, '2026-07-16 07:08:18'),
(19, '3a44a1075a16898bf1e630c38e722144.jpg', 'jpg', 299013, '/uploads/announcements/3a44a1075a16898bf1e630c38e722144.jpg', 1, '2026-07-16 14:46:53'),
(20, 'a0d8dd28f06398b283a55dc31ea155c5.jpg', 'jpg', 90824, '/uploads/announcements/a0d8dd28f06398b283a55dc31ea155c5.jpg', 1, '2026-07-16 16:45:11'),
(21, '0455046db454a2679380309529030c3c.jpg', 'jpg', 254308, '/uploads/announcements/0455046db454a2679380309529030c3c.jpg', 1, '2026-07-16 23:40:19'),
(22, 'c7811ba562a870e77188dab1da006fd2.jpg', 'jpg', 90824, '/uploads/announcements/c7811ba562a870e77188dab1da006fd2.jpg', 1, '2026-07-17 04:21:04'),
(23, '9a64fd225bd7fd2f138a8199c098a07a.jpg', 'jpg', 104622, '/uploads/announcements/9a64fd225bd7fd2f138a8199c098a07a.jpg', 1, '2026-07-17 04:21:43'),
(24, 'eb18ac258ae3e9c453903744436afd07.jpg', 'jpg', 106016, '/uploads/announcements/eb18ac258ae3e9c453903744436afd07.jpg', 1, '2026-07-17 04:23:46'),
(25, '5086847ac0bd8f198461e84cf747fd6b.jpg', 'jpg', 106016, '/uploads/announcements/5086847ac0bd8f198461e84cf747fd6b.jpg', 1, '2026-07-17 06:34:51'),
(26, 'fb_403969556396264_1035954229185930.jpg', 'jpg', 84987, '/uploads/facebook/fb_403969556396264_1035954229185930.jpg', NULL, '2026-08-03 08:08:36'),
(27, 'fb_403969556396264_1036375882477098.jpg', 'jpg', 61468, '/uploads/facebook/fb_403969556396264_1036375882477098.jpg', NULL, '2026-08-03 13:35:13'),
(28, 'fb_403969556396264_1036891585758861.jpg', 'jpg', 216529, '/uploads/facebook/fb_403969556396264_1036891585758861.jpg', NULL, '2026-08-04 11:22:38'),
(29, 'fb_403969556396264_1038288982285788.jpg', 'jpg', 46706, '/uploads/facebook/fb_403969556396264_1038288982285788.jpg', NULL, '2026-08-06 07:21:03'),
(30, 'fb_403969556396264_1038918485556171.jpg', 'jpg', 46706, '/uploads/facebook/fb_403969556396264_1038918485556171.jpg', NULL, '2026-08-06 10:52:24'),
(31, 'fb_403969556396264_1038865048894848.jpg', 'jpg', 41066, '/uploads/facebook/fb_403969556396264_1038865048894848.jpg', NULL, '2026-08-06 10:52:24'),
(32, 'fb_403969556396264_1043093625138657.jpg', 'jpg', 46831, '/uploads/facebook/fb_403969556396264_1043093625138657.jpg', NULL, '2026-08-12 08:37:35'),
(33, 'fb_403969556396264_1042378795210140.jpg', 'jpg', 46831, '/uploads/facebook/fb_403969556396264_1042378795210140.jpg', NULL, '2026-08-12 08:37:35'),
(34, 'fb_403969556396264_1042007068580646.jpg', 'jpg', 44246, '/uploads/facebook/fb_403969556396264_1042007068580646.jpg', NULL, '2026-08-12 08:37:35'),
(35, 'fb_403969556396264_1041429128638440.jpg', 'jpg', 50047, '/uploads/facebook/fb_403969556396264_1041429128638440.jpg', NULL, '2026-08-12 08:37:35'),
(36, 'fb_403969556396264_1044023491712337.jpg', 'jpg', 45994, '/uploads/facebook/fb_403969556396264_1044023491712337.jpg', NULL, '2026-08-12 14:26:04'),
(37, 'fb_403969556396264_1045769208204432.jpg', 'jpg', 46831, '/uploads/facebook/fb_403969556396264_1045769208204432.jpg', NULL, '2026-08-14 12:04:00'),
(38, 'fb_403969556396264_1049153111199375.jpg', 'jpg', 66868, '/uploads/facebook/fb_403969556396264_1049153111199375.jpg', NULL, '2026-08-18 09:35:13'),
(39, 'fb_403969556396264_1048796227901730.jpg', 'jpg', 46706, '/uploads/facebook/fb_403969556396264_1048796227901730.jpg', NULL, '2026-08-18 09:35:13'),
(40, 'fb_403969556396264_1048273411287345.jpg', 'jpg', 45995, '/uploads/facebook/fb_403969556396264_1048273411287345.jpg', NULL, '2026-08-18 09:35:13'),
(41, 'fb_403969556396264_1047623121352374.jpg', 'jpg', 46831, '/uploads/facebook/fb_403969556396264_1047623121352374.jpg', NULL, '2026-08-18 09:35:13'),
(42, 'fb_403969556396264_1049979204450099.jpg', 'jpg', 73824, '/uploads/facebook/fb_403969556396264_1049979204450099.jpg', NULL, '2026-08-19 11:20:14'),
(43, 'fb_403969556396264_1050841237697229.jpg', 'jpg', 72990, '/uploads/facebook/fb_403969556396264_1050841237697229.jpg', NULL, '2026-08-20 10:02:09'),
(44, 'fb_403969556396264_1051546377626715.jpg', 'jpg', 87481, '/uploads/facebook/fb_403969556396264_1051546377626715.jpg', NULL, '2026-08-21 04:11:53'),
(45, 'fb_403969556396264_1051725190942167.jpg', 'jpg', 70310, '/uploads/facebook/fb_403969556396264_1051725190942167.jpg', NULL, '2026-08-21 13:25:50'),
(46, 'fb_403969556396264_1053401580774528.jpg', 'jpg', 70487, '/uploads/facebook/fb_403969556396264_1053401580774528.jpg', NULL, '2026-08-23 10:36:09'),
(47, 'fb_403969556396264_1052563940858292.jpg', 'jpg', 71639, '/uploads/facebook/fb_403969556396264_1052563940858292.jpg', NULL, '2026-08-23 10:36:09'),
(48, 'fb_403969556396264_1054393764008643.jpg', 'jpg', 72762, '/uploads/facebook/fb_403969556396264_1054393764008643.jpg', NULL, '2026-08-24 12:24:04'),
(49, '1ea7a2520ba1f3c5ee5f11ce791202d2.png', 'png', 5688235, '/uploads/news/1ea7a2520ba1f3c5ee5f11ce791202d2.png', 1, '2026-08-25 08:57:17'),
(50, 'fb_403969556396264_1055223623925657.jpg', 'jpg', 80240, '/uploads/facebook/fb_403969556396264_1055223623925657.jpg', NULL, '2026-08-25 15:43:34'),
(51, 'fb_403969556396264_1057694423678577.jpg', 'jpg', 68409, '/uploads/facebook/fb_403969556396264_1057694423678577.jpg', NULL, '2026-08-28 09:22:05'),
(52, 'fb_403969556396264_1057037967077556.jpg', 'jpg', 77013, '/uploads/facebook/fb_403969556396264_1057037967077556.jpg', NULL, '2026-08-28 09:22:05'),
(53, 'fb_403969556396264_1058494193598600.jpg', 'jpg', 75910, '/uploads/facebook/fb_403969556396264_1058494193598600.jpg', NULL, '2026-08-30 05:28:15'),
(54, 'fb_403969556396264_1057858256995527.jpg', 'jpg', 69752, '/uploads/facebook/fb_403969556396264_1057858256995527.jpg', NULL, '2026-08-30 05:28:15'),
(55, 'bac7c1199f9d80c17080018d5bdc5489.jpg', 'jpg', 228592, '/uploads/announcements/bac7c1199f9d80c17080018d5bdc5489.jpg', 1, '2026-08-30 06:31:01'),
(56, 'fb_403969556396264_1060034363444583.jpg', 'jpg', 78047, '/uploads/facebook/fb_403969556396264_1060034363444583.jpg', NULL, '2026-08-31 14:59:22'),
(57, 'fb_403969556396264_1059914310123255.jpg', 'jpg', 102363, '/uploads/facebook/fb_403969556396264_1059914310123255.jpg', NULL, '2026-08-31 14:59:22'),
(58, 'fb_403969556396264_1061889839925702.jpg', 'jpg', 79289, '/uploads/facebook/fb_403969556396264_1061889839925702.jpg', NULL, '2026-09-02 10:56:39'),
(59, 'fb_403969556396264_1067654666015886.jpg', 'jpg', 79165, '/uploads/facebook/fb_403969556396264_1067654666015886.jpg', NULL, '2026-09-09 06:26:16'),
(60, 'fb_403969556396264_1065225529592133.jpg', 'jpg', 81169, '/uploads/facebook/fb_403969556396264_1065225529592133.jpg', NULL, '2026-09-09 06:26:16'),
(61, 'fb_403969556396264_1071140179000668.jpg', 'jpg', 69462, '/uploads/facebook/fb_403969556396264_1071140179000668.jpg', NULL, '2026-09-14 05:15:06'),
(62, 'fb_403969556396264_1066204736160879.jpg', 'jpg', 77620, '/uploads/facebook/fb_403969556396264_1066204736160879.jpg', NULL, '2026-09-14 05:15:06'),
(63, 'fb_403969556396264_1078492421598777.jpg', 'jpg', 78723, '/uploads/facebook/fb_403969556396264_1078492421598777.jpg', NULL, '2026-09-22 07:35:16'),
(64, 'fb_403969556396264_1079535318161154.jpg', 'jpg', 71419, '/uploads/facebook/fb_403969556396264_1079535318161154.jpg', NULL, '2026-09-23 14:13:51'),
(66, 'cased-logo.svg', 'svg', 0, '/assets/images/logos/cased-logo.svg', NULL, '2026-09-24 03:37:24'),
(67, 'cba-logo.svg', 'svg', 0, '/assets/images/logos/cba-logo.svg', NULL, '2026-09-24 03:37:24'),
(68, 'coe-logo.svg', 'svg', 0, '/assets/images/logos/coe-logo.svg', NULL, '2026-09-24 03:37:24'),
(69, 'camp-logo.svg', 'svg', 0, '/assets/images/logos/camp-logo.svg', NULL, '2026-09-24 03:37:24'),
(70, 'coa-logo.svg', 'svg', 0, '/assets/images/logos/coa-logo.svg', NULL, '2026-09-24 03:37:24'),
(71, 'con-logo.svg', 'svg', 0, '/assets/images/logos/con-logo.svg', NULL, '2026-09-24 03:37:24'),
(81, '6883f2e2-3c85-4667-a390-9a5c04f98b39 (2).jpg', 'jpg', 99024, '/uploads/department-deans/02f1a98d8f3186717147cc353ca43d70.jpg', 1, '2026-09-27 05:12:16'),
(82, 'oic-sacdalan.png', 'png', 122277, '/uploads/department-deans/e0494110879608d18edb4ea3de467fc2.png', 1, '2026-09-27 05:12:41'),
(83, 'sir-capati.png', 'png', 259298, '/uploads/department-deans/7794da3c0199876ac96e96070db6fa10.png', 1, '2026-09-27 05:12:51'),
(84, 'sir-isip.png', 'png', 554668, '/uploads/department-deans/5146415fefc7760c34cf57a770b95e9b.png', 1, '2026-09-27 05:13:00'),
(85, 'maam-bernardo.png', 'png', 395101, '/uploads/department-deans/27e8161c6e0b62ca259f1f2e587229f8.png', 1, '2026-09-27 05:13:18'),
(88, 'fb_403969556396264_1083383604442992.jpg', 'jpg', 85630, '/uploads/facebook/fb_403969556396264_1083383604442992.jpg', NULL, '2026-09-27 10:52:40'),
(89, 'f579b227126a3ea532d4e111122bfcb6.png', 'png', 1806317, '/uploads/announcements/f579b227126a3ea532d4e111122bfcb6.png', 1, '2026-09-27 12:41:40'),
(90, 'fb_403969556396264_1084330121015007.jpg', 'jpg', 89923, '/uploads/facebook/fb_403969556396264_1084330121015007.jpg', NULL, '2026-09-28 12:51:42'),
(91, 'fb_403969556396264_1084562407658445.jpg', 'jpg', 100955, '/uploads/facebook/fb_403969556396264_1084562407658445.jpg', NULL, '2026-09-28 14:33:57'),
(92, 'fb_403969556396264_1085054784275874.jpg', 'jpg', 194666, '/uploads/facebook/fb_403969556396264_1085054784275874.jpg', NULL, '2026-09-29 11:52:00'),
(93, 'fb_403969556396264_1084936617621024.jpg', 'jpg', 157139, '/uploads/facebook/fb_403969556396264_1084936617621024.jpg', NULL, '2026-09-29 11:52:00'),
(94, 'fb_403969556396264_1086044574176895.jpg', 'jpg', 78723, '/uploads/facebook/fb_403969556396264_1086044574176895.jpg', NULL, '2026-09-30 13:08:31'),
(95, 'd5e9172a84d904782ec22e88c16b166e.png', 'png', 3832116, '/uploads/announcements/d5e9172a84d904782ec22e88c16b166e.png', 1, '2026-09-30 14:00:05'),
(96, '7a92b6dd1143f04ecd0783a3f78e09ff.png', 'png', 3832116, '/uploads/announcements/7a92b6dd1143f04ecd0783a3f78e09ff.png', 1, '2026-09-30 14:00:18'),
(97, '675592939de0c9ab33d259f7684f9475.png', 'png', 3832116, '/uploads/announcements/675592939de0c9ab33d259f7684f9475.png', 1, '2026-09-30 14:02:09'),
(101, 'fb_403969556396264_1849893476177384.jpg', 'jpg', 516913, '/uploads/facebook/fb_403969556396264_1849893476177384.jpg', NULL, '2026-10-02 12:15:01'),
(102, 'fb_403969556396264_1089091540538865.jpg', 'jpg', 70693, '/uploads/facebook/fb_403969556396264_1089091540538865.jpg', NULL, '2026-10-03 05:33:32'),
(103, 'fb_403969556396264_1089079430540076.jpg', 'jpg', 97202, '/uploads/facebook/fb_403969556396264_1089079430540076.jpg', NULL, '2026-10-03 05:33:32'),
(104, 'fb_403969556396264_1090971760350843.jpg', 'jpg', 70261, '/uploads/facebook/fb_403969556396264_1090971760350843.jpg', NULL, '2026-10-05 11:52:18'),
(109, 'fb_403969556396264_1091777606936925.jpg', 'jpg', 90750, '/uploads/facebook/fb_403969556396264_1091777606936925.jpg', NULL, '2026-10-06 05:13:05'),
(111, 'cased-logo.svg', 'svg', 277862, '/uploads/programs/66478ee8ed0d9cb1d1104a4ed1221c01.svg', 1, '2026-10-07 05:10:21'),
(112, '1f4ce0cf1780ff729f7c951208ee1945.jpg', 'jpg', 1342443, '/uploads/news/1f4ce0cf1780ff729f7c951208ee1945.jpg', 1, '2026-10-08 03:00:00'),
(113, 'ec98b1db5aef595f303fb354b3580708.jpg', 'jpg', 317135, '/uploads/news/ec98b1db5aef595f303fb354b3580708.jpg', 1, '2026-10-08 03:44:02'),
(114, '8ca72ce9fb5a7e7fb4c3350377c67290.jpg', 'jpg', 61447, '/uploads/news/8ca72ce9fb5a7e7fb4c3350377c67290.jpg', 1, '2026-10-08 03:44:02'),
(115, '65e5f6d0c12f6efbe27ff52062fc8b38.jpg', 'jpg', 49396, '/uploads/announcements/65e5f6d0c12f6efbe27ff52062fc8b38.jpg', 1, '2026-10-08 04:23:42'),
(116, '845b448587cc15f38b25ee906a344acd.jpg', 'jpg', 98681, '/uploads/announcements/845b448587cc15f38b25ee906a344acd.jpg', 1, '2026-10-08 04:38:21'),
(117, '7ed410854ad0858e2d83ccd2bd2aecf6.jpg', 'jpg', 66952, '/uploads/announcements/7ed410854ad0858e2d83ccd2bd2aecf6.jpg', 1, '2026-10-08 04:38:21'),
(118, '30de64cf61318cc0aae4875e033469ca.jpg', 'jpg', 60599, '/uploads/news/30de64cf61318cc0aae4875e033469ca.jpg', 1, '2026-10-08 04:41:51'),
(119, '981d974656777af17d2cb12e41b3e851.png', 'png', 7253216, '/uploads/news/981d974656777af17d2cb12e41b3e851.png', 1, '2026-10-08 04:41:51'),
(120, '2ca98d2fcec3366ac86cd26e01e2b4f5.jpg', 'jpg', 58050, '/uploads/news/2ca98d2fcec3366ac86cd26e01e2b4f5.jpg', 1, '2026-10-08 04:41:51'),
(121, '35755b7a791aec3954fea8684326f31d.png', 'png', 1809720, '/uploads/news/35755b7a791aec3954fea8684326f31d.png', 1, '2026-10-08 04:42:38'),
(122, '77bd3f9d1979d9c0f44b2b572b3e4680.jpg', 'jpg', 146769, '/uploads/news/77bd3f9d1979d9c0f44b2b572b3e4680.jpg', 1, '2026-10-08 04:42:38'),
(123, '16316d00a119392a206bcd9be04771d3.jpg', 'jpg', 105780, '/uploads/news/16316d00a119392a206bcd9be04771d3.jpg', 1, '2026-10-08 04:43:08'),
(124, 'fb_403969556396264_1595734318713399.jpg', 'jpg', 399515, '/uploads/facebook/fb_403969556396264_1595734318713399.jpg', NULL, '2026-10-09 10:09:45'),
(125, 'fb_403969556396264_1094477743333578.jpg', 'jpg', 132931, '/uploads/facebook/fb_403969556396264_1094477743333578.jpg', NULL, '2026-10-09 10:09:45'),
(126, 'fb_403969556396264_975893022239205.jpg', 'jpg', 373188, '/uploads/facebook/fb_403969556396264_975893022239205.jpg', NULL, '2026-10-09 10:09:45'),
(128, 'Studying-amico 1.svg', 'svg', 173700, '/uploads/programs/d2e2d669fca1b46fe50bad70fc60dc9e.svg', 1, '2026-10-09 10:47:44'),
(129, 'Learning-amico 2.svg', 'svg', 305298, '/uploads/programs/3dbe2a0aaa445d2e6c46d62b3a802004.svg', 1, '2026-10-09 10:47:44'),
(130, 'Kindergarten student-amico 1.svg', 'svg', 253735, '/uploads/programs/2b0703e21a99a52049ccf41ae3e0575d.svg', 1, '2026-10-09 10:47:44'),
(131, 'college class-amico 1.svg', 'svg', 748709, '/uploads/programs/31c99631551f115bc77dff133fac0d6a.svg', 1, '2026-10-09 10:47:45'),
(132, 'Education-amico 1.svg', 'svg', 293149, '/uploads/programs/d7e45c7e00332d5b10607cfa31606a73.svg', 1, '2026-10-09 10:47:45'),
(133, 'college entrance exam-amico 1.svg', 'svg', 290989, '/uploads/programs/6e3fc5e49b2c34cfa49982f256acbbc1.svg', 1, '2026-10-09 10:47:45'),
(134, 'Rectangle 46.png', 'png', 53849, '/uploads/programs/edbf1bbe14d359a12a4acf787f920790.png', 1, '2026-10-09 11:19:54'),
(135, 'Rectangle 47.png', 'png', 63190, '/uploads/programs/5ebcc01fc73ecb0f0aa441f64ab4ed00.png', 1, '2026-10-09 11:19:54'),
(136, 'Rectangle 48.png', 'png', 61884, '/uploads/programs/6323bfac986cd0343f79862394205532.png', 1, '2026-10-09 11:19:54'),
(137, 'Rectangle 49.png', 'png', 38169, '/uploads/programs/9f55b36780f5895ae77db7bdc78ee7e8.png', 1, '2026-10-09 11:19:54'),
(139, 'cased-logo.svg', 'svg', 277862, '/uploads/programs/44a12cb6032f78aa167db818aeed4d0f.svg', 1, '2026-10-09 11:22:26'),
(140, '861a0d7cbcd7a3262c6ffb5d147fb6c6.png', 'png', 3832116, '/uploads/news/861a0d7cbcd7a3262c6ffb5d147fb6c6.png', 1, '2026-10-09 15:42:01'),
(141, 'b1e239bc350b94bda6b2bc66ac037901.png', 'png', 398950, '/uploads/news/b1e239bc350b94bda6b2bc66ac037901.png', 1, '2026-10-09 15:46:12'),
(149, 'maam-maris.png', 'png', 576768, '/uploads/department-deans/30b2739ccdbc0e725f67f2c393054494.png', 1, '2026-10-12 08:42:55'),
(155, 'fb_403969556396264_1097472023034150.jpg', 'jpg', 76919, '/uploads/facebook/fb_403969556396264_1097472023034150.jpg', NULL, '2026-10-12 09:53:15'),
(156, 'bc878f2faec4bb65db1969f8bcc2f7e1.jpg', 'jpg', 317135, '/uploads/news/bc878f2faec4bb65db1969f8bcc2f7e1.jpg', 1, '2026-10-12 10:25:10'),
(157, 'maam-president.png', 'png', 875353, '/uploads/homepage/6c1b6258278efcafdd4fd9af5e1e5109.png', 1, '2026-10-12 10:44:58'),
(158, '7.png', 'png', 1365877, '/uploads/programs/f69cf3071149ba02db2d98f5ba729e75.png', 1, '2026-10-12 10:58:54'),
(159, '7.png', 'png', 1365877, '/uploads/programs/3720e685893b3dab39bdf55c9ac0ac78.png', 1, '2026-10-12 10:58:55'),
(160, '14.png', 'png', 927903, '/uploads/programs/bc54eb0721f1e49c1e545cf175ceb5f9.png', 1, '2026-10-12 11:00:36'),
(161, '12.png', 'png', 915395, '/uploads/programs/3ccc9f439e019ca1b0b4b6bf7512727e.png', 1, '2026-10-12 11:00:36'),
(162, '13.png', 'png', 971400, '/uploads/programs/af36d53767e23f39113138a35dd6bc58.png', 1, '2026-10-12 11:00:36'),
(163, '11.png', 'png', 809509, '/uploads/programs/61123019f81eadf35e61252e4adcb8ca.png', 1, '2026-10-12 11:00:36'),
(164, '6.png', 'png', 1015094, '/uploads/programs/17ccda47af6bb7749750cc327b80d35f.png', 1, '2026-10-12 11:00:59'),
(165, '10.png', 'png', 1078219, '/uploads/programs/5a001783a5c06bb8a0bd97edb9edad57.png', 1, '2026-10-12 11:01:14'),
(166, '8.png', 'png', 1304886, '/uploads/programs/a55202b2a390148feb9da9e57d0d4e7c.png', 1, '2026-10-12 11:01:27'),
(167, '9.png', 'png', 1424537, '/uploads/programs/bc56893cd36ed3655d29bbcc9c22330d.png', 1, '2026-10-12 11:01:38'),
(170, 'Department Header v2.png', 'png', 971111, '/uploads/programs/2a94b029460a1a0a256946ff1cdf9bb7.png', 1, '2026-10-12 13:15:48'),
(171, 'Department Header v2.png', 'png', 971111, '/uploads/programs/7da3a8211264930d5fa52752fd8edddc.png', 1, '2026-10-12 13:18:44'),
(172, 'secondary teacher.png', 'png', 463318, '/uploads/programs/0164f784263faaf28ffeef8ca2257355.png', 1, '2026-10-12 13:24:20'),
(173, 'curriculum specialist.png', 'png', 442282, '/uploads/programs/433f44ac6cf27e0ae46d3428c18dc99a.png', 1, '2026-10-12 13:24:20'),
(174, 'academic coor.png', 'png', 635074, '/uploads/programs/e8a9a982d9b2088ae9a95f51650667c0.png', 1, '2026-10-12 13:24:20'),
(175, 'educ and training specialist.png', 'png', 552701, '/uploads/programs/a5354b3d520541a5a65498b829e7be04.png', 1, '2026-10-12 13:24:20'),
(176, 'chairmain-goseco.png', 'png', 101001, '/uploads/administration/469c8a6f71bad14e721fff3c7fd2aa6f.png', 1, '2026-10-13 12:32:45'),
(177, 'fb_403969556396264_1099113709536648.jpg', 'jpg', 59420, '/uploads/facebook/fb_403969556396264_1099113709536648.jpg', NULL, '2026-10-14 03:03:39'),
(178, 'fb_403969556396264_1099110659536953.jpg', 'jpg', 74443, '/uploads/facebook/fb_403969556396264_1099110659536953.jpg', NULL, '2026-10-14 03:03:39'),
(180, 'Part 1 VLSM (1).pdf', 'pdf', 26774, '/uploads/program-pdfs/5152ec5a167c37e18cfcef6ef7207d01.pdf', 1, '2026-10-14 04:37:53'),
(181, 'DSC_3098.JPG', 'jpg', 5810206, '/uploads/programs/1cd457dd70aaed3c526905e09ffcc2ac.jpg', 1, '2026-10-14 04:52:51'),
(182, 'DSC_3686.JPG', 'jpg', 5592486, '/uploads/programs/88c6c3b9f22322717e8e6bee2bdac32a.jpg', 1, '2026-10-14 04:52:51'),
(183, 'DSC_3743.JPG', 'jpg', 6073616, '/uploads/programs/4205acf96e49847ae4bb9711448750ef.jpg', 1, '2026-10-14 04:52:51'),
(184, 'DSC_3755.JPG', 'jpg', 6405964, '/uploads/programs/a9f284cd7be9bfb2b2875e8c96c1b4df.jpg', 1, '2026-10-14 04:52:51'),
(185, 'DSC_6802.JPG', 'jpg', 7470693, '/uploads/programs/99c42bea9f78a4dbe7fe1d7a2c468240.jpg', 1, '2026-10-14 04:52:51'),
(186, 'DSC_6804.JPG', 'jpg', 7077331, '/uploads/programs/a8762782d75de447b0fa6aa8840e2bd9.jpg', 1, '2026-10-14 04:52:51'),
(187, 'hm-cheflab-female.png', 'png', 842289, '/uploads/programs/160478d3205382697fd89d9e0ac44b34.png', 1, '2026-10-14 05:18:15'),
(188, 'hm-cheflab-male.png', 'png', 838011, '/uploads/programs/ff4dd30fe44ffbea232adb270bf6f3eb.png', 1, '2026-10-14 05:18:15'),
(189, 'DSC_3683.JPG', 'jpg', 6726053, '/uploads/programs/cee90790bb1e524fb33e6649c9202b1a.jpg', 1, '2026-10-14 14:38:58'),
(190, 'DSC_3687.JPG', 'jpg', 6927619, '/uploads/programs/33d249e0ce4d6d8031a1341d664ee099.jpg', 1, '2026-10-14 14:38:58'),
(191, 'DSC_3743.JPG', 'jpg', 6073616, '/uploads/programs/46b783b244b5e81bbbddc6406f473d64.jpg', 1, '2026-10-14 14:38:58'),
(192, 'DSC_3755.JPG', 'jpg', 6405964, '/uploads/programs/2a7e36a85227060d77c34d4c8ccc7ac8.jpg', 1, '2026-10-14 14:38:58'),
(193, 'DSC_6802.JPG', 'jpg', 7470693, '/uploads/programs/c26e5cda78c76528295dede0cdc93eb5.jpg', 1, '2026-10-14 14:38:58'),
(194, 'DSC_6804.JPG', 'jpg', 7077331, '/uploads/programs/5122197a55f983023b0c2d4d7bb4d163.jpg', 1, '2026-10-14 14:38:58'),
(196, 'DSC_3683.JPG', 'jpg', 6726053, '/uploads/programs/947e630d5dec339d948f601fd51e1901.jpg', 1, '2026-10-14 14:42:25'),
(197, 'DSC_3687.JPG', 'jpg', 6927619, '/uploads/programs/8889eda88b54970d013c08ba398d6170.jpg', 1, '2026-10-14 14:42:25'),
(198, 'DSC_3743.JPG', 'jpg', 6073616, '/uploads/programs/df6b0fdcd00d146f7563a37cbc8326d0.jpg', 1, '2026-10-14 14:42:25'),
(199, 'DSC_3755.JPG', 'jpg', 6405964, '/uploads/programs/564746698e8dfb8d00928cb73624c8dc.jpg', 1, '2026-10-14 14:42:25'),
(200, 'DSC_6801.JPG', 'jpg', 6855377, '/uploads/programs/ce979a851847e91c9b6d0a51eb5e9af9.jpg', 1, '2026-10-14 14:42:25'),
(201, 'DSC_6804.JPG', 'jpg', 7077331, '/uploads/programs/4a5e2ce1b44998a3fbdb915a3eef3c91.jpg', 1, '2026-10-14 14:42:25'),
(202, 'coe-logo.svg', 'svg', 1082599, '/uploads/programs/c5670342bdee61f07e04afcec1f12e43.svg', 1, '2026-10-15 10:03:40'),
(203, 'coa-logo.svg', 'svg', 344299, '/uploads/programs/c416e7055f288fa39eb206adb871c1fb.svg', 1, '2026-10-15 10:12:30'),
(205, 'cased-logo.svg', 'svg', 277862, '/uploads/departments/fe19b4ef4df0f05708f1fbf1f9b73477.svg', 1, '2026-10-15 10:13:43');

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
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`news_id`, `category_id`, `user_id`, `title`, `slug`, `content`, `author`, `category`, `featured_image`, `status`, `is_pinned`, `pinned_at`, `published_at`, `created_at`, `updated_at`) VALUES
(7, NULL, 1, 'News 123', 'news-123', '<p>It was a good idea. At least, they all thought it was a good idea at the time. Hindsight would reveal that in reality, it was an unbelievably terrible idea, but it would take another week for them to understand that. Right now, at this very moment. they all agreed that it was the perfect course of action for the current situation.</p><p><br></p>', NULL, 'Others', 141, 'draft', 0, NULL, NULL, '2026-10-09 15:46:12', '2026-10-09 15:46:12'),
(8, NULL, 1, 'TESTING', 'testing', '<p>The red glint of paint sparkled under the sun. He had dreamed of owning this car since he was ten, and that dream had become a reality less than a year ago. It was his baby and he spent hours caring for it, pampering it, and fondling over it. She knew this all too well, and that\'s exactly why she had taken a sludge hammer to it.</p><p><br></p>', NULL, 'Others', 156, 'published', 0, NULL, '2026-10-12 10:25:10', '2026-10-12 10:25:10', '2026-10-13 05:53:09');

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

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`page_id`, `title`, `slug`, `description`, `page_type`, `display_order`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Home', 'home', NULL, 'slideshow', 1, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(2, 'About Us', 'about-us', NULL, 'sections', 2, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(3, 'Vision & Mission', 'vision-mission', NULL, 'sections', 3, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(4, 'History', 'history', NULL, 'sections', 4, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(5, 'College Departments', 'college-departments', NULL, 'sections', 5, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(6, 'Programs', 'programs', NULL, 'sections', 6, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(7, 'Student Services', 'student-services', NULL, 'sections', 7, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(8, 'Contact Us', 'contact-us', NULL, 'sections', 8, 'active', NULL, '2026-09-22 07:40:35', '2026-09-22 07:40:35'),
(9, 'Administration', 'administration', NULL, 'sections', 9, 'active', NULL, '2026-10-01 10:37:28', '2026-10-01 10:37:28'),
(10, 'Basic Education Departments', 'basic-education-departments', NULL, 'sections', 10, 'active', NULL, '2026-10-05 11:53:17', '2026-10-05 11:53:17');

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

--
-- Dumping data for table `page_sections`
--

INSERT INTO `page_sections` (`section_id`, `page_id`, `section_key`, `title`, `subtitle`, `content`, `draft_content`, `status`, `review_status`, `submitted_by`, `submitted_at`, `reviewed_by`, `reviewed_at`, `review_note`, `updated_by`, `updated_at`, `media_id`, `display_order`) VALUES
(1, 3, 'vision', 'Vision', NULL, '<p style=\"text-align:justify\">Our graduates will be globally competitive citizens. They will uphold our founding values.</p>', NULL, 'published', 'none', NULL, NULL, 1, '2026-10-11 12:09:11', NULL, 1, '2026-10-11 12:09:11', NULL, 1),
(2, 3, 'mission', 'Mission', NULL, '<p style=\"text-align:justify\">We will be the leading learning institution in Western Pampanga.</p>', NULL, 'published', 'none', NULL, NULL, 1, '2026-10-11 12:09:17', NULL, 1, '2026-10-11 12:09:17', NULL, 2);

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

--
-- Dumping data for table `page_visits`
--

INSERT INTO `page_visits` (`page_id`, `page_url`, `page_title`, `visit_count`, `last_visited`, `created_at`) VALUES
(1, '/', 'Guagua National Colleges', 179, '2026-10-15 19:41:19', '2026-10-01 16:13:02'),
(5, '/pages/academics/college/college-departments.php', 'College Departments', 25, '2026-10-06 23:07:21', '2026-10-01 16:18:54'),
(9, '/pages/academics/college/departments/cased.php', 'College of Arts and Sciences, and Education', 16, '2026-10-13 22:48:18', '2026-10-01 16:32:37'),
(10, '/pages/admission/admission-req.php', 'Admission Requirements', 15, '2026-10-13 23:30:25', '2026-10-01 16:51:47'),
(11, '/pages/news-and-announcement.php', 'News & Announcements', 31, '2026-10-15 12:10:11', '2026-10-01 17:01:58'),
(14, '/pages/academics/college/programs/beed.php', 'Elementary Education', 8, '2026-10-13 15:35:08', '2026-10-01 17:07:17'),
(15, '/pages/about/administration.php', 'Administration', 18, '2026-10-15 18:01:37', '2026-10-01 17:07:30'),
(21, '/pages/about/gnc-history.php', 'History of GNC', 45, '2026-10-15 19:43:13', '2026-10-01 19:51:23'),
(28, '/pages/academics/college/departments/cba.php', 'College of Business Administration', 3, '2026-10-06 23:22:15', '2026-10-01 21:03:26'),
(30, '/pages/academics/basic-edu/basic-edu-dept.php', 'Basic Education Departments', 28, '2026-10-15 18:01:57', '2026-10-01 21:04:52'),
(31, '/pages/academics/basic-edu/basic-edu-department/jhs-regular.php', 'Junior High School Regular', 11, '2026-10-14 22:59:04', '2026-10-01 21:04:54'),
(32, '/pages/academics/basic-edu/basic-edu-department/casa-gradeschool.php', 'CASA and Grade School Montessori', 13, '2026-10-15 18:01:59', '2026-10-01 21:04:59'),
(33, '/pages/academics/college/departments/coe.php', 'College of Engineering', 3, '2026-10-04 20:04:40', '2026-10-01 21:13:51'),
(34, '/pages/academics/college/departments/comlsp.php', 'College of Medical Laboratory Science and Pharmacy', 3, '2026-10-06 10:47:15', '2026-10-01 21:13:55'),
(35, '/pages/academics/college/departments/coa.php', 'College of Accountancy', 1, '2026-10-01 21:13:59', '2026-10-01 21:13:59'),
(36, '/pages/academics/college/departments/conursing.php', 'College of Nursing', 2, '2026-10-04 18:30:35', '2026-10-01 21:14:38'),
(39, '/pages/about/vision-mission.php', 'Vision and Mission', 25, '2026-10-15 17:02:15', '2026-10-01 21:15:15'),
(40, '/pages/about/core-values.php', 'Core Values', 12, '2026-10-15 11:40:02', '2026-10-01 21:15:18'),
(41, '/pages/about/logo-meaning.php', 'Institutional Logo and Meaning', 11, '2026-10-15 11:40:05', '2026-10-01 21:15:20'),
(42, '/pages/admission/enrollment-procedures.php', 'Enrollment Procedures', 14, '2026-10-12 22:13:34', '2026-10-01 21:17:10'),
(43, '/pages/academics/college/programs/bsba-fm.php', 'Financial Management', 2, '2026-10-10 19:18:32', '2026-10-01 21:24:20'),
(44, '/pages/academics/graduate-school/graduate-school.php', 'Graduate School', 14, '2026-10-15 17:03:39', '2026-10-01 21:28:26'),
(45, '/pages/academics/graduate-school/programs/phd-educ-mgt.php', 'PhD in Educational Management', 6, '2026-10-13 23:25:57', '2026-10-01 21:28:27'),
(84, '/pages/academics/basic-edu/basic-edu-department/hs-monte.php', 'High School Montesorri', 4, '2026-10-07 15:06:03', '2026-10-03 14:54:53'),
(98, '/pages/academics/basic-edu/basic-edu-department/shs-regular.php', 'Senior High School Regular', 3, '2026-10-13 15:35:18', '2026-10-03 19:10:16'),
(128, '/pages/academics/college/programs/bsce.php', 'Civil Engineering', 1, '2026-10-04 18:49:12', '2026-10-04 18:49:12'),
(129, '/pages/academics/college/programs/bscs.php', 'Computer Science', 1, '2026-10-04 18:51:32', '2026-10-04 18:51:32'),
(144, '/pages/academics/graduate-school/programs/ma-educ.php', 'Master of Arts in Education', 3, '2026-10-13 23:26:04', '2026-10-04 21:17:55'),
(147, '/pages/academics/college/programs/bsmls.php', 'Medical Laboratory Science', 4, '2026-10-07 12:56:46', '2026-10-04 21:31:33'),
(164, '/pages/academics/graduate-school/programs/ma-public-admin.php', 'Master of Arts in Public Administration', 2, '2026-10-07 15:36:41', '2026-10-04 23:23:22'),
(206, '/pages/academics/college/college-department.php', 'College of Engineering', 40, '2026-10-15 19:44:46', '2026-10-06 15:46:18'),
(236, '/pages/academics/college/college-dept.php', 'College Departments', 31, '2026-10-15 18:13:13', '2026-10-06 23:19:47'),
(246, '/pages/academics/college/program.php', 'Bachelor of Elementary Education', 45, '2026-10-15 19:43:54', '2026-10-07 13:04:46'),
(287, '/news-article.php', 'TESTING — Guagua National Colleges', 13, '2026-10-13 14:16:20', '2026-10-08 11:43:41'),
(293, '/announcement-article.php', 'tests — Guagua National Colleges', 4, '2026-10-15 19:41:30', '2026-10-08 12:24:08'),
(332, '/pages/academics/college/programs/bsed.php', 'Secondary Education', 1, '2026-10-09 18:31:01', '2026-10-09 18:31:01'),
(521, '/pages/admission/scholarship.php', 'Scholarship', 5, '2026-10-14 18:34:42', '2026-10-13 20:59:53'),
(568, '/pages/contact-us.php', 'Contact Us', 5, '2026-10-14 21:08:21', '2026-10-14 18:46:24');

-- --------------------------------------------------------

--
-- Table structure for table `page_visits_daily`
--

CREATE TABLE `page_visits_daily` (
  `page_id` int UNSIGNED NOT NULL,
  `visit_date` date NOT NULL,
  `views` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `page_visits_daily`
--

INSERT INTO `page_visits_daily` (`page_id`, `visit_date`, `views`) VALUES
(1, '2026-10-01', 14),
(1, '2026-10-02', 4),
(1, '2026-10-03', 19),
(1, '2026-10-04', 7),
(1, '2026-10-05', 3),
(1, '2026-10-06', 11),
(1, '2026-10-07', 8),
(1, '2026-10-08', 18),
(1, '2026-10-09', 9),
(1, '2026-10-10', 27),
(1, '2026-10-11', 2),
(1, '2026-10-12', 14),
(1, '2026-10-13', 13),
(1, '2026-10-14', 22),
(1, '2026-10-15', 8),
(5, '2026-10-01', 5),
(5, '2026-10-03', 2),
(5, '2026-10-04', 4),
(5, '2026-10-05', 2),
(5, '2026-10-06', 12),
(9, '2026-10-01', 5),
(9, '2026-10-03', 1),
(9, '2026-10-04', 2),
(9, '2026-10-06', 6),
(9, '2026-10-08', 1),
(9, '2026-10-13', 1),
(10, '2026-10-01', 3),
(10, '2026-10-03', 2),
(10, '2026-10-04', 1),
(10, '2026-10-07', 1),
(10, '2026-10-08', 1),
(10, '2026-10-10', 3),
(10, '2026-10-12', 2),
(10, '2026-10-13', 2),
(11, '2026-10-01', 5),
(11, '2026-10-03', 2),
(11, '2026-10-04', 2),
(11, '2026-10-05', 2),
(11, '2026-10-06', 2),
(11, '2026-10-07', 2),
(11, '2026-10-08', 6),
(11, '2026-10-09', 3),
(11, '2026-10-10', 3),
(11, '2026-10-12', 1),
(11, '2026-10-13', 1),
(11, '2026-10-14', 1),
(11, '2026-10-15', 1),
(14, '2026-10-01', 2),
(14, '2026-10-04', 2),
(14, '2026-10-08', 1),
(14, '2026-10-09', 1),
(14, '2026-10-12', 1),
(14, '2026-10-13', 1),
(15, '2026-10-01', 3),
(15, '2026-10-03', 2),
(15, '2026-10-04', 3),
(15, '2026-10-05', 1),
(15, '2026-10-07', 1),
(15, '2026-10-10', 2),
(15, '2026-10-13', 3),
(15, '2026-10-14', 1),
(15, '2026-10-15', 2),
(21, '2026-10-01', 4),
(21, '2026-10-03', 3),
(21, '2026-10-04', 3),
(21, '2026-10-05', 2),
(21, '2026-10-06', 2),
(21, '2026-10-07', 3),
(21, '2026-10-08', 2),
(21, '2026-10-09', 3),
(21, '2026-10-10', 7),
(21, '2026-10-12', 4),
(21, '2026-10-13', 6),
(21, '2026-10-14', 4),
(21, '2026-10-15', 2),
(28, '2026-10-01', 1),
(28, '2026-10-04', 1),
(28, '2026-10-06', 1),
(30, '2026-10-01', 2),
(30, '2026-10-03', 4),
(30, '2026-10-04', 2),
(30, '2026-10-05', 3),
(30, '2026-10-06', 3),
(30, '2026-10-07', 1),
(30, '2026-10-08', 3),
(30, '2026-10-09', 2),
(30, '2026-10-10', 3),
(30, '2026-10-13', 1),
(30, '2026-10-14', 2),
(30, '2026-10-15', 2),
(31, '2026-10-01', 3),
(31, '2026-10-03', 1),
(31, '2026-10-05', 2),
(31, '2026-10-06', 1),
(31, '2026-10-10', 2),
(31, '2026-10-13', 1),
(31, '2026-10-14', 1),
(32, '2026-10-01', 2),
(32, '2026-10-03', 2),
(32, '2026-10-05', 2),
(32, '2026-10-07', 1),
(32, '2026-10-09', 1),
(32, '2026-10-10', 2),
(32, '2026-10-13', 1),
(32, '2026-10-14', 1),
(32, '2026-10-15', 1),
(33, '2026-10-01', 1),
(33, '2026-10-04', 2),
(34, '2026-10-01', 1),
(34, '2026-10-04', 1),
(34, '2026-10-06', 1),
(35, '2026-10-01', 1),
(36, '2026-10-01', 1),
(36, '2026-10-04', 1),
(39, '2026-10-01', 2),
(39, '2026-10-03', 1),
(39, '2026-10-04', 3),
(39, '2026-10-05', 1),
(39, '2026-10-06', 2),
(39, '2026-10-07', 2),
(39, '2026-10-09', 3),
(39, '2026-10-10', 3),
(39, '2026-10-11', 2),
(39, '2026-10-13', 2),
(39, '2026-10-14', 2),
(39, '2026-10-15', 2),
(40, '2026-10-01', 2),
(40, '2026-10-03', 1),
(40, '2026-10-05', 1),
(40, '2026-10-07', 1),
(40, '2026-10-10', 3),
(40, '2026-10-13', 1),
(40, '2026-10-14', 2),
(40, '2026-10-15', 1),
(41, '2026-10-01', 2),
(41, '2026-10-03', 1),
(41, '2026-10-04', 1),
(41, '2026-10-05', 1),
(41, '2026-10-07', 1),
(41, '2026-10-09', 1),
(41, '2026-10-10', 1),
(41, '2026-10-13', 1),
(41, '2026-10-14', 1),
(41, '2026-10-15', 1),
(42, '2026-10-01', 2),
(42, '2026-10-03', 1),
(42, '2026-10-04', 4),
(42, '2026-10-09', 2),
(42, '2026-10-10', 3),
(42, '2026-10-12', 2),
(43, '2026-10-01', 1),
(43, '2026-10-10', 1),
(44, '2026-10-01', 2),
(44, '2026-10-03', 1),
(44, '2026-10-04', 3),
(44, '2026-10-07', 1),
(44, '2026-10-08', 1),
(44, '2026-10-09', 1),
(44, '2026-10-10', 3),
(44, '2026-10-13', 1),
(44, '2026-10-15', 1),
(45, '2026-10-01', 2),
(45, '2026-10-04', 3),
(45, '2026-10-13', 1),
(84, '2026-10-03', 2),
(84, '2026-10-04', 1),
(84, '2026-10-07', 1),
(98, '2026-10-03', 1),
(98, '2026-10-05', 1),
(98, '2026-10-13', 1),
(128, '2026-10-04', 1),
(129, '2026-10-04', 1),
(144, '2026-10-04', 2),
(144, '2026-10-13', 1),
(147, '2026-10-04', 1),
(147, '2026-10-06', 2),
(147, '2026-10-07', 1),
(164, '2026-10-04', 1),
(164, '2026-10-07', 1),
(206, '2026-10-06', 7),
(206, '2026-10-07', 4),
(206, '2026-10-08', 1),
(206, '2026-10-09', 2),
(206, '2026-10-10', 3),
(206, '2026-10-12', 4),
(206, '2026-10-13', 6),
(206, '2026-10-14', 6),
(206, '2026-10-15', 7),
(236, '2026-10-06', 2),
(236, '2026-10-07', 4),
(236, '2026-10-08', 3),
(236, '2026-10-09', 3),
(236, '2026-10-10', 3),
(236, '2026-10-12', 2),
(236, '2026-10-13', 3),
(236, '2026-10-14', 8),
(236, '2026-10-15', 3),
(246, '2026-10-07', 2),
(246, '2026-10-08', 1),
(246, '2026-10-09', 2),
(246, '2026-10-10', 7),
(246, '2026-10-12', 8),
(246, '2026-10-13', 4),
(246, '2026-10-14', 13),
(246, '2026-10-15', 8),
(287, '2026-10-08', 5),
(287, '2026-10-09', 3),
(287, '2026-10-10', 1),
(287, '2026-10-12', 3),
(287, '2026-10-13', 1),
(293, '2026-10-08', 1),
(293, '2026-10-12', 1),
(293, '2026-10-13', 1),
(293, '2026-10-15', 1),
(332, '2026-10-09', 1),
(521, '2026-10-13', 4),
(521, '2026-10-14', 1),
(568, '2026-10-14', 5);

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

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `program_code`, `slug`, `description`, `image_media_id`, `theme_color`, `theme_color_light`, `dept_id`, `pdf_media_id`, `program_name`, `is_published`, `core_values_enabled`, `hero_media_id`, `seal_media_id`, `page_content`) VALUES
(1, 'BEEd', 'beed', 'An undergraduate teacher education program that prepares students to become competent, motivated, and responsible elementary school educators.', 158, '#135E95', '#A9C6EB', 1, 180, 'Bachelor of Elementary Education', 1, 0, 170, 111, '{\"cta_icon\": \"material:volunteer_activism\", \"cta_link\": \"\", \"cta_text\": \"\", \"subtitle\": \"Inspiring Young Minds, Shaping Tomorrow’s Future.\", \"cta_title\": \"\", \"hero_desc\": \"The Elementary Education program prepares competent and compassionate educators committed to nurturing young learners and shaping the future through professional excellence, and effective learner-centered education.\", \"seal_path\": \"\", \"why_intro\": \"Our program develops competent, compassionate, and innovative elementary educators who are committed to learner-centered teaching, professional excellence, and the holistic development of children through effective pedagogy, ethical practice, and lifelong learning.\", \"why_title\": \"\", \"about_text\": \"The Bachelor of Elementary Education is an undergraduate teacher education program that prepares students to become competent, motivated, and responsible elementary school teachers. It develops essential knowledge, teaching skills, and pedagogical strategies needed to effectively support the learning and holistic development of children in both public and private elementary schools.\", \"core_intro\": \"\", \"core_title\": \"\", \"cta_button\": \"\", \"about_title\": \"\", \"vision_icon\": \"bi:bullseye\", \"vision_text\": \"The College of Arts and Sciences provides quality     general education instruction that constitutes the foundation of all knowledge.\\n\\nThe College of Education considers itself as a provider of quality instruction for the development of professional educators who can meet the demands and challenges of teaching in a globally competitive environment.\", \"career_title\": \"\", \"mission_icon\": \"bi:lightbulb\", \"mission_text\": \"To equip students with an adequate understanding of the Humanities, Social Sciences and Communication Education to develop in them a deeper sense of appreciation of the arts and sciences.\\n\\nIt intends to prepare dynamic, innovative and professional educators dedicated to nurturing their future students’ intellectual growth and overall, well-being.\", \"uniform_text\": \"\", \"vision_title\": \"\", \"mission_title\": \"\", \"uniform_title\": \"\", \"facility_title\": \"\", \"hero_image_path\": \"\", \"curriculum_intro\": \"\", \"curriculum_title\": \"\"}'),
(2, 'BSEd', 'bsed', 'An undergraduate teacher education program that prepares future educators with the knowledge, skills, and competencies needed to teach in secondary schools.', 159, '#135E95', '#A9C6EB', 1, NULL, 'Bachelor of Secondary Education', 1, 0, 171, 139, '{\"cta_icon\": \"material:volunteer_activism\", \"cta_link\": \"\", \"cta_text\": \"Join a community driven by purpose, dedicated to excellence, and built to make an impact.\", \"subtitle\": \"Shaping Future-Ready Educators Through Knowledge, Leadership, and Excellence.\", \"cta_title\": \"Make a difference. Begin Your Journey with GNCI.\", \"hero_desc\": \"The Secondary Education program prepares competent and innovative educators committed to guiding young learners, advancing quality education, and making a meaningful impact through effective teaching, professional excellence, and lifelong learning.\", \"seal_path\": \"\", \"why_intro\": \"Our program develops competent, innovative, and dedicated educators who are committed to excellence in teaching, learner development, and lifelong learning through effective pedagogy, specialized knowledge, and professional practice.\", \"why_title\": \"\", \"about_text\": \"The Bachelor of Secondary Education is an undergraduate teacher education program that prepares future educators with the knowledge, skills, and competencies needed to teach effectively in their chosen area of specialization at the secondary level. The program develops learners’ intellectual, emotional, social, and professional qualities, preparing them to become competent, responsible, and compassionate educators in public and private schools.\", \"core_intro\": \"\", \"core_title\": \"\", \"cta_button\": \"\", \"about_title\": \"\", \"vision_icon\": \"material:target\", \"vision_text\": \"To develop competent, ethical, and innovative secondary educators equipped with strong content knowledge, effective teaching strategies, and appropriate ICT skills. It aims to prepare graduates to respond to diverse learning needs, uphold professional and cultural values, pursue lifelong growth, and contribute meaningfully to the advancement of education and society.\", \"career_title\": \"\", \"mission_icon\": \"material:lightbulb\", \"mission_text\": \"To develop competent, ethical, and innovative secondary educators equipped with strong content knowledge, effective teaching strategies, and appropriate ICT skills. It aims to prepare graduates to respond to diverse learning needs, uphold professional and cultural values, pursue lifelong growth, and contribute meaningfully to the advancement of education and society.\", \"uniform_text\": \"\", \"vision_title\": \"\", \"mission_title\": \"\", \"uniform_title\": \"\", \"facility_title\": \"\", \"hero_image_path\": \"\", \"curriculum_intro\": \"\", \"curriculum_title\": \"\"}'),
(3, 'BAEL', 'bael', 'Develops student proficiency in English communication, critical thinking, and language-related skills. It integrates theory and practice in the study of language and literature.', NULL, '#135E95', '#A9C6EB', 1, NULL, 'Bachelor of Arts in English Language', 1, 0, NULL, NULL, '{\"cta_icon\": \"material:volunteer_activism\", \"cta_link\": \"\", \"cta_text\": \"\", \"subtitle\": \"Developing Effective Communicators and Language Professionals for a Global Society.\", \"cta_title\": \"\", \"hero_desc\": \"The English Language program develops proficient and adaptable communicators equipped with strong language skills, critical thinking, and practical expertise to thrive in diverse professional and global contexts.\", \"seal_path\": \"\", \"why_intro\": \"Our program develops proficient, critical, and culturally aware English language professionals who communicate effectively across diverse contexts and contribute meaningfully to education, communication, and society through ethical and purposeful practice.\", \"why_title\": \"\", \"about_text\": \"The Bachelor of Arts in English Language program develops students’ proficiency in English communication, critical thinking, and language-related skills. It integrates theory and practice to prepare graduates for effective communication across diverse academic, professional, and social contexts, while providing a strong foundation for employment and further studies.\", \"core_intro\": \"\", \"core_title\": \"\", \"cta_button\": \"\", \"about_title\": \"\", \"vision_icon\": \"\", \"vision_text\": \"Her eyebrows were a shade darker than her hair. They were thick and almost horizontal, emphasizing the depth of her eyes. She was rather handsome than beautiful. Her face was captivating by reason of a certain frankness of expression and a contradictory subtle play of features. Her manner was engaging\", \"career_title\": \"\", \"mission_icon\": \"\", \"mission_text\": \"To develop proficient, ethical, and culturally aware communicators who excel in oral and written communication, collaborate effectively across diverse fields and cultures, and contribute meaningfully to the preservation and promotion of Filipino heritage.\", \"uniform_text\": \"\", \"vision_title\": \"\", \"mission_title\": \"\", \"uniform_title\": \"\", \"facility_title\": \"\", \"hero_image_path\": \"\", \"curriculum_intro\": \"\", \"curriculum_title\": \"\"}'),
(4, 'BSBA-FM', 'bsba-fm', 'Provides students with a solid foundation in financial concepts, business management, and strategic decision-making for careers in finance and banking.', 161, '#EABA3B', '#FDF9EC', 2, NULL, 'Bachelor of Science in Business Administration major in Financial Management', 1, 0, NULL, 203, '{\"cta_icon\": \"\", \"cta_link\": \"\", \"cta_text\": \"\", \"subtitle\": \"\", \"cta_title\": \"\", \"hero_desc\": \"\", \"seal_path\": \"\", \"why_intro\": \"\", \"why_title\": \"\", \"about_text\": \"\", \"core_intro\": \"\", \"core_title\": \"\", \"cta_button\": \"\", \"about_title\": \"\", \"vision_icon\": \"\", \"vision_text\": \"\", \"career_title\": \"\", \"mission_icon\": \"\", \"mission_text\": \"\", \"uniform_text\": \"\", \"vision_title\": \"\", \"mission_title\": \"\", \"uniform_title\": \"\", \"facility_title\": \"\", \"hero_image_path\": \"\", \"curriculum_intro\": \"\", \"curriculum_title\": \"\"}'),
(5, 'BSCS', 'bscs', 'Trains students in computing theories, algorithm design, software development, and problem-solving for careers in the IT industry.', NULL, '#6F716E', '#EEEEEE', 2, NULL, 'Bachelor of Science in Computer Science', 1, 0, NULL, NULL, NULL),
(6, 'BSIT', 'bsit', 'Equips students with practical skills in systems development, network administration, database management, and IT support.', 163, '#6F716E', '#EEEEEE', 2, NULL, 'Bachelor of Science in Information Technology', 1, 0, NULL, NULL, NULL),
(7, 'BSHM', 'bshm', 'Prepares students for leadership roles in the hospitality industry through training in hotel, restaurant, and tourism operations.', 160, '#E86698', '#FDF0F5', 2, NULL, 'Bachelor of Science in Hospitality Management', 1, 0, NULL, NULL, NULL),
(8, 'BSTM', 'bstm', 'Develops competencies in tourism planning, operations, and management for careers in the travel and tourism industry.', 162, '#5C198B', '#EFE8F4', 2, NULL, 'Bachelor of Science in Tourism Management', 1, 0, NULL, NULL, NULL),
(9, 'BSCE', 'bsce', 'A comprehensive program covering structural, geotechnical, transportation, and water resources engineering, preparing students for professional engineering practice.', 164, '#FD541B', '#FFEEE9', 3, NULL, 'Bachelor of Science in Civil Engineering', 1, 0, NULL, 202, '{\"cta_icon\": \"\", \"cta_link\": \"\", \"cta_text\": \"\", \"subtitle\": \"\", \"cta_title\": \"\", \"hero_desc\": \"\", \"seal_path\": \"\", \"why_intro\": \"\", \"why_title\": \"\", \"about_text\": \"\", \"core_intro\": \"\", \"core_title\": \"\", \"cta_button\": \"\", \"about_title\": \"\", \"vision_icon\": \"\", \"vision_text\": \"\", \"career_title\": \"\", \"mission_icon\": \"\", \"mission_text\": \"\", \"uniform_text\": \"\", \"vision_title\": \"\", \"mission_title\": \"\", \"uniform_title\": \"\", \"facility_title\": \"\", \"hero_image_path\": \"\", \"curriculum_intro\": \"\", \"curriculum_title\": \"\"}'),
(10, 'BSMLS', 'bsmls', 'Trains students in laboratory diagnostics, clinical analysis, and medical research for careers in medical laboratory science.', 165, '#307056', '#E3EFE9', 4, NULL, 'Bachelor of Science in Medical Laboratory Science', 1, 1, NULL, NULL, '{\"cta_icon\": \"material:volunteer_activism\", \"subtitle\": \"Precision in Science. Excellence in Diagnosis. Commitment to Healthcare.\", \"hero_desc\": \"The Medical Laboratory Science program develops analytical and precision-driven professionals committed to delivering accurate diagnostic insights and advancing excellence in healthcare.\", \"seal_path\": \"/assets/images/svg/medtech-logo.svg\", \"why_intro\": \"Our program develops analytical and precision-driven professionals who are committed to clinical excellence, technical competence, and the advancement of healthcare through evidence-based laboratory diagnostics and compassionate service.\", \"why_title\": \"Why Choose BSMLS at GNCI?\", \"about_text\": \"The Bachelor of Science in Medical Laboratory Science (BSMLS) program develops competent, ethical, and skilled professionals essential to disease detection, diagnosis, and patient care. Through rigorous scientific training and clinical practice, students gain the expertise to deliver accurate diagnostic results and contribute to better health outcomes.\", \"core_intro\": \"Guiding our commitment to excellence in education, research, and healthcare\", \"vision_icon\": \"material:psychiatry\", \"vision_text\": \"The College of Medical Laboratory Science aspires to be a preeminent center of education, dedicated to nurturing highly skilled and research-driven medical technologists.\", \"mission_icon\": \"material:shield_with_heart\", \"mission_text\": \"To foster the growth of a new generation of medical technologists who excel in applying analytical and critical thinking skills to advance healthcare through precise diagnostics and pioneering research.\", \"uniform_text\": \"The prescribed uniform for BS Medical Laboratory Science reflects professionalism, cleanliness, and readiness for laboratory practice, it promotes discipline and helps students embody the standards expected of future medical laboratory professionals.\", \"hero_image_path\": \"/assets/images/medtech-hero.png\"}'),
(11, 'BSPh', 'bsph', 'Prepares students for careers in community pharmacy, hospital pharmacy, pharmaceutical research, and the drug manufacturing industry.', NULL, '#307056', '#E3EFE9', 4, NULL, 'Bachelor of Science in Pharmacy', 1, 0, NULL, NULL, NULL),
(12, 'BSAIS', 'bsais', 'Combines accounting and information systems training for careers in auditing, taxation, and business technology.', NULL, '#EABA3B', '#FDF9EC', 5, NULL, 'Bachelor of Science in Accounting Information Systems', 1, 0, NULL, NULL, NULL),
(13, 'BSA', 'bsa', 'Provides rigorous training in accounting theory, auditing, and taxation, preparing graduates for the CPA licensure examination.', 166, '#EABA3B', '#FDF9EC', 5, NULL, 'Bachelor of Science in Accountancy', 1, 0, NULL, NULL, NULL),
(14, 'BSN', 'bsn', 'A competency-based program that develops professional nursing skills in patient care, clinical practice, and community health.', 167, '#E15221', '#FFBDA7', 6, NULL, 'Bachelor of Science in Nursing', 1, 0, NULL, NULL, NULL);

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

--
-- Dumping data for table `program_items`
--

INSERT INTO `program_items` (`item_id`, `program_id`, `item_type`, `title`, `subtitle`, `body`, `icon`, `letter`, `image_media_id`, `image_path`, `display_order`, `created_at`) VALUES
(1, 10, 'badge', '4 Years', 'Program', NULL, 'material:school', NULL, NULL, NULL, 1, '2026-10-07 04:47:52'),
(2, 10, 'badge', 'CHED', 'Accredited', NULL, 'material:workspace_premium', NULL, NULL, NULL, 2, '2026-10-07 04:47:52'),
(3, 10, 'badge', 'BSML', 'Degree', NULL, 'material:stethoscope', NULL, NULL, NULL, 3, '2026-10-07 04:47:52'),
(4, 10, 'badge', 'Clinical', 'Experience', NULL, 'material:medical_information', NULL, NULL, NULL, 4, '2026-10-07 04:47:52'),
(5, 10, 'core_value', 'Mastery', NULL, 'Striving for excellence in every aspect of education, research, and professional practice.', 'material:trophy', 'M', NULL, NULL, 1, '2026-10-07 04:47:52'),
(6, 10, 'core_value', 'Empowerment', NULL, 'Empowering our students and faculty to take ownership of their learning and teaching, fostering a culture of independence and growth.', 'material:finance_mode', 'E', NULL, NULL, 2, '2026-10-07 04:47:52'),
(7, 10, 'core_value', 'Dedication', NULL, 'Dedicated to the pursuit of knowledge, the enhancement of skills, and the betterment of healthcare through unwavering commitment.', 'material:psychology', 'D', NULL, NULL, 3, '2026-10-07 04:47:52'),
(8, 10, 'core_value', 'Teamwork', NULL, 'Foster a collaborative and supportive environment, valuing teamwork and effective communication among students, faculty, and the healthcare community.', 'material:groups', 'T', NULL, NULL, 4, '2026-10-07 04:47:52'),
(9, 10, 'core_value', 'Ethics', NULL, 'Uphold the highest ethical standards, promoting integrity, honesty, and responsibility in all our endeavors.', 'material:balance', 'E', NULL, NULL, 5, '2026-10-07 04:47:52'),
(10, 10, 'core_value', 'Creativity', NULL, 'Encouraging creativity and innovation in research, teaching methods, and problem-solving, contributing to advancements in medical laboratory science.', 'material:category', 'C', NULL, NULL, 6, '2026-10-07 04:47:52'),
(11, 10, 'core_value', 'Humanity', NULL, 'We recognize the importance of empathy and compassion in healthcare, promoting a human-centered approach in all interactions and practices.', 'material:diversity_1', 'H', NULL, NULL, 7, '2026-10-07 04:47:52'),
(12, 10, 'why', 'Advanced Clinical Competence', NULL, 'Master the performance of rigorous laboratory tests for accurate diagnosis, treatment, and disease management using modern equipment.', NULL, NULL, NULL, '/assets/images/svg/Doctors-amico 1.svg', 1, '2026-10-07 04:47:52'),
(13, 10, 'why', 'Evidence-Based Diagnostics', NULL, 'Develop analytical skills in specimen collection, results validation, and health information management to ensure high-quality patient care.', NULL, NULL, NULL, '/assets/images/svg/Vaccine development-amico 1.svg', 2, '2026-10-07 04:47:52'),
(14, 10, 'why', 'Inter-professional Collaboration', NULL, 'Learn to work effectively in multi-disciplinary healthcare teams, emphasizing leadership, ethical practice, and interpersonal communication.', NULL, NULL, NULL, '/assets/images/svg/Science-amico 2.svg', 3, '2026-10-07 04:47:52'),
(15, 10, 'why', 'Research-Oriented Inquiry', NULL, 'Apply scientific research skills and critical thinking to develop innovations in laboratory science and broader healthcare management.', NULL, NULL, NULL, '/assets/images/svg/Researchers-amico 1.svg', 4, '2026-10-07 04:47:52'),
(16, 10, 'why', 'Safety & Quality Standards', NULL, 'Master strict biosafety protocols, waste management practices, and ethical standards essential for a professional medical laboratory environment.', NULL, NULL, NULL, '/assets/images/svg/Doctor-amico 1.svg', 5, '2026-10-07 04:47:52'),
(17, 10, 'why', 'Global Professional Readiness', NULL, 'Build the competencies required for local licensure (MTLE) and engage in lifelong professional development for global healthcare opportunities.', NULL, NULL, NULL, '/assets/images/svg/Doctor-amico 1.svg', 6, '2026-10-07 04:47:52'),
(18, 10, 'uniform', 'Female Uniform', NULL, NULL, NULL, NULL, NULL, '/assets/images/medtech-unif-female.png', 1, '2026-10-07 04:47:52'),
(19, 10, 'uniform', 'Male Uniform', NULL, NULL, NULL, NULL, NULL, '/assets/images/medtech-unif-male.png', 2, '2026-10-07 04:47:52'),
(20, 10, 'uniform', '3rd and 4th Year Uniform', NULL, NULL, NULL, NULL, NULL, '/assets/images/medtech-unif-3rd&4th.png', 3, '2026-10-07 04:47:52'),
(21, 10, 'career', 'Registered Medical Technologist (RMT)', NULL, 'Performs complex clinical laboratory tests on biological samples that provides essential data for diagnosis, treatment, and monitoring of diseases.', NULL, NULL, NULL, '/assets/images/rmt.png', 1, '2026-10-07 04:47:52'),
(22, 10, 'career', 'Molecular Laboratory Scientist', NULL, 'Conducts complex genetic and molecular testing to detect and characterize inherited, malignant, and infectious diseases.', NULL, NULL, NULL, '/assets/images/mls.png', 2, '2026-10-07 04:47:52'),
(23, 10, 'career', 'Diagnostic Application Specialist', NULL, 'Provides product demonstrations, software setup, and hands-on training for complex laboratory analyzers and medical devices.', NULL, NULL, NULL, '/assets/images/das.png', 3, '2026-10-07 04:47:52'),
(24, 10, 'career', 'Quality Assurance Officer', NULL, 'Ensures testing procedures and medical equipment strictly comply with regulatory and accreditation standards.', NULL, NULL, NULL, '/assets/images/qao.png', 4, '2026-10-07 04:47:52'),
(32, 1, 'badge', '4 Years', 'Program', NULL, 'material:school', NULL, NULL, NULL, 1, '2026-10-07 05:28:32'),
(33, 1, 'badge', 'CHED', 'Accredited', NULL, 'material:military_tech', NULL, NULL, NULL, 2, '2026-10-07 05:28:32'),
(34, 1, 'badge', 'BEEd', 'Degree', NULL, 'material:auto_stories', NULL, NULL, NULL, 3, '2026-10-07 05:28:32'),
(35, 1, 'badge', 'Professional', 'Practice', NULL, 'material:verified', NULL, NULL, NULL, 4, '2026-10-07 05:28:32'),
(36, 1, 'why', 'Comprehensive Teacher Preparation', NULL, 'Build a strong foundation in elementary education through essential knowledge, teaching strategies, and pedagogical skills.', NULL, NULL, 128, NULL, 1, '2026-10-09 10:47:45'),
(37, 1, 'why', 'Diverse Teaching Opportunities', NULL, 'Prepare to teach across different learning areas and work with learners in public and private elementary schools.', NULL, NULL, 129, NULL, 2, '2026-10-09 10:47:45'),
(38, 1, 'why', 'Hands-On Teaching Experience', NULL, 'Develop practical teaching skills through field-based experiences, classroom activities, and real-world educational settings.', NULL, NULL, 130, NULL, 3, '2026-10-09 10:47:45'),
(39, 1, 'why', 'Learner-Centered Education', NULL, 'Learn to understand diverse learners and apply appropriate teaching methods, assessment tools, and learning approaches.', NULL, NULL, 131, NULL, 4, '2026-10-09 10:47:45'),
(40, 1, 'why', 'Professional & Ethical Development', NULL, 'Develop the personal qualities, professionalism, and ethical responsibility needed to become an effective and exemplary teacher.', NULL, NULL, 132, NULL, 5, '2026-10-09 10:47:45'),
(41, 1, 'why', 'LET Readiness and Lifelong Growth', NULL, 'Receive comprehensive preparation for the Licensure Examination for Teachers (LET) while developing a commitment to continuous personal and professional growth.', NULL, NULL, 133, NULL, 6, '2026-10-09 10:47:45'),
(42, 1, 'career', 'Elementary School Teacher', NULL, 'Teach and facilitate learning in public or private elementary schools.', NULL, NULL, 134, NULL, 1, '2026-10-09 11:19:54'),
(43, 1, 'career', 'Special Education Teacher', NULL, 'Provide specialized instruction and support to learners with diverse educational needs.', NULL, NULL, 135, NULL, 2, '2026-10-09 11:19:54'),
(44, 1, 'career', 'Preschool Teacher', NULL, 'Guide young learners in their early academic, social, and developmental experiences.', NULL, NULL, 136, NULL, 3, '2026-10-09 11:19:54'),
(45, 1, 'career', 'Education Program Coordinator', NULL, 'Assist in developing, implementing, and managing educational programs and learning activities.', NULL, NULL, 137, NULL, 4, '2026-10-09 11:19:54'),
(46, 2, 'badge', '4 Years', 'Program', NULL, 'material:school', NULL, NULL, NULL, 1, '2026-10-09 11:25:39'),
(47, 2, 'badge', 'CHED', 'Accredited', NULL, 'material:military_tech', NULL, NULL, NULL, 2, '2026-10-09 11:25:39'),
(48, 2, 'badge', 'BSEd', 'Degree', NULL, 'material:auto_stories', NULL, NULL, NULL, 3, '2026-10-09 11:25:39'),
(49, 2, 'badge', 'Professional', 'Practice', NULL, 'material:verified', NULL, NULL, NULL, 4, '2026-10-09 11:25:39'),
(50, 2, 'why', 'Comprehensive Secondary Teacher Preparation', NULL, 'Build a strong foundation in secondary education through essential knowledge, pedagogical skills, and teaching practices designed to prepare you for both public and private schools.', NULL, NULL, NULL, NULL, 1, '2026-10-12 13:21:37'),
(51, 2, 'why', 'Specialized Teaching Opportunities', NULL, 'Develop expertise in your chosen area of specialization, including Mathematics, English, Filipino, Physical and Biological Sciences, and MAPEH, while gaining the content knowledge and pedagogy needed to teach high school learners effectively.', NULL, NULL, NULL, NULL, 2, '2026-10-12 13:21:37'),
(52, 2, 'why', 'Diverse and Learner-Centered Teaching', NULL, 'Learn to facilitate meaningful learning through a wide range of teaching methodologies, delivery modes, assessment strategies, and instructional approaches suited to diverse learners and learning environments.', NULL, NULL, NULL, NULL, 3, '2026-10-12 13:21:37'),
(53, 2, 'why', 'Innovative and Technology-Enhanced Education', NULL, 'Develop innovative curricula, instructional plans, teaching resources, and approaches while utilizing ICT to promote quality, relevant, and sustainable educational practices.', NULL, NULL, NULL, NULL, 4, '2026-10-12 13:21:37'),
(54, 2, 'why', 'Professional, Ethical and Cultural Development', NULL, 'Develop the qualities of a model teacher by practicing professional and ethical standards, responding to changing societal needs, and recognizing your responsibility to the local, national, and global community while preserving and promoting Filipino heritage.', NULL, NULL, NULL, NULL, 5, '2026-10-12 13:21:37'),
(55, 2, 'why', 'LET Readiness and Lifelong Growth', NULL, 'Receive comprehensive preparation for the Licensure Examination for Teachers (LET) while gaining field-based and experiential opportunities that encourage continuous personal and professional growth.', NULL, NULL, NULL, NULL, 6, '2026-10-12 13:21:37'),
(56, 2, 'career', 'Secondary School Teacher', NULL, 'Teach specialized subjects in public or private junior and senior high schools, applying appropriate teaching strategies, assessment methods, and learning resources.', NULL, NULL, 172, NULL, 1, '2026-10-12 13:24:20'),
(57, 2, 'career', 'Curriculum and Instruction Specialist', NULL, 'Develop and improve curricula, lesson plans, instructional materials, and teaching approaches to support effective and engaging learning experiences.', NULL, NULL, 173, NULL, 2, '2026-10-12 13:24:20'),
(58, 2, 'career', 'Academic Coordinator', NULL, 'Plan, coordinate, and support academic programs and activities while working with teachers and school administrators to improve educational outcomes.', NULL, NULL, 174, NULL, 3, '2026-10-12 13:24:20'),
(59, 2, 'career', 'Education and Training Specialist', NULL, 'Apply teaching and training skills in educational institutions, training organizations, and other settings that provide learning and professional development programs.', NULL, NULL, 175, NULL, 4, '2026-10-12 13:24:20'),
(60, 1, 'facility', 'DSC 3098', NULL, NULL, NULL, NULL, 181, NULL, 1, '2026-10-14 04:52:51'),
(61, 1, 'facility', 'DSC 3686', NULL, NULL, NULL, NULL, 182, NULL, 2, '2026-10-14 04:52:51'),
(62, 1, 'facility', 'DSC 3743', NULL, NULL, NULL, NULL, 183, NULL, 3, '2026-10-14 04:52:51'),
(63, 1, 'facility', 'DSC 3755', NULL, NULL, NULL, NULL, 184, NULL, 4, '2026-10-14 04:52:51'),
(64, 1, 'facility', 'DSC 6802', NULL, NULL, NULL, NULL, 185, NULL, 5, '2026-10-14 04:52:51'),
(65, 1, 'facility', 'DSC 6804', NULL, NULL, NULL, NULL, 186, NULL, 6, '2026-10-14 04:52:51'),
(66, 1, 'uniform', 'Test', NULL, NULL, NULL, NULL, 187, NULL, 1, '2026-10-14 05:18:15'),
(67, 1, 'uniform', 'Test', NULL, NULL, NULL, NULL, 188, NULL, 2, '2026-10-14 05:18:15'),
(69, 1, 'hero_slide', 'Department Header v2', NULL, NULL, NULL, NULL, NULL, '/uploads/program-banners/e4ed8490f5a2a194f07938333c38a10e.png', 1, '2026-10-14 10:02:39'),
(71, 2, 'hero_slide', 'Department Header', NULL, NULL, NULL, NULL, NULL, '/uploads/program-banners/989036d6914b009a9583ab2f256f2822.png', 1, '2026-10-14 14:38:28'),
(72, 2, 'facility', 'DSC 3683', NULL, NULL, NULL, NULL, 189, NULL, 1, '2026-10-14 14:38:58'),
(73, 2, 'facility', 'DSC 3687', NULL, NULL, NULL, NULL, 190, NULL, 2, '2026-10-14 14:38:58'),
(74, 2, 'facility', 'DSC 3743', NULL, NULL, NULL, NULL, 191, NULL, 3, '2026-10-14 14:38:58'),
(75, 2, 'facility', 'DSC 3755', NULL, NULL, NULL, NULL, 192, NULL, 4, '2026-10-14 14:38:58'),
(76, 2, 'facility', 'DSC 6802', NULL, NULL, NULL, NULL, 193, NULL, 5, '2026-10-14 14:38:58'),
(77, 2, 'facility', 'DSC 6804', NULL, NULL, NULL, NULL, 194, NULL, 6, '2026-10-14 14:38:58'),
(79, 3, 'hero_slide', 'Department Header v2', NULL, NULL, NULL, NULL, NULL, '/uploads/program-banners/e10b968076f4d3ced926f730d7740136.png', 1, '2026-10-14 14:42:01'),
(80, 3, 'facility', 'DSC 3683', NULL, NULL, NULL, NULL, 196, NULL, 1, '2026-10-14 14:42:25'),
(81, 3, 'facility', 'DSC 3687', NULL, NULL, NULL, NULL, 197, NULL, 2, '2026-10-14 14:42:25'),
(82, 3, 'facility', 'DSC 3743', NULL, NULL, NULL, NULL, 198, NULL, 3, '2026-10-14 14:42:25'),
(83, 3, 'facility', 'DSC 3755', NULL, NULL, NULL, NULL, 199, NULL, 4, '2026-10-14 14:42:25'),
(84, 3, 'facility', 'DSC 6801', NULL, NULL, NULL, NULL, 200, NULL, 5, '2026-10-14 14:42:25'),
(85, 3, 'facility', 'DSC 6804', NULL, NULL, NULL, NULL, 201, NULL, 6, '2026-10-14 14:42:25'),
(86, 3, 'badge', '4 Years', 'Program', NULL, 'material:school', NULL, NULL, NULL, 1, '2026-10-14 14:46:56'),
(87, 3, 'badge', 'CHED', 'Accredited', NULL, 'material:military_tech', NULL, NULL, NULL, 2, '2026-10-14 14:46:56'),
(88, 3, 'badge', 'BAEL', 'Degree', NULL, 'bi:translate', NULL, NULL, NULL, 3, '2026-10-14 14:46:56'),
(89, 3, 'badge', 'Professional', 'Practice', NULL, 'material:verified', NULL, NULL, NULL, 4, '2026-10-14 14:46:56'),
(90, 3, 'why', 'Strong English Communication Skills', NULL, 'Develop advanced oral and written communication skills for academic, professional, and social settings.', NULL, NULL, NULL, NULL, 1, '2026-10-14 14:48:54'),
(91, 3, 'why', 'Diverse Career Opportunities', NULL, 'Prepare for careers in education, communication, writing, publishing, media, business, and other language-related fields.', NULL, NULL, NULL, NULL, 2, '2026-10-14 14:48:54'),
(92, 3, 'why', 'Critical and Analytical Thinking', NULL, 'Strengthen your ability to analyze language, literature, information, and ideas with clarity and depth.', NULL, NULL, NULL, NULL, 3, '2026-10-14 14:48:54'),
(93, 3, 'why', 'Effective Communication Across Cultures', NULL, 'Learn to communicate confidently and appropriately in diverse, multicultural, and professional environments.', NULL, NULL, NULL, NULL, 4, '2026-10-14 14:48:54'),
(94, 3, 'why', 'Professional and Ethical Development', NULL, 'Develop professionalism, social responsibility, and ethical awareness essential for effective communication and collaboration.', NULL, NULL, NULL, NULL, 5, '2026-10-14 14:48:54'),
(95, 3, 'why', 'Cultural Awareness and Heritage', NULL, 'Gain a deeper appreciation of Filipino language, history, and culture while contributing to their preservation and promotion.', NULL, NULL, NULL, NULL, 6, '2026-10-14 14:48:54'),
(99, 4, 'badge', 'New badge', NULL, NULL, 'material:school', NULL, NULL, NULL, 1, '2026-10-15 10:11:59'),
(100, 9, 'why', 'New reason', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-10-15 10:14:19'),
(101, 9, 'why', 'New reason', NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-10-15 10:14:22'),
(102, 9, 'why', 'New reason', NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-10-15 10:14:24');

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
-- Table structure for table `site_visitors`
--

CREATE TABLE `site_visitors` (
  `id` int UNSIGNED NOT NULL,
  `visitor_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `visit_date` date NOT NULL,
  `first_seen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_visitors`
--

INSERT INTO `site_visitors` (`id`, `visitor_hash`, `visit_date`, `first_seen`) VALUES
(1, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-01', '2026-10-01 04:51:45'),
(4, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-01', '2026-10-01 08:13:39'),
(68, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-02', '2026-10-02 12:15:02'),
(72, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-03', '2026-10-03 05:33:33'),
(74, '63b63541d88ec8eb1a27fcc07cdef4e0dafab6626da479c97399a0114130ff5e', '2026-10-03', '2026-10-03 05:34:13'),
(118, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-04', '2026-10-03 16:25:41'),
(170, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-05', '2026-10-05 11:52:18'),
(191, '63b63541d88ec8eb1a27fcc07cdef4e0dafab6626da479c97399a0114130ff5e', '2026-10-06', '2026-10-06 02:11:30'),
(192, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-06', '2026-10-06 02:11:38'),
(200, '7c44a2cb187bd5b96bea13cfc1c796da929477253f5e9a91d1848761b2b6a126', '2026-10-06', '2026-10-06 05:13:05'),
(216, '5783b1379fa7bb8adcb66ebe3eb1423e8e31c74f3f8aa1c6743aae6c4fd87f32', '2026-10-06', '2026-10-06 08:31:30'),
(243, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-07', '2026-10-07 04:44:41'),
(278, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-08', '2026-10-08 02:50:41'),
(298, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-08', '2026-10-08 10:48:42'),
(322, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-09', '2026-10-09 10:09:45'),
(359, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-10', '2026-10-09 16:13:49'),
(362, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-10', '2026-10-09 16:36:07'),
(436, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-11', '2026-10-11 11:22:56'),
(440, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-12', '2026-10-12 02:29:23'),
(448, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-12', '2026-10-12 05:31:18'),
(482, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-13', '2026-10-13 05:40:31'),
(509, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-13', '2026-10-13 12:20:38'),
(539, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-14', '2026-10-13 16:19:29'),
(544, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-14', '2026-10-14 03:03:40'),
(609, '46297c48948f055f79ed0fc3784de50271d743e0e3b13749f119f5a6b879ea39', '2026-10-15', '2026-10-15 03:39:45'),
(621, 'ad1767282dff0af0c7a0a791dc0fa4dd719724ba25869044873966c28e138310', '2026-10-15', '2026-10-15 09:00:24');

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
-- Table structure for table `users`
--

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

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `first_name`, `last_name`, `email`, `password`, `role`, `status`, `otp_code`, `otp_expires_at`, `otp_verified`, `login_attempts`, `locked_until`, `remember_token`, `remember_token_expires_at`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Justine', 'Maoel', 'justinemaoel.gnc@gmail.com', '$2a$12$uzZt3y3HM9l4NwZcZVkN6unJhuEDZTnReijA9O3Q8Nq2sY15orqlq', 'Super Admin', 'active', NULL, NULL, 1, 0, NULL, 'b3f20d647211f8d3a043e3735b703e0e6cbca72608acad0d1b81c946b4a35dc8', '2026-11-13 03:25:31', '2026-10-15 11:39:54', '2026-06-28 12:55:06', '2026-10-15 11:39:54');

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
-- Indexes for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `fk_annimg_announcement` (`announcement_id`),
  ADD KEY `fk_annimg_media` (`media_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`);

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
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_evt_cat` (`category_id`),
  ADD KEY `fk_evt_user` (`user_id`),
  ADD KEY `fk_evt_media` (`media_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`faq_id`),
  ADD KEY `fk_faq_cat` (`category_id`);

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
  MODIFY `log_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=559;

--
-- AUTO_INCREMENT for table `admin_offices`
--
ALTER TABLE `admin_offices`
  MODIFY `office_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `admin_people`
--
ALTER TABLE `admin_people`
  MODIFY `person_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `announcement_images`
--
ALTER TABLE `announcement_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `csrf_tokens`
--
ALTER TABLE `csrf_tokens`
  MODIFY `token_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=345;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `dept_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `faq_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fb_api_call_log`
--
ALTER TABLE `fb_api_call_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=405;

--
-- AUTO_INCREMENT for table `hero_slides`
--
ALTER TABLE `hero_slides`
  MODIFY `slide_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `media_library`
--
ALTER TABLE `media_library`
  MODIFY `media_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `news_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `news_images`
--
ALTER TABLE `news_images`
  MODIFY `image_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `page_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `page_sections`
--
ALTER TABLE `page_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `page_visits`
--
ALTER TABLE `page_visits`
  MODIFY `page_id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=648;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `program_items`
--
ALTER TABLE `program_items`
  MODIFY `item_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `program_sections`
--
ALTER TABLE `program_sections`
  MODIFY `section_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_visitors`
--
ALTER TABLE `site_visitors`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=649;

--
-- AUTO_INCREMENT for table `slug_history`
--
ALTER TABLE `slug_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `fk_ann_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_dept` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`dept_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ann_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD CONSTRAINT `fk_annimg_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_annimg_media` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE CASCADE;

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_dept_dean_media` FOREIGN KEY (`dean_media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dept_logo` FOREIGN KEY (`media_id`) REFERENCES `media_library` (`media_id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
