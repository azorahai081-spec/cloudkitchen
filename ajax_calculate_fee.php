<?php
/*
 * ajax_calculate_fee.php
 * KitchCo: Cloud Kitchen Fee Calculator AJAX Helper
 * Version 1.3 - (MODIFIED) Integers Only for BDT
 *
 * This file calculates the delivery fee.
 */

// 1. CONFIGURATION
require_once('config.php');
header('Content-Type: application/json');

// 2. GET INPUT
$area_id = $_GET['area_id'] ?? 0;

if (empty($area_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid delivery area.']);
    exit;
}

try {
    // 3. --- GET BASE DELIVERY FEE ---
    $stmt = $db->prepare("SELECT base_charge FROM delivery_areas WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        throw new Exception('Selected delivery area is not available.');
    }
    
    $area_data = $result->fetch_assoc();
    // (MODIFIED) Cast to int
    $base_charge = (int)$area_data['base_charge'];
    $surcharge_amount = 0;

    // 4. --- CALCULATE NIGHT SURCHARGE ---
    $enable_surcharge = true; 
    // (MODIFIED) Cast to int
    $surcharge = (int)($settings['night_surcharge_amount'] ?? 0);
    
    // Check exemption list
    $exempt_areas_str = $settings['night_surcharge_exempt_areas'] ?? '';
    $exempt_areas = explode(',', $exempt_areas_str);
    $is_exempt = in_array($area_id, $exempt_areas);
    
    if ($enable_surcharge && $surcharge > 0 && !$is_exempt) {
        $start_hour = (int)($settings['night_surcharge_start_hour'] ?? 0);
        $end_hour = (int)($settings['night_surcharge_end_hour'] ?? 6);
        $current_hour = (int)date('G');
        
        $is_surcharge_time = false;
        
        if ($start_hour > $end_hour) {
            if ($current_hour >= $start_hour || $current_hour < $end_hour) {
                $is_surcharge_time = true;
            }
        } else {
            if ($current_hour >= $start_hour && $current_hour < $end_hour) {
                $is_surcharge_time = true;
            }
        }
        
        if ($is_surcharge_time) {
            $surcharge_amount = $surcharge;
        }
    }
    
    // 5. --- PREPARE RESPONSE ---
    $total_delivery_fee = $base_charge + $surcharge_amount;

    // --- DELIVERY PROMOTION LOGIC ---
    if (!empty($settings['free_delivery_active']) && $settings['free_delivery_active'] == '1') {
        $total_delivery_fee = 0;
        $surcharge_amount = 0;
    } 
    else if (!empty($settings['delivery_discount_active']) && $settings['delivery_discount_active'] == '1') {
        $discount_value = (float)($settings['delivery_discount_percentage'] ?? 0);
        
        if ($discount_value > 0 && $discount_value <= 100) { 
            $discount_amount = $total_delivery_fee * ($discount_value / 100);
            // (MODIFIED) Round to nearest int
            $total_delivery_fee = (int)round($total_delivery_fee - $discount_amount);
        }
    }
    
    echo json_encode([
        'success' => true,
        'base_charge' => $base_charge,
        'surcharge_amount' => $surcharge_amount,
        'total_delivery_fee' => $total_delivery_fee
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>