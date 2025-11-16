<?php
/*
 * 404.php
 * KitchCo: Cloud Kitchen Custom 404 "Not Found" Page
 * Version 1.0
 *
 * This page is shown whenever a user visits a URL that doesn't exist.
 */

// 1. CONFIGURATION (MUST be first)
require_once('config.php'); 

// 2. --- (IMPORTANT) SET 404 STATUS CODE ---
// This tells search engines (like Google) that this is an error page
// and not a real page to index. This MUST be before any HTML is sent.
http_response_code(404);

// 3. PAGE SETUP
$page_title = 'Page Not Found (404) - ' . ($settings['store_name'] ?? 'Pizza Mania');
$meta_description = 'Sorry, the page you were looking for could not be found.';

// 4. HEADER (Now it's safe to send HTML)
require_once('includes/header.php');

?>

<!-- Main Content -->
<div class="max-w-2xl mx-auto text-center py-16">
    <!-- Icon -->
    <svg class="w-24 h-24 text-brand-red mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
    </svg>
    
    <h1 class="mt-4 text-3xl font-bold text-gray-900">Page Not Found</h1>
    <p class="mt-2 text-lg text-gray-600">
        Sorry, we couldn't find the page you were looking for. It might have been moved or deleted.
    </p>
    
    <a href="<?php echo BASE_URL; ?>/" class="mt-8 inline-block px-6 py-3 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700">
        &larr; Back to Homepage
    </a>
</div>

<?php
// 5. FOOTER
require_once('includes/footer.php');
?>