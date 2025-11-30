<?php
/*
 * cart_actions.php
 * PizzaMania: Cloud Kitchen Cart AJAX Handler
 * Version 1.6 - (MODIFIED) Returns Cart Total for Floating Bar
 *
 * This file handles all cart modifications (add, update, remove).
 */

// 1. CONFIGURATION
require_once('config.php');
header('Content-Type: application/json');

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

    // Round to nearest whole number and ensure it's at least 0
    return max(0, (int) round($new_price));
}

// 2. INITIALIZE SESSION CART
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// 3. GET ACTION
$action = $_POST['action'] ?? $_GET['action'] ?? null;

try {
    // CSRF Check
    if ($action === 'add' || $action === 'update' || $action === 'remove') {
        if (!validate_csrf_token()) {
            if ($action === 'add') {
                throw new Exception('Invalid session. Please refresh the page and try again.');
            } else {
                header('Location: cart.php');
                exit;
            }
        }
    }

    switch ($action) {
        // --- ACTION: ADD TO CART ---
        case 'add':
            if ($settings['store_is_open'] == '0') {
                throw new Exception('Sorry, the store is currently closed.');
            }

            $item_id = $_POST['item_id'] ?? 0;
            $quantity = $_POST['quantity'] ?? 1;
            $option_ids = $_POST['options'] ?? [];

            if ($item_id <= 0 || $quantity <= 0) {
                throw new Exception('Invalid item data.');
            }

            // --- SERVER-SIDE VALIDATION ---
            $stmt_item = $db->prepare("SELECT name, price, image FROM menu_items WHERE id = ? AND is_available = 1");
            $stmt_item->bind_param('i', $item_id);
            $stmt_item->execute();
            $result_item = $stmt_item->get_result();
            if ($result_item->num_rows == 0) {
                throw new Exception('This item is not available.');
            }
            $item_data = $result_item->fetch_assoc();

            // (MODIFIED) Apply global discount & Cast to Int
            $original_base_price = $item_data['price'];
            $base_price = calculate_discounted_price($original_base_price, $settings);
            $item_name = $item_data['name'];
            $item_image = $item_data['image'];

            // B. Get options and their prices
            $options_price = 0;
            $options_desc = [];
            if (!empty($option_ids)) {
                $placeholders = implode(',', array_fill(0, count($option_ids), '?'));
                $types = str_repeat('i', count($option_ids));

                $sql_opt = "SELECT name, price_increase FROM item_options WHERE id IN ($placeholders)";
                $stmt_opt = $db->prepare($sql_opt);
                $stmt_opt->bind_param($types, ...$option_ids);
                $stmt_opt->execute();
                $result_opt = $stmt_opt->get_result();

                while ($row = $result_opt->fetch_assoc()) {
                    // (MODIFIED) Cast option price to int
                    $opt_price = (int) $row['price_increase'];
                    $options_price += $opt_price;
                    $options_desc[] = [
                        'name' => $row['name'],
                        'price' => $opt_price
                    ];
                }
            }
            // --- END VALIDATION ---

            // (MODIFIED) Calculate final integer price
            $single_item_price = $base_price + $options_price;

            // Create a unique key
            $option_key = implode('-', $option_ids);
            $cart_key = $item_id . '_' . md5($option_key);

            // Add or update in cart
            if (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$cart_key] = [
                    'item_id' => $item_id,
                    'item_name' => $item_name,
                    'image' => $item_image,
                    'quantity' => (int) $quantity,
                    'base_price' => (int) $base_price, // Store as int
                    'options' => $options_desc,
                    'single_item_price' => (int) $single_item_price, // Store as int
                ];
            }

            echo json_encode([
                'success' => true,
                'message' => 'Item added to cart!',
                'cart_count' => get_cart_count_from_session(),
                'cart_total' => get_cart_total_from_session() // (NEW) Return total for floating bar
            ]);
            break;

        // --- ACTION: UPDATE QUANTITY ---
        case 'update':
            $cart_key = $_POST['cart_key'] ?? '';
            $quantity = $_POST['quantity'] ?? 1;

            if ($quantity <= 0) {
                unset($_SESSION['cart'][$cart_key]);
            } elseif (isset($_SESSION['cart'][$cart_key])) {
                $_SESSION['cart'][$cart_key]['quantity'] = (int) $quantity;
            }

            header('Location: cart.php');
            exit;

        // --- ACTION: REMOVE ITEM ---
        case 'remove':
            $cart_key = $_POST['cart_key'] ?? '';
            if (isset($_SESSION['cart'][$cart_key])) {
                unset($_SESSION['cart'][$cart_key]);
            }

            header('Location: cart.php');
            exit;

        default:
            throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function get_cart_count_from_session()
{
    $count = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $count += $item['quantity'];
        }
    }
    return $count;
}

// (NEW) Helper to calculate total
function get_cart_total_from_session()
{
    $total = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $total += $item['single_item_price'] * $item['quantity'];
        }
    }
    return $total;
}
?>