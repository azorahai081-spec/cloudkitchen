<?php
/*
 * admin/homepage_manager.php
 * KitchCo: Cloud Kitchen Homepage Section Manager
 * Version 1.1 - Added CSRF Protection
 *
 * This page controls the `homepage_sections` table.
 * It's an ADMIN-ONLY page (like settings).
 */

// 1. HEADER
require_once('header.php');

// 2. SECURITY CHECK - ADMINS ONLY
if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

// 3. PAGE VARIABLES & INITIALIZATION
$page_title = 'Manage Homepage Sections';
$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // --- A. Handle Add New Section ---
        if (isset($_POST['add_section'])) {
            $category_id = $_POST['category_id'];
            $display_order = $_POST['display_order'];
            
            if (empty($category_id)) {
                $error_message = 'Please select a category to add.';
            } else {
                // Check if this category is already added
                $check_sql = "SELECT * FROM homepage_sections WHERE category_id = ?";
                $check_stmt = $db->prepare($check_sql);
                $check_stmt->bind_param('i', $category_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error_message = 'That category is already on the homepage. You can edit it below.';
                } else {
                    // Add it
                    $sql = "INSERT INTO homepage_sections (category_id, display_order, is_visible) VALUES (?, ?, 1)";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('ii', $category_id, $display_order);
                    if ($stmt->execute()) {
                        $success_message = 'Homepage section added successfully!';
                    } else {
                        $error_message = 'Failed to add section.';
                    }
                    $stmt->close();
                }
                $check_stmt->close();
            }
        }
        
        // --- B. Handle Update Existing Sections ---
        if (isset($_POST['update_sections'])) {
            $section_orders = $_POST['display_order']; // This is an array: [section_id => order]
            $section_visibility = $_POST['is_visible'] ?? []; // Array of IDs that are visible
            
            $sql = "UPDATE homepage_sections SET display_order = ?, is_visible = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            
            foreach ($section_orders as $id => $order) {
                $is_visible = in_array($id, $section_visibility) ? 1 : 0;
                $stmt->bind_param('iii', $order, $is_visible, $id);
                $stmt->execute();
            }
            $stmt->close();
            $success_message = 'Homepage sections updated!';
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Delete) ---
$action = $_GET['action'] ?? 'list';
$section_id = $_GET['id'] ?? null;

if ($action === 'delete' && $section_id) {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $sql = "DELETE FROM homepage_sections WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $section_id);
        if ($stmt->execute()) {
            $success_message = 'Section removed from homepage.';
        } else {
            $error_message = 'Failed to remove section.';
        }
        $stmt->close();
    }
}


// 6. --- LOAD DATA FOR DISPLAY ---
// ... (rest of the file is unchanged) ...
$categories = [];
$cat_result = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
}
$sections = [];
$sql = "SELECT hs.*, c.name as category_name 
        FROM homepage_sections hs
        JOIN categories c ON hs.category_id = c.id
        ORDER BY hs.display_order ASC";
$sec_result = $db->query($sql);
while ($row = $sec_result->fetch_assoc()) {
    $sections[] = $row;
}
?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo e($page_title); ?></h1>
<p class="text-gray-600 mb-8">Control which food categories appear on your homepage and in what order.</p>

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Add New Section Form -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Add New Section</h2>
            <form action="homepage_manager.php" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700">Category to Show</label>
                    <select id="category_id" name="category_id" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">-- Select a Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e($category['id']); ?>"><?php echo e($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                    <input type="number" id="display_order" name="display_order" value="10" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <button type="submit" name="add_section" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                    Add Section to Homepage
                </button>
            </form>
        </div>
    </div>

    <!-- Column 2: Existing Sections List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Homepage Sections (<?php echo count($sections); ?>)
            </h2>
            
            <form action="homepage_manager.php" method="POST">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visible</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($sections)): ?>
                                <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No homepage sections found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sections as $section): ?>
                                    <tr>
                                        <td class="px-6 py-4 w-24">
                                            <input type="number" name="display_order[<?php echo e($section['id']); ?>]" 
                                                   value="<?php echo e($section['display_order']); ?>" 
                                                   class="w-20 px-2 py-1 border border-gray-300 rounded-lg">
                                        </td>
                                        <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($section['category_name']); ?></div></td>
                                        <td class="px-6 py-4">
                                            <input type="checkbox" name="is_visible[]" 
                                                   value="<?php echo e($section['id']); ?>" 
                                                   <?php echo ($section['is_visible']) ? 'checked' : ''; ?>
                                                   class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                                        </td>
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <!-- (MODIFIED) Added CSRF token to delete link -->
                                            <a href="homepage_manager.php?action=delete&id=<?php echo e($section['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                               class="text-red-600 hover:text-red-900" 
                                               onclick="return confirm('Are you sure you want to remove this section?');">
                                                Remove
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (!empty($sections)): ?>
                <div class="p-6 border-t border-gray-200 text-right">
                    <button type="submit" name="update_sections" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700">
                        Update All Sections
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<?php
// 7. FOOTER
require_once('footer.php');
?>