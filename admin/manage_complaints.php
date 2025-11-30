<?php
/*
 * admin/manage_complaints.php
 * PizzaMania: Cloud Kitchen Complaint List
 * Version 1.3 - (MODIFIED) Allowed Manager Access
 *
 * This page allows Admins AND Managers to list and add complaints.
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK
// (MODIFIED) Removed strict Admin check. 
// Since header.php ensures the user is logged in, both Admins and Managers can access this.

// 3. PAGE TITLE
$page_title = 'Manage Complaints';
$error_message = '';
$success_message = '';

// Form placeholders for manual add
$form_order_id = '';
$form_complaint_type = '';
$form_complaint_text = '';
$form_status = 'Submitted';


// 4. --- HANDLE POST REQUESTS (Manual Add) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_complaint'])) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $form_order_id_raw = trim($_POST['order_id']);
        $form_complaint_type = trim($_POST['complaint_type']);
        $form_complaint_text = trim($_POST['complaint_text']);
        $form_status = trim($_POST['status']);
        
        // Sanitize order ID (e.g., "PM-123" -> 123)
        $order_id_clean = (int)preg_replace('/[^0-9]/', '', $form_order_id_raw);

        if ($order_id_clean <= 0 || empty($form_complaint_type) || empty($form_complaint_text)) {
            $error_message = 'Order ID, Complaint Type, and Complaint Text are required.';
            // Repopulate form
            $form_order_id = $form_order_id_raw;
        } else {
            // 1. Verify the order exists and get customer info
            $sql_order = "SELECT customer_name, customer_phone FROM orders WHERE id = ?";
            $stmt_order = $db->prepare($sql_order);
            $stmt_order->bind_param('i', $order_id_clean);
            $stmt_order->execute();
            $result_order = $stmt_order->get_result();

            if ($result_order->num_rows == 0) {
                $error_message = "No order found with ID #{$order_id_clean}.";
                $form_order_id = $form_order_id_raw; // Repopulate
            } else {
                $order = $result_order->fetch_assoc();
                $customer_name = $order['customer_name'];
                $customer_phone = $order['customer_phone'];

                // 2. Check for duplicates
                $sql_check = "SELECT id FROM complaints WHERE order_id = ?";
                $stmt_check = $db->prepare($sql_check);
                $stmt_check->bind_param('i', $order_id_clean);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($result_check->num_rows > 0) {
                    $error_message = "A complaint already exists for Order #{$order_id_clean}. You can manage it below.";
                } else {
                    // 3. Insert the new complaint
                    $sql_insert = "INSERT INTO complaints (order_id, customer_name, customer_phone, complaint_type, complaint_text, status) 
                                   VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $db->prepare($sql_insert);
                    $stmt_insert->bind_param('isssss', $order_id_clean, $customer_name, $customer_phone, $form_complaint_type, $form_complaint_text, $form_status);
                    
                    if ($stmt_insert->execute()) {
                        $success_message = "Manual complaint for Order #{$order_id_clean} has been logged.";
                        // Clear form
                        $form_order_id = '';
                        $form_complaint_type = '';
                        $form_complaint_text = '';
                        $form_status = 'Submitted';
                    } else {
                        $error_message = "Failed to log complaint: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt_check->close();
            }
            $stmt_order->close();
        }
    }
}


// 5. --- HANDLE DELETE ACTION (ADMIN ONLY) ---
// (MODIFIED) We keep DELETE strictly for Admins to prevent managers from erasing history.
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!hasAdminAccess()) {
        $error_message = "Access Denied. Only Admins can delete records.";
    } elseif (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $complaint_id_to_delete = (int)$_GET['id'];
        
        $sql_delete = "DELETE FROM complaints WHERE id = ?";
        $stmt_delete = $db->prepare($sql_delete);
        $stmt_delete->bind_param('i', $complaint_id_to_delete);
        
        if ($stmt_delete->execute()) {
            $success_message = "Complaint #" . $complaint_id_to_delete . " has been permanently deleted.";
        } else {
            $error_message = "Failed to delete complaint: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }
}

// 6. --- LOAD DATA FOR DISPLAY ---
$filter_status = $_GET['status'] ?? '';

$sql = "SELECT * FROM complaints";
if (!empty($filter_status)) {
    $sql .= " WHERE status = '" . $db->real_escape_string($filter_status) . "'";
}
$sql .= " ORDER BY created_at DESC";

$complaints = [];
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
}
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900"><?php echo e($page_title); ?></h1>
        <p class="text-gray-600 mt-1">Review and resolve customer complaints.</p>
    </div>
</div>

<!-- Main grid layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Manual Add Form -->
    <div class="lg:col-span-1">
    
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

        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Log Manual Complaint</h2>
            <form action="manage_complaints.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <div>
                    <label for="order_id" class="block text-sm font-medium text-gray-700">Order ID *</label>
                    <input type="text" id="order_id" name="order_id" value="<?php echo e($form_order_id); ?>"
                           placeholder="e.g., PM-123" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="complaint_type" class="block text-sm font-medium text-gray-700">Category of Issue *</label>
                    <select id="complaint_type" name="complaint_type" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">-- Select a Category --</option>
                        <option value="Food Quality (e.g., cold, not tasty)" <?php echo ($form_complaint_type == 'Food Quality (e.g., cold, not tasty)') ? 'selected' : ''; ?>>Food Quality</option>
                        <option value="Wrong Item(s) Received" <?php echo ($form_complaint_type == 'Wrong Item(s) Received') ? 'selected' : ''; ?>>Wrong Item(s) Received</option>
                        <option value="Missing Item(s)" <?php echo ($form_complaint_type == 'Missing Item(s)') ? 'selected' : ''; ?>>Missing Item(s)</option>
                        <option value="Delivery Issue (e.g., late, driver)" <?php echo ($form_complaint_type == 'Delivery Issue (e.g., late, driver)') ? 'selected' : ''; ?>>Delivery Issue</option>
                        <option value="Packaging Issue" <?php echo ($form_complaint_type == 'Packaging Issue') ? 'selected' : ''; ?>>Packaging Issue</option>
                        <option value="Other" <?php echo ($form_complaint_type == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div>
                    <label for="complaint_text" class="block text-sm font-medium text-gray-700">Complaint Details *</label>
                    <textarea id="complaint_text" name="complaint_text" rows="5" required
                              placeholder="Copy/paste or describe the customer's message here."
                              class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($form_complaint_text); ?></textarea>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700">Set Initial Status *</label>
                    <select id="status" name="status" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="Submitted" <?php echo ($form_status == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                        <option value="In Review" <?php echo ($form_status == 'In Review') ? 'selected' : ''; ?>>In Review</option>
                        <option value="Resolved" <?php echo ($form_status == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>

                <button type="submit" name="add_complaint"
                        class="mt-6 w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 transition-colors">
                    Log Complaint
                </button>
            </form>
        </div>
    </div>

    <!-- Column 2: List of Complaints -->
    <div class="lg:col-span-2">
        <!-- Filter Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-lg mb-8">
            <form action="manage_complaints.php" method="GET" class="flex flex-col sm:flex-row gap-4">
                <select name="status" class="px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">All Statuses</option>
                    <option value="Submitted" <?php echo ($filter_status == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                    <option value="In Review" <?php echo ($filter_status == 'In Review') ? 'selected' : ''; ?>>In Review</option>
                    <option value="Resolved" <?php echo ($filter_status == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                </select>
                
                <button type="submit" class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                    Filter
                </button>
                <a href="manage_complaints.php" class="px-6 py-3 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                    Clear
                </a>
            </form>
        </div>

        <!-- Complaint List Table -->
        <div class="bg-white rounded-2xl shadow-lg">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($complaints)): ?>
                            <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No complaints found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($complaints as $complaint): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <a href="order_details.php?id=<?php echo e($complaint['order_id']); ?>" class="text-sm font-bold text-orange-600 hover:text-orange-800" target="_blank">
                                            #PM-<?php echo e($complaint['order_id']); ?>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($complaint['customer_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo e($complaint['customer_phone']); ?></div>
                                    </td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-700"><?php echo e($complaint['complaint_type']); ?></div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-700"><?php echo e(date('d M, h:i A', strtotime($complaint['created_at']))); ?></div></td>
                                    <td class="px-6 py-4">
                                        <?php if ($complaint['status'] == 'Submitted'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Submitted</span>
                                        <?php elseif ($complaint['status'] == 'In Review'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">In Review</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Resolved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="complaint_details.php?id=<?php echo e($complaint['id']); ?>" class="text-orange-600 hover:text-orange-900">View/Manage</a>
                                        
                                        <?php if (hasAdminAccess()): ?>
                                        <a href="manage_complaints.php?action=delete&id=<?php echo e($complaint['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to permanently delete this complaint?');">
                                            Delete
                                        </a>
                                        <?php endif; ?>
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