<?php
/**
 * Add GSRN / Stock Entry
 * Screen for recording Goods Received Note and Stock Entries
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'GSRN / Stock Entry - MJR Group ERP';
$selected_company_id = active_company_id();
if (!$selected_company_id) {
    set_flash('Please select a company to record GSRN.', 'warning');
    redirect(url('index.php'));
}

// Get suppliers for dropdown (strictly for this company)
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// Get companies
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$selected_company_id]);

// Get warehouses (from locations table)
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// Get categories
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// Get inventory items for lookup
$inventory_items = db_fetch_all("SELECT id, code, name, cost_price, unit_of_measure FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// Get managers for approval selection
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$selected_company_id]);

// Get current user's assigned manager
$current_user = current_user();
$assigned_manager_id = $current_user['manager_id'] ?? null;

// Handle PO pre-load if po_id is provided
$preloaded_po = null;
$preloaded_lines = [];
$po_id = get_param('po_id');
if ($po_id) {
    $preloaded_po = db_fetch("
        SELECT po.*, s.name as supplier_name, s.id as supplier_id
        FROM purchase_orders po
        JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ? AND po.company_id = ? AND po.status IN ('approved', 'sent', 'confirmed')
    ", [$po_id, $selected_company_id]);

    if ($preloaded_po) {
        $preloaded_lines = db_fetch_all("
            SELECT pol.*, i.code, i.name, i.unit_of_measure, i.cost_price
            FROM purchase_order_lines pol
            JOIN inventory_items i ON pol.item_id = i.id
            WHERE pol.po_id = ?
        ", [$po_id]);
    }
}

// Handle POST request
if (is_post()) {
    $errors = [];
    $item_errors = [];
    $action = post('action');

    // Get form data for validation
    $gsrn_date = post('gsrn_date');
    $transaction_type = post('transaction_type');
    $company_id = post('company_id');
    $warehouse_id = post('warehouse_id');
    $manager_id = post('manager_id');
    $item_ids = post('item_id', []);

    // Validation
    if (empty($gsrn_date)) $errors['gsrn_date'] = err_required();
    if (empty($transaction_type)) $errors['transaction_type'] = err_required();
    if (empty($company_id)) $errors['company_id'] = err_required();
    if (empty($warehouse_id)) $errors['warehouse_id'] = err_required();
    if (empty($manager_id)) $errors['manager_id'] = err_required();

    $has_item = false;
    foreach ($item_ids as $id) {
        if (!empty($id)) {
            $has_item = true;
            break;
        }
    }
    if (!$has_item) $errors['items'] = err_required();

    if (!empty($errors)) {
        $error = 'Please fix the validation errors.';
    } else {
        // Begin transaction with retry logic for connection stability
        $max_attempts = 5;
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            try {
                db_begin_transaction();

                $user_id = current_user_id();
                $status = ($action === 'submit_approval') ? 'pending_approval' : 'draft';

                // 1. Generate GSRN Number (e.g., GRN-00001)
                $last_gsrn = db_fetch("SELECT gsrn_number FROM gsrn_headers ORDER BY id DESC LIMIT 1");
                $next_number = 1;
                if ($last_gsrn) {
                    $last_num = (int) str_replace('GRN-', '', $last_gsrn['gsrn_number']);
                    $next_number = $last_num + 1;
                }
                $gsrn_number = 'GRN-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);

                // 1.5 Ensure Production Columns Exist (Schema Guard)
                require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';
                if (!inventory_table_column_exists('gsrn_headers', 'produced_item_id')) {
                    db_query("ALTER TABLE gsrn_headers 
                              ADD COLUMN produced_item_id INT NULL, 
                              ADD COLUMN production_qty DECIMAL(15,4) NULL, 
                              ADD COLUMN production_cost DECIMAL(15,4) NULL, 
                              ADD COLUMN production_date DATE NULL,
                              ADD COLUMN production_order_ref VARCHAR(100) NULL");
                }

                $header_data = [
                    $gsrn_number,
                    to_db_date(post('gsrn_date')),
                    post('transaction_type'),
                    post('po_id') ?: null,
                    post('supplier_id') ?: null,
                    post('company_id'),
                    post('warehouse_id'),
                    post('category_id') ?: null,
                    post('currency', 'INR'),
                    post('exchange_rate', 1.0),
                    post('invoice_value', 0),
                    post('freight_cost', 0),
                    post('import_duty', 0),
                    post('insurance', 0),
                    post('handling_charges', 0),
                    post('other_sundry_costs', 0),
                    post('adjustment_type', 'None'),
                    post('adjustment_amount', 0),
                    post('adjustment_reason'),
                    post('final_landed_cost', 0),
                    post('internal_notes'),
                    post('warehouse_remarks'),
                    $status,
                    $user_id,
                    post('manager_id'),
                    // New Production Fields
                    post('produced_item_id') ?: null,
                    post('production_qty', 0),
                    post('production_cost', 0),
                    to_db_date(post('production_date')),
                    post('production_order_ref')
                ];

                $gsrn_id = db_insert("
                    INSERT INTO gsrn_headers (
                        gsrn_number, gsrn_date, transaction_type, po_id, supplier_id, company_id, 
                        warehouse_id, category_id, currency, exchange_rate, invoice_value, 
                        freight_cost, import_duty, insurance, handling_charges, 
                        other_sundry_costs, adjustment_type, adjustment_amount, 
                        adjustment_reason, final_landed_cost, internal_notes, 
                        warehouse_remarks, status, created_by, manager_id,
                        produced_item_id, production_qty, production_cost, production_date, production_order_ref
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", $header_data);

                if (!$gsrn_id) {
                    throw new Exception("Failed to retrieve generated GSRN ID. Please try again.");
                }

                // 3. Insert Items
                $item_ids = post('item_id', []);
                $quantities = post('quantity', []);
                $bin_locations = post('bin_location', []);
                $uoms = post('uom', []);
                $unit_costs = post('unit_cost', []);
                $landed_unit_costs = post('landed_unit_cost', []); // New field for distributed cost
                $total_values = post('total_value', []);
                $batch_serials = post('batch_serial', []);

                for ($i = 0; $i < count($item_ids); $i++) {
                    if (empty($item_ids[$i]))
                        continue;

                    $final_unit_cost = isset($landed_unit_costs[$i]) ? $landed_unit_costs[$i] : $unit_costs[$i];

                    db_query("
                        INSERT INTO gsrn_items (
                            gsrn_id, item_id, bin_location, quantity, uom, unit_cost, landed_unit_cost, total_value, batch_serial
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $gsrn_id,
                        $item_ids[$i],
                        $bin_locations[$i],
                        $quantities[$i],
                        $uoms[$i],
                        $unit_costs[$i],
                        $final_unit_cost,
                        $total_values[$i],
                        $batch_serials[$i]
                    ]);

                    // Update Item Cost Price if approved (Note: approval happens in another step usually, but we set it up here)
                    // If the user wants weighted average cost, we should update the item's cost_price once approved.
                }


                // 3.5 Handle File Uploads
                if (!empty($_FILES['gsrn_files']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../../public/uploads/gsrn/';
                    if (!is_dir($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    foreach ($_FILES['gsrn_files']['tmp_name'] as $key => $tmp_name) {
                        $file_name = $_FILES['gsrn_files']['name'][$key];
                        $file_size = $_FILES['gsrn_files']['size'][$key];
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                        if (in_array($file_ext, $allowed) && $file_size <= 5242880) {
                            $new_name = 'gsrn_' . $gsrn_id . '_' . uniqid() . '.' . $file_ext;
                            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                                db_query("INSERT INTO gsrn_files (gsrn_id, file_path, file_name) VALUES (?, ?, ?)", [
                                    $gsrn_id,
                                    'uploads/gsrn/' . $new_name,
                                    $file_name
                                ]);
                            }
                        }
                    }
                }

                // 4. Log History
                db_query("INSERT INTO gsrn_history (gsrn_id, status, notes, changed_by) VALUES (?, ?, ?, ?)", [
                    $gsrn_id,
                    $status,
                    ($status === 'draft' ? 'Saved as draft' : 'Submitted for approval'),
                    $user_id
                ]);


                db_commit();
                set_flash("GSRN $gsrn_number successfully " . ($status === 'draft' ? "saved as draft." : "submitted for approval."), "success");
                redirect("index.php");
                break;

            } catch (Exception $e) {
                db_rollback();

                $error_msg = strtolower($e->getMessage());
                $is_retryable = (
                    strpos($error_msg, 'gone away') !== false ||
                    strpos($error_msg, 'lost connection') !== false ||
                    strpos($error_msg, 'duplicate') !== false ||
                    strpos($error_msg, 'child row') !== false
                );

                if ($attempt < $max_attempts - 1 && $is_retryable) {
                    usleep(50000);
                    continue;
                }

                log_error("GSRN creation fail: " . $e->getMessage());
                $error = sanitize_db_error($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<style>
    .gsrn-section-title {
        border-left: 4px solid #0dcaf0;
        padding-left: 15px;
        margin-bottom: 20px;
        color: #fff;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
    }

    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        margin-bottom: 25px;
        transition: all 0.3s ease;
    }

    .premium-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
    }

    .premium-card .card-header {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        font-weight: 600;
        color: #0dcaf0;
        padding: 18px 25px;
        border-radius: 12px 12px 0 0;
    }

    .premium-card .card-body {
        padding: 25px;
    }

    .form-label {
        color: #a2a3b7;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .form-control,
    .form-select {
        background-color: #1a1a27;
        border: 1px solid #323248;
        color: #fff;
        border-radius: 8px;
        padding: 10px 15px;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: #1a1a27;
        border-color: #0dcaf0;
        color: #fff;
        box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
    }

    .table-premium {
        color: #fff;
    }

    .table-premium thead th {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 2px solid #323248;
        color: #a2a3b7;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        padding: 15px;
    }

    .table-premium tbody td {
        border-bottom: 1px solid #323248;
        padding: 12px 15px;
        vertical-align: middle;
    }

    .btn-premium-primary {
        background: linear-gradient(45deg, #0dcaf0, #0aa2c0);
        border: none;
        padding: 10px 25px;
        font-weight: 600;
        border-radius: 8px;
        color: #000;
        box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3);
    }

    .btn-premium-secondary {
        background: #323248;
        border: none;
        padding: 10px 25px;
        font-weight: 600;
        border-radius: 8px;
        color: #fff;
    }

    .gsrn-number-badge {
        background: rgba(13, 202, 240, 0.1);
        color: #0dcaf0;
        padding: 5px 15px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 0.9rem;
    }

    .costing-row {
        background: rgba(255, 255, 255, 0.02);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 10px;
    }

    .landed-cost-display {
        background: linear-gradient(45deg, #1e1e2d, #161621);
        border: 1px solid #0d6efd;
        padding: 20px;
        border-radius: 12px;
        text-align: right;
    }

    .landed-cost-value {
        font-size: 2rem;
        font-weight: 800;
        color: #0dcaf0;
    }

    .workflow-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }

    .dot-draft {
        background: #6c757d;
    }

    .dot-pending {
        background: #ffc107;
    }

    .dot-approved {
        background: #198754;
    }
</style>

<div class="container-fluid py-4 h-100 overflow-auto">
    <!-- Header Page Actions -->
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="text-white mb-0">GSRN / STOCK ENTRY SCREEN</h2>
            <p class="text-muted mb-0">Record and manage Goods Received Notes</p>
        </div>
        <div class="col-auto">
            <div class="gsrn-number-badge">
                GSRN: Auto-Generated (Next: GRN-00001)
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form id="gsrnForm" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="po_id" id="hidden_po_id" value="<?= $preloaded_po ? $preloaded_po['id'] : '' ?>">
        <div class="row">
            <!-- Left Column: Header & Items -->
            <div class="col-lg-8">
                <!-- 1. GSRN HEADER -->
                <div class="card premium-card">
                    <div class="card-header">
                        <i class="fas fa-file-alt me-2"></i> 1. GSRN HEADER (Document Information)
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">GSRN Date *</label>
                                <input type="text" name="gsrn_date" class="form-control <?= isset($errors['gsrn_date']) ? 'is-invalid' : '' ?>" value="<?= escape_html(post('gsrn_date', date('d-m-Y'))) ?>" placeholder="DD-MM-YYYY" required>
                                <?php if (isset($errors['gsrn_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['gsrn_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transaction Type *</label>
                                <select name="transaction_type" id="transaction_type" class="form-select <?= isset($errors['transaction_type']) ? 'is-invalid' : '' ?>" onchange="toggleManufacturing(this.value)">
                                    <option value="Purchase" <?= post('transaction_type', 'Purchase') === 'Purchase' ? 'selected' : '' ?>>Purchase / Procurement</option>
                                    <option value="Production" <?= (post('transaction_type') === 'Production') ? 'selected' : '' ?> <?= ($preloaded_po ? 'disabled' : '') ?>>Production / Manufacturing</option>
                                </select>
                                <?php if (isset($errors['transaction_type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['transaction_type'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Order Reference</label>
                                <div class="input-group">
                                    <input type="text" id="po_search" class="form-control"
                                        placeholder="Search Approved POs..."
                                        value="<?= $preloaded_po ? $preloaded_po['po_number'] : '' ?>">
                                    <button class="btn btn-outline-info" type="button" onclick="loadPOItems()">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select name="supplier_id" id="supplier_id" class="form-select">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= ($preloaded_po && $preloaded_po['supplier_id'] == $s['id']) ? 'selected' : '' ?>>
                                            <?= escape_html($s['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Company *</label>
                                <div class="input-group has-validation">
                                    <?php if (!empty($_SESSION['company_id'])): ?>
                                        <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                        <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                    <?php else: ?>
                                        <select name="company_id" class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= post('company_id') == $c['id'] ? 'selected' : '' ?>><?= escape_html($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <a href="../../companies/create.php" class="btn btn-outline-info" title="Manage Companies">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <?php if (isset($errors['company_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['company_id'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Warehouse / Store *</label>
                                <select name="warehouse_id" id="warehouse_id" class="form-select <?= isset($errors['warehouse_id']) ? 'is-invalid' : '' ?>" required>
                                    <option value="">Select Target Warehouse</option>
                                    <?php foreach ($warehouses as $w): ?>
                                        <option value="<?= $w['id'] ?>" <?= (post('warehouse_id') ?: ($preloaded_po['warehouse_id'] ?? '')) == $w['id'] ? 'selected' : '' ?>>
                                            <?= escape_html($w['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['warehouse_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['warehouse_id'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stock Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape_html($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency</label>
                                <select name="currency" id="currency" class="form-select">
                                    <option value="FJD" selected>FJD / $</option>
                                    <option value="INR">INR / ₹</option>
                                    <option value="USD">USD / $</option>
                                    <option value="EUR">EUR / €</option>
                                    <option value="GBP">GBP / £</option>

                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Exchange Rate</label>
                                <input type="number" name="exchange_rate" id="exchange_rate" class="form-control"
                                    value="1.00" step="0.000001">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. PURCHASE ORDER SELECTION PANEL (Quick View) -->
                <div id="po_panel" class="card premium-card" style="display:none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-shopping-cart me-2"></i> 2. PURCHASE ORDER SELECTION PANEL</div>
                        <span id="po_badge" class="badge bg-success">Approved</span>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="text-muted small">PO Number</div>
                                <div id="po_view_number" class="fw-bold text-white">PO-00001</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small">Supplier</div>
                                <div id="po_view_supplier" class="text-white">Steel Suppliers Co</div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-muted small">PO Date</div>
                                <div id="po_view_date" class="text-white">12-Mar-2026</div>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="text-muted small">Total Value</div>
                                <div id="po_view_total" class="fw-bold text-info fs-5">$10,000.00</div>
                            </div>
                        </div>
                        <hr class="my-3 opacity-10">
                        <button type="button" class="btn btn-sm btn-info text-dark fw-bold" onclick="importPOLines()">
                            <i class="fas fa-file-import me-1"></i> LOAD PO ITEMS INTO TABLE
                        </button>
                    </div>
                </div>

                <!-- 3. STOCK ENTRY ITEM TABLE -->
                <div class="card premium-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-list me-2"></i> 3. STOCK ENTRY ITEM TABLE</div>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="addRow()">
                            <i class="fas fa-plus me-1"></i> Add Manual Entry
                        </button>
                    </div>
                    <div class="card-body px-0 pt-0">
                        <?php if (isset($errors['items'])): ?>
                            <div class="alert alert-danger mx-3 mt-3"><?= $errors['items'] ?></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-premium mb-0" id="gsrnItemsTable">
                                <thead>
                                    <tr>
                                        <th style="width: 250px;">Item / Product</th>
                                        <th style="width: 150px;">Bin / Store</th>
                                        <th style="width: 120px;">Qty Received</th>
                                        <th style="width: 100px;">Unit</th>
                                        <th style="width: 120px;">Unit Cost</th>
                                        <th style="width: 150px;">Total Value</th>
                                        <th style="width: 150px;">Batch / Serial</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemBody">
                                    <!-- Rows added dynamically -->
                                </tbody>
                            </table>
                            <datalist id="gsrnBinOptions"></datalist>
                        </div>
                        <div id="emptyMessage" class="text-center py-5 text-muted">
                            <i class="fas fa-boxes fa-3x mb-3 opacity-25"></i>
                            <p>No items added yet. Load items from PO or add manually.</p>
                        </div>
                    </div>
                </div>

                <!-- 5. MANUFACTURING SECTION (Conditional) -->
                <div id="manufacturing_section" class="card premium-card" style="display:none;">
                    <div class="card-header">
                        <i class="fas fa-industry me-2"></i> 5. MANUFACTURING STOCK ENTRY SECTION
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Production Order Reference</label>
                                <input type="text" name="production_order_ref" class="form-control"
                                    placeholder="Search WO#...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product Produced</label>
                                <select name="produced_item_id" class="form-select">
                                    <option value="">Select Finished Good</option>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <option value="<?= $item['id'] ?>"><?= escape_html($item['code']) ?> -
                                            <?= escape_html($item['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Production Quantity</label>
                                <input type="number" name="production_qty" class="form-control" value="0" step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Production Cost</label>
                                <input type="number" name="production_cost" class="form-control" value="0.00"
                                    step="0.01">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Completion Date</label>
                                <input type="text" name="production_date" class="form-control"
                                    value="<?= format_date(date('Y-m-d')) ?>" placeholder="DD-MM-YYYY">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Costing & Sidebar -->
            <div class="col-lg-4">
                <!-- 4. COSTING TEMPLATE SECTION -->
                <div class="card premium-card">
                    <div class="card-header">
                        <i class="fas fa-calculator me-2"></i> 4. COSTING TEMPLATE SECTION
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <label class="form-label">Invoice Value (FOB/Ex-Works)</label>
                            <input type="number" name="invoice_value" id="invoice_value"
                                class="form-control form-control-lg" value="0.00" step="0.01"
                                onchange="calculateLandedCost()">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Freight Cost</label>
                                <input type="number" name="freight_cost" class="form-control costing-input" value="0.00"
                                    step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Import Duty</label>
                                <input type="number" name="import_duty" class="form-control costing-input" value="0.00"
                                    step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Insurance</label>
                                <input type="number" name="insurance" class="form-control costing-input" value="0.00"
                                    step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Handling Charges</label>
                                <input type="number" name="handling_charges" class="form-control costing-input"
                                    value="0.00" step="0.01" onchange="calculateLandedCost()">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label">Other Sundry Costs</label>
                            <input type="number" name="other_sundry_costs" class="form-control costing-input"
                                value="0.00" step="0.01" onchange="calculateLandedCost()">
                        </div>

                        <!-- 8. COST ADJUSTMENT FEATURE -->
                        <div class="mt-4 pt-3 border-top border-secondary">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Cost Adjustment</label>
                                <select name="adjustment_type" class="form-select form-select-sm w-50">
                                    <option value="None">No Adjustment</option>
                                    <option value="Add">Add (+) </option>
                                    <option value="Subtract">Subtract (-)</option>
                                </select>
                            </div>
                            <input type="number" name="adjustment_amount" class="form-control mb-2"
                                placeholder="Adjustment Amount" value="0.00" step="0.01"
                                onchange="calculateLandedCost()">
                            <input type="text" name="adjustment_reason" class="form-control form-control-sm"
                                placeholder="Reason for adjustment...">
                        </div>

                        <div class="landed_cost_display mt-4">
                            <div class="text-muted small">FINAL LANDED COST</div>
                            <div class="landed-cost-value" id="final_landed_cost_display">₹ 0.00</div>
                            <input type="hidden" name="final_landed_cost" id="final_landed_cost" value="0.00">
                        </div>
                    </div>
                </div>

                <!-- 7. ATTACHMENTS & NOTES -->
                <div class="card premium-card">
                    <div class="card-header">
                        <i class="fas fa-paperclip me-2"></i> 7. ATTACHMENTS & NOTES
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Internal Notes</label>
                            <textarea name="internal_notes" class="form-control" rows="2"
                                placeholder="Private internal comments..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warehouse Remarks</label>
                            <textarea name="warehouse_remarks" class="form-control" rows="2"
                                placeholder="Storage or handling remarks..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Documents / Invoice</label>
                            <input type="file" id="gsrn_files" name="gsrn_files[]" class="form-control" multiple>
                            <div class="form-text text-muted small">PDF, JPG, PNG allowed. Max 5MB per file.</div>
                            <div id="gsrnFileList"
                                class="mt-2 list-group list-group-flush border border-secondary rounded"
                                style="display:none;"></div>
                        </div>
                    </div>
                </div>

                <!-- 9. WORKFLOW & APPROVAL PANEL -->
                <div class="card premium-card">
                    <div class="card-header">
                        <i class="fas fa-check-double me-2"></i> 9. WORKFLOW & APPROVAL
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted">Current Status:</span>
                            <span class="fw-bold text-info"><span class="workflow-dot dot-draft"></span> Draft</span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Send for Approval to *</label>
                            <select name="manager_id" class="form-select form-select-sm <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= (post('manager_id') == $m['id'] || ($assigned_manager_id == $m['id'] && !post('manager_id'))) ? 'selected' : '' ?>>
                                        <?= escape_html($m['full_name'] ?: $m['username']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['manager_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['manager_id'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Created By:</span>
                            <span class="text-white"><?= escape_html($_SESSION['username']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Created Date:</span>
                            <span class="text-white"><?= date('d-M-Y') ?></span>
                        </div>
                        <hr class="my-3 opacity-10">
                        <p class="text-muted small"><i class="fas fa-info-circle me-1"></i> Stock will be added to
                            inventory only after <strong>Approved</strong> status.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 10. ACTION BUTTONS (Floating Bar) -->
        <div
            class="premium-footer-bar bg-dark position-sticky bottom-0 start-0 w-100 p-3 mt-4 border-top border-secondary shadow-lg">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col">
                        <a href="index.php" class="btn btn-premium-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                    <div class="col-auto">
                        <button type="submit" name="action" value="save_draft" class="btn btn-premium-secondary me-2">
                            <i class="fas fa-save me-2 text-warning"></i>Save Draft
                        </button>
                        <button type="submit" name="action" value="submit_approval" class="btn btn-premium-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Wireframe wire-up Javascript -->
<script>
    const inventoryItems = <?= json_encode($inventory_items) ?>;
    let itemCount = 0;
    let activeWarehouseBins = [];

    async function loadWarehouseBins() {
        const warehouseSelect = document.getElementById('warehouse_id');
        const binOptions = document.getElementById('gsrnBinOptions');
        if (!warehouseSelect || !binOptions) return;

        const warehouseId = warehouseSelect.value;
        binOptions.innerHTML = '';
        activeWarehouseBins = [];
        if (!warehouseId) return;

        try {
            const response = await fetch(`../ajax_get_bins.php?location_id=${encodeURIComponent(warehouseId)}`);
            const bins = await response.json();
            if (!Array.isArray(bins)) return;

            activeWarehouseBins = bins;
            bins.forEach(b => {
                const option = document.createElement('option');
                option.value = b.bin_location || '';
                binOptions.appendChild(option);
            });
        } catch (error) {
            console.error('Failed to load warehouse bins:', error);
        }
    }

    function openCreateBinLocation() {
        const warehouseSelect = document.getElementById('warehouse_id');
        const warehouseId = warehouseSelect ? warehouseSelect.value : '';
        if (!warehouseId) {
            alert('Please select Warehouse / Store first.');
            return;
        }
        window.open(`../modules/warehouses/index.php?location_id=${encodeURIComponent(warehouseId)}`, '_blank');
    }

    function addRow(data = null) {
        document.getElementById('emptyMessage').style.display = 'none';
        itemCount++;
        const tbody = document.getElementById('itemBody');
        const row = document.createElement('tr');
        row.id = `item-row-${itemCount}`;

        row.innerHTML = `
            <td>
                <select name="item_id[]" class="form-select form-select-sm item-select" onchange="updateRowInfo(this, ${itemCount})">
                    <option value="">Select Item / Product</option>
                    ${inventoryItems.map(item => `
                        <option value="${item.id}" data-code="${item.code}" data-cost="${item.cost_price}" data-uom="${item.unit_of_measure}">
                            ${item.code} - ${item.name}
                        </option>
                    `).join('')}
                </select>
                <div class="text-muted small mt-1 item-code-display"></div>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="text" name="bin_location[]" class="form-control" list="gsrnBinOptions" placeholder="Bin Location">
                    <button class="btn btn-outline-secondary" type="button" title="Create New Bin" onclick="openCreateBinLocation()"><i class="fas fa-plus-square"></i></button>
                </div>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control form-control-sm qty-input" value="1" step="0.01" onchange="calculateRowTotal(${itemCount})">
            </td>
            <td>
                <input type="text" name="uom[]" class="form-control form-control-sm uom-display" readonly>
            </td>
            <td>
                <input type="number" name="unit_cost[]" class="form-control form-control-sm cost-input" value="0.00" step="0.01" onchange="calculateRowTotal(${itemCount})">
            </td>
            <td>
                <input type="text" name="total_value[]" class="form-control form-control-sm total-display" value="0.00" readonly>
            </td>
            <td>
                <input type="text" name="batch_serial[]" class="form-control form-control-sm" placeholder="Batch / Serial #">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(${itemCount})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);

        if (data) {
            const select = row.querySelector('.item-select');
            select.value = data.item_id;
            row.querySelector('.qty-input').value = data.quantity;
            row.querySelector('.cost-input').value = data.unit_cost;
            updateRowInfo(select, itemCount);
        }
    }

    function removeRow(id) {
        document.getElementById(`item-row-${id}`).remove();
        if (document.querySelectorAll('#itemBody tr').length === 0) {
            document.getElementById('emptyMessage').style.display = 'block';
        }
        calculateLandedCost();
    }

    function updateRowInfo(select, id) {
        const row = document.getElementById(`item-row-${id}`);
        const opt = select.options[select.selectedIndex];

        row.querySelector('.item-code-display').textContent = opt.getAttribute('data-code') || '';
        row.querySelector('.uom-display').value = opt.getAttribute('data-uom') || '';
        row.querySelector('.cost-input').value = (opt.getAttribute('data-cost') || 0);

        calculateRowTotal(id);
    }

    function calculateRowTotal(id) {
        const row = document.getElementById(`item-row-${id}`);
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        const total = qty * cost;

        row.querySelector('.total-display').value = total.toFixed(2);
        calculateLandedCost();
    }

    function calculateLandedCost() {
        let invoiceValue = 0;
        const items = [];

        document.querySelectorAll('#itemBody tr').forEach(row => {
            const id = row.id.replace('item-row-', '');
            const total = parseFloat(row.querySelector('.total-display').value) || 0;
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            if (total > 0) {
                invoiceValue += total;
                items.push({ id, total, qty });
            }
        });

        document.getElementById('invoice_value').value = invoiceValue.toFixed(2);

        let additionalCosts = 0;
        document.querySelectorAll('.costing-input').forEach(el => {
            additionalCosts += parseFloat(el.value) || 0;
        });

        let adjustment = 0;
        const adjType = document.getElementsByName('adjustment_type')[0].value;
        const adjAmount = parseFloat(document.getElementsByName('adjustment_amount')[0].value) || 0;

        if (adjType === 'Add') adjustment = adjAmount;
        else if (adjType === 'Subtract') adjustment = -adjAmount;

        const totalLandedCost = invoiceValue + additionalCosts + adjustment;
        const currency = document.getElementById('currency').value;
        const currencySymbol = (currency === 'USD' || currency === 'FJD') ? '$' : (currency === 'INR' ? '₹' : '€');

        document.getElementById('final_landed_cost_display').textContent = currencySymbol + ' ' + totalLandedCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('final_landed_cost').value = totalLandedCost.toFixed(2);

        // Distribute Landed Cost to each product (Value-based distribution)
        if (invoiceValue > 0) {
            const extraPerUnitValue = (additionalCosts + adjustment) / invoiceValue;
            items.forEach(item => {
                const row = document.getElementById(`item-row-${item.id}`);
                const originalUnitCost = parseFloat(row.querySelector('.cost-input').value) || 0;
                const landedUnitCost = originalUnitCost * (1 + extraPerUnitValue);

                // Show distributed cost in a tooltip or small text if needed, for now we update a hidden field
                let landedInput = row.querySelector('.landed-unit-cost');
                if (!landedInput) {
                    landedInput = document.createElement('input');
                    landedInput.type = 'hidden';
                    landedInput.name = 'landed_unit_cost[]';
                    landedInput.className = 'landed-unit-cost';
                    row.appendChild(landedInput);

                    const displayDiv = document.createElement('div');
                    displayDiv.className = 'text-info small mt-1 landed-cost-info';
                    row.querySelector('td:nth-child(5)').appendChild(displayDiv);
                }
                landedInput.value = landedUnitCost.toFixed(4);
                row.querySelector('.landed-cost-info').textContent = 'Landed: ' + landedUnitCost.toFixed(2);
            });
        }
    }


    function toggleManufacturing(type) {
        const section = document.getElementById('manufacturing_section');
        section.style.display = (type === 'Production') ? 'block' : 'none';

        // Adjust headers
        if (type === 'Production') {
            document.querySelector('.card-header i.fa-file-alt').nextSibling.textContent = ' 1. GSRN HEADER (Production Entry)';
        } else {
            document.querySelector('.card-header i.fa-file-alt').nextSibling.textContent = ' 1. GSRN HEADER (Document Information)';
        }
    }

    // Initialize with preloaded data if available
    document.addEventListener('DOMContentLoaded', () => {
        const warehouseSelect = document.getElementById('warehouse_id');
        if (warehouseSelect) {
            warehouseSelect.addEventListener('change', loadWarehouseBins);
            loadWarehouseBins();
        }

        <?php if ($preloaded_po): ?>
            document.getElementById('po_panel').style.display = 'block';
            document.getElementById('po_view_number').textContent = '<?= $preloaded_po['po_number'] ?>';
            document.getElementById('po_view_supplier').textContent = '<?= escape_html($preloaded_po['supplier_name']) ?>';
            document.getElementById('po_view_date').textContent = '<?= format_date($preloaded_po['order_date']) ?>';
            document.getElementById('po_view_total').textContent = '<?= format_currency($preloaded_po['total_amount'], $preloaded_po['currency_code'] ?? 'USD') ?>';

            <?php foreach ($preloaded_lines as $line): ?>
                addRow({
                    item_id: '<?= $line['item_id'] ?>',
                    quantity: '<?= $line['quantity'] ?>',
                    unit_cost: '<?= $line['unit_price'] ?>'
                });
            <?php endforeach; ?>
            calculateLandedCost();
        <?php endif; ?>
    });

    let currentPOLines = [];

    async function loadPOItems() {
        const poNumber = document.getElementById('po_search').value;
        if (!poNumber) {
            alert('Please enter a PO Number to search');
            return;
        }

        try {
            const response = await fetch(`ajax_get_po.php?po_number=${encodeURIComponent(poNumber)}`);
            const data = await response.json();

            if (data.success) {
                document.getElementById('po_panel').style.display = 'block';
                document.getElementById('po_view_number').textContent = data.po.po_number;
                document.getElementById('po_view_supplier').textContent = data.po.supplier_name;
                document.getElementById('po_view_date').textContent = data.po.order_date;
                document.getElementById('po_view_total').textContent = '$' + parseFloat(data.po.total_amount).toFixed(2);

                // Set supplier and warehouse if matched
                if (data.po.supplier_id) document.getElementById('supplier_id').value = data.po.supplier_id;
                if (data.po.id) document.getElementById('hidden_po_id').value = data.po.id;

                currentPOLines = data.lines;

                // Scroll to panel
                document.getElementById('po_panel').scrollIntoView({ behavior: 'smooth' });
            } else {
                alert(data.message || 'PO not found');
            }
        } catch (error) {
            console.error('Error fetching PO:', error);
            alert('Error connecting to server');
        }
    }

    function importPOLines() {
        if (currentPOLines.length === 0) {
            // If we preloaded via PHP and haven't fetched via AJAX yet, we don't need to do anything here
            // because the PHP loop already called addRow()
            return;
        }

        // Clear table first? Maybe ask user. For now just append.
        currentPOLines.forEach(line => {
            addRow({
                item_id: line.item_id,
                quantity: line.quantity,
                unit_cost: line.unit_price
            });
        });

        calculateLandedCost();
        currentPOLines = []; // Reset after import
    }

    // Multi-file accumulation logic
    let selectedGSRNFiles = [];
    const gsrnFilesInput = document.getElementById('gsrn_files');
    const gsrnFileListDiv = document.getElementById('gsrnFileList');

    if (gsrnFilesInput) {
        gsrnFilesInput.addEventListener('change', function (e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                const exists = selectedGSRNFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                if (!exists) {
                    selectedGSRNFiles.push(file);
                }
            });
            renderGSRNFileList();
            updateGSRNInputFiles();
        });
    }

    function renderGSRNFileList() {
        if (!gsrnFileListDiv) return;
        if (selectedGSRNFiles.length === 0) {
            gsrnFileListDiv.style.display = 'none';
            return;
        }
        gsrnFileListDiv.style.display = 'block';
        gsrnFileListDiv.innerHTML = '';
        selectedGSRNFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center py-1';
            item.innerHTML = `
                <span class="text-white small text-truncate" style="max-width: 80%;">${file.name}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeGSRNFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            gsrnFileListDiv.appendChild(item);
        });
    }

    window.removeGSRNFile = function (index) {
        selectedGSRNFiles.splice(index, 1);
        renderGSRNFileList();
        updateGSRNInputFiles();
    };

    function updateGSRNInputFiles() {
        if (!gsrnFilesInput) return;
        const dataTransfer = new DataTransfer();
        selectedGSRNFiles.forEach(file => dataTransfer.items.add(file));
        gsrnFilesInput.files = dataTransfer.files;
    }
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
