<?php
/*
 * admin/manage_coupons.php
 * KitchCo: Cloud Kitchen Coupon Manager
 *
 * This page handles full CRUD for coupons.
 * Based on manage_categories.php
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. PAGE VARIABLES & INITIALIZATION
$action = $_GET['action'] ?? 'list';
$coupon_id = $_GET['id'] ?? null;
$page_title = 'Manage Coupons';

// Form data placeholders
$code = '';
$description = '';
$type = 'fixed';
$value = 0;
$min_order_amount = 0;
$start_date = date('Y-m-d\TH:i');
$end_date = date('Y-m-d\TH:i', strtotime('+30 days'));
$max_uses = 100;
$is_active = 1;

$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // Get form data
        $code = trim($_POST['code']);
        $description = trim($_POST['description']);
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $min_order_amount = (float)$_POST['min_order_amount'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $max_uses = (int)$_POST['max_uses'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Validation
        if (empty($code) || $value <= 0 || empty($start_date) || empty($end_date) || $max_uses <= 0) {
            $error_message = 'Coupon Code, Value, Start/End Dates, and Max Uses are required.';
        } elseif (strtotime($end_date) <= strtotime($start_date)) {
            $error_message = 'End date must be after the start date.';
        }
        
        if (empty($error_message)) {
            if (isset($_POST['coupon_id']) && !empty($_POST['coupon_id'])) {
                // --- UPDATE existing coupon ---
                $coupon_id = $_POST['coupon_id'];
                $sql = "UPDATE coupons SET 
                            code = ?, description = ?, type = ?, value = ?, 
                            min_order_amount = ?, start_date = ?, end_date = ?, 
                            max_uses = ?, is_active = ? 
                        WHERE id = ?";
                $stmt = $db->prepare($sql);
                // sssddssiii
                $stmt->bind_param('sssddssiii', 
                    $code, $description, $type, $value, 
                    $min_order_amount, $start_date, $end_date, 
                    $max_uses, $is_active, $coupon_id
                );
                
                if ($stmt->execute()) {
                    $success_message = 'Coupon updated successfully!';
                } else {
                    $error_message = 'Failed to update coupon. Code may already exist.';
                }
                $stmt->close();
                
            } else {
                // --- CREATE new coupon ---
                $sql = "INSERT INTO coupons (code, description, type, value, min_order_amount, start_date, end_date, max_uses, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                // sssddssii
                $stmt->bind_param('sssddssii', 
                    $code, $description, $type, $value, 
                    $min_order_amount, $start_date, $end_date, 
                    $max_uses, $is_active
                );
                
                if ($stmt->execute()) {
                    $success_message = 'Coupon created successfully!';
                    // Clear form
                    $code = ''; $description = ''; $type = 'fixed'; $value = 0; $min_order_amount = 0;
                    $start_date = date('Y-m-d\TH:i'); $end_date = date('Y-m-d\TH:i', strtotime('+30 days'));
                    $max_uses = 100; $is_active = 1;
                } else {
                    $error_message = 'Failed to create coupon. Code may already exist.';
                }
                $stmt->close();
            }
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Edit & Delete) ---

// Handle "Edit" - Load data into the form
if ($action === 'edit' && $coupon_id) {
    $page_title = 'Edit Coupon';
    $sql = "SELECT * FROM coupons WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $coupon_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $coupon = $result->fetch_assoc();
        $code = $coupon['code'];
        $description = $coupon['description'];
        $type = $coupon['type'];
        $value = $coupon['value'];
        $min_order_amount = $coupon['min_order_amount'];
        // Format dates for datetime-local input
        $start_date = date('Y-m-d\TH:i', strtotime($coupon['start_date']));
        $end_date = date('Y-m-d\TH:i', strtotime($coupon['end_date']));
        $max_uses = $coupon['max_uses'];
        $is_active = $coupon['is_active'];
    } else {
        $error_message = 'Coupon not found.';
        $action = 'list';
    }
    $stmt->close();
}

// Handle "Delete"
if ($action === 'delete' && $coupon_id) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // We set coupon_id to NULL in `orders` table due to ON DELETE SET NULL
        $sql = "DELETE FROM coupons WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $coupon_id);
        
        if ($stmt->execute()) {
            $success_message = 'Coupon deleted successfully!';
        } else {
            $error_message = 'Failed to delete coupon.';
        }
        $stmt->close();
    }
    $action = 'list';
}

// 6. --- LOAD DATA FOR DISPLAY ---
$coupons = [];
$result = $db->query("SELECT * FROM coupons ORDER BY end_date DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $coupons[] = $row;
    }
}

?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_title); ?></h1>

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

    <!-- Column 1: Add/Edit Form -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($action === 'edit') ? 'Edit Coupon' : 'Add New Coupon'; ?>
            </h2>
            
            <form action="manage_coupons.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                <?php if ($action === 'edit' && $coupon_id): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo e($coupon_id); ?>">
                <?php endif; ?>

                <!-- Coupon Code -->
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Coupon Code (e.g., EID50)</label>
                    <input type="text" id="code" name="code" value="<?php echo e($code); ?>" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <!-- Type & Value -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="type" name="type" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="fixed" <?php echo ($type == 'fixed') ? 'selected' : ''; ?>>Fixed (BDT)</option>
                            <option value="percentage" <?php echo ($type == 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="value" class="block text-sm font-medium text-gray-700">Value</label>
                        <input type="number" step="0.01" id="value" name="value" value="<?php echo e($value); ?>" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>

                <!-- Min Order & Max Uses -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="min_order_amount" class="block text-sm font-medium text-gray-700">Min. Order (BDT)</label>
                        <input type="number" step="0.01" id="min_order_amount" name="min_order_amount" value="<?php echo e($min_order_amount); ?>" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="max_uses" class="block text-sm font-medium text-gray-700">Max Uses</label>
                        <input type="number" id="max_uses" name="max_uses" value="<?php echo e($max_uses); ?>" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>

                <!-- Start Date & End Date -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="datetime-local" id="start_date" name="start_date" value="<?php echo e($start_date); ?>" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="datetime-local" id="end_date" name="end_date" value="<?php echo e($end_date); ?>" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                    <textarea id="description" name="description" rows="2" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($description); ?></textarea>
                </div>
                
                <!-- Active Toggle -->
                <div class="flex items-center">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($is_active) ? 'checked' : ''; ?> class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                </div>

                <!-- Submit Button -->
                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Add Coupon'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_coupons.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Coupon List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Coupons (<?php echo count($coupons); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usage</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($coupons)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                    No coupons found. Add one using the form!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($coupons as $coupon): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($coupon['code']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            <?php echo ($coupon['type'] == 'percentage') ? e($coupon['value']) . '%' : e(number_format($coupon['value'], 2)) . ' BDT'; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo e($coupon['current_uses']); ?> / <?php echo e($coupon['max_uses']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo e(date('d M Y, h:i A', strtotime($coupon['end_date']))); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $is_expired = strtotime($coupon['end_date']) < time();
                                        $is_used_up = $coupon['current_uses'] >= $coupon['max_uses'];
                                        if ($coupon['is_active'] && !$is_expired && !$is_used_up): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        <?php elseif (!$coupon['is_active']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Inactive
                                            </span>
                                        <?php elseif ($is_expired): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Expired
                                            </span>
                                        <?php elseif ($is_used_up): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Used Up
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <a href="manage_coupons.php?action=edit&id=<?php echo e($coupon['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <a href="manage_coupons.php?action=delete&id=<?php echo e($coupon['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this coupon? This cannot be undone.');">Delete</a>
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

<?php
// 7. FOOTER
require_once('footer.php');
?>