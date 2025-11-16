<?php
/*
 * admin/complaint_details.php
 * KitchCo: Cloud Kitchen Complaint Details Page
 * Version 1.0
 *
 * This is an ADMIN-ONLY page to view and manage a single complaint.
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. --- GET COMPLAINT ID ---
$complaint_id = $_GET['id'] ?? null;
if (empty($complaint_id)) {
    header('Location: manage_complaints.php');
    exit;
}
$complaint_id = (int)$complaint_id;
$page_title = "Complaint Details #{$complaint_id}";

// 4. --- HANDLE POST ACTIONS (Update Status) ---
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $new_status = $_POST['new_status'];
        $allowed_statuses = ['Submitted', 'In Review', 'Resolved'];
        
        if (in_array($new_status, $allowed_statuses)) {
            $sql_update = "UPDATE complaints SET status = ? WHERE id = ?";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bind_param('si', $new_status, $complaint_id);
            if ($stmt_update->execute()) {
                $success_message = "Complaint status updated to '{$new_status}'!";
            } else {
                $error_message = 'Failed to update status.';
            }
            $stmt_update->close();
        }
    }
}

// 5. --- LOAD COMPLAINT DATA ---
$sql = "SELECT * FROM complaints WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $complaint_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header('Location: manage_complaints.php');
    exit;
}
$complaint = $result->fetch_assoc();
$stmt->close();

?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900"><?php echo e($page_title); ?></h1>
        <p class="text-gray-600 mt-1">
            Submitted on <?php echo e(date('d M Y, h:i A', strtotime($complaint['created_at']))); ?>
        </p>
    </div>
    <a href="manage_complaints.php" class="mt-4 sm:mt-0 px-5 py-2 bg-gray-200 text-gray-700 font-medium rounded-lg shadow-md hover:bg-gray-300">
        &larr; Back to All Complaints
    </a>
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

    <!-- Column 1: Complaint Details -->
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Complaint Details</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Complaint Type</label>
                    <p class="text-lg font-medium text-gray-900"><?php echo e($complaint['complaint_type']); ?></p>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500">Full Complaint Message</label>
                    <div class="mt-1 p-4 w-full bg-gray-50 border border-gray-200 rounded-lg">
                        <p class="text-base text-gray-800" style="white-space: pre-wrap;"><?php echo e($complaint['complaint_text']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Column 2: Customer & Actions -->
    <aside class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-8 space-y-6">
            <!-- Customer Details -->
            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Customer & Order</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Customer</label>
                        <p class="text-lg font-medium text-gray-900"><?php echo e($complaint['customer_name']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone</label>
                        <p class="text-lg font-medium text-gray-900"><?php echo e($complaint['customer_phone']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Related Order</label>
                        <p>
                            <a href="order_details.php?id=<?php echo e($complaint['order_id']); ?>" class="text-lg font-medium text-orange-600 hover:text-orange-800">
                                View Order #PM-<?php echo e($complaint['order_id']); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div>
                <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Manage Status</h2>
                <form action="complaint_details.php?id=<?php echo e($complaint_id); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                    
                    <div>
                        <label for="new_status" class="block text-sm font-medium text-gray-700">Change Status</label>
                        <select id="new_status" name="new_status" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="Submitted" <?php echo ($complaint['status'] == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="In Review" <?php echo ($complaint['status'] == 'In Review') ? 'selected' : ''; ?>>In Review</option>
                            <option value="Resolved" <?php echo ($complaint['status'] == 'Resolved') ? 'selected' : ''; ?>>Resolved</option>
                        </select>
                    </div>
                    <button type="submit" name="update_status" class="w-full py-3 px-4 bg-green-600 text-white font-medium rounded-lg shadow-md hover:bg-green-700">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
    </aside>
</div>

<?php
// 6. FOOTER
require_once('footer.php');
?>