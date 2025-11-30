<?php
/*
 * admin/manage_riders.php
 * PizzaMania: Rider Management
 * Version 1.1 - Added Phone Validation
 */

require_once('header.php');

if (!hasAdminAccess()) {
    header('Location: live_orders.php');
    exit;
}

$page_title = 'Manage Riders';
$error_message = '';
$success_message = '';

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid session.';
    } else {
        // ADD RIDER
        if (isset($_POST['add_rider'])) {
            $name = trim($_POST['name']);
            $phone = trim($_POST['phone']);

            // Validation: Phone allows numbers, spaces, +, -, (, )
            if (!preg_match('/^[0-9\+\-\(\)\s]+$/', $phone)) {
                $error_message = "Invalid phone number format. Only numbers and symbols (+, -, space) allowed.";
            } elseif (!empty($name) && !empty($phone)) {
                $stmt = $db->prepare("INSERT INTO riders (name, phone) VALUES (?, ?)");
                $stmt->bind_param('ss', $name, $phone);
                if ($stmt->execute())
                    $success_message = "Rider added successfully.";
                else
                    $error_message = "Error adding rider.";
            } else {
                $error_message = "Name and Phone are required.";
            }
        }
        // DELETE RIDER
        if (isset($_POST['delete_rider'])) {
            $id = (int) $_POST['rider_id'];
            $db->query("DELETE FROM riders WHERE id = $id");
            $success_message = "Rider deleted.";
        }
    }
}

// FETCH RIDERS
$riders = [];
$res = $db->query("SELECT * FROM riders ORDER BY name ASC");
while ($row = $res->fetch_assoc())
    $riders[] = $row;
?>

<div class="flex flex-col md:flex-row gap-8">
    <!-- Add Form -->
    <div class="w-full md:w-1/3">
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Add New Rider</h2>

            <?php if ($success_message): ?>
                <div class="p-3 mb-4 bg-green-100 text-green-700 rounded"><?php echo $success_message; ?></div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="p-3 mb-4 bg-red-100 text-red-700 rounded"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Rider Name</label>
                        <input type="text" name="name" required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 p-2 border">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Phone Number</label>
                        <!-- Added pattern, inputmode, and type="tel" -->
                        <input type="tel" name="phone" required pattern="[0-9\+\-\(\)\s]+" inputmode="numeric"
                            title="Only numbers and +, -, (, ) are allowed" placeholder="e.g. 01700000000"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 p-2 border">
                    </div>
                    <button type="submit" name="add_rider"
                        class="w-full bg-orange-600 text-white py-2 px-4 rounded-md hover:bg-orange-700">Add
                        Rider</button>
                </div>
            </form>
        </div>
    </div>

    <!-- List -->
    <div class="w-full md:w-2/3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="font-bold text-gray-800">Rider List</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($riders)): ?>
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">No riders added yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($riders as $r): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?php echo e($r['name']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo e($r['phone']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <form method="POST" onsubmit="return confirm('Delete this rider?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                                        <input type="hidden" name="rider_id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" name="delete_rider"
                                            class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once('footer.php'); ?>