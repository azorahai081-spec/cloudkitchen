<?php
/*
 * admin/live_orders.php
 * KitchCo: Cloud Kitchen Live Order Dashboard
 * Version 1.0
 *
 * This is the main dashboard page. It's the "mission control" for the kitchen.
 * For now, it's a static layout. We will add the "live" polling in Phase 4.
 */

// 1. HEADER
// Includes session start, DB connection, and security check.
require_once('header.php');

// 2. (Optional) PHP logic to fetch stats
// In the future, we will put PHP here to calculate these numbers.
$todays_sales = "12,540";
$todays_orders = "42";
$new_orders = "3";
$store_is_open = $settings['store_is_open'] == '1';

?>

<!-- 
This file uses the header.php file, which sets the <title>.
We can set a more specific title by echoing it in the <head>
but for now, we'll just set it in PHP.
-->
<?php $page_title = 'Live Order Dashboard'; ?>

<!-- Main Header -->
<header class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Live Dashboard</h1>
        <p class="text-gray-600 mt-1">Welcome back, <?php echo $username; ?>. Here's what's happening.</p>
    </div>
    
    <!-- Store Open/Closed Toggle (From admin/settings.php) -->
    <div class="flex items-center space-x-3 mt-4 sm:mt-0">
        <span class="font-medium text-gray-700">Store Status:</span>
        <label for="store-toggle" class="relative inline-flex items-center cursor-pointer">
            <!-- 
                This is a demo toggle. In Phase 5, we will make this
                a real form that updates the database via AJAX.
            -->
            <input type="checkbox" id="store-toggle" class="sr-only toggle-checkbox" <?php echo $store_is_open ? 'checked' : ''; ?>>
            <div class="w-14 h-8 bg-gray-300 rounded-full transition-all"></div>
            <div class="absolute left-1 top-1 w-6 h-6 bg-white rounded-full shadow-md transform transition-all toggle-checkbox"></div>
        </label>
        <span id="store-status-text" class="font-medium <?php echo $store_is_open ? 'text-green-600' : 'text-red-600'; ?>">
            <?php echo $store_is_open ? 'Open' : 'Closed'; ?>
        </span>
    </div>
</header>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <!-- Card 1: Today's Sales -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c.171.127.38.19.59.19s.419-.063.59-.19l.879-.659m-2.118-5.514l.879.659c.171.127.38.19.59.19s.419-.063.59-.19l.879-.659m-2.118-5.514l.879.659c.171.127.38.19.59.19s.419-.063.59-.19l.879-.659M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-18 0h18" /></svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Today's Sales</div>
                <div class="text-3xl font-bold text-gray-900"><?php echo e($todays_sales); ?> BDT</div>
            </div>
        </div>
    </div>
    
    <!-- Card 2: Today's Orders -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Today's Orders</div>
                <div class="text-3xl font-bold text-gray-900"><?php echo e($todays_orders); ?></div>
            </div>
        </div>
    </div>

    <!-- Card 3: New Orders (Pending) -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">New Orders (Pending)</div>
                <div class="text-3xl font-bold text-gray-900"><?php echo e($new_orders); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 
    Order Columns (The "Live Order Screen")
    This is the main part of admin/live_orders.php 
-->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Column 1: New Orders (Live Feed) -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">New Orders (Live Feed)</h2>
            <p class="text-sm text-gray-500">New orders will appear here automatically. (Sound on!)</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto">
            
            <!-- This is a placeholder. We will fetch this with AJAX. -->
            <div class="border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #1256</span>
                        <span class="ml-2 text-sm text-green-700 font-medium">(2m ago)</span>
                    </div>
                    <span class="px-3 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">NEW</span>
                </div>
                <div class="mt-3">
                    <div class="font-medium text-gray-700">Customer: Rahim Sheikh</div>
                    <div class="text-sm text-gray-500">Address: Gulshan 1, Road 12</div>
                </div>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">850 BDT</span>
                    <button class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                        View & Accept
                    </button>
                </div>
            </div>
            
            <!-- This is a placeholder. -->
            <div class="border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #1255</span>
                        <span class="ml-2 text-sm text-green-700 font-medium">(5m ago)</span>
                    </div>
                    <span class="px-3 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">NEW</span>
                </div>
                <div class="mt-3">
                    <div class="font-medium text-gray-700">Customer: Ayesha Begum</div>
                    <div class="text-sm text-gray-500">Address: Dhanmondi 32</div>
                </div>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">1,420 BDT</span>
                    <button class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                        View & Accept
                    </button>
                </div>
            </div>

        </div>
    </div>

    <!-- Column 2: In Progress (Preparing) -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">In Progress (Preparing)</h2>
            <p class="text-sm text-gray-500">Orders you have already accepted.</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto">
            
            <!-- This is a placeholder. -->
            <div class="border border-blue-300 bg-blue-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #1254</span>
                        <span class="ml-2 text-sm text-blue-700 font-medium">(10m ago)</span>
                    </div>
                    <span class="px-3 py-1 bg-blue-200 text-blue-800 text-xs font-bold rounded-full">PREPARING</span>
                </div>
                <ul class="text-sm text-gray-700 mt-3 list-disc list-inside">
                    <li>Kacchi Biryani (x2)</li>
                    <li>Shorshe Ilish (x1)</li>
                    <li>Coke (x3)</li>
                </ul>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">1120 BDT</span>
                    <button class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        Mark as Ready
                    </button>
                </div>
            </div>

        </div>
    </div>

</div>

<?php
// 3. FOOTER
// Includes all closing tags and mobile menu script
require_once('footer.php');
?>