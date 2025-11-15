<?php
/*
 * admin/live_orders.php
 * KitchCo: Cloud Kitchen Live Order Dashboard
 * Version 2.1 - (MODIFIED) Fixed store status toggle UI
 *
 * This is the main dashboard page. It's the "mission control" for the kitchen.
 */

// 1. HEADER
// Includes session start, DB connection, and security check.
require_once('header.php');

// 2. (MODIFIED) PHP logic to fetch stats
// Get the store's timezone to ensure "today" is accurate
$timezone = new DateTimeZone($settings['timezone'] ?? 'UTC');
$today_start = new DateTime('today 00:00:00', $timezone);
$today_start_mysql = $today_start->format('Y-m-d H:i:s');

// Query 1: Get Today's Sales
// We sum all non-cancelled orders from the start of today
$sql_sales = "SELECT SUM(total_amount) as total_sales 
              FROM orders 
              WHERE order_time >= ? 
              AND order_status != 'Cancelled'";
$stmt_sales = $db->prepare($sql_sales);
$stmt_sales->bind_param('s', $today_start_mysql);
$stmt_sales->execute();
$result_sales = $stmt_sales->get_result();
$sales_data = $result_sales->fetch_assoc();
$todays_sales = number_format($sales_data['total_sales'] ?? 0, 2);
$stmt_sales->close();

// Query 2: Get Today's Orders
// We count all non-cancelled orders from the start of today
$sql_orders = "SELECT COUNT(id) as total_orders 
               FROM orders 
               WHERE order_time >= ? 
               AND order_status != 'Cancelled'";
$stmt_orders = $db->prepare($sql_orders);
$stmt_orders->bind_param('s', $today_start_mysql);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();
$orders_data = $result_orders->fetch_assoc();
$todays_orders = $orders_data['total_orders'] ?? 0;
$stmt_orders->close();

$store_is_open = $settings['store_is_open'] == '1';

// --- (MODIFIED) LOAD TOP 5 PENDING, PREPARING, AND READY ORDERS ---
$pending_orders = [];
// (MODIFIED) Added LIMIT 5
$sql_pending = "SELECT * FROM orders WHERE order_status = 'Pending' ORDER BY order_time DESC LIMIT 5";
$res_pending = $db->query($sql_pending);
if ($res_pending) {
    while ($row = $res_pending->fetch_assoc()) {
        $pending_orders[] = $row;
    }
}

$preparing_orders = [];
// (MODIFIED) Added LIMIT 5
$sql_preparing = "SELECT * FROM orders WHERE order_status = 'Preparing' ORDER BY order_time DESC LIMIT 5";
$res_preparing = $db->query($sql_preparing);
if ($res_preparing) {
    while ($row = $res_preparing->fetch_assoc()) {
        $preparing_orders[] = $row;
    }
}

// (NEW) Load Ready orders
$ready_orders = [];
$sql_ready = "SELECT * FROM orders WHERE order_status = 'Ready' ORDER BY order_time DESC LIMIT 5";
$res_ready = $db->query($sql_ready);
if ($res_ready) {
    while ($row = $res_ready->fetch_assoc()) {
        $ready_orders[] = $row;
    }
}
// --- (END MODIFIED) ---

?>

<!-- 
This file uses the header.php file, which sets the <title>.
We can set a more specific title by echoing it in the <head>
but for now, we'll just set it in PHP.
    <!-- (MODIFIED) Store Open/Closed Toggle -->
    <!-- This now uses Tailwind's "peer" system to style itself -->
    <div class="flex items-center space-x-3 mt-4 sm:mt-0">
        <span class="font-medium text-gray-700">Store Status:</span>
        <label for="store-toggle" class="relative inline-flex items-center <?php echo hasAdminAccess() ? 'cursor-pointer' : 'cursor-not-allowed'; ?>">
            <input type="checkbox" id="store-toggle" class="sr-only peer" 
                   <?php echo $store_is_open ? 'checked' : ''; ?>
                   <?php echo hasAdminAccess() ? '' : 'disabled'; ?>>
            
            <!-- This is the track -->
            <div class="w-14 h-8 bg-gray-300 rounded-full transition-colors 
                        peer-checked:bg-green-600 
                        <?php echo hasAdminAccess() ? '' : 'opacity-50'; ?>">
            </div>
            
            <!-- This is the thumb -->
            <div class="absolute left-1 top-1 w-6 h-6 bg-white rounded-full shadow-md transform transition-transform 
                        peer-checked:translate-x-6">
            </div>
        </label>
        <span id="store-status-text" class="font-medium <?php echo $store_is_open ? 'text-green-600' : 'text-red-600'; ?>">
            <?php echo $store_is_open ? 'Open' : 'Closed'; ?>
        </span>
    </div>
</header>

<!-- (MODIFIED) Stats Cards - now 4 columns -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
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
                <div class="text-3xl font-bold text-gray-900" id="stat-new-orders"><?php echo count($pending_orders); ?></div>
            </div>
        </div>
    </div>

    <!-- (NEW) Card 4: Ready for Pickup -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="flex items-center space-x-4">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.263-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
            </div>
            <div>
                <div class="text-sm font-medium text-gray-500">Ready for Pickup</div>
                <div class="text-3xl font-bold text-gray-900" id="stat-ready-orders"><?php echo count($ready_orders); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- 
    (MODIFIED) Order Columns - now 3 columns
-->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- Column 1: New Orders (Live Feed) -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">New Orders (Live Feed)</h2>
            <p class="text-sm text-gray-500">Showing newest 5. (Sound on!)</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto" id="pending-orders-list">
            
            <?php if (empty($pending_orders)): ?>
                <p id="no-pending-orders" class="text-gray-500 text-center py-4">No pending orders.</p>
            <?php else: ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div id="order-card-<?php echo e($order['id']); ?>" class="order-card border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-gray-800">Order #PM-<?php echo e($order['id']); ?></span>
                                <span class="ml-2 text-sm text-green-700 font-medium">(<?php echo e(date('h:i A', strtotime($order['order_time']))); ?>)</span>
                            </div>
                            <span class="px-3 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">NEW</span>
                        </div>
                        <div class="mt-3">
                            <div class="font-medium text-gray-700">Customer: <?php echo e($order['customer_name']); ?></div>
                            <div class="text-sm text-gray-500">Address: <?php echo e($order['customer_address']); ?></div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <span class="text-xl font-bold text-gray-900"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                            <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                                View & Accept
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Column 2: In Progress (Preparing) -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">In Progress (Preparing)</h2>
            <p class="text-sm text-gray-500">Showing newest 5.</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto" id="preparing-orders-list">
            
            <?php if (empty($preparing_orders)): ?>
                <p id="no-preparing-orders" class="text-gray-500 text-center py-4">No orders are being prepared.</p>
            <?php else: ?>
                <?php foreach ($preparing_orders as $order): ?>
                    <div id="order-card-<?php echo e($order['id']); ?>" class="order-card border border-blue-300 bg-blue-50 rounded-lg p-4 transition-all hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-gray-800">Order #PM-<?php echo e($order['id']); ?></span>
                                <span class="ml-2 text-sm text-blue-700 font-medium">(<?php echo e(date('h:i A', strtotime($order['order_time']))); ?>)</span>
                            </div>
                            <span class="px-3 py-1 bg-blue-200 text-blue-800 text-xs font-bold rounded-full">PREPARING</span>
                        </div>
                        <div class="mt-3">
                            <div class="font-medium text-gray-700">Customer: <?php echo e($order['customer_name']); ?></div>
                            <div class="text-sm text-gray-500">Rider: <?php echo e($order['rider_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <span class="text-xl font-bold text-gray-900"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                            <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                                Mark as Ready
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
    
    <!-- (NEW) Column 3: Ready for Pickup -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">Ready for Pickup</h2>
            <p class="text-sm text-gray-500">Showing newest 5.</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto" id="ready-orders-list">
            
            <?php if (empty($ready_orders)): ?>
                <p id="no-ready-orders" class="text-gray-500 text-center py-4">No orders are ready for pickup.</p>
            <?php else: ?>
                <?php foreach ($ready_orders as $order): ?>
                    <div id="order-card-<?php echo e($order['id']); ?>" class="order-card border border-yellow-300 bg-yellow-50 rounded-lg p-4 transition-all hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-gray-800">Order #PM-<?php echo e($order['id']); ?></span>
                                <span class="ml-2 text-sm text-yellow-700 font-medium">(<?php echo e(date('h:i A', strtotime($order['order_time']))); ?>)</span>
                            </div>
                            <span class="px-3 py-1 bg-yellow-200 text-yellow-800 text-xs font-bold rounded-full">READY</span>
                        </div>
                        <div class="mt-3">
                            <div class="font-medium text-gray-700">Customer: <?php echo e($order['customer_name']); ?></div>
                            <div class="text-sm text-gray-500">Rider: <?php echo e($order['rider_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <span class="text-xl font-bold text-gray-900"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                            <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700">
                                Mark as Delivered
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- (NEW) Audio element for notification sound -->
<audio id="notification-sound" src="https://assets.mixkit.co/sfx/preview/mixkit-positive-notification-951.mp3" preload="auto"></audio>

<?php
// 3. FOOTER
// Includes all closing tags and mobile menu script
require_once('footer.php');
?>

<!-- (MODIFIED) Live Order Polling JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    
    // --- (START) NEW STORE STATUS TOGGLE LOGIC ---
    
    const storeToggle = document.getElementById('store-toggle');
    const storeStatusText = document.getElementById('store-status-text');
    const csrfToken = '<?php echo e(get_csrf_token()); ?>'; 
    
    if (storeToggle) {
        storeToggle.addEventListener('change', async function() {
            const isChecked = this.checked;
            // ... (store toggle logic is unchanged) ...
            try {
                const response = await fetch('ajax_update_store_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken // Send CSRF token in header
                    },
                    body: JSON.stringify({
                        store_is_open: isChecked
                    })
                });
                
                if (!response.ok) {
                    let errorDetails = `HTTP Error ${response.status}.`;
                    try {
                        const errorBody = await response.text();
                        if (errorBody.startsWith('<')) {
                            throw new Error('Server returned HTML instead of JSON (Possible PHP Warning).');
                        }
                        const jsonError = JSON.parse(errorBody);
                        errorDetails = jsonError.error || errorBody;
                    } catch (e) {
                         // If reading text/JSON fails, the status text is enough.
                    }
                    throw new Error(errorDetails);
                }

                const result = await response.json();
                
                if (result.success) {
                    storeStatusText.textContent = result.new_status_text;
                    if (result.new_status_text === 'Open') {
                        storeStatusText.classList.remove('text-red-600');
                        storeStatusText.classList.add('text-green-600');
                    } else {
                        storeStatusText.classList.remove('text-green-600');
                        storeStatusText.classList.add('text-red-600');
                    }
                } else {
                    throw new Error(result.error || 'Failed to update status.');
                }
                
            } catch (error) {
                console.error('Failed to update store status:', error);
                storeToggle.checked = !isChecked;
                const oldStatusText = isChecked ? 'Closed' : 'Open';
                storeStatusText.textContent = oldStatusText;
                alert('Error: ' + error.message);
            }
        });
    }
    
    // --- (END) NEW STORE STATUS TOGGLE LOGIC ---


    // --- (START) (MODIFIED) LIVE ORDER POLLING LOGIC ---
    
    // Get references to DOM elements
    const pendingList = document.getElementById('pending-orders-list');
    const preparingList = document.getElementById('preparing-orders-list');
    const readyList = document.getElementById('ready-orders-list'); // (NEW)
    
    const notificationSound = document.getElementById('notification-sound');
    
    const statNewOrders = document.getElementById('stat-new-orders');
    const statReadyOrders = document.getElementById('stat-ready-orders'); // (NEW)
    
    // (NEW) Store a set of currently visible pending order IDs
    let displayedPendingIDs = new Set();
    
    function getDisplayedPendingIDs() {
        const ids = new Set();
        const pendingCards = pendingList.querySelectorAll('.order-card');
        pendingCards.forEach(card => {
            ids.add(card.id); // card.id is "order-card-7"
        });
        return ids;
    }
    displayedPendingIDs = getDisplayedPendingIDs();
    
    function playSound() {
        notificationSound.currentTime = 0; // Rewind to start
        notificationSound.play().catch(e => console.log("Sound play failed:", e));
    }

    // --- (MODIFIED) Function to create an Order Card HTML ---
    function createOrderCard(order) {
        const time = new Date(order.order_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const cardId = `order-card-${order.id}`;
        
        if (order.order_status === 'Pending') {
            return `
            <div id="${cardId}" class="order-card border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #PM-${order.id}</span>
                        <span class="ml-2 text-sm text-green-700 font-medium">(${time})</span>
                    </div>
                    <span class="px-3 py-1 bg-green-200 text-green-800 text-xs font-bold rounded-full">NEW</span>
                </div>
                <div class="mt-3">
                    <div class="font-medium text-gray-700">Customer: ${order.customer_name}</div>
                    <div class="text-sm text-gray-500">Address: ${order.customer_address}</div>
                </div>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">${parseFloat(order.total_amount).toFixed(2)} BDT</span>
                    <a href="order_details.php?id=${order.id}" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                        View & Accept
                    </a>
                </div>
            </div>`;
        }
        
        if (order.order_status === 'Preparing') {
            return `
            <div id="${cardId}" class="order-card border border-blue-300 bg-blue-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #PM-${order.id}</span>
                        <span class="ml-2 text-sm text-blue-700 font-medium">(${time})</span>
                    </div>
                    <span class="px-3 py-1 bg-blue-200 text-blue-800 text-xs font-bold rounded-full">PREPARING</span>
                </div>
                <div class="mt-3">
                    <div class="font-medium text-gray-700">Customer: ${order.customer_name}</div>
                    <div class="text-sm text-gray-500">Rider: ${order.rider_name || 'Not assigned'}</div>
                </div>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">${parseFloat(order.total_amount).toFixed(2)} BDT</span>
                    <a href="order_details.php?id=${order.id}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                        Mark as Ready
                    </a>
                </div>
            </div>`;
        }

        // (NEW) Card for "Ready" status
        if (order.order_status === 'Ready') {
            return `
            <div id="${cardId}" class="order-card border border-yellow-300 bg-yellow-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #PM-${order.id}</span>
                        <span class="ml-2 text-sm text-yellow-700 font-medium">(${time})</span>
                    </div>
                    <span class="px-3 py-1 bg-yellow-200 text-yellow-800 text-xs font-bold rounded-full">READY</span>
                </div>
                <div class="mt-3">
                    <div class="font-medium text-gray-700">Customer: ${order.customer_name}</div>
                    <div class="text-sm text-gray-500">Rider: ${order.rider_name || 'Not assigned'}</div>
                </div>
                <div class="mt-3 flex justify-between items-center">
                    <span class="text-xl font-bold text-gray-900">${parseFloat(order.total_amount).toFixed(2)} BDT</span>
                    <a href="order_details.php?id=${order.id}" class="px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700">
                        Mark as Delivered
                    </a>
                </div>
            </div>`;
        }
    }

    // --- (MODIFIED) Function to update the DOM with new lists ---
    function updateOrderLists(pendingOrders, preparingOrders, readyOrders) {
        let newOrderSound = false;
        
        // --- 1. Update Pending List ---
        let pendingHtml = '';
        const newPendingIDs = new Set();
        pendingOrders.forEach(order => {
            pendingHtml += createOrderCard(order);
            const cardId = `order-card-${order.id}`;
            newPendingIDs.add(cardId);
            
            // Check if this ID was NOT in the previously displayed list
            if (!displayedPendingIDs.has(cardId)) {
                newOrderSound = true;
            }
        });
        
        pendingList.innerHTML = pendingHtml;
        displayedPendingIDs = newPendingIDs; // Update the "current state"
        
        if (pendingOrders.length === 0) {
            pendingList.innerHTML = '<p id="no-pending-orders" class="text-gray-500 text-center py-4">No pending orders.</p>';
        }

        // --- 2. Update Preparing List ---
        let preparingHtml = '';
        preparingOrders.forEach(order => {
            preparingHtml += createOrderCard(order);
        });
        
        preparingList.innerHTML = preparingHtml;
        
        if (preparingOrders.length === 0) {
            preparingList.innerHTML = '<p id="no-preparing-orders" class="text-gray-500 text-center py-4">No orders are being prepared.</p>';
        }
        
        // --- 3. (NEW) Update Ready List ---
        let readyHtml = '';
        readyOrders.forEach(order => {
            readyHtml += createOrderCard(order);
        });
        
        readyList.innerHTML = readyHtml;
        
        if (readyOrders.length === 0) {
            readyList.innerHTML = '<p id="no-ready-orders" class="text-gray-500 text-center py-4">No orders are ready for pickup.</p>';
        }
        
        // --- 4. Play sound if needed ---
        if (newOrderSound) {
            playSound();
        }
    }

    // --- Function to fetch new data ---
    async function checkNewOrders() {
        try {
            // (MODIFIED) No longer sending last_id
            const response = await fetch(`ajax_check_new_orders.php`);
            if (!response.ok) {
                console.error('Network error checking orders.');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                // 1. Update stats
                statNewOrders.textContent = data.pending_count;
                statReadyOrders.textContent = data.ready_count; // (NEW)
                
                // 2. Rebuild the lists in the DOM
                updateOrderLists(data.pending_orders, data.preparing_orders, data.ready_orders); // (MODIFIED)
            }
            
        } catch (error) {
            console.error('Error polling for orders:', error);
        }
    }

    // --- Start the polling ---
    // Check every 15 seconds
    setInterval(checkNewOrders, 15000);
    
    // --- (END) LIVE ORDER POLLING LOGIC ---
});
</script>
<!-- (END NEW) -->