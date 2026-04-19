<?php
/**
 * Add GSRN / Stock Entry (Production Folder)
 * Screen for recording Goods Received Note and Stock Entries
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'GSRN / Stock Entry - MJR Group ERP';
$company_id_context = active_company_id(1);
$company_name = active_company_name('Current Company');

// Get suppliers for dropdown
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");

// Get companies
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$company_id_context]);

// Get warehouses (from locations table)
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$company_id_context]);

// Get categories
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

// Get inventory items for lookup
$inventory_items = db_fetch_all("SELECT id, code, name, cost_price, unit_of_measure FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id_context]);

// Get managers for approval selection
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$company_id_context]);

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
    ", [$po_id, $company_id_context]);

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
    $work_order_id = intval(post('work_order_id', 0));
    $produced_item_id = intval(post('produced_item_id', 0));
    $production_qty = floatval(post('gsrn_qty', post('quantity', 0)));
    $raw_transaction_type = trim((string)post('transaction_type', ''));
    $transactionTypeMap = [
        'production_entry' => 'Production',
        'production' => 'Production',
        'purchase_entry' => 'Purchase',
        'purchase' => 'Purchase'
    ];
    $transaction_type = $transactionTypeMap[strtolower($raw_transaction_type)] ?? ($raw_transaction_type !== '' ? $raw_transaction_type : 'Production');
    $company_id = intval(post('company_id', 0));
    $warehouse_id = intval(post('warehouse_id', 0));
    $manager_id = post('manager_id') ?: null;

    // Server-side fallback from selected Work Order (if JS auto-fill didn't run)
    if ($work_order_id > 0) {
        $wo_snapshot = db_fetch("SELECT wo.wo_number, wo.product_id, wo.quantity, wo.location_id FROM work_orders wo JOIN inventory_items i ON wo.product_id = i.id WHERE wo.id = ? AND i.company_id = ?", [$work_order_id, $company_id_context]);
        if ($wo_snapshot) {
            if ($produced_item_id <= 0) {
                $produced_item_id = intval($wo_snapshot['product_id'] ?? 0);
            }
            if ($production_qty <= 0) {
                $production_qty = floatval($wo_snapshot['quantity'] ?? 0);
            }
            if ($warehouse_id <= 0) {
                $warehouse_id = intval($wo_snapshot['location_id'] ?? 0);
            }
        }
    }

    // Fallbacks for required DB fields not present in this UI
    if ($company_id <= 0) {
        $currentUser = current_user();
        $company_id = intval($currentUser['company_id'] ?? 0);
        if ($company_id <= 0 && !empty($companies)) {
            $company_id = intval($companies[0]['id']);
        }
    }
    if ($warehouse_id <= 0) {
        $warehouse_id = intval(post('target_warehouse_id', 0));
    }

    // Validation
    if (empty($gsrn_date)) $errors['gsrn_date'] = err_required();
    if ($work_order_id <= 0) $errors['work_order_id'] = err_required();
    if ($produced_item_id <= 0) $errors['produced_item_id'] = 'Produced item was not loaded from work order.';
    if ($production_qty <= 0) $errors['gsrn_qty'] = 'Product quantity must be greater than zero.';
    $allowedTransactionTypes = ['Purchase', 'Production'];
    if (!in_array($transaction_type, $allowedTransactionTypes, true)) {
        $errors['transaction_type'] = 'Invalid transaction type.';
    }
    if ($company_id <= 0) $errors['company_id'] = err_required();
    if ($warehouse_id <= 0) $errors['warehouse_id'] = err_required();

    if (!empty($errors)) {
        $error = 'Please fill that section';
    } else {
        // Begin transaction with retry logic for connection stability
        $max_attempts = 5;
        for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
            try {
                db_begin_transaction();

                $user_id = current_user_id();
                $status = strtolower((string)post('status', 'draft'));
                if ($action === 'submit_approval') {
                    $status = 'pending_approval';
                }

                // 1. Generate GSRN Number (e.g., GRN-00001)
                $last_gsrn = db_fetch("SELECT gsrn_number FROM gsrn_headers ORDER BY id DESC LIMIT 1");
                $next_number = 1;
                if ($last_gsrn) {
                    $last_num = (int) str_replace('GRN-', '', $last_gsrn['gsrn_number']);
                    $next_number = $last_num + 1;
                }
                $gsrn_number = 'GRN-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);

                // 1.5 Ensure Production Columns Exist (Schema Guard)
                require_once __DIR__ . '/../../includes/inventory_transaction_service.php';
                $gsrnHeaderColumnsToEnsure = [
                    'produced_item_id' => "ALTER TABLE gsrn_headers ADD COLUMN produced_item_id INT NULL",
                    'production_qty' => "ALTER TABLE gsrn_headers ADD COLUMN production_qty DECIMAL(15,4) NULL",
                    'production_cost' => "ALTER TABLE gsrn_headers ADD COLUMN production_cost DECIMAL(15,4) NULL",
                    'production_date' => "ALTER TABLE gsrn_headers ADD COLUMN production_date DATE NULL",
                    'production_order_ref' => "ALTER TABLE gsrn_headers ADD COLUMN production_order_ref VARCHAR(100) NULL",
                    'work_order_id' => "ALTER TABLE gsrn_headers ADD COLUMN work_order_id INT NULL",
                    'labor_cost' => "ALTER TABLE gsrn_headers ADD COLUMN labor_cost DECIMAL(15,4) DEFAULT 0",
                    'machine_cost' => "ALTER TABLE gsrn_headers ADD COLUMN machine_cost DECIMAL(15,4) DEFAULT 0",
                    'other_overhead_cost' => "ALTER TABLE gsrn_headers ADD COLUMN other_overhead_cost DECIMAL(15,4) DEFAULT 0",
                    'raw_material_cost' => "ALTER TABLE gsrn_headers ADD COLUMN raw_material_cost DECIMAL(15,4) DEFAULT 0"
                ];
                foreach ($gsrnHeaderColumnsToEnsure as $col => $sqlEnsure) {
                    if (!inventory_table_column_exists('gsrn_headers', $col)) {
                        db_query($sqlEnsure);
                    }
                }

                if (!inventory_table_column_exists('gsrn_items', 'bin_location')) {
                    db_query("ALTER TABLE gsrn_items ADD COLUMN bin_location VARCHAR(100) NULL");
                }

                $headerMap = [
                    'gsrn_number' => $gsrn_number,
                    'gsrn_date' => to_db_date(post('gsrn_date')),
                    'transaction_type' => $transaction_type,
                    'po_id' => post('po_id') ?: null,
                    'supplier_id' => post('supplier_id') ?: null,
                    'company_id' => $company_id,
                    'warehouse_id' => $warehouse_id,
                    'category_id' => post('category_id') ?: null,
                    'currency' => post('currency', 'INR'),
                    'exchange_rate' => post('exchange_rate', 1.0),
                    'invoice_value' => post('invoice_value', 0),
                    'freight_cost' => post('freight_cost', 0),
                    'import_duty' => post('import_duty', 0),
                    'insurance' => post('insurance', 0),
                    'handling_charges' => post('handling_charges', 0),
                    'other_sundry_costs' => post('other_sundry_costs', 0),
                    'adjustment_type' => post('adjustment_type', 'None'),
                    'adjustment_amount' => post('adjustment_amount', 0),
                    'adjustment_reason' => post('adjustment_reason'),
                    'final_landed_cost' => post('hidden_grand_total', 0),
                    'internal_notes' => post('internal_notes'),
                    'warehouse_remarks' => post('warehouse_remarks'),
                    'status' => $status,
                    'created_by' => $user_id,
                    'manager_id' => $manager_id,
                    'produced_item_id' => $produced_item_id ?: null,
                    'production_qty' => $production_qty,
                    'production_cost' => post('hidden_grand_total', 0),
                    'production_date' => date('Y-m-d'),
                    'production_order_ref' => post('production_order_ref'),
                    'work_order_id' => $work_order_id ?: null,
                    'labor_cost' => post('labor_cost', 0),
                    'machine_cost' => post('machine_cost', 0),
                    'other_overhead_cost' => post('other_cost', 0),
                    'raw_material_cost' => post('rm_total_cost', 0)
                ];

                $insertColumns = [];
                $insertValues = [];
                foreach ($headerMap as $col => $val) {
                    if (inventory_table_column_exists('gsrn_headers', $col)) {
                        $insertColumns[] = $col;
                        $insertValues[] = $val;
                    }
                }

                if (empty($insertColumns)) {
                    throw new Exception('GSRN header insert failed: no compatible columns found.');
                }

                $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                $gsrn_id = db_insert(
                    "INSERT INTO gsrn_headers (" . implode(', ', $insertColumns) . ") VALUES ($placeholders)",
                    $insertValues
                );

                if (!$gsrn_id) {
                    throw new Exception("Failed to retrieve generated GSRN ID. Please try again.");
                }

                // 3. Insert Finished Good as the primary stock entry item
                if (post('produced_item_id')) {
                    $unitCost = $production_qty > 0 ? (floatval(post('hidden_grand_total', 0)) / $production_qty) : 0;
                    db_query("
                        INSERT INTO gsrn_items (
                            gsrn_id, item_id, bin_location, quantity, uom, unit_cost, landed_unit_cost, total_value, batch_serial
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [
                        $gsrn_id,
                        post('produced_item_id'),
                        post('target_warehouse_id'), // Use target warehouse from WO
                        $production_qty,
                        post('fg_uom', post('fg_uom_input', 'PCS')),
                        $unitCost, // Unit cost = Total cost / Qty
                        $unitCost,
                        post('hidden_grand_total', 0),
                        post('man_batch_no') ?: post('man_serial_no')
                    ]);
                }


                // 3.5 Handle File Uploads
                if (!empty($_FILES['gsrn_files']['name'][0])) {
                    $upload_dir = __DIR__ . '/../../uploads/gsrn/';
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

                // 3.5 Handle Production Stock Movements (RM Deduction & FG Addition)
                if ($work_order_id > 0 && $status === 'approved') {
                    require_once __DIR__ . '/../../includes/production_functions.php';
                    $wo_id = $work_order_id;
                    
                    // Update WO with actual data before processing stock
                    db_query("
                        UPDATE work_orders SET 
                            actual_qty = ?,
                            production_cost = ?,
                            labor_cost = ?,
                            machine_cost = ?,
                            other_cost = ?,
                            raw_material_cost = ?,
                            status = 'Completed',
                            completed_at = NOW()
                        WHERE id = ?
                    ", [
                        $production_qty,
                        post('hidden_grand_total'),
                        post('labor_cost'),
                        post('machine_cost'),
                        post('other_cost'),
                        post('rm_total_cost'),
                        $wo_id
                    ]);

                    // Deduct Raw Materials (Process RM Actuals if available)
                    deduct_production_stock($wo_id);
                    
                    // Add Finished Goods
                    add_finished_goods_stock($wo_id);
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
                redirect("production_orders.php"); // Redirect to production dashboard or list
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

include __DIR__ . '/../../templates/header.php';
?>

<style>
    :root {
        --primary-glow: rgba(13, 202, 240, 0.3);
        --secondary-glow: rgba(102, 16, 242, 0.3);
        --glass-bg: rgba(30, 30, 45, 0.7);
        --glass-border: rgba(255, 255, 255, 0.08);
        --accent-cyan: #0dcaf0;
        --accent-indigo: #6610f2;
        --text-muted: #a2a3b7;
    }

    body {
        background: radial-gradient(circle at top right, #1a1a2e, #161621);
        min-height: 100vh;
        color: #fff;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    .gsrn-section-title {
        border-left: 5px solid var(--accent-cyan);
        padding-left: 20px;
        margin-bottom: 30px;
        color: #fff;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-shadow: 0 0 10px var(--primary-glow);
    }

    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .premium-card {
        border: 1px solid var(--glass-border);
        border-radius: 16px;
        background: var(--glass-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
        margin-bottom: 30px;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        overflow: hidden;
    }

    .premium-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
    }

    .premium-card .card-header {
        background: linear-gradient(to right, rgba(255, 255, 255, 0.05), transparent);
        border-bottom: 1px solid var(--glass-border);
        font-weight: 700;
        color: var(--accent-cyan);
        padding: 22px 28px;
        font-size: 1.1rem;
        display: flex;
        align-items: center;
    }

    .premium-card .card-header i {
        margin-right: 12px;
        font-size: 1.2rem;
        filter: drop-shadow(0 0 5px var(--primary-glow));
    }

    .premium-card .card-body {
        padding: 30px;
    }

    .form-label {
        color: var(--text-muted);
        font-size: 0.8rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
    }

    .form-control,
    .form-select {
        background-color: rgba(0, 0, 0, 0.2);
        border: 1px solid #323248;
        color: #fff;
        border-radius: 10px;
        padding: 12px 18px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .form-control:focus,
    .form-select:focus {
        background-color: rgba(0, 0, 0, 0.3);
        border-color: var(--accent-cyan);
        box-shadow: 0 0 0 4px rgba(13, 202, 240, 0.15);
        color: #fff;
    }

    .form-control:read-only {
        background-color: rgba(255, 255, 255, 0.02);
        border-color: rgba(255, 255, 255, 0.05);
        color: #888;
    }

    .table-premium {
        width: 100%;
        color: #eee;
    }

    .table-premium thead th {
        background: rgba(0, 0, 0, 0.2);
        border-bottom: 2px solid var(--accent-cyan);
        color: var(--text-muted);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        padding: 18px;
        letter-spacing: 1px;
    }

    .table-premium tbody td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 18px;
        vertical-align: middle;
        font-weight: 500;
    }

    .table-premium tbody tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }

    .btn-premium-primary {
        background: linear-gradient(135deg, #0dcaf0, #6610f2);
        border: none;
        padding: 14px 35px;
        font-weight: 700;
        border-radius: 12px;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
    }

    .btn-premium-primary:hover {
        transform: scale(1.05);
        box-shadow: 0 12px 25px rgba(13, 202, 240, 0.3);
        color: #fff;
    }

    .btn-success-premium {
        background: linear-gradient(135deg, #198754, #146c43);
        border: none;
        box-shadow: 0 5px 15px rgba(25, 135, 84, 0.2);
    }

    .gsrn-number-badge {
        background: rgba(13, 202, 240, 0.15);
        border: 1px solid rgba(13, 202, 240, 0.3);
        color: var(--accent-cyan);
        padding: 8px 20px;
        border-radius: 50px;
        font-weight: 800;
        font-size: 1rem;
        box-shadow: 0 0 15px rgba(13, 202, 240, 0.1);
    }

    .cost-summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    #grand_total_cost {
        font-size: 1.8rem;
        color: var(--accent-cyan);
        text-shadow: 0 0 15px var(--primary-glow);
    }

    .bg-info-gradient { background: linear-gradient(to right, #0dcaf0, #007bff); }
    .bg-danger-gradient { background: linear-gradient(to right, #dc3545, #b02a37); }
    .bg-secondary-gradient { background: linear-gradient(to right, #6c757d, #495057); }

    .workflow-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 10px;
        box-shadow: 0 0 8px currentColor;
    }

    .dot-draft { color: #6c757d; background: #6c757d; }
    .dot-approved { color: #198754; background: #198754; }

    .premium-footer-bar {
        background: rgba(22, 22, 33, 0.95) !important;
        backdrop-filter: blur(10px);
        border-radius: 15px 15px 0 0;
    }
</style>

<div class="container-fluid py-4 h-100 overflow-auto">
    <!-- Header Page Actions -->
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="text-white mb-0 text-uppercase"><i class="fas fa-industry me-2 text-info"></i>  GSRN STOCK ENTRY</h2>
            <p class="text-muted mb-0">Manufacturing Goods Receipt and Stock Deduction Screen</p>
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
        
        <!-- 1. MANUFACTURING GSRN HEADER (Full Width) -->
        <div class="card premium-card">
                    <div class="card-header bg-primary-gradient">
                        <i class="fas fa-file-invoice me-2"></i> MANUFACTURING GSRN HEADER
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Manufacturing WO No (Dropdown) *</label>
                                <select name="work_order_id" id="work_order_id" class="form-select select2" onchange="loadWODetails(this.value)" required>
                                    <option value="">Select Work Order</option>
                                    <?php 
                                    $open_wos = db_fetch_all("SELECT wo.id, wo.wo_number, wo.product_id FROM work_orders wo JOIN inventory_items i ON wo.product_id = i.id WHERE wo.status NOT IN ('completed', 'cancelled') AND i.company_id = ? ORDER BY wo.id DESC", [$company_id_context]);
                                    foreach($open_wos as $wo): ?>
                                        <option value="<?= $wo['id'] ?>"><?= escape_html($wo['wo_number']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Production Order No (Auto)</label>
                                <input type="text" id="view_wo_number" name="production_order_ref" class="form-control bg-dark" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Product (Auto)</label>
                                <input type="text" id="view_product_name" class="form-control bg-dark" readonly>
                                <input type="hidden" name="produced_item_id" id="produced_item_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Production Item ID (Auto)</label>
                                <input type="text" id="view_product_id" class="form-control bg-dark" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Generation Reference (Auto)</label>
                                <input type="text" name="generation_ref" class="form-control bg-dark" value="AUTO-REF-<?= time() ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">GSRN Date *</label>
                                <input type="date" name="gsrn_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Created By (User)</label>
                                <input type="text" class="form-control bg-dark" value="<?= escape_html($_SESSION['username']) ?>" readonly>
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
                                <input type="number" name="exchange_rate" id="exchange_rate" class="form-control" value="1.00" step="0.000001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select bg-dark">
                                    <option value="draft">Draft</option>
                                    <option value="approved">Approved</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>


                <!-- 2. FINISHED GOODS ENTRY TABLE -->
                <div class="card premium-card mt-4">
                    <div class="card-header bg-success-gradient">
                        <i class="fas fa-box-open me-2"></i> FINISHED GOODS ENTRY TABLE
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark mb-0" id="fgTable">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Product Name</th>
                                        <th width="120">Product Qty</th>
                                        <th width="120">Approved Qty</th>
                                        <th width="150">Batch/Serial No</th>
                                        <th width="80">UOM</th>
                                        <th width="150">Warehouse</th>
                                        <th width="120">Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span id="fg_item_code" class="text-muted">Select WO...</span></td>
                                        <td><span id="fg_item_display">Select WO...</span></td>
                                        <td><input type="number" name="quantity" id="gsrn_qty" class="form-control form-control-sm" oninput="updateRMConsumption()" required></td>
                                        <td><input type="number" name="approved_qty" id="approved_qty" class="form-control form-control-sm"></td>
                                        <td><input type="text" name="batch_number" class="form-control form-control-sm" placeholder="Batch #"></td>
                                        <td>
                                            <span id="fg_uom" class="text-muted">-</span>
                                            <input type="hidden" name="fg_uom_input" id="fg_uom_input" value="PCS">
                                        </td>
                                        <td>
                                            <select name="target_warehouse_id" id="target_warehouse_id" class="form-select form-select-sm">
                                                <?php foreach ($warehouses as $w): ?>
                                                    <option value="<?= $w['id'] ?>"><?= escape_html($w['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="target_bin_id" id="target_bin_id" class="form-select form-select-sm">
                                                <option value="">Default</option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 3. RAW MATERIAL DEDUCTION PANEL -->
                <div class="card premium-card mt-4">
                    <div class="card-header bg-danger-gradient">
                        <i class="fas fa-minus-circle me-2"></i> RAW MATERIAL DEDUCTION PANEL
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark mb-0" id="rmTable">
                                <thead>
                                    <tr>
                                        <th>Material Code</th>
                                        <th>Material Name</th>
                                        <th>Material Quantity</th>
                                        <th>Actual Consumption</th>
                                        <th>Warehouse</th>
                                        <th>Location (Bin)</th>
                                        <th>UOM</th>
                                        <th>Unit Cost</th>
                                    </tr>
                                </thead>
                                <tbody id="rm_consumption_body">
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No raw materials loaded.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

        <div class="row">
            <!-- Left Column: Cost Summary -->
            <div class="col-lg-6">
                <!-- 4. COST SUMMARY PANEL -->
                <div class="card premium-card">
                    <div class="card-header bg-info-gradient">
                        <i class="fas fa-calculator me-2"></i> COST SUMMARY PANEL
                    </div>
                    <div class="card-body">
                        <div class="cost-summary-item">
                            <span>Raw Material Cost:</span>
                            <span id="rm_total_cost" class="fw-bold">0.00</span>
                            <input type="hidden" name="rm_total_cost" id="hidden_rm_cost" value="0.00">
                        </div>
                        <div class="cost-summary-item">
                            <span>Labor Cost:</span>
                            <input type="number" name="labor_cost" id="labor_cost" class="form-control form-control-sm w-50 text-end" value="0.00" oninput="calculateTotalCost()">
                        </div>
                        <div class="cost-summary-item">
                            <span>Machine Cost:</span>
                            <input type="number" name="machine_cost" id="machine_cost" class="form-control form-control-sm w-50 text-end" value="0.00" oninput="calculateTotalCost()">
                        </div>
                        <div class="cost-summary-item">
                            <span>Other Overhead:</span>
                            <input type="number" name="other_cost" id="other_cost" class="form-control form-control-sm w-50 text-end" value="0.00" oninput="calculateTotalCost()">
                        </div>
                        <div class="cost-summary-item mt-3 pt-3 border-top border-secondary">
                            <span class="fw-bold text-info">Total Production Cost:</span>
                            <span id="grand_total_cost" class="fw-bold">0.00</span>
                            <input type="hidden" name="hidden_grand_total" id="hidden_grand_total">
                        </div>
                        <div class="cost-summary-item text-muted small">
                            <span>Cost per Unit:</span>
                            <span id="cost_per_unit">0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Warehouse Entry -->
            <div class="col-lg-6">
                <!-- 5. WAREHOUSE ENTRY PANEL -->
                <div class="card premium-card">
                    <div class="card-header bg-secondary-gradient">
                        <i class="fas fa-warehouse me-2"></i> WAREHOUSE ENTRY PANEL
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label small">Warehouse</label>
                                <input type="text" id="view_warehouse" class="form-control form-control-sm bg-dark" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Section / Bin</label>
                                <input type="text" id="view_bin" class="form-control form-control-sm bg-dark" readonly>
                            </div>
                            <div class="col-12">
                                <label class="form-label small">Stock Category</label>
                                <select name="category_id" class="form-select form-select-sm">
                                    <option value="">General Stock</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>"><?= escape_html($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Batch Number</label>
                                <input type="text" name="man_batch_no" class="form-control form-control-sm" placeholder="Auto/Manual">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">Serial Number</label>
                                <input type="text" name="man_serial_no" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-dark border-top border-secondary p-3">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-bold mb-2">
                            <i class="fas fa-check-circle me-1"></i> FINISH PRODUCTION
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="history.back()">
                            CANCEL
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Wireframe wire-up Javascript -->
<script>
    let currentBOM = [];

    // 1. Load Work Order Details & BOM
    function loadWODetails(woId) {
        if (!woId) {
            resetForm();
            return;
        }

        $.ajax({
            url: 'ajax_get_wo_details.php',
            method: 'GET',
            data: { wo_id: woId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const wo = response.wo;
                    // Header Auto-fills
                    $('#view_wo_number').val(wo.wo_number);
                    $('#view_product_name').val(wo.product_name);
                    $('#view_product_id').val(wo.product_id);
                    $('#produced_item_id').val(wo.product_id);
                    
                    // FG Table Auto-fills
                    $('#fg_item_code').text(wo.product_code);
                    $('#fg_item_display').text(wo.product_name);
                    $('#fg_uom').text(wo.unit_of_measure || 'Units');
                    $('#fg_uom_input').val(wo.unit_of_measure || 'PCS');
                    $('#gsrn_qty').val(wo.quantity);
                    $('#approved_qty').val(wo.quantity);
                    
                    // Warehouse Entry Panel
                    $('#view_warehouse').val(wo.location_name);
                    $('#target_warehouse_id').val(wo.location_id);
                    
                    // Store BOM for consumption calculation
                    currentBOM = response.bom;
                    renderRMTable();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('Connection error while fetching WO details.');
            }
        });
    }

    // 2. Render Raw Material Deduction Table
    function renderRMTable() {
        const prodQty = parseFloat($('#gsrn_qty').val()) || 0;
        let html = '';
        let totalRMCost = 0;

        if (currentBOM.length === 0) {
            html = '<tr><td colspan="8" class="text-center py-4 text-muted">No raw materials defined for this WO.</td></tr>';
        } else {
            currentBOM.forEach(item => {
                const totalReq = item.req_qty * prodQty;
                const unitCost = parseFloat(item.unit_cost || 0);
                const itemTotalCost = totalReq * unitCost;
                totalRMCost += itemTotalCost;

                html += `
                    <tr>
                        <td>${item.code}</td>
                        <td>${item.name}</td>
                        <td class="text-muted">${totalReq.toFixed(2)}</td>
                        <td>
                            <input type="number" name="rm_actual_qty[${item.component_id}]" class="form-control form-control-sm rm-actual-input" 
                                   value="${totalReq.toFixed(2)}" step="0.01" oninput="calculateTotalCost()">
                            <input type="hidden" name="rm_item_id[]" value="${item.component_id}">
                        </td>
                        <td>${item.warehouse_name || 'Main'}</td>
                        <td>${item.bin_location || 'Default'}</td>
                        <td>${item.unit_of_measure}</td>
                        <td>₹ ${unitCost.toFixed(2)}</td>
                    </tr>
                `;
            });
        }

        $('#rm_consumption_body').html(html);
        $('#rm_total_cost').text(totalRMCost.toFixed(2));
        calculateTotalCost();
    }

    // 3. Simple change trigger for FG quantity
    function updateRMConsumption() {
        renderRMTable();
    }

    // 4. Unified Cost Summary Calculation
    function calculateTotalCost() {
        // Calculate RM Cost from actual inputs
        let totalRM = 0;
        $('.rm-actual-input').each(function(index) {
            const actual = parseFloat($(this).val()) || 0;
            const unitCost = parseFloat(currentBOM[index] ? currentBOM[index].unit_cost : 0);
            totalRM += actual * unitCost;
        });
        
        $('#rm_total_cost').text(totalRM.toFixed(2));
        $('#hidden_rm_cost').val(totalRM.toFixed(2));

        // Get other overheads
        const labor = parseFloat($('#labor_cost').val()) || 0;
        const machine = parseFloat($('#machine_cost').val()) || 0;
        const other = parseFloat($('#other_cost').val()) || 0;

        // Grand Total
        const grandTotal = totalRM + labor + machine + other;
        $('#grand_total_cost').text(grandTotal.toFixed(2));
        $('#hidden_grand_total').val(grandTotal.toFixed(2));

        // Cost per Unit
        const prodQty = parseFloat($('#gsrn_qty').val()) || 0;
        if (prodQty > 0) {
            $('#cost_per_unit').text((grandTotal / prodQty).toFixed(2));
        } else {
            $('#cost_per_unit').text('0.00');
        }
    }

    // 5. Form Utility
    function resetForm() {
        $('#view_wo_number, #view_product_name, #view_product_id, #produced_item_id, #view_warehouse, #view_bin').val('');
        $('#fg_item_code, #fg_item_display, #fg_uom').text('-');
        $('#fg_uom_input').val('PCS');
        $('#rm_consumption_body').html('<tr><td colspan="8" class="text-center py-4 text-muted">No raw materials loaded.</td></tr>');
        $('#rm_total_cost, #grand_total_cost, #cost_per_unit').text('0.00');
        currentBOM = [];
    }

    $(document).ready(function() {
        if ($.fn.select2) {
            $('.select2').select2({ theme: 'bootstrap-5' });
        }
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>