<?php
/*
 * cart.php
 * PizzaMania: Cloud Kitchen View Cart Page
 * Version 2.2 - (FIXED) Seamless Cross-Sell Add
 */

// 1. PAGE SETUP
$page_title = 'Your Shopping Cart - PizzaMania';
$meta_description = 'Review your order and proceed to checkout.';

// 2. HEADER
require_once('includes/header.php');

// 3. --- LOAD CART & CALCULATE TOTALS ---
$cart = $_SESSION['cart'] ?? [];
$subtotal = 0;

foreach ($cart as $item) {
    $subtotal += $item['single_item_price'] * $item['quantity'];
}

// 4. --- FETCH CROSS-SELL ITEMS ---
$cross_sell_items = [];
$sql_cross = "SELECT id, name, price, image, description FROM menu_items 
              WHERE category_id IN (4, 9) AND is_available = 1 
              ORDER BY RAND() LIMIT 3";
$result_cross = $db->query($sql_cross);
if ($result_cross) {
    while ($row = $result_cross->fetch_assoc()) {
        $cross_sell_items[] = $row;
    }
}
?>

<div class="bg-gray-50 min-h-screen font-sans text-gray-800 pb-12">
    <main class="container mx-auto px-4 py-8 max-w-6xl">

        <h1 class="text-3xl font-bold text-gray-900 mb-6">Your Shopping Cart</h1>

        <div class="flex flex-col lg:flex-row gap-8">

            <!-- LEFT COLUMN: Cart Items & Cross Sells -->
            <div class="flex-grow space-y-6">

                <?php if (empty($cart)): ?>
                    <!-- Empty State -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                        <div
                            class="w-24 h-24 bg-orange-50 text-orange-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-cart-shopping text-4xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h2>
                        <p class="text-gray-500 mb-8">Looks like you haven't added any delicious food yet.</p>
                        <a href="<?php echo BASE_URL; ?>/menu"
                            class="inline-flex items-center justify-center px-8 py-3 bg-brand-red text-white font-bold rounded-xl shadow-md hover:bg-red-700 transition-all transform hover:-translate-y-0.5">
                            Browse Full Menu
                        </a>
                    </div>
                <?php else: ?>

                    <!-- Cart Items List -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <!-- Header Row (Desktop) -->
                        <div
                            class="hidden md:grid grid-cols-12 gap-4 px-6 py-3 bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            <div class="col-span-6">Product</div>
                            <div class="col-span-3 text-center">Quantity</div>
                            <div class="col-span-3 text-right">Total</div>
                        </div>

                        <?php foreach ($cart as $cart_key => $item):
                            $item_total = $item['single_item_price'] * $item['quantity'];
                            ?>
                            <!-- Item Row -->
                            <div
                                class="p-6 border-b last:border-b-0 flex flex-col md:grid md:grid-cols-12 gap-6 items-center hover:bg-gray-50 transition-colors">

                                <!-- Image & Details -->
                                <div class="col-span-6 w-full flex gap-4 items-start">
                                    <div
                                        class="h-20 w-20 flex-shrink-0 rounded-lg overflow-hidden border border-gray-200 bg-gray-100">
                                        <img src="<?php echo e(BASE_URL . ($item['image'] ?? 'https://placehold.co/100x100/EFEFEF/AAAAAA?text=Item')); ?>"
                                            alt="<?php echo e($item['item_name']); ?>" class="h-full w-full object-cover"
                                            onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=Item'">
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-900"><?php echo e($item['item_name']); ?></h3>

                                        <?php if (!empty($item['options'])): ?>
                                            <div class="mt-1 text-xs text-gray-600 space-y-0.5">
                                                <?php foreach ($item['options'] as $option): ?>
                                                    <div class="flex items-center gap-1">
                                                        <i class="fa-solid fa-check text-green-500 text-[10px]"></i>
                                                        <span><?php echo e($option['name']); ?>
                                                            (+<?php echo number_format($option['price'], 0); ?>)</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mt-3 flex gap-3 text-xs font-medium">
                                            <form action="cart_actions.php" method="POST" class="inline">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_key" value="<?php echo e($cart_key); ?>">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo e(get_csrf_token()); ?>">
                                                <button type="submit"
                                                    class="text-red-500 hover:text-red-700 flex items-center gap-1 transition-colors"
                                                    onclick="return confirm('Remove this item?');">
                                                    <i class="fa-solid fa-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quantity Selector -->
                                <div class="col-span-3 flex justify-center w-full md:w-auto">
                                    <form action="cart_actions.php" method="POST"
                                        class="flex items-center border border-gray-300 rounded-lg bg-white overflow-hidden h-9">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_key" value="<?php echo e($cart_key); ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

                                        <!-- Minus Button -->
                                        <button type="submit" name="quantity"
                                            value="<?php echo max(0, $item['quantity'] - 1); ?>"
                                            class="px-3 bg-gray-50 hover:bg-gray-100 text-gray-600 border-r border-gray-200 h-full transition-colors flex items-center justify-center">
                                            <i class="fa-solid fa-minus text-xs"></i>
                                        </button>

                                        <!-- Display -->
                                        <div
                                            class="w-12 text-center text-sm font-semibold text-gray-900 select-none flex items-center justify-center h-full">
                                            <?php echo e($item['quantity']); ?>
                                        </div>

                                        <!-- Plus Button -->
                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>"
                                            class="px-3 bg-gray-50 hover:bg-gray-100 text-gray-600 border-l border-gray-200 h-full transition-colors flex items-center justify-center">
                                            <i class="fa-solid fa-plus text-xs"></i>
                                        </button>
                                    </form>
                                </div>

                                <!-- Total Price -->
                                <div class="col-span-3 text-right w-full md:w-auto flex justify-between md:block items-center">
                                    <span class="md:hidden text-sm font-medium text-gray-500">Total:</span>
                                    <p class="text-lg font-bold text-brand-red"><?php echo number_format($item_total, 0); ?> BDT
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>

                    </div>
                <?php endif; ?>

                <!-- Cross Sells -->
                <?php if (!empty($cross_sell_items)): ?>
                    <div class="mt-8">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Complete your meal</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                            <?php foreach ($cross_sell_items as $cs_item): ?>
                                <div
                                    class="bg-white p-3 rounded-xl border border-gray-200 shadow-sm flex items-center gap-3 hover:shadow-md transition-shadow cursor-pointer group">
                                    <div class="h-12 w-12 rounded-lg bg-gray-100 overflow-hidden flex-shrink-0">
                                        <img src="<?php echo e(BASE_URL . ($cs_item['image'] ?? 'https://placehold.co/100x100/EFEFEF/AAAAAA?text=Add')); ?>"
                                            class="h-full w-full object-cover group-hover:scale-110 transition-transform"
                                            onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=Add'">
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <h4 class="text-sm font-bold text-gray-800 truncate"><?php echo e($cs_item['name']); ?>
                                        </h4>
                                        <p class="text-xs text-gray-500"><?php echo number_format($cs_item['price'], 0); ?> BDT
                                        </p>
                                    </div>

                                    <!-- Quick Add Form (UPDATED with class 'cross-sell-form') -->
                                    <form action="cart_actions.php" method="POST" class="cross-sell-form">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="item_id" value="<?php echo $cs_item['id']; ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

                                        <button type="submit"
                                            class="h-8 w-8 rounded-full bg-gray-100 text-gray-600 hover:bg-brand-red hover:text-white flex items-center justify-center transition-colors"
                                            title="Add to Cart">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <!-- RIGHT COLUMN: Order Summary -->
            <?php if (!empty($cart)): ?>
                <div class="lg:w-96 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-lg border border-gray-200 p-6 sticky top-24">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 border-b pb-4">Order Summary</h2>

                        <div class="space-y-3 text-sm text-gray-600 mb-6">
                            <div class="flex justify-between">
                                <span>Subtotal</span>
                                <span class="font-semibold text-gray-900"><?php echo number_format($subtotal, 0); ?>
                                    BDT</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="flex items-center gap-1">
                                    Delivery Fee
                                    <i class="fa-regular fa-circle-question text-gray-400 cursor-help"
                                        title="Calculated based on your location at checkout"></i>
                                </span>
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-500">Calculated at
                                    checkout</span>
                            </div>
                        </div>

                        <!-- Coupon Code -->
                        <div class="mb-6">
                            <form id="cart-coupon-form">
                                <div class="flex justify-between items-center mb-2">
                                    <label class="text-xs font-bold uppercase text-gray-500">Coupon Code</label>
                                </div>
                                <div class="flex gap-2">
                                    <input type="text" id="cart_coupon_code" placeholder="Enter code"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-red/20 text-sm">
                                    <button type="submit" id="cart-apply-coupon-btn"
                                        class="px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors">
                                        Apply
                                    </button>
                                </div>
                                <p id="cart-coupon-message" class="text-xs mt-2 hidden"></p>
                            </form>
                        </div>

                        <div class="border-t border-dashed border-gray-300 my-4"></div>

                        <!-- Total -->
                        <div class="flex justify-between items-end mb-6">
                            <span class="text-base font-bold text-gray-900">Total Amount</span>
                            <div class="text-right">
                                <span
                                    class="block text-2xl font-extrabold text-brand-red"><?php echo number_format($subtotal, 0); ?>
                                    BDT</span>
                                <span class="text-xs text-gray-500">(Excl. delivery)</span>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <a href="<?php echo BASE_URL; ?>/checkout"
                            class="block w-full py-3.5 bg-brand-red text-white font-bold rounded-xl shadow-md hover:bg-red-700 hover:shadow-lg transition-all transform hover:-translate-y-0.5 text-center flex items-center justify-center gap-2 <?php echo ($settings['store_is_open'] == '0') ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
                            Proceed to Checkout <i class="fa-solid fa-arrow-right"></i>
                        </a>

                        <?php if ($settings['store_is_open'] == '0'): ?>
                            <p class="text-xs text-red-500 text-center mt-2">Store is currently closed.</p>
                        <?php endif; ?>

                        <a href="<?php echo BASE_URL; ?>/menu"
                            class="block text-center text-sm font-medium text-gray-500 mt-4 hover:text-gray-800">
                            or Continue Shopping
                        </a>

                        <div class="mt-4 text-center">
                            <div
                                class="inline-flex items-center justify-center gap-1 text-xs text-green-600 font-medium bg-green-50 py-1 px-3 rounded-full">
                                <i class="fa-regular fa-clock"></i> Est. Delivery: 30-45 mins
                            </div>
                        </div>

                    </div>
                </div>
            <?php endif; ?>

        </div>

    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // --- 1. COUPON LOGIC ---
        const couponForm = document.getElementById('cart-coupon-form');
        const couponInput = document.getElementById('cart_coupon_code');
        const couponBtn = document.getElementById('cart-apply-coupon-btn');
        const couponMsg = document.getElementById('cart-coupon-message');

        const appliedCoupon = sessionStorage.getItem('coupon_code');
        if (appliedCoupon) {
            couponInput.value = appliedCoupon;
            couponMsg.textContent = 'Code applied! Discount will appear at checkout.';
            couponMsg.className = 'text-xs mt-2 text-green-600 block';
            couponInput.disabled = true;
            couponBtn.textContent = 'Applied';
            couponBtn.disabled = true;
            couponBtn.classList.add('opacity-50', 'cursor-not-allowed');
        }

        if (couponForm) {
            couponForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const code = couponInput.value.trim();
                if (!code) {
                    couponMsg.textContent = 'Please enter a code.';
                    couponMsg.className = 'text-xs mt-2 text-red-600 block';
                    return;
                }

                couponBtn.disabled = true;
                couponBtn.textContent = '...';

                try {
                    const formData = new FormData();
                    formData.append('coupon_code', code);
                    formData.append('subtotal', <?php echo $subtotal; ?>);
                    formData.append('csrf_token', '<?php echo e(get_csrf_token()); ?>');

                    const response = await fetch('ajax_apply_coupon.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) throw new Error('Network error');

                    const data = await response.json();

                    if (data.success) {
                        sessionStorage.setItem('coupon_code', code);
                        couponMsg.textContent = 'Code valid! Discount will appear at checkout.';
                        couponMsg.className = 'text-xs mt-2 text-green-600 block';
                        couponInput.disabled = true;
                        couponBtn.textContent = 'Applied';
                    } else {
                        couponMsg.textContent = data.message;
                        couponMsg.className = 'text-xs mt-2 text-red-600 block';
                        couponBtn.disabled = false;
                        couponBtn.textContent = 'Apply';
                    }
                } catch (error) {
                    couponMsg.textContent = 'Error: ' + error.message;
                    couponMsg.className = 'text-xs mt-2 text-red-600 block';
                    couponBtn.disabled = false;
                    couponBtn.textContent = 'Apply';
                }
            });
        }

        // --- 2. CROSS-SELL ADD HANDLER (FIXED) ---
        const crossSellForms = document.querySelectorAll('.cross-sell-form');

        crossSellForms.forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const button = form.querySelector('button');
                const originalContent = button.innerHTML;

                // Show loading spinner
                button.disabled = true;
                button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

                try {
                    const formData = new FormData(form);
                    const response = await fetch('cart_actions.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const result = await response.json();

                    if (result.success) {
                        // Reload page to update cart UI
                        window.location.reload();
                    } else {
                        alert(result.message || 'Failed to add item.');
                        button.disabled = false;
                        button.innerHTML = originalContent;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('An error occurred while adding the item.');
                    button.disabled = false;
                    button.innerHTML = originalContent;
                }
            });
        });
    });
</script>

<?php
require_once('includes/footer.php');
?>