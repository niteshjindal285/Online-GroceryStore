<?php
/**
 * Convert Requisition to Purchase Order
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_purchasing');

$company_id = active_company_id(1);
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    set_flash('Invalid Requisition ID.', 'error');
    redirect('requisitions.php');
}

$req = db_fetch("SELECT * FROM purchase_requisitions WHERE id = ?", [$id]);

if (!$req || $req['status'] !== 'approved') {
    set_flash('Only APPROVED requisitions can be converted.', 'error');
    redirect("view_requisition.php?id=$id");
}

$lines = db_fetch_all("
    SELECT l.*, i.code, i.name as item_name, i.purchase_unit_id, i.unit_of_measure_id, i.purchase_conversion_factor
    FROM purchase_requisition_lines l
    JOIN inventory_items i ON l.item_id = i.id
    WHERE l.requisition_id = ?
", [$id]);

$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = post('supplier_id');
    $delivery_location_id = post('delivery_location_id');
    $expected_delivery_date = post('expected_delivery_date');
    $notes = post('notes');
    
    // Check if lengths match
    $line_items = post('item_id') ?: [];
    $line_qtys = post('qty') ?: [];
    $line_prices = post('price') ?: [];
    
    if (empty($supplier_id) || empty($delivery_location_id)) {
        set_flash('Supplier and Delivery Location are required.', 'error');
    } else {
        try {
            db_begin_transaction();
            
            // Generate PO Number
            $po_num = 'PO-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $total_amount = 0;
            
            // Calculate Total
            foreach ($line_items as $idx => $itemId) {
                $q = floatval($line_qtys[$idx]);
                $p = floatval($line_prices[$idx]);
                $total_amount += ($q * $p);
            }
            
            // Initial insert
            $po_id = db_insert("
                INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_delivery_date, delivery_location_id, status, notes, total_amount, created_by, company_id, created_at)
                VALUES (?, ?, CURDATE(), ?, ?, 'draft', ?, ?, ?, ?, NOW())
            ", [$po_num, $supplier_id, $expected_delivery_date, $delivery_location_id, $notes . "\n(Converted from " . $req['requisition_number'] . ")", $total_amount, $_SESSION['user_id'], $company_id]);
            
            // PO Lines
            foreach ($line_items as $idx => $item_id) {
                $qty = floatval($line_qtys[$idx]);
                $price = floatval($line_prices[$idx]);
                $line_total = $qty * $price;
                
                if ($qty > 0) {
                    db_query("
                        INSERT INTO purchase_order_lines (po_id, item_id, quantity, unit_price, line_total)
                        VALUES (?, ?, ?, ?, ?)
                    ", [$po_id, $item_id, $qty, $price, $line_total]);
                }
            }
            
            // Update Requisition
            db_query("UPDATE purchase_requisitions SET status = 'converted', po_id = ? WHERE id = ?", [$po_id, $id]);
            
            db_commit();
            
            set_flash('Purchase Order Draft created from Requisition! Please review and send.', 'success');
            redirect("../inventory/purchase_order/view_purchase_order.php?id=$po_id");
            
        } catch (Exception $e) {
            db_rollback();
            set_flash("Database error: " . $e->getMessage(), 'error');
        }
    }
}

$page_title = 'Convert to PO - MJR Group ERP';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-exchange-alt me-2 text-warning"></i>Convert Requisition to PO</h2>
            <p class="text-muted">Turn internal request <?= escape_html($req['requisition_number']) ?> into a formal Purchase Order</p>
        </div>
        <a href="view_requisition.php?id=<?= $id ?>" class="btn btn-secondary">
            <i class="fas fa-times me-1"></i> Cancel
        </a>
    </div>

    <form method="POST">
        <div class="row">
            <!-- Left Info Pane -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-4">
                        <h5 class="text-primary mb-0">PO Parameters</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Select Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['id'] ?>"><?= escape_html($sup['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Delivery Location <span class="text-danger">*</span></label>
                            <select name="delivery_location_id" class="form-select" required>
                                <option value="">-- Choose Delivery Location --</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"><?= escape_html($loc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Expected Delivery Date <span class="text-danger">*</span></label>
                            <input type="date" name="expected_delivery_date" class="form-control" value="<?= $req['required_date'] ?>" required>
                            <div class="form-text">Defaults to requisition's required date.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Buyer Notes</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional conditions for the supplier..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Line Items Pane -->
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white pt-4">
                        <h5 class="text-primary mb-0">Line Items & Pricing Review</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th width="35%">Item</th>
                                        <th width="20%">Order Qty</th>
                                        <th width="20%">Unit Price ($)</th>
                                        <th width="25%">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lines as $idx => $line): ?>
                                        <?php 
                                            // Handle purchase unit conversion
                                            $purch_conversion = $line['purchase_conversion_factor'] > 0 ? $line['purchase_conversion_factor'] : 1;
                                            $purch_qty = $line['quantity'] / $purch_conversion; 
                                            // E.g. Request 1000 KG. Conversion is 1000 (1 Ton = 1000 KG). Order Qty = 1 Ton.
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="item_id[]" value="<?= $line['item_id'] ?>">
                                                <strong><?= escape_html($line['code']) ?></strong><br>
                                                <small class="text-muted"><?= escape_html($line['item_name']) ?></small>
                                            </td>
                                            <td>
                                                <input type="number" step="0.0001" name="qty[]" class="form-control item-qty" value="<?= $purch_qty ?>" required>
                                                <?php if ($purch_conversion != 1): ?>
                                                    <small class="text-warning"><i class="fas fa-exclamation-circle"></i> Converted from Base Req: <?= floatval($line['quantity']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <!-- If ordered in bulk, price needs to reflect bulk. We estimate by multiplying by conversion rate -->
                                                <input type="number" step="0.01" name="price[]" class="form-control item-price" value="<?= $line['estimated_unit_price'] * $purch_conversion ?>" required>
                                            </td>
                                            <td>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light">$</span>
                                                    <input type="text" class="form-control line-total bg-white" readonly value="<?= number_format($purch_qty * ($line['estimated_unit_price'] * $purch_conversion), 2) ?>">
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end border-0 pt-3">Final PO Estimate:</th>
                                        <th class="border-0 pt-3"><span id="grand-total" class="fs-4 text-success">$<?= number_format($req['total_estimated_amount'], 2) ?></span></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 py-3 text-end">
                        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-file-invoice me-2"></i>Generate PO Draft</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    function calcTotals() {
        let gt = 0;
        document.querySelectorAll('tbody tr').forEach(row => {
            const p = parseFloat(row.querySelector('.item-price').value) || 0;
            const q = parseFloat(row.querySelector('.item-qty').value) || 0;
            const t = p * q;
            row.querySelector('.line-total').value = t.toFixed(2);
            gt += t;
        });
        document.getElementById('grand-total').innerText = '$' + gt.toFixed(2);
    }

    document.querySelectorAll('.item-price, .item-qty').forEach(input => {
        input.addEventListener('input', calcTotals);
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

