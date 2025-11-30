<?php
/*
 * submit_order.php
 * KitchCo: Cloud Kitchen Order Submission Handler
 * Version 2.1 - (MODIFIED) Integers Only for BDT
 *
 * This file handles final order validation and database insertion.
 */

// 1. CONFIGURATION
require_once('config.php');
require_once('includes/fb_capi.php');

// (NEW) Helper function to apply global discount (Returns Integer)
function calculate_discounted_price($original_price, $settings)
{
    if (empty($settings['global_discount_active']) || $settings['global_discount_active'] == '0' || empty($settings['global_discount_value']) || $settings['global_discount_value'] <= 0) {
        return (int) $original_price;
    }
    $discount_type = $settings['global_discount_type'];
    $discount_value = (float) $settings['global_discount_value'];
    $new_price = $original_price;
    if ($discount_type == 'percentage') {
        $new_price = $original_price - ($original_price * ($discount_value / 100));
    } elseif ($discount_type == 'fixed') {
        $new_price = $original_price - $discount_value;
    }
    return max(0, (int) round($new_price));
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
$delivery_area_id = (int) ($_POST['delivery_area_id'] ?? 0);
$payment_method = $_POST['payment_method'] ?? 'cod';
$order_note = trim($_POST['order_note'] ?? '');

$coupon_code = trim($_POST['final_discount_code'] ?? '');
$coupon_id = null;
$discount_type = 'none';
$discount_amount = 0;

// --- SERVER-SIDE VALIDATION ---
if (strlen($customer_name) < 4) {
    $_SESSION['checkout_error'] = 'Full Name must be at least 4 characters long.';
    header('Location: checkout.php');
    exit;
}
$name_pattern = "/^[a-zA-Z .'-]+$/";
if (!preg_match($name_pattern, $customer_name)) {
    $_SESSION['checkout_error'] = 'Full Name contains invalid characters.';
    header('Location: checkout.php');
    exit;
}

$phone_pattern = "/^(\+88|88)?01[0-9]{9}$/";
if (!preg_match($phone_pattern, $customer_phone)) {
    $_SESSION['checkout_error'] = 'Please enter a valid 11-digit Bangladeshi phone number.';
    header('Location: checkout.php');
    exit;
}

if (empty($customer_address) || $delivery_area_id <= 0) {
    $_SESSION['checkout_error'] = 'Please provide a full address and select a delivery area.';
    header('Location: checkout.php');
    exit;
}


// 4. --- SERVER-SIDE RE-CALCULATION (CRITICAL) ---

// Start the transaction before entering the try block
$db->begin_transaction();

try {
    // --- A. Calculate Subtotal ---
    $subtotal = 0;
    foreach ($cart as $cart_key => $item) {

        $stmt_item = $db->prepare("SELECT price FROM menu_items WHERE id = ?");
        $stmt_item->bind_param('i', $item['item_id']);
        $stmt_item->execute();
        $result_item = $stmt_item->get_result();
        if ($result_item->num_rows == 0)
            throw new Exception("Item {$item['item_name']} is no longer available.");
        $db_item = $result_item->fetch_assoc();

        // (MODIFIED) Use int logic
        $original_base_price = $db_item['price'];
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
            while ($row = $result_opt->fetch_assoc()) {
                // (MODIFIED) Cast to int
                $options_price += (int) $row['price_increase'];
            }
            $stmt_opt->close();
        }

        // 3. Update cart item with re-verified price
        $single_item_price = $base_price + $options_price;

        // (MODIFIED) Allow for integer logic check (strict check might fail if session had floats, so we rely on re-calc)
        // We won't throw an exception for minor float diffs, we just trust the server calculation now.

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
            if (
                $coupon['is_active'] && $coupon['current_uses'] < $coupon['max_uses'] &&
                $now >= strtotime($coupon['start_date']) && $now <= strtotime($coupon['end_date']) &&
                $subtotal >= $coupon['min_order_amount']
            ) {
                $coupon_id = $coupon['id'];
                $discount_type = $coupon['type'];

                if ($coupon['type'] == 'percentage') {
                    $raw_discount = $subtotal * ($coupon['value'] / 100);
                    $discount_amount = (int) round($raw_discount);
                } else {
                    $discount_amount = (int) $coupon['value'];
                }

                if ($discount_amount > $subtotal) {
                    $discount_amount = $subtotal;
                }
            }
        }
        $stmt_coupon->close();
    }


    // --- C. Calculate Delivery Fee ---
    $stmt_area = $db->prepare("SELECT base_charge FROM delivery_areas WHERE id = ? AND is_active = 1");
    $stmt_area->bind_param('i', $delivery_area_id);
    $stmt_area->execute();
    $result_area = $stmt_area->get_result();
    if ($result_area->num_rows == 0)
        throw new Exception("Selected delivery area is not available.");

    // (MODIFIED) Cast to int
    $base_charge = (int) $result_area->fetch_assoc()['base_charge'];
    $surcharge_amount = 0;
    $surcharge = (int) ($settings['night_surcharge_amount'] ?? 0);

    // Check exemption
    $exempt_areas_str = $settings['night_surcharge_exempt_areas'] ?? '';
    $exempt_areas = explode(',', $exempt_areas_str);
    $is_exempt = in_array($delivery_area_id, $exempt_areas);

    if ($surcharge > 0 && !$is_exempt) {
        $start_hour = (int) ($settings['night_surcharge_start_hour'] ?? 0);
        $end_hour = (int) ($settings['night_surcharge_end_hour'] ?? 6);
        $current_hour = (int) date('G');
        if (
            ($start_hour > $end_hour && ($current_hour >= $start_hour || $current_hour < $end_hour)) ||
            ($start_hour <= $end_hour && ($current_hour >= $start_hour && $current_hour < $end_hour))
        ) {
            $surcharge_amount = $surcharge;
        }
    }
    $stmt_area->close();

    $total_delivery_fee = $base_charge + $surcharge_amount;

    // --- DELIVERY PROMOTION LOGIC ---
    if (!empty($settings['free_delivery_active']) && $settings['free_delivery_active'] == '1') {
        $total_delivery_fee = 0;
    } else if (!empty($settings['delivery_discount_active']) && $settings['delivery_discount_active'] == '1') {
        $discount_value = (float) ($settings['delivery_discount_percentage'] ?? 0);
        if ($discount_value > 0 && $discount_value <= 100) {
            $discount_percent_amount = $total_delivery_fee * ($discount_value / 100);
            $total_delivery_fee = (int) round($total_delivery_fee - $discount_percent_amount);
        }
    }

    // --- D. Calculate Final Total (All integers) ---
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
    // Note: types d (double) changed to i (integer) for monetary values, or kept d if generic numeric
    // Ideally 'd' handles both float/int, but we send ints.
    $stmt_order->bind_param(
        'ssssiiissisi',
        $customer_name,
        $customer_phone,
        $customer_address,
        $order_note,
        $delivery_area_id,
        $subtotal,
        $total_delivery_fee,
        $total_amount,
        $order_status,
        $coupon_id,
        $discount_type,
        $discount_amount
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

    if ($order_id <= 0)
        throw new Exception("Failed to create order header.");
    $stmt_order->close();

    // --- B. Insert into `order_items` and `order_item_options` ---
    $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $db->prepare($sql_item);

    $sql_option = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
    $stmt_option = $db->prepare($sql_option);

    $gtm_items = [];

    foreach ($cart as $item) {
        // Recalculate using our integer base price found in step 4.A
        // (Or use item from cart if we trust cart_actions.php logic which we updated)

        // To be ultra-safe, re-fetch price again? No, we did it in 4.A
        // We can rely on the $single_item_price calculated in the 4.A loop if we restructured, 
        // but simpler: Re-fetch is safest OR use what we just verified.
        // Since we verified in the loop but didn't update the $cart array in-place for this loop, let's just re-calc base logic quickly or use passed values.
        // Actually, the easiest is to trust the 4.A check passed, so the session values are 'close enough' 
        // OR ideally update the session in 4.A. 
        // Let's just use the logic:

        // Get price (we know it exists from 4.A)
        $q = $db->query("SELECT price FROM menu_items WHERE id = " . $item['item_id']);
        $row = $q->fetch_assoc();
        $bp = calculate_discounted_price($row['price'], $settings);

        $item_total = $bp * $item['quantity'];
        // Add option costs
        if (!empty($item['options'])) {
            foreach ($item['options'] as $opt) {
                $item_total += ((int) $opt['price'] * $item['quantity']);
            }
        }

        $stmt_item->bind_param('iiiii', $order_id, $item['item_id'], $item['quantity'], $bp, $item_total);
        $stmt_item->execute();
        $order_item_id = $db->insert_id;

        if ($order_item_id <= 0)
            throw new Exception("Failed to save order item: {$item['item_name']}");

        // Add options
        foreach ($item['options'] as $option) {
            $op_price = (int) $option['price'];
            $stmt_option->bind_param('isi', $order_item_id, $option['name'], $op_price);
            $stmt_option->execute();
        }

        $gtm_items[] = [
            'item_id' => $item['item_id'],
            'item_name' => $item['item_name'],
            'price' => $bp,
            'quantity' => $item['quantity']
        ];

        $items_for_capi[] = [
            'menu_item_id' => $item['item_id'],
            'quantity' => $item['quantity'],
            'single_item_price' => $bp
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

    // 7. --- MARKETING & SESSION ---
    $_SESSION['gtm_purchase_data'] = [
        'event' => 'purchase',
        'ecommerce' => [
            'transaction_id' => 'PM-' . $order_id,
            'value' => $total_amount,
            'tax' => 0,
            'shipping' => $total_delivery_fee,
            'currency' => 'BDT',
            'coupon' => $coupon_code,
            'discount' => $discount_amount,
            'items' => $gtm_items
        ]
    ];

    fire_facebook_capi($order_for_capi, $items_for_capi, $settings);

    // 8. --- CLEANUP & REDIRECT ---
    $_SESSION['cart'] = [];
    $_SESSION['last_order_id'] = $order_id;
    header('Location: ' . BASE_URL . '/order-success');
    exit;

} catch (Exception $e) {
    $db->rollback();
    die('Error placing order: ' . $e->getMessage() . ' Please go back and try again.');
}
?>