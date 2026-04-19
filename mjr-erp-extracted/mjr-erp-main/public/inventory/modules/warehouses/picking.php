<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

// Hum maan lete hain ki hum Order ID #101 ke liye picking kar rahe hain
$order_id = $_GET['order_id'] ?? 0;

// Logic: Warehouse Inventory se details nikaalna aur Bin locations dikhana
$pick_list = db_fetch_all("
    SELECT i.name, i.code as sku, wi.bin_location, wi.quantity as available
    FROM inventory_items i
    JOIN warehouse_inventory wi ON i.id = wi.product_id
    WHERE wi.quantity > 0 
    ORDER BY wi.bin_location ASC
");

$page_title = "Warehouse Picking List";
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="card shadow border-0">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <h5 class="mb-0">Picking List - Order #<?php echo $order_id; ?></h5>
            <button onclick="window.print()" class="btn btn-sm btn-light"><i class="fas fa-print"></i> Print List</button>
        </div>
        <div class="card-body">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Bin Location (Rack)</th>
                        <th>Product Details</th>
                        <th>Available</th>
                        <th>Required Qty</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pick_list as $item): ?>
                    <tr>
                        <td><strong class="text-primary"><?php echo $item['bin_location']; ?></strong></td>
                        <td>
                            <strong><?php echo $item['name']; ?></strong><br>
                            <small class="text-muted">SKU: <?php echo $item['sku']; ?></small>
                        </td>
                        <td><?php echo $item['available']; ?></td>
                        <td><input type="number" class="form-control form-control-sm" style="width: 80px;"></td>
                        <td>
                            <button class="btn btn-sm btn-success">Mark Picked</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>