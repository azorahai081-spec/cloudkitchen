<?php
/*
 * reviews.php
 * PizzaMania: Cloud Kitchen Full Reviews Page
 *
 * This page displays all visible, manually-added customer reviews.
 */

// 1. PAGE SETUP
$page_title = 'Customer Reviews - Pizza Mania';
$meta_description = 'See what our customers are saying about our food!';

// 2. HEADER
require_once('includes/header.php');

// 3. --- LOAD ALL REVIEWS ---
$reviews = [];
$sql = "SELECT * FROM admin_reviews WHERE is_visible = 1 ORDER BY id DESC";
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $reviews[] = $row;
    }
}

// Helper function to render stars
function render_stars($rating)
{
    $html = '<div class="flex text-yellow-400">';
    for ($i = 0; $i < 5; $i++) {
        if ($i < $rating) {
            // Full star
            $html .= '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
        } else {
            // Empty star (though you'll likely only have full stars)
            $html .= '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>';
        }
    }
    $html .= '</div>';
    return $html;
}
?>

<!-- Main Content -->
<section class="py-16">
    <h1 class="text-3xl font-bold text-gray-900 mb-10 text-center">What Our Customers Say</h1>

    <?php if (empty($reviews)): ?>
        <p class="text-center text-gray-600">No reviews yet. Be the first to leave one!</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

            <?php foreach ($reviews as $review): ?>
                <!-- Review Card -->
                <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-brand-red">
                    <div class="flex items-center space-x-4">
                        <div
                            class="flex-shrink-0 w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-lg">
                            <?php echo e(strtoupper(substr($review['customer_name'], 0, 2))); ?>
                        </div>
                        <div>
                            <div class="font-bold text-gray-900"><?php echo e($review['customer_name']); ?></div>
                            <?php echo render_stars($review['rating']); ?>
                        </div>
                    </div>
                    <p class="text-gray-600 mt-4 italic">"<?php echo nl2br(e($review['review_text'])); ?>"</p>
                    <div class="flex items-center mt-4">
                        <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-2 text-sm font-medium text-green-600">Verified Customer</span>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    <?php endif; ?>

    <!-- "See All Reviews" Button -->
    <div class="text-center mt-12">
        <a href="<?php echo BASE_URL; ?>/menu.php"
            class="px-8 py-3 bg-brand-red text-white font-semibold rounded-lg shadow-md hover:bg-red-700 transition-colors">
            &larr; Back to Menu
        </a>
    </div>
</section>

<?php
// 4. FOOTER
require_once('includes/footer.php');
?>