-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 22, 2025 at 07:54 PM
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
-- Database: `csd_canteen`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line` varchar(255) NOT NULL,
  `address_type` varchar(50) DEFAULT NULL,
  `locality` varchar(100) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `landmark` varchar(50) DEFAULT NULL,
  `alt_phone` varchar(15) DEFAULT NULL,
  `pincode` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `name`, `phone`, `address_line`, `address_type`, `locality`, `city`, `state`, `landmark`, `alt_phone`, `pincode`) VALUES
(1, 20, 'AK kamal', '0000000000', 'rtyuiol,mhgfvb', 'Home', 'aq', 'Edathua', 'Kerala', 'wqq', '', '689572');

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `product_id`, `quantity`) VALUES
(1, 1, 1, 3);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(4, 'Audio'),
(3, 'Cloths'),
(7, 'Grocery'),
(8, 'Home Appliances'),
(2, 'Laptop'),
(1, 'MOBILE'),
(6, 'Skin care'),
(5, 'Sports');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_date` datetime NOT NULL,
  `status` varchar(50) DEFAULT 'Processing',
  `return_requested` tinyint(1) DEFAULT 0,
  `return_reason` varchar(255) DEFAULT NULL,
  `return_status` varchar(50) DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `address_id`, `total_amount`, `order_date`, `status`, `return_requested`, `return_reason`, `return_status`) VALUES
(1, 20, 1, 150.00, '2025-09-22 16:34:53', 'Delivered', 1, 'not working', 'Rejected'),
(2, 20, 1, 150.00, '2025-09-22 22:04:27', 'Cancelled', 0, NULL, 'Pending'),
(3, 20, 1, 12000.00, '2025-09-22 22:05:56', 'Returned', 1, 'nothing', 'Approved'),
(4, 20, 1, 157.00, '2025-09-22 22:09:53', 'Delivered', 1, 'dont like it', 'Approved'),
(5, 20, 1, 17999.00, '2025-09-22 22:39:57', 'Processing', 0, NULL, 'Pending'),
(6, 20, 1, 29306.00, '2025-09-22 22:42:54', 'Processing', 0, NULL, 'Pending'),
(7, 20, 1, 5999.00, '2025-09-22 22:49:17', 'Returned', 1, 'sdfghjkl;\'/;ljlhj', 'Approved'),
(8, 20, 1, 56990.00, '2025-09-22 23:09:24', 'Processing', 0, NULL, 'Pending'),
(9, 20, 1, 649.00, '2025-09-22 23:14:30', 'Processing', 0, NULL, 'Pending'),
(10, 20, 1, 12000.00, '2025-09-22 23:15:12', 'Processing', 0, NULL, 'Pending'),
(11, 20, 1, 12157.00, '2025-09-22 23:18:00', 'Delivered', 0, NULL, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 8, 1, 150.00),
(2, 2, 8, 1, 150.00),
(3, 3, 10, 1, 12000.00),
(4, 4, 11, 1, 157.00),
(5, 5, 10, 1, 12000.00),
(6, 5, 5, 1, 5999.00),
(7, 6, 7, 1, 150.00),
(8, 6, 11, 1, 157.00),
(9, 6, 2, 1, 28999.00),
(10, 7, 5, 1, 5999.00),
(11, 8, 3, 1, 56990.00),
(12, 9, 6, 1, 649.00),
(13, 10, 10, 1, 12000.00),
(14, 11, 11, 1, 157.00),
(15, 11, 10, 1, 12000.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `description`, `price`, `image`, `category_id`, `stock`, `is_active`) VALUES
(1, 'Apple iPhone 16 (Teal, 128 GB)', 'new arrival and sper', 50999.00, '1750052745_1.png', 1, 3, 1),
(2, 'IQOO Neo 10R 5G (Raging Blue, 256 GB)  (8 GB RAM)', '8 GB RAM | 256 GB ROM\r\n17.22 cm (6.78 inch) Display\r\n50MP Rear Camera\r\n6400 mAh Battery', 28999.00, '1751474231_iqoo.png', 1, 17, 1),
(3, 'ASUS TUF Gaming A15 AMD Ryzen 7 Octa Core 7435HS', 'AMD Ryzen 7 Octa Core 7435HS - (16 GB/512 GB SSD/Windows 11 Home/4 GB Graphics/NVIDIA GeForce RTX 2050) FA566NFR-HN259W Gaming Laptop  15.6 inch, Graphite Black, 2.30 Kg.', 56990.00, '1751474470_tuf.png', 2, 14, 1),
(4, 'Men Regular Fit Self Design Spread Collar Casual Shirt', 'Featuring a textured pattern, this men\'s shirt adds a subtle touch of character to the overall look without being too bold. The stylish pattern creates a refined visual effect that works well with both solid and printed trousers. This detailing makes this shirt suitable for those who prefer understated styling that still feels distinctive and polished.', 350.00, '1757581075_nw.png', 3, 19, 1),
(5, 'boAt Aavante Bar 2400 Pro', 'Sales Package:\r\n1 X Soundbar, 1 X Subwoofer, 1 X User Manual\r\nModel Number\r\nAavante Bar 2400 Pro\r\nModel Name\r\nAavante Bar 2400 Pro w/ EQ Modes, Bass & Treble Controls, Sleek & Premium Design\r\nType\r\nSoundbar\r\nBluetooth\r\nYes\r\nConfiguration\r\n5.1\r\nPower Output (RMS)\r\n220 W\r\nColor\r\nBlack', 5999.00, '1752036184_1.png', 4, 109, 1),
(6, 'NIVIA Storm Football', 'Sales Package\r\n1 Ball\r\nModel Name\r\nStorm\r\nNeedle Included\r\nYes\r\nIdeal For\r\nStandard\r\nAge Group\r\n10-50 years\r\nDesigned For\r\nRecreational, Training, Beginner\r\nStitching Type\r\nRubber Moulded\r\nBladder Type\r\nButyl Bladders\r\nOther Body Features\r\nProudly Made in India, Rubber Molded\r\nOther Features\r\nEnsures Good Performance and High Durability, Built for Maximum Output, Long Lasting, Durable and Reliable Performance, Offering Optimum Response and Feel\r\nNet Quantity\r\n1\r\nColor\r\nWhite', 649.00, '1752036234_2.png', 5, 104, 1),
(7, 'MUUCHSTAC Ocean Face Wash for Men', 'Face Wash for Men - fights acne & pimple, brighten & clear skin, oil control, suitable for all skin types | Specfically Developed for Men - multi action formula with masculine fragrance & refreshing feel | How To Use: apply on wet face with gentle movements, avoiding the eye contour, and then rinse with lukewarm water. use twice a day for best results | Enriched with safe ingredients such as salicylic acids, licorice extract, menthol, etc. | Contents: 1x Muuchstac Ocean Face Wash, Volume: 100 ml', 150.00, '1752036340_3.png', 6, 16, 1),
(8, 'Gold Winner Refined Sunflower Oil Pouch (Sooryakanthi Enna)  (1 L)', 'Brand\r\nGold Winner\r\nModel Name\r\nRefined\r\nType\r\nSunflower Oil\r\nQuantity\r\n1 L\r\nUsed For\r\nCooking\r\nProcessing Type\r\nCold Pressed\r\nMaximum Shelf Life\r\n6 Months\r\nContainer Type\r\nPouch', 150.00, '1752036456_4.png', 7, 16, 1),
(9, 'LG 7 kg 5 Star Fully Automatic Top Load Washing Machine Black ', 'In The Box\r\n1 Unit Washing Machine, Water Supply Hoses, Drain Hose, Snap Ring, Anti-Rat Cover, User Manual, Warranty Card\r\nBrand\r\nLG\r\nModel Name\r\nT70VBMB1Z\r\nFunction Type\r\nFully Automatic Top Load\r\nEnergy Rating\r\n5\r\nWashing Capacity\r\n7 kg\r\nWashing Method\r\nPulsator\r\nMaximum Spin Speed\r\n740 rpm\r\nIn-built Heater\r\nNo\r\nColor\r\nBlack\r\nShade\r\nMiddle Black\r\nWater Consumption\r\n17.5 L\r\nSteam\r\nNo\r\nInverter\r\nYes\r\nManufacturer Year\r\n2024', 17490.00, '1752036704_5.png', 8, 217, 0),
(10, 'Whirlpool 184 L Direct Cool Single Door 2 Star Refrigerator', '\r\nIn The Box\r\n1 Refrigerator Unit, Egg Tray, Ice Tray, Key, User Manual, Warranty Card\r\nType\r\nSingle Door\r\nRefrigerator Type\r\nCompact Refrigerator\r\nDefrosting Type\r\nDirect Cool\r\nCompressor Type\r\nNormal Compressor\r\nCapacity\r\n184 L\r\nNumber of Doors\r\n1\r\nStar Rating\r\n2\r\nToughened Glass\r\nYes\r\nBuilt-in Stabilizer\r\nYes', 12000.00, '1752036781_6.png', 8, 8, 1),
(11, 'Casual Regular Sleeves Printed Women Multicolor Top', 'Casual Regular Sleeves Printed Women Multicolor Top', 157.00, '1752036872_7.png', 3, 32, 1),
(12, 'MOTOROLA 80 cm (32 inch) QLED HD Ready Smart Google TV 2025 Edition  (32HDGQMDDAQ)', 'Motorola TV features a Quad-Core Processor paired with 1 GB of RAM and 8 GB of internal storage to ensure efficient performance, along with 40W down-firing speakers that deliver a cinematic surround sound experience. Experience ultra-vibrant visuals with HD resolution and Vivid Picture Mode, in addition to the wide array of entertainment choices offered by Google TV 5.0 and the captivating audio quality of Dolby Audio.\r\n', 9200.00, '1758355870_tv.png', 8, 47, 1);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ph_no` varchar(15) NOT NULL,
  `army_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `is_approved` tinyint(4) DEFAULT 0,
  `role` enum('user','admin') DEFAULT 'user',
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `ph_no`, `army_id`, `password`, `is_approved`, `role`, `address`) VALUES
(1, 'Abhinav', '8138838211', 'ABHI123', 'abhinav123', 1, 'user', ''),
(3, 'admin', '9999999999', '0000', '$2y$10$PuHggE5DFapEGbp0s5hMPuzAirKAdUC/33KwrBCGMXrsepGX23zQq', 1, 'admin', NULL),
(13, 'aswin', '123456', 'MJNJ7', '$2y$10$o2BF3B3oStE9j6lKMOgm0OVPs.VZIEANu2KNvZBW8v9mr.2CZF/Vi', 1, 'user', NULL),
(15, '10200kittu', '10200', '1020020010', '$2y$10$/gGnezv0Op91sF3vh.6gWeyRYGRkpKFa.97fFoTsgdsiSnTNgNjPq', 1, 'user', NULL),
(18, 'ABHINAV B', '0987654321', 'mko', '$2y$10$Z4ddX.HLcm9ROKwyMgCkS.I2O6MGPGgJ//.yb./qU.Xgz/jFRtQpu', 1, 'user', NULL),
(20, 'Kamal', '0000000000', 'hhjj', '$2y$10$syOkC3ch3tp6.9BKVe6zt.N4RN7bSWgSRBmh4opKbNwUm9ZEHnVJW', 1, 'user', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ph_no` (`ph_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
