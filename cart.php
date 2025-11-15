<?php
/*
 * cart.php
 * KitchCo: Cloud Kitchen View Cart Page
 * Version 1.3 - Updated links for Clean URLs
 *
 * This page:
 * 1. Displays all items in the session cart.
 * 2. Allows users to update quantities or remove items.
 * 3. Shows the subtotal.
 * 4. Links to checkout.
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
                <a href="<?php echo BASE_URL; ?>/menu" class="mt-6 inline-block px-6 py-3 bg-brand-orange text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
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
                                        <input type="number" id="quantity-<?php echo e($cart_key); ?>" name="quantity" value="<?php echo e($item['quantity']); ?>" min="0" class="w-16 px-2 py-1 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-orange">
                                        <button type="submit" class="ml-2 text-xs text-brand-orange font-medium hover:text-orange-700">Update</button>
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
                                            <button type="submit" 
                                                    class="font-medium text-red-600 hover:text-red-500"
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
                    Shipping and taxes will be calculated at checkout.
                </p>
                <div class="mt-6">
                    <!-- (MODIFIED) Clean URL -->
                    <a href="<?php echo BASE_URL; ?>/checkout" class="flex w-full items-center justify-center rounded-lg border border-transparent bg-brand-orange px-6 py-3 text-base font-medium text-white shadow-sm hover:bg-orange-700 <?php echo ($store_is_open == '0') ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo ($store_is_open == '0') ? 'onclick="event.preventDefault(); alert(\'The store is currently closed.\');"' : ''; ?>>
                        Proceed to Checkout
                    </a>
                </div>
                <div class="mt-4 text-center text-sm">
                    <!-- (MODIFIED) Clean URL -->
                    <a href="<?php echo BASE_URL; ?>/menu" class="font-medium text-brand-orange hover:text-orange-700">
                        or Continue Shopping &rarr;
                    </a>
                </div>
            </div>
        </div>
    </aside>
    <?php endif; ?>

</div>

<?php
// 5. FOOTER
require_once('includes/footer.php');
?>