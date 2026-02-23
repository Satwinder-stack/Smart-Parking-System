-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 12, 2025 at 06:04 PM
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
-- Database: `parking_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_credentials`
--

CREATE TABLE `admin_credentials` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `usb_device_id` varchar(100) DEFAULT NULL,
  `recovery_phrase` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `failed_attempts` int(11) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_credentials`
--

INSERT INTO `admin_credentials` (`id`, `username`, `password`, `usb_device_id`, `recovery_phrase`, `created_at`, `failed_attempts`, `last_login`, `account_locked`, `lockout_until`, `updated_at`) VALUES
(2, 'admin', '$2y$10$iCl4WoGY9AZSzy4roqpOkeG2cG6MUBm.xb3YoCjjIrueQzBsSrVsm', '8087-0026-noserial', '$2y$10$yyThcvHjqNLFQJFbUc5fBebW7/Q5WfpWkUyj/vaiuGCyImfQm8lxW', '2025-06-08 13:35:45', 5, '2025-06-10 18:07:08', 0, NULL, '2025-06-11 15:35:52');

-- --------------------------------------------------------

--
-- Table structure for table `available_spots`
--

CREATE TABLE `available_spots` (
  `id` int(11) NOT NULL,
  `spot_number` varchar(10) NOT NULL,
  `status` enum('available','occupied') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `available_spots`
--

INSERT INTO `available_spots` (`id`, `spot_number`, `status`, `created_at`) VALUES
(1, 'P1', 'occupied', '2025-05-29 15:43:13'),
(2, 'P2', 'occupied', '2025-05-29 15:43:13'),
(3, 'P3', 'occupied', '2025-05-29 15:43:13'),
(4, 'P4', 'occupied', '2025-05-29 15:43:13'),
(5, 'P5', 'available', '2025-05-29 15:43:13'),
(6, 'P6', 'available', '2025-05-29 15:43:13'),
(7, 'P7', 'occupied', '2025-05-29 15:43:13'),
(8, 'P8', 'occupied', '2025-05-29 15:43:13'),
(9, 'P9', 'available', '2025-05-29 15:43:13'),
(10, 'P10', 'occupied', '2025-05-29 15:43:13'),
(11, 'P11', 'available', '2025-05-29 15:43:13'),
(12, 'P12', 'available', '2025-05-29 15:43:13'),
(13, 'P13', 'available', '2025-05-29 15:43:13'),
(14, 'P14', 'available', '2025-05-29 15:43:13');

-- --------------------------------------------------------

--
-- Table structure for table `occupied_spots`
--

CREATE TABLE `occupied_spots` (
  `id` int(11) NOT NULL,
  `spot_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `cost` decimal(10,2) NOT NULL,
  `status` enum('reserved','ongoing','completed') DEFAULT 'reserved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `occupied_spots`
--

INSERT INTO `occupied_spots` (`id`, `spot_id`, `user_id`, `reservation_date`, `end_date`, `start_time`, `end_time`, `cost`, `status`, `created_at`) VALUES
(167, 1, 5, '2025-06-13', '2025-06-13', '00:03:00', '23:59:00', 1200.00, 'ongoing', '2025-06-12 16:03:32');

-- --------------------------------------------------------

--
-- Table structure for table `parking_spots`
--

CREATE TABLE `parking_spots` (
  `id` int(11) NOT NULL,
  `spot_number` varchar(10) NOT NULL,
  `status` enum('available','occupied') DEFAULT 'available',
  `user_id` int(11) DEFAULT NULL,
  `entry_time` datetime DEFAULT NULL,
  `exit_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_spots`
--

INSERT INTO `parking_spots` (`id`, `spot_number`, `status`, `user_id`, `entry_time`, `exit_time`) VALUES
(1, 'P1', 'available', NULL, NULL, NULL),
(2, 'P2', 'available', NULL, NULL, NULL),
(3, 'P3', 'available', NULL, NULL, NULL),
(4, 'P4', 'available', NULL, NULL, NULL),
(5, 'P5', 'available', NULL, NULL, NULL),
(6, 'P6', 'available', NULL, NULL, NULL),
(7, 'P7', 'available', NULL, NULL, NULL),
(8, 'P8', 'available', NULL, NULL, NULL),
(9, 'P9', 'available', NULL, NULL, NULL),
(10, 'P10', 'available', NULL, NULL, NULL),
(11, 'P11', 'available', NULL, NULL, NULL),
(12, 'P12', 'available', NULL, NULL, NULL),
(13, 'P13', 'available', NULL, NULL, NULL),
(14, 'P14', 'available', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `spot_id` int(11) NOT NULL,
  `reservation_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `entry_time` datetime DEFAULT NULL,
  `exit_time` datetime DEFAULT NULL,
  `cost` decimal(10,2) NOT NULL,
  `status` enum('reserved','pending','completed','cancelled') DEFAULT 'reserved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usb_device_names`
--

CREATE TABLE `usb_device_names` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `custom_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usb_device_names`
--

INSERT INTO `usb_device_names` (`id`, `admin_id`, `device_id`, `custom_name`, `created_at`, `updated_at`) VALUES
(1, 2, '8087-0026-noserial', 'Key', '2025-06-11 15:36:00', '2025-06-11 15:36:29');

-- --------------------------------------------------------

--
-- Table structure for table `usb_key_codes`
--

CREATE TABLE `usb_key_codes` (
  `id` int(11) NOT NULL,
  `expected_code_hash` varchar(255) NOT NULL,
  `file_content_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `usb_key_codes`
--

INSERT INTO `usb_key_codes` (`id`, `expected_code_hash`, `file_content_hash`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '$2y$10$L0FzJ3fWEDi1zOHMOxCBe.fG9i60kIPcrWWPMQrTb3fs3Hd5I8G/i', '$2y$10$Nc3Heb1Q829wTrUZ4KOkS.KGggLtyKUYAkcy1xwC8phNf7HEhJUdG', 1, '2025-06-11 16:43:02', '2025-06-11 16:43:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `vehicle_number` varchar(20) DEFAULT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `phone`, `address`, `vehicle_number`, `vehicle_type`, `profile_photo`, `created_at`) VALUES
(5, 'hutao', 'hutao', 'srjeerh09@gmail.com', '$2y$10$q.ea06Gm87rROKKROmWE1OU0mgS1b7v2ipMrZcF3Vvd3wNEw36rdi', NULL, NULL, NULL, NULL, NULL, '2025-06-12 16:03:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_credentials`
--
ALTER TABLE `admin_credentials`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_admin_username` (`username`),
  ADD KEY `idx_admin_failed_attempts` (`failed_attempts`),
  ADD KEY `idx_admin_last_login` (`last_login`);

--
-- Indexes for table `available_spots`
--
ALTER TABLE `available_spots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `spot_number` (`spot_number`);

--
-- Indexes for table `occupied_spots`
--
ALTER TABLE `occupied_spots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `spot_id` (`spot_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `parking_spots`
--
ALTER TABLE `parking_spots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `spot_number` (`spot_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_spot_id` (`spot_id`),
  ADD KEY `idx_reservation_date` (`reservation_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `usb_device_names`
--
ALTER TABLE `usb_device_names`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin_device` (`admin_id`,`device_id`);

--
-- Indexes for table `usb_key_codes`
--
ALTER TABLE `usb_key_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_credentials`
--
ALTER TABLE `admin_credentials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `available_spots`
--
ALTER TABLE `available_spots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `occupied_spots`
--
ALTER TABLE `occupied_spots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=168;

--
-- AUTO_INCREMENT for table `parking_spots`
--
ALTER TABLE `parking_spots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usb_device_names`
--
ALTER TABLE `usb_device_names`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `usb_key_codes`
--
ALTER TABLE `usb_key_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `occupied_spots`
--
ALTER TABLE `occupied_spots`
  ADD CONSTRAINT `occupied_spots_ibfk_1` FOREIGN KEY (`spot_id`) REFERENCES `available_spots` (`id`),
  ADD CONSTRAINT `occupied_spots_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `parking_spots`
--
ALTER TABLE `parking_spots`
  ADD CONSTRAINT `parking_spots_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`spot_id`) REFERENCES `parking_spots` (`id`);

--
-- Constraints for table `usb_device_names`
--
ALTER TABLE `usb_device_names`
  ADD CONSTRAINT `usb_device_names_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_credentials` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
