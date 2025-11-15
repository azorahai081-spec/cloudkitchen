<?php
/*
 * admin/order_details.php
 * KitchCo: Cloud Kitchen Order Details Page
 * Version 1.8 - (NEW) Added Admin-only delete functionality
 *
 * This page:
 * 1. Loads a single order and all its items/options.
 * 2. Allows staff to update the order status.
 * 3. Allows staff to assign a rider.
 */

// 1. HEADER
require_once('header.php');

// 2. --- GET ORDER ID ---
$order_id = $_GET['id'] ?? null;
if (empty($order_id)) {
    header('Location: live_orders.php');
    exit;
}
$order_id = (int)$order_id;
// (MODIFIED) Changed prefix
$page_title = "Order Details #PM-{$order_id}";

// 3. --- HANDLE POST ACTIONS (Update Status / Assign Rider) ---
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // A. Handle Status Update
        if (isset($_POST['update_status'])) {
            $new_status = $_POST['new_status'];
            $allowed_statuses = ['Pending', 'Preparing', 'Ready', 'Delivered', 'Cancelled'];
            
            if (in_array($new_status, $allowed_statuses)) {
                // (FIXED) Update WHERE id = ?
                $sql_update = "UPDATE orders SET order_status = ? WHERE id = ?";
                $stmt_update = $db->prepare($sql_update);
                $stmt_update->bind_param('si', $new_status, $order_id);
                if ($stmt_update->execute()) {
                    $success_message = "Order status updated to '{$new_status}'!";
                    if ($new_status == 'Preparing') {
                        $success_message .= " Order moved to 'In Progress'.";
                    }
                } else {
                    $error_message = 'Failed to update status.';
                }
                $stmt_update->close();
            }
        }
        
        // B. Handle Rider Assignment
        if (isset($_POST['assign_rider'])) {
            // (FIXED) Changed 'assigned_rider_name' to 'rider_name' to match DB
            $rider_name = trim($_POST['rider_name']); 
            
            // (FIXED) Update WHERE id = ?
            // (FIXED) Changed 'assigned_rider_name' to 'rider_name'
            $sql_rider = "UPDATE orders SET rider_name = ? WHERE id = ?";
            $stmt_rider = $db->prepare($sql_rider);
            $stmt_rider->bind_param('si', $rider_name, $order_id);
            if ($stmt_rider->execute()) {
                $success_message = "Rider '{$rider_name}' assigned successfully!";
            } else {
                $error_message = 'Failed to assign rider.';
            }
            $stmt_rider->close();
        }
    }
}


// 4. --- LOAD ORDER DATA ---
// A. Load Order Header
// (FIXED) Select WHERE o.id = ?
$sql_order = "SELECT o.*, da.area_name 
              FROM orders o
              LEFT JOIN delivery_areas da ON o.delivery_area_id = da.id
              WHERE o.id = ?";
$stmt_order = $db->prepare($sql_order);
$stmt_order->bind_param('i', $order_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows == 0) {
    // Order not found
    header('Location: manage_orders.php');
    exit;
}
$order = $result_order->fetch_assoc();

// B. Load Order Items
$order_items = [];
// (MODIFIED) Initialize $stmt_options to null to prevent crash
$stmt_options = null; 
// (MODIFIED) Changed to LEFT JOIN to handle deleted menu items
$sql_items = "SELECT oi.*, mi.name as item_name
              FROM order_items oi
              LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
              WHERE oi.order_id = ?";
$stmt_items = $db->prepare($sql_items);
$stmt_items->bind_param('i', $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($item_row = $result_items->fetch_assoc()) {
    // (MODIFIED) Check if item was deleted
    $item_row['item_name'] = $item_row['item_name'] ?? '[Deleted Item]';
    
    // (FIXED) The primary key of the order_items table is 'id', not 'order_item_id'
    $order_item_id = $item_row['id']; 
    $item_row['options'] = [];
    
    // C. Load Options for this item
    // (FIXED) This links to order_items.id
    $sql_options = "SELECT * FROM order_item_options WHERE order_item_id = ?";
    // (MODIFIED) This variable is now initialized
    $stmt_options = $db->prepare($sql_options); 
    $stmt_options->bind_param('i', $order_item_id);
    $stmt_options->execute();
    $result_options = $stmt_options->get_result();
    
    while ($option_row = $result_options->fetch_assoc()) {
        $item_row['options'][] = $option_row;
    }
    $order_items[] = $item_row;
}
$stmt_order->close();
$stmt_items->close();

// (MODIFIED) Only close $stmt_options if it was actually initialized
// This fixes the fatal crash on orders with no items.
if ($stmt_options !== null) {
    $stmt_options->close();
}
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900"><?php echo e($page_title); ?></h1>
        <p class="text-gray-600 mt-1">
            Order placed at <?php echo e(date('d M Y, h:i A', strtotime($order['order_time']))); ?>
        </p>
    </div>
    <div class="flex space-x-2 mt-4 sm:mt-0">
        <a href="print_receipt.php?id=<?php echo e($order_id); ?>&copy=customer" target="_blank"
           class="px-5 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700">
            Print Customer Copy
        </a>
        <a href="print_receipt.php?id=<?php echo e($order_id); ?>&copy=chef" target="_blank"
           class="px-5 py-2 bg-gray-700 text-white font-medium rounded-lg shadow-md hover:bg-gray-800">
            Print Chef Copy
        </a>
    </div>
</div>

<!-- Success & Error Messages -->
<?php if (!empty($success_message)): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
        <?php echo e($success_message); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">
        <?php echo e($error_message); ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Order Items & Actions -->
    <div class="lg:col-span-2">
        <!-- Order Items -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Order Items</h2>
            <div class="space-y-4">
                <?php if (empty($order_items)): ?>
                    <p class="text-gray-500">No items were found for this order. (This may have been a test order with no items.)</p>
                <?php else: ?>
                    <?php foreach ($order_items as $item): ?>
                    <div class="flex justify-between items-start border-b pb-4">
                        <div class="flex-1">
                            <p class="text-lg font-bold text-gray-800">
                                <?php echo e($item['quantity']); ?>x <?php echo e($item['item_name']); ?>

                            </p>
                            <!-- Options -->
                            <?php if (!empty($item['options'])): ?>
                            <ul class="text-sm text-gray-600 list-disc list-inside pl-4 mt-1">
                                <?php foreach ($item['options'] as $option): ?>
                                <li>
                                    <?php echo e($option['option_name']); ?> 
                                    (+<?php echo e(number_format($option['option_price'], 2)); ?>)
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900">
                                <?php echo e(number_format($item['total_price'], 2)); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                (<?php echo e(number_format($item['base_price'], 2)); ?> base)
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Totals -->
            <div class="mt-6 space-y-2 border-t pt-4">
                <div class="flex justify-between text-lg">
                    <span class="text-gray-700">Subtotal</span>
                    <span class="font-medium text-gray-900"><?php echo e(number_format($order['subtotal'], 2)); ?></span>
                </div>
                <div class="flex justify-between text-lg">
                    <span class="text-gray-700">Delivery Fee</span>
                    <span class="font-medium text-gray-900"><?php echo e(number_format($order['delivery_fee'], 2)); ?></span>
                </div>
                <div class="flex justify-between text-2xl font-bold">
                    <span class="text-gray-900">Grand Total</span>
                    <span class="text-orange-600"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                </div>
            </div>
        </div>

        <!-- Order Actions -->
        <div class="bg-white p-6 rounded-2xl shadow-lg mt-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Order Actions</h2>
            <form action="order_details.php?id=<?php echo e($order_id); ?>" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <div>
                    <label for="new_status" class="block text-sm font-medium text-gray-700">Change Order Status</label>
                    <select id="new_status" name="new_status" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="Pending" <?php echo ($order['order_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="Preparing" <?php echo ($order['order_status'] == 'Preparing') ? 'selected' : ''; ?>>Preparing</option>
                        <option value="Ready" <?php echo ($order['order_status'] == 'Ready') ? 'selected' : ''; ?>>Ready for Pickup</option>
                        <option value="Delivered" <?php echo ($order['order_status'] == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo ($order['order_status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="w-full py-3 px-4 bg-green-600 text-white font-medium rounded-lg shadow-md hover:bg-green-700">
                    Update Status
                </button>
            </form>
            
            <hr class="my-6">
            
            <form action="order_details.php?id=<?php echo e($order_id); ?>" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <div>
                    <label for="rider_name" class="block text-sm font-medium text-gray-700">Assign Rider</label>
                    <!-- (FIXED) Changed 'assigned_rider_name' to 'rider_name' -->
                    <input type="text" id="rider_name" name="rider_name" 
                           value="<?php echo e($order['rider_name'] ?? ''); ?>"
                           placeholder="Type rider's name"
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <button type="submit" name="assign_rider" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                    Assign Rider
                </button>
            </form>

            <!-- (NEW) Admin-only Delete Section -->
            <?php if (hasAdminAccess()): ?>
                <hr class="my-6 border-red-300">
                <div>
                    <label class="block text-sm font-medium text-red-700">Danger Zone</label>
                    <p class="text-xs text-gray-500 mb-2">This action is permanent and cannot be undone.</p>
                    <a href="manage_orders.php?action=delete&id=<?php echo e($order_id); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                       class="w-full flex justify-center py-3 px-4 bg-red-600 text-white font-medium rounded-lg shadow-md hover:bg-red-700"
                       onclick="return confirm('WARNING: This will permanently delete order #PM-<?php echo e($order_id); ?> and all its items. This action cannot be undone. Are you sure?');">
                        Permanently Delete This Order
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Column 2: Customer Details -->
    <aside class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Customer Details</h2>
            <div class="space-y-3">
                <div>
                    <label class="text-sm font-medium text-gray-500">Name</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo e($order['customer_name']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Phone</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo e($order['customer_phone']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Delivery Area</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo e($order['area_name'] ?? 'N/A'); ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Address</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo e($order['customer_address']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment</label>
                    <p class="text-lg font-medium text-gray-900">Cash on Delivery</p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Rider</label>
                    <!-- (FIXED) Changed 'assigned_rider_name' to 'rider_name' -->
                    <p class="text-lg font-medium text-gray-900">
                        <?php echo e(empty($order['rider_name']) ? 'Not Assigned' : $order['rider_name']); ?>
                    </p>
                </div>
            </div>
        </div>
    </aside>
</div>

<?php
// 5. FOOTER
require_once('footer.php');
?>