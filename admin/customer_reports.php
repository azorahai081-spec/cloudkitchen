<?php
/*
 * admin/customer_reports.php
 * KitchCo: Cloud Kitchen Customer & Order Insights
 * Version 2.5 - Fixed Function Redeclaration Error
 *
 * This page provides a 360-degree view of customer behavior:
 * 1. Loyal Customers (Delivered Orders)
 * 2. At-Risk/Problematic Customers (Cancellations)
 */

// 1. CONFIGURATION (Start Session & DB - Must be at the top)
// FIXED: Added '../' because this file is inside the 'admin' folder
require_once('../config.php');

// 2. SECURITY CHECK (Must be done before header.php is loaded for export)
if (!isset($_SESSION['user_id'])) {
    header('Location: live_orders.php');
    exit;
}

// --- 3. HANDLE EXPORT ACTION (MUST BE FIRST AFTER CONFIG/SECURITY) ---
if (isset($_GET['export']) && $_GET['export'] === 'loyal') {
    
    // Check permission manually here since header.php isn't loaded yet
    $user_role = $_SESSION['user_role'] ?? 'manager';
    if ($user_role !== 'admin') {
        die('Access Denied');
    }

    // If output buffering is active, clean it. 
    // This removes any stray whitespace/output before headers are sent.
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set CSV Headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=loyal_customers_' . date('Y-m-d') . '.csv');
    
    // Open output stream
    $output = fopen('php://output', 'w');

    // Add Column Headers
    fputcsv($output, ['Customer Name', 'Phone', 'Total Delivered Orders', 'Total Spent (BDT)', 'Last Order Date', 'Days Since Last Order']);

    // Get Filter Params
    $min_orders_exp = (int)($_GET['min_orders'] ?? 1);
    $search_exp = $_GET['search'] ?? '';

    // Build Query (Same logic as display, but NO LIMIT)
    $sql_export = "SELECT 
                    MAX(customer_name) as customer_name, 
                    customer_phone,
                    COUNT(id) as total_orders,
                    SUM(total_amount) as total_spent,
                    MAX(order_time) as last_order_date
                FROM orders
                WHERE order_status = 'Delivered' 
                GROUP BY customer_phone";

    $having_clauses = [];
    if ($min_orders_exp > 1) {
        $having_clauses[] = "total_orders >= $min_orders_exp";
    }
    if (!empty($search_exp)) {
        $sq = $db->real_escape_string($search_exp);
        $having_clauses[] = "(customer_phone LIKE '%$sq%' OR MAX(customer_name) LIKE '%$sq%')";
    }
    if (!empty($having_clauses)) {
        $sql_export .= " HAVING " . implode(' AND ', $having_clauses);
    }
    
    $sql_export .= " ORDER BY total_orders DESC, total_spent DESC"; // No Limit for export

    $result_exp = $db->query($sql_export);
    if ($result_exp) {
        while ($row = $result_exp->fetch_assoc()) {
            $days_ago = floor((time() - strtotime($row['last_order_date'])) / (60 * 60 * 24));
            fputcsv($output, [
                $row['customer_name'],
                $row['customer_phone'],
                $row['total_orders'],
                number_format($row['total_spent'], 2, '.', ''), 
                $row['last_order_date'],
                $days_ago . ' days ago'
            ]);
        }
    }
    
    fclose($output);
    exit; // Stop script execution immediately after download
}

// 4. HEADER (Now it's safe to load header.php)
// This file defines hasAdminAccess(), so we don't need to define it manually above.
require_once('header.php');

// Extra security check using the function from header.php
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}


// 5. PAGE VARIABLES
$page_title = 'Customer & Order Insights';
$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? 'loyal'; // 'loyal' or 'cancelled'

// --- 6. STATS SUMMARY (All Time) ---
// Get counts for Delivered vs Cancelled
$sql_stats = "SELECT 
                SUM(CASE WHEN order_status = 'Delivered' THEN 1 ELSE 0 END) as total_delivered,
                SUM(CASE WHEN order_status = 'Cancelled' THEN 1 ELSE 0 END) as total_cancelled,
                SUM(CASE WHEN order_status = 'Cancelled' THEN total_amount ELSE 0 END) as lost_revenue
              FROM orders";
$result_stats = $db->query($sql_stats);
$stats = $result_stats->fetch_assoc();


// --- 7. TAB 1: LOYAL CUSTOMERS (Delivered) ---
$loyal_customers = [];
if ($tab === 'loyal') {
    $min_orders = (int)($_GET['min_orders'] ?? 1);
    
    $sql_loyal = "SELECT 
                    customer_phone,
                    MAX(customer_name) as customer_name, 
                    COUNT(id) as total_orders,
                    SUM(total_amount) as total_spent,
                    MAX(order_time) as last_order_date
                FROM orders
                WHERE order_status = 'Delivered' 
                GROUP BY customer_phone";

    $having_clauses = [];
    if ($min_orders > 1) {
        $having_clauses[] = "total_orders >= $min_orders";
    }
    if (!empty($search)) {
        $sq = $db->real_escape_string($search);
        $having_clauses[] = "(customer_phone LIKE '%$sq%' OR MAX(customer_name) LIKE '%$sq%')";
    }
    if (!empty($having_clauses)) {
        $sql_loyal .= " HAVING " . implode(' AND ', $having_clauses);
    }

    $sql_loyal .= " ORDER BY total_orders DESC, total_spent DESC LIMIT 100";
    
    $result_loyal = $db->query($sql_loyal);
    if ($result_loyal) {
        while ($row = $result_loyal->fetch_assoc()) {
            $loyal_customers[] = $row;
        }
    }
}

// --- 8. TAB 2: CANCELLATIONS (Cancelled) ---
$risk_customers = [];
$cancelled_orders = [];

if ($tab === 'cancelled') {
    // A. Top Cancellers
    $sql_risk = "SELECT 
                    customer_phone, 
                    MAX(customer_name) as name, 
                    COUNT(id) as cancel_count, 
                    SUM(total_amount) as total_value
                 FROM orders 
                 WHERE order_status = 'Cancelled'
                 GROUP BY customer_phone
                 ORDER BY cancel_count DESC 
                 LIMIT 10";
    $result_risk = $db->query($sql_risk);
    if ($result_risk) {
        while ($row = $result_risk->fetch_assoc()) {
            $risk_customers[] = $row;
        }
    }

    // B. Recent Cancellation List
    $sql_list = "SELECT o.*, da.area_name 
                 FROM orders o
                 LEFT JOIN delivery_areas da ON o.delivery_area_id = da.id
                 WHERE o.order_status = 'Cancelled'";
                 
    if (!empty($search)) {
        $sq = $db->real_escape_string($search);
        $sql_list .= " AND (o.customer_phone LIKE '%$sq%' OR o.customer_name LIKE '%$sq%')";
    }
    
    $sql_list .= " ORDER BY o.order_time DESC LIMIT 50";
    
    $result_list = $db->query($sql_list);
    if ($result_list) {
        while ($row = $result_list->fetch_assoc()) {
            $cancelled_orders[] = $row;
        }
    }
}
?>

<!-- Page Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900"><?php echo e($page_title); ?></h1>
        <p class="text-gray-600 mt-1">Analyze customer loyalty and cancellation trends.</p>
    </div>
</div>

<!-- Summary Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Card 1 -->
    <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-green-500">
        <div class="text-sm font-medium text-gray-500">Total Delivered Orders</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $stats['total_delivered']; ?></div>
    </div>
    <!-- Card 2 -->
    <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-red-500">
        <div class="text-sm font-medium text-gray-500">Total Cancelled Orders</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo $stats['total_cancelled']; ?></div>
    </div>
    <!-- Card 3 -->
    <div class="bg-white p-6 rounded-2xl shadow-lg border-l-4 border-orange-500">
        <div class="text-sm font-medium text-gray-500">Lost Revenue (Cancelled)</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo number_format($stats['lost_revenue'], 2); ?> BDT</div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex space-x-8">
        <a href="customer_reports.php?tab=loyal" 
           class="<?php echo ($tab === 'loyal') ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Loyal Customers (Delivered)
        </a>
        <a href="customer_reports.php?tab=cancelled" 
           class="<?php echo ($tab === 'cancelled') ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            Cancellations & Risks
        </a>
    </nav>
</div>


<!-- ========================== -->
<!-- TAB CONTENT: LOYALTY       -->
<!-- ========================== -->
<?php if ($tab === 'loyal'): ?>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
        <form action="customer_reports.php" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
            <input type="hidden" name="tab" value="loyal">
            <div class="w-full sm:w-1/3">
                <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                <input type="text" name="search" id="search" value="<?php echo e($search); ?>" placeholder="Name or Phone..." 
                       class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="w-full sm:w-1/4">
                <label for="min_orders" class="block text-sm font-medium text-gray-700">Filter by Orders</label>
                <select name="min_orders" id="min_orders" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="1" <?php echo (isset($_GET['min_orders']) && $_GET['min_orders'] == 1) ? 'selected' : ''; ?>>All Customers</option>
                    <option value="2" <?php echo (isset($_GET['min_orders']) && $_GET['min_orders'] == 2) ? 'selected' : ''; ?>>Repeat Customers (2+)</option>
                    <option value="5" <?php echo (isset($_GET['min_orders']) && $_GET['min_orders'] == 5) ? 'selected' : ''; ?>>VIPs (5+)</option>
                    <option value="10" <?php echo (isset($_GET['min_orders']) && $_GET['min_orders'] == 10) ? 'selected' : ''; ?>>Super VIPs (10+)</option>
                </select>
            </div>
            
            <!-- Filter Button -->
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700">
                Filter List
            </button>
            
            <!-- Export Button -->
            <a href="customer_reports.php?export=loyal&search=<?php echo urlencode($search); ?>&min_orders=<?php echo (int)($min_orders ?? 1); ?>" 
               target="_blank" 
               class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg shadow-md hover:bg-green-700 flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export CSV
            </a>
        </form>
    </div>

    <!-- List -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rank</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Delivered Orders</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Lifetime Value</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Last Order</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($loyal_customers)): ?>
                        <tr><td colspan="7" class="px-6 py-4 text-center text-gray-500">No data found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($loyal_customers as $index => $c): 
                            $rank = $index + 1;
                            $wa_phone = preg_replace('/[^0-9]/', '', $c['customer_phone']);
                            if (substr($wa_phone, 0, 2) === '01') { $wa_phone = '88' . $wa_phone; }
                            
                            $badge = '';
                            if ($c['total_orders'] >= 10) $badge = '<span class="ml-2 px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-800 border border-yellow-200">Gold</span>';
                            elseif ($c['total_orders'] >= 5) $badge = '<span class="ml-2 px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-800 border border-gray-200">Silver</span>';
                        ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo $rank; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-bold text-gray-900 flex items-center">
                                        <?php echo e($c['customer_name']); ?>
                                        <?php echo $badge; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo e($c['customer_phone']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-sm font-medium <?php echo ($c['total_orders'] > 1) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                        <?php echo $c['total_orders']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                    <?php echo number_format($c['total_spent'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    <?php 
                                        $days_ago = floor((time() - strtotime($c['last_order_date'])) / (60 * 60 * 24));
                                        $date_color = ($days_ago > 30) ? 'text-red-500' : 'text-gray-500';
                                        echo date('M d', strtotime($c['last_order_date']));
                                        echo " <span class='text-xs $date_color'>({$days_ago} days ago)</span>";
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" class="text-green-600 hover:text-green-900 inline-flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.008-.57-.008-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                                        Offer
                                    </a>
                                    <a href="manage_orders.php?search=<?php echo urlencode($c['customer_phone']); ?>" class="text-blue-600 hover:text-blue-900 ml-2">History</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- ========================== -->
<!-- TAB CONTENT: CANCELLED     -->
<!-- ========================== -->
<?php elseif ($tab === 'cancelled'): ?>

    <!-- Search Bar -->
    <div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
        <form action="customer_reports.php" method="GET" class="flex gap-4">
            <input type="hidden" name="tab" value="cancelled">
            <div class="flex-grow">
                <input type="text" name="search" value="<?php echo e($search); ?>" placeholder="Search cancelled orders by name/phone..." 
                       class="block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-red-500 focus:border-red-500">
            </div>
            <button type="submit" class="px-6 py-2 bg-red-600 text-white font-medium rounded-lg shadow-md hover:bg-red-700">Search</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Column 1: High Risk List -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="p-4 bg-red-50 border-b border-red-100">
                    <h2 class="text-lg font-bold text-red-800">Top Cancellers (High Risk)</h2>
                    <p class="text-xs text-red-600">Customers with most cancellations.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    <?php if (empty($risk_customers)): ?>
                        <p class="p-4 text-sm text-gray-500 text-center">No data available.</p>
                    <?php else: ?>
                        <?php foreach ($risk_customers as $risk): ?>
                            <div class="p-4 flex justify-between items-center hover:bg-red-50 transition-colors">
                                <div>
                                    <div class="font-bold text-gray-800"><?php echo e($risk['name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo e($risk['customer_phone']); ?></div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-red-600"><?php echo $risk['cancel_count']; ?></div>
                                    <div class="text-xs text-gray-400">Cancelled</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Column 2: Recent Log -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-900">Recent Cancellation Log</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($cancelled_orders)): ?>
                                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No cancellations found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cancelled_orders as $order): ?>
                                    <tr>
                                        <td class="px-6 py-4 text-sm font-bold text-gray-900">
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="hover:text-red-600">
                                                #PM-<?php echo $order['id']; ?>
                                            </a>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo e($order['customer_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo e($order['customer_phone']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php echo e($order['area_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right font-medium text-red-600">
                                            <?php echo number_format($order['total_amount'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-right text-gray-500">
                                            <?php echo date('M d, H:i', strtotime($order['order_time'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<?php
// 7. FOOTER
require_once('footer.php');
?>