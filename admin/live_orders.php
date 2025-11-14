<?php
/*
 * admin/live_orders.php
 * KitchCo: Cloud Kitchen Live Order Dashboard
 * Version 1.5 - Applied Store Status Fixes
 *
 * This is the main dashboard page. It's the "mission control" for the kitchen.
 */

// 1. HEADER
// Includes session start, DB connection, and security check.
require_once('header.php');

// 2. (Optional) PHP logic to fetch stats
// These are placeholders for now
$todays_sales = "12,540"; // You can build a query for this
$todays_orders = "42"; // You can build a query for this
$store_is_open = $settings['store_is_open'] == '1';

// --- (NEW) LOAD PENDING & PREPARING ORDERS ---
$pending_orders = [];
$sql_pending = "SELECT * FROM orders WHERE order_status = 'Pending' ORDER BY order_time ASC";
$res_pending = $db->query($sql_pending);
if ($res_pending) {
    while ($row = $res_pending->fetch_assoc()) {
        $pending_orders[] = $row;
    }
}

$preparing_orders = [];
$sql_preparing = "SELECT * FROM orders WHERE order_status = 'Preparing' ORDER BY order_time ASC";
$res_preparing = $db->query($sql_preparing);
if ($res_preparing) {
    while ($row = $res_preparing->fetch_assoc()) {
        $preparing_orders[] = $row;
    }
}
// --- (END NEW) ---

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
    
    <!-- (MODIFIED) Store Open/Closed Toggle -->
    <!-- This is now a functional switch (if user is admin) -->
    <div class="flex items-center space-x-3 mt-4 sm:mt-0">
        <span class="font-medium text-gray-700">Store Status:</span>
        <label for="store-toggle" class="relative inline-flex items-center <?php echo hasAdminAccess() ? 'cursor-pointer' : 'cursor-not-allowed'; ?>">
            <input type="checkbox" id="store-toggle" class="sr-only toggle-checkbox" 
                   <?php echo $store_is_open ? 'checked' : ''; ?>
                   <?php echo hasAdminAccess() ? '' : 'disabled'; ?>>
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
                <div class="text-3xl font-bold text-gray-900" id="stat-new-orders"><?php echo count($pending_orders); ?></div>
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
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto" id="pending-orders-list">
            
            <!-- (MODIFIED) Orders are now loaded from PHP -->
            <?php if (empty($pending_orders)): ?>
                <p id="no-pending-orders" class="text-gray-500 text-center py-4">No pending orders.</p>
            <?php else: ?>
                <?php foreach ($pending_orders as $order): ?>
                    <div id="order-card-<?php echo e($order['id']); ?>" class="order-card border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-gray-800">Order #<?php echo e($order['id']); ?></span>
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
                            <!-- (NEW) Link to order details -->
                            <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                                View & Accept
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- (END MODIFIED) -->

        </div>
    </div>

    <!-- Column 2: In Progress (Preparing) -->
    <div class="bg-white rounded-2xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-xl font-bold text-gray-900">In Progress (Preparing)</h2>
            <p class="text-sm text-gray-500">Orders you have already accepted.</p>
        </div>
        <div class="p-6 space-y-4 max-h-96 overflow-y-auto" id="preparing-orders-list">
            
            <!-- (MODIFIED) Orders are now loaded from PHP -->
            <?php if (empty($preparing_orders)): ?>
                <p id="no-preparing-orders" class="text-gray-500 text-center py-4">No orders are being prepared.</p>
            <?php else: ?>
                <?php foreach ($preparing_orders as $order): ?>
                    <div id="order-card-<?php echo e($order['id']); ?>" class="order-card border border-blue-300 bg-blue-50 rounded-lg p-4 transition-all hover:shadow-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="text-lg font-bold text-gray-800">Order #<?php echo e($order['id']); ?></span>
                                <span class="ml-2 text-sm text-blue-700 font-medium">(<?php echo e(date('h:i A', strtotime($order['order_time']))); ?>)</span>
                            </div>
                            <span class="px-3 py-1 bg-blue-200 text-blue-800 text-xs font-bold rounded-full">PREPARING</span>
                        </div>
                        <div class="mt-3">
                            <div class="font-medium text-gray-700">Customer: <?php echo e($order['customer_name']); ?></div>
                            <!-- (FIXED) Changed 'assigned_rider_name' to 'rider_name' -->
                            <div class="text-sm text-gray-500">Rider: <?php echo e($order['rider_name'] ?? 'Not assigned'); ?></div>
                        </div>
                        <div class="mt-3 flex justify-between items-center">
                            <span class="text-xl font-bold text-gray-900"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                            <!-- (NEW) Link to order details -->
                            <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
                                Mark as Ready
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- (END MODIFIED) -->

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
    // Get the current CSRF token from the header (assuming it's available)
    // We will use the one from the 'logout' link
    const csrfToken = '<?php echo e(get_csrf_token()); ?>'; 
    
    if (storeToggle) {
        storeToggle.addEventListener('change', async function() {
            const isChecked = this.checked;
            const newStatusText = isChecked ? 'Open' : 'Closed';
            
            // Optimistically update the UI
            storeStatusText.textContent = 'Updating...';
            
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
                
                // IMPORTANT: Check response status BEFORE trying to parse JSON
                if (!response.ok) {
                    // Try to read error message if provided, otherwise default.
                    let errorDetails = `HTTP Error ${response.status}.`;
                    try {
                        const errorBody = await response.text();
                        // This handles the error you saw previously: non-JSON output.
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
                    // Success! Update text and color
                    storeStatusText.textContent = result.new_status_text;
                    if (result.new_status_text === 'Open') {
                        storeStatusText.classList.remove('text-red-600');
                        storeStatusText.classList.add('text-green-600');
                    } else {
                        storeStatusText.classList.remove('text-green-600');
                        storeStatusText.classList.add('text-red-600');
                    }
                } else {
                    // Revert on failure
                    throw new Error(result.error || 'Failed to update status.');
                }
                
            } catch (error) {
                console.error('Failed to update store status:', error);
                // Revert the toggle and text
                storeToggle.checked = !isChecked;
                const oldStatusText = isChecked ? 'Closed' : 'Open';
                storeStatusText.textContent = oldStatusText;
                alert('Error: ' + error.message);
            }
        });
    }
    
    // --- (END) NEW STORE STATUS TOGGLE LOGIC ---


    // --- (START) LIVE ORDER POLLING LOGIC ---
    
    // Get references to DOM elements
    const pendingList = document.getElementById('pending-orders-list');
    const noPendingMsg = document.getElementById('no-pending-orders');
    const preparingList = document.getElementById('preparing-orders-list');
    const noPreparingMsg = document.getElementById('no-preparing-orders');
    const notificationSound = document.getElementById('notification-sound');
    const statNewOrders = document.getElementById('stat-new-orders');
    
    // Store the ID of the latest order we've seen
    let lastKnownOrderId = 0;
    
    // Find the latest order ID on the page right now
    const existingCards = document.querySelectorAll('.order-card');
    if (existingCards.length > 0) {
        const ids = Array.from(existingCards).map(card => parseInt(card.id.split('-')[2]));
        lastKnownOrderId = Math.max(...ids);
    }
    
    // --- Function to play notification sound ---
    function playSound() {
        notificationSound.currentTime = 0; // Rewind to start
        notificationSound.play().catch(e => console.log("Sound play failed:", e));
    }

    // --- Function to create an Order Card HTML ---
    function createOrderCard(order) {
        const time = new Date(order.order_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        
        if (order.order_status === 'Pending') {
            return `
            <div id="order-card-${order.id}" class="order-card border border-green-300 bg-green-50 rounded-lg p-4 transition-all hover:shadow-md opacity-0 transform -translate-y-4">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #${order.id}</span>
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
            // (FIXED) Changed 'order.assigned_rider_name' to 'order.rider_name'
            return `
            <div id="order-card-${order.id}" class="order-card border border-blue-300 bg-blue-50 rounded-lg p-4 transition-all hover:shadow-md">
                <div class="flex justify-between items-center">
                    <div>
                        <span class="text-lg font-bold text-gray-800">Order #${order.id}</span>
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
    }

    // --- Function to fetch new data ---
    async function checkNewOrders() {
        try {
            const response = await fetch(`ajax_check_new_orders.php?last_id=${lastKnownOrderId}`);
            if (!response.ok) {
                console.error('Network error checking orders.');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                let newOrdersFound = false;
                
                // 1. Add new pending orders
                if (data.new_orders && data.new_orders.length > 0) {
                    if (noPendingMsg) noPendingMsg.style.display = 'none';
                    
                    data.new_orders.forEach(order => {
                        if (order.id > lastKnownOrderId) {
                            lastKnownOrderId = order.id;
                        }
                        // Add card to top of list
                        const cardHtml = createOrderCard(order);
                        pendingList.insertAdjacentHTML('afterbegin', cardHtml);
                        
                        // Animate it
                        // (FIXED) Changed 'order.order_id' to 'order.id'
                        setTimeout(() => {
                            const newCard = document.getElementById(`order-card-${order.id}`);
                            if (newCard) {
                                newCard.classList.remove('opacity-0', '-translate-y-4');
                            }
                        }, 50);
                        
                        newOrdersFound = true;
                    });
                }
                
                // 2. Move updated orders (from Pending to Preparing)
                if (data.updated_orders && data.updated_orders.length > 0) {
                    data.updated_orders.forEach(order => {
                        // Check if it's on the page
                        const oldCard = document.getElementById(`order-card-${order.id}`);
                        if (oldCard) {
                            oldCard.remove(); // Remove from Pending list
                        }
                        
                        // Add to Preparing list
                        if (noPreparingMsg) noPreparingMsg.style.display = 'none';
                        const cardHtml = createOrderCard(order);
                        preparingList.insertAdjacentHTML('afterbegin', cardHtml);
                    });
                }
                
                // 3. Update stats
                statNewOrders.textContent = data.pending_count;
                if (data.pending_count === 0 && noPendingMsg) {
                    noPendingMsg.style.display = 'block';
                }
                if (data.preparing_count === 0 && noPreparingMsg) {
                    noPreparingMsg.style.display = 'block';
                }

                // 4. Play sound if new
                if (newOrdersFound) {
                    playSound();
                }
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