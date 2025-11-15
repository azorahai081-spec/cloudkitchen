<?php
/*
 * submit_order.php
 * KitchCo: Cloud Kitchen Order Submission Handler
 * Version 1.6 - (MODIFIED) Added Order Note
 *
 * This file is NOT a visible page. It:
 * 1. Is the target for the checkout.php form.
 * 2. Validates all POST data (customer info, totals).
 * 3. Re-validates coupon code.
 * 4. Re-calculates totals with global discount.
 * 5. Saves the order to the database using a transaction.
 * 6. Increments coupon usage.
 * 7. Clears the cart from the session.
 * 8. Prepares GTM data and fires CAPI event.
 * 9. Redirects to order_success.php.
 */

// 1. CONFIGURATION
require_once('config.php');
// (NEW) 1B. Include CAPI Helper
require_once('includes/fb_capi.php');

// (NEW) Helper function to apply global discount
function calculate_discounted_price($original_price, $settings) {
    if (empty($settings['global_discount_active']) || $settings['global_discount_active'] == '0' || empty($settings['global_discount_value']) || $settings['global_discount_value'] <= 0) {
        return $original_price;
    }

    $discount_type = $settings['global_discount_type'];
    $discount_value = (float)$settings['global_discount_value'];
    $new_price = $original_price;

    if ($discount_type == 'percentage') {
        $new_price = $original_price - ($original_price * ($discount_value / 100));
    } elseif ($discount_type == 'fixed') {
        $new_price = $original_price - $discount_value;
    }
    
    // Don't let price go below 0
    return ($new_price > 0) ? $new_price : 0;
}

// 2. --- INITIAL VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Not a POST request
    header('Location: ' . BASE_URL . '/');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || $settings['store_is_open'] == '0') {
    // Cart is empty or store is closed
    header('Location: ' . BASE_URL . '/menu');
    exit;
}

// 3. --- GET & SANITIZE CUSTOMER DATA ---
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$delivery_area_id = (int)($_POST['delivery_area_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cod';
$order_note = trim($_POST['order_note'] ?? ''); // (NEW) Get the order note

// (NEW) Get Coupon Data
$coupon_code = trim($_POST['final_discount_code'] ?? '');
$coupon_id = null;
$discount_type = 'none';
$discount_amount = 0;

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
        
        // (MODIFIED) Apply global discount
        $original_base_price = (float)$db_item['price'];
        $base_price = calculate_discounted_price($original_base_price, $settings);
        
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
        
        // (MODIFIED) Security Check: Compare session price with calculated price
        // This ensures cart_actions.php and submit_order.php use the same logic.
        if (abs($single_item_price - $item['single_item_price']) > 0.01) {
            // Price mismatch, potential tampering
            throw new Exception("Price mismatch for item {$item['item_name']}. Please clear your cart and try again.");
        }
        
        // 4. Add to subtotal
        $subtotal += $single_item_price * $item['quantity'];
    }

    // --- B. (NEW) Re-Validate Coupon ---
    if (!empty($coupon_code)) {
        $sql = "SELECT * FROM coupons WHERE code = ? LIMIT 1";
        $stmt_coupon = $db->prepare($sql);
        $stmt_coupon->bind_param('s', $coupon_code);
        $stmt_coupon->execute();
        $result_coupon = $stmt_coupon->get_result();

        if ($result_coupon->num_rows == 1) {
            $coupon = $result_coupon->fetch_assoc();
            // All checks again to prevent tampering
            $now = time();
            if ($coupon['is_active'] && $coupon['current_uses'] < $coupon['max_uses'] &&
                $now >= strtotime($coupon['start_date']) && $now <= strtotime($coupon['end_date']) &&
                $subtotal >= $coupon['min_order_amount']) 
            {
                // Coupon is valid, calculate discount
                $coupon_id = $coupon['id'];
                $discount_type = $coupon['type'];
                
                if ($coupon['type'] == 'percentage') {
                    $discount_amount = $subtotal * ($coupon['value'] / 100);
                } else {
                    $discount_amount = $coupon['value'];
                }

                if ($discount_amount > $subtotal) {
                    $discount_amount = $subtotal;
                }
                $discount_amount = (float)number_format($discount_amount, 2, '.', '');
            }
        }
    }


    // --- C. Calculate Delivery Fee (re-using logic from ajax_calculate_fee.php) ---
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
    
    // --- D. (NEW) Calculate Final Total ---
    $total_amount = ($subtotal - $discount_amount) + $total_delivery_fee;

    // 5. --- SAVE TO DATABASE (TRANSACTION) ---
    
    // --- A. Insert into `orders` table ---
    $order_status = 'Pending'; // Kitchen will change this
    
    // (MODIFIED) Added discount fields and order_note to query
    $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, order_note, delivery_area_id, 
                                      subtotal, delivery_fee, total_amount, order_status, 
                                      coupon_id, discount_type, discount_amount, order_time) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_order = $db->prepare($sql_order);
    // (MODIFIED) Added new bound parameters: s, i, s, d
    $stmt_order->bind_param('ssssidddsisd', 
        $customer_name, $customer_phone, $customer_address, $order_note, $delivery_area_id, 
        $subtotal, $total_delivery_fee, $total_amount, $order_status,
        $coupon_id, $discount_type, $discount_amount
    );
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
            'price' => $item['single_item_price'], // This is the discounted price
            'quantity' => $item['quantity']
        ];
        
        // (NEW) Add to CAPI items array
        $items_for_capi[] = [
            'menu_item_id' => $item['item_id'],
            'quantity' => $item['quantity'],
            'single_item_price' => $item['single_item_price'] // This is the discounted price
        ];
    }
    
    // --- C. (NEW) Increment Coupon Usage ---
    if ($coupon_id) {
        $sql_update_coupon = "UPDATE coupons SET current_uses = current_uses + 1 WHERE id = ?";
        $stmt_update_coupon = $db->prepare($sql_update_coupon);
        $stmt_update_coupon->bind_param('i', $coupon_id);
        $stmt_update_coupon->execute();
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
            'coupon' => $coupon_code, // (NEW)
            'discount' => $discount_amount, // (NEW)
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