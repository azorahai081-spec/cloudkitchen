<?php
/*
 * admin/order_details.php
 * KitchCo: Cloud Kitchen Order Details
 * Version 4.2 - Added Surcharge Display
 */

require_once('header.php');

// 1. GET ORDER
$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) {
    header('Location: live_orders.php');
    exit;
}

// 2. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (validate_csrf_token()) {
        // Update Status
        if (isset($_POST['update_status'])) {
            $status = $_POST['new_status'];
            $stmt = $db->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $order_id);
            $stmt->execute();
        }
        // Assign Rider
        if (isset($_POST['assign_rider'])) {
            $rider = $_POST['rider_name'];
            $stmt = $db->prepare("UPDATE orders SET rider_name = ? WHERE id = ?");
            $stmt->bind_param('si', $rider, $order_id);
            $stmt->execute();
        }
    }
    // Refresh
    echo "<script>window.location.href='order_details.php?id=$order_id';</script>";
    exit;
}

// 3. FETCH DATA
$stmt = $db->prepare("SELECT o.*, da.area_name, da.base_charge FROM orders o LEFT JOIN delivery_areas da ON o.delivery_area_id = da.id WHERE o.id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die("Order not found");

// Items
$items = [];
$stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $img_q = $db->query("SELECT image, name FROM menu_items WHERE id = {$row['menu_item_id']}");
    $img_data = $img_q->fetch_assoc();
    $row['image'] = $img_data['image'] ?? '';
    $row['real_name'] = $img_data['name'] ?? '[Deleted]';
    
    $opts = [];
    $opt_q = $db->query("SELECT * FROM order_item_options WHERE order_item_id = {$row['id']}");
    while($opt = $opt_q->fetch_assoc()) $opts[] = $opt;
    $row['opts'] = $opts;
    $items[] = $row;
}

// Riders (From DB)
$riders = [];
$r_res = $db->query("SELECT name, phone FROM riders WHERE is_active = 1 ORDER BY name ASC");
while($r = $r_res->fetch_assoc()) $riders[] = $r;

// 4. HELPERS
$status_map = ['Pending' => 1, 'Preparing' => 2, 'Ready' => 3, 'Delivered' => 4, 'Cancelled' => 0];
$current_step = $status_map[$order['order_status']] ?? 1;

// Dynamic Badge Colors
$badge_colors = [
    'Pending' => 'bg-orange-100 text-orange-700 border-orange-200',
    'Preparing' => 'bg-blue-100 text-blue-700 border-blue-200',
    'Ready' => 'bg-purple-100 text-purple-700 border-purple-200',
    'Delivered' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
    'Cancelled' => 'bg-red-100 text-red-700 border-red-200',
];
$status_badge_class = $badge_colors[$order['order_status']] ?? 'bg-gray-100 text-gray-700 border-gray-200';

// Action Logic
$next_action = '';
$next_status = '';
$btn_color = 'bg-orange-500 hover:bg-orange-600';

if ($order['order_status'] == 'Pending') {
    $next_action = 'Accept & Start Cooking';
    $next_status = 'Preparing';
    $btn_color = 'bg-blue-600 hover:bg-blue-700 shadow-blue-200';
} elseif ($order['order_status'] == 'Preparing') {
    $next_action = 'Mark Ready for Pickup';
    $next_status = 'Ready';
    $btn_color = 'bg-purple-600 hover:bg-purple-700 shadow-purple-200';
} elseif ($order['order_status'] == 'Ready') {
    $next_action = 'Mark as Delivered';
    $next_status = 'Delivered';
    $btn_color = 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-200';
}

// Surcharge Calc
$base = (float)($order['base_charge'] ?? 0);
$saved_fee = (float)$order['delivery_fee'];
$adj = (float)($order['delivery_adjustment'] ?? 0);
// The 'surcharge' is whatever is left in the delivery fee after removing base charge and manual adjustment
$surcharge = max(0, $saved_fee - $base - $adj);
?>

<!-- Header Area -->
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold text-gray-900 tracking-tight">#PM-<?php echo $order['id']; ?></h1>
            <span class="px-3 py-1 rounded-full text-xs font-bold border uppercase tracking-wider <?php echo $status_badge_class; ?>">
                <?php echo $order['order_status']; ?>
            </span>
        </div>
        <div class="flex items-center text-sm text-gray-500 mt-1 gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Placed: <?php echo date('d M, h:i A', strtotime($order['order_time'])); ?>
        </div>
    </div>

    <!-- Status Overrider (Rollback) -->
    <form method="POST" class="flex gap-2 items-center">
        <span class="text-xs font-medium text-gray-400 uppercase">Override Status:</span>
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <select name="new_status" class="text-sm border-gray-300 rounded-lg shadow-sm focus:border-gray-500 focus:ring-gray-500 py-1.5">
            <option value="Pending" <?php echo ($order['order_status']=='Pending')?'selected':''; ?>>Pending</option>
            <option value="Preparing" <?php echo ($order['order_status']=='Preparing')?'selected':''; ?>>Preparing</option>
            <option value="Ready" <?php echo ($order['order_status']=='Ready')?'selected':''; ?>>Ready</option>
            <option value="Delivered" <?php echo ($order['order_status']=='Delivered')?'selected':''; ?>>Delivered</option>
            <option value="Cancelled" <?php echo ($order['order_status']=='Cancelled')?'selected':''; ?>>Cancelled</option>
        </select>
        <button type="submit" name="update_status" class="bg-white hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-300 shadow-sm transition-colors">Update</button>
    </form>
</div>

<!-- Visual Stepper -->
<?php if ($order['order_status'] != 'Cancelled'): ?>
<div class="mb-10 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
    <div class="relative flex items-center justify-between w-full max-w-4xl mx-auto">
        <!-- Background Line -->
        <div class="absolute left-0 top-[20px] w-full h-1.5 bg-gray-100 rounded-full -z-10"></div>
        
        <!-- Active Line -->
        <?php 
            $progress_width = 0;
            if($current_step > 1) $progress_width = ($current_step - 1) * 33.33;
            // Colors for the line based on stage
            $line_color = 'bg-gray-300';
            if ($current_step >= 2) $line_color = 'bg-blue-500';
            if ($current_step >= 3) $line_color = 'bg-purple-500';
            if ($current_step >= 4) $line_color = 'bg-emerald-500';
        ?>
        <div class="absolute left-0 top-[20px] h-1.5 <?php echo $line_color; ?> rounded-full -z-10 transition-all duration-700 ease-in-out" style="width: <?php echo $progress_width; ?>%"></div>

        <?php
        $steps = [
            1 => ['label'=>'Accepted', 'icon'=>'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'active_color'=>'bg-emerald-500 text-white ring-emerald-100', 'text_color'=>'text-emerald-600'],
            2 => ['label'=>'Cooking', 'icon'=>'M15.362 5.214A8.252 8.252 0 0112 21 8.25 8.25 0 016.038 7.048 8.287 8.287 0 009 9.6a8.983 8.983 0 013.361-6.867 8.21 8.21 0 003 2.48z', 'active_color'=>'bg-blue-500 text-white ring-blue-100', 'text_color'=>'text-blue-600'],
            3 => ['label'=>'Ready', 'icon'=>'M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.263-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z', 'active_color'=>'bg-purple-500 text-white ring-purple-100', 'text_color'=>'text-purple-600'],
            4 => ['label'=>'Delivered', 'icon'=>'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12', 'active_color'=>'bg-gray-800 text-white ring-gray-200', 'text_color'=>'text-gray-800']
        ];

        foreach($steps as $step_num => $data):
            $is_completed = $current_step > $step_num;
            $is_current = $current_step == $step_num;
            $is_pending = $current_step < $step_num;
            
            // Dynamic styling based on state
            $circle_class = 'bg-white border-2 border-gray-200 text-gray-300';
            $label_class = 'text-gray-400 font-medium';
            
            if ($is_completed) {
                $circle_class = 'bg-emerald-500 border-emerald-500 text-white';
                $label_class = 'text-emerald-600 font-bold';
            } elseif ($is_current) {
                $circle_class = $data['active_color'] . ' ring-4 border-transparent'; 
                $label_class = $data['text_color'] . ' font-bold';
            }
        ?>
        <div class="flex flex-col items-center z-10">
            <div class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 <?php echo $circle_class; ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $data['icon']; ?>"></path></svg>
            </div>
            <span class="text-xs mt-2 uppercase tracking-wide <?php echo $label_class; ?>"><?php echo $data['label']; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Note Alert -->
<?php if (!empty($order['order_note'])): ?>
<div class="mb-8">
    <div class="flex items-center gap-4 p-4 bg-red-50 border border-red-100 rounded-xl">
        <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        </div>
        <div>
            <h3 class="font-bold text-red-900 text-sm uppercase tracking-wide">Customer Note</h3>
            <p class="text-red-700 font-medium mt-0.5">"<?php echo e($order['order_note']); ?>"</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <!-- ITEMS COLUMN -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                <h2 class="font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Items Ordered <span class="bg-gray-200 text-gray-600 text-xs py-0.5 px-2 rounded-full ml-1"><?php echo count($items); ?></span>
                </h2>
                <?php if ($current_step < 4): ?>
                    <a href="edit_order.php?id=<?php echo $order_id; ?>" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">Edit Order</a>
                <?php endif; ?>
            </div>

            <div class="divide-y divide-gray-50">
                <?php foreach ($items as $item): 
                    $img_src = !empty($item['image']) ? BASE_URL . $item['image'] : 'https://placehold.co/100x100/EFEFEF/AAAAAA?text=No+Image';
                ?>
                <div class="p-5 hover:bg-gray-50 transition-colors flex items-start gap-5">
                    <div class="w-16 h-16 rounded-xl bg-gray-100 flex-shrink-0 overflow-hidden border border-gray-200 shadow-sm">
                        <img src="<?php echo e($img_src); ?>" class="w-full h-full object-cover" alt="Item">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <h3 class="font-bold text-gray-900 text-lg truncate pr-4"><?php echo e($item['real_name']); ?></h3>
                            <span class="font-bold text-gray-900 whitespace-nowrap"><?php echo number_format($item['total_price'], 2); ?></span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <?php if (!empty($item['opts'])): ?>
                                <?php foreach ($item['opts'] as $opt): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                    <?php echo e($opt['option_name']); ?> (+<?php echo number_format($opt['option_price'], 2); ?>)
                                </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-sm text-gray-400 italic">No options selected</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-lg font-bold text-gray-400 ml-2 bg-gray-100 px-2 py-1 rounded-lg border border-gray-200">x<?php echo $item['quantity']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-gray-50/80 p-6 border-t border-gray-200">
                <div class="space-y-3 text-sm text-gray-600 max-w-sm ml-auto">
                    <div class="flex justify-between"><span>Subtotal</span><span class="font-medium text-gray-900"><?php echo number_format($order['subtotal'], 2); ?></span></div>
                    
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-emerald-600 bg-emerald-50 px-2 py-1 rounded">
                        <span>Discount</span>
                        <span>-<?php echo number_format($order['discount_amount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <span>Delivery Fee (<?php echo e($order['area_name']); ?>)</span>
                        <span class="font-medium text-gray-900"><?php echo number_format($order['delivery_fee'], 2); ?></span>
                    </div>

                    <!-- (NEW) Surcharge Display -->
                    <?php if ($surcharge > 0): ?>
                    <div class="flex justify-between text-orange-600 bg-orange-50 px-2 py-1 rounded text-xs">
                        <span>↳ Includes Night Surcharge</span>
                        <span>+<?php echo number_format($surcharge, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($adj != 0): ?>
                    <div class="flex justify-between text-gray-500 italic">
                        <span>Manual Adjustment</span>
                        <span><?php echo ($adj > 0 ? '+' : '') . number_format($adj, 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-6 pt-4 border-t border-gray-200 flex justify-between items-center">
                    <div class="text-sm font-medium text-black-500 uppercase tracking-wider">Total Amount</div>
                    <!-- Improved Money Color -->
                    <div class="font-extrabold text-3xl text-emerald-600 flex items-baseline gap-1">
                        <?php echo number_format($order['total_amount'], 2); ?> <span class="text-sm font-bold text-emerald-600/60">BDT</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CUSTOMER COLUMN -->
    <div class="space-y-6">
        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-xs font-bold text-black uppercase tracking-wider mb-6 border-b border-gray-100 pb-2">Customer Information</h3>
            
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-full bg-slate-800 text-white flex items-center justify-center font-bold text-xl shadow-md">
                    <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                </div>
                <div>
                    <p class="font-bold text-gray-900 text-lg leading-tight"><?php echo e($order['customer_name']); ?></p>
                    <p class="text-sm text-gray-500">Customer</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-8">
                <a href="tel:<?php echo e($order['customer_phone']); ?>" class="flex items-center justify-center gap-2 py-2.5 px-4 border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition shadow-sm">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                    Call
                </a>
                <?php $wa_phone = preg_replace('/[^0-9]/', '', $order['customer_phone']); ?>
                <!-- Official WhatsApp Green -->
                <a href="https://wa.me/<?php echo $wa_phone; ?>" target="_blank" class="flex items-center justify-center gap-2 py-2.5 px-4 bg-[#25D366]/10 border border-[#25D366]/20 rounded-xl text-sm font-semibold text-[#128C7E] hover:bg-[#25D366]/20 transition shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.008-.57-.008-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
                    WhatsApp
                </a>
            </div>
            
            <div class="space-y-5">
                <div>
                    <label class="text-xs text-black font-bold uppercase tracking-wider flex items-center gap-1 mb-2">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
    </svg>
    Delivery Address
</label>

                    <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-sm text-gray-800 leading-relaxed">
                        <?php echo e($order['customer_address']); ?>
                    </div>
                </div>
                
                <div>
                    <label class="text-xs text-black font-bold uppercase tracking-wider flex items-center gap-1 mb-2">
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
    </svg>
    Assigned Rider
</label>

                    <form method="POST" class="flex gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
                        <div class="relative w-full">
                            <select name="rider_name" class="appearance-none block w-full text-sm border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500 p-2.5 bg-white">
                                <option value="">Select Rider...</option>
                                <?php foreach ($riders as $r): ?>
                                    <option value="<?php echo e($r['name']); ?>" <?php echo ($order['rider_name'] == $r['name']) ? 'selected' : ''; ?>>
                                        <?php echo e($r['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </div>
                        </div>
                        <button type="submit" name="assign_rider" class="bg-blue-600 text-white px-4 rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm transition-colors">Save</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <a href="print_receipt.php?id=<?php echo $order_id; ?>&copy=chef" target="_blank" class="group flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all hover:-translate-y-0.5">
                <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center mb-2 group-hover:bg-white group-hover:text-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                </div>
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide group-hover:text-gray-800">Chef Copy</span>
            </a>
            <a href="print_receipt.php?id=<?php echo $order_id; ?>&copy=customer" target="_blank" class="group flex flex-col items-center justify-center p-4 bg-white border border-gray-200 rounded-xl shadow-sm hover:bg-gray-50 hover:border-gray-300 transition-all hover:-translate-y-0.5">
                <div class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 flex items-center justify-center mb-2 group-hover:bg-white group-hover:text-gray-800 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
                <span class="text-xs font-bold text-gray-600 uppercase tracking-wide group-hover:text-gray-800">Receipt</span>
            </a>
        </div>
    </div>
</div>

<!-- Sticky Footer -->
<?php if (!empty($next_action)): ?>
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 shadow-lg z-40 md:hidden">
    <form method="POST" class="grid grid-cols-1 gap-3">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <input type="hidden" name="new_status" value="<?php echo $next_status; ?>">
        <button type="submit" name="update_status" class="w-full <?php echo $btn_color; ?> text-white font-bold py-3.5 px-4 rounded-xl shadow-md transition flex items-center justify-center gap-2 text-lg">
            <?php echo $next_action; ?>
        </button>
    </form>
</div>
<div class="hidden md:block fixed bottom-8 right-8 z-40">
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <input type="hidden" name="new_status" value="<?php echo $next_status; ?>">
        <button type="submit" name="update_status" class="<?php echo $btn_color; ?> text-white font-bold py-4 px-8 rounded-full shadow-xl hover:shadow-2xl hover:scale-105 transition transform flex items-center gap-3 text-lg border-2 border-white/20">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <?php echo $next_action; ?>
        </button>
    </form>
</div>
<?php endif; ?>

<?php require_once('footer.php'); ?>