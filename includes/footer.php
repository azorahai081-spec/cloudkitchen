<?php
/*
 * includes/footer.php
 * Version 1.7 - (MODIFIED) Floating Cart Bar is now Mobile-Only
 *
 * This file is included at the bottom of ALL public-facing pages.
 */

// Calculate Cart Totals for Initial Load (PHP side)
$footer_cart_count = 0;
$footer_cart_total = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $footer_cart_count += $item['quantity'];
        $footer_cart_total += $item['single_item_price'] * $item['quantity'];
    }
}
// Show the bar if cart is not empty (count > 0)
$show_cart_bar = ($footer_cart_count > 0) ? '' : 'hidden';
?>
    </main> <!-- Closes the main content wrapper from header.php -->

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 mt-12 pb-24 md:pb-12"> <!-- Added bottom padding for mobile -->
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-xl font-bold text-white mb-2"><?php echo e($settings['store_name'] ?? 'Pizza Mania'); ?></h3>
                    <p class="text-sm">
                        Hot, fresh food delivered to your doorstep.
                        Order online and taste the difference.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-2">Quick Links</h3>
                    <ul class="space-y-1">
                        <li><a href="<?php echo BASE_URL; ?>/" class="hover:text-white">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/menu" class="hover:text-white">Full Menu</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/cart" class="hover:text-white">My Cart</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/checkout" class="hover:text-white">Checkout</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/track-order" class="hover:text-white">Track Order</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/complain" class="hover:text-white">File a Complaint</a></li>
                    </ul>
                </div>
                
                <!-- Contact (Placeholder) -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-2">Contact Us</h3>
                    <ul class="space-y-1 text-sm">
                        <li>Phone: 01886-600861</li>
                        <li>Address: Chawkbazer,Chattogram, Chittagong, Bangladesh</li>
                        <li>Email: pizzamania861@gmail.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-8 border-t border-gray-700 pt-6 text-center text-sm">
                &copy; <?php echo date('Y'); ?> <?php echo e($settings['store_name'] ?? 'Pizza Mania'); ?>. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- 
    =====================================================
        (NEW) FLOATING VIEW CART BAR (MOBILE ONLY)
        Added 'md:hidden' to hide on desktop
    =====================================================
    -->
    <a href="<?php echo BASE_URL; ?>/cart" id="floating-cart-bar" class="<?php echo $show_cart_bar; ?> md:hidden fixed bottom-4 left-4 right-4 bg-green-600 hover:bg-green-700 text-white rounded-xl shadow-2xl p-4 z-50 flex justify-between items-center transition-all duration-300 transform hover:-translate-y-1 cursor-pointer border border-green-500">
        <!-- Left: Quantity -->
        <div class="flex items-center">
            <div class="w-10 h-10 rounded-full bg-green-800 bg-opacity-50 flex items-center justify-center font-bold text-lg" id="float-cart-count">
                <?php echo $footer_cart_count; ?>
            </div>
            <div class="ml-3 flex flex-col">
                <span class="text-xs text-green-100 uppercase tracking-wider font-semibold">Items</span>
            </div>
        </div>

        <!-- Center: Text -->
        <div class="font-bold text-lg">
            View Cart
        </div>

        <!-- Right: Total Price -->
        <div class="flex items-center font-bold text-lg">
            <span id="float-cart-total"><?php echo number_format($footer_cart_total, 0); ?></span>
            <span class="ml-1 text-sm opacity-80">৳</span>
        </div>
    </a>

    <!-- 
    =====================================================
        FLOATING WHATSAPP BUTTON
    =====================================================
    -->
    <style>
        .whatsapp-float-btn {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 6rem; /* Moved up to avoid overlap with cart bar on mobile */
            right: 2rem;
            background-color: #25D366;
            color: #FFF;
            border-radius: 50%;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .whatsapp-float-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }
        .whatsapp-float-btn svg {
            width: 32px;
            height: 32px;
        }
        
        @media (max-width: 768px) {
            .whatsapp-float-btn {
                width: 50px;
                height: 50px;
                bottom: 6rem; /* Keep it consistent */
                right: 1rem;
            }
        }
        
        /* Reset WhatsApp button position for desktop since cart bar is hidden */
        @media (min-width: 768px) {
            .whatsapp-float-btn {
                bottom: 2rem; /* Move back down on desktop */
            }
        }
    </style>

    <a href="https://wa.me/880123456789" class="whatsapp-float-btn" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="white">
            <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27.2 106.1 27.2h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.8 0-66.3-8.8-94.3-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.7 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/>
        </svg>
    </a>

    <!-- Global JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const menuButton = document.getElementById('mobile-menu-open-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            
            if(menuButton) {
                menuButton.addEventListener('click', () => {
                    mobileMenu.classList.toggle('hidden');
                });
            }
        });
    </script>

</body>
</html>