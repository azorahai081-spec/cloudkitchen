<?php
/*
 * admin/reports.php
 * KitchCo: Cloud Kitchen Reports & Analytics
 * Version 3.0 - Added Dynamic Date Range Filter
 *
 * This is an ADMIN-ONLY page.
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. PAGE VARIABLES & INITIALIZATION
$page_title = 'Reports & Analytics';
$timezone = new DateTimeZone($settings['timezone'] ?? 'UTC');

// --- 4. (MODIFIED) GET DATE RANGE ---
// Set default dates (last 30 days)
$default_to = new DateTime('now', $timezone);
$default_from = (new DateTime('now', $timezone))->modify('-29 days');

// Check for user-submitted dates
$date_from_str = $_GET['date_from'] ?? $default_from->format('Y-m-d');
$date_to_str = $_GET['date_to'] ?? $default_to->format('Y-m-d');

// Create DateTime objects for precise start/end times
$date_from = new DateTime($date_from_str . ' 00:00:00', $timezone);
$date_to = new DateTime($date_to_str . ' 23:59:59', $timezone);

// Format for SQL queries
$sql_start_date = $date_from->format('Y-m-d H:i:s');
$sql_end_date = $date_to->format('Y-m-d H:i:s');

// Format for display
$display_date_range = $date_from->format('M d, Y') . ' - ' . $date_to->format('M d, Y');


// --- 5. DATA FETCHING (ADVANCED) ---

// --- A. Sales Comparison Stats ---
// We now use the selected date range for the main stat
$sales_in_range = 0;
$orders_in_range = 0;
$avg_order_value = 0;

$sql = "SELECT SUM(total_amount) as total, COUNT(id) as count
        FROM orders
        WHERE order_status != 'Cancelled'
        AND order_time BETWEEN ? AND ?";
$stmt = $db->prepare($sql);
$stmt->bind_param('ss', $sql_start_date, $sql_end_date);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();

$sales_in_range = $data['total'] ?? 0;
$orders_in_range = $data['count'] ?? 0;
if ($orders_in_range > 0) {
    $avg_order_value = $sales_in_range / $orders_in_range;
}

// Get "Today's" stats separately for the quick-view card
$today_start = (new DateTime('today 00:00:00', $timezone))->format('Y-m-d H:i:s');
$today_end = (new DateTime('today 23:59:59', $timezone))->format('Y-m-d H:i:s');

function get_sales_total($db, $start, $end) {
    $sql = "SELECT SUM(total_amount) as total
            FROM orders
            WHERE order_status != 'Cancelled'
            AND order_time BETWEEN ? AND ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();
    return $data['total'] ?? 0;
}
$sales_today = get_sales_total($db, $today_start, $today_end);


// --- B. Sales Chart (Dynamic Range) ---
$sales_by_day = [];
$chart_labels = [];
$period = new DatePeriod($date_from, new DateInterval('P1D'), $date_to->modify('+1 day')); // Include end day

// 1. Create a PHP array for every day in the range, all with 0 sales
foreach ($period as $day) {
    $date_key = $day->format('Y-m-d');
    $chart_labels[] = $day->format('M d'); // e.g., "Nov 15"
    $sales_by_day[$date_key] = 0;
}

// 2. Get sales data from DB for the range
$chart_sql = "SELECT
                  DATE(order_time) as sale_date,
                  SUM(total_amount) as daily_sales
              FROM orders
              WHERE
                  order_status != 'Cancelled' AND
                  order_time BETWEEN ? AND ?
              GROUP BY
                  sale_date
              ORDER BY
                  sale_date ASC";
$chart_stmt = $db->prepare($chart_sql);
$chart_stmt->bind_param('ss', $sql_start_date, $sql_end_date);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

// 3. Update the PHP array with real sales data
if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        if (isset($sales_by_day[$row['sale_date']])) {
            $sales_by_day[$row['sale_date']] = (float)$row['daily_sales'];
        }
    }
}
$chart_stmt->close();

// 4. Convert data for Chart.js
$chart_data = array_values($sales_by_day);

// 5. JSON-encode for JavaScript
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);


// --- C. Top 10 Selling Items (Dynamic Range) ---
$top_selling_items = [];
$top_items_sql = "SELECT m.name, SUM(oi.quantity) as total_sold
                  FROM order_items oi
                  JOIN menu_items m ON oi.menu_item_id = m.id
                  JOIN orders o ON oi.order_id = o.id
                  WHERE o.order_status != 'Cancelled'
                  AND o.order_time BETWEEN ? AND ?
                  GROUP BY oi.menu_item_id, m.name
                  ORDER BY total_sold DESC
                  LIMIT 10";
$top_items_stmt = $db->prepare($top_items_sql);
$top_items_stmt->bind_param('ss', $sql_start_date, $sql_end_date);
$top_items_stmt->execute();
$top_items_result = $top_items_stmt->get_result();
if ($top_items_result) {
    while ($row = $top_items_result->fetch_assoc()) {
        $top_selling_items[] = $row;
    }
}
$top_items_stmt->close();


// --- D. Sales by Delivery Area (Dynamic Range) ---
$sales_by_area = [];
$area_sales_sql = "SELECT da.area_name, SUM(o.total_amount) as total_sales, COUNT(o.id) as total_orders
                   FROM orders o
                   JOIN delivery_areas da ON o.delivery_area_id = da.id
                   WHERE o.order_status != 'Cancelled'
                   AND o.order_time BETWEEN ? AND ?
                   GROUP BY o.delivery_area_id, da.area_name
                   ORDER BY total_sales DESC";
$area_sales_stmt = $db->prepare($area_sales_sql);
$area_sales_stmt->bind_param('ss', $sql_start_date, $sql_end_date);
$area_sales_stmt->execute();
$area_sales_result = $area_sales_stmt->get_result();
if ($area_sales_result) {
    while ($row = $area_sales_result->fetch_assoc()) {
        $sales_by_area[] = $row;
    }
}
$area_sales_stmt->close();

?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo e($page_title); ?></h1>

<!-- (NEW) Date Range Filter Form -->
<div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
    <form action="reports.php" method="GET" class="flex flex-col sm:flex-row items-center gap-4">
        <!-- CSRF is not needed for GET forms -->
        <div class="flex-1">
            <label for="date_from" class="block text-sm font-medium text-gray-700">From</label>
            <input 
                type="date" 
                id="date_from" 
                name="date_from"
                value="<?php echo e($date_from_str); ?>"
                class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
        </div>
        <div class="flex-1">
            <label for="date_to" class="block text-sm font-medium text-gray-700">To</label>
            <input 
                type="date" 
                id="date_to" 
                name="date_to"
                value="<?php echo e($date_to_str); ?>"
                class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
            >
        </div>
        <div class="flex space-x-2 mt-auto">
            <button 
                type="submit" 
                class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700"
            >
                Filter
            </button>
            <a 
                href="reports.php"
                class="px-6 py-3 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300"
            >
                Reset
            </a>
        </div>
    </form>
</div>

<!-- (MODIFIED) Sales Comparison Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Total Sales (in range)</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($sales_in_range, 2)); ?> BDT</div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Total Orders (in range)</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo e($orders_in_range); ?></div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Avg. Order Value (in range)</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($avg_order_value, 2)); ?> BDT</div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Today's Sales</div>
        <div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($sales_today, 2)); ?> BDT</div>
    </div>
</div>

<!-- (MODIFIED) Sales Chart -->
<div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-4">
        Sales Chart (<?php echo $display_date_range; ?>)
    </h2>
    <!-- The chart will be rendered here -->
    <div class="relative h-96">
        <canvas id="salesChart"></canvas>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

    <!-- Column 1: Top 10 Selling Items -->
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">
            Top 10 Selling Items (<?php echo $display_date_range; ?>)
        </h2>
        <ol class="list-decimal list-inside space-y-3">
            <?php if (empty($top_selling_items)): ?>
                <li class="text-gray-500">No sales data in this period.</li>
            <?php else: ?>
                <?php foreach ($top_selling_items as $index => $item): ?>
                    <li class="text-gray-700">
                        <span class="font-medium text-gray-900"><?php echo e($item['name']); ?></span>
                        <span class="text-sm">(<?php echo e($item['total_sold']); ?> units sold)</span>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ol>
    </div>

    <!-- Column 2: Sales by Delivery Area -->
    <div class="bg-white rounded-2xl shadow-lg">
        <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
            Sales by Delivery Area (<?php echo $display_date_range; ?>)
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area Name</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Orders</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Sales</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($sales_by_area)): ?>
                        <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No sales data in this period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales_by_area as $area): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo e($area['area_name']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm text-gray-700"><?php echo e($area['total_orders']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="text-sm font-medium text-gray-900"><?php echo e(number_format($area['total_sales'], 2)); ?> BDT</div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- (NEW) Chart.js CDN Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- (NEW) Chart Initialization Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            // Get the data we passed from PHP
            const chartLabels = <?php echo $chart_labels_json; ?>;
            const chartData = <?php echo $chart_data_json; ?>;
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Daily Sales (BDT)',
                        data: chartData,
                        borderColor: 'rgb(234, 88, 12)', // Tailwind 'orange-600'
                        backgroundColor: 'rgba(234, 88, 12, 0.1)',
                        fill: true,
                        tension: 0.1,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Sales: ${context.formattedValue} BDT`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<?php
// 5. FOOTER
require_once('footer.php');
?>