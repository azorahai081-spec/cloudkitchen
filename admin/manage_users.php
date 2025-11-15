<?php
/*
 * admin/manage_users.php
 * KitchCo: Cloud Kitchen User Manager
 * Version 1.2 - Added "Last Admin" check
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
$user_id = $_GET['id'] ?? null;
$page_title = 'Manage Users';

$username = '';
$role = 'manager';

$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $username = $_POST['username'];
        $role = $_POST['role'];
        $password = $_POST['password'];
        
        if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
            // --- UPDATE existing user ---
            $user_id_to_update = $_POST['user_id'];
            
            if (!empty($password)) {
                if (strlen($password) < 8) {
                    $error_message = "Password must be at least 8 characters long.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE admin_users SET username = ?, role = ?, password = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->bind_param('sssi', $username, $role, $hashed_password, $user_id_to_update);
                }
            } else {
                // Update without changing password
                $sql = "UPDATE admin_users SET username = ?, role = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssi', $username, $role, $user_id_to_update);
            }
            
            if (empty($error_message) && $stmt->execute()) {
                $success_message = 'User updated successfully!';
            } elseif (empty($error_message)) {
                $error_message = 'Failed to update user. Username may already exist.';
            }
            $stmt->close();
            
        } else {
            // --- CREATE new user ---
            if (empty($username) || empty($password) || empty($role)) {
                $error_message = 'Username, password, and role are required.';
            } elseif (strlen($password) < 8) {
                $error_message = "Password must be at least 8 characters long.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO admin_users (username, password, role) VALUES (?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('sss', $username, $hashed_password, $role);
                
                if ($stmt->execute()) {
                    $success_message = 'User created successfully!';
                    $username = ''; $role = 'manager';
                } else {
                    $error_message = 'Failed to create user. Username may already exist.';
                }
                $stmt->close();
            }
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Edit & Delete) ---

if ($action === 'edit' && $user_id) {
    $page_title = 'Edit User';
    $sql = "SELECT username, role FROM admin_users WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $username = $user['username'];
        $role = $user['role'];
    } else {
        $error_message = 'User not found.';
        $action = 'list';
    }
    $stmt->close();
}

if ($action === 'delete' && $user_id) {
    // (NEW) CSRF Token validation
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } elseif ($user_id == $_SESSION['user_id']) {
        $error_message = "You cannot delete your own account.";
    } else {
        
        // --- (MODIFIED) "LAST ADMIN" CHECK ---
        // First, check if this user is an admin
        $role_sql = "SELECT role FROM admin_users WHERE id = ?";
        $role_stmt = $db->prepare($role_sql);
        $role_stmt->bind_param('i', $user_id);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $user_to_delete = $role_result->fetch_assoc();

        if ($user_to_delete && $user_to_delete['role'] === 'admin') {
            // This is an admin. Check if they are the last one.
            $count_sql = "SELECT COUNT(*) as admin_count FROM admin_users WHERE role = 'admin'";
            $count_result = $db->query($count_sql);
            $admin_count = $count_result->fetch_assoc()['admin_count'];
            
            if ($admin_count <= 1) {
                $error_message = 'Cannot delete the last admin account.';
            }
        }
        $role_stmt->close();
        // --- END "LAST ADMIN" CHECK ---
        
        // Only proceed if there is no error
        if (empty($error_message)) {
            $sql = "DELETE FROM admin_users WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $user_id);
            
            if ($stmt->execute()) {
                $success_message = 'User deleted successfully!';
            } else {
                $error_message = 'Failed to delete user.';
            }
            $stmt->close();
        }
    }
    $action = 'list';
}

// 6. --- LOAD DATA FOR DISPLAY ---
$users = [];
$result = $db->query("SELECT id, username, role, created_at FROM admin_users ORDER BY username ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
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
                <?php echo ($action === 'edit') ? 'Edit User' : 'Add New User'; ?>
            </h2>
            
            <form action="manage_users.php" method="POST" class="space-y-4">
                <!-- (NEW) CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <?php if ($action === 'edit' && $user_id): ?>
                    <input type="hidden" name="user_id" value="<?php echo e($user_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo e($username); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">
                        Password
                        <?php if ($action === 'edit'): ?>
                            <span class="text-xs text-gray-500">(Leave blank to keep current password)</span>
                        <?php endif; ?>
                    </label>
                    <input type="password" id="password" name="password" <?php echo ($action !== 'edit') ? 'required' : ''; ?>
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                    <select id="role" name="role" class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="manager" <?php echo ($role === 'manager') ? 'selected' : ''; ?>>Manager</option>
                        <option value="admin" <?php echo ($role === 'admin') ? 'selected' : ''; ?>>Admin (Owner)</option>
                    </select>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Create User'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_users.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: User List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Users (<?php echo count($users); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($users)): ?>
                            <tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($user['username']); ?></div></td>
                                    <td class="px-6 py-4"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($user['role'] === 'admin' ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'); ?> capitalize"><?php echo e($user['role']); ?></span></td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="manage_users.php?action=edit&id=<?php echo e($user['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <!-- (MODIFIED) Added CSRF token to delete link -->
                                            <a href="manage_users.php?action=delete&id=<?php echo e($user['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                               class="text-red-600 hover:text-red-900" 
                                               onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                                        <?php endif; ?>
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