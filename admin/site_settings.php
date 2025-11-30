<?php
/*
 * admin/site_settings.php
 * PizzaMania: Cloud Kitchen Site & Store Settings
 * Version 2.4 - (UPDATED) Added Marquee Animation Type
 *
 * This is an ADMIN-ONLY page.
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. PAGE VARIABLES & INITIALIZATION
$page_title = 'Site & Store Settings';
$error_message = '';
$success_message = '';

// The $settings array is already loaded from config.php

// 4. --- HANDLE POST REQUESTS (Update Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // Handle the array of exempt areas
        $exempt_areas = $_POST['night_surcharge_exempt_areas'] ?? [];
        $exempt_areas_string = implode(',', $exempt_areas);

        $new_settings = [
            'store_name' => $_POST['store_name'],
            'hero_title' => $_POST['hero_title'],
            'hero_subtitle' => $_POST['hero_subtitle'],
            'night_surcharge_amount' => (int)$_POST['night_surcharge_amount'],
            'night_surcharge_start_hour' => $_POST['night_surcharge_start_hour'],
            'night_surcharge_end_hour' => $_POST['night_surcharge_end_hour'],
            'night_surcharge_exempt_areas' => $exempt_areas_string,
            
            'timezone' => $_POST['timezone'],
            'global_discount_type' => $_POST['global_discount_type'],
            'global_discount_value' => (int)($_POST['global_discount_value'] ?? 0),
            'global_discount_active' => isset($_POST['global_discount_active']) ? '1' : '0',
            'hero_image_style' => $_POST['hero_image_style'],
            'hero_image_card_color' => $_POST['hero_image_card_color'],
            'offer_is_active' => isset($_POST['offer_is_active']) ? '1' : '0',
            'offer_title' => $_POST['offer_title'],
            'offer_text' => $_POST['offer_text'],
            'free_delivery_active' => isset($_POST['free_delivery_active']) ? '1' : '0',
            'delivery_discount_active' => isset($_POST['delivery_discount_active']) ? '1' : '0',
            'delivery_discount_percentage' => $_POST['delivery_discount_percentage'] ?? '0',
            
            // (NEW) Marquee Settings
            'marquee_text' => $_POST['marquee_text'] ?? 'SALE % SALE',
            'marquee_is_active' => isset($_POST['marquee_is_active']) ? '1' : '0',
            'marquee_animation' => $_POST['marquee_animation'] ?? 'scroll' // New setting
        ];
        
        // --- START IMAGE UPLOAD LOGIC (for Hero Banner) ---
        $current_image = $settings['hero_image_url'];
        $image_path = $current_image; // Default to current

        if (isset($_FILES['hero_image_url']) && $_FILES['hero_image_url']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/banners/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['hero_image_url'];
            $file_name = 'hero_banner_' . time() . '_' . basename($file['name']);
            $target_path = $upload_dir . $file_name;
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowed_types)) {
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $image_path = '/uploads/banners/' . $file_name;
                    if (!empty($current_image) && $current_image != '/uploads/default-banner.jpg' && file_exists('..' . $current_image)) {
                        unlink('..' . $current_image);
                    }
                } else {
                    $error_message = 'Failed to move uploaded banner.';
                }
            } else {
                $error_message = 'Invalid file type for banner. Please upload a JPG, PNG, GIF, or WebP.';
            }
        }
        $new_settings['hero_image_url'] = $image_path;
        // --- END IMAGE UPLOAD LOGIC ---
        
        
        // --- DATABASE UPDATE ---
        $sql = "UPDATE site_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $db->prepare($sql);
        
        if (!$stmt) {
            $error_message = "Error preparing statement: " . $db->error;
        } else {
            foreach ($new_settings as $key => $value) {
                if (array_key_exists($key, $settings) || isset($settings[$key])) { 
                    $stmt->bind_param('ss', $value, $key);
                    if (!$stmt->execute()) {
                         $error_message = "Error updating setting: $key";
                    }
                } else {
                    $insert_sql = "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)";
                    $insert_stmt = $db->prepare($insert_sql);
                    $insert_stmt->bind_param('ss', $key, $value);
                    $insert_stmt->execute();
                    $insert_stmt->close();
                }
            }
            $stmt->close();
        }

        if (empty($error_message)) {
            $success_message = 'Settings updated successfully!';
            // Reload settings
            $settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");
            if ($settings_query) {
                while ($row = $settings_query->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
}

// 5. --- LOAD DATA FOR DROPDOWNS ---
$timezone_identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
$delivery_areas = [];
$area_result = $db->query("SELECT id, area_name FROM delivery_areas WHERE is_active = 1 ORDER BY area_name ASC");
while ($row = $area_result->fetch_assoc()) {
    $delivery_areas[] = $row;
}
$current_exempt_ids = explode(',', $settings['night_surcharge_exempt_areas'] ?? '');
?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_title); ?></h1>

<!-- Success & Error Messages -->
<?php if (!empty($success_message)): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-700 rounded-lg">
        <?php echo e($success_message); ?>
    </div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-700 rounded-lg">
        <?php echo e($error_message); ?>
    </div>
<?php endif; ?>

<form action="site_settings.php" method="POST" enctype="multipart/form-data" class="space-y-12">
    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

    <!-- Section 1: Homepage Content -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Homepage Content (CMS)</h2>
        <div class="space-y-6">
            
            <div>
                <label for="store_name" class="block text-sm font-medium text-gray-700">Store Name (Brand)</label>
                <input type="text" id="store_name" name="store_name" 
                       value="<?php echo e($settings['store_name'] ?? 'Pizza Mania'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">This will appear on receipts and the site header.</p>
            </div>

            <!-- Marquee Settings -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Notice Bar (Marquee)</h3>
                <div class="flex items-center mb-4">
                    <input type="checkbox" id="marquee_is_active" name="marquee_is_active" value="1" 
                           <?php echo (($settings['marquee_is_active'] ?? '0') == '1') ? 'checked' : ''; ?>
                           class="h-5 w-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="marquee_is_active" class="ml-2 block text-sm font-medium text-gray-900">
                        Enable Notice Bar
                    </label>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="marquee_text" class="block text-sm font-medium text-gray-700">Bar Text</label>
                        <input type="text" id="marquee_text" name="marquee_text" 
                               value="<?php echo e($settings['marquee_text'] ?? 'SALE % SALE'); ?>"
                               placeholder="e.g. SALE % SALE"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="marquee_animation" class="block text-sm font-medium text-gray-700">Animation</label>
                        <select id="marquee_animation" name="marquee_animation" 
                                class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="scroll" <?php echo (($settings['marquee_animation'] ?? 'scroll') == 'scroll') ? 'selected' : ''; ?>>Scrolling (Marquee)</option>
                            <option value="static" <?php echo (($settings['marquee_animation'] ?? 'scroll') == 'static') ? 'selected' : ''; ?>>Static (Centered)</option>
                        </select>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Choose "Scrolling" for long text or effects, "Static" for simple announcements.</p>
            </div>

            <div>
                <label for="hero_title" class="block text-sm font-medium text-gray-700">Homepage Title</label>
                <input type="text" id="hero_title" name="hero_title" 
                       value="<?php echo e($settings['hero_title'] ?? ''); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div>
                <label for="hero_subtitle" class="block text-sm font-medium text-gray-700">
                    Homepage Welcome Text / Subtitle
                </label>
                <textarea id="hero_subtitle" name="hero_subtitle" rows="6"
                          class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                ><?php echo htmlspecialchars($settings['hero_subtitle'] ?? ''); ?></textarea>
            </div>
            
            <div>
                <label for="hero_image_url" class="block text-sm font-medium text-gray-700">Homepage Banner Image</label>
                <input type="file" id="hero_image_url" name="hero_image_url" 
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200">
                
                <?php if (!empty($settings['hero_image_url'])): ?>
                    <div class="mt-4">
                        <img src="<?php echo e(BASE_URL . $settings['hero_image_url']); ?>" alt="Current Banner" class="w-auto h-32 object-cover rounded-lg shadow-md">
                        <p class="text-xs text-gray-500 mt-1">Current banner. Uploading a new one will replace it.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t">
                <div>
                    <label for="hero_image_style" class="block text-sm font-medium text-gray-700">Banner Image Style</label>
                    <select id="hero_image_style" name="hero_image_style" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="shadow" <?php echo (($settings['hero_image_style'] ?? 'shadow') == 'shadow') ? 'selected' : ''; ?>>Shadow & Tilt (Default)</option>
                        <option value="card" <?php echo (($settings['hero_image_style'] ?? 'shadow') == 'card') ? 'selected' : ''; ?>>Contained in Card</option>
                        <option value="tilt-no-shadow" <?php echo (($settings['hero_image_style'] ?? 'shadow') == 'tilt-no-shadow') ? 'selected' : ''; ?>>Tilt (No Shadow)</option>
                        <option value="none" <?php echo (($settings['hero_image_style'] ?? 'shadow') == 'none') ? 'selected' : ''; ?>>None (Simple Image)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">"Contained in Card" is best for transparent images.</p>
                </div>
                <div>
                    <label for="hero_image_card_color" class="block text-sm font-medium text-gray-700">Card Color (if 'Card' style)</label>
                    <input type="text" id="hero_image_card_color" name="hero_image_card_color" 
                           value="<?php echo e($settings['hero_image_card_color'] ?? '#FFFFFF'); ?>"
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <p class="text-xs text-gray-500 mt-1">Use a hex code (e.g., #FFFFFF for white).</p>
                </div>
            </div>

            <!-- Offer Banner Settings -->
            <div class="pt-6 border-t">
                <h3 class="text-lg font-bold text-gray-900">Offer Banner</h3>
                <p class="text-sm text-gray-500 mb-4">This will show a banner on the homepage, under the categories.</p>
                <div class="flex items-center mb-4">
                    <input type="checkbox" id="offer_is_active" name="offer_is_active" value="1" 
                           <?php echo (($settings['offer_is_active'] ?? '0') == '1') ? 'checked' : ''; ?>
                           class="h-5 w-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="offer_is_active" class="ml-2 block text-sm font-medium text-gray-900">
                        Enable Offer Banner
                    </label>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="offer_title" class="block text-sm font-medium text-gray-700">Offer Title</label>
                        <input type="text" id="offer_title" name="offer_title" 
                               value="<?php echo e($settings['offer_title'] ?? ''); ?>"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                    <div>
                        <label for="offer_text" class="block text-sm font-medium text-gray-700">Offer Text (Short)</label>
                        <input type="text" id="offer_text" name="offer_text" 
                               value="<?php echo e($settings['offer_text'] ?? ''); ?>"
                               placeholder="e.g., Get 20% off all Pizza. Use code: PIZZA20"
                               class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    </div>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Section 2: Store & Surcharge Settings -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Store & Surcharge Settings</h2>
        <p class="text-sm text-gray-500 mb-6">Store Open/Closed status is managed live on the **Live Dashboard** page.</p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
                <label for="night_surcharge_amount" class="block text-sm font-medium text-gray-700">Night Surcharge Amount (BDT)</label>
                <input type="number" step="1" id="night_surcharge_amount" name="night_surcharge_amount" 
                       value="<?php echo e((int)($settings['night_surcharge_amount'] ?? '0')); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            
            <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700">Store Timezone</label>
                <select id="timezone" name="timezone" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <?php foreach ($timezone_identifiers as $tz): ?>
                        <option value="<?php echo e($tz); ?>" <?php echo (($settings['timezone'] ?? 'UTC') == $tz) ? 'selected' : ''; ?>>
                            <?php echo e($tz); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label for="night_surcharge_start_hour" class="block text-sm font-medium text-gray-700">Surcharge Start (Hour 0-23)</label>
                <input type="number" min="0" max="23" id="night_surcharge_start_hour" name="night_surcharge_start_hour" 
                       value="<?php echo e($settings['night_surcharge_start_hour'] ?? '0'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">e.g., '0' for Midnight</p>
            </div>
            
            <div>
                <label for="night_surcharge_end_hour" class="block text-sm font-medium text-gray-700">Surcharge End (Hour 0-23)</label>
                <input type="number" min="0" max="23" id="night_surcharge_end_hour" name="night_surcharge_end_hour" 
                       value="<?php echo e($settings['night_surcharge_end_hour'] ?? '6'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">e.g., '6' for 6:00 AM</p>
            </div>
            
        </div>

        <!-- Improved Exempt Areas Selector -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-2">Exclude Areas from Night Surcharge</h3>
            <p class="text-sm text-gray-500 mb-4">Search and select areas where the night surcharge should <strong>NOT</strong> apply.</p>
            
            <div class="mb-3 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                    </svg>
                </div>
                <input type="text" id="exempt_area_search" placeholder="Type to filter areas..." 
                       class="block w-full pl-10 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent">
            </div>

            <div class="border border-gray-300 rounded-lg bg-gray-50 p-4 max-h-64 overflow-y-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" id="exempt_area_list">
                    <?php foreach ($delivery_areas as $area): ?>
                    <div class="flex items-center area-item" data-name="<?php echo strtolower(e($area['area_name'])); ?>">
                        <input type="checkbox" 
                               id="exempt_area_<?php echo $area['id']; ?>" 
                               name="night_surcharge_exempt_areas[]" 
                               value="<?php echo $area['id']; ?>"
                               <?php echo in_array($area['id'], $current_exempt_ids) ? 'checked' : ''; ?>
                               class="focus:ring-orange-500 h-4 w-4 text-orange-600 border-gray-300 rounded cursor-pointer">
                        <label for="exempt_area_<?php echo $area['id']; ?>" class="ml-3 text-sm font-medium text-gray-700 cursor-pointer select-none">
                            <?php echo e($area['area_name']); ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p id="no_areas_found" class="text-sm text-gray-500 text-center py-4 hidden">No areas match your search.</p>
            </div>
            <p class="text-xs text-gray-500 mt-2 text-right"><span id="exempt_count"><?php echo count($current_exempt_ids); ?></span> areas currently excluded.</p>
        </div>
    </div>
    
    <!-- Section 3: Global Discount Settings -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Global Promotions</h2>
        <p class="text-sm text-gray-500 mb-6">Apply store-wide discounts. These stack with coupons.</p>

        <!-- Item Discount -->
        <h3 class="text-lg font-bold text-gray-900">Item Discount</h3>
        <p class="text-sm text-gray-500 mb-4">Apply a discount to ALL menu items. This is calculated *before* cart-level coupons.</p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div>
                <label for="global_discount_type" class="block text-sm font-medium text-gray-700">Discount Type</label>
                <select id="global_discount_type" name="global_discount_type" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="none" <?php echo (($settings['global_discount_type'] ?? 'none') == 'none') ? 'selected' : ''; ?>>None</option>
                    <option value="percentage" <?php echo (($settings['global_discount_type'] ?? 'none') == 'percentage') ? 'selected' : ''; ?>>Percentage (%)</option>
                    <option value="fixed" <?php echo (($settings['global_discount_type'] ?? 'none') == 'fixed') ? 'selected' : ''; ?>>Fixed (BDT)</option>
                </select>
            </div>
            
            <div>
                <label for="global_discount_value" class="block text-sm font-medium text-gray-700">Discount Value</label>
                <input type="number" step="1" id="global_discount_value" name="global_discount_value" 
                       value="<?php echo e((int)($settings['global_discount_value'] ?? '0')); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            
            <div class="flex items-center justify-center">
                <div class="flex items-center mt-6">
                    <input type="checkbox" id="global_discount_active" name="global_discount_active" value="1" 
                           <?php echo (($settings['global_discount_active'] ?? '0') == '1') ? 'checked' : ''; ?>
                           class="h-5 w-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="global_discount_active" class="ml-2 block text-sm font-medium text-gray-900">
                        Enable Item Discount
                    </label>
                </div>
            </div>
        </div>

        <!-- Delivery Discount -->
        <div class="pt-6 border-t mt-6">
            <h3 class="text-lg font-bold text-gray-900">Delivery Discount</h3>
            <p class="text-sm text-gray-500 mb-4">Apply a discount to the delivery fee. "Free Delivery" will override the percentage discount.</p>
            
            <div class="flex items-center mt-2">
                <input type="checkbox" id="free_delivery_active" name="free_delivery_active" value="1" 
                       <?php echo (($settings['free_delivery_active'] ?? '0') == '1') ? 'checked' : ''; ?>
                       class="h-5 w-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <label for="free_delivery_active" class="ml-2 block text-sm font-medium text-gray-900">
                    Enable FREE DELIVERY for all orders
                </label>
            </div>

            <hr class="my-4">

            <div class="flex items-center mt-2">
                <input type="checkbox" id="delivery_discount_active" name="delivery_discount_active" value="1" 
                       <?php echo (($settings['delivery_discount_active'] ?? '0') == '1') ? 'checked' : ''; ?>
                       class="h-5 w-5 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                <label for="delivery_discount_active" class="ml-2 block text-sm font-medium text-gray-900">
                    Enable PERCENTAGE discount on delivery
                </label>
            </div>
            <div class="mt-2 w-full md:w-1/3">
                <label for="delivery_discount_percentage" class="block text-sm font-medium text-gray-700">Delivery Discount Percentage (%)</label>
                <input type="number" step="1" min="0" max="100" id="delivery_discount_percentage" name="delivery_discount_percentage" 
                       value="<?php echo e((int)($settings['delivery_discount_percentage'] ?? '0')); ?>"
                       placeholder="e.g., 50 for 50% off"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
        </div>
        
    </div>

    <!-- Submit Button -->
    <div class="flex justify-end">
        <button type="submit" class="px-8 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
            Save All Settings
        </button>
    </div>
    
</form>

<!-- Scripts -->
<script src="https://cdn.ckeditor.com/ckeditor5/40.2.0/classic/ckeditor.js"></script>
<script>
    ClassicEditor
        .create(document.querySelector('#hero_subtitle'), {
            toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo' ]
        })
        .catch(error => {
            console.error('Error loading CKEditor:', error);
        });

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('exempt_area_search');
        const areaItems = document.querySelectorAll('.area-item');
        const noAreasMsg = document.getElementById('no_areas_found');
        const checkboxes = document.querySelectorAll('input[name="night_surcharge_exempt_areas[]"]');
        const exemptCount = document.getElementById('exempt_count');

        function updateCount() {
            let count = 0;
            checkboxes.forEach(cb => {
                if(cb.checked) count++;
            });
            exemptCount.textContent = count;
        }
        checkboxes.forEach(cb => cb.addEventListener('change', updateCount));
        updateCount(); 

        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            let visibleCount = 0;

            areaItems.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(term)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noAreasMsg.classList.remove('hidden');
            } else {
                noAreasMsg.classList.add('hidden');
            }
        });
    });
</script>

<?php
// 6. FOOTER
require_once('footer.php');
?>