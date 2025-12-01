<?php
/*
 * complain.php
 * PizzaMania: Cloud Kitchen Customer Complaint Page
 * Version 1.1 - Added specific SEO Variables
 */

// 1. CONFIGURATION (Load first to get DB settings)
require_once('config.php');

// 2. PAGE SPECIFIC SEO VARIABLES
$page_title = 'File a Complaint - ' . ($settings['store_name'] ?? 'Pizza Mania');
$meta_description = 'We are sorry if you had a bad experience. Please file a complaint here so we can resolve it.';

// 3. HEADER
require_once('includes/header.php');

// 3. --- PAGE LOGIC ---
$error_message = '';
$success_message = '';
$step = 1; // Start at step 1 (Verification)

$order_id_raw = '';
$customer_phone = '';
$verified_order_id = null;

// --- A. HANDLE STEP 1: VERIFY ORDER (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_order'])) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $order_id_raw = trim($_POST['order_id']);
        $customer_phone = trim($_POST['customer_phone']);

        // Sanitize order ID (e.g., "PM-123" -> 123)
        $order_id_clean = (int) preg_replace('/[^0-9]/', '', $order_id_raw);

        if ($order_id_clean <= 0 || empty($customer_phone)) {
            $error_message = 'Please enter a valid Order ID and Phone Number.';
        } else {
            // Check 1: Find the order
            $sql_order = "SELECT id, order_status, customer_name FROM orders WHERE id = ? AND customer_phone = ?";
            $stmt_order = $db->prepare($sql_order);
            $stmt_order->bind_param('is', $order_id_clean, $customer_phone);
            $stmt_order->execute();
            $result_order = $stmt_order->get_result();

            if ($result_order->num_rows == 0) {
                $error_message = 'Order ID or Phone Number not found. Please check your details and try again.';
            } else {
                $order = $result_order->fetch_assoc();

                // Check 2: Is status 'Delivered'?
                if ($order['order_status'] !== 'Delivered') {
                    $error_message = "You can only submit a complaint for a 'Delivered' order. Your order status is: <strong>" . e($order['order_status']) . "</strong>.";
                } else {
                    // Check 3: Has a complaint already been filed?
                    $sql_check = "SELECT id FROM complaints WHERE order_id = ?";
                    $stmt_check = $db->prepare($sql_check);
                    $stmt_check->bind_param('i', $order_id_clean);
                    $stmt_check->execute();
                    $result_check = $stmt_check->get_result();

                    if ($result_check->num_rows > 0) {
                        $error_message = "We have already received a complaint for Order #PM-" . e($order_id_clean) . ". A team member will be in touch soon.";
                    } else {
                        // All checks passed! Proceed to Step 2.
                        $step = 2;
                        $verified_order_id = $order_id_clean;
                        // (Keep these for the next form)
                        $order_id_raw = 'PM-' . $order_id_clean;
                        $customer_phone = $customer_phone;
                    }
                    $stmt_check->close();
                }
            }
            $stmt_order->close();
        }
    }
}

// --- B. HANDLE STEP 2: SUBMIT COMPLAINT (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $order_id = (int) $_POST['order_id'];
        $customer_phone = trim($_POST['customer_phone']);
        $complaint_type = trim($_POST['complaint_type']);
        $complaint_text = trim($_POST['complaint_text']);

        // Final validation
        if (empty($complaint_type) || empty($complaint_text) || $order_id <= 0 || empty($customer_phone)) {
            $error_message = 'Please select a complaint category and provide a detailed message.';
            $step = 2; // Stay on step 2
            $verified_order_id = $order_id; // Keep form populated
            $order_id_raw = 'PM-' . $order_id;
        } else {
            // Re-fetch customer name from order for logging
            $sql_order = "SELECT customer_name FROM orders WHERE id = ? AND customer_phone = ?";
            $stmt_order = $db->prepare($sql_order);
            $stmt_order->bind_param('is', $order_id, $customer_phone);
            $stmt_order->execute();
            $result_order = $stmt_order->get_result();

            if ($result_order->num_rows == 1) {
                $order = $result_order->fetch_assoc();
                $customer_name = $order['customer_name'];

                // Insert into the new complaints table
                $sql_insert = "INSERT INTO complaints (order_id, customer_name, customer_phone, complaint_type, complaint_text, status) 
                               VALUES (?, ?, ?, ?, ?, 'Submitted')";
                $stmt_insert = $db->prepare($sql_insert);
                $stmt_insert->bind_param('issss', $order_id, $customer_name, $customer_phone, $complaint_type, $complaint_text);

                if ($stmt_insert->execute()) {
                    $success_message = "Your complaint for Order #PM-" . e($order_id) . " has been submitted. A team member will review it and get in touch with you shortly.";
                    $step = 3; // Show success message
                } else {
                    $error_message = 'An error occurred while submitting your complaint. Please try again later.';
                    $step = 2; // Stay on step 2
                }
                $stmt_insert->close();
            } else {
                $error_message = 'Order verification failed. Please start over.';
                $step = 1; // Send back to step 1
            }
            $stmt_order->close();
        }
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">Submit a Complaint</h1>

        <!-- Success Message -->
        <?php if ($step == 3): ?>
            <div class="text-center py-8">
                <svg class="w-24 h-24 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h2 class="mt-4 text-xl font-bold text-gray-900">Complaint Submitted</h2>
                <p class="text-gray-600 mt-2"><?php echo e($success_message); ?></p>
                <a href="<?php echo BASE_URL; ?>/"
                    class="mt-8 inline-block px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700">
                    &larr; Back to Homepage
                </a>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">
                <?php echo $error_message; // Allow <strong> tag for status ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Verification Form -->
        <?php if ($step == 1): ?>
            <p class="text-gray-600 mb-4">
                We're sorry you had a bad experience. To file a complaint, please verify your order.
                You can only submit a complaint for an order that has been marked as "Delivered".
            </p>
            <form action="complain.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

                <div>
                    <label for="order_id" class="block text-sm font-medium text-gray-700">Order ID *</label>
                    <input type="text" id="order_id" name="order_id" value="<?php echo e($order_id_raw); ?>"
                        placeholder="e.g., PM-123" required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                </div>

                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" value="<?php echo e($customer_phone); ?>"
                        placeholder="The phone number you used for delivery" required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                </div>

                <button type="submit" name="verify_order"
                    class="mt-6 w-full py-3 px-4 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700 transition-colors">
                    Verify Order
                </button>
            </form>
        <?php endif; ?>

        <!-- Step 2: Complaint Form -->
        <?php if ($step == 2): ?>
            <form action="complain.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                <input type="hidden" name="order_id" value="<?php echo e($verified_order_id); ?>">
                <input type="hidden" name="customer_phone" value="<?php echo e($customer_phone); ?>">

                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="font-medium text-green-800">
                        Order Verified: <strong><?php echo e($order_id_raw); ?></strong>
                    </p>
                    <p class="text-sm text-green-700">Please provide details about your issue below.</p>
                </div>

                <div>
                    <label for="complaint_type" class="block text-sm font-medium text-gray-700">Category of Issue *</label>
                    <select id="complaint_type" name="complaint_type" required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                        <option value="">-- Select a Category --</option>
                        <option value="Food Quality (e.g., cold, not tasty)">Food Quality (e.g., cold, not tasty)</option>
                        <option value="Wrong Item(s) Received">Wrong Item(s) Received</option>
                        <option value="Missing Item(s)">Missing Item(s)</option>
                        <option value="Delivery Issue (e.g., late, driver)">Delivery Issue (e.g., late, driver)</option>
                        <option value="Packaging Issue">Packaging Issue</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label for="complaint_text" class="block text-sm font-medium text-gray-700">Please describe the issue in
                        detail *</label>
                    <textarea id="complaint_text" name="complaint_text" rows="5" required
                        placeholder="Please provide as much detail as possible so we can help."
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red"></textarea>
                </div>

                <button type="submit" name="submit_complaint"
                    class="mt-6 w-full py-3 px-4 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700 transition-colors">
                    Submit Complaint
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
// 4. FOOTER
require_once('includes/footer.php');
?>