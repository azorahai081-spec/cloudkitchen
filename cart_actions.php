<?php
/*
 * cart_actions.php
 * KitchCo: Cloud Kitchen Cart AJAX Handler
 * Version 1.1 - Hardened "Remove" action to use POST
 *
 * This file handles all cart modifications (add, update, remove).
 * It is called via AJAX, validates data, updates the session,
 * and returns a JSON response.
 */

// 1. CONFIGURATION
// Start session, connect to DB
require_once('config.php');
header('Content-Type: application/json');

// 2. INITIALIZE SESSION CART
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 3. GET ACTION
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    switch ($action) {
        // --- ACTION: ADD TO CART ---
        case 'add':
            if ($settings['store_is_open'] == '0') {
                throw new Exception('Sorry, the store is currently closed.');
            }
            
            $item_id = $_POST['item_id'] ?? 0;
            $quantity = $_POST['quantity'] ?? 1;
            $option_ids = $_POST['options'] ?? []; // Array of item_options IDs

            if ($item_id <= 0 || $quantity <= 0) {
                throw new Exception('Invalid item data.');
            }

            // --- SERVER-SIDE VALIDATION ---
            // A. Get base item price
            $stmt_item = $db->prepare("SELECT name, price FROM menu_items WHERE id = ? AND is_available = 1");
            $stmt_item->bind_param('i', $item_id);
            $stmt_item->execute();
            $result_item = $stmt_item->get_result();
            if ($result_item->num_rows == 0) {
                throw new Exception('This item is not available.');
            }
            $item_data = $result_item->fetch_assoc();
            $base_price = $item_data['price'];
            $item_name = $item_data['name'];

            // B. Get options and their prices
            $options_price = 0;
            $options_desc = [];
            if (!empty($option_ids)) {
                // Create placeholders (?, ?, ?)
                $placeholders = implode(',', array_fill(0, count($option_ids), '?'));
                // Create types string (e.g., "iii")
                $types = str_repeat('i', count($option_ids));
                
                $sql_opt = "SELECT name, price_increase FROM item_options WHERE id IN ($placeholders)";
                $stmt_opt = $db->prepare($sql_opt);
                $stmt_opt->bind_param($types, ...$option_ids);
                $stmt_opt->execute();
                $result_opt = $stmt_opt->get_result();
                
                while ($row = $result_opt->fetch_assoc()) {
                    $options_price += $row['price_increase'];
                    $options_desc[] = [
                        'name' => $row['name'],
                        'price' => $row['price_increase']
                    ];
                }
            }
            // --- END VALIDATION ---
            
            // Calculate final price for a single item
            $single_item_price = $base_price + $options_price;

            // Create a unique key for this item configuration
            // This stacks items (e.g., 2x "Large Coke")
            $option_key = implode('-', $option_ids);
            $cart_key = $item_id . '_' . md5($option_key);

            // Add or update in cart
            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cart_key] = [
                    'item_id' => $item_id,
                    'item_name' => $item_name,
                    'quantity' => (int)$quantity,
                    'base_price' => (float)$base_price,
                    'options' => $options_desc, // Store text description
                    'single_item_price' => (float)$single_item_price,
                ];
            }
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'Item added to cart!',
                'cart_count' => get_cart_count_from_session()
            ]);
            break;

        // --- ACTION: UPDATE QUANTITY ---
        case 'update':
            $cart_key = $_POST['cart_key'] ?? '';
            $quantity = $_POST['quantity'] ?? 1;

            if ($quantity <= 0) {
                // If quantity is 0 or less, remove it
                unset($_SESSION['cart'][$cart_key]);
            } elseif (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] = (int)$quantity;
            }
            
            // Redirect back to cart page
            header('Location: cart.php');
            exit;
            
        // --- ACTION: REMOVE ITEM ---
        case 'remove':
            // (FIXED) Changed from $_GET to $_POST
            $cart_key = $_POST['cart_key'] ?? ''; 
            if (isset($_SESSION['cart'][$cart_key])) {
                unset($_SESSION['cart'][$cart_key]);
            }
            
            // Redirect back to cart page
            header('Location: cart.php');
            exit;

        default:
            throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Helper function to count cart items directly from session
 */
function get_cart_count_from_session() {
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}
?>