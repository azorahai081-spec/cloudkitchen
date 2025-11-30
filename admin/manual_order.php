<?php
/*
 * admin/manual_order.php
 * PizzaMania: Cloud Kitchen Manual Order Entry (POS)
 * Version 1.9 - (UPDATED) Colorful Category Sections
 *
 * This page allows logged-in staff to create orders on behalf of customers.
 */

// 1. HEADER
require_once('header.php');

// (NEW) Helper function to apply global discount
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

    // Don't let price go below 0
    return max(0, (int) round($new_price));
}

// 2. PAGE VARIABLES & INITIALIZATION
$page_title = 'Manual Order Entry';
$error_message = '';
$success_message = '';

// 3. --- HANDLE POST REQUESTS (Submit the New Order) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {

    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // --- A. GET CUSTOMER DATA ---
        $customer_name = $_POST['customer_name'];
        $customer_phone = $_POST['customer_phone'];
        $customer_address = $_POST['customer_address'];
        $delivery_area_id = (int) $_POST['delivery_area_id'];

        // (NEW) Get Manual Discount Data (int)
        $discount_type = $_POST['discount_type'] ?? 'none';
        $discount_value = (int) ($_POST['discount_value'] ?? 0);
        $final_discount_amount = 0;

        $order_status = 'Preparing'; // Manual orders are usually accepted right away

        // --- B. GET CART DATA ---
        $cart_json = $_POST['cart_data'];
        $cart_items = json_decode($cart_json, true);

        // --- C. VALIDATION ---
        if (empty($customer_name) || empty($customer_phone) || empty($delivery_area_id)) {
            $error_message = 'Customer Name, Phone, and Delivery Area are required.';
        } elseif (empty($cart_items)) {
            $error_message = 'Cannot submit an empty order. Please add items to the cart.';
        }

        if (empty($error_message)) {
            // --- D. (MODIFIED) SERVER-SIDE PRICE RE-CALCULATION ---

            $db->begin_transaction();

            try {
                $subtotal = 0;
                $verified_cart_for_db = [];

                // Prepare statements outside the loop for efficiency
                $stmt_item = $db->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmt_option = $db->prepare("SELECT name, price_increase FROM item_options WHERE id = ?");

                foreach ($cart_items as $item) {
                    $item_id = (int) $item['id'];
                    $quantity = (int) $item['quantity'];

                    // 1. Get base item price from DB
                    $stmt_item->bind_param('i', $item_id);
                    $stmt_item->execute();
                    $result_item = $stmt_item->get_result();
                    if ($result_item->num_rows == 0)
                        throw new Exception("Item ID {$item_id} not found.");
                    $db_item = $result_item->fetch_assoc();

                    // (MODIFIED) Apply global discount
                    $original_base_price = $db_item['price'];
                    $base_price = calculate_discounted_price($original_base_price, $settings);

                    // 2. Get options prices from DB
                    $options_price = 0;
                    $verified_options_list = [];
                    if (!empty($item['options'])) {
                        foreach ($item['options'] as $option) {
                            $option_id = (int) $option['id']; // Get ID from fixed JS
                            $stmt_option->bind_param('i', $option_id);
                            $stmt_option->execute();
                            $result_option = $stmt_option->get_result();
                            if ($result_option->num_rows == 0)
                                throw new Exception("Option ID {$option_id} not found.");

                            $db_option = $result_option->fetch_assoc();
                            // (MODIFIED) Cast to int
                            $price_increase = (int) $db_option['price_increase'];

                            $options_price += $price_increase;
                            $verified_options_list[] = [
                                'name' => $db_option['name'], // Use verified name
                                'price' => $price_increase   // Use verified price
                            ];
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
                        'base_price' => $base_price, // Save the discounted base price
                        'total_price' => $total_item_price,
                        'options' => $verified_options_list
                    ];
                }

                // 5. (NEW) Calculate Manual Discount
                if ($discount_type == 'percentage' && $discount_value > 0) {
                    // Round percentage calc
                    $final_discount_amount = (int) round($subtotal * ($discount_value / 100));
                } elseif ($discount_type == 'fixed' && $discount_value > 0) {
                    $final_discount_amount = $discount_value;
                }

                // Ensure discount doesn't exceed subtotal
                if ($final_discount_amount > $subtotal) {
                    $final_discount_amount = $subtotal;
                }

                // 6. Calculate Delivery Fee
                $stmt_area = $db->prepare("SELECT base_charge FROM delivery_areas WHERE id = ? AND is_active = 1");
                $stmt_area->bind_param('i', $delivery_area_id);
                $stmt_area->execute();
                $result_area = $stmt_area->get_result();
                if ($result_area->num_rows == 0)
                    throw new Exception("Selected delivery area is not available.");

                // (MODIFIED) Cast to int
                $base_charge = (int) $result_area->fetch_assoc()['base_charge'];
                $surcharge_amount = 0;
                $surcharge = (int) ($settings['night_surcharge_amount'] ?? 0);

                // (NEW) Check exemption list
                $exempt_areas_str = $settings['night_surcharge_exempt_areas'] ?? '';
                $exempt_areas = explode(',', $exempt_areas_str);
                $is_exempt = in_array($delivery_area_id, $exempt_areas);

                if ($surcharge > 0 && !$is_exempt) {
                    $start_hour = (int) ($settings['night_surcharge_start_hour'] ?? 0);
                    $end_hour = (int) ($settings['night_surcharge_end_hour'] ?? 6);
                    $current_hour = (int) date('G');
                    if (
                        ($start_hour > $end_hour && ($current_hour >= $start_hour || $current_hour < $end_hour)) ||
                        ($start_hour <= $end_hour && ($current_hour >= $start_hour && $current_hour < $end_hour))
                    ) {
                        $surcharge_amount = $surcharge;
                    }
                }

                $total_delivery_fee = $base_charge + $surcharge_amount;

                // 7. (NEW) Calculate Final Total
                $total_amount = ($subtotal - $final_discount_amount) + $total_delivery_fee;

                // --- E. SAVE TO DATABASE ---

                // 1. Insert into `orders` table
                $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, delivery_area_id, 
                                      subtotal, delivery_fee, total_amount, order_status, 
                                      discount_type, discount_amount, order_time) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt_order = $db->prepare($sql_order);
                // Note: Integers use 'i', but 'd' is fine for ints too.
                $stmt_order->bind_param(
                    'sssidddssd',
                    $customer_name,
                    $customer_phone,
                    $customer_address,
                    $delivery_area_id,
                    $subtotal,
                    $total_delivery_fee,
                    $total_amount,
                    $order_status,
                    $discount_type,
                    $final_discount_amount
                );
                $stmt_order->execute();
                $order_id = $db->insert_id; // Get the new order ID

                if ($order_id <= 0)
                    throw new Exception("Failed to create order header.");

                // 2. Prepare statements for items and options
                $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
                $stmt_item_db = $db->prepare($sql_item);

                $sql_option_db = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
                $stmt_option_db = $db->prepare($sql_option_db);

                // 3. Loop through our VERIFIED cart and insert
                foreach ($verified_cart_for_db as $item) {
                    $stmt_item_db->bind_param('iiidd', $order_id, $item['item_id'], $item['quantity'], $item['base_price'], $item['total_price']);
                    $stmt_item_db->execute();
                    $order_item_id = $db->insert_id;

                    if ($order_item_id <= 0)
                        throw new Exception("Failed to save order item.");

                    foreach ($item['options'] as $option) {
                        $stmt_option_db->bind_param('isd', $order_item_id, $option['name'], $option['price']);
                        $stmt_option_db->execute();
                    }
                }

                // 4. Commit the transaction
                $db->commit();

                $success_message = "Order #PM-{$order_id} created successfully!";

            } catch (Exception $e) {
                // Something went wrong, roll back
                $db->rollback();
                $error_message = 'Failed to create order: ' . $e->getMessage();
            }
        }
    }
}


// 4. --- LOAD DATA FOR DISPLAY ---
// Load Delivery Areas for the dropdown
$delivery_areas = [];
$result = $db->query("SELECT * FROM delivery_areas WHERE is_active = 1 ORDER BY area_name ASC");
while ($row = $result->fetch_assoc()) {
    $delivery_areas[] = $row;
}

// Load All Menu Items for the search (we will pass this to JavaScript)
// (MODIFIED) Order by Category Name first, then Item Name
$menu_items = [];
$sql = "SELECT m.id, m.name, m.price, c.name as category_name 
        FROM menu_items m
        JOIN categories c ON m.category_id = c.id
        WHERE m.is_available = 1 
        ORDER BY c.name ASC, m.name ASC";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
    // (NEW) Apply global discount before passing to JS
    $original_price = (float) $row['price'];
    $discounted_price = calculate_discounted_price($original_price, $settings);

    $row['price'] = $discounted_price; // Overwrite price with discounted one
    $row['original_price'] = $original_price;
    $row['has_discount'] = ($discounted_price < $original_price);

    $menu_items[] = $row;
}

// (NEW) Exempt area list for JS calc
$exempt_areas = json_encode(explode(',', $settings['night_surcharge_exempt_areas'] ?? ''));
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

<!-- 
This is a complex page. We use a single <form> for the final submission.
The cart is managed by JavaScript and its data is stored in a hidden input.
-->
<form action="manual_order.php" method="POST" id="manual-order-form">
    <!-- (NEW) CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

    <!-- This hidden input will hold the JSON string of our cart -->
    <input type="hidden" name="cart_data" id="cart-data-input">

    <!-- (MODIFIED) These are now only for JS display, not for POST -->
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
                        <label for="customer_name" class="block text-sm font-medium text-gray-700">Customer Name
                            *</label>
                        <input type="text" id="customer_name" name="customer_name" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-gray-700">Customer Phone
                            *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="customer_address" class="block text-sm font-medium text-gray-700">Customer
                            Address</label>
                        <textarea id="customer_address" name="customer_address" rows="2"
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>
                    <div>
                        <label for="delivery_area_id" class="block text-sm font-medium text-gray-700">Delivery Area
                            *</label>
                        <select id="delivery_area_id" name="delivery_area_id" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">-- Select Area --</option>
                            <?php foreach ($delivery_areas as $area): ?>
                                <option value="<?php echo e($area['id']); ?>"
                                    data-charge="<?php echo e($area['base_charge']); ?>">
                                    <!-- (MODIFIED) Removed decimals -->
                                    <?php echo e($area['area_name']); ?>
                                    (<?php echo number_format($area['base_charge'], 0); ?> BDT)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- (NEW) Manual Discount -->
            <div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">2. Manual Discount (Optional)</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="discount_type" class="block text-sm font-medium text-gray-700">Discount Type</label>
                        <select id="discount_type" name="discount_type"
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="none">None</option>
                            <option value="fixed">Fixed (BDT)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label for="discount_value" class="block text-sm font-medium text-gray-700">Discount
                            Value</label>
                        <!-- (MODIFIED) step="1" -->
                        <input type="number" step="1" id="discount_value" name="discount_value" value="0"
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
            </div>

            <!-- Menu Search -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                    <h2 class="text-xl font-bold text-gray-900">3. Add Items to Order</h2>

                </div>

                <div class="relative mb-6">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" id="item-search" placeholder="Search for items (e.g. 'Pizza', 'Pasta')..."
                        class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 transition-shadow">
                </div>

                <!-- Search Results will be injected here by JavaScript -->
                <!-- (MODIFIED) Increased max-height and styling -->
                <div id="item-search-results"
                    class="mt-2 max-h-[600px] overflow-y-auto pr-2 space-y-6 custom-scrollbar">
                    <!-- JS will populate this with categories -->
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
                        <span id="cart-subtotal">0 BDT</span>
                    </div>
                    <!-- (NEW) Discount Row -->
                    <div class="flex justify-between text-red-600">
                        <span>Discount</span>
                        <span id="cart-discount">-0 BDT</span>
                    </div>
                    <div class="flex justify-between text-gray-700">
                        <span>Delivery Fee</span>
                        <span id="cart-delivery-fee">0 BDT</span>
                    </div>

                    <!-- (NEW) Surcharge Row for Manual Order Page -->
                    <div id="cart-surcharge-row" class="hidden flex justify-between text-sm text-gray-600">
                        <span>Night Surcharge</span>
                        <span id="cart-surcharge-fee"></span>
                    </div>

                    <div class="flex justify-between font-bold text-gray-900 text-lg">
                        <span>Grand Total</span>
                        <span id="cart-total">0 BDT</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" name="submit_order"
                    class="mt-6 w-full py-3 px-4 bg-green-600 text-white font-medium rounded-lg shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                    Create Order
                </button>
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
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
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
            <button id="modal-add-to-cart-btn"
                class="px-6 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                <!-- (MODIFIED) Removed decimals -->
                Add to Order (Total: <span id="modal-total-price">0</span>)
            </button>
        </div>
    </div>
</div>


<!-- 
=====================================================
    JAVASCRIPT LOGIC
=====================================================
This is the "brain" of the manual order page.
-->
<script>
    // 1. --- FULL MENU DATA ---
    // (MODIFIED) This now contains discounted prices
    const fullMenu = <?php echo json_encode($menu_items); ?>;
    // (NEW) Exempt area list for JS calc
    const exemptAreas = <?php echo $exempt_areas; ?>;

    // 2. --- GLOBAL STATE ---
    // This is our JavaScript "cart"
    let cart = [];
    let currentModalItem = {}; // Holds the item being configured in the modal

    // 3. --- DOM ELEMENT REFERENCES ---
    const searchInput = document.getElementById('item-search');
    const searchResultsContainer = document.getElementById('item-search-results');

    const cartItemsList = document.getElementById('cart-items-list');
    const cartEmptyMsg = document.getElementById('cart-empty-msg');
    const cartSubtotalEl = document.getElementById('cart-subtotal');
    const cartDeliveryFeeEl = document.getElementById('cart-delivery-fee');
    const cartDiscountEl = document.getElementById('cart-discount'); // (NEW)
    const cartTotalEl = document.getElementById('cart-total');
    // (NEW) Surcharge Elements
    const cartSurchargeRow = document.getElementById('cart-surcharge-row');
    const cartSurchargeFee = document.getElementById('cart-surcharge-fee');

    const deliveryAreaSelect = document.getElementById('delivery_area_id');
    // (NEW) Discount fields
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

    // (MODIFIED) References to JS-only hidden inputs
    const jsSubtotalInput = document.getElementById('js-subtotal');
    const jsDeliveryFeeInput = document.getElementById('js-delivery-fee');
    const jsDiscountInput = document.getElementById('js-discount'); // (NEW)
    const jsTotalInput = document.getElementById('js-total');


    // 4. --- CORE FUNCTIONS ---

    /**
     * (MODIFIED) Renders the menu items grouped by category WITH COLORS
     */
    function renderMenu(itemsToRender) {
        searchResultsContainer.innerHTML = ''; // Clear old results
        if (itemsToRender.length === 0) {
            searchResultsContainer.innerHTML = '<div class="p-4 text-center text-gray-500 bg-gray-50 rounded-lg">No items match your search.</div>';
            return;
        }

        // 1. Group items by category_name
        const groupedItems = {};
        itemsToRender.forEach(item => {
            if (!groupedItems[item.category_name]) {
                groupedItems[item.category_name] = [];
            }
            groupedItems[item.category_name].push(item);
        });

        // 2. Define Color Palettes for Sections
        const colorPalettes = [
            { bg: 'bg-orange-50', border: 'border-orange-200', text: 'text-orange-800', badge: 'bg-orange-100', hover: 'hover:bg-orange-100', btn: 'text-orange-600 border-orange-200 hover:bg-orange-600 hover:border-orange-600' },
            { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-800', badge: 'bg-blue-100', hover: 'hover:bg-blue-100', btn: 'text-blue-600 border-blue-200 hover:bg-blue-600 hover:border-blue-600' },
            { bg: 'bg-green-50', border: 'border-green-200', text: 'text-green-800', badge: 'bg-green-100', hover: 'hover:bg-green-100', btn: 'text-green-600 border-green-200 hover:bg-green-600 hover:border-green-600' },
            { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-800', badge: 'bg-purple-100', hover: 'hover:bg-purple-100', btn: 'text-purple-600 border-purple-200 hover:bg-purple-600 hover:border-purple-600' },
            { bg: 'bg-pink-50', border: 'border-pink-200', text: 'text-pink-800', badge: 'bg-pink-100', hover: 'hover:bg-pink-100', btn: 'text-pink-600 border-pink-200 hover:bg-pink-600 hover:border-pink-600' },
            { bg: 'bg-teal-50', border: 'border-teal-200', text: 'text-teal-800', badge: 'bg-teal-100', hover: 'hover:bg-teal-100', btn: 'text-teal-600 border-teal-200 hover:bg-teal-600 hover:border-teal-600' },
        ];

        // 3. Render each group
        let colorIndex = 0;
        for (const [categoryName, items] of Object.entries(groupedItems)) {

            const theme = colorPalettes[colorIndex % colorPalettes.length];
            colorIndex++;

            // A. Category Header (Wrapped in a colorful box)
            const catHeader = document.createElement('div');
            catHeader.className = `mb-6 bg-white rounded-xl p-4 border ${theme.border} shadow-sm`;

            catHeader.innerHTML = `
                <h3 class="text-lg font-bold ${theme.text} border-b ${theme.border} pb-2 mb-3 flex items-center">
                    <span class="${theme.badge} ${theme.text} px-3 py-1 rounded-full text-sm mr-2 shadow-sm">${items.length}</span>
                    ${e(categoryName)}
                </h3>
            `;

            // B. Items Grid
            const grid = document.createElement('div');
            grid.className = 'grid grid-cols-1 md:grid-cols-2 gap-3';

            items.forEach(item => {
                let priceHtml = '';
                if (item.has_discount) {
                    priceHtml = `<span class="font-bold text-gray-900">${parseInt(item.price)}</span> <span class="text-xs text-gray-400 line-through">${parseInt(item.original_price)}</span>`;
                } else {
                    priceHtml = `<span class="font-bold text-gray-900">${parseInt(item.price)}</span>`;
                }

                const itemDiv = document.createElement('div');
                // Apply theme colors to item cards
                itemDiv.className = `flex items-center justify-between p-3 ${theme.bg} ${theme.hover} rounded-lg border ${theme.border} transition-all duration-200`;
                itemDiv.innerHTML = `
                    <div class="flex-1 min-w-0 pr-3">
                        <div class="font-semibold text-gray-800 truncate" title="${e(item.name)}">${e(item.name)}</div>
                        <div class="text-sm mt-0.5">${priceHtml} <span class="text-xs text-gray-500">BDT</span></div>
                    </div>
                    <button type="button" class="flex-shrink-0 px-4 py-2 bg-white ${theme.btn} hover:text-white text-sm font-bold rounded-lg shadow-sm transition-all" onclick="openItemModal(${item.id})">
                        ADD
                    </button>
                `;
                grid.appendChild(itemDiv);
            });

            catHeader.appendChild(grid);
            searchResultsContainer.appendChild(catHeader);
        }
    }

    /**
     * Renders the cart items in the sidebar
     */
    function renderCart() {
        if (cart.length === 0) {
            cartEmptyMsg.style.display = 'block';
            cartItemsList.innerHTML = ''; // Clear items, but not the msg
        } else {
            cartEmptyMsg.style.display = 'none';
            cartItemsList.innerHTML = ''; // Clear

            cart.forEach((item, index) => {
                let optionsHtml = '<ul class="text-xs text-gray-500 list-disc list-inside pl-1">';
                item.options.forEach(opt => {
                    // (MODIFIED) parseInt
                    optionsHtml += `<li>${opt.name} (+${parseInt(opt.price)})</li>`;
                });
                optionsHtml += '</ul>';

                cartItemsList.innerHTML += `
                    <div class="border-b pb-2 last:border-b-0">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="font-medium text-gray-800 flex items-center">
                                    <span class="bg-gray-100 text-gray-600 text-xs px-1.5 py-0.5 rounded mr-2">x${item.quantity}</span>
                                    ${item.name}
                                </div>
                                ${optionsHtml}
                            </div>
                            <div class="text-right pl-2">
                                <div class="font-bold text-gray-900">${parseInt(item.totalPrice)}</div>
                                <button type="button" class="text-xs text-red-500 hover:text-red-700 underline mt-1" onclick="removeFromCart(${index})">
                                    Remove
                                </button>
                            </div>
                        </div>
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

        // (NEW) Calculate Manual Discount
        const discountType = discountTypeSelect.value;
        // (MODIFIED) parseFloat to handle text, but cast result to int
        const discountValue = parseFloat(discountValueInput.value) || 0;
        let discountAmount = 0;

        if (discountType === 'percentage') {
            // (MODIFIED) parseInt
            discountAmount = parseInt(subtotal * (discountValue / 100));
        } else if (discountType === 'fixed') {
            discountAmount = parseInt(discountValue);
        }
        if (discountAmount > subtotal) {
            discountAmount = subtotal;
        }

        const selectedArea = deliveryAreaSelect.options[deliveryAreaSelect.selectedIndex];
        const selectedAreaId = deliveryAreaSelect.value;
        let deliveryFee = 0;
        // (NEW) Track actual surcharge amount for display
        let appliedSurcharge = 0;

        if (selectedArea && selectedArea.dataset.charge) {
            // (MODIFIED) parseInt
            deliveryFee = parseInt(selectedArea.dataset.charge);

            // --- NIGHT SURCHARGE LOGIC (With Exemption) ---
            const isExempt = exemptAreas.includes(selectedAreaId);

            if (!isExempt) {
                // (MODIFIED) parseInt
                const surchargeAmount = parseInt(<?php echo json_encode($settings['night_surcharge_amount'] ?? 0); ?>);
                const surchargeStart = parseInt(<?php echo json_encode($settings['night_surcharge_start_hour'] ?? 0); ?>);
                const surchargeEnd = parseInt(<?php echo json_encode($settings['night_surcharge_end_hour'] ?? 6); ?>);
                const currentHour = new Date().getHours(); // Get current hour (0-23)

                if (surchargeStart > surchargeEnd) {
                    if (currentHour >= surchargeStart || currentHour < surchargeEnd) {
                        deliveryFee += surchargeAmount;
                        appliedSurcharge = surchargeAmount;
                    }
                } else {
                    if (currentHour >= surchargeStart && currentHour < surchargeEnd) {
                        deliveryFee += surchargeAmount;
                        appliedSurcharge = surchargeAmount;
                    }
                }
            }
        }

        const total = (subtotal - discountAmount) + deliveryFee;

        // Update the display (MODIFIED) No decimals
        cartSubtotalEl.textContent = `${subtotal} BDT`;
        cartDiscountEl.textContent = `-${discountAmount} BDT`; // (NEW)
        cartDeliveryFeeEl.textContent = `${deliveryFee} BDT`;
        cartTotalEl.textContent = `${total} BDT`;

        // (NEW) Show/Hide Surcharge Row
        if (appliedSurcharge > 0) {
            cartSurchargeFee.textContent = `(Includes ${appliedSurcharge} surcharge)`;
            cartSurchargeRow.classList.remove('hidden');
        } else {
            cartSurchargeRow.classList.add('hidden');
        }

        // (MODIFIED) Update the JS-only hidden inputs
        jsSubtotalInput.value = subtotal;
        jsDiscountInput.value = discountAmount; // (NEW)
        jsDeliveryFeeInput.value = deliveryFee;
        jsTotalInput.value = total;
    }

    /**
     * Opens the modal to configure an item's options
     */
    async function openItemModal(itemId) {
        // Find the base item data
        const baseItem = fullMenu.find(item => item.id == itemId);
        if (!baseItem) return;

        // Reset and show modal
        modal.style.display = 'flex';
        modalItemName.textContent = baseItem.name;
        modalOptionsContent.innerHTML = '<p class="text-gray-500">Loading options...</p>';
        modalQuantity.value = 1;

        // Store item data for later
        currentModalItem = {
            id: baseItem.id,
            name: baseItem.name,
            // (MODIFIED) parseInt
            basePrice: parseInt(baseItem.price) // (MODIFIED) This is now the discounted price
        };

        try {
            // Fetch the item's options from our new AJAX file
            const response = await fetch(`ajax_get_item_details.php?id=${itemId}`);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            const data = await response.json();

            // Build the HTML for the options
            let optionsHtml = '';
            if (data.option_groups && data.option_groups.length > 0) {
                data.option_groups.forEach(group => {
                    optionsHtml += `<fieldset class="space-y-2">`;
                    optionsHtml += `<legend class="text-sm font-medium text-gray-900">${group.name} (${group.type === 'radio' ? 'Choose 1' : 'Choose any'})</legend>`;

                    group.options.forEach(option => {
                        // (MODIFIED) parseInt for display
                        optionsHtml += `
                            <div class="flex items-center justify-between">
                                <label for="option-${option.id}" class="text-sm text-gray-700 cursor-pointer select-none w-full">
                                    ${e(option.name)}
                                </label>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm text-gray-600">+${parseInt(option.price_increase)} BDT</span>
                                    <input 
                                        type="${group.type}" 
                                        id="option-${option.id}" 
                                        name="group-${group.id}" 
                                        value="${option.id}"
                                        data-name="${e(option.name)}"
                                        data-price="${option.price_increase}"
                                        class="h-5 w-5 text-orange-600 border-gray-300 focus:ring-orange-500 cursor-pointer"
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
            updateModalPrice(); // Set initial price

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
            // (MODIFIED) parseInt
            optionsPrice += parseInt(opt.dataset.price);
        });

        const quantity = parseInt(modalQuantity.value) || 1;
        const total = (currentModalItem.basePrice + optionsPrice) * quantity;

        // (MODIFIED) Remove decimals
        modalTotalPrice.textContent = total;
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
            // (MODIFIED) parseInt
            const price = parseInt(opt.dataset.price);
            selectedOptions.push({
                // (MODIFIED) Add the option ID for server-side verification
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

    // Initial render of the full menu
    document.addEventListener('DOMContentLoaded', () => {
        renderMenu(fullMenu);
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