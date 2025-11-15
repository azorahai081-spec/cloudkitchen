<?php
/*
 * admin/manage_item_options.php
 * KitchCo: Cloud Kitchen Item Options Manager
 * Version 1.2 - (FIXED) Added error handling for deletes
 *
 * This page handles full CRUD for:
 * 1. `item_options_groups` (e.g., "Size", "Toppings")
 * 2. `item_options` (e.g., "Small", "Large", "Extra Cheese")
 */

// 1. HEADER
require_once('header.php');

// 2. PAGE VARIABLES & INITIALIZATION
$action = $_GET['action'] ?? 'list_groups'; // Default action
$group_id = $_GET['group_id'] ?? $_GET['id'] ?? null;
$option_id = $_GET['option_id'] ?? null;
$page_title = 'Manage Item Options';

// Form data placeholders
$group_name = '';
$group_type = 'radio';
$option_name = '';
$option_price = 0;

$error_message = '';
$success_message = '';

// 3. --- HANDLE POST REQUESTS (Create/Update Groups & Options) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $form_type = $_POST['form_type'] ?? '';

        // --- A. Handle Group Form ---
        if ($form_type === 'group') {
            $group_name = $_POST['group_name'];
            $group_type = $_POST['group_type'];
            $edit_group_id = $_POST['group_id'] ?? null;

            if (empty($group_name)) {
                $error_message = 'Group name is required.';
            } else {
                if ($edit_group_id) {
                    // UPDATE Group
                    $sql = "UPDATE item_options_groups SET name = ?, type = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('ssi', $group_name, $group_type, $edit_group_id);
                    if ($stmt->execute()) {
                        $success_message = 'Option group updated successfully!';
                    } else {
                        $error_message = 'Failed to update group.';
                    }
                    $stmt->close();
                } else {
                    // CREATE Group
                    $sql = "INSERT INTO item_options_groups (name, type) VALUES (?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('ss', $group_name, $group_type);
                    if ($stmt->execute()) {
                        $success_message = 'Option group created successfully!';
                        $group_name = ''; // Clear form
                    } else {
                        $error_message = 'Failed to create group.';
                    }
                    $stmt->close();
                }
            }
        }
        
        // --- B. Handle Option Form ---
        if ($form_type === 'option' && $group_id) {
            $option_name = $_POST['option_name'];
            $option_price = $_POST['option_price'];
            $edit_option_id = $_POST['option_id'] ?? null;

            if (empty($option_name)) {
                $error_message = 'Option name is required.';
            } else {
                if ($edit_option_id) {
                    // UPDATE Option
                    $sql = "UPDATE item_options SET name = ?, price_increase = ? WHERE id = ? AND group_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('sdii', $option_name, $option_price, $edit_option_id, $group_id);
                    if ($stmt->execute()) {
                        $success_message = 'Option updated successfully!';
                    } else {
                        $error_message = 'Failed to update option.';
                    }
                    $stmt->close();
                } else {
                    // CREATE Option
                    $sql = "INSERT INTO item_options (group_id, name, price_increase) VALUES (?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('isd', $group_id, $option_name, $option_price);
                    if ($stmt->execute()) {
                        $success_message = 'Option created successfully!';
                        $option_name = ''; $option_price = 0; // Clear form
                    } else {
                        $error_message = 'Failed to create option.';
                    }
                    $stmt->close();
                }
            }
            $action = 'manage_options'; // Stay on the options page
        }
    }
}

// 4. --- HANDLE GET ACTIONS (Edit/Delete Groups & Options) ---
// --- A. Group Actions ---
if ($action === 'edit_group' && $group_id) {
    $page_title = 'Edit Option Group';
    $sql = "SELECT * FROM item_options_groups WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $group = $result->fetch_assoc();
        $group_name = $group['name'];
        $group_type = $group['type'];
    } else {
        $error_message = 'Group not found.';
    }
    $stmt->close();
    $action = 'list_groups'; // Show the form with data
}

if ($action === 'delete_group' && $group_id) {
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        
        // (MODIFIED) Wrap database action in try...catch
        // This prevents a fatal error (blank page) if a foreign key constraint fails
        try {
            $sql = "DELETE FROM item_options_groups WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $group_id);
            
            if ($stmt->execute()) {
                $success_message = 'Group and all its options deleted successfully!';
            } else {
                // This will now be shown instead of a blank page
                $error_message = 'Failed to delete group. It is likely linked to a menu item.';
            }
            $stmt->close();
            
        } catch (mysqli_sql_exception $e) {
            // Catch the database-level error
            $error_message = 'Failed to delete group. It is linked to a menu item. Please remove it from all menu items first.';
        }
    }
    $action = 'list_groups';
}

// --- B. Option Actions ---
if ($action === 'edit_option' && $group_id && $option_id) {
    $page_title = 'Edit Option';
    $sql = "SELECT * FROM item_options WHERE id = ? AND group_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('ii', $option_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $option = $result->fetch_assoc();
        $option_name = $option['name'];
        $option_price = $option['price_increase'];
    } else {
        $error_message = 'Option not found.';
    }
    $stmt->close();
    $action = 'manage_options'; // Show the form with data
}

if ($action === 'delete_option' && $group_id && $option_id) {
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        // (MODIFIED) Wrap database action in try...catch
        try {
            $sql = "DELETE FROM item_options WHERE id = ? AND group_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('ii', $option_id, $group_id);
            if ($stmt->execute()) {
                $success_message = 'Option deleted successfully!';
            } else {
                $error_message = 'Failed to delete option.';
            }
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
             $error_message = 'Failed to delete option. (Error: ' . $e->getMessage() . ')';
        }
    }
    $action = 'manage_options';
}


// 5. --- LOAD DATA FOR DISPLAY ---
$groups = [];
$options = [];
$current_group_name = '';

if ($action === 'manage_options' && $group_id) {
    // Load the specific group we are managing
    $stmt = $db->prepare("SELECT name FROM item_options_groups WHERE id = ?");
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $current_group_name = $result->fetch_assoc()['name'];
        $page_title = 'Manage Options for "' . e($current_group_name) . '"';
    } else {
        $error_message = 'Group not found.';
        $action = 'list_groups'; // Kick back to list
    }
    $stmt->close();

    // Load all options for this group
    $stmt = $db->prepare("SELECT * FROM item_options WHERE group_id = ? ORDER BY name ASC");
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $options[] = $row;
    }
    $stmt->close();
}

if ($action === 'list_groups') {
    // Load all groups
    $result = $db->query("SELECT * FROM item_options_groups ORDER BY name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $groups[] = $row;
        }
    }
}

?>

<!-- Page Title -->
<h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_title); ?></h1>

<!-- Back Link (if managing options) -->
<?php if ($action === 'manage_options'): ?>
    <div class="mb-4">
        <a href="manage_item_options.php" class="text-orange-600 hover:text-orange-900">&larr; Back to All Option Groups</a>
    </div>
<?php endif; ?>

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
2. The list (for viewing all items)
-->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Add/Edit Form -->
    <div class="lg:col-span-1">
        
        <?php if ($action === 'list_groups' || $action === 'edit_group'): ?>
        <!-- --- GROUP FORM --- -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($action === 'edit_group') ? 'Edit Group' : 'Add New Group'; ?>
            </h2>
            
            <form action="manage_item_options.php" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                <input type="hidden" name="form_type" value="group">
                <?php if ($action === 'edit_group' && $group_id): ?>
                    <input type="hidden" name="group_id" value="<?php echo e($group_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="group_name" class="block text-sm font-medium text-gray-700">
                        Group Name (e.g., "Size", "Toppings")
                    </label>
                    <input type="text" id="group_name" name="group_name" value="<?php echo e($group_name); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="group_type" class="block text-sm font-medium text-gray-700">
                        Selection Type
                    </label>
                    <select id="group_type" name="group_type" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="radio" <?php echo ($group_type === 'radio') ? 'selected' : ''; ?>>Choose ONE (e.g., Size)</option>
                        <option value="checkbox" <?php echo ($group_type === 'checkbox') ? 'selected' : ''; ?>>Choose MANY (e.g., Toppings)</option>
                    </select>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                        <?php echo ($action === 'edit_group') ? 'Save Group' : 'Add Group'; ?>
                    </button>
                    <?php if ($action === 'edit_group'): ?>
                        <a href="manage_item_options.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if ($action === 'manage_options' || $action === 'edit_option'): ?>
        <!-- --- OPTION FORM --- -->
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($action === 'edit_option') ? 'Edit Option' : 'Add New Option'; ?>
            </h2>
            
            <form action="manage_item_options.php?action=manage_options&group_id=<?php echo e($group_id); ?>" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                <input type="hidden" name="form_type" value="option">
                <?php if ($action === 'edit_option' && $option_id): ?>
                    <input type="hidden" name="option_id" value="<?php echo e($option_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="option_name" class="block text-sm font-medium text-gray-700">
                        Option Name (e.g., "Large", "Extra Cheese")
                    </label>
                    <input type="text" id="option_name" name="option_name" value="<?php echo e($option_name); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="option_price" class="block text-sm font-medium text-gray-700">
                        Price Increase (e.g., 50.00)
                    </label>
                    <input type="number" step="0.01" id="option_price" name="option_price" value="<?php echo e($option_price); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                        <?php echo ($action === 'edit_option') ? 'Save Option' : 'Add Option'; ?>
                    </button>
                    <?php if ($action === 'edit_option'): ?>
                        <a href="manage_item_options.php?action=manage_options&group_id=<?php echo e($group_id); ?>" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
    </div>

    <!-- Column 2: List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            
            <?php if ($action === 'list_groups' || $action === 'edit_group'): ?>
            <!-- --- GROUP LIST --- -->
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Option Groups (<?php echo count($groups); ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($groups)): ?>
                            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No option groups found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($groups as $group): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900"><?php echo e($group['name']); ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800 capitalize"><?php echo e($group['type']); ?></span></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <a href="manage_item_options.php?action=manage_options&group_id=<?php echo e($group['id']); ?>" class="text-blue-600 hover:text-blue-900 font-bold">Manage Options</a>
                                        <a href="manage_item_options.php?action=edit_group&id=<?php echo e($group['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <!-- (MODIFIED) Added CSRF token to delete link -->
                                        <a href="manage_item_options.php?action=delete_group&id=<?php echo e($group['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this group? ALL its options will be deleted.');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($action === 'manage_options' || $action === 'edit_option'): ?>
            <!-- --- OPTION LIST --- -->
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Options for "<?php echo e($current_group_name); ?>" (<?php echo count($options); ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Option Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price Increase</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($options)): ?>
                            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No options found for this group.</td></tr>
                        <?php else: ?>
                            <?php foreach ($options as $option): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm font-medium text-gray-900"><?php echo e($option['name']); ?></div></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><div class="text-sm text-gray-700">+ <?php echo e(number_format($option['price_increase'], 2)); ?> BDT</div></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        <a href="manage_item_options.php?action=edit_option&group_id=<?php echo e($group_id); ?>&option_id=<?php echo e($option['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <!-- (MODIFIED) Added CSRF token to delete link -->
                                        <a href="manage_item_options.php?action=delete_option&group_id=<?php echo e($group_id); ?>&option_id=<?php echo e($option['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this option?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<?php
// 6. FOOTER
require_once('footer.php');
?>