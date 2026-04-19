<?php
/**
 * Planned Orders
 * Manage and convert planned orders to purchase orders or production orders
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Planned Orders - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// ── Handle CONVERT ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } else {
        $order_id  = (int)($_POST['order_id']  ?? 0);
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);   // user-selected
        $location_id = (int)($_POST['location_id'] ?? 0);   // user-selected

        db_begin_transaction();
        try {
            // Get planned order details
            $planned_order = db_fetch("
                SELECT po.*, i.code, i.name, i.cost_price
                FROM planned_orders po
                JOIN inventory_items i ON po.item_id = i.id
                WHERE po.id = ? AND po.status = 'planned' AND i.company_id = ?
            ", [$order_id, $company_id]);

            if (!$planned_order) {
                throw new Exception('Planned order not found or already converted.');
            }

            if ($planned_order['order_type'] === 'purchase') {
                // ── Convert to Purchase Order ──────────────────────────────────
                if (!$supplier_id) {
                    throw new Exception('Please select a supplier before converting.');
                }
                $supplier = db_fetch("SELECT id FROM suppliers WHERE id = ? AND is_active = 1", [$supplier_id]);
                if (!$supplier) {
                    throw new Exception('Selected supplier is inactive or not found.');
                }

                // Generate unique PO number (safe against concurrent inserts)
                $attempts = 0;
                $po_number = null;
                while ($attempts < 5) {
                    $last_po = db_fetch("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
                    if ($last_po && preg_match('/PO-(\d+)/', $last_po['po_number'], $m)) {
                        $next = intval($m[1]) + 1 + $attempts;
                    } else {
                        $next = 1 + $attempts;
                    }
                    $candidate = 'PO-' . str_pad($next, 6, '0', STR_PAD_LEFT);
                    $exists = db_fetch("SELECT id FROM purchase_orders WHERE po_number = ?", [$candidate]);
                    if (!$exists) { $po_number = $candidate; break; }
                    $attempts++;
                }
                if (!$po_number) throw new Exception('Could not generate unique PO number.');

                $line_total = $planned_order['quantity'] * ($planned_order['cost_price'] ?? 0);

                $new_po_id = db_insert("
                    INSERT INTO purchase_orders
                        (po_number, supplier_id, order_date, expected_delivery_date, status, subtotal, total_amount, notes, created_by, company_id, created_at)
                    VALUES (?, ?, CURDATE(), ?, 'draft', ?, ?, ?, ?, ?, NOW())
                ", [
                    $po_number,
                    $supplier_id,
                    $planned_order['planned_date'],
                    $line_total, $line_total,
                    'Generated from MRP planned order',
                    $_SESSION['user_id'],
                    $company_id
                ]);

                db_insert("
                    INSERT INTO purchase_order_lines (po_id, item_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?)
                ", [$new_po_id, $planned_order['item_id'], $planned_order['quantity'], $planned_order['cost_price'] ?? 0, $line_total]);

                db_query("UPDATE planned_orders SET status='converted', converted_to_po_id=? WHERE id=?", [$new_po_id, $order_id]);

                db_commit();
                set_flash("Converted to Purchase Order: <strong>$po_number</strong> — go verify supplier details before sending.", 'success');

            } elseif ($planned_order['order_type'] === 'production') {
                // ── Convert to Production Order ──────────────────────────────────────
                if (!$location_id) {
                    throw new Exception('Please select a production location before converting.');
                }
                $loc = db_fetch("SELECT id FROM locations WHERE id = ? AND is_active = 1 AND company_id = ?", [$location_id, $company_id]);
                if (!$loc) {
                    throw new Exception('Selected location is inactive or not found.');
                }

                // Generate WO number
                $last_wo = db_fetch("SELECT wo_number FROM work_orders ORDER BY id DESC LIMIT 1");
                if ($last_wo && preg_match('/WO-(\d+)/', $last_wo['wo_number'], $m)) {
                    $next_num = intval($m[1]) + 1;
                } else {
                    $next_num = 1;
                }
                $wo_number = 'WO-' . str_pad($next_num, 6, '0', STR_PAD_LEFT);

                $new_wo_id = db_insert("
                    INSERT INTO work_orders
                        (wo_number, product_id, quantity, start_date, due_date, location_id, status, notes, created_by, created_at)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, 'planned', ?, ?, NOW())
                ", [
                    $wo_number,
                    $planned_order['item_id'],
                    $planned_order['quantity'],
                    $planned_order['planned_date'],
                    $location_id,
                    'Generated from MRP planned order',
                    $_SESSION['user_id']
                ]);

                // BOM → production order materials
                $bom_items = db_fetch_all("
                    SELECT bi.component_id, bi.quantity_required
                    FROM bom_items bi
                    JOIN bom b ON bi.bom_id = b.id
                    WHERE b.product_id = ? AND b.is_active = 1
                      AND b.version = (SELECT MAX(version) FROM bom WHERE product_id = ? AND is_active = 1)
                ", [$planned_order['item_id'], $planned_order['item_id']]);

                foreach ($bom_items as $bom) {
                    $req_qty = $bom['quantity_required'] * $planned_order['quantity'];
                    db_insert("
                        INSERT INTO work_order_materials (wo_id, item_id, quantity_required, quantity_allocated, quantity_consumed)
                        VALUES (?, ?, ?, 0, 0)
                    ", [$new_wo_id, $bom['component_id'], $req_qty]);
                }

                db_query("UPDATE planned_orders SET status='converted', converted_to_wo_id=? WHERE id=?", [$new_wo_id, $order_id]);

                db_commit();
                $mat_msg = $bom_items ? ' with ' . count($bom_items) . ' material(s)' : ' (no BOM found)';
                set_flash("Converted to Production Order: <strong>$wo_number</strong>$mat_msg", 'success');
            }

        } catch (Exception $e) {
            db_rollback();
            set_flash($e->getMessage(), 'error');
        }
    }
    header('Location: planned_orders.php');
    exit;
}

// ── Handle CANCEL ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } else {
        $order_id = (int)($_POST['order_id'] ?? 0);
        db_query("UPDATE planned_orders SET status='cancelled' WHERE id=? AND status='planned' AND item_id IN (SELECT id FROM inventory_items WHERE company_id = ?)", [$order_id, $company_id]);
        set_flash('Planned order cancelled.', 'success');
    }
    header('Location: planned_orders.php');
    exit;
}

// ── Filters ────────────────────────────────────────────────────────────────────
$status     = $_GET['status']     ?? 'planned';
$order_type = $_GET['order_type'] ?? '';

$where  = ['i.company_id = ?'];
$params = [$company_id];

if ($status !== '') {
    $where[] = 'po.status = ?';
    $params[] = $status;
}
if ($order_type !== '') {
    $where[] = 'po.order_type = ?';
    $params[] = $order_type;
}

$where_sql = implode(' AND ', $where);

// Get planned orders
$planned_orders = db_fetch_all("
    SELECT
        po.*,
        i.code as item_code, i.name as item_name, i.unit_of_measure,
        mr.run_number, mr.run_date,
        CASE
            WHEN po.order_type = 'purchase'   THEN pur.po_number
            WHEN po.order_type = 'production' THEN wo.wo_number
        END as converted_order_number
    FROM planned_orders po
    JOIN inventory_items i ON po.item_id = i.id
    JOIN mrp_runs mr ON po.mrp_run_id = mr.id
    LEFT JOIN purchase_orders pur ON po.converted_to_po_id = pur.id
    LEFT JOIN work_orders wo     ON po.converted_to_wo_id  = wo.id
    WHERE $where_sql
    ORDER BY po.planned_date ASC, po.id DESC
", $params);

// Summary counts for the selected company
$summary = db_fetch("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN po.status='planned'   THEN 1 ELSE 0 END) as planned,
        SUM(CASE WHEN po.status='converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN po.status='cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM planned_orders po
    JOIN inventory_items i ON po.item_id = i.id
    WHERE i.company_id = ?
", [$company_id]);

// Load suppliers and locations for modals
$suppliers = db_fetch_all("SELECT id, supplier_code, name FROM suppliers WHERE is_active=1 ORDER BY name");
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active=1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2><i class="fas fa-list-ul me-2"></i>Planned Orders</h2>
            <p class="lead mb-0">Convert planned orders for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <a href="master_schedule.php" class="btn btn-primary">
                <i class="fas fa-calendar me-2"></i>Master Schedule
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-2">
            <div class="card bg-info text-center py-2">
                <div class="small text-uppercase opacity-75">Total</div>
                <div class="fs-4 fw-bold"><?= $summary['total'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <a href="?status=planned<?= $order_type ? '&order_type=' . urlencode($order_type) : '' ?>" class="text-decoration-none">
                <div class="card bg-warning text-center py-2">
                    <div class="small text-uppercase opacity-75">Pending</div>
                    <div class="fs-4 fw-bold"><?= $summary['planned'] ?? 0 ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <a href="?status=converted<?= $order_type ? '&order_type=' . urlencode($order_type) : '' ?>" class="text-decoration-none">
                <div class="card bg-success text-center py-2">
                    <div class="small text-uppercase opacity-75">Converted</div>
                    <div class="fs-4 fw-bold"><?= $summary['converted'] ?? 0 ?></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card bg-danger text-center py-2">
                <div class="small text-uppercase opacity-75">Cancelled</div>
                <div class="fs-4 fw-bold"><?= $summary['cancelled'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="planned"   <?= $status==='planned'   ?'selected':''?>>Planned</option>
                        <option value="converted" <?= $status==='converted' ?'selected':''?>>Converted</option>
                        <option value="cancelled" <?= $status==='cancelled' ?'selected':''?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Order Type</label>
                    <select name="order_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="purchase"   <?= $order_type==='purchase'   ?'selected':''?>>Purchase</option>
                        <option value="production" <?= $order_type==='production' ?'selected':''?>>Production</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
                    <a href="planned_orders.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Planned Orders Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Orders
                <span class="badge bg-secondary ms-1"><?= count($planned_orders) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($planned_orders)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover" id="ordersTable">
                    <thead>
                        <tr>
                            <th>MRP Run</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th class="text-end">Quantity</th>
                            <th>Planned Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($planned_orders as $order):
                            $type_bg     = $order['order_type'] === 'purchase' ? 'info' : 'primary';
                            $status_bg   = match($order['status']) { 'planned'=>'warning','converted'=>'success','cancelled'=>'danger',default=>'secondary' };
                            $is_overdue  = $order['status'] === 'planned' && !empty($order['planned_date']) && strtotime($order['planned_date']) < time();
                        ?>
                        <tr <?= $is_overdue ? 'class="table-danger"' : '' ?>>
                            <td>
                                <small><?= escape_html($order['run_number']) ?></small><br>
                                <small class="text-muted"><?= format_date($order['run_date']) ?></small>
                            </td>
                            <td>
                                <strong><?= escape_html($order['item_code']) ?></strong><br>
                                <small><?= escape_html($order['item_name']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $type_bg ?>">
                                    <i class="fas fa-<?= $order['order_type']==='purchase'?'shopping-cart':'cogs' ?> me-1"></i>
                                    <?= ucfirst($order['order_type']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <strong><?= format_number($order['quantity']) ?></strong>
                                <?= escape_html($order['unit_of_measure']) ?>
                            </td>
                            <td>
                                <?= format_date($order['planned_date']) ?>
                                <?php if ($is_overdue): ?><span class="badge bg-danger ms-1">Overdue</span><?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $status_bg ?>"><?= ucfirst($order['status']) ?></span>
                            </td>
                            <td>
                                <?php if ($order['status'] === 'planned'): ?>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($order['order_type'] === 'purchase'): ?>
                                    <button type="button" class="btn btn-outline-success"
                                            onclick="openConvertPO(<?= $order['id'] ?>, '<?= escape_html(addslashes($order['item_code'])) ?> – <?= escape_html(addslashes($order['item_name'])) ?>', <?= floatval($order['quantity']) ?>)"
                                            title="Convert to Purchase Order">
                                        <i class="fas fa-shopping-cart me-1"></i>→ PO
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-outline-primary"
                                            onclick="openConvertWO(<?= $order['id'] ?>, '<?= escape_html(addslashes($order['item_code'])) ?> – <?= escape_html(addslashes($order['item_name'])) ?>', <?= floatval($order['quantity']) ?>)"
                                            title="Convert to Production Order">
                                        <i class="fas fa-cogs me-1"></i>→ WO
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-outline-danger"
                                            onclick="cancelOrder(<?= $order['id'] ?>, '<?= escape_html(addslashes($order['item_code'])) ?>')"
                                            title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <?php elseif ($order['status'] === 'converted'): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle me-1"></i>
                                        <?= escape_html($order['converted_order_number'] ?? 'N/A') ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-list-ul fa-3x text-muted mb-3"></i>
                <p class="text-muted">No planned orders found</p>
                <a href="master_schedule.php" class="btn btn-primary">
                    <i class="fas fa-calendar me-2"></i>Go to Master Schedule
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Modal: Convert to Purchase Order ────────────────────────────────────── -->
<div class="modal fade" id="convertPOModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shopping-cart me-2"></i>Convert to Purchase Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="convertPOForm">
                <div class="modal-body">
                    <input type="hidden" name="action"     value="convert">
                    <input type="hidden" name="order_id"   id="po_order_id">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="alert alert-info" id="poItemInfo"></div>

                    <div class="mb-3">
                        <label class="form-label">Supplier <span class="text-danger">*</span></label>
                        <select class="form-select" name="supplier_id" required>
                            <option value="">— Select Supplier —</option>
                            <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= escape_html($s['supplier_code']) ?> – <?= escape_html($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choose which supplier to order from</div>
                    </div>

                    <?php if (empty($suppliers)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No active suppliers found. <a href="../inventory/supplier/add_supplier.php">Add a supplier first</a>.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" <?= empty($suppliers) ? 'disabled' : '' ?>>
                        <i class="fas fa-check me-1"></i>Create Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: Convert to Production Order ───────────────────────────────────────── -->
<div class="modal fade" id="convertWOModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Convert to Production Order</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="convertWOForm">
                <div class="modal-body">
                    <input type="hidden" name="action"     value="convert">
                    <input type="hidden" name="order_id"   id="wo_order_id">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <div class="alert alert-info" id="woItemInfo"></div>

                    <div class="mb-3">
                        <label class="form-label">Production Location <span class="text-danger">*</span></label>
                        <select class="form-select" name="location_id" required>
                            <option value="">— Select Location —</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>"><?= escape_html($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Choose where production will happen (BOM materials will be loaded automatically)</div>
                    </div>

                    <?php if (empty($locations)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No active locations found. Please create a location first.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" <?= empty($locations) ? 'disabled' : '' ?>>
                        <i class="fas fa-check me-1"></i>Create Production Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel form (hidden) -->
<form id="cancelForm" method="POST" style="display:none;">
    <input type="hidden" name="action"     value="cancel">
    <input type="hidden" name="order_id"   id="cancelOrderId">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<script>
$(document).ready(function () {
    <?php if (!empty($planned_orders)): ?>
    $('#ordersTable').DataTable({
        order: [[4, 'asc']],
        pageLength: 25,
        language: { search: "Search orders:" }
    });
    <?php endif; ?>
});

function openConvertPO(id, itemLabel, qty) {
    document.getElementById('po_order_id').value = id;
    document.getElementById('poItemInfo').textContent = 'Creating PO for: ' + itemLabel + ' — Qty: ' + qty;
    document.getElementById('convertPOForm').querySelector('select[name="supplier_id"]').value = '';
    new bootstrap.Modal(document.getElementById('convertPOModal')).show();
}

function openConvertWO(id, itemLabel, qty) {
    document.getElementById('wo_order_id').value = id;
    document.getElementById('woItemInfo').textContent = 'Creating WO for: ' + itemLabel + ' — Qty: ' + qty;
    document.getElementById('convertWOForm').querySelector('select[name="location_id"]').value = '';
    new bootstrap.Modal(document.getElementById('convertWOModal')).show();
}

function cancelOrder(id, itemCode) {
    if (confirm('Cancel planned order for "' + itemCode + '"?\n\nThis cannot be undone.')) {
        document.getElementById('cancelOrderId').value = id;
        document.getElementById('cancelForm').submit();
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

