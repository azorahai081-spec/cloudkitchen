<?php
/*
 * admin/ajax_check_new_orders.php
 * KitchCo: Cloud Kitchen AJAX Helper for Live Orders
 * Version 1.0
 *
 * This file is called by live_orders.php every 15 seconds.
 * It returns JSON data about:
 * 1. Any 'Pending' orders newer than the 'last_id' provided.
 * 2. Any orders that changed status (e.g., 'Pending' -> 'Preparing').
 * 3. The total count of 'Pending' and 'Preparing' orders.
 */

// 1. CONFIGURATION
require_once('../config.php');
header('Content-Type: application/json');

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

// 3. GET LAST KNOWN ID
$last_id = (int)($_GET['last_id'] ?? 0);

// 4. PREPARE RESPONSE
$response = [
    'success' => true,
    'new_orders' => [],
    'updated_orders' => [],
    'pending_count' => 0,
    'preparing_count' => 0
];

try {
    // --- A. Get NEW 'Pending' orders ---
    // We fetch any orders with an ID greater than the last one the page saw
    $sql_new = "SELECT * FROM orders 
                WHERE order_status = 'Pending' AND order_id > ?
                ORDER BY order_time ASC";
    $stmt_new = $db->prepare($sql_new);
    $stmt_new->bind_param('i', $last_id);
    $stmt_new->execute();
    $result_new = $stmt_new->get_result();
    
    while ($row = $result_new->fetch_assoc()) {
        $response['new_orders'][] = $row;
    }
    
    // --- B. Get UPDATED orders (that moved from Pending to Preparing) ---
    // This is a failsafe in case another admin accepts an order on a different computer.
    // It checks for orders that are 'Preparing' but have an ID *less than* our last_id
    // (meaning the page *should* have seen it, but as 'Pending').
    $sql_updated = "SELECT * FROM orders 
                    WHERE order_status = 'Preparing' AND order_id <= ?
                    ORDER BY order_time ASC";
    $stmt_updated = $db->prepare($sql_updated);
    $stmt_updated->bind_param('i', $last_id);
    $stmt_updated->execute();
    $result_updated = $stmt_updated->get_result();
    
    while ($row = $result_updated->fetch_assoc()) {
        $response['updated_orders'][] = $row;
    }
    
    // --- C. Get total counts for stats ---
    $result_counts = $db->query("SELECT order_status, COUNT(*) as count 
                                 FROM orders 
                                 WHERE order_status IN ('Pending', 'Preparing') 
                                 GROUP BY order_status");
    
    if ($result_counts) {
        while ($row = $result_counts->fetch_assoc()) {
            if ($row['order_status'] == 'Pending') {
                $response['pending_count'] = (int)$row['count'];
            }
            if ($row['order_status'] == 'Preparing') {
                $response['preparing_count'] = (int)$row['count'];
            }
        }
    }

    // 5. SEND JSON RESPONSE
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>