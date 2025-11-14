<?php
/*
 * admin/footer.php
 * KitchCo: Cloud Kitchen Master Admin Footer
 * Version 1.0
 *
 * This file is included at the bottom of ALL protected admin pages.
 * It handles:
 * 1. Closing the main content tags.
 * 2. Adding the JavaScript for the mobile sidebar.
 */
?>

            </div> <!-- Closes the main content wrapper (p-6 lg:p-8) -->
        </main> <!-- Closes the main content (flex-1 lg:ml-64) -->
    
    </div> <!-- Closes the main layout (relative min-h-screen lg:flex) -->

    <!-- Mobile Menu Backdrop -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden lg:hidden"></div>

    <script>
        // Simple JavaScript for mobile sidebar toggle
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const menuButton = document.getElementById('mobile-menu-button');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        }

        // Add event listeners only if the elements exist
        if (menuButton) {
            menuButton.addEventListener('click', toggleSidebar);
        }
        
        if (backdrop) {
            backdrop.addEventListener('click', toggleSidebar);
        }
        
        // This is a simple helper to highlight the active menu link
        // It checks the current URL and matches it to the link's href
        document.addEventListener('DOMContentLoaded', (event) => {
            const currentPath = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('#sidebar nav a');
            
            let hasActive = false;

            navLinks.forEach(link => {
                const linkPath = link.getAttribute('href').split('/').pop();
                
                // Remove active class from all
                link.classList.remove('nav-link-active');
                link.classList.add('nav-link-default');

                if (linkPath === currentPath) {
                    link.classList.add('nav-link-active');
                    link.classList.remove('nav-link-default');
                    hasActive = true;
                }
            });

            // Fallback for the 'live_orders.php' page
            if (!hasActive && (currentPath === 'admin' || currentPath === '')) {
                const dashboardLink = document.querySelector('a[href="live_orders.php"]');
                if (dashboardLink) {
                    dashboardLink.classList.add('nav-link-active');
                    dashboardLink.classList.remove('nav-link-default');
                }
            }
        });
    </script>

</body>
</html>