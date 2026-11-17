-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2026 at 05:11 PM
-- Server version: 10.11.16-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gym_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `related_record` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `related_record`, `created_at`) VALUES
(1, NULL, 'Account created (seed)', 'users#1 (admin)', '2026-11-11 12:18:48'),
(2, NULL, 'Account created (seed)', 'users#2 (staff)', '2026-11-11 12:18:48'),
(3, 6, 'Changed Member ID 4 status to inactive', NULL, '2026-11-16 11:21:16'),
(4, 5, 'Approved Payment ID 2', NULL, '2026-11-16 16:04:00');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `member_id`, `time_in`, `time_out`, `recorded_by`, `created_at`) VALUES
(1, 4, '2026-11-16 15:06:26', '2026-11-16 15:08:06', 5, '2026-11-16 07:06:26'),
(2, 4, '2026-11-16 17:22:47', '2026-11-16 17:22:49', 5, '2026-11-16 09:22:47');

-- --------------------------------------------------------

--
-- Table structure for table `membership_plans`
--

CREATE TABLE `membership_plans` (
  `id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `membership_plans`
--

INSERT INTO `membership_plans` (`id`, `plan_name`, `price`, `duration_days`, `description`, `status`, `created_at`) VALUES
(1, 'Daily Pass', 100.00, 1, 'Full gym access for one day', 'active', '2026-11-11 12:10:43'),
(2, 'Monthly Plan', 1000.00, 30, 'Full gym access for 30 days', 'active', '2026-11-11 12:10:43'),
(3, 'Annual Plan', 10000.00, 365, 'Full gym access for one year', 'active', '2026-11-11 12:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `member_memberships`
--

CREATE TABLE `member_memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `plan_id` int(11) NOT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `membership_status` enum('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `member_memberships`
--

INSERT INTO `member_memberships` (`id`, `user_id`, `plan_id`, `payment_status`, `membership_status`, `start_date`, `end_date`, `created_at`) VALUES
(1, 4, 1, 'paid', 'active', '2026-11-16', '2026-11-17', '2026-11-16 05:26:56'),
(2, 7, 2, 'paid', 'active', '2026-11-17', '2026-12-17', '2026-11-16 13:22:23');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `membership_id` int(11) NOT NULL,
  `payment_method` enum('cash','gcash') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','rejected') NOT NULL DEFAULT 'pending',
  `reference_number` varchar(100) DEFAULT NULL,
  `proof_of_payment` varchar(255) DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `membership_id`, `payment_method`, `amount`, `payment_status`, `reference_number`, `proof_of_payment`, `verified_by`, `verified_at`, `created_at`) VALUES
(1, 1, 'cash', 100.00, 'paid', NULL, NULL, 5, '2026-11-16 14:37:02', '2026-11-16 06:36:11'),
(2, 2, 'cash', 1000.00, 'paid', NULL, NULL, 5, '2026-11-17 00:04:00', '2026-11-16 16:03:09');

-- --------------------------------------------------------

--
-- Table structure for table `staff_attendance`
--

CREATE TABLE `staff_attendance` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `time_in` datetime NOT NULL DEFAULT current_timestamp(),
  `time_out` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_attendance`
--

INSERT INTO `staff_attendance` (`id`, `staff_id`, `time_in`, `time_out`) VALUES
(1, 5, '2026-11-16 17:51:29', '2026-11-16 17:51:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','staff','member') NOT NULL DEFAULT 'member',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `role`, `is_verified`, `verification_token`, `token_expires_at`, `status`, `created_at`) VALUES
(4, 'Sarah Buan', 'bebsdoy23@gmail.com', '$2y$12$HXxASaitmsmk5rHWyf9eNOVBiJcI.qNOATNRg1xaGJLUb9X9CEYNS', 'member', 1, NULL, NULL, 'active', '2026-11-16 05:26:56'),
(5, 'Kinjo Calicdan', 'vanessabuan29@gmail.com', '$2y$12$rGzbwpQafaI.hLMev7PFZuBnF7H1dUA04uln6r0ef2gA/3MqwXTQW', 'staff', 1, NULL, NULL, 'active', '2026-11-16 06:15:44'),
(6, 'Adrian Bigornia', 'adrianbigornia5@gmail.com', '$2y$12$OO4yAQVT4XaYUeWO7v9yFu5Ll.exfnv0igaO6Av34BVqZga9ruTDG', 'admin', 1, NULL, NULL, 'active', '2026-11-16 08:29:53'),
(7, 'Kinjo Calicdan', 'lightskeenjokin@gmail.com', '$2y$12$jfuHAPCWEqUWYlZ7DFLp6uGYN/YiFCt.YVaR8ND3YIBfdZcJqcXjC', 'member', 1, NULL, NULL, 'active', '2026-11-16 13:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `weight_records`
--

CREATE TABLE `weight_records` (
  `id` int(11) NOT NULL,
  `member_id` int(10) UNSIGNED NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `weight_records`
--

INSERT INTO `weight_records` (`id`, `member_id`, `weight_kg`, `recorded_at`, `created_at`) VALUES
(1, 4, 65.50, '2026-11-16 15:31:47', '2026-11-16 07:31:47'),
(2, 4, 65.20, '2026-11-16 15:32:21', '2026-11-16 07:32:21'),
(3, 4, 65.00, '2026-11-16 15:32:26', '2026-11-16 07:32:26');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_logs_user` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attendance_staff` (`recorded_by`),
  ADD KEY `idx_attendance_member` (`member_id`),
  ADD KEY `idx_attendance_time_in` (`time_in`);

--
-- Indexes for table `membership_plans`
--
ALTER TABLE `membership_plans`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `member_memberships`
--
ALTER TABLE `member_memberships`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_member_user` (`user_id`),
  ADD KEY `fk_member_plan` (`plan_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_payment_membership` (`membership_id`),
  ADD KEY `fk_payment_staff` (`verified_by`);

--
-- Indexes for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_time_in` (`time_in`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `weight_records`
--
ALTER TABLE `weight_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_weight_member_date` (`member_id`,`recorded_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `membership_plans`
--
ALTER TABLE `membership_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `member_memberships`
--
ALTER TABLE `member_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `weight_records`
--
ALTER TABLE `weight_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_member` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_attendance_staff` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `member_memberships`
--
ALTER TABLE `member_memberships`
  ADD CONSTRAINT `fk_member_plan` FOREIGN KEY (`plan_id`) REFERENCES `membership_plans` (`id`),
  ADD CONSTRAINT `fk_member_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_membership` FOREIGN KEY (`membership_id`) REFERENCES `member_memberships` (`id`),
  ADD CONSTRAINT `fk_payment_staff` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_attendance`
--
ALTER TABLE `staff_attendance`
  ADD CONSTRAINT `fk_staff_attendance_user` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `weight_records`
--
ALTER TABLE `weight_records`
  ADD CONSTRAINT `fk_weight_member` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
