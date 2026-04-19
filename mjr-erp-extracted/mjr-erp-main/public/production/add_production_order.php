<?php
/**
 * Add Production Order
 * Create new production order
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_production');

$page_title = 'Create Production Order - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to create production orders.', 'warning');
    redirect(url('index.php'));
}

// Get products for dropdown
$products = db_fetch_all("SELECT i.id, i.code, i.name, u.code as unit_code FROM inventory_items i LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id WHERE i.is_active = 1 AND i.company_id = ? ORDER BY i.code", [$company_id]);

// Get warehouses for FG destination
$warehouses = db_fetch_all("SELECT id, name FROM warehouses WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get bins (will be filtered by JS)
$all_bins = db_fetch_all("SELECT b.id, b.warehouse_id, b.code FROM bins b JOIN warehouses w ON b.warehouse_id = w.id WHERE b.is_active = 1 AND w.company_id = ? ORDER BY b.code", [$company_id]);

// Get active locations for production
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get confirmed sales orders for linking
$sales_orders = db_fetch_all("SELECT id, order_number FROM sales_orders WHERE status IN ('confirmed', 'partial') AND company_id = ? ORDER BY order_number DESC", [$company_id]);

// Get tax classes for quick add
$tax_classes = db_fetch_all("SELECT id, tax_name FROM tax_configurations WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL) ORDER BY tax_name", [$company_id]);
$uoms = db_fetch_all("SELECT id, code, name FROM units_of_measure WHERE is_active = 1 ORDER BY name");
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

$errors = [];

/**
 * Return BOM shortage lines for a requested production quantity.
 *
 * @param int $product_id
 * @param float $order_qty
 * @return array<int, array<string, mixed>>
 */
function get_bom_shortages_for_order($product_id, $order_qty) {
    return db_fetch_all("
        SELECT
            b.component_id,
            i.code AS component_code,
            i.name AS component_name,
            b.quantity_required AS per_piece_qty,
            (b.quantity_required * ?) AS required_qty,
            COALESCE(SUM(wi.quantity), 0) AS available_qty,
            ((b.quantity_required * ?) - COALESCE(SUM(wi.quantity), 0)) AS shortage_qty
        FROM bill_of_materials b
        JOIN inventory_items i ON i.id = b.component_id
        LEFT JOIN warehouse_inventory wi ON wi.product_id = b.component_id
        WHERE b.product_id = ? AND COALESCE(b.is_active, 1) = 1
        GROUP BY b.id, i.id
        HAVING shortage_qty > 0
        ORDER BY i.name
    ", [$order_qty, $order_qty, $product_id]);
}

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $product_id = intval(post('product_id'));
            $quantity = floatval(post('quantity'));
            $production_type = post('production_type', 'Stock Production');
            $raw_material_cost = floatval(post('raw_material_cost', 0));
            $labor_cost = floatval(post('labor_cost', 0));
            $electricity_cost = floatval(post('electricity_cost', 0));
            $machine_cost = floatval(post('machine_cost', 0));
            $other_cost = floatval(post('other_cost', 0));
            $overhead_cost = floatval(post('overhead_cost', 0));
            $welding_cost = floatval(post('welding_cost', 0));
            $fg_warehouse_id = post('fg_warehouse_id');
            $fg_bin_id = post('fg_bin_id');
            $notes = post('notes');
            $status = post('form_status') ?: 'planned'; // Use form_status from JS submission

            // Handle multiple file uploads
            $upload_dir = __DIR__ . '/../../uploads/production/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $attachment_paths = [];
            if (!empty($_FILES['attachments']['name'][0])) {
                foreach ($_FILES['attachments']['name'] as $idx => $fname) {
                    if ($_FILES['attachments']['error'][$idx] === UPLOAD_ERR_OK) {
                        $safe_name = time() . '_' . $idx . '_' . basename($fname);
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$idx], $upload_dir . $safe_name)) {
                            $attachment_paths[] = $safe_name;
                        }
                    }
                }
            }
            $attachments_json = !empty($attachment_paths) ? json_encode($attachment_paths) : null;
            
            // Validate
            if ($product_id <= 0 || $quantity <= 0) {
                throw new Exception('Product and quantity are required');
            }

            $bom_count_row = db_fetch(
                "SELECT COUNT(*) AS c FROM bill_of_materials WHERE product_id = ? AND COALESCE(is_active, 1) = 1",
                [$product_id]
            );
            $bom_count = intval($bom_count_row['c'] ?? 0);
            if ($bom_count <= 0) {
                throw new Exception('No BOM found for selected product. Please configure BOM first.');
            }

            $shortages = get_bom_shortages_for_order($product_id, $quantity);
            if (!empty($shortages)) {
                $msg_parts = [];
                foreach ($shortages as $s) {
                    $msg_parts[] = sprintf(
                        '%s - Need %.2f, Available %.2f, Short %.2f',
                        trim(($s['component_code'] ?? '') . ' ' . ($s['component_name'] ?? '')),
                        (float)$s['required_qty'],
                        (float)$s['available_qty'],
                        (float)$s['shortage_qty']
                    );
                }
                throw new Exception('Cannot create production order. Product shortage: ' . implode(' | ', $msg_parts));
            }
            
            // Get default location
            if (empty($locations)) {
                 throw new Exception('No production locations configured in system');
            }
            $location_id = $locations[0]['id'];
            
            // Generate WO number
            $wo_prefix = 'WO-' . date('Y');
            $last_wo = db_fetch("SELECT wo_number FROM work_orders WHERE wo_number LIKE ? ORDER BY id DESC LIMIT 1", [$wo_prefix . '%']);
            $new_num = $last_wo ? intval(substr($last_wo['wo_number'], -4)) + 1 : 1;
            $wo_number = $wo_prefix . '-' . str_pad($new_num, 4, '0', STR_PAD_LEFT);
            
            // Calculate totals
            $subtotal = $raw_material_cost + $labor_cost + $electricity_cost + $machine_cost + $other_cost + $overhead_cost + $welding_cost;
            $estimated_cost = $subtotal;
            $total_cost = $subtotal;
            
            $current_user = current_user();
            
            // Insert production order
            $sql = "INSERT INTO work_orders (
                        wo_number, product_id, quantity, location_id, fg_warehouse_id, fg_bin_id, 
                        production_type, manufacturing_model, raw_material_cost, labor_cost, electricity_cost, machine_cost, 
                        overhead_cost, welding_cost, other_cost,
                        subtotal, estimated_cost, total_cost, notes, status, created_at, created_by
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, 
                        ?, 'fixed', ?, ?, ?, ?, 
                        ?, ?, ?,
                        ?, ?, ?, ?, ?, NOW(), ?
                    )";
            
            db_query($sql, [
                $wo_number, $product_id, $quantity, $location_id, $fg_warehouse_id ?: null, $fg_bin_id ?: null,
                $production_type, $raw_material_cost, $labor_cost, $electricity_cost, $machine_cost, 
                $overhead_cost, $welding_cost, $other_cost,
                $subtotal, $estimated_cost, $total_cost, $notes, $status, $current_user['id']
            ]);

            $new_id = db_insert_id();

            // Update sales_order_id if applicable
            if ($production_type === 'Sales Order' && post('sales_order_id')) {
                db_query("UPDATE work_orders SET sales_order_id = ? WHERE id = ?", [post('sales_order_id'), $new_id]);
            }

            // Handle Automation if created as completed
            if ($status === 'completed') {
                require_once __DIR__ . '/../../includes/production_functions.php';
                deduct_production_stock($new_id);
                add_finished_goods_stock($new_id);
            }

            set_flash('Production order created successfully: ' . $wo_number, 'success');
            redirect('view_production_order.php?id=' . $new_id);
            
        } catch (Exception $e) {
            set_flash('Error creating production order: ' . $e->getMessage(), 'error');
        }
    } else {
        set_flash('Invalid CSRF token.', 'error');
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-plus me-2"></i>Create Production Order</h2>
        </div>
        <div class="col-auto">
            <a href="production_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Production Orders
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="addProductionForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="form_status" id="form_status" value="planned">

                <h5 class="mb-3 border-bottom pb-2">Production Order Header</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Order No</label>
                        <input type="text" class="form-control bg-light" value="Auto-generated" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select class="form-select" id="product_id" name="product_id" required onchange="fetchProductBOM()">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" data-unit="<?= escape_html($product['unit_code']) ?>">
                                    <?= escape_html($product['code']) ?> - <?= escape_html($product['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-info" onclick="openQuickAddModal()" title="Quick Add Product">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="quantity" class="form-label">Qty <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" required onchange="fetchProductBOM()">
                    </div>
                    <div class="col-md-3">
                        <label for="production_type" class="form-label">Production Type</label>
                        <select class="form-select" id="production_type" name="production_type" onchange="toggleSalesOrder()">
                            <option value="Stock Production">Stock Production</option>
                            <option value="Sales Order">Sales Order</option>
                            <option value="Trial Production">Trial Production</option>
                        </select>
                    </div>
                </div>

                <div class="row g-3 mb-4" id="salesOrderRow" style="display: none;">
                    <div class="col-md-6">
                        <label for="sales_order_id" class="form-label">Sales Order Reference <span class="text-danger">*</span></label>
                        <select class="form-select" id="sales_order_id" name="sales_order_id">
                            <option value="">Select Sales Order</option>
                            <?php foreach ($sales_orders as $so): ?>
                            <option value="<?= $so['id'] ?>"><?= escape_html($so['order_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Production Type Panel</h5>
                <div class="row g-2 mb-4" id="productionTypePanel">
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary active" onclick="setProductionType(this, 'Sales Order')">Sales Order</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary" onclick="setProductionType(this, 'Stock Production')">Stock</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" class="btn btn-outline-primary" onclick="setProductionType(this, 'Trial Production')">Trial</button>
                    </div>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Bill of Materials Table</h5>
                <div id="fgStatusPanel" class="alert alert-secondary d-flex flex-wrap gap-3 align-items-center mb-3">
                    <span class="fw-semibold">Finished Goods: <span id="finishedGoodsQty">0.00</span></span>
                    <span class="fw-semibold">Unfinished Goods: <span id="unfinishedGoodsQty">0.00</span></span>
                    <span id="shortageNotice" class="text-danger fw-semibold" style="display:none;">
                        Raw material shortage found. This order cannot be saved.
                    </span>
                </div>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-dark">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Required (1 Piece)</th>
                                <th>Required (Order Qty)</th>
                                <th>Available</th>
                                <th>Shortage</th>
                            </tr>
                        </thead>
                        <tbody id="bomBody">
                            <tr><td colspan="5" class="text-center text-muted">Select product to load BOM</td></tr>
                        </tbody>
                    </table>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Production Costing</h5>
                <input type="hidden" id="electricity_cost" name="electricity_cost" value="0">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label for="raw_material_cost" class="form-label">Material Cost</label>
                        <input type="number" class="form-control" id="raw_material_cost" name="raw_material_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="labor_cost" class="form-label">Labor</label>
                        <input type="number" class="form-control" id="labor_cost" name="labor_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="machines_cost" class="form-label">Machine</label>
                        <input type="number" class="form-control" id="machine_cost" name="machine_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="other_cost" class="form-label">Other Costs</label>
                        <input type="number" class="form-control" id="other_cost" name="other_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="overhead_cost" class="form-label">Overhead Cost</label>
                        <input type="number" class="form-control" id="overhead_cost" name="overhead_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="welding_cost" class="form-label">Welding Cost</label>
                        <input type="number" class="form-control" id="welding_cost" name="welding_cost" value="0" step="0.01" min="0" onchange="calculateTotals()">
                    </div>
                    <div class="col-md-3">
                        <label for="totalCostDisplay" class="form-label">Total</label>
                        <input type="text" class="form-control bg-light text-primary fw-bold" id="totalCostDisplay" readonly value="0.00">
                    </div>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Warehouse Output Section</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="fg_warehouse_id" class="form-label">Finished Goods Warehouse</label>
                        <select class="form-select" id="fg_warehouse_id" name="fg_warehouse_id" onchange="filterBins()">
                            <option value="">Select Warehouse</option>
                            <?php foreach ($warehouses as $wh): ?>
                            <option value="<?= $wh['id'] ?>"><?= escape_html($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="fg_bin_id" class="form-label">Bin</label>
                        <select class="form-select" id="fg_bin_id" name="fg_bin_id">
                            <option value="">Select Bin</option>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Production Status Panel</h5>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-success px-4" onclick="submitWithStatus('in_progress')">Start Production</button>
                            <button type="button" class="btn btn-outline-success px-4" onclick="submitWithStatus('completed')">Finish Production</button>
                        </div>
                    </div>
                </div>

                <h5 class="mb-3 border-bottom pb-2">Attachments &amp; Notes</h5>
                <div id="attachments-container" class="mb-2">
                    <div class="attachment-row d-flex align-items-center gap-2 mb-2">
                        <input type="text" class="form-control" name="attachment_labels[]" placeholder="Label (e.g. Design File)" style="max-width:200px">
                        <input type="file" class="form-control" name="attachments[]">
                        <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0" onclick="removeAttachment(this)" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm mb-4" onclick="addAttachment()">
                    <i class="fas fa-plus me-1"></i> Add More Files
                </button>
                <div class="mb-4">
                    <label for="notes" class="form-label">Supervisor Notes / Remarks</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='production_orders.php'">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-2"></i>Create Production Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Quick Add Product Modal -->
<div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-info"><i class="fas fa-cube me-2"></i>Quick Add Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickAddForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Item Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="qa_code" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Item Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="qa_name" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="qa_category_id">
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= escape_html($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Unit <span class="text-danger">*</span></label>
                            <select class="form-select" name="qa_uom_id" required>
                                <?php foreach ($uoms as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= escape_html($u['code']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tax class</label>
                            <select class="form-select" name="qa_tax_id">
                                <option value="">No Tax</option>
                                <?php foreach ($tax_classes as $tax): ?>
                                <option value="<?= $tax['id'] ?>"><?= escape_html($tax['tax_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="saveQuickProduct()">Save Product</button>
            </div>
        </div>
    </div>
</div>

<script>
const allBins = <?= json_encode($all_bins) ?>;
let bomHasShortage = false;

function setProductionType(btn, value) {
    // Update the dropdown value
    document.getElementById('production_type').value = value;
    toggleSalesOrder();
    
    // Update visual state of buttons
    const buttons = document.querySelectorAll('#productionTypePanel button');
    buttons.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function toggleSalesOrder() {
    const type = document.getElementById('production_type').value;
    const row = document.getElementById('salesOrderRow');
    const soSelect = document.getElementById('sales_order_id');
    
    if (type === 'Sales Order') {
        row.style.display = 'flex';
        soSelect.required = true;
    } else {
        row.style.display = 'none';
        soSelect.required = false;
        soSelect.value = '';
    }
}

function openQuickAddModal() {
    new bootstrap.Modal(document.getElementById('quickAddModal')).show();
}

function saveQuickProduct() {
    const form = document.getElementById('quickAddForm');
    const data = new FormData(form);
    
    fetch('../inventory/ajax_create_item.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Add to the product dropdown and select it
            const select = document.getElementById('product_id');
            const option = document.createElement('option');
            option.value = data.id;
            option.text = data.code + ' - ' + data.name;
            option.selected = true;
            select.add(option);
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('quickAddModal')).hide();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(e => alert('Failed to save product.'));
}

function fetchProductBOM() {
    const productId = document.getElementById('product_id').value;
    const qty = parseFloat(document.getElementById('quantity').value) || 1;
    const bomBody = document.getElementById('bomBody');
    
    if (!productId) {
        bomBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Select product to load BOM</td></tr>';
        document.getElementById('raw_material_cost').value = '0.00';
        updateFinishedGoodsPanel(0, 0, false);
        calculateTotals();
        return;
    }

    fetch(`ajax_get_product_bom.php?product_id=${encodeURIComponent(productId)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const rows = (data && data.success && Array.isArray(data.rows)) ? data.rows : [];

        if (rows.length === 0) {
            bomBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No BOM found for selected product</td></tr>';
            document.getElementById('raw_material_cost').value = '0.00';
            updateFinishedGoodsPanel(0, qty, true);
            calculateTotals();
            return;
        }

        bomBody.innerHTML = '';
        let materialCost = 0;
        let hasShortage = false;

        rows.forEach(row => {
            const perPiece = parseFloat(row.quantity_required) || 0;
            const required = perPiece * qty;
            const available = parseFloat(row.stock_available) || 0;
            const shortage = Math.max(0, required - available);
            const unitCost = parseFloat(row.unit_cost) || 0;
            if (shortage > 0) hasShortage = true;
            materialCost += required * unitCost;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${row.component_name || row.component_code}</td>
                <td>${perPiece.toFixed(2)}</td>
                <td>${required.toFixed(2)}</td>
                <td>${available.toFixed(2)}</td>
                <td class="${shortage > 0 ? 'text-danger fw-bold' : ''}">${shortage.toFixed(2)}</td>
            `;
            bomBody.appendChild(tr);
        });

        document.getElementById('raw_material_cost').value = materialCost.toFixed(2);
        updateFinishedGoodsPanel(hasShortage ? 0 : qty, hasShortage ? qty : 0, hasShortage);
        calculateTotals();
    })
    .catch(() => {
        bomBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Unable to load BOM from database</td></tr>';
        document.getElementById('raw_material_cost').value = '0.00';
        updateFinishedGoodsPanel(0, qty, true);
        calculateTotals();
    });
}

function updateFinishedGoodsPanel(finishedQty, unfinishedQty, hasShortage) {
    bomHasShortage = hasShortage;
    document.getElementById('finishedGoodsQty').textContent = (parseFloat(finishedQty) || 0).toFixed(2);
    document.getElementById('unfinishedGoodsQty').textContent = (parseFloat(unfinishedQty) || 0).toFixed(2);
    document.getElementById('shortageNotice').style.display = hasShortage ? 'inline' : 'none';
}

function calculateTotals() {
    const getNumericValue = (id) => {
        const el = document.getElementById(id);
        if (!el) return 0;
        return parseFloat(el.value) || 0;
    };

    const materialCost = getNumericValue('raw_material_cost');
    const laborCost = getNumericValue('labor_cost');
    const electricityCost = getNumericValue('electricity_cost');
    const machineCost = getNumericValue('machine_cost');
    const otherCost = getNumericValue('other_cost');
    const overheadCost = getNumericValue('overhead_cost');
    const weldingCost = getNumericValue('welding_cost');
    
    const total = materialCost + laborCost + electricityCost + machineCost + otherCost + overheadCost + weldingCost;
    const display = document.getElementById('totalCostDisplay');
    if (display) display.value = total.toFixed(2);
}

function filterBins() {
    const warehouseId = document.getElementById('fg_warehouse_id').value;
    const binSelect = document.getElementById('fg_bin_id');
    binSelect.innerHTML = '<option value="">Select Bin</option>';
    
    if (warehouseId) {
        const filteredBins = allBins.filter(bin => bin.warehouse_id == warehouseId);
        filteredBins.forEach(bin => {
            const option = document.createElement('option');
            option.value = bin.id;
            option.text = bin.code;
            binSelect.appendChild(option);
        });
    }
}

function addAttachment() {
    const container = document.getElementById('attachments-container');
    const row = document.createElement('div');
    row.className = 'attachment-row d-flex align-items-center gap-2 mb-2';
    row.innerHTML = `
        <input type="text" class="form-control" name="attachment_labels[]" placeholder="Label / description (e.g. Design File)" style="max-width:220px">
        <input type="file" class="form-control" name="attachments[]">
        <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0" onclick="removeAttachment(this)" title="Remove"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(row);
}

function removeAttachment(btn) {
    const rows = document.querySelectorAll('.attachment-row');
    if (rows.length > 1) {
        btn.closest('.attachment-row').remove();
    } else {
        btn.closest('.attachment-row').querySelectorAll('input').forEach(i => i.value = '');
    }
}

function submitWithStatus(status) {
    document.getElementById('form_status').value = status;
    document.getElementById('addProductionForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('addProductionForm').addEventListener('submit', function(e) {
        if (bomHasShortage) {
            e.preventDefault();
            alert('Product shortage found. Please fix BOM/material stock before creating production order.');
        }
    });

    calculateTotals();
    filterBins();
    fetchProductBOM(); // Initial load for BOM if product is pre-selected
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
