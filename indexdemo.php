<?php
/*
 * index_pizzamania_demo.php
 * "Pizza Mania" themed homepage
 * Version 1.0
 *
 * This is a new theme for your homepage.
 * It uses the *exact same* PHP variables as your old index.php,
 * so it connects perfectly to your admin panel.
 */

// 1. PAGE SETUP
$page_title = $settings['hero_title'] ?? 'Pizza Mania - Hot & Fresh';
$meta_description = strip_tags($settings['hero_subtitle'] ?? 'Order the best pizza in town, delivered fast.');

// 2. HEADER
require_once('includes/header.php');

// 3. --- LOAD PAGE DATA ---

// --- A. Load Featured Items ---
$featured_items = [];
$sql_featured = "SELECT m.id, m.name, m.price, m.image, c.name as category_name
                 FROM menu_items m
                 JOIN categories c ON m.category_id = c.id
                 WHERE m.is_available = 1 AND m.is_featured = 1
                 ORDER BY m.name ASC
                 LIMIT 8";
                 
$result_featured = $db->query($sql_featured);
if ($result_featured) {
    while ($row = $result_featured->fetch_assoc()) {
        $featured_items[] = $row;
    }
}

// --- B. Load Homepage Categories ---
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
    NEW "PIZZA MANIA" THEME
=========================================
-->

<!-- Section 1: Hero Banner -->
<section class="relative bg-gray-900 h-[60vh] min-h-[400px] flex items-center justify-center text-center -mt-8 shadow-lg">
    <!-- Background Image (From Admin Panel) -->
    <!-- This new design uses a dark, semi-transparent overlay -->
    <div class="absolute inset-0">
        <img src="<?php echo e(BASE_URL . ($settings['hero_image_url'] ?? 'https://placehold.co/1600x700/222222/555555?text=Pizza+Mania+Banner')); ?>" 
             alt="Pizza Mania" 
             class="w-full h-full object-cover"
             onerror="this.src='https://placehold.co/1600x700/222222/555555?text=Pizza+Mania+Banner'">
        <div class="absolute inset-0 bg-black bg-opacity-60"></div>
    </div>
         
    <!-- Content (From Admin Panel) -->
    <div class="relative max-w-3xl mx-auto px-6 z-10">
        <h1 class="text-4xl lg:text-6xl font-extrabold text-white shadow-lg leading-tight">
            <!-- This variable comes from admin/site_settings.php -->
            <?php echo e($settings['hero_title'] ?? 'Welcome to Pizza Mania'); ?>
        </h1>
        <div class="mt-6 text-xl text-yellow-400 font-medium shadow-lg">
            <!-- This variable also comes from admin/site_settings.php -->
            <?php echo strip_tags(
                $settings['hero_subtitle'] ?? '<p>Hot, fresh pizza delivered to your door.</p>',
                '<p><b><i>' // Whitelist of safe tags
            ); ?>
        </div>
        <div class="mt-10">
            <a href="<?php echo BASE_URL; ?>/menu" class="px-10 py-4 bg-brand-red text-white text-lg font-bold rounded-full shadow-lg hover:bg-red-700 transition-colors transform hover:scale-105">
                Order Now
            </a>
        </div>
    </div>
</section>

<!-- Section 2: Featured Items -->
<?php if (!empty($featured_items)): ?>
<section class="py-16 bg-slate-100">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Our Featured Pizzas</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <!-- This loop pulls from "Featured Items" in the admin panel -->
            <?php foreach ($featured_items as $item): ?>
                <div class="bg-white rounded-lg shadow-xl overflow-hidden transform transition-all hover:scale-105 hover:shadow-2xl">
                    <a href="<?php echo BASE_URL; ?>/menu#item-<?php echo e($item['id']); ?>" class="block">
                        <img 
                            src="<?php echo e(BASE_URL . ($item['image'] ?? 'https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                            alt="<?php echo e($item['name']); ?>" 
                            class="w-full h-48 object-cover"
                            onerror="this.src='https://placehold.co/400x300/EFEFEF/AAAAAA?text=No+Image'"
                        >
                    </a>
                    <div class="p-6">
                        <div class="text-sm font-semibold text-brand-red mb-1 uppercase"><?php echo e($item['category_name']); ?></div>
                        <h3 class="text-xl font-bold text-gray-900 truncate"><?php echo e($item['name']); ?></h3>
                        <p class="text-2xl font-bold text-gray-800 mt-2"><?php echo e(number_format($item['price'], 2)); ?> BDT</p>
                        <a href="<?php echo BASE_URL; ?>/menu#item-<?php echo e($item['id']); ?>" class="mt-4 block w-full text-center px-4 py-3 bg-gray-900 text-white font-semibold rounded-lg hover:bg-gray-700 transition-colors">
                            Add to Cart
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- Section 3: Shop by Category -->
<?php if (!empty($homepage_categories)): ?>
<section class="py-16 bg-gray-900">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-white mb-10 text-center">Shop by Category</h2>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6 justify-center">
            <!-- This loop pulls from the "Homepage Section Manager" in the admin panel -->
            <?php foreach ($homepage_categories as $category): ?>
                <a href="<?php echo BASE_URL; ?>/menu/category/<?php echo e($category['id']); ?>" class="block group text-center">
                    <div class="relative w-32 h-32 lg:w-40 lg:h-40 mx-auto rounded-full shadow-lg overflow-hidden transform transition-transform group-hover:scale-105 border-4 border-gray-700 group-hover:border-yellow-400">
                        <img 
                            src="<?php echo e(BASE_URL . ($category['image'] ?? 'https://placehold.co/200x200/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                            alt="<?php echo e($category['name']); ?>" 
                            class="w-full h-full object-cover"
                            onerror="this.src='https://placehold.co/200x200/EFEFEF/AAAAAA?text=No+Image'"
                        >
                        <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-20 transition-all"></div>
                    </div>
                    <h3 class="mt-4 text-lg font-bold text-white group-hover:text-yellow-400">
                        <?php echo e($category['name']); ?>
                    </h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<?php
// 5. FOOTER
// This closes the <body> and <html> tags
require_once('includes/footer.php');
?>