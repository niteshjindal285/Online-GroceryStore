<?php
/**
 * Receive Purchase Order
 * Updates inventory when goods are received
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();
ensure_inventory_transaction_reporting_schema();

$page_title = 'Receive Purchase Order - MJR Group ERP';
$company_id = active_company_id(1);

$po_id = get_param('id');

if (!$po_id) {
    set_flash('Purchase order not found.', 'error');
    redirect('purchase_orders.php');
}

// Get PO details — allow both 'confirmed' and 'partially_received'
$po = db_fetch("
    SELECT po.*, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.id = ? AND po.company_id = ? AND po.status IN ('confirmed','partially_received')
", [$po_id, $company_id]);

if (!$po) {
    set_flash('Purchase order not found or not ready for receiving.', 'error');
    redirect('purchase_orders.php');
}

// Get PO line items
$po_lines = db_fetch_all("
    SELECT pol.*, i.code, i.name as item_name
    FROM purchase_order_lines pol
    JOIN inventory_items i ON pol.item_id = i.id
    WHERE pol.po_id = ?
", [$po_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $received_quantities = post('received_quantity') ?: [];
            $location_id = post('location_id');
            $received_date = post('received_date');
            $notes = post('notes');
            
            if (empty($location_id)) {
                throw new Exception('Please select a receiving location');
            }
            
            $current_user = current_user();
            
            // Validate all received quantities first
            foreach ($po_lines as $index => $line) {
                $received_qty = floatval($received_quantities[$index] ?? 0);
                
                // Validate: must be numeric and non-negative
                if ($received_qty < 0) {
                    throw new Exception("Received quantity for {$line['item_name']} cannot be negative");
                }

                if (floor($received_qty) != $received_qty) {
                    throw new Exception("Received quantity for {$line['item_name']} must be a whole number");
                }
                
                // Validate: cannot exceed ordered quantity
                if ($received_qty > $line['quantity']) {
                    throw new Exception("Received quantity ({$received_qty}) for {$line['item_name']} cannot exceed ordered quantity ({$line['quantity']})");
                }
            }
            
            // Begin transaction
            db_begin_transaction();
            
            // Process each line item
            foreach ($po_lines as $index => $line) {
                $received_qty = intval($received_quantities[$index] ?? 0);
                
                if ($received_qty > 0) {
                    // UNIT CONVERSION LOGIC
                    $itemData = db_fetch("SELECT purchase_unit_id, purchase_conversion_factor FROM inventory_items WHERE id = ?", [$line['item_id']]);
                    $conversion = ($itemData && $itemData['purchase_conversion_factor']) ? floatval($itemData['purchase_conversion_factor']) : 1.0;
                    if ($conversion <= 0) $conversion = 1.0;
                    
                    $actual_base_qty = $received_qty * $conversion;

                    inventory_apply_stock_movement($line['item_id'], intval($location_id), $actual_base_qty);

                    $itemSnapshot = inventory_item_snapshot($line['item_id']);
                    $lineCost = isset($line['unit_price']) ? floatval($line['unit_price']) : floatval($itemSnapshot['cost_price'] ?? 0);

                    inventory_record_transaction([
                        'item_id' => intval($line['item_id']),
                        'location_id' => intval($location_id),
                        'transaction_type' => 'purchase',
                        'movement_reason' => 'Purchase receipt',
                        'quantity_signed' => $actual_base_qty,
                        'unit_cost' => $lineCost / $conversion, // Cost per BASE unit
                        'selling_price' => floatval($itemSnapshot['selling_price'] ?? 0),
                        'unit_of_measure' => $itemSnapshot['unit_of_measure'] ?? 'PCS',
                        'supplier_id' => intval($po['supplier_id']),
                        'reference' => $po['po_number'],
                        'reference_type' => 'purchase_order',
                        'reference_id' => intval($po_id),
                        'notes' => 'PO receipt' . ($notes ? ' - ' . $notes : ''),
                        'created_by' => intval($current_user['id'])
                    ]);
                }
            }
            
            // Determine new PO status:
            // 'received' only if ALL line quantities were fully received, else 'partially_received'
            $fully_received = true;
            foreach ($po_lines as $index => $line) {
                $rqty = intval($received_quantities[$index] ?? 0);
                if ($rqty < intval($line['quantity'])) {
                    $fully_received = false;
                    break;
                }
            }
            $new_status = $fully_received ? 'received' : 'partially_received';

            db_query("
                UPDATE purchase_orders
                SET status = ?,
                    received_date = ?
                WHERE id = ?
            ", [$new_status, $received_date, $po_id]);

            log_po_history($po_id, $new_status, $msg);
            
            db_commit();

            $msg = $fully_received
                ? 'Purchase order fully received! Inventory updated.'
                : 'Partial receipt recorded. PO marked as Partially Received. Inventory updated.';
            set_flash($msg, 'success');
            redirect('purchase_orders.php');
            
        } catch (Exception $e) {
            db_rollback();
            log_error("Error receiving PO: " . $e->getMessage());
            set_flash($e->getMessage(), 'error');
        }
    }
}

// Get locations for dropdown
$locations = db_fetch_all("SELECT id, code AS location_code, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-check me-2"></i>Receive Purchase Order</h2>
        </div>
        <div class="col-auto">
            <a href="view_purchase_order.php?id=<?= $po['id'] ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to PO
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>PO Number:</strong> <?= escape_html($po['po_number']) ?> |
        <strong>Supplier:</strong> <?= escape_html($po['supplier_name']) ?> |
        <strong>Total Amount:</strong> $<?= number_format($po['total_amount'], 2) ?>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Receiving Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="location_id" class="form-label">Receiving Location <span class="text-danger">*</span></label>
                        <select class="form-select" id="location_id" name="location_id" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>">
                                <?= escape_html($location['location_code']) ?> - <?= escape_html($location['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Where the goods will be stored</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="received_date" class="form-label">Received Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="received_date" name="received_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="notes" class="form-label">Receiving Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                              placeholder="Any notes about the receipt (condition, damages, etc.)"></textarea>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5>Items to Receive</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Ordered Qty</th>
                                <th>Received Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($po_lines as $index => $line): ?>
                            <tr>
                                <td><?= escape_html($line['code']) ?></td>
                                <td><?= escape_html($line['item_name']) ?></td>
                                <td><?= $line['quantity'] ?></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" 
                                           name="received_quantity[<?= $index ?>]" 
                                           value="<?= $line['quantity'] ?>" 
                                           step="1" min="0" max="<?= $line['quantity'] ?>" required>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted"><small>Note: Enter the actual quantity received for each item. This will update the inventory levels.</small></p>
            </div>
        </div>

        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Warning:</strong> This action will update inventory levels and mark the purchase order as received. 
            Make sure all quantities are correct before proceeding.
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <a href="view_purchase_order.php?id=<?= $po['id'] ?>" class="btn btn-secondary me-md-2">Cancel</a>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check me-2"></i>Confirm Receipt
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

