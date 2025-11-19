-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Nov 19, 2025 at 12:35 PM
-- Server version: 11.8.3-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u371064108_pizzamania`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_ledger`
--

CREATE TABLE `admin_ledger` (
  `id` int(11) NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `entry_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_ledger`
--

INSERT INTO `admin_ledger` (`id`, `type`, `entry_date`, `amount`, `description`, `created_at`) VALUES
(3, 'deposit', '2025-10-01', 1000.00, '', '2025-11-16 16:42:56'),
(4, 'deposit', '2025-10-02', 10000.00, '', '2025-11-16 16:48:18'),
(5, 'deposit', '2025-11-01', 5000.00, '', '2025-11-16 16:48:43'),
(6, 'withdrawal', '2025-11-16', 20000.00, '', '2025-11-16 17:16:09'),
(7, 'deposit', '2025-11-16', 30000.00, '', '2025-11-16 17:16:16');

-- --------------------------------------------------------

--
-- Table structure for table `admin_reviews`
--

CREATE TABLE `admin_reviews` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `rating` int(11) NOT NULL DEFAULT 5,
  `review_text` text NOT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_reviews`
--

INSERT INTO `admin_reviews` (`id`, `customer_name`, `rating`, `review_text`, `is_visible`) VALUES
(1, 'Rakib Islam', 5, 'Great pizza, fast delivery! The crust was perfect and the toppings were fresh. Will be ordering again!', 1),
(2, 'Sanjida Ahmed', 5, 'The BBQ Meat Box was amazing. So much food for the price, and everything was delicious. Highly recommend', 1),
(3, 'Fahmida Rahman', 5, 'Best pizza in Dhaka, hands down. We tried the Margherita and it was just perfect. Simple and tasty', 1);

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','manager') NOT NULL DEFAULT 'manager',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$8K8zPgN/YKDYfsOGuACun.GUolvvAT0KCnE8PJtAotena6EiVh6oe', 'admin', '2025-11-14 14:43:44'),
(2, 'manager', '$2y$10$G9xn6NAW7NqwWnVUKDxf7./o3ATudlh6Td0n/oI.DzvYM7HRR7k.e', 'manager', '2025-11-15 08:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `svg_icon` text DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `svg_icon`, `is_visible`) VALUES
(4, 'Appetizers', '', '', '<svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" class=\"size-6\">\r\n  <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3\" />\r\n</svg>\r\n', 1),
(5, 'Meat Box', '', '', '', 1),
(6, 'Rice Bowl & Biryani', '', '', '', 1),
(7, 'Pizza', '', '', '', 1),
(8, 'Pasta', '', '', '', 1),
(9, 'Beverage', '', '', '', 1),
(12, 'Fries', '', '', '<svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"1.5\" stroke=\"currentColor\" class=\"size-6\">\r\n  <path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3\" />\r\n</svg>\r\n', 1);

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `complaint_type` varchar(50) NOT NULL,
  `complaint_text` text NOT NULL,
  `status` enum('Submitted','In Review','Resolved') NOT NULL DEFAULT 'Submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`id`, `order_id`, `customer_name`, `customer_phone`, `complaint_type`, `complaint_text`, `status`, `created_at`) VALUES
(2, 12, 'Ziaul Hoque', '01420336015', 'Food Quality (e.g., cold, not tasty)', 'asdsad', 'Submitted', '2025-11-16 19:14:57'),
(3, 13, 'Shahidul islam', '01820336015', 'Missing Item(s)', 'avsva', 'Submitted', '2025-11-16 19:19:34');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('percentage','fixed') NOT NULL DEFAULT 'fixed',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_order_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `max_uses` int(11) NOT NULL DEFAULT 100,
  `current_uses` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `description`, `type`, `value`, `min_order_amount`, `start_date`, `end_date`, `max_uses`, `current_uses`, `is_active`) VALUES
(1, 'EID50', '', 'percentage', 50.00, 0.00, '2025-11-15 19:44:00', '2025-12-15 19:44:00', 1, 1, 1),
(2, 'EID501', '', 'percentage', 50.00, 0.00, '2025-11-16 11:50:00', '2025-12-16 11:50:00', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `delivery_areas`
--

CREATE TABLE `delivery_areas` (
  `id` int(11) NOT NULL,
  `area_name` varchar(150) NOT NULL,
  `base_charge` decimal(10,2) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_areas`
--

INSERT INTO `delivery_areas` (`id`, `area_name`, `base_charge`, `is_active`) VALUES
(1, 'Chwakbazar', 20.00, 1),
(2, 'Agrabad', 120.00, 1),
(3, 'PICKUP POINT', 0.00, 1),
(4, 'Halisahar \"A/B/K Block\"', 130.00, 1),
(5, 'Muradpur', 100.00, 1),
(6, 'Agrabad (চৌমুহনি)', 100.00, 1),
(7, 'Agrabad (চৌমুহনি)', 100.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `faq`
--

CREATE TABLE `faq` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faq`
--

INSERT INTO `faq` (`id`, `question`, `answer`, `display_order`, `is_visible`) VALUES
(1, 'What are your delivery hours?', 'We are open and deliver from 11:00 AM to 10:00 PM, seven days a week. Please note that a night surcharge may apply for orders placed after 8:00 PM.', 10, 1),
(2, 'How do I track my order?', 'After you place your order, you will get an Order ID (e.g., PM-123). You can enter this ID on our Track Order page to see its live status, from \"Preparing\" to \"Delivered\".', 20, 1),
(3, 'What areas do you deliver to?', 'During checkout, you can select your area from the \"Delivery Area\" dropdown list. If your area is not on the list, we unfortunately do not deliver there at this time. The delivery fee for your area will be calculated automatically.', 30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `homepage_sections`
--

CREATE TABLE `homepage_sections` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_sections`
--

INSERT INTO `homepage_sections` (`id`, `category_id`, `display_order`, `is_visible`) VALUES
(4, 8, 2, 1),
(5, 4, 4, 1),
(6, 5, 3, 1),
(7, 7, 1, 1),
(8, 6, 5, 1),
(9, 9, 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `item_options`
--

CREATE TABLE `item_options` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price_increase` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_options`
--

INSERT INTO `item_options` (`id`, `group_id`, `name`, `price_increase`) VALUES
(3, 7, '10', 100.00),
(4, 8, 'Extra Cheese', 50.00),
(5, 9, '80', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `item_options_groups`
--

CREATE TABLE `item_options_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('radio','checkbox') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `item_options_groups`
--

INSERT INTO `item_options_groups` (`id`, `name`, `type`) VALUES
(7, 'Size', 'radio'),
(8, 'Toppings', 'radio'),
(9, 'Small', 'radio');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `category_id`, `name`, `description`, `price`, `image`, `is_available`, `is_featured`) VALUES
(4, 4, 'CRISPY WINGS-6PCS', 'Golden, crunchy chicken wings seasoned to perfection.', 190.00, '/uploads/menu_items/1763281022_CRISPYWINGs.jpeg', 1, 0),
(5, 4, 'PERI PERI WINGS-6PCS', 'Zesty peri peri wings bursting with spicy, citrusy flavours.', 220.00, '/uploads/menu_items/1763281010_PERIPERIWINGs.jpeg', 1, 0),
(6, 4, 'BUFFALO WINGS-6PCS', 'Tangy and spicy Buffalo-style wings with a bold kick.', 230.00, '/uploads/menu_items/1763280989_BUFFALOWINGs.jpeg', 1, 1),
(7, 4, 'BBQ WINGS-6PCS', 'Crispy chicken wings coated in sweet and smoky BBQ sauce.', 200.00, '/uploads/menu_items/1763280977_BBQWINGSs.jpeg', 1, 1),
(8, 5, 'REGULAR MEAT BOX', 'A classic meat box with perfectly seasoned chicken and a balanced flavour profile.', 180.00, '/uploads/menu_items/1763280964_REGULARMEATBOx.jpeg', 1, 0),
(9, 5, 'BBQ MEAT BOX', 'Juicy chicken tossed in smoky BBQ sauce, served in a hearty meat box.', 200.00, '/uploads/menu_items/1763280951_BBQMEAt.jpeg', 1, 1),
(10, 5, 'NAGA MEAT BOX', 'A spicy meat box infused with bold Naga chilli flavour for heat lovers.', 200.00, '/uploads/menu_items/1763280935_nagameatbox.jpeg', 1, 0),
(11, 5, 'CHEESY MEAT BOX', 'A rich and satisfying meat box topped with melted cheese for extra creaminess and flavour.', 230.00, '/uploads/menu_items/1763280918_cheesymeat.png', 1, 0),
(12, 5, 'MEAT BOX WITH DUMSTRIC (LARGE SIZE)', 'A large, loaded meat box filled with tender chicken pieces, crispy dumstric strips, and signature seasonings.', 358.00, '/uploads/menu_items/1763280673_MEATBOXWITHDUMSTRIC.png', 1, 1),
(13, 8, 'OVEN BAKED PASTA', 'Cheesy, layered pasta baked to perfection with creamy sauce, herbs, and a golden crust on top.', 200.00, '/uploads/menu_items/1763280577_ovenbaked.png', 1, 0),
(14, 8, 'MASALA PASTA', 'A fusion-style pasta cooked with Indian masala, veggies, and bold spices for a vibrant flavour.', 200.00, '/uploads/menu_items/1763280509_MASALAPASTA.png', 1, 0),
(15, 8, 'GREEN SAUCE PASTA', 'Pasta tossed in a fresh, herb-based green sauce made with basil, coriander, and a touch of cream.', 200.00, '/uploads/menu_items/1763280463_GREENSAUCEPASTA.png', 1, 0),
(16, 8, 'WHITE SAUCE PASTA', 'Smooth and creamy white sauce coated over perfectly cooked pasta, finished with herbs and cheese.', 230.00, '/uploads/menu_items/1763280427_WHITESAUCEPASTA.png', 1, 1),
(17, 8, 'SPICY CREAMY PASTA', 'A rich and creamy pasta with a spicy kick, perfectly blended with herbs, cheese, and flavorful seasonings.', 200.00, '/uploads/menu_items/1763280352_SPICYCREAMYPASTA.png', 1, 0),
(18, 6, 'CHICKEN RICE BOWL (FRIED RICE)', 'Classic fried rice mixed with tender chicken pieces, fresh vegetables, and balanced Asian spices.', 200.00, '/uploads/menu_items/1763280300_CHICKENRICEBOWL.png', 1, 0),
(19, 6, 'SAUSAGE RICE BOWL (FRIED RICE)', 'Flavourful fried rice tossed with juicy sausage slices, veggies, and light seasoning for a satisfying meal.', 180.00, '/uploads/menu_items/1763280225_SAUSAGERICEBOWL.png', 1, 1),
(20, 6, 'CHICKEN DUM BIRYANI', 'Slow-cooked aromatic basmati rice layered with tender chicken, blended with rich dum masala and traditional spices.', 128.00, '/uploads/menu_items/1763280186_chickendum.png', 1, 1),
(21, 7, 'Margherita', 'A cheesy pizza with herby Californian Tomato sauce topped with loads of Mozzarella Cheese', 348.00, '/uploads/menu_items/1763242197_1.webp', 1, 1),
(22, 7, 'Spicy Chicken', 'A combination of tender & Spicy Chicken, crunchy Capsicum, and zesty Red Onions for a flavor-packed experience\r\n\r\n', 398.00, '/uploads/menu_items/1763242190_7ab537159088a62156e09f8970289e79.webp', 1, 1),
(23, 9, 'Borhani', '1 glass of refreshing borhani as a perfect accompaniment to a meal', 70.00, '/uploads/menu_items/1763286907_images.jfif', 1, 0),
(24, 9, 'Zafrani Sharbat', 'A delectable sweet drink with the natural essence', 90.00, '/uploads/menu_items/1763286952_images1.jfif', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `menu_item_options_groups`
--

CREATE TABLE `menu_item_options_groups` (
  `menu_item_id` int(11) NOT NULL,
  `option_group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_item_options_groups`
--

INSERT INTO `menu_item_options_groups` (`menu_item_id`, `option_group_id`) VALUES
(21, 7),
(22, 7),
(21, 8),
(22, 8),
(15, 9);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(50) NOT NULL,
  `customer_address` text NOT NULL,
  `order_note` text DEFAULT NULL,
  `delivery_area_id` int(11) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `delivery_fee` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_status` enum('Pending','Preparing','Ready','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `order_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `rider_name` varchar(100) DEFAULT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `discount_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_name`, `customer_phone`, `customer_address`, `order_note`, `delivery_area_id`, `subtotal`, `delivery_fee`, `total_amount`, `order_status`, `order_time`, `rider_name`, `coupon_id`, `discount_type`, `discount_amount`) VALUES
(10, 'Arif', '01820331015', 'adasd', NULL, 1, 800.00, 30.00, 707.00, 'Preparing', '2025-11-15 10:48:20', 'ikram', NULL, 'fixed', 123.00),
(11, 'Nazrul Islam', '01420332015', 'asdasd', NULL, 1, 2150.00, 30.00, 1105.00, 'Preparing', '2025-11-15 11:00:35', NULL, NULL, 'percentage', 1075.00),
(12, 'Ziaul Hoque', '01420336015', 'sdasdsa', NULL, 1, 200.00, 20.00, 220.00, 'Delivered', '2025-11-15 11:01:49', 'ikram', NULL, 'none', 0.00),
(13, 'Shahidul islam', '01820336015', '676767f76', NULL, 1, 620.00, 30.00, 340.00, 'Delivered', '2025-11-15 11:41:17', 'ikram', NULL, 'percentage', 310.00),
(14, 'Shahadat Hossain', '0000', 'abasb', NULL, 2, 720.00, 130.00, 490.00, 'Delivered', '2025-11-15 13:45:40', NULL, 1, 'percentage', 360.00),
(15, 'Shahidul islam', '01820331015', 'asdasdas', 'Spicy', 2, 180.00, 120.00, 300.00, 'Pending', '2025-11-15 14:22:32', NULL, NULL, 'none', 0.00),
(26, 'Shahidul islam', '01820331015', 'sdad', 'vavw', 1, 190.00, 10.00, 200.00, 'Pending', '2025-11-16 21:54:36', NULL, NULL, 'none', 0.00),
(27, 'Nahid', '01620332015', 'asd', '', 3, 190.00, 0.00, 190.00, 'Delivered', '2025-11-16 21:56:17', NULL, NULL, 'none', 0.00),
(28, 'Ikram', '01865129950', 'Rashulbhag d block', 'Less spicy', 2, 578.00, 60.00, 638.00, 'Pending', '2025-11-16 22:06:09', NULL, NULL, 'none', 0.00),
(29, 'Robiul Islam', '01820536015', 'Bbs', '', 2, 908.00, 60.00, 968.00, 'Pending', '2025-11-16 22:10:56', NULL, NULL, 'none', 0.00),
(30, 'Arif', '01820336015', 'ভাসাভা', '', 2, 998.00, 120.00, 1118.00, 'Delivered', '2025-11-17 07:20:51', 'ikram', NULL, 'none', 0.00),
(31, 'Arfat', '01886600861', 'Dbbsjkksjjbshjk', 'Spicy,crust single', 2, 1800.00, 120.00, 1920.00, 'Delivered', '2025-11-17 17:46:07', 'Masud', NULL, 'none', 0.00),
(32, 'Ikram', '01886600861', 'Bsbsb', 'Dbdd', 4, 460.00, 130.00, 544.00, 'Cancelled', '2025-11-17 18:28:08', '', NULL, 'percentage', 46.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `base_price`, `total_price`) VALUES
(13, 12, 7, 1, 200.00, 200.00),
(29, 13, 7, 1, 200.00, 200.00),
(30, 13, 7, 1, 200.00, 200.00),
(31, 13, 5, 1, 220.00, 220.00),
(32, 10, 7, 1, 200.00, 200.00),
(33, 10, 7, 3, 200.00, 600.00),
(38, 14, 7, 4, 180.00, 720.00),
(39, 15, 7, 1, 180.00, 180.00),
(57, 26, 4, 1, 190.00, 190.00),
(58, 27, 4, 1, 190.00, 190.00),
(59, 28, 19, 1, 180.00, 180.00),
(60, 28, 22, 1, 398.00, 398.00),
(61, 29, 24, 1, 90.00, 90.00),
(62, 29, 5, 1, 220.00, 220.00),
(63, 29, 14, 1, 200.00, 200.00),
(64, 29, 22, 1, 398.00, 398.00),
(65, 30, 7, 1, 200.00, 200.00),
(66, 30, 24, 1, 90.00, 90.00),
(67, 30, 15, 1, 200.00, 200.00),
(68, 30, 20, 1, 128.00, 128.00),
(69, 30, 19, 1, 180.00, 180.00),
(70, 30, 18, 1, 200.00, 200.00),
(74, 11, 7, 4, 200.00, 800.00),
(75, 11, 6, 5, 230.00, 1150.00),
(76, 11, 14, 1, 200.00, 200.00),
(77, 31, 11, 6, 230.00, 1380.00),
(78, 31, 6, 1, 230.00, 230.00),
(79, 31, 4, 1, 190.00, 190.00),
(82, 32, 6, 2, 230.00, 460.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_item_options`
--

CREATE TABLE `order_item_options` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `option_name` varchar(100) NOT NULL,
  `option_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('capi_pixel_id', ''),
('capi_token', ''),
('delivery_discount_active', '0'),
('delivery_discount_percentage', '50'),
('fb_pixel_id', ''),
('free_delivery_active', '0'),
('global_discount_active', '0'),
('global_discount_type', 'percentage'),
('global_discount_value', '50'),
('gtm_id', ''),
('hero_image_card_color', '#FFFFFF'),
('hero_image_style', 'tilt-no-shadow'),
('hero_image_url', '/uploads/banners/hero_banner_1763283791_1.png'),
('hero_subtitle', '<p><strong>Hand-tossed dough, fresh ingredients, and lightning-fast delivery. What are you waiting for?</strong></p>'),
('hero_title', 'The Best Pizza in Town'),
('night_surcharge_amount', '10'),
('night_surcharge_end_hour', '20'),
('night_surcharge_start_hour', '15'),
('offer_is_active', '1'),
('offer_text', 'Get 20% off all Pizza orders. Use code: PIZZA20'),
('offer_title', 'Weekend\'s Special'),
('store_is_open', '1'),
('store_name', 'Pizza Mania'),
('timezone', 'Asia/Dhaka');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_ledger`
--
ALTER TABLE `admin_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_saving_date` (`entry_date`);

--
-- Indexes for table `admin_reviews`
--
ALTER TABLE `admin_reviews`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `delivery_areas`
--
ALTER TABLE `delivery_areas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `faq`
--
ALTER TABLE `faq`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `homepage_sections`
--
ALTER TABLE `homepage_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `item_options`
--
ALTER TABLE `item_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`);

--
-- Indexes for table `item_options_groups`
--
ALTER TABLE `item_options_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `menu_item_options_groups`
--
ALTER TABLE `menu_item_options_groups`
  ADD PRIMARY KEY (`menu_item_id`,`option_group_id`),
  ADD KEY `fk_group_id` (`option_group_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_area_id` (`delivery_area_id`),
  ADD KEY `fk_order_coupon` (`coupon_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `order_item_options`
--
ALTER TABLE `order_item_options`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_ledger`
--
ALTER TABLE `admin_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `admin_reviews`
--
ALTER TABLE `admin_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `delivery_areas`
--
ALTER TABLE `delivery_areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `faq`
--
ALTER TABLE `faq`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `homepage_sections`
--
ALTER TABLE `homepage_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `item_options`
--
ALTER TABLE `item_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `item_options_groups`
--
ALTER TABLE `item_options_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `order_item_options`
--
ALTER TABLE `order_item_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `fk_complaint_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

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
