<?php
/*
 * checkout.php
 * KitchCo: Cloud Kitchen Checkout Page
 * Version 1.2 - (MODIFIED) Redesigned buttons
 *
 * This page:
 * 1. Requires a non-empty cart to view.
 * 2. Displays the final order summary.
 * 3. Collects customer info (name, phone, address).
 * 4. Loads delivery areas for a dropdown.
 * 5. Uses AJAX to calculate delivery fees live.
 */

// 1. CONFIGURATION
require_once('config.php');

// 2. --- (MODIFIED) SECURITY CHECK (MOVED UP) ---
// This check MUST happen before any HTML is output (i.e., before header.php)
$cart = $_SESSION['cart'] ?? [];
$store_is_open = $settings['store_is_open'] ?? '1'; // Get store status from config

if (empty($cart) || $store_is_open == '0') {
    // If cart is empty or store is closed, redirect them.
    header('Location: menu.php');
    exit;
}

// 3. PAGE SETUP
$page_title = 'Checkout - KitchCo';
$meta_description = 'Complete your order and get your food delivered.';

// 4. HEADER (HTML output starts here)
require_once('includes/header.php');

// 5. --- LOAD DELIVERY AREAS ---
$delivery_areas = [];
$sql_areas = "SELECT id, area_name, base_charge FROM delivery_areas WHERE is_active = 1 ORDER BY area_name ASC";
$result_areas = $db->query($sql_areas);
if ($result_areas) {
    while ($row = $result_areas->fetch_assoc()) {
        $delivery_areas[] = $row;
    }
}

// 6. --- CALCULATE INITIAL SUBTOTAL ---
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['single_item_price'] * $item['quantity'];
}
?>

<!-- GTM Data Layer (begin_checkout) -->
<script>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: 'begin_checkout',
        ecommerce: {
            items: [
                <?php foreach($cart as $item) {
                    echo "{
                        item_id: '{$item['item_id']}',
                        item_name: '{$item['item_name']}',
                        price: {$item['single_item_price']},
                        quantity: {$item['quantity']}
                    },";
                } ?>
            ]
        }
    });
</script>

<h1 class="text-3xl font-bold text-gray-900 mb-8">Complete Your Order</h1>

<form action="submit_order.php" method="POST" id="checkout-form">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Column 1: Customer Details -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-6 border-b pb-3">1. Delivery Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Customer Name -->
                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                </div>
                
                <!-- Customer Phone -->
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                </div>
                
                <!-- Delivery Area -->
                <div class="md:col-span-2">
                    <label for="delivery_area_id" class="block text-sm font-medium text-gray-700">Delivery Area *</label>
                    <select id="delivery_area_id" name="delivery_area_id" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                        <option value="">-- Select Your Area --</option>
                        <?php foreach ($delivery_areas as $area): ?>
                            <option value="<?php echo e($area['id']); ?>" data-charge="<?php echo e($area['base_charge']); ?>">
                                <?php echo e($area['area_name']); ?> (<?php echo e(number_format($area['base_charge'], 2)); ?> BDT)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Customer Address -->
                <div class="md:col-span-2">
                    <label for="customer_address" class="block text-sm font-medium text-gray-700">Full Address (House, Road, Block) *</label>
                    <textarea id="customer_address" name="customer_address" rows="3" required
                              class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red"></textarea>
                </div>
            </div>
            
            <h2 class="text-xl font-bold text-gray-900 mt-8 mb-6 border-b pb-3">2. Payment Method</h2>
            <div class="p-4 border border-gray-300 rounded-lg bg-gray-50">
                <input type="radio" id="payment_cod" name="payment_method" value="cod" checked class="h-4 w-4 text-brand-red focus:ring-brand-red">
                <label for="payment_cod" class="ml-3 text-base font-medium text-gray-900">
                    Cash on Delivery (COD)
                </label>
                <p class="ml-7 text-sm text-gray-600">Please pay the rider when you receive your order.</p>
            </div>
            
        </div>

        <!-- Column 2: Order Summary -->
        <aside class="lg:col-span-1">
            <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-24">
                <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Order Summary</h2>
                
                <!-- Item List -->
                <div class="space-y-3 max-h-64 overflow-y-auto pr-2 border-b pb-3">
                    <?php foreach ($cart as $item): ?>
                        <div class="flex justify-between items-start">
                            <div>
                                <div class="font-medium text-gray-800">
                                    <?php echo e($item['quantity']); ?>x <?php echo e($item['item_name']); ?>

                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php foreach ($item['options'] as $option): ?>
                                        <div>+ <?php echo e($option['name']); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <span class="text-gray-700 font-medium"><?php echo e(number_format($item['single_item_price'] * $item['quantity'], 2)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Totals -->
                <div class="mt-4 space-y-2">
                    <div class="flex justify-between text-gray-700">
                        <span>Subtotal</span>
                        <span id="summary-subtotal"><?php echo e(number_format($subtotal, 2)); ?></span>
                    </div>
                    <div class="flex justify-between text-gray-700">
                        <span>Delivery Fee</span>
                        <span id="summary-delivery-fee">...</span>
                    </div>
                    <div id="summary-surcharge-row" class="hidden flex justify-between text-sm text-gray-600">
                        <span>Night Surcharge</span>
                        <span id="summary-surcharge-fee"></span>
                    </div>
                    <div class="flex justify-between font-bold text-gray-900 text-lg border-t pt-2">
                        <span>Total</span>
                        <span id="summary-total">...</span>
                    </div>
                </div>

                <!-- Hidden inputs for final totals -->
                <input type="hidden" name="final_subtotal" id="final-subtotal" value="<?php echo e($subtotal); ?>">
                <input type="hidden" name="final_delivery_fee" id="final-delivery-fee" value="0">
                <input type="hidden" name="final_total" id="final-total" value="0">

                <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
                <button type="submit" id="submit-order-btn" disabled
                        class="mt-6 w-full py-3 px-4 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700 transition-colors
                               disabled:bg-gray-400 disabled:cursor-not-allowed">
                    Place Order
                </button>
                <p id="submit-error" class="text-center text-sm text-red-600 mt-2"></p>
            </div>
        </aside>

    </div>
</form>

<!-- 
=====================================================
    JAVASCRIPT LOGIC
=====================================================
-->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const deliverySelect = document.getElementById('delivery_area_id');
        const summaryFee = document.getElementById('summary-delivery-fee');
        const summarySurchargeRow = document.getElementById('summary-surcharge-row');
        const summarySurchargeFee = document.getElementById('summary-surcharge-fee');
        const summaryTotal = document.getElementById('summary-total');
        const submitBtn = document.getElementById('submit-order-btn');
        const submitError = document.getElementById('submit-error');
        
        const subtotal = parseFloat(document.getElementById('final-subtotal').value);

        const finalDeliveryFeeInput = document.getElementById('final-delivery-fee');
        const finalTotalInput = document.getElementById('final-total');

        // --- Function to calculate fees ---
        async function calculateFees() {
            const areaId = deliverySelect.value;
            if (!areaId) {
                summaryFee.textContent = '...';
                summaryTotal.textContent = '...';
                submitBtn.disabled = true;
                submitError.textContent = 'Please select a delivery area.';
                return;
            }
            
            submitError.textContent = '';
            summaryFee.textContent = 'Calculating...';
            
            try {
                // Call our new AJAX file
                const response = await fetch(`ajax_calculate_fee.php?area_id=${areaId}`);
                if (!response.ok) throw new Error('Network error');
                
                const data = await response.json();
                
                if (data.success) {
                    const baseFee = data.base_charge;
                    const surcharge = data.surcharge_amount;
                    const totalFee = data.total_delivery_fee;
                    const grandTotal = subtotal + totalFee;

                    // Update summary
                    summaryFee.textContent = `${totalFee.toFixed(2)}`;
                    summaryTotal.textContent = `${grandTotal.toFixed(2)} BDT`;

                    // Show surcharge row if applied
                    if (surcharge > 0) {
                        summarySurchargeFee.textContent = `+${surcharge.toFixed(2)}`;
                        summarySurchargeRow.classList.remove('hidden');
                    } else {
                        summarySurchargeRow.classList.add('hidden');
                    }
                    
                    // Update hidden inputs
                    finalDeliveryFeeInput.value = totalFee.toFixed(2);
                    finalTotalInput.value = grandTotal.toFixed(2);
                    
                    // Enable button
                    submitBtn.disabled = false;
                    
                } else {
                    throw new Error(data.message || 'Could not calculate fee');
                }
                
            } catch (error) {
                submitBtn.disabled = true;
                summaryFee.textContent = 'Error';
                summaryTotal.textContent = 'Error';
                submitError.textContent = error.message;
            }
        }

        // --- Event Listener ---
        deliverySelect.addEventListener('change', calculateFees);
    });
</script>

<?php
// 7. FOOTER
require_once('includes/footer.php');
?>