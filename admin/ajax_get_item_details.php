<?php
/*
 * admin/ajax_get_item_details.php
 * KitchCo: Cloud Kitchen AJAX Helper
 * Version 1.0
 *
 * This file is called by JavaScript (fetch) from manual_order.php.
 * It does not load any HTML.
 * It takes a menu item ID, fetches its associated option groups and options,
 * and returns them as a JSON object.
 */

// 1. HEADER (minimal)
// We just need the DB connection and session (for security)
require_once('../config.php');

// 2. SECURITY CHECK
// Only logged-in users can access this data
if (!isset($_SESSION['user_id'])) {
    // Send a 403 Forbidden error if not logged in
    http_response_code(403);
    echo json_encode(['error' => 'Access Denied']);
    exit;
}

// 3. SET CONTENT TYPE TO JSON
// Tell the browser we are sending back JSON, not HTML
header('Content-Type: application/json');

// 4. GET THE ITEM ID
$item_id = $_GET['id'] ?? null;

if (empty($item_id)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'No item ID provided']);
    exit;
}

// 5. PREPARE THE DATA STRUCTURE
$response_data = [
    'item_id' => $item_id,
    'option_groups' => []
];

try {
    // --- QUERY 1: Get all OPTION GROUPS linked to this item ---
    // We join 3 tables:
    // 1. menu_item_options_groups (the link table)
    // 2. item_options_groups (to get the group name, e.g., "Size")
    // 3. menu_items (to make sure we are on the right item)
    
    $sql_groups = "SELECT 
                       g.id, 
                       g.name, 
                       g.type
                   FROM item_options_groups g
                   JOIN menu_item_options_groups mig ON g.id = mig.option_group_id
                   WHERE mig.menu_item_id = ?
                   ORDER BY g.name";
                   
    $stmt_groups = $db->prepare($sql_groups);
    $stmt_groups->bind_param('i', $item_id);
    $stmt_groups->execute();
    $result_groups = $stmt_groups->get_result();
    
    // --- PREPARE QUERY 2: Get all OPTIONS for a specific group ---
    // We will run this inside the loop below
    $sql_options = "SELECT 
                        id, 
                        name, 
                        price_increase 
                    FROM item_options
                    WHERE group_id = ?
                    ORDER BY name";
    $stmt_options = $db->prepare($sql_options);

    // --- LOOP 1: Process each group ---
    while ($group_row = $result_groups->fetch_assoc()) {
        $group_id = $group_row['id'];
        
        $current_group = [
            'id' => $group_id,
            'name' => $group_row['name'],
            'type' => $group_row['type'],
            'options' => []
        ];

        // --- LOOP 2: Fetch all options for this specific group ---
        $stmt_options->bind_param('i', $group_id);
        $stmt_options->execute();
        $result_options = $stmt_options->get_result();
        
        while ($option_row = $result_options->fetch_assoc()) {
            $current_group['options'][] = [
                'id' => $option_row['id'],
                'name' => $option_row['name'],
                'price_increase' => $option_row['price_increase']
            ];
        }
        
        // Add this fully populated group to our response
        $response_data['option_groups'][] = $current_group;
    }
    
    // 6. CLOSE STATEMENTS
    $stmt_groups->close();
    $stmt_options->close();
    $db->close();

    // 7. SEND THE JSON RESPONSE
    echo json_encode($response_data);
    exit;

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database query failed.', 'details' => $e->getMessage()]);
    exit;
}

?>