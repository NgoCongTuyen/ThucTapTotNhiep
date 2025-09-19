-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 03:55 PM
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
-- Database: `warehouse_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Điện tử', 'Các sản phẩm điện tử', '2025-08-06 10:51:49'),
(2, 'Văn phòng phẩm', 'Đồ dùng văn phòng', '2025-08-06 10:51:49'),
(3, 'Thực phẩm', 'Các loại thực phẩm', '2025-08-06 10:51:49'),
(4, 'Gia dụng', 'Đồ gia dụng, dụng cụ nhà bếp', '2025-08-06 10:52:21'),
(5, 'Thời trang', 'Quần áo, giày dép, phụ kiện', '2025-08-06 10:52:21');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `unit` varchar(20) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `min_stock` int(11) DEFAULT 0,
  `current_stock` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `zone` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `code`, `name`, `category_id`, `supplier_id`, `unit`, `price`, `min_stock`, `current_stock`, `description`, `created_at`, `zone`) VALUES
(1, 'SP001', 'Laptop Dell Inspiron 15', 1, 1, 'Chiếc', 15000000.00, 4, 10, 'Laptop Dell Inspiron 15 inch, RAM 8GB, SSD 256GB', '2025-08-06 10:52:22', 'A'),
(2, 'SP002', 'Bút bi Thiên Long', 2, 2, 'Cây', 5000.00, 100, 150, 'Bút bi Thiên Long màu xanh', '2025-08-06 10:52:22', 'B'),
(4, 'SP004', 'Máy tính để bàn HP', 1, 1, 'Bộ', 12000000.00, 3, 8, 'Máy tính để bàn HP Core i5', '2025-08-06 10:52:22', 'A'),
(5, 'SP005', 'Giấy A4', 2, 2, 'Ream', 80000.00, 20, 21, 'Giấy A4 80gsm, 500 tờ/ream', '2025-08-06 10:52:22', 'B'),
(6, 'SP006', 'Cà phê G7', 3, 3, 'Hộp', 45000.00, 30, 20, 'Cà phê hòa tan G7 3in1', '2025-08-06 10:52:22', 'C'),
(7, 'SP007', 'Chuột máy tính Logitech', 1, 1, 'Cái', 350000.00, 10, 15, 'Chuột không dây Logitech M705', '2025-08-06 10:52:22', 'A'),
(8, 'SP008', 'Bàn phím cơ', 1, 2, 'Cái', 1200000.00, 5, 3, 'Bàn phím cơ gaming RGB', '2025-08-06 10:52:22', 'A'),
(9, 'SP009', 'Nước suối Lavie', 3, 3, 'chai', 5000.00, 10, 50, 'Nước suối tinh khiết', '2025-08-06 13:17:19', 'B');

-- --------------------------------------------------------

--
-- Table structure for table `stock_transactions`
--

CREATE TABLE `stock_transactions` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `transaction_type` enum('in','out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stock_transactions`
--

INSERT INTO `stock_transactions` (`id`, `product_id`, `transaction_type`, `quantity`, `unit_price`, `total_amount`, `reference_no`, `notes`, `user_id`, `transaction_date`) VALUES
(1, 6, 'in', 20, 45000.00, 900000.00, 'anckgkgkg', 'abc', 1, '2025-08-06 10:56:59'),
(2, 2, 'out', 100, 5000.00, 500000.00, 'anckgkgkg', 'Xuất đến: gfgfgf | Ghi chú: aaaaa', 1, '2025-08-06 10:57:14'),
(3, 5, 'out', 24, 80000.00, 1920000.00, '32313', 'Xuất đến: gfgfgf | Ghi chú: âq', 1, '2025-08-06 10:57:55'),
(4, 9, 'in', 50, 5000.00, 250000.00, '`2`2`', '', 1, '2025-08-06 13:18:31');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `created_at`) VALUES
(1, 'Công ty ABC', 'Nguyễn Văn A', '0123456789', 'abc@company.com', NULL, '2025-08-06 10:51:49'),
(2, 'Công ty XYZ', 'Trần Thị B', '0987654321', 'xyz@company.com', NULL, '2025-08-06 10:51:49'),
(3, 'Công ty DEF', 'Lê Văn C', '0369852147', 'def@company.com', 'Đà Nẵng', '2025-08-06 10:52:21');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2025-08-06 10:51:49'),
(2, 'staff', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nhân viên kho', 'staff', '2025-08-06 10:51:49');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`);

--
-- Constraints for table `stock_transactions`
--
ALTER TABLE `stock_transactions`
  ADD CONSTRAINT `stock_transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_transactions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
