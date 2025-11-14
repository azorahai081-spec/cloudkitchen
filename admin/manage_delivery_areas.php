<?php
/*
 * admin/manage_delivery_areas.php
 * KitchCo: Cloud Kitchen Delivery Area Manager
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
$action = $_GET['action'] ?? 'list';
$area_id = $_GET['id'] ?? null;
$page_title = 'Manage Delivery Areas';

$area_name = '';
$base_charge = '';
$is_active = 1;

$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $area_name = $_POST['area_name'];
        $base_charge = $_POST['base_charge'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($area_name) || !is_numeric($base_charge)) {
            $error_message = 'Area name is required, and base charge must be a number.';
        } else {
            if (isset($_POST['area_id']) && !empty($_POST['area_id'])) {
                // --- UPDATE existing area ---
                $cat_id = $_POST['area_id'];
                $sql = "UPDATE delivery_areas SET area_name = ?, base_charge = ?, is_active = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('sdii', $area_name, $base_charge, $is_active, $cat_id);
                
                if ($stmt->execute()) {
                    $success_message = 'Delivery area updated successfully!';
                } else {
                    $error_message = 'Failed to update area.';
                }
                $stmt->close();
                
            } else {
                // --- CREATE new area ---
                $sql = "INSERT INTO delivery_areas (area_name, base_charge, is_active) VALUES (?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('sdi', $area_name, $base_charge, $is_active);
                
                if ($stmt->execute()) {
                    $success_message = 'Delivery area created successfully!';
                    $area_name = ''; $base_charge = '';
                } else {
                    $error_message = 'Failed to create area.';
                }
                $stmt->close();
            }
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Edit & Delete) ---

if ($action === 'edit' && $area_id) {
    $page_title = 'Edit Delivery Area';
    $sql = "SELECT * FROM delivery_areas WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $area_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $area = $result->fetch_assoc();
        $area_name = $area['area_name'];
        $base_charge = $area['base_charge'];
        $is_active = $area['is_active'];
    } else {
        $error_message = 'Delivery area not found.';
        $action = 'list';
    }
    $stmt->close();
}

if ($action === 'delete' && $area_id) {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $sql = "DELETE FROM delivery_areas WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $area_id);
        
        if ($stmt->execute()) {
            $success_message = 'Delivery area deleted successfully!';
        } else {
            $error_message = 'Failed to delete area. It might be linked to existing orders.';
        }
        $stmt->close();
    }
    $action = 'list';
}

// 6. --- LOAD DATA FOR DISPLAY ---
$areas = [];
$result = $db->query("SELECT * FROM delivery_areas ORDER BY area_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $areas[] = $row;
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Column 1: Add/Edit Form -->
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <?php echo ($action === 'edit') ? 'Edit Area' : 'Add New Area'; ?>
            </h2>
            
            <form action="manage_delivery_areas.php" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">

                <?php if ($action === 'edit' && $area_id): ?>
                    <input type="hidden" name="area_id" value="<?php echo e($area_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="area_name" class="block text-sm font-medium text-gray-700">
                        Area Name (e.g., "Gulshan", "Dhanmondi")
                    </label>
                    <input type="text" id="area_name" name="area_name" value="<?php echo e($area_name); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="base_charge" class="block text-sm font-medium text-gray-700">
                        Base Charge (BDT)
                    </label>
                    <input type="number" step="0.01" id="base_charge" name="base_charge" value="<?php echo e($base_charge); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="is_active" name="is_active" value="1" <?php echo ($is_active) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="is_active" class="ml-2 block text-sm text-gray-900">
                        Active (Visible to customers)
                    </label>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Add Area'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_delivery_areas.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Area List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Delivery Areas (<?php echo count($areas); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Area Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Base Charge</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($areas)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No delivery areas found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($areas as $area): ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($area['area_name']); ?></div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-700"><?php echo e(number_format($area['base_charge'], 2)); ?> BDT</div></td>
                                    <td class="px-6 py-4">
                                        <?php if ($area['is_active']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="manage_delivery_areas.php?action=edit&id=<?php echo e($area['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <!-- (MODIFIED) Added CSRF token to delete link -->
                                        <a href="manage_delivery_areas.php?action=delete&id=<?php echo e($area['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this area?');">Delete</a>
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
// 7. FOOTER
require_once('footer.php');
?>