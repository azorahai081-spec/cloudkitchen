-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 15, 2025 at 10:09 AM
-- Server version: 8.0.31
-- PHP Version: 8.1.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cloud_kitchen`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manager',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$8K8zPgN/YKDYfsOGuACun.GUolvvAT0KCnE8PJtAotena6EiVh6oe', 'admin', '2025-11-14 14:43:44'),
(2, 'manager123', '$2y$10$Os9ruT7H39sxLXym40XKreF8ltrkXTnMJQxGaeKbdjCv3IXL2vOKm', 'manager', '2025-11-15 08:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `is_visible`) VALUES
(1, 'SMASHER Burgers', '', '', 1),
(2, 'Combos', '', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_areas`
--

DROP TABLE IF EXISTS `delivery_areas`;
CREATE TABLE IF NOT EXISTS `delivery_areas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `area_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `base_charge` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_areas`
--

INSERT INTO `delivery_areas` (`id`, `area_name`, `base_charge`, `is_active`) VALUES
(1, 'Chwakbazar', '20.00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `homepage_sections`
--

DROP TABLE IF EXISTS `homepage_sections`;
CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_sections`
--

INSERT INTO `homepage_sections` (`id`, `category_id`, `display_order`, `is_visible`) VALUES
(1, 2, 10, 1);

-- --------------------------------------------------------

--
-- Table structure for table `item_options`
--

DROP TABLE IF EXISTS `item_options`;
CREATE TABLE IF NOT EXISTS `item_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_increase` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_options`
--

INSERT INTO `item_options` (`id`, `group_id`, `name`, `price_increase`) VALUES
(1, 1, 'Coke', '30.00'),
(2, 1, 'Sprite', '30.00');

-- --------------------------------------------------------

--
-- Table structure for table `item_options_groups`
--

DROP TABLE IF EXISTS `item_options_groups`;
CREATE TABLE IF NOT EXISTS `item_options_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('radio','checkbox') COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_options_groups`
--

INSERT INTO `item_options_groups` (`id`, `name`, `type`) VALUES
(1, 'Drink', 'radio'),
(2, 'Size', 'radio'),
(3, 'Size', 'radio'),
(4, 'Size', 'radio');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT '1',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `image`, `is_available`, `is_featured`) VALUES
(1, 1, 'Truffle Smasher (Single Patty)', '', '395.00', '0', 1, 0),
(2, 1, 'Truffle Smasher (Double Patty)', '', '590.00', '0', 1, 1),
(3, 2, 'Burger Combo', '', '500.00', '0', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `menu_item_options_groups`
--

DROP TABLE IF EXISTS `menu_item_options_groups`;
CREATE TABLE IF NOT EXISTS `menu_item_options_groups` (
  `menu_item_id` int NOT NULL,
  `option_group_id` int NOT NULL,
  PRIMARY KEY (`menu_item_id`,`option_group_id`),
  KEY `fk_group_id` (`option_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_item_options_groups`
--

INSERT INTO `menu_item_options_groups` (`menu_item_id`, `option_group_id`) VALUES
(3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_area_id` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('Pending','Preparing','Ready','Delivered','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `order_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rider_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `delivery_area_id` (`delivery_area_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `customer_phone`, `customer_address`, `delivery_area_id`, `subtotal`, `delivery_fee`, `total_amount`, `order_status`, `order_time`, `rider_name`) VALUES
(1, 'Arif', '01820336015', 'sadasdd', 1, '395.00', '20.00', '415.00', 'Pending', '2025-11-14 15:01:06', 'ikram'),
(2, 'Nazrul Islam', '01820336015', 'asd', 1, '590.00', '20.00', '610.00', 'Cancelled', '2025-11-14 15:06:34', NULL),
(3, 'Arif', 'ads', 'asdasd', 1, '530.00', '20.00', '550.00', 'Preparing', '2025-11-14 15:39:48', NULL),
(4, 'Nazrul Islam', '01820336015', 'jki', 1, '500.00', '20.00', '520.00', 'Preparing', '2025-11-15 00:40:00', 'ikram'),
(5, 'Shahadat Hossain', '01820336015', 'asda', 1, '590.00', '20.00', '610.00', 'Preparing', '2025-11-15 07:40:21', NULL),
(6, 'Nahui', '01820336015', 'fhdfh', 1, '1060.00', '20.00', '1080.00', 'Delivered', '2025-11-15 08:16:20', 'ikram'),
(7, 'Shahadat Hossain', '01820336012', 'asdasd', 1, '1180.00', '20.00', '1200.00', 'Preparing', '2025-11-15 08:42:13', NULL),
(8, 'Robiul Awal', '01820331015', 'sadas', 1, '530.00', '20.00', '550.00', 'Preparing', '2025-11-15 08:52:17', NULL),
(9, 'Surcharge', '01430336014', 'yuktyktk', 1, '530.00', '30.00', '560.00', 'Pending', '2025-11-15 09:50:51', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `menu_item_id` int DEFAULT NULL,
  `quantity` int NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `menu_item_id` (`menu_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `base_price`, `total_price`) VALUES
(1, 1, 1, 1, '395.00', '395.00'),
(2, 2, 2, 1, '590.00', '590.00'),
(3, 3, 3, 1, '500.00', '530.00'),
(4, 4, 3, 1, '500.00', '500.00'),
(5, 5, 2, 1, '590.00', '590.00'),
(6, 6, 3, 2, '500.00', '1060.00'),
(7, 7, 2, 2, '590.00', '1180.00'),
(8, 8, 3, 1, '500.00', '530.00'),
(9, 9, 3, 1, '500.00', '530.00');

-- --------------------------------------------------------

--
-- Table structure for table `order_item_options`
--

DROP TABLE IF EXISTS `order_item_options`;
CREATE TABLE IF NOT EXISTS `order_item_options` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_item_id` int NOT NULL,
  `option_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_item_id` (`order_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_item_options`
--

INSERT INTO `order_item_options` (`id`, `order_item_id`, `option_name`, `option_price`) VALUES
(1, 3, 'Sprite', '30.00'),
(2, 6, 'Coke', '30.00'),
(3, 8, 'Sprite', '30.00'),
(4, 9, 'Sprite', '30.00');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('capi_pixel_id', ''),
('capi_token', ''),
('fb_pixel_id', ''),
('gtm_id', ''),
('hero_image_url', '/uploads/banners/hero_banner_1763200038_468943792_122143674482332422_8657173118974339025_n.jpg'),
('hero_subtitle', '<h2><strong>hero_subtitle</strong></h2><h2>&nbsp;</h2><p><i><strong>sdasd</strong></i></p>'),
('hero_title', 'hero_title'),
('night_surcharge_amount', '10'),
('night_surcharge_end_hour', '17'),
('night_surcharge_start_hour', '15'),
('store_is_open', '1'),
('timezone', 'Asia/Dhaka');

--
-- Constraints for dumped tables
--

--
-- Constraints for table `homepage_sections`
--
ALTER TABLE `homepage_sections`
  ADD CONSTRAINT `fk_hs_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `item_options`
--
ALTER TABLE `item_options`
  ADD CONSTRAINT `fk_option_group` FOREIGN KEY (`group_id`) REFERENCES `item_options_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menu_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_item_options_groups`
--
ALTER TABLE `menu_item_options_groups`
  ADD CONSTRAINT `fk_menu_item_id` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_option_group_id` FOREIGN KEY (`option_group_id`) REFERENCES `item_options_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_order_delivery_area` FOREIGN KEY (`delivery_area_id`) REFERENCES `delivery_areas` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_menu_item` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_item_options`
--
ALTER TABLE `order_item_options`
  ADD CONSTRAINT `fk_oio_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
