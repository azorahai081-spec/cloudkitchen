<?php
/*
 * includes/header.php
 * KitchCo: Cloud Kitchen Public Header
 * Version 1.4 - Re-branded for Pizza Mania
 *
 * This file is included at the top of ALL public-facing pages.
 * It handles:
 * 1. Including the main config.php
 * 2. Displaying the main navigation
 * 3. Displaying a dynamic cart count
 * 4. (NEW) Inserting GTM script
 */

// 1. CONFIGURATION
// Config.php starts the session and connects to the DB.
require_once('config.php');

// 2. HELPER FUNCTION - Get Cart Count
// Gets the total number of *items* in the cart
function get_cart_count() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}
$cart_count = get_cart_count();

// 3. (Phase 3) Check if store is open
$store_is_open = $settings['store_is_open'] ?? '1';
$gtm_id = $settings['gtm_id'] ?? ''; // (NEW) Get GTM ID

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Dynamic Title (will be set by each page) -->
    <title><?php echo e($page_title ?? 'Pizza Mania - Hot & Fresh'); ?></title>
    
    <!-- Meta Description (for SEO) -->
    <meta name="description" content="<?php echo e($meta_description ?? 'Order your favorite meals from Pizza Mania, delivered fast and fresh.'); ?>">
    
    <!-- 1. Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- 2. Load Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- 3. Configure Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        // (MODIFIED) Brand colors for "Pizza Mania"
                        'brand-red': '#dc2626', // red-600
                        'brand-yellow': '#facc15', // yellow-400
                    }
                },
            },
        };
    </script>
    
    <!-- 4. Data Layer (for GTM - Phase 5) -->
    <script>
        window.dataLayer = window.dataLayer || [];
    </script>
    
    <!-- (NEW) 5. Google Tag Manager (Head) -->
    <?php if (!empty($gtm_id)): ?>
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo e($gtm_id); ?>');</script>
    <?php endif; ?>
    
</head>
<body class="bg-gray-50 font-sans antialiased">

    <!-- (NEW) Google Tag Manager (Body) -->
    <?php if (!empty($gtm_id)): ?>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo e($gtm_id); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <?php endif; ?>

    <!-- Store Closed Banner -->
    <?php if ($store_is_open == '0'): ?>
    <div class="bg-brand-red text-white text-center p-3 font-medium">
        We are currently closed and not accepting new orders. Please check back later!
    </div>
    <?php endif; ?>

    <!-- Main Navigation -->
    <nav class="bg-white shadow-md sticky top-0 z-40">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex-shrink-0 flex items-center">
                    <!-- (MODIFIED) Clean URL for homepage & New Name -->
                    <a href="<?php echo BASE_URL; ?>/" class="text-2xl font-extrabold text-brand-red">
                        Pizza Mania
                    </a>
                </div>
                
                <!-- Desktop Nav -->
                <div class="hidden sm:ml-6 sm:flex sm:space-x-8">
                    <?php
                        // Helper to get the current page name
                        $current_page = basename($_SERVER['SCRIPT_NAME']);
                    ?>
                    <!-- (MODIFIED) Clean URL for homepage & Active State -->
                    <a href="<?php echo BASE_URL; ?>/" class="inline-flex items-center px-1 pt-1 border-b-2 <?php echo ($current_page == 'index.php') ? 'border-brand-red text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300'; ?> text-sm font-medium">Homepage</a>
                    <!-- (MODIFIED) Clean URL for menu & Active State -->
                    <a href="<?php echo BASE_URL; ?>/menu" class="inline-flex items-center px-1 pt-1 border-b-2 <?php echo ($current_page == 'menu.php') ? 'border-brand-red text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300'; ?> text-sm font-medium">Full Menu</a>
                    <!-- (NEW) Clean URL for track-order & Active State -->
                    <a href="<?php echo BASE_URL; ?>/track-order" class="inline-flex items-center px-1 pt-1 border-b-2 <?php echo ($current_page == 'track_order.php') ? 'border-brand-red text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300'; ?> text-sm font-medium">Track Order</a>
                </div>
                
                <!-- Right Side (Cart & Mobile) -->
                <div class="flex items-center">
                    <!-- Cart -->
                    <!-- (MODIFIED) Clean URL for cart -->
                    <a href="<?php echo BASE_URL; ?>/cart" class="relative p-2 rounded-full text-gray-600 hover:bg-gray-100 hover:text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                        </svg>
                        <span id="cart-count-bubble" class="absolute -top-1 -right-1 bg-brand-red text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center">
                            <?php echo e($cart_count); ?>
                        </span>
                    </a>
                    
                    <!-- Mobile Menu Button -->
                    <button id="mobile-menu-open-btn" class="sm:hidden p-2 ml-2 rounded-md text-gray-600 hover:bg-gray-100 hover:text-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu (Hidden by default) -->
        <div id="mobile-menu" class="sm:hidden hidden"">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <!-- (MODIFIED) Clean URL for homepage -->
                <a href="<?php echo BASE_URL; ?>/" class="block px-3 py-2 rounded-md text-base font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900">Homepage</a>
                <!-- (MODIFIED) Clean URL for menu -->
                <a href="<?php echo BASE_URL; ?>/menu" class="block px-3 py-2 rounded-md text-base font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900">Full Menu</a>
                <!-- (NEW) Clean URL for track-order -->
                <a href="<?php echo BASE_URL; ?>/track-order" class="block px-3 py-2 rounded-md text-base font-medium text-gray-500 hover:bg-gray-50 hover:text-gray-900">Track Order</a>
            </div>
        </div>
    </nav>

    <!-- Main Content wrapper -->
    <!-- This tag is opened here and closed in footer.php -->
    <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">