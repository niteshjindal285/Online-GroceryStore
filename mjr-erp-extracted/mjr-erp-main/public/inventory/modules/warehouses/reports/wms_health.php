<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

// 1. Stock Levels Check
$items = db_fetch_all("SELECT i.name, i.code, i.reorder_level, i.max_stock_level,
                      IFNULL(SUM(wi.quantity), 0) as total_qty
                      FROM inventory_items i
                      LEFT JOIN warehouse_inventory wi ON i.id = wi.product_id
                      GROUP BY i.id");

$page_title = "WMS Inventory Health";
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4">Warehouse Inventory Analytics</h2>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-danger text-white shadow-sm border-0">
                <div class="card-body">
                    <h6>Out of Stock / Critical</h6>
                    <?php 
                        $low_stock_count = count(array_filter($items, function($i) { 
                            return $i['total_qty'] <= $i['reorder_level']; 
                        }));
                    ?>
                    <h3><?php echo $low_stock_count; ?> Items</h3>
                    <small>Action required: Reorder immediately</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white font-weight-bold">Stock Status Report</div>
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Item Details</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th>Utilization</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <strong><?php echo $item['name']; ?></strong><br>
                            <small class="text-muted"><?php echo $item['code']; ?></small>
                        </td>
                        <td><?php echo $item['total_qty']; ?> Units</td>
                        <td>
                            <?php if($item['total_qty'] <= $item['reorder_level']): ?>
                                <span class="badge bg-danger">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">Healthy</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="progress" style="height: 8px; width: 150px;">
                                <?php 
                                    $usage = ($item['total_qty'] / ($item['max_stock_level'] ?: 100)) * 100;
                                ?>
                                <div class="progress-bar bg-info" style="width: <?php echo $usage; ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>