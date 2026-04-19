<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Production Order - MJR Group ERP';
$company_id = active_company_id(1);
$wo_id = intval(get_param('id', 0));
if ($wo_id <= 0) {
    set_flash('Production order not found.', 'error');
    redirect('production_orders.php');
}

$work_order = db_fetch("SELECT wo.* FROM work_orders wo JOIN inventory_items i ON wo.product_id = i.id WHERE wo.id = ? AND i.company_id = ?", [$wo_id, $company_id]);
if (!$work_order) {
    set_flash('Production order not found.', 'error');
    redirect('production_orders.php');
}

$errors = [];
if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $product_id = post('product_id', '');
    $quantity = post('quantity', '');
    $location_id = post('location_id', '');
    $start_date = to_db_date(post('start_date', ''));
    $due_date = to_db_date(post('due_date', ''));
    $priority = post('priority', 'normal');
    $estimated_cost = floatval(post('estimated_cost', '0'));
    $cost_currency = post('cost_currency', 'FJD');
    $tax_class_id = post('tax_class_id', '');
    $status = post('production_status', $work_order['status'] ?? 'planned');
    $production_type = post('production_type', 'stock');
    $labor_cost = floatval(post('labor_cost', '0'));
    $electricity_cost = floatval(post('electricity_cost', '0'));
    $machine_cost = floatval(post('machine_cost', '0'));
    $other_cost = floatval(post('other_cost', '0'));
    $notes = trim(post('notes', ''));

    if (empty($product_id)) $errors['product_id'] = err_required();
    if (empty($quantity) || floatval($quantity) <= 0) $errors['quantity'] = 'Quantity must be greater than 0';
    if (empty($location_id)) $errors['location_id'] = err_required();

    if (!$errors) {
        $tax_amount = 0;
        if (!empty($tax_class_id)) {
            $tax = db_fetch("SELECT tax_rate FROM tax_configurations WHERE id = ? AND is_active = 1", [$tax_class_id]);
            if ($tax && !empty($tax['tax_rate'])) {
                $tax_amount = $estimated_cost * floatval($tax['tax_rate']);
            }
        }
        $total_cost = $estimated_cost + $tax_amount;

        db_query(
            "UPDATE work_orders
             SET product_id = ?, quantity = ?, location_id = ?, start_date = ?, due_date = ?, priority = ?,
                 estimated_cost = ?, cost_currency = ?, subtotal = ?, tax_amount = ?, total_cost = ?, tax_class_id = ?, 
                 notes = ?, status = ?, production_type = ?, labor_cost = ?, electricity_cost = ?, machine_cost = ?, other_cost = ?
             WHERE id = ?",
            [
                intval($product_id), floatval($quantity), intval($location_id), $start_date ?: null, $due_date ?: null, $priority,
                $estimated_cost, $cost_currency, $estimated_cost, $tax_amount, $total_cost, $tax_class_id ?: null, 
                $notes, $status, $production_type, $labor_cost, $electricity_cost, $machine_cost, $other_cost, $wo_id
            ]
        );
        set_flash('Production order updated successfully!', 'success');
        redirect('production_orders.php');
    }
}

$products = db_fetch_all("SELECT id, code, name, unit_of_measure FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$supervisors = db_fetch_all("SELECT id, username FROM users WHERE is_active = 1 AND company_id = ? ORDER BY username", [$company_id]);
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_rate FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('sales_tax','both') ORDER BY tax_name");
$selected_location_id = intval(post('location_id', $work_order['location_id'] ?? 0));
$bins = $selected_location_id > 0
    ? db_fetch_all("SELECT b.id, b.code FROM bins b JOIN warehouses w ON w.id=b.warehouse_id WHERE COALESCE(b.is_active,1)=1 AND w.location_id=? ORDER BY b.code", [$selected_location_id])
    : [];
$bom_catalog = db_fetch_all("
    SELECT bom.product_id, comp.code AS component_code, comp.name AS component_name, cat.name AS category_name,
           comp.unit_of_measure, bom.quantity_required, COALESCE(comp.cost_price,0) AS unit_cost, COALESCE(SUM(wi.quantity),0) AS stock_available
    FROM bill_of_materials bom
    JOIN inventory_items comp ON comp.id=bom.component_id
    LEFT JOIN categories cat ON cat.id=comp.category_id
    LEFT JOIN warehouse_inventory wi ON wi.product_id=comp.id
    WHERE bom.is_active=1 AND comp.company_id = $company_id
    GROUP BY bom.product_id, bom.id, comp.id
    ORDER BY bom.product_id, comp.name
");
$production_history = db_fetch_all("
    SELECT wo.wo_number, wo.quantity, wo.status, wo.created_at, ii.code AS product_code, ii.name AS product_name
    FROM work_orders wo LEFT JOIN inventory_items ii ON ii.id=wo.product_id
    ORDER BY wo.id DESC LIMIT 10
");

include __DIR__ . '/../../templates/header.php';
?>
<div class="container">
    <div class="row mb-4">
        <div class="col"><h2><i class="fas fa-edit me-2"></i>Edit Production Order</h2><p class="text-muted">PO: <?= escape_html($work_order['wo_number']) ?></p></div>
        <div class="col-auto"><a href="production_orders.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a></div>
    </div>
    <div class="card"><div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <h5 class="mb-3 border-bottom pb-2">Production Order Header</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><label class="form-label">Order No</label><input class="form-control" readonly value="<?= escape_html($work_order['wo_number']) ?>"></div>
                <div class="col-md-4"><label class="form-label">Product</label><select class="form-select" id="product_id" name="product_id"><?php foreach ($products as $p): ?><option value="<?= $p['id'] ?>" data-uom="<?= escape_html((string)$p['unit_of_measure']) ?>" <?= post('product_id', $work_order['product_id']) == $p['id'] ? 'selected' : '' ?>><?= escape_html($p['code'].' - '.$p['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2"><label class="form-label">Qty</label><input class="form-control" id="quantity" name="quantity" type="number" min="1" value="<?= escape_html(post('quantity', (string)$work_order['quantity'])) ?>"></div>
                <div class="col-md-3"><label class="form-label">Production Type</label><select class="form-select" id="production_type" name="production_type"><option value="sales_order">Sales Order</option><option value="stock" selected>Stock</option><option value="trial">Trial</option></select></div>
            </div>

            <h5 class="mb-3 border-bottom pb-2">Production Order Type Panel</h5>
            <div class="btn-group mb-4" role="group"><button class="btn btn-outline-primary type-btn" type="button" data-type="sales_order">Sales Order</button><button class="btn btn-outline-primary type-btn" type="button" data-type="stock">Stock</button><button class="btn btn-outline-primary type-btn" type="button" data-type="trial">Trial</button></div>

            <h5 class="mb-3 border-bottom pb-2">Bill of Materials (Raw Material Table)</h5>
            <div class="table-responsive mb-4"><table class="table table-bordered"><thead><tr><th>Code</th><th>Name</th><th>Category</th><th class="text-end">Required</th><th class="text-end">Available</th><th class="text-end">Shortage</th><th>Warehouse</th><th class="text-end">Unit Cost</th><th class="text-end">Total</th></tr></thead><tbody id="bomBody"><tr><td colspan="9" class="text-center text-muted">Select product to load BOM</td></tr></tbody></table></div>

            <h5 class="mb-3 border-bottom pb-2">Production Costing Section</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><label class="form-label">Raw Material Cost</label><input id="material_cost" class="form-control" readonly value="0.00"></div>
                <div class="col-md-3"><label class="form-label">Labor Cost</label><input id="labor_cost" name="labor_cost" class="form-control" value="<?= escape_html(post('labor_cost', (string)($work_order['labor_cost'] ?? 0))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Electricity Cost</label><input id="electricity_cost" name="electricity_cost" class="form-control" value="<?= escape_html(post('electricity_cost', (string)($work_order['electricity_cost'] ?? 0))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Machine Cost</label><input id="machine_cost" name="machine_cost" class="form-control" value="<?= escape_html(post('machine_cost', (string)($work_order['machine_cost'] ?? 0))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Other Production Cost</label><input id="other_cost" name="other_cost" class="form-control" value="<?= escape_html(post('other_cost', (string)($work_order['other_cost'] ?? 0))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Total Production Cost</label><input id="estimated_cost" name="estimated_cost" class="form-control" readonly value="<?= escape_html(post('estimated_cost', (string)($work_order['estimated_cost'] ?? 0))) ?>"></div>
                <div class="col-md-3"><label class="form-label">Currency</label><select id="cost_currency" name="cost_currency" class="form-select"><option value="FJD" <?= post('cost_currency', $work_order['cost_currency'] ?? 'FJD') == 'FJD' ? 'selected' : '' ?>>FJD / $</option><option value="INR" <?= post('cost_currency', $work_order['cost_currency'] ?? '') == 'INR' ? 'selected' : '' ?>>INR / Rs</option><option value="USD" <?= post('cost_currency', $work_order['cost_currency'] ?? '') == 'USD' ? 'selected' : '' ?>>USD / $</option></select></div>
                <div class="col-md-3"><label class="form-label">Tax Class</label><select id="tax_class_id" name="tax_class_id" class="form-select"><option value="">No Tax</option><?php foreach ($tax_classes as $t): ?><option value="<?= $t['id'] ?>" data-rate="<?= $t['tax_rate'] ?>" <?= post('tax_class_id', $work_order['tax_class_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= escape_html($t['tax_name']) ?> (<?= number_format($t['tax_rate']*100,2) ?>%)</option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Total + Tax</label><div class="input-group"><span class="input-group-text" id="currencySymbol">$</span><input id="totalCostDisplay" class="form-control" readonly value="0.00"></div></div>
                <div class="col-md-3"><label class="form-label">Cost per Unit</label><input id="costPerUnitDisplay" class="form-control" readonly value="0.00"></div>
            </div>

            <h5 class="mb-3 border-bottom pb-2">Production Status Panel</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-4"><label class="form-label">Production Status</label><select class="form-select" name="production_status"><option value="draft" <?= post('production_status', $work_order['status']) === 'draft' ? 'selected' : '' ?>>Draft</option><option value="planned" <?= post('production_status', $work_order['status']) === 'planned' ? 'selected' : '' ?>>Planned</option><option value="in_progress" <?= post('production_status', $work_order['status']) === 'in_progress' ? 'selected' : '' ?>>In Progress</option><option value="paused" <?= post('production_status', $work_order['status']) === 'paused' ? 'selected' : '' ?>>Paused</option><option value="completed" <?= post('production_status', $work_order['status']) === 'completed' ? 'selected' : '' ?>>Completed</option><option value="cancelled" <?= post('production_status', $work_order['status']) === 'cancelled' ? 'selected' : '' ?>>Cancelled</option></select></div>
                <div class="col-md-2"><label class="form-label">Actual Qty</label><input class="form-control" type="number" name="actual_production_qty"></div>
                <div class="col-md-3"><label class="form-label">Completion Date</label><input class="form-control" name="production_completion_date" placeholder="DD-MM-YYYY"></div>
                <div class="col-md-3"><label class="form-label">Supervisor</label><select class="form-select" name="production_supervisor_id"><option value="">Select</option><?php foreach ($supervisors as $s): ?><option value="<?= $s['id'] ?>"><?= escape_html($s['username']) ?></option><?php endforeach; ?></select></div>
            </div>

            <h5 class="mb-3 border-bottom pb-2">Warehouse Output Section</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6"><label class="form-label">Finished Goods Warehouse</label><select class="form-select" id="location_id" name="location_id"><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>" <?= post('location_id', $work_order['location_id']) == $l['id'] ? 'selected' : '' ?>><?= escape_html($l['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Finished Goods Bin</label><div class="input-group"><select class="form-select" id="output_bin_id" name="output_bin_id"><option value="">Select Bin</option><?php foreach ($bins as $b): ?><option value="<?= $b['id'] ?>"><?= escape_html($b['code']) ?></option><?php endforeach; ?></select><a href="../inventory/modules/warehouses/index.php" target="_blank" class="btn btn-outline-info"><i class="fas fa-plus"></i></a></div></div>
                <div class="col-md-4"><label class="form-label">Start Date</label><input class="form-control" name="start_date" id="start_date" value="<?= escape_html(post('start_date', format_date($work_order['start_date'] ?: ''))) ?>"></div>
                <div class="col-md-4"><label class="form-label">Expected Completion Date</label><input class="form-control" name="due_date" id="due_date" value="<?= escape_html(post('due_date', format_date($work_order['due_date'] ?: ''))) ?>"></div>
                <div class="col-md-4"><label class="form-label">Priority</label><select class="form-select" name="priority"><?php foreach (['urgent','high','normal','low'] as $p): ?><option value="<?= $p ?>" <?= post('priority', $work_order['priority']) == $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select></div>
            </div>

            <h5 class="mb-3 border-bottom pb-2">Attachments & Notes</h5>
            <div class="row g-3 mb-3"><div class="col-md-6"><label class="form-label">Upload Production Report</label><input class="form-control" type="file"></div><div class="col-md-6"><label class="form-label">Upload Design File</label><input class="form-control" type="file"></div></div>
            <div class="mb-4"><label class="form-label">Supervisor Notes</label><textarea class="form-control" name="notes" id="notes" rows="3"><?= escape_html(post('notes', (string)$work_order['notes'])) ?></textarea></div>



            <!-- Action Buttons (Styled cleanly below notes) -->
            <div class="mt-4 pt-3 border-top border-secondary">
                <!-- Top Row: Lifecycle Actions -->
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-danger px-3 form-control-sm border border-danger text-danger bg-transparent" onclick="deleteOrder()">Delete Item</button>
                    
                    <?php if ($work_order['status'] == 'planned' || $work_order['status'] == 'paused'): ?>
                        <button type="button" class="btn btn-outline-success px-3 form-control-sm border border-success text-success bg-transparent" onclick="submitWithStatus('in_progress')">Start Production</button>
                    <?php endif; ?>
                    
                    <?php if ($work_order['status'] == 'in_progress'): ?>
                        <button type="button" class="btn btn-outline-warning px-3 form-control-sm border border-warning text-warning bg-transparent" onclick="submitWithStatus('paused')">Pause Production</button>
                        <button type="button" class="btn btn-outline-success px-3 form-control-sm border border-success text-success bg-transparent" onclick="submitWithStatus('completed')">Finish Production</button>
                    <?php endif; ?>
                    
                    <?php if ($work_order['status'] != 'cancelled'): ?>
                        <button type="button" class="btn btn-outline-danger px-3 form-control-sm border border-danger text-danger bg-transparent" onclick="submitWithStatus('cancelled')">Cancel Production</button>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-success px-4 form-control-sm border border-success text-success bg-transparent" onclick="submitWithStatus('<?= $work_order['status'] ?>')">
                        <i class="fas fa-save me-1"></i>Update &amp; Save Changes
                    </button>
                </div>
                
                <!-- Bottom Row: Utility Actions -->
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary px-3 form-control-sm text-secondary bg-transparent border border-secondary" onclick="window.print()">Print Production Sheet</button>
                    <a href="view_production_order.php?id=<?= $wo_id ?>&export=1" class="btn btn-info px-4 form-control-sm fw-bold text-dark" style="background-color: #00d2ff; border-color: #00d2ff;"><i class="fas fa-file-export me-1"></i>Export Production Report</a>
                </div>
            </div>
        </form>
    </div></div>
</div>

<script>
const bomCatalog = <?= json_encode($bom_catalog) ?>;
function getCurrencySymbol(c){ if(c==='INR') return 'Rs'; return '$'; }
function updateBomTableAndCost(){
  const productId=document.getElementById('product_id').value;
  const qty=parseFloat(document.getElementById('quantity').value)||0;
  const body=document.getElementById('bomBody');
  const rows=bomCatalog.filter(r=>String(r.product_id)===String(productId));
  let material=0;
  if(!rows.length){ body.innerHTML='<tr><td colspan="9" class="text-center text-muted">No BOM found for selected product</td></tr>'; document.getElementById('material_cost').value='0.00'; updateTotals(); return; }
  body.innerHTML='';
  rows.forEach(r=>{
    const req=(parseFloat(r.quantity_required)||0)*qty, av=parseFloat(r.stock_available)||0, sh=Math.max(0,req-av), uc=parseFloat(r.unit_cost)||0, tc=req*uc;
    material+=tc;
    const wh=(document.getElementById('location_id').selectedOptions[0]||{}).textContent||'-';
    const tr=document.createElement('tr');
    tr.innerHTML=`<td>${r.component_code||''}</td><td>${r.component_name||''}</td><td>${r.category_name||'-'}</td><td class="text-end">${req.toFixed(2)} ${r.unit_of_measure||''}</td><td class="text-end">${av.toFixed(2)}</td><td class="text-end ${sh>0?'text-danger fw-bold':''}">${sh.toFixed(2)}</td><td>${wh}</td><td class="text-end">${uc.toFixed(2)}</td><td class="text-end">${tc.toFixed(2)}</td>`;
    body.appendChild(tr);
  });
  document.getElementById('material_cost').value=material.toFixed(2);
  updateTotals();
}
function updateTotals(){
  const material=parseFloat(document.getElementById('material_cost').value)||0;
  const labor=parseFloat(document.getElementById('labor_cost').value)||0;
  const elec=parseFloat(document.getElementById('electricity_cost').value)||0;
  const machine=parseFloat(document.getElementById('machine_cost').value)||0;
  const other=parseFloat(document.getElementById('other_cost').value)||0;
  const subtotal=material+labor+elec+machine+other;
  document.getElementById('estimated_cost').value=subtotal.toFixed(2);
  document.getElementById('currencySymbol').textContent=getCurrencySymbol(document.getElementById('cost_currency').value);
  let tax=0; const t=document.getElementById('tax_class_id'); if(t&&t.value){ tax=subtotal*(parseFloat(t.options[t.selectedIndex].getAttribute('data-rate'))||0); }
  const total=subtotal+tax; document.getElementById('totalCostDisplay').value=total.toFixed(2);
  const qty=parseFloat(document.getElementById('quantity').value)||0; document.getElementById('costPerUnitDisplay').value=(qty>0?subtotal/qty:0).toFixed(2);
}
function loadBinsByLocation(){
  const loc=document.getElementById('location_id').value, bin=document.getElementById('output_bin_id');
  bin.innerHTML='<option value="">Select Bin</option>'; if(!loc) return;
  fetch('../inventory/ajax_get_bins.php?location_id='+encodeURIComponent(loc)).then(r=>r.json()).then(d=>{ (Array.isArray(d)?d:[]).forEach(b=>{ const o=document.createElement('option'); o.value=b.bin_id; o.textContent=b.bin_location; bin.appendChild(o);});});
}
function deleteOrder() {
    if (!confirm('Are you ABSOLUTELY sure you want to delete this production order? This cannot be undone.')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'production_orders.php';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
    form.appendChild(csrfInput);
    
    // Add delete action and ID
    const deleteIdInput = document.createElement('input');
    deleteIdInput.type = 'hidden';
    deleteIdInput.name = 'delete_id';
    deleteIdInput.value = '<?= $wo_id ?>';
    form.appendChild(deleteIdInput);
    
    document.body.appendChild(form);
    form.submit();
}

function submitWithStatus(statusStr) {
    const form = document.querySelector('form');
    
    // Create or update status input
    let statusInput = document.querySelector('input[name="production_status"]');
    if (!statusInput) {
        // Find the select and update its value instead
        const statusSelect = document.querySelector('select[name="production_status"]');
        if (statusSelect) {
            statusSelect.value = statusStr;
        } else {
            statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'production_status';
            statusInput.value = statusStr;
            form.appendChild(statusInput);
        }
    } else {
        statusInput.value = statusStr;
    }
    
    form.submit();
}

document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.type-btn').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.type-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('production_type').value=btn.dataset.type;}));
  ['product_id','quantity'].forEach(id=>{const e=document.getElementById(id); if(e){e.addEventListener('change',updateBomTableAndCost); e.addEventListener('input',updateBomTableAndCost);}});
  ['labor_cost','electricity_cost','machine_cost','other_cost','tax_class_id','cost_currency'].forEach(id=>{const e=document.getElementById(id); if(e){e.addEventListener('change',updateTotals); if(e.tagName==='INPUT') e.addEventListener('input',updateTotals);}});
  const l=document.getElementById('location_id'); if(l) l.addEventListener('change',()=>{loadBinsByLocation(); updateBomTableAndCost();});
  updateBomTableAndCost(); updateTotals();
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
