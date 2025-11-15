<?php
/*
 * admin/ajax_check_new_orders.php
 * KitchCo: Cloud Kitchen AJAX Helper for Live Orders
 * Version 2.2 - (MODIFIED) Added "Ready" status and LIMIT 5
 *
 * This file is called by live_orders.php every 15 seconds.
 * It returns JSON data with two complete lists:
 * 1. All orders with 'Pending' status.
 * 2. All orders with 'Preparing' status.
 */

// 1. CONFIGURATION
ob_start();
require_once('../config.php');
header('Content-Type: application/json');

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

// 3. PREPARE RESPONSE
$response = [
    'success' => true,
    'pending_orders' => [],
    'preparing_orders' => [],
    'ready_orders' => [], // (NEW)
    'pending_count' => 0,
    'preparing_count' => 0,
    'ready_count' => 0 // (NEW)
];

try {
    // --- A. Get TOP 5 'Pending' orders ---
    // (MODIFIED) Added LIMIT 5
    $sql_pending = "SELECT * FROM orders 
                    WHERE order_status = 'Pending'
                    ORDER BY order_time DESC LIMIT 5";
    $result_pending = $db->query($sql_pending);
    
    if ($result_pending) {
        while ($row = $result_pending->fetch_assoc()) {
            $response['pending_orders'][] = $row;
        }
    }
    
    // --- B. Get TOP 5 'Preparing' orders ---
    // (MODIFIED) Added LIMIT 5
    $sql_preparing = "SELECT * FROM orders 
                      WHERE order_status = 'Preparing'
                      ORDER BY order_time DESC LIMIT 5";
    $result_preparing = $db->query($sql_preparing);
    
    if ($result_preparing) {
        while ($row = $result_preparing->fetch_assoc()) {
            $response['preparing_orders'][] = $row;
        }
    }

    // --- C. (NEW) Get TOP 5 'Ready' orders ---
    $sql_ready = "SELECT * FROM orders 
                  WHERE order_status = 'Ready'
                  ORDER BY order_time DESC LIMIT 5";
    $result_ready = $db->query($sql_ready);
    
    if ($result_ready) {
        while ($row = $result_ready->fetch_assoc()) {
            $response['ready_orders'][] = $row;
        }
    }
    
    // --- D. Get counts (We count ALL orders for the stats, not just the top 5) ---
    $result_counts = $db->query("SELECT order_status, COUNT(*) as count 
                                 FROM orders 
                                 WHERE order_status IN ('Pending', 'Preparing', 'Ready') 
                                 GROUP BY order_status");
    
    if ($result_counts) {
        while ($row = $result_counts->fetch_assoc()) {
            if ($row['order_status'] == 'Pending') {
                $response['pending_count'] = (int)$row['count'];
            }
            if ($row['order_status'] == 'Preparing') {
                $response['preparing_count'] = (int)$row['count'];
            }
            if ($row['order_status'] == 'Ready') {
                $response['ready_count'] = (int)$row['count'];
            }
        }
    }

    // 5. SEND JSON RESPONSE
    ob_end_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>