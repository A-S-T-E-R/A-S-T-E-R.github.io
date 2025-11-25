-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2025 at 11:02 AM
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
-- Database: `school_portal`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounts`
--

CREATE TABLE `tbl_accounts` (
  `account_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student','parent') NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_accounts`
--

INSERT INTO `tbl_accounts` (`account_id`, `username`, `password`, `role`, `first_name`, `last_name`, `email`, `contact_number`, `created_at`) VALUES
(1, 'b', '$2y$10$HkGQsW50WTELbuuZFoqRreQWwi.sj8AVxvuRXMY2awNGes83fid9u', 'admin', 'Ka ', 'Ramos', 'marcopauloramos03@gmail.com', '09079572813', '2025-08-12 17:59:25'),
(12, 'parent2', 'p', 'parent', 'Maria', 'Dela Cruz', 'maria@example.com', '09123456789', '2025-10-25 00:25:12'),
(13, 's.s', '$2y$10$OdAAViC5KT8djiXLfgtVaOIuXmHconvq/qcMxiVBJvpgZWSLepUpG', 'student', 's', 's', NULL, 's', '2025-10-25 00:30:43'),
(14, 't@t', '$2y$10$nao8ulsGnwtjs6IGjSTdT.i3fXIEIdqNp0aZQak12kF/OlikqiyPC', 'teacher', 't', 't', NULL, 't', '2025-10-25 00:31:02'),
(15, 'p@p', '$2y$10$VbCUBs2yx8yc6sKeHahFZOfMLC884vHPmjM3UwnBbOd99uerVJKMK', 'parent', 'p', 'p', NULL, 'p', '2025-10-25 00:53:39');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_accounts_archive`
--

CREATE TABLE `tbl_accounts_archive` (
  `archive_id` int(11) NOT NULL,
  `original_account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','teacher','student','parent') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_activity_comments`
--

CREATE TABLE `tbl_activity_comments` (
  `comment_id` int(11) NOT NULL,
  `activity_id` int(11) NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `comment_text` text NOT NULL,
  `comment_type` enum('public','private') DEFAULT 'public',
  `file_path` varchar(255) DEFAULT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `max_points` int(10) UNSIGNED DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `graded_by` int(10) UNSIGNED DEFAULT NULL,
  `graded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_audit_logs`
--

CREATE TABLE `tbl_audit_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(200) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_career_results`
--

CREATE TABLE `tbl_career_results` (
  `result_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `test_id` int(10) UNSIGNED NOT NULL,
  `result_text` text DEFAULT NULL,
  `recommended_track` varchar(100) DEFAULT NULL,
  `date_taken` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_career_tests`
--

CREATE TABLE `tbl_career_tests` (
  `test_id` int(10) UNSIGNED NOT NULL,
  `test_name` varchar(120) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stem_score` int(11) DEFAULT 0,
  `abm_score` int(11) DEFAULT 0,
  `humss_score` int(11) DEFAULT 0,
  `tvl_score` int(11) DEFAULT 0,
  `recommended_strand` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_career_tests`
--

INSERT INTO `tbl_career_tests` (`test_id`, `test_name`, `description`, `created_at`, `stem_score`, `abm_score`, `humss_score`, `tvl_score`, `recommended_strand`) VALUES
(1, 'SHS Career Strand Test', 'Senior High Strand Recommendation Test', '2025-10-25 00:24:32', 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_feedbacks`
--

CREATE TABLE `tbl_feedbacks` (
  `feedback_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED DEFAULT NULL,
  `feedback_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_grades`
--

CREATE TABLE `tbl_grades` (
  `grade_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `teacher_account_id` int(10) UNSIGNED DEFAULT NULL,
  `grading_period` enum('Q1','Q2','Q3','Q4','Final') NOT NULL DEFAULT 'Final',
  `q1` decimal(5,2) DEFAULT NULL,
  `q2` decimal(5,2) DEFAULT NULL,
  `q3` decimal(5,2) DEFAULT NULL,
  `q4` decimal(5,2) DEFAULT NULL,
  `midterm` decimal(5,2) DEFAULT NULL,
  `final` decimal(5,2) DEFAULT NULL,
  `grade` decimal(5,2) NOT NULL,
  `remarks` varchar(60) DEFAULT NULL,
  `date_recorded` datetime NOT NULL DEFAULT current_timestamp(),
  `quarter_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_grades`
--

INSERT INTO `tbl_grades` (`grade_id`, `student_id`, `subject_id`, `teacher_account_id`, `grading_period`, `q1`, `q2`, `q3`, `q4`, `midterm`, `final`, `grade`, `remarks`, `date_recorded`, `quarter_id`) VALUES
(1, 7, 16, 14, 'Q1', NULL, NULL, NULL, NULL, NULL, NULL, 99.00, NULL, '2025-10-25 00:31:59', NULL),
(2, 7, 16, 14, 'Q2', NULL, NULL, NULL, NULL, NULL, NULL, 24.00, NULL, '2025-10-25 00:31:59', NULL),
(3, 7, 16, 14, 'Q3', NULL, NULL, NULL, NULL, NULL, NULL, 67.00, NULL, '2025-10-25 00:31:59', NULL),
(4, 7, 16, 14, 'Q4', NULL, NULL, NULL, NULL, NULL, NULL, 89.00, NULL, '2025-10-25 00:31:59', NULL),
(5, 7, 16, 14, 'Final', NULL, NULL, NULL, NULL, NULL, NULL, 69.75, '0', '2025-10-25 00:31:59', NULL),
(6, 7, 28, 14, 'Q1', NULL, NULL, NULL, NULL, NULL, NULL, 90.00, NULL, '2025-10-25 00:33:45', NULL),
(7, 7, 28, 14, 'Q2', NULL, NULL, NULL, NULL, NULL, NULL, 90.00, NULL, '2025-10-25 00:33:45', NULL),
(8, 7, 28, 14, 'Q3', NULL, NULL, NULL, NULL, NULL, NULL, 80.00, NULL, '2025-10-25 00:33:45', NULL),
(9, 7, 28, 14, 'Q4', NULL, NULL, NULL, NULL, NULL, NULL, 80.00, NULL, '2025-10-25 00:33:45', NULL),
(10, 7, 28, 14, 'Final', NULL, NULL, NULL, NULL, NULL, NULL, 85.00, '0', '2025-10-25 00:33:45', NULL),
(11, 7, 24, 14, 'Q1', NULL, NULL, NULL, NULL, NULL, NULL, 91.00, NULL, '2025-10-25 00:46:28', NULL),
(12, 7, 24, 14, 'Q2', NULL, NULL, NULL, NULL, NULL, NULL, 90.00, NULL, '2025-10-25 00:46:28', NULL),
(13, 7, 24, 14, 'Q3', NULL, NULL, NULL, NULL, NULL, NULL, 28.00, NULL, '2025-10-25 00:46:28', NULL),
(14, 7, 24, 14, 'Q4', NULL, NULL, NULL, NULL, NULL, NULL, 90.00, NULL, '2025-10-25 00:46:28', NULL),
(15, 7, 24, 14, 'Final', NULL, NULL, NULL, NULL, NULL, NULL, 74.75, '0', '2025-10-25 00:46:28', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_grades_archive`
--

CREATE TABLE `tbl_grades_archive` (
  `archive_id` int(11) NOT NULL,
  `original_grade_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_account_id` int(11) DEFAULT NULL,
  `grade` decimal(5,2) DEFAULT NULL,
  `grading_period` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_parents`
--

CREATE TABLE `tbl_parents` (
  `parent_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_parents`
--

INSERT INTO `tbl_parents` (`parent_id`, `account_id`, `created_at`) VALUES
(1, 15, '2025-10-25 00:53:39');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_parents_archive`
--

CREATE TABLE `tbl_parents_archive` (
  `archive_id` int(11) NOT NULL,
  `original_parent_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_parent_student`
--

CREATE TABLE `tbl_parent_student` (
  `id` int(10) UNSIGNED NOT NULL,
  `parent_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `relation` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_parent_student`
--

INSERT INTO `tbl_parent_student` (`id`, `parent_id`, `student_id`, `relation`, `created_at`) VALUES
(1, 1, 7, NULL, '2025-10-25 00:53:48');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_quarters`
--

CREATE TABLE `tbl_quarters` (
  `quarter_id` int(11) NOT NULL,
  `quarter_name` varchar(50) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `semester_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_scores`
--

CREATE TABLE `tbl_scores` (
  `score_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `quiz1` decimal(5,2) DEFAULT NULL,
  `quiz2` decimal(5,2) DEFAULT NULL,
  `quiz3` decimal(5,2) DEFAULT NULL,
  `midterm` decimal(5,2) DEFAULT NULL,
  `final` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_sections`
--

CREATE TABLE `tbl_sections` (
  `section_id` int(10) UNSIGNED NOT NULL,
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `strand_id` int(10) UNSIGNED DEFAULT NULL,
  `section_name` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_sections`
--

INSERT INTO `tbl_sections` (`section_id`, `year_level_id`, `strand_id`, `section_name`) VALUES
(1, 1, NULL, 'A'),
(2, 1, NULL, 'B'),
(3, 2, NULL, 'A'),
(4, 2, NULL, 'B'),
(5, 3, NULL, 'A'),
(6, 3, NULL, 'B'),
(7, 4, NULL, 'A'),
(8, 4, NULL, 'B'),
(9, 5, NULL, 'A'),
(10, 5, NULL, 'B'),
(11, 6, NULL, 'A'),
(12, 6, NULL, 'B'),
(13, 7, NULL, 'A'),
(14, 7, NULL, 'B'),
(15, 8, NULL, 'A'),
(16, 8, NULL, 'B'),
(17, 9, NULL, 'A'),
(18, 9, NULL, 'B'),
(19, 10, NULL, 'A'),
(20, 10, NULL, 'B'),
(21, 11, NULL, 'STEM-1'),
(22, 11, NULL, 'ABM-1'),
(23, 12, NULL, 'STEM-1'),
(24, 12, NULL, 'ABM-1');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_semester`
--

CREATE TABLE `tbl_semester` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_semester`
--

INSERT INTO `tbl_semester` (`semester_id`, `semester_name`) VALUES
(1, '1st Semester'),
(2, '2nd Semester');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_semesters`
--

CREATE TABLE `tbl_semesters` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `academic_year` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_spreadsheets`
--

CREATE TABLE `tbl_spreadsheets` (
  `sheet_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `sheet_name` varchar(150) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_spreadsheet_columns`
--

CREATE TABLE `tbl_spreadsheet_columns` (
  `column_id` int(10) UNSIGNED NOT NULL,
  `sheet_id` int(10) UNSIGNED NOT NULL,
  `column_name` varchar(150) NOT NULL,
  `column_type` enum('activity','quiz','exam','other') DEFAULT 'other',
  `max_score` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_spreadsheet_scores`
--

CREATE TABLE `tbl_spreadsheet_scores` (
  `score_id` int(10) UNSIGNED NOT NULL,
  `sheet_id` int(10) UNSIGNED NOT NULL,
  `column_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(6,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_strands`
--

CREATE TABLE `tbl_strands` (
  `strand_id` int(10) UNSIGNED NOT NULL,
  `strand_name` varchar(120) NOT NULL,
  `strand_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `education_stage` enum('Senior High') DEFAULT 'Senior High',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_strands`
--

INSERT INTO `tbl_strands` (`strand_id`, `strand_name`, `strand_code`, `description`, `education_stage`, `created_at`) VALUES
(1, 'STEM', 'STEM', 'Science, Technology, Engineering, and Mathematics', 'Senior High', '2025-10-25 00:24:37'),
(2, 'ABM', 'ABM', 'Accountancy, Business, and Management', 'Senior High', '2025-10-25 00:24:37'),
(3, 'HUMSS', 'HUMSS', 'Humanities and Social Sciences', 'Senior High', '2025-10-25 00:24:37'),
(4, 'TVL', 'TVL', 'Technical-Vocational-Livelihood', 'Senior High', '2025-10-25 00:24:37');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_students`
--

CREATE TABLE `tbl_students` (
  `student_id` int(10) UNSIGNED NOT NULL,
  `account_id` int(10) UNSIGNED NOT NULL,
  `parent_account_id` int(11) DEFAULT NULL,
  `lrn` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_students`
--

INSERT INTO `tbl_students` (`student_id`, `account_id`, `parent_account_id`, `lrn`, `birth_date`, `gender`, `address`, `year_level_id`, `section_id`, `created_at`) VALUES
(3, 3, NULL, '104757090163', '2025-08-02', 'Male', NULL, 1, 1, '2025-08-12 13:50:33'),
(4, 8, NULL, '2', '2025-08-12', 'Male', NULL, 1, 1, '2025-08-12 21:11:52'),
(5, 9, NULL, 'j', '2025-08-12', 'Female', NULL, 2, 3, '2025-08-12 21:15:24'),
(6, 10, NULL, '104757090163-dup', '2025-08-12', 'Male', NULL, 1, 1, '2025-08-12 21:40:32'),
(7, 13, NULL, '123', '2003-12-12', 'Male', NULL, 3, 6, '2025-10-25 00:30:43');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_students_archive`
--

CREATE TABLE `tbl_students_archive` (
  `archive_id` int(11) NOT NULL,
  `original_student_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `year_level_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subjects`
--

CREATE TABLE `tbl_subjects` (
  `subject_id` int(10) UNSIGNED NOT NULL,
  `subject_name` varchar(120) NOT NULL,
  `year_level_id` int(10) UNSIGNED DEFAULT NULL,
  `semester` enum('1','2','Full Year') DEFAULT 'Full Year',
  `semester_id` int(11) DEFAULT NULL,
  `strand_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_subjects`
--

INSERT INTO `tbl_subjects` (`subject_id`, `subject_name`, `year_level_id`, `semester`, `semester_id`, `strand_id`) VALUES
(11, 'Language', 1, 'Full Year', NULL, NULL),
(12, 'Reading and Literacy', 1, 'Full Year', NULL, NULL),
(13, 'Mathematics', 1, 'Full Year', NULL, NULL),
(14, 'Makabansa', 1, 'Full Year', NULL, NULL),
(15, 'GMRC', 1, 'Full Year', NULL, NULL),
(16, 'Filipino', 2, 'Full Year', NULL, NULL),
(17, 'English', 2, 'Full Year', NULL, NULL),
(18, 'Mathematics', 2, 'Full Year', NULL, NULL),
(19, 'Araling Panlipunan', 2, 'Full Year', NULL, NULL),
(20, 'MAPEH', 2, 'Full Year', NULL, NULL),
(21, 'Mother Tongue', 2, 'Full Year', NULL, NULL),
(22, 'ESP', 2, 'Full Year', NULL, NULL),
(23, 'Filipino', 3, 'Full Year', NULL, NULL),
(24, 'English', 3, 'Full Year', NULL, NULL),
(25, 'Science', 3, 'Full Year', NULL, NULL),
(26, 'Mathematics', 3, 'Full Year', NULL, NULL),
(27, 'Araling Panlipunan', 3, 'Full Year', NULL, NULL),
(28, 'MAPEH', 3, 'Full Year', NULL, NULL),
(29, 'Mother Tongue', 3, 'Full Year', NULL, NULL),
(30, 'ESP', 3, 'Full Year', NULL, NULL),
(31, 'Filipino', 4, 'Full Year', NULL, NULL),
(32, 'English', 4, 'Full Year', NULL, NULL),
(33, 'Science', 4, 'Full Year', NULL, NULL),
(34, 'Mathematics', 4, 'Full Year', NULL, NULL),
(35, 'Araling Panlipunan', 4, 'Full Year', NULL, NULL),
(36, 'MAPEH', 4, 'Full Year', NULL, NULL),
(37, 'EPP/TLE', 4, 'Full Year', NULL, NULL),
(38, 'GMRC', 4, 'Full Year', NULL, NULL),
(39, 'Filipino', 5, 'Full Year', NULL, NULL),
(40, 'English', 5, 'Full Year', NULL, NULL),
(41, 'Science', 5, 'Full Year', NULL, NULL),
(42, 'Mathematics', 5, 'Full Year', NULL, NULL),
(43, 'Araling Panlipunan', 5, 'Full Year', NULL, NULL),
(44, 'MAPEH', 5, 'Full Year', NULL, NULL),
(45, 'EPP/TLE', 5, 'Full Year', NULL, NULL),
(46, 'ESP', 5, 'Full Year', NULL, NULL),
(47, 'Filipino', 6, 'Full Year', NULL, NULL),
(48, 'English', 6, 'Full Year', NULL, NULL),
(49, 'Science', 6, 'Full Year', NULL, NULL),
(50, 'Mathematics', 6, 'Full Year', NULL, NULL),
(51, 'Araling Panlipunan', 6, 'Full Year', NULL, NULL),
(52, 'MAPEH', 6, 'Full Year', NULL, NULL),
(53, 'EPP/TLE', 6, 'Full Year', NULL, NULL),
(54, 'ESP', 6, 'Full Year', NULL, NULL),
(55, 'Filipino', 7, 'Full Year', NULL, NULL),
(56, 'English', 7, 'Full Year', NULL, NULL),
(57, 'Science', 7, 'Full Year', NULL, NULL),
(58, 'Mathematics', 7, 'Full Year', NULL, NULL),
(59, 'Araling Panlipunan', 7, 'Full Year', NULL, NULL),
(60, 'MAPEH', 7, 'Full Year', NULL, NULL),
(61, 'TLE', 7, 'Full Year', NULL, NULL),
(62, 'Values Ed', 7, 'Full Year', NULL, NULL),
(63, 'Filipino', 8, 'Full Year', NULL, NULL),
(64, 'English', 8, 'Full Year', NULL, NULL),
(65, 'Science', 8, 'Full Year', NULL, NULL),
(66, 'Mathematics', 8, 'Full Year', NULL, NULL),
(67, 'Araling Panlipunan', 8, 'Full Year', NULL, NULL),
(68, 'MAPEH', 8, 'Full Year', NULL, NULL),
(69, 'TLE', 8, 'Full Year', NULL, NULL),
(70, 'Values Ed', 8, 'Full Year', NULL, NULL),
(71, 'Filipino', 9, 'Full Year', NULL, NULL),
(72, 'English', 9, 'Full Year', NULL, NULL),
(73, 'Science', 9, 'Full Year', NULL, NULL),
(74, 'Mathematics', 9, 'Full Year', NULL, NULL),
(75, 'Araling Panlipunan', 9, 'Full Year', NULL, NULL),
(76, 'MAPEH', 9, 'Full Year', NULL, NULL),
(77, 'TLE', 9, 'Full Year', NULL, NULL),
(78, 'Values Ed', 9, 'Full Year', NULL, NULL),
(79, 'Filipino', 10, 'Full Year', NULL, NULL),
(80, 'English', 10, 'Full Year', NULL, NULL),
(81, 'Science', 10, 'Full Year', NULL, NULL),
(82, 'Mathematics', 10, 'Full Year', NULL, NULL),
(83, 'Araling Panlipunan', 10, 'Full Year', NULL, NULL),
(84, 'MAPEH', 10, 'Full Year', NULL, NULL),
(85, 'TLE', 10, 'Full Year', NULL, NULL),
(86, 'Values Ed', 10, 'Full Year', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subjects_archive`
--

CREATE TABLE `tbl_subjects_archive` (
  `archive_id` int(11) NOT NULL,
  `original_subject_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `year_level_id` int(11) DEFAULT NULL,
  `semester` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_subject_enrollments`
--

CREATE TABLE `tbl_subject_enrollments` (
  `enrollment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `lecture_units` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `lab_units` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `date_enrolled` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_subject_enrollments`
--

INSERT INTO `tbl_subject_enrollments` (`enrollment_id`, `student_id`, `subject_id`, `lecture_units`, `lab_units`, `date_enrolled`) VALUES
(1, 3, 11, 0, 0, '2025-10-25 00:26:52'),
(2, 3, 12, 0, 0, '2025-10-25 00:26:52'),
(3, 3, 13, 0, 0, '2025-10-25 00:26:52'),
(4, 3, 14, 0, 0, '2025-10-25 00:26:52'),
(5, 3, 15, 0, 0, '2025-10-25 00:26:52'),
(6, 4, 11, 0, 0, '2025-10-25 00:26:52'),
(7, 4, 12, 0, 0, '2025-10-25 00:26:52'),
(8, 4, 13, 0, 0, '2025-10-25 00:26:52'),
(9, 4, 14, 0, 0, '2025-10-25 00:26:52'),
(10, 4, 15, 0, 0, '2025-10-25 00:26:52'),
(11, 6, 11, 0, 0, '2025-10-25 00:26:52'),
(12, 6, 12, 0, 0, '2025-10-25 00:26:52'),
(13, 6, 13, 0, 0, '2025-10-25 00:26:52'),
(14, 6, 14, 0, 0, '2025-10-25 00:26:52'),
(15, 6, 15, 0, 0, '2025-10-25 00:26:52'),
(16, 5, 16, 0, 0, '2025-10-25 00:26:52'),
(17, 5, 17, 0, 0, '2025-10-25 00:26:52'),
(18, 5, 18, 0, 0, '2025-10-25 00:26:52'),
(19, 5, 19, 0, 0, '2025-10-25 00:26:52'),
(20, 5, 20, 0, 0, '2025-10-25 00:26:52'),
(21, 5, 21, 0, 0, '2025-10-25 00:26:52'),
(22, 5, 22, 0, 0, '2025-10-25 00:26:52'),
(43, 7, 23, 0, 0, '2025-10-25 00:33:01'),
(44, 7, 24, 0, 0, '2025-10-25 00:33:01'),
(45, 7, 25, 0, 0, '2025-10-25 00:33:01'),
(46, 7, 26, 0, 0, '2025-10-25 00:33:01'),
(47, 7, 27, 0, 0, '2025-10-25 00:33:01'),
(48, 7, 28, 0, 0, '2025-10-25 00:33:01'),
(49, 7, 29, 0, 0, '2025-10-25 00:33:01'),
(50, 7, 30, 0, 0, '2025-10-25 00:33:01');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teachers_archive`
--

CREATE TABLE `tbl_teachers_archive` (
  `archive_id` int(11) NOT NULL,
  `original_teacher_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teacher_activities`
--

CREATE TABLE `tbl_teacher_activities` (
  `activity_id` int(11) NOT NULL,
  `teacher_account_id` int(10) UNSIGNED NOT NULL,
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED DEFAULT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `activity_type` enum('assignment','activity','reviewer','announcement') DEFAULT 'assignment',
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `points` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_teacher_assignments`
--

CREATE TABLE `tbl_teacher_assignments` (
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `teacher_account_id` int(10) UNSIGNED NOT NULL,
  `subject_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_teacher_assignments`
--

INSERT INTO `tbl_teacher_assignments` (`assignment_id`, `teacher_account_id`, `subject_id`, `section_id`, `created_at`) VALUES
(4, 14, 28, 6, '2025-10-25 00:31:34'),
(5, 14, 24, 6, '2025-10-25 00:46:11');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_yearlevels`
--

CREATE TABLE `tbl_yearlevels` (
  `year_level_id` int(10) UNSIGNED NOT NULL,
  `level_name` varchar(30) NOT NULL,
  `education_stage` enum('Elementary','Junior High','Senior High') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_yearlevels`
--

INSERT INTO `tbl_yearlevels` (`year_level_id`, `level_name`, `education_stage`) VALUES
(1, 'Grade 1', 'Elementary'),
(10, 'Grade 10', 'Junior High'),
(11, 'Grade 11', 'Senior High'),
(12, 'Grade 12', 'Senior High'),
(2, 'Grade 2', 'Elementary'),
(3, 'Grade 3', 'Elementary'),
(4, 'Grade 4', 'Elementary'),
(5, 'Grade 5', 'Elementary'),
(6, 'Grade 6', 'Elementary'),
(7, 'Grade 7', 'Junior High'),
(8, 'Grade 8', 'Junior High'),
(9, 'Grade 9', 'Junior High');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  ADD PRIMARY KEY (`account_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `tbl_accounts_archive`
--
ALTER TABLE `tbl_accounts_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_account_id` (`original_account_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_activity_comments`
--
ALTER TABLE `tbl_activity_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `activity_id` (`activity_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `fk_comment_grader` (`graded_by`);

--
-- Indexes for table `tbl_audit_logs`
--
ALTER TABLE `tbl_audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `tbl_career_results`
--
ALTER TABLE `tbl_career_results`
  ADD PRIMARY KEY (`result_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Indexes for table `tbl_career_tests`
--
ALTER TABLE `tbl_career_tests`
  ADD PRIMARY KEY (`test_id`);

--
-- Indexes for table `tbl_feedbacks`
--
ALTER TABLE `tbl_feedbacks`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `tbl_grades`
--
ALTER TABLE `tbl_grades`
  ADD PRIMARY KEY (`grade_id`),
  ADD UNIQUE KEY `uk_student_subject_period_component` (`student_id`,`subject_id`,`grading_period`,`remarks`),
  ADD KEY `idx_student_subject` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_account_id` (`teacher_account_id`),
  ADD KEY `fk_grades_quarter_new` (`quarter_id`);

--
-- Indexes for table `tbl_grades_archive`
--
ALTER TABLE `tbl_grades_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_grade_id` (`original_grade_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_parents`
--
ALTER TABLE `tbl_parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `uk_account_parent` (`account_id`);

--
-- Indexes for table `tbl_parents_archive`
--
ALTER TABLE `tbl_parents_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_parent_id` (`original_parent_id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_parent_student`
--
ALTER TABLE `tbl_parent_student`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_parent_student` (`parent_id`,`student_id`),
  ADD KEY `fk_ps_student` (`student_id`);

--
-- Indexes for table `tbl_quarters`
--
ALTER TABLE `tbl_quarters`
  ADD PRIMARY KEY (`quarter_id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Indexes for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  ADD PRIMARY KEY (`score_id`),
  ADD UNIQUE KEY `uk_student_subject` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `uk_year_section` (`year_level_id`,`section_name`),
  ADD KEY `fk_sections_strand` (`strand_id`);

--
-- Indexes for table `tbl_semester`
--
ALTER TABLE `tbl_semester`
  ADD PRIMARY KEY (`semester_id`);

--
-- Indexes for table `tbl_semesters`
--
ALTER TABLE `tbl_semesters`
  ADD PRIMARY KEY (`semester_id`);

--
-- Indexes for table `tbl_spreadsheets`
--
ALTER TABLE `tbl_spreadsheets`
  ADD PRIMARY KEY (`sheet_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `tbl_spreadsheet_columns`
--
ALTER TABLE `tbl_spreadsheet_columns`
  ADD PRIMARY KEY (`column_id`),
  ADD KEY `sheet_id` (`sheet_id`);

--
-- Indexes for table `tbl_spreadsheet_scores`
--
ALTER TABLE `tbl_spreadsheet_scores`
  ADD PRIMARY KEY (`score_id`),
  ADD KEY `sheet_id` (`sheet_id`),
  ADD KEY `column_id` (`column_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `tbl_strands`
--
ALTER TABLE `tbl_strands`
  ADD PRIMARY KEY (`strand_id`),
  ADD UNIQUE KEY `uk_strand_name` (`strand_name`),
  ADD UNIQUE KEY `uk_strand_code` (`strand_code`);

--
-- Indexes for table `tbl_students`
--
ALTER TABLE `tbl_students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `uk_account_student` (`account_id`),
  ADD KEY `year_level_id` (`year_level_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `tbl_students_archive`
--
ALTER TABLE `tbl_students_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_student_id` (`original_student_id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_subjects`
--
ALTER TABLE `tbl_subjects`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `uk_subject_year` (`subject_name`,`year_level_id`),
  ADD KEY `year_level_id` (`year_level_id`),
  ADD KEY `fk_subjects_semester` (`semester_id`),
  ADD KEY `fk_subject_strand` (`strand_id`);

--
-- Indexes for table `tbl_subjects_archive`
--
ALTER TABLE `tbl_subjects_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_subject_id` (`original_subject_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_subject_enrollments`
--
ALTER TABLE `tbl_subject_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `uk_student_subject` (`student_id`,`subject_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `tbl_teachers_archive`
--
ALTER TABLE `tbl_teachers_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `idx_original_teacher_id` (`original_teacher_id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `tbl_teacher_activities`
--
ALTER TABLE `tbl_teacher_activities`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `teacher_account_id` (`teacher_account_id`),
  ADD KEY `year_level_id` (`year_level_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `fk_subject` (`subject_id`);

--
-- Indexes for table `tbl_teacher_assignments`
--
ALTER TABLE `tbl_teacher_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uk_teacher_subject_section` (`teacher_account_id`,`subject_id`,`section_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `tbl_yearlevels`
--
ALTER TABLE `tbl_yearlevels`
  ADD PRIMARY KEY (`year_level_id`),
  ADD UNIQUE KEY `level_name` (`level_name`,`education_stage`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_accounts`
--
ALTER TABLE `tbl_accounts`
  MODIFY `account_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_accounts_archive`
--
ALTER TABLE `tbl_accounts_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_activity_comments`
--
ALTER TABLE `tbl_activity_comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_audit_logs`
--
ALTER TABLE `tbl_audit_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_career_results`
--
ALTER TABLE `tbl_career_results`
  MODIFY `result_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_career_tests`
--
ALTER TABLE `tbl_career_tests`
  MODIFY `test_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_feedbacks`
--
ALTER TABLE `tbl_feedbacks`
  MODIFY `feedback_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_grades`
--
ALTER TABLE `tbl_grades`
  MODIFY `grade_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_grades_archive`
--
ALTER TABLE `tbl_grades_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_parents`
--
ALTER TABLE `tbl_parents`
  MODIFY `parent_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_parents_archive`
--
ALTER TABLE `tbl_parents_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_parent_student`
--
ALTER TABLE `tbl_parent_student`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_quarters`
--
ALTER TABLE `tbl_quarters`
  MODIFY `quarter_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  MODIFY `score_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  MODIFY `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `tbl_semester`
--
ALTER TABLE `tbl_semester`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tbl_semesters`
--
ALTER TABLE `tbl_semesters`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_spreadsheets`
--
ALTER TABLE `tbl_spreadsheets`
  MODIFY `sheet_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_spreadsheet_columns`
--
ALTER TABLE `tbl_spreadsheet_columns`
  MODIFY `column_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_spreadsheet_scores`
--
ALTER TABLE `tbl_spreadsheet_scores`
  MODIFY `score_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_strands`
--
ALTER TABLE `tbl_strands`
  MODIFY `strand_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tbl_students`
--
ALTER TABLE `tbl_students`
  MODIFY `student_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_students_archive`
--
ALTER TABLE `tbl_students_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_subjects`
--
ALTER TABLE `tbl_subjects`
  MODIFY `subject_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT for table `tbl_subjects_archive`
--
ALTER TABLE `tbl_subjects_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_subject_enrollments`
--
ALTER TABLE `tbl_subject_enrollments`
  MODIFY `enrollment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `tbl_teachers_archive`
--
ALTER TABLE `tbl_teachers_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_teacher_activities`
--
ALTER TABLE `tbl_teacher_activities`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_teacher_assignments`
--
ALTER TABLE `tbl_teacher_assignments`
  MODIFY `assignment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tbl_yearlevels`
--
ALTER TABLE `tbl_yearlevels`
  MODIFY `year_level_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_activity_comments`
--
ALTER TABLE `tbl_activity_comments`
  ADD CONSTRAINT `fk_comment_grader` FOREIGN KEY (`graded_by`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_activity_comments_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `tbl_teacher_activities` (`activity_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_activity_comments_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_activity_comments_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_audit_logs`
--
ALTER TABLE `tbl_audit_logs`
  ADD CONSTRAINT `tbl_audit_logs_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_career_results`
--
ALTER TABLE `tbl_career_results`
  ADD CONSTRAINT `tbl_career_results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_career_results_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tbl_career_tests` (`test_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_feedbacks`
--
ALTER TABLE `tbl_feedbacks`
  ADD CONSTRAINT `tbl_feedbacks_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE SET NULL;

--
-- Constraints for table `tbl_grades`
--
ALTER TABLE `tbl_grades`
  ADD CONSTRAINT `fk_grades_quarter_new` FOREIGN KEY (`quarter_id`) REFERENCES `tbl_quarters` (`quarter_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_grades_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_grades_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_grades_ibfk_3` FOREIGN KEY (`teacher_account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_parents`
--
ALTER TABLE `tbl_parents`
  ADD CONSTRAINT `tbl_parents_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_parent_student`
--
ALTER TABLE `tbl_parent_student`
  ADD CONSTRAINT `fk_ps_parent` FOREIGN KEY (`parent_id`) REFERENCES `tbl_parents` (`parent_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ps_student` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_quarters`
--
ALTER TABLE `tbl_quarters`
  ADD CONSTRAINT `tbl_quarters_ibfk_1` FOREIGN KEY (`semester_id`) REFERENCES `tbl_semesters` (`semester_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_scores`
--
ALTER TABLE `tbl_scores`
  ADD CONSTRAINT `tbl_scores_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_scores_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_sections`
--
ALTER TABLE `tbl_sections`
  ADD CONSTRAINT `fk_sections_strand` FOREIGN KEY (`strand_id`) REFERENCES `tbl_strands` (`strand_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_sections_ibfk_1` FOREIGN KEY (`year_level_id`) REFERENCES `tbl_yearlevels` (`year_level_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_spreadsheets`
--
ALTER TABLE `tbl_spreadsheets`
  ADD CONSTRAINT `tbl_spreadsheets_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`),
  ADD CONSTRAINT `tbl_spreadsheets_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `tbl_accounts` (`account_id`);

--
-- Constraints for table `tbl_spreadsheet_columns`
--
ALTER TABLE `tbl_spreadsheet_columns`
  ADD CONSTRAINT `tbl_spreadsheet_columns_ibfk_1` FOREIGN KEY (`sheet_id`) REFERENCES `tbl_spreadsheets` (`sheet_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_spreadsheet_scores`
--
ALTER TABLE `tbl_spreadsheet_scores`
  ADD CONSTRAINT `tbl_spreadsheet_scores_ibfk_1` FOREIGN KEY (`sheet_id`) REFERENCES `tbl_spreadsheets` (`sheet_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_spreadsheet_scores_ibfk_2` FOREIGN KEY (`column_id`) REFERENCES `tbl_spreadsheet_columns` (`column_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_spreadsheet_scores_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_subjects`
--
ALTER TABLE `tbl_subjects`
  ADD CONSTRAINT `fk_subject_strand` FOREIGN KEY (`strand_id`) REFERENCES `tbl_strands` (`strand_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_subjects_semester` FOREIGN KEY (`semester_id`) REFERENCES `tbl_semesters` (`semester_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_subjects_ibfk_1` FOREIGN KEY (`year_level_id`) REFERENCES `tbl_yearlevels` (`year_level_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_subject_enrollments`
--
ALTER TABLE `tbl_subject_enrollments`
  ADD CONSTRAINT `tbl_subject_enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `tbl_students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_subject_enrollments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_teacher_activities`
--
ALTER TABLE `tbl_teacher_activities`
  ADD CONSTRAINT `fk_subject` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_teacher_activities_ibfk_1` FOREIGN KEY (`teacher_account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_teacher_activities_ibfk_2` FOREIGN KEY (`year_level_id`) REFERENCES `tbl_yearlevels` (`year_level_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tbl_teacher_activities_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `tbl_sections` (`section_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tbl_teacher_activities_ibfk_4` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_teacher_assignments`
--
ALTER TABLE `tbl_teacher_assignments`
  ADD CONSTRAINT `tbl_teacher_assignments_ibfk_1` FOREIGN KEY (`teacher_account_id`) REFERENCES `tbl_accounts` (`account_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_teacher_assignments_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `tbl_subjects` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_teacher_assignments_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `tbl_sections` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
