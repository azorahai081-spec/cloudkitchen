<?php
/*
 * admin/manage_menu_items.php
 * KitchCo: Cloud Kitchen Menu Item Manager
 * Version 1.3 - (FIXED) Image paths and sort order
 *
 * This is the most complex CRUD page. It handles:
 * 1. CRUD for menu_items (name, price, image, etc.)
 * 2. Pulling data from 'categories' for a dropdown.
 * 3. Pulling data from 'item_options_groups' for checkboxes.
 * 4. Managing the 'menu_item_options_groups' join table.
 */

// 1. HEADER
require_once('header.php');

// 2. PAGE VARIABLES & INITIALIZATION
$action = $_GET['action'] ?? 'list'; // Default action
$item_id = $_GET['id'] ?? null;
$page_title = 'Manage Menu Items';

// Form data placeholders
$item_name = '';
$item_description = '';
$item_price = '';
$item_category_id = '';
$item_image = '';
$is_available = 1;
$is_featured = 0;
$selected_option_groups = []; // Array to hold IDs of attached groups

$error_message = '';
$success_message = '';

// 3. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // Get all form data
        $item_name = $_POST['item_name'];
        $item_description = $_POST['item_description'];
        $item_price = $_POST['item_price'];
        $item_category_id = $_POST['item_category_id'];
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $current_image = $_POST['current_image'] ?? '';
        // This will be an array of group IDs, e.g., [1, 3, 5]
        $selected_option_groups = $_POST['option_groups'] ?? []; 
        
        // --- START IMAGE UPLOAD LOGIC ---
        $image_path = $current_image; // Default to current
        
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/menu_items/'; // New folder for item images
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file = $_FILES['item_image'];
            // (FIX) Sanitize filename to prevent issues
            $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9\._-]/", "", basename($file['name']));
            $target_path = $upload_dir . $file_name;
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($file['type'], $allowed_types)) {
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    // (FIX) Store path relative to the /uploads/ folder
                    $image_path = '/uploads/menu_items/' . $file_name;
                    if (!empty($current_image) && file_exists('..' . $current_image)) {
                        unlink('..' . $current_image);
                    }
                } else {
                    $error_message = 'Failed to move uploaded file.';
                }
            } else {
                $error_message = 'Invalid file type. Please upload a JPG, PNG, GIF, or WebP.';
            }
        }
        // --- END IMAGE UPLOAD LOGIC ---
        
        // Basic validation
        if (empty($item_name) || empty($item_price) || empty($item_category_id)) {
            $error_message = 'Item Name, Price, and Category are required.';
        }
        
        // (MODIFIED) Check for $error_message *before* DB operations
        if (empty($error_message)) {
            if (isset($_POST['item_id']) && !empty($_POST['item_id'])) {
                // --- UPDATE existing item ---
                $item_id = $_POST['item_id'];
                $sql = "UPDATE menu_items SET name = ?, description = ?, price = ?, category_id = ?, image = ?, is_available = ?, is_featured = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssdsisii', $item_name, $item_description, $item_price, $item_category_id, $image_path, $is_available, $is_featured, $item_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Menu item updated successfully!';
                } else {
                    $error_message = 'Failed to update menu item.';
                }
                $stmt->close();
                
            } else {
                // --- CREATE new item ---
                $sql = "INSERT INTO menu_items (name, description, price, category_id, image, is_available, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssdsisi', $item_name, $item_description, $item_price, $item_category_id, $image_path, $is_available, $is_featured);
                
                if ($stmt->execute()) {
                    $item_id = $db->insert_id; // Get the ID of the new item
                    $success_message = 'Menu item created successfully!';
                } else {
                    $error_message = 'Failed to create menu item.';
                }
                $stmt->close();
            }
            
            // --- SYNC OPTION GROUPS (The Join Table) ---
            // This runs on both CREATE and UPDATE, as long as we have an $item_id
            if ($item_id && empty($error_message)) {
                // 1. Delete all *existing* associations for this item
                $delete_sql = "DELETE FROM menu_item_options_groups WHERE menu_item_id = ?";
                $delete_stmt = $db->prepare($delete_sql);
                $delete_stmt->bind_param('i', $item_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // 2. Insert the new associations
                if (!empty($selected_option_groups)) {
                    $insert_sql = "INSERT INTO menu_item_options_groups (menu_item_id, option_group_id) VALUES (?, ?)";
                    $insert_stmt = $db->prepare($insert_sql);
                    
                    foreach ($selected_option_groups as $group_id) {
                        $insert_stmt->bind_param('ii', $item_id, $group_id);
                        $insert_stmt->execute();
                    }
                    $insert_stmt->close();
                }
            }
            
            if (empty($error_message) && !isset($_POST['item_id'])) {
                 // Clear form fields on successful *creation*
                $item_name = ''; $item_description = ''; $item_price = ''; 
                $item_category_id = ''; $item_image = ''; $is_available = 1;
                $is_featured = 0; $selected_option_groups = [];
            }
        }
    }
}

// 4. --- HANDLE GET ACTIONS (Edit & Delete) ---

if ($action === 'edit' && $item_id) {
    $page_title = 'Edit Menu Item';
    
    // Load item data
    $sql = "SELECT * FROM menu_items WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $item = $result->fetch_assoc();
        $item_name = $item['name'];
        $item_description = $item['description'];
        $item_price = $item['price'];
        $item_category_id = $item['category_id'];
        $item_image = $item['image'];
        $is_available = $item['is_available'];
        $is_featured = $item['is_featured'];
        
        // Load the associated option groups
        $group_sql = "SELECT option_group_id FROM menu_item_options_groups WHERE menu_item_id = ?";
        $group_stmt = $db->prepare($group_sql);
        $group_stmt->bind_param('i', $item_id);
        $group_stmt->execute();
        $group_result = $group_stmt->get_result();
        while ($row = $group_result->fetch_assoc()) {
            $selected_option_groups[] = $row['option_group_id'];
        }
        $group_stmt->close();
        
    } else {
        $error_message = 'Menu item not found.';
        $action = 'list';
    }
    $stmt->close();
}

if ($action === 'delete' && $item_id) {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // Get image path for deletion
        $img_sql = "SELECT image FROM menu_items WHERE id = ?";
        $img_stmt = $db->prepare($img_sql);
        $img_stmt->bind_param('i', $item_id);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        $image_to_delete = '';
        if ($img_result->num_rows === 1) {
            $image_to_delete = $img_result->fetch_assoc()['image'];
        }
        $img_stmt->close();
        
        // Delete the item (cascades will handle join table)
        $sql = "DELETE FROM menu_items WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $item_id);
        
        if ($stmt->execute()) {
            $success_message = 'Menu item deleted successfully!';
            // (FIX) Use '..' to go up one directory to the project root
            if (!empty($image_to_delete) && file_exists('..' . $image_to_delete)) {
                unlink('..' . $image_to_delete);
            }
        } else {
            $error_message = 'Failed to delete menu item. It might be part of an old order.';
        }
        $stmt->close();
    }
    $action = 'list';
}

// 5. --- LOAD DATA FOR DISPLAY ---

// Load Categories for the dropdown
$categories = [];
$cat_result = $db->query("SELECT id, name FROM categories ORDER BY name ASC");
while ($row = $cat_result->fetch_assoc()) {
    $categories[] = $row;
}

// Load Option Groups for the checkboxes
$option_groups = [];
$group_result = $db->query("SELECT id, name, type FROM item_options_groups ORDER BY name ASC");
while ($row = $group_result->fetch_assoc()) {
    $option_groups[] = $row;
}

// Load Menu Items for the list
$menu_items = [];
// (MODIFIED) Changed ORDER BY from m.name ASC to m.id DESC
$sql = "SELECT m.*, c.name as category_name 
        FROM menu_items m
        LEFT JOIN categories c ON m.category_id = c.id
        ORDER BY m.id DESC";
$item_result = $db->query($sql);
while ($row = $item_result->fetch_assoc()) {
    $menu_items[] = $row;
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

<?php if ($action === 'list'): ?>
<!-- --- LIST VIEW --- -->
<div class="bg-white rounded-2xl shadow-lg">
    <div class="flex justify-between items-center p-6 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">
            Existing Menu Items (<?php echo count($menu_items); ?>)
        </h2>
        <a href="manage_menu_items.php?action=add" class="px-5 py-2 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
            Add New Item
        </a>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Image</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($menu_items)): ?>
                    <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No menu items found.</td></tr>
                <?php else: ?>
                    <?php foreach ($menu_items as $item): ?>
                        <tr>
                            <td class="px-6 py-4">
                                <!-- (FIX) Use BASE_URL for the image path -->
                                <img src="<?php echo e(BASE_URL); ?><?php echo e($item['image']); ?>" 
                                     alt="<?php echo e($item['name']); ?>" 
                                     class="w-12 h-12 object-cover rounded-lg" 
                                     onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=No+Image'">
                            </td>
                            <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($item['name']); ?></div></td>
                            <td class="px-6 py-4"><div class="text-sm text-gray-500"><?php echo e($item['category_name']); ?></div></td>
                            <td class="px-6 py-4"><div class="text-sm text-gray-900"><?php echo e(number_format($item['price'], 2)); ?> BDT</div></td>
                            <td class="px-6 py-4">
                                <?php if ($item['is_available']): ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Available</span>
                                <?php else: ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Sold Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                <a href="manage_menu_items.php?action=edit&id=<?php echo e($item['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                <!-- (MODIFIED) Added CSRF token to delete link -->
                                <a href="manage_menu_items.php?action=delete&id=<?php echo e($item['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                   class="text-red-600 hover:text-red-900" 
                                   onclick="return confirm('Are you sure you want to delete this item?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- --- ADD/EDIT FORM --- -->
<div class="bg-white p-8 rounded-2xl shadow-lg">
    <a href="manage_menu_items.php" class="text-orange-600 hover:text-orange-900 mb-4 inline-block">&larr; Back to all items</a>
    <h2 class="text-2xl font-bold text-gray-900 mb-6">
        <?php echo ($action === 'edit') ? 'Edit Menu Item' : 'Add New Menu Item'; ?>
    </h2>

    <form action="manage_menu_items.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Column 1: Main Details -->
        <div class="lg:col-span-2 space-y-6">
            <!-- (NEW) CSRF Token -->
            <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
            
            <?php if ($action === 'edit' && $item_id): ?>
                <input type="hidden" name="item_id" value="<?php echo e($item_id); ?>">
            <?php endif; ?>
            <input type="hidden" name="current_image" value="<?php echo e($item_image); ?>">

            <div>
                <label for="item_name" class="block text-sm font-medium text-gray-700">Item Name</label>
                <input type="text" id="item_name" name="item_name" value="<?php echo e($item_name); ?>" required
                       class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            
            <div>
                <label for="item_description" class="block text-sm font-medium text-gray-700">Description</label>
                <textarea id="item_description" name="item_description" rows="4"
                          class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($item_description); ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label for="item_price" class="block text-sm font-medium text-gray-700">Base Price (BDT)</label>
                    <input type="number" step="0.01" id="item_price" name="item_price" value="<?php echo e($item_price); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                
                <div>
                    <label for="item_category_id" class="block text-sm font-medium text-gray-700">Category</label>
                    <select id="item_category_id" name="item_category_id" required
                            class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">-- Select a Category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo e($category['id']); ?>" <?php echo ($item_category_id == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo e($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex items-center space-x-6">
                <div class="flex items-center">
                    <input type="checkbox" id="is_available" name="is_available" value="1" <?php echo ($is_available) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="is_available" class="ml-2 block text-sm text-gray-900">Available (Sold Out)</label>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="is_featured" name="is_featured" value="1" <?php echo ($is_featured) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="is_featured" class="ml-2 block text-sm text-gray-900">Featured on Homepage</label>
                </div>
            </div>
        </div>
        
        <!-- Column 2: Image & Options -->
        <div class="lg:col-span-1 space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700">Item Image</label>
                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg">
                    <div class="space-y-1 text-center">
                        <?php if ($action === 'edit' && !empty($item_image)): ?>
                            <!-- (FIX) Use BASE_URL for the image path -->
                            <img src="<?php echo e(BASE_URL); ?><?php echo e($item_image); ?>" 
                                 alt="Current Image" 
                                 class="w-40 h-40 mx-auto object-cover rounded-lg mb-4"
                                 onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=No+Image'">
                        <?php else: ?>
                            <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        <?php endif; ?>
                        <div class="flex text-sm text-gray-600">
                            <label for="item_image" class="relative cursor-pointer bg-white rounded-md font-medium text-orange-600 hover:text-orange-500 focus-within:outline-none">
                                <span>Upload a file</span>
                                <input id="item_image" name="item_image" type="file" class="sr-only" accept="image/*">
                            </label>
                            <p class="pl-1">or drag and drop</p>
                        </div>
                        <p class="text-xs text-gray-500">PNG, JPG, GIF, WebP up to 2MB</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Attach Option Groups</label>
                <p class="text-xs text-gray-500 mb-2">Which options should appear when a user clicks this item?</p>
                <div class="mt-2 p-4 h-60 overflow-y-auto border border-gray-300 rounded-lg space-y-2">
                    <?php if (empty($option_groups)): ?>
                        <p class="text-gray-500">No option groups found. <a href="manage_item_options.php" class="text-orange-600">Create one first!</a></p>
                    <?php else: ?>
                        <?php foreach ($option_groups as $group): ?>
                            <div class="flex items-center">
                                <input 
                                    type="checkbox" 
                                    id="group_<?php echo e($group['id']); ?>" 
                                    name="option_groups[]" 
                                    value="<?php echo e($group['id']); ?>"
                                    <?php echo (in_array($group['id'], $selected_option_groups)) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                                >
                                <label for="group_<?php echo e($group['id']); ?>" class="ml-2 block text-sm text-gray-900">
                                    <?php echo e($group['name']); ?> <span class="text-xs text-gray-500">(<?php echo e($group['type']); ?>)</span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Submit Button (Full Width) -->
        <div class="lg:col-span-3">
            <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors">
                <?php echo ($action === 'edit') ? 'Save Changes to Item' : 'Create New Item'; ?>
            </button>
        </div>
        
    </form>
</div>
<?php endif; ?>

<?php
// 6. FOOTER
require_once('footer.php');
?>