<?php
/*
 * admin/manage_faq.php
 * KitchCo: Cloud Kitchen FAQ Manager
 * Version 1.0 - New file
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
$faq_id = $_GET['id'] ?? null;
$page_title = 'Manage FAQ';

// Form data placeholders
$question = '';
$answer = '';
$display_order = 0;
$is_visible = 1;

$error_message = '';
$success_message = '';

// 4. --- HANDLE POST REQUESTS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $question = $_POST['question'];
        $answer = $_POST['answer'];
        $display_order = (int)$_POST['display_order'];
        $is_visible = isset($_POST['is_visible']) ? 1 : 0;
        
        if (empty($question) || empty($answer)) {
            $error_message = 'Question and Answer are required.';
        } else {
            if (isset($_POST['faq_id']) && !empty($_POST['faq_id'])) {
                // --- UPDATE existing FAQ ---
                $faq_id_to_update = $_POST['faq_id'];
                $sql = "UPDATE faq SET question = ?, answer = ?, display_order = ?, is_visible = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssiii', $question, $answer, $display_order, $is_visible, $faq_id_to_update);
                
                if ($stmt->execute()) {
                    $success_message = 'FAQ updated successfully!';
                } else {
                    $error_message = 'Failed to update FAQ.';
                }
                $stmt->close();
                
            } else {
                // --- CREATE new FAQ ---
                $sql = "INSERT INTO faq (question, answer, display_order, is_visible) VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->bind_param('ssii', $question, $answer, $display_order, $is_visible);
                
                if ($stmt->execute()) {
                    $success_message = 'FAQ created successfully!';
                    $question = ''; $answer = ''; $display_order = 0; $is_visible = 1; // Clear form
                } else {
                    $error_message = 'Failed to create FAQ.';
                }
                $stmt->close();
            }
        }
    }
}

// 5. --- HANDLE GET ACTIONS (Edit & Delete) ---

if ($action === 'edit' && $faq_id) {
    $page_title = 'Edit FAQ';
    $sql = "SELECT * FROM faq WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $faq_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $faq = $result->fetch_assoc();
        $question = $faq['question'];
        $answer = $faq['answer'];
        $display_order = $faq['display_order'];
        $is_visible = $faq['is_visible'];
    } else {
        $error_message = 'FAQ not found.';
        $action = 'list';
    }
    $stmt->close();
}

if ($action === 'delete' && $faq_id) {
    if (!validate_csrf_token()) {
        $error_message = 'Invalid or expired session. Please try again.';
    } else {
        $sql = "DELETE FROM faq WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $faq_id);
        
        if ($stmt->execute()) {
            $success_message = 'FAQ deleted successfully!';
        } else {
            $error_message = 'Failed to delete FAQ.';
        }
        $stmt->close();
    }
    $action = 'list';
}

// 6. --- LOAD DATA FOR DISPLAY ---
$faqs = [];
$result = $db->query("SELECT * FROM faq ORDER BY display_order ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
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
                <?php echo ($action === 'edit') ? 'Edit FAQ' : 'Add New FAQ'; ?>
            </h2>
            
            <form action="manage_faq.php" method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo e(get_csrf_token()); ?>">
                
                <?php if ($action === 'edit' && $faq_id): ?>
                    <input type="hidden" name="faq_id" value="<?php echo e($faq_id); ?>">
                <?php endif; ?>

                <div>
                    <label for="question" class="block text-sm font-medium text-gray-700">Question</label>
                    <textarea id="question" name="question" rows="3" required
                              class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($question); ?></textarea>
                </div>

                <div>
                    <label for="answer" class="block text-sm font-medium text-gray-700">Answer</label>
                    <textarea id="answer" name="answer" rows="5" required
                              class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500"><?php echo e($answer); ?></textarea>
                </div>

                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                    <input type="number" id="display_order" name="display_order" value="<?php echo e($display_order); ?>" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
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
                        <?php echo ($action === 'edit') ? 'Save Changes' : 'Add FAQ'; ?>
                    </button>
                    <?php if ($action === 'edit'): ?>
                        <a href="manage_faq.php" class="w-full py-3 px-4 bg-gray-200 text-gray-700 text-center font-medium rounded-lg shadow-md hover:bg-gray-300">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Column 2: FAQ List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-lg">
            <h2 class="text-xl font-bold text-gray-900 mb-4 p-6 border-b border-gray-200">
                Existing FAQs (<?php echo count($faqs); ?>)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Question</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($faqs)): ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No FAQs found. Add one!</td></tr>
                        <?php else: ?>
                            <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td class="px-6 py-4"><div class="text-sm font-medium text-gray-900"><?php echo e($faq['display_order']); ?></div></td>
                                    <td class="px-6 py-4"><div class="text-sm text-gray-900"><?php echo e(substr($faq['question'], 0, 50)); ?>...</div></td>
                                    <td class="px-6 py-4">
                                        <?php if ($faq['is_visible']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Visible</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm font-medium space-x-2">
                                        <a href="manage_faq.php?action=edit&id=<?php echo e($faq['id']); ?>" class="text-orange-600 hover:text-orange-900">Edit</a>
                                        <a href="manage_faq.php?action=delete&id=<?php echo e($faq['id']); ?>&csrf_token=<?php echo e(get_csrf_token()); ?>" 
                                           class="text-red-600 hover:text-red-900" 
                                           onclick="return confirm('Are you sure you want to delete this FAQ?');">Delete</a>
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