<?php
/**
 * Edit GSRN / Stock Entry
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('GSRN not found.', 'error');
    redirect('index.php');
}

// Get GSRN Header
$gsrn = db_fetch("SELECT * FROM gsrn_headers WHERE id = ?", [$id]);
if (!$gsrn || !in_array($gsrn['status'], ['draft', 'rejected'])) {
    set_flash('Only drafts or rejected entries can be edited.', 'error');
    redirect('index.php');
}

// Get Items
$items = db_fetch_all("SELECT * FROM gsrn_items WHERE gsrn_id = ?", [$id]);

$page_title = 'Edit GSRN / Stock Entry - MJR Group ERP';
$selected_company_id = active_company_id(1);

// Get dependencies
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$selected_company_id]);
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$inventory_items = db_fetch_all("SELECT id, code, name, cost_price, unit_of_measure FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$selected_company_id]);

// Get existing files
$gsrn_files = db_fetch_all("SELECT * FROM gsrn_files WHERE gsrn_id = ?", [$id]);

$errors = [];
$item_errors = [];

$form_value = static function ($field, $default = '') {
    return is_post() ? post($field, $default) : $default;
};

$selected_manager_id = $form_value('manager_id', $gsrn['manager_id']);
$existing_items = $items;

// Handle POST request
if (is_post()) {
    $action = post('action');
    $status = ($action === 'submit_approval') ? 'pending_approval' : 'draft';

    $gsrn_date = trim((string) post('gsrn_date'));
    $transaction_type = trim((string) post('transaction_type'));
    $company_id = post('company_id');
    $warehouse_id = post('warehouse_id');
    $manager_id = post('manager_id');

    $item_ids = post('item_id', []);
    $quantities = post('quantity', []);
    $bin_locations = post('bin_location', []);
    $uoms = post('uom', []);
    $unit_costs = post('unit_cost', []);
    $total_values = post('total_value', []);
    $batch_serials = post('batch_serial', []);

    if ($gsrn_date === '') {
        $errors['gsrn_date'] = err_required();
    }
    if ($transaction_type === '') {
        $errors['transaction_type'] = err_required();
    }
    if (empty($company_id)) {
        $errors['company_id'] = err_required();
    }
    if (empty($warehouse_id)) {
        $errors['warehouse_id'] = err_required();
    }
    if (empty($manager_id)) {
        $errors['manager_id'] = err_required();
    }

    $existing_items = [];
    $has_valid_item = false;

    for ($i = 0; $i < count($item_ids); $i++) {
        $row_item_id = $item_ids[$i] ?? '';
        $row_quantity = $quantities[$i] ?? '';
        $row_uom = $uoms[$i] ?? '';
        $row_unit_cost = $unit_costs[$i] ?? '';
        $row_total_value = $total_values[$i] ?? '';
        $row_batch_serial = $batch_serials[$i] ?? '';
        $row_bin_location = $bin_locations[$i] ?? '';

        $row_has_data = $row_item_id !== ''
            || trim((string) $row_quantity) !== ''
            || trim((string) $row_unit_cost) !== ''
            || trim((string) $row_bin_location) !== ''
            || trim((string) $row_batch_serial) !== '';

        $row_errors = [];

        if ($row_has_data) {
            if (empty($row_item_id)) {
                $row_errors['item_id'] = err_required();
            }

            if (trim((string) $row_quantity) === '') {
                $row_errors['quantity'] = err_required();
            } elseif ((float) $row_quantity <= 0) {
                $row_errors['quantity'] = 'Quantity must be greater than 0.';
            }

            if (trim((string) $row_unit_cost) === '') {
                $row_errors['unit_cost'] = err_required();
            } elseif ((float) $row_unit_cost < 0) {
                $row_errors['unit_cost'] = 'Unit Cost cannot be negative.';
            }

            if (empty($row_errors)) {
                $has_valid_item = true;
            } else {
                $item_errors[$i] = $row_errors;
            }
        }

        $existing_items[] = [
            'item_id' => $row_item_id,
            'bin_location' => $row_bin_location,
            'quantity' => $row_quantity,
            'uom' => $row_uom,
            'unit_cost' => $row_unit_cost,
            'total_value' => $row_total_value,
            'batch_serial' => $row_batch_serial,
            'errors' => $row_errors,
        ];
    }

    if (!$has_valid_item) {
        $errors['items'] = err_required();
    }

    if (!empty($errors) || !empty($item_errors)) {
        $error = err_required();
    } else {
        $selected_manager_id = $manager_id;

        // Begin transaction with retry logic
        $max_attempts = 3;
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            try {
                db_begin_transaction();

                $user_id = current_user_id();

                // Update Header
                db_query("
                    UPDATE gsrn_headers SET 
                        gsrn_date = ?, transaction_type = ?, po_id = ?, supplier_id = ?, company_id = ?, 
                        warehouse_id = ?, category_id = ?, currency = ?, exchange_rate = ?, 
                        invoice_value = ?, freight_cost = ?, import_duty = ?, insurance = ?, 
                        handling_charges = ?, other_sundry_costs = ?, adjustment_type = ?, 
                        adjustment_amount = ?, adjustment_reason = ?, final_landed_cost = ?, 
                        internal_notes = ?, warehouse_remarks = ?, status = ?, manager_id = ?
                    WHERE id = ?
                ", [
                    to_db_date($gsrn_date),
                    $transaction_type,
                    post('po_id') ?: null,
                    post('supplier_id') ?: null,
                    $company_id,
                    $warehouse_id,
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
                    $manager_id,
                    $id
                ]);

                // Delete and Re-insert Items
                db_query("DELETE FROM gsrn_items WHERE gsrn_id = ?", [$id]);

                for ($i = 0; $i < count($item_ids); $i++) {
                    if (empty($item_ids[$i])) {
                        continue;
                    }

                    db_query("
                        INSERT INTO gsrn_items (
                            gsrn_id, item_id, bin_location, quantity, uom, unit_cost, total_value, batch_serial
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $id,
                        $item_ids[$i],
                        $bin_locations[$i],
                        $quantities[$i],
                        $uoms[$i],
                        $unit_costs[$i],
                        $total_values[$i],
                        $batch_serials[$i]
                    ]);
                }

                // Log History
                db_query("INSERT INTO gsrn_history (gsrn_id, status, notes, changed_by) VALUES (?, ?, ?, ?)", [
                    $id,
                    $status,
                    ($status === 'draft' ? 'GSRN updated' : 'GSRN updated and submitted for approval'),
                    $user_id
                ]);

                // Handle New File Uploads
                if (!empty($_FILES['gsrn_files']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../../public/uploads/gsrn/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    foreach ($_FILES['gsrn_files']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['gsrn_files']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['gsrn_files']['name'][$key];
                            $file_size = $_FILES['gsrn_files']['size'][$key];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed = ['pdf', 'jpg', 'jpeg', 'png'];

                            if (in_array($file_ext, $allowed) && $file_size <= 5242880) {
                                $new_name = 'gsrn_' . $id . '_' . uniqid() . '.' . $file_ext;
                                if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                                    db_query("INSERT INTO gsrn_files (gsrn_id, file_path, file_name) VALUES (?, ?, ?)", [
                                        $id,
                                        'uploads/gsrn/' . $new_name,
                                        $file_name
                                    ]);
                                }
                            }
                        }
                    }
                }

                db_commit();
                set_flash("GSRN " . $gsrn['gsrn_number'] . " successfully updated.", "success");
                redirect("index.php");
                break;

            } catch (Exception $e) {
                db_rollback();

                $error_msg = strtolower($e->getMessage());
                $is_retryable = (
                    strpos($error_msg, 'gone away') !== false ||
                    strpos($error_msg, 'lost connection') !== false ||
                    strpos($error_msg, 'deadlock') !== false
                );

                if ($attempt < $max_attempts - 1 && $is_retryable) {
                    usleep(100000);
                    continue;
                }

                $error = sanitize_db_error($e->getMessage());
                log_error("GSRN update fail: " . $e->getMessage());
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

    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield;
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

    .invalid-feedback {
        display: block;
        color: #ff8f8f;
        font-size: 0.8rem;
        margin-top: 6px;
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
        border: 1px solid #0dcaf0;
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
            <h2 class="text-white mb-0">EDIT GSRN / STOCK ENTRY</h2>
            <p class="text-muted mb-0">Modify Goods Received Note details</p>
        </div>
        <div class="col-auto">
            <div class="gsrn-number-badge">
                GSRN: <?= $gsrn['gsrn_number'] ?>
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
        <input type="hidden" name="po_id" id="hidden_po_id" value="<?= escape_html((string) $form_value('po_id', $gsrn['po_id'])) ?>">
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
                                <input type="text" name="gsrn_date" class="form-control <?= isset($errors['gsrn_date']) ? 'is-invalid' : '' ?>"
                                    value="<?= escape_html($form_value('gsrn_date', format_date($gsrn['gsrn_date']))) ?>" placeholder="DD-MM-YYYY" required>
                                <?php if (isset($errors['gsrn_date'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['gsrn_date']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Transaction Type *</label>
                                <select name="transaction_type" id="transaction_type" class="form-select <?= isset($errors['transaction_type']) ? 'is-invalid' : '' ?>"
                                    onchange="toggleManufacturing(this.value)">
                                    <option value="Purchase" <?= $form_value('transaction_type', $gsrn['transaction_type']) === 'Purchase' ? 'selected' : '' ?>>Purchase / Procurement</option>
                                    <option value="Production" <?= $form_value('transaction_type', $gsrn['transaction_type']) === 'Production' ? 'selected' : '' ?>>Production / Manufacturing</option>
                                </select>
                                <?php if (isset($errors['transaction_type'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['transaction_type']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Order Reference</label>
                                <div class="input-group">
                                    <input type="text" id="po_search" class="form-control"
                                        placeholder="Search Approved POs...">
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
                                        <option value="<?= $s['id'] ?>" <?= $form_value('supplier_id', $gsrn['supplier_id']) == $s['id'] ? 'selected' : '' ?>><?= escape_html($s['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Company *</label>
                                <div class="input-group">
                                    <?php if (!empty($_SESSION['company_id'])): ?>
                                        <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                        <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                    <?php else: ?>
                                        <select name="company_id" class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $c): ?>
                                                <option value="<?= $c['id'] ?>" <?= $form_value('company_id', $gsrn['company_id']) == $c['id'] ? 'selected' : '' ?>><?= escape_html($c['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <a href="../../companies/index.php" class="btn btn-outline-info"
                                        title="Manage Companies">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                                <?php if (isset($errors['company_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= escape_html($errors['company_id']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Warehouse / Store *</label>
                                <select name="warehouse_id" id="warehouse_id" class="form-select <?= isset($errors['warehouse_id']) ? 'is-invalid' : '' ?>" required>
                                    <option value="">Select Target Warehouse</option>
                                    <?php foreach ($warehouses as $w): ?>
                                        <option value="<?= $w['id'] ?>" <?= $form_value('warehouse_id', $gsrn['warehouse_id']) == $w['id'] ? 'selected' : '' ?>><?= escape_html($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['warehouse_id'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['warehouse_id']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stock Category</label>
                                <select name="category_id" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $form_value('category_id', $gsrn['category_id']) == $cat['id'] ? 'selected' : '' ?>><?= escape_html($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Currency</label>
                                <select name="currency" id="currency" class="form-select">
                                    <option value="FJD" <?= $gsrn['currency'] === 'FJD' ? 'selected' : '' ?>>FJD / $
                                    </option>
                                    <option value="INR" <?= $gsrn['currency'] === 'INR' ? 'selected' : '' ?>>INR / ₹
                                    </option>
                                    <option value="USD" <?= $gsrn['currency'] === 'USD' ? 'selected' : '' ?>>USD / $
                                    </option>
                                    <option value="EUR" <?= $gsrn['currency'] === 'EUR' ? 'selected' : '' ?>>EUR / €
                                    </option>
                                    <option value="GBP" <?= $gsrn['currency'] === 'GBP' ? 'selected' : '' ?>>GBP / £
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Exchange Rate</label>
                                <input type="number" name="exchange_rate" id="exchange_rate" class="form-control"
                                    value="<?= escape_html((string) $form_value('exchange_rate', $gsrn['exchange_rate'])) ?>" step="0.000001">
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
                        <div class="px-3 pt-3 <?= isset($errors['items']) ? '' : 'd-none' ?>" id="gsrnItemsErrorWrap">
                            <div class="alert alert-danger mb-0" id="gsrnItemsError"><?= isset($errors['items']) ? escape_html($errors['items']) : '' ?></div>
                        </div>
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
                        <div id="emptyMessage" class="text-center py-5 text-muted" style="display:none;">
                            <i class="fas fa-boxes fa-3x mb-3 opacity-25"></i>
                            <p>No items added yet. Load items from PO or add manually.</p>
                        </div>
                    </div>
                </div>

                <!-- 5. MANUFACTURING SECTION (Conditional) -->
                <div id="manufacturing_section" class="card premium-card" style="display:none;">
                    <!-- Placeholder logic similar to add_gsrn -->
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
                                class="form-control form-control-lg" value="<?= escape_html((string) $form_value('invoice_value', $gsrn['invoice_value'])) ?>" step="0.01"
                                onchange="calculateLandedCost()">
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label">Freight Cost</label>
                                <input type="number" name="freight_cost" class="form-control costing-input"
                                    value="<?= escape_html((string) $form_value('freight_cost', $gsrn['freight_cost'])) ?>" step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Import Duty</label>
                                <input type="number" name="import_duty" class="form-control costing-input"
                                    value="<?= escape_html((string) $form_value('import_duty', $gsrn['import_duty'])) ?>" step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Insurance</label>
                                <input type="number" name="insurance" class="form-control costing-input"
                                    value="<?= escape_html((string) $form_value('insurance', $gsrn['insurance'])) ?>" step="0.01" onchange="calculateLandedCost()">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Handling Charges</label>
                                <input type="number" name="handling_charges" class="form-control costing-input"
                                    value="<?= escape_html((string) $form_value('handling_charges', $gsrn['handling_charges'])) ?>" step="0.01"
                                    onchange="calculateLandedCost()">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label">Other Sundry Costs</label>
                            <input type="number" name="other_sundry_costs" class="form-control costing-input"
                                value="<?= escape_html((string) $form_value('other_sundry_costs', $gsrn['other_sundry_costs'])) ?>" step="0.01" onchange="calculateLandedCost()">
                        </div>

                        <!-- 8. COST ADJUSTMENT FEATURE -->
                        <div class="mt-4 pt-3 border-top border-secondary">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Cost Adjustment</label>
                                <select name="adjustment_type" class="form-select form-select-sm w-50">
                                    <option value="None" <?= $gsrn['adjustment_type'] === 'None' ? 'selected' : '' ?>>No
                                        Adjustment</option>
                                    <option value="Add" <?= $gsrn['adjustment_type'] === 'Add' ? 'selected' : '' ?>>Add (+)
                                    </option>
                                    <option value="Subtract" <?= $gsrn['adjustment_type'] === 'Subtract' ? 'selected' : '' ?>>Subtract (-)</option>
                                </select>
                            </div>
                            <input type="number" name="adjustment_amount" class="form-control mb-2"
                                placeholder="Adjustment Amount" value="<?= escape_html((string) $form_value('adjustment_amount', $gsrn['adjustment_amount'])) ?>" step="0.01"
                                onchange="calculateLandedCost()">
                            <input type="text" name="adjustment_reason" class="form-control form-control-sm"
                                placeholder="Reason for adjustment..."
                                value="<?= escape_html($form_value('adjustment_reason', $gsrn['adjustment_reason'])) ?>">
                        </div>

                        <div class="landed_cost_display mt-4">
                            <div class="text-muted small">FINAL LANDED COST</div>
                            <div class="landed-cost-value" id="final_landed_cost_display">₹
                                <?= number_format($gsrn['final_landed_cost'], 2) ?></div>
                            <input type="hidden" name="final_landed_cost" id="final_landed_cost"
                                value="<?= $gsrn['final_landed_cost'] ?>">
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
                                placeholder="Private internal comments..."><?= escape_html($form_value('internal_notes', $gsrn['internal_notes'])) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Warehouse Remarks</label>
                            <textarea name="warehouse_remarks" class="form-control" rows="2"
                                placeholder="Storage or handling remarks..."><?= escape_html($form_value('warehouse_remarks', $gsrn['warehouse_remarks'])) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload New Documents / Invoice</label>
                            <input type="file" id="edit_gsrn_files" name="gsrn_files[]" class="form-control" multiple>
                            <div class="form-text text-muted small">PDF, JPG, PNG allowed. Max 5MB per file.</div>
                            <div id="newGSRNFileList"
                                class="mt-2 list-group list-group-flush border border-secondary rounded"
                                style="display:none;"></div>
                        </div>

                        <?php if (!empty($gsrn_files)): ?>
                            <hr class="my-3 opacity-10">
                            <label class="form-label">Existing Files</label>
                            <div class="list-group">
                                <?php foreach ($gsrn_files as $file): ?>
                                    <div
                                        class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center">
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <i class="fas fa-file-alt me-2 text-info"></i>
                                            <span class="text-white small"><?= escape_html($file['file_name']) ?></span>
                                        </div>
                                        <a href="../../../public/<?= $file['file_path'] ?>" target="_blank"
                                            class="btn btn-sm btn-outline-info p-1 px-2">
                                            <i class="fas fa-eye small"></i> View
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
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
                            <span class="fw-bold text-info"><span class="workflow-dot dot-draft"></span>
                                <?= strtoupper($gsrn['status']) ?></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Send for Approval to *</label>
                            <select name="manager_id" class="form-select form-select-sm <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= $selected_manager_id == $m['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($m['full_name'] ?: $m['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['manager_id'])): ?>
                                <div class="invalid-feedback"><?= escape_html($errors['manager_id']) ?></div>
                            <?php endif; ?>
                        </div>
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
                            <i class="fas fa-save me-2 text-warning"></i>Save Changes
                        </button>
                        <button type="submit" name="action" value="submit_approval" class="btn btn-premium-primary">
                            <i class="fas fa-paper-plane me-2"></i>Update & Submit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

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
        const errors = data && data.errors ? data.errors : {};

        row.innerHTML = `
            <td>
                <select name="item_id[]" class="form-select form-select-sm item-select ${errors.item_id ? 'is-invalid' : ''}" onchange="updateRowInfo(this, ${itemCount})">
                    <option value="">Select Item / Product</option>
                    ${inventoryItems.map(item => `
                        <option value="${item.id}" data-code="${item.code}" data-cost="${item.cost_price}" data-uom="${item.unit_of_measure}" ${data && data.item_id == item.id ? 'selected' : ''}>
                            ${item.code} - ${item.name}
                        </option>
                    `).join('')}
                </select>
                <div class="invalid-feedback">${errors.item_id || ''}</div>
            </td>
            <td>
                <div class="input-group input-group-sm">
                    <input type="text" name="bin_location[]" class="form-control form-control-sm" list="gsrnBinOptions" placeholder="Bin Location" value="${data ? (data.bin_location || '') : ''}">
                    <button class="btn btn-outline-secondary" type="button" title="Create New Bin" onclick="openCreateBinLocation()"><i class="fas fa-plus-square"></i></button>
                </div>
            </td>
            <td>
                <input type="number" name="quantity[]" class="form-control form-control-sm qty-input ${errors.quantity ? 'is-invalid' : ''}" value="${data ? data.quantity : 1}" step="0.01" onchange="calculateRowTotal(${itemCount})">
                <div class="invalid-feedback">${errors.quantity || ''}</div>
            </td>
            <td>
                <input type="text" name="uom[]" class="form-control form-control-sm uom-display" value="${data ? (data.uom || '') : ''}" readonly>
            </td>
            <td>
                <input type="number" name="unit_cost[]" class="form-control form-control-sm cost-input ${errors.unit_cost ? 'is-invalid' : ''}" value="${data ? data.unit_cost : 0}" step="0.01" onchange="calculateRowTotal(${itemCount})">
                <div class="invalid-feedback">${errors.unit_cost || ''}</div>
            </td>
            <td>
                <input type="text" name="total_value[]" class="form-control form-control-sm total-display" value="${data ? data.total_value : 0}" readonly>
            </td>
            <td>
                <input type="text" name="batch_serial[]" class="form-control form-control-sm" placeholder="Batch / Serial #" value="${data ? (data.batch_serial || '') : ''}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeRow(${itemCount})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);
        calculateRowTotal(itemCount);
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
        row.querySelector('.uom-display').value = opt.getAttribute('data-uom') || '';
        clearFieldError(select);
        calculateRowTotal(id);
    }

    function calculateRowTotal(id) {
        const row = document.getElementById(`item-row-${id}`);
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        const total = qty * cost;
        row.querySelector('.total-display').value = total.toFixed(2);
        clearFieldError(row.querySelector('.qty-input'));
        clearFieldError(row.querySelector('.cost-input'));
        calculateLandedCost();
    }

    function setFieldError(field, message) {
        if (!field) return;
        field.classList.add('is-invalid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
        }
    }

    function clearFieldError(field) {
        if (!field) return;
        field.classList.remove('is-invalid');
        const feedback = field.parentElement.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
        }
    }

    function validateForm() {
        let isValid = true;

        const requiredFields = [
            { selector: '[name="gsrn_date"]', message: 'Please fill GSRN Date field.' },
            { selector: '[name="transaction_type"]', message: 'Please fill Transaction Type field.' },
            { selector: '[name="company_id"]', message: 'Please fill Company field.' },
            { selector: '[name="warehouse_id"]', message: 'Please fill Warehouse field.' },
            { selector: '[name="manager_id"]', message: 'Please fill Approval Manager field.' }
        ];

        requiredFields.forEach(({ selector, message }) => {
            const field = document.querySelector(selector);
            if (!field) {
                return;
            }

            field.classList.remove('is-invalid');
            let feedback = field.parentElement.querySelector('.invalid-feedback');
            if (!feedback && field.parentElement.classList.contains('input-group')) {
                feedback = field.parentElement.parentElement.querySelector('.invalid-feedback');
            }
            if (feedback) {
                feedback.textContent = '';
            }

            if (!String(field.value || '').trim()) {
                isValid = false;
                field.classList.add('is-invalid');
                if (feedback) {
                    feedback.textContent = message;
                }
            }
        });

        const rows = document.querySelectorAll('#itemBody tr');
        let hasValidItem = false;

        rows.forEach(row => {
            const itemField = row.querySelector('.item-select');
            const qtyField = row.querySelector('.qty-input');
            const costField = row.querySelector('.cost-input');
            const hasData = [itemField.value, qtyField.value, costField.value].some(value => String(value || '').trim() !== '');

            if (!hasData) {
                clearFieldError(itemField);
                clearFieldError(qtyField);
                clearFieldError(costField);
                return;
            }

            let rowValid = true;

            if (!itemField.value) {
                setFieldError(itemField, 'Please fill Item field.');
                rowValid = false;
            }

            if (!String(qtyField.value || '').trim()) {
                setFieldError(qtyField, 'Please fill Quantity field.');
                rowValid = false;
            } else if (parseFloat(qtyField.value) <= 0) {
                setFieldError(qtyField, 'Quantity must be greater than 0.');
                rowValid = false;
            }

            if (!String(costField.value || '').trim()) {
                setFieldError(costField, 'Please fill Unit Cost field.');
                rowValid = false;
            } else if (parseFloat(costField.value) < 0) {
                setFieldError(costField, 'Unit Cost cannot be negative.');
                rowValid = false;
            }

            if (rowValid) {
                hasValidItem = true;
            } else {
                isValid = false;
            }
        });

        const itemsAlertWrap = document.getElementById('gsrnItemsErrorWrap');
        const itemsAlert = document.getElementById('gsrnItemsError');
        if (!hasValidItem) {
            isValid = false;
            if (itemsAlert) {
                itemsAlert.textContent = 'Please fill at least one item row.';
            }
            if (itemsAlertWrap) {
                itemsAlertWrap.classList.remove('d-none');
            }
        } else if (itemsAlertWrap) {
            itemsAlertWrap.classList.add('d-none');
        }

        return isValid;
    }

    function calculateLandedCost() {
        let invoiceValue = 0;
        document.querySelectorAll('.total-display').forEach(el => {
            invoiceValue += parseFloat(el.value) || 0;
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

        const totalCost = invoiceValue + additionalCosts + adjustment;
        document.getElementById('final_landed_cost_display').textContent = '₹ ' + totalCost.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('final_landed_cost').value = totalCost.toFixed(2);
    }

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

                if (data.po.supplier_id) document.getElementById('supplier_id').value = data.po.supplier_id;
                if (data.po.id) document.getElementById('hidden_po_id').value = data.po.id;

                currentPOLines = data.lines;
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
        if (currentPOLines.length === 0) return;
        currentPOLines.forEach(line => {
            addRow({
                item_id: line.item_id,
                quantity: line.quantity,
                unit_cost: line.unit_price,
                uom: line.unit_of_measure,
                total_value: (line.quantity * line.unit_price).toFixed(2)
            });
        });
        calculateLandedCost();
        currentPOLines = [];
    }

    function toggleManufacturing(type) {
        // Toggle logic if needed
    }

    document.addEventListener('DOMContentLoaded', () => {
        const warehouseSelect = document.getElementById('warehouse_id');
        if (warehouseSelect) {
            warehouseSelect.addEventListener('change', loadWarehouseBins);
            loadWarehouseBins();
        }

        const existingItems = <?= json_encode($existing_items) ?>;
        if (existingItems.length > 0) {
            existingItems.forEach(item => {
                // Fetch item info to get UOM
                const invItem = inventoryItems.find(i => i.id == item.item_id);
                addRow({
                    ...item,
                    uom: invItem ? invItem.unit_of_measure : ''
                });
            });
        } else {
            addRow();
        }

        const form = document.getElementById('gsrnForm');
        if (form) {
            form.addEventListener('submit', (event) => {
                if (!validateForm()) {
                    event.preventDefault();
                }
            });
        }
    });

    // Multi-file accumulation logic for GSRN Edit
    let selectedNewGSRNFiles = [];
    const editGsrnFilesInput = document.getElementById('edit_gsrn_files');
    const newGSRNFileListDiv = document.getElementById('newGSRNFileList');

    if (editGsrnFilesInput) {
        editGsrnFilesInput.addEventListener('change', function (e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                const exists = selectedNewGSRNFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                if (!exists) {
                    selectedNewGSRNFiles.push(file);
                }
            });
            renderNewGSRNFileList();
            updateEditGSRNFilesInput();
        });
    }

    function renderNewGSRNFileList() {
        if (!newGSRNFileListDiv) return;
        if (selectedNewGSRNFiles.length === 0) {
            newGSRNFileListDiv.style.display = 'none';
            return;
        }
        newGSRNFileListDiv.style.display = 'block';
        newGSRNFileListDiv.innerHTML = '<div class="list-group-item bg-dark border-secondary py-1 small fw-bold text-info">New files to upload:</div>';
        selectedNewGSRNFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center py-1';
            item.innerHTML = `
                <span class="text-white small text-truncate" style="max-width: 80%;">${file.name}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeNewGSRNFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            newGSRNFileListDiv.appendChild(item);
        });
    }

    window.removeNewGSRNFile = function (index) {
        selectedNewGSRNFiles.splice(index, 1);
        renderNewGSRNFileList();
        updateEditGSRNFilesInput();
    };

    function updateEditGSRNFilesInput() {
        if (!editGsrnFilesInput) return;
        const dataTransfer = new DataTransfer();
        selectedNewGSRNFiles.forEach(file => dataTransfer.items.add(file));
        editGsrnFilesInput.files = dataTransfer.files;
    }
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
