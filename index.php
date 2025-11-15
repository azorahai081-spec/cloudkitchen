<?php
/*
 * index.php
 * KitchCo: Cloud Kitchen Homepage
 * Version 2.0 - "Pizza Mania" (Bright) Theme
 *
 * This is the main customer-facing homepage.
 * It pulls all its content from the CMS tables:
 * 1. Hero Content (`site_settings`)
 * 2. Featured Items (`menu_items` where `is_featured` = 1)
 * 3. Homepage Categories (`homepage_sections`)
 */

// 1. PAGE SETUP
$page_title = $settings['hero_title'] ?? 'Pizza Mania - Hot & Fresh';
$meta_description = strip_tags($settings['hero_subtitle'] ?? 'Order the best pizza in town, delivered fast.');

// 2. HEADER
// This will include config.php, session_start(), db connection, and the nav bar
require_once('includes/header.php');

// 3. --- LOAD PAGE DATA ---

// --- A. Load Featured Items ---
$featured_items = [];
$sql_featured = "SELECT m.id, m.name, m.price, m.image, m.description, c.name as category_name
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

// 4. --- Schema.org JSON-LD for Restaurant ---
$schema_restaurant = [
    '@context' => 'https://schema.org',
    '@type' => 'Restaurant',
    'name' => 'Pizza Mania', // Updated
    'image' => BASE_URL . ($settings['hero_image_url'] ?? ''),
    'description' => $meta_description,
    'servesCuisine' => 'Pizza, Italian, Fast Food',
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

<!-- Schema.org Script -->
<script type="application/ld+json">
<?php echo json_encode($schema_restaurant, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>


<!-- 
=========================================
    "PIZZA MANIA" THEME (BRIGHT)
=========================================
-->

<!-- Section 1: Hero Banner (Split Layout) -->
<!-- Note: The <main> tag is opened in header.php -->
<section class="py-16 md:py-24 -mt-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        <!-- Left Column: Content -->
        <div class="text-center lg:text-left">
            <span class="inline-block px-4 py-1 bg-brand-red text-white text-sm font-semibold rounded-full uppercase tracking-wider">
                Hot & Fresh
            </span>
            <h1 class="text-4xl lg:text-6xl font-extrabold text-gray-900 mt-4 leading-tight">
                <!-- This pulls from Admin Panel -->
                <?php echo e($settings['hero_title'] ?? 'The Best Pizza in Town'); ?>
            </h1>
            <div class="mt-6 text-lg text-gray-600">
                <!-- This pulls from Admin Panel (CKEditor) -->
                <?php echo strip_tags(
                    $settings['hero_subtitle'] ?? '<p>Hand-tossed dough, fresh ingredients, and lightning-fast delivery. What are you waiting for?</p>',
                    '<p><b><i><strong>' // Whitelist of safe tags
                ); ?>
            </div>
            <div class="mt-10">
                <a href="<?php echo BASE_URL; ?>/menu" class="px-10 py-4 bg-brand-red text-white text-lg font-bold rounded-lg shadow-lg hover:bg-red-700 transition-colors transform hover:scale-105">
                    Order Now
                </a>
            </div>
        </div>
        
        <!-- Right Column: Image (From Admin Panel) -->
        <div class="flex items-center justify-center">
            <img 
                src="<?php echo e(BASE_URL . ($settings['hero_image_url'] ?? 'https://placehold.co/600x600/FFB000/000000?text=Pizza+Mania')); ?>"
                alt="Delicious Pizza"
                class="rounded-3xl shadow-2xl transform lg:rotate-6 transition-transform hover:rotate-0"
                onerror="this.src='https://placehold.co/600x600/EFEFEF/AAAAAA?text=Pizza+Image'">
        </div>
    </div>
</section>

<!-- Section 2: Shop by Category -->
<?php if (!empty($homepage_categories)): ?>
<section class="py-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Explore Our Menu</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <!-- This loop pulls from "Homepage Section Manager" in Admin Panel -->
            <?php foreach ($homepage_categories as $category): ?>
                <a href="<?php echo BASE_URL; ?>/menu/category/<?php echo e($category['id']); ?>" class="block bg-white p-6 rounded-2xl shadow-lg transform transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="flex items-center justify-center w-16 h-16 bg-brand-red rounded-full text-white mx-auto">
                        <!-- Generic Icon: You can replace this with unique icons if you add an 'icon' field to your categories table -->
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-xl font-bold text-gray-900 text-center"><?php echo e($category['name']); ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section 3: Featured Items -->
<?php if (!empty($featured_items)): ?>
<section class="py-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Fan Favorites</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- This loop pulls from "Featured Items" in Admin Panel -->
            <?php foreach ($featured_items as $item): ?>
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden transform transition-all hover:shadow-xl hover:-translate-y-1">
                    <a href="<?php echo BASE_URL; ?>/menu#item-<?php echo e($item['id']); ?>" class="block">
                        <img 
                            src="<?php echo e(BASE_URL . ($item['image'] ?? 'https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                            alt="<?php echo e($item['name']); ?>" 
                            class="w-full h-48 object-cover"
                            onerror="this.src='https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image'">
                    </a>
                    <div class="p-5">
                        <h3 class="text-xl font-bold text-gray-900 truncate"><?php echo e($item['name']); ?></h3>
                        <p class="text-gray-600 text-sm mt-1 h-10 overflow-hidden"><?php echo e($item['description']); ?></p>
                        <div class="flex justify-between items-center mt-4">
                            <p class="text-2xl font-bold text-gray-900"><?php echo e(number_format($item['price'], 2)); ?> <span class="text-sm font-normal">BDT</span></p>
                            <!-- This link goes to the menu page and highlights the item -->
                            <a href="<?php echo BASE_URL; ?>/menu#item-<?php echo e($item['id']); ?>" class="px-4 py-2 bg-brand-yellow text-gray-900 font-bold rounded-lg hover:bg-yellow-500 transition-colors">
                                Add
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
<!-- The <main> tag is closed in footer.php -->

<?php
// 5. FOOTER
// This closes the <body> and <html> tags
require_once('includes/footer.php');
?>