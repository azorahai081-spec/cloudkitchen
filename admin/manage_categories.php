<?php
/*
 * admin/manage_categories.php
 * KitchCo: Cloud Kitchen Category Manager
 * Version 1.1 - Added Image Upload Logic
 *
 * This page handles full CRUD for food categories.
 * It uses a single-page approach with URL parameters (?action=)
 */

// 1. HEADER
// Includes session start, DB connection, and security check.
require_once('header.php');

// 2. PAGE VARIABLES & INITIALIZATION
$action = $_GET['action'] ?? 'list'; // Default action is 'list'
$category_id = $_GET['id'] ?? null;
$page_title = 'Manage Categories';

$category_name = '';
$category_description = '';
$category_image = ''; // Will hold the path of the image
$is_visible = 1;

$error_message = '';
$success_message = '';

// 3. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Token validation (will be added in Phase 5, for now we check a hidden field)
    
    $category_name = $_POST['category_name'];
    $category_description = $_POST['category_description'];
    $is_visible = isset($_POST['is_visible']) ? 1 : 0;
    $current_image = $_POST['current_image'] ?? ''; // Get the path of the current image, if editing
    
    // --- START IMAGE UPLOAD LOGIC ---
    $image_path = $current_image; // Default to the current image
    
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/categories/';
        
        // Ensure the upload directory exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file = $_FILES['category_image'];
        $file_name = time() . '_' . basename($file['name']);
        $target_path = $upload_dir . $file_name;
        
        // Validate file type (simple check)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $allowed_types)) {
            // Move the file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Save the *web-accessible* path, not the server path
                $image_path = '/uploads/categories/' . $file_name;
                
                // If this is an UPDATE, delete the old image
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
    
    if (empty($category_name)) {
        $error_message = 'Category name is required.';
    } else {
        if (isset($_POST['category_id']) && !empty($_POST['category_id'])) {
            // --- UPDATE existing category ---
            $cat_id = $_POST['category_id'];
            // Now we include the `image` field in the update
            $sql = "UPDATE categories SET name = ?, description = ?, image = ?, is_visible = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('sssii', $category_name, $category_description, $image_path, $is_visible, $cat_id);
            
            if ($stmt->execute()) {
                $success_message = 'Category updated successfully!';
            } else {
                $error_message = 'Failed to update category. Please try again.';
            }
            $stmt->close();
            
        } else {
            // --- CREATE new category ---
            // Now we include the `image` field in the insert
            $sql = "INSERT INTO categories (name, description, is_visible, image) VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ssis', $category_name, $category_description, $is_visible, $image_path);
            
            if ($stmt->execute()) {
                $success_message = 'Category created successfully!';
                // Clear fields after success
                $category_name = '';
                $category_description = '';
                $image_path = ''; // Clear image path on success
            } else {
                $error_message = 'Failed to create category. Please try again.';
            }
            $stmt->close();
        }
    }
}

// 4. --- HANDLE GET ACTIONS (Edit & Delete) ---

// Handle "Edit" - Load data into the form
if ($action === 'edit' && $category_id) {
    $page_title = 'Edit Category';
    $sql = "SELECT * FROM categories WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $category = $result->fetch_assoc();
        $category_name = $category['name'];
        $category_description = $category['description'];
        $is_visible = $category['is_visible'];
        $category_image = $category['image']; // Load the image path
    } else {
        $error_message = 'Category not found.';
        $action = 'list'; // Reset action
    }
    $stmt->close();
}

// Handle "Delete"
if ($action === 'delete' && $category_id) {
    // TODO: Add CSRF token check
    
    // First, get the image path so we can delete the file
    $img_sql = "SELECT image FROM categories WHERE id = ?";
    $img_stmt = $db->prepare($img_sql);
    $img_stmt->bind_param('i', $category_id);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    if ($img_result->num_rows === 1) {
        $img_row = $img_result->fetch_assoc();
        $image_to_delete = $img_row['image'];
    }
    $img_stmt->close();
    
    // Now, delete the category from the database
    $sql = "DELETE FROM categories WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $category_id);
    
    if ($stmt->execute()) {
        $success_message = 'Category deleted successfully!';
        // After successful DB deletion, delete the file from the server
        if (!empty($image_to_delete) && file_exists('..' . $image_to_delete)) {
            unlink('..' . $image_to_delete);
        }
    } else {
        $error_message = 'Failed to delete category. It might have menu items linked to it.';
    }
    $stmt->close();
    $action = 'list'; // Reset to list view
}

// 5. --- LOAD DATA FOR DISPLAY ---
// Always fetch the list of categories to display in the table
$categories = [];
$result = $db->query("SELECT * FROM categories ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
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

<!-- 
This grid layout splits the page into two columns:
1. The form (for adding/editing)
2. The list (for viewing all categories)
-->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Add/Edit Form -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($action === 'edit') ? 'Edit Category' : 'Add New Category'; ?>
            </h2>
            
            <!-- IMPORTANT: We must add enctype for file uploads -->
            <form action="manage_categories.php" method="POST" class="space-y-4" enctype="multipart/form-data">
                <!-- Hidden field for UPDATE operations -->
                <?php if ($action === 'edit' && $category_id): ?>
                    <input type="hidden" name="category_id" value="<?php echo e($category_id); ?>">
                <?php endif; ?>
                
                <!-- Hidden field to store current image path during edit -->
                <input type="hidden" name="current_image" value="<?php echo e($category_image); ?>">

                <!-- Category Name -->
                <div>
                    <label for="category_name" class="block text-sm font-medium text-gray-700">
                        Category Name
                    </label>
                    <input 
                        type="text" 
                        id="category_name" 
                        name="category_name" 
                        value="<?php echo e($category_name); ?>"
                        required
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                    >
                </div>

                <!-- Category Description -->
                <div>
                    <label for="category_description" class="block text-sm font-medium text-gray-700">
                        Description
                    </label>
                    <textarea 
                        id="category_description" 
                        name="category_description" 
                        rows="3"
                        class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"
                    ><?php echo e($category_description); ?></textarea>
                </div>
                
                <!-- Image Upload (Now Enabled) -->
                <div>
                    <label for="category_image" class="block text-sm font-medium text-gray-700">
                        Category Image
                    </label>
                    <input 
                        type="file" 
                        id="category_image" 
                        name="category_image" 
                        accept="image/jpeg,image/png,image/gif,image/webp"
                        class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200"
                    >
                    <?php if ($action === 'edit' && !empty($category_image)): ?>
                        <div class="mt-2">
                            <img src="<?php echo e(BASE_URL . $category_image); ?>" alt="Current Image" class="w-24 h-24 object-cover rounded-lg">
                            <p class="text-xs text-gray-500 mt-1">Current image. Uploading a new one will replace it.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Visibility Toggle -->
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="is_visible" 
                        name="is_visible" 
                        value="1" 
                        <?php echo ($is_visible) ? 'checked' : ''; ?>
                        class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500"
                    >
                    <label for="is_visible" class="ml-2 block text-sm text-gray-900">
                        Visible on website
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="flex space-x-2">
                    <button 
                        type="submit" 
                        class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors"
                    >
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Add Category'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_categories.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 transition-colors">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Category List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Categories (<?php echo count($categories); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No categories found. Add one using the form!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <img 
                                            src="<?php echo e(BASE_URL . ($category['image'] ?? '/uploads/placeholder.png')); ?>" 
                                            alt="<?php echo e($category['name']); ?>" 
                                            class="w-12 h-12 object-cover rounded-lg"
                                            onerror="this.src='https://placehold.co/100x100/EFEFEF/AAAAAA?text=No+Image'"
                                        >
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo e($category['name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo e(substr($category['description'], 0, 50)) . (strlen($category['description']) > 50 ? '...' : ''); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($category['is_visible']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Visible
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                Hidden
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <a href="manage_categories.php?action=edit&id=<?php echo e($category['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <a href="manage_categories.php?action=delete&id=<?php echo e($category['id']); ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this category? This cannot be undone.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

<?php
// 6. FOOTER
// Includes all closing tags and mobile menu script
require_once('footer.php');
?>