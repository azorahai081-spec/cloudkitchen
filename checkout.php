<?php
/*
 * checkout.php
 * PizzaMania: Cloud Kitchen Checkout Page
 * Version 2.0 - (MODIFIED) Removed decimal points for BDT
 *
 * This page:
 * 1. Requires a non-empty cart to view.
 * 2. Displays the final order summary.
 * 3. Collects customer info (name, phone, address).
 * 4. Validates customer name and phone number.
 * 5. Loads delivery areas for a custom searchable component.
 * 6. Uses AJAX to calculate delivery fees live.
 * 7. Uses AJAX to apply coupon codes.
 */

// 1. CONFIGURATION
require_once('config.php');

// 2. --- SECURITY CHECK ---
// This check MUST happen before any HTML is output (i.e., before header.php)
$cart = $_SESSION['cart'] ?? [];
$store_is_open = $settings['store_is_open'] ?? '1'; // Get store status from config

if (empty($cart) || $store_is_open == '0') {
    // If cart is empty or store is closed, redirect them.
    header('Location: menu.php');
    exit;
}

// 3. PAGE SETUP
$page_title = 'Checkout - PizzaMania';
$meta_description = 'Complete your order and get your food delivered.';

// 4. HEADER (HTML output starts here)
require_once('includes/header.php');

// Check for server-side validation errors
$checkout_error = $_SESSION['checkout_error'] ?? '';
if (!empty($checkout_error)) {
    echo '<div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">' . e($checkout_error) . '</div>';
    unset($_SESSION['checkout_error']); // Clear it after showing
}

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
                <?php foreach ($cart as $item) {
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

    // Store delivery areas in JS for the search component
    const allAreas = <?php echo json_encode($delivery_areas); ?>;
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
                    <input type="text" id="customer_name" name="customer_name" required minlength="4"
                        pattern="^[a-zA-Z .'-]{4,}$"
                        title="Please enter at least 4 characters. Letters, spaces, hyphens, and periods are allowed."
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red invalid:border-red-500 invalid:text-red-600 focus:invalid:ring-red-500">
                </div>

                <!-- Customer Phone -->
                <div>
                    <label for="customer_phone" class="block text-sm font-medium text-gray-700">Phone Number *</label>
                    <input type="tel" id="customer_phone" name="customer_phone" required
                        pattern="^(\+88|88)?01[0-9]{9}$"
                        title="Please enter a valid 11-digit Bangladeshi number (e.g., 01712345678)."
                        placeholder="01XXXXXXXXX"
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red invalid:border-red-500 invalid:text-red-600 focus:invalid:ring-red-500">
                </div>

                <!-- Delivery Area -->
                <div class="md:col-span-2">
                    <label for="area-search-button" class="block text-sm font-medium text-gray-700">Select or Search
                        Delivery Area *</label>
                    <div class="relative mt-1" id="custom-area-select">
                        <!-- 1. The button that shows the selected value -->
                        <button type="button" id="area-search-button"
                            class="relative w-full cursor-default rounded-lg border border-gray-300 bg-white px-4 py-3 pr-10 text-left shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red sm:text-sm">
                            <span class="block truncate text-gray-500" id="selected-area-text">Select your
                                area...</span>
                            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd"
                                        d="M10 3a.75.75 0 01.53.22l3.5 3.5a.75.75 0 01-1.06 1.06L10 4.81 6.53 8.28a.75.75 0 01-1.06-1.06l3.5-3.5A.75.75 0 0110 3zm-3.72 9.53a.75.75 0 011.06 0L10 15.19l3.47-3.47a.75.75 0 111.06 1.06l-4 4a.75.75 0 01-1.06 0l-4-4a.75.75 0 010-1.06z"
                                        clip-rule="evenodd" />
                                </svg>
                            </span>
                        </button>

                        <!-- 2. The dropdown panel (hidden by default) -->
                        <div id="area-dropdown-panel"
                            class="absolute z-10 mt-1 hidden w-full rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                            <!-- 2a. Search Input -->
                            <div class="p-2">
                                <input type="text" id="area-search-input" placeholder="Type to find your area..."
                                    autocomplete="off"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red sm:text-sm">
                            </div>
                            <!-- 2b. Options List -->
                            <ul class="max-h-60 overflow-auto py-1 text-base" id="area-options-list">
                                <!-- Options will be rendered here by JS -->
                                <li class="p-4 text-sm text-gray-500">Loading...</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Hidden input to store the selected Area ID -->
                    <input type="hidden" name="delivery_area_id" id="delivery_area_id" value="">
                    <!-- We still need the original input for validation, but it can be hidden -->
                    <input type="text" id="area-validation-input" required class="h-0 w-0 p-0 border-0" value=""
                        style="opacity: 0; position: absolute; z-index: -1;">
                </div>

                <!-- Customer Address -->
                <div class="md:col-span-2">
                    <label for="customer_address" class="block text-sm font-medium text-gray-700">Full Address (House,
                        Road, Block) *</label>
                    <textarea id="customer_address" name="customer_address" rows="3" required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red invalid:border-red-500 invalid:text-red-600 focus:invalid:ring-red-500"></textarea>
                </div>

                <!-- Order Note -->
                <div class="md:col-span-2">
                    <label for="order_note" class="block text-sm font-medium text-gray-700">Note / Special Instructions
                        (Optional)</label>
                    <textarea id="order_note" name="order_note" rows="3"
                        placeholder="e.g. less spicy, don't ring doorbell, etc."
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red"></textarea>
                </div>

            </div>

            <h2 class="text-xl font-bold text-gray-900 mt-8 mb-6 border-b pb-3">2. Payment Method</h2>
            <div class="p-4 border border-gray-300 rounded-lg bg-gray-50">
                <input type="radio" id="payment_cod" name="payment_method" value="cod" checked
                    class="h-4 w-4 text-brand-red focus:ring-brand-red">
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
                            <!-- (MODIFIED) Removed decimals -->
                            <span
                                class="text-gray-700 font-medium"><?php echo e(number_format($item['single_item_price'] * $item['quantity'], 0)); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totals -->
                <div class="mt-4 space-y-2">
                    <div class="flex justify-between text-gray-700">
                        <span>Subtotal</span>
                        <!-- (MODIFIED) Removed decimals -->
                        <span id="summary-subtotal"><?php echo e(number_format($subtotal, 0)); ?></span>
                    </div>
                    <!-- Coupon Discount Row -->
                    <div id="summary-discount-row" class="hidden flex justify-between text-brand-red">
                        <span>Discount</span>
                        <!-- (MODIFIED) Removed decimals -->
                        <span id="summary-discount-fee">0</span>
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

                <!-- Coupon Form -->
                <div class="mt-4 space-y-2 border-t pt-4">
                    <label for="coupon_code" class="block text-sm font-medium text-gray-700">Have a coupon?</label>
                    <div class="flex gap-2">
                        <input type="text" id="coupon_code" name="coupon_code_display" placeholder="Enter coupon code"
                            class="flex-grow mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                        <button type="button" id="apply-coupon-btn"
                            class="mt-1 px-5 py-3 bg-brand-yellow text-gray-900 font-semibold rounded-lg shadow-sm hover:bg-yellow-500 transition-colors">Apply</button>
                    </div>
                    <p id="coupon-message" class="text-sm mt-1"></p>
                </div>

                <!-- (NEW) Cooking & Delivery Time Estimates -->
                <div class="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 text-blue-500 mt-0.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="text-sm text-blue-800">
                            <p><strong>Cooking Time:</strong> 15-20 minutes</p>
                            <p><strong>Delivery Time:</strong> 15-20 minutes</p>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for final totals -->
                <input type="hidden" name="final_subtotal" id="final-subtotal" value="<?php echo e($subtotal); ?>">
                <input type="hidden" name="final_delivery_fee" id="final-delivery-fee" value="0">
                <input type="hidden" name="final_discount_code" id="final-discount-code" value="">
                <input type="hidden" name="final_discount_amount" id="final-discount-amount" value="0">
                <input type="hidden" name="final_total" id="final-total" value="0">

                <button type="submit" id="submit-order-btn" disabled class="mt-6 w-full py-3 px-4 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700 transition-colors
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
        // --- VALIDATION ELEMENTS ---
        const checkoutForm = document.getElementById('checkout-form');
        const nameInput = document.getElementById('customer_name');
        const phoneInput = document.getElementById('customer_phone');
        const addressInput = document.getElementById('customer_address');

        // --- DELIVERY AREA ELEMENTS ---
        const customSelect = document.getElementById('custom-area-select');
        const areaButton = document.getElementById('area-search-button');
        const selectedAreaText = document.getElementById('selected-area-text');
        const areaDropdown = document.getElementById('area-dropdown-panel');
        const areaSearchInput = document.getElementById('area-search-input');
        const areaOptionsList = document.getElementById('area-options-list');
        const deliveryIdHidden = document.getElementById('delivery_area_id');
        const areaValidationInput = document.getElementById('area-validation-input');

        // --- EXISTING ELEMENTS ---
        const summaryFee = document.getElementById('summary-delivery-fee');
        const summarySurchargeRow = document.getElementById('summary-surcharge-row');
        const summarySurchargeFee = document.getElementById('summary-surcharge-fee');
        const summaryTotal = document.getElementById('summary-total');
        const submitBtn = document.getElementById('submit-order-btn');
        const submitError = document.getElementById('submit-error');

        const subtotal = parseFloat(document.getElementById('final-subtotal').value);

        const finalDeliveryFeeInput = document.getElementById('final-delivery-fee');
        const finalTotalInput = document.getElementById('final-total');

        const couponInput = document.getElementById('coupon_code');
        const couponBtn = document.getElementById('apply-coupon-btn');
        const couponMsg = document.getElementById('coupon-message');
        const summaryDiscountRow = document.getElementById('summary-discount-row');
        const summaryDiscountFee = document.getElementById('summary-discount-fee');
        const finalDiscountCodeInput = document.getElementById('final-discount-code');
        const finalDiscountAmountInput = document.getElementById('final-discount-amount');

        let currentDiscount = 0;
        let currentDeliveryFee = 0;
        let isFeeCalculated = false;

        // --- Custom Select/Search Logic ---

        // Function to render options in the list
        function renderAreaOptions(filter = '') {
            areaOptionsList.innerHTML = ''; // Clear list
            const filteredAreas = allAreas.filter(area =>
                area.area_name.toLowerCase().includes(filter.toLowerCase())
            );

            if (filteredAreas.length === 0) {
                areaOptionsList.innerHTML = '<li class="p-3 text-sm text-gray-500">No areas found.</li>';
                return;
            }

            filteredAreas.forEach(area => {
                const li = document.createElement('li');
                li.className = 'cursor-pointer select-none relative p-3 text-sm text-gray-900 hover:bg-brand-red hover:text-white';
                // (MODIFIED) Use parseInt for display logic
                li.textContent = `${area.area_name} (${parseInt(area.base_charge)} BDT)`;
                li.dataset.id = area.id;
                li.dataset.name = area.area_name;
                areaOptionsList.appendChild(li);
            });
        }

        // Toggle dropdown
        areaButton.addEventListener('click', (e) => {
            e.stopPropagation();
            const isHidden = areaDropdown.classList.toggle('hidden');
            if (!isHidden) {
                // When opening, render all options and focus the search bar
                renderAreaOptions();
                areaSearchInput.value = ''; // Clear search on open
                areaSearchInput.focus();
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!customSelect.contains(e.target)) {
                areaDropdown.classList.add('hidden');
            }
        });

        // Filter list on search input
        areaSearchInput.addEventListener('input', () => {
            renderAreaOptions(areaSearchInput.value);
        });

        // Handle selecting an option
        areaOptionsList.addEventListener('click', (e) => {
            if (e.target && e.target.tagName === 'LI' && e.target.dataset.id) {
                const areaId = e.target.dataset.id;
                const areaName = e.target.dataset.name;

                // 1. Update the button text
                selectedAreaText.textContent = areaName;
                selectedAreaText.classList.remove('text-gray-500');
                selectedAreaText.classList.add('text-gray-900');

                // 2. Set the hidden input values
                deliveryIdHidden.value = areaId;
                areaValidationInput.value = areaName; // Set validation input

                // 3. Close dropdown
                areaDropdown.classList.add('hidden');

                // 4. Trigger calculations and validation
                calculateFees();
                checkAllValidity();
            }
        });

        // --- Function to check all form validity ---
        function checkAllValidity() {
            if (!checkoutForm) return;

            const isFormValid = checkoutForm.checkValidity();
            const isAreaSelected = deliveryIdHidden.value !== '';

            submitBtn.disabled = !isFormValid || !isAreaSelected;

            if (!isFormValid) {
                if (areaValidationInput.validity.valueMissing) {
                    submitError.textContent = 'Please select a delivery area from the list.';
                } else {
                    submitError.textContent = 'Please fix the errors in the form.';
                }
            } else if (!isAreaSelected) {
                submitError.textContent = 'Please select a delivery area.';
            } else {
                submitError.textContent = '';
            }
        }

        // --- Function to apply coupon ---
        async function applyCoupon() {
            const code = couponInput.value.trim();
            if (!code) {
                return;
            }

            couponBtn.disabled = true;
            couponBtn.textContent = '...';
            couponMsg.textContent = '';

            try {
                const formData = new FormData();
                formData.append('coupon_code', code);
                formData.append('subtotal', subtotal);
                formData.append('csrf_token', '<?php echo e(get_csrf_token()); ?>');

                const response = await fetch('ajax_apply_coupon.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Network error');

                const data = await response.json();

                if (data.success) {
                    currentDiscount = data.discount_amount;
                    finalDiscountCodeInput.value = code; // Save code for submission
                    finalDiscountAmountInput.value = currentDiscount;

                    // (MODIFIED) Remove decimals
                    summaryDiscountFee.textContent = `-${currentDiscount.toFixed(0)}`;
                    summaryDiscountRow.classList.remove('hidden');

                    couponMsg.textContent = data.message;
                    couponMsg.className = 'text-sm mt-1 text-green-600';
                    couponInput.disabled = true;
                    couponBtn.textContent = 'Applied';
                } else {
                    currentDiscount = 0;
                    finalDiscountCodeInput.value = '';
                    finalDiscountAmountInput.value = 0;
                    summaryDiscountRow.classList.add('hidden');
                    couponMsg.textContent = data.message;
                    couponMsg.className = 'text-sm mt-1 text-red-600';
                    couponBtn.disabled = false;
                    couponBtn.textContent = 'Apply';
                }

            } catch (error) {
                couponMsg.textContent = 'Error: ' + error.message;
                couponMsg.className = 'text-sm mt-1 text-red-600';
                couponBtn.disabled = false;
                couponBtn.textContent = 'Apply';
            }

            updateGrandTotal();
        }

        // --- Function to calculate fees ---
        async function calculateFees() {
            const areaId = deliveryIdHidden.value;
            isFeeCalculated = false;
            if (!areaId) {
                summaryFee.textContent = '...';
                summaryTotal.textContent = '...';
                currentDeliveryFee = 0;
                areaValidationInput.value = '';
                updateGrandTotal();
                return;
            }

            summaryFee.textContent = 'Calculating...';

            try {
                const response = await fetch(`ajax_calculate_fee.php?area_id=${areaId}`);
                if (!response.ok) throw new Error('Network error');

                const data = await response.json();

                if (data.success) {
                    const baseFee = data.base_charge;
                    const surcharge = data.surcharge_amount;
                    currentDeliveryFee = data.total_delivery_fee; // Store fee

                    // Update summary (MODIFIED) Remove decimals
                    summaryFee.textContent = `${currentDeliveryFee.toFixed(0)}`;

                    // Show surcharge row if applied (MODIFIED) Remove decimals
                    if (surcharge > 0 && currentDeliveryFee > 0) {
                        summarySurchargeFee.textContent = `+${surcharge.toFixed(0)}`;
                        summarySurchargeRow.classList.remove('hidden');
                    } else {
                        summarySurchargeRow.classList.add('hidden');
                    }

                    // Update hidden inputs (MODIFIED) Remove decimals
                    finalDeliveryFeeInput.value = currentDeliveryFee.toFixed(0);

                    isFeeCalculated = true;

                } else {
                    throw new Error(data.message || 'Could not calculate fee');
                }

            } catch (error) {
                isFeeCalculated = false;
                summaryFee.textContent = 'Error';
                currentDeliveryFee = 0;
                submitError.textContent = error.message;
            }

            // Update grand total
            updateGrandTotal();
        }

        // Function to update the final total and check validity
        function updateGrandTotal() {
            // Recalculate grand total
            const grandTotal = subtotal - currentDiscount + currentDeliveryFee;
            // (MODIFIED) Remove decimals
            summaryTotal.textContent = `${grandTotal.toFixed(0)} BDT`;
            finalTotalInput.value = grandTotal.toFixed(0);

            // Check validity to enable/disable button
            checkAllValidity();
        }

        // --- Event Listeners ---
        couponBtn.addEventListener('click', applyCoupon);

        nameInput.addEventListener('input', checkAllValidity);
        phoneInput.addEventListener('input', checkAllValidity);
        addressInput.addEventListener('input', checkAllValidity);

        // Check session storage for an applied coupon from cart.php
        const sessionCoupon = sessionStorage.getItem('coupon_code');
        if (sessionCoupon) {
            couponInput.value = sessionCoupon;
            applyCoupon();
            sessionStorage.removeItem('coupon_code'); // Clear it
        }

        // Initial render of options
        renderAreaOptions();
    });
</script>

<?php
// 7. FOOTER
require_once('includes/footer.php');
?>