<?php
/*
 * menu.php
 * KitchCo: Cloud Kitchen Full Menu Page
 * Version 1.3 - (MODIFIED) Redesigned buttons
 *
 * This page:
 * 1. Loads all visible categories for filtering.
 * 2. Loads all visible items, grouped by category.
 * 3. Can be filtered by a GET param: menu.php?category=ID
 * 4. Includes the "Item Options" modal popup.
 * 5. Handles adding items to the cart via AJAX.
 */

// 1. PAGE SETUP
$page_title = 'Full Menu - KitchCo';
$meta_description = 'Browse our full menu of delicious, fresh meals.';

// 2. HEADER
require_once('includes/header.php');

// 3. --- LOAD DATA FOR DISPLAY ---

// Get the category filter, if any
$filter_category_id = $_GET['category'] ?? null;
$page_heading = 'Our Full Menu';

// --- A. Load Categories for Sidebar ---
$categories = [];
$sql_cat = "SELECT id, name FROM categories WHERE is_visible = 1 ORDER BY name ASC";
$result_cat = $db->query($sql_cat);
if ($result_cat) {
    while ($row = $result_cat->fetch_assoc()) {
        $categories[] = $row;
        if ($filter_category_id && $row['id'] == $filter_category_id) {
            $page_heading = 'Menu: ' . e($row['name']);
        }
    }
}

// --- B. Load All Menu Items (grouped by category) ---
$menu = [];
$sql_menu = "SELECT 
                c.id as category_id, 
                c.name as category_name, 
                m.id as item_id, 
                m.name as item_name, 
                m.description as item_description, 
                m.price as item_price, 
                m.image as item_image
             FROM menu_items m
             JOIN categories c ON m.category_id = c.id
             WHERE m.is_available = 1 AND c.is_visible = 1";

if ($filter_category_id) {
    $sql_menu .= " AND c.id = " . intval($filter_category_id);
}
$sql_menu .= " ORDER BY c.name ASC, m.name ASC";

$result_menu = $db->query($sql_menu);
if ($result_menu) {
    while ($row = $result_menu->fetch_assoc()) {
        // Group items by their category name
        $menu[$row['category_name']][] = $row;
    }
}

// 4. --- (NEW) Schema.org JSON-LD for Menu ---
$schema_menu_items = [];
foreach ($menu as $category => $items) {
    foreach ($items as $item) {
        $schema_menu_items[] = [
            '@type' => 'MenuItem',
            'name' => $item['item_name'],
            'description' => $item['item_description'],
            'image' => BASE_URL . ($item['item_image'] ?? ''),
            'offers' => [
                '@type' => 'Offer',
                'price' => $item['item_price'],
                'priceCurrency' => 'BDT'
            ]
        ];
    }
}

$schema_menu = [
    '@context' => 'https://schema.org',
    '@type' => 'Menu',
    'name' => 'KitchCo Full Menu',
    'hasMenuItem' => $schema_menu_items
];
?>

<!-- (NEW) Schema.org Script -->
<script type="application/ld+json">
<?php echo json_encode($schema_menu, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<!-- Page Heading -->
<h1 class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_heading); ?></h1>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
    
    <!-- Column 1: Category Filter Sidebar -->
    <aside class="lg:col-span-1">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-24">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Categories</h2>
            <ul class="space-y-2">
                <li>
                    <!-- (MODIFIED) Clean URL -->
                    <a href="<?php echo BASE_URL; ?>/menu" class="block px-3 py-2 rounded-lg font-medium <?php echo !$filter_category_id ? 'bg-orange-100 text-brand-red' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        All Categories
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                <li>
                    <!-- (MODIFIED) Clean URL for categories -->
                    <a href="<?php echo BASE_URL; ?>/menu/category/<?php echo e($category['id']); ?>" class="block px-3 py-2 rounded-lg font-medium <?php echo ($filter_category_id == $category['id']) ? 'bg-orange-100 text-brand-red' : 'text-gray-700 hover:bg-gray-100'; ?>">
                        <?php echo e($category['name']); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Column 2: Menu Items -->
    <div class="lg:col-span-3 space-y-12">
        <?php if (empty($menu)): ?>
            <div class="bg-white p-8 rounded-2xl shadow-lg text-center">
                <h3 class="text-xl font-bold text-gray-900">No Items Found</h3>
                <p class="text-gray-600 mt-2">
                    <?php if ($filter_category_id): ?>
                        There are no available items in this category right now.
                    <?php else: ?>
                        Our menu is currently empty. Please check back later!
                    <?php endif; ?>
                </p>
                <!-- (MODIFIED) Clean URL -->
                <a href="<?php echo BASE_URL; ?>/menu" class="mt-4 inline-block px-5 py-2 bg-brand-red text-white font-medium rounded-lg">
                    View All Categories
                </a>
            </div>
        <?php else: ?>
            <!-- Loop through each Category -->
            <?php foreach ($menu as $category_name => $items): ?>
                <section id="category-<?php echo e($items[0]['category_id']); ?>">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-red">
                        <?php echo e($category_name); ?>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Loop through each Item in this Category -->
                        <?php foreach ($items as $item): ?>
                            <div id="item-<?php echo e($item['item_id']); ?>" class="bg-white rounded-2xl shadow-lg overflow-hidden flex">
                                <img 
                                    src="<?php echo e(BASE_URL . ($item['item_image'] ?? 'https://placehold.co/150x150/EFEFEF/AAAAAA?text=No+Image')); ?>" 
                                    alt="<?php echo e($item['item_name']); ?>"
                                    class="w-32 h-full object-cover"
                                    onerror="this.src='https://placehold.co/150x150/EFEFEF/AAAAAA?text=No+Image'"
                                >
                                <div class="p-5 flex flex-col justify-between w-full">
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-900"><?php echo e($item['item_name']); ?></h3>
                                        <p class="text-sm text-gray-600 mt-1"><?php echo e($item['item_description']); ?></p>
                                    </div>
                                    <div class="flex justify-between items-center mt-4">
                                        <span class="text-xl font-bold text-brand-red"><?php echo e(number_format($item['item_price'], 2)); ?> BDT</span>
                                        <!-- (MODIFIED) Button styling updated -->
                                        <button 
                                            onclick="openItemModal(<?php echo e($item['item_id']); ?>, '<?php echo e(addslashes($item['item_name'])); ?>', <?php echo e($item['item_price']); ?>)"
                                            class="px-4 py-2 bg-brand-red text-white font-medium rounded-lg shadow-md hover:bg-red-700 transition-all transform hover:scale-105 <?php echo ($store_is_open == '0') ? 'hidden' : ''; ?>">
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>


<!-- 
=====================================================
    ITEM OPTIONS MODAL (Hidden by default)
=====================================================
-->
<div id="item-options-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg transform transition-all opacity-0 -translate-y-10" id="modal-content">
        <!-- Modal Header -->
        <div class="flex justify-between items-center p-5 border-b">
            <h2 id="modal-item-name" class="text-2xl font-bold text-gray-900">Item Options</h2>
            <button id="modal-close-btn" class="p-2 text-gray-500 hover:text-gray-800 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <!-- Modal Body: Options -->
        <form id="item-options-form">
            <input type="hidden" id="modal-item-id" value="">
            <input type="hidden" id="modal-base-price" value="">
            
            <div id="modal-options-content" class="p-6 max-h-[60vh] overflow-y-auto space-y-5">
                <!-- JS will populate this -->
                <p class="text-gray-500 text-center">Loading options...</p>
            </div>
            
            <!-- Modal Footer: Quantity & Price -->
            <div class="p-5 border-t bg-gray-50 rounded-b-2xl flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-700">Quantity:</span>
                    <input id="modal-quantity" type="number" value="1" min="1"
                           class="w-20 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-red">
                </div>
                <!-- (MODIFIED) Button styling updated from brand-orange to brand-red and added disabled state -->
                <button id="modal-add-to-cart-btn" type="submit" class="w-full sm:w-auto px-6 py-3 bg-brand-red text-white font-bold rounded-lg shadow-md hover:bg-red-700 transition-colors disabled:bg-gray-400">
                    Add to Cart (Total: <span id="modal-total-price">0.00</span>)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 
=====================================================
    JAVASCRIPT LOGIC
=====================================================
-->
<script>
    // --- GTM Data Layer (view_item_list) ---
    window.dataLayer.push({
        event: 'view_item_list',
        ecommerce: {
            item_list_name: '<?php echo e($page_heading); ?>',
            items: [
                <?php foreach($menu as $category => $items) {
                    foreach($items as $item) {
                        echo "{
                            item_id: '{$item['item_id']}',
                            item_name: '{$item['item_name']}',
                            item_category: '{$item['category_name']}',
                            price: {$item['item_price']}
                        },";
                    }
                } ?>
            ]
        }
    });

    // --- Modal Elements ---
    const modal = document.getElementById('item-options-modal');
    const modalContent = document.getElementById('modal-content');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalItemName = document.getElementById('modal-item-name');
    const modalOptionsContent = document.getElementById('modal-options-content');
    const modalForm = document.getElementById('item-options-form');
    
    const modalItemId = document.getElementById('modal-item-id');
    const modalBasePrice = document.getElementById('modal-base-price');
    const modalQuantity = document.getElementById('modal-quantity');
    const modalTotalPrice = document.getElementById('modal-total-price');
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');
    // (NEW) Get CSRF token from config.php (via header.php)
    const csrfToken = '<?php echo e(get_csrf_token()); ?>';

    /**
     * Opens the Item Options modal
     */
    async function openItemModal(itemId, itemName, basePrice) {
        // 1. Reset and show modal
        modal.style.display = 'flex';
        modalItemName.textContent = itemName;
        modalOptionsContent.innerHTML = '<p class="text-gray-500 text-center">Loading options...</p>';
        modalQuantity.value = 1;
        modalItemId.value = itemId;
        modalBasePrice.value = basePrice;
        
        // Modal animations
        setTimeout(() => {
            modalContent.classList.remove('opacity-0', '-translate-y-10');
            modalContent.classList.add('opacity-100', 'translate-y-0');
        }, 10);

        // --- GTM Data Layer (view_item) ---
        window.dataLayer.push({
            event: 'view_item',
            ecommerce: {
                items: [{
                    item_id: itemId,
                    item_name: itemName,
                    price: basePrice
                }]
            }
        });

        // 2. Fetch item options
        try {
            // (MODIFIED) Point to the new public AJAX file
            const response = await fetch(`ajax_get_item_details.php?id=${itemId}`);
            if (!response.ok) throw new Error('Network error');
            
            const data = await response.json();
            
            // 3. Build options HTML
            let optionsHtml = '';
            if (data.option_groups && data.option_groups.length > 0) {
                data.option_groups.forEach(group => {
                    optionsHtml += `<fieldset class="space-y-2">`;
                    optionsHtml += `<legend class="text-sm font-medium text-gray-900 border-b pb-1 mb-2">${group.name} (${group.type === 'radio' ? 'Choose 1' : 'Choose any'})</legend>`;
                    
                    group.options.forEach(option => {
                        const inputType = group.type === 'radio' ? 'radio' : 'checkbox';
                        optionsHtml += `
                            <div class="flex items-center justify-between">
                                <label for="option-${option.id}" class="text-sm text-gray-700 flex-1">
                                    ${option.name}
                                </label>
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-600 mr-3">+${parseFloat(option.price_increase).toFixed(2)}</span>
                                    <input 
                                        type="${inputType}" 
                                        id="option-${option.id}" 
                                        name="option_group[${group.id}][]" 
                                        value="${option.id}"
                                        data-price="${option.price_increase}"
                                        class="h-4 w-4 text-brand-red border-gray-300 focus:ring-brand-red"
                                        onchange="updateModalPrice()"
                                        ${inputType === 'radio' ? 'required' : ''}
                                    >
                                </div>
                            </div>
                        `;
                    });
                    optionsHtml += `</fieldset>`;
                });
            } else {
                optionsHtml = '<p class="text-gray-500 text-center">This item has no options.</p>';
            }
            
            modalOptionsContent.innerHTML = optionsHtml;
            updateModalPrice(); // Set initial price

        } catch (error) {
            modalOptionsContent.innerHTML = `<p class="text-red-500 text-center">Error loading options: ${error.message}</p>`;
        }
    }

    /**
     * Closes the Item Options modal
     */
    function closeModal() {
        modalContent.classList.add('opacity-0', '-translate-y-10');
        modalContent.classList.remove('opacity-100', 'translate-y-0');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300); // Wait for animation
    }

    /**
     * Updates the total price in the modal based on selected options and quantity
     */
    function updateModalPrice() {
        let optionsPrice = 0;
        const selectedOptions = modalOptionsContent.querySelectorAll('input:checked');
        
        selectedOptions.forEach(opt => {
            optionsPrice += parseFloat(opt.dataset.price);
        });
        
        const basePrice = parseFloat(modalBasePrice.value);
        const quantity = parseInt(modalQuantity.value) || 1;
        const total = (basePrice + optionsPrice) * quantity;
        
        modalTotalPrice.textContent = total.toFixed(2);
    }
    
    /**
     * Handles the submission of the options form (Add to Cart)
     */
    async function handleAddToCart(event) {
        event.preventDefault();
        
        const itemId = modalItemId.value;
        const quantity = modalQuantity.value;
        const selectedOptions = [];
        const selectedElements = modalOptionsContent.querySelectorAll('input:checked');
        
        selectedElements.forEach(el => {
            selectedOptions.push(el.value);
        });
        
        modalAddToCartBtn.disabled = true;
        modalAddToCartBtn.innerHTML = 'Adding...';

        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('item_id', itemId);
            formData.append('quantity', quantity);
            
            // Append options as an array
            selectedOptions.forEach(optId => {
                formData.append('options[]', optId);
            });
            
            // (NEW) Add CSRF token to the form data
            formData.append('csrf_token', csrfToken);

            const response = await fetch('cart_actions.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) throw new Error('Server error');
            
            const result = await response.json();
            
            if (result.success) {
                // Update cart bubble count
                document.getElementById('cart-count-bubble').textContent = result.cart_count;
                // --- GTM Data Layer (add_to_cart) ---
                window.dataLayer.push({
                    event: 'add_to_cart',
                    ecommerce: {
                        items: [{
                            item_id: itemId,
                            item_name: modalItemName.textContent,
                            price: parseFloat(modalBasePrice.value),
                            quantity: parseInt(quantity)
                        }]
                    }
                });
                closeModal();
            } else {
                throw new Error(result.message || 'Failed to add item');
            }

        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            modalAddToCartBtn.disabled = false;
            updateModalPrice(); // Re-renders the button text
        }
    }

    // --- Event Listeners ---
    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal(); // Close on backdrop click
    });
    modalQuantity.addEventListener('input', updateModalPrice);
    modalForm.addEventListener('submit', handleAddToCart);

</script>

<?php
// 5. FOOTER
require_once('includes/footer.php');
?>