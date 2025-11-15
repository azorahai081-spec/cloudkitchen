<?php
/*
 * admin/edit_order.php
 * KitchCo: Cloud Kitchen Order Editor
 * Version 1.2 - (FIXED) Reloads data after save, fixes new item option ID bug.
 *
 * This page loads an existing order into the manual order interface
 * and allows an admin to modify and re-save it.
 * Based on admin/manual_order.php
 */

// 1. HEADER
require_once('header.php');

// (NEW) Helper function to apply global discount
function calculate_discounted_price($original_price, $settings) {
    if (empty($settings['global_discount_active']) || $settings['global_discount_active'] == '0' || empty($settings['global_discount_value']) || $settings['global_discount_value'] <= 0) {
        return $original_price;
    }

    $discount_type = $settings['global_discount_type'];
    $discount_value = (float)$settings['global_discount_value'];
    $new_price = $original_price;

    if ($discount_type == 'percentage') {
        $new_price = $original_price - ($original_price * ($discount_value / 100));
    } elseif ($discount_type == 'fixed') {
        $new_price = $original_price - $discount_value;
    }
    
    // Don't let price go below 0
    return ($new_price > 0) ? $new_price : 0;
}


// 2. PAGE VARIABLES & INITIALIZATION
$page_title = 'Edit Order';
$error_message = '';
$success_message = '';
$cart_for_js = []; // (NEW) To pre-populate the cart

// (NEW) --- GET ORDER ID & CHECK ACCESS ---
$order_id = $_GET['id'] ?? null;
if (empty($order_id)) {
    header('Location: manage_orders.php');
    exit;
}
$order_id = (int)$order_id;
$page_title = "Edit Order #PM-{$order_id}";


// 3. --- HANDLE POST REQUEST (UPDATE the Order) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // --- A. GET ORDER ID FROM POST ---
        $order_id_to_update = (int)$_POST['order_id'];
        if (empty($order_id_to_update)) {
            $error_message = 'Order ID missing. Cannot save changes.';
        }
        
        // --- B. GET CUSTOMER DATA ---
        $customer_name = $_POST['customer_name'];
        $customer_phone = $_POST['customer_phone'];
        $customer_address = $_POST['customer_address'];
        $delivery_area_id = (int)$_POST['delivery_area_id'];
        
        // --- C. GET DISCOUNT DATA ---
        $discount_type = $_POST['discount_type'] ?? 'none';
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $final_discount_amount = 0;
        
        // --- D. GET CART DATA ---
        $cart_json = $_POST['cart_data'];
        $cart_items = json_decode($cart_json, true);
        
        // --- E. VALIDATION ---
        if (empty($customer_name) || empty($customer_phone) || empty($delivery_area_id)) {
            $error_message = 'Customer Name, Phone, and Delivery Area are required.';
        } elseif (empty($cart_items)) {
            $error_message = 'Cannot save an empty order. Please add items to the cart.';
        }
        
        if (empty($error_message)) {
            // --- F. SERVER-SIDE PRICE RE-CALCULATION ---
            $db->begin_transaction();
            
            try {
                $subtotal = 0;
                $verified_cart_for_db = []; 

                $stmt_item = $db->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmt_option = $db->prepare("SELECT id, name, price_increase FROM item_options WHERE id = ?");

                foreach ($cart_items as $item) {
                    $item_id = (int)$item['id'];
                    $quantity = (int)$item['quantity'];

                    // 1. Get base item price (and apply global discount)
                    $stmt_item->bind_param('i', $item_id);
                    $stmt_item->execute();
                    $result_item = $stmt_item->get_result();
                    if ($result_item->num_rows == 0) throw new Exception("Item ID {$item_id} not found.");
                    $db_item = $result_item->fetch_assoc();
                    $original_base_price = (float)$db_item['price'];
                    $base_price = calculate_discounted_price($original_base_price, $settings);
                    
                    // 2. Get options prices
                    $options_price = 0;
                    $verified_options_list = [];
                    if (!empty($item['options'])) {
                        foreach ($item['options'] as $option) {
                            $option_id = (int)$option['id'];
                            
                            // (FIX) Handle items just added from the modal (ID is from item_options table)
                            // vs. items loaded from DB (ID is from order_item_options table)
                            // We will look up by *name* if the ID isn't in item_options
                            
                            $stmt_option->bind_param('i', $option_id);
                            $stmt_option->execute();
                            $result_option = $stmt_option->get_result();

                            if ($result_option->num_rows == 0) {
                                // ID not found, try by name (for items loaded from DB)
                                $stmt_opt_by_name = $db->prepare("SELECT id, name, price_increase FROM item_options WHERE name = ? LIMIT 1");
                                $stmt_opt_by_name->bind_param('s', $option['name']);
                                $stmt_opt_by_name->execute();
                                $result_option = $stmt_opt_by_name->get_result();
                            }

                            if ($result_option->num_rows == 0) throw new Exception("Option {$option['name']} not found.");
                            
                            $db_option = $result_option->fetch_assoc();
                            $price_increase = (float)$db_option['price_increase'];
                            
                            $options_price += $price_increase;
                            $verified_options_list[] = [
                                'name' => $db_option['name'],
                                'price' => $price_increase
                            ];

                            if (isset($stmt_opt_by_name)) $stmt_opt_by_name->close();
                        }
                    }

                    // 3. Calculate verified totals for this item
                    $single_item_price = $base_price + $options_price;
                    $total_item_price = $single_item_price * $quantity;
                    $subtotal += $total_item_price;

                    // 4. Store for DB insertion
                    $verified_cart_for_db[] = [
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'base_price' => $base_price,
                        'total_price' => $total_item_price,
                        'options' => $verified_options_list
                    ];
                }

                // 5. Calculate Manual Discount
                if ($discount_type == 'percentage' && $discount_value > 0) {
                    $final_discount_amount = $subtotal * ($discount_value / 100);
                } elseif ($discount_type == 'fixed' && $discount_value > 0) {
                    $final_discount_amount = $discount_value;
                }
                if ($final_discount_amount > $subtotal) $final_discount_amount = $subtotal;
                $final_discount_amount = (float)number_format($final_discount_amount, 2, '.', '');

                // 6. Calculate Delivery Fee
                $stmt_area = $db->prepare("SELECT base_charge FROM delivery_areas WHERE id = ? AND is_active = 1");
                $stmt_area->bind_param('i', $delivery_area_id);
                $stmt_area->execute();
                $result_area = $stmt_area->get_result();
                if ($result_area->num_rows == 0) throw new Exception("Selected delivery area is not available.");
                
                $base_charge = (float)$result_area->fetch_assoc()['base_charge'];
                $surcharge_amount = 0;
                $surcharge = (float)($settings['night_surcharge_amount'] ?? 0);
                
                if ($surcharge > 0) {
                    // ... (surcharge logic as before) ...
                    $start_hour = (int)($settings['night_surcharge_start_hour'] ?? 0);
                    $end_hour = (int)($settings['night_surcharge_end_hour'] ?? 6);
                    $current_hour = (int)date('G');
                    if (($start_hour > $end_hour && ($current_hour >= $start_hour || $current_hour < $end_hour)) ||
                        ($start_hour <= $end_hour && ($current_hour >= $start_hour && $current_hour < $end_hour))) {
                        $surcharge_amount = $surcharge;
                    }
                }
                $total_delivery_fee = $base_charge + $surcharge_amount;
                
                // 7. Calculate Final Total
                $total_amount = ($subtotal - $final_discount_amount) + $total_delivery_fee;

                // --- G. UPDATE DATABASE (THE NEW LOGIC) ---
                
                // 1. Delete all old items for this order
                $stmt_delete_items = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt_delete_items->bind_param('i', $order_id_to_update);
                $stmt_delete_items->execute();

                // 2. Update the main `orders` table
                $sql_update_order = "UPDATE orders SET 
                                        customer_name = ?, customer_phone = ?, customer_address = ?, 
                                        delivery_area_id = ?, subtotal = ?, delivery_fee = ?, 
                                        total_amount = ?, discount_type = ?, discount_amount = ?,
                                        coupon_id = NULL 
                                    WHERE id = ?";
                $stmt_update_order = $db->prepare($sql_update_order);
                
                // (FIX) Corrected the type string from 'sssidddssdi' (11 chars) to 'sssidddsdi' (10 chars)
                $stmt_update_order->bind_param('sssidddsdi', 
                    $customer_name, $customer_phone, $customer_address, 
                    $delivery_area_id, $subtotal, $total_delivery_fee, 
                    $total_amount, $discount_type, $final_discount_amount,
                    $order_id_to_update
                ); // This was line 197
                
                $stmt_update_order->execute();

                // 3. Re-insert all items and options (same as manual_order.php)
                $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
                $stmt_item_db = $db->prepare($sql_item);
                
                $sql_option_db = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
                $stmt_option_db = $db->prepare($sql_option_db);
                
                foreach ($verified_cart_for_db as $item) {
                    $stmt_item_db->bind_param('iiidd', $order_id_to_update, $item['item_id'], $item['quantity'], $item['base_price'], $item['total_price']);
                    $stmt_item_db->execute();
                    $order_item_id = $db->insert_id;
                    
                    if ($order_item_id <= 0) throw new Exception("Failed to save order item.");
                    
                    foreach ($item['options'] as $option) {
                        $stmt_option_db->bind_param('isd', $order_item_id, $option['name'], $option['price']);
                        $stmt_option_db->execute();
                    }
                }
                
                // 4. Commit the transaction
                $db->commit();
                
                $success_message = "Order #PM-{$order_id_to_update} updated successfully!";
                
                // (!!!) NEW FIX: Re-load all data from DB to show the updated order
                // This is the same logic from the GET request block
                
                $stmt_order = $db->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt_order->bind_param('i', $order_id_to_update); // Use the ID we just updated
                $stmt_order->execute();
                $result_order = $stmt_order->get_result();
                $order = $result_order->fetch_assoc();
                
                // Re-populate all page variables
                $customer_name = $order['customer_name'];
                $customer_phone = $order['customer_phone'];
                $customer_address = $order['customer_address'];
                $delivery_area_id = $order['delivery_area_id'];
                $discount_type = $order['discount_type'];
                $discount_value = ($order['discount_type'] == 'percentage') ? 0 : $order['discount_amount'];
                if ($order['discount_type'] == 'percentage' && $order['subtotal'] > 0) {
                     $discount_value = ($order['discount_amount'] / $order['subtotal']) * 100;
                }
            
                // Re-load items and build the JS cart
                $cart_for_js = []; // Clear the old JS cart
                $sql_items = "SELECT oi.id, oi.menu_item_id, mi.name, oi.quantity, oi.base_price, oi.total_price 
                              FROM order_items oi
                              LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                              WHERE oi.order_id = ?";
                $stmt_items = $db->prepare($sql_items);
                $stmt_items->bind_param('i', $order_id_to_update);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
            
                while ($item = $result_items->fetch_assoc()) {
                    $order_item_id = $item['id'];
                    $item_options = [];
                    
                    // (FIX) We need the ID from item_options, not order_item_options, for the JS
                    $sql_options = "SELECT oio.option_name, oio.option_price, io.id 
                                    FROM order_item_options oio
                                    LEFT JOIN item_options io ON oio.option_name = io.name
                                    WHERE oio.order_item_id = ?";
                    $stmt_options = $db->prepare($sql_options);
                    $stmt_options->bind_param('i', $order_item_id);
                    $stmt_options->execute();
                    $result_options = $stmt_options->get_result();
                    
                    while ($option = $result_options->fetch_assoc()) {
                        $item_options[] = [
                            'id' => $option['id'] ?? 0, // Use the ID from item_options
                            'name' => $option['option_name'],
                            'price' => (float)$option['option_price']
                        ];
                    }
            
                    $cart_for_js[] = [
                        'id' => $item['menu_item_id'],
                        'name' => $item['name'] ?? '[Deleted Item]',
                        'basePrice' => (float)$item['base_price'],
                        'quantity' => (int)$item['quantity'],
                        'options' => $item_options,
                        'totalPrice' => (float)$item['total_price']
                    ];
                }
                // (END OF NEW FIX)
                
            } catch (Exception $e) {
                // Something went wrong, roll back
                $db->rollback();
                $error_message = 'Failed to update order: ' . $e->getMessage();
            }
        }
    }
}
// 4. --- (MODIFIED) LOAD EXISTING ORDER DATA (GET REQUEST) ---
else if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Added 'else if'
    $stmt_order = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt_order->bind_param('i', $order_id);
    $stmt_order->execute();
    $result_order = $stmt_order->get_result();

    if ($result_order->num_rows == 0) {
        $_SESSION['error_message'] = "Order #{$order_id} not found.";
        header('Location: manage_orders.php');
        exit;
    }
    
    $order = $result_order->fetch_assoc();
    
    // Pre-populate customer fields
    $customer_name = $order['customer_name'];
    $customer_phone = $order['customer_phone'];
    $customer_address = $order['customer_address'];
    $delivery_area_id = $order['delivery_area_id'];
    
    // Pre-populate discount fields
    $discount_type = $order['discount_type'];
    $discount_value = ($order['discount_type'] == 'percentage') ? 0 : $order['discount_amount']; // approximation
    if ($order['discount_type'] == 'percentage' && $order['subtotal'] > 0) {
         $discount_value = ($order['discount_amount'] / $order['subtotal']) * 100;
    }

    // Load items and build the JS cart
    $sql_items = "SELECT oi.id, oi.menu_item_id, mi.name, oi.quantity, oi.base_price, oi.total_price 
                  FROM order_items oi
                  LEFT JOIN menu_items mi ON oi.menu_item_id = mi.id
                  WHERE oi.order_id = ?";
    $stmt_items = $db->prepare($sql_items);
    $stmt_items->bind_param('i', $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    while ($item = $result_items->fetch_assoc()) {
        $order_item_id = $item['id'];
        $item_options = [];
        
        // (FIX) We need the ID from item_options, not order_item_options, for the JS
        $sql_options = "SELECT oio.option_name, oio.option_price, io.id 
                        FROM order_item_options oio
                        LEFT JOIN item_options io ON oio.option_name = io.name
                        WHERE oio.order_item_id = ?";
        $stmt_options = $db->prepare($sql_options);
        $stmt_options->bind_param('i', $order_item_id);
        $stmt_options->execute();
        $result_options = $stmt_options->get_result();
        
        while ($option = $result_options->fetch_assoc()) {
            // (FIX) Pass the *actual option ID* from item_options to the JS cart
            $item_options[] = [
                'id' => $option['id'] ?? 0, // Use the ID from item_options
                'name' => $option['option_name'],
                'price' => (float)$option['option_price']
            ];
        }

        // Rebuild cart item structure for JS
        $cart_for_js[] = [
            'id' => $item['menu_item_id'],
            'name' => $item['name'] ?? '[Deleted Item]',
            'basePrice' => (float)$item['base_price'],
            'quantity' => (int)$item['quantity'],
            'options' => $item_options,
            'totalPrice' => (float)$item['total_price']
        ];
    }
}


// 5. --- LOAD DATA FOR DISPLAY ---
// Load Delivery Areas for the dropdown
$delivery_areas = [];
$result = $db->query("SELECT * FROM delivery_areas WHERE is_active = 1 ORDER BY area_name ASC");
while ($row = $result->fetch_assoc()) {
    $delivery_areas[] = $row;
}

// Load All Menu Items for the search (we will pass this to JavaScript)
$menu_items = [];
$sql = "SELECT m.id, m.name, m.price, c.name as category_name 
        FROM menu_items m
        JOIN categories c ON m.category_id = c.id
        WHERE m.is_available = 1 
        ORDER BY m.name ASC";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
    // (NEW) Apply global discount before passing to JS
    $original_price = (float)$row['price'];
    $discounted_price = calculate_discounted_price($original_price, $settings);
    
    $row['price'] = $discounted_price; // Overwrite price with discounted one
    $row['original_price'] = $original_price;
    $row['has_discount'] = ($discounted_price < $original_price);
    
    $menu_items[] = $row;
}
?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_title); ?></h1>

<!-- Success & Error Messages -->
<?php if (!empty($success_message)): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
        <?php echo e($success_message); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">
        <?php echo e($error_message); ?>
    </div>
<?php endif; ?>


<form action="edit_order.php?id=<?php echo e($order_id); ?>" method="POST" id="manual-order-form">
    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
    <input type="hidden" name="order_id" value="<?php echo e($order_id); ?>">
    
    <!-- This hidden input will hold the JSON string of our cart -->
    <input type="hidden" name="cart_data" id="cart-data-input">
    
    <input type="hidden" id="js-subtotal" value="0">
    <input type="hidden" id="js-delivery-fee" value="0">
    <input type="hidden" id="js-discount" value="0">
    <input type="hidden" id="js-total" value="0">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Center Column: Menu & Items -->
        <div class="lg:col-span-2">
            
            <!-- Customer Details Form -->
            <div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">1. Customer Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">Customer Name *</label>
                        <input type="text" id="customer_name" name="customer_name" required
                               value="<?php echo e($customer_name); ?>"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-gray-700">Customer Phone *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required
                               value="<?php echo e($customer_phone); ?>"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="customer_address" class="block text-sm font-medium text-gray-700">Customer Address</label>
                        <textarea id="customer_address" name="customer_address" rows="2"
                                  class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($customer_address); ?></textarea>
                    </div>
                    <div>
                        <label for="delivery_area_id" class="block text-sm font-medium text-gray-700">Delivery Area *</label>
                        <select id="delivery_area_id" name="delivery_area_id" required
                                class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">-- Select Area --</option>
                            <?php foreach ($delivery_areas as $area): ?>
                                <option value="<?php echo e($area['id']); ?>" 
                                        data-charge="<?php echo e($area['base_charge']); ?>"
                                        <?php echo ($area['id'] == $delivery_area_id) ? 'selected' : ''; ?>>
                                    <?php echo e($area['area_name']); ?> (<?php echo e($area['base_charge']); ?> BDT)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Manual Discount -->
            <div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">2. Manual Discount (Optional)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="discount_type" class="block text-sm font-medium text-gray-700">Discount Type</label>
                        <select id="discount_type" name="discount_type" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="none" <?php echo ($discount_type == 'none') ? 'selected' : ''; ?>>None</option>
                            <option value="fixed" <?php echo ($discount_type == 'fixed') ? 'selected' : ''; ?>>Fixed (BDT)</option>
                            <option value="percentage" <?php echo ($discount_type == 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="discount_value" class="block text-sm font-medium text-gray-700">Discount Value</label>
                        <input type="number" step="0.01" id="discount_value" name="discount_value" value="<?php echo e(number_format($discount_value, 2, '.', '')); ?>"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
            </div>

            <!-- Menu Search -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-900 mb-4">3. Add/Remove Items</h2>
                <div>
                    <label for="item-search" class="block text-sm font-medium text-gray-700">Search Menu Items</label>
                    <input type="text" id="item-search" placeholder="Type to search for 'Biryani' or 'Burger'..."
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                
                <!-- Search Results will be injected here by JavaScript -->
                <div id="item-search-results" class="mt-4 max-h-96 overflow-y-auto space-y-2">
                    <!-- JS will populate this -->
                </div>
            </div>

        </div>

        <!-- Right Column: Order Summary (Cart) -->
        <div class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">4. Order Summary</h2>
                
                <!-- Cart Items List -->
                <div id="cart-items-list" class="space-y-3 max-h-64 overflow-y-auto pr-2">
                    <p id="cart-empty-msg" class="text-gray-500 text-center">Your cart is empty.</p>
                    <!-- Cart items will be injected here by JavaScript -->
                </div>
                
                <!-- Totals Section -->
                <div class="mt-6 border-t pt-4 space-y-2">
                    <div class="flex justify-between text-gray-700">
                        <span>Subtotal</span>
                        <span id="cart-subtotal">0.00 BDT</span>
                    </div>
                    <!-- (NEW) Discount Row -->
                    <div class="flex justify-between text-red-600">
                        <span>Discount</span>
                        <span id="cart-discount">-0.00 BDT</span>
                    </div>
                    <div class="flex justify-between text-gray-700">
                        <span>Delivery Fee</span>
                        <span id="cart-delivery-fee">0.00 BDT</span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 text-lg">
                        <span>Grand Total</span>
                        <span id="cart-total">0.00 BDT</span>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="submit_order" 
                        class="mt-6 w-full py-3 px-4 bg-green-600 text-white font-medium rounded-lg shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                    Save Changes to Order
                </button>
                <a href="order_details.php?id=<?php echo e($order_id); ?>" class="mt-2 w-full block text-center py-3 px-4 bg-gray-200 text-gray-700 font-medium rounded-lg shadow-md hover:bg-gray-300">
                    Cancel Edit
                </a>
            </div>
        </div>

    </div>
</form>


<!-- Item Options Modal (Hidden by default) -->
<div id="options-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg">
        <div class="flex justify-between items-center p-6 border-b">
            <h2 id="modal-item-name" class="text-2xl font-bold text-gray-900">Item Options</h2>
            <button id="modal-close-btn" class="p-2 text-gray-500 hover:text-gray-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <div id="modal-options-content" class="p-6 max-h-96 overflow-y-auto space-y-6">
            <!-- Options will be injected here by JavaScript -->
            <p>Loading options...</p>
        </div>
        
        <div class="p-6 border-t bg-gray-50 rounded-b-2xl flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-gray-700">Quantity:</span>
                <input id="modal-quantity" type="number" value="1" min="1"
                       class="w-20 px-3 py-1 border border-gray-300 rounded-lg shadow-sm">
            </div>
            <button id="modal-add-to-cart-btn" class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                Add to Order (Total: <span id="modal-total-price">0.00</span>)
            </button>
        </div>
    </div>
</div>


<!-- 
=====================================================
    JAVASCRIPT LOGIC
=====================================================
-->
<script>
    // 1. --- FULL MENU DATA ---
    const fullMenu = <?php echo json_encode($menu_items); ?>;

    // 2. --- (MODIFIED) GLOBAL STATE ---
    // Pre-populate cart from PHP
    let cart = <?php echo json_encode($cart_for_js); ?>; 
    let currentModalItem = {};
    
    // 3. --- DOM ELEMENT REFERENCES ---
    const searchInput = document.getElementById('item-search');
    const searchResultsContainer = document.getElementById('item-search-results');
    
    const cartItemsList = document.getElementById('cart-items-list');
    const cartEmptyMsg = document.getElementById('cart-empty-msg');
    const cartSubtotalEl = document.getElementById('cart-subtotal');
    const cartDeliveryFeeEl = document.getElementById('cart-delivery-fee');
    const cartDiscountEl = document.getElementById('cart-discount');
    const cartTotalEl = document.getElementById('cart-total');
    
    const deliveryAreaSelect = document.getElementById('delivery_area_id');
    const discountTypeSelect = document.getElementById('discount_type');
    const discountValueInput = document.getElementById('discount_value');
    
    const modal = document.getElementById('options-modal');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalItemName = document.getElementById('modal-item-name');
    const modalOptionsContent = document.getElementById('modal-options-content');
    const modalQuantity = document.getElementById('modal-quantity');
    const modalTotalPrice = document.getElementById('modal-total-price');
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');

    const form = document.getElementById('manual-order-form');
    const cartDataInput = document.getElementById('cart-data-input');
    
    const jsSubtotalInput = document.getElementById('js-subtotal');
    const jsDeliveryFeeInput = document.getElementById('js-delivery-fee');
    const jsDiscountInput = document.getElementById('js-discount');
    const jsTotalInput = document.getElementById('js-total');
    
    
    // 4. --- CORE FUNCTIONS --- (Identical to manual_order.php)

    /**
     * Renders the menu items in the search results list
     */
    function renderMenu(itemsToRender) {
        searchResultsContainer.innerHTML = ''; // Clear old results
        if (itemsToRender.length === 0) {
            searchResultsContainer.innerHTML = '<p class="text-gray-500">No items match your search.</p>';
            return;
        }
        
        itemsToRender.forEach(item => {
            let priceHtml = '';
            if (item.has_discount) {
                priceHtml = `${parseFloat(item.price).toFixed(2)} BDT <span class="text-gray-400 line-through ml-1">${parseFloat(item.original_price).toFixed(2)}</span>`;
            } else {
                priceHtml = `${parseFloat(item.price).toFixed(2)} BDT`;
            }

            searchResultsContainer.innerHTML += `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                    <div>
                        <div class="font-medium text-gray-800">${item.name}</div>
                        <div class="text-sm text-gray-500">${item.category_name} - ${priceHtml}</div>
                    </div>
                    <button type="button" class="px-3 py-1 bg-orange-500 text-white text-sm font-medium rounded-lg" onclick="openItemModal(${item.id})">
                        Add
                    </button>
                </div>
            `;
        });
    }

    /**
     * Renders the cart items in the sidebar
     */
    function renderCart() {
        if (cart.length === 0) {
            cartEmptyMsg.style.display = 'block';
            cartItemsList.innerHTML = ''; 
        } else {
            cartEmptyMsg.style.display = 'none';
            cartItemsList.innerHTML = '';
            
            cart.forEach((item, index) => {
                let optionsHtml = '<ul class="text-xs text-gray-500 list-disc list-inside pl-1">';
                if (item.options) {
                    item.options.forEach(opt => {
                        optionsHtml += `<li>${e(opt.name)} (+${parseFloat(opt.price).toFixed(2)})</li>`;
                    });
                }
                optionsHtml += '</ul>';

                cartItemsList.innerHTML += `
                    <div class="border-b pb-2">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-800">${item.quantity}x ${e(item.name)}</span>
                            <span class="font-medium">${item.totalPrice.toFixed(2)}</span>
                        </div>
                        ${optionsHtml}
                        <button type="button" class="text-xs text-red-500 hover:text-red-700" onclick="removeFromCart(${index})">
                            Remove
                        </button>
                    </div>
                `;
            });
        }
        updateTotals();
    }
    
    /**
     * Calculates and updates all totals
     */
    function updateTotals() {
        const subtotal = cart.reduce((sum, item) => sum + item.totalPrice, 0);
        
        const discountType = discountTypeSelect.value;
        const discountValue = parseFloat(discountValueInput.value) || 0;
        let discountAmount = 0;
        
        if (discountType === 'percentage') {
            discountAmount = subtotal * (discountValue / 100);
        } else if (discountType === 'fixed') {
            discountAmount = discountValue;
        }
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
        }

        const selectedArea = deliveryAreaSelect.options[deliveryAreaSelect.selectedIndex];
        let deliveryFee = 0;
        
        if (selectedArea && selectedArea.dataset.charge) {
            deliveryFee = parseFloat(selectedArea.dataset.charge);
            
            const surchargeAmount = parseFloat(<?php echo json_encode($settings['night_surcharge_amount'] ?? 0); ?>);
            const surchargeStart = parseInt(<?php echo json_encode($settings['night_surcharge_start_hour'] ?? 0); ?>);
            const surchargeEnd = parseInt(<?php echo json_encode($settings['night_surcharge_end_hour'] ?? 6); ?>);
            const currentHour = new Date().getHours();
            
            if (surchargeStart > surchargeEnd) {
                if (currentHour >= surchargeStart || currentHour < surchargeEnd) {
                    deliveryFee += surchargeAmount;
                }
            } else {
                if (currentHour >= surchargeStart && currentHour < surchargeEnd) {
                    deliveryFee += surchargeAmount;
                }
            }
        }
        
        const total = (subtotal - discountAmount) + deliveryFee;
        
        cartSubtotalEl.textContent = `${subtotal.toFixed(2)} BDT`;
        cartDiscountEl.textContent = `-${discountAmount.toFixed(2)} BDT`;
        cartDeliveryFeeEl.textContent = `${deliveryFee.toFixed(2)} BDT`;
        cartTotalEl.textContent = `${total.toFixed(2)} BDT`;
        
        jsSubtotalInput.value = subtotal.toFixed(2);
        jsDiscountInput.value = discountAmount.toFixed(2);
        jsDeliveryFeeInput.value = deliveryFee.toFixed(2);
        jsTotalInput.value = total.toFixed(2);
    }
    
    /**
     * Opens the modal to configure an item's options
     */
    async function openItemModal(itemId) {
        const baseItem = fullMenu.find(item => item.id == itemId);
        if (!baseItem) return;

        modal.style.display = 'flex';
        modalItemName.textContent = baseItem.name;
        modalOptionsContent.innerHTML = '<p class="text-gray-500">Loading options...</p>';
        modalQuantity.value = 1;

        currentModalItem = {
            id: baseItem.id,
            name: baseItem.name,
            basePrice: parseFloat(baseItem.price)
        };

        try {
            const response = await fetch(`ajax_get_item_details.php?id=${itemId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();
            
            let optionsHtml = '';
            if (data.option_groups && data.option_groups.length > 0) {
                data.option_groups.forEach(group => {
                    optionsHtml += `<fieldset class="space-y-2">`;
                    optionsHtml += `<legend class="text-sm font-medium text-gray-900">${group.name} (${group.type === 'radio' ? 'Choose 1' : 'Choose any'})</legend>`;
                    
                    group.options.forEach(option => {
                        optionsHtml += `
                            <div class="flex items-center justify-between">
                                <label for="option-${option.id}" class="text-sm text-gray-700">
                                    ${e(option.name)}
                                </label>
                                <div>
                                    <span class="text-sm text-gray-600">+${parseFloat(option.price_increase).toFixed(2)} BDT</span>
                                    <input 
                                        type="${group.type}" 
                                        id="option-${option.id}" 
                                        name="group-${group.id}" 
                                        value="${option.id}"
                                        data-name="${e(option.name)}"
                                        data-price="${option.price_increase}"
                                        class="h-4 w-4 ml-3 text-orange-600 border-gray-300 focus:ring-orange-500"
                                        onchange="updateModalPrice()"
                                    >
                                </div>
                            </div>
                        `;
                    });
                    optionsHtml += `</fieldset><hr>`;
                });
            } else {
                optionsHtml = '<p class="text-gray-500">This item has no options.</p>';
            }
            
            modalOptionsContent.innerHTML = optionsHtml;
            updateModalPrice(); 

        } catch (error) {
            modalOptionsContent.innerHTML = `<p class="text-red-500">Error loading options: ${error.message}</p>`;
        }
    }

    /**
     * Updates the total price in the modal as options are selected
     */
    function updateModalPrice() {
        let optionsPrice = 0;
        const selectedOptions = modalOptionsContent.querySelectorAll('input:checked');
        
        selectedOptions.forEach(opt => {
            optionsPrice += parseFloat(opt.dataset.price);
        });
        
        const quantity = parseInt(modalQuantity.value) || 1;
        const total = (currentModalItem.basePrice + optionsPrice) * quantity;
        
        modalTotalPrice.textContent = total.toFixed(2);
    }
    
    /**
     * Closes the item modal
     */
    function closeModal() {
        modal.style.display = 'none';
        currentModalItem = {};
    }
    
    /**
     * Adds the configured item from the modal to the main cart array
     */
    function addItemToCart() {
        const selectedOptions = [];
        const selectedElements = modalOptionsContent.querySelectorAll('input:checked');
        
        let optionsPrice = 0;
        selectedElements.forEach(opt => {
            const price = parseFloat(opt.dataset.price);
            selectedOptions.push({
                id: opt.value, 
                name: opt.dataset.name,
                price: price
            });
            optionsPrice += price;
        });

        const quantity = parseInt(modalQuantity.value) || 1;
        const singleItemPrice = currentModalItem.basePrice + optionsPrice;
        
        const cartItem = {
            id: currentModalItem.id,
            name: currentModalItem.name,
            basePrice: currentModalItem.basePrice,
            quantity: quantity,
            options: selectedOptions,
            totalPrice: singleItemPrice * quantity,
        };
        
        cart.push(cartItem);
        renderCart();
        closeModal();
    }
    
    /**
     * Removes an item from the cart by its index
     */
    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }
    
    // Helper to escape HTML in JS
    function e(str) {
        if (!str) return '';
        return str.toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // 5. --- EVENT LISTENERS ---
    
    // (MODIFIED) Initial render on page load
    document.addEventListener('DOMContentLoaded', () => {
        renderMenu(fullMenu);
        renderCart(); // This will render the pre-populated cart
        updateTotals(); // This will calculate totals based on pre-populated data
    });
    
    // Search input filtering
    searchInput.addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        const filteredMenu = fullMenu.filter(item => 
            item.name.toLowerCase().includes(searchTerm) ||
            item.category_name.toLowerCase().includes(searchTerm)
        );
        renderMenu(filteredMenu);
    });

    // Delivery area change
    deliveryAreaSelect.addEventListener('change', updateTotals);
    
    // (NEW) Discount fields change
    discountTypeSelect.addEventListener('change', updateTotals);
    discountValueInput.addEventListener('input', updateTotals);
    
    // Modal controls
    modalCloseBtn.addEventListener('click', closeModal);
    modalAddToCartBtn.addEventListener('click', addItemToCart);
    modalQuantity.addEventListener('input', updateModalPrice);
    
    // Form submission
    form.addEventListener('submit', (e) => {
        // Before submitting, update the hidden input with the final cart data
        cartDataInput.value = JSON.stringify(cart);
        
        // The rest of the form submission is handled by the browser
    });

</script>

<?php
// 6. FOOTER
require_once('footer.php');
?>