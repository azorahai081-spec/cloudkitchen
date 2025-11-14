<?php
/*
 * admin/marketing_settings.php
 * KitchCo: Cloud Kitchen Marketing & API Key Manager
 * Version 1.1 - Added CSRF Protection
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
$page_title = 'Marketing & SEO Settings';
$error_message = '';
$success_message = '';

// The $settings array is already loaded from config.php

// 4. --- HANDLE POST REQUESTS (Update Settings) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $new_settings = [
            'gtm_id' => $_POST['gtm_id'] ?? '',
            'fb_pixel_id' => $_POST['fb_pixel_id'] ?? '',
            'fb_capi_token' => $_POST['fb_capi_token'] ?? '',
            'fb_capi_test_code' => $_POST['fb_capi_test_code'] ?? ''
        ];
        
        // --- DATABASE UPDATE ---
        $sql = "UPDATE site_settings SET setting_value = ? WHERE setting_key = ?";
        $stmt = $db->prepare($sql);
        
        if (!$stmt) {
            $error_message = "Error preparing statement: " . $db->error;
        } else {
            foreach ($new_settings as $key => $value) {
                // Check if key exists first
                if (array_key_exists($key, $settings)) {
                    $stmt->bind_param('ss', $value, $key);
                    $stmt->execute();
                } else {
                    // If key doesn't exist, insert it (future-proofing)
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
            $success_message = 'Marketing settings updated successfully!';
            // Reload settings from DB
            $settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");
            if ($settings_query) {
                while ($row = $settings_query->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        }
    }
}
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

<!-- Settings Form -->
<form action="marketing_settings.php" method="POST" class="space-y-12">
    <!-- (NEW) CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

    <!-- Section 1: Google Tag Manager -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Google Tag Manager</h2>
        <div class="space-y-6">
            <div>
                <label for="gtm_id" class="block text-sm font-medium text-gray-700">GTM Container ID</label>
                <input type="text" id="gtm_id" name="gtm_id" 
                       value="<?php echo e($settings['gtm_id'] ?? ''); ?>"
                       placeholder="GTM-XXXXXXX"
                       class="mt-1 block w-full md:w-1/2 px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">This will be added to all public pages.</p>
            </div>
        </div>
    </div>

    <!-- Section 2: Facebook (Pixel & CAPI) -->
    <div class="bg-white p-8 rounded-2xl shadow-lg">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Facebook Pixel & CAPI</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div>
                <label for="fb_pixel_id" class="block text-sm font-medium text-gray-700">Facebook Pixel ID</label>
                <input type="text" id="fb_pixel_id" name="fb_pixel_id" 
                       value="<?php echo e($settings['fb_pixel_id'] ?? ''); ?>"
                       placeholder="1234567890"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">Used for browser-side tracking.</p>
            </div>
            
            <div>
                <label for="fb_capi_token" class="block text-sm font-medium text-gray-700">CAPI Access Token</label>
                <input type="password" id="fb_capi_token" name="fb_capi_token" 
                       value="<?php echo e($settings['fb_capi_token'] ?? ''); ?>"
                       placeholder="EAA..."
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">Your server-to-server Conversions API token.</p>
            </div>
            
            <div class="md:col-span-2">
                <label for="fb_capi_test_code" class="block text-sm font-medium text-gray-700">CAPI Test Event Code (Optional)</label>
                <input type="text" id="fb_capi_test_code" name="fb_capi_test_code" 
                       value="<?php echo e($settings['fb_capi_test_code'] ?? ''); ?>"
                       placeholder="TEST12345"
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <p class="text-xs text-gray-500 mt-1">Use this to test server events in Facebook's Event Manager.</p>
            </div>
            
        </div>
    </div>
    
    <!-- Submit Button -->
    <div class="flex justify-end">
        <button type="submit" class="px-8 py-3 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
            Save Marketing Settings
        </button>
    </div>
    
</form>

<?php
// 6. FOOTER
require_once('footer.php');
?>