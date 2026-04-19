<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Production Order Details - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view production orders.', 'warning');
    redirect(url('index.php'));
}
$wo_id = intval(get_param('id', 0));
if ($wo_id <= 0) {
    set_flash('Production order not found.', 'error');
    redirect('production_orders.php');
}

$work_order = db_fetch("
    SELECT wo.*, i.code AS product_code, i.name AS product_name, i.unit_of_measure, l.name AS location_name, u.username AS created_by_name
    FROM work_orders wo
    LEFT JOIN inventory_items i ON i.id = wo.product_id
    LEFT JOIN locations l ON l.id = wo.location_id
    LEFT JOIN users u ON u.id = wo.created_by
    WHERE wo.id = ? AND i.company_id = ?
", [$wo_id, $company_id]);
if (!$work_order) {
    set_flash('Production order not found.', 'error');
    redirect('production_orders.php');
}

$bom_items = db_fetch_all("
    SELECT bom.*, comp.code AS component_code, comp.name AS component_name, cat.name AS category_name, comp.unit_of_measure, COALESCE(comp.cost_price,0) AS unit_cost, COALESCE(SUM(wi.quantity),0) AS stock_available
    FROM bill_of_materials bom
    JOIN inventory_items comp ON comp.id = bom.component_id
    LEFT JOIN categories cat ON cat.id = comp.category_id
    LEFT JOIN warehouse_inventory wi ON wi.product_id = comp.id
    WHERE bom.product_id = ? AND bom.is_active = 1 AND comp.company_id = ?
    GROUP BY bom.id, comp.id
", [$work_order['product_id'], $company_id]);

$requested_qty = (float)($work_order['quantity'] ?? 0);
$max_producible_qty = $requested_qty;
if (empty($bom_items)) {
    $max_producible_qty = 0;
} else {
    foreach ($bom_items as $item) {
        $per_piece = (float)($item['quantity_required'] ?? 0);
        $available = (float)($item['stock_available'] ?? 0);
        if ($per_piece <= 0) {
            continue;
        }
        $possible = floor($available / $per_piece);
        $max_producible_qty = min($max_producible_qty, $possible);
    }
}
$max_producible_qty = max(0, $max_producible_qty);
$unfinished_qty = max(0, $requested_qty - $max_producible_qty);

$production_history = db_fetch_all("
    SELECT wo.id, wo.wo_number, wo.quantity, wo.status, wo.created_at, ii.code AS product_code, ii.name AS product_name
    FROM work_orders wo
    JOIN inventory_items ii ON ii.id = wo.product_id
    WHERE ii.company_id = ?
    ORDER BY wo.id DESC
    LIMIT 10
", [$company_id]);

$parsed_notes = (string)($work_order['notes'] ?? '');
$production_type = $work_order['production_type'] ?: 'Stock';
$fg_bin = $work_order['fg_bin_id'] ?: '-';
if ($fg_bin !== '-') {
    $bin_search = db_fetch("SELECT code FROM bins WHERE id = ?", [$fg_bin]);
    $fg_bin = $bin_search['code'] ?? '-';
}

include __DIR__ . '/../../templates/header.php';
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-tasks me-2"></i>Production Order Details</h2>
            <p class="text-muted mb-0"><?= escape_html($work_order['wo_number']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="edit_production_order.php?id=<?= $wo_id ?>" class="btn btn-success"><i class="fas fa-edit me-2"></i>Edit</a>
            <button class="btn btn-info" onclick="window.open('print_production_order.php?id=<?= $wo_id ?>', '_blank')"><i class="fas fa-print me-2"></i>Print</button>
            <a href="production_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
        </div>
    </div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Production Order Header</h5>
        <div class="row g-3">
            <div class="col-md-3"><strong>Production Order Number</strong><div><?= escape_html($work_order['wo_number']) ?></div></div>
            <div class="col-md-3"><strong>Production Date</strong><div><?= format_date($work_order['created_at']) ?></div></div>
            <div class="col-md-3"><strong>Production Type</strong><div><?= escape_html($production_type) ?></div></div>
            <div class="col-md-3"><strong>Customer</strong><div>-</div></div>
            <div class="col-md-4"><strong>Product</strong><div><?= escape_html($work_order['product_code'].' - '.$work_order['product_name']) ?></div></div>
            <div class="col-md-2"><strong>Quantity</strong><div><?= number_format((float)$work_order['quantity'], 2) ?></div></div>
            <div class="col-md-2"><strong>Unit</strong><div><?= escape_html($work_order['unit_of_measure'] ?: '-') ?></div></div>
            <div class="col-md-2"><strong>Priority</strong><div><?= escape_html(ucfirst((string)$work_order['priority'])) ?></div></div>
            <div class="col-md-2"><strong>Location</strong><div><?= escape_html($work_order['location_name'] ?: '-') ?></div></div>
            <div class="col-md-3"><strong>Expected Completion Date</strong><div><?= $work_order['due_date'] ? format_date($work_order['due_date']) : '-' ?></div></div>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Production Order Type Panel</h5>
        <div class="d-flex gap-2">
            <span class="badge <?= $production_type === 'Sales Order' ? 'bg-primary' : 'bg-secondary' ?>">Sales Linked Production</span>
            <span class="badge <?= $production_type === 'Stock' ? 'bg-primary' : 'bg-secondary' ?>">Stock Building</span>
            <span class="badge <?= $production_type === 'Trial' ? 'bg-primary' : 'bg-secondary' ?>">Trial Production</span>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Bill of Materials (Raw Material Table)</h5>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Raw Material Code</th><th>Raw Material Name</th><th>Category</th><th class="text-end">Required Qty</th><th class="text-end">Stock In</th><th class="text-end">Shortage Qty</th><th>Warehouse</th><th class="text-end">Unit Cost</th><th class="text-end">Total Cost</th></tr></thead>
                <tbody>
                <?php $material_total = 0; foreach ($bom_items as $item): $required = (float)$item['quantity_required'] * (float)$work_order['quantity']; $avail = (float)$item['stock_available']; $short = max(0, $required - $avail); $line = $required * (float)$item['unit_cost']; $material_total += $line; ?>
                    <tr>
                        <td><?= escape_html($item['component_code']) ?></td>
                        <td><?= escape_html($item['component_name']) ?></td>
                        <td><?= escape_html($item['category_name'] ?: '-') ?></td>
                        <td class="text-end"><?= number_format($required, 2) ?></td>
                        <td class="text-end"><?= number_format($avail, 2) ?></td>
                        <td class="text-end <?= $short > 0 ? 'text-danger fw-bold' : '' ?>"><?= number_format($short, 2) ?></td>
                        <td><?= escape_html($work_order['location_name'] ?: '-') ?></td>
                        <td class="text-end"><?= number_format((float)$item['unit_cost'], 2) ?></td>
                        <td class="text-end"><?= number_format($line, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Production Costing Section</h5>
        <div class="row g-3">
            <div class="col-md-3"><strong>Raw Material Cost</strong><div><?= number_format($material_total, 2) ?></div></div>
            <div class="col-md-3"><strong>Labor Cost</strong><div><?= number_format((float)($work_order['labor_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Electricity Cost</strong><div><?= number_format((float)($work_order['electricity_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Machine Cost</strong><div><?= number_format((float)($work_order['machine_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Overhead Cost</strong><div><?= number_format((float)($work_order['overhead_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Welding Cost</strong><div><?= number_format((float)($work_order['welding_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Other Production Cost</strong><div><?= number_format((float)($work_order['other_cost'] ?? 0), 2) ?></div></div>
            <div class="col-md-3"><strong>Total Production Cost</strong><div><?= number_format((float)($work_order['total_cost'] ?: $work_order['estimated_cost']), 2) ?></div></div>
            <div class="col-md-3"><strong>Cost per Unit</strong><div><?= number_format(((float)$work_order['quantity'] > 0 ? (float)($work_order['total_cost'] ?: $work_order['estimated_cost']) / (float)$work_order['quantity'] : 0), 2) ?></div></div>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Production Status Panel</h5>
        <div class="row g-3 mb-3">
            <div class="col-md-3"><strong>Production Status</strong><div><?= escape_html(ucfirst(str_replace('_', ' ', (string)$work_order['status']))) ?></div></div>
            <div class="col-md-3"><strong>Completion Date</strong><div><?= $work_order['completion_date'] ? format_date($work_order['completion_date']) : '-' ?></div></div>
            <div class="col-md-3"><strong>Supervisor</strong><div>-</div></div>
            <div class="col-md-3"><strong>Finished Goods</strong><div class="text-success fw-bold"><?= number_format($max_producible_qty, 2) ?></div></div>
            <div class="col-md-3"><strong>Unfinished Goods</strong><div class="<?= $unfinished_qty > 0 ? 'text-danger fw-bold' : 'text-success fw-bold' ?>"><?= number_format($unfinished_qty, 2) ?></div></div>
        </div>
        <?php if ($unfinished_qty > 0): ?>
        <div class="alert alert-danger py-2">
            Product shortage exists for this order. Please arrange raw materials for remaining <strong><?= number_format($unfinished_qty, 2) ?></strong> unit(s).
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2">
            <?php if ($work_order['status'] === 'planned' || $work_order['status'] === 'draft'): ?>
            <form method="POST" action="production_orders.php"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="update_status" value="1"><input type="hidden" name="wo_id" value="<?= $wo_id ?>"><input type="hidden" name="new_status" value="in_progress"><button class="btn btn-outline-primary">Start Production</button></form>
            <?php endif; ?>
            <?php if ($work_order['status'] === 'in_progress'): ?>
            <form method="POST" action="production_orders.php"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="update_status" value="1"><input type="hidden" name="wo_id" value="<?= $wo_id ?>"><input type="hidden" name="new_status" value="paused"><button class="btn btn-outline-warning">Pause Production</button></form>
            <?php endif; ?>
            <?php if ($work_order['status'] === 'paused'): ?>
            <form method="POST" action="production_orders.php"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="update_status" value="1"><input type="hidden" name="wo_id" value="<?= $wo_id ?>"><input type="hidden" name="new_status" value="in_progress"><button class="btn btn-outline-primary">Resume Production</button></form>
            <?php endif; ?>
            <?php if ($work_order['status'] === 'in_progress' || $work_order['status'] === 'paused'): ?>
            <form method="POST" action="production_orders.php"><input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>"><input type="hidden" name="update_status" value="1"><input type="hidden" name="wo_id" value="<?= $wo_id ?>"><input type="hidden" name="new_status" value="completed"><button class="btn btn-outline-success">Finish Production</button></form>
            <?php endif; ?>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Warehouse Output Section</h5>
        <div class="row g-3">
            <div class="col-md-4"><strong>Finished Goods Warehouse</strong><div><?= escape_html($work_order['location_name'] ?: '-') ?></div></div>
            <div class="col-md-4"><strong>Finished Goods Bin</strong><div><?= escape_html($fg_bin) ?></div></div>
            <div class="col-md-4"><strong>Stock Category</strong><div>-</div></div>
        </div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Attachments & Notes</h5>
        <div><strong>Notes</strong></div>
        <div class="border rounded p-3"><?= nl2br(escape_html((string)$work_order['notes'])) ?></div>
    </div></div>

    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3 border-bottom pb-2">Action Buttons</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="edit_production_order.php?id=<?= $wo_id ?>" class="btn btn-outline-secondary">Edit Order</a>
            <button type="button" class="btn btn-outline-info" onclick="addRawMaterial()">Add Raw Material</button>
            <button type="button" class="btn btn-outline-primary" onclick="loadSalesOrder()">Load Sales Order</button>
            <button type="button" class="btn btn-outline-danger" onclick="deleteOrder()">Delete Order</button>
            <button type="button" class="btn btn-outline-secondary" onclick="window.open('print_production_order.php?id=<?= $wo_id ?>','_blank')">Print Production Sheet</button>
            <button type="button" class="btn btn-outline-info" onclick="window.open('print_production_order.php?id=<?= $wo_id ?>&export=1','_blank')">Export Production Report</button>
        </div>
    </div></div>

</div>

<script>
function deleteOrder() {
    if (!confirm('Are you absolutely sure you want to delete this production order? This cannot be undone.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'production_orders.php';
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = 'csrf_token'; csrf.value = '<?= generate_csrf_token() ?>';
    form.appendChild(csrf);
    const del = document.createElement('input');
    del.type = 'hidden'; del.name = 'delete_id'; del.value = '<?= $wo_id ?>';
    form.appendChild(del);
    document.body.appendChild(form);
    form.submit();
}

function addRawMaterial() {
    alert("To manage raw materials, please edit the order and update the Bill of Materials.");
    window.location.href = 'edit_production_order.php?id=<?= $wo_id ?>';
}

function loadSalesOrder() {
    alert("Sales Orders should be linked during the editing phase.");
    window.location.href = 'edit_production_order.php?id=<?= $wo_id ?>';
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
