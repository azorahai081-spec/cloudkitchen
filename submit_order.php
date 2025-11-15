<?php
/*
 * submit_order.php
 * KitchCo: Cloud Kitchen Order Submission Handler
 * Version 1.3 - Changed Order ID prefix to PM-
 *
 * This file is NOT a visible page. It:
 * 1. Is the target for the checkout.php form.
 * 2. Validates all POST data (customer info, totals).
 * 3. Re-calculates totals on the server to prevent tampering.
 * 4. Saves the order to the database using a transaction.
 * 5. Clears the cart from the session.
 * 6. (Phase 5) Prepares GTM data and fires CAPI event.
 * 7. Redirects to order_success.php.
 */

// 1. CONFIGURATION
require_once('config.php');
// (NEW) 1B. Include CAPI Helper
require_once('includes/fb_capi.php');

// 2. --- INITIAL VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Not a POST request
    // (MODIFIED) Clean URL
    header('Location: ' . BASE_URL . '/');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || $settings['store_is_open'] == '0') {
    // Cart is empty or store is closed
    // (MODIFIED) Clean URL
    header('Location: ' . BASE_URL . '/menu');
    exit;
}

// 3. --- GET & SANITIZE CUSTOMER DATA ---
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$delivery_area_id = (int)($_POST['delivery_area_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cod';

// Basic validation
if (empty($customer_name) || empty($customer_phone) || empty($customer_address) || $delivery_area_id <= 0) {
    // In a real app, you'd save this to session and show on checkout.php
    // For now, we'll just stop.
    die('Error: Missing required fields. Please go back and try again.');
}

// 4. --- SERVER-SIDE RE-CALCULATION (CRITICAL) ---
// Trust NOTHING from the client (e.g., $_POST['final_total'])
$db->begin_transaction();

try {
    // --- A. Calculate Subtotal ---
    $subtotal = 0;
    foreach ($cart as $cart_key => $item) {
        // We re-check the price of every item and its options from the DB
        
        // 1. Get base item price
        $stmt_item = $db->prepare("SELECT price FROM menu_items WHERE id = ?");
        $stmt_item->bind_param('i', $item['item_id']);
        $stmt_item->execute();
        $result_item = $stmt_item->get_result();
        if ($result_item->num_rows == 0) throw new Exception("Item {$item['item_name']} is no longer available.");
        $db_item = $result_item->fetch_assoc();
        $base_price = (float)$db_item['price'];
        
        // 2. Get options prices
        $options_price = 0;
        if (!empty($item['options'])) {
            $option_names = array_column($item['options'], 'name');
            $placeholders = implode(',', array_fill(0, count($option_names), '?'));
            $types = str_repeat('s', count($option_names));
            
            $sql_opt = "SELECT price_increase FROM item_options WHERE name IN ($placeholders)";
            $stmt_opt = $db->prepare($sql_opt);
            $stmt_opt->bind_param($types, ...$option_names);
            $stmt_opt->execute();
            $result_opt = $stmt_opt->get_result();
            while($row = $result_opt->fetch_assoc()) {
                $options_price += (float)$row['price_increase'];
            }
        }
        
        // 3. Update cart item with re-verified price
        $single_item_price = $base_price + $options_price;
        $_SESSION['cart'][$cart_key]['single_item_price'] = $single_item_price;
        
        // 4. Add to subtotal
        $subtotal += $single_item_price * $item['quantity'];
    }

    // --- B. Calculate Delivery Fee (re-using logic from ajax_calculate_fee.php) ---
    $stmt_area = $db->prepare("SELECT base_charge FROM delivery_areas WHERE id = ? AND is_active = 1");
    $stmt_area->bind_param('i', $delivery_area_id);
    $stmt_area->execute();
    $result_area = $stmt_area->get_result();
    if ($result_area->num_rows == 0) throw new Exception("Selected delivery area is not available.");
    
    $base_charge = (float)$result_area->fetch_assoc()['base_charge'];
    $surcharge_amount = 0;
    $surcharge = (float)($settings['night_surcharge_amount'] ?? 0);
    
    if ($surcharge > 0) {
        $start_hour = (int)($settings['night_surcharge_start_hour'] ?? 0);
        $end_hour = (int)($settings['night_surcharge_end_hour'] ?? 6);
        $current_hour = (int)date('G');
        if (($start_hour > $end_hour && ($current_hour >= $start_hour || $current_hour < $end_hour)) ||
            ($start_hour <= $end_hour && ($current_hour >= $start_hour && $current_hour < $end_hour))) {
            $surcharge_amount = $surcharge;
        }
    }
    
    $total_delivery_fee = $base_charge + $surcharge_amount;
    $total_amount = $subtotal + $total_delivery_fee;

    // 5. --- SAVE TO DATABASE (TRANSACTION) ---
    
    // --- A. Insert into `orders` table ---
    $order_status = 'Pending'; // Kitchen will change this
    $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, delivery_area_id, subtotal, delivery_fee, total_amount, order_status, order_time) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_order = $db->prepare($sql_order);
    $stmt_order->bind_param('sssiddds', $customer_name, $customer_phone, $customer_address, $delivery_area_id, $subtotal, $total_delivery_fee, $total_amount, $order_status);
    $stmt_order->execute();
    $order_id = $db->insert_id; // Get the new order ID
    
    // (NEW) Create array for CAPI
    $order_for_capi = [
        'order_id' => $order_id,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'total_amount' => $total_amount
    ];
    $items_for_capi = [];

    if ($order_id <= 0) throw new Exception("Failed to create order header.");

    // --- B. Insert into `order_items` and `order_item_options` ---
    $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $db->prepare($sql_item);
    
    $sql_option = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
    $stmt_option = $db->prepare($sql_option);

    $gtm_items = []; // For GTM data

    foreach ($cart as $item) {
        $item_total_price = $item['single_item_price'] * $item['quantity'];
        
        $stmt_item->bind_param('iiidd', $order_id, $item['item_id'], $item['quantity'], $item['base_price'], $item_total_price);
        $stmt_item->execute();
        $order_item_id = $db->insert_id;
        
        if ($order_item_id <= 0) throw new Exception("Failed to save order item: {$item['item_name']}");
        
        // Add options
        foreach ($item['options'] as $option) {
            $stmt_option->bind_param('isd', $order_item_id, $option['name'], $option['price']);
            $stmt_option->execute();
        }

        // Add to GTM items array
        $gtm_items[] = [
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'price' => $item['single_item_price'],
            'quantity' => $item['quantity']
        ];
        
        // (NEW) Add to CAPI items array
        $items_for_capi[] = [
            'menu_item_id' => $item['item_id'],
            'quantity' => $item['quantity'],
            'single_item_price' => $item['single_item_price']
        ];
    }
    
    // 6. --- COMMIT TRANSACTION ---
    $db->commit();
    
    // 7. --- (PHASE 5) MARKETING & SESSION ---
    
    // A. Prepare GTM 'purchase' event data and store in session
    $_SESSION['gtm_purchase_data'] = [
        'event' => 'purchase',
        'ecommerce' => [
            // (MODIFIED) Changed prefix
            'transaction_id' => 'PM-' . $order_id,
            'value' => $total_amount,
            'tax' => 0, // Assuming no tax for now
            'shipping' => $total_delivery_fee,
            'currency' => 'BDT',
            'items' => $gtm_items
        ]
    ];
    
    // B. (MODIFIED) Fire Facebook CAPI (Server-Side)
    // The $settings array is already loaded from config.php
    fire_facebook_capi($order_for_capi, $items_for_capi, $settings);

    // 8. --- CLEANUP & REDIRECT ---
    
    // A. Clear the cart
    $_SESSION['cart'] = [];
    
    // B. Store last order ID for success page
    $_SESSION['last_order_id'] = $order_id;
    
    // C. Redirect to "Thank You" page
    // (MODIFIED) Clean URL
    header('Location: ' . BASE_URL . '/order-success');
    exit;

} catch (Exception $e) {
    // Something went wrong, roll back the transaction
    $db->rollback();
    
    // In a real app, you'd log this error and show a user-friendly message
    // For now, we'll just die.
    die('Error placing order: ' . $e->getMessage() . ' Please go back and try again.');
}
?>