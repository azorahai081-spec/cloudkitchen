<?php
/*
 * ajax_apply_coupon.php
 * KitchCo: Cloud Kitchen Coupon AJAX Helper
 *
 * This file is called by checkout.php and cart.php.
 * It validates a coupon code and returns the discount amount.
 */

// 1. CONFIGURATION
require_once('config.php');
header('Content-Type: application/json');

// 2. INITIALIZE
$response = [
    'success' => false,
    'message' => '',
    'discount_amount' => 0
];

try {
    // 3. VALIDATE CSRF
    if (!validate_csrf_token()) {
        throw new Exception('Invalid session. Please refresh the page.');
    }

    // 4. GET INPUT
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    $subtotal = (float)($_POST['subtotal'] ?? 0);

    if (empty($coupon_code)) {
        throw new Exception('Please enter a coupon code.');
    }
    if ($subtotal <= 0) {
        throw new Exception('Your cart is empty.');
    }

    // 5. QUERY DATABASE
    $sql = "SELECT * FROM coupons WHERE code = ? LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $coupon_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        throw new Exception('Invalid coupon code.');
    }

    $coupon = $result->fetch_assoc();

    // 6. VALIDATION CHECKS
    if (!$coupon['is_active']) {
        throw new Exception('This coupon is no longer active.');
    }
    if ($coupon['current_uses'] >= $coupon['max_uses']) {
        throw new Exception('This coupon has reached its maximum usage limit.');
    }
    
    $now = time();
    $start_date = strtotime($coupon['start_date']);
    $end_date = strtotime($coupon['end_date']);

    if ($now < $start_date) {
        throw new Exception('This coupon is not yet valid.');
    }
    if ($now > $end_date) {
        throw new Exception('This coupon has expired.');
    }
    if ($subtotal < $coupon['min_order_amount']) {
        throw new Exception('A minimum order of ' . number_format($coupon['min_order_amount'], 2) . ' BDT is required.');
    }

    // 7. CALCULATE DISCOUNT
    $discount_amount = 0;
    if ($coupon['type'] == 'percentage') {
        $discount_amount = $subtotal * ($coupon['value'] / 100);
    } else {
        $discount_amount = $coupon['value'];
    }
    
    // Ensure discount doesn't exceed subtotal
    if ($discount_amount > $subtotal) {
        $discount_amount = $subtotal;
    }

    // 8. SUCCESS RESPONSE
    $response['success'] = true;
    $response['message'] = 'Coupon applied successfully!';
    $response['discount_amount'] = (float)number_format($discount_amount, 2, '.', ''); // Format to 2 decimal places

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

// 9. SEND JSON
echo json_encode($response);
?>