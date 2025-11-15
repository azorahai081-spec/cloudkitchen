<?php
/*
 * index.php
 * KitchCo: Cloud Kitchen Homepage
 * Version 1.1 - Added Restaurant Schema.org
 *
 * This is the main customer-facing homepage.
 * It pulls all its content from the CMS tables:
 * 1. Hero Content (`site_settings`)
 * 2. Featured Items (`menu_items` where `is_featured` = 1)
 * 3. Homepage Categories (`homepage_sections`)
 */

// 1. PAGE SETUP
$page_title = $settings['hero_title'] ?? 'Welcome to KitchCo';
$meta_description = strip_tags($settings['hero_subtitle'] ?? 'Order delicious food online.');

// 2. HEADER
// This will include config.php, session_start(), db connection, and the nav bar
require_once('includes/header.php');

// 3. --- LOAD PAGE DATA ---

// --- A. Load Featured Items ---
$featured_items = [];
$sql_featured = "SELECT m.id, m.name, m.price, m.image, c.name as category_name
                 FROM menu_items m
                 JOIN categories c ON m.category_id = c.id
                 WHERE m.is_available = 1 AND m.is_featured = 1
                 ORDER BY m.name ASC
                 LIMIT 8"; // Show max 8 featured items
                 
$result_featured = $db->query($sql_featured);
if ($result_featured) {
    while ($row = $result_featured->fetch_assoc()) {
        $featured_items[] = $row;
    }
}

// --- B. Load Homepage Categories ---
// This list is controlled from `homepage_manager.php` in the admin panel
$homepage_categories = [];
$sql_categories = "SELECT c.id, c.name, c.image, c.description
                   FROM homepage_sections hs
                   JOIN categories c ON hs.category_id = c.id
                   WHERE hs.is_visible = 1 AND c.is_visible = 1
                   ORDER BY hs.display_order ASC";

$result_categories = $db->query($sql_categories);
if ($result_categories) {
    while ($row = $result_categories->fetch_assoc()) {
        $homepage_categories[] = $row;
    }
}

// 4. --- (NEW) Schema.org JSON-LD for Restaurant ---
$schema_restaurant = [
    '@context' => 'https://schema.org',
    '@type' => 'Restaurant',
    'name' => 'KitchCo',
    'image' => BASE_URL . ($settings['hero_image_url'] ?? ''),
    'description' => $meta_description,
    'servesCuisine' => 'Bengali, Indian, Fast Food', // Generic, can be dynamic
    'priceRange' => '$$',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Gulshan', // Placeholder
        'addressLocality' => 'Dhaka',
        'postalCode' => '1212', // Placeholder
        'addressCountry' => 'BD'
    ],
    'telephone' => '+8801234567890' // Placeholder
];
?>

<!-- (NEW) Schema.org Script -->
<script type="application/ld+json">
<?php echo json_encode($schema_restaurant, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<!-- Section 1: Hero Banner -->
<section class="relative bg-gray-900 rounded-2xl shadow-lg overflow-hidden -mt-4">
    <!-- Background Image -->
    <img src="<?php echo e(BASE_URL . ($settings['hero_image_url'] ?? 'https://placehold.co/1600x600/333333/aaaaaa?text=KitchCo+Banner')); ?>" 
         alt="KitchCo food banner" 
         class="absolute inset-0 w-full h-full object-cover opacity-50"
         onerror="this.src='https://placehold.co/1600x600/333333/aaaaaa?text=KitchCo+Banner'">
         
    <!-- Content -->
    <div class="relative max-w-3xl mx-auto text-center py-24 px-6 lg:py-32">
        <h1 class="text-4xl lg:text-5xl font-bold text-white shadow-lg">
            <?php echo e($settings['hero_title'] ?? 'Welcome to KitchCo'); ?>
        </h1>
        <div class="mt-6 text-lg text-gray-200 shadow-lg">
            <!-- (MODIFIED) Render HTML content from TinyMCE -->
            <!-- (FIXED) Added strip_tags to prevent Stored XSS -->
            <?php echo strip_tags(
                $settings['hero_subtitle'] ?? '<p>Delicious, fresh meals delivered to your door.</p>',
                '<p><b><i><u><strong><ul><ol><li>' // Whitelist of safe tags
            ); ?>
        </div>
        <div class="mt-10">
            <a href="menu.php" class="px-8 py-3 bg-brand-orange text-white text-lg font-medium rounded-lg shadow-md hover:bg-orange-700 transition-colors">
                Order Now
            </a>
        </div>
    </div>
</section>

<!-- Section 2: Featured Items -->
<?php if (!empty($featured_items)): ?>
<section class="mt-16">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Our Featured Items</h2>
    
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <?php foreach ($featured_items as $item): ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden transition-all hover:shadow-xl">
                <a href="menu.php#item-<?php echo e($item['id']); ?>" class="block">
                    <img 
                        src="<?php echo e(BASE_URL . ($item['image'] ?? 'https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                        alt="<?php echo e($item['name']); ?>" 
                        class="w-full h-48 object-cover"
                        onerror="this.src='https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image'"
                    >
                </a>
                <div class="p-5">
                    <div class="text-sm text-gray-500 mb-1"><?php echo e($item['category_name']); ?></div>
                    <h3 class="text-lg font-bold text-gray-900 truncate"><?php echo e($item['name']); ?></h3>
                    <p class="text-xl font-bold text-brand-orange mt-2"><?php echo e(number_format($item['price'], 2)); ?> BDT</p>
                    <a href="menu.php#item-<?php echo e($item['id']); ?>" class="mt-4 block w-full text-center px-4 py-2 bg-gray-100 text-gray-800 font-medium rounded-lg hover:bg-gray-200">
                        View Item
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


<!-- Section 3: Shop by Category -->
<?php if (!empty($homepage_categories)): ?>
<section class="mt-16">
    <h2 class="text-3xl font-bold text-gray-900 mb-6">Shop by Category</h2>
    
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6">
        <?php foreach ($homepage_categories as $category): ?>
            <a href="menu.php?category=<?php echo e($category['id']); ?>" class="block group text-center">
                <div class="relative w-32 h-32 lg:w-40 lg:h-40 mx-auto rounded-full shadow-lg overflow-hidden transform transition-transform group-hover:scale-105">
                    <img 
                        src="<?php echo e(BASE_URL . ($category['image'] ?? 'https://placehold.co/200x200/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                        alt="<?php echo e($category['name']); ?>" 
                        class="w-full h-full object-cover"
                        onerror="this.src='https://placehold.co/200x200/EFEFEF/AAAAAA?text=No+Image'"
                    >
                    <div class="absolute inset-0 bg-black bg-opacity-30 group-hover:bg-opacity-10 transition-all"></div>
                </div>
                <h3 class="mt-4 text-lg font-bold text-gray-900 group-hover:text-brand-orange">
                    <?php echo e($category['name']); ?>
                </h3>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


<?php
// 5. FOOTER
// This closes the <body> and <html> tags
require_once('includes/footer.php');
?>