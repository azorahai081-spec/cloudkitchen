-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 15, 2025 at 08:39 PM
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `is_visible`) VALUES
(4, 'APPETIZERS', '', '', 1),
(5, 'MEAT BOX', '', '', 1),
(6, 'RICE BOWL & BIRYANI', '', '', 1),
(7, 'PIZZA', '', '', 1),
(8, 'PASTA', '', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `type` enum('percentage','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `max_uses` int NOT NULL DEFAULT '100',
  `current_uses` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `description`, `type`, `value`, `min_order_amount`, `start_date`, `end_date`, `max_uses`, `current_uses`, `is_active`) VALUES
(1, 'EID50', '', 'percentage', '50.00', '0.00', '2025-11-15 19:44:00', '2025-12-15 19:44:00', 1, 1, 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_areas`
--

INSERT INTO `delivery_areas` (`id`, `area_name`, `base_charge`, `is_active`) VALUES
(1, 'Chwakbazar', '20.00', 1),
(2, 'Agrabad', '120.00', 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_sections`
--

INSERT INTO `homepage_sections` (`id`, `category_id`, `display_order`, `is_visible`) VALUES
(4, 8, 2, 1),
(5, 4, 1, 1),
(6, 5, 3, 1),
(7, 7, 4, 1),
(8, 6, 5, 1);

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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_options_groups`
--

INSERT INTO `item_options_groups` (`id`, `name`, `type`) VALUES
(6, 'Toppings', 'checkbox');

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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `image`, `is_available`, `is_featured`) VALUES
(4, 4, 'CRISPY WINGS-6PCS', '', '190.00', '0', 1, 0),
(5, 4, 'PERI PERI WINGS-6PCS', '', '220.00', '0', 1, 0),
(6, 4, 'BUFFALO WINGS-6PCS', '', '230.00', '0', 1, 1),
(7, 4, 'BBQ WINGS-6PCS', '', '200.00', '0', 1, 1),
(8, 5, 'REGULAR MEAT BOX', '', '180.00', '0', 1, 0),
(9, 5, 'BBQ MEAT BOX', '', '200.00', '0', 1, 1),
(10, 5, 'NAGA MEAT BOX', '', '200.00', '0', 1, 0),
(11, 5, 'CHEESY MEAT BOX', '', '230.00', '0', 1, 0),
(12, 5, 'MEAT BOX WITH DUMSTRIC (LARGE SIZE)', '', '358.00', '0', 1, 1),
(13, 8, 'OVEN BAKED PASTA', '', '200.00', '0', 1, 0),
(14, 8, 'MASALA PASTA', '', '200.00', '0', 1, 0),
(15, 8, 'GREEN SAUCE PASTA', '', '200.00', '0', 1, 0),
(16, 8, 'WHITE SAUCE PASTA', '', '230.00', '0', 1, 1),
(17, 8, 'SPICY CREAMY PASTA', '', '200.00', '0', 1, 0),
(18, 6, 'CHICKEN RICE BOWL (FRIED RICE)', '', '200.00', '0', 1, 0),
(19, 6, 'SAUSAGE RICE BOWL (FRIED RICE)', '', '180.00', '0', 1, 1),
(20, 6, 'CHICKEN DUM BIRYANI', '', '128.00', '0', 1, 0),
(21, 7, 'Margherita', 'A cheesy pizza with herby Californian Tomato sauce topped with loads of Mozzarella Cheese', '348.00', '0', 1, 1);

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
  `order_note` text COLLATE utf8mb4_unicode_ci,
  `delivery_area_id` int NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('Pending','Preparing','Ready','Delivered','Cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `order_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `rider_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `coupon_id` int DEFAULT NULL,
  `discount_type` enum('none','percentage','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `delivery_area_id` (`delivery_area_id`),
  KEY `fk_order_coupon` (`coupon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `customer_phone`, `customer_address`, `order_note`, `delivery_area_id`, `subtotal`, `delivery_fee`, `total_amount`, `order_status`, `order_time`, `rider_name`, `coupon_id`, `discount_type`, `discount_amount`) VALUES
(10, 'Arif', '01820331015', 'adasd', NULL, 1, '800.00', '30.00', '707.00', 'Preparing', '2025-11-15 10:48:20', 'ikram', NULL, 'fixed', '123.00'),
(11, 'Nazrul Islam', '01420332015', 'asdasd', NULL, 1, '1950.00', '30.00', '1005.00', 'Preparing', '2025-11-15 11:00:35', NULL, NULL, 'percentage', '975.00'),
(12, 'Ziaul Hoque', '01420336015', 'sdasdsa', NULL, 1, '200.00', '20.00', '220.00', 'Delivered', '2025-11-15 11:01:49', 'ikram', NULL, 'none', '0.00'),
(13, 'Shahidul islam', '01820336015', '676767f76', NULL, 1, '620.00', '30.00', '340.00', 'Ready', '2025-11-15 11:41:17', 'ikram', NULL, 'percentage', '310.00'),
(14, 'Shahadat Hossain', '0000', 'abasb', NULL, 2, '720.00', '130.00', '490.00', 'Pending', '2025-11-15 13:45:40', NULL, 1, 'percentage', '360.00'),
(15, 'Shahidul islam', '01820331015', 'asdasdas', 'Spicy', 2, '180.00', '120.00', '300.00', 'Pending', '2025-11-15 14:22:32', NULL, NULL, 'none', '0.00');

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
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `base_price`, `total_price`) VALUES
(13, 12, 7, 1, '200.00', '200.00'),
(29, 13, 7, 1, '200.00', '200.00'),
(30, 13, 7, 1, '200.00', '200.00'),
(31, 13, 5, 1, '220.00', '220.00'),
(32, 10, 7, 1, '200.00', '200.00'),
(33, 10, 7, 3, '200.00', '600.00'),
(36, 11, 7, 4, '200.00', '800.00'),
(37, 11, 6, 5, '230.00', '1150.00'),
(38, 14, 7, 4, '180.00', '720.00'),
(39, 15, 7, 1, '180.00', '180.00');

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
('global_discount_active', '1'),
('global_discount_type', 'percentage'),
('global_discount_value', '10'),
('gtm_id', ''),
('hero_image_url', '/uploads/banners/hero_banner_1763200038_468943792_122143674482332422_8657173118974339025_n.jpg'),
('hero_subtitle', '<p><strong>Hand-tossed dough, fresh ingredients, and lightning-fast delivery. What are you waiting for?</strong></p>'),
('hero_title', 'The Best Pizza in Town'),
('night_surcharge_amount', '10'),
('night_surcharge_end_hour', '20'),
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
  ADD CONSTRAINT `fk_order_coupon` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL,
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
