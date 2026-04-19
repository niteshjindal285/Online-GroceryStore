<?php
/**
 * Manage Damaged and Write-off Goods
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();

// Get Damage Store ID
$damage_loc = db_fetch("SELECT id FROM locations WHERE code = 'DW-STORE'");
if (!$damage_loc) {
    set_flash('Damage Store location not found. Please contact admin.', 'error');
    redirect('index.php');
}
$damage_id = $damage_loc['id'];

// Handle Disposal / Write-off
if (is_post() && has_role('manager')) {
    $item_id = post('item_id');
    $qty_to_dispose = floatval(post('qty'));
    $notes = post('notes');
    $user_id = current_user_id();

    if ($item_id && $qty_to_dispose > 0) {
        try {
            db_begin_transaction();

            // 1. Check current stock in damage store
            $current = db_fetch("SELECT quantity_on_hand FROM inventory_stock_levels WHERE item_id = ? AND location_id = ?", [$item_id, $damage_id]);
            if (!$current || $current['quantity_on_hand'] < $qty_to_dispose) {
                throw new Exception("Insufficient stock in Damaged Store.");
            }

            // 2. Apply negative movement (Dispose)
            inventory_apply_stock_movement($item_id, $damage_id, -$qty_to_dispose);

            // 3. Record Transaction
            inventory_record_transaction([
                'item_id' => $item_id,
                'location_id' => $damage_id,
                'transaction_type' => 'write_off',
                'movement_reason' => 'Damaged Goods Disposal',
                'quantity_signed' => -$qty_to_dispose,
                'unit_cost' => db_fetch("SELECT cost_price FROM inventory_items WHERE id = ?", [$item_id])['cost_price'] ?? 0,
                'reference' => 'DISP-' . date('Ymd-His'),
                'reference_type' => 'disposal',
                'notes' => $notes ?: 'Standard damaged goods disposal/write-off',
                'created_by' => $user_id
            ]);

            db_commit();
            set_flash('Damaged items written off successfully.', 'success');
        } catch (Exception $e) {
            db_rollback();
            set_flash('Error: ' . $e->getMessage(), 'error');
        }
    }
}

// Fetch Items in Damage Store
$damaged_items = db_fetch_all("
    SELECT isl.*, ii.code, ii.name, ii.cost_price
    FROM inventory_stock_levels isl
    JOIN inventory_items ii ON isl.item_id = ii.id
    WHERE isl.location_id = ? AND isl.quantity_on_hand > 0
", [$damage_id]);

$page_title = 'Damaged & Write-off Bin - MJR Group ERP';

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-trash-alt me-2 text-danger"></i>Damaged & Write-off Bin
            </h2>
            <p class="text-muted mb-0">Review and clear stock identified as damaged during audits</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Stock Takes
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card premium-card">
                <div class="card-header py-3 bg-dark-light">
                    <h5 class="mb-0 fw-bold">Current Damaged Stock</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Location/Bin</th>
                                    <th class="text-end">Qty in Bin</th>
                                    <th class="text-end">Est. Value</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($damaged_items)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">No damaged stock found.</td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($damaged_items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?= escape_html($item['name']) ?></div>
                                            <code><?= escape_html($item['code']) ?></code>
                                        </td>
                                        <td><span
                                                class="badge bg-secondary"><?= escape_html($item['bin_location'] ?: 'DW-STORE') ?></span>
                                        </td>
                                        <td class="text-end fw-bold text-danger">
                                            <?= number_format($item['quantity_on_hand'], 2) ?></td>
                                        <td class="text-end text-muted small">
                                            <?= format_currency($item['quantity_on_hand'] * $item['cost_price']) ?></td>
                                        <td class="text-end">
                                            <?php if (has_role('manager')): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="openDisposeModal(<?= $item['item_id'] ?>, '<?= escape_html($item['name']) ?>', <?= $item['quantity_on_hand'] ?>)">
                                                    <i class="fas fa-eraser me-1"></i> Clear / Dispose
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">Manager Only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card premium-card border-danger border-opacity-25 shadow-lg mb-4">
                <div class="card-body text-center py-4">
                    <div class="display-4 text-danger mb-2"><i class="fas fa-dumpster"></i></div>
                    <h5 class="text-white fw-bold">Disposal Information</h5>
                    <p class="text-muted small">Clearing items from this bin will permanently deduct them from inventory
                        and log them as a loss (Write-off).</p>
                    <hr class="border-secondary opacity-25">
                    <div class="text-start">
                        <label class="text-muted small uppercase">Total Bin Value</label>
                        <?php
                        $total_val = array_sum(array_map(fn($i) => $i['quantity_on_hand'] * $i['cost_price'], $damaged_items));
                        ?>
                        <h3 class="text-danger fw-bold mb-0"><?= format_currency($total_val) ?></h3>
                    </div>
                </div>
            </div>

            <div class="alert alert-warning border-warning small">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Items are moved here automatically during <strong>Stock Take Approval</strong> if shortages are found.
            </div>
        </div>
    </div>
</div>

<!-- Disposal Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-danger shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-danger"><i class="fas fa-eraser me-2"></i>Final Disposal / Write-off</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="modalItemId">
                    <h6 class="text-white" id="modalItemName">Item Name</h6>
                    <p class="text-muted small mb-4">This action will remove the item from the Damaged Store and
                        finalize the write-off entry.</p>

                    <div class="mb-3">
                        <label class="form-label text-muted">Quantity to Clear</label>
                        <div class="input-group">
                            <input type="number" name="qty" id="modalQty"
                                class="form-control bg-dark text-white border-secondary" step="0.01" required>
                            <span class="input-group-text bg-secondary border-secondary text-white" id="modalMaxQty">/
                                0.00</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">Reason / Disposal Note</label>
                        <textarea name="notes" class="form-control bg-dark text-white border-secondary" rows="2"
                            placeholder="e.g. Physical destruction, expired, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">DISPOSE ITEMS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDisposeModal(id, name, max) {
        document.getElementById('modalItemId').value = id;
        document.getElementById('modalItemName').textContent = name;
        document.getElementById('modalQty').value = max;
        document.getElementById('modalQty').max = max;
        document.getElementById('modalMaxQty').textContent = '/ ' + max.toFixed(2);

        const modal = new bootstrap.Modal(document.getElementById('disposeModal'));
        modal.show();
    }
</script>

<style>
    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    }

    .bg-dark-light {
        background: rgba(255, 255, 255, 0.02);
    }

    .table-dark thead th {
        background: rgba(255, 255, 255, 0.03);
        color: #0dcaf0;
        padding: 15px 20px;
    }

    .table-dark td {
        padding: 12px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>