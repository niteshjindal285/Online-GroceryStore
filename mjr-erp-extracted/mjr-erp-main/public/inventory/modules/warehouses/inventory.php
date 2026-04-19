<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_once __DIR__ . '/../../../../includes/inventory_transaction_service.php';
require_login();

// Get warehouse ID from URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid Warehouse ID.");
}

// Fetch warehouse details
$warehouse = db_fetch_all("SELECT * FROM warehouses WHERE id = $id")[0] ?? null;

if (!$warehouse) { 
    header("Location: index.php"); 
    exit; 
}

// Fetch inventory by joining Modern (inventory_stock_levels) and Legacy (warehouse_inventory)
$inventory = db_fetch_all("
    SELECT
        isl.id AS id,
        isl.item_id AS product_id,
        isl.quantity_on_hand AS quantity,
        COALESCE(wi_grouped.total_qty, 0) AS legacy_quantity,
        COALESCE(NULLIF(isl.bin_location, ''), 'General Stock') AS bin_location,
        i.name AS prod_name,
        i.code AS sku,
        (isl.quantity_on_hand - COALESCE(wi_grouped.total_qty, 0)) as discrepancy
    FROM inventory_stock_levels isl
    JOIN inventory_items i ON isl.item_id = i.id
    LEFT JOIN (
        SELECT product_id, SUM(quantity) as total_qty 
        FROM warehouse_inventory 
        WHERE warehouse_id = ? 
        GROUP BY product_id
    ) wi_grouped ON wi_grouped.product_id = isl.item_id
    WHERE isl.location_id = ?
    ORDER BY i.name ASC
", [$id, $warehouse['location_id']]);

// If no modern records exist, try grabbing legacy ones (rare case)
if (empty($inventory)) {
    $inventory = db_fetch_all("
        SELECT
            wi.id,
            wi.product_id,
            wi.quantity,
            wi.quantity as legacy_quantity,
            COALESCE(wi.bin_location, 'General Stock') AS bin_location,
            i.name AS prod_name,
            i.code AS sku,
            0 as discrepancy
        FROM warehouse_inventory wi
        JOIN inventory_items i ON wi.product_id = i.id
        WHERE wi.warehouse_id = ?
        ORDER BY i.name ASC
    ", [$id]);
}

// Fetch all bin locations configured for this warehouse (including empty bins)
$all_bins = db_fetch_all("
    SELECT code
    FROM bins
    WHERE warehouse_id = ?
      AND COALESCE(is_active, 1) = 1
    ORDER BY code ASC
", [$id]);


// Calculate total stock and metrics
$total_stock = 0;
$total_value = 0;
foreach ($inventory as $item) {
    $total_stock += $item['quantity'];
}
$active_stock_take = inventory_get_active_stock_take_for_location(intval($warehouse['location_id'] ?? 0));
$is_stock_locked = !empty($active_stock_take);
$lock_reason = $is_stock_locked
    ? 'Locked: stock take ' . $active_stock_take['stock_take_number'] . ' is active for this warehouse.'
    : '';

$page_title = "Warehouse Inventory - " . htmlspecialchars($warehouse['name']);
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Success/Info Messages -->
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] == 'success' || $_GET['msg'] == 'audit_success'): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                if (isset($_GET['details'])) {
                    echo htmlspecialchars(urldecode($_GET['details']));
                } else {
                    echo "Operation completed successfully!";
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($is_stock_locked): ?>
        <div class="alert alert-warning border-warning-subtle">
            <i class="fas fa-lock me-2"></i>
            Stock create/edit actions are locked for this warehouse because stock take
            <strong><?= escape_html($active_stock_take['stock_take_number']) ?></strong> is in progress.
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>
            <i class="fas fa-boxes me-2"></i>
            Stock in <?php echo htmlspecialchars($warehouse['name']); ?>
        </h2>
        <div>
            <a href="<?= $is_stock_locked ? 'javascript:void(0)' : 'sync_inventory.php?warehouse_id=' . $id; ?>"
               class="btn btn-warning me-2<?= $is_stock_locked ? ' disabled' : '' ?>"
               <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1" title="' . escape_html($lock_reason) . '"' : '' ?>>
                <i class="fas fa-sync-alt me-2"></i>Sync All Stock
            </a>
            <a href="<?= $is_stock_locked ? 'javascript:void(0)' : '../../../../public/inventory/add_transaction.php?location_id=' . (int)($warehouse['location_id'] ?? 0) . '&warehouse_id=' . $id; ?>"
               class="btn btn-success me-2<?= $is_stock_locked ? ' disabled' : '' ?>"
               <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1" title="' . escape_html($lock_reason) . '"' : '' ?>>
                <i class="fas fa-exchange-alt me-2"></i>Add Transaction
            </a>
            <a href="index.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to Warehouses
            </a>
        </div>
    </div>

    <!-- Warehouse Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-warehouse fa-2x text-primary mb-2"></i>
                    <h6 class="text-muted mb-1">Warehouse</h6>
                    <h5 class="mb-0"><?php echo htmlspecialchars($warehouse['name']); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-map-marker-alt fa-2x text-info mb-2"></i>
                    <h6 class="text-muted mb-1">Location</h6>
                    <h5 class="mb-0"><?php echo htmlspecialchars($warehouse['location'] ?? 'N/A'); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-cubes fa-2x text-success mb-2"></i>
                    <h6 class="text-muted mb-1">Unique Products</h6>
                    <h5 class="mb-0"><?php echo count($inventory); ?></h5>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-layer-group fa-2x text-warning mb-2"></i>
                    <h6 class="text-muted mb-1">Total Units</h6>
                    <h5 class="mb-0"><?php echo number_format($total_stock); ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- All Bin Locations -->
    <div class="card shadow-sm border-0 mb-4 warehouse-dark-card">
        <div class="card-header warehouse-dark-head">
            <h5 class="mb-0">
                <i class="fas fa-map-pin me-2"></i>All Bin Locations In This Warehouse
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($all_bins)): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($all_bins as $bin): ?>
                        <span class="badge bg-info-subtle text-info border border-info px-3 py-2">
                            <?= escape_html($bin['code']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No bin locations configured for this warehouse.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card shadow-sm border-0 warehouse-dark-card">
        <div class="card-header warehouse-dark-head">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Inventory Details
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 warehouse-dark-table">
                    <thead>
                        <tr>
                            <th class="ps-3">SKU Code</th>
                            <th>Product Name</th>
                             <th>Bin Location</th>
                             <th class="text-center">Actual Qty</th>
                             <th class="text-center">Legacy Qty</th>
                             <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventory)): ?>
                            <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td class="ps-3">
                                    <code class="bg-light px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($item['sku']); ?>
                                    </code>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['prod_name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($item['bin_location'] === 'General'): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-inbox me-1"></i>General Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">
                                            <i class="fas fa-map-pin me-1"></i>
                                            <?php echo htmlspecialchars($item['bin_location']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                 <td class="text-center">
                                    <?php if ($item['quantity'] < 0): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger fs-6">
                                        <?php echo number_format($item['quantity']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-success-subtle text-success border border-success fs-6">
                                        <?php echo number_format($item['quantity']); ?>
                                    </span>
                                    <?php endif; ?>
                                 </td>
                                 <td class="text-center">
                                    <?php if ($item['discrepancy'] != 0): ?>
                                        <span class="badge bg-danger pe-1" title="Sync required! Tables mismatch by <?= $item['discrepancy'] ?>">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <?php echo number_format($item['legacy_quantity']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">
                                            <?php echo number_format($item['legacy_quantity']); ?>
                                        </span>
                                    <?php endif; ?>
                                 </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                         <a href="<?= $is_stock_locked ? 'javascript:void(0)' : 'cycle_count.php?warehouse_id=' . $id . '&product_id=' . $item['product_id']; ?>"
                                            class="btn btn-outline-warning<?= $is_stock_locked ? ' disabled' : '' ?>"
                                            title="<?= $is_stock_locked ? escape_html($lock_reason) : 'Cycle Count' ?>"
                                            <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                             <i class="fas fa-clipboard-check"></i>
                                         </a>
                                         <?php if ($item['discrepancy'] != 0): ?>
                                             <a href="<?= $is_stock_locked ? 'javascript:void(0)' : 'sync_inventory.php?warehouse_id=' . $id . '&product_id=' . $item['product_id']; ?>"
                                                class="btn btn-warning<?= $is_stock_locked ? ' disabled' : '' ?>"
                                                title="<?= $is_stock_locked ? escape_html($lock_reason) : 'Sync legacy qty to actual stock' ?>"
                                                <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                                 <i class="fas fa-sync"></i>
                                             </a>
                                         <?php endif; ?>
                                        <?php if ((int)$item['id'] > 0): ?>
                                            <button class="btn btn-outline-primary"
                                                    <?= $is_stock_locked ? 'disabled title="' . escape_html($lock_reason) . '"' : '' ?>
                                                    onclick="adjustStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['prod_name']); ?>', <?php echo $item['quantity']; ?>)"
                                                    title="Adjust Quantity">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger"
                                                    <?= $is_stock_locked ? 'disabled title="' . escape_html($lock_reason) . '"' : '' ?>
                                                    onclick="removeStock(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['prod_name']); ?>')"
                                                    title="Remove Stock">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary" disabled title="Sync stock row first">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" disabled title="Sync stock row first">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
                                    <h5 class="text-muted">No Stock Available</h5>
                                    <p class="text-muted mb-3">This warehouse is currently empty.</p>
                                    <a href="<?= $is_stock_locked ? 'javascript:void(0)' : '../../../../public/inventory/add_transaction.php?location_id=' . (int)($warehouse['location_id'] ?? 0) . '&warehouse_id=' . $id; ?>"
                                       class="btn btn-success<?= $is_stock_locked ? ' disabled' : '' ?>"
                                       <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1" title="' . escape_html($lock_reason) . '"' : '' ?>>
                                        <i class="fas fa-exchange-alt me-2"></i>Add Your First Transaction
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="card mt-4 border-info">
        <div class="card-body">
            <h6 class="text-info mb-3">
                <i class="fas fa-bolt me-2"></i>Quick Actions
            </h6>
            <div class="row">
                <div class="col-md-3">
                    <a href="<?= $is_stock_locked ? 'javascript:void(0)' : '../../../../public/inventory/add_transaction.php?location_id=' . (int)($warehouse['location_id'] ?? 0) . '&warehouse_id=' . $id; ?>"
                       class="btn btn-outline-success w-100 mb-2<?= $is_stock_locked ? ' disabled' : '' ?>"
                       <?= $is_stock_locked ? 'aria-disabled="true" tabindex="-1" title="' . escape_html($lock_reason) . '"' : '' ?>>
                        <i class="fas fa-exchange-alt me-2"></i>Add Transaction
                    </a>
                </div>
                <!-- <div class="col-md-3">
                    <a href="picking.php?id=<?php echo $id; ?>" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fas fa-shipping-fast me-2"></i>Pick Items
                    </a>
                </div>
                <div class="col-md-3">
                    <button onclick="cycle_count.php?warehouse_id=7&product_id=12" class="btn btn-outline-warning w-100 mb-2">
                        <i class="fas fa-clipboard-check me-2"></i>Bulk Cycle Count
                    </button>
                </div>
                <div class="col-md-3">
                    <a href="reports/inventory_report.php?warehouse_id=<?php echo $id; ?>" class="btn btn-outline-info w-100 mb-2">
                        <i class="fas fa-chart-bar me-2"></i>View Reports
                    </a>
                </div> -->
            </div>
        </div>
    </div>
</div>

<!-- Adjust Stock Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Adjust Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1 text-muted small">Product</p>
                <p class="fw-bold mb-3" id="adjustProductName"></p>
                <p class="mb-1 text-muted small">Current Quantity</p>
                <p class="mb-3"><span class="badge bg-secondary fs-6" id="adjustCurrentQty"></span></p>
                <div class="mb-2">
                    <label class="form-label fw-semibold">New Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control form-control-lg" id="adjustNewQty" min="0" placeholder="Enter new quantity">
                    <div class="invalid-feedback">Please enter a valid quantity (0 or greater).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAdjust()">
                    <i class="fas fa-check me-1"></i>Update Stock
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Move Stock Modal -->
<div class="modal fade" id="moveStockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-arrows-alt me-2 text-warning"></i>Move Stock</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1 text-muted small">Moving</p>
                <p class="fw-bold mb-3" id="moveProductName"></p>
                <div class="mb-2">
                    <label class="form-label fw-semibold">New Bin Location <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="moveNewBin" placeholder="e.g. A-02-03" maxlength="50">
                    <div class="invalid-feedback">Please enter a bin location.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="submitMove()">
                    <i class="fas fa-check me-1"></i>Move
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let _adjustId = null;
let _moveId   = null;

function adjustStock(inventoryId, productName, currentQty) {
    _adjustId = inventoryId;
    document.getElementById('adjustProductName').textContent = productName;
    document.getElementById('adjustCurrentQty').textContent  = currentQty;
    const input = document.getElementById('adjustNewQty');
    input.value = currentQty;
    input.classList.remove('is-invalid');
    const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
    document.getElementById('adjustStockModal').addEventListener('shown.bs.modal', () => input.select(), { once: true });
    modal.show();
}

function submitAdjust() {
    const input = document.getElementById('adjustNewQty');
    const qty   = parseInt(input.value);
    if (isNaN(qty) || qty < 0) {
        input.classList.add('is-invalid');
        return;
    }
    window.location.href = `adjust_stock.php?id=${_adjustId}&qty=${qty}`;
}

function moveStock(inventoryId, productName) {
    _moveId = inventoryId;
    document.getElementById('moveProductName').textContent = productName;
    const input = document.getElementById('moveNewBin');
    input.value = '';
    input.classList.remove('is-invalid');
    const modal = new bootstrap.Modal(document.getElementById('moveStockModal'));
    document.getElementById('moveStockModal').addEventListener('shown.bs.modal', () => input.focus(), { once: true });
    modal.show();
}

function submitMove() {
    const input = document.getElementById('moveNewBin');
    const bin   = input.value.trim();
    if (!bin) {
        input.classList.add('is-invalid');
        return;
    }
    window.location.href = `move_stock.php?id=${_moveId}&bin=${encodeURIComponent(bin)}`;
}

function removeStock(inventoryId, productName) {
    if (confirm(`Remove ALL stock for "${productName}"?\n\nThis cannot be undone.`)) {
        window.location.href = `remove_stock.php?id=${inventoryId}`;
    }
}

function bulkCycleCount() {
    if (confirm('Start bulk cycle count for all items in this warehouse?\n\nYou will be taken through each item one by one.')) {
        window.location.href = `bulk_cycle_count.php?warehouse_id=<?php echo $id; ?>`;
    }
}

// Enter key submits whichever modal is open
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    if (document.getElementById('adjustStockModal').classList.contains('show')) submitAdjust();
    if (document.getElementById('moveStockModal').classList.contains('show')) submitMove();
});
</script>

<style>
.warehouse-dark-card {
    background: #1f2937;
    color: #e5e7eb;
}
.warehouse-dark-head {
    background: #111827 !important;
    color: #f3f4f6;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.warehouse-dark-table thead th {
    background: #111827;
    color: #f3f4f6;
    border-color: rgba(255,255,255,0.08);
}
</style>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>
