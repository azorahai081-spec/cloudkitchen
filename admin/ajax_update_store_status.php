<?php
/*
 * admin/ajax_update_store_status.php
 * KitchCo: Cloud Kitchen AJAX Helper
 * Version 1.1 - Added Output Buffering to ensure clean JSON
 *
 * This file is called by JavaScript from live_orders.php.
 * It updates the 'store_is_open' setting in the database.
 */

// 1. CONFIGURATION
// Start output buffering to catch any stray warnings/errors before JSON header
ob_start();
require_once('../config.php');
header('Content-Type: application/json');

// 2. SECURITY CHECK (Admin Only)
if (!isset($_SESSION['user_id']) || !hasAdminAccess()) {
    // Clear buffer before sending JSON response
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access Denied']);
    exit;
}

// 3. GET DATA
// We expect a JSON payload
$data = json_decode(file_get_contents('php://input'), true);
$new_status = isset($data['store_is_open']) && $data['store_is_open'] ? '1' : '0';

// 4. VALIDATE CSRF TOKEN
// We pass the token in a custom header
$token_header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $token_header)) {
    // Clear buffer before sending JSON response
    ob_end_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session token. Please refresh the page.']);
    exit;
}

// 5. UPDATE DATABASE
try {
    $sql = "UPDATE site_settings SET setting_value = ? WHERE setting_key = 'store_is_open'";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('s', $new_status);
    $stmt->execute();
    
    // 6. SEND JSON RESPONSE (Success)
    // Clear buffer and send final JSON
    ob_end_clean();
    echo json_encode([
        'success' => true, 
        'new_status_text' => $new_status == '1' ? 'Open' : 'Closed'
    ]);
    $stmt->close();

} catch (Exception $e) {
    // 6. SEND JSON RESPONSE (Error)
    // Clear buffer and send error JSON
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>