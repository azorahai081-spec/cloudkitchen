<?php
/*
 * menu.php
 * PizzaMania: Cloud Kitchen Full Menu Page
 * Version 2.0 - (UPDATED) Added JS for Floating Cart Bar Update
 *
 * This page:
 * 1. Loads all visible categories for filtering.
 * 2. Loads ALL visible items, grouped by category.
 * 3. Calculates and displays global discounts.
 * 4. Includes the "Item Options" modal popup.
 * 5. Handles adding items to the cart via AJAX.
 */

// 1. PAGE SETUP
$page_title = 'Full Menu - ' . ($settings['store_name'] ?? 'Pizza Mania');
$meta_description = 'Browse our full menu of delicious, fresh meals.';

// 2. HEADER
require_once('includes/header.php');

// Helper function to apply global discount
function calculate_discounted_price($original_price, $settings)
{
    if (empty($settings['global_discount_active']) || $settings['global_discount_active'] == '0' || empty($settings['global_discount_value']) || $settings['global_discount_value'] <= 0) {
        return $original_price;
    }

    $discount_type = $settings['global_discount_type'];
    $discount_value = (float) $settings['global_discount_value'];
    $new_price = $original_price;

    if ($discount_type == 'percentage') {
        $new_price = $original_price - ($original_price * ($discount_value / 100));
    } elseif ($discount_type == 'fixed') {
        $new_price = $original_price - $discount_value;
    }

    // Don't let price go below 0
    return ($new_price > 0) ? $new_price : 0;
}


// 3. --- LOAD DATA FOR DISPLAY ---

$page_heading = 'Our Full Menu';

// --- A. Load Categories for Sidebar ---
$categories = [];
$sql_cat = "SELECT id, name FROM categories WHERE is_visible = 1 ORDER BY name ASC";
$result_cat = $db->query($sql_cat);
if ($result_cat) {
    while ($row = $result_cat->fetch_assoc()) {
        $categories[] = $row;
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

$sql_menu .= " ORDER BY c.name ASC, m.name ASC";

$result_menu = $db->query($sql_menu);
if ($result_menu) {
    while ($row = $result_menu->fetch_assoc()) {
        // Apply global discount logic
        $original_price = (float) $row['item_price'];
        $discounted_price = calculate_discounted_price($original_price, $settings);

        $row['original_price'] = $original_price;
        $row['item_price'] = $discounted_price;
        $row['has_discount'] = ($discounted_price < $original_price);

        // Group items by their category name
        $menu[$row['category_name']][] = $row;
    }
}

// 4. --- Schema.org JSON-LD for Menu ---
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
    'name' => $settings['store_name'] ?? 'Pizza Mania' . ' Full Menu',
    'hasMenuItem' => $schema_menu_items
];
?>

<!-- Schema.org Script -->
<script type="application/ld+json">
<?php echo json_encode($schema_menu, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
</script>

<h1 id="menu-heading" class="text-3xl font-bold text-gray-900 mb-8"><?php echo e($page_heading); ?></h1>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-8">

    <!-- Column 1: Category Filter Sidebar -->
    <aside class="lg:col-span-1 hidden lg:block">
        <div class="bg-white p-6 rounded-2xl shadow-lg sticky top-24">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Categories</h2>
            <ul class="space-y-2">
                <li>
                    <a href="#menu-heading"
                        class="block px-3 py-2 rounded-lg font-medium text-gray-700 hover:bg-gray-100">
                        All Categories
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="#category-<?php echo e($category['id']); ?>"
                            class="block px-3 py-2 rounded-lg font-medium text-gray-700 hover:bg-gray-100">
                            <?php echo e($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>

    <!-- Column 2: Menu Items -->
    <div class="lg:col-span-3 space-y-8">

        <!-- Search Bar -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-200 sticky top-20 z-30">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" id="menu-search" placeholder="Search for pizza, burger, pasta..."
                    class="block w-full pl-10 pr-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-red focus:border-transparent bg-gray-50 focus:bg-white transition-all shadow-sm">
            </div>
        </div>

        <div id="menu-container">
            <?php if (empty($menu)): ?>
                <div class="bg-white p-8 rounded-2xl shadow-lg text-center">
                    <h3 class="text-xl font-bold text-gray-900">No Items Found</h3>
                    <p class="text-gray-600 mt-2">
                        Our menu is currently empty. Please check back later!
                    </p>
                </div>
            <?php else: ?>
                <!-- Loop through each Category -->
                <?php foreach ($menu as $category_name => $items): ?>
                    <section id="category-<?php echo e($items[0]['category_id']); ?>" class="menu-section scroll-mt-32 mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4 pb-2 border-b-2 border-brand-red">
                            <?php echo e($category_name); ?>
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Loop through each Item in this Category -->
                            <?php foreach ($items as $item): ?>
                                <div id="item-<?php echo e($item['item_id']); ?>"
                                    class="menu-item bg-white rounded-2xl shadow-lg overflow-hidden flex transform transition-all hover:shadow-xl">
                                    <img src="<?php echo e(BASE_URL . ($item['item_image'] ?? 'https://placehold.co/150x150/EFEFEF/AAAAAA?text=No+Image')); ?>"
                                        alt="<?php echo e($item['item_name']); ?>" class="w-32 h-full object-cover flex-shrink-0"
                                        onerror="this.src='https://placehold.co/150x150/EFEFEF/AAAAAA?text=No+Image'">
                                    <div class="p-5 flex flex-col justify-between w-full">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 item-name">
                                                <?php echo e($item['item_name']); ?></h3>
                                            <p class="text-sm text-gray-600 mt-1 item-desc line-clamp-2">
                                                <?php echo e($item['item_description']); ?></p>
                                        </div>
                                        <div class="flex justify-between items-center mt-4">
                                            <span class="text-xl font-bold text-brand-red">
                                                <?php if ($item['has_discount']): ?>
                                                    <?php echo e(number_format($item['item_price'], 0)); ?> BDT
                                                    <span
                                                        class="text-sm text-gray-500 line-through ml-1"><?php echo e(number_format($item['original_price'], 0)); ?></span>
                                                <?php else: ?>
                                                    <?php echo e(number_format($item['item_price'], 0)); ?> BDT
                                                <?php endif; ?>
                                            </span>
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

                <!-- No Search Results Message -->
                <div id="no-search-results" class="hidden text-center py-12">
                    <div class="inline-block p-4 rounded-full bg-gray-100 mb-4 text-gray-400">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900">No items found</h3>
                    <p class="text-gray-500">Try searching for something else.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- 
=====================================================
    ITEM OPTIONS MODAL (Hidden by default)
=====================================================
-->
<div id="item-options-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-lg w-full max-w-lg transform transition-all opacity-0 -translate-y-10"
        id="modal-content">
        <!-- Modal Header -->
        <div class="flex justify-between items-center p-5 border-b">
            <h2 id="modal-item-name" class="text-2xl font-bold text-gray-900">Item Options</h2>
            <button id="modal-close-btn" class="p-2 text-gray-500 hover:text-gray-800 rounded-full">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                    stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
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
            <div
                class="p-5 border-t bg-gray-50 rounded-b-2xl flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-sm font-medium text-gray-700">Quantity:</span>
                    <input id="modal-quantity" type="number" value="1" min="1"
                        class="w-20 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-brand-red">
                </div>
                <button id="modal-add-to-cart-btn" type="submit"
                    class="w-full sm:w-auto px-6 py-3 bg-brand-red text-white font-bold rounded-lg shadow-md hover:bg-red-700 transition-colors disabled:bg-gray-400">
                    Add to Cart (Total: <span id="modal-total-price">0</span>)
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
    // --- Menu Search Logic ---
    const menuSearchInput = document.getElementById('menu-search');

    if (menuSearchInput) {
        menuSearchInput.addEventListener('input', function (e) {
            const term = e.target.value.toLowerCase().trim();
            const sections = document.querySelectorAll('.menu-section');
            const noResultsMsg = document.getElementById('no-search-results');
            let globalMatchCount = 0;

            sections.forEach(section => {
                const items = section.querySelectorAll('.menu-item');
                let visibleItemsCount = 0;

                items.forEach(item => {
                    const name = item.querySelector('.item-name').textContent.toLowerCase();
                    const desc = item.querySelector('.item-desc').textContent.toLowerCase();

                    if (name.includes(term) || desc.includes(term)) {
                        item.style.display = 'flex';
                        visibleItemsCount++;
                        globalMatchCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Hide category if no items match
                if (visibleItemsCount > 0) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });

            if (globalMatchCount === 0 && sections.length > 0) {
                noResultsMsg.classList.remove('hidden');
            } else {
                noResultsMsg.classList.add('hidden');
            }
        });
    }

    // --- GTM Data Layer ---
    window.dataLayer.push({
        event: 'view_item_list',
        ecommerce: {
            item_list_name: '<?php echo e($page_heading); ?>',
            items: [
                <?php foreach ($menu as $category => $items) {
                    foreach ($items as $item) {
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
    const modalAddToCartBtn = document.getElementById('modal-add-to-cart-btn');
    const csrfToken = '<?php echo e(get_csrf_token()); ?>';

    // --- (NEW) Floating Cart Elements ---
    const floatCartBar = document.getElementById('floating-cart-bar');
    const floatCartCount = document.getElementById('float-cart-count');
    const floatCartTotal = document.getElementById('float-cart-total');

    async function openItemModal(itemId, itemName, basePrice) {
        modal.style.display = 'flex';
        modalItemName.textContent = itemName;
        modalOptionsContent.innerHTML = '<p class="text-gray-500 text-center">Loading options...</p>';
        modalQuantity.value = 1;
        modalItemId.value = itemId;
        modalBasePrice.value = basePrice;

        modalAddToCartBtn.disabled = false;
        modalAddToCartBtn.innerHTML = 'Add to Cart (Total: <span id="modal-total-price">0</span>)';

        setTimeout(() => {
            modalContent.classList.remove('opacity-0', '-translate-y-10');
            modalContent.classList.add('opacity-100', 'translate-y-0');
        }, 10);

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

        try {
            const response = await fetch(`ajax_get_item_details.php?id=${itemId}`);
            if (!response.ok) throw new Error('Network error');

            const data = await response.json();

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
                                    <span class="text-sm text-gray-600 mr-3">+${parseInt(option.price_increase)}</span>
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
            updateModalPrice();

        } catch (error) {
            modalOptionsContent.innerHTML = `<p class="text-red-500 text-center">Error loading options: ${error.message}</p>`;
        }
    }

    function closeModal() {
        modalContent.classList.add('opacity-0', '-translate-y-10');
        modalContent.classList.remove('opacity-100', 'translate-y-0');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
    }

    function updateModalPrice() {
        let optionsPrice = 0;
        const modalTotalPriceSpan = document.getElementById('modal-total-price');
        const selectedOptions = modalOptionsContent.querySelectorAll('input:checked');

        selectedOptions.forEach(opt => {
            optionsPrice += parseInt(opt.dataset.price);
        });

        const basePrice = parseInt(modalBasePrice.value);
        const quantity = parseInt(modalQuantity.value) || 1;
        const total = (basePrice + optionsPrice) * quantity;

        if (modalTotalPriceSpan) {
            modalTotalPriceSpan.textContent = total;
        }
    }

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
        for (let node of modalAddToCartBtn.childNodes) {
            if (node.nodeType === Node.TEXT_NODE) {
                node.textContent = 'Adding... ';
                break;
            }
        }

        try {
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('item_id', itemId);
            formData.append('quantity', quantity);

            selectedOptions.forEach(optId => {
                formData.append('options[]', optId);
            });

            formData.append('csrf_token', csrfToken);

            const response = await fetch('cart_actions.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) throw new Error('Server error');

            const result = await response.json();

            if (result.success) {
                // 1. Update cart bubble count in header
                document.getElementById('cart-count-bubble').textContent = result.cart_count;
                
                // 2. (NEW) Update Floating Cart Bar
                if (floatCartBar && floatCartCount && floatCartTotal) {
                    floatCartCount.textContent = result.cart_count;
                    floatCartTotal.textContent = result.cart_total; // Note: cart_actions.php should return integer now
                    floatCartBar.classList.remove('hidden');
                }

                // GTM Event
                window.dataLayer.push({
                    event: 'add_to_cart',
                    ecommerce: {
                        items: [{
                            item_id: itemId,
                            item_name: modalItemName.textContent,
                            price: parseInt(modalBasePrice.value),
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
            modalAddToCartBtn.innerHTML = 'Add to Cart (Total: <span id="modal-total-price">0</span>)';
            updateModalPrice();
        }
    }

    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    modalQuantity.addEventListener('input', updateModalPrice);
    modalForm.addEventListener('submit', handleAddToCart);

</script>

<?php
// 5. FOOTER
require_once('includes/footer.php');
?>