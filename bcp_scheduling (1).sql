-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2026 at 04:17 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bcp_scheduling`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_periods`
--

CREATE TABLE `academic_periods` (
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `academic_year` varchar(9) NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL,
  `period_status` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_periods`
--

INSERT INTO `academic_periods` (`academic_period_id`, `academic_year`, `semester`, `period_status`, `created_at`) VALUES
(1, '2026-2027', 1, 'DEMO', '2026-09-24 18:08:55'),
(2, '2027-2028', 1, 'DEMO', '2026-09-26 21:11:13');

-- --------------------------------------------------------

--
-- Table structure for table `academic_period_calendars`
--

CREATE TABLE `academic_period_calendars` (
  `academic_period_calendar_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `teaching_start_date` date DEFAULT NULL,
  `teaching_end_date` date DEFAULT NULL,
  `calendar_status` enum('PENDING','APPROVED','REVOKED') NOT NULL DEFAULT 'PENDING',
  `approval_reference` varchar(150) DEFAULT NULL,
  `approved_by` varchar(150) DEFAULT NULL,
  `approval_evidence_sha256` char(64) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_users`
--

CREATE TABLE `auth_users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('ADMIN','SCHEDULER') NOT NULL DEFAULT 'SCHEDULER',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_users`
--

INSERT INTO `auth_users` (`user_id`, `username`, `password_hash`, `role`, `is_active`, `failed_attempts`, `locked_until`, `created_at`) VALUES
(1, 'admin1', '$2y$10$89WSivonm/XqBfPC1537s.XkQ7GzKZ3HDtpz6pbrIe5OeUDuXTp2q', 'ADMIN', 1, 0, NULL, '2026-09-25 18:47:09');

-- --------------------------------------------------------

--
-- Table structure for table `exam_batches`
--

CREATE TABLE `exam_batches` (
  `exam_batch_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `class_batch_id` bigint(20) UNSIGNED NOT NULL,
  `exam_label` varchar(60) NOT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `exam_day_1` date NOT NULL,
  `exam_day_2` date NOT NULL,
  `exam_day_3` date NOT NULL,
  `status` enum('ACTIVE','SUPERSEDED') NOT NULL DEFAULT 'ACTIVE',
  `active_key` tinyint(4) GENERATED ALWAYS AS (case when `status` = 'ACTIVE' then 1 else NULL end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exam_meetings`
--

CREATE TABLE `exam_meetings` (
  `exam_meeting_id` bigint(20) UNSIGNED NOT NULL,
  `exam_batch_id` bigint(20) UNSIGNED NOT NULL,
  `section_subject_id` int(10) UNSIGNED NOT NULL,
  `proctor_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `exam_day` tinyint(3) UNSIGNED NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(10) UNSIGNED NOT NULL,
  `program_code` varchar(30) NOT NULL,
  `program_name` varchar(200) NOT NULL,
  `education_level` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `program_code`, `program_name`, `education_level`, `is_active`, `created_at`) VALUES
(1, 'STEM', 'Science, Technology, Engineering, and Mathematics', 'Senior High School', 1, '2026-09-24 17:27:59'),
(2, 'ABM', 'Accountancy, Business, and Management', 'Senior High School', 1, '2026-09-24 17:27:59'),
(3, 'HUMSS', 'Humanities and Social Sciences', 'Senior High School', 1, '2026-09-24 17:27:59'),
(4, 'BSIT', 'Bachelor of Science in Information Technology', 'College', 1, '2026-09-24 17:27:59'),
(5, 'BSHM', 'Bachelor of Science in Hospitality Management', 'College', 1, '2026-09-24 17:27:59'),
(6, 'BSOA', 'Bachelor of Science in Office Administration', 'College', 1, '2026-09-24 17:27:59'),
(7, 'BSBA', 'Bachelor of Science in Business Administration', 'College', 1, '2026-09-24 17:27:59'),
(8, 'BSCRIM', 'Bachelor of Science in Criminology', 'College', 1, '2026-09-24 17:27:59'),
(9, 'BEED', 'Bachelor of Elementary Education', 'College', 1, '2026-09-24 17:27:59'),
(10, 'BSED', 'Bachelor of Secondary Education', 'College', 1, '2026-09-24 17:27:59');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED DEFAULT NULL,
  `room_name` varchar(100) NOT NULL,
  `building` varchar(150) DEFAULT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 50,
  `room_type` enum('GENERAL','LABORATORY','SPECIALIZED') NOT NULL DEFAULT 'GENERAL',
  `status` enum('AVAILABLE','UNAVAILABLE','MAINTENANCE') NOT NULL DEFAULT 'AVAILABLE',
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `program_id`, `room_name`, `building`, `capacity`, `room_type`, `status`, `data_origin`, `created_at`) VALUES
(1, 4, 'DEMO-BSIT-201', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(2, 4, 'DEMO-BSIT-211', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(3, 4, 'DEMO-BSIT-202', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(4, 4, 'DEMO-BSIT-212', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(5, 4, 'DEMO-BSIT-203', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(6, 4, 'DEMO-BSIT-213', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(7, 4, 'DEMO-BSIT-204', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(8, 4, 'DEMO-BSIT-214', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(9, 4, 'DEMO-BSIT-205', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(10, 4, 'DEMO-BSIT-215', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(11, 4, 'DEMO-BSIT-206', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(12, 4, 'DEMO-BSIT-216', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(13, 4, 'DEMO-BSIT-207', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(14, 4, 'DEMO-BSIT-217', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(15, 4, 'DEMO-BSIT-208', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(16, 4, 'DEMO-BSIT-218', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(17, 4, 'DEMO-BSIT-209', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(18, 4, 'DEMO-BSIT-219', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(19, 4, 'DEMO-BSIT-210', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59'),
(20, 4, 'DEMO-BSIT-220', 'DEMO BUILDING', 50, 'GENERAL', 'AVAILABLE', 'DEMO', '2026-09-24 18:30:59');

-- --------------------------------------------------------

--
-- Table structure for table `room_availability`
--

CREATE TABLE `room_availability` (
  `availability_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `availability_status` enum('AVAILABLE','UNAVAILABLE') NOT NULL DEFAULT 'AVAILABLE',
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_availability`
--

INSERT INTO `room_availability` (`availability_id`, `room_id`, `academic_period_id`, `day_of_week`, `start_time`, `end_time`, `availability_status`, `data_origin`) VALUES
(1, 1, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(2, 1, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(3, 1, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(4, 1, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(5, 1, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(6, 1, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(7, 2, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(8, 2, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(9, 2, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(10, 2, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(11, 2, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(12, 2, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(13, 3, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(14, 3, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(15, 3, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(16, 3, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(17, 3, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(18, 3, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(19, 4, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(20, 4, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(21, 4, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(22, 4, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(23, 4, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(24, 4, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(25, 5, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(26, 5, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(27, 5, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(28, 5, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(29, 5, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(30, 5, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(31, 6, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(32, 6, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(33, 6, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(34, 6, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(35, 6, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(36, 6, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(37, 7, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(38, 7, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(39, 7, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(40, 7, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(41, 7, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(42, 7, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(43, 8, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(44, 8, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(45, 8, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(46, 8, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(47, 8, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(48, 8, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(49, 9, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(50, 9, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(51, 9, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(52, 9, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(53, 9, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(54, 9, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(55, 10, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(56, 10, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(57, 10, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(58, 10, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(59, 10, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(60, 10, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(61, 11, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(62, 11, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(63, 11, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(64, 11, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(65, 11, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(66, 11, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(67, 12, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(68, 12, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(69, 12, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(70, 12, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(71, 12, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(72, 12, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(73, 13, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(74, 13, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(75, 13, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(76, 13, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(77, 13, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(78, 13, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(79, 14, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(80, 14, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(81, 14, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(82, 14, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(83, 14, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(84, 14, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(85, 15, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(86, 15, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(87, 15, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(88, 15, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(89, 15, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(90, 15, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(91, 16, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(92, 16, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(93, 16, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(94, 16, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(95, 16, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(96, 16, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(97, 17, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(98, 17, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(99, 17, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(100, 17, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(101, 17, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(102, 17, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(103, 18, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(104, 18, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(105, 18, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(106, 18, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(107, 18, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(108, 18, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(109, 19, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(110, 19, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(111, 19, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(112, 19, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(113, 19, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(114, 19, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(115, 20, 1, 'Monday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(116, 20, 1, 'Tuesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(117, 20, 1, 'Wednesday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(118, 20, 1, 'Thursday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(119, 20, 1, 'Friday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(120, 20, 1, 'Saturday', '06:00:00', '21:00:00', 'AVAILABLE', 'DEMO');

-- --------------------------------------------------------

--
-- Table structure for table `schedule_batches`
--

CREATE TABLE `schedule_batches` (
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL,
  `status` enum('ACTIVE','SUPERSEDED') NOT NULL DEFAULT 'ACTIVE',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_meetings`
--

CREATE TABLE `schedule_meetings` (
  `meeting_id` bigint(20) UNSIGNED NOT NULL,
  `batch_id` bigint(20) UNSIGNED NOT NULL,
  `section_subject_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL,
  `delivery_mode` enum('F2F','ONLINE') NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_reference_batches`
--

CREATE TABLE `schedule_reference_batches` (
  `reference_batch_id` bigint(20) UNSIGNED NOT NULL,
  `source_batch_id` bigint(20) UNSIGNED NOT NULL,
  `source_academic_period_id` int(10) UNSIGNED NOT NULL,
  `target_academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `reference_status` enum('DRAFT','READY','USED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  `source_academic_year_snapshot` varchar(9) NOT NULL,
  `source_semester_snapshot` tinyint(3) UNSIGNED NOT NULL,
  `target_academic_year_snapshot` varchar(9) NOT NULL,
  `target_semester_snapshot` tinyint(3) UNSIGNED NOT NULL,
  `total_reference_meetings` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedule_reference_meetings`
--

CREATE TABLE `schedule_reference_meetings` (
  `reference_meeting_id` bigint(20) UNSIGNED NOT NULL,
  `reference_batch_id` bigint(20) UNSIGNED NOT NULL,
  `source_meeting_id` bigint(20) UNSIGNED DEFAULT NULL,
  `section_code_snapshot` varchar(50) NOT NULL,
  `year_level_snapshot` varchar(50) NOT NULL,
  `section_type_snapshot` varchar(30) NOT NULL,
  `source_subject_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_code_snapshot` varchar(50) NOT NULL,
  `subject_title_snapshot` varchar(255) NOT NULL,
  `units_snapshot` decimal(5,2) NOT NULL,
  `f2f_hours_snapshot` decimal(5,2) NOT NULL,
  `online_hours_snapshot` decimal(5,2) NOT NULL,
  `source_teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_employee_no_snapshot` varchar(50) DEFAULT NULL,
  `teacher_name_snapshot` varchar(150) DEFAULT NULL,
  `teacher_was_authorized_snapshot` tinyint(1) NOT NULL DEFAULT 0,
  `source_room_id` int(10) UNSIGNED DEFAULT NULL,
  `room_name_snapshot` varchar(100) DEFAULT NULL,
  `building_snapshot` varchar(150) DEFAULT NULL,
  `room_type_snapshot` varchar(50) DEFAULT NULL,
  `room_capacity_snapshot` smallint(5) UNSIGNED DEFAULT NULL,
  `delivery_mode` enum('F2F','ONLINE') NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(10) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `section_code` char(5) NOT NULL,
  `year_level` tinyint(3) UNSIGNED NOT NULL,
  `section_type` enum('REGULAR','CLUSTER','MAJOR') NOT NULL DEFAULT 'REGULAR',
  `student_count` smallint(5) UNSIGNED NOT NULL DEFAULT 50,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `academic_period_id`, `program_id`, `section_code`, `year_level`, `section_type`, `student_count`, `data_origin`, `is_active`, `created_at`) VALUES
(1, 1, 4, '11001', 1, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(2, 1, 4, '11002', 1, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(3, 1, 4, '21001', 2, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(4, 1, 4, '21002', 2, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(5, 1, 4, '31001', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(6, 1, 4, '31002', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(7, 1, 4, '31003', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(8, 1, 4, '41001', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(9, 1, 4, '41002', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(10, 1, 4, '41003', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(11, 1, 4, '41004', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(12, 1, 4, '41005', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(13, 1, 4, '41006', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-24 18:08:55'),
(14, 1, 4, '11003', 1, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(15, 1, 4, '11004', 1, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(16, 1, 4, '21003', 2, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(17, 1, 4, '21004', 2, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(18, 1, 4, '31004', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(19, 1, 4, '31005', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(20, 1, 4, '31006', 3, 'REGULAR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(21, 1, 4, '41007', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(22, 1, 4, '41008', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(23, 1, 4, '41009', 4, 'CLUSTER', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(24, 1, 4, '41010', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(25, 1, 4, '41011', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-25 18:53:34'),
(26, 1, 4, '41012', 4, 'MAJOR', 50, 'DEMO', 1, '2026-09-25 18:53:34');

-- --------------------------------------------------------

--
-- Table structure for table `section_major_links`
--

CREATE TABLE `section_major_links` (
  `link_id` int(10) UNSIGNED NOT NULL,
  `home_section_id` int(10) UNSIGNED NOT NULL,
  `major_section_id` int(10) UNSIGNED NOT NULL,
  `linked_student_count` smallint(5) UNSIGNED NOT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section_major_links`
--

INSERT INTO `section_major_links` (`link_id`, `home_section_id`, `major_section_id`, `linked_student_count`, `data_origin`, `created_at`) VALUES
(1, 8, 11, 50, 'DEMO', '2026-09-24 18:08:55'),
(2, 9, 12, 50, 'DEMO', '2026-09-24 18:08:55'),
(3, 10, 13, 50, 'DEMO', '2026-09-24 18:08:55'),
(4, 21, 24, 50, 'DEMO', '2026-09-25 18:54:21'),
(5, 22, 25, 50, 'DEMO', '2026-09-25 18:54:21'),
(6, 23, 26, 50, 'DEMO', '2026-09-25 18:54:21');

-- --------------------------------------------------------

--
-- Table structure for table `section_subjects`
--

CREATE TABLE `section_subjects` (
  `section_subject_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `section_subjects`
--

INSERT INTO `section_subjects` (`section_subject_id`, `section_id`, `subject_id`, `data_origin`, `created_at`) VALUES
(1, 1, 1, 'DEMO', '2026-09-24 18:08:55'),
(2, 2, 1, 'DEMO', '2026-09-24 18:08:55'),
(3, 1, 2, 'DEMO', '2026-09-24 18:08:55'),
(4, 2, 2, 'DEMO', '2026-09-24 18:08:55'),
(5, 1, 3, 'DEMO', '2026-09-24 18:08:55'),
(6, 2, 3, 'DEMO', '2026-09-24 18:08:55'),
(7, 1, 4, 'DEMO', '2026-09-24 18:08:55'),
(8, 2, 4, 'DEMO', '2026-09-24 18:08:55'),
(9, 1, 5, 'DEMO', '2026-09-24 18:08:55'),
(10, 2, 5, 'DEMO', '2026-09-24 18:08:55'),
(11, 1, 6, 'DEMO', '2026-09-24 18:08:55'),
(12, 2, 6, 'DEMO', '2026-09-24 18:08:55'),
(13, 1, 7, 'DEMO', '2026-09-24 18:08:55'),
(14, 2, 7, 'DEMO', '2026-09-24 18:08:55'),
(15, 1, 8, 'DEMO', '2026-09-24 18:08:55'),
(16, 2, 8, 'DEMO', '2026-09-24 18:08:55'),
(17, 1, 9, 'DEMO', '2026-09-24 18:08:55'),
(18, 2, 9, 'DEMO', '2026-09-24 18:08:55'),
(19, 3, 19, 'DEMO', '2026-09-24 18:08:55'),
(20, 4, 19, 'DEMO', '2026-09-24 18:08:55'),
(21, 3, 20, 'DEMO', '2026-09-24 18:08:55'),
(22, 4, 20, 'DEMO', '2026-09-24 18:08:55'),
(23, 3, 21, 'DEMO', '2026-09-24 18:08:55'),
(24, 4, 21, 'DEMO', '2026-09-24 18:08:55'),
(25, 3, 22, 'DEMO', '2026-09-24 18:08:55'),
(26, 4, 22, 'DEMO', '2026-09-24 18:08:55'),
(27, 3, 23, 'DEMO', '2026-09-24 18:08:55'),
(28, 4, 23, 'DEMO', '2026-09-24 18:08:55'),
(29, 3, 24, 'DEMO', '2026-09-24 18:08:55'),
(30, 4, 24, 'DEMO', '2026-09-24 18:08:55'),
(31, 3, 25, 'DEMO', '2026-09-24 18:08:55'),
(32, 4, 25, 'DEMO', '2026-09-24 18:08:55'),
(33, 3, 26, 'DEMO', '2026-09-24 18:08:55'),
(34, 4, 26, 'DEMO', '2026-09-24 18:08:55'),
(35, 5, 35, 'DEMO', '2026-09-24 18:08:55'),
(36, 6, 35, 'DEMO', '2026-09-24 18:08:55'),
(37, 7, 35, 'DEMO', '2026-09-24 18:08:55'),
(38, 5, 36, 'DEMO', '2026-09-24 18:08:55'),
(39, 6, 36, 'DEMO', '2026-09-24 18:08:55'),
(40, 7, 36, 'DEMO', '2026-09-24 18:08:55'),
(41, 5, 37, 'DEMO', '2026-09-24 18:08:55'),
(42, 6, 37, 'DEMO', '2026-09-24 18:08:55'),
(43, 7, 37, 'DEMO', '2026-09-24 18:08:55'),
(44, 5, 38, 'DEMO', '2026-09-24 18:08:55'),
(45, 6, 38, 'DEMO', '2026-09-24 18:08:55'),
(46, 7, 38, 'DEMO', '2026-09-24 18:08:55'),
(47, 5, 39, 'DEMO', '2026-09-24 18:08:55'),
(48, 6, 39, 'DEMO', '2026-09-24 18:08:55'),
(49, 7, 39, 'DEMO', '2026-09-24 18:08:55'),
(50, 5, 46, 'DEMO', '2026-09-24 18:08:55'),
(51, 6, 47, 'DEMO', '2026-09-24 18:08:55'),
(52, 7, 49, 'DEMO', '2026-09-24 18:08:55'),
(53, 8, 51, 'DEMO', '2026-09-24 18:08:55'),
(54, 9, 51, 'DEMO', '2026-09-24 18:08:55'),
(55, 10, 51, 'DEMO', '2026-09-24 18:08:55'),
(56, 8, 52, 'DEMO', '2026-09-24 18:08:55'),
(57, 9, 52, 'DEMO', '2026-09-24 18:08:55'),
(58, 10, 52, 'DEMO', '2026-09-24 18:08:55'),
(59, 8, 53, 'DEMO', '2026-09-24 18:08:55'),
(60, 9, 53, 'DEMO', '2026-09-24 18:08:55'),
(61, 10, 53, 'DEMO', '2026-09-24 18:08:55'),
(62, 11, 54, 'DEMO', '2026-09-24 18:08:55'),
(63, 12, 60, 'DEMO', '2026-09-24 18:08:55'),
(64, 13, 61, 'DEMO', '2026-09-24 18:08:55'),
(128, 14, 1, 'DEMO', '2026-09-25 18:53:48'),
(129, 14, 2, 'DEMO', '2026-09-25 18:53:48'),
(130, 14, 3, 'DEMO', '2026-09-25 18:53:48'),
(131, 14, 4, 'DEMO', '2026-09-25 18:53:48'),
(132, 14, 5, 'DEMO', '2026-09-25 18:53:48'),
(133, 14, 6, 'DEMO', '2026-09-25 18:53:48'),
(134, 14, 7, 'DEMO', '2026-09-25 18:53:48'),
(135, 14, 8, 'DEMO', '2026-09-25 18:53:48'),
(136, 14, 9, 'DEMO', '2026-09-25 18:53:48'),
(137, 15, 1, 'DEMO', '2026-09-25 18:53:48'),
(138, 15, 2, 'DEMO', '2026-09-25 18:53:48'),
(139, 15, 3, 'DEMO', '2026-09-25 18:53:48'),
(140, 15, 4, 'DEMO', '2026-09-25 18:53:48'),
(141, 15, 5, 'DEMO', '2026-09-25 18:53:48'),
(142, 15, 6, 'DEMO', '2026-09-25 18:53:48'),
(143, 15, 7, 'DEMO', '2026-09-25 18:53:48'),
(144, 15, 8, 'DEMO', '2026-09-25 18:53:48'),
(145, 15, 9, 'DEMO', '2026-09-25 18:53:48'),
(146, 16, 19, 'DEMO', '2026-09-25 18:53:48'),
(147, 16, 20, 'DEMO', '2026-09-25 18:53:48'),
(148, 16, 21, 'DEMO', '2026-09-25 18:53:48'),
(149, 16, 22, 'DEMO', '2026-09-25 18:53:48'),
(150, 16, 23, 'DEMO', '2026-09-25 18:53:48'),
(151, 16, 24, 'DEMO', '2026-09-25 18:53:48'),
(152, 16, 25, 'DEMO', '2026-09-25 18:53:48'),
(153, 16, 26, 'DEMO', '2026-09-25 18:53:48'),
(154, 17, 19, 'DEMO', '2026-09-25 18:53:48'),
(155, 17, 20, 'DEMO', '2026-09-25 18:53:48'),
(156, 17, 21, 'DEMO', '2026-09-25 18:53:48'),
(157, 17, 22, 'DEMO', '2026-09-25 18:53:48'),
(158, 17, 23, 'DEMO', '2026-09-25 18:53:48'),
(159, 17, 24, 'DEMO', '2026-09-25 18:53:48'),
(160, 17, 25, 'DEMO', '2026-09-25 18:53:48'),
(161, 17, 26, 'DEMO', '2026-09-25 18:53:48'),
(162, 18, 35, 'DEMO', '2026-09-25 18:53:48'),
(163, 18, 36, 'DEMO', '2026-09-25 18:53:48'),
(164, 18, 37, 'DEMO', '2026-09-25 18:53:48'),
(165, 18, 38, 'DEMO', '2026-09-25 18:53:48'),
(166, 18, 39, 'DEMO', '2026-09-25 18:53:48'),
(167, 18, 46, 'DEMO', '2026-09-25 18:53:48'),
(168, 19, 35, 'DEMO', '2026-09-25 18:53:48'),
(169, 19, 36, 'DEMO', '2026-09-25 18:53:48'),
(170, 19, 37, 'DEMO', '2026-09-25 18:53:48'),
(171, 19, 38, 'DEMO', '2026-09-25 18:53:48'),
(172, 19, 39, 'DEMO', '2026-09-25 18:53:48'),
(173, 19, 47, 'DEMO', '2026-09-25 18:53:48'),
(174, 20, 35, 'DEMO', '2026-09-25 18:53:48'),
(175, 20, 36, 'DEMO', '2026-09-25 18:53:48'),
(176, 20, 37, 'DEMO', '2026-09-25 18:53:48'),
(177, 20, 38, 'DEMO', '2026-09-25 18:53:48'),
(178, 20, 39, 'DEMO', '2026-09-25 18:53:48'),
(179, 20, 49, 'DEMO', '2026-09-25 18:53:48'),
(180, 21, 51, 'DEMO', '2026-09-25 18:53:48'),
(181, 21, 52, 'DEMO', '2026-09-25 18:53:48'),
(182, 21, 53, 'DEMO', '2026-09-25 18:53:48'),
(183, 22, 51, 'DEMO', '2026-09-25 18:53:48'),
(184, 22, 52, 'DEMO', '2026-09-25 18:53:48'),
(185, 22, 53, 'DEMO', '2026-09-25 18:53:48'),
(186, 23, 51, 'DEMO', '2026-09-25 18:53:48'),
(187, 23, 52, 'DEMO', '2026-09-25 18:53:48'),
(188, 23, 53, 'DEMO', '2026-09-25 18:53:48'),
(189, 24, 54, 'DEMO', '2026-09-25 18:53:48'),
(190, 25, 60, 'DEMO', '2026-09-25 18:53:48'),
(191, 26, 61, 'DEMO', '2026-09-25 18:53:48');

-- --------------------------------------------------------

--
-- Table structure for table `special_classes`
--

CREATE TABLE `special_classes` (
  `special_class_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `class_type` enum('REMEDIAL','IRREGULAR','OCTOBERIAN') NOT NULL,
  `recurrence` enum('WEEKLY') NOT NULL DEFAULT 'WEEKLY',
  `teacher_id` int(10) UNSIGNED DEFAULT NULL,
  `approved_duration_minutes` smallint(5) UNSIGNED DEFAULT NULL,
  `approval_reference` varchar(150) DEFAULT NULL,
  `status` enum('DRAFT','ACTIVE','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_class_meetings`
--

CREATE TABLE `special_class_meetings` (
  `special_meeting_id` bigint(20) UNSIGNED NOT NULL,
  `special_class_id` bigint(20) UNSIGNED NOT NULL,
  `meeting_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_id` int(10) UNSIGNED DEFAULT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `delivery_mode` enum('F2F','ONLINE') NOT NULL,
  `status` enum('SCHEDULED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_class_policies`
--

CREATE TABLE `special_class_policies` (
  `special_class_policy_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `class_type` enum('REMEDIAL','IRREGULAR','OCTOBERIAN') NOT NULL,
  `recurrence` enum('WEEKLY') NOT NULL DEFAULT 'WEEKLY',
  `approved_duration_minutes` smallint(5) UNSIGNED DEFAULT NULL,
  `delivery_mode` enum('F2F','ONLINE') DEFAULT NULL,
  `policy_status` enum('PENDING','APPROVED','REVOKED') NOT NULL DEFAULT 'PENDING',
  `approval_reference` varchar(150) DEFAULT NULL,
  `approved_by` varchar(150) DEFAULT NULL,
  `approval_evidence_sha256` char(64) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `octoberian_specific_rules_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_class_students`
--

CREATE TABLE `special_class_students` (
  `special_class_id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` bigint(20) UNSIGNED NOT NULL,
  `student_number` varchar(40) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `home_section_id` int(10) UNSIGNED NOT NULL,
  `major_section_id` int(10) UNSIGNED DEFAULT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_number`, `first_name`, `last_name`, `program_id`, `academic_period_id`, `home_section_id`, `major_section_id`, `data_origin`, `created_at`) VALUES
(1, 'DEMO-BSIT-2026S1-0001', 'Daniel', 'Torres', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(2, 'DEMO-BSIT-2026S1-0011', 'Joaquin', 'Mercado', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(3, 'DEMO-BSIT-2026S1-0021', 'Bianca', 'Rivera', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(4, 'DEMO-BSIT-2026S1-0031', 'Patricia', 'Soriano', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(5, 'DEMO-BSIT-2026S1-0041', 'Miguel', 'Navarro', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(6, 'DEMO-BSIT-2026S1-0002', 'Isabella', 'Flores', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(7, 'DEMO-BSIT-2026S1-0012', 'Joshua', 'Aquino', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(8, 'DEMO-BSIT-2026S1-0022', 'Daniel', 'Ramos', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(9, 'DEMO-BSIT-2026S1-0032', 'Adrian', 'Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(10, 'DEMO-BSIT-2026S1-0042', 'Mikaela', 'Soriano', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(11, 'DEMO-BSIT-2026S1-0003', 'Nathan', 'Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(12, 'DEMO-BSIT-2026S1-0013', 'Marco', 'Santiago', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(13, 'DEMO-BSIT-2026S1-0023', 'Paolo', 'Aquino', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(14, 'DEMO-BSIT-2026S1-0033', 'Luis', 'Dela Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(15, 'DEMO-BSIT-2026S1-0043', 'Joshua', 'Reyes', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(16, 'DEMO-BSIT-2026S1-0004', 'Marco', 'Salazar', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(17, 'DEMO-BSIT-2026S1-0014', 'Enzo', 'Santiago', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(18, 'DEMO-BSIT-2026S1-0024', 'Luis', 'Valdez', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(19, 'DEMO-BSIT-2026S1-0034', 'Angela', 'Fernandez', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(20, 'DEMO-BSIT-2026S1-0044', 'Isabella', 'Reyes', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(21, 'DEMO-BSIT-2026S1-0005', 'Joshua', 'Fernandez', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(22, 'DEMO-BSIT-2026S1-0015', 'Bianca', 'Valdez', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(23, 'DEMO-BSIT-2026S1-0025', 'Kristine', 'Espinoza', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(24, 'DEMO-BSIT-2026S1-0035', 'Daniel', 'Salazar', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(25, 'DEMO-BSIT-2026S1-0045', 'Enzo', 'Mendoza', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(26, 'DEMO-BSIT-2026S1-0006', 'Kristine', 'Domingo', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(27, 'DEMO-BSIT-2026S1-0016', 'Daniel', 'Mercado', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(28, 'DEMO-BSIT-2026S1-0026', 'Joshua', 'Rivera', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(29, 'DEMO-BSIT-2026S1-0036', 'Isabella', 'Soriano', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(30, 'DEMO-BSIT-2026S1-0046', 'Sofia', 'Salazar', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(31, 'DEMO-BSIT-2026S1-0007', 'Luis', 'Mendoza', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(32, 'DEMO-BSIT-2026S1-0017', 'Sofia', 'Gonzales', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(33, 'DEMO-BSIT-2026S1-0027', 'Mikaela', 'Mercado', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(34, 'DEMO-BSIT-2026S1-0037', 'Nathan', 'Reyes', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(35, 'DEMO-BSIT-2026S1-0047', 'Alyssa', 'Dela Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(36, 'DEMO-BSIT-2026S1-0008', 'Patricia', 'Torres', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(37, 'DEMO-BSIT-2026S1-0018', 'Bianca', 'Villanueva', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(38, 'DEMO-BSIT-2026S1-0028', 'Kristine', 'Gonzales', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(39, 'DEMO-BSIT-2026S1-0038', 'Daniel', 'Castillo', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(40, 'DEMO-BSIT-2026S1-0048', 'Enzo', 'Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(41, 'DEMO-BSIT-2026S1-0009', 'Gabriel', 'Soriano', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(42, 'DEMO-BSIT-2026S1-0019', 'Enzo', 'Rivera', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(43, 'DEMO-BSIT-2026S1-0029', 'Luis', 'Mercado', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(44, 'DEMO-BSIT-2026S1-0039', 'Paolo', 'Torres', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(45, 'DEMO-BSIT-2026S1-0049', 'Bianca', 'Dela Cruz', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(46, 'DEMO-BSIT-2026S1-0010', 'Miguel', 'Gonzales', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(47, 'DEMO-BSIT-2026S1-0020', 'Enzo', 'Villanueva', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(48, 'DEMO-BSIT-2026S1-0030', 'Mikaela', 'Reyes', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(49, 'DEMO-BSIT-2026S1-0040', 'Kristine', 'Fernandez', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(50, 'DEMO-BSIT-2026S1-0050', 'Vincent', 'Aquino', 4, 1, 1, NULL, 'DEMO', '2026-09-24 21:04:23'),
(51, 'DEMO-BSIT-2026S1-0051', 'Paolo', 'Espinoza', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(52, 'DEMO-BSIT-2026S1-0061', 'Marco', 'Garcia', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(53, 'DEMO-BSIT-2026S1-0071', 'Nathan', 'Flores', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(54, 'DEMO-BSIT-2026S1-0081', 'Carlo', 'Santiago', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(55, 'DEMO-BSIT-2026S1-0091', 'Patricia', 'Navarro', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(56, 'DEMO-BSIT-2026S1-0052', 'Jasmine', 'Santos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(57, 'DEMO-BSIT-2026S1-0062', 'Miguel', 'Bautista', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(58, 'DEMO-BSIT-2026S1-0072', 'Angela', 'Reyes', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(59, 'DEMO-BSIT-2026S1-0082', 'Vincent', 'Rivera', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(60, 'DEMO-BSIT-2026S1-0092', 'Joaquin', 'Castillo', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(61, 'DEMO-BSIT-2026S1-0053', 'Carlo', 'Mercado', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(62, 'DEMO-BSIT-2026S1-0063', 'Kristine', 'Santos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(63, 'DEMO-BSIT-2026S1-0073', 'Vincent', 'Castillo', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(64, 'DEMO-BSIT-2026S1-0083', 'Angela', 'Mercado', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(65, 'DEMO-BSIT-2026S1-0093', 'Miguel', 'Torres', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(66, 'DEMO-BSIT-2026S1-0054', 'Patricia', 'Villanueva', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(67, 'DEMO-BSIT-2026S1-0064', 'Daniel', 'Santos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(68, 'DEMO-BSIT-2026S1-0074', 'Adrian', 'Soriano', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(69, 'DEMO-BSIT-2026S1-0084', 'Miguel', 'Bautista', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(70, 'DEMO-BSIT-2026S1-0094', 'Sofia', 'Domingo', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(71, 'DEMO-BSIT-2026S1-0055', 'Marco', 'Gonzales', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(72, 'DEMO-BSIT-2026S1-0065', 'Angela', 'Mercado', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(73, 'DEMO-BSIT-2026S1-0075', 'Miguel', 'Reyes', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(74, 'DEMO-BSIT-2026S1-0085', 'Joaquin', 'Santos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(75, 'DEMO-BSIT-2026S1-0095', 'Alyssa', 'Castillo', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(76, 'DEMO-BSIT-2026S1-0056', 'Miguel', 'Santiago', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(77, 'DEMO-BSIT-2026S1-0066', 'Enzo', 'Valdez', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(78, 'DEMO-BSIT-2026S1-0076', 'Mikaela', 'Fernandez', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(79, 'DEMO-BSIT-2026S1-0086', 'Joshua', 'Espinoza', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(80, 'DEMO-BSIT-2026S1-0096', 'Bianca', 'Cruz', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(81, 'DEMO-BSIT-2026S1-0057', 'Joaquin', 'Aquino', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(82, 'DEMO-BSIT-2026S1-0067', 'Isabella', 'Ramos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(83, 'DEMO-BSIT-2026S1-0077', 'Patricia', 'Salazar', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(84, 'DEMO-BSIT-2026S1-0087', 'Gabriel', 'Valdez', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(85, 'DEMO-BSIT-2026S1-0097', 'Nathan', 'Dela Cruz', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(86, 'DEMO-BSIT-2026S1-0058', 'Mikaela', 'Ramos', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(87, 'DEMO-BSIT-2026S1-0068', 'Sofia', 'Aquino', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(88, 'DEMO-BSIT-2026S1-0078', 'Miguel', 'Dela Cruz', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(89, 'DEMO-BSIT-2026S1-0088', 'Joaquin', 'Santiago', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(90, 'DEMO-BSIT-2026S1-0098', 'Alyssa', 'Salazar', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(91, 'DEMO-BSIT-2026S1-0059', 'Patricia', 'Aquino', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(92, 'DEMO-BSIT-2026S1-0069', 'Alyssa', 'Santiago', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(93, 'DEMO-BSIT-2026S1-0079', 'Kristine', 'Cruz', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(94, 'DEMO-BSIT-2026S1-0089', 'Luis', 'Aquino', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(95, 'DEMO-BSIT-2026S1-0099', 'Angela', 'Flores', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(96, 'DEMO-BSIT-2026S1-0060', 'Andrei', 'Espinoza', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(97, 'DEMO-BSIT-2026S1-0070', 'Carlo', 'Salazar', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(98, 'DEMO-BSIT-2026S1-0080', 'Nathan', 'Aquino', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(99, 'DEMO-BSIT-2026S1-0090', 'Marco', 'Fernandez', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(100, 'DEMO-BSIT-2026S1-0100', 'Marco', 'Salazar', 4, 1, 2, NULL, 'DEMO', '2026-09-24 21:04:23'),
(101, 'DEMO-BSIT-2026S1-0101', 'Patricia', 'Dela Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(102, 'DEMO-BSIT-2026S1-0111', 'Carlo', 'Aquino', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(103, 'DEMO-BSIT-2026S1-0121', 'Adrian', 'Espinoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(104, 'DEMO-BSIT-2026S1-0131', 'Daniel', 'Salazar', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(105, 'DEMO-BSIT-2026S1-0141', 'Nathan', 'Mendoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(106, 'DEMO-BSIT-2026S1-0102', 'Adrian', 'Domingo', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(107, 'DEMO-BSIT-2026S1-0112', 'Vincent', 'Villanueva', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(108, 'DEMO-BSIT-2026S1-0122', 'Joshua', 'Santos', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(109, 'DEMO-BSIT-2026S1-0132', 'Bianca', 'Castillo', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(110, 'DEMO-BSIT-2026S1-0142', 'Paolo', 'Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(111, 'DEMO-BSIT-2026S1-0103', 'Camille', 'Mendoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(112, 'DEMO-BSIT-2026S1-0113', 'Paolo', 'Gonzales', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(113, 'DEMO-BSIT-2026S1-0123', 'Gabriel', 'Mercado', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(114, 'DEMO-BSIT-2026S1-0133', 'Jasmine', 'Torres', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(115, 'DEMO-BSIT-2026S1-0143', 'Vincent', 'Flores', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(116, 'DEMO-BSIT-2026S1-0104', 'Sofia', 'Mendoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(117, 'DEMO-BSIT-2026S1-0114', 'Camille', 'Gonzales', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(118, 'DEMO-BSIT-2026S1-0124', 'Nathan', 'Bautista', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(119, 'DEMO-BSIT-2026S1-0134', 'Mikaela', 'Reyes', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(120, 'DEMO-BSIT-2026S1-0144', 'Adrian', 'Fernandez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(121, 'DEMO-BSIT-2026S1-0105', 'Vincent', 'Torres', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(122, 'DEMO-BSIT-2026S1-0115', 'Adrian', 'Villanueva', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(123, 'DEMO-BSIT-2026S1-0125', 'Isabella', 'Rivera', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(124, 'DEMO-BSIT-2026S1-0135', 'Joshua', 'Soriano', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(125, 'DEMO-BSIT-2026S1-0145', 'Miguel', 'Salazar', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(126, 'DEMO-BSIT-2026S1-0106', 'Isabella', 'Fernandez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(127, 'DEMO-BSIT-2026S1-0116', 'Patricia', 'Valdez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(128, 'DEMO-BSIT-2026S1-0126', 'Vincent', 'Santiago', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(129, 'DEMO-BSIT-2026S1-0136', 'Adrian', 'Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(130, 'DEMO-BSIT-2026S1-0146', 'Marco', 'Mendoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(131, 'DEMO-BSIT-2026S1-0107', 'Jasmine', 'Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(132, 'DEMO-BSIT-2026S1-0117', 'Mikaela', 'Espinoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(133, 'DEMO-BSIT-2026S1-0127', 'Angela', 'Garcia', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(134, 'DEMO-BSIT-2026S1-0137', 'Camille', 'Dela Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(135, 'DEMO-BSIT-2026S1-0147', 'Joshua', 'Reyes', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(136, 'DEMO-BSIT-2026S1-0108', 'Alyssa', 'Dela Cruz', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(137, 'DEMO-BSIT-2026S1-0118', 'Joaquin', 'Valdez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(138, 'DEMO-BSIT-2026S1-0128', 'Bianca', 'Espinoza', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(139, 'DEMO-BSIT-2026S1-0138', 'Andrei', 'Navarro', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(140, 'DEMO-BSIT-2026S1-0148', 'Luis', 'Castillo', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(141, 'DEMO-BSIT-2026S1-0109', 'Paolo', 'Navarro', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(142, 'DEMO-BSIT-2026S1-0119', 'Luis', 'Santiago', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(143, 'DEMO-BSIT-2026S1-0129', 'Nathan', 'Aquino', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(144, 'DEMO-BSIT-2026S1-0139', 'Gabriel', 'Fernandez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(145, 'DEMO-BSIT-2026S1-0149', 'Adrian', 'Domingo', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(146, 'DEMO-BSIT-2026S1-0110', 'Jasmine', 'Santiago', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(147, 'DEMO-BSIT-2026S1-0120', 'Camille', 'Valdez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(148, 'DEMO-BSIT-2026S1-0130', 'Paolo', 'Fernandez', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(149, 'DEMO-BSIT-2026S1-0140', 'Bianca', 'Domingo', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(150, 'DEMO-BSIT-2026S1-0150', 'Joshua', 'Villanueva', 4, 1, 3, NULL, 'DEMO', '2026-09-24 21:04:23'),
(151, 'DEMO-BSIT-2026S1-0151', 'Gabriel', 'Santos', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(152, 'DEMO-BSIT-2026S1-0161', 'Angela', 'Mercado', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(153, 'DEMO-BSIT-2026S1-0171', 'Camille', 'Domingo', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(154, 'DEMO-BSIT-2026S1-0181', 'Kristine', 'Rivera', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(155, 'DEMO-BSIT-2026S1-0191', 'Daniel', 'Soriano', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(156, 'DEMO-BSIT-2026S1-0152', 'Camille', 'Santiago', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(157, 'DEMO-BSIT-2026S1-0162', 'Jasmine', 'Aquino', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(158, 'DEMO-BSIT-2026S1-0172', 'Gabriel', 'Fernandez', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(159, 'DEMO-BSIT-2026S1-0182', 'Joshua', 'Ramos', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(160, 'DEMO-BSIT-2026S1-0192', 'Bianca', 'Salazar', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(161, 'DEMO-BSIT-2026S1-0153', 'Joaquin', 'Valdez', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(162, 'DEMO-BSIT-2026S1-0163', 'Bianca', 'Ramos', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(163, 'DEMO-BSIT-2026S1-0173', 'Patricia', 'Cruz', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(164, 'DEMO-BSIT-2026S1-0183', 'Gabriel', 'Garcia', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(165, 'DEMO-BSIT-2026S1-0193', 'Jasmine', 'Fernandez', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(166, 'DEMO-BSIT-2026S1-0154', 'Alyssa', 'Aquino', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(167, 'DEMO-BSIT-2026S1-0164', 'Patricia', 'Santiago', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(168, 'DEMO-BSIT-2026S1-0174', 'Isabella', 'Cruz', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(169, 'DEMO-BSIT-2026S1-0184', 'Jasmine', 'Valdez', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(170, 'DEMO-BSIT-2026S1-0194', 'Gabriel', 'Dela Cruz', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(171, 'DEMO-BSIT-2026S1-0155', 'Angela', 'Santiago', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(172, 'DEMO-BSIT-2026S1-0165', 'Marco', 'Aquino', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(173, 'DEMO-BSIT-2026S1-0175', 'Nathan', 'Dela Cruz', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(174, 'DEMO-BSIT-2026S1-0185', 'Isabella', 'Espinoza', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(175, 'DEMO-BSIT-2026S1-0195', 'Joshua', 'Navarro', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(176, 'DEMO-BSIT-2026S1-0156', 'Jasmine', 'Rivera', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(177, 'DEMO-BSIT-2026S1-0166', 'Luis', 'Bautista', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(178, 'DEMO-BSIT-2026S1-0176', 'Paolo', 'Torres', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(179, 'DEMO-BSIT-2026S1-0186', 'Daniel', 'Gonzales', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(180, 'DEMO-BSIT-2026S1-0196', 'Joaquin', 'Soriano', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(181, 'DEMO-BSIT-2026S1-0157', 'Carlo', 'Villanueva', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(182, 'DEMO-BSIT-2026S1-0167', 'Kristine', 'Santos', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(183, 'DEMO-BSIT-2026S1-0177', 'Alyssa', 'Castillo', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(184, 'DEMO-BSIT-2026S1-0187', 'Sofia', 'Villanueva', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(185, 'DEMO-BSIT-2026S1-0197', 'Camille', 'Torres', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(186, 'DEMO-BSIT-2026S1-0158', 'Paolo', 'Rivera', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(187, 'DEMO-BSIT-2026S1-0168', 'Gabriel', 'Mercado', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(188, 'DEMO-BSIT-2026S1-0178', 'Nathan', 'Domingo', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(189, 'DEMO-BSIT-2026S1-0188', 'Bianca', 'Rivera', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(190, 'DEMO-BSIT-2026S1-0198', 'Andrei', 'Mendoza', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(191, 'DEMO-BSIT-2026S1-0159', 'Alyssa', 'Villanueva', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(192, 'DEMO-BSIT-2026S1-0169', 'Patricia', 'Gonzales', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(193, 'DEMO-BSIT-2026S1-0179', 'Bianca', 'Soriano', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(194, 'DEMO-BSIT-2026S1-0189', 'Jasmine', 'Villanueva', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(195, 'DEMO-BSIT-2026S1-0199', 'Mikaela', 'Reyes', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(196, 'DEMO-BSIT-2026S1-0160', 'Daniel', 'Rivera', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(197, 'DEMO-BSIT-2026S1-0170', 'Adrian', 'Mendoza', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(198, 'DEMO-BSIT-2026S1-0180', 'Camille', 'Villanueva', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(199, 'DEMO-BSIT-2026S1-0190', 'Angela', 'Domingo', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(200, 'DEMO-BSIT-2026S1-0200', 'Luis', 'Torres', 4, 1, 4, NULL, 'DEMO', '2026-09-24 21:04:23'),
(201, 'DEMO-BSIT-2026S1-0201', 'Joaquin', 'Soriano', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(202, 'DEMO-BSIT-2026S1-0211', 'Alyssa', 'Santos', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(203, 'DEMO-BSIT-2026S1-0221', 'Andrei', 'Villanueva', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(204, 'DEMO-BSIT-2026S1-0231', 'Bianca', 'Reyes', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(205, 'DEMO-BSIT-2026S1-0241', 'Angela', 'Dela Cruz', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(206, 'DEMO-BSIT-2026S1-0202', 'Patricia', 'Salazar', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(207, 'DEMO-BSIT-2026S1-0212', 'Isabella', 'Espinoza', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(208, 'DEMO-BSIT-2026S1-0222', 'Joaquin', 'Garcia', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(209, 'DEMO-BSIT-2026S1-0232', 'Vincent', 'Dela Cruz', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(210, 'DEMO-BSIT-2026S1-0242', 'Enzo', 'Reyes', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(211, 'DEMO-BSIT-2026S1-0203', 'Gabriel', 'Flores', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(212, 'DEMO-BSIT-2026S1-0213', 'Jasmine', 'Garcia', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(213, 'DEMO-BSIT-2026S1-0223', 'Miguel', 'Ramos', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(214, 'DEMO-BSIT-2026S1-0233', 'Sofia', 'Navarro', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(215, 'DEMO-BSIT-2026S1-0243', 'Isabella', 'Castillo', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(216, 'DEMO-BSIT-2026S1-0204', 'Jasmine', 'Fernandez', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(217, 'DEMO-BSIT-2026S1-0214', 'Gabriel', 'Garcia', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(218, 'DEMO-BSIT-2026S1-0224', 'Angela', 'Ramos', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(219, 'DEMO-BSIT-2026S1-0234', 'Camille', 'Salazar', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(220, 'DEMO-BSIT-2026S1-0244', 'Patricia', 'Mendoza', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(221, 'DEMO-BSIT-2026S1-0205', 'Bianca', 'Salazar', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(222, 'DEMO-BSIT-2026S1-0215', 'Patricia', 'Ramos', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(223, 'DEMO-BSIT-2026S1-0225', 'Vincent', 'Aquino', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(224, 'DEMO-BSIT-2026S1-0235', 'Joaquin', 'Flores', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(225, 'DEMO-BSIT-2026S1-0245', 'Gabriel', 'Domingo', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(226, 'DEMO-BSIT-2026S1-0206', 'Vincent', 'Mendoza', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(227, 'DEMO-BSIT-2026S1-0216', 'Joaquin', 'Gonzales', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(228, 'DEMO-BSIT-2026S1-0226', 'Isabella', 'Mercado', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(229, 'DEMO-BSIT-2026S1-0236', 'Andrei', 'Torres', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(230, 'DEMO-BSIT-2026S1-0246', 'Luis', 'Flores', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(231, 'DEMO-BSIT-2026S1-0207', 'Sofia', 'Reyes', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(232, 'DEMO-BSIT-2026S1-0217', 'Camille', 'Bautista', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(233, 'DEMO-BSIT-2026S1-0227', 'Jasmine', 'Gonzales', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(234, 'DEMO-BSIT-2026S1-0237', 'Marco', 'Soriano', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(235, 'DEMO-BSIT-2026S1-0247', 'Joaquin', 'Navarro', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(236, 'DEMO-BSIT-2026S1-0208', 'Bianca', 'Castillo', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(237, 'DEMO-BSIT-2026S1-0218', 'Patricia', 'Rivera', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(238, 'DEMO-BSIT-2026S1-0228', 'Daniel', 'Mercado', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(239, 'DEMO-BSIT-2026S1-0238', 'Kristine', 'Reyes', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(240, 'DEMO-BSIT-2026S1-0248', 'Mikaela', 'Fernandez', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(241, 'DEMO-BSIT-2026S1-0209', 'Nathan', 'Reyes', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(242, 'DEMO-BSIT-2026S1-0219', 'Gabriel', 'Villanueva', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(243, 'DEMO-BSIT-2026S1-0229', 'Sofia', 'Santos', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(244, 'DEMO-BSIT-2026S1-0239', 'Camille', 'Soriano', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(245, 'DEMO-BSIT-2026S1-0249', 'Andrei', 'Cruz', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(246, 'DEMO-BSIT-2026S1-0210', 'Angela', 'Mercado', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(247, 'DEMO-BSIT-2026S1-0220', 'Marco', 'Rivera', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(248, 'DEMO-BSIT-2026S1-0230', 'Nathan', 'Castillo', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(249, 'DEMO-BSIT-2026S1-0240', 'Alyssa', 'Navarro', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(250, 'DEMO-BSIT-2026S1-0250', 'Adrian', 'Santiago', 4, 1, 5, NULL, 'DEMO', '2026-09-24 21:04:23'),
(251, 'DEMO-BSIT-2026S1-0251', 'Luis', 'Garcia', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(252, 'DEMO-BSIT-2026S1-0261', 'Enzo', 'Ramos', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(253, 'DEMO-BSIT-2026S1-0271', 'Mikaela', 'Cruz', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(254, 'DEMO-BSIT-2026S1-0281', 'Patricia', 'Valdez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(255, 'DEMO-BSIT-2026S1-0291', 'Carlo', 'Fernandez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(256, 'DEMO-BSIT-2026S1-0252', 'Gabriel', 'Mercado', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(257, 'DEMO-BSIT-2026S1-0262', 'Paolo', 'Rivera', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(258, 'DEMO-BSIT-2026S1-0272', 'Miguel', 'Mendoza', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(259, 'DEMO-BSIT-2026S1-0282', 'Adrian', 'Bautista', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(260, 'DEMO-BSIT-2026S1-0292', 'Daniel', 'Domingo', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(261, 'DEMO-BSIT-2026S1-0253', 'Patricia', 'Rivera', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(262, 'DEMO-BSIT-2026S1-0263', 'Daniel', 'Villanueva', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(263, 'DEMO-BSIT-2026S1-0273', 'Joaquin', 'Reyes', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(264, 'DEMO-BSIT-2026S1-0283', 'Camille', 'Rivera', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(265, 'DEMO-BSIT-2026S1-0293', 'Sofia', 'Soriano', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(266, 'DEMO-BSIT-2026S1-0254', 'Bianca', 'Gonzales', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(267, 'DEMO-BSIT-2026S1-0264', 'Kristine', 'Villanueva', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(268, 'DEMO-BSIT-2026S1-0274', 'Alyssa', 'Domingo', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(269, 'DEMO-BSIT-2026S1-0284', 'Paolo', 'Gonzales', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(270, 'DEMO-BSIT-2026S1-0294', 'Camille', 'Castillo', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(271, 'DEMO-BSIT-2026S1-0255', 'Jasmine', 'Villanueva', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(272, 'DEMO-BSIT-2026S1-0265', 'Miguel', 'Rivera', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(273, 'DEMO-BSIT-2026S1-0275', 'Angela', 'Mendoza', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(274, 'DEMO-BSIT-2026S1-0285', 'Daniel', 'Mercado', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(275, 'DEMO-BSIT-2026S1-0295', 'Adrian', 'Reyes', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(276, 'DEMO-BSIT-2026S1-0256', 'Paolo', 'Garcia', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(277, 'DEMO-BSIT-2026S1-0266', 'Gabriel', 'Santiago', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(278, 'DEMO-BSIT-2026S1-0276', 'Nathan', 'Navarro', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(279, 'DEMO-BSIT-2026S1-0286', 'Isabella', 'Valdez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(280, 'DEMO-BSIT-2026S1-0296', 'Joshua', 'Dela Cruz', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(281, 'DEMO-BSIT-2026S1-0257', 'Daniel', 'Ramos', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(282, 'DEMO-BSIT-2026S1-0267', 'Andrei', 'Aquino', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(283, 'DEMO-BSIT-2026S1-0277', 'Carlo', 'Fernandez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(284, 'DEMO-BSIT-2026S1-0287', 'Jasmine', 'Ramos', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(285, 'DEMO-BSIT-2026S1-0297', 'Mikaela', 'Salazar', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(286, 'DEMO-BSIT-2026S1-0258', 'Jasmine', 'Garcia', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(287, 'DEMO-BSIT-2026S1-0268', 'Luis', 'Espinoza', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(288, 'DEMO-BSIT-2026S1-0278', 'Sofia', 'Navarro', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(289, 'DEMO-BSIT-2026S1-0288', 'Vincent', 'Valdez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(290, 'DEMO-BSIT-2026S1-0298', 'Adrian', 'Dela Cruz', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(291, 'DEMO-BSIT-2026S1-0259', 'Carlo', 'Espinoza', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(292, 'DEMO-BSIT-2026S1-0269', 'Adrian', 'Valdez', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(293, 'DEMO-BSIT-2026S1-0279', 'Alyssa', 'Flores', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(294, 'DEMO-BSIT-2026S1-0289', 'Paolo', 'Espinoza', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(295, 'DEMO-BSIT-2026S1-0299', 'Luis', 'Salazar', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(296, 'DEMO-BSIT-2026S1-0260', 'Isabella', 'Garcia', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(297, 'DEMO-BSIT-2026S1-0270', 'Andrei', 'Dela Cruz', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(298, 'DEMO-BSIT-2026S1-0280', 'Mikaela', 'Ramos', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(299, 'DEMO-BSIT-2026S1-0290', 'Nathan', 'Salazar', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(300, 'DEMO-BSIT-2026S1-0300', 'Enzo', 'Flores', 4, 1, 6, NULL, 'DEMO', '2026-09-24 21:04:23'),
(301, 'DEMO-BSIT-2026S1-0301', 'Carlo', 'Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(302, 'DEMO-BSIT-2026S1-0311', 'Joshua', 'Espinoza', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(303, 'DEMO-BSIT-2026S1-0321', 'Daniel', 'Garcia', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(304, 'DEMO-BSIT-2026S1-0331', 'Adrian', 'Dela Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(305, 'DEMO-BSIT-2026S1-0341', 'Marco', 'Torres', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(306, 'DEMO-BSIT-2026S1-0302', 'Daniel', 'Mendoza', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(307, 'DEMO-BSIT-2026S1-0312', 'Joaquin', 'Gonzales', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(308, 'DEMO-BSIT-2026S1-0322', 'Carlo', 'Bautista', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(309, 'DEMO-BSIT-2026S1-0332', 'Joshua', 'Reyes', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(310, 'DEMO-BSIT-2026S1-0342', 'Luis', 'Flores', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(311, 'DEMO-BSIT-2026S1-0303', 'Paolo', 'Reyes', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(312, 'DEMO-BSIT-2026S1-0313', 'Luis', 'Bautista', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(313, 'DEMO-BSIT-2026S1-0323', 'Jasmine', 'Rivera', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(314, 'DEMO-BSIT-2026S1-0333', 'Gabriel', 'Castillo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(315, 'DEMO-BSIT-2026S1-0343', 'Joaquin', 'Salazar', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(316, 'DEMO-BSIT-2026S1-0304', 'Camille', 'Domingo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(317, 'DEMO-BSIT-2026S1-0314', 'Angela', 'Mercado', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(318, 'DEMO-BSIT-2026S1-0324', 'Marco', 'Santos', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(319, 'DEMO-BSIT-2026S1-0334', 'Nathan', 'Soriano', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(320, 'DEMO-BSIT-2026S1-0344', 'Daniel', 'Navarro', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(321, 'DEMO-BSIT-2026S1-0305', 'Adrian', 'Castillo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(322, 'DEMO-BSIT-2026S1-0315', 'Vincent', 'Santos', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(323, 'DEMO-BSIT-2026S1-0325', 'Patricia', 'Mercado', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(324, 'DEMO-BSIT-2026S1-0335', 'Carlo', 'Reyes', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(325, 'DEMO-BSIT-2026S1-0345', 'Paolo', 'Dela Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(326, 'DEMO-BSIT-2026S1-0306', 'Joshua', 'Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(327, 'DEMO-BSIT-2026S1-0316', 'Carlo', 'Santiago', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(328, 'DEMO-BSIT-2026S1-0326', 'Joaquin', 'Garcia', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(329, 'DEMO-BSIT-2026S1-0336', 'Alyssa', 'Flores', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(330, 'DEMO-BSIT-2026S1-0346', 'Nathan', 'Torres', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(331, 'DEMO-BSIT-2026S1-0307', 'Marco', 'Dela Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(332, 'DEMO-BSIT-2026S1-0317', 'Nathan', 'Valdez', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(333, 'DEMO-BSIT-2026S1-0327', 'Luis', 'Espinoza', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(334, 'DEMO-BSIT-2026S1-0337', 'Angela', 'Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(335, 'DEMO-BSIT-2026S1-0347', 'Isabella', 'Castillo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(336, 'DEMO-BSIT-2026S1-0308', 'Kristine', 'Navarro', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(337, 'DEMO-BSIT-2026S1-0318', 'Vincent', 'Espinoza', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(338, 'DEMO-BSIT-2026S1-0328', 'Joshua', 'Garcia', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(339, 'DEMO-BSIT-2026S1-0338', 'Bianca', 'Flores', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(340, 'DEMO-BSIT-2026S1-0348', 'Paolo', 'Torres', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(341, 'DEMO-BSIT-2026S1-0309', 'Luis', 'Dela Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(342, 'DEMO-BSIT-2026S1-0319', 'Angela', 'Garcia', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(343, 'DEMO-BSIT-2026S1-0329', 'Mikaela', 'Ramos', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(344, 'DEMO-BSIT-2026S1-0339', 'Jasmine', 'Cruz', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(345, 'DEMO-BSIT-2026S1-0349', 'Alyssa', 'Castillo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(346, 'DEMO-BSIT-2026S1-0310', 'Mikaela', 'Garcia', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(347, 'DEMO-BSIT-2026S1-0320', 'Sofia', 'Ramos', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(348, 'DEMO-BSIT-2026S1-0330', 'Miguel', 'Salazar', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(349, 'DEMO-BSIT-2026S1-0340', 'Joshua', 'Castillo', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(350, 'DEMO-BSIT-2026S1-0350', 'Isabella', 'Rivera', 4, 1, 7, NULL, 'DEMO', '2026-09-24 21:04:23'),
(351, 'DEMO-BSIT-2026S1-0351', 'Jasmine', 'Bautista', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(352, 'DEMO-BSIT-2026S1-0361', 'Luis', 'Gonzales', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(353, 'DEMO-BSIT-2026S1-0371', 'Paolo', 'Mendoza', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(354, 'DEMO-BSIT-2026S1-0381', 'Alyssa', 'Villanueva', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(355, 'DEMO-BSIT-2026S1-0391', 'Adrian', 'Torres', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(356, 'DEMO-BSIT-2026S1-0352', 'Sofia', 'Aquino', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(357, 'DEMO-BSIT-2026S1-0362', 'Marco', 'Ramos', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(358, 'DEMO-BSIT-2026S1-0372', 'Jasmine', 'Cruz', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(359, 'DEMO-BSIT-2026S1-0382', 'Carlo', 'Aquino', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(360, 'DEMO-BSIT-2026S1-0392', 'Joshua', 'Fernandez', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(361, 'DEMO-BSIT-2026S1-0353', 'Daniel', 'Espinoza', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(362, 'DEMO-BSIT-2026S1-0363', 'Andrei', 'Garcia', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(363, 'DEMO-BSIT-2026S1-0373', 'Isabella', 'Flores', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(364, 'DEMO-BSIT-2026S1-0383', 'Nathan', 'Santiago', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(365, 'DEMO-BSIT-2026S1-0393', 'Marco', 'Navarro', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(366, 'DEMO-BSIT-2026S1-0354', 'Kristine', 'Santiago', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(367, 'DEMO-BSIT-2026S1-0364', 'Bianca', 'Aquino', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(368, 'DEMO-BSIT-2026S1-0374', 'Joshua', 'Dela Cruz', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(369, 'DEMO-BSIT-2026S1-0384', 'Mikaela', 'Santiago', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(370, 'DEMO-BSIT-2026S1-0394', 'Enzo', 'Salazar', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(371, 'DEMO-BSIT-2026S1-0355', 'Miguel', 'Valdez', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(372, 'DEMO-BSIT-2026S1-0365', 'Enzo', 'Santiago', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(373, 'DEMO-BSIT-2026S1-0375', 'Marco', 'Salazar', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(374, 'DEMO-BSIT-2026S1-0385', 'Andrei', 'Aquino', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(375, 'DEMO-BSIT-2026S1-0395', 'Isabella', 'Flores', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(376, 'DEMO-BSIT-2026S1-0356', 'Marco', 'Villanueva', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(377, 'DEMO-BSIT-2026S1-0366', 'Paolo', 'Santos', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(378, 'DEMO-BSIT-2026S1-0376', 'Camille', 'Soriano', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(379, 'DEMO-BSIT-2026S1-0386', 'Joaquin', 'Bautista', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(380, 'DEMO-BSIT-2026S1-0396', 'Vincent', 'Reyes', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(381, 'DEMO-BSIT-2026S1-0357', 'Andrei', 'Rivera', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(382, 'DEMO-BSIT-2026S1-0367', 'Alyssa', 'Villanueva', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(383, 'DEMO-BSIT-2026S1-0377', 'Kristine', 'Reyes', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(384, 'DEMO-BSIT-2026S1-0387', 'Miguel', 'Rivera', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(385, 'DEMO-BSIT-2026S1-0397', 'Paolo', 'Castillo', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(386, 'DEMO-BSIT-2026S1-0358', 'Luis', 'Bautista', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(387, 'DEMO-BSIT-2026S1-0368', 'Jasmine', 'Santos', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(388, 'DEMO-BSIT-2026S1-0378', 'Gabriel', 'Mendoza', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(389, 'DEMO-BSIT-2026S1-0388', 'Joshua', 'Bautista', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(390, 'DEMO-BSIT-2026S1-0398', 'Bianca', 'Torres', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(391, 'DEMO-BSIT-2026S1-0359', 'Kristine', 'Rivera', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(392, 'DEMO-BSIT-2026S1-0369', 'Isabella', 'Villanueva', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(393, 'DEMO-BSIT-2026S1-0379', 'Andrei', 'Reyes', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(394, 'DEMO-BSIT-2026S1-0389', 'Mikaela', 'Santos', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(395, 'DEMO-BSIT-2026S1-0399', 'Jasmine', 'Castillo', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(396, 'DEMO-BSIT-2026S1-0360', 'Adrian', 'Villanueva', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(397, 'DEMO-BSIT-2026S1-0370', 'Alyssa', 'Reyes', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(398, 'DEMO-BSIT-2026S1-0380', 'Paolo', 'Rivera', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(399, 'DEMO-BSIT-2026S1-0390', 'Miguel', 'Mendoza', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(400, 'DEMO-BSIT-2026S1-0400', 'Daniel', 'Ramos', 4, 1, 8, 11, 'DEMO', '2026-09-24 21:04:23'),
(401, 'DEMO-BSIT-2026S1-0401', 'Sofia', 'Valdez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(402, 'DEMO-BSIT-2026S1-0411', 'Camille', 'Dela Cruz', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(403, 'DEMO-BSIT-2026S1-0421', 'Nathan', 'Navarro', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(404, 'DEMO-BSIT-2026S1-0431', 'Mikaela', 'Ramos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(405, 'DEMO-BSIT-2026S1-0441', 'Adrian', 'Santos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(406, 'DEMO-BSIT-2026S1-0402', 'Enzo', 'Mercado', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(407, 'DEMO-BSIT-2026S1-0412', 'Gabriel', 'Torres', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(408, 'DEMO-BSIT-2026S1-0422', 'Paolo', 'Castillo', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(409, 'DEMO-BSIT-2026S1-0432', 'Miguel', 'Gonzales', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(410, 'DEMO-BSIT-2026S1-0442', 'Joshua', 'Ramos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(411, 'DEMO-BSIT-2026S1-0403', 'Bianca', 'Santos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(412, 'DEMO-BSIT-2026S1-0413', 'Joshua', 'Castillo', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(413, 'DEMO-BSIT-2026S1-0423', 'Daniel', 'Domingo', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(414, 'DEMO-BSIT-2026S1-0433', 'Kristine', 'Mercado', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(415, 'DEMO-BSIT-2026S1-0443', 'Gabriel', 'Aquino', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(416, 'DEMO-BSIT-2026S1-0404', 'Andrei', 'Santos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(417, 'DEMO-BSIT-2026S1-0414', 'Carlo', 'Soriano', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(418, 'DEMO-BSIT-2026S1-0424', 'Joaquin', 'Torres', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(419, 'DEMO-BSIT-2026S1-0434', 'Vincent', 'Villanueva', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(420, 'DEMO-BSIT-2026S1-0444', 'Nathan', 'Valdez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(421, 'DEMO-BSIT-2026S1-0405', 'Marco', 'Villanueva', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(422, 'DEMO-BSIT-2026S1-0415', 'Jasmine', 'Reyes', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(423, 'DEMO-BSIT-2026S1-0425', 'Miguel', 'Soriano', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(424, 'DEMO-BSIT-2026S1-0435', 'Sofia', 'Rivera', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(425, 'DEMO-BSIT-2026S1-0445', 'Bianca', 'Espinoza', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(426, 'DEMO-BSIT-2026S1-0406', 'Miguel', 'Garcia', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(427, 'DEMO-BSIT-2026S1-0416', 'Angela', 'Flores', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(428, 'DEMO-BSIT-2026S1-0426', 'Mikaela', 'Cruz', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(429, 'DEMO-BSIT-2026S1-0436', 'Enzo', 'Ramos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(430, 'DEMO-BSIT-2026S1-0446', 'Vincent', 'Rivera', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(431, 'DEMO-BSIT-2026S1-0407', 'Joaquin', 'Espinoza', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(432, 'DEMO-BSIT-2026S1-0417', 'Vincent', 'Salazar', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(433, 'DEMO-BSIT-2026S1-0427', 'Joshua', 'Fernandez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(434, 'DEMO-BSIT-2026S1-0437', 'Bianca', 'Valdez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(435, 'DEMO-BSIT-2026S1-0447', 'Paolo', 'Bautista', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(436, 'DEMO-BSIT-2026S1-0408', 'Gabriel', 'Aquino', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(437, 'DEMO-BSIT-2026S1-0418', 'Enzo', 'Dela Cruz', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(438, 'DEMO-BSIT-2026S1-0428', 'Miguel', 'Cruz', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(439, 'DEMO-BSIT-2026S1-0438', 'Paolo', 'Santiago', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(440, 'DEMO-BSIT-2026S1-0448', 'Isabella', 'Santos', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(441, 'DEMO-BSIT-2026S1-0409', 'Patricia', 'Santiago', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(442, 'DEMO-BSIT-2026S1-0419', 'Carlo', 'Cruz', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(443, 'DEMO-BSIT-2026S1-0429', 'Joaquin', 'Fernandez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(444, 'DEMO-BSIT-2026S1-0439', 'Vincent', 'Valdez', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(445, 'DEMO-BSIT-2026S1-0449', 'Nathan', 'Bautista', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(446, 'DEMO-BSIT-2026S1-0410', 'Joaquin', 'Navarro', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(447, 'DEMO-BSIT-2026S1-0420', 'Bianca', 'Flores', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(448, 'DEMO-BSIT-2026S1-0430', 'Joshua', 'Garcia', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(449, 'DEMO-BSIT-2026S1-0440', 'Luis', 'Villanueva', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(450, 'DEMO-BSIT-2026S1-0450', 'Sofia', 'Reyes', 4, 1, 9, 12, 'DEMO', '2026-09-24 21:04:23'),
(451, 'DEMO-BSIT-2026S1-0451', 'Vincent', 'Mendoza', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(452, 'DEMO-BSIT-2026S1-0461', 'Patricia', 'Torres', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(453, 'DEMO-BSIT-2026S1-0471', 'Isabella', 'Mercado', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(454, 'DEMO-BSIT-2026S1-0481', 'Enzo', 'Soriano', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(455, 'DEMO-BSIT-2026S1-0491', 'Gabriel', 'Gonzales', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(456, 'DEMO-BSIT-2026S1-0452', 'Isabella', 'Cruz', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(457, 'DEMO-BSIT-2026S1-0462', 'Joaquin', 'Dela Cruz', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(458, 'DEMO-BSIT-2026S1-0472', 'Alyssa', 'Garcia', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(459, 'DEMO-BSIT-2026S1-0482', 'Angela', 'Cruz', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(460, 'DEMO-BSIT-2026S1-0492', 'Camille', 'Espinoza', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(461, 'DEMO-BSIT-2026S1-0453', 'Jasmine', 'Fernandez', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(462, 'DEMO-BSIT-2026S1-0463', 'Luis', 'Navarro', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(463, 'DEMO-BSIT-2026S1-0473', 'Angela', 'Ramos', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(464, 'DEMO-BSIT-2026S1-0483', 'Alyssa', 'Flores', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(465, 'DEMO-BSIT-2026S1-0493', 'Joaquin', 'Aquino', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(466, 'DEMO-BSIT-2026S1-0454', 'Marco', 'Fernandez', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(467, 'DEMO-BSIT-2026S1-0464', 'Sofia', 'Cruz', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(468, 'DEMO-BSIT-2026S1-0474', 'Miguel', 'Ramos', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(469, 'DEMO-BSIT-2026S1-0484', 'Adrian', 'Flores', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(470, 'DEMO-BSIT-2026S1-0494', 'Daniel', 'Valdez', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(471, 'DEMO-BSIT-2026S1-0455', 'Patricia', 'Salazar', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(472, 'DEMO-BSIT-2026S1-0465', 'Vincent', 'Fernandez', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(473, 'DEMO-BSIT-2026S1-0475', 'Joaquin', 'Aquino', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(474, 'DEMO-BSIT-2026S1-0485', 'Miguel', 'Navarro', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(475, 'DEMO-BSIT-2026S1-0495', 'Sofia', 'Santiago', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(476, 'DEMO-BSIT-2026S1-0456', 'Kristine', 'Soriano', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(477, 'DEMO-BSIT-2026S1-0466', 'Bianca', 'Domingo', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(478, 'DEMO-BSIT-2026S1-0476', 'Patricia', 'Bautista', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(479, 'DEMO-BSIT-2026S1-0486', 'Gabriel', 'Castillo', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(480, 'DEMO-BSIT-2026S1-0496', 'Jasmine', 'Santos', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(481, 'DEMO-BSIT-2026S1-0457', 'Luis', 'Reyes', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(482, 'DEMO-BSIT-2026S1-0467', 'Nathan', 'Soriano', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(483, 'DEMO-BSIT-2026S1-0477', 'Gabriel', 'Santos', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(484, 'DEMO-BSIT-2026S1-0487', 'Patricia', 'Reyes', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(485, 'DEMO-BSIT-2026S1-0497', 'Bianca', 'Mercado', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(486, 'DEMO-BSIT-2026S1-0458', 'Patricia', 'Castillo', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(487, 'DEMO-BSIT-2026S1-0468', 'Alyssa', 'Reyes', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(488, 'DEMO-BSIT-2026S1-0478', 'Kristine', 'Villanueva', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(489, 'DEMO-BSIT-2026S1-0488', 'Camille', 'Mendoza', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(490, 'DEMO-BSIT-2026S1-0498', 'Paolo', 'Santos', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(491, 'DEMO-BSIT-2026S1-0459', 'Gabriel', 'Domingo', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(492, 'DEMO-BSIT-2026S1-0469', 'Sofia', 'Soriano', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(493, 'DEMO-BSIT-2026S1-0479', 'Camille', 'Rivera', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(494, 'DEMO-BSIT-2026S1-0489', 'Kristine', 'Torres', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(495, 'DEMO-BSIT-2026S1-0499', 'Alyssa', 'Villanueva', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(496, 'DEMO-BSIT-2026S1-0460', 'Gabriel', 'Castillo', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(497, 'DEMO-BSIT-2026S1-0470', 'Jasmine', 'Gonzales', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(498, 'DEMO-BSIT-2026S1-0480', 'Carlo', 'Reyes', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(499, 'DEMO-BSIT-2026S1-0490', 'Andrei', 'Mercado', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(500, 'DEMO-BSIT-2026S1-0500', 'Joshua', 'Rivera', 4, 1, 10, 13, 'DEMO', '2026-09-24 21:04:23'),
(501, 'STRESS-BSIT-2026S1-0001', 'Kristine', 'Garcia', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(502, 'STRESS-BSIT-2026S1-0011', 'Alyssa', 'Fernandez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(503, 'STRESS-BSIT-2026S1-0021', 'Andrei', 'Navarro', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(504, 'STRESS-BSIT-2026S1-0031', 'Carlo', 'Santiago', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(505, 'STRESS-BSIT-2026S1-0041', 'Sofia', 'Rivera', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(506, 'STRESS-BSIT-2026S1-0002', 'Andrei', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(507, 'STRESS-BSIT-2026S1-0012', 'Isabella', 'Reyes', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(508, 'STRESS-BSIT-2026S1-0022', 'Adrian', 'Castillo', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(509, 'STRESS-BSIT-2026S1-0032', 'Vincent', 'Santos', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(510, 'STRESS-BSIT-2026S1-0042', 'Enzo', 'Espinoza', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(511, 'STRESS-BSIT-2026S1-0003', 'Mikaela', 'Rivera', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(512, 'STRESS-BSIT-2026S1-0013', 'Jasmine', 'Soriano', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(513, 'STRESS-BSIT-2026S1-0023', 'Miguel', 'Domingo', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(514, 'STRESS-BSIT-2026S1-0033', 'Sofia', 'Villanueva', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(515, 'STRESS-BSIT-2026S1-0043', 'Bianca', 'Aquino', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(516, 'STRESS-BSIT-2026S1-0004', 'Jasmine', 'Santos', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01');
INSERT INTO `students` (`student_id`, `student_number`, `first_name`, `last_name`, `program_id`, `academic_period_id`, `home_section_id`, `major_section_id`, `data_origin`, `created_at`) VALUES
(517, 'STRESS-BSIT-2026S1-0014', 'Marco', 'Mendoza', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(518, 'STRESS-BSIT-2026S1-0024', 'Sofia', 'Torres', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(519, 'STRESS-BSIT-2026S1-0034', 'Miguel', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(520, 'STRESS-BSIT-2026S1-0044', 'Andrei', 'Garcia', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(521, 'STRESS-BSIT-2026S1-0005', 'Isabella', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(522, 'STRESS-BSIT-2026S1-0015', 'Andrei', 'Reyes', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(523, 'STRESS-BSIT-2026S1-0025', 'Alyssa', 'Castillo', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(524, 'STRESS-BSIT-2026S1-0035', 'Kristine', 'Rivera', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(525, 'STRESS-BSIT-2026S1-0045', 'Mikaela', 'Santiago', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(526, 'STRESS-BSIT-2026S1-0006', 'Daniel', 'Aquino', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(527, 'STRESS-BSIT-2026S1-0016', 'Kristine', 'Fernandez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(528, 'STRESS-BSIT-2026S1-0026', 'Bianca', 'Cruz', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(529, 'STRESS-BSIT-2026S1-0036', 'Joshua', 'Santiago', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(530, 'STRESS-BSIT-2026S1-0046', 'Luis', 'Gonzales', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(531, 'STRESS-BSIT-2026S1-0007', 'Paolo', 'Espinoza', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(532, 'STRESS-BSIT-2026S1-0017', 'Camille', 'Navarro', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(533, 'STRESS-BSIT-2026S1-0027', 'Jasmine', 'Fernandez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(534, 'STRESS-BSIT-2026S1-0037', 'Gabriel', 'Aquino', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(535, 'STRESS-BSIT-2026S1-0047', 'Adrian', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(536, 'STRESS-BSIT-2026S1-0008', 'Bianca', 'Garcia', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(537, 'STRESS-BSIT-2026S1-0018', 'Patricia', 'Fernandez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(538, 'STRESS-BSIT-2026S1-0028', 'Daniel', 'Cruz', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(539, 'STRESS-BSIT-2026S1-0038', 'Joaquin', 'Ramos', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(540, 'STRESS-BSIT-2026S1-0048', 'Gabriel', 'Rivera', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(541, 'STRESS-BSIT-2026S1-0009', 'Enzo', 'Santiago', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(542, 'STRESS-BSIT-2026S1-0019', 'Marco', 'Navarro', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(543, 'STRESS-BSIT-2026S1-0029', 'Paolo', 'Flores', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(544, 'STRESS-BSIT-2026S1-0039', 'Camille', 'Valdez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(545, 'STRESS-BSIT-2026S1-0049', 'Andrei', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(546, 'STRESS-BSIT-2026S1-0010', 'Angela', 'Navarro', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(547, 'STRESS-BSIT-2026S1-0020', 'Marco', 'Fernandez', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(548, 'STRESS-BSIT-2026S1-0030', 'Nathan', 'Aquino', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(549, 'STRESS-BSIT-2026S1-0040', 'Daniel', 'Bautista', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(550, 'STRESS-BSIT-2026S1-0050', 'Joaquin', 'Reyes', 4, 1, 14, NULL, 'DEMO', '2026-09-25 18:42:01'),
(551, 'STRESS-BSIT-2026S1-0051', 'Camille', 'Castillo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(552, 'STRESS-BSIT-2026S1-0061', 'Enzo', 'Domingo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(553, 'STRESS-BSIT-2026S1-0071', 'Gabriel', 'Villanueva', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(554, 'STRESS-BSIT-2026S1-0081', 'Joshua', 'Mendoza', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(555, 'STRESS-BSIT-2026S1-0091', 'Bianca', 'Gonzales', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(556, 'STRESS-BSIT-2026S1-0052', 'Mikaela', 'Navarro', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(557, 'STRESS-BSIT-2026S1-0062', 'Paolo', 'Flores', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(558, 'STRESS-BSIT-2026S1-0072', 'Luis', 'Garcia', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(559, 'STRESS-BSIT-2026S1-0082', 'Adrian', 'Salazar', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(560, 'STRESS-BSIT-2026S1-0092', 'Daniel', 'Santiago', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(561, 'STRESS-BSIT-2026S1-0053', 'Andrei', 'Dela Cruz', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(562, 'STRESS-BSIT-2026S1-0063', 'Daniel', 'Cruz', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(563, 'STRESS-BSIT-2026S1-0073', 'Adrian', 'Espinoza', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(564, 'STRESS-BSIT-2026S1-0083', 'Luis', 'Dela Cruz', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(565, 'STRESS-BSIT-2026S1-0093', 'Angela', 'Aquino', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(566, 'STRESS-BSIT-2026S1-0054', 'Isabella', 'Dela Cruz', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(567, 'STRESS-BSIT-2026S1-0064', 'Adrian', 'Navarro', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(568, 'STRESS-BSIT-2026S1-0074', 'Alyssa', 'Ramos', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(569, 'STRESS-BSIT-2026S1-0084', 'Angela', 'Dela Cruz', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(570, 'STRESS-BSIT-2026S1-0094', 'Luis', 'Valdez', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(571, 'STRESS-BSIT-2026S1-0055', 'Nathan', 'Salazar', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(572, 'STRESS-BSIT-2026S1-0065', 'Miguel', 'Fernandez', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(573, 'STRESS-BSIT-2026S1-0075', 'Paolo', 'Valdez', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(574, 'STRESS-BSIT-2026S1-0085', 'Daniel', 'Salazar', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(575, 'STRESS-BSIT-2026S1-0095', 'Joaquin', 'Santiago', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(576, 'STRESS-BSIT-2026S1-0056', 'Paolo', 'Soriano', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(577, 'STRESS-BSIT-2026S1-0066', 'Marco', 'Domingo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(578, 'STRESS-BSIT-2026S1-0076', 'Enzo', 'Bautista', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(579, 'STRESS-BSIT-2026S1-0086', 'Isabella', 'Soriano', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(580, 'STRESS-BSIT-2026S1-0096', 'Patricia', 'Santos', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(581, 'STRESS-BSIT-2026S1-0057', 'Daniel', 'Reyes', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(582, 'STRESS-BSIT-2026S1-0067', 'Joshua', 'Castillo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(583, 'STRESS-BSIT-2026S1-0077', 'Carlo', 'Rivera', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(584, 'STRESS-BSIT-2026S1-0087', 'Jasmine', 'Reyes', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(585, 'STRESS-BSIT-2026S1-0097', 'Mikaela', 'Bautista', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(586, 'STRESS-BSIT-2026S1-0058', 'Nathan', 'Soriano', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(587, 'STRESS-BSIT-2026S1-0068', 'Miguel', 'Domingo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(588, 'STRESS-BSIT-2026S1-0078', 'Paolo', 'Villanueva', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(589, 'STRESS-BSIT-2026S1-0088', 'Vincent', 'Mendoza', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(590, 'STRESS-BSIT-2026S1-0098', 'Kristine', 'Santos', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(591, 'STRESS-BSIT-2026S1-0059', 'Bianca', 'Domingo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(592, 'STRESS-BSIT-2026S1-0069', 'Kristine', 'Castillo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(593, 'STRESS-BSIT-2026S1-0079', 'Alyssa', 'Santos', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(594, 'STRESS-BSIT-2026S1-0089', 'Sofia', 'Reyes', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(595, 'STRESS-BSIT-2026S1-0099', 'Miguel', 'Bautista', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(596, 'STRESS-BSIT-2026S1-0060', 'Isabella', 'Castillo', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(597, 'STRESS-BSIT-2026S1-0070', 'Patricia', 'Rivera', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(598, 'STRESS-BSIT-2026S1-0080', 'Mikaela', 'Torres', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(599, 'STRESS-BSIT-2026S1-0090', 'Enzo', 'Mercado', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(600, 'STRESS-BSIT-2026S1-0100', 'Nathan', 'Gonzales', 4, 1, 15, NULL, 'DEMO', '2026-09-25 18:42:01'),
(601, 'STRESS-BSIT-2026S1-0101', 'Isabella', 'Villanueva', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(602, 'STRESS-BSIT-2026S1-0111', 'Andrei', 'Domingo', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(603, 'STRESS-BSIT-2026S1-0121', 'Alyssa', 'Castillo', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(604, 'STRESS-BSIT-2026S1-0131', 'Joaquin', 'Rivera', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(605, 'STRESS-BSIT-2026S1-0141', 'Gabriel', 'Santiago', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(606, 'STRESS-BSIT-2026S1-0102', 'Alyssa', 'Aquino', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(607, 'STRESS-BSIT-2026S1-0112', 'Joaquin', 'Dela Cruz', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(608, 'STRESS-BSIT-2026S1-0122', 'Carlo', 'Cruz', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(609, 'STRESS-BSIT-2026S1-0132', 'Andrei', 'Santiago', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(610, 'STRESS-BSIT-2026S1-0142', 'Luis', 'Gonzales', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(611, 'STRESS-BSIT-2026S1-0103', 'Angela', 'Espinoza', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(612, 'STRESS-BSIT-2026S1-0113', 'Camille', 'Cruz', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(613, 'STRESS-BSIT-2026S1-0123', 'Nathan', 'Flores', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(614, 'STRESS-BSIT-2026S1-0133', 'Mikaela', 'Valdez', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(615, 'STRESS-BSIT-2026S1-0143', 'Kristine', 'Villanueva', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(616, 'STRESS-BSIT-2026S1-0104', 'Luis', 'Ramos', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(617, 'STRESS-BSIT-2026S1-0114', 'Paolo', 'Cruz', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(618, 'STRESS-BSIT-2026S1-0124', 'Mikaela', 'Flores', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(619, 'STRESS-BSIT-2026S1-0134', 'Enzo', 'Garcia', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(620, 'STRESS-BSIT-2026S1-0144', 'Daniel', 'Villanueva', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(621, 'STRESS-BSIT-2026S1-0105', 'Kristine', 'Aquino', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(622, 'STRESS-BSIT-2026S1-0115', 'Vincent', 'Dela Cruz', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(623, 'STRESS-BSIT-2026S1-0125', 'Joshua', 'Navarro', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(624, 'STRESS-BSIT-2026S1-0135', 'Bianca', 'Ramos', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(625, 'STRESS-BSIT-2026S1-0145', 'Paolo', 'Gonzales', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(626, 'STRESS-BSIT-2026S1-0106', 'Andrei', 'Mercado', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(627, 'STRESS-BSIT-2026S1-0116', 'Carlo', 'Reyes', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(628, 'STRESS-BSIT-2026S1-0126', 'Joaquin', 'Soriano', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(629, 'STRESS-BSIT-2026S1-0136', 'Alyssa', 'Santos', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(630, 'STRESS-BSIT-2026S1-0146', 'Enzo', 'Ramos', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(631, 'STRESS-BSIT-2026S1-0107', 'Marco', 'Gonzales', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(632, 'STRESS-BSIT-2026S1-0117', 'Enzo', 'Soriano', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(633, 'STRESS-BSIT-2026S1-0127', 'Miguel', 'Domingo', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(634, 'STRESS-BSIT-2026S1-0137', 'Angela', 'Mercado', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(635, 'STRESS-BSIT-2026S1-0147', 'Carlo', 'Garcia', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(636, 'STRESS-BSIT-2026S1-0108', 'Kristine', 'Villanueva', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(637, 'STRESS-BSIT-2026S1-0118', 'Vincent', 'Domingo', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(638, 'STRESS-BSIT-2026S1-0128', 'Joshua', 'Soriano', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(639, 'STRESS-BSIT-2026S1-0138', 'Carlo', 'Rivera', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(640, 'STRESS-BSIT-2026S1-0148', 'Angela', 'Espinoza', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(641, 'STRESS-BSIT-2026S1-0109', 'Camille', 'Gonzales', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(642, 'STRESS-BSIT-2026S1-0119', 'Sofia', 'Soriano', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(643, 'STRESS-BSIT-2026S1-0129', 'Marco', 'Torres', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(644, 'STRESS-BSIT-2026S1-0139', 'Enzo', 'Bautista', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(645, 'STRESS-BSIT-2026S1-0149', 'Vincent', 'Aquino', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(646, 'STRESS-BSIT-2026S1-0110', 'Mikaela', 'Castillo', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(647, 'STRESS-BSIT-2026S1-0120', 'Sofia', 'Torres', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(648, 'STRESS-BSIT-2026S1-0130', 'Miguel', 'Villanueva', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(649, 'STRESS-BSIT-2026S1-0140', 'Andrei', 'Aquino', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(650, 'STRESS-BSIT-2026S1-0150', 'Carlo', 'Flores', 4, 1, 16, NULL, 'DEMO', '2026-09-25 18:42:01'),
(651, 'STRESS-BSIT-2026S1-0151', 'Jasmine', 'Cruz', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(652, 'STRESS-BSIT-2026S1-0161', 'Miguel', 'Flores', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(653, 'STRESS-BSIT-2026S1-0171', 'Sofia', 'Aquino', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(654, 'STRESS-BSIT-2026S1-0181', 'Alyssa', 'Navarro', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(655, 'STRESS-BSIT-2026S1-0191', 'Adrian', 'Espinoza', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(656, 'STRESS-BSIT-2026S1-0152', 'Angela', 'Castillo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(657, 'STRESS-BSIT-2026S1-0162', 'Marco', 'Reyes', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(658, 'STRESS-BSIT-2026S1-0172', 'Nathan', 'Bautista', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(659, 'STRESS-BSIT-2026S1-0182', 'Carlo', 'Castillo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(660, 'STRESS-BSIT-2026S1-0192', 'Joshua', 'Gonzales', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(661, 'STRESS-BSIT-2026S1-0153', 'Vincent', 'Torres', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(662, 'STRESS-BSIT-2026S1-0163', 'Patricia', 'Castillo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(663, 'STRESS-BSIT-2026S1-0173', 'Isabella', 'Santos', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(664, 'STRESS-BSIT-2026S1-0183', 'Nathan', 'Domingo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(665, 'STRESS-BSIT-2026S1-0193', 'Mikaela', 'Mercado', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(666, 'STRESS-BSIT-2026S1-0154', 'Joaquin', 'Domingo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(667, 'STRESS-BSIT-2026S1-0164', 'Bianca', 'Mendoza', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(668, 'STRESS-BSIT-2026S1-0174', 'Patricia', 'Santos', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(669, 'STRESS-BSIT-2026S1-0184', 'Marco', 'Domingo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(670, 'STRESS-BSIT-2026S1-0194', 'Jasmine', 'Mercado', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(671, 'STRESS-BSIT-2026S1-0155', 'Miguel', 'Castillo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(672, 'STRESS-BSIT-2026S1-0165', 'Jasmine', 'Domingo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(673, 'STRESS-BSIT-2026S1-0175', 'Mikaela', 'Mercado', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(674, 'STRESS-BSIT-2026S1-0185', 'Patricia', 'Castillo', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(675, 'STRESS-BSIT-2026S1-0195', 'Bianca', 'Gonzales', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(676, 'STRESS-BSIT-2026S1-0156', 'Gabriel', 'Salazar', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(677, 'STRESS-BSIT-2026S1-0166', 'Angela', 'Flores', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(678, 'STRESS-BSIT-2026S1-0176', 'Miguel', 'Garcia', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(679, 'STRESS-BSIT-2026S1-0186', 'Joaquin', 'Cruz', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(680, 'STRESS-BSIT-2026S1-0196', 'Daniel', 'Espinoza', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(681, 'STRESS-BSIT-2026S1-0157', 'Joshua', 'Fernandez', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(682, 'STRESS-BSIT-2026S1-0167', 'Vincent', 'Salazar', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(683, 'STRESS-BSIT-2026S1-0177', 'Kristine', 'Ramos', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(684, 'STRESS-BSIT-2026S1-0187', 'Miguel', 'Fernandez', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(685, 'STRESS-BSIT-2026S1-0197', 'Paolo', 'Garcia', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(686, 'STRESS-BSIT-2026S1-0158', 'Camille', 'Navarro', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(687, 'STRESS-BSIT-2026S1-0168', 'Jasmine', 'Fernandez', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(688, 'STRESS-BSIT-2026S1-0178', 'Gabriel', 'Valdez', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(689, 'STRESS-BSIT-2026S1-0188', 'Joshua', 'Navarro', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(690, 'STRESS-BSIT-2026S1-0198', 'Carlo', 'Espinoza', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(691, 'STRESS-BSIT-2026S1-0159', 'Kristine', 'Dela Cruz', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(692, 'STRESS-BSIT-2026S1-0169', 'Carlo', 'Navarro', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(693, 'STRESS-BSIT-2026S1-0179', 'Joshua', 'Ramos', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(694, 'STRESS-BSIT-2026S1-0189', 'Gabriel', 'Fernandez', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(695, 'STRESS-BSIT-2026S1-0199', 'Jasmine', 'Garcia', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(696, 'STRESS-BSIT-2026S1-0160', 'Kristine', 'Cruz', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(697, 'STRESS-BSIT-2026S1-0170', 'Alyssa', 'Ramos', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(698, 'STRESS-BSIT-2026S1-0180', 'Angela', 'Flores', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(699, 'STRESS-BSIT-2026S1-0190', 'Miguel', 'Aquino', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(700, 'STRESS-BSIT-2026S1-0200', 'Paolo', 'Garcia', 4, 1, 17, NULL, 'DEMO', '2026-09-25 18:42:01'),
(701, 'STRESS-BSIT-2026S1-0201', 'Daniel', 'Espinoza', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(702, 'STRESS-BSIT-2026S1-0211', 'Kristine', 'Navarro', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(703, 'STRESS-BSIT-2026S1-0221', 'Carlo', 'Flores', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(704, 'STRESS-BSIT-2026S1-0231', 'Patricia', 'Garcia', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(705, 'STRESS-BSIT-2026S1-0241', 'Miguel', 'Villanueva', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(706, 'STRESS-BSIT-2026S1-0202', 'Carlo', 'Rivera', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(707, 'STRESS-BSIT-2026S1-0212', 'Joshua', 'Soriano', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(708, 'STRESS-BSIT-2026S1-0222', 'Vincent', 'Domingo', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(709, 'STRESS-BSIT-2026S1-0232', 'Adrian', 'Villanueva', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(710, 'STRESS-BSIT-2026S1-0242', 'Mikaela', 'Valdez', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(711, 'STRESS-BSIT-2026S1-0203', 'Nathan', 'Bautista', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(712, 'STRESS-BSIT-2026S1-0213', 'Marco', 'Reyes', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(713, 'STRESS-BSIT-2026S1-0223', 'Angela', 'Mendoza', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(714, 'STRESS-BSIT-2026S1-0233', 'Luis', 'Santos', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(715, 'STRESS-BSIT-2026S1-0243', 'Joshua', 'Santiago', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(716, 'STRESS-BSIT-2026S1-0204', 'Marco', 'Bautista', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(717, 'STRESS-BSIT-2026S1-0214', 'Jasmine', 'Reyes', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(718, 'STRESS-BSIT-2026S1-0224', 'Camille', 'Castillo', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(719, 'STRESS-BSIT-2026S1-0234', 'Sofia', 'Rivera', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(720, 'STRESS-BSIT-2026S1-0244', 'Carlo', 'Santiago', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(721, 'STRESS-BSIT-2026S1-0205', 'Joshua', 'Santos', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(722, 'STRESS-BSIT-2026S1-0215', 'Bianca', 'Soriano', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(723, 'STRESS-BSIT-2026S1-0225', 'Adrian', 'Domingo', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(724, 'STRESS-BSIT-2026S1-0235', 'Daniel', 'Mercado', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(725, 'STRESS-BSIT-2026S1-0245', 'Jasmine', 'Valdez', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(726, 'STRESS-BSIT-2026S1-0206', 'Joaquin', 'Santiago', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(727, 'STRESS-BSIT-2026S1-0216', 'Alyssa', 'Salazar', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(728, 'STRESS-BSIT-2026S1-0226', 'Joshua', 'Dela Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(729, 'STRESS-BSIT-2026S1-0236', 'Carlo', 'Aquino', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(730, 'STRESS-BSIT-2026S1-0246', 'Paolo', 'Bautista', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(731, 'STRESS-BSIT-2026S1-0207', 'Miguel', 'Aquino', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(732, 'STRESS-BSIT-2026S1-0217', 'Angela', 'Fernandez', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(733, 'STRESS-BSIT-2026S1-0227', 'Gabriel', 'Navarro', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(734, 'STRESS-BSIT-2026S1-0237', 'Enzo', 'Santiago', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(735, 'STRESS-BSIT-2026S1-0247', 'Alyssa', 'Rivera', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(736, 'STRESS-BSIT-2026S1-0208', 'Andrei', 'Ramos', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(737, 'STRESS-BSIT-2026S1-0218', 'Bianca', 'Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(738, 'STRESS-BSIT-2026S1-0228', 'Joaquin', 'Dela Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(739, 'STRESS-BSIT-2026S1-0238', 'Alyssa', 'Aquino', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(740, 'STRESS-BSIT-2026S1-0248', 'Enzo', 'Mercado', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(741, 'STRESS-BSIT-2026S1-0209', 'Gabriel', 'Garcia', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(742, 'STRESS-BSIT-2026S1-0219', 'Nathan', 'Dela Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(743, 'STRESS-BSIT-2026S1-0229', 'Camille', 'Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(744, 'STRESS-BSIT-2026S1-0239', 'Paolo', 'Ramos', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(745, 'STRESS-BSIT-2026S1-0249', 'Bianca', 'Santos', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(746, 'STRESS-BSIT-2026S1-0210', 'Miguel', 'Dela Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(747, 'STRESS-BSIT-2026S1-0220', 'Jasmine', 'Cruz', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(748, 'STRESS-BSIT-2026S1-0230', 'Mikaela', 'Santiago', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(749, 'STRESS-BSIT-2026S1-0240', 'Kristine', 'Rivera', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(750, 'STRESS-BSIT-2026S1-0250', 'Daniel', 'Castillo', 4, 1, 18, NULL, 'DEMO', '2026-09-25 18:42:01'),
(751, 'STRESS-BSIT-2026S1-0251', 'Sofia', 'Reyes', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(752, 'STRESS-BSIT-2026S1-0261', 'Gabriel', 'Castillo', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(753, 'STRESS-BSIT-2026S1-0271', 'Jasmine', 'Rivera', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(754, 'STRESS-BSIT-2026S1-0281', 'Bianca', 'Reyes', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(755, 'STRESS-BSIT-2026S1-0291', 'Andrei', 'Mercado', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(756, 'STRESS-BSIT-2026S1-0252', 'Enzo', 'Fernandez', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(757, 'STRESS-BSIT-2026S1-0262', 'Miguel', 'Cruz', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(758, 'STRESS-BSIT-2026S1-0272', 'Sofia', 'Santiago', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(759, 'STRESS-BSIT-2026S1-0282', 'Alyssa', 'Fernandez', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(760, 'STRESS-BSIT-2026S1-0292', 'Joaquin', 'Valdez', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(761, 'STRESS-BSIT-2026S1-0253', 'Bianca', 'Navarro', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(762, 'STRESS-BSIT-2026S1-0263', 'Adrian', 'Flores', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(763, 'STRESS-BSIT-2026S1-0273', 'Alyssa', 'Valdez', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(764, 'STRESS-BSIT-2026S1-0283', 'Paolo', 'Navarro', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(765, 'STRESS-BSIT-2026S1-0293', 'Camille', 'Ramos', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(766, 'STRESS-BSIT-2026S1-0254', 'Andrei', 'Navarro', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(767, 'STRESS-BSIT-2026S1-0264', 'Alyssa', 'Dela Cruz', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(768, 'STRESS-BSIT-2026S1-0274', 'Adrian', 'Aquino', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(769, 'STRESS-BSIT-2026S1-0284', 'Camille', 'Navarro', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(770, 'STRESS-BSIT-2026S1-0294', 'Sofia', 'Espinoza', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(771, 'STRESS-BSIT-2026S1-0255', 'Marco', 'Dela Cruz', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(772, 'STRESS-BSIT-2026S1-0265', 'Paolo', 'Salazar', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(773, 'STRESS-BSIT-2026S1-0275', 'Luis', 'Santiago', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(774, 'STRESS-BSIT-2026S1-0285', 'Joaquin', 'Dela Cruz', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(775, 'STRESS-BSIT-2026S1-0295', 'Vincent', 'Valdez', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(776, 'STRESS-BSIT-2026S1-0256', 'Miguel', 'Reyes', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(777, 'STRESS-BSIT-2026S1-0266', 'Nathan', 'Mendoza', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(778, 'STRESS-BSIT-2026S1-0276', 'Gabriel', 'Santos', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(779, 'STRESS-BSIT-2026S1-0286', 'Andrei', 'Torres', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(780, 'STRESS-BSIT-2026S1-0296', 'Carlo', 'Villanueva', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(781, 'STRESS-BSIT-2026S1-0257', 'Adrian', 'Mendoza', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(782, 'STRESS-BSIT-2026S1-0267', 'Bianca', 'Reyes', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(783, 'STRESS-BSIT-2026S1-0277', 'Joshua', 'Bautista', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(784, 'STRESS-BSIT-2026S1-0287', 'Mikaela', 'Soriano', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(785, 'STRESS-BSIT-2026S1-0297', 'Nathan', 'Rivera', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(786, 'STRESS-BSIT-2026S1-0258', 'Mikaela', 'Reyes', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(787, 'STRESS-BSIT-2026S1-0268', 'Sofia', 'Castillo', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(788, 'STRESS-BSIT-2026S1-0278', 'Luis', 'Gonzales', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(789, 'STRESS-BSIT-2026S1-0288', 'Adrian', 'Domingo', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(790, 'STRESS-BSIT-2026S1-0298', 'Alyssa', 'Bautista', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(791, 'STRESS-BSIT-2026S1-0259', 'Andrei', 'Soriano', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(792, 'STRESS-BSIT-2026S1-0269', 'Vincent', 'Torres', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(793, 'STRESS-BSIT-2026S1-0279', 'Adrian', 'Villanueva', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(794, 'STRESS-BSIT-2026S1-0289', 'Miguel', 'Mendoza', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(795, 'STRESS-BSIT-2026S1-0299', 'Sofia', 'Santos', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(796, 'STRESS-BSIT-2026S1-0260', 'Joshua', 'Torres', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(797, 'STRESS-BSIT-2026S1-0270', 'Isabella', 'Villanueva', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(798, 'STRESS-BSIT-2026S1-0280', 'Enzo', 'Mendoza', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(799, 'STRESS-BSIT-2026S1-0290', 'Marco', 'Rivera', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(800, 'STRESS-BSIT-2026S1-0300', 'Gabriel', 'Bautista', 4, 1, 19, NULL, 'DEMO', '2026-09-25 18:42:01'),
(801, 'STRESS-BSIT-2026S1-0301', 'Patricia', 'Gonzales', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(802, 'STRESS-BSIT-2026S1-0311', 'Bianca', 'Castillo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(803, 'STRESS-BSIT-2026S1-0321', 'Kristine', 'Domingo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(804, 'STRESS-BSIT-2026S1-0331', 'Daniel', 'Villanueva', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(805, 'STRESS-BSIT-2026S1-0341', 'Nathan', 'Valdez', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(806, 'STRESS-BSIT-2026S1-0302', 'Kristine', 'Espinoza', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(807, 'STRESS-BSIT-2026S1-0312', 'Alyssa', 'Navarro', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(808, 'STRESS-BSIT-2026S1-0322', 'Patricia', 'Dela Cruz', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(809, 'STRESS-BSIT-2026S1-0332', 'Isabella', 'Aquino', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(810, 'STRESS-BSIT-2026S1-0342', 'Angela', 'Villanueva', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(811, 'STRESS-BSIT-2026S1-0303', 'Camille', 'Aquino', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(812, 'STRESS-BSIT-2026S1-0313', 'Paolo', 'Flores', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(813, 'STRESS-BSIT-2026S1-0323', 'Gabriel', 'Navarro', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(814, 'STRESS-BSIT-2026S1-0333', 'Nathan', 'Espinoza', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(815, 'STRESS-BSIT-2026S1-0343', 'Vincent', 'Rivera', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(816, 'STRESS-BSIT-2026S1-0304', 'Angela', 'Valdez', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(817, 'STRESS-BSIT-2026S1-0314', 'Luis', 'Dela Cruz', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(818, 'STRESS-BSIT-2026S1-0324', 'Enzo', 'Cruz', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(819, 'STRESS-BSIT-2026S1-0334', 'Gabriel', 'Espinoza', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(820, 'STRESS-BSIT-2026S1-0344', 'Adrian', 'Santos', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(821, 'STRESS-BSIT-2026S1-0305', 'Daniel', 'Santiago', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(822, 'STRESS-BSIT-2026S1-0315', 'Kristine', 'Navarro', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(823, 'STRESS-BSIT-2026S1-0325', 'Carlo', 'Flores', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(824, 'STRESS-BSIT-2026S1-0335', 'Joshua', 'Aquino', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(825, 'STRESS-BSIT-2026S1-0345', 'Camille', 'Mercado', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(826, 'STRESS-BSIT-2026S1-0306', 'Isabella', 'Santos', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(827, 'STRESS-BSIT-2026S1-0316', 'Patricia', 'Soriano', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(828, 'STRESS-BSIT-2026S1-0326', 'Daniel', 'Torres', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(829, 'STRESS-BSIT-2026S1-0336', 'Adrian', 'Bautista', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(830, 'STRESS-BSIT-2026S1-0346', 'Gabriel', 'Garcia', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(831, 'STRESS-BSIT-2026S1-0307', 'Nathan', 'Bautista', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(832, 'STRESS-BSIT-2026S1-0317', 'Mikaela', 'Domingo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(833, 'STRESS-BSIT-2026S1-0327', 'Angela', 'Castillo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(834, 'STRESS-BSIT-2026S1-0337', 'Luis', 'Gonzales', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(835, 'STRESS-BSIT-2026S1-0347', 'Andrei', 'Santiago', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(836, 'STRESS-BSIT-2026S1-0308', 'Vincent', 'Santos', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(837, 'STRESS-BSIT-2026S1-0318', 'Joaquin', 'Castillo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(838, 'STRESS-BSIT-2026S1-0328', 'Isabella', 'Domingo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(839, 'STRESS-BSIT-2026S1-0338', 'Patricia', 'Mercado', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(840, 'STRESS-BSIT-2026S1-0348', 'Miguel', 'Valdez', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(841, 'STRESS-BSIT-2026S1-0309', 'Paolo', 'Villanueva', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(842, 'STRESS-BSIT-2026S1-0319', 'Miguel', 'Domingo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(843, 'STRESS-BSIT-2026S1-0329', 'Enzo', 'Castillo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(844, 'STRESS-BSIT-2026S1-0339', 'Gabriel', 'Rivera', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(845, 'STRESS-BSIT-2026S1-0349', 'Adrian', 'Espinoza', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(846, 'STRESS-BSIT-2026S1-0310', 'Nathan', 'Reyes', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(847, 'STRESS-BSIT-2026S1-0320', 'Camille', 'Castillo', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(848, 'STRESS-BSIT-2026S1-0330', 'Angela', 'Santos', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(849, 'STRESS-BSIT-2026S1-0340', 'Carlo', 'Ramos', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(850, 'STRESS-BSIT-2026S1-0350', 'Andrei', 'Navarro', 4, 1, 20, NULL, 'DEMO', '2026-09-25 18:42:01'),
(851, 'STRESS-BSIT-2026S1-0351', 'Mikaela', 'Fernandez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(852, 'STRESS-BSIT-2026S1-0361', 'Angela', 'Salazar', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(853, 'STRESS-BSIT-2026S1-0371', 'Miguel', 'Espinoza', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(854, 'STRESS-BSIT-2026S1-0381', 'Kristine', 'Fernandez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(855, 'STRESS-BSIT-2026S1-0391', 'Vincent', 'Garcia', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(856, 'STRESS-BSIT-2026S1-0352', 'Miguel', 'Torres', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(857, 'STRESS-BSIT-2026S1-0362', 'Nathan', 'Soriano', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(858, 'STRESS-BSIT-2026S1-0372', 'Gabriel', 'Santos', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(859, 'STRESS-BSIT-2026S1-0382', 'Patricia', 'Torres', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(860, 'STRESS-BSIT-2026S1-0392', 'Isabella', 'Bautista', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(861, 'STRESS-BSIT-2026S1-0353', 'Kristine', 'Castillo', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(862, 'STRESS-BSIT-2026S1-0363', 'Isabella', 'Torres', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(863, 'STRESS-BSIT-2026S1-0373', 'Joshua', 'Bautista', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(864, 'STRESS-BSIT-2026S1-0383', 'Mikaela', 'Castillo', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(865, 'STRESS-BSIT-2026S1-0393', 'Enzo', 'Gonzales', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(866, 'STRESS-BSIT-2026S1-0354', 'Daniel', 'Mendoza', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(867, 'STRESS-BSIT-2026S1-0364', 'Patricia', 'Reyes', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(868, 'STRESS-BSIT-2026S1-0374', 'Carlo', 'Villanueva', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(869, 'STRESS-BSIT-2026S1-0384', 'Jasmine', 'Soriano', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(870, 'STRESS-BSIT-2026S1-0394', 'Mikaela', 'Rivera', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(871, 'STRESS-BSIT-2026S1-0355', 'Paolo', 'Domingo', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(872, 'STRESS-BSIT-2026S1-0365', 'Marco', 'Castillo', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(873, 'STRESS-BSIT-2026S1-0375', 'Nathan', 'Santos', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(874, 'STRESS-BSIT-2026S1-0385', 'Bianca', 'Reyes', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(875, 'STRESS-BSIT-2026S1-0395', 'Andrei', 'Bautista', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(876, 'STRESS-BSIT-2026S1-0356', 'Enzo', 'Dela Cruz', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(877, 'STRESS-BSIT-2026S1-0366', 'Miguel', 'Navarro', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(878, 'STRESS-BSIT-2026S1-0376', 'Angela', 'Santiago', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(879, 'STRESS-BSIT-2026S1-0386', 'Vincent', 'Dela Cruz', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(880, 'STRESS-BSIT-2026S1-0396', 'Adrian', 'Valdez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(881, 'STRESS-BSIT-2026S1-0357', 'Carlo', 'Navarro', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(882, 'STRESS-BSIT-2026S1-0367', 'Joaquin', 'Dela Cruz', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(883, 'STRESS-BSIT-2026S1-0377', 'Daniel', 'Garcia', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(884, 'STRESS-BSIT-2026S1-0387', 'Angela', 'Salazar', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(885, 'STRESS-BSIT-2026S1-0397', 'Camille', 'Ramos', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(886, 'STRESS-BSIT-2026S1-0358', 'Angela', 'Dela Cruz', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(887, 'STRESS-BSIT-2026S1-0368', 'Marco', 'Salazar', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(888, 'STRESS-BSIT-2026S1-0378', 'Nathan', 'Santiago', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(889, 'STRESS-BSIT-2026S1-0388', 'Bianca', 'Fernandez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(890, 'STRESS-BSIT-2026S1-0398', 'Patricia', 'Garcia', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(891, 'STRESS-BSIT-2026S1-0359', 'Vincent', 'Cruz', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(892, 'STRESS-BSIT-2026S1-0369', 'Patricia', 'Fernandez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(893, 'STRESS-BSIT-2026S1-0379', 'Carlo', 'Aquino', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(894, 'STRESS-BSIT-2026S1-0389', 'Enzo', 'Navarro', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(895, 'STRESS-BSIT-2026S1-0399', 'Gabriel', 'Ramos', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(896, 'STRESS-BSIT-2026S1-0360', 'Vincent', 'Fernandez', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(897, 'STRESS-BSIT-2026S1-0370', 'Joaquin', 'Garcia', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(898, 'STRESS-BSIT-2026S1-0380', 'Camille', 'Navarro', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(899, 'STRESS-BSIT-2026S1-0390', 'Sofia', 'Ramos', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(900, 'STRESS-BSIT-2026S1-0400', 'Adrian', 'Soriano', 4, 1, 21, 24, 'DEMO', '2026-09-25 18:42:01'),
(901, 'STRESS-BSIT-2026S1-0401', 'Camille', 'Torres', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(902, 'STRESS-BSIT-2026S1-0411', 'Angela', 'Mercado', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(903, 'STRESS-BSIT-2026S1-0421', 'Mikaela', 'Gonzales', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(904, 'STRESS-BSIT-2026S1-0431', 'Jasmine', 'Mendoza', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(905, 'STRESS-BSIT-2026S1-0441', 'Daniel', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(906, 'STRESS-BSIT-2026S1-0402', 'Mikaela', 'Dela Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(907, 'STRESS-BSIT-2026S1-0412', 'Jasmine', 'Valdez', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(908, 'STRESS-BSIT-2026S1-0422', 'Camille', 'Santiago', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(909, 'STRESS-BSIT-2026S1-0432', 'Paolo', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(910, 'STRESS-BSIT-2026S1-0442', 'Isabella', 'Castillo', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(911, 'STRESS-BSIT-2026S1-0403', 'Patricia', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(912, 'STRESS-BSIT-2026S1-0413', 'Bianca', 'Ramos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(913, 'STRESS-BSIT-2026S1-0423', 'Adrian', 'Garcia', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(914, 'STRESS-BSIT-2026S1-0433', 'Daniel', 'Dela Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(915, 'STRESS-BSIT-2026S1-0443', 'Enzo', 'Torres', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(916, 'STRESS-BSIT-2026S1-0404', 'Bianca', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(917, 'STRESS-BSIT-2026S1-0414', 'Patricia', 'Ramos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(918, 'STRESS-BSIT-2026S1-0424', 'Vincent', 'Garcia', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(919, 'STRESS-BSIT-2026S1-0434', 'Adrian', 'Dela Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(920, 'STRESS-BSIT-2026S1-0444', 'Marco', 'Reyes', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(921, 'STRESS-BSIT-2026S1-0405', 'Nathan', 'Flores', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(922, 'STRESS-BSIT-2026S1-0415', 'Mikaela', 'Valdez', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(923, 'STRESS-BSIT-2026S1-0425', 'Sofia', 'Espinoza', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(924, 'STRESS-BSIT-2026S1-0435', 'Miguel', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(925, 'STRESS-BSIT-2026S1-0445', 'Patricia', 'Soriano', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(926, 'STRESS-BSIT-2026S1-0406', 'Angela', 'Domingo', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(927, 'STRESS-BSIT-2026S1-0416', 'Luis', 'Villanueva', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(928, 'STRESS-BSIT-2026S1-0426', 'Enzo', 'Santos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(929, 'STRESS-BSIT-2026S1-0436', 'Mikaela', 'Castillo', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(930, 'STRESS-BSIT-2026S1-0446', 'Kristine', 'Salazar', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(931, 'STRESS-BSIT-2026S1-0407', 'Daniel', 'Soriano', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(932, 'STRESS-BSIT-2026S1-0417', 'Adrian', 'Santos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(933, 'STRESS-BSIT-2026S1-0427', 'Bianca', 'Bautista', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(934, 'STRESS-BSIT-2026S1-0437', 'Patricia', 'Torres', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(935, 'STRESS-BSIT-2026S1-0447', 'Camille', 'Flores', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(936, 'STRESS-BSIT-2026S1-0408', 'Jasmine', 'Reyes', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(937, 'STRESS-BSIT-2026S1-0418', 'Marco', 'Villanueva', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(938, 'STRESS-BSIT-2026S1-0428', 'Paolo', 'Gonzales', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(939, 'STRESS-BSIT-2026S1-0438', 'Camille', 'Soriano', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(940, 'STRESS-BSIT-2026S1-0448', 'Joshua', 'Cruz', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(941, 'STRESS-BSIT-2026S1-0409', 'Bianca', 'Castillo', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(942, 'STRESS-BSIT-2026S1-0419', 'Joshua', 'Santos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(943, 'STRESS-BSIT-2026S1-0429', 'Vincent', 'Mercado', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(944, 'STRESS-BSIT-2026S1-0439', 'Kristine', 'Torres', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(945, 'STRESS-BSIT-2026S1-0449', 'Mikaela', 'Flores', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(946, 'STRESS-BSIT-2026S1-0410', 'Vincent', 'Santos', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(947, 'STRESS-BSIT-2026S1-0420', 'Patricia', 'Bautista', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(948, 'STRESS-BSIT-2026S1-0430', 'Carlo', 'Reyes', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(949, 'STRESS-BSIT-2026S1-0440', 'Sofia', 'Fernandez', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(950, 'STRESS-BSIT-2026S1-0450', 'Miguel', 'Valdez', 4, 1, 22, 25, 'DEMO', '2026-09-25 18:42:01'),
(951, 'STRESS-BSIT-2026S1-0451', 'Kristine', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(952, 'STRESS-BSIT-2026S1-0461', 'Carlo', 'Valdez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(953, 'STRESS-BSIT-2026S1-0471', 'Andrei', 'Flores', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(954, 'STRESS-BSIT-2026S1-0481', 'Mikaela', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(955, 'STRESS-BSIT-2026S1-0491', 'Nathan', 'Salazar', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(956, 'STRESS-BSIT-2026S1-0452', 'Joshua', 'Rivera', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(957, 'STRESS-BSIT-2026S1-0462', 'Daniel', 'Bautista', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(958, 'STRESS-BSIT-2026S1-0472', 'Adrian', 'Reyes', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(959, 'STRESS-BSIT-2026S1-0482', 'Luis', 'Santos', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(960, 'STRESS-BSIT-2026S1-0492', 'Angela', 'Castillo', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(961, 'STRESS-BSIT-2026S1-0453', 'Mikaela', 'Villanueva', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(962, 'STRESS-BSIT-2026S1-0463', 'Sofia', 'Santos', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(963, 'STRESS-BSIT-2026S1-0473', 'Miguel', 'Mendoza', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(964, 'STRESS-BSIT-2026S1-0483', 'Kristine', 'Villanueva', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(965, 'STRESS-BSIT-2026S1-0493', 'Daniel', 'Domingo', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(966, 'STRESS-BSIT-2026S1-0454', 'Nathan', 'Mercado', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(967, 'STRESS-BSIT-2026S1-0464', 'Camille', 'Rivera', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(968, 'STRESS-BSIT-2026S1-0474', 'Angela', 'Soriano', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(969, 'STRESS-BSIT-2026S1-0484', 'Vincent', 'Villanueva', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(970, 'STRESS-BSIT-2026S1-0494', 'Kristine', 'Torres', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(971, 'STRESS-BSIT-2026S1-0455', 'Carlo', 'Rivera', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(972, 'STRESS-BSIT-2026S1-0465', 'Adrian', 'Mercado', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(973, 'STRESS-BSIT-2026S1-0475', 'Vincent', 'Reyes', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(974, 'STRESS-BSIT-2026S1-0485', 'Angela', 'Santos', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(975, 'STRESS-BSIT-2026S1-0495', 'Luis', 'Soriano', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(976, 'STRESS-BSIT-2026S1-0456', 'Alyssa', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(977, 'STRESS-BSIT-2026S1-0466', 'Joshua', 'Garcia', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(978, 'STRESS-BSIT-2026S1-0476', 'Isabella', 'Dela Cruz', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(979, 'STRESS-BSIT-2026S1-0486', 'Jasmine', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(980, 'STRESS-BSIT-2026S1-0496', 'Mikaela', 'Salazar', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(981, 'STRESS-BSIT-2026S1-0457', 'Paolo', 'Valdez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(982, 'STRESS-BSIT-2026S1-0467', 'Mikaela', 'Espinoza', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(983, 'STRESS-BSIT-2026S1-0477', 'Jasmine', 'Salazar', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(984, 'STRESS-BSIT-2026S1-0487', 'Isabella', 'Valdez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(985, 'STRESS-BSIT-2026S1-0497', 'Patricia', 'Fernandez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(986, 'STRESS-BSIT-2026S1-0458', 'Isabella', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(987, 'STRESS-BSIT-2026S1-0468', 'Adrian', 'Valdez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(988, 'STRESS-BSIT-2026S1-0478', 'Daniel', 'Flores', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(989, 'STRESS-BSIT-2026S1-0488', 'Angela', 'Espinoza', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(990, 'STRESS-BSIT-2026S1-0498', 'Luis', 'Cruz', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(991, 'STRESS-BSIT-2026S1-0459', 'Enzo', 'Garcia', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(992, 'STRESS-BSIT-2026S1-0469', 'Miguel', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(993, 'STRESS-BSIT-2026S1-0479', 'Paolo', 'Navarro', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(994, 'STRESS-BSIT-2026S1-0489', 'Vincent', 'Aquino', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(995, 'STRESS-BSIT-2026S1-0499', 'Kristine', 'Dela Cruz', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(996, 'STRESS-BSIT-2026S1-0460', 'Enzo', 'Santiago', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(997, 'STRESS-BSIT-2026S1-0470', 'Marco', 'Salazar', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(998, 'STRESS-BSIT-2026S1-0480', 'Joshua', 'Valdez', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(999, 'STRESS-BSIT-2026S1-0490', 'Isabella', 'Dela Cruz', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01'),
(1000, 'STRESS-BSIT-2026S1-0500', 'Isabella', 'Salazar', 4, 1, 23, 26, 'DEMO', '2026-09-25 18:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `subject_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `subject_title` varchar(255) NOT NULL,
  `units` decimal(5,2) NOT NULL,
  `f2f_hours` decimal(5,2) NOT NULL,
  `online_hours` decimal(5,2) NOT NULL,
  `year_level` varchar(50) NOT NULL,
  `semester` tinyint(3) UNSIGNED NOT NULL,
  `legacy_subject_id` int(10) UNSIGNED DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`subject_id`, `program_id`, `subject_code`, `specialization`, `subject_title`, `units`, `f2f_hours`, `online_hours`, `year_level`, `semester`, `legacy_subject_id`, `is_verified`, `is_active`, `created_at`) VALUES
(1, 4, 'GE1', NULL, 'Understanding the Self', 3.00, 2.00, 1.00, '1st Year', 1, 1, 0, 1, '2026-09-24 17:27:59'),
(2, 4, 'GE2', NULL, 'Readings in Philippine History', 3.00, 2.00, 1.00, '1st Year', 1, 2, 0, 1, '2026-09-24 17:27:59'),
(3, 4, 'GE3', NULL, 'The Contemporary World', 3.00, 2.00, 1.00, '1st Year', 1, 3, 0, 1, '2026-09-24 17:27:59'),
(4, 4, 'GE4', NULL, 'Mathematics in the Modern World', 3.00, 2.00, 1.00, '1st Year', 1, 4, 0, 1, '2026-09-24 17:27:59'),
(5, 4, 'CC10', NULL, 'Introduction to Computing', 3.00, 2.00, 1.00, '1st Year', 1, 5, 0, 1, '2026-09-24 17:27:59'),
(6, 4, 'CCS1102', NULL, 'Computer Programming 1', 3.00, 2.00, 1.00, '1st Year', 1, 6, 0, 1, '2026-09-24 17:27:59'),
(7, 4, 'KOMFIL', NULL, 'Kontekstwalisadong Komunikasyon sa Filipino', 3.00, 2.00, 1.00, '1st Year', 1, 7, 0, 1, '2026-09-24 17:27:59'),
(8, 4, 'NSTP1', NULL, 'National Service Training Program 1', 3.00, 2.00, 1.00, '1st Year', 1, 8, 0, 1, '2026-09-24 17:27:59'),
(9, 4, 'PE1', NULL, 'Physical Fitness', 2.00, 1.00, 1.00, '1st Year', 1, 9, 0, 1, '2026-09-24 17:27:59'),
(10, 4, 'GE5', NULL, 'Purposive Communication', 3.00, 2.00, 1.00, '1st Year', 2, 10, 0, 1, '2026-09-24 17:27:59'),
(11, 4, 'GE6', NULL, 'Art Appreciation', 3.00, 2.00, 1.00, '1st Year', 2, 11, 0, 1, '2026-09-24 17:27:59'),
(12, 4, 'GE7', NULL, 'Science and Technology (ST) and Society', 3.00, 2.00, 1.00, '1st Year', 2, 12, 0, 1, '2026-09-24 17:27:59'),
(13, 4, 'GE8', NULL, 'Ethics', 3.00, 2.00, 1.00, '1st Year', 2, 13, 0, 1, '2026-09-24 17:27:59'),
(14, 4, 'MS101', NULL, 'Discrete Mathematics', 3.00, 2.00, 1.00, '1st Year', 2, 14, 0, 1, '2026-09-24 17:27:59'),
(15, 4, 'CC103', NULL, 'Computer Programming 2', 3.00, 2.00, 1.00, '1st Year', 2, 15, 0, 1, '2026-09-24 17:27:59'),
(16, 4, 'FILDIS', NULL, 'Filipino sa iba\'t-ibang Disiplina', 3.00, 2.00, 1.00, '1st Year', 2, 16, 0, 1, '2026-09-24 17:27:59'),
(17, 4, 'NSTP2', NULL, 'National Service Training Program 2', 3.00, 2.00, 1.00, '1st Year', 2, 17, 0, 1, '2026-09-24 17:27:59'),
(18, 4, 'PE2', NULL, 'Folk Dance and Rhythmic Activities', 2.00, 1.00, 1.00, '1st Year', 2, 18, 0, 1, '2026-09-24 17:27:59'),
(19, 4, 'MS102', NULL, 'Quantitative Methods with Modelling Simulation', 3.00, 2.00, 1.00, '2nd Year', 1, 19, 0, 1, '2026-09-24 17:27:59'),
(20, 4, 'CC104', NULL, 'Data Structures And Algorithms', 3.00, 2.00, 1.00, '2nd Year', 1, 20, 0, 1, '2026-09-24 17:27:59'),
(21, 4, 'HCI101', NULL, 'Introduction To Human Computer Interaction', 3.00, 2.00, 1.00, '2nd Year', 1, 21, 0, 1, '2026-09-24 17:27:59'),
(22, 4, 'IPT101', NULL, 'Integrative Programming And Technologies 1', 3.00, 2.00, 1.00, '2nd Year', 1, 22, 0, 1, '2026-09-24 17:27:59'),
(23, 4, 'NET101', NULL, 'Networking 1', 3.00, 2.00, 1.00, '2nd Year', 1, 23, 0, 1, '2026-09-24 17:27:59'),
(24, 4, 'ITE1', NULL, 'IT ELECTIVE 1 (Web Fundamental)', 3.00, 2.00, 1.00, '2nd Year', 1, 24, 0, 1, '2026-09-24 17:27:59'),
(25, 4, 'SOSLIT', NULL, 'Sosyedad at Literatura', 3.00, 2.00, 1.00, '2nd Year', 1, 25, 0, 1, '2026-09-24 17:27:59'),
(26, 4, 'PE3', NULL, 'Individual and Dual Sports', 2.00, 1.00, 1.00, '2nd Year', 1, 26, 0, 1, '2026-09-24 17:27:59'),
(27, 4, 'GE9', NULL, 'The Life and Works of Jose Rizal', 3.00, 2.00, 1.00, '2nd Year', 2, 27, 0, 1, '2026-09-24 17:27:59'),
(28, 4, 'DM102', NULL, 'Financial Management', 3.00, 2.00, 1.00, '2nd Year', 2, 28, 0, 1, '2026-09-24 17:27:59'),
(29, 4, 'SIA101', NULL, 'System Integration and Architecture 1', 3.00, 2.00, 1.00, '2nd Year', 2, 29, 0, 1, '2026-09-24 17:27:59'),
(30, 4, 'CC105', NULL, 'Information Management', 3.00, 2.00, 1.00, '2nd Year', 2, 30, 0, 1, '2026-09-24 17:27:59'),
(31, 4, 'NET102', NULL, 'Networking 2', 3.00, 2.00, 1.00, '2nd Year', 2, 31, 0, 1, '2026-09-24 17:27:59'),
(32, 4, 'WEB101', NULL, 'Web Development (Advance Web / Platform)', 3.00, 2.00, 1.00, '2nd Year', 2, 32, 0, 1, '2026-09-24 17:27:59'),
(33, 4, 'ITE2', NULL, 'IT ELECTIVE 2 (Advance Java or OOP)', 3.00, 2.00, 1.00, '2nd Year', 2, 33, 0, 1, '2026-09-24 17:27:59'),
(34, 4, 'PE4', NULL, 'Team Sports', 2.00, 1.00, 1.00, '2nd Year', 2, 34, 0, 1, '2026-09-24 17:27:59'),
(35, 4, 'CC106', NULL, 'Application Development and Emerging Technologies', 3.00, 2.00, 1.00, '3rd Year', 1, 35, 0, 1, '2026-09-24 17:27:59'),
(36, 4, 'IAS101', NULL, 'Information Assurance and Security 1', 3.00, 2.00, 1.00, '3rd Year', 1, 36, 0, 1, '2026-09-24 17:27:59'),
(37, 4, 'IM101', NULL, 'Fundamentals of Database System', 3.00, 2.00, 1.00, '3rd Year', 1, 37, 0, 1, '2026-09-24 17:27:59'),
(38, 4, 'PM101', NULL, 'Project Management', 3.00, 2.00, 1.00, '3rd Year', 1, 39, 0, 1, '2026-09-24 17:27:59'),
(39, 4, 'ITE3', NULL, 'IT ELECTIVE 3 (Research)', 3.00, 2.00, 1.00, '3rd Year', 1, 40, 0, 1, '2026-09-24 17:27:59'),
(40, 4, 'SP101', NULL, 'Social and Professional Issues', 3.00, 2.00, 1.00, '3rd Year', 2, 41, 0, 1, '2026-09-24 17:27:59'),
(41, 4, 'IAS102', NULL, 'Information Assurance and Security 2', 3.00, 2.00, 1.00, '3rd Year', 2, 42, 0, 1, '2026-09-24 17:27:59'),
(42, 4, 'TEC101', NULL, 'Technopreneurship', 3.00, 2.00, 1.00, '3rd Year', 2, 43, 0, 1, '2026-09-24 17:27:59'),
(43, 4, 'BPM101', NULL, 'Business Process Management in IT', 3.00, 2.00, 1.00, '3rd Year', 2, 44, 0, 1, '2026-09-24 17:27:59'),
(44, 4, 'ITSP2A', 'IM', 'IT Specialization Project 2 (IM)', 3.00, 2.00, 1.00, '3rd Year', 2, 45, 0, 1, '2026-09-24 17:27:59'),
(45, 4, 'SA101', NULL, 'System Administration And Maintenance', 3.00, 2.00, 1.00, '3rd Year', 2, 46, 0, 1, '2026-09-24 17:27:59'),
(46, 4, 'ITSP1A', 'IM', 'IT Specialization Project 1 (IM)', 3.00, 2.00, 1.00, '3rd Year', 1, 51, 0, 1, '2026-09-24 17:27:59'),
(47, 4, 'ITSP1B', 'NA', 'IT Specialization Project 1 (NA)', 3.00, 2.00, 1.00, '3rd Year', 1, 52, 0, 1, '2026-09-24 17:27:59'),
(48, 4, 'ITSP2B', 'NA', 'IT Specialization Project 2 (NA)', 3.00, 2.00, 1.00, '3rd Year', 2, 53, 0, 1, '2026-09-24 17:27:59'),
(49, 4, 'ITSP1C', 'IS', 'IT Specialization Project 1 (IS)', 3.00, 2.00, 1.00, '3rd Year', 1, 54, 0, 1, '2026-09-24 17:27:59'),
(50, 4, 'ITSP2C', 'IS', 'IT Specialization Project 2 (IS)', 3.00, 2.00, 1.00, '3rd Year', 2, 55, 0, 1, '2026-09-24 17:27:59'),
(51, 4, 'ITE4', NULL, 'IT ELECTIVE 4', 3.00, 2.00, 1.00, '4th Year', 1, 47, 0, 1, '2026-09-24 17:27:59'),
(52, 4, 'CAP101', NULL, 'Capstone Project and Research 1', 3.00, 2.00, 1.00, '4th Year', 1, 48, 0, 1, '2026-09-24 17:27:59'),
(53, 4, 'PRAC101', NULL, 'OJT/Practicum 1', 3.00, 2.00, 1.00, '4th Year', 1, 49, 0, 1, '2026-09-24 17:27:59'),
(54, 4, 'ITSP3A', 'IM', 'Pending Official Subject Title', 3.00, 2.00, 1.00, '4th Year', 1, 50, 0, 1, '2026-09-24 17:27:59'),
(55, 4, 'CAP102', NULL, 'Capstone Project and Research 2', 3.00, 2.00, 1.00, '4th Year', 2, 58, 0, 1, '2026-09-24 17:27:59'),
(56, 4, 'PRAC102', NULL, 'OJT/Practicum 2', 3.00, 2.00, 1.00, '4th Year', 2, 59, 0, 1, '2026-09-24 17:27:59'),
(57, 4, 'ITSP4A', 'IM', 'Pending Official Subject Title', 3.00, 2.00, 1.00, '4th Year', 2, 60, 0, 1, '2026-09-24 17:27:59'),
(58, 4, 'ITSP4B', 'NA', 'Network Defense and Remote Access Configuration', 3.00, 2.00, 1.00, '4th Year', 2, 61, 0, 1, '2026-09-24 17:27:59'),
(59, 4, 'ITSP4C', 'IS', 'Information Security', 3.00, 2.00, 1.00, '4th Year', 2, 62, 0, 1, '2026-09-24 17:27:59'),
(60, 4, 'ITSP3B', 'NA', 'Big Data Analysis', 3.00, 2.00, 1.00, '4th Year', 1, 63, 0, 1, '2026-09-24 17:27:59'),
(61, 4, 'ITSP3C', 'IS', 'Information Security', 3.00, 2.00, 1.00, '4th Year', 1, 64, 0, 1, '2026-09-24 17:27:59');

-- --------------------------------------------------------

--
-- Table structure for table `substitute_assignments`
--

CREATE TABLE `substitute_assignments` (
  `substitute_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `meeting_id` bigint(20) UNSIGNED NOT NULL,
  `class_batch_id` bigint(20) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `duty_date` date NOT NULL,
  `original_teacher_id` int(10) UNSIGNED NOT NULL,
  `substitute_teacher_id` int(10) UNSIGNED NOT NULL,
  `reason` varchar(500) NOT NULL,
  `status` enum('ACTIVE','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  `active_key` tinyint(4) GENERATED ALWAYS AS (case when `status` = 'ACTIVE' then 1 else NULL end) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `program_id` int(10) UNSIGNED NOT NULL,
  `employee_no` varchar(50) NOT NULL,
  `teacher_name` varchar(150) NOT NULL,
  `demo_no` tinyint(3) UNSIGNED DEFAULT NULL,
  `max_daily_hours` tinyint(3) UNSIGNED NOT NULL DEFAULT 8,
  `max_weekly_hours` tinyint(3) UNSIGNED NOT NULL DEFAULT 30,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`teacher_id`, `program_id`, `employee_no`, `teacher_name`, `demo_no`, `max_daily_hours`, `max_weekly_hours`, `status`, `data_origin`, `created_at`) VALUES
(1, 4, 'DEMO-BSIT-01', 'Adrian M. Santos', 1, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(2, 4, 'DEMO-BSIT-11', 'Paolo M. Torres', 11, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(3, 4, 'DEMO-BSIT-21', 'Joaquin R. Soriano', 21, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(4, 4, 'DEMO-BSIT-02', 'Bianca L. Reyes', 2, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(5, 4, 'DEMO-BSIT-12', 'Isabella R. Aquino', 12, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(6, 4, 'DEMO-BSIT-22', 'Nicole M. Mercado', 22, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(7, 4, 'DEMO-BSIT-03', 'Carlos P. Navarro', 3, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(8, 4, 'DEMO-BSIT-13', 'Nathan J. Castillo', 13, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(9, 4, 'DEMO-BSIT-23', 'Carlo P. Espinoza', 23, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(10, 4, 'DEMO-BSIT-04', 'Maria Angela Cruz', 4, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(11, 4, 'DEMO-BSIT-14', 'Sofia A. Villanueva', 14, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(12, 4, 'DEMO-BSIT-24', 'Mikaela S. Dela Cruz', 24, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(13, 4, 'DEMO-BSIT-05', 'Joshua R. Mendoza', 5, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(14, 4, 'DEMO-BSIT-15', 'Gabriel P. Santiago', 15, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(15, 4, 'DEMO-BSIT-06', 'Patricia D. Garcia', 6, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(16, 4, 'DEMO-BSIT-16', 'Jasmine L. Fernandez', 16, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(17, 4, 'DEMO-BSIT-07', 'Miguel A. Bautista', 7, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(18, 4, 'DEMO-BSIT-17', 'Marco D. Rivera', 17, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(19, 4, 'DEMO-BSIT-08', 'Andrea C. Ramos', 8, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(20, 4, 'DEMO-BSIT-18', 'Alyssa M. Domingo', 18, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(21, 4, 'DEMO-BSIT-09', 'Daniel B. Flores', 9, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(22, 4, 'DEMO-BSIT-19', 'Vincent C. Salazar', 19, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(23, 4, 'DEMO-BSIT-10', 'Camille S. Gonzales', 10, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59'),
(24, 4, 'DEMO-BSIT-20', 'Kristine A. Valdez', 20, 8, 30, 'ACTIVE', 'DEMO', '2026-09-24 18:30:59');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_availability`
--

CREATE TABLE `teacher_availability` (
  `availability_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `academic_period_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `availability_status` enum('AVAILABLE','UNAVAILABLE') NOT NULL DEFAULT 'AVAILABLE',
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_availability`
--

INSERT INTO `teacher_availability` (`availability_id`, `teacher_id`, `academic_period_id`, `day_of_week`, `start_time`, `end_time`, `availability_status`, `data_origin`) VALUES
(1, 1, 1, 'Monday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(2, 1, 1, 'Tuesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(3, 1, 1, 'Wednesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(4, 1, 1, 'Thursday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(5, 1, 1, 'Friday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(6, 1, 1, 'Saturday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(7, 2, 1, 'Monday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(8, 2, 1, 'Tuesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(9, 2, 1, 'Wednesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(10, 2, 1, 'Thursday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(11, 2, 1, 'Friday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(12, 2, 1, 'Saturday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(13, 3, 1, 'Monday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(14, 3, 1, 'Tuesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(15, 3, 1, 'Wednesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(16, 3, 1, 'Thursday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(17, 3, 1, 'Friday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(18, 3, 1, 'Saturday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(19, 4, 1, 'Monday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(20, 4, 1, 'Tuesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(21, 4, 1, 'Wednesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(22, 4, 1, 'Thursday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(23, 4, 1, 'Friday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(24, 4, 1, 'Saturday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(25, 5, 1, 'Monday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(26, 5, 1, 'Tuesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(27, 5, 1, 'Wednesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(28, 5, 1, 'Thursday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(29, 5, 1, 'Friday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(30, 5, 1, 'Saturday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(31, 6, 1, 'Monday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(32, 6, 1, 'Tuesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(33, 6, 1, 'Wednesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(34, 6, 1, 'Thursday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(35, 6, 1, 'Friday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(36, 6, 1, 'Saturday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(37, 7, 1, 'Monday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(38, 7, 1, 'Tuesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(39, 7, 1, 'Wednesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(40, 7, 1, 'Thursday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(41, 7, 1, 'Friday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(42, 7, 1, 'Saturday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(43, 8, 1, 'Monday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(44, 8, 1, 'Tuesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(45, 8, 1, 'Wednesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(46, 8, 1, 'Thursday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(47, 8, 1, 'Friday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(48, 8, 1, 'Saturday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(49, 9, 1, 'Monday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(50, 9, 1, 'Tuesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(51, 9, 1, 'Wednesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(52, 9, 1, 'Thursday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(53, 9, 1, 'Friday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(54, 9, 1, 'Saturday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(55, 10, 1, 'Monday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(56, 10, 1, 'Tuesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(57, 10, 1, 'Wednesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(58, 10, 1, 'Thursday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(59, 10, 1, 'Friday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(60, 10, 1, 'Saturday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(61, 11, 1, 'Monday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(62, 11, 1, 'Tuesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(63, 11, 1, 'Wednesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(64, 11, 1, 'Thursday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(65, 11, 1, 'Friday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(66, 11, 1, 'Saturday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(67, 12, 1, 'Monday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(68, 12, 1, 'Tuesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(69, 12, 1, 'Wednesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(70, 12, 1, 'Thursday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(71, 12, 1, 'Friday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(72, 12, 1, 'Saturday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(73, 13, 1, 'Monday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(74, 13, 1, 'Tuesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(75, 13, 1, 'Wednesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(76, 13, 1, 'Thursday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(77, 13, 1, 'Friday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(78, 13, 1, 'Saturday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(79, 14, 1, 'Monday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(80, 14, 1, 'Tuesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(81, 14, 1, 'Wednesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(82, 14, 1, 'Thursday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(83, 14, 1, 'Friday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(84, 14, 1, 'Saturday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(85, 15, 1, 'Monday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(86, 15, 1, 'Tuesday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(87, 15, 1, 'Wednesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(88, 15, 1, 'Thursday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(89, 15, 1, 'Friday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(90, 15, 1, 'Saturday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(91, 16, 1, 'Monday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(92, 16, 1, 'Tuesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(93, 16, 1, 'Wednesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(94, 16, 1, 'Thursday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(95, 16, 1, 'Friday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(96, 16, 1, 'Saturday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(97, 17, 1, 'Monday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(98, 17, 1, 'Tuesday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(99, 17, 1, 'Wednesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(100, 17, 1, 'Thursday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(101, 17, 1, 'Friday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(102, 17, 1, 'Saturday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(103, 18, 1, 'Monday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(104, 18, 1, 'Tuesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(105, 18, 1, 'Wednesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(106, 18, 1, 'Thursday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(107, 18, 1, 'Friday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(108, 18, 1, 'Saturday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(109, 19, 1, 'Monday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(110, 19, 1, 'Tuesday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(111, 19, 1, 'Wednesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(112, 19, 1, 'Thursday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(113, 19, 1, 'Friday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(114, 19, 1, 'Saturday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(115, 20, 1, 'Monday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(116, 20, 1, 'Tuesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(117, 20, 1, 'Wednesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(118, 20, 1, 'Thursday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(119, 20, 1, 'Friday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(120, 20, 1, 'Saturday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(121, 21, 1, 'Monday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO'),
(122, 21, 1, 'Tuesday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(123, 21, 1, 'Wednesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(124, 21, 1, 'Thursday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(125, 21, 1, 'Friday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(126, 21, 1, 'Saturday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(127, 22, 1, 'Monday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(128, 22, 1, 'Tuesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(129, 22, 1, 'Wednesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(130, 22, 1, 'Thursday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(131, 22, 1, 'Friday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(132, 22, 1, 'Saturday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(133, 23, 1, 'Monday', '07:00:00', '15:00:00', 'AVAILABLE', 'DEMO'),
(134, 23, 1, 'Tuesday', '08:00:00', '16:00:00', 'AVAILABLE', 'DEMO'),
(135, 23, 1, 'Wednesday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(136, 23, 1, 'Thursday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(137, 23, 1, 'Friday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(138, 23, 1, 'Saturday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(139, 24, 1, 'Monday', '09:00:00', '17:00:00', 'AVAILABLE', 'DEMO'),
(140, 24, 1, 'Tuesday', '10:00:00', '18:00:00', 'AVAILABLE', 'DEMO'),
(141, 24, 1, 'Wednesday', '11:00:00', '19:00:00', 'AVAILABLE', 'DEMO'),
(142, 24, 1, 'Thursday', '12:00:00', '20:00:00', 'AVAILABLE', 'DEMO'),
(143, 24, 1, 'Friday', '13:00:00', '21:00:00', 'AVAILABLE', 'DEMO'),
(144, 24, 1, 'Saturday', '06:00:00', '14:00:00', 'AVAILABLE', 'DEMO');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subject_authorizations`
--

CREATE TABLE `teacher_subject_authorizations` (
  `authorization_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `teacher_subject_authorizations`
--

INSERT INTO `teacher_subject_authorizations` (`authorization_id`, `teacher_id`, `subject_id`, `data_origin`, `created_at`) VALUES
(1, 1, 1, 'DEMO', '2026-09-24 18:30:59'),
(2, 4, 1, 'DEMO', '2026-09-24 18:30:59'),
(3, 7, 1, 'DEMO', '2026-09-24 18:30:59'),
(4, 4, 2, 'DEMO', '2026-09-24 18:30:59'),
(5, 7, 2, 'DEMO', '2026-09-24 18:30:59'),
(6, 10, 2, 'DEMO', '2026-09-24 18:30:59'),
(7, 7, 3, 'DEMO', '2026-09-24 18:30:59'),
(8, 10, 3, 'DEMO', '2026-09-24 18:30:59'),
(9, 13, 3, 'DEMO', '2026-09-24 18:30:59'),
(10, 10, 4, 'DEMO', '2026-09-24 18:30:59'),
(11, 13, 4, 'DEMO', '2026-09-24 18:30:59'),
(12, 15, 4, 'DEMO', '2026-09-24 18:30:59'),
(13, 13, 5, 'DEMO', '2026-09-24 18:30:59'),
(14, 15, 5, 'DEMO', '2026-09-24 18:30:59'),
(15, 17, 5, 'DEMO', '2026-09-24 18:30:59'),
(16, 15, 6, 'DEMO', '2026-09-24 18:30:59'),
(17, 17, 6, 'DEMO', '2026-09-24 18:30:59'),
(18, 19, 6, 'DEMO', '2026-09-24 18:30:59'),
(19, 17, 7, 'DEMO', '2026-09-24 18:30:59'),
(20, 19, 7, 'DEMO', '2026-09-24 18:30:59'),
(21, 21, 7, 'DEMO', '2026-09-24 18:30:59'),
(22, 19, 8, 'DEMO', '2026-09-24 18:30:59'),
(23, 21, 8, 'DEMO', '2026-09-24 18:30:59'),
(24, 23, 8, 'DEMO', '2026-09-24 18:30:59'),
(25, 21, 9, 'DEMO', '2026-09-24 18:30:59'),
(26, 23, 9, 'DEMO', '2026-09-24 18:30:59'),
(27, 2, 9, 'DEMO', '2026-09-24 18:30:59'),
(28, 22, 19, 'DEMO', '2026-09-24 18:30:59'),
(29, 24, 19, 'DEMO', '2026-09-24 18:30:59'),
(30, 3, 19, 'DEMO', '2026-09-24 18:30:59'),
(31, 24, 20, 'DEMO', '2026-09-24 18:30:59'),
(32, 3, 20, 'DEMO', '2026-09-24 18:30:59'),
(33, 6, 20, 'DEMO', '2026-09-24 18:30:59'),
(34, 3, 21, 'DEMO', '2026-09-24 18:30:59'),
(35, 6, 21, 'DEMO', '2026-09-24 18:30:59'),
(36, 9, 21, 'DEMO', '2026-09-24 18:30:59'),
(37, 6, 22, 'DEMO', '2026-09-24 18:30:59'),
(38, 9, 22, 'DEMO', '2026-09-24 18:30:59'),
(39, 12, 22, 'DEMO', '2026-09-24 18:30:59'),
(40, 1, 23, 'DEMO', '2026-09-24 18:30:59'),
(41, 9, 23, 'DEMO', '2026-09-24 18:30:59'),
(42, 12, 23, 'DEMO', '2026-09-24 18:30:59'),
(43, 1, 24, 'DEMO', '2026-09-24 18:30:59'),
(44, 4, 24, 'DEMO', '2026-09-24 18:30:59'),
(45, 12, 24, 'DEMO', '2026-09-24 18:30:59'),
(46, 1, 25, 'DEMO', '2026-09-24 18:30:59'),
(47, 4, 25, 'DEMO', '2026-09-24 18:30:59'),
(48, 7, 25, 'DEMO', '2026-09-24 18:30:59'),
(49, 4, 26, 'DEMO', '2026-09-24 18:30:59'),
(50, 7, 26, 'DEMO', '2026-09-24 18:30:59'),
(51, 10, 26, 'DEMO', '2026-09-24 18:30:59'),
(52, 2, 35, 'DEMO', '2026-09-24 18:30:59'),
(53, 5, 35, 'DEMO', '2026-09-24 18:30:59'),
(54, 8, 35, 'DEMO', '2026-09-24 18:30:59'),
(55, 5, 36, 'DEMO', '2026-09-24 18:30:59'),
(56, 8, 36, 'DEMO', '2026-09-24 18:30:59'),
(57, 11, 36, 'DEMO', '2026-09-24 18:30:59'),
(58, 8, 37, 'DEMO', '2026-09-24 18:30:59'),
(59, 11, 37, 'DEMO', '2026-09-24 18:30:59'),
(60, 14, 37, 'DEMO', '2026-09-24 18:30:59'),
(61, 11, 38, 'DEMO', '2026-09-24 18:30:59'),
(62, 14, 38, 'DEMO', '2026-09-24 18:30:59'),
(63, 16, 38, 'DEMO', '2026-09-24 18:30:59'),
(64, 14, 39, 'DEMO', '2026-09-24 18:30:59'),
(65, 16, 39, 'DEMO', '2026-09-24 18:30:59'),
(66, 18, 39, 'DEMO', '2026-09-24 18:30:59'),
(67, 6, 46, 'DEMO', '2026-09-24 18:30:59'),
(68, 9, 46, 'DEMO', '2026-09-24 18:30:59'),
(69, 12, 46, 'DEMO', '2026-09-24 18:30:59'),
(70, 1, 47, 'DEMO', '2026-09-24 18:30:59'),
(71, 9, 47, 'DEMO', '2026-09-24 18:30:59'),
(72, 12, 47, 'DEMO', '2026-09-24 18:30:59'),
(73, 1, 49, 'DEMO', '2026-09-24 18:30:59'),
(74, 4, 49, 'DEMO', '2026-09-24 18:30:59'),
(75, 7, 49, 'DEMO', '2026-09-24 18:30:59'),
(76, 7, 51, 'DEMO', '2026-09-24 18:30:59'),
(77, 10, 51, 'DEMO', '2026-09-24 18:30:59'),
(78, 13, 51, 'DEMO', '2026-09-24 18:30:59'),
(79, 10, 52, 'DEMO', '2026-09-24 18:30:59'),
(80, 13, 52, 'DEMO', '2026-09-24 18:30:59'),
(81, 15, 52, 'DEMO', '2026-09-24 18:30:59'),
(82, 13, 53, 'DEMO', '2026-09-24 18:30:59'),
(83, 15, 53, 'DEMO', '2026-09-24 18:30:59'),
(84, 17, 53, 'DEMO', '2026-09-24 18:30:59'),
(85, 15, 54, 'DEMO', '2026-09-24 18:30:59'),
(86, 17, 54, 'DEMO', '2026-09-24 18:30:59'),
(87, 19, 54, 'DEMO', '2026-09-24 18:30:59'),
(88, 5, 60, 'DEMO', '2026-09-24 18:30:59'),
(89, 8, 60, 'DEMO', '2026-09-24 18:30:59'),
(90, 11, 60, 'DEMO', '2026-09-24 18:30:59'),
(91, 8, 61, 'DEMO', '2026-09-24 18:30:59'),
(92, 11, 61, 'DEMO', '2026-09-24 18:30:59'),
(93, 14, 61, 'DEMO', '2026-09-24 18:30:59');

-- --------------------------------------------------------

--
-- Table structure for table `time_slots`
--

CREATE TABLE `time_slots` (
  `time_slot_id` int(10) UNSIGNED NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `day_pattern` enum('MWF','TTHS') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `data_origin` enum('DEMO','OFFICIAL') NOT NULL DEFAULT 'DEMO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_slots`
--

INSERT INTO `time_slots` (`time_slot_id`, `day_of_week`, `day_pattern`, `start_time`, `end_time`, `is_active`, `data_origin`) VALUES
(1, 'Monday', 'MWF', '06:00:00', '06:30:00', 1, 'DEMO'),
(2, 'Monday', 'MWF', '11:00:00', '11:30:00', 1, 'DEMO'),
(3, 'Monday', 'MWF', '16:00:00', '16:30:00', 1, 'DEMO'),
(4, 'Tuesday', 'TTHS', '06:00:00', '06:30:00', 1, 'DEMO'),
(5, 'Tuesday', 'TTHS', '11:00:00', '11:30:00', 1, 'DEMO'),
(6, 'Tuesday', 'TTHS', '16:00:00', '16:30:00', 1, 'DEMO'),
(7, 'Wednesday', 'MWF', '06:00:00', '06:30:00', 1, 'DEMO'),
(8, 'Wednesday', 'MWF', '11:00:00', '11:30:00', 1, 'DEMO'),
(9, 'Wednesday', 'MWF', '16:00:00', '16:30:00', 1, 'DEMO'),
(10, 'Thursday', 'TTHS', '06:00:00', '06:30:00', 1, 'DEMO'),
(11, 'Thursday', 'TTHS', '11:00:00', '11:30:00', 1, 'DEMO'),
(12, 'Thursday', 'TTHS', '16:00:00', '16:30:00', 1, 'DEMO'),
(13, 'Friday', 'MWF', '06:00:00', '06:30:00', 1, 'DEMO'),
(14, 'Friday', 'MWF', '11:00:00', '11:30:00', 1, 'DEMO'),
(15, 'Friday', 'MWF', '16:00:00', '16:30:00', 1, 'DEMO'),
(16, 'Saturday', 'TTHS', '06:00:00', '06:30:00', 1, 'DEMO'),
(17, 'Saturday', 'TTHS', '11:00:00', '11:30:00', 1, 'DEMO'),
(18, 'Saturday', 'TTHS', '16:00:00', '16:30:00', 1, 'DEMO'),
(19, 'Monday', 'MWF', '06:30:00', '07:00:00', 1, 'DEMO'),
(20, 'Monday', 'MWF', '11:30:00', '12:00:00', 1, 'DEMO'),
(21, 'Monday', 'MWF', '16:30:00', '17:00:00', 1, 'DEMO'),
(22, 'Tuesday', 'TTHS', '06:30:00', '07:00:00', 1, 'DEMO'),
(23, 'Tuesday', 'TTHS', '11:30:00', '12:00:00', 1, 'DEMO'),
(24, 'Tuesday', 'TTHS', '16:30:00', '17:00:00', 1, 'DEMO'),
(25, 'Wednesday', 'MWF', '06:30:00', '07:00:00', 1, 'DEMO'),
(26, 'Wednesday', 'MWF', '11:30:00', '12:00:00', 1, 'DEMO'),
(27, 'Wednesday', 'MWF', '16:30:00', '17:00:00', 1, 'DEMO'),
(28, 'Thursday', 'TTHS', '06:30:00', '07:00:00', 1, 'DEMO'),
(29, 'Thursday', 'TTHS', '11:30:00', '12:00:00', 1, 'DEMO'),
(30, 'Thursday', 'TTHS', '16:30:00', '17:00:00', 1, 'DEMO'),
(31, 'Friday', 'MWF', '06:30:00', '07:00:00', 1, 'DEMO'),
(32, 'Friday', 'MWF', '11:30:00', '12:00:00', 1, 'DEMO'),
(33, 'Friday', 'MWF', '16:30:00', '17:00:00', 1, 'DEMO'),
(34, 'Saturday', 'TTHS', '06:30:00', '07:00:00', 1, 'DEMO'),
(35, 'Saturday', 'TTHS', '11:30:00', '12:00:00', 1, 'DEMO'),
(36, 'Saturday', 'TTHS', '16:30:00', '17:00:00', 1, 'DEMO'),
(37, 'Monday', 'MWF', '07:00:00', '07:30:00', 1, 'DEMO'),
(38, 'Monday', 'MWF', '12:00:00', '12:30:00', 1, 'DEMO'),
(39, 'Monday', 'MWF', '17:00:00', '17:30:00', 1, 'DEMO'),
(40, 'Tuesday', 'TTHS', '07:00:00', '07:30:00', 1, 'DEMO'),
(41, 'Tuesday', 'TTHS', '12:00:00', '12:30:00', 1, 'DEMO'),
(42, 'Tuesday', 'TTHS', '17:00:00', '17:30:00', 1, 'DEMO'),
(43, 'Wednesday', 'MWF', '07:00:00', '07:30:00', 1, 'DEMO'),
(44, 'Wednesday', 'MWF', '12:00:00', '12:30:00', 1, 'DEMO'),
(45, 'Wednesday', 'MWF', '17:00:00', '17:30:00', 1, 'DEMO'),
(46, 'Thursday', 'TTHS', '07:00:00', '07:30:00', 1, 'DEMO'),
(47, 'Thursday', 'TTHS', '12:00:00', '12:30:00', 1, 'DEMO'),
(48, 'Thursday', 'TTHS', '17:00:00', '17:30:00', 1, 'DEMO'),
(49, 'Friday', 'MWF', '07:00:00', '07:30:00', 1, 'DEMO'),
(50, 'Friday', 'MWF', '12:00:00', '12:30:00', 1, 'DEMO'),
(51, 'Friday', 'MWF', '17:00:00', '17:30:00', 1, 'DEMO'),
(52, 'Saturday', 'TTHS', '07:00:00', '07:30:00', 1, 'DEMO'),
(53, 'Saturday', 'TTHS', '12:00:00', '12:30:00', 1, 'DEMO'),
(54, 'Saturday', 'TTHS', '17:00:00', '17:30:00', 1, 'DEMO'),
(55, 'Monday', 'MWF', '07:30:00', '08:00:00', 1, 'DEMO'),
(56, 'Monday', 'MWF', '12:30:00', '13:00:00', 1, 'DEMO'),
(57, 'Monday', 'MWF', '17:30:00', '18:00:00', 1, 'DEMO'),
(58, 'Tuesday', 'TTHS', '07:30:00', '08:00:00', 1, 'DEMO'),
(59, 'Tuesday', 'TTHS', '12:30:00', '13:00:00', 1, 'DEMO'),
(60, 'Tuesday', 'TTHS', '17:30:00', '18:00:00', 1, 'DEMO'),
(61, 'Wednesday', 'MWF', '07:30:00', '08:00:00', 1, 'DEMO'),
(62, 'Wednesday', 'MWF', '12:30:00', '13:00:00', 1, 'DEMO'),
(63, 'Wednesday', 'MWF', '17:30:00', '18:00:00', 1, 'DEMO'),
(64, 'Thursday', 'TTHS', '07:30:00', '08:00:00', 1, 'DEMO'),
(65, 'Thursday', 'TTHS', '12:30:00', '13:00:00', 1, 'DEMO'),
(66, 'Thursday', 'TTHS', '17:30:00', '18:00:00', 1, 'DEMO'),
(67, 'Friday', 'MWF', '07:30:00', '08:00:00', 1, 'DEMO'),
(68, 'Friday', 'MWF', '12:30:00', '13:00:00', 1, 'DEMO'),
(69, 'Friday', 'MWF', '17:30:00', '18:00:00', 1, 'DEMO'),
(70, 'Saturday', 'TTHS', '07:30:00', '08:00:00', 1, 'DEMO'),
(71, 'Saturday', 'TTHS', '12:30:00', '13:00:00', 1, 'DEMO'),
(72, 'Saturday', 'TTHS', '17:30:00', '18:00:00', 1, 'DEMO'),
(73, 'Monday', 'MWF', '08:00:00', '08:30:00', 1, 'DEMO'),
(74, 'Monday', 'MWF', '13:00:00', '13:30:00', 1, 'DEMO'),
(75, 'Monday', 'MWF', '18:00:00', '18:30:00', 1, 'DEMO'),
(76, 'Tuesday', 'TTHS', '08:00:00', '08:30:00', 1, 'DEMO'),
(77, 'Tuesday', 'TTHS', '13:00:00', '13:30:00', 1, 'DEMO'),
(78, 'Tuesday', 'TTHS', '18:00:00', '18:30:00', 1, 'DEMO'),
(79, 'Wednesday', 'MWF', '08:00:00', '08:30:00', 1, 'DEMO'),
(80, 'Wednesday', 'MWF', '13:00:00', '13:30:00', 1, 'DEMO'),
(81, 'Wednesday', 'MWF', '18:00:00', '18:30:00', 1, 'DEMO'),
(82, 'Thursday', 'TTHS', '08:00:00', '08:30:00', 1, 'DEMO'),
(83, 'Thursday', 'TTHS', '13:00:00', '13:30:00', 1, 'DEMO'),
(84, 'Thursday', 'TTHS', '18:00:00', '18:30:00', 1, 'DEMO'),
(85, 'Friday', 'MWF', '08:00:00', '08:30:00', 1, 'DEMO'),
(86, 'Friday', 'MWF', '13:00:00', '13:30:00', 1, 'DEMO'),
(87, 'Friday', 'MWF', '18:00:00', '18:30:00', 1, 'DEMO'),
(88, 'Saturday', 'TTHS', '08:00:00', '08:30:00', 1, 'DEMO'),
(89, 'Saturday', 'TTHS', '13:00:00', '13:30:00', 1, 'DEMO'),
(90, 'Saturday', 'TTHS', '18:00:00', '18:30:00', 1, 'DEMO'),
(91, 'Monday', 'MWF', '08:30:00', '09:00:00', 1, 'DEMO'),
(92, 'Monday', 'MWF', '13:30:00', '14:00:00', 1, 'DEMO'),
(93, 'Monday', 'MWF', '18:30:00', '19:00:00', 1, 'DEMO'),
(94, 'Tuesday', 'TTHS', '08:30:00', '09:00:00', 1, 'DEMO'),
(95, 'Tuesday', 'TTHS', '13:30:00', '14:00:00', 1, 'DEMO'),
(96, 'Tuesday', 'TTHS', '18:30:00', '19:00:00', 1, 'DEMO'),
(97, 'Wednesday', 'MWF', '08:30:00', '09:00:00', 1, 'DEMO'),
(98, 'Wednesday', 'MWF', '13:30:00', '14:00:00', 1, 'DEMO'),
(99, 'Wednesday', 'MWF', '18:30:00', '19:00:00', 1, 'DEMO'),
(100, 'Thursday', 'TTHS', '08:30:00', '09:00:00', 1, 'DEMO'),
(101, 'Thursday', 'TTHS', '13:30:00', '14:00:00', 1, 'DEMO'),
(102, 'Thursday', 'TTHS', '18:30:00', '19:00:00', 1, 'DEMO'),
(103, 'Friday', 'MWF', '08:30:00', '09:00:00', 1, 'DEMO'),
(104, 'Friday', 'MWF', '13:30:00', '14:00:00', 1, 'DEMO'),
(105, 'Friday', 'MWF', '18:30:00', '19:00:00', 1, 'DEMO'),
(106, 'Saturday', 'TTHS', '08:30:00', '09:00:00', 1, 'DEMO'),
(107, 'Saturday', 'TTHS', '13:30:00', '14:00:00', 1, 'DEMO'),
(108, 'Saturday', 'TTHS', '18:30:00', '19:00:00', 1, 'DEMO'),
(109, 'Monday', 'MWF', '09:00:00', '09:30:00', 1, 'DEMO'),
(110, 'Monday', 'MWF', '14:00:00', '14:30:00', 1, 'DEMO'),
(111, 'Monday', 'MWF', '19:00:00', '19:30:00', 1, 'DEMO'),
(112, 'Tuesday', 'TTHS', '09:00:00', '09:30:00', 1, 'DEMO'),
(113, 'Tuesday', 'TTHS', '14:00:00', '14:30:00', 1, 'DEMO'),
(114, 'Tuesday', 'TTHS', '19:00:00', '19:30:00', 1, 'DEMO'),
(115, 'Wednesday', 'MWF', '09:00:00', '09:30:00', 1, 'DEMO'),
(116, 'Wednesday', 'MWF', '14:00:00', '14:30:00', 1, 'DEMO'),
(117, 'Wednesday', 'MWF', '19:00:00', '19:30:00', 1, 'DEMO'),
(118, 'Thursday', 'TTHS', '09:00:00', '09:30:00', 1, 'DEMO'),
(119, 'Thursday', 'TTHS', '14:00:00', '14:30:00', 1, 'DEMO'),
(120, 'Thursday', 'TTHS', '19:00:00', '19:30:00', 1, 'DEMO'),
(121, 'Friday', 'MWF', '09:00:00', '09:30:00', 1, 'DEMO'),
(122, 'Friday', 'MWF', '14:00:00', '14:30:00', 1, 'DEMO'),
(123, 'Friday', 'MWF', '19:00:00', '19:30:00', 1, 'DEMO'),
(124, 'Saturday', 'TTHS', '09:00:00', '09:30:00', 1, 'DEMO'),
(125, 'Saturday', 'TTHS', '14:00:00', '14:30:00', 1, 'DEMO'),
(126, 'Saturday', 'TTHS', '19:00:00', '19:30:00', 1, 'DEMO'),
(127, 'Monday', 'MWF', '09:30:00', '10:00:00', 1, 'DEMO'),
(128, 'Monday', 'MWF', '14:30:00', '15:00:00', 1, 'DEMO'),
(129, 'Monday', 'MWF', '19:30:00', '20:00:00', 1, 'DEMO'),
(130, 'Tuesday', 'TTHS', '09:30:00', '10:00:00', 1, 'DEMO'),
(131, 'Tuesday', 'TTHS', '14:30:00', '15:00:00', 1, 'DEMO'),
(132, 'Tuesday', 'TTHS', '19:30:00', '20:00:00', 1, 'DEMO'),
(133, 'Wednesday', 'MWF', '09:30:00', '10:00:00', 1, 'DEMO'),
(134, 'Wednesday', 'MWF', '14:30:00', '15:00:00', 1, 'DEMO'),
(135, 'Wednesday', 'MWF', '19:30:00', '20:00:00', 1, 'DEMO'),
(136, 'Thursday', 'TTHS', '09:30:00', '10:00:00', 1, 'DEMO'),
(137, 'Thursday', 'TTHS', '14:30:00', '15:00:00', 1, 'DEMO'),
(138, 'Thursday', 'TTHS', '19:30:00', '20:00:00', 1, 'DEMO'),
(139, 'Friday', 'MWF', '09:30:00', '10:00:00', 1, 'DEMO'),
(140, 'Friday', 'MWF', '14:30:00', '15:00:00', 1, 'DEMO'),
(141, 'Friday', 'MWF', '19:30:00', '20:00:00', 1, 'DEMO'),
(142, 'Saturday', 'TTHS', '09:30:00', '10:00:00', 1, 'DEMO'),
(143, 'Saturday', 'TTHS', '14:30:00', '15:00:00', 1, 'DEMO'),
(144, 'Saturday', 'TTHS', '19:30:00', '20:00:00', 1, 'DEMO'),
(145, 'Monday', 'MWF', '10:00:00', '10:30:00', 1, 'DEMO'),
(146, 'Monday', 'MWF', '15:00:00', '15:30:00', 1, 'DEMO'),
(147, 'Monday', 'MWF', '20:00:00', '20:30:00', 1, 'DEMO'),
(148, 'Tuesday', 'TTHS', '10:00:00', '10:30:00', 1, 'DEMO'),
(149, 'Tuesday', 'TTHS', '15:00:00', '15:30:00', 1, 'DEMO'),
(150, 'Tuesday', 'TTHS', '20:00:00', '20:30:00', 1, 'DEMO'),
(151, 'Wednesday', 'MWF', '10:00:00', '10:30:00', 1, 'DEMO'),
(152, 'Wednesday', 'MWF', '15:00:00', '15:30:00', 1, 'DEMO'),
(153, 'Wednesday', 'MWF', '20:00:00', '20:30:00', 1, 'DEMO'),
(154, 'Thursday', 'TTHS', '10:00:00', '10:30:00', 1, 'DEMO'),
(155, 'Thursday', 'TTHS', '15:00:00', '15:30:00', 1, 'DEMO'),
(156, 'Thursday', 'TTHS', '20:00:00', '20:30:00', 1, 'DEMO'),
(157, 'Friday', 'MWF', '10:00:00', '10:30:00', 1, 'DEMO'),
(158, 'Friday', 'MWF', '15:00:00', '15:30:00', 1, 'DEMO'),
(159, 'Friday', 'MWF', '20:00:00', '20:30:00', 1, 'DEMO'),
(160, 'Saturday', 'TTHS', '10:00:00', '10:30:00', 1, 'DEMO'),
(161, 'Saturday', 'TTHS', '15:00:00', '15:30:00', 1, 'DEMO'),
(162, 'Saturday', 'TTHS', '20:00:00', '20:30:00', 1, 'DEMO'),
(163, 'Monday', 'MWF', '10:30:00', '11:00:00', 1, 'DEMO'),
(164, 'Monday', 'MWF', '15:30:00', '16:00:00', 1, 'DEMO'),
(165, 'Monday', 'MWF', '20:30:00', '21:00:00', 1, 'DEMO'),
(166, 'Tuesday', 'TTHS', '10:30:00', '11:00:00', 1, 'DEMO'),
(167, 'Tuesday', 'TTHS', '15:30:00', '16:00:00', 1, 'DEMO'),
(168, 'Tuesday', 'TTHS', '20:30:00', '21:00:00', 1, 'DEMO'),
(169, 'Wednesday', 'MWF', '10:30:00', '11:00:00', 1, 'DEMO'),
(170, 'Wednesday', 'MWF', '15:30:00', '16:00:00', 1, 'DEMO'),
(171, 'Wednesday', 'MWF', '20:30:00', '21:00:00', 1, 'DEMO'),
(172, 'Thursday', 'TTHS', '10:30:00', '11:00:00', 1, 'DEMO'),
(173, 'Thursday', 'TTHS', '15:30:00', '16:00:00', 1, 'DEMO'),
(174, 'Thursday', 'TTHS', '20:30:00', '21:00:00', 1, 'DEMO'),
(175, 'Friday', 'MWF', '10:30:00', '11:00:00', 1, 'DEMO'),
(176, 'Friday', 'MWF', '15:30:00', '16:00:00', 1, 'DEMO'),
(177, 'Friday', 'MWF', '20:30:00', '21:00:00', 1, 'DEMO'),
(178, 'Saturday', 'TTHS', '10:30:00', '11:00:00', 1, 'DEMO'),
(179, 'Saturday', 'TTHS', '15:30:00', '16:00:00', 1, 'DEMO'),
(180, 'Saturday', 'TTHS', '20:30:00', '21:00:00', 1, 'DEMO');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_periods`
--
ALTER TABLE `academic_periods`
  ADD PRIMARY KEY (`academic_period_id`),
  ADD UNIQUE KEY `uq_academic_period` (`academic_year`,`semester`);

--
-- Indexes for table `academic_period_calendars`
--
ALTER TABLE `academic_period_calendars`
  ADD PRIMARY KEY (`academic_period_calendar_id`),
  ADD UNIQUE KEY `uq_calendar_period` (`academic_period_id`);

--
-- Indexes for table `auth_users`
--
ALTER TABLE `auth_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_auth_username` (`username`);

--
-- Indexes for table `exam_batches`
--
ALTER TABLE `exam_batches`
  ADD PRIMARY KEY (`exam_batch_id`),
  ADD UNIQUE KEY `uq_active_exam_batch` (`academic_period_id`,`program_id`,`exam_label`,`active_key`),
  ADD KEY `idx_exam_batch_period` (`academic_period_id`,`status`),
  ADD KEY `idx_exam_batch_program` (`program_id`),
  ADD KEY `idx_exam_class_batch` (`class_batch_id`);

--
-- Indexes for table `exam_meetings`
--
ALTER TABLE `exam_meetings`
  ADD PRIMARY KEY (`exam_meeting_id`),
  ADD UNIQUE KEY `uq_exam_subject_batch` (`exam_batch_id`,`section_subject_id`),
  ADD KEY `idx_exam_date_time` (`exam_date`,`start_time`,`end_time`),
  ADD KEY `idx_exam_room` (`room_id`,`exam_date`,`start_time`,`end_time`),
  ADD KEY `idx_exam_proctor` (`proctor_id`,`exam_date`,`start_time`,`end_time`),
  ADD KEY `idx_exam_section_subject` (`section_subject_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `uq_program_code` (`program_code`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`room_id`),
  ADD UNIQUE KEY `uq_room_name` (`room_name`),
  ADD KEY `fk_room_program` (`program_id`);

--
-- Indexes for table `room_availability`
--
ALTER TABLE `room_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD UNIQUE KEY `uq_room_availability` (`room_id`,`academic_period_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `fk_ra_period` (`academic_period_id`);

--
-- Indexes for table `schedule_batches`
--
ALTER TABLE `schedule_batches`
  ADD PRIMARY KEY (`batch_id`),
  ADD KEY `idx_batch_period_status` (`academic_period_id`,`status`,`program_id`),
  ADD KEY `fk_schedule_batch_program` (`program_id`);

--
-- Indexes for table `schedule_meetings`
--
ALTER TABLE `schedule_meetings`
  ADD PRIMARY KEY (`meeting_id`),
  ADD UNIQUE KEY `uq_batch_subject_delivery` (`batch_id`,`section_subject_id`,`delivery_mode`),
  ADD KEY `idx_meeting_teacher_time` (`teacher_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_meeting_room_time` (`room_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `idx_meeting_section_subject` (`section_subject_id`);

--
-- Indexes for table `schedule_reference_batches`
--
ALTER TABLE `schedule_reference_batches`
  ADD PRIMARY KEY (`reference_batch_id`),
  ADD KEY `idx_reference_target` (`target_academic_period_id`,`program_id`,`reference_status`),
  ADD KEY `idx_reference_source_batch` (`source_batch_id`),
  ADD KEY `fk_reference_source_period` (`source_academic_period_id`),
  ADD KEY `fk_reference_program` (`program_id`);

--
-- Indexes for table `schedule_reference_meetings`
--
ALTER TABLE `schedule_reference_meetings`
  ADD PRIMARY KEY (`reference_meeting_id`),
  ADD KEY `idx_reference_meeting_batch` (`reference_batch_id`),
  ADD KEY `idx_reference_section_subject` (`section_code_snapshot`,`subject_code_snapshot`),
  ADD KEY `idx_reference_teacher` (`teacher_employee_no_snapshot`),
  ADD KEY `idx_reference_room` (`room_name_snapshot`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uq_section_identity` (`academic_period_id`,`program_id`,`section_code`),
  ADD KEY `idx_section_program` (`program_id`,`academic_period_id`);

--
-- Indexes for table `section_major_links`
--
ALTER TABLE `section_major_links`
  ADD PRIMARY KEY (`link_id`),
  ADD UNIQUE KEY `uq_home_major` (`home_section_id`,`major_section_id`),
  ADD KEY `fk_major_section` (`major_section_id`);

--
-- Indexes for table `section_subjects`
--
ALTER TABLE `section_subjects`
  ADD PRIMARY KEY (`section_subject_id`),
  ADD UNIQUE KEY `uq_section_subject` (`section_id`,`subject_id`),
  ADD KEY `fk_ss_subject` (`subject_id`);

--
-- Indexes for table `special_classes`
--
ALTER TABLE `special_classes`
  ADD PRIMARY KEY (`special_class_id`),
  ADD KEY `idx_sc_period` (`academic_period_id`,`program_id`,`status`),
  ADD KEY `fk_sc_program` (`program_id`),
  ADD KEY `fk_sc_subject` (`subject_id`),
  ADD KEY `fk_sc_teacher` (`teacher_id`);

--
-- Indexes for table `special_class_meetings`
--
ALTER TABLE `special_class_meetings`
  ADD PRIMARY KEY (`special_meeting_id`),
  ADD UNIQUE KEY `uq_special_weekly_meeting` (`special_class_id`,`meeting_date`,`start_time`),
  ADD KEY `idx_special_date_teacher` (`meeting_date`,`teacher_id`,`start_time`,`end_time`),
  ADD KEY `idx_special_date_room` (`meeting_date`,`room_id`,`start_time`,`end_time`),
  ADD KEY `fk_scm_room` (`room_id`),
  ADD KEY `fk_scm_teacher` (`teacher_id`);

--
-- Indexes for table `special_class_policies`
--
ALTER TABLE `special_class_policies`
  ADD PRIMARY KEY (`special_class_policy_id`),
  ADD UNIQUE KEY `uq_special_policy_scope` (`academic_period_id`,`program_id`,`class_type`),
  ADD KEY `fk_special_policy_program` (`program_id`);

--
-- Indexes for table `special_class_students`
--
ALTER TABLE `special_class_students`
  ADD PRIMARY KEY (`special_class_id`,`student_number`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `uq_student_period` (`academic_period_id`,`student_number`),
  ADD KEY `idx_student_roster` (`program_id`,`academic_period_id`,`home_section_id`),
  ADD KEY `idx_student_major` (`major_section_id`),
  ADD KEY `fk_demo_student_home` (`home_section_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `uq_legacy_subject` (`legacy_subject_id`),
  ADD KEY `idx_subject_program` (`program_id`),
  ADD KEY `idx_subject_period` (`program_id`,`year_level`,`semester`);

--
-- Indexes for table `substitute_assignments`
--
ALTER TABLE `substitute_assignments`
  ADD PRIMARY KEY (`substitute_assignment_id`),
  ADD UNIQUE KEY `uq_substitute_one_active` (`meeting_id`,`duty_date`,`active_key`),
  ADD KEY `idx_substitute_period_date` (`academic_period_id`,`duty_date`,`status`),
  ADD KEY `idx_substitute_teacher_date` (`substitute_teacher_id`,`duty_date`,`status`),
  ADD KEY `idx_substitute_batch` (`class_batch_id`),
  ADD KEY `fk_sub_original_teacher` (`original_teacher_id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`teacher_id`),
  ADD UNIQUE KEY `uq_teacher_employee` (`employee_no`),
  ADD UNIQUE KEY `uq_teacher_demo` (`program_id`,`demo_no`);

--
-- Indexes for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD UNIQUE KEY `uq_teacher_availability` (`teacher_id`,`academic_period_id`,`day_of_week`,`start_time`,`end_time`),
  ADD KEY `fk_ta_period` (`academic_period_id`);

--
-- Indexes for table `teacher_subject_authorizations`
--
ALTER TABLE `teacher_subject_authorizations`
  ADD PRIMARY KEY (`authorization_id`),
  ADD UNIQUE KEY `uq_teacher_subject` (`teacher_id`,`subject_id`),
  ADD KEY `fk_auth_subject` (`subject_id`);

--
-- Indexes for table `time_slots`
--
ALTER TABLE `time_slots`
  ADD PRIMARY KEY (`time_slot_id`),
  ADD UNIQUE KEY `uq_time_slot` (`day_of_week`,`start_time`,`end_time`,`data_origin`),
  ADD KEY `idx_time_slot_day` (`day_of_week`,`start_time`,`end_time`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_periods`
--
ALTER TABLE `academic_periods`
  MODIFY `academic_period_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `academic_period_calendars`
--
ALTER TABLE `academic_period_calendars`
  MODIFY `academic_period_calendar_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auth_users`
--
ALTER TABLE `auth_users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `exam_batches`
--
ALTER TABLE `exam_batches`
  MODIFY `exam_batch_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `exam_meetings`
--
ALTER TABLE `exam_meetings`
  MODIFY `exam_meeting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=129;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `room_availability`
--
ALTER TABLE `room_availability`
  MODIFY `availability_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=241;

--
-- AUTO_INCREMENT for table `schedule_batches`
--
ALTER TABLE `schedule_batches`
  MODIFY `batch_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `schedule_meetings`
--
ALTER TABLE `schedule_meetings`
  MODIFY `meeting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=769;

--
-- AUTO_INCREMENT for table `schedule_reference_batches`
--
ALTER TABLE `schedule_reference_batches`
  MODIFY `reference_batch_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedule_reference_meetings`
--
ALTER TABLE `schedule_reference_meetings`
  MODIFY `reference_meeting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `section_major_links`
--
ALTER TABLE `section_major_links`
  MODIFY `link_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `section_subjects`
--
ALTER TABLE `section_subjects`
  MODIFY `section_subject_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=383;

--
-- AUTO_INCREMENT for table `special_classes`
--
ALTER TABLE `special_classes`
  MODIFY `special_class_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_class_meetings`
--
ALTER TABLE `special_class_meetings`
  MODIFY `special_meeting_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_class_policies`
--
ALTER TABLE `special_class_policies`
  MODIFY `special_class_policy_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2012;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `subject_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `substitute_assignments`
--
ALTER TABLE `substitute_assignments`
  MODIFY `substitute_assignment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `teacher_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  MODIFY `availability_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=289;

--
-- AUTO_INCREMENT for table `teacher_subject_authorizations`
--
ALTER TABLE `teacher_subject_authorizations`
  MODIFY `authorization_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `time_slots`
--
ALTER TABLE `time_slots`
  MODIFY `time_slot_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `academic_period_calendars`
--
ALTER TABLE `academic_period_calendars`
  ADD CONSTRAINT `fk_calendar_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`);

--
-- Constraints for table `exam_batches`
--
ALTER TABLE `exam_batches`
  ADD CONSTRAINT `fk_exam_batch_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_exam_batch_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  ADD CONSTRAINT `fk_exam_class_batch` FOREIGN KEY (`class_batch_id`) REFERENCES `schedule_batches` (`batch_id`);

--
-- Constraints for table `exam_meetings`
--
ALTER TABLE `exam_meetings`
  ADD CONSTRAINT `fk_exam_meeting_batch` FOREIGN KEY (`exam_batch_id`) REFERENCES `exam_batches` (`exam_batch_id`),
  ADD CONSTRAINT `fk_exam_proctor` FOREIGN KEY (`proctor_id`) REFERENCES `teachers` (`teacher_id`),
  ADD CONSTRAINT `fk_exam_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `fk_exam_section_subject` FOREIGN KEY (`section_subject_id`) REFERENCES `section_subjects` (`section_subject_id`);

--
-- Constraints for table `rooms`
--
ALTER TABLE `rooms`
  ADD CONSTRAINT `fk_room_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `room_availability`
--
ALTER TABLE `room_availability`
  ADD CONSTRAINT `fk_ra_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_ra_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`);

--
-- Constraints for table `schedule_batches`
--
ALTER TABLE `schedule_batches`
  ADD CONSTRAINT `fk_schedule_batch_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_schedule_batch_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `schedule_meetings`
--
ALTER TABLE `schedule_meetings`
  ADD CONSTRAINT `fk_meeting_batch` FOREIGN KEY (`batch_id`) REFERENCES `schedule_batches` (`batch_id`),
  ADD CONSTRAINT `fk_meeting_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `fk_meeting_section_subject` FOREIGN KEY (`section_subject_id`) REFERENCES `section_subjects` (`section_subject_id`),
  ADD CONSTRAINT `fk_meeting_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `schedule_reference_batches`
--
ALTER TABLE `schedule_reference_batches`
  ADD CONSTRAINT `fk_reference_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  ADD CONSTRAINT `fk_reference_source_batch` FOREIGN KEY (`source_batch_id`) REFERENCES `schedule_batches` (`batch_id`),
  ADD CONSTRAINT `fk_reference_source_period` FOREIGN KEY (`source_academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_reference_target_period` FOREIGN KEY (`target_academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`);

--
-- Constraints for table `schedule_reference_meetings`
--
ALTER TABLE `schedule_reference_meetings`
  ADD CONSTRAINT `fk_reference_meeting_batch` FOREIGN KEY (`reference_batch_id`) REFERENCES `schedule_reference_batches` (`reference_batch_id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `fk_section_academic_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_section_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `section_major_links`
--
ALTER TABLE `section_major_links`
  ADD CONSTRAINT `fk_major_home` FOREIGN KEY (`home_section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_major_section` FOREIGN KEY (`major_section_id`) REFERENCES `sections` (`section_id`);

--
-- Constraints for table `section_subjects`
--
ALTER TABLE `section_subjects`
  ADD CONSTRAINT `fk_ss_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_ss_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`);

--
-- Constraints for table `special_classes`
--
ALTER TABLE `special_classes`
  ADD CONSTRAINT `fk_sc_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_sc_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`),
  ADD CONSTRAINT `fk_sc_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_sc_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `special_class_meetings`
--
ALTER TABLE `special_class_meetings`
  ADD CONSTRAINT `fk_scm_class` FOREIGN KEY (`special_class_id`) REFERENCES `special_classes` (`special_class_id`),
  ADD CONSTRAINT `fk_scm_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`),
  ADD CONSTRAINT `fk_scm_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `special_class_policies`
--
ALTER TABLE `special_class_policies`
  ADD CONSTRAINT `fk_special_policy_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_special_policy_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `special_class_students`
--
ALTER TABLE `special_class_students`
  ADD CONSTRAINT `fk_scs_class` FOREIGN KEY (`special_class_id`) REFERENCES `special_classes` (`special_class_id`);

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_demo_student_home` FOREIGN KEY (`home_section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_demo_student_major` FOREIGN KEY (`major_section_id`) REFERENCES `sections` (`section_id`),
  ADD CONSTRAINT `fk_demo_student_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_demo_student_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `fk_subject_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `substitute_assignments`
--
ALTER TABLE `substitute_assignments`
  ADD CONSTRAINT `fk_sub_batch` FOREIGN KEY (`class_batch_id`) REFERENCES `schedule_batches` (`batch_id`),
  ADD CONSTRAINT `fk_sub_meeting` FOREIGN KEY (`meeting_id`) REFERENCES `schedule_meetings` (`meeting_id`),
  ADD CONSTRAINT `fk_sub_original_teacher` FOREIGN KEY (`original_teacher_id`) REFERENCES `teachers` (`teacher_id`),
  ADD CONSTRAINT `fk_sub_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_sub_replacement_teacher` FOREIGN KEY (`substitute_teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `fk_teacher_program` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`);

--
-- Constraints for table `teacher_availability`
--
ALTER TABLE `teacher_availability`
  ADD CONSTRAINT `fk_ta_period` FOREIGN KEY (`academic_period_id`) REFERENCES `academic_periods` (`academic_period_id`),
  ADD CONSTRAINT `fk_ta_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);

--
-- Constraints for table `teacher_subject_authorizations`
--
ALTER TABLE `teacher_subject_authorizations`
  ADD CONSTRAINT `fk_auth_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`),
  ADD CONSTRAINT `fk_auth_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`teacher_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
