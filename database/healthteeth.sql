-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 28, 2026 at 09:27 AM
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
-- Database: `healthteeth`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_invites`
--

CREATE TABLE `account_invites` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `account_invites`
--

INSERT INTO `account_invites` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 4, '5d0c2c7d13d437ddbb1486ef3c440dfa735236637994f1bf4665553eebe41cac', '2026-10-30 15:48:02', '2026-10-28 15:49:38', '2026-10-28 07:48:02'),
(2, 5, '070a10b350de586c291ed24bf735737093ce6410549232590d71b3b96de5871b', '2026-10-30 15:50:54', '2026-10-28 15:52:14', '2026-10-28 07:50:54'),
(3, 7, '59b9ec1883f15bd986a1dfb1f6f62f6f6e8014a15e6efbab292c42ed602d26e4', '2026-10-30 16:19:23', NULL, '2026-10-28 08:19:23');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `email`, `ip`, `attempted_at`) VALUES
(1, 'admin@healthteeth.com', '::1', '2026-10-28 07:35:40'),
(2, 'admin@healthteeth.com', '::1', '2026-10-28 07:35:45'),
(3, 'admin@healthteeth.com', '::1', '2026-10-28 07:35:55'),
(5, 'admin@healthteeth.com', '::1', '2026-10-28 07:40:10'),
(6, 'admin@healthteeth.com', '::1', '2026-10-28 07:40:15'),
(14, 's2500563@usls.edu.ph', '::1', '2026-10-28 07:54:44');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `pin_hash` varchar(255) NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `auth_token_hash` char(64) DEFAULT NULL,
  `auth_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `pin_hash`, `attempts`, `expires_at`, `created_at`, `used_at`, `verified_at`, `auth_token_hash`, `auth_expires_at`) VALUES
(1, 3, '$2y$10$vbhWIRv8IIwt9/rHMTrDLukvlRfTSNA1mF1XL0XmD5/3u2vSF7Qw6', 0, '2026-10-28 15:50:03', '2026-10-28 07:40:03', NULL, NULL, NULL, NULL),
(2, 1, '$2y$10$ptfdC5qmRg/APTh6TEqPsehaGkfmj3ChJSiJe01s7X/tWPZZ3SsbK', 0, '2026-10-28 15:53:41', '2026-10-28 07:43:41', '2026-10-28 15:45:03', NULL, NULL, NULL),
(3, 1, '$2y$10$ucocrJW5i6Orxaipo4DANeGgscHpe.cqC9WNGVIPUKFYjbVY.WwpC', 0, '2026-10-28 15:55:03', '2026-10-28 07:45:03', '2026-10-28 16:05:35', NULL, NULL, NULL),
(4, 4, '$2y$10$ESTwkpsoghhgx5rZLBYXv.siuHshOsDLALnvvSqdkTDl9RzHtY7/O', 0, '2026-10-28 15:48:43', '2026-10-28 07:48:43', '2026-10-28 15:48:43', '2026-10-28 15:48:43', NULL, NULL),
(5, 5, '$2y$10$dqu9quHv2Hg1egMwEOy/cOSEp2FODa3l4RFzzDjWYs/YQn4IjVcSC', 0, '2026-10-28 15:51:08', '2026-10-28 07:51:08', '2026-10-28 15:51:08', '2026-10-28 15:51:08', NULL, NULL),
(6, 5, '$2y$10$xxnifpxVCsEQFKEh3FLi5ucLf4zpU0dFEl/7mQzFfhp3chRdSGIhC', 0, '2026-10-28 16:03:08', '2026-10-28 07:53:08', NULL, NULL, NULL, NULL),
(7, 1, '$2y$10$g3xU5DlGEEsa4oif9OPfQeW1EdqqsK5uSD5s/0tLObXIip4iz2n.C', 0, '2026-10-28 16:15:35', '2026-10-28 08:05:35', '2026-10-28 16:06:46', NULL, NULL, NULL),
(8, 1, '$2y$10$PHSnLDF8xLs10oGTK.bMCeJvdqXejQB1YlivuGhWkS.PIagunjn7q', 0, '2026-10-28 16:16:46', '2026-10-28 08:06:46', '2026-10-28 16:07:01', '2026-10-28 16:07:01', NULL, NULL),
(9, 1, '$2y$10$5CT2Pm.XfgcUZEGGCttl3uBI3YBVhwET2kEIOyWh1X8XIf9bLksj2', 0, '2026-10-28 16:34:09', '2026-10-28 08:24:09', '2026-10-28 16:24:35', '2026-10-28 16:24:35', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('patient','dentist','staff','admin') NOT NULL DEFAULT 'patient',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `license_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `role`, `status`, `license_number`, `created_at`) VALUES
(1, 'TIMOTHY FLOYD CANSON JALANDONI', 's2500552@usls.edu.ph', '$2y$10$brqbPfsQdclpnSf2qqDrOutKOkKAwFN/aXnyay91MBkdu5i5VIY3.', 'patient', 'active', NULL, '2026-10-28 07:32:39'),
(3, 'System Admin', 'admin@healthteeth.local', '$2y$10$Pac0RiSX/XpT.ew23IbK1eNsws5wrwne2OFHY/DRqOYE28KkN0wf6', 'admin', 'active', NULL, '2026-10-28 07:39:36'),
(4, 'Renz Juarez', 's2500563@usls.edu.ph', '$2y$10$WmcMDDSMjIQx9Fm6e55y6eBNSJ8IA/WxEXHAnV.5orXi3iKR57EZm', 'dentist', 'disabled', '33550336', '2026-10-28 07:48:02'),
(5, 'Ethan Namacpacan', 's2500571@usls.edu.ph', '$2y$10$73E1MDz8/XLpuLq.4nz9N.aZYVrA1.MPPN08mqaPe/wiS/UVhHDry', 'staff', 'active', NULL, '2026-10-28 07:50:54'),
(6, 'Jay Sevillo', 's2500607@usls.edu.ph', '$2y$10$TOsv/TmVm7WpBj883mjJm.8UR4yb76Tfg4wtQOdKa/L8fTtP04J/a', 'patient', 'active', NULL, '2026-10-28 08:16:25'),
(7, '<script>alert(\'Cooked\');</script>', 's25005532@usls.edu.ph', '$2y$10$BxLNvESUad/PlTsDf8WBgus8FAPbVrJy2dbkVCy.xYN2WSGnQADmG', 'dentist', 'active', '33550777', '2026-10-28 08:19:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_invites`
--
ALTER TABLE `account_invites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_invite_token` (`token_hash`),
  ADD KEY `idx_invite_user` (`user_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_login_email` (`email`,`attempted_at`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reset_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_invites`
--
ALTER TABLE `account_invites`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `account_invites`
--
ALTER TABLE `account_invites`
  ADD CONSTRAINT `fk_invite_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
