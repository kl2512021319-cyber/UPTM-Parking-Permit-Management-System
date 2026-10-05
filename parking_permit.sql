-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 05, 2026 at 11:54 AM
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
-- Database: `parking_permit`
--

-- --------------------------------------------------------

--
-- Table structure for table `parking_permits`
--

CREATE TABLE `parking_permits` (
  `permit_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `permit_number` varchar(50) NOT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parking_permits`
--

INSERT INTO `parking_permits` (`permit_id`, `application_id`, `permit_number`, `issue_date`, `expiry_date`) VALUES
(2, 6, 'UPTM-2026-0006', '2026-09-04', '2027-09-04'),
(3, 7, 'UPTM-2026-0007', '2026-09-06', '2027-09-06'),
(4, 12, 'UPTM-2026-0012', '2026-09-17', '2027-09-17'),
(7, 19, 'UPTM-2026-0019', '2026-09-28', '2028-10-28');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `payment_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Bank Transfer','Cash Deposit') NOT NULL,
  `payment_reference` varchar(100) NOT NULL,
  `payment_proof` varchar(255) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `payment_status` enum('Pending Verification','Paid','Rejected') NOT NULL DEFAULT 'Pending Verification',
  `admin_remark` varchar(255) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `application_id`, `payment_amount`, `payment_method`, `payment_reference`, `payment_proof`, `payment_date`, `payment_status`, `admin_remark`, `verified_at`) VALUES
(2, 6, 10.00, 'Bank Transfer', 'TXN100001', 'uploads/payment_proofs/sample_payment_1.jpg', '2026-09-02 14:00:00', 'Paid', 'Payment verified.', '2026-09-03 09:00:00'),
(3, 7, 10.00, 'Bank Transfer', 'TXN100002', 'uploads/payment_proofs/sample_payment_2.jpg', '2026-09-04 15:30:00', 'Paid', 'Payment verified.', '2026-09-05 09:30:00'),
(4, 8, 10.00, 'Bank Transfer', 'TXN100003', 'uploads/payment_proofs/sample_payment_3.jpg', '2026-09-07 10:00:00', 'Paid', '', '2026-10-04 21:16:44'),
(5, 12, 10.00, 'Cash Deposit', 'CD100004', 'uploads/payment_proofs/sample_payment_4.jpg', '2026-09-15 13:00:00', 'Paid', 'Payment verified.', '2026-09-16 09:00:00'),
(6, 13, 10.00, 'Bank Transfer', 'TXN100005', 'uploads/payment_proofs/sample_payment_5.jpg', '2026-09-18 11:00:00', 'Rejected', 'Payment proof is unclear. Please upload a clearer image.', NULL),
(9, 19, 10.00, 'Bank Transfer', 'tyghja1234', 'uploads/payment_proofs/payment_19_1790570110.png', '2026-09-28 12:35:10', 'Paid', '', '2026-09-28 12:36:49');

-- --------------------------------------------------------

--
-- Table structure for table `permit_applications`
--

CREATE TABLE `permit_applications` (
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `application_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `admin_remark` varchar(255) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permit_applications`
--

INSERT INTO `permit_applications` (`application_id`, `user_id`, `vehicle_id`, `application_date`, `status`, `admin_remark`, `reviewed_at`) VALUES
(6, 5, 6, '2026-09-01 09:30:00', 'Approved', 'Application approved.', '2026-09-02 10:00:00'),
(7, 6, 7, '2026-09-03 10:15:00', 'Approved', 'Application approved.', '2026-09-04 11:00:00'),
(8, 7, 8, '2026-09-05 08:45:00', 'Approved', 'Application approved.', '2026-09-06 09:20:00'),
(9, 8, 9, '2026-09-07 11:30:00', 'Approved', 'Application approved.', '2026-09-08 12:00:00'),
(10, 9, 10, '2026-09-10 14:00:00', 'Pending', NULL, NULL),
(11, 10, 11, '2026-09-12 09:10:00', 'Rejected', 'Vehicle information is incomplete.', '2026-09-13 10:30:00'),
(12, 11, 12, '2026-09-14 13:20:00', 'Approved', 'Application approved.', '2026-09-15 09:00:00'),
(13, 12, 13, '2026-09-16 15:00:00', 'Approved', 'Application approved.', '2026-09-17 10:00:00'),
(14, 13, 14, '2026-09-20 10:40:00', 'Rejected', 'Please check the submitted vehicle details.', '2026-09-21 11:15:00'),
(19, 17, 19, '2026-09-28 00:00:00', 'Approved', 'Application approved.', NULL),
(20, 17, 20, '2026-10-04 00:00:00', 'Approved', 'Application approved.', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Student','Staff','Admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `created_at`) VALUES
(2, 'Nur Zabidah binti Zamani', 'nurzabidah10@gmail.com', 'Zabidah@10', 'Admin', '2026-09-25 02:24:02'),
(5, 'AISYAH BINTI RAHMAN', 'aisyah@student.uptm.edu.my', 'Aisyah@123', 'Student', '2026-09-27 08:38:09'),
(6, 'MUHAMMAD AMIR BIN ZULKIFLI', 'amir@student.uptm.edu.my', 'Amir@1234', 'Student', '2026-09-27 08:38:09'),
(7, 'NUR IZZATI BINTI AHMAD', 'izzati@student.uptm.edu.my', 'Izzati@123', 'Student', '2026-09-27 08:38:09'),
(8, 'DANIEL LEE SHAN', 'daniel@student.uptm.edu.my', 'Daniel@123', 'Student', '2026-09-27 08:38:09'),
(9, 'SITI HAJAR BINTI ISMAIL', 'hajar@student.uptm.edu.my', 'Hajar@1234', 'Student', '2026-09-27 08:38:09'),
(10, 'ADAM HAKIM BIN ROSLAN', 'adam@student.uptm.edu.my', 'Adam@12345', 'Student', '2026-09-27 08:38:09'),
(11, 'NUR SYAFIQAH BINTI ALI', 'syafiqah@uptm.edu.my', 'Syafiqah@123', 'Staff', '2026-09-27 08:38:09'),
(12, 'FARHAN BIN ABDULLAH', 'farhan@uptm.edu.my', 'Farhan@123', 'Staff', '2026-09-27 08:38:09'),
(13, 'AMIRA BINTI HASSAN', 'amira@student.uptm.edu.my', 'Amira@1234', 'Student', '2026-09-27 08:38:09'),
(17, 'ATHIRAH IZLIA BINTI IDRIS', 'athirah@student.uptm.edu.my', 'Athirah@123', 'Student', '2026-09-28 04:26:41'),
(18, 'NUR ATIQAH BINTI HAMID', 'atiqah12@gmail.com', 'Atiqah@12', 'Staff', '2026-10-04 13:36:18');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plate_number` varchar(20) NOT NULL,
  `vehicle_type` varchar(50) NOT NULL,
  `vehicle_model` varchar(100) NOT NULL,
  `vehicle_colour` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `user_id`, `plate_number`, `vehicle_type`, `vehicle_model`, `vehicle_colour`) VALUES
(6, 5, 'VDF1234', 'Car', 'PERODUA MYVI', 'WHITE'),
(7, 6, 'BQA8821', 'Car', 'PROTON SAGA', 'BLACK'),
(8, 7, 'WXY4567', 'Car', 'PERODUA AXIA', 'SILVER'),
(9, 8, 'VCE9012', 'Motorcycle', 'YAMAHA Y15ZR', 'BLUE'),
(10, 9, 'BND3356', 'Car', 'HONDA CITY', 'RED'),
(11, 10, 'WVK7281', 'Motorcycle', 'HONDA RS-X', 'BLACK'),
(12, 11, 'VAA5678', 'Car', 'TOYOTA VIOS', 'WHITE'),
(13, 12, 'BPP2210', 'Car', 'PROTON X50', 'GREY'),
(14, 13, 'WTD8890', 'Car', 'PERODUA BEZZA', 'BLUE'),
(19, 17, 'ABC123', 'Car', 'PERODUA MYVI', 'WHITE'),
(20, 17, 'ASD4567', 'Car', 'PERDUA ALZA', 'RED');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `parking_permits`
--
ALTER TABLE `parking_permits`
  ADD PRIMARY KEY (`permit_id`),
  ADD UNIQUE KEY `application_id` (`application_id`),
  ADD UNIQUE KEY `permit_number` (`permit_number`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_application` (`application_id`);

--
-- Indexes for table `permit_applications`
--
ALTER TABLE `permit_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `fk_application_user` (`user_id`),
  ADD KEY `fk_application_vehicle` (`vehicle_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD KEY `fk_vehicle_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `parking_permits`
--
ALTER TABLE `parking_permits`
  MODIFY `permit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `permit_applications`
--
ALTER TABLE `permit_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `parking_permits`
--
ALTER TABLE `parking_permits`
  ADD CONSTRAINT `fk_permit_application` FOREIGN KEY (`application_id`) REFERENCES `permit_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_application` FOREIGN KEY (`application_id`) REFERENCES `permit_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `permit_applications`
--
ALTER TABLE `permit_applications`
  ADD CONSTRAINT `fk_application_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_application_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `fk_vehicle_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
