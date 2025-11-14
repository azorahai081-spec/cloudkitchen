<?php
/*
 * admin/manual_order.php
 * KitchCo: Cloud Kitchen Manual Order Entry (POS)
 * Version 1.0
 *
 * This page allows logged-in staff to create orders on behalf of customers
 * (e.g., for phone orders).
 */

// 1. HEADER
require_once('header.php');

// 2. PAGE VARIABLES & INITIALIZATION
$page_title = 'Manual Order Entry';
$error_message = '';
$success_message = '';

// 3. --- HANDLE POST REQUESTS (Submit the New Order) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    
    // --- A. GET CUSTOMER & ORDER DATA ---
    $customer_name = $_POST['customer_name'];
    $customer_phone = $_POST['customer_phone'];
    $customer_address = $_POST['customer_address'];
    $delivery_area_id = $_POST['delivery_area_id'];
    $subtotal = $_POST['final_subtotal'];
    $delivery_fee = $_POST['final_delivery_fee'];
    $total_amount = $_POST['final_total'];
    $order_status = 'Preparing'; // Manual orders are usually accepted right away
    
    // --- B. GET CART DATA ---
    // The cart data will be sent as a JSON string
    $cart_json = $_POST['cart_data'];
    $cart_items = json_decode($cart_json, true);
    
    // --- C. VALIDATION ---
    if (empty($customer_name) || empty($customer_phone) || empty($delivery_area_id)) {
        $error_message = 'Customer Name, Phone, and Delivery Area are required.';
    } elseif (empty($cart_items)) {
        $error_message = 'Cannot submit an empty order. Please add items to the cart.';
    }
    
    if (empty($error_message)) {
        // --- D. DATABASE TRANSACTION ---
        // We use a transaction because we are writing to multiple tables.
        // If one part fails, the whole order is rolled back.
        
        $db->begin_transaction();
        
        try {
            // 1. Insert into `orders` table
            $sql_order = "INSERT INTO orders (customer_name, customer_phone, customer_address, delivery_area_id, subtotal, delivery_fee, total_amount, order_status, order_time) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt_order = $db->prepare($sql_order);
            $stmt_order->bind_param('sssiddds', $customer_name, $customer_phone, $customer_address, $delivery_area_id, $subtotal, $delivery_fee, $total_amount, $order_status);
            $stmt_order->execute();
            $order_id = $db->insert_id; // Get the new order ID
            
            // 2. Prepare statements for `order_items` and `order_item_options`
            $sql_item = "INSERT INTO order_items (order_id, menu_item_id, quantity, base_price, total_price) VALUES (?, ?, ?, ?, ?)";
            $stmt_item = $db->prepare($sql_item);
            
            $sql_option = "INSERT INTO order_item_options (order_item_id, option_name, option_price) VALUES (?, ?, ?)";
            $stmt_option = $db->prepare($sql_option);
            
            // 3. Loop through cart items and insert them
            foreach ($cart_items as $item) {
                // Insert into `order_items`
                $stmt_item->bind_param('iiidd', $order_id, $item['id'], $item['quantity'], $item['basePrice'], $item['totalPrice']);
                $stmt_item->execute();
                $order_item_id = $db->insert_id; // Get the new order_item_id
                
                // Insert into `order_item_options`
                foreach ($item['options'] as $option) {
                    $stmt_option->bind_param('isd', $order_item_id, $option['name'], $option['price']);
                    $stmt_option->execute();
                }
            }
            
            // 4. Commit the transaction
            $db->commit();
            
            // Success!
            $success_message = "Order #{$order_id} created successfully!";
            // In a real app, you might redirect to a receipt page:
            // header("Location: print_receipt.php?id={$order_id}");
            
        } catch (Exception $e) {
            // Something went wrong, roll back
            $db->rollback();
            $error_message = 'Failed to create order. A database error occurred: ' . $e->getMessage();
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
$menu_items = [];
$sql = "SELECT m.id, m.name, m.price, c.name as category_name 
        FROM menu_items m
        JOIN categories c ON m.category_id = c.id
        WHERE m.is_available = 1 
        ORDER BY m.name ASC";
$result = $db->query($sql);
while ($row = $result->fetch_assoc()) {
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

<!-- 
This is a complex page. We use a single <form> for the final submission.
The cart is managed by JavaScript and its data is stored in a hidden input.
-->
<form action="manual_order.php" method="POST" id="manual-order-form">
    <!-- This hidden input will hold the JSON string of our cart -->
    <input type="hidden" name="cart_data" id="cart-data-input">
    
    <!-- Hidden inputs for final calculated totals -->
    <input type="hidden" name="final_subtotal" id="final-subtotal-input" value="0">
    <input type="hidden" name="final_delivery_fee" id="final-delivery-fee-input" value="0">
    <input type="hidden" name="final_total" id="final-total-input" value="0">

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
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="customer_phone" class="block text-sm font-medium text-gray-700">Customer Phone *</label>
                        <input type="tel" id="customer_phone" name="customer_phone" required
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div class="md:col-span-2">
                        <label for="customer_address" class="block text-sm font-medium text-gray-700">Customer Address</label>
                        <textarea id="customer_address" name="customer_address" rows="2"
                                  class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
                    </div>
                    <div>
                        <label for="delivery_area_id" class="block text-sm font-medium text-gray-700">Delivery Area *</label>
                        <select id="delivery_area_id" name="delivery_area_id" required
                                class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="">-- Select Area --</option>
                            <?php foreach ($delivery_areas as $area): ?>
                                <option value="<?php echo e($area['id']); ?>" data-charge="<?php echo e($area['base_charge']); ?>">
                                    <?php echo e($area['area_name']); ?> (<?php echo e($area['base_charge']); ?> BDT)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Menu Search -->
            <div class="bg-white p-6 rounded-2xl shadow-lg">
                <h2 class="text-xl font-bold text-gray-900 mb-4">2. Add Items to Order</h2>
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
                <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">3. Order Summary</h2>
                
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
This is the "brain" of the manual order page.
-->
<script>
    // 1. --- FULL MENU DATA ---
    // We pass the full menu from PHP to JavaScript
    const fullMenu = <?php echo json_encode($menu_items); ?>;

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
    const cartTotalEl = document.getElementById('cart-total');
    
    const deliveryAreaSelect = document.getElementById('delivery_area_id');
    
    const modal = document.getElementById('options-modal');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalItemName = document.getElementById('modal-item-name');
    const modalOptionsContent = document.getElementById('modal-options-content');
    const modalQuantity = document.getElementById('modal-quantity');
    const modalTotalPrice = document.getElementById('modal-total-price');
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');

    const form = document.getElementById('manual-order-form');
    const cartDataInput = document.getElementById('cart-data-input');
    const finalSubtotalInput = document.getElementById('final-subtotal-input');
    const finalDeliveryFeeInput = document.getElementById('final-delivery-fee-input');
    const finalTotalInput = document.getElementById('final-total-input');
    
    
    // 4. --- CORE FUNCTIONS ---

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
            searchResultsContainer.innerHTML += `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border">
                    <div>
                        <div class="font-medium text-gray-800">${item.name}</div>
                        <div class="text-sm text-gray-500">${item.category_name} - ${parseFloat(item.price).toFixed(2)} BDT</div>
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
            cartItemsList.innerHTML = ''; // Clear items, but not the msg
        } else {
            cartEmptyMsg.style.display = 'none';
            cartItemsList.innerHTML = ''; // Clear
            
            cart.forEach((item, index) => {
                let optionsHtml = '<ul class="text-xs text-gray-500 list-disc list-inside pl-1">';
                item.options.forEach(opt => {
                    optionsHtml += `<li>${opt.name} (+${opt.price.toFixed(2)})</li>`;
                });
                optionsHtml += '</ul>';

                cartItemsList.innerHTML += `
                    <div classclass="border-b pb-2">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-800">${item.quantity}x ${item.name}</span>
                            <span class="font-medium">${item.totalPrice.toFixed(2)}</span>
                        </div>
                        ${optionsHtml}
                        <button type_button" class="text-xs text-red-500 hover:text-red-700" onclick="removeFromCart(${index})">
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
        
        const selectedArea = deliveryAreaSelect.options[deliveryAreaSelect.selectedIndex];
        let deliveryFee = 0;
        
        if (selectedArea && selectedArea.dataset.charge) {
            deliveryFee = parseFloat(selectedArea.dataset.charge);
            
            // --- NIGHT SURCHARGE LOGIC ---
            // We get this from the $settings array (already in config.php)
            const surchargeAmount = parseFloat(<?php echo json_encode($settings['night_surcharge_amount'] ?? 0); ?>);
            const surchargeStart = parseInt(<?php echo json_encode($settings['night_surcharge_start_hour'] ?? 0); ?>);
            const surchargeEnd = parseInt(<?php echo json_encode($settings['night_surcharge_end_hour'] ?? 6); ?>);
            const currentHour = new Date().getHours(); // Get current hour (0-23)
            
            // Check if current time is in the surcharge window
            if (surchargeStart > surchargeEnd) {
                // Overnight (e.g., 22:00 to 06:00)
                if (currentHour >= surchargeStart || currentHour < surchargeEnd) {
                    deliveryFee += surchargeAmount;
                }
            } else {
                // Same day (e.g., 00:00 to 06:00)
                if (currentHour >= surchargeStart && currentHour < surchargeEnd) {
                    deliveryFee += surchargeAmount;
                }
            }
        }
        
        const total = subtotal + deliveryFee;
        
        // Update the display
        cartSubtotalEl.textContent = `${subtotal.toFixed(2)} BDT`;
        cartDeliveryFeeEl.textContent = `${deliveryFee.toFixed(2)} BDT`;
        cartTotalEl.textContent = `${total.toFixed(2)} BDT`;
        
        // Update the hidden form inputs
        finalSubtotalInput.value = subtotal.toFixed(2);
        finalDeliveryFeeInput.value = deliveryFee.toFixed(2);
        finalTotalInput.value = total.toFixed(2);
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
            basePrice: parseFloat(baseItem.price)
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
                        optionsHtml += `
                            <div class="flex items-center justify-between">
                                <label for="option-${option.id}" class="text-sm text-gray-700">
                                    ${option.name}
                                </label>
                                <div>
                                    <span class="text-sm text-gray-600">+${parseFloat(option.price_increase).toFixed(2)} BDT</span>
                                    <input 
                                        type="${group.type}" 
                                        id="option-${option.id}" 
                                        name="group-${group.id}" 
                                        value="${option.id}"
                                        data-name="${option.name}"
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
            // We use a unique ID based on options to stack identical items
            // but for simplicity, we'll just add as a new line
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