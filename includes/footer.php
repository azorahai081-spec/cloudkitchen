<?php
/*
 * includes/footer.php
 * Version 1.5 - (MODIFIED) Added floating WhatsApp button
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
    <footer class="bg-gray-900 text-gray-400 mt-12">
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
                        <!-- (FIXED) Clean URLs -->
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
        (NEW) FLOATING WHATSAPP BUTTON
    =====================================================
    -->
    <style>
        .whatsapp-float-btn {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 2rem;
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
        /* Make it a bit smaller on mobile */
        @media (max-width: 768px) {
            .whatsapp-float-btn {
                width: 50px;
                height: 50px;
                bottom: 1rem;
                right: 1rem;
            }
            .whatsapp-float-btn svg {
                width: 28px;
                height: 28px;
            }
        }
    </style>

    <!-- 
        !!! IMPORTANT: CHANGE THE PHONE NUMBER HERE !!!
        Replace "880123456789" with your full WhatsApp number including country code (without + or spaces).
    -->
    <a href="https://wa.me/880123456789" class="whatsapp-float-btn" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
        <!-- Inline WhatsApp SVG Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" fill="white">
            <path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27.2 106.1 27.2h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.8 0-66.3-8.8-94.3-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.7 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"/>
        </svg>
    </a>
    <!-- =============================================== -->
    <!-- END FLOATING WHATSAPP BUTTON                    -->
    <!-- =============================================== -->


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