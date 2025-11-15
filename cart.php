<?php
/*
 * cart.php
 * KitchCo: Cloud Kitchen View Cart Page
 * Version 1.5 - (MODIFIED) Added Coupon Field
 *
 * This page:
 * 1. Displays all items in the session cart.
 * 2. Allows users to update quantities or remove items.
 * 3. Shows the subtotal.
 * 4. (NEW) Allows applying a coupon code.
 * 5. Links to checkout.
 */

// 1. PAGE SETUP
$page_title = 'Your Shopping Cart - KitchCo';
$meta_description = 'Review your order and proceed to checkout.';

// 2. HEADER
require_once('includes/header.php');

// 3. --- LOAD CART & CALCULATE TOTALS ---
$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;

foreach ($cart as $item) {
    $subtotal += $item['single_item_price'] * $item['quantity'];
}

?>

<h1 class="text-3xl font-bold text-gray-900 mb-8">Your Shopping Cart</h1>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Cart Items -->
    <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-lg">
        <?php if (empty($cart)): ?>
            <div class="text-center py-12">
                <img src="https://placehold.co/100x100/EFEFEF/AAAAAA?text=Cart" alt="Empty Cart" class="mx-auto h-24 w-24 text-gray-400">
                <h2 class="mt-4 text-xl font-bold text-gray-900">Your cart is empty</h2>
                <p class="mt-2 text-gray-600">Looks like you haven't added any items yet.</p>
                <!-- (MODIFIED) Clean URL -->
                <a href="<?php echo BASE_URL; ?>/menu" class="mt-6 inline-block px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700">
                    Browse Our Menu
                </a>
            </div>
        <?php else: ?>
            <div class="flow-root">
                <ul role="list" class="-my-6 divide-y divide-gray-200">
                    <?php foreach ($cart as $cart_key => $item): ?>
                        <li class="flex py-6">
                            <!-- In a real app, you'd fetch the item image -->
                            <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200">
                                <img src="https://placehold.co/100x100/EFEFEF/AAAAAA?text=Item" alt="<?php echo e($item['item_name']); ?>" class="h-full w-full object-cover object-center">
                            </div>

                            <div class="ml-4 flex flex-1 flex-col">
                                <div>
                                    <div class="flex justify-between text-base font-medium text-gray-900">
                                        <h3><?php echo e($item['item_name']); ?></h3>
                                        <p class="ml-4"><?php echo e(number_format($item['single_item_price'] * $item['quantity'], 2)); ?> BDT</p>
                                    </div>
                                    <!-- Options List -->
                                    <?php if (!empty($item['options'])): ?>
                                        <div class="mt-1 text-sm text-gray-500">
                                            <?php foreach ($item['options'] as $option): ?>
                                                <div><?php echo e($option['name']); ?> (+<?php echo e(number_format($option['price'], 2)); ?>)</div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex flex-1 items-end justify-between text-sm">
                                    <!-- Quantity Form -->
                                    <!-- (MODIFIED) Action points to the .php file, not a clean URL -->
                                    <form action="cart_actions.php" method="POST" class="flex items-center">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_key" value="<?php echo e($cart_key); ?>">
                                        <!-- (NEW) CSRF Token -->
                                        <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                                        <label for="quantity-<?php echo e($cart_key); ?>" class="mr-2 text-gray-700">Qty:</label>
                                        <input type="number" id="quantity-<?php echo e($cart_key); ?>" name="quantity" value="<?php echo e($item['quantity']); ?>" min="0" class="w-16 px-2 py-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-red">
                                        <!-- (MODIFIED) Button styling updated -->
                                        <button type="submit" class="ml-2 px-3 py-1 bg-brand-yellow text-gray-900 font-semibold rounded-lg shadow-sm hover:bg-yellow-500 transition-colors text-xs">Update</button>
                                    </form>

                                    <!-- Remove Button -->
                                    <div class="flex">
                                        <!-- (FIXED) Changed from <a> link to a <form> to use POST -->
                                        <!-- (MODIFIED) Action points to the .php file, not a clean URL -->
                                        <form action="cart_actions.php" method="POST">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="cart_key" value="<?php echo e($cart_key); ?>">
                                            <!-- (NEW) CSRF Token -->
                                            <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                                            <!-- (MODIFIED) Button styling updated -->
                                            <button type="submit" 
                                                    class="font-medium text-xs px-3 py-1 bg-gray-100 text-gray-700 rounded-lg hover:bg-red-100 hover:text-red-600 transition-colors"
                                                    onclick="return confirm('Are you sure you want to remove this item?');">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- Column 2: Order Summary -->
    <?php if (!empty($cart)): ?>
    <aside class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-24">
            <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-3">Order Summary</h2>
            <div class="space-y-3">
                <div class="flex justify-between text-base font-medium text-gray-900">
                    <p>Subtotal</p>
                    <p><?php echo e(number_format($subtotal, 2)); ?> BDT</p>
                </div>
                <p class="text-sm text-gray-500">
                    Discount, shipping and taxes will be calculated at checkout.
                </p>

                <!-- (NEW) Coupon Form -->
                <div class="mt-4 space-y-2 border-t pt-4">
                    <form id="cart-coupon-form">
                        <label for="cart_coupon_code" class="block text-sm font-medium text-gray-700">Have a coupon?</label>
                        <div class="flex gap-2">
                            <input type="text" id="cart_coupon_code" name="coupon_code" placeholder="Enter coupon code" class="flex-grow mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-brand-red">
                            <button type="submit" id="cart-apply-coupon-btn" class="mt-1 px-5 py-3 bg-brand-yellow text-gray-900 font-semibold rounded-lg shadow-sm hover:bg-yellow-500 transition-colors">Apply</button>
                        </div>
                        <p id="cart-coupon-message" class="text-sm mt-1"></p>
                    </form>
                </div>

                <div class="mt-6">
                    <!-- (MODIFIED) Clean URL -->
                    <!-- (MODIFIED) Button styling updated from brand-orange to brand-red -->
                    <a href="<?php echo BASE_URL; ?>/checkout" class="flex w-full items-center justify-center rounded-lg border border-transparent bg-brand-red px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-red-700 <?php echo ($store_is_open == '0') ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo ($store_is_open == '0') ? 'onclick="event.preventDefault(); alert(\'The store is currently closed.\');"' : ''; ?>>
                        Proceed to Checkout
                    </a>
                </div>
                <div class="mt-4 text-center text-sm">
                    <!-- (MODIFIED) Clean URL -->
                    <!-- (MODIFIED) Link styling updated from brand-orange to brand-red -->
                    <a href="<?php echo BASE_URL; ?>/menu" class="font-medium text-brand-red hover:text-red-700">
                        or Continue Shopping &rarr;
                    </a>
                </div>
            </div>
        </div>
    </aside>
    <?php endif; ?>

</div>

<!-- (NEW) JavaScript for Cart Coupon -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const couponForm = document.getElementById('cart-coupon-form');
        const couponInput = document.getElementById('cart_coupon_code');
        const couponBtn = document.getElementById('cart-apply-coupon-btn');
        const couponMsg = document.getElementById('cart-coupon-message');

        // Check if a coupon is already applied in session storage
        const appliedCoupon = sessionStorage.getItem('coupon_code');
        if (appliedCoupon) {
            couponInput.value = appliedCoupon;
            couponMsg.textContent = 'Coupon applied! Proceed to checkout to see discount.';
            couponMsg.className = 'text-sm mt-1 text-green-600';
            couponInput.disabled = true;
            couponBtn.textContent = 'Applied';
            couponBtn.disabled = true;
        }

        couponForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const code = couponInput.value.trim();
            if (!code) {
                couponMsg.textContent = 'Please enter a code.';
                couponMsg.className = 'text-sm mt-1 text-red-600';
                return;
            }

            couponBtn.disabled = true;
            couponBtn.textContent = '...';

            // For simplicity, we'll use the same AJAX file as checkout
            try {
                const formData = new FormData();
                formData.append('coupon_code', code);
                formData.append('subtotal', <?php echo $subtotal; ?>); // Pass current subtotal
                formData.append('csrf_token', '<?php echo e(get_csrf_token()); ?>');

                const response = await fetch('ajax_apply_coupon.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) throw new Error('Network error');
                
                const data = await response.json();

                if (data.success) {
                    // Store the valid code to be used on the checkout page
                    sessionStorage.setItem('coupon_code', code);
                    couponMsg.textContent = 'Coupon applied! Proceed to checkout to see discount.';
                    couponMsg.className = 'text-sm mt-1 text-green-600';
                    couponInput.disabled = true;
                    couponBtn.textContent = 'Applied';
                } else {
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
        });
    });
</script>

<?php
// 5. FOOTER
require_once('includes/footer.php');
?>