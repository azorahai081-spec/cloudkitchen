<?php
/*
 * admin/ledger.php
 * KitchCo: Cloud Kitchen Admin's Personal Ledger
 * Version 2.1 - (MODIFIED) Integers Only for BDT
 *
 * This is an ADMIN-ONLY page for logging personal savings and costs.
 * It is not connected to orders or sales data.
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. PAGE VARIABLES & INITIALIZATION
$page_title = 'Personal Ledger';
$error_message = '';
$success_message = '';
$timezone = new DateTimeZone($settings['timezone'] ?? 'UTC');
$default_to = new DateTime('now', $timezone);

$action = $_GET['action'] ?? 'list';
$entry_id = $_GET['id'] ?? null;
$form_mode = 'add'; // 'add' or 'edit'

// Form data placeholders
$entry_date_form = $default_to->format('Y-m-d');
$type_form = 'deposit';
$amount_form = '';
$description_form = '';


// 4. --- HANDLE DELETE ACTION (GET) ---
if ($action === 'delete' && $entry_id) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $entry_id_to_delete = (int)$entry_id;
        
        $sql_delete = "DELETE FROM admin_ledger WHERE id = ?";
        $stmt_delete = $db->prepare($sql_delete);
        $stmt_delete->bind_param('i', $entry_id_to_delete);
        
        if ($stmt_delete->execute()) {
            $success_message = "Ledger entry deleted successfully.";
        } else {
            $error_message = "Failed to delete entry: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }
}

// 5. --- HANDLE EDIT ACTION (GET) ---
if ($action === 'edit' && $entry_id) {
    $form_mode = 'edit';
    $page_title = 'Edit Ledger Entry';

    $stmt_edit = $db->prepare("SELECT * FROM admin_ledger WHERE id = ?");
    $stmt_edit->bind_param('i', $entry_id);
    $stmt_edit->execute();
    $result_edit = $stmt_edit->get_result();

    if ($result_edit->num_rows === 1) {
        $entry = $result_edit->fetch_assoc();
        $entry_date_form = $entry['entry_date'];
        $type_form = $entry['type']; 
        // (MODIFIED) Cast to int
        $amount_form = (int)$entry['amount'];
        $description_form = $entry['description'];
    } else {
        $error_message = "Ledger entry not found.";
        $form_mode = 'add'; 
    }
    $stmt_edit->close();
}

// 6. --- HANDLE ADD/UPDATE ENTRY (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_entry'])) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $entry_date = $_POST['entry_date'];
        $type = $_POST['type']; 
        // (MODIFIED) Cast to int
        $amount = (int)$_POST['amount'];
        $description = trim($_POST['description']);
        $entry_id_to_update = (int)($_POST['entry_id'] ?? 0);

        if (empty($entry_date) || $amount <= 0 || !in_array($type, ['deposit', 'withdrawal'])) {
            $error_message = 'Please enter a valid date, type, and an amount greater than zero.';
        } else {
            if ($entry_id_to_update > 0) {
                // --- UPDATE ---
                $sql = "UPDATE admin_ledger SET entry_date = ?, type = ?, amount = ?, description = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                // (MODIFIED) Bind as 'i' or 'd' (using d for general safety but logic is int)
                $stmt->bind_param('ssdsi', $entry_date, $type, $amount, $description, $entry_id_to_update);
                
                if ($stmt->execute()) {
                    $success_message = 'Ledger entry updated successfully!';
                    $form_mode = 'add';
                    $entry_date_form = $default_to->format('Y-m-d');
                    $type_form = 'deposit';
                    $amount_form = '';
                    $description_form = '';
                    $page_title = 'Personal Ledger';
                } else {
                    $error_message = 'Failed to update entry: ' . $stmt->error;
                    $form_mode = 'edit';
                    $entry_date_form = $entry_date;
                    $type_form = $type;
                    $amount_form = $amount;
                    $description_form = $description;
                }
                $stmt->close();

            } else {
                // --- INSERT ---
                $sql = "INSERT INTO admin_ledger (entry_date, type, amount, description) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssds', $entry_date, $type, $amount, $description);
                
                if ($stmt->execute()) {
                    $success_message = 'Entry of ' . number_format($amount, 0) . ' BDT recorded!';
                } else {
                    $error_message = 'Failed to save entry: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// 7. --- GET DATE RANGE ---
$default_from = (new DateTime('now', $timezone))->modify('-29 days');
$date_from_str = $_GET['date_from'] ?? $default_from->format('Y-m-d');
$date_to_str = $_GET['date_to'] ?? $default_to->format('Y-m-d');
$date_from = new DateTime($date_from_str . ' 00:00:00', $timezone);
$date_to = new DateTime($date_to_str . ' 23:59:59', $timezone);
$sql_start_date = $date_from->format('Y-m-d');
$sql_end_date = $date_to->format('Y-m-d');
$display_date_range = $date_from->format('M d, Y') . ' - ' . $date_to->format('M d, Y');

// 8. --- LOAD DATA FOR DISPLAY ---

// A. Get Totals (All Time)
$total_deposits_all = 0;
$total_withdrawals_all = 0;
$result_total = $db->query("SELECT type, SUM(amount) as total FROM admin_ledger GROUP BY type");
if ($result_total) {
    while($row = $result_total->fetch_assoc()) {
        if ($row['type'] == 'deposit') {
            $total_deposits_all = (int)$row['total'];
        } else {
            $total_withdrawals_all = (int)$row['total'];
        }
    }
}
$current_balance = $total_deposits_all - $total_withdrawals_all;

// B. Get Totals (In Date Range)
$total_deposits_range = 0;
$total_withdrawals_range = 0;
$stmt_range = $db->prepare("SELECT type, SUM(amount) as total FROM admin_ledger WHERE entry_date BETWEEN ? AND ? GROUP BY type");
$stmt_range->bind_param('ss', $sql_start_date, $sql_end_date);
$stmt_range->execute();
$result_range = $stmt_range->get_result();
if ($result_range) {
    while($row = $result_range->fetch_assoc()) {
        if ($row['type'] == 'deposit') {
            $total_deposits_range = (int)$row['total'];
        } else {
            $total_withdrawals_range = (int)$row['total'];
        }
    }
}
$stmt_range->close();

// C. Get Daily Basis (In Date Range)
$daily_entries = [];
$stmt_daily = $db->prepare("SELECT * FROM admin_ledger WHERE entry_date BETWEEN ? AND ? ORDER BY entry_date DESC, id DESC");
$stmt_daily->bind_param('ss', $sql_start_date, $sql_end_date);
$stmt_daily->execute();
$result_daily = $stmt_daily->get_result();
if ($result_daily) {
    while ($row = $result_daily->fetch_assoc()) {
        $daily_entries[] = $row;
    }
}
$stmt_daily->close();

// D. Get Monthly Basis (All Time)
$monthly_summary = [];
$sql_monthly = "SELECT 
                    DATE_FORMAT(entry_date, '%Y-%m') as month_key, 
                    DATE_FORMAT(entry_date, '%M %Y') as month_name, 
                    SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                    SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) as total_withdrawals
                FROM admin_ledger 
                GROUP BY month_key, month_name 
                ORDER BY month_key DESC";
$result_monthly = $db->query($sql_monthly);
if ($result_monthly) {
    while ($row = $result_monthly->fetch_assoc()) {
        $monthly_summary[] = $row;
    }
}

// E. Get Data for Chart (Daily in Range)
$chart_labels = [];
$chart_deposits_data = [];
$chart_withdrawals_data = [];
$data_by_day = [];

$date_diff = $date_from->diff($date_to);
$days_in_range = $date_diff->days;

if ($days_in_range <= 45) {
    // --- DAILY CHART LOGIC ---
    $period = new DatePeriod($date_from, new DateInterval('P1D'), $date_to->modify('+1 day'));
    foreach ($period as $day) {
        $date_key = $day->format('Y-m-d');
        $chart_labels[] = $day->format('M d');
        $data_by_day[$date_key] = ['deposits' => 0, 'withdrawals' => 0];
    }
    $chart_sql = "SELECT 
                      DATE(entry_date) as e_date, 
                      type,
                      SUM(amount) as daily_total 
                  FROM admin_ledger 
                  WHERE entry_date BETWEEN ? AND ? 
                  GROUP BY e_date, type 
                  ORDER BY e_date ASC";
} else {
    // --- MONTHLY CHART LOGIC ---
    $period = new DatePeriod($date_from, new DateInterval('P1M'), $date_to->modify('+1 month'));
    foreach ($period as $month) {
        $date_key = $month->format('Y-m');
        $chart_labels[] = $month->format('M Y');
        $data_by_day[$date_key] = ['deposits' => 0, 'withdrawals' => 0];
    }
    $chart_sql = "SELECT 
                      DATE_FORMAT(entry_date, '%Y-%m') as e_date, 
                      type,
                      SUM(amount) as daily_total 
                  FROM admin_ledger 
                  WHERE entry_date BETWEEN ? AND ? 
                  GROUP BY e_date, type 
                  ORDER BY e_date ASC";
}

$chart_stmt = $db->prepare($chart_sql);
$chart_stmt->bind_param('ss', $sql_start_date, $sql_end_date);
$chart_stmt->execute();
$chart_result = $chart_stmt->get_result();

if ($chart_result) {
    while ($row = $chart_result->fetch_assoc()) {
        if (isset($data_by_day[$row['e_date']])) {
            if ($row['type'] == 'deposit') {
                // (MODIFIED) Cast to int
                $data_by_day[$row['e_date']]['deposits'] = (int)$row['daily_total'];
            } else {
                // (MODIFIED) Cast to int
                $data_by_day[$row['e_date']]['withdrawals'] = (int)$row['daily_total'];
            }
        }
    }
}
$chart_stmt->close();

foreach ($data_by_day as $day_data) {
    $chart_deposits_data[] = $day_data['deposits'];
    $chart_withdrawals_data[] = $day_data['withdrawals'];
}

$chart_labels_json = json_encode($chart_labels);
$chart_deposits_json = json_encode($chart_deposits_data);
$chart_withdrawals_json = json_encode($chart_withdrawals_data);

?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-4"><?php echo e($page_title); ?></h1>
<p class="text-gray-600 mb-8">A simple, private ledger to record personal savings and costs. This is not connected to sales data.</p>

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

<!-- Date Range Filter Form -->
<div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
    <form action="ledger.php" method="GET" class="flex flex-col sm:flex-row items-center gap-4">
        <div class="flex-1">
            <label for="date_from" class="block text-sm font-medium text-gray-700">From</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo e($date_from_str); ?>"
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <div class="flex-1">
            <label for="date_to" class="block text-sm font-medium text-gray-700">To</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo e($date_to_str); ?>"
                   class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <div class="flex space-x-2 mt-auto">
            <button type="submit" class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                Filter
            </button>
            <a href="ledger.php" class="px-6 py-3 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Summary Cards (The "Comparison") -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Current Balance (All Time)</div>
        <div class="text-3xl font-bold <?php echo ($current_balance >= 0) ? 'text-green-600' : 'text-red-600'; ?>">
            <!-- (MODIFIED) Removed decimals -->
            <?php echo e(number_format($current_balance, 0)); ?> BDT
        </div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Deposits in Range</div>
        <!-- (MODIFIED) Removed decimals -->
        <div class="text-3xl font-bold text-gray-900"><?php echo e(number_format($total_deposits_range, 0)); ?> BDT</div>
    </div>
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <div class="text-sm font-medium text-gray-500">Costs in Range</div>
        <!-- (MODIFIED) Removed decimals -->
        <div class="text-3xl font-bold text-gray-900">-<?php echo e(number_format($total_withdrawals_range, 0)); ?> BDT</div>
    </div>
</div>

<!-- Savings Chart -->
<div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
    <h2 class="text-xl font-bold text-gray-900 mb-4">
        Ledger Chart (<?php echo $display_date_range; ?>)
    </h2>
    <div class="relative h-96">
        <canvas id="ledgerChart"></canvas>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Add/Edit Saving -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($form_mode === 'edit') ? 'Edit Ledger Entry' : 'Add New Entry'; ?>
            </h2>
            
            <form action="ledger.php<?php echo ($form_mode === 'edit') ? '?action=edit&id=' . e($entry_id) : ''; ?>" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <?php if ($form_mode === 'edit'): ?>
                    <input type="hidden" name="entry_id" value="<?php echo e($entry_id); ?>">
                <?php endif; ?>
                
                <div>
                    <label for="entry_date" class="block text-sm font-medium text-gray-700">Date</label>
                    <input type="date" id="entry_date" name="entry_date" value="<?php echo e($entry_date_form); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700">Entry Type</label>
                    <div class="mt-1 flex gap-4">
                        <label class="flex items-center">
                            <input type="radio" name="type" value="deposit" <?php echo ($type_form == 'deposit') ? 'checked' : ''; ?> class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Saving (Deposit)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="type" value="withdrawal" <?php echo ($type_form == 'withdrawal') ? 'checked' : ''; ?> class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Cost (Withdrawal)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700">Amount (BDT)</label>
                    <!-- (MODIFIED) step="1" -->
                    <input type="number" step="1" id="amount" name="amount" value="<?php echo e($amount_form); ?>" placeholder="e.g., 500" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                    <input type="text" id="description" name="description" value="<?php echo e($description_form); ?>" placeholder="e.g., From cash drawer"
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="flex space-x-2">
                    <button type="submit" name="submit_entry" class="w-full py-3 px-4 <?php echo ($form_mode === 'edit') ? 'bg-orange-600 hover:bg-orange-700' : 'bg-green-600 hover:bg-green-700'; ?> text-white font-medium rounded-lg shadow-md">
                        <?php echo ($form_mode === 'edit') ? 'Save Changes' : 'Add Entry'; ?>
                    </button>
                    <?php if ($form_mode === 'edit'): ?>
                        <a href="ledger.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Monthly & Daily View -->
    <div class="lg:col-span-2 space-y-8">
        <!-- View 1: Monthly Basis -->
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 p-6 border-b border-gray-200">
                Monthly Summary (All Time)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deposited</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Withdrawn</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Income</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($monthly_summary)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No entries recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthly_summary as $month): 
                                $net_change = $month['total_deposits'] - $month['total_withdrawals'];
                            ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($month['month_name']); ?></div></td>
                                    <!-- (MODIFIED) Removed decimals -->
                                    <td class="px-6 py-4 text-right"><div class="text-sm text-green-600"><?php echo number_format($month['total_deposits'], 0); ?></div></td>
                                    <!-- (MODIFIED) Removed decimals -->
                                    <td class="px-6 py-4 text-right"><div class="text-sm text-red-600">-<?php echo number_format($month['total_withdrawals'], 0); ?></div></td>
                                    <!-- (MODIFIED) Removed decimals -->
                                    <td class="px-6 py-4 text-right"><div class="text-sm font-medium <?php echo ($net_change >= 0) ? 'text-green-600' : 'text-red-600'; ?>"><?php echo number_format($net_change, 0); ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- View 2: Daily Basis -->
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 p-6 border-b border-gray-200">
                Daily Entries (<?php echo $display_date_range; ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($daily_entries)): ?>
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No entries found in this date range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($daily_entries as $entry): ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-900"><?php echo e(date('M d, Y', strtotime($entry['entry_date']))); ?></div></td>
                                    <td class="px-6 py-4">
                                        <?php if ($entry['type'] == 'deposit'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Deposit</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Cost</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-500"><?php echo e($entry['description']); ?></div></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="text-sm font-medium <?php echo ($entry['type'] == 'deposit') ? 'text-green-600' : 'text-red-600'; ?>">
                                            <!-- (MODIFIED) Removed decimals -->
                                            <?php echo ($entry['type'] == 'deposit') ? '+' : '-'; ?><?php echo number_format($entry['amount'], 0); ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="ledger.php?action=edit&id=<?php echo e($entry['id']); ?>" 
                                           class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <a href="ledger.php?action=delete&id=<?php echo e($entry['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this entry?');">Delete</a>
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

<!-- Chart.js CDN Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Chart Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('ledgerChart');
    if (ctx) {
        // Get the data we passed from PHP
        const chartLabels = <?php echo $chart_labels_json; ?>;
        const depositsData = <?php echo $chart_deposits_json; ?>;
        const withdrawalsData = <?php echo $chart_withdrawals_json; ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [
                    {
                        label: 'Deposits (BDT)',
                        data: depositsData,
                        borderColor: 'rgb(22, 163, 74)', // green-600
                        backgroundColor: 'rgba(22, 163, 74, 0.1)',
                        fill: true,
                        tension: 0.1,
                        borderWidth: 2
                    },
                    {
                        label: 'Costs (BDT)',
                        data: withdrawalsData,
                        borderColor: 'rgb(220, 38, 38)', // red-600
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        fill: true,
                        tension: 0.1,
                        borderWidth: 2
                    }
                ]
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
                                return ` ${context.dataset.label}: ${context.formattedValue} BDT`;
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
// 8. FOOTER
require_once('footer.php');
?>