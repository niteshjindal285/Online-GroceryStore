<?php
/**
 * BOM Quantity Manager
 * Edit and view required quantity (per 1 piece) used by production orders.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_production');

$page_title = 'BOM Quantity Manager - MJR Group ERP';

$products = db_fetch_all("
    SELECT id, code, name
    FROM inventory_items
    WHERE is_active = 1
    ORDER BY code, name
");

$selected_product_id = intval(get_param('product_id', post('product_id', 0)));

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid CSRF token.', 'error');
        redirect('bom_quantity_manager.php?product_id=' . $selected_product_id);
    }

    try {
        $product_id = intval(post('product_id', 0));
        $component_ids = post('component_id', []);
        $required_qtys = post('required_qty', []);

        if ($product_id <= 0) {
            throw new Exception('Please select a product.');
        }

        if (!is_array($component_ids) || !is_array($required_qtys) || count($component_ids) !== count($required_qtys)) {
            throw new Exception('Invalid BOM row data.');
        }

        db_begin_transaction();

        for ($i = 0; $i < count($component_ids); $i++) {
            $component_id = intval($component_ids[$i]);
            $required_qty = floatval($required_qtys[$i]);

            if ($component_id <= 0) {
                continue;
            }
            if ($required_qty < 0) {
                throw new Exception('Required quantity cannot be negative.');
            }

            db_query(
                "UPDATE bill_of_materials
                 SET quantity_required = ?
                 WHERE product_id = ? AND component_id = ?",
                [$required_qty, $product_id, $component_id]
            );
        }

        db_commit();
        set_flash('BOM required quantities updated successfully.', 'success');
    } catch (Exception $e) {
        db_rollback();
        set_flash('Failed to update BOM quantities: ' . $e->getMessage(), 'error');
    }

    redirect('bom_quantity_manager.php?product_id=' . intval(post('product_id', 0)));
}

$bom_rows = [];
if ($selected_product_id > 0) {
    $bom_rows = db_fetch_all("
        SELECT
            b.product_id,
            b.component_id,
            b.quantity_required,
            p.code AS component_code,
            p.name AS component_name,
            COALESCE(u.code, p.unit_of_measure, '-') AS uom_code,
            COALESCE(SUM(wi.quantity), 0) AS stock_available
        FROM bill_of_materials b
        JOIN inventory_items p ON p.id = b.component_id
        LEFT JOIN units_of_measure u ON u.id = p.unit_of_measure_id
        LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id
        WHERE b.product_id = ?
        GROUP BY b.id, p.id, u.code, p.unit_of_measure
        ORDER BY p.name
    ", [$selected_product_id]);
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-sliders-h me-2"></i>BOM Quantity Manager</h2>
            <p class="text-muted mb-0">Manage required quantity per 1 piece used by Production Orders.</p>
        </div>
        <div class="col-auto">
            <a href="production_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Production Orders
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="product_id" class="form-label">Finished Product</label>
                    <select class="form-select" id="product_id" name="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $selected_product_id === intval($p['id']) ? 'selected' : '' ?>>
                                <?= escape_html($p['code']) ?> - <?= escape_html($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Show BOM
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected_product_id > 0): ?>
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="product_id" value="<?= $selected_product_id ?>">

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Component Code</th>
                                    <th>Component Name</th>
                                    <th class="text-end">Required Qty (1 Piece)</th>
                                    <th class="text-end">Available Stock</th>
                                    <th>UOM</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bom_rows)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No BOM rows found for this product.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($bom_rows as $index => $row): ?>
                                        <tr>
                                            <td><?= escape_html($row['component_code']) ?></td>
                                            <td><?= escape_html($row['component_name']) ?></td>
                                            <td class="text-end">
                                                <input type="hidden" name="component_id[]" value="<?= intval($row['component_id']) ?>">
                                                <input
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    name="required_qty[]"
                                                    class="form-control text-end"
                                                    value="<?= number_format((float)$row['quantity_required'], 4, '.', '') ?>"
                                                    required
                                                >
                                            </td>
                                            <td class="text-end"><?= number_format((float)$row['stock_available'], 2) ?></td>
                                            <td><?= escape_html($row['uom_code']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($bom_rows)): ?>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-success px-4">
                                <i class="fas fa-save me-2"></i>Save Required Quantities
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

