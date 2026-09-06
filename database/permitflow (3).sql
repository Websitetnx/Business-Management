-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2026 at 07:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `permitflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_analytics_reports`
--

CREATE TABLE `ai_analytics_reports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`metrics`)),
  `insights` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`insights`)),
  `model` varchar(80) NOT NULL,
  `generated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_analytics_reports`
--

INSERT INTO `ai_analytics_reports` (`id`, `metrics`, `insights`, `model`, `generated_by`, `created_at`) VALUES
(1, '{\"generated_at\":\"2026-08-28T06:23:00+08:00\",\"historical_applications\":1,\"current_backlog\":0,\"completed_applications\":1,\"average_processing_days\":0.5,\"estimated_backlog_clearance_days\":1,\"predicted_applications_next_7_days\":0,\"daily_trend_slope\":0.005,\"revision_rate_percent\":0,\"forecast_confidence_percent\":20,\"completed_ai_scans\":0,\"average_document_quality\":null,\"document_type_mismatches\":0,\"daily_series\":[{\"date\":\"2026-07-30\",\"count\":0},{\"date\":\"2026-07-31\",\"count\":0},{\"date\":\"2026-08-01\",\"count\":0},{\"date\":\"2026-08-02\",\"count\":0},{\"date\":\"2026-08-03\",\"count\":0},{\"date\":\"2026-08-04\",\"count\":0},{\"date\":\"2026-08-05\",\"count\":0},{\"date\":\"2026-08-06\",\"count\":0},{\"date\":\"2026-08-07\",\"count\":0},{\"date\":\"2026-08-08\",\"count\":0},{\"date\":\"2026-08-09\",\"count\":0},{\"date\":\"2026-08-10\",\"count\":0},{\"date\":\"2026-08-11\",\"count\":0},{\"date\":\"2026-08-12\",\"count\":0},{\"date\":\"2026-08-13\",\"count\":0},{\"date\":\"2026-08-14\",\"count\":0},{\"date\":\"2026-08-15\",\"count\":0},{\"date\":\"2026-08-16\",\"count\":0},{\"date\":\"2026-08-17\",\"count\":0},{\"date\":\"2026-08-18\",\"count\":0},{\"date\":\"2026-08-19\",\"count\":0},{\"date\":\"2026-08-20\",\"count\":0},{\"date\":\"2026-08-21\",\"count\":0},{\"date\":\"2026-08-22\",\"count\":0},{\"date\":\"2026-08-23\",\"count\":0},{\"date\":\"2026-08-24\",\"count\":1},{\"date\":\"2026-08-25\",\"count\":0},{\"date\":\"2026-08-26\",\"count\":0},{\"date\":\"2026-08-27\",\"count\":0},{\"date\":\"2026-08-28\",\"count\":0}],\"seven_day_forecast\":[0,0,0,0,0,0,0]}', '{\"headline\":\"Workload is currently clear, but the dataset is too limited for a reliable forecast\",\"summary\":\"The aggregate metrics show 1 historical application, 1 completed application, and no current backlog. Average processing time is 0.5 days, and the estimated backlog clearance time is 1 day. No revisions or document-type mismatches were recorded. However, activity appears limited to a single application on August 24, with zero applications on the other 30 days shown. The seven-day forecast is zero applications, but its confidence is only 20%, so it should be treated as a tentative estimate requiring human judgment.\",\"workload_risk\":\"Low\",\"recommendations\":[\"Continue routine monitoring because the current backlog is zero and the recorded processing time is short.\",\"Validate that application intake and daily reporting are complete, since the dataset contains only one application across the 30-day series.\",\"Avoid capacity reductions based solely on the zero-application forecast; reassess after additional weeks of observed intake data.\",\"Begin tracking AI scan volume and document-quality metrics once available, as no completed AI scans or document-quality score has been recorded.\",\"Review the trend and forecast after new applications are logged, particularly if intake begins to increase from the current near-zero baseline.\"],\"limitations\":\"This is an aggregate operational view only and does not support decisions on individual applications. The sample is extremely small: 1 application, no AI scans, and a 20% forecast confidence level. The forecast and one-day clearance estimate are therefore uncertain and require human judgment. Null document-quality data also prevents assessment of document quality trends.\"}', 'gpt-5.6-luna', 1, '2026-08-27 22:23:06');

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `business_id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(30) NOT NULL,
  `permit_number` varchar(30) DEFAULT NULL,
  `application_type` enum('New','Renewal') NOT NULL DEFAULT 'New',
  `status` enum('Submitted','For Review','Needs Revision','Approved - Awaiting Payment','Payment Submitted','Paid','Released','Rejected') NOT NULL DEFAULT 'Submitted',
  `stage` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `declared_capital` decimal(14,2) DEFAULT NULL,
  `gross_sales` decimal(14,2) DEFAULT NULL,
  `requires_building_inspection` tinyint(1) NOT NULL DEFAULT 0,
  `requires_electrical_inspection` tinyint(1) NOT NULL DEFAULT 0,
  `requires_plumbing_inspection` tinyint(1) NOT NULL DEFAULT 0,
  `applicant_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `user_id`, `business_id`, `reference`, `permit_number`, `application_type`, `status`, `stage`, `declared_capital`, `gross_sales`, `requires_building_inspection`, `requires_electrical_inspection`, `requires_plumbing_inspection`, `applicant_notes`, `admin_notes`, `submitted_at`, `reviewed_at`, `approved_at`, `updated_at`) VALUES
(1, 2, 1, 'BPL-2026-35771', 'BP-2026-55868', 'New', '', 3, NULL, NULL, 0, 0, 0, NULL, 'done', '2026-08-25 06:08:04', '2026-08-25 20:01:42', '2026-08-25 19:12:48', '2026-08-25 20:01:42'),
(2, 5, 2, 'BPL-2026-59179', NULL, 'New', 'Needs Revision', 1, 10000.00, NULL, 1, 1, 1, NULL, NULL, '2026-09-02 00:49:50', NULL, NULL, '2026-09-02 00:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `application_documents`
--

CREATE TABLE `application_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(120) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_size` bigint(20) UNSIGNED NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_documents`
--

INSERT INTO `application_documents` (`id`, `application_id`, `document_type`, `original_name`, `stored_name`, `mime_type`, `file_size`, `uploaded_at`) VALUES
(1, 1, 'registration_doc', 'Screenshot_2025-09-30-15-23-56-89_439a3fec0400f8974d35eed09a31f914.jpg', '80301dce2b24769771a6d9c5d827bcd31e17c9b09482bdb3.jpg', 'image/jpeg', 968526, '2026-08-25 06:08:04'),
(2, 1, 'bfp_application_doc', 'Screenshot_2025-09-30-15-23-56-89_439a3fec0400f8974d35eed09a31f914.jpg', '8309d2a1653f871798beb39e0349f109055bde214107b42a.jpg', 'image/jpeg', 968526, '2026-08-25 06:08:04'),
(3, 1, 'bfp_questionnaire_doc', 'Screenshot_2025-09-30-15-23-56-89_439a3fec0400f8974d35eed09a31f914.jpg', '84c4709c3cc5f2cef3ec6fba754d8cbf8e5be5792a57c422.jpg', 'image/jpeg', 968526, '2026-08-25 06:08:04'),
(4, 1, 'consent_form_doc', 'Screenshot_2025-09-30-15-23-56-89_439a3fec0400f8974d35eed09a31f914.jpg', '7c6cd49f92d22eb7145bd924b30fc4c7766fa39339468776.jpg', 'image/jpeg', 968526, '2026-08-25 06:08:04'),
(5, 1, 'occupancy_doc', 'Screenshot_2025-09-30-15-23-56-89_439a3fec0400f8974d35eed09a31f914.jpg', '9ea0b760e7d6391262087fcb6c6e6e2e580cc4cbfe524e1a.jpg', 'image/jpeg', 968526, '2026-08-25 06:08:04'),
(6, 2, 'registration_doc', 'logo light.png', '2e4895fda1c0ba95d4ef57fd0c3c6c9056d65cf82be1e709.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(7, 2, 'bfp_application_doc', 'logo light.png', '3aca038d0c287ca2e40e3f266a7a3ced7960c12caf001479.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(8, 2, 'bfp_questionnaire_doc', 'logo light.png', '2fe4f140567c4c2fe118e8b7d50513b918da9019931fdaa6.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(9, 2, 'consent_form_doc', 'logo light.png', '01f1a7d7d58874f15e6b7dfb83c5ccba3835ca3655b761ae.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(10, 2, 'fsic_occupancy_doc', 'logo light.png', 'c696e91aa75f895170dabe03bc52c6a3a7ca46045359ab5e.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(11, 2, 'occupancy_doc', 'logo light.png', 'bfad73e9988a3626a8affdb1bd06152dab5a04ef2019e9aa.png', 'image/png', 94103, '2026-09-02 00:49:50'),
(12, 2, 'occupancy_affidavit_doc', 'logo light.png', '245f8b611a66556b4d54b43f134e8a498a965874aea4efd0.png', 'image/png', 94103, '2026-09-02 00:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `application_status_history`
--

CREATE TABLE `application_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(40) NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `application_status_history`
--

INSERT INTO `application_status_history` (`id`, `application_id`, `status`, `notes`, `changed_by`, `created_at`) VALUES
(1, 1, 'For Review', 'New application submitted.', 2, '2026-08-25 06:08:04'),
(2, 1, 'Approved', NULL, 1, '2026-08-25 19:12:48'),
(3, 1, 'Needs Revision', 'cause it lact of documents', 1, '2026-08-25 19:27:31'),
(4, 1, 'Approved', 'done', 1, '2026-08-25 20:01:42'),
(5, 2, 'Needs Revision', 'Auto-scan detected document issues requiring correction.', 5, '2026-09-02 00:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `created_at`) VALUES
(1, 1, 'create_first_admin', 'user', 1, '::1', '2026-08-25 05:52:09'),
(2, 1, 'login', 'user', 1, '::1', '2026-08-25 05:52:14'),
(3, 2, 'register', 'user', 2, '::1', '2026-08-25 05:54:28'),
(4, 1, 'login', 'user', 1, '::1', '2026-08-25 06:03:22'),
(5, 2, 'login', 'user', 2, '::1', '2026-08-25 06:04:15'),
(6, 2, 'submit_application', 'application', 1, '::1', '2026-08-25 06:08:04'),
(7, 1, 'login', 'user', 1, '::1', '2026-08-25 06:09:13'),
(8, 2, 'login', 'user', 2, '::1', '2026-08-25 06:48:24'),
(9, 1, 'login', 'user', 1, '::1', '2026-08-25 06:49:31'),
(10, 1, 'login', 'user', 1, '::1', '2026-08-25 06:51:48'),
(11, 1, 'login', 'user', 1, '::1', '2026-08-25 17:39:49'),
(12, 2, 'login', 'user', 2, '::1', '2026-08-25 18:13:42'),
(13, 1, 'login', 'user', 1, '::1', '2026-08-25 18:19:09'),
(14, 2, 'login', 'user', 2, '::1', '2026-08-25 19:09:37'),
(15, 2, 'login', 'user', 2, '::1', '2026-08-25 19:09:58'),
(16, 1, 'login', 'user', 1, '::1', '2026-08-25 19:10:59'),
(17, 1, 'update_status_approved', 'application', 1, '::1', '2026-08-25 19:12:48'),
(18, 2, 'login', 'user', 2, '::1', '2026-08-25 19:13:02'),
(19, 2, 'login', 'user', 2, '::1', '2026-08-25 19:13:10'),
(20, 1, 'login', 'user', 1, '::1', '2026-08-25 19:15:20'),
(21, 1, 'update_status_needs_revision', 'application', 1, '::1', '2026-08-25 19:27:31'),
(22, 2, 'login', 'user', 2, '::1', '2026-08-25 19:27:38'),
(23, 1, 'login', 'user', 1, '::1', '2026-08-25 20:00:40'),
(24, 1, 'update_status_approved', 'application', 1, '::1', '2026-08-25 20:01:42'),
(25, 2, 'login', 'user', 2, '::1', '2026-08-25 20:01:49'),
(26, 2, 'submit_payment', 'payment', 1, '::1', '2026-08-25 20:08:32'),
(27, 1, 'login', 'user', 1, '::1', '2026-08-25 20:08:43'),
(28, 1, 'verify_payment', 'payment', 1, '::1', '2026-08-25 20:09:33'),
(29, 2, 'login', 'user', 2, '::1', '2026-08-25 20:10:26'),
(30, 1, 'login', 'user', 1, '::1', '2026-08-25 20:14:35'),
(31, 1, 'login', 'user', 1, '::1', '2026-08-25 20:32:33'),
(32, 2, 'login', 'user', 2, '::1', '2026-08-25 20:41:18'),
(33, 1, 'login', 'user', 1, '::1', '2026-08-25 20:46:40'),
(34, 1, 'login', 'user', 1, '::1', '2026-08-25 20:53:04'),
(35, 2, 'login', 'user', 2, '::1', '2026-08-25 21:01:54'),
(36, 2, 'login', 'user', 2, '::1', '2026-08-25 21:26:57'),
(37, 2, 'login', 'user', 2, '::1', '2026-08-25 21:56:57'),
(38, 1, 'login', 'user', 1, '::1', '2026-08-25 21:57:04'),
(39, 2, 'login', 'user', 2, '::1', '2026-08-25 22:09:15'),
(40, 2, 'login', 'user', 2, '::1', '2026-08-25 22:15:39'),
(41, 1, 'login', 'user', 1, '::1', '2026-08-25 22:16:24'),
(42, 2, 'login', 'user', 2, '::1', '2026-08-25 22:19:20'),
(43, 1, 'login', 'user', 1, '::1', '2026-08-25 23:49:10'),
(44, 2, 'login', 'user', 2, '::1', '2026-08-25 23:53:51'),
(45, 1, 'login', 'user', 1, '::1', '2026-08-25 23:54:50'),
(46, 1, 'login', 'user', 1, '::1', '2026-08-26 20:45:01'),
(47, 1, 'login', 'user', 1, '::1', '2026-08-27 20:22:35'),
(48, 1, 'login', 'user', 1, '::1', '2026-08-27 21:33:45'),
(49, 1, 'login', 'user', 1, '::1', '2026-08-27 21:51:37'),
(50, 1, 'generate_ai_analytics', 'ai_analytics_report', 1, '::1', '2026-08-27 22:23:06'),
(51, 1, 'ai_scan_document', 'application_document', 1, '::1', '2026-08-27 22:24:29'),
(52, 1, 'login', 'user', 1, '::1', '2026-08-28 04:48:41'),
(53, 2, 'login', 'user', 2, '::1', '2026-08-28 04:57:42'),
(54, 2, 'login', 'user', 2, '::1', '2026-08-28 04:59:58'),
(55, 2, 'login', 'user', 2, '::1', '2026-08-28 05:08:20'),
(56, 1, 'login', 'user', 1, '::1', '2026-08-28 05:16:58'),
(57, 2, 'login', 'user', 2, '::1', '2026-08-28 05:26:19'),
(58, 1, 'login', 'user', 1, '::1', '2026-08-28 05:43:25'),
(59, 1, 'login', 'user', 1, '::1', '2026-08-28 19:58:33'),
(60, 4, 'login', 'user', 4, '::1', '2026-08-28 20:14:40'),
(61, 4, 'login', 'user', 4, '::1', '2026-08-28 20:21:07'),
(62, 1, 'login', 'user', 1, '::1', '2026-08-28 20:34:28'),
(63, 2, 'login', 'user', 2, '::1', '2026-08-30 06:39:29'),
(64, 1, 'login', 'user', 1, '::1', '2026-08-30 06:43:47'),
(65, 1, 'login', 'user', 1, '::1', '2026-08-31 09:25:55'),
(66, 1, 'login', 'user', 1, '::1', '2026-09-02 00:42:49'),
(67, 5, 'register', 'user', 5, '::1', '2026-09-02 00:46:57'),
(68, 5, 'ai_auto_scan_attempt', 'application_document', 6, '::1', '2026-09-02 00:49:50'),
(69, 5, 'ai_auto_scan_document', 'application_document', 6, '::1', '2026-09-02 00:49:57'),
(70, 5, 'ai_auto_scan_attempt', 'application_document', 7, '::1', '2026-09-02 00:49:57'),
(71, 5, 'ai_auto_scan_document', 'application_document', 7, '::1', '2026-09-02 00:50:00'),
(72, 5, 'ai_auto_scan_attempt', 'application_document', 8, '::1', '2026-09-02 00:50:00'),
(73, 5, 'ai_auto_scan_document', 'application_document', 8, '::1', '2026-09-02 00:50:03'),
(74, 5, 'ai_auto_scan_attempt', 'application_document', 9, '::1', '2026-09-02 00:50:03'),
(75, 5, 'ai_auto_scan_document', 'application_document', 9, '::1', '2026-09-02 00:50:07'),
(76, 5, 'ai_auto_scan_attempt', 'application_document', 10, '::1', '2026-09-02 00:50:07'),
(77, 5, 'ai_auto_scan_document', 'application_document', 10, '::1', '2026-09-02 00:50:11'),
(78, 5, 'ai_auto_scan_attempt', 'application_document', 11, '::1', '2026-09-02 00:50:11'),
(79, 5, 'ai_auto_scan_document', 'application_document', 11, '::1', '2026-09-02 00:50:15'),
(80, 5, 'ai_auto_scan_attempt', 'application_document', 12, '::1', '2026-09-02 00:50:15'),
(81, 5, 'ai_auto_scan_document', 'application_document', 12, '::1', '2026-09-02 00:50:18'),
(82, 5, 'submit_application', 'application', 2, '::1', '2026-09-02 00:50:18'),
(83, 1, 'login', 'user', 1, '::1', '2026-09-02 00:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `businesses`
--

CREATE TABLE `businesses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `business_name` varchar(190) NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `organization_type` varchar(100) NOT NULL,
  `tin` varchar(30) NOT NULL,
  `contact` varchar(40) NOT NULL,
  `email` varchar(190) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location_accuracy_m` decimal(10,2) DEFAULT NULL,
  `location_captured_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `businesses`
--

INSERT INTO `businesses` (`id`, `user_id`, `business_name`, `business_type`, `organization_type`, `tin`, `contact`, `email`, `address`, `latitude`, `longitude`, `location_accuracy_m`, `location_captured_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'sari sari store nanays', 'Food and Beverage', 'Partnership', '242422353523', '323523532523', 'patrickpatricio@wvsu.edu.ph', 'Talaban himamaylan city negros occidental', NULL, NULL, NULL, NULL, '2026-08-25 06:08:04', '2026-08-25 06:08:04'),
(2, 5, 'daday\'s empanada', 'Food and Beverage', 'Sole Proprietorship', '463728193029', '09123456789', 'golo@gmail.com', 'Talaban himamaylan city negros occidental', 10.0765381, 122.8676314, 212.00, '2026-09-02 15:49:50', '2026-09-02 00:49:50', '2026-09-02 00:49:50');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `size` varchar(100) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_ai_scans`
--

CREATE TABLE `document_ai_scans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `document_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `scan_status` enum('Completed','Failed') NOT NULL,
  `detected_document_type` varchar(190) DEFAULT NULL,
  `matches_expected_type` tinyint(1) DEFAULT NULL,
  `quality_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `confidence_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `extracted_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extracted_fields`)),
  `issues` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`issues`)),
  `summary` text DEFAULT NULL,
  `requires_human_review` tinyint(1) NOT NULL DEFAULT 1,
  `model` varchar(80) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `scanned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `document_ai_scans`
--

INSERT INTO `document_ai_scans` (`id`, `document_id`, `application_id`, `scan_status`, `detected_document_type`, `matches_expected_type`, `quality_score`, `confidence_score`, `extracted_fields`, `issues`, `summary`, `requires_human_review`, `model`, `error_message`, `scanned_by`, `scanned_at`, `updated_at`) VALUES
(1, 1, 1, 'Completed', 'Business Model Canvas Rubric', 0, 72, 99, '[{\"field\":\"Document title\",\"value\":\"BUSINESS MODEL CANVAS RUBRIC\",\"confidence\":99},{\"field\":\"Document content\",\"value\":\"Rubric covering product idea, customer segments, value proposition, channels, customer relationships, revenue streams, key partners, key activities, key resources, and cost structure\",\"confidence\":96},{\"field\":\"Expected registration document\",\"value\":\"DTI / SEC / CDA Registration\",\"confidence\":99}]', '[\"The visible document is a business model canvas rubric, not a DTI, SEC, or CDA registration certificate/document.\",\"No DTI Certificate of Business Name Registration, SEC registration certificate, or CDA registration certificate is visible.\",\"No registration number, registrant/entity name, issuance date, or registration date is visible for the expected document type.\",\"The image is a mobile screenshot with application interface elements and an advertisement; it is not a clean scan of a registration document.\",\"Some rubric text is small and may be difficult to read, although the document type is clear.\"]', 'The uploaded image visibly contains a Business Model Canvas Rubric and does not show the expected DTI, SEC, or CDA Registration document. A human BPLO reviewer should request the appropriate registration certificate or verify that the correct file was uploaded.', 1, 'gpt-5.6-luna', NULL, 1, '2026-08-27 22:24:29', '2026-08-27 22:24:29'),
(3, 2, 1, 'Failed', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, 'gpt-5.6-luna', 'AI service error: You exceeded your current quota, please check your plan and billing details. For more information on this error, read the docs: https://platform.openai.com/docs/guides/error-codes/api-errors.', 1, '2026-08-25 06:34:51', '2026-08-25 06:34:51'),
(9, 6, 2, 'Completed', 'Unidentifiable blank/obscured image', 0, 5, 98, '[]', '[\"No visible document text, registration details, logo, stamp, signature, or date.\",\"Image appears substantially blank or obscured by a uniform blue gradient.\",\"Unable to determine whether this is a DTI, SEC, or CDA registration document.\",\"A clearer, properly captured scan is needed for review.\"]', 'No usable document evidence is visible. The submission cannot be compared with the expected DTI / SEC / CDA Registration requirement.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:49:57', '2026-09-02 00:49:57'),
(10, 7, 2, 'Completed', 'Unclear; no readable document content visible', 0, 5, 99, '[]', '[\"The image appears to show a mostly blank blue area with black borders or obscured content.\",\"No readable title, form fields, applicant details, signatures, dates, or BFP information are visible.\",\"The document cannot be assessed as a BFP Application Form from the visible evidence.\"]', 'The uploaded image is not legible enough to identify or review a BFP Application Form.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:00', '2026-09-02 00:50:00'),
(11, 8, 2, 'Completed', 'Blank or unidentifiable image', 0, 5, 99, '[]', '[\"No visible document text, form, title, or identifying content.\",\"Image appears to contain only a blue gradient/blank area.\",\"BFP Questionnaire cannot be verified from the provided image.\",\"No signatures, dates, or other fields are visible.\"]', 'The uploaded image does not show a recognizable BFP Questionnaire or other permit document. A clear, complete scan is needed for BPLO review.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:03', '2026-09-02 00:50:03'),
(12, 9, 2, 'Completed', '', 0, 5, 99, '[]', '[\"No recognizable document content is visible.\",\"The image appears to show a mostly blank blue/gray area with no readable text, form fields, signature, date, or identifying details.\",\"Unable to determine whether this is a Consent Form.\"]', 'The submitted image does not provide visible evidence of a Consent Form and cannot be assessed for the expected requirement.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:07', '2026-09-02 00:50:07'),
(13, 10, 2, 'Completed', 'No document visible; blank/obscured image', 0, 0, 100, '[]', '[\"The image does not show a readable document or permit content.\",\"No evidence of an FSIC of Occupancy is visible.\",\"The document title, validity period, signatures, dates, issuer, and reference details cannot be assessed.\"]', 'The uploaded image appears blank or fully obscured and cannot be evaluated against the expected requirement: FSIC of Occupancy valid for 9 months.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:11', '2026-09-02 00:50:11'),
(14, 11, 2, 'Completed', '', 0, 5, 99, '[]', '[\"No visible document content, text, form, seal, signature, or date is present.\",\"The image appears to show a mostly blank blue gradient area with black margins.\",\"Unable to assess whether an Occupancy Permit is provided.\"]', 'No legible permit document is visible in the uploaded image, so an Occupancy Permit cannot be identified or assessed.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:15', '2026-09-02 00:50:15'),
(15, 12, 2, 'Completed', 'No discernible document', 0, 0, 99, '[]', '[\"The image contains only a blank blue gradient area with no visible document content.\",\"No affidavit title, text, identification details, signature, date, or issuing information is visible.\",\"The submission cannot be assessed as an Affidavit of Undertaking in Absence of Occupancy.\"]', 'No readable permit-related document is visible in the submitted image.', 1, 'gpt-5.6-luna', NULL, 5, '2026-09-02 00:50:18', '2026-09-02 00:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `application_id`, `message`, `read_at`, `created_at`) VALUES
(1, 2, 1, 'Application BPL-2026-35771 was updated to Approved.', '2026-08-30 06:39:38', '2026-08-25 19:12:48'),
(2, 2, 1, 'Application BPL-2026-35771 was updated to Needs Revision.', '2026-08-30 06:39:38', '2026-08-25 19:27:31'),
(3, 2, 1, 'Application BPL-2026-35771 was approved. Assessed permit fee: ₱500.00. Submit payment from your application page.', '2026-08-30 06:39:38', '2026-08-25 20:01:42'),
(4, 1, 1, 'Payment submitted for application BPL-2026-35771 and is awaiting verification.', '2026-08-30 06:44:21', '2026-08-25 20:08:32'),
(5, 2, 1, 'Payment for application BPL-2026-35771 was verified. Receipt OR-2026-408895 is available. Your permit is now eligible for release.', '2026-08-30 06:39:38', '2026-08-25 20:09:33'),
(6, 5, 2, 'Automated verification found 7 documents needing revision in application BPL-2026-59179. Open the application to replace the listed files.', NULL, '2026-09-02 00:50:18'),
(7, 5, 2, 'BPL-2026-59179 - DTI / SEC / CDA Registration failed verification: Expected \"DTI / SEC / CDA Registration\", but detected \"Unidentifiable blank/obscured image\". Document quality is too low (5%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(8, 5, 2, 'BPL-2026-59179 - BFP Application Form failed verification: Expected \"BFP Application Form\", but detected \"Unclear; no readable document content visible\". Document quality is too low (5%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(9, 5, 2, 'BPL-2026-59179 - BFP Questionnaire failed verification: Expected \"BFP Questionnaire\", but detected \"Blank or unidentifiable image\". Document quality is too low (5%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(10, 5, 2, 'BPL-2026-59179 - Consent Form failed verification: The uploaded file does not match the expected \"Consent Form\" document type. Document quality is too low (5%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(11, 5, 2, 'BPL-2026-59179 - FSIC of Occupancy Valid for 9 Months failed verification: Expected \"FSIC of Occupancy Valid for 9 Months\", but detected \"No document visible; blank/obscured image\". Document quality is too low (0%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(12, 5, 2, 'BPL-2026-59179 - Occupancy Permit failed verification: The uploaded file does not match the expected \"Occupancy Permit\" document type. Document quality is too low (5%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18'),
(13, 5, 2, 'BPL-2026-59179 - Affidavit of Undertaking in Absence of Occupancy failed verification: Expected \"Affidavit of Undertaking in Absence of Occupancy\", but detected \"No discernible document\". Document quality is too low (0%). The minimum is 40%. Please re-upload a corrected copy.', NULL, '2026-09-02 00:50:18');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `zip_code` varchar(10) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('COD','GCash') NOT NULL DEFAULT 'COD',
  `status` enum('Pending','Confirmed','Processing','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `size` varchar(100) DEFAULT NULL,
  `color` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `application_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `assessment_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assessment_breakdown`)),
  `assessed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `assessed_at` timestamp NULL DEFAULT NULL,
  `payment_method` enum('GCash','Maya','Bank Transfer','City Treasurer Counter') DEFAULT NULL,
  `payer_name` varchar(120) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Paid','Failed','Refunded') NOT NULL DEFAULT 'Pending',
  `proof_original_name` varchar(255) DEFAULT NULL,
  `proof_stored_name` varchar(120) DEFAULT NULL,
  `proof_mime_type` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(40) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) UNSIGNED DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `application_id`, `amount`, `assessment_breakdown`, `assessed_by`, `assessed_at`, `payment_method`, `payer_name`, `payment_reference`, `status`, `proof_original_name`, `proof_stored_name`, `proof_mime_type`, `receipt_number`, `submitted_at`, `paid_at`, `verified_by`, `verified_at`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 500.00, NULL, NULL, NULL, 'GCash', 'Patrick T Patricio', '131312423', 'Paid', 'LOGO.png', 'a9747ff64e3625808cbc0fba0163db82eb542310f50ee01e.png', 'image/png', 'OR-2026-408895', '2026-08-25 20:08:32', '2026-08-25 20:09:33', 1, '2026-08-25 20:09:33', NULL, '2026-08-25 20:01:42', '2026-08-25 20:09:33');

-- --------------------------------------------------------

--
-- Table structure for table `permit_business_type_rates`
--

CREATE TABLE `permit_business_type_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_type` varchar(100) NOT NULL,
  `new_lbt_rate_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `renewal_lbt_rate_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `mayors_permit_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permit_business_type_rates`
--

INSERT INTO `permit_business_type_rates` (`id`, `business_type`, `new_lbt_rate_percent`, `renewal_lbt_rate_percent`, `mayors_permit_fee`, `is_active`, `updated_at`) VALUES
(1, 'Retail', 0.0000, 0.0000, 0.00, 1, '2026-08-25 23:45:50'),
(2, 'Food and Beverage', 0.0000, 0.0000, 0.00, 1, '2026-08-25 23:45:50'),
(3, 'Professional Services', 0.0000, 0.0000, 0.00, 1, '2026-08-25 23:45:50'),
(4, 'Manufacturing', 0.0000, 0.0000, 0.00, 1, '2026-08-25 23:45:50'),
(5, 'Other', 0.0000, 0.0000, 0.00, 1, '2026-08-25 23:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `permit_fee_settings`
--

CREATE TABLE `permit_fee_settings` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `lgu_name` varchar(190) NOT NULL DEFAULT 'Local Government Unit',
  `sanitary_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `zoning_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `general_inspection_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `building_inspection_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `electrical_inspection_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `plumbing_inspection_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `barangay_clearance_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `community_tax_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bfp_rate_percent` decimal(7,4) NOT NULL DEFAULT 0.0000,
  `bfp_minimum_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_configured` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permit_fee_settings`
--

INSERT INTO `permit_fee_settings` (`id`, `lgu_name`, `sanitary_fee`, `zoning_fee`, `general_inspection_fee`, `building_inspection_fee`, `electrical_inspection_fee`, `plumbing_inspection_fee`, `barangay_clearance_fee`, `community_tax_fee`, `bfp_rate_percent`, `bfp_minimum_fee`, `is_configured`, `updated_by`, `updated_at`) VALUES
(1, 'Local Government Unit', 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0.0000, 0.00, 0, NULL, '2026-08-25 23:45:50');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `gender` enum('Men','Women','Unisex') DEFAULT 'Unisex',
  `sizes` varchar(255) DEFAULT NULL,
  `colors` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `discount` int(11) DEFAULT 0,
  `stock` int(11) DEFAULT 0,
  `rating` decimal(2,1) DEFAULT 4.5,
  `sold` int(11) DEFAULT 0,
  `featured` tinyint(1) DEFAULT 0,
  `new_arrival` tinyint(1) DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('Available','Out of Stock') DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('applicant','admin','treasurer') NOT NULL DEFAULT 'applicant',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Allyna', 'allynavargas@wvsu.edu.ph', '$2y$10$4cjinZOCfjTz8HCvBruBtetRMHt8IIvaE37KNk1ZbBu3m2wud4RDG', 'admin', 1, '2026-08-25 05:52:09', '2026-08-25 05:52:09'),
(2, 'Patrick T Patricio', 'patrickpatricio@wvsu.edu.ph', '$2y$10$VEcTTu/1Io/yeHhSOayFy.uvRnsFU174eCyc4IWENbBw/IoWUUOFe', 'applicant', 1, '2026-08-25 05:54:28', '2026-08-25 05:54:28'),
(3, 'City Mayor / Chief Admin', 'admin@permitflow.gov.ph', '$2y$10$dGmMIzscLtoMEPGzX8mjbumY/YnBAukPHtB3mrATrDs.LELWtJhNW', 'admin', 1, '2026-08-28 20:08:01', '2026-08-28 20:13:40'),
(4, 'Municipal Treasurer Office', 'treasurer@permitflow.gov.ph', '$2y$10$dGmMIzscLtoMEPGzX8mjbumY/YnBAukPHtB3mrATrDs.LELWtJhNW', 'treasurer', 1, '2026-08-28 20:08:01', '2026-08-28 20:13:40'),
(5, 'go lo', 'golo@gmail.com', '$2y$10$Yk9Atm1TXTvPuu76UDp.1.BZjiRvq9eoXm9j9AcE52Ztgg8RmmXRW', 'applicant', 1, '2026-09-02 00:46:57', '2026-09-02 00:46:57');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_analytics_reports`
--
ALTER TABLE `ai_analytics_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ai_report_user` (`generated_by`),
  ADD KEY `idx_ai_report_created` (`created_at`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `idx_application_user` (`user_id`),
  ADD KEY `idx_application_permit` (`permit_number`),
  ADD KEY `idx_application_status` (`status`),
  ADD KEY `idx_application_business` (`business_id`),
  ADD KEY `idx_application_submitted` (`submitted_at`);

--
-- Indexes for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stored_name` (`stored_name`),
  ADD KEY `idx_document_application` (`application_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_user` (`changed_by`),
  ADD KEY `idx_history_application` (`application_id`),
  ADD KEY `idx_history_created` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `businesses`
--
ALTER TABLE `businesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_business_user` (`user_id`),
  ADD KEY `idx_business_name` (`business_name`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `document_ai_scans`
--
ALTER TABLE `document_ai_scans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `document_id` (`document_id`),
  ADD KEY `fk_ai_scan_user` (`scanned_by`),
  ADD KEY `idx_ai_scan_application` (`application_id`),
  ADD KEY `idx_ai_scan_status` (`scan_status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notification_application` (`application_id`),
  ADD KEY `idx_notification_user` (`user_id`,`read_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_payment_application` (`application_id`),
  ADD UNIQUE KEY `uq_payment_receipt` (`receipt_number`),
  ADD KEY `idx_payment_application` (`application_id`),
  ADD KEY `idx_payment_status` (`status`),
  ADD KEY `fk_payment_verifier` (`verified_by`),
  ADD KEY `fk_payment_assessor` (`assessed_by`);

--
-- Indexes for table `permit_business_type_rates`
--
ALTER TABLE `permit_business_type_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `business_type` (`business_type`);

--
-- Indexes for table `permit_fee_settings`
--
ALTER TABLE `permit_fee_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_fee_settings_user` (`updated_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_analytics_reports`
--
ALTER TABLE `ai_analytics_reports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `application_documents`
--
ALTER TABLE `application_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `application_status_history`
--
ALTER TABLE `application_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `businesses`
--
ALTER TABLE `businesses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `document_ai_scans`
--
ALTER TABLE `document_ai_scans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `permit_business_type_rates`
--
ALTER TABLE `permit_business_type_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_analytics_reports`
--
ALTER TABLE `ai_analytics_reports`
  ADD CONSTRAINT `fk_ai_report_user` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `fk_application_business` FOREIGN KEY (`business_id`) REFERENCES `businesses` (`id`),
  ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_documents`
--
ALTER TABLE `application_documents`
  ADD CONSTRAINT `fk_document_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `application_status_history`
--
ALTER TABLE `application_status_history`
  ADD CONSTRAINT `fk_history_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `businesses`
--
ALTER TABLE `businesses`
  ADD CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `document_ai_scans`
--
ALTER TABLE `document_ai_scans`
  ADD CONSTRAINT `fk_ai_scan_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_scan_document` FOREIGN KEY (`document_id`) REFERENCES `application_documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ai_scan_user` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payment_assessor` FOREIGN KEY (`assessed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_payment_verifier` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `permit_fee_settings`
--
ALTER TABLE `permit_fee_settings`
  ADD CONSTRAINT `fk_fee_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
