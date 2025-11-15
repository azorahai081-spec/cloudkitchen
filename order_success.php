<?php
/*
 * order_success.php
 * KitchCo: Cloud Kitchen Order Success ("Thank You") Page
 * Version 1.4 - (MODIFIED) Redesigned buttons
 *
 * This page:
 * 1. Confirms the order was placed.
 * 2. Fetches the new order ID from the session.
 * 3. Displays basic order details.
 * 4. (Phase 5) Fires the GTM 'purchase' event.
 */

// 1. PAGE SETUP
$page_title = 'Order Confirmed! - KitchCo';
$meta_description = 'Thank you for your order.';

// 2. HEADER
require_once('includes/header.php');

// 3. --- SECURITY CHECK & LOAD DATA ---
if (!isset($_SESSION['last_order_id'])) {
    // If no order was just placed, redirect to homepage
    // (MODIFIED) Clean URL
    header('Location: ' . BASE_URL . '/');
    exit;
}

$order_id = $_SESSION['last_order_id'];

// 4. --- FETCH ORDER DETAILS ---
// (FIXED) Select 'id' not 'order_id'
$sql = "SELECT id, total_amount, customer_name FROM orders WHERE id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param('i', $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Can't find the order, something is wrong
    // (MODIFIED) Clean URL
    header('Location: ' . BASE_URL . '/');
    exit;
}
$order = $result->fetch_assoc();

// 5. --- (PHASE 5) GTM DATA LAYER ---
// Check if the purchase data exists in the session
if (isset($_SESSION['gtm_purchase_data'])) {
    $gtm_data = $_SESSION['gtm_purchase_data'];
    // Unset it so it doesn't fire again on refresh
    unset($_SESSION['gtm_purchase_data']);
    
    echo "<script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push(" . json_encode($gtm_data) . ");
    </script>";
}

// 6. --- CLEANUP SESSION ---
// Unset the last_order_id so this page can't be refreshed
unset($_SESSION['last_order_id']);

?>

<!-- Main Content -->
<div class="max-w-2xl mx-auto text-center py-16">
    <!-- Checkmark Icon -->
    <svg class="w-24 h-24 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    
    <h1 class="mt-4 text-3xl font-bold text-gray-900">Thank You, <?php echo e($order['customer_name']); ?>!</h1>
    <p class="mt-2 text-lg text-gray-600">Your order has been placed successfully.</p>
    
    <div class="mt-8 bg-white p-6 rounded-2xl shadow-lg border text-left">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Order Summary</h2>
        <div class="space-y-2">
            <div class="flex justify-between">
                <span class="text-gray-600">Order ID:</span>
                <!-- (MODIFIED) Changed prefix -->
                <span class="font-medium text-gray-900">PM-<?php echo e($order['id']); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Payment Method:</span>
                <span class="font-medium text-gray-900">Cash on Delivery</span>
            </div>
            <div class="flex justify-between text-lg font-bold">
                <span class="text-gray-900">Total Amount:</span>
                <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
                <span class="text-brand-red"><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
            </div>
        </div>
        
        <p class="mt-6 text-sm text-gray-600">
            Our kitchen has received your order and will start preparing it shortly.
            You will receive a call from our rider soon.
        </p>
    </div>
    
    <!-- (MODIFIED) Clean URL -->
    <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
    <a href="<?php echo BASE_URL; ?>/" class="mt-8 inline-block px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700">
        &larr; Back to Homepage
    </a>
</div>

<?php
// 7. FOOTER
require_once('includes/footer.php');
?>