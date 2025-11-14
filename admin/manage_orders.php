<?php
/*
 * admin/manage_orders.php
 * KitchCo: Cloud Kitchen Order History Page
 * Version 1.1 - Added CSRF Protection & fixed order_id key
 *
 * This page provides a full, searchable list of ALL orders.
 */

// 1. HEADER
require_once('header.php');

// 2. PAGE TITLE
$page_title = 'Order History';

// 3. --- (NEW) HANDLE STATUS UPDATE ---
// This is a quick-action form on the page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        // Handle error - for simplicity, we'll just ignore the update
    } else {
        $order_id_to_update = $_POST['order_id'];
        $new_status = $_POST['new_status'];
        
        $allowed_statuses = ['Pending', 'Preparing', 'Ready', 'Delivered', 'Cancelled'];
        
        if (in_array($new_status, $allowed_statuses)) {
            $sql_update = "UPDATE orders SET order_status = ? WHERE id = ?";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bind_param('si', $new_status, $order_id_to_update);
            $stmt_update->execute();
            $stmt_update->close();
            
            // (Optional: Add success/error message)
        }
    }
}


// 4. --- LOAD DATA FOR DISPLAY ---
// Basic filtering (can be expanded)
$filter_status = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

$sql = "SELECT * FROM orders";
$where_clauses = [];

if (!empty($filter_status)) {
    $where_clauses[] = "order_status = '" . $db->real_escape_string($filter_status) . "'";
}
if (!empty($search_query)) {
    $sq = $db->real_escape_string($search_query);
    $where_clauses[] = "(customer_name LIKE '%$sq%' OR customer_phone LIKE '%$sq%' OR id LIKE '%$sq%')";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY order_time DESC LIMIT 100"; // Limit to most recent 100

$orders = [];
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
}
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900"><?php echo e($page_title); ?></h1>
        <p class="text-gray-600 mt-1">Search, view, and manage all past and present orders.</p>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
    <form action="manage_orders.php" method="GET" class="flex flex-col sm:flex-row gap-4">
        <input 
            type="text" 
            name="search" 
            placeholder="Search by ID, Name, or Phone..." 
            value="<?php echo e($search_query); ?>"
            class="flex-grow px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        
        <select name="status" class="px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">All Statuses</option>
            <option value="Pending" <?php echo ($filter_status == 'Pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="Preparing" <?php echo ($filter_status == 'Preparing') ? 'selected' : ''; ?>>Preparing</option>
            <option value="Ready" <?php echo ($filter_status == 'Ready') ? 'selected' : ''; ?>>Ready</option>
            <option value="Delivered" <?php echo ($filter_status == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
            <option value="Cancelled" <?php echo ($filter_status == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
        </select>
        
        <button type="submit" class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
            Search
        </button>
    </form>
</div>

<!-- Order List Table -->
<div class="bg-white rounded-2xl shadow-lg">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td class="px-6 py-4"><div class="text-sm font-bold text-gray-900">#<?php echo e($order['id']); ?></div></td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo e($order['customer_name']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo e($order['customer_phone']); ?></div>
                            </td>
                            <td class="px-6 py-4"><div class="text-sm text-gray-700"><?php echo e(date('d M, h:i A', strtotime($order['order_time']))); ?></div></td>
                            <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e(number_format($order['total_amount'], 2)); ?></div></td>
                            <td class="px-6 py-4">
                                <!-- (NEW) Quick Status Update Form -->
                                <form action="manage_orders.php?<?php echo e($_SERVER['QUERY_STRING']); ?>" method="POST" class="flex items-center">
                                    <!-- (NEW) CSRF Token -->
                                    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                                    <input type="hidden" name="order_id" value="<?php echo e($order['id']); ?>">
                                    <select name="new_status" class="text-xs p-1 border border-gray-300 rounded-md" onchange="this.form.submit()">
                                        <option value="Pending" <?php echo ($order['order_status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="Preparing" <?php echo ($order['order_status'] == 'Preparing') ? 'selected' : ''; ?>>Preparing</option>
                                        <option value="Ready" <?php echo ($order['order_status'] == 'Ready') ? 'selected' : ''; ?>>Ready</option>
                                        <option value="Delivered" <?php echo ($order['order_status'] == 'Delivered') ? 'selected' : ''; ?>>Delivered</option>
                                        <option value="Cancelled" <?php echo ($order['order_status'] == 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                <a href="order_details.php?id=<?php echo e($order['id']); ?>" class="text-orange-600 hover:text-orange-900">Details</a>
                                <a href="print_receipt.php?id=<?php echo e($order['id']); ?>" target="_blank" class="text-blue-600 hover:text-blue-900">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 5. FOOTER
require_once('footer.php');
?>