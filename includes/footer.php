<?php
/*
 * includes/footer.php
 * KitchCo: Cloud Kitchen Public Footer
 * Version 1.0
 *
 * This file is included at the bottom of ALL public-facing pages.
 * It handles:
 * 1. Closing the main content tags.
 * 2. Displaying the footer.
 * 3. Adding the mobile menu JavaScript.
 */
?>
    </main> <!-- Closes the main content wrapper from header.php -->

    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 mt-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- About -->
                <div>
                    <h3 class="text-xl font-bold text-white mb-2">KitchCo</h3>
                    <p class="text-sm">
                        Bringing delicious, home-style meals right to your doorstep.
                        Order online and taste the difference.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-2">Quick Links</h3>
                    <ul class="space-y-1">
                        <li><a href="index.php" class="hover:text-white">Home</a></li>
                        <li><a href="menu.php" class="hover:text-white">Full Menu</a></li>
                        <li><a href="cart.php" class="hover:text-white">My Cart</a></li>
                        <li><a href="checkout.php" class="hover:text-white">Checkout</a></li>
                    </ul>
                </div>
                
                <!-- Contact (Placeholder) -->
                <div>
                    <h3 class="text-lg font-semibold text-white mb-2">Contact Us</h3>
                    <ul class="space-y-1 text-sm">
                        <li>Phone: 01234-567890</li>
                        <li>Address: Gulshan, Dhaka</li>
                        <li>Email: orders@kitchco.demo</li>
                    </ul>
                </div>
            </div>
            
            <div class="mt-8 border-t border-gray-700 pt-4 text-center text-sm">
                &copy; <?php echo date('Y'); ?> KitchCo. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- 
    =====================================================
        GLOBAL JAVASCRIPT
    =====================================================
    -->
    
    <!-- Mobile Menu Toggle -->
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
    
    <!-- (Phase 5) GTM Body Snippet would go here -->

</body>
</html>