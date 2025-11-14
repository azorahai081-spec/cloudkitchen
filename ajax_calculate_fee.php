<?php
/*
 * ajax_calculate_fee.php
 * KitchCo: Cloud Kitchen Fee Calculator AJAX Helper
 * Version 1.0
 *
 * This file is called by checkout.php.
 * It takes a delivery_area_id, checks for night surcharges,
 * and returns the final delivery fee as JSON.
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
    $base_charge = (float)$area_data['base_charge'];
    $surcharge_amount = 0;

    // 4. --- CALCULATE NIGHT SURCHARGE ---
    // Settings are loaded from config.php
    $enable_surcharge = true; // You could add a 'enable_surcharge' setting
    $surcharge = (float)($settings['night_surcharge_amount'] ?? 0);
    
    if ($enable_surcharge && $surcharge > 0) {
        // We use the timezone set in config.php
        $start_hour = (int)($settings['night_surcharge_start_hour'] ?? 0); // e.g., 22 (10 PM)
        $end_hour = (int)($settings['night_surcharge_end_hour'] ?? 6); // e.g., 6 (6 AM)
        $current_hour = (int)date('G'); // Get current hour (0-23)
        
        $is_surcharge_time = false;
        
        if ($start_hour > $end_hour) {
            // This is an overnight period (e.g., 22:00 to 06:00)
            if ($current_hour >= $start_hour || $current_hour < $end_hour) {
                $is_surcharge_time = true;
            }
        } else {
            // This is a same-day period (e.g., 00:00 to 06:00)
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