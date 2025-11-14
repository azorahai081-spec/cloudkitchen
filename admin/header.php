<?php
/*
 * admin/header.php
 * KitchCo: Cloud Kitchen Master Admin Header
 * Version 1.0
 *
 * This file is included at the top of ALL protected admin pages.
 * It handles:
 * 1. Including the main config.php
 * 2. Security Check: Redirects to login.php if user is not logged in.
 * 3. Role-Based Access: Hides certain links if user is a 'manager'.
 * 4. Displaying the responsive Sidebar and Top-bar.
 */

// 1. CONFIGURATION
// We must include the config file to start the session and connect to the DB.
// Using '../config.php' because this file is in the 'admin' folder.
require_once('../config.php');

// 2. SECURITY CHECK (THE "FIREWALL")
// If the 'user_id' session variable is not set, the user is not logged in.
// Redirect them to the login page and stop all further script execution.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 3. ROLE-BASED ACCESS
// Get the user's role from the session.
$user_role = $_SESSION['user_role'] ?? 'manager'; // Default to 'manager' for safety

// This simple function makes checking roles in our HTML cleaner
function hasAdminAccess() {
    return $_SESSION['user_role'] === 'admin';
}

// Get username for display
$username = e($_SESSION['username']);
$user_initial = strtoupper(substr($username, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Title will be set by the page that includes this header -->
    <title>KitchCo Admin</title>
    
    <!-- 1. Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- 2. Load Google Font (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- 3. Configure Tailwind -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                },
            },
        };
    </script>
    
    <!-- 4. Custom Admin Styles -->
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .toggle-checkbox:checked { right: 0; border-color: #22c55e; }
        .toggle-checkbox:checked + .toggle-label { background-color: #22c55e; }
        
        /* This style is for the "active" nav link */
        .nav-link-active {
            background-color: #ea580c; /* orange-600 */
            color: #ffffff;
        }
        .nav-link-default {
            color: #d1d5db; /* gray-300 */
        }
        .nav-link-default:hover {
            background-color: #4b5563; /* gray-700 */
            color: #ffffff;
        }
    </style>
</head>
<body class="bg-slate-100 font-sans antialiased">

    <!-- The main layout container -->
    <div class="relative min-h-screen lg:flex">

        <!-- Mobile Header (Visible on mobile, hidden on desktop) -->
        <header class="lg:hidden bg-white shadow-md sticky top-0 z-40">
            <div class="container mx-auto px-4 h-16 flex justify-between items-center">
                <a href="live_orders.php" class="text-2xl font-bold text-orange-600">
                    KitchCo Admin
                </a>
                <button id="mobile-menu-button" class="p-2 rounded-md text-gray-700">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- 
          Sidebar 
          - Hidden on mobile (translateX(-100%))
          - Visible on desktop (lg:translate-x-0)
          - Toggled by JavaScript for mobile
        -->
        <aside id="sidebar" class="bg-gray-900 text-white w-64 fixed inset-y-0 left-0 z-50
                        transform -translate-x-full lg:translate-x-0
                        transition-transform duration-300 ease-in-out
                        flex flex-col h-screen shadow-lg">
            
            <!-- Logo -->
            <div class="h-16 flex items-center px-6 border-b border-gray-700">
                <a href="live_orders.php" class="text-2xl font-bold text-orange-500">
                    KitchCo Admin
                </a>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
                <a href="live_orders.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default nav-link-active">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span class="font-medium">Live Orders</span>
                </a>
                
                <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10.5 11.25v6M13.5 11.25v6M2.25 7.5h19.5" /></svg>
                    <span class="font-medium">Order History</span>
                </a>

                <a href="manual_order.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                    <span class="font-medium">Manual Order Entry</span>
                </a>
                
                <hr class="border-gray-700 my-4">

                <p class="px-3 text-xs font-semibold text-gray-400 uppercase">Manage</p>
                <a href="manage_menu_items.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                    <span class="font-medium">Menu Items</span>
                </a>
                <a href="manage_categories.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V.75m0 6a2.25 2.25 0 01-2.25 2.25H2.25m0 0a2.25 2.25 0 01-2.25-2.25V.75m0 6l2.25 2.25m0 0l2.25 2.25m0 0l2.25 2.25m-2.25-2.25l-2.25 2.25m0 0l-2.25-2.25m6 0l2.25 2.25M9 15.75V21.75m0-6a2.25 2.25 0 00-2.25 2.25H2.25m0 0a2.25 2.25 0 00-2.25-2.25V15.75m0 6l2.25-2.25m0 0l2.25-2.25m0 0l2.25-2.25m-2.25 2.25l-2.25-2.25m0 0l-2.25 2.25m6 0l2.25-2.25" /></svg>
                    <span class="font-medium">Item Categories</span>
                </a>
                 <a href="manage_item_options.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" /></svg>
                    <span class="font-medium">Item Options</span>
                </a>

                <!-- 4. ROLE-BASED LINKS (ADMIN ONLY) -->
                <?php if (hasAdminAccess()): ?>
                    <hr class="border-gray-700 my-4">
                    <p class="px-3 text-xs font-semibold text-gray-400 uppercase">Administration</p>

                    <a href="manage_delivery_areas.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                        <svg xmlns="http://www.w.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <span class="font-medium">Delivery Areas</span>
                    </a>
                    <a href="homepage_manager.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                        <svg xmlns="http://www.w.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                        <span class="font-medium">Homepage Editor</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                        <svg xmlns="http://www.w.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.677 1.02-1.952 1.66-3.34 1.66-1.388 0-2.663-.64-3.34-1.66m6.68 0a6.72 6.72 0 01-.668 1.66c-.677 1.02-1.952 1.66-3.34 1.66s-2.663-.64-3.34-1.66a6.72 6.72 0 01-.668-1.66m6.68 0c.677-1.02 1.952-1.66 3.34-1.66 1.388 0 2.663.64 3.34 1.66m-6.68 0a6.72 6.72 0 00.668 1.66c.677 1.02 1.952 1.66 3.34 1.66s2.663-.64 3.34-1.66a6.72 6.72 0 00.668-1.66m-6.68 0H6.75m6.68 0h6.68m0 0c.677 1.02 1.952 1.66 3.34 1.66 1.388 0 2.663-.64 3.34-1.66a6.72 6.72 0 00.668-1.66m-6.68 0c-.677 1.02-1.952 1.66-3.34 1.66-1.388 0-2.663-.64-3.34-1.66a6.72 6.72 0 00-.668-1.66" /></svg>
                        <span class="font-medium">Marketing</span>
                    </a>
                    <a href="site_settings.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h3.75" /></svg>
                        <span class="font-medium">Store Settings</span>
                    </a>
                    <a href="manage_users.php" class="flex items-center space-x-3 px-3 py-2 rounded-lg nav-link-default">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128V21a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 21V5.25A2.25 2.25 0 015.25 3h9.75a2.25 2.25 0 012.25 2.25v.192" /></svg>
                        <span class="font-medium">User Management</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- User -->
            <div class="p-4 border-t border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-orange-500 flex items-center justify-center font-bold">
                            <?php echo e($user_initial); ?>
                        </div>
                        <div>
                            <div class="font-medium"><?php echo e($username); ?></div>
                            <div class="text-sm text-gray-400 capitalize"><?php echo e($user_role); ?></div>
                        </div>
                    </div>
                    <!-- Logout Button -->
                    <a href="logout.php" title="Logout" class="p-2 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <!-- This `main` tag is where all your other pages will go -->
        <main class="flex-1 lg:ml-64">
            
            <!-- This is the container for the page content -->
            <div class="p-6 lg:p-8">
                <!-- 
                Page content starts here. 
                For example, live_orders.php would put its content here.
                -->