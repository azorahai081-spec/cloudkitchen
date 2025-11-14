<?php
/*
 * includes/fb_capi.php
 * KitchCo: Facebook Conversion API (CAPI) Helper
 * Version 1.0
 *
 * This file contains the function to send server-side purchase events
 * to Facebook after an order is successfully placed.
 */

/**
 * Sends a 'Purchase' event to the Facebook Conversions API.
 *
 * @param array $order - The order details from the 'orders' table.
 * @param array $items - The list of items from the 'order_items' table.
 * @param array $settings - The global $settings array containing API keys.
 */
function fire_facebook_capi($order, $items, $settings) {
    
    // 1. --- Check for required keys ---
    $pixel_id = $settings['fb_pixel_id'] ?? '';
    $access_token = $settings['fb_capi_token'] ?? '';
    
    if (empty($pixel_id) || empty($access_token)) {
        // Not configured, so we just return silently
        return;
    }

    // 2. --- Build User Data ---
    // We hash the data as required by Facebook
    $user_data = [
        'ph' => [hash('sha256', $order['customer_phone'])],
        'fn' => [hash('sha256', $order['customer_name'])],
        'client_ip_address' => $_SERVER['REMOTE_ADDR'],
        'client_user_agent' => $_SERVER['HTTP_USER_AGENT'],
    ];

    // 3. --- Build Contents (Items) ---
    $contents = [];
    foreach ($items as $item) {
        $contents[] = [
            'id' => $item['menu_item_id'],
            'quantity' => $item['quantity'],
            'item_price' => $item['single_item_price'], // Price of one unit
        ];
    }

    // 4. --- Build Custom Data (Order Details) ---
    $custom_data = [
        'value' => $order['total_amount'],
        'currency' => 'BDT',
        'contents' => $contents,
        'order_id' => 'KCO-' . $order['order_id'], // Add prefix to make it unique
    ];

    // 5. --- Build Final Payload ---
    $event_id = 'order_' . $order['order_id']; // Unique event ID
    
    $payload = [
        'data' => [
            [
                'event_name' => 'Purchase',
                'event_time' => time(),
                'event_id' => $event_id,
                'event_source_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/order_success.php',
                'action_source' => 'website',
                'user_data' => $user_data,
                'custom_data' => $custom_data,
            ]
        ],
    ];

    // 6. --- Add Test Code if present ---
    $test_code = $settings['fb_capi_test_code'] ?? '';
    if (!empty($test_code)) {
        $payload['test_event_code'] = $test_code;
    }

    // 7. --- Send with cURL ---
    $url = "https://graph.facebook.com/v15.0/{$pixel_id}/events?access_token={$access_token}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 second timeout

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // 8. --- (Optional) Log the response ---
    // In a real app, you would log this to a file, not output it.
    // file_put_contents('capi_log.txt', "Event: $event_id, Status: $http_code, Response: $response\n", FILE_APPEND);
}
?>