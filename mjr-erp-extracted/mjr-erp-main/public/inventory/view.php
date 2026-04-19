<?php
/**
 * Inventory Module - View Item
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();
ensure_inventory_transaction_reporting_schema();

$item_id = intval(get_param('id'));

if (!$item_id) {
    set_flash('Invalid item ID.', 'error');
    redirect('index.php');
}

// Get item details
$sql = "
    SELECT i.*, 
           c.name as category_name, 
           u.name as unit_name, u.code as unit_code,
           s.name as supplier_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE i.id = ?
";

$item = db_fetch($sql, [$item_id]);

if (!$item) {
    set_flash('Item not found.', 'error');
    redirect('index.php');
}

// Get stock levels by location
$stock_levels = db_fetch_all("
    SELECT l.name as location_name, 
           s.quantity_on_hand, s.quantity_reserved, s.quantity_available
    FROM inventory_stock_levels s
    JOIN locations l ON s.location_id = l.id
    WHERE s.item_id = ?
    ORDER BY l.name
", [$item_id]);

// Get recent transactions using detailed report service
$transactions = inventory_fetch_transaction_report_rows(['item_id' => $item_id], 20);

$page_title = 'View Item: ' . $item['name'];

include __DIR__ . '/../../templates/header.php';
?>

<div class="mb-4">
    <h2><i class="fas fa-box me-2"></i><?= escape_html($item['name']) ?></h2>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
            <li class="breadcrumb-item active"><?= escape_html($item['code']) ?></li>
        </ol>
    </nav>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Item Details -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Item Details</h5>
                <div>
                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                    <a href="delete.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-danger" 
                       onclick="return confirm('Are you sure you want to delete this item?')">
                        <i class="fas fa-trash me-1"></i>Delete
                    </a>
                </div>
            </div>
            <div class="card-body">
                <table class="table table-dark">
                    <tr>
                        <th width="30%">Item Code:</th>
                        <td><strong><?= escape_html($item['code']) ?></strong></td>
                    </tr>
                    <tr>
                        <th>Name:</th>
                        <td><?= escape_html($item['name']) ?></td>
                    </tr>
                    <tr>
                        <th>Description:</th>
                        <td><?= escape_html($item['description'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Category:</th>
                        <td><?= escape_html($item['category_name'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Unit of Measure:</th>
                        <td><?= escape_html($item['unit_name']) ?> (<?= escape_html($item['unit_code']) ?>)</td>
                    </tr>
                    <tr>
                        <th>Supplier Cost Price:</th>
                        <td><?= format_currency($item['cost_price']) ?></td>
                    </tr>
                    <tr>
                        <th>Average Unit Price:</th>
                        <td class="text-info"><?= format_currency($item['average_cost']) ?></td>
                    </tr>
                    <tr>
                        <th>Selling Price:</th>
                        <td>
                            <?= format_currency($item['selling_price']) ?>
                            <?php if ($item['price_includes_tax']): ?>
                                <small class="text-success ms-2">(Inc. 15% Tax)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Reorder Level:</th>
                        <td><?= format_number($item['reorder_level'], 0) ?></td>
                    </tr>
                    <tr>
                        <th>Reorder Quantity:</th>
                        <td><?= format_number($item['reorder_quantity'], 0) ?></td>
                    </tr>
                    <tr>
                        <th>Barcode:</th>
                        <td><?= escape_html($item['barcode'] ?? 'N/A') ?></td>
                    </tr>
                    <tr>
                        <th>Tracking:</th>
                        <td>
                            <?php if ($item['track_serial']): ?>
                                <span class="badge bg-info">Serial Numbers</span>
                            <?php endif; ?>
                            <?php if ($item['track_lot']): ?>
                                <span class="badge bg-info">Lot Numbers</span>
                            <?php endif; ?>
                            <?php if ($item['is_manufactured']): ?>
                                <span class="badge bg-warning">Manufactured</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Default Supplier:</th>
                        <td><?= escape_html($item['supplier_name'] ?? 'N/A') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Recent Detailed Transactions (Drill-down History) -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Complete Transaction History (Drill-down)</h5>
                <a href="transactions.php?item_id=<?= $item_id ?>" class="btn btn-sm btn-outline-light">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($transactions)): ?>
                    <p class="text-muted">No transaction history yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Ref / PO#</th>
                                    <th>Type</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Unit Price/Cost</th>
                                    <th>Supplier/Customer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?= format_date($tx['created_at'], DISPLAY_DATETIME_FORMAT) ?></td>
                                    <td>
                                        <?php if ($tx['reference_type'] === 'purchase_order'): ?>
                                            <a href="../inventory/purchase_order/view_purchase_order.php?id=<?= $tx['reference_id'] ?>" class="text-info">
                                                <?= escape_html($tx['reference_display']) ?>
                                            </a>
                                        <?php elseif ($tx['reference_type'] === 'sales_order'): ?>
                                            <a href="../sales/view_order.php?id=<?= $tx['reference_id'] ?>" class="text-info">
                                                <?= escape_html($tx['reference_display']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-info"><?= escape_html($tx['reference_display']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= intval($tx['quantity_signed']) >= 0 ? 'success' : 'danger' ?>">
                                            <?= escape_html($tx['transaction_label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <?= intval($tx['quantity_signed']) > 0 ? '+' : '' ?><?= format_number($tx['quantity_signed'], 0) ?>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <?= format_currency($tx['unit_cost']) ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tx['supplier_name'])): ?>
                                            <?= escape_html($tx['supplier_name']) ?> <small class="text-muted">(Supplier)</small>
                                        <?php elseif (!empty($tx['customer_name'])): ?>
                                            <?= escape_html($tx['customer_name']) ?> <small class="text-muted">(Customer)</small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Stock Levels -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Stock by Location</h5>
            </div>
            <div class="card-body">
                <?php if (empty($stock_levels)): ?>
                    <p class="text-muted">No stock recorded.</p>
                <?php else: ?>
                    <?php foreach ($stock_levels as $stock): ?>
                    <div class="mb-3">
                        <h6><?= escape_html($stock['location_name']) ?></h6>
                        <table class="table table-sm table-dark">
                            <tr>
                                <td>On Hand:</td>
                                <td><strong><?= format_number($stock['quantity_on_hand'], 0) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Reserved:</td>
                                <td><?= format_number($stock['quantity_reserved'], 0) ?></td>
                            </tr>
                            <tr>
                                <td>Available:</td>
                                <td class="text-success"><strong><?= format_number($stock['quantity_available'], 0) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>




