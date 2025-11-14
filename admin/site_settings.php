<?php
/*
 * admin/site_settings.php
 * KitchCo: Cloud Kitchen Site & Store Settings
 * Version 1.3 - Removed TinyMCE
 *
 * This is an ADMIN-ONLY page.
 * It provides a UI to edit all values in the `site_settings` table.
 * This includes homepage content, store status, and surcharge logic.
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
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // Get all the new settings from the form
        // We use an array to hold them, then loop to update the DB
        $new_settings = [
            'hero_title' => $_POST['hero_title'],
            'hero_subtitle' => $_POST['hero_subtitle'],
            'store_is_open' => isset($_POST['store_is_open']) ? '1' : '0',
            'night_surcharge_amount' => $_POST['night_surcharge_amount'],
            'night_surcharge_start_hour' => $_POST['night_surcharge_start_hour'],
            'night_surcharge_end_hour' => $_POST['night_surcharge_end_hour'],
            'timezone' => $_POST['timezone']
        ];
        
        // --- START IMAGE UPLOAD LOGIC (for Hero Banner) ---
        $current_image = $settings['hero_image_url'];
        $image_path = $current_image; // Default to current

        if (isset($_FILES['hero_image_url']) && $_FILES['hero_image_url']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/banners/'; // New folder for banners
            
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
                    // Delete old banner if it's not the default
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
        // Add the new image path to our settings array
        $new_settings['hero_image_url'] = $image_path;
        // --- END IMAGE UPLOAD LOGIC ---
        
        
        // --- DATABASE UPDATE ---
        // Prepare the update statement. We will re-use this.
        $sql = "UPDATE site_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $db->prepare($sql);
        
        if (!$stmt) {
            $error_message = "Error preparing statement: " . $db->error;
        } else {
            foreach ($new_settings as $key => $value) {
                $stmt->bind_param('ss', $value, $key);
                if (!$stmt->execute()) {
                    $error_message = "Error updating setting: $key";
                }
            }
            $stmt->close();
        }

        if (empty($error_message)) {
            $success_message = 'Settings updated successfully!';
            // IMPORTANT: Reload settings from DB into our $settings array
            // so the page shows the new values instantly.
            $settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");
            if ($settings_query) {
                while ($row = $settings_query->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
}

// 5. --- LOAD TIMEZONES FOR DROPDOWN ---
// Helper to get a list of timezones for the dropdown
$timezone_identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

?>

<!-- (REMOVED) TinyMCE scripts -->

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

<!-- Settings are all in one form -->
<form action="site_settings.php" method="POST" enctype="multipart/form-data" class="space-y-12">
    <!-- (NEW) CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

    <!-- Section 1: Homepage Content -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Homepage Content (CMS)</h2>
        <div class="space-y-6">
            
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
                <!-- (MODIFIED) This is now a standard textarea -->
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
            
        </div>
    </div>

    <!-- Section 2: Store & Surcharge Settings -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Store & Surcharge Settings</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <!-- Store Status -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Store Status</label>
                <div class="mt-2 p-4 bg-slate-50 rounded-lg flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-gray-900">Store is Open</h3>
                        <p class="text-sm text-gray-500">
                            When "Closed", customers will be blocked from placing new orders.
                        </p>
                    </div>
                    <label for="store_is_open" class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="store_is_open" name="store_is_open" value="1" 
                               class="sr-only toggle-checkbox" 
                               <?php echo ($settings['store_is_open'] == '1') ? 'checked' : ''; ?>>
                        <div class="w-14 h-8 bg-gray-300 rounded-full transition-all"></div>
                        <div class="absolute left-1 top-1 w-6 h-6 bg-white rounded-full shadow-md transform transition-all toggle-checkbox"></div>
                    </label>
                </div>
            </div>

            <!-- Night Surcharge -->
            <div>
                <label for="night_surcharge_amount" class="block text-sm font-medium text-gray-700">Night Surcharge Amount (BDT)</label>
                <input type="number" step="0.01" id="night_surcharge_amount" name="night_surcharge_amount" 
                       value="<?php echo e($settings['night_surcharge_amount'] ?? '0'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            
            <!-- Timezone -->
            <div>
                <label for="timezone" class="block text-sm font-medium text-gray-700">Store Timezone</label>
                <select id="timezone" name="timezone" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <?php foreach ($timezone_identifiers as $tz): ?>
                        <option value="<?php echo e($tz); ?>" <?php echo ($settings['timezone'] == $tz) ? 'selected' : ''; ?>>
                            <?php echo e($tz); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Surcharge Start -->
            <div>
                <label for="night_surcharge_start_hour" class="block text-sm font-medium text-gray-700">Surcharge Start (Hour 0-23)</label>

                <input type="number" min="0" max="23" id="night_surcharge_start_hour" name="night_surcharge_start_hour" 
                       value="<?php echo e($settings['night_surcharge_start_hour'] ?? '0'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">e.g., '0' for Midnight</p>
            </div>
            
            <!-- Surcharge End -->
            <div>
                <label for="night_surcharge_end_hour" class="block text-sm font-medium text-gray-700">Surcharge End (Hour 0-23)</label>
                <input type="number" min="0" max="23" id="night_surcharge_end_hour" name="night_surcharge_end_hour" 
                       value="<?php echo e($settings['night_surcharge_end_hour'] ?? '6'); ?>"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">e.g., '6' for 6:00 AM</p>
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

<?php
// 6. FOOTER
require_once('footer.php');
?>