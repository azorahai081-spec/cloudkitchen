<?php
/*
 * track_order.php
 * KitchCo: Cloud Kitchen Customer Order Tracking
 * Version 1.2 - (MODIFIED) Redesigned buttons and status colors
 *
 * This page allows a customer to check their order status
 * by entering their Order ID.
 */

// 1. PAGE SETUP
$page_title = 'Track Your Order - KitchCo';
$meta_description = 'Check the status of your KitchCo food order.';

// 2. HEADER
require_once('includes/header.php');

// 3. --- INITIALIZE VARIABLES ---
$order_id_raw = $_GET['order_id'] ?? '';
$order_id_clean = null;
$error_message = '';
$order_status = null;
$customer_name = null;
$order_time = null;

// 4. --- CHECK FOR ORDER ID ---
if (!empty($order_id_raw)) {
    // Sanitize the input.
    // Customers might enter "PM-123" or just "123".
    $order_id_clean = (int)preg_replace('/[^0-9]/', '', $order_id_raw);
    
    if ($order_id_clean > 0) {
        // --- A. VALID ID, QUERY THE DATABASE ---
        $sql = "SELECT order_status, customer_name, order_time FROM orders WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $order_id_clean);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // --- B. ORDER FOUND ---
            $order = $result->fetch_assoc();
            $order_status = $order['order_status'];
            $customer_name = $order['customer_name'];
            $order_time = $order['order_time'];
        } else {
            // --- C. ORDER NOT FOUND ---
            // (MODIFIED) Changed prefix
            $error_message = "Sorry, we couldn't find an order with the ID 'PM-" . e($order_id_clean) . "'.";
        }
        $stmt->close();
    } else {
        $error_message = "Please enter a valid Order ID number.";
    }
}

// 5. --- HELPER FUNCTION FOR STATUS MESSAGE ---
function getStatusDetails($status) {
    switch ($status) {
        case 'Pending':
            return [
                'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z', // Clock icon
                'color' => 'text-gray-500',
                'message' => 'We\'ve received your order and are waiting for the kitchen to accept it.'
            ];
        case 'Preparing':
            return [
                'icon' => 'M12 8.25H18M12 11.25H18M12 14.25H18M7.5 13.5h.008v.008H7.5v-.008zM7.5 16.5h.008v.008H7.5v-.008zM3.375 21h17.25c1.035 0 1.875-.84 1.875-1.875V7.5c0-1.036-.84-1.875-1.875-1.875H3.375c-1.036 0-1.875.84-1.875 1.875v11.625c0 1.035.84 1.875 1.875 1.875z', // Clipboard icon
                'color' => 'text-blue-500',
                'message' => 'Our kitchen is currently preparing your delicious food!'
            ];
        case 'Ready':
            return [
                'icon' => 'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.263-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z', // Bag icon
                'color' => 'text-brand-yellow', // (MODIFIED) Changed from orange-500
                'message' => 'Your order is ready and waiting for a rider to pick it up.'
            ];
        case 'Delivered':
            return [
                'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', // Check circle
                'color' => 'text-green-500',
                'message' => 'Your order has been successfully delivered. Enjoy your meal!'
            ];
        case 'Cancelled':
            return [
                'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z', // X circle
                'color' => 'text-red-500',
                'message' => 'This order has been cancelled. Please contact us if you have any questions.'
            ];
        default:
            return null;
    }
}
?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white p-6 rounded-2xl shadow-lg">
        <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">Track Your Order</h1>
        
        <!-- Search Form -->
        <form action="<?php echo BASE_URL; ?>/track-order" method="GET" class="flex gap-2">
            <input 
                type="text" 
                name="order_id" 
                value="<?php echo e($order_id_raw); ?>"
                placeholder="Enter your Order ID (e.g., PM-123)"
                required
                class="flex-grow mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red"
            >
            <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
            <button 
                type="submit"
                class="mt-1 px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700"
            >
                Track
            </button>
        </form>
    </div>
    
    <?php if ($error_message): ?>
        <!-- Error Message -->
        <div class="bg-white p-6 rounded-2xl shadow-lg mt-8 text-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto text-red-400">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
            </svg>
            <h2 class="text-xl font-bold text-gray-900 mt-4">Order Not Found</h2>
            <p class="text-gray-600 mt-2"><?php echo e($error_message); ?></p>
        </div>
    <?php elseif ($order_status): 
        $statusDetails = getStatusDetails($order_status);
    ?>
        <!-- Status Result -->
        <div class="bg-white p-6 rounded-2xl shadow-lg mt-8">
            <div class="text-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mx-auto <?php echo $statusDetails['color']; ?>">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?php echo $statusDetails['icon']; ?>" />
                </svg>
                <h2 class="text-2xl font-bold text-gray-900 mt-4">Order Status: <?php echo e($order_status); ?></h2>
                <p class="text-lg text-gray-600 mt-2"><?php echo e($statusDetails['message']); ?></p>
            </div>
            
            <div class="mt-6 border-t pt-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Order ID:</span>
                    <!-- (MODIFIED) Changed prefix -->
                    <span class="font-medium text-gray-900">PM-<?php echo e($order_id_clean); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Customer:</span>
                    <span class="font-medium text-gray-900"><?php echo e($customer_name); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Order Time:</span>
                    <span class="font-medium text-gray-900"><?php echo e(date('d M Y, h:i A', strtotime($order_time))); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
    <div class="text-center">
        <a href="<?php echo BASE_URL; ?>/" class="mt-8 inline-block px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700">
            &larr; Back to Homepage
        </a>
    </div>
    
</div>

<?php
// 6. FOOTER
require_once('includes/footer.php');
?>