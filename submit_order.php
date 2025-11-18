<?php
/*
 * submit_order.php
 * KitchCo: Cloud Kitchen Order Submission Handler
 * Version 2.0 - (MODIFIED) Added Night Surcharge Exemption Logic
 *
 * This file is NOT a visible page. It:
 * 1. Is the target for the checkout.php form.
 * 2. Validates name and phone number.
 * 3. Re-validates coupon code.
 * 4. Re-calculates totals with global discount.
 * 5. Applies delivery promotions.
 * 6. Saves the order to the database using a transaction.
 * 7. Increments coupon usage.
 * 8. Clears the cart from the session.
 * 9. Prepares GTM data and fires CAPI event.
 * 10. Redirects to order_success.php.
 */

// 1. CONFIGURATION
require_once('config.php');
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
    return ($new_price > 0) ? $new_price : 0;
}

// 2. --- INITIAL VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) || $settings['store_is_open'] == '0') {
    header('Location: ' . BASE_URL . '/menu');
    exit;
}

// 3. --- GET & SANITIZE CUSTOMER DATA ---
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$delivery_area_id = (int)($_POST['delivery_area_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cod';
$order_note = trim($_POST['order_note'] ?? ''); 

$coupon_code = trim($_POST['final_discount_code'] ?? '');
$coupon_id = null;
$discount_type = 'none';
$discount_amount = 0;

// --- SERVER-SIDE VALIDATION ---
// Rule 1: Name must be at least 4 characters
if (strlen($customer_name) < 4) {
    $_SESSION['checkout_error'] = 'Full Name must be at least 4 characters long.';
    header('Location: checkout.php');
    exit;
}
// Rule 2: Name must only contain valid characters
$name_pattern = "/^[a-zA-Z .'-]+$/"; 
if (!preg_match($name_pattern, $customer_name)) {
    $_SESSION['checkout_error'] = 'Full Name contains invalid characters. Only letters, spaces, periods, and hyphens are allowed.';
    header('Location: checkout.php');
    exit;
}

// Rule 3: Phone must match Bangladeshi format
$phone_pattern = "/^(\+88|88)?01[0-9]{9}$/";
if (!preg_match($phone_pattern, $customer_phone)) {
    $_SESSION['checkout_error'] = 'Please enter a valid 11-digit Bangladeshi phone number (e.g., 01712345678).';
    header('Location: checkout.php');
    exit;
}

// Rule 4: Other basic validation
if (empty($customer_address) || $delivery_area_id <= 0) {
    $_SESSION['checkout_error'] = 'Please provide a full address and select a delivery area.';
    header('Location: checkout.php');
    exit;
}
// --- END VALIDATION ---


// 4. --- SERVER-SIDE RE-CALCULATION (CRITICAL) ---
try {
    // --- A. Calculate Subtotal ---
    $subtotal = 0;
    foreach ($cart as $cart_key => $item) {
        
        $stmt_item = $db->prepare("SELECT price FROM menu_items WHERE id = ?");
        $stmt_item->bind_param('i', $item['item_id']);
        $stmt_item->execute();
        $result_item = $stmt_item->get_result();
        if ($result_item->num_rows == 0) throw new Exception("Item {$item['item_name']} is no longer available.");
        $db_item = $result_item->fetch_assoc();
        
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
            $stmt_opt->close(); 
        }
        
        // 3. Update cart item with re-verified price
        $single_item_price = $base_price + $options_price;
        
        if (abs($single_item_price - $item['single_item_price']) > 0.01) {
            throw new Exception("Price mismatch for item {$item['item_name']}. Please clear your cart and try again.");
        }
        
        // 4. Add to subtotal
        $subtotal += $single_item_price * $item['quantity'];
        $stmt_item->close(); 
    }

    // --- B. Re-Validate Coupon ---
    if (!empty($coupon_code)) {
        $sql = "SELECT * FROM coupons WHERE code = ? LIMIT 1";
        $stmt_coupon = $db->prepare($sql);
        $stmt_coupon->bind_param('s', $coupon_code);
        $stmt_coupon->execute();
        $result_coupon = $stmt_coupon->get_result();

        if ($result_coupon->num_rows == 1) {
            $coupon = $result_coupon->fetch_assoc();
            $now = time();
            if ($coupon['is_active'] && $coupon['current_uses'] < $coupon['max_uses'] &&
                $now >= strtotime($coupon['start_date']) && $now <= strtotime($coupon['end_date']) &&
                $subtotal >= $coupon['min_order_amount']) 
            {
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
        $stmt_coupon->close(); 
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
    
    // (NEW) Check exemption list
    $exempt_areas_str = $settings['night_surcharge_exempt_areas'] ?? '';
    $exempt_areas = explode(',', $exempt_areas_str);
    $is_exempt = in_array($delivery_area_id, $exempt_areas);

    if ($surcharge > 0 && !$is_exempt) {
        $start_hour = (int)($settings['night_surcharge_start_hour'] ?? 0);
        $end_hour = (int)($settings['night_surcharge_end_hour'] ?? 6);
        $current_hour = (int)date('G');
        if (($start_hour > $end_hour && ($current_hour >= $start_hour || $current_hour < $end_hour)) ||
            ($start_hour <= $end_hour && ($current_hour >= $start_hour && $current_hour < $end_hour))) {
            $surcharge_amount = $surcharge;
        }
    }
    $stmt_area->close(); 
    
    $total_delivery_fee = $base_charge + $surcharge_amount;

    // --- DELIVERY PROMOTION LOGIC ---
    
    // First, check for global "Free Delivery" (overrides everything)
    if (!empty($settings['free_delivery_active']) && $settings['free_delivery_active'] == '1') {
        $total_delivery_fee = 0;
    } 
    // ELSE, check for a percentage discount
    else if (!empty($settings['delivery_discount_active']) && $settings['delivery_discount_active'] == '1') {
        $discount_value = (float)($settings['delivery_discount_percentage'] ?? 0);
        
        if ($discount_value > 0 && $discount_value <= 100) { 
            $discount_percent_amount = $total_delivery_fee * ($discount_value / 100);
            $total_delivery_fee = $total_delivery_fee - $discount_percent_amount;
        }
    }
    // --- END OF LOGIC ---
    
    // --- D. Calculate Final Total ---
    $total_amount = ($subtotal - $discount_amount) + $total_delivery_fee;
    if ($total_amount < 0) {
        $total_amount = 0;
    }

    // 5. --- SAVE TO DATABASE (TRANSACTION) ---
    
    // --- A. Insert into `orders` table ---
    $order_status = 'Pending'; 
    
    $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, order_note, delivery_area_id, 
                                      subtotal, delivery_fee, total_amount, order_status, 
                                      coupon_id, discount_type, discount_amount, order_time) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_order = $db->prepare($sql_order);
    $stmt_order->bind_param('ssssidddsisd', 
        $customer_name, $customer_phone, $customer_address, $order_note, $delivery_area_id, 
        $subtotal, $total_delivery_fee, $total_amount, $order_status,
        $coupon_id, $discount_type, $discount_amount
    );
    $stmt_order->execute();
    $order_id = $db->insert_id; 
    
    $order_for_capi = [
        'order_id' => $order_id,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'total_amount' => $total_amount
    ];
    $items_for_capi = [];

    if ($order_id <= 0) throw new Exception("Failed to create order header.");
    $stmt_order->close(); 

    // --- B. Insert into `order_items` and `order_item_options` ---
    $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $db->prepare($sql_item);
    
    $sql_option = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
    $stmt_option = $db->prepare($sql_option);

    $gtm_items = []; 

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
        
        // Add to CAPI items array
        $items_for_capi[] = [
            'menu_item_id' => $item['item_id'],
            'quantity' => $item['quantity'],
            'single_item_price' => $item['single_item_price']
        ];
    }
    $stmt_item->close(); 
    $stmt_option->close();
    
    // --- C. Increment Coupon Usage ---
    if ($coupon_id) {
        $sql_update_coupon = "UPDATE coupons SET current_uses = current_uses + 1 WHERE id = ?";
        $stmt_update_coupon = $db->prepare($sql_update_coupon);
        $stmt_update_coupon->bind_param('i', $coupon_id);
        $stmt_update_coupon->execute();
        $stmt_update_coupon->close(); 
    }

    // 6. --- COMMIT TRANSACTION ---
    $db->commit();
    
    // 7. --- (PHASE 5) MARKETING & SESSION ---
    
    // A. Prepare GTM 'purchase' event data and store in session
    $_SESSION['gtm_purchase_data'] = [
        'event' => 'purchase',
        'ecommerce' => [
            'transaction_id' => 'PM-' . $order_id,
            'value' => $total_amount,
            'tax' => 0, 
            'shipping' => $total_delivery_fee,
            'currency' => 'BDT',
            'coupon' => $coupon_code,
            'discount' => $discount_amount, // This is cart discount, not delivery
            'items' => $gtm_items
        ]
    ];
    
    // B. Fire Facebook CAPI (Server-Side)
    fire_facebook_capi($order_for_capi, $items_for_capi, $settings);

    // 8. --- CLEANUP & REDIRECT ---
    
    // A. Clear the cart
    $_SESSION['cart'] = [];
    
    // B. Store last order ID for success page
    $_SESSION['last_order_id'] = $order_id;
    
    // C. Redirect to "Thank You" page
    header('Location: ' . BASE_URL . '/order-success');
    exit;

} catch (Exception $e) {
    // Something went wrong, roll back the transaction
    $db->rollback();
    die('Error placing order: ' . $e->getMessage() . ' Please go back and try again.');
}
?>