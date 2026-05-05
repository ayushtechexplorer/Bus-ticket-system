-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 17, 2025 at 05:23 AM
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
-- Database: `bus_booking`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `booking_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `bus_id` int(11) DEFAULT NULL,
  `seat_numbers` text DEFAULT NULL,
  `total_seats` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `qr_code_path` varchar(255) DEFAULT NULL,
  `ride_status` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `transaction_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`booking_id`, `user_id`, `bus_id`, `seat_numbers`, `total_seats`, `total_amount`, `payment_status`, `payment_method`, `paid_at`, `qr_code_path`, `ride_status`, `created_at`, `transaction_id`) VALUES
(86, 2, 15, 'S3', 1, 2000.00, 'Paid', 'Khalti', '2025-12-17 06:59:39', NULL, 'Upcoming', '2025-12-17 06:59:30', NULL),
(88, 2, 19, 'S5, S4', 2, 2500.00, 'Paid', 'eSewa', '2025-12-17 10:02:21', NULL, 'Upcoming', '2025-12-17 10:02:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `buses`
--

CREATE TABLE `buses` (
  `id` int(11) NOT NULL,
  `bus_name` varchar(100) NOT NULL,
  `bus_number` varchar(20) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `driver_phone` varchar(15) DEFAULT NULL,
  `route_from` varchar(100) NOT NULL,
  `route_to` varchar(100) NOT NULL,
  `departure_time` datetime NOT NULL,
  `total_seats` int(11) NOT NULL DEFAULT 40,
  `available_seats` int(11) NOT NULL DEFAULT 40,
  `ticket_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `driver_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buses`
--

INSERT INTO `buses` (`id`, `bus_name`, `bus_number`, `driver_name`, `driver_phone`, `route_from`, `route_to`, `departure_time`, `total_seats`, `available_seats`, `ticket_price`, `created_at`, `driver_id`) VALUES
(1, 'Sajha Deluxe', 'BUS001', 'Ayush kharel', '9823857566', 'Kathmandu', 'Pokhara', '2026-02-28 23:03:00', 39, 39, 1200.00, '2025-10-07 07:37:53', 1),
(2, 'Greenline Express', 'BUS002', 'Ayush Kharel', '9867564637', 'Kathmandu', 'Chitwan', '2026-02-28 09:30:00', 39, 39, 1000.00, '2025-10-07 07:37:53', 2),
(4, 'Sajha Deluxe', 'ba 39398', 'Prakash thapa', '9857463789', 'kathmandu', 'lumbini', '2026-02-27 06:08:00', 39, 39, 1500.00, '2025-10-28 08:18:22', NULL),
(6, 'Makalu Yatayat', 'ख २१०३', 'Asim saru', '9867543651', 'Butwal', 'Kathmandu', '2026-02-28 07:56:00', 40, 40, 1200.00, '2025-11-16 02:08:07', NULL),
(7, 'Buddha Express', '३–ख ३१८४', 'Suresh kumar Magar', '9867879387', 'Butwal', 'Pokhara', '2026-02-28 07:54:00', 40, 40, 700.00, '2025-11-16 02:10:32', NULL),
(8, 'Greenline Tourist', '३–ख १७०२', 'Samrat thapa', '9865789873', 'Butwal', 'Lumbini', '2026-02-28 11:56:00', 40, 40, 400.00, '2025-11-16 02:11:52', NULL),
(9, 'Jagadamba Yatayat', '५–ख ४५२२', 'Ayush kharel', '9865743621', 'Kathmandu', 'Baglung', '2026-02-28 07:59:00', 39, 39, 1000.00, '2025-11-16 02:14:49', NULL),
(10, 'Lumbini Deluxe', '७–ख ०९३१', 'Kaushal yadav', '9876878746', 'Kathmandu', 'Chitwan', '2026-02-28 08:00:00', 39, 39, 600.00, '2025-11-16 02:16:04', NULL),
(11, 'Prithivi Yatayat', '३–ख ५८०६', 'Saugat GC', '9878675366', 'Kathmandu', 'Jomsom', '2026-02-27 09:05:00', 39, 39, 4000.00, '2025-11-16 02:17:50', NULL),
(12, 'Araniko Express', '२–ख ३३२५', 'Tushar rayamajhi', '9878632153', 'Butwal', 'Janakpur', '2026-02-28 11:07:00', 39, 39, 1000.00, '2025-11-16 02:19:04', NULL),
(13, 'Narayani Yatayat', '२–ख ०८८२', 'Manish ghimire', '9876574361', 'Chitwan', 'Butwal', '2026-02-28 18:33:00', 35, 35, 500.00, '2025-11-16 02:47:38', NULL),
(14, 'Rapti Super Express', '५–ख ५६४७', 'Samar Kumar', '9865712532', 'Chitwan', 'Jomsom', '2026-02-27 23:43:00', 35, 35, 4000.00, '2025-11-16 02:58:46', NULL),
(15, 'Rara Express', '७–ख २५६४', 'Samyog sharma', '9876576376', 'Kathmandu', 'Lumbini', '2026-02-26 00:01:00', 35, 34, 2000.00, '2025-11-16 03:16:33', NULL),
(16, 'Seti Mahakali Yatayat', '७–ख १३१९', 'Sagar Neupane', '9875467837', 'Kathmandu', 'Butwall', '2026-02-26 00:11:00', 35, 35, 2000.00, '2025-11-16 03:27:06', NULL),
(17, 'Modern Super Deluxe', '३–ख ६६१४', 'Suprim Bhusal', '9865743612', 'Butwal', 'Kathmandu', '2026-02-28 10:21:00', 35, 35, 1000.00, '2025-11-16 04:32:53', NULL),
(19, 'SALINA DELUXE', 'lu3 kha 7500', 'Mukti  Thapa', '9847049867', 'Lumbini', 'pokhara', '2025-12-18 09:00:00', 36, 34, 1250.00, '2025-12-17 04:13:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','passenger') DEFAULT 'passenger',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `address`, `username`, `gender`, `password`, `role`, `created_at`) VALUES
(1, 'Admin', 'admin@bus.com', '9800000000', 'Kathmandu', 'admin', 'Male', 'admin123', 'admin', '2025-10-07 07:37:53'),
(2, 'Ayush Kharel', 'ayushkharel301@gmail.com', '9857463625', 'butwal-10, kalikanagar', 'ayushispro123', 'Male', 'ayushispro@1234', 'passenger', '2025-10-07 07:53:38'),
(4, 'Dinesh gautam', 'dineshgautam321@gmail.com', '9865783212', 'tilottama-1 , nayamil', 'dinesh123', 'Male', 'dineshispro@1234', 'passenger', '2025-11-16 08:52:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`booking_id`);

--
-- Indexes for table `buses`
--
ALTER TABLE `buses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `buses`
--
ALTER TABLE `buses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
