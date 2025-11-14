<?php
/*
 * admin/print_receipt.php
 * KitchCo: Cloud Kitchen Thermal Receipt
 * Version 1.1 - Fixed ALL column name bugs
 *
 * This page is STYLED FOR A 58mm THERMAL PRINTER.
 * It does NOT include the admin header or footer.
 * It has two modes:
 * 1. ?copy=chef (Chef Copy: Large font, items only)
 * 2. ?copy=customer (Customer Copy: Full details, prices, rider)
 */

// 1. CONFIGURATION
require_once('../config.php');

// 2. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Access Denied.';
    exit;
}

// 3. --- GET ORDER ID & COPY TYPE ---
$order_id = $_GET['id'] ?? null;
$copy_type = $_GET['copy'] ?? 'customer'; // Default to customer copy

if (empty($order_id)) {
    die('No order ID specified.');
}
$order_id = (int)$order_id;

// 4. --- LOAD ORDER DATA ---
// A. Load Order Header
// (FIXED) Changed 'o.order_id' to 'o.id'
$sql_order = "SELECT o.*, da.area_name 
              FROM orders o
              LEFT JOIN delivery_areas da ON o.delivery_area_id = da.id
              WHERE o.id = ?";
$stmt_order = $db->prepare($sql_order);
$stmt_order->bind_param('i', $order_id);
$stmt_order->execute();
$result_order = $stmt_order->get_result();

if ($result_order->num_rows == 0) {
    die('Order not found.');
}
$order = $result_order->fetch_assoc();

// B. Load Order Items & Options
$order_items = [];
$sql_items = "SELECT oi.*, mi.name as item_name
              FROM order_items oi
              JOIN menu_items mi ON oi.menu_item_id = mi.id
              WHERE oi.order_id = ?";
$stmt_items = $db->prepare($sql_items);
$stmt_items->bind_param('i', $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

while ($item_row = $result_items->fetch_assoc()) {
    // (FIXED) Changed 'order_item_id' to 'id' to match DB table
    $order_item_id = $item_row['id']; 
    $item_row['options'] = [];
    $sql_options = "SELECT * FROM order_item_options WHERE order_item_id = ?";
    $stmt_options = $db->prepare($sql_options);
    $stmt_options->bind_param('i', $order_item_id);
    $stmt_options->execute();
    $result_options = $stmt_options->get_result();
    
    while ($option_row = $result_options->fetch_assoc()) {
        $item_row['options'][] = $option_row;
    }
    $order_items[] = $item_row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Order #<?php echo e($order_id); ?></title>
    <!-- Thermal Printer Styling -->
    <style>
        @page {
            margin: 2mm; /* Adjust margins for 58mm printer */
        }
        body {
            font-family: 'Arial', sans-serif;
            font-size: 10px; /* Base font size */
            line-height: 1.4;
            color: #000;
            width: 100%;
            padding: 0;
            margin: 0;
        }
        .receipt {
            width: 100%;
            max-width: 300px; /* Approx 58mm width */
            margin: 0 auto;
        }
        h1, h2, h3, p, div {
            margin: 0;
            padding: 0;
        }
        h1 {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }
        h2 {
            font-size: 12px;
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 3px 0;
            margin: 5px 0;
            text-align: center;
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 5px;
        }
        .item-list {
            margin-top: 5px;
        }
        .item {
            margin-bottom: 3px;
        }
        .item-name {
            font-weight: bold;
            font-size: 12px;
        }
        .item-options {
            padding-left: 10px;
            font-size: 10px;
        }
        .totals {
            margin-top: 5px;
            border-top: 1px solid #000;
            padding-top: 3px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
        }
        .total-row.grand-total {
            font-weight: bold;
            font-size: 14px;
            margin-top: 3px;
        }
        .info {
            margin-top: 5px;
        }
        .info div {
            margin-bottom: 2px;
        }
        
        /* Chef Copy Specifics */
        <?php if ($copy_type == 'chef'): ?>
        .chef-item {
            margin-bottom: 5px;
        }
        .chef-item-name {
            font-size: 16px; /* Larger font */
            font-weight: bold;
        }
        .chef-item-options {
            padding-left: 15px;
            font-size: 14px; /* Larger font */
        }
        <?php endif; ?>
    </style>
</head>
<body onload="window.print(); setTimeout(window.close, 1000);">

    <div class="receipt">
        <?php if ($copy_type == 'chef'): ?>
            <!--
            ========================
                CHEF COPY
            ========================
            -->
            <h2>** CHEF COPY **</h2>
            <h1>Order #<?php echo e($order_id); ?> (<?php echo e($order['order_status']); ?>)</h1>
            <div class="info">
                <div><strong>Time:</strong> <?php echo e(date('h:i A', strtotime($order['order_time']))); ?></div>
            </div>
            
            <div class="item-list">
                <?php foreach ($order_items as $item): ?>
                <div class="chef-item">
                    <div class="chef-item-name">
                        <?php echo e($item['quantity']); ?>x <?php echo e($item['item_name']); ?>

                    </div>
                    <?php if (!empty($item['options'])): ?>
                    <div class="chef-item-options">
                        <?php foreach ($item['options'] as $option): ?>
                        <div>- <?php echo e($option['option_name']); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
        <?php else: ?>
            <!--
            ========================
                CUSTOMER COPY
            ========================
            -->
            <div class="header">
                <h1>KitchCo</h1>
                <p>Order #<?php echo e($order_id); ?></p>
                <p><?php echo e(date('d M Y, h:i A', strtotime($order['order_time']))); ?></p>
            </div>
            
            <h2>** CUSTOMER COPY **</h2>
            
            <div class="info">
                <div><strong>Customer:</strong> <?php echo e($order['customer_name']); ?></div>
                <div><strong>Phone:</strong> <?php echo e($order['customer_phone']); ?></div>
                <div><strong>Address:</strong> <?php echo e($order['customer_address']); ?></div>
                <div><strong>Area:</strong> <?php echo e($order['area_name'] ?? 'N/A'); ?></div>
                <!-- (FIXED) Changed 'assigned_rider_name' to 'rider_name' -->
                <div><strong>Rider:</strong> <?php echo e($order['rider_name'] ?? 'Not Assigned'); ?></div>
            </div>

            <div class="item-list">
                <?php foreach ($order_items as $item): ?>
                <div class="item">
                    <div class="total-row">
                        <span class="item-name">
                            <?php echo e($item['quantity']); ?>x <?php echo e($item['item_name']); ?>

                        </span>
                        <span><?php echo e(number_format($item['total_price'], 2)); ?></span>
                    </div>
                    <?php if (!empty($item['options'])): ?>
                    <div class="item-options">
                        <?php foreach ($item['options'] as $option): ?>
                        <div>+ <?php echo e($option['option_name']); ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="totals">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span><?php echo e(number_format($order['subtotal'], 2)); ?></span>
                </div>
                <div class="total-row">
                    <span>Delivery Fee:</span>
                    <span><?php echo e(number_format($order['delivery_fee'], 2)); ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span><?php echo e(number_format($order['total_amount'], 2)); ?> BDT</span>
                </div>
            </div>
            
            <div class="footer">
                <p style="margin-top: 10px;">Thank you for your order!</p>
                <p>Payment: Cash on Delivery</p>
            </div>
        
        <?php endif; ?>
    </div>

</body>
</html>