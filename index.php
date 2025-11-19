<?php
/*
 * index.php
 * KitchCo: Cloud Kitchen Homepage
 * Version 2.8 - (UPDATED) Custom Sorting for Fan Favorites
 *
 * This is the main customer-facing homepage.
 */

// 1. PAGE SETUP
$page_title = $settings['hero_title'] ?? 'Pizza Mania - Hot & Fresh';
$meta_description = strip_tags($settings['hero_subtitle'] ?? 'Order the best pizza in town, delivered fast.');

// 2. HEADER
require_once('includes/header.php');

// Helper function to apply global discount
function calculate_discounted_price($original_price, $settings) {
    if (empty($settings['global_discount_active']) || $settings['global_discount_active'] == '0' || empty($settings['global_discount_value']) || $settings['global_discount_value'] <= 0) {
        return $original_price;
    }
    $discount_type = $settings['global_discount_type'];
    $discount_value = (float)$settings['global_discount_value'];
    $new_price = $original_price;
    if ($discount_type == 'percentage') {
        $new_price = $original_price - ($original_price * ($discount_value / 100));
    } elseif ($discount_type == 'fixed') {
        $new_price = $original_price - $discount_value;
    }
    return ($new_price > 0) ? $new_price : 0;
}


// 3. --- LOAD PAGE DATA ---

// --- A. Load Featured Items (Fan Favorites) ---
$featured_items = [];
// (MODIFIED) Added 'ORDER BY m.display_order ASC'
$sql_featured = "SELECT m.id, m.name, m.price, m.image, m.description, c.name as category_name
                 FROM menu_items m
                 JOIN categories c ON m.category_id = c.id
                 WHERE m.is_available = 1 AND m.is_featured = 1
                 ORDER BY m.display_order ASC, m.name ASC
                 LIMIT 8";
                 
$result_featured = $db->query($sql_featured);
if ($result_featured) {
    while ($row = $result_featured->fetch_assoc()) {
        $original_price = (float)$row['price'];
        $discounted_price = calculate_discounted_price($original_price, $settings);
        $row['original_price'] = $original_price;
        $row['price'] = $discounted_price;
        $row['has_discount'] = ($discounted_price < $original_price);
        $featured_items[] = $row;
    }
}

// --- B. Load Homepage Categories ---
$homepage_categories = [];
$sql_categories = "SELECT c.id, c.name, c.image, c.description, c.svg_icon
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

// --- C. Load Homepage Reviews ---
$reviews = [];
$sql_reviews = "SELECT * FROM admin_reviews WHERE is_visible = 1 ORDER BY id DESC LIMIT 3";
$result_reviews = $db->query($sql_reviews);
if ($result_reviews) {
    while ($row = $result_reviews->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// --- D. Load FAQ Data ---
$faqs = [];
$sql_faq = "SELECT * FROM faq WHERE is_visible = 1 ORDER BY display_order ASC";
$result_faq = $db->query($sql_faq);
if ($result_faq) {
    while ($row = $result_faq->fetch_assoc()) {
        $faqs[] = $row;
    }
}


// Helper function to render stars
function render_stars($rating) {
    $html = '<div class="flex text-yellow-400">';
    for ($i = 0; $i < 5; $i++) {
        if ($i < $rating) {
            $html .= '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
        }
    }
    $html .= '</div>';
    return $html;
}


// 4. --- Schema.org JSON-LD for Restaurant ---
$schema_restaurant = [
    '@context' => 'https://schema.org',
    '@type' => 'Restaurant',
    'name' => $settings['store_name'] ?? 'Pizza Mania',
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

<!-- Section 1: Hero Banner -->
<section class="py-16 md:py-24 -mt-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
        
        <div class="flex flex-col text-center lg:text-left">
            <div class="order-1">
                <span class="inline-block px-4 py-1 bg-brand-red text-white text-sm font-semibold rounded-full uppercase tracking-wider">
                    Hot & Fresh
                </span>
            </div>

            <div class="flex items-center justify-center order-2 lg:hidden mt-6">
                <?php
                $style = $settings['hero_image_style'] ?? 'shadow';
                $image_classes = 'transition-transform w-full h-auto object-cover';
                $image_style = '';
                if ($style == 'shadow') { $image_classes .= ' rounded-3xl shadow-2xl transform'; }
                elseif ($style == 'card') {
                    $image_classes .= ' rounded-3xl shadow-2xl p-4 md:p-6';
                    $card_color = $settings['hero_image_card_color'] ?? '#FFFFFF';
                    $image_style = 'background-color: ' . e($card_color) . ';';
                } elseif ($style == 'tilt-no-shadow') { $image_classes .= ' rounded-3xl transform'; }
                else { $image_classes .= ' rounded-3xl'; }
                ?>
                <img 
                    src="<?php echo e(BASE_URL . ($settings['hero_image_url'] ?? 'https://placehold.co/600x600/FFB000/000000?text=Pizza+Mania')); ?>"
                    alt="Delicious Pizza"
                    class="<?php echo $image_classes; ?>"
                    style="<?php echo $image_style; ?>"
                    onerror="this.src='https://placehold.co/600x600/EFEFEF/AAAAAA?text=Pizza+Image'">
            </div>

            <div class="order-3">
                <h1 class="text-4xl lg:text-6xl font-extrabold text-gray-900 mt-4 leading-tight font-bangla">
                    <?php echo e($settings['hero_title'] ?? 'The Best Pizza in Town'); ?>
                </h1>
            </div>

            <div class="order-4 mt-6 text-lg text-gray-600 font-bangla">
                <?php echo strip_tags(
                    $settings['hero_subtitle'] ?? '<p>Hand-tossed dough, fresh ingredients, and lightning-fast delivery. What are you waiting for?</p>',
                    '<p><b><i><strong>'
                ); ?>
            </div>

            <div class="order-5 mt-10">
                <a href="<?php echo BASE_URL; ?>/menu" class="px-10 py-4 bg-brand-red text-white text-lg font-bold rounded-lg shadow-lg hover:bg-red-700 transition-colors transform hover:scale-105">
                    Order Now
                </a>
            </div>
        </div>
        
        <div class="hidden lg:flex items-center justify-center">
            <?php
                $style = $settings['hero_image_style'] ?? 'shadow';
                $image_classes = 'transition-transform w-full h-auto object-cover';
                $image_style = '';
                if ($style == 'shadow') { $image_classes .= ' rounded-3xl shadow-2xl transform lg:rotate-6 hover:rotate-0'; }
                elseif ($style == 'card') {
                    $image_classes .= ' rounded-3xl shadow-2xl p-4 md:p-6';
                    $card_color = $settings['hero_image_card_color'] ?? '#FFFFFF';
                    $image_style = 'background-color: ' . e($card_color) . ';';
                } elseif ($style == 'tilt-no-shadow') { $image_classes .= ' rounded-3xl transform lg:rotate-6 hover:rotate-0'; }
                else { $image_classes .= ' rounded-3xl'; }
            ?>
            <img 
                src="<?php echo e(BASE_URL . ($settings['hero_image_url'] ?? 'https://placehold.co/600x600/FFB000/000000?text=Pizza+Mania')); ?>"
                alt="Delicious Pizza"
                class="<?php echo $image_classes; ?>"
                style="<?php echo $image_style; ?>"
                onerror="this.src='https://placehold.co/600x600/EFEFEF/AAAAAA?text=Pizza+Image'">
        </div>
    </div>
</section>

<!-- Section 2: Shop by Category -->
<?php if (!empty($homepage_categories)): ?>
<section class="py-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Explore Our Menu</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-<?php echo count($homepage_categories); ?> gap-6">
            <?php foreach ($homepage_categories as $category): ?>
                <a href="<?php echo BASE_URL; ?>/menu#category-<?php echo e($category['id']); ?>" class="block bg-white p-6 rounded-2xl shadow-lg transform transition-all hover:shadow-xl hover:-translate-y-1">
                    <div class="flex items-center justify-center w-16 h-16 bg-red-100 rounded-full text-brand-red mx-auto">
                        
                        <?php if (!empty($category['svg_icon'])): ?>
                            <?php echo $category['svg_icon']; ?>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 00.495-7.467 5.99 5.99 0 00-1.925 3.546 5.974 5.974 0 01-2.133-1.001A3.75 3.75 0 0012 18z" />
                            </svg>
                        <?php endif; ?>

                    </div>
                    <h3 class="mt-4 text-xl font-bold text-gray-900 text-center"><?php echo e($category['name']); ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- NEW OFFER SECTION -->
<?php if (!empty($settings['offer_is_active']) && $settings['offer_is_active'] == '1'): ?>
<section class="py-16">
    <div class="bg-red-50 border-l-8 border-brand-red rounded-2xl shadow-lg p-8 md:p-12 flex flex-col md:flex-row justify-between items-center gap-6">
        <div class="text-center md:text-left">
            <h2 class="text-3xl font-bold text-gray-900 font-bangla"><?php echo e($settings['offer_title']); ?></h2>
            <p class="text-xl text-gray-700 mt-2 font-bangla">
                <?php echo e($settings['offer_text']); ?>
            </p>
        </div>
        <div class="flex-shrink-0">
            <a href="<?php echo BASE_URL; ?>/menu" class="px-8 py-4 bg-brand-red text-white text-lg font-bold rounded-lg shadow-lg hover:bg-red-700 transition-colors">
                Order Now
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Section 3: Fan Favorites -->
<?php if (!empty($featured_items)): ?>
<section class="py-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Fan Favorites</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
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
                            <p class="text-2xl font-bold text-gray-900">
                                <?php if ($item['has_discount']): ?>
                                    <?php echo number_format($item['price'], 0); ?>
                                    <span class="text-sm font-normal text-gray-500 line-through ml-1"><?php echo number_format($item['original_price'], 0); ?></span>
                                <?php else: ?>
                                    <?php echo number_format($item['price'], 0); ?>
                                <?php endif; ?>
                                <span class="text-sm font-normal">BDT</span>
                            </p>
                            <a href="<?php echo BASE_URL; ?>/menu#item-<?php echo e($item['id']); ?>"
                               class="px-4 py-2 bg-brand-red text-white font-bold rounded-lg transition-all duration-200 hover:bg-red-700">
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


<!-- REVIEW SECTION -->
<?php if (!empty($reviews)): ?>
<section class="py-16">
    <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">What Our Customers Say</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <?php foreach ($reviews as $review): ?>
        <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-brand-red">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-lg">
                    <?php echo e(strtoupper(substr($review['customer_name'], 0, 2))); ?>
                </div>
                <div>
                    <div class="font-bold text-gray-900 font-bangla"><?php echo e($review['customer_name']); ?></div>
                    <?php echo render_stars($review['rating']); ?>
                </div>
            </div>
            <p class="text-gray-600 mt-4 italic font-bangla">"<?php echo nl2br(e($review['review_text'])); ?>"</p>
            <div class="flex items-center mt-4">
                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                <span class="ml-2 text-sm font-medium text-green-600">Verified Customer</span>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

    <div class="text-center mt-12">
        <a href="<?php echo BASE_URL; ?>/reviews" class="px-8 py-3 bg-white text-brand-red font-semibold rounded-lg shadow-md hover:bg-gray-50 border border-gray-200 transition-colors">
            See All Reviews
        </a>
    </div>
</section>
<?php endif; ?>


<!-- "HOW TO ORDER" SECTION -->
<section class="py-16">
    <div class="bg-white rounded-2xl shadow-lg p-8 md:p-12">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">How to Order in 4 Easy Steps</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            
            <div class="text-center">
                <div class="flex items-center justify-center w-20 h-20 bg-brand-red text-white rounded-full mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900">1. Browse the Menu</h3>
                <p class="mt-1 text-gray-600">Find your favorite pizza, pasta, and meat boxes from our full menu.</p>
            </div>
            
            <div class="text-center">
                <div class="flex items-center justify-center w-20 h-20 bg-brand-red text-white rounded-full mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900">2. Add to Cart</h3>
                <p class="mt-1 text-gray-600">Click "Add" and select any options you want, like extra cheese!</p>
            </div>
            
            <div class="text-center">
                <div class="flex items-center justify-center w-20 h-20 bg-brand-red text-white rounded-full mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 00-2.121-1.58H6.881a2.25 2.25 0 00-2.12 1.58L2.35 13.177a2.25 2.25 0 00-.1.661z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900">3. Checkout</h3>
                <p class="mt-1 text-gray-600">Enter your name, phone, and select your delivery area to confirm.</p>
            </div>
            
            <div class="text-center">
                <div class="flex items-center justify-center w-20 h-20 bg-brand-red text-white rounded-full mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-xl font-bold text-gray-900">4. Track Your Order</h3>
                <p class="mt-1 text-gray-600">Use the "Track Order" page with your Order ID to see its status in real-time.</p>
            </div>
        </div>
    </div>
</section>


<!-- FAQ SECTION -->
<?php if (!empty($faqs)): ?>
<section class="py-16">
    <div class="max-w-3xl mx-auto">
        <h2 class="text-3xl font-bold text-gray-900 mb-10 text-center">Frequently Asked Questions</h2>
        
        <div class="space-y-6">
            <?php foreach ($faqs as $faq): ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-brand-red">
                <h3 class="text-xl font-bold text-gray-900 font-bangla">
                    <?php echo e($faq['question']); ?>
                </h3>
                <p class="text-gray-700 mt-2 font-bangla">
                    <?php echo nl2br(e($faq['answer'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>


<?php
// 5. FOOTER
require_once('includes/footer.php');
?>