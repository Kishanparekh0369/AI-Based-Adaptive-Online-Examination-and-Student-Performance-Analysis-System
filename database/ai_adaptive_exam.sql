-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 08, 2026 at 12:46 PM
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
-- Database: `ai_adaptive_exam`
--

-- --------------------------------------------------------

--
-- Table structure for table `adaptive_answers`
--

CREATE TABLE `adaptive_answers` (
  `id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `student_answer` char(1) DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `time_taken_seconds` int(11) DEFAULT 0,
  `difficulty_at_time` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adaptive_answers`
--

INSERT INTO `adaptive_answers` (`id`, `session_id`, `question_id`, `student_answer`, `is_correct`, `time_taken_seconds`, `difficulty_at_time`) VALUES
(13, 2, 17, 'A', 1, 60, 'Medium'),
(14, 2, 21, 'B', 0, 63, 'Hard'),
(15, 2, 18, 'B', 1, 65, 'Medium'),
(16, 2, 22, 'C', 0, 68, 'Hard'),
(17, 2, 19, 'C', 0, 71, 'Medium'),
(18, 2, 13, 'A', 0, 76, 'Easy'),
(19, 2, 14, 'C', 1, 79, 'Easy'),
(20, 2, 20, 'B', 1, 81, 'Medium'),
(21, 2, 23, 'D', 0, 84, 'Hard'),
(22, 2, 15, 'B', 1, 86, 'Easy'),
(23, 2, 16, 'B', 1, 93, 'Easy'),
(24, 2, 24, 'D', 0, 96, 'Hard'),
(25, 3, 28, 'A', 1, 16, 'Medium'),
(26, 3, 29, 'C', 1, 43, 'Hard'),
(27, 3, 32, 'B', 1, 47, 'Hard'),
(28, 3, 35, 'C', 0, 55, 'Hard'),
(29, 3, 31, 'C', 1, 57, 'Medium'),
(30, 3, 38, 'A', 0, 60, 'Hard'),
(31, 3, 34, 'B', 1, 62, 'Medium'),
(32, 3, 37, 'C', 0, 65, 'Medium'),
(33, 3, 27, 'B', 0, 68, 'Easy'),
(34, 3, 30, 'A', 0, 77, 'Easy'),
(35, 3, 33, 'A', 0, 89, 'Easy'),
(36, 3, 36, 'B', 1, 100, 'Easy'),
(49, 6, 17, 'A', 1, 4, 'Medium'),
(50, 6, 21, 'B', 0, 7, 'Hard'),
(51, 6, 18, 'B', 1, 17, 'Medium'),
(52, 6, 22, 'C', 0, 32, 'Hard'),
(53, 6, 19, 'B', 0, 36, 'Medium'),
(54, 6, 13, 'D', 0, 38, 'Easy'),
(55, 6, 14, 'A', 0, 43, 'Easy'),
(56, 6, 15, 'B', 1, 46, 'Easy'),
(57, 6, 20, 'B', 1, 49, 'Medium'),
(58, 6, 23, 'D', 0, 51, 'Hard'),
(59, 6, 16, 'B', 1, 54, 'Easy'),
(60, 6, 24, 'C', 1, 57, 'Hard'),
(61, 7, 17, 'A', 1, 4, 'Medium'),
(62, 7, 21, 'D', 0, 7, 'Hard'),
(63, 7, 18, 'B', 1, 9, 'Medium'),
(64, 7, 22, 'C', 0, 12, 'Hard'),
(65, 7, 19, 'C', 0, 15, 'Medium'),
(66, 7, 13, 'A', 0, 18, 'Easy'),
(67, 7, 14, 'C', 1, 21, 'Easy'),
(68, 7, 20, 'B', 1, 24, 'Medium'),
(69, 7, 23, 'D', 0, 26, 'Hard'),
(70, 7, 15, 'B', 1, 28, 'Easy'),
(71, 7, 16, 'B', 1, 37, 'Easy'),
(72, 7, 24, 'D', 0, 40, 'Hard'),
(73, 8, 28, 'D', 0, 5, 'Medium'),
(74, 8, 27, 'C', 0, 16, 'Easy'),
(75, 8, 30, 'C', 0, 23, 'Easy'),
(76, 8, 33, 'B', 0, 26, 'Easy'),
(77, 8, 36, 'D', 0, 31, 'Easy'),
(78, 8, 31, 'D', 0, 34, 'Medium'),
(79, 8, 34, 'C', 0, 43, 'Medium'),
(80, 8, 37, 'D', 0, 48, 'Medium'),
(81, 8, 29, 'C', 1, 59, 'Hard'),
(82, 8, 32, 'D', 0, 68, 'Hard'),
(83, 8, 35, 'C', 0, 82, 'Hard'),
(84, 8, 38, 'D', 0, 95, 'Hard'),
(85, 9, 17, 'A', 1, 13, 'Medium'),
(86, 9, 21, 'B', 0, 16, 'Hard'),
(87, 9, 18, 'D', 0, 18, 'Medium'),
(88, 9, 13, 'D', 0, 19, 'Easy'),
(89, 9, 14, 'C', 1, 23, 'Easy'),
(90, 9, 19, 'D', 0, 24, 'Medium'),
(91, 9, 15, 'C', 0, 26, 'Easy'),
(92, 9, 16, 'C', 0, 28, 'Easy'),
(93, 9, 20, 'C', 0, 29, 'Medium'),
(94, 9, 22, 'C', 0, 31, 'Hard'),
(95, 9, 23, 'C', 1, 33, 'Hard'),
(96, 9, 24, 'B', 0, 36, 'Hard'),
(97, 13, 40, 'A', 0, 43, 'Medium'),
(98, 15, 28, 'B', 0, 613, 'Medium'),
(99, 17, 40, 'C', 1, 385, 'Medium'),
(100, 17, 41, 'B', 1, 390, 'Hard'),
(101, 17, 44, 'D', 0, 397, 'Hard'),
(102, 17, 43, 'A', 0, 442, 'Medium'),
(103, 17, 39, 'B', 1, 444, 'Easy'),
(104, 17, 46, 'B', 1, 448, 'Medium'),
(105, 17, 47, 'C', 1, 453, 'Hard'),
(106, 17, 50, 'D', 0, 456, 'Hard'),
(107, 17, 49, 'B', 1, 459, 'Medium'),
(108, 17, 42, 'A', 1, 462, 'Easy'),
(109, 17, 45, 'B', 1, 464, 'Easy'),
(110, 17, 48, 'B', 1, 486, 'Easy'),
(111, 19, 17, 'A', 1, 30, 'Medium'),
(112, 19, 21, 'B', 0, 33, 'Hard'),
(113, 19, 18, 'B', 1, 36, 'Medium'),
(114, 19, 22, 'C', 0, 39, 'Hard'),
(115, 19, 19, 'C', 0, 41, 'Medium'),
(116, 19, 13, 'A', 0, 47, 'Easy'),
(117, 19, 14, 'C', 1, 57, 'Easy'),
(118, 19, 20, 'B', 1, 95, 'Medium'),
(119, 19, 23, 'D', 0, 98, 'Hard'),
(120, 19, 15, 'B', 1, 100, 'Easy'),
(121, 19, 16, 'B', 1, 102, 'Easy'),
(122, 19, 24, 'C', 1, 106, 'Hard');

-- --------------------------------------------------------

--
-- Table structure for table `adaptive_sessions`
--

CREATE TABLE `adaptive_sessions` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `theta_value` decimal(5,2) DEFAULT 0.00,
  `current_difficulty` varchar(20) DEFAULT 'Medium',
  `questions_asked` int(11) DEFAULT 0,
  `correct_count` int(11) DEFAULT 0,
  `wrong_count` int(11) DEFAULT 0,
  `is_completed` tinyint(1) DEFAULT 0,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adaptive_sessions`
--

INSERT INTO `adaptive_sessions` (`id`, `student_id`, `exam_id`, `theta_value`, `current_difficulty`, `questions_asked`, `correct_count`, `wrong_count`, `is_completed`, `started_at`) VALUES
(2, 1, 2, -0.78, 'ANY', 12, 6, 6, 1, '2026-07-27 14:32:12'),
(3, 1, 4, 0.28, 'ANY', 12, 6, 6, 1, '2026-07-27 14:52:59'),
(6, 3, 2, -0.21, 'ANY', 12, 6, 6, 1, '2026-07-30 17:03:38'),
(7, 7, 2, -0.78, 'ANY', 12, 6, 6, 1, '2026-07-31 06:33:51'),
(8, 7, 4, -1.88, 'ANY', 12, 1, 11, 1, '2026-07-31 06:38:38'),
(9, 2, 2, -1.31, 'ANY', 12, 3, 9, 1, '2026-08-01 10:20:12'),
(10, 1, 5, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-04 07:47:06'),
(11, 2, 4, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-04 07:48:39'),
(12, 2, 5, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-09 16:25:15'),
(13, 7, 5, -0.19, 'Easy', 1, 0, 1, 0, '2026-08-09 16:53:50'),
(14, 6, 2, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-09 17:10:54'),
(15, 6, 4, -0.19, 'Easy', 1, 0, 1, 1, '2026-08-09 17:20:18'),
(16, 6, 5, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-09 17:34:37'),
(17, 8, 5, 0.59, 'ANY', 12, 9, 3, 1, '2026-08-10 10:52:39'),
(18, 8, 2, 0.00, 'Medium', 0, 0, 0, 0, '2026-08-10 11:09:04'),
(19, 5, 2, -0.10, 'ANY', 12, 7, 5, 1, '2026-09-08 10:16:44'),
(20, 5, 4, 0.00, 'Medium', 0, 0, 0, 1, '2026-09-08 10:28:54');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `name`, `email`, `password`) VALUES
(1, 'Admin', 'admin@gmail.com', 'admin123');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date DEFAULT NULL,
  `time` time DEFAULT NULL,
  `status` varchar(20) DEFAULT 'Present',
  `photo_path` varchar(255) NOT NULL,
  `matched` tinyint(1) DEFAULT 0,
  `similarity_score` decimal(5,2) DEFAULT 0.00,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `reply` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `exam_title` varchar(150) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `duration` int(11) NOT NULL,
  `total_marks` int(11) NOT NULL,
  `pass_marks` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `exam_mode` varchar(20) DEFAULT 'Standard',
  `max_questions` int(11) DEFAULT 10
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `exam_title`, `subject_id`, `exam_date`, `duration`, `total_marks`, `pass_marks`, `status`, `exam_mode`, `max_questions`) VALUES
(2, 'PHP', 2, '2026-07-27', 10, 12, 2, 'Active', 'Adaptive', 12),
(3, 'C#', 4, '2026-07-27', 5, 1, 1, 'Active', 'Standard', 10),
(4, 'python', 5, '2026-07-27', 10, 12, 2, 'Active', 'Adaptive', 12),
(5, 'AI in Computer IT', 3, '2026-08-04', 10, 12, 3, 'Active', 'Adaptive', 12);

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempt_blocks`
--

CREATE TABLE `exam_attempt_blocks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `reason` varchar(80) NOT NULL DEFAULT 'Navigation exit',
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_attempt_blocks`
--

INSERT INTO `exam_attempt_blocks` (`id`, `student_id`, `exam_id`, `session_id`, `reason`, `blocked_at`) VALUES
(1, 2, 3, NULL, 'Fullscreen exit', '2026-08-01 10:34:16'),
(2, 1, 5, 10, 'Fullscreen exit', '2026-08-04 07:47:17'),
(3, 2, 4, 11, 'Fullscreen exit', '2026-08-04 07:52:05'),
(4, 2, 5, 12, 'Fullscreen exit', '2026-08-09 16:26:27'),
(5, 7, 5, 13, 'Fullscreen exit', '2026-08-09 16:54:52'),
(6, 6, 2, 14, 'Fullscreen exit', '2026-08-09 17:18:39'),
(7, 6, 5, 16, 'Fullscreen exit', '2026-08-09 17:35:34'),
(8, 8, 2, 18, 'Fullscreen exit', '2026-08-10 11:10:03');

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_id`) VALUES
(13, 2, 13),
(14, 2, 14),
(15, 2, 15),
(16, 2, 16),
(17, 2, 17),
(18, 2, 18),
(19, 2, 19),
(20, 2, 20),
(21, 2, 21),
(22, 2, 22),
(23, 2, 23),
(24, 2, 24),
(25, 3, 25),
(26, 4, 27),
(27, 4, 28),
(28, 4, 29),
(29, 4, 30),
(30, 4, 31),
(31, 4, 32),
(32, 4, 33),
(33, 4, 34),
(34, 4, 35),
(35, 4, 36),
(36, 4, 37),
(37, 4, 38),
(50, 5, 39),
(51, 5, 40),
(52, 5, 41),
(53, 5, 42),
(54, 5, 43),
(55, 5, 44),
(56, 5, 45),
(57, 5, 46),
(58, 5, 47),
(59, 5, 48),
(60, 5, 49),
(61, 5, 50);

-- --------------------------------------------------------

--
-- Table structure for table `face_verifications`
--

CREATE TABLE `face_verifications` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `verification_type` enum('registration','attendance','exam','profile_update') NOT NULL DEFAULT 'attendance',
  `captured_image` varchar(255) NOT NULL,
  `similarity_score` decimal(5,2) DEFAULT 0.00,
  `quality_score` decimal(5,2) DEFAULT 0.00,
  `status` enum('success','failed') NOT NULL DEFAULT 'failed',
  `failure_reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`id`, `name`, `email`, `phone`, `password`, `profile_image`, `status`, `last_login`, `created_at`) VALUES
(1, 'cfdfr', 'admin@gmail.com', '4484', '$2y$10$5LlDgNxgLi.Z0mgCukBwqu.sP092D.NKc69jjW7X7LJQEE7ZP7I0K', NULL, 'Active', '2026-08-28 06:08:55', '2026-08-28 06:08:00');

-- --------------------------------------------------------

--
-- Table structure for table `faculty_subjects`
--

CREATE TABLE `faculty_subjects` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_subjects`
--

INSERT INTO `faculty_subjects` (`id`, `faculty_id`, `subject_id`, `created_at`) VALUES
(2, 1, 1, '2026-08-28 06:08:25');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `rating` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `student_id`, `message`, `rating`, `created_at`) VALUES
(1, 1, 'Nice Website', 5, '2026-07-27 10:37:08');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_type` varchar(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT '#',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_type`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 'admin', NULL, 'New Student Added', 'kishan was added by admin.', 'students.php', 1, '2026-07-27 05:57:32'),
(2, 'student', 1, 'Welcome', 'Your student account is ready.', 'dashboard.php', 1, '2026-07-27 05:57:32'),
(3, 'admin', NULL, 'Subject Added', 'C was added successfully.', 'subjects.php', 1, '2026-07-27 05:59:46'),
(4, 'admin', NULL, 'New Student Added', 'Dev was added by admin.', 'students.php', 1, '2026-07-27 10:30:48'),
(5, 'student', 2, 'Welcome', 'Your student account is ready.', 'dashboard.php', 1, '2026-07-27 10:30:48'),
(6, 'admin', NULL, 'New Student Added', 'Ayush was added by admin.', 'students.php', 1, '2026-07-27 10:31:18'),
(7, 'student', 3, 'Welcome', 'Your student account is ready.', 'dashboard.php', 1, '2026-07-27 10:31:18'),
(8, 'admin', NULL, 'New Student Added', 'Nitya was added by admin.', 'students.php', 1, '2026-07-27 10:31:59'),
(9, 'student', 4, 'Welcome', 'Your student account is ready.', 'dashboard.php', 0, '2026-07-27 10:31:59'),
(10, 'admin', NULL, 'Subject Added', 'PHP was added successfully.', 'subjects.php', 1, '2026-07-27 10:32:18'),
(11, 'admin', NULL, 'Subject Added', 'AI was added successfully.', 'subjects.php', 1, '2026-07-27 10:32:32'),
(12, 'student', NULL, 'New Exam Created', 'C Progaraming is available for students.', 'exams.php', 1, '2026-07-27 10:33:37'),
(13, 'admin', NULL, 'New Exam Created', 'C Progaraming was created successfully.', 'exams.php', 1, '2026-07-27 10:33:37'),
(14, 'student', 1, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-27 10:35:52'),
(15, 'admin', NULL, 'Adaptive Exam Completed', 'kishan completed an adaptive exam.', 'results.php', 1, '2026-07-27 10:35:52'),
(16, 'admin', NULL, 'Feedback Submitted', 'kishan submitted feedback.', 'feedback.php', 1, '2026-07-27 10:37:08'),
(17, 'student', 1, 'Feedback Sent', 'Your feedback was submitted successfully.', 'feedback.php', 1, '2026-07-27 10:37:08'),
(18, 'student', NULL, 'New Exam Created', 'PHP is available for students.', 'exams.php', 1, '2026-07-27 11:36:08'),
(19, 'admin', NULL, 'New Exam Created', 'PHP was created successfully.', 'exams.php', 1, '2026-07-27 11:36:08'),
(20, 'admin', NULL, 'Subject Added', 'C# was added successfully.', 'subjects.php', 1, '2026-07-27 11:48:11'),
(21, 'admin', NULL, 'Question Added', 'A new question was added to the question bank.', 'questions.php', 1, '2026-07-27 11:49:25'),
(22, 'admin', NULL, 'Question Added', 'A new question was added to the question bank.', 'questions.php', 1, '2026-07-27 11:50:45'),
(23, 'student', NULL, 'New Exam Created', 'C# is available for students.', 'exams.php', 1, '2026-07-27 11:51:29'),
(24, 'admin', NULL, 'New Exam Created', 'C# was created successfully.', 'exams.php', 1, '2026-07-27 11:51:29'),
(25, 'student', 1, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-27 14:33:54'),
(26, 'admin', NULL, 'Adaptive Exam Completed', 'kishan completed an adaptive exam.', 'results.php', 1, '2026-07-27 14:33:54'),
(27, 'admin', NULL, 'Subject Added', 'python was added successfully.', 'subjects.php', 1, '2026-07-27 14:49:58'),
(28, 'student', NULL, 'New Exam Created', 'python is available for students.', 'exams.php', 1, '2026-07-27 14:52:07'),
(29, 'admin', NULL, 'New Exam Created', 'python was created successfully.', 'exams.php', 1, '2026-07-27 14:52:07'),
(30, 'student', 1, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-27 14:54:48'),
(31, 'admin', NULL, 'Adaptive Exam Completed', 'kishan completed an adaptive exam.', 'results.php', 1, '2026-07-27 14:54:48'),
(32, 'student', 1, 'Login', 'You logged into your account.', 'dashboard.php', 1, '2026-07-27 15:09:35'),
(33, 'student', 1, 'Exam Completed', 'Your exam has been submitted and the result is ready.', 'result.php', 1, '2026-07-27 15:10:12'),
(34, 'admin', NULL, 'Exam Completed', 'kishan submitted an exam.', 'results.php', 1, '2026-07-27 15:10:12'),
(35, 'student', 2, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-27 15:12:34'),
(36, 'admin', NULL, 'Adaptive Exam Completed', 'Dev completed an adaptive exam.', 'results.php', 1, '2026-07-27 15:12:34'),
(37, 'student', 3, 'Login', 'You logged into your account.', 'dashboard.php', 1, '2026-07-30 16:59:37'),
(38, 'student', 3, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 0, '2026-07-30 17:04:40'),
(39, 'admin', NULL, 'Adaptive Exam Completed', 'Ayush completed an adaptive exam.', 'results.php', 1, '2026-07-30 17:04:40'),
(40, 'student', 6, 'Login', 'You logged into your account.', 'dashboard.php', 1, '2026-07-30 17:14:59'),
(41, 'student', 1, 'Profile Updated', 'Your student profile was updated by admin.', 'account_settings.php', 1, '2026-07-30 17:16:06'),
(42, 'student', 4, 'Profile Updated', 'Your student profile was updated by admin.', 'account_settings.php', 0, '2026-07-31 06:17:29'),
(43, 'student', 2, 'Profile Updated', 'Your student profile was updated by admin.', 'account_settings.php', 1, '2026-07-31 06:17:57'),
(44, 'student', 7, 'Login', 'You logged into your account.', 'dashboard.php', 1, '2026-07-31 06:33:16'),
(45, 'student', 7, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-31 06:34:50'),
(46, 'admin', NULL, 'Adaptive Exam Completed', 'jj completed an adaptive exam.', 'results.php', 1, '2026-07-31 06:34:50'),
(47, 'student', 7, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-07-31 06:40:19'),
(48, 'admin', NULL, 'Adaptive Exam Completed', 'jj completed an adaptive exam.', 'results.php', 1, '2026-07-31 06:40:19'),
(49, 'student', 1, 'Login', 'You logged into your account.', 'dashboard.php', 1, '2026-08-01 07:30:07'),
(50, 'student', 2, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 1, '2026-08-01 10:20:54'),
(51, 'admin', NULL, 'Adaptive Exam Completed', 'Dev completed an adaptive exam.', 'results.php', 1, '2026-08-01 10:20:54'),
(52, 'student', 7, 'Exam Completed', 'Your exam has been submitted and the result is ready.', 'result.php', 1, '2026-08-01 10:36:29'),
(53, 'admin', NULL, 'Exam Completed', 'jj submitted an exam.', 'results.php', 1, '2026-08-01 10:36:29'),
(54, 'student', NULL, 'New Exam Created', 'AI in Computer IT is available for students.', 'exams.php', 1, '2026-08-04 07:46:57'),
(55, 'admin', NULL, 'New Exam Created', 'AI in Computer IT was created successfully.', 'exams.php', 1, '2026-08-04 07:46:57'),
(56, 'student', 4, 'Profile Updated', 'Your student profile was updated by admin.', 'account_settings.php', 0, '2026-08-09 16:23:12'),
(57, 'student', 6, 'Exam Completed', 'Your exam has been submitted and the result is ready.', 'result.php', 1, '2026-08-09 17:19:19'),
(58, 'admin', NULL, 'Exam Completed', 'priyesh patel submitted an exam.', 'results.php', 1, '2026-08-09 17:19:19'),
(59, 'student', 6, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 0, '2026-08-09 17:30:34'),
(60, 'admin', NULL, 'Adaptive Exam Completed', 'priyesh patel completed an adaptive exam.', 'results.php', 1, '2026-08-09 17:30:34'),
(61, 'student', 8, 'Login', 'You logged into your account.', 'dashboard.php', 0, '2026-08-10 10:51:56'),
(62, 'student', 8, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 0, '2026-08-10 11:00:51'),
(63, 'admin', NULL, 'Adaptive Exam Completed', 'vraj completed an adaptive exam.', 'results.php', 1, '2026-08-10 11:00:51'),
(64, 'admin', NULL, 'Subject Added', 'ML was added successfully.', 'subjects.php', 0, '2026-08-22 10:06:49'),
(65, 'admin', NULL, 'Subject Added', 'VB.net was added successfully.', 'subjects.php', 0, '2026-08-22 10:25:52'),
(66, 'admin', NULL, 'Subject Added', 'logical reasoning was added successfully.', 'subjects.php', 0, '2026-08-22 16:58:20'),
(67, 'admin', NULL, 'Faculty Added', 'cfdfr was added successfully.', 'faculty.php', 0, '2026-08-28 06:08:00'),
(68, 'faculty', 1, 'Subject Assigned', 'A subject assignment was updated by admin.', 'subjects.php', 0, '2026-08-28 06:08:00'),
(69, 'faculty', 1, 'Subject Assigned', 'A subject assignment was updated by admin.', 'subjects.php', 0, '2026-08-28 06:08:25'),
(70, 'student', 5, 'Profile Updated', 'Your student profile was updated by admin.', 'account_settings.php', 0, '2026-09-08 10:16:18'),
(71, 'student', 5, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 0, '2026-09-08 10:18:35'),
(72, 'admin', NULL, 'Adaptive Exam Completed', 'pp completed an adaptive exam.', 'results.php', 0, '2026-09-08 10:18:35'),
(73, 'student', 5, 'Exam Completed', 'Your exam has been submitted and the result is ready.', 'result.php', 0, '2026-09-08 10:24:24'),
(74, 'admin', NULL, 'Exam Completed', 'pp submitted an exam.', 'results.php', 0, '2026-09-08 10:24:24'),
(75, 'student', 5, 'Adaptive Exam Completed', 'Your adaptive exam result is ready.', 'result.php', 0, '2026-09-08 10:38:54'),
(76, 'admin', NULL, 'Adaptive Exam Completed', 'pp completed an adaptive exam.', 'results.php', 0, '2026-09-08 10:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `language` varchar(40) NOT NULL DEFAULT 'English',
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` char(1) NOT NULL,
  `difficulty` varchar(20) NOT NULL,
  `marks` int(11) NOT NULL DEFAULT 1,
  `ai_generated` tinyint(1) DEFAULT 0,
  `keywords` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `questions`
--

INSERT INTO `questions` (`id`, `subject_id`, `question`, `language`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `difficulty`, `marks`, `ai_generated`, `keywords`) VALUES
(1, 1, 'What does the keyword \'void\' mean in the context of a function in C programming?', 'English', 'It means the function returns no value', 'It means the function takes no arguments', 'It means the function can be used anywhere in the program', 'It means the function will always throw an error', 'A', 'Easy', 1, 1, NULL),
(2, 1, 'What is the purpose of the \'printf\' function in C programming?', 'English', 'To read input from the user', 'To perform arithmetic operations', 'To display output on the screen', 'To declare variables', 'C', 'Easy', 1, 1, NULL),
(3, 1, 'What is the purpose of the \'#include\' directive in C programming?', 'English', 'To declare a variable', 'To define a function', 'To include header files', 'To compile the program', 'C', 'Easy', 1, 1, NULL),
(4, 1, 'What is the purpose of the \'main\' function in C programming?', 'English', 'To declare variables', 'To define a custom data type', 'To serve as the entry point of the program', 'To perform input/output operations', 'C', 'Easy', 1, 1, NULL),
(5, 1, 'What is the primary purpose of the \'scanf\' function in C programming?', 'English', 'To print formatted output to the screen', 'To read formatted input from the keyboard', 'To perform arithmetic operations on variables', 'To store data in an array', 'B', 'Medium', 1, 1, NULL),
(6, 1, 'What is the purpose of the \'break\' statement in a C programming loop?', 'English', 'To exit the loop and transfer control to the next statement', 'To skip the current iteration and move to the next one', 'To repeat the current iteration', 'To terminate the program', 'A', 'Medium', 1, 1, NULL),
(7, 1, 'What is the purpose of the \'sizeof\' operator in C programming?', 'English', 'To calculate the memory address of a variable', 'To determine the data type of a variable', 'To get the size of a variable or data type in bytes', 'To perform arithmetic operations on variables', 'C', 'Medium', 1, 1, NULL),
(8, 1, 'What is the purpose of the \'continue\' statement in a C programming loop?', 'English', 'To exit the loop immediately', 'To skip the rest of the code in the current iteration and move to the next iteration', 'To repeat the current iteration', 'To terminate the program', 'B', 'Medium', 1, 1, NULL),
(9, 1, 'What is the primary purpose of using pointers to structures in C programming?', 'English', 'To optimize memory usage by storing data in a continuous block', 'To enable dynamic memory allocation for arrays', 'To allow indirect access and modification of structure members', 'To improve code readability by eliminating the need for structure notation', 'C', 'Hard', 1, 1, NULL),
(10, 1, 'What is the purpose of the \'volatile\' keyword in C programming?', 'English', 'To declare a variable that can be modified by multiple threads', 'To declare a variable that cannot be optimized by the compiler', 'To declare a variable that can only be accessed through a pointer', 'To declare a variable that is stored in read-only memory', 'B', 'Hard', 1, 1, NULL),
(11, 1, 'What is the purpose of the \'const\' keyword when used with a variable in C programming?', 'English', 'To specify that the variable can be modified only within a specific function', 'To inform the compiler that the variable\'s value should be stored in read-only memory', 'To allow the variable to be used as a pointer to a constant value', 'To declare a variable that can be accessed from any file in the program', 'B', 'Hard', 1, 1, NULL),
(12, 1, 'What is the primary purpose of using the \'static\' keyword with a variable inside a function in C programming?', 'English', 'To allocate memory for the variable on the heap', 'To make the variable accessible from any file in the program', 'To preserve the value of the variable between function calls', 'To deallocate memory for the variable when the function returns', 'C', 'Hard', 1, 1, NULL),
(13, 2, 'What does PHP stand for?', 'English', 'Hypertext Preprocessor', 'Personal Home Page', 'PHP: Hypertext Preprocessor', 'Professional HTML Programmer', 'C', 'Easy', 1, 1, NULL),
(14, 2, 'What is the primary function of the PHP tag <?= ?>?', 'English', 'To start and end a PHP code block', 'To comment out PHP code', 'To echo the result of an expression', 'To import external libraries', 'C', 'Easy', 1, 1, NULL),
(15, 2, 'What is the purpose of the PHP function \'htmlspecialchars\'?', 'English', 'To connect to a MySQL database', 'To convert special characters to HTML entities', 'To start a PHP session', 'To send an HTTP redirect', 'B', 'Easy', 1, 1, NULL),
(16, 2, 'What is the purpose of the \'isset\' function in PHP?', 'English', 'To check if a variable is empty', 'To check if a variable is set and not null', 'To check the data type of a variable', 'To unset a variable', 'B', 'Easy', 1, 1, NULL),
(17, 2, 'What is the purpose of the \'explode\' function in PHP?', 'English', 'To split a string into an array based on a specified separator', 'To concatenate two or more strings into a single string', 'To search for a specific substring within a string', 'To remove all HTML tags from a string', 'A', 'Medium', 1, 1, NULL),
(18, 2, 'What is the purpose of the \'session_start\' function in PHP?', 'English', 'To start a new database connection', 'To initialize a new PHP session and resume an existing one', 'To redirect the user to a different webpage', 'To close an open file handle', 'B', 'Medium', 1, 1, NULL),
(19, 2, 'What is the purpose of the \'empty\' function in PHP?', 'English', 'To check if a variable is not set and has no value', 'To check if a variable is set but has an empty value', 'To check if a variable exists and has a non-empty value', 'To remove all empty elements from an array', 'A', 'Medium', 1, 1, NULL),
(20, 2, 'What is the purpose of the \'preg_match\' function in PHP?', 'English', 'To replace a pattern in a string with a replacement', 'To search for a pattern in a string and return 1 if the pattern is found', 'To split a string into an array using a pattern', 'To evaluate a string as PHP code', 'B', 'Medium', 1, 1, NULL),
(21, 2, 'What is the primary difference between the \'==\' and \'===\' operators in PHP?', 'English', 'The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality', 'The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality', 'The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison', 'The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison', 'A', 'Hard', 1, 1, NULL),
(22, 2, 'What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?', 'English', 'To make the class abstract and prevent it from being instantiated directly', 'To allow the class to be extended by other classes, but prevent its methods from being overridden', 'To prevent the class from being extended by other classes, while still allowing its methods to be overridden', 'To prevent the class from being extended by other classes and prevent its methods from being overridden', 'D', 'Hard', 1, 1, NULL),
(23, 2, 'What is the primary purpose of using the \'autoload\' function in PHP?', 'English', 'To automatically load PHP extensions', 'To handle errors and exceptions in PHP', 'To automatically load classes in PHP', 'To improve the performance of PHP scripts by caching', 'C', 'Hard', 1, 1, NULL),
(24, 2, 'What is the primary purpose of using the \'trait\' keyword in PHP?', 'English', 'To define an abstract class that cannot be instantiated', 'To define a class that can be used as a base class for other classes', 'To define a reusable block of code that can be used in multiple classes', 'To define an interface that must be implemented by any class that uses it', 'C', 'Hard', 1, 1, NULL),
(25, 4, 'What is C# ?', 'English', 'Programing Language', 'Language', 'normal', 'other', 'A', 'Easy', 1, 0, ''),
(26, 4, '2+1 ?', 'English', '3', '4', '5', '6', 'A', 'Medium', 1, 0, ''),
(27, 5, 'What is the function of the \'type()\' function in Python?', 'English', 'To check if a variable is of a certain type', 'To convert a variable from one type to another', 'To get the memory address of a variable', 'To check the version of the Python interpreter', 'A', 'Easy', 1, 1, NULL),
(28, 5, 'What is the primary purpose of the \'try-except\' block in Python?', 'English', 'To handle runtime errors and exceptions, allowing the program to continue execution', 'To improve code readability by grouping related statements together', 'To optimize code performance by reducing the number of function calls', 'To implement multithreading and concurrent programming in Python', 'A', 'Medium', 1, 1, NULL),
(29, 5, 'What is the term for the process of modifying or extending the behavior of a class in Python without permanently changing the class itself?', 'English', 'Inheritance', 'Polymorphism', 'Monkey patching', 'Encapsulation', 'C', 'Hard', 1, 1, NULL),
(30, 5, 'What is the output of the Python expression \'5 % 2\'?', 'English', '2', '1', '3', '0', 'B', 'Easy', 1, 1, NULL),
(31, 5, 'What is the primary function of the \'with\' statement in Python when working with files?', 'English', 'To handle exceptions that occur during file operations', 'To create a new file if it does not already exist', 'To ensure that files are properly closed after they are no longer needed', 'To read the entire contents of a file into memory', 'C', 'Medium', 1, 1, NULL),
(32, 5, 'What is the term for the phenomenon in Python where an object\'s lifetime is extended because there are still references to it, even if they are indirect or circular?', 'English', 'Garbage Collection', 'Reference Counting', 'Object Persistence', 'Memory Leaking', 'B', 'Hard', 1, 1, NULL),
(33, 5, 'What is the data type of the variable \'x\' in the Python code \'x = [1, 2, 3]\'?', 'English', 'Integer', 'Float', 'List', 'String', 'C', 'Easy', 1, 1, NULL),
(34, 5, 'What is the purpose of the \'self\' parameter in a Python class method?', 'English', 'To access the class itself', 'To represent the instance of the class and access its attributes', 'To define a static method', 'To import modules', 'B', 'Medium', 1, 1, NULL),
(35, 5, 'What is the term for the concept in Python where a child class inherits the attributes and methods of a parent class, but can also add new attributes or override the ones inherited from the parent class?', 'English', 'Polymorphism', 'Inheritance', 'Encapsulation', 'Abstraction', 'B', 'Hard', 1, 1, NULL),
(36, 5, 'What is the function of the \'len()\' function in Python?', 'English', 'To calculate the sum of all elements in a list', 'To get the length of a string, list, tuple, or dictionary', 'To sort the elements of a list in ascending order', 'To find the maximum value in a list', 'B', 'Easy', 1, 1, NULL),
(37, 5, 'What is the purpose of the \'zip()\' function in Python?', 'English', 'To iterate over two lists in parallel', 'To sort a list in ascending order', 'To convert a string to an integer', 'To reverse a list', 'A', 'Medium', 1, 1, NULL),
(38, 5, 'What is the term for the concept in Python where a function can accept a variable number of arguments, which are collected into a tuple or dictionary?', 'English', 'Function Overloading', 'Function Overriding', 'Variable Arguments', 'Default Arguments', 'C', 'Hard', 1, 1, NULL),
(39, 3, 'What does the term \'Machine Learning\' refer to in the field of Artificial Intelligence?', 'English', 'A type of computer network', 'A subset of AI that involves training algorithms to learn from data', 'A human-computer interaction method', 'A type of computer hardware', 'B', 'Easy', 1, 1, NULL),
(40, 3, 'What is the primary purpose of the backpropagation algorithm in neural networks?', 'English', 'To initialize the weights of the neural network', 'To normalize the input data for the neural network', 'To update the model\'s parameters by minimizing the error', 'To select the most relevant features for the neural network', 'C', 'Medium', 1, 1, NULL),
(41, 3, 'What is the primary limitation of using traditional feedforward neural networks for sequence prediction tasks, such as language modeling or time series forecasting?', 'English', 'Insufficient training data', 'Inability to capture long-range dependencies in sequential data', 'High computational cost due to large model sizes', 'Inadequate choice of activation functions', 'B', 'Hard', 1, 1, NULL),
(42, 3, 'What is the main function of Natural Language Processing in Artificial Intelligence?', 'English', 'To enable computers to understand and generate human language', 'To develop expert systems for decision-making', 'To create computer vision for image recognition', 'To design robotic systems for physical tasks', 'A', 'Easy', 1, 1, NULL),
(43, 3, 'What is the primary goal of the k-means clustering algorithm in unsupervised machine learning?', 'English', 'To predict a continuous output variable based on input features', 'To classify data points into predefined categories', 'To partition the data into k clusters based on similarity', 'To reduce the dimensionality of high-dimensional data', 'C', 'Medium', 1, 1, NULL),
(44, 3, 'What is the primary challenge in applying traditional machine learning approaches to reinforcement learning problems?', 'English', 'High dimensionality of the feature space', 'Exploration-exploitation trade-off', 'Limited availability of labeled training data', 'Presence of non-linear relationships between variables', 'B', 'Hard', 1, 1, NULL),
(45, 3, 'Which of the following AI techniques involves the use of algorithms to parse and understand human language?', 'English', 'Computer Vision', 'Natural Language Processing', 'Robotics', 'Expert Systems', 'B', 'Easy', 1, 1, NULL),
(46, 3, 'What is the primary function of the \'Attention Mechanism\' in deep learning models?', 'English', 'To improve the stability of the model by regularizing the weights', 'To enable the model to focus on specific parts of the input data when making predictions', 'To increase the dimensionality of the input data for better feature representation', 'To reduce the computational complexity of the model by pruning unnecessary neurons', 'B', 'Medium', 1, 1, NULL),
(47, 3, 'What is the primary advantage of using Transfer Learning in Deep Learning models, particularly in scenarios where there is a scarcity of training data?', 'English', 'It reduces the computational complexity of the model', 'It enables the model to learn from scratch without requiring large datasets', 'It allows the model to leverage pre-trained weights and fine-tune them for the target task, thus improving performance and reducing training time', 'It increases the risk of overfitting by introducing additional parameters', 'C', 'Hard', 1, 1, NULL),
(48, 3, 'What is the primary function of the \'Activation Function\' in a neural network?', 'English', 'To reduce the dimensionality of the input data', 'To introduce non-linearity into the model, allowing it to learn complex relationships', 'To normalize the output of the model', 'To increase the number of features in the dataset', 'B', 'Easy', 1, 1, NULL),
(49, 3, 'What is the primary purpose of using Dropout in Deep Neural Networks?', 'English', 'To increase the learning rate of the model', 'To reduce overfitting by randomly dropping out neurons during training', 'To improve the interpretability of the model', 'To speed up the training process by reducing the number of parameters', 'B', 'Medium', 1, 1, NULL),
(50, 3, 'What is the primary technique used to address the vanishing gradient problem in deep neural networks during the backpropagation process?', 'English', 'Gradient Clipping', 'Batch Normalization', 'Rectified Linear Unit (ReLU) Activation Function', 'Long Short-Term Memory (LSTM) Architecture', 'C', 'Hard', 1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `wrong_answers` int(11) NOT NULL,
  `marks_obtained` int(11) NOT NULL,
  `percentage` decimal(5,2) NOT NULL,
  `status` varchar(20) NOT NULL,
  `ai_insight` text DEFAULT NULL,
  `skill_category` varchar(20) DEFAULT NULL,
  `theta_score` decimal(5,2) DEFAULT NULL,
  `difficulty_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`difficulty_breakdown`)),
  `answers_detail` longtext DEFAULT NULL,
  `time_spent_seconds` int(11) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `results`
--

INSERT INTO `results` (`id`, `student_id`, `exam_id`, `session_id`, `total_questions`, `correct_answers`, `wrong_answers`, `marks_obtained`, `percentage`, `status`, `ai_insight`, `skill_category`, `theta_score`, `difficulty_breakdown`, `answers_detail`, `time_spent_seconds`, `submitted_at`) VALUES
(2, 1, 2, 2, 12, 6, 6, 6, 50.00, 'Pass', 'Kishan demonstrated perseverance by completing the PHP exam within the given time frame of 00:15:22 and achieving a passing score of 50%.\nKishan struggled with accuracy, answering only half of the questions correctly, indicating a need for improvement in understanding and applying PHP concepts.\nKishan should focus on reviewing and practicing PHP fundamentals, particularly in areas where incorrect answers were marked, to enhance knowledge and confidence.\n💪 Keep going, Kishan, you\'ve made a great start by passing the exam, and with dedication and practice, you can significantly improve your PHP skills.\n\n📊 Difficulty Analysis:\nThe student demonstrates strength in tackling medium and easy-level questions, as evidenced by their 75% success rate in both categories, suggesting they have a solid foundation in the subject matter. However, they struggle significantly with hard-level questions, scoring 0%, which indicates a need for improvement in critical thinking, problem-solving, or possibly a lack of understanding of more complex concepts.', 'Weak', -0.78, '{\"Easy\":{\"total\":4,\"correct\":3},\"Hard\":{\"total\":4,\"correct\":0},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the purpose of the \'explode\' function in PHP?\",\"option_a\":\"To split a string into an array based on a specified separator\",\"option_b\":\"To concatenate two or more strings into a single string\",\"option_c\":\"To search for a specific substring within a string\",\"option_d\":\"To remove all HTML tags from a string\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary difference between the \'==\' and \'===\' operators in PHP?\",\"option_a\":\"The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality\",\"option_b\":\"The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality\",\"option_c\":\"The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison\",\"option_d\":\"The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'session_start\' function in PHP?\",\"option_a\":\"To start a new database connection\",\"option_b\":\"To initialize a new PHP session and resume an existing one\",\"option_c\":\"To redirect the user to a different webpage\",\"option_d\":\"To close an open file handle\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?\",\"option_a\":\"To make the class abstract and prevent it from being instantiated directly\",\"option_b\":\"To allow the class to be extended by other classes, but prevent its methods from being overridden\",\"option_c\":\"To prevent the class from being extended by other classes, while still allowing its methods to be overridden\",\"option_d\":\"To prevent the class from being extended by other classes and prevent its methods from being overridden\",\"student_answer\":\"C\",\"correct_answer\":\"D\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'empty\' function in PHP?\",\"option_a\":\"To check if a variable is not set and has no value\",\"option_b\":\"To check if a variable is set but has an empty value\",\"option_c\":\"To check if a variable exists and has a non-empty value\",\"option_d\":\"To remove all empty elements from an array\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does PHP stand for?\",\"option_a\":\"Hypertext Preprocessor\",\"option_b\":\"Personal Home Page\",\"option_c\":\"PHP: Hypertext Preprocessor\",\"option_d\":\"Professional HTML Programmer\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the PHP tag <?= ?>?\",\"option_a\":\"To start and end a PHP code block\",\"option_b\":\"To comment out PHP code\",\"option_c\":\"To echo the result of an expression\",\"option_d\":\"To import external libraries\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'preg_match\' function in PHP?\",\"option_a\":\"To replace a pattern in a string with a replacement\",\"option_b\":\"To search for a pattern in a string and return 1 if the pattern is found\",\"option_c\":\"To split a string into an array using a pattern\",\"option_d\":\"To evaluate a string as PHP code\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'autoload\' function in PHP?\",\"option_a\":\"To automatically load PHP extensions\",\"option_b\":\"To handle errors and exceptions in PHP\",\"option_c\":\"To automatically load classes in PHP\",\"option_d\":\"To improve the performance of PHP scripts by caching\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the PHP function \'htmlspecialchars\'?\",\"option_a\":\"To connect to a MySQL database\",\"option_b\":\"To convert special characters to HTML entities\",\"option_c\":\"To start a PHP session\",\"option_d\":\"To send an HTTP redirect\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'isset\' function in PHP?\",\"option_a\":\"To check if a variable is empty\",\"option_b\":\"To check if a variable is set and not null\",\"option_c\":\"To check the data type of a variable\",\"option_d\":\"To unset a variable\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'trait\' keyword in PHP?\",\"option_a\":\"To define an abstract class that cannot be instantiated\",\"option_b\":\"To define a class that can be used as a base class for other classes\",\"option_c\":\"To define a reusable block of code that can be used in multiple classes\",\"option_d\":\"To define an interface that must be implemented by any class that uses it\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1}]', 922, '2026-07-27 14:33:54'),
(3, 1, 4, 3, 12, 6, 6, 6, 50.00, 'Pass', 'Kishan demonstrated a good foundation in Python by answering 6 out of 12 questions correctly, showcasing their understanding of key concepts.\nKishan struggled with consistency, getting 6 questions wrong, indicating a need to review and reinforce their knowledge of Python fundamentals.\nKishan should focus on practicing with sample questions and reviewing the topics where they went wrong to improve their overall performance.\n💪 Keep practicing and you\'ll see improvement, congratulations on passing the exam!\n\n📊 Difficulty Analysis:\nThe student excels in medium-difficulty tasks, demonstrating a strong foundation in the subject matter with a 75% success rate, but struggles with easy tasks, achieving only a 25% success rate, suggesting a need to review and reinforce basic concepts. In contrast, their performance on hard tasks is relatively strong at 50%, indicating that they are capable of handling complex material, but may benefit from targeted support to address gaps in their understanding of fundamental principles.', 'Moderate', 0.28, '{\"Easy\":{\"total\":4,\"correct\":1},\"Hard\":{\"total\":4,\"correct\":2},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the primary purpose of the \'try-except\' block in Python?\",\"option_a\":\"To handle runtime errors and exceptions, allowing the program to continue execution\",\"option_b\":\"To improve code readability by grouping related statements together\",\"option_c\":\"To optimize code performance by reducing the number of function calls\",\"option_d\":\"To implement multithreading and concurrent programming in Python\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the term for the process of modifying or extending the behavior of a class in Python without permanently changing the class itself?\",\"option_a\":\"Inheritance\",\"option_b\":\"Polymorphism\",\"option_c\":\"Monkey patching\",\"option_d\":\"Encapsulation\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the term for the phenomenon in Python where an object\'s lifetime is extended because there are still references to it, even if they are indirect or circular?\",\"option_a\":\"Garbage Collection\",\"option_b\":\"Reference Counting\",\"option_c\":\"Object Persistence\",\"option_d\":\"Memory Leaking\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the term for the concept in Python where a child class inherits the attributes and methods of a parent class, but can also add new attributes or override the ones inherited from the parent class?\",\"option_a\":\"Polymorphism\",\"option_b\":\"Inheritance\",\"option_c\":\"Encapsulation\",\"option_d\":\"Abstraction\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary function of the \'with\' statement in Python when working with files?\",\"option_a\":\"To handle exceptions that occur during file operations\",\"option_b\":\"To create a new file if it does not already exist\",\"option_c\":\"To ensure that files are properly closed after they are no longer needed\",\"option_d\":\"To read the entire contents of a file into memory\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the term for the concept in Python where a function can accept a variable number of arguments, which are collected into a tuple or dictionary?\",\"option_a\":\"Function Overloading\",\"option_b\":\"Function Overriding\",\"option_c\":\"Variable Arguments\",\"option_d\":\"Default Arguments\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'self\' parameter in a Python class method?\",\"option_a\":\"To access the class itself\",\"option_b\":\"To represent the instance of the class and access its attributes\",\"option_c\":\"To define a static method\",\"option_d\":\"To import modules\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the purpose of the \'zip()\' function in Python?\",\"option_a\":\"To iterate over two lists in parallel\",\"option_b\":\"To sort a list in ascending order\",\"option_c\":\"To convert a string to an integer\",\"option_d\":\"To reverse a list\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the function of the \'type()\' function in Python?\",\"option_a\":\"To check if a variable is of a certain type\",\"option_b\":\"To convert a variable from one type to another\",\"option_c\":\"To get the memory address of a variable\",\"option_d\":\"To check the version of the Python interpreter\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the output of the Python expression \'5 % 2\'?\",\"option_a\":\"2\",\"option_b\":\"1\",\"option_c\":\"3\",\"option_d\":\"0\",\"student_answer\":\"A\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the data type of the variable \'x\' in the Python code \'x = [1, 2, 3]\'?\",\"option_a\":\"Integer\",\"option_b\":\"Float\",\"option_c\":\"List\",\"option_d\":\"String\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the function of the \'len()\' function in Python?\",\"option_a\":\"To calculate the sum of all elements in a list\",\"option_b\":\"To get the length of a string, list, tuple, or dictionary\",\"option_c\":\"To sort the elements of a list in ascending order\",\"option_d\":\"To find the maximum value in a list\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1}]', 739, '2026-07-27 14:54:48'),
(4, 1, 3, NULL, 1, 1, 0, 1, 100.00, 'Pass', 'Kishan demonstrated exceptional mastery of the C# subject matter by achieving a perfect score of 100% on the exam.\nThere are no notable areas of weakness in this particular exam, but Kishan may benefit from being exposed to a wider range of question types and difficulty levels.\nTo further solidify his skills, Kishan should attempt to solve more complex and diverse C# problems to enhance his problem-solving abilities.\n💪 Great job, Kishan, keep up the excellent work and continue to push yourself to new heights in your programming journey.\n\nDifficulty Analysis:\nThis student has demonstrated exceptional proficiency in easy-level material, achieving a perfect score of 100% and indicating a strong foundation in fundamental concepts. However, since there is no data on their performance in medium or hard-level material, it is unclear whether they can apply their knowledge to more complex and challenging problems, highlighting a need for assessment in higher difficulty levels to fully understand their strengths and weaknesses.', 'Advanced', 2.00, '{\"Easy\":{\"total\":1,\"correct\":1}}', '[{\"question\":\"What is C# ?\",\"option_a\":\"Programing Language\",\"option_b\":\"Language\",\"option_c\":\"normal\",\"option_d\":\"other\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1}]', 26, '2026-07-27 15:10:12'),
(7, 3, 2, 6, 12, 6, 6, 6, 50.00, 'Pass', 'Ayush demonstrated a decent understanding of PHP concepts by answering 6 out of 12 questions correctly.\nAyush struggled with accuracy, getting 6 questions wrong, indicating a need to improve in this area.\nReview and practice PHP concepts regularly, focusing on areas where mistakes were made, to improve overall performance.\n💪 Congratulations on passing, keep working and you\'ll see improvement\n\n📊 Difficulty Analysis:\nThe student excels in medium-difficulty questions, answering 75% of them correctly, indicating a strong foundation in the subject matter and the ability to apply concepts to moderately complex problems. However, they struggle with harder questions, answering only 25% correctly, and also perform relatively poorly on easy questions, with a 50% success rate, suggesting a need to review fundamental concepts and develop strategies to tackle more challenging problems.', 'Moderate', -0.21, '{\"Easy\":{\"total\":4,\"correct\":2},\"Hard\":{\"total\":4,\"correct\":1},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the purpose of the \'explode\' function in PHP?\",\"option_a\":\"To split a string into an array based on a specified separator\",\"option_b\":\"To concatenate two or more strings into a single string\",\"option_c\":\"To search for a specific substring within a string\",\"option_d\":\"To remove all HTML tags from a string\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary difference between the \'==\' and \'===\' operators in PHP?\",\"option_a\":\"The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality\",\"option_b\":\"The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality\",\"option_c\":\"The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison\",\"option_d\":\"The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'session_start\' function in PHP?\",\"option_a\":\"To start a new database connection\",\"option_b\":\"To initialize a new PHP session and resume an existing one\",\"option_c\":\"To redirect the user to a different webpage\",\"option_d\":\"To close an open file handle\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?\",\"option_a\":\"To make the class abstract and prevent it from being instantiated directly\",\"option_b\":\"To allow the class to be extended by other classes, but prevent its methods from being overridden\",\"option_c\":\"To prevent the class from being extended by other classes, while still allowing its methods to be overridden\",\"option_d\":\"To prevent the class from being extended by other classes and prevent its methods from being overridden\",\"student_answer\":\"C\",\"correct_answer\":\"D\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'empty\' function in PHP?\",\"option_a\":\"To check if a variable is not set and has no value\",\"option_b\":\"To check if a variable is set but has an empty value\",\"option_c\":\"To check if a variable exists and has a non-empty value\",\"option_d\":\"To remove all empty elements from an array\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does PHP stand for?\",\"option_a\":\"Hypertext Preprocessor\",\"option_b\":\"Personal Home Page\",\"option_c\":\"PHP: Hypertext Preprocessor\",\"option_d\":\"Professional HTML Programmer\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the PHP tag <?= ?>?\",\"option_a\":\"To start and end a PHP code block\",\"option_b\":\"To comment out PHP code\",\"option_c\":\"To echo the result of an expression\",\"option_d\":\"To import external libraries\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the PHP function \'htmlspecialchars\'?\",\"option_a\":\"To connect to a MySQL database\",\"option_b\":\"To convert special characters to HTML entities\",\"option_c\":\"To start a PHP session\",\"option_d\":\"To send an HTTP redirect\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'preg_match\' function in PHP?\",\"option_a\":\"To replace a pattern in a string with a replacement\",\"option_b\":\"To search for a pattern in a string and return 1 if the pattern is found\",\"option_c\":\"To split a string into an array using a pattern\",\"option_d\":\"To evaluate a string as PHP code\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'autoload\' function in PHP?\",\"option_a\":\"To automatically load PHP extensions\",\"option_b\":\"To handle errors and exceptions in PHP\",\"option_c\":\"To automatically load classes in PHP\",\"option_d\":\"To improve the performance of PHP scripts by caching\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'isset\' function in PHP?\",\"option_a\":\"To check if a variable is empty\",\"option_b\":\"To check if a variable is set and not null\",\"option_c\":\"To check the data type of a variable\",\"option_d\":\"To unset a variable\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'trait\' keyword in PHP?\",\"option_a\":\"To define an abstract class that cannot be instantiated\",\"option_b\":\"To define a class that can be used as a base class for other classes\",\"option_c\":\"To define a reusable block of code that can be used in multiple classes\",\"option_d\":\"To define an interface that must be implemented by any class that uses it\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1}]', 434, '2026-07-30 17:04:40'),
(8, 7, 2, 7, 12, 6, 6, 6, 50.00, 'Pass', 'The student demonstrated perseverance by completing the PHP exam within a relatively short time of 00:04:01.\nThe student struggled with accuracy, answering only half of the questions correctly, indicating a need for improvement in their PHP knowledge.\nReview and practice PHP fundamentals, focusing on areas where incorrect answers were given, to improve overall understanding and performance.\n💪 You\'ve passed, now build on this momentum to achieve even greater success in future exams.\n\n📊 Difficulty Analysis:\nThe student demonstrates strength in tackling medium and easy-level questions, with a 75% success rate in both categories, indicating a solid foundation in the subject matter. However, they struggle significantly with hard-level questions, scoring 0%, which suggests a weakness in applying complex concepts or critical thinking skills to more challenging problems.', 'Weak', -0.78, '{\"Easy\":{\"total\":4,\"correct\":3},\"Hard\":{\"total\":4,\"correct\":0},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the purpose of the \'explode\' function in PHP?\",\"option_a\":\"To split a string into an array based on a specified separator\",\"option_b\":\"To concatenate two or more strings into a single string\",\"option_c\":\"To search for a specific substring within a string\",\"option_d\":\"To remove all HTML tags from a string\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary difference between the \'==\' and \'===\' operators in PHP?\",\"option_a\":\"The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality\",\"option_b\":\"The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality\",\"option_c\":\"The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison\",\"option_d\":\"The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison\",\"student_answer\":\"D\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'session_start\' function in PHP?\",\"option_a\":\"To start a new database connection\",\"option_b\":\"To initialize a new PHP session and resume an existing one\",\"option_c\":\"To redirect the user to a different webpage\",\"option_d\":\"To close an open file handle\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?\",\"option_a\":\"To make the class abstract and prevent it from being instantiated directly\",\"option_b\":\"To allow the class to be extended by other classes, but prevent its methods from being overridden\",\"option_c\":\"To prevent the class from being extended by other classes, while still allowing its methods to be overridden\",\"option_d\":\"To prevent the class from being extended by other classes and prevent its methods from being overridden\",\"student_answer\":\"C\",\"correct_answer\":\"D\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'empty\' function in PHP?\",\"option_a\":\"To check if a variable is not set and has no value\",\"option_b\":\"To check if a variable is set but has an empty value\",\"option_c\":\"To check if a variable exists and has a non-empty value\",\"option_d\":\"To remove all empty elements from an array\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does PHP stand for?\",\"option_a\":\"Hypertext Preprocessor\",\"option_b\":\"Personal Home Page\",\"option_c\":\"PHP: Hypertext Preprocessor\",\"option_d\":\"Professional HTML Programmer\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the PHP tag <?= ?>?\",\"option_a\":\"To start and end a PHP code block\",\"option_b\":\"To comment out PHP code\",\"option_c\":\"To echo the result of an expression\",\"option_d\":\"To import external libraries\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'preg_match\' function in PHP?\",\"option_a\":\"To replace a pattern in a string with a replacement\",\"option_b\":\"To search for a pattern in a string and return 1 if the pattern is found\",\"option_c\":\"To split a string into an array using a pattern\",\"option_d\":\"To evaluate a string as PHP code\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'autoload\' function in PHP?\",\"option_a\":\"To automatically load PHP extensions\",\"option_b\":\"To handle errors and exceptions in PHP\",\"option_c\":\"To automatically load classes in PHP\",\"option_d\":\"To improve the performance of PHP scripts by caching\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the PHP function \'htmlspecialchars\'?\",\"option_a\":\"To connect to a MySQL database\",\"option_b\":\"To convert special characters to HTML entities\",\"option_c\":\"To start a PHP session\",\"option_d\":\"To send an HTTP redirect\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'isset\' function in PHP?\",\"option_a\":\"To check if a variable is empty\",\"option_b\":\"To check if a variable is set and not null\",\"option_c\":\"To check the data type of a variable\",\"option_d\":\"To unset a variable\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'trait\' keyword in PHP?\",\"option_a\":\"To define an abstract class that cannot be instantiated\",\"option_b\":\"To define a class that can be used as a base class for other classes\",\"option_c\":\"To define a reusable block of code that can be used in multiple classes\",\"option_d\":\"To define an interface that must be implemented by any class that uses it\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1}]', 241, '2026-07-31 06:34:50'),
(9, 7, 4, 8, 12, 1, 11, 1, 8.33, 'Fail', 'The student was able to correctly answer at least one question, demonstrating some knowledge of Python concepts.\nThe student struggled with the majority of the exam questions, indicating a need for significant improvement in their understanding of Python fundamentals.\nThe student should review the Python basics and practice with online resources or tutorials to reinforce their knowledge and build confidence.\n💪 Don\'t be discouraged by this result, every failure is an opportunity to learn and improve, keep working and you will see progress.\n\n📊 Difficulty Analysis:\nThe student\'s performance suggests that their strength lies in tackling challenging problems, as evidenced by their 25% score in the \"Hard\" category, which is higher than their scores in the \"Easy\" and \"Medium\" categories. However, their weakness is apparent in their inability to solve straightforward problems, with a 0% score in both the \"Easy\" and \"Medium\" categories, indicating a need for improvement in foundational knowledge and basic problem-solving skills.', 'Weak', -1.88, '{\"Easy\":{\"total\":4,\"correct\":0},\"Hard\":{\"total\":4,\"correct\":1},\"Medium\":{\"total\":4,\"correct\":0}}', '[{\"question\":\"What is the primary purpose of the \'try-except\' block in Python?\",\"option_a\":\"To handle runtime errors and exceptions, allowing the program to continue execution\",\"option_b\":\"To improve code readability by grouping related statements together\",\"option_c\":\"To optimize code performance by reducing the number of function calls\",\"option_d\":\"To implement multithreading and concurrent programming in Python\",\"student_answer\":\"D\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the function of the \'type()\' function in Python?\",\"option_a\":\"To check if a variable is of a certain type\",\"option_b\":\"To convert a variable from one type to another\",\"option_c\":\"To get the memory address of a variable\",\"option_d\":\"To check the version of the Python interpreter\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the output of the Python expression \'5 % 2\'?\",\"option_a\":\"2\",\"option_b\":\"1\",\"option_c\":\"3\",\"option_d\":\"0\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the data type of the variable \'x\' in the Python code \'x = [1, 2, 3]\'?\",\"option_a\":\"Integer\",\"option_b\":\"Float\",\"option_c\":\"List\",\"option_d\":\"String\",\"student_answer\":\"B\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the function of the \'len()\' function in Python?\",\"option_a\":\"To calculate the sum of all elements in a list\",\"option_b\":\"To get the length of a string, list, tuple, or dictionary\",\"option_c\":\"To sort the elements of a list in ascending order\",\"option_d\":\"To find the maximum value in a list\",\"student_answer\":\"D\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the \'with\' statement in Python when working with files?\",\"option_a\":\"To handle exceptions that occur during file operations\",\"option_b\":\"To create a new file if it does not already exist\",\"option_c\":\"To ensure that files are properly closed after they are no longer needed\",\"option_d\":\"To read the entire contents of a file into memory\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the purpose of the \'self\' parameter in a Python class method?\",\"option_a\":\"To access the class itself\",\"option_b\":\"To represent the instance of the class and access its attributes\",\"option_c\":\"To define a static method\",\"option_d\":\"To import modules\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the purpose of the \'zip()\' function in Python?\",\"option_a\":\"To iterate over two lists in parallel\",\"option_b\":\"To sort a list in ascending order\",\"option_c\":\"To convert a string to an integer\",\"option_d\":\"To reverse a list\",\"student_answer\":\"D\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the term for the process of modifying or extending the behavior of a class in Python without permanently changing the class itself?\",\"option_a\":\"Inheritance\",\"option_b\":\"Polymorphism\",\"option_c\":\"Monkey patching\",\"option_d\":\"Encapsulation\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the term for the phenomenon in Python where an object\'s lifetime is extended because there are still references to it, even if they are indirect or circular?\",\"option_a\":\"Garbage Collection\",\"option_b\":\"Reference Counting\",\"option_c\":\"Object Persistence\",\"option_d\":\"Memory Leaking\",\"student_answer\":\"D\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the term for the concept in Python where a child class inherits the attributes and methods of a parent class, but can also add new attributes or override the ones inherited from the parent class?\",\"option_a\":\"Polymorphism\",\"option_b\":\"Inheritance\",\"option_c\":\"Encapsulation\",\"option_d\":\"Abstraction\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the term for the concept in Python where a function can accept a variable number of arguments, which are collected into a tuple or dictionary?\",\"option_a\":\"Function Overloading\",\"option_b\":\"Function Overriding\",\"option_c\":\"Variable Arguments\",\"option_d\":\"Default Arguments\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1}]', 530, '2026-07-31 06:40:19'),
(10, 2, 2, 9, 12, 3, 9, 3, 25.00, 'Fail', 'Dev demonstrated some understanding of PHP concepts by correctly answering 3 out of 12 questions.\nDev struggled with the majority of the questions, indicating a need to review and practice PHP fundamentals.\nDev should focus on reviewing the wrong answers and practice with similar questions to improve understanding of PHP concepts.\n💪 Don\'t be discouraged, Dev - every failure is an opportunity to learn and improve for the next attempt.\n\n📊 Difficulty Analysis:\nThis student appears to struggle across the board, with equally low scores in easy, medium, and hard difficulty levels, indicating a need for overall improvement in their skills and understanding of the subject matter. Specifically, they scored only 25% in each category, suggesting that they may need to revisit fundamental concepts and develop strategies to tackle problems of varying difficulty levels, as they do not demonstrate a particular strength or weakness in any one area.', 'Weak', -1.31, '{\"Easy\":{\"total\":4,\"correct\":1},\"Hard\":{\"total\":4,\"correct\":1},\"Medium\":{\"total\":4,\"correct\":1}}', '[{\"question\":\"What is the purpose of the \'explode\' function in PHP?\",\"option_a\":\"To split a string into an array based on a specified separator\",\"option_b\":\"To concatenate two or more strings into a single string\",\"option_c\":\"To search for a specific substring within a string\",\"option_d\":\"To remove all HTML tags from a string\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary difference between the \'==\' and \'===\' operators in PHP?\",\"option_a\":\"The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality\",\"option_b\":\"The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality\",\"option_c\":\"The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison\",\"option_d\":\"The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'session_start\' function in PHP?\",\"option_a\":\"To start a new database connection\",\"option_b\":\"To initialize a new PHP session and resume an existing one\",\"option_c\":\"To redirect the user to a different webpage\",\"option_d\":\"To close an open file handle\",\"student_answer\":\"D\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does PHP stand for?\",\"option_a\":\"Hypertext Preprocessor\",\"option_b\":\"Personal Home Page\",\"option_c\":\"PHP: Hypertext Preprocessor\",\"option_d\":\"Professional HTML Programmer\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the PHP tag <?= ?>?\",\"option_a\":\"To start and end a PHP code block\",\"option_b\":\"To comment out PHP code\",\"option_c\":\"To echo the result of an expression\",\"option_d\":\"To import external libraries\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'empty\' function in PHP?\",\"option_a\":\"To check if a variable is not set and has no value\",\"option_b\":\"To check if a variable is set but has an empty value\",\"option_c\":\"To check if a variable exists and has a non-empty value\",\"option_d\":\"To remove all empty elements from an array\",\"student_answer\":\"D\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the purpose of the PHP function \'htmlspecialchars\'?\",\"option_a\":\"To connect to a MySQL database\",\"option_b\":\"To convert special characters to HTML entities\",\"option_c\":\"To start a PHP session\",\"option_d\":\"To send an HTTP redirect\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'isset\' function in PHP?\",\"option_a\":\"To check if a variable is empty\",\"option_b\":\"To check if a variable is set and not null\",\"option_c\":\"To check the data type of a variable\",\"option_d\":\"To unset a variable\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'preg_match\' function in PHP?\",\"option_a\":\"To replace a pattern in a string with a replacement\",\"option_b\":\"To search for a pattern in a string and return 1 if the pattern is found\",\"option_c\":\"To split a string into an array using a pattern\",\"option_d\":\"To evaluate a string as PHP code\",\"student_answer\":\"C\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?\",\"option_a\":\"To make the class abstract and prevent it from being instantiated directly\",\"option_b\":\"To allow the class to be extended by other classes, but prevent its methods from being overridden\",\"option_c\":\"To prevent the class from being extended by other classes, while still allowing its methods to be overridden\",\"option_d\":\"To prevent the class from being extended by other classes and prevent its methods from being overridden\",\"student_answer\":\"C\",\"correct_answer\":\"D\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'autoload\' function in PHP?\",\"option_a\":\"To automatically load PHP extensions\",\"option_b\":\"To handle errors and exceptions in PHP\",\"option_c\":\"To automatically load classes in PHP\",\"option_d\":\"To improve the performance of PHP scripts by caching\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'trait\' keyword in PHP?\",\"option_a\":\"To define an abstract class that cannot be instantiated\",\"option_b\":\"To define a class that can be used as a base class for other classes\",\"option_c\":\"To define a reusable block of code that can be used in multiple classes\",\"option_d\":\"To define an interface that must be implemented by any class that uses it\",\"student_answer\":\"B\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1}]', 296, '2026-08-01 10:20:54'),
(11, 7, 3, NULL, 1, 1, 0, 1, 100.00, 'Pass', 'The student demonstrated exceptional proficiency in C# by achieving a perfect score of 100% on the advanced exam.\nGiven the extremely short duration of the exam, it\'s difficult to identify a specific area for improvement, but the student may benefit from practicing more complex and time-consuming problems.\nTo further challenge themselves, the student should attempt to solve a series of complex, timed C# problems to simulate real-world development scenarios.\n💪 Keep up the outstanding work and continue pushing yourself to new heights in C# development.\n\nDifficulty Analysis:\nThis student has demonstrated exceptional performance on easy-level tasks, achieving a perfect score of 100%, indicating a strong foundation in the subject matter and a high level of proficiency in basic concepts. However, since there is no data on their performance at higher difficulty levels, it is unclear whether they can apply their knowledge and skills to more complex and challenging problems, which is an area that may require further assessment and development.', 'Advanced', 2.00, '{\"Easy\":{\"total\":1,\"correct\":1}}', '[{\"question\":\"What is C# ?\",\"option_a\":\"Programing Language\",\"option_b\":\"Language\",\"option_c\":\"normal\",\"option_d\":\"other\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1}]', 5, '2026-08-01 10:36:29'),
(12, 6, 3, NULL, 1, 0, 1, 0, 0.00, 'Fail', 'Priyesh demonstrated courage by attempting the C# exam, which is a crucial step in the learning process.\nPriyesh struggled with the exam questions, answering none correctly, indicating a need for improvement in understanding C# concepts.\nPriyesh should focus on reviewing the fundamentals of C# and practice with sample questions to build a stronger foundation.\n💪 Don\'t be discouraged by this result, Priyesh - every failure is an opportunity to learn and grow.\n\nDifficulty Analysis:\nThis student struggles significantly with even the easiest material, as evidenced by their 0% score on easy questions, indicating a need for foundational review and support. Unfortunately, without data on their performance at higher difficulty levels, it is difficult to identify any strengths, but it is clear that they require immediate attention and reinforcement on basic concepts to build a stronger understanding.', 'Weak', -2.00, '{\"Easy\":{\"total\":1,\"correct\":0}}', '[{\"question\":\"What is C# ?\",\"option_a\":\"Programing Language\",\"option_b\":\"Language\",\"option_c\":\"normal\",\"option_d\":\"other\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1}]', 29, '2026-08-09 17:19:19'),
(13, 6, 4, 15, 1, 0, 1, 0, 0.00, 'Fail', 'Priyesh attempted the exam, which shows his willingness to challenge himself and learn from the experience.\nPriyesh struggled with the python exam, scoring 0% and answering only one question incorrectly, indicating a need for significant improvement in his understanding of the subject.\nPriyesh should review the python fundamentals and practice with sample questions to improve his problem-solving skills and build confidence.\n💪 Don\'t be discouraged by this result, Priyesh - every failure is an opportunity to learn and grow, and with persistence and dedication, you can master python.\n\n📊 Difficulty Analysis:\nThis student struggles with medium-level questions, as evidenced by their 0% score, indicating a need to improve their understanding and application of concepts at this difficulty level. Specifically, they may need to focus on developing their problem-solving skills and knowledge in areas where medium-level questions are typically drawn, in order to strengthen their overall performance and build a foundation for tackling more challenging material.', 'Moderate', -0.19, '{\"Medium\":{\"total\":1,\"correct\":0}}', '[{\"question\":\"What is the primary purpose of the \'try-except\' block in Python?\",\"option_a\":\"To handle runtime errors and exceptions, allowing the program to continue execution\",\"option_b\":\"To improve code readability by grouping related statements together\",\"option_c\":\"To optimize code performance by reducing the number of function calls\",\"option_d\":\"To implement multithreading and concurrent programming in Python\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1}]', 613, '2026-08-09 17:30:34');
INSERT INTO `results` (`id`, `student_id`, `exam_id`, `session_id`, `total_questions`, `correct_answers`, `wrong_answers`, `marks_obtained`, `percentage`, `status`, `ai_insight`, `skill_category`, `theta_score`, `difficulty_breakdown`, `answers_detail`, `time_spent_seconds`, `submitted_at`) VALUES
(14, 8, 5, 17, 12, 9, 3, 9, 75.00, 'Pass', 'Vraj demonstrated a solid understanding of AI concepts, answering 9 out of 12 questions correctly to achieve a passing score of 75%.\nVraj struggled with 3 questions, indicating a need to review and reinforce knowledge in specific areas of the AI subject matter.\nVraj should focus on reviewing the incorrect questions and related topics to improve comprehension and retention of key AI concepts.\n💪 Great job passing the exam, Vraj, keep up the good work and stay motivated to continue improving your skills in AI!\n\n📊 Difficulty Analysis:\nThe student excels in easy-level questions, demonstrating a strong foundation in the subject matter with a perfect 100% score, and also performs well in medium-level questions with a 75% score, indicating a good understanding of moderately complex concepts. However, they struggle with hard-level questions, achieving only a 50% score, suggesting that they may need to work on developing their critical thinking and problem-solving skills to tackle more challenging and complex problems.', 'Moderate', 0.59, '{\"Easy\":{\"total\":4,\"correct\":4},\"Hard\":{\"total\":4,\"correct\":2},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the primary purpose of the backpropagation algorithm in neural networks?\",\"option_a\":\"To initialize the weights of the neural network\",\"option_b\":\"To normalize the input data for the neural network\",\"option_c\":\"To update the model\'s parameters by minimizing the error\",\"option_d\":\"To select the most relevant features for the neural network\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary limitation of using traditional feedforward neural networks for sequence prediction tasks, such as language modeling or time series forecasting?\",\"option_a\":\"Insufficient training data\",\"option_b\":\"Inability to capture long-range dependencies in sequential data\",\"option_c\":\"High computational cost due to large model sizes\",\"option_d\":\"Inadequate choice of activation functions\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary challenge in applying traditional machine learning approaches to reinforcement learning problems?\",\"option_a\":\"High dimensionality of the feature space\",\"option_b\":\"Exploration-exploitation trade-off\",\"option_c\":\"Limited availability of labeled training data\",\"option_d\":\"Presence of non-linear relationships between variables\",\"student_answer\":\"D\",\"correct_answer\":\"B\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary goal of the k-means clustering algorithm in unsupervised machine learning?\",\"option_a\":\"To predict a continuous output variable based on input features\",\"option_b\":\"To classify data points into predefined categories\",\"option_c\":\"To partition the data into k clusters based on similarity\",\"option_d\":\"To reduce the dimensionality of high-dimensional data\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does the term \'Machine Learning\' refer to in the field of Artificial Intelligence?\",\"option_a\":\"A type of computer network\",\"option_b\":\"A subset of AI that involves training algorithms to learn from data\",\"option_c\":\"A human-computer interaction method\",\"option_d\":\"A type of computer hardware\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the \'Attention Mechanism\' in deep learning models?\",\"option_a\":\"To improve the stability of the model by regularizing the weights\",\"option_b\":\"To enable the model to focus on specific parts of the input data when making predictions\",\"option_c\":\"To increase the dimensionality of the input data for better feature representation\",\"option_d\":\"To reduce the computational complexity of the model by pruning unnecessary neurons\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary advantage of using Transfer Learning in Deep Learning models, particularly in scenarios where there is a scarcity of training data?\",\"option_a\":\"It reduces the computational complexity of the model\",\"option_b\":\"It enables the model to learn from scratch without requiring large datasets\",\"option_c\":\"It allows the model to leverage pre-trained weights and fine-tune them for the target task, thus improving performance and reducing training time\",\"option_d\":\"It increases the risk of overfitting by introducing additional parameters\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary technique used to address the vanishing gradient problem in deep neural networks during the backpropagation process?\",\"option_a\":\"Gradient Clipping\",\"option_b\":\"Batch Normalization\",\"option_c\":\"Rectified Linear Unit (ReLU) Activation Function\",\"option_d\":\"Long Short-Term Memory (LSTM) Architecture\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the primary purpose of using Dropout in Deep Neural Networks?\",\"option_a\":\"To increase the learning rate of the model\",\"option_b\":\"To reduce overfitting by randomly dropping out neurons during training\",\"option_c\":\"To improve the interpretability of the model\",\"option_d\":\"To speed up the training process by reducing the number of parameters\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the main function of Natural Language Processing in Artificial Intelligence?\",\"option_a\":\"To enable computers to understand and generate human language\",\"option_b\":\"To develop expert systems for decision-making\",\"option_c\":\"To create computer vision for image recognition\",\"option_d\":\"To design robotic systems for physical tasks\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"Which of the following AI techniques involves the use of algorithms to parse and understand human language?\",\"option_a\":\"Computer Vision\",\"option_b\":\"Natural Language Processing\",\"option_c\":\"Robotics\",\"option_d\":\"Expert Systems\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the \'Activation Function\' in a neural network?\",\"option_a\":\"To reduce the dimensionality of the input data\",\"option_b\":\"To introduce non-linearity into the model, allowing it to learn complex relationships\",\"option_c\":\"To normalize the output of the model\",\"option_d\":\"To increase the number of features in the dataset\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1}]', 5286, '2026-08-10 11:00:51'),
(15, 5, 2, 19, 12, 7, 5, 7, 58.33, 'Pass', 'Fair attempt. You have basic understanding but need more practice. Review the fundamentals and try again. Keep learning! 💪', 'Moderate', -0.10, '{\"Easy\":{\"total\":4,\"correct\":3},\"Hard\":{\"total\":4,\"correct\":1},\"Medium\":{\"total\":4,\"correct\":3}}', '[{\"question\":\"What is the purpose of the \'explode\' function in PHP?\",\"option_a\":\"To split a string into an array based on a specified separator\",\"option_b\":\"To concatenate two or more strings into a single string\",\"option_c\":\"To search for a specific substring within a string\",\"option_d\":\"To remove all HTML tags from a string\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary difference between the \'==\' and \'===\' operators in PHP?\",\"option_a\":\"The \'==\' operator checks for value equality, while the \'===\' operator checks for both value and type equality\",\"option_b\":\"The \'==\' operator checks for both value and type equality, while the \'===\' operator checks for value equality\",\"option_c\":\"The \'==\' operator is used for assignment, while the \'===\' operator is used for comparison\",\"option_d\":\"The \'==\' operator is used for string comparison, while the \'===\' operator is used for numeric comparison\",\"student_answer\":\"B\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'session_start\' function in PHP?\",\"option_a\":\"To start a new database connection\",\"option_b\":\"To initialize a new PHP session and resume an existing one\",\"option_c\":\"To redirect the user to a different webpage\",\"option_d\":\"To close an open file handle\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'final\' keyword when declaring a class in PHP?\",\"option_a\":\"To make the class abstract and prevent it from being instantiated directly\",\"option_b\":\"To allow the class to be extended by other classes, but prevent its methods from being overridden\",\"option_c\":\"To prevent the class from being extended by other classes, while still allowing its methods to be overridden\",\"option_d\":\"To prevent the class from being extended by other classes and prevent its methods from being overridden\",\"student_answer\":\"C\",\"correct_answer\":\"D\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the \'empty\' function in PHP?\",\"option_a\":\"To check if a variable is not set and has no value\",\"option_b\":\"To check if a variable is set but has an empty value\",\"option_c\":\"To check if a variable exists and has a non-empty value\",\"option_d\":\"To remove all empty elements from an array\",\"student_answer\":\"C\",\"correct_answer\":\"A\",\"is_correct\":0,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What does PHP stand for?\",\"option_a\":\"Hypertext Preprocessor\",\"option_b\":\"Personal Home Page\",\"option_c\":\"PHP: Hypertext Preprocessor\",\"option_d\":\"Professional HTML Programmer\",\"student_answer\":\"A\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary function of the PHP tag <?= ?>?\",\"option_a\":\"To start and end a PHP code block\",\"option_b\":\"To comment out PHP code\",\"option_c\":\"To echo the result of an expression\",\"option_d\":\"To import external libraries\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'preg_match\' function in PHP?\",\"option_a\":\"To replace a pattern in a string with a replacement\",\"option_b\":\"To search for a pattern in a string and return 1 if the pattern is found\",\"option_c\":\"To split a string into an array using a pattern\",\"option_d\":\"To evaluate a string as PHP code\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Medium\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'autoload\' function in PHP?\",\"option_a\":\"To automatically load PHP extensions\",\"option_b\":\"To handle errors and exceptions in PHP\",\"option_c\":\"To automatically load classes in PHP\",\"option_d\":\"To improve the performance of PHP scripts by caching\",\"student_answer\":\"D\",\"correct_answer\":\"C\",\"is_correct\":0,\"difficulty\":\"Hard\",\"marks\":1},{\"question\":\"What is the purpose of the PHP function \'htmlspecialchars\'?\",\"option_a\":\"To connect to a MySQL database\",\"option_b\":\"To convert special characters to HTML entities\",\"option_c\":\"To start a PHP session\",\"option_d\":\"To send an HTTP redirect\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the purpose of the \'isset\' function in PHP?\",\"option_a\":\"To check if a variable is empty\",\"option_b\":\"To check if a variable is set and not null\",\"option_c\":\"To check the data type of a variable\",\"option_d\":\"To unset a variable\",\"student_answer\":\"B\",\"correct_answer\":\"B\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1},{\"question\":\"What is the primary purpose of using the \'trait\' keyword in PHP?\",\"option_a\":\"To define an abstract class that cannot be instantiated\",\"option_b\":\"To define a class that can be used as a base class for other classes\",\"option_c\":\"To define a reusable block of code that can be used in multiple classes\",\"option_d\":\"To define an interface that must be implemented by any class that uses it\",\"student_answer\":\"C\",\"correct_answer\":\"C\",\"is_correct\":1,\"difficulty\":\"Hard\",\"marks\":1}]', 784, '2026-09-08 10:18:35'),
(16, 5, 3, NULL, 1, 1, 0, 1, 100.00, 'Pass', 'Excellent performance! You demonstrated strong mastery of the subject. Keep up the great work and challenge yourself with advanced topics. 📚', 'Advanced', 2.00, '{\"Easy\":{\"total\":1,\"correct\":1}}', '[{\"question\":\"What is C# ?\",\"option_a\":\"Programing Language\",\"option_b\":\"Language\",\"option_c\":\"normal\",\"option_d\":\"other\",\"student_answer\":\"A\",\"correct_answer\":\"A\",\"is_correct\":1,\"difficulty\":\"Easy\",\"marks\":1}]', 43, '2026-09-08 10:24:24'),
(17, 5, 4, 20, 0, 0, 0, 0, 0.00, 'Fail', 'This is a learning opportunity. Review the core concepts, practice more questions, and don\'t give up. Every mistake is a step toward mastery! 🎯', 'Moderate', 0.00, '[]', '[]', 0, '2026-09-08 10:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `course` varchar(80) NOT NULL,
  `semester` varchar(30) NOT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `profile_image_updated_at` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `face_verified_at` timestamp NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `name`, `email`, `phone`, `password`, `course`, `semester`, `profile_image`, `profile_image_updated_at`, `last_login`, `face_verified_at`, `status`, `created_at`) VALUES
(1, 'kishan', 'kishan@gmail.com', '9316361979', 'kishan@0369', 'MCA', 'Semester 2', 'profile_admin_1785431766_386.jpeg', '2026-07-30 17:16:06', '2026-08-01 07:30:07', NULL, 'Active', '2026-07-27 05:57:32'),
(2, 'Dev', 'dev@gmail.com', '4564556456', 'dev@2208', 'MCA', 'Semester 7', 'profile_admin_1785478677_589.jpeg', '2026-07-31 06:17:57', NULL, NULL, 'Active', '2026-07-27 10:30:48'),
(3, 'Ayush', 'ayush@gmail.com', '9712745545', '$2y$10$jcNVuJF0DSQNs330zm6tLe1psvjmesi6BJIh3sI2BHXIken/zX4da', 'MCA', 'SEM-3', 'profile_3_1785430817.jpeg', '2026-07-30 17:00:17', '2026-07-30 16:59:37', NULL, 'Active', '2026-07-27 10:31:18'),
(4, 'Nitya', 'nitya@gmail.com', '5454548545', 'nitya@123', 'MCA', 'Semester 3', 'profile_admin_1786292592_570.jpeg', '2026-08-09 16:23:12', NULL, NULL, 'Active', '2026-07-27 10:31:59'),
(5, 'pp', 'pp@gmail.com', '8878787878', 'Kishan@0369', 'BCA', 'Semester 6', 'profile_1785431357_318.jpeg', '2026-07-30 17:09:17', NULL, NULL, 'Active', '2026-07-30 17:09:17'),
(6, 'priyesh patel', 'en@gmail.com', '1655444949', 'kishan@0369', 'BCA', 'Semester 2', 'profile_1785431668_545.jpeg', '2026-07-30 17:14:28', '2026-07-30 17:14:59', NULL, 'Active', '2026-07-30 17:14:28'),
(7, 'jj', 'jj@gmail.com', '2151654564', 'kishan@0369', 'B.Sc IT', 'Semester 4', 'profile_1785479560_844.jpeg', '2026-07-31 06:32:40', '2026-07-31 06:33:16', NULL, 'Active', '2026-07-31 06:32:40'),
(8, 'vraj', 'vraj@gmail.com', '8866821068', 'vraj@123', 'BCA', 'Semester 6', 'profile_1786359105_188.jpeg', '2026-08-10 10:51:45', '2026-08-10 10:51:56', NULL, 'Active', '2026-08-10 10:51:45');

-- --------------------------------------------------------

--
-- Table structure for table `student_answers`
--

CREATE TABLE `student_answers` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_answer` char(1) NOT NULL,
  `correct_answer` char(1) NOT NULL,
  `is_correct` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_answers`
--

INSERT INTO `student_answers` (`id`, `student_id`, `exam_id`, `question_id`, `selected_answer`, `correct_answer`, `is_correct`) VALUES
(1, 1, 3, 25, 'A', 'A', 1),
(2, 7, 3, 25, 'A', 'A', 1),
(3, 6, 3, 25, 'C', 'A', 0),
(4, 5, 3, 25, 'A', 'A', 1);

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(30) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `description`, `status`) VALUES
(1, 'C', 'c001', 'c practical', 'Active'),
(2, 'PHP', 'PS001', 'Practical', 'Active'),
(3, 'AI', 'a001', 'AI information', 'Active'),
(4, 'C#', 'cs01', '', 'Active'),
(5, 'python', 'py001', 'python practical', 'Active'),
(6, 'ML', 'ml001', 'AI/ML', 'Active'),
(7, 'VB.net', 'vb001', '', 'Active'),
(8, 'logical reasoning', 'lr001', '', 'Active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adaptive_answers`
--
ALTER TABLE `adaptive_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `adaptive_sessions`
--
ALTER TABLE `adaptive_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `exam_attempt_blocks`
--
ALTER TABLE `exam_attempt_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_exam_block` (`student_id`,`exam_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_exam_question` (`exam_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `face_verifications`
--
ALTER TABLE `face_verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_faculty_subject` (`faculty_id`,`subject_id`),
  ADD KEY `faculty_subjects_subject_id` (`subject_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_exam` (`student_id`,`exam_id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `exam_id` (`exam_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_name` (`subject_name`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adaptive_answers`
--
ALTER TABLE `adaptive_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123;

--
-- AUTO_INCREMENT for table `adaptive_sessions`
--
ALTER TABLE `adaptive_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `exam_attempt_blocks`
--
ALTER TABLE `exam_attempt_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `face_verifications`
--
ALTER TABLE `face_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `faculty_subjects`
--
ALTER TABLE `faculty_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `student_answers`
--
ALTER TABLE `student_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adaptive_answers`
--
ALTER TABLE `adaptive_answers`
  ADD CONSTRAINT `adaptive_answers_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `adaptive_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adaptive_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `adaptive_sessions`
--
ALTER TABLE `adaptive_sessions`
  ADD CONSTRAINT `adaptive_sessions_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `adaptive_sessions_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_attempt_blocks`
--
ALTER TABLE `exam_attempt_blocks`
  ADD CONSTRAINT `exam_attempt_blocks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_attempt_blocks_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `face_verifications`
--
ALTER TABLE `face_verifications`
  ADD CONSTRAINT `face_verifications_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `questions`
--
ALTER TABLE `questions`
  ADD CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_answers`
--
ALTER TABLE `student_answers`
  ADD CONSTRAINT `student_answers_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_2` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_answers_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
