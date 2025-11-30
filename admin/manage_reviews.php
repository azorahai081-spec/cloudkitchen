<?php
/*
 * admin/manage_reviews.php
 * PizzaMania: Cloud Kitchen Manual Review Manager
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
$review_id = $_GET['id'] ?? null;
$page_title = 'Manage Customer Reviews';

// Form data placeholders
$customer_name = '';
$rating = 5;
$review_text = '';
$is_visible = 1;

$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $customer_name = $_POST['customer_name'];
        $rating = (int)$_POST['rating'];
        $review_text = trim($_POST['review_text']);
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        if (empty($customer_name) || empty($review_text) || $rating < 1 || $rating > 5) {
            $error_message = 'Customer Name, Review Text, and a valid Rating (1-5) are required.';
        } else {
            if (isset($_POST['review_id']) && !empty($_POST['review_id'])) {
                // --- UPDATE existing review ---
                $review_id_to_update = $_POST['review_id'];
                $sql = "UPDATE admin_reviews SET customer_name = ?, rating = ?, review_text = ?, is_visible = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('sisi', $customer_name, $rating, $review_text, $is_visible, $review_id_to_update);
                
                if ($stmt->execute()) {
                    $success_message = 'Review updated successfully!';
                } else {
                    $error_message = 'Failed to update review.';
                }
                $stmt->close();
                
            } else {
                // --- CREATE new review ---
                $sql = "INSERT INTO admin_reviews (customer_name, rating, review_text, is_visible) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('sisi', $customer_name, $rating, $review_text, $is_visible);
                
                if ($stmt->execute()) {
                    $success_message = 'Review created successfully!';
                    $customer_name = ''; $rating = 5; $review_text = ''; $is_visible = 1; // Clear form
                } else {
                    $error_message = 'Failed to create review.';
                }
                $stmt->close();
            }
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Edit & Delete) ---

if ($action === 'edit' && $review_id) {
    $page_title = 'Edit Review';
    $sql = "SELECT * FROM admin_reviews WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $review_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $review = $result->fetch_assoc();
        $customer_name = $review['customer_name'];
        $rating = $review['rating'];
        $review_text = $review['review_text'];
        $is_visible = $review['is_visible'];
    } else {
        $error_message = 'Review not found.';
        $action = 'list';
    }
    $stmt->close();
}

if ($action === 'delete' && $review_id) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $sql = "DELETE FROM admin_reviews WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $review_id);
        
        if ($stmt->execute()) {
            $success_message = 'Review deleted successfully!';
        } else {
            $error_message = 'Failed to delete review.';
        }
        $stmt->close();
    }
    $action = 'list';
}

// 6. --- LOAD DATA FOR DISPLAY ---
$reviews = [];
$result = $db->query("SELECT * FROM admin_reviews ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
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
                <?php echo ($action === 'edit') ? 'Edit Review' : 'Add New Review'; ?>
            </h2>
            
            <form action="manage_reviews.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <?php if ($action === 'edit' && $review_id): ?>
                    <input type="hidden" name="review_id" value="<?php echo e($review_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="customer_name" class="block text-sm font-medium text-gray-700">Customer Name</label>
                    <input type="text" id="customer_name" name="customer_name" value="<?php echo e($customer_name); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>

                <div>
                    <label for="rating" class="block text-sm font-medium text-gray-700">Rating (1-5)</label>
                    <select id="rating" name="rating" required class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="5" <?php echo ($rating == 5) ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo ($rating == 4) ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo ($rating == 3) ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo ($rating == 2) ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo ($rating == 1) ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>

                <div>
                    <label for="review_text" class="block text-sm font-medium text-gray-700">Review Text</label>
                    <textarea id="review_text" name="review_text" rows="4" required
                              class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($review_text); ?></textarea>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="is_visible" name="is_visible" value="1" <?php echo ($is_visible) ? 'checked' : ''; ?>
                           class="h-4 w-4 text-orange-600 border-gray-300 rounded focus:ring-orange-500">
                    <label for="is_visible" class="ml-2 block text-sm text-gray-900">
                        Visible on website
                    </label>
                </div>

                <div class="flex space-x-2">
                    <button type="submit" class="w-full py-3 px-4 bg-orange-600 text-white font-medium rounded-lg shadow-md hover:bg-orange-700">
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Add Review'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_reviews.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: Review List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing Reviews (<?php echo count($reviews); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rating</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Review</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($reviews)): ?>
                            <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No reviews found. Add one!</td></tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($review['customer_name']); ?></div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-yellow-500 font-bold"><?php echo e($review['rating']); ?> &star;</div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-500"><?php echo e(substr($review['review_text'], 0, 50)); ?>...</div></td>
                                    <td class="px-6 py-4">
                                        <?php if ($review['is_visible']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Visible</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="manage_reviews.php?action=edit&id=<?php echo e($review['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <a href="manage_reviews.php?action=delete&id=<?php echo e($review['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this review?');">Delete</a>
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