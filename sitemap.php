<?php
/*
 * sitemap.php
 * KitchCo: Cloud Kitchen Dynamic Sitemap Generator
 * Version 1.0
 *
 * This file queries the database and generates a sitemap.xml
 * for search engines.
 */

// 1. CONFIGURATION
require_once('config.php'); // Needs BASE_URL and $db

// 2. SET XML HEADER
header('Content-Type: application/xml');

/**
 * Helper function to create a valid XML sitemap <url> entry.
 *
 * @param string $loc        The full URL of the page.
 * @param string $lastmod    The last modified date (YYYY-MM-DD).
 * @param string $changefreq How frequently the page is likely to change.
 * @param string $priority   The priority of this URL relative to other URLs on the site.
 * @return string            The formatted XML <url> block.
 */
function create_url_entry($loc, $lastmod, $changefreq = 'weekly', $priority = '0.8') {
    $loc_esc = e($loc); // Use our e() helper from config.php
    $lastmod_esc = e($lastmod);
    return "
    <url>
        <loc>$loc_esc</loc>
        <lastmod>$lastmod_esc</lastmod>
        <changefreq>$changefreq</changefreq>
        <priority>$priority</priority>
    </url>";
}

// 3. START XML OUTPUT
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

$today = date('Y-m-d');

// 4. ADD STATIC PAGES
// We use the new "clean" URLs from .htaccess
echo create_url_entry(BASE_URL . '/', $today, 'daily', '1.0');
echo create_url_entry(BASE_URL . '/menu', $today, 'daily', '0.9');
echo create_url_entry(BASE_URL . '/cart', $today, 'monthly', '0.5');
echo create_url_entry(BASE_URL . '/checkout', $today, 'monthly', '0.5');

// 5. ADD DYNAMIC CATEGORY PAGES
$cat_sql = "SELECT id FROM categories WHERE is_visible = 1";
$cat_result = $db->query($cat_sql);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        // Use the new clean URL structure
        echo create_url_entry(BASE_URL . '/menu/category/' . $row['id'], $today, 'weekly', '0.8');
    }
}

// 6. ADD DYNAMIC MENU ITEM PAGES
// Note: Your plan doesn't specify individual item pages, only category pages.
// If you had links like /item/burger, you would query and add them here.
// Since items are on the category pages, this sitemap is complete.

// 7. END XML
echo '</urlset>';

$db->close();
?>