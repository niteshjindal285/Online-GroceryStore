<?php
/**
 * Add Purchase Order
 * Create new purchase order with multiple line items
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('manage_procurement');

$page_title = 'Create Purchase Order - MJR Group ERP';
$selected_company_id = active_company_id(1);

// Get active currencies
$currencies = db_fetch_all("SELECT id, code, name, symbol, exchange_rate, is_base FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code ASC");
$base_currency = db_fetch("SELECT * FROM currencies WHERE is_base = 1 AND is_active = 1 LIMIT 1");

// Handle form submission
$errors = [];
// FIX: Initialize $existing_lines so JS json_encode never gets an undefined variable
$existing_lines = [];

if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        error_log("POST DATA: " . print_r($_POST, true));
        try {
            $supplier_id             = post('supplier_id');
            $supplier_contact        = post('supplier_contact');
            $supplier_email          = post('supplier_email');
            $supplier_address        = post('supplier_address');
            $company_id              = (int) (post('company_id') ?: $selected_company_id);
            $purchase_type           = post('purchase_type');
            $order_category          = post('order_category');
            $reference_no            = post('reference_no');
            $order_date              = to_db_date(post('order_date'));
            $expected_delivery_date  = to_db_date(post('expected_delivery_date'));
            $warehouse_id            = post('warehouse_id');
            $warehouse_bin_id        = post('warehouse_bin_id');
            $shipping_cost           = post('shipping_cost') ?: 0;
            $freight                 = post('freight') ?: 0;
            $custom_duty             = post('custom_duty') ?: 0;
            $customs_processing_fees = post('customs_processing_fees') ?: 0;
            $quarantine_fees         = post('quarantine_fees') ?: 0;
            $excise_tax              = post('excise_tax') ?: 0;
            $shipping_line_anl       = post('shipping_line_anl') ?: 0;
            $brokerage               = post('brokerage') ?: 0;
            $container_detention     = post('container_detention') ?: 0;
            $bond_refund             = post('bond_refund') ?: 0;
            $cartage                 = post('cartage') ?: 0;
            $inspection_fees         = post('inspection_fees') ?: 0;
            $insurance               = post('insurance') ?: 0;
            $other_charges           = post('other_charges') ?: 0;
            $landed_cost             = post('landed_cost') ?: 0;
            $landed_cost_method      = post('landed_cost_method') ?: 'value';
            $tax_class_id            = post('tax_class_id');
            $currency_id             = post('currency_id');
            $status                  = 'draft';
            $notes                   = post('notes');
            $exchange_rate           = post('exchange_rate') ?: 1.000000;
            $manager_id              = post('manager_id');

            if (!empty($warehouse_bin_id)) {
                $bin_row = db_fetch("SELECT code FROM bins WHERE id = ?", [$warehouse_bin_id]);
                if ($bin_row && !empty($bin_row['code'])) {
                    $notes = trim(($notes ? $notes . "\n" : '') . 'Preferred Bin: ' . $bin_row['code']);
                }
            }

            $item_ids    = post('item_id') ?: [];
            $quantities  = post('quantity') ?: [];
            $unit_prices = post('unit_price') ?: [];
            $landed_costs = post('landed_unit_cost') ?: [];
            $cbm_lengths = post('cbm_length') ?: [];
            $cbm_widths  = post('cbm_width') ?: [];
            $cbm_heights = post('cbm_height') ?: [];
            $cbm_totals  = post('cbm_total') ?: [];
            $manual_pcts = post('manual_pct') ?: [];

            // Field validation
            if (empty($supplier_id))  $errors['supplier_id']  = err_required();
            if (empty($order_date))   $errors['order_date']   = err_required();
            if (empty($company_id))   $errors['company_id']   = err_required();
            if (empty($warehouse_id)) $errors['warehouse_id'] = err_required();
            if (empty($currency_id))  $errors['currency_id']  = err_required();
            if (empty($purchase_type)) $errors['purchase_type'] = err_required();
            if (empty($order_category)) $errors['order_category'] = err_required();
            if (empty($manager_id))   $errors['manager_id']   = err_required();

            // Calculate totals and build line items first
            $subtotal   = 0;
            $line_items = [];

            foreach ($item_ids as $index => $item_id) {
                if (empty($item_id)) continue;

                $quantity   = floatval($quantities[$index]  ?? 0);
                $unit_price = floatval($unit_prices[$index] ?? 0);
                $line_total = $quantity * $unit_price;

                if ($quantity <= 0) {
                    throw new Exception('Please fill Quantity with a value greater than 0');
                }
                if ($unit_price < 0) {
                    throw new Exception('Please fill Unit Price with a non-negative value');
                }

                $subtotal    += $line_total;
                $line_items[] = [
                    'item_id'    => $item_id,
                    'quantity'   => $quantity,
                    'unit_price' => $unit_price,
                    'line_total' => $line_total,
                    'landed_unit_cost' => floatval($landed_costs[$index] ?? 0),
                    'cbm_length' => floatval($cbm_lengths[$index] ?? 0),
                    'cbm_width'  => floatval($cbm_widths[$index] ?? 0),
                    'cbm_height' => floatval($cbm_heights[$index] ?? 0),
                    'cbm_total'  => floatval($cbm_totals[$index] ?? 0),
                    'manual_pct' => floatval($manual_pcts[$index] ?? 0)
                ];

                // FIX: Re-populate existing_lines so the form can re-render them on validation error
                $existing_lines[] = [
                    'item_id'    => $item_id,
                    'quantity'   => $quantity,
                    'unit_price' => $unit_price,
                    'has_error'  => false,
                    'cbm_length' => floatval($cbm_lengths[$index] ?? 0),
                    'cbm_width'  => floatval($cbm_widths[$index] ?? 0),
                    'cbm_height' => floatval($cbm_heights[$index] ?? 0),
                    'cbm_total'  => floatval($cbm_totals[$index] ?? 0),
                    'manual_pct' => floatval($manual_pcts[$index] ?? 0)
                ];
            }

            // Validate: must have at least one non-empty line item
            if (empty($line_items)) {
                $errors['line_items'] = err_required();
            }

            // Stop here if there are validation errors
            if (!empty($errors)) {
                throw new Exception('Please fix the validation errors below.');
            }

            // Calculate tax
            $tax_amount = 0;
            if (!empty($tax_class_id)) {
                $tax_class = db_fetch("SELECT tax_rate FROM tax_configurations WHERE id = ? AND is_active = 1", [$tax_class_id]);
                if ($tax_class && !empty($tax_class['tax_rate'])) {
                    $tax_amount = $subtotal * floatval($tax_class['tax_rate']);
                }
            }

            $total_amount = $subtotal + $tax_amount + $shipping_cost + $freight + $custom_duty + $customs_processing_fees + $quarantine_fees + $excise_tax + $shipping_line_anl + $brokerage + $container_detention + $bond_refund + $cartage + $inspection_fees + $insurance + $other_charges;

            $current_user = current_user();

            // Generate PO number with retry to handle concurrent inserts
            $max_attempts = 5;
            $po_number    = null;
            $po_id        = null;

            for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
                try {
                    db_begin_transaction();

                    $last_po = db_fetch("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
                    if ($last_po && preg_match('/PO-(\d+)/', $last_po['po_number'], $matches)) {
                        $next_num = intval($matches[1]) + 1;
                    } else {
                        $next_num = 1;
                    }
                    $po_number = 'PO-' . str_pad($next_num, 6, '0', STR_PAD_LEFT);

                    $po_id = db_insert("
                        INSERT INTO purchase_orders (
                            po_number, supplier_id, supplier_contact, supplier_email, supplier_address,
                            company_id, purchase_type, order_category, reference_no,
                            order_date, expected_delivery_date, warehouse_id,
                            status, subtotal, tax_amount, shipping_cost, freight, custom_duty, customs_processing_fees, quarantine_fees, excise_tax, shipping_line_anl, brokerage, container_detention, bond_refund, cartage, inspection_fees, insurance, other_charges, landed_cost,
                            total_amount, tax_class_id, currency_id, landed_cost_method, notes, created_by, manager_id, created_at, exchange_rate
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
                    ", [
                        $po_number, $supplier_id, $supplier_contact, $supplier_email, $supplier_address,
                        $company_id, $purchase_type, $order_category, $reference_no,
                        $order_date, $expected_delivery_date, $warehouse_id,
                        $status, $subtotal, $tax_amount, $shipping_cost, $freight, $custom_duty, $customs_processing_fees, $quarantine_fees, $excise_tax, $shipping_line_anl, $brokerage, $container_detention, $bond_refund, $cartage, $inspection_fees, $insurance, $other_charges, $landed_cost,
                        $total_amount, $tax_class_id ?: null, $currency_id, $landed_cost_method, $notes, $current_user['id'], $manager_id, $exchange_rate
                    ]);

                    if (!$po_id) {
                        throw new Exception("Could not retrieve generated Purchase Order ID. Please check database connectivity.");
                    }

                    // Insert PO lines within same transaction
                    foreach ($line_items as $line) {
                        db_query("
                            INSERT INTO purchase_order_lines (po_id, item_id, quantity, unit_price, line_total, landed_unit_cost, cbm_length, cbm_width, cbm_height, cbm_total, manual_pct)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ", [
                            $po_id,
                            $line['item_id'],
                            $line['quantity'],
                            $line['unit_price'],
                            $line['line_total'],
                            $line['landed_unit_cost'],
                            $line['cbm_length'],
                            $line['cbm_width'],
                            $line['cbm_height'],
                            $line['cbm_total'],
                            $line['manual_pct']
                        ]);
                    }

                    // FIX: Log history correctly as 'draft' — not 'pending_approval'
                    log_po_history($po_id, 'draft', 'Purchase order created as draft');

                    // FIX: Handle attachments INSIDE the transaction so failures are atomic
                    if (!empty($_FILES['attachments']['name'][0])) {
                        $upload_dir = __DIR__ . '/../../../public/uploads/po/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        foreach ($_FILES['attachments']['name'] as $i => $name) {
                            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                                $tmp_name  = $_FILES['attachments']['tmp_name'][$i];
                                $file_ext  = pathinfo($name, PATHINFO_EXTENSION);
                                $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                                $file_path = 'uploads/po/' . $file_name;
                                $target    = $upload_dir . $file_name;

                                if (move_uploaded_file($tmp_name, $target)) {
                                    db_insert("
                                        INSERT INTO purchase_order_attachments (po_id, file_name, file_path, file_type, file_size, uploaded_by)
                                        VALUES (?, ?, ?, ?, ?, ?)
                                    ", [
                                        $po_id, $name, $file_path,
                                        $_FILES['attachments']['type'][$i],
                                        $_FILES['attachments']['size'][$i],
                                        $current_user['id'],
                                    ]);
                                }
                            }
                        }
                    }

                    // Commit transaction — success!
                    db_commit();

                    // Success — break out of retry loop
                    break;

                } catch (Exception $e) {
                    db_rollback();

                    $error_msg    = strtolower($e->getMessage());
                    $is_duplicate = (
                        strpos($error_msg, 'duplicate')         !== false ||
                        strpos($error_msg, 'unique constraint') !== false
                    );
                    $is_lost_conn = (
                        strpos($error_msg, 'gone away') !== false ||
                        strpos($error_msg, 'lost connection') !== false ||
                        strpos($error_msg, 'child row') !== false // Catch the FK error as a signal to retry the whole thing
                    );

                    if ($attempt < $max_attempts - 1 && ($is_duplicate || $is_lost_conn)) {
                        $delay = 50000 * pow(2, $attempt);
                        usleep($delay);
                        log_error("PO creation retry on attempt " . ($attempt + 1) . " due to: " . $e->getMessage());
                        continue;
                    }
                    throw $e;
                }
            }

            if (!$po_id) {
                throw new Exception('Failed to generate unique PO number after ' . $max_attempts . ' attempts. Please try again.');
            }

            // FIX: Flash message is consistent with the draft status saved
            set_flash("Purchase Order $po_number created successfully as draft!", 'success');
            redirect("view_purchase_order.php?id=$po_id");

        } catch (Exception $e) {
            $error_message = $e->getMessage();
            log_error("Error creating PO: " . $error_message);
            
            // Only show the message if it is not the generic validation sentinel
            if ($e->getMessage() !== 'Please fix the validation errors below.') {
                set_flash(sanitize_db_error($error_message), 'error');
            }
        }
    }
}

// Get suppliers — scoped to the active company
$suppliers = db_fetch_all("SELECT id, supplier_code, name, contact_person, email, address FROM suppliers WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL) ORDER BY name", [$selected_company_id]);

// Get companies
$companies = is_super_admin()
    ? db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")
    : db_fetch_all("SELECT id, name FROM companies WHERE id = ? AND is_active = 1 ORDER BY name", [$selected_company_id]);

// Get warehouses
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// Get inventory items
$inventory_items = db_fetch_all("SELECT id, code, name, cost_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// FIX: Single query for tax classes (removed duplicate query from top of file)
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_rate FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('purchase_tax', 'both') ORDER BY tax_name");

// Get managers for workflow (strictly within the same company)
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$selected_company_id]);

// Get current user's assigned manager
$assigned_manager_id = $current_user['manager_id'] ?? null;

include __DIR__ . '/../../../templates/header.php';
?>

<style>
    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        margin-bottom: 25px;
        transition: all 0.3s ease;
    }
    .premium-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(0,0,0,0.4);
    }
    .premium-card .card-header {
        background: rgba(255,255,255,0.03);
        border-bottom: 1px solid rgba(255,255,255,0.08);
        font-weight: 600;
        color: #0dcaf0; /* Cyan for a more high-tech look */
        padding: 18px 25px;
        border-radius: 12px 12px 0 0;
    }
    .workflow-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 8px;
    }
    .dot-draft   { background: #6c757d; }
    .dot-pending { background: #ffc107; }
    .dot-approved{ background: #198754; }
    
    /* Input focus effects */
    .form-control:focus, .form-select:focus {
        background-color: #2b2b40;
        border-color: #0dcaf0;
        box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
        color: #fff;
    }
</style>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-plus me-2"></i>Create Purchase Order</h2>
        </div>
        <div class="col-auto">
            <a href="purchase_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Purchase Orders
            </a>
        </div>
    </div>

    <form method="POST" id="poForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <!-- Purchase Order Header -->
        <div class="card premium-card mb-4 border-start border-4 border-info">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="mb-0 text-primary">Purchase Order Header</h4>
                        <p class="text-muted small mb-0">General information about the purchase order</p>
                    </div>
                    <div class="col-auto">
                        <div class="bg-dark p-2 rounded border border-secondary text-center" style="min-width: 200px;">
                            <label class="small text-muted d-block">PO Number</label>
                            <?php
                            // FIX: Use po_number string (same source as generator) so preview is accurate
                            $last_po_row = db_fetch("SELECT po_number FROM purchase_orders ORDER BY id DESC LIMIT 1");
                            $preview_num = 1;
                            if ($last_po_row && preg_match('/PO-(\d+)/', $last_po_row['po_number'], $m)) {
                                $preview_num = intval($m[1]) + 1;
                            }
                            ?>
                            <span class="fw-bold text-info">Auto Generated (Next: <?= 'PO-' . str_pad($preview_num, 6, '0', STR_PAD_LEFT) ?>)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Supplier Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 premium-card">
                    <div class="card-header py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-truck me-2 text-info"></i>Supplier Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="supplier_id" class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select class="form-select select2 <?= isset($errors['supplier_id']) ? 'is-invalid' : '' ?>" id="supplier_id" name="supplier_id" required onchange="onSupplierChange(this)">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>"
                                            <?= (post('supplier_id') == $supplier['id']) ? 'selected' : '' ?>
                                            data-contact="<?= escape_html($supplier['contact_person']) ?>"
                                            data-email="<?= escape_html($supplier['email']) ?>"
                                            data-address="<?= escape_html($supplier['address']) ?>">
                                        <?= escape_html($supplier['supplier_code']) ?> - <?= escape_html($supplier['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="../supplier/add_supplier.php" class="btn btn-outline-primary" title="Create New Supplier">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php if (isset($errors['supplier_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['supplier_id'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="supplier_contact" class="form-label">Supplier Contact</label>
                            <input type="text" class="form-control" id="supplier_contact" name="supplier_contact" value="<?= escape_html(post('supplier_contact')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="supplier_email" class="form-label">Supplier Email</label>
                            <input type="email" class="form-control" id="supplier_email" name="supplier_email" value="<?= escape_html(post('supplier_email')) ?>">
                        </div>
                        <div class="mb-3">
                            <label for="supplier_address" class="form-label">Supplier Address</label>
                            <textarea class="form-control" id="supplier_address" name="supplier_address" rows="3"><?= escape_html(post('supplier_address')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 premium-card">
                    <div class="card-header py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Order Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['order_date']) ? 'is-invalid' : '' ?>" id="order_date" name="order_date" value="<?= post('order_date') ?: format_date(date('Y-m-d')) ?>" placeholder="DD-MM-YYYY" required>
                                <?php if (isset($errors['order_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['order_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expected_delivery_date" class="form-label">Expected Delivery</label>
                                <input type="text" class="form-control" id="expected_delivery_date" name="expected_delivery_date" value="<?= post('expected_delivery_date') ?: format_date(date('Y-m-d', strtotime('+14 days'))) ?>" placeholder="DD-MM-YYYY">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <?php if (!empty($_SESSION['company_id'])): ?>
                                        <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                        <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                    <?php else: ?>
                                        <select class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" id="company_id" name="company_id" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $company): ?>
                                            <option value="<?= $company_id_val = $company['id'] ?>" <?= (post('company_id') == $company_id_val) ? 'selected' : '' ?>><?= escape_html($company['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <a href="../../companies/create.php" class="btn btn-outline-primary" title="Create New Company">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <?php if (isset($errors['company_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['company_id'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
 
                            <div class="col-md-6 mb-3">
                                <label for="warehouse_id" class="form-label">Warehouse Location <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['warehouse_id']) ? 'is-invalid' : '' ?>" id="warehouse_id" name="warehouse_id" required>
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse_id_val = $warehouse['id'] ?>" <?= (post('warehouse_id') == $warehouse_id_val) ? 'selected' : '' ?>><?= escape_html($warehouse['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['warehouse_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['warehouse_id'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="warehouse_bin_id" class="form-label">Bin Location</label>
                                <select class="form-select" id="warehouse_bin_id" name="warehouse_bin_id">
                                    <option value="">Select Bin (Optional)</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="purchase_type" class="form-label">Purchase Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['purchase_type']) ? 'is-invalid' : '' ?>" id="purchase_type" name="purchase_type" onchange="toggleExchangeRate()" required>
                                    <option value="Local"         <?= post('purchase_type') === 'Local'         ? 'selected' : '' ?>>Local</option>
                                    <option value="International" <?= post('purchase_type') === 'International' ? 'selected' : '' ?>>International</option>
                                </select>
                                <?php if (isset($errors['purchase_type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['purchase_type'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="currency_id" class="form-label">Currency <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <select class="form-select <?= isset($errors['currency_id']) ? 'is-invalid' : '' ?>" id="currency_id" name="currency_id" onchange="updateCurrencyInfo()" required>
                                        <?php foreach ($currencies as $curr): 
                                            $label = $curr['code'];
                                            if ($curr['code'] === 'FJD') $label .= ' / $';
                                            elseif ($curr['code'] === 'INR') $label .= ' / ₹';
                                            elseif ($curr['code'] === 'USD') $label .= ' / $';
                                            elseif ($curr['code'] === 'EUR') $label .= ' / €';
                                            elseif ($curr['code'] === 'GBP') $label .= ' / £';
                                            else $label .= ' / ' . $curr['symbol'];
                                        ?>
                                        <option value="<?= $curr['id'] ?>"
                                                data-symbol="<?= escape_html($curr['symbol']) ?>"
                                                data-rate="<?= $curr['exchange_rate'] ?>"
                                                <?= ($curr['code'] === 'FJD' || ($curr['is_base'] && !post('currency_id')) || post('currency_id') == $curr['id']) ? 'selected' : '' ?>>
                                            <?= escape_html($label) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCurrencyModal" title="Add New Currency">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['currency_id'])): ?>
                                    <div class="invalid-feedback d-block"><?= $errors['currency_id'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4 mb-3" id="exchange_rate_container" style="display: none;">
                                <label for="exchange_rate" class="form-label">Exchange Rate</label>
                                <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" step="0.000001" value="<?= post('exchange_rate') ?: '1.000000' ?>">
                                <small class="text-muted">Rate against base USD</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="order_category" class="form-label">Order Category <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['order_category']) ? 'is-invalid' : '' ?>" id="order_category" name="order_category" required>
                                    <option value="Purchase"     <?= post('order_category') === 'Purchase'     ? 'selected' : '' ?>>Purchase</option>
                                    <option value="Manufactured" <?= post('order_category') === 'Manufactured' ? 'selected' : '' ?>>Manufactured</option>
                                </select>
                                <?php if (isset($errors['order_category'])): ?>
                                    <div class="invalid-feedback"><?= $errors['order_category'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="reference_no" class="form-label">Reference No</label>
                                <input type="text" class="form-control" id="reference_no" name="reference_no" value="<?= escape_html(post('reference_no')) ?>" placeholder="Supplier Ref">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="landed_cost_method" class="form-label">Landed Cost Distribution Method</label>
                                <select class="form-select" id="landed_cost_method" name="landed_cost_method" onchange="calculateTotal()">
                                    <option value="value" <?= post('landed_cost_method') === 'value' ? 'selected' : '' ?>>Cost / Invoice Value</option>
                                    <option value="cbm" <?= post('landed_cost_method') === 'cbm' ? 'selected' : '' ?>>CBM - Meters (Volume)</option>
                                    <option value="manual" <?= post('landed_cost_method') === 'manual' ? 'selected' : '' ?>>Manual Percentage</option>
                                </select>
                            </div>
                            <!-- FIX: Removed the broken extra closing </div> that mismatched the .row -->
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <input type="text" class="form-control bg-dark text-info fw-bold" value="Draft" readonly>
                                <input type="hidden" name="status" value="draft">
                            </div>
                        </div>
                    </div><!-- /.card-body -->
                </div><!-- /.card -->
            </div><!-- /.col -->
        </div><!-- /.row -->


        <!-- Line Items -->
        <div class="card premium-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0 text-info">Line Items</h5>
                <button type="button" class="btn btn-sm btn-primary" onclick="addLineItem()">
                    <i class="fas fa-plus"></i> Add Item
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Unit Landed (Local)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <!-- Line items populated by JS on DOMContentLoaded -->
                        </tbody>
                        <?php if (isset($errors['line_items'])): ?>
                            <tfoot id="lineItemError">
                                <tr>
                                    <td colspan="5" class="text-danger small px-2">
                                        <i class="fas fa-exclamation-circle me-1"></i> <?= $errors['line_items'] ?>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

        <!-- International Cost Section -->
        <div id="internationalSection" class="card premium-card mb-4" style="display: none;">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-globe me-2 text-info"></i>International Cost Section</h5>
            </div>
            <div class="card-body">
                <div class="row border-bottom border-secondary pb-3 mb-3">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Invoice Value (Foreign)</label>
                        <input type="number" class="form-control bg-dark" id="invoice_value_foreign" value="0.00" readonly>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="exchange_rate_intl" class="form-label">Exchange Rate</label>
                        <input type="number" class="form-control" id="exchange_rate_intl" step="0.000001" value="1.000000" onchange="syncExchangeRate(this)" onkeyup="syncExchangeRate(this)">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="freight" class="form-label">Freight Cost</label>
                        <input type="number" class="form-control text-success fw-bold offset-local" id="freight" name="freight" step="0.01" value="<?= post('freight') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Converted Value (Base)</label>
                        <input type="number" class="form-control bg-dark text-success" id="converted_value" value="0.00" readonly>
                    </div>
                </div>

                <h6 class="text-info fw-bold mb-3 px-2">All the below is in Local Currency</h6>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="custom_duty" class="form-label">Custom import duty</label>
                        <input type="number" class="form-control offset-local" id="custom_duty" name="custom_duty" step="0.01" value="<?= post('custom_duty') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="customs_processing_fees" class="form-label">Customs processing fees</label>
                        <input type="number" class="form-control offset-local" id="customs_processing_fees" name="customs_processing_fees" step="0.01" value="<?= post('customs_processing_fees') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="quarantine_fees" class="form-label">Quarantine fees</label>
                        <input type="number" class="form-control offset-local" id="quarantine_fees" name="quarantine_fees" step="0.01" value="<?= post('quarantine_fees') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="excise_tax" class="form-label">Excise tax</label>
                        <input type="number" class="form-control offset-local" id="excise_tax" name="excise_tax" step="0.01" value="<?= post('excise_tax') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="shipping_line_anl" class="form-label">Shipping Line - ANL</label>
                        <input type="number" class="form-control offset-local" id="shipping_line_anl" name="shipping_line_anl" step="0.01" value="<?= post('shipping_line_anl') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="brokerage" class="form-label">Brokerage</label>
                        <input type="number" class="form-control offset-local" id="brokerage" name="brokerage" step="0.01" value="<?= post('brokerage') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="container_detention" class="form-label">Container Detention</label>
                        <input type="number" class="form-control offset-local" id="container_detention" name="container_detention" step="0.01" value="<?= post('container_detention') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="bond_refund" class="form-label">Bond refund</label>
                        <input type="number" class="form-control offset-local" id="bond_refund" name="bond_refund" step="0.01" value="<?= post('bond_refund') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>

                    <div class="col-md-3 mb-3">
                        <label for="cartage" class="form-label">Cartage</label>
                        <input type="number" class="form-control offset-local" id="cartage" name="cartage" step="0.01" value="<?= post('cartage') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="inspection_fees" class="form-label">Inspection fees - Transport</label>
                        <input type="number" class="form-control offset-local" id="inspection_fees" name="inspection_fees" step="0.01" value="<?= post('inspection_fees') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="insurance" class="form-label">Insurance Cost</label>
                        <input type="number" class="form-control offset-local" id="insurance" name="insurance" step="0.01" value="<?= post('insurance') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="other_charges" class="form-label">Other Charges</label>
                        <input type="number" class="form-control offset-local" id="other_charges" name="other_charges" step="0.01" value="<?= post('other_charges') ?: '0.00' ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3 mt-3 offset-md-8">
                        <label class="form-label text-primary">Total Landed Cost <small class="text-muted">(Local Currency)</small></label>
                        <input type="number" class="form-control bg-dark border-primary fw-bold text-primary fs-5" id="landed_cost" name="landed_cost" step="0.01" value="0.00" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attachments | Notes -->
        <div class="card premium-card mb-4">
            <div class="card-header py-3">
                <h5 class="mb-0 fw-bold"><i class="fas fa-paperclip me-2 text-info"></i>Attachments | Notes</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="attachments" class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                        <small class="text-muted">You can upload multiple files (Invoice, Delivery Note, etc.)</small>
                        <div id="fileList" class="mt-2 list-group list-group-flush border border-secondary rounded" style="display:none;"></div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any additional instructions..."><?= escape_html(post('notes')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary & Totals -->
        <div class="card premium-card mb-4">
            <div class="card-header py-3 text-center">
                <h5 class="mb-0 fw-bold text-uppercase text-info">Order Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card premium-card">
                            <div class="card-header">
                                <i class="fas fa-check-double me-2"></i> WORKFLOW &amp; APPROVAL
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">Current Status:</span>
                                    <span class="fw-bold text-info"><span class="workflow-dot dot-draft"></span> Draft</span>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Send for Approval to <span class="text-danger">*</span></label>
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
                                <p class="text-muted small"><i class="fas fa-info-circle me-1"></i> Stock will be added to inventory only after <strong>Approved</strong> status.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td class="fw-bold">Subtotal:</td>
                                <td class="text-end"><span id="subtotalDisplay">$0.00</span></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Tax Config:</td>
                                <td class="text-end">
                                    <select class="form-select form-select-sm" id="tax_class_id" name="tax_class_id" onchange="calculateTotal()">
                                        <option value="">No Tax (0%)</option>
                                        <?php foreach ($tax_classes as $tax): ?>
                                            <option value="<?= $tax['id'] ?>"
                                                    data-rate="<?= $tax['tax_rate'] ?>"
                                                    <?= (post('tax_class_id') == $tax['id']) ? 'selected' : '' ?>>
                                                <?= escape_html($tax['tax_name']) ?> (<?= number_format($tax['tax_rate'] * 100, 2) ?>%)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Tax Amount:</td>
                                <td class="text-end"><span id="taxDisplay">$0.00</span></td>
                            </tr>
                            <tr>
                                <td class="fw-bold">Shipping Cost:</td>
                                <td class="text-end">
                                    <input type="number" class="form-control form-control-sm text-end d-inline-block w-50"
                                           id="shipping_cost" name="shipping_cost"
                                           value="<?= post('shipping_cost') ?: 0 ?>" step="0.01" min="0" onchange="calculateTotal()" onkeyup="calculateTotal()">
                                </td>
                            </tr>
                            <?php if (isset($errors['line_items'])): ?>
                            <tr>
                                <td colspan="2" class="text-center bg-danger-subtle py-2">
                                    <span class="text-danger small fw-bold"><i class="fas fa-exclamation-circle me-1"></i> <?= escape_html($errors['line_items']) ?></span>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr class="border-top border-bottom border-primary">
                                <td class="fw-bold fs-5 color-primary">Grand Total:</td>
                                <td class="text-end fw-bold fs-5 color-primary"><span id="totalDisplay">$0.00</span></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-5">
            <a href="purchase_orders.php" class="btn btn-outline-secondary me-md-2">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
            <button type="submit" class="btn btn-primary px-4">
                <i class="fas fa-save me-2"></i>Save Purchase Order
            </button>
        </div>
    </form>
</div>

<script>
function onSupplierChange(select) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('supplier_contact').value = opt.value ? (opt.getAttribute('data-contact') || '') : '';
    document.getElementById('supplier_email').value   = opt.value ? (opt.getAttribute('data-email')   || '') : '';
    document.getElementById('supplier_address').value = opt.value ? (opt.getAttribute('data-address') || '') : '';
}

function updateCurrencyInfo() {
    const sel = document.getElementById('currency_id');
    const opt = sel.options[sel.selectedIndex];
    const symbol = opt.getAttribute('data-symbol') || '$';
    const rate   = opt.getAttribute('data-rate')   || 1;

    document.querySelectorAll('.currency-symbol').forEach(s => s.textContent = symbol);

    if (document.getElementById('purchase_type').value === 'International') {
        document.getElementById('exchange_rate').value = parseFloat(rate).toFixed(6);
        const intl = document.getElementById('exchange_rate_intl');
        if (intl) intl.value = parseFloat(rate).toFixed(6);
    } else {
        document.getElementById('exchange_rate').value = '1.000000';
    }
    calculateTotal();
}

function toggleExchangeRate() {
    const isIntl        = document.getElementById('purchase_type').value === 'International';
    const container     = document.getElementById('exchange_rate_container');
    const intlSection   = document.getElementById('internationalSection');

    if (container)   container.style.display   = isIntl ? 'block' : 'none';
    if (intlSection) intlSection.style.display = isIntl ? 'block' : 'none';

    if (isIntl) {
        const sel  = document.getElementById('currency_id');
        const rate = sel.options[sel.selectedIndex].getAttribute('data-rate') || 1;
        document.getElementById('exchange_rate').value = parseFloat(rate).toFixed(6);
        const intl = document.getElementById('exchange_rate_intl');
        if (intl) intl.value = parseFloat(rate).toFixed(6);
    } else {
        document.querySelectorAll('.offset-local').forEach(el => el.value = '0.00');
        document.getElementById('landed_cost').value = '0.00';
        document.getElementById('exchange_rate').value = '1.000000';
        const intl = document.getElementById('exchange_rate_intl');
        if (intl) intl.value = '1.000000';
    }
    calculateTotal();
}

const inventoryItems = <?= json_encode($inventory_items) ?>;
let lineItemCount = 0;

// FIX: addLineItem now accepts optional pre-fill parameters so existing lines
//      can be re-rendered after a validation error.
function addLineItem(prefillItemId, prefillQty, prefillPrice) {
    lineItemCount++;
    const tbody = document.getElementById('lineItemsBody');
    const row   = document.createElement('tr');
    row.id      = 'line-' + lineItemCount;

    const optionsHtml = inventoryItems.map(item =>
        `<option value="${item.id}" data-price="${item.cost_price}"
            ${String(item.id) === String(prefillItemId) ? 'selected' : ''}>
            ${item.code} - ${item.name}
        </option>`
    ).join('');

    row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" name="item_id[]" required onchange="updatePrice(this, ${lineItemCount})">
                <option value="">Select Item</option>
                ${optionsHtml}
            </select>
            <div class="cbm-fields mt-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-secondary text-white border-secondary">L</span>
                    <input type="number" step="0.0001" class="form-control cbm-length" name="cbm_length[]" value="0.0000" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                    <span class="input-group-text bg-secondary text-white border-secondary">W</span>
                    <input type="number" step="0.0001" class="form-control cbm-width" name="cbm_width[]" value="0.0000" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                    <span class="input-group-text bg-secondary text-white border-secondary">H</span>
                    <input type="number" step="0.0001" class="form-control cbm-height" name="cbm_height[]" value="0.0000" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                </div>
                <div class="mt-1">
                    <small class="text-info">Total CBM: <span class="cbm-total-display">0.0000</span></small>
                    <input type="hidden" class="cbm-total-input" name="cbm_total[]" value="0">
                </div>
            </div>
            <div class="manual-fields mt-2">
                <div class="input-group input-group-sm w-50">
                    <span class="input-group-text bg-secondary text-white border-secondary">Manual %</span>
                    <input type="number" step="0.01" max="100" class="form-control manual-pct" name="manual_pct[]" value="0.00" onchange="calculateTotal()" onkeyup="calculateTotal()">
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm quantity-input" name="quantity[]"
                   step="0.01" min="0.01" required value="${prefillQty || ''}"
                   onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm price-input" name="unit_price[]"
                   step="0.01" min="0" required value="${prefillPrice || ''}"
                   onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})" id="price-${lineItemCount}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm total-input" readonly id="total-${lineItemCount}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm landed-input bg-dark border-info text-info" name="landed_unit_cost[]" readonly id="landed-${lineItemCount}" value="0.0000">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem(${lineItemCount})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);

    // If pre-filling, update the line total immediately
    if (prefillQty && prefillPrice) {
        calculateLineTotal(lineItemCount);
    }
    
    // Update visibility of method fields
    calculateTotal();
}

function calcLineCBM(lineId) {
    const row = document.getElementById('line-' + lineId);
    if (!row) return;
    const l = parseFloat(row.querySelector('.cbm-length').value) || 0;
    const w = parseFloat(row.querySelector('.cbm-width').value) || 0;
    const h = parseFloat(row.querySelector('.cbm-height').value) || 0;
    const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
    
    // CBM per unit * qty
    const totalCbm = (l * w * h) * Math.max(qty, 1);
    
    row.querySelector('.cbm-total-input').value = totalCbm.toFixed(4);
    row.querySelector('.cbm-total-display').textContent = totalCbm.toFixed(4);
    calculateTotal();
}

function removeLineItem(id) {
    const row = document.getElementById('line-' + id);
    if (row) { row.remove(); calculateTotal(); }
}

function updatePrice(select, lineId) {
    const opt = select.options[select.selectedIndex];
    document.getElementById('price-' + lineId).value = parseFloat(opt.getAttribute('data-price') || 0).toFixed(2);
    calculateLineTotal(lineId);
    calcLineCBM(lineId);
}

function calculateLineTotal(lineId) {
    const row = document.getElementById('line-' + lineId);
    if (!row) return;
    const qty   = parseFloat(row.querySelector('.quantity-input').value) || 0;
    const price = parseFloat(row.querySelector('.price-input').value)    || 0;
    row.querySelector('.total-input').value = (qty * price).toFixed(2);
    calcLineCBM(lineId); // Auto recalculate cbm which depends on qty
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    let totalCbmAll = 0;
    let totalPctAll = 0;
    
    const method = document.getElementById('landed_cost_method').value;
    
    document.querySelectorAll('#lineItemsBody tr').forEach(row => {
        const qty   = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
        const price = parseFloat(row.querySelector('.price-input')?.value)    || 0;
        const cbm   = parseFloat(row.querySelector('.cbm-total-input')?.value) || 0;
        const pct   = parseFloat(row.querySelector('.manual-pct')?.value) || 0;
        
        subtotal += qty * price;
        totalCbmAll += cbm;
        totalPctAll += pct;
        
        // Toggle visibility of fields based on method
        const cbmFields = row.querySelector('.cbm-fields');
        const manFields = row.querySelector('.manual-fields');
        if (cbmFields) cbmFields.style.display = method === 'cbm' ? 'block' : 'none';
        if (manFields) manFields.style.display = method === 'manual' ? 'block' : 'none';
    });

    let taxAmount = 0;
    const taxSel = document.getElementById('tax_class_id');
    if (taxSel && taxSel.value) {
        const taxRate = parseFloat(taxSel.options[taxSel.selectedIndex].getAttribute('data-rate')) || 0;
        taxAmount = subtotal * taxRate;
    }

    const shippingCost  = parseFloat(document.getElementById('shipping_cost').value)  || 0;
    const exchangeRate  = parseFloat(document.getElementById('exchange_rate').value)  || 1;
    const convertedValue = subtotal * exchangeRate;

    const freight       = parseFloat(document.getElementById('freight').value) || 0;
    // User formula: (Invoice Value * Exchange Rate) + Freight Cost
    const convertedValueDisplay = convertedValue + freight;

    let localCosts = 0;
    document.querySelectorAll('.offset-local').forEach(el => {
        localCosts += parseFloat(el.value || 0);
    });

    const totalLandedCost   = convertedValue + localCosts;
    const totalGrand        = subtotal + taxAmount + shippingCost + localCosts;

    const currSel      = document.getElementById('currency_id');
    const sym          = currSel ? currSel.options[currSel.selectedIndex].getAttribute('data-symbol') || '$' : '$';

    document.getElementById('invoice_value_foreign').value = subtotal.toFixed(2);
    document.getElementById('converted_value').value       = convertedValueDisplay.toFixed(2);
    document.getElementById('subtotalDisplay').textContent = sym + subtotal.toFixed(2);
    document.getElementById('taxDisplay').textContent      = sym + taxAmount.toFixed(2);
    document.getElementById('totalDisplay').textContent    = sym + totalGrand.toFixed(2);
    document.getElementById('landed_cost').value           = totalLandedCost.toFixed(2);

    // Distribute Landed Cost (Additional Local Costs)
    document.querySelectorAll('#lineItemsBody tr').forEach(row => {
        const lineId = row.id.split('-')[1];
        const qty    = parseFloat(row.querySelector('.quantity-input')?.value) || 0;
        const total  = parseFloat(row.querySelector('.total-input')?.value)    || 0;
        const cbm    = parseFloat(row.querySelector('.cbm-total-input')?.value) || 0;
        const pct    = parseFloat(row.querySelector('.manual-pct')?.value) || 0;
        
        let unitLanded = 0;
        if (qty > 0) {
            const lineValueLocal = total * exchangeRate;
            let lineShare = 0;
            
            if (method === 'cbm') {
                lineShare = totalCbmAll > 0 ? (cbm / totalCbmAll) : 0;
            } else if (method === 'manual') {
                lineShare = (pct / 100);
            } else {
                // value
                lineShare = subtotal > 0 ? (total / subtotal) : 0;
            }
            
            const lineLanded = lineValueLocal + (lineShare * localCosts);
            unitLanded       = lineLanded / qty;
        }
        
        const landedInp = document.getElementById('landed-' + lineId);
        if (landedInp) landedInp.value = unitLanded.toFixed(4);
    });
}

function syncExchangeRate(input) {
    document.getElementById('exchange_rate').value = input.value;
    calculateTotal();
}

// FIX: DOMContentLoaded correctly calls addLineItem WITH the pre-fill arguments,
//      which the function now accepts and applies.
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('poForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const items = document.querySelectorAll('select[name="item_id[]"]');
            let hasItem = false;
            items.forEach(sel => {
                if (sel.value) hasItem = true;
            });

            if (!hasItem) {
                e.preventDefault();
                alert('Please fill at least one Line Item that field');
                return false;
            }
        });
    }

    const existingLines = <?= json_encode($existing_lines) ?>;
    if (existingLines.length > 0) {
        existingLines.forEach(line => {
            addLineItem(line.item_id, line.quantity, line.unit_price);
        });
    } else {
        addLineItem();
    }
    toggleExchangeRate();

    const warehouseSel = document.getElementById('warehouse_id');
    const binSel = document.getElementById('warehouse_bin_id');
    const selectedBinId = '<?= escape_html((string)post('warehouse_bin_id')) ?>';
    if (warehouseSel && binSel) {
        const loadBins = () => {
            const locId = warehouseSel.value;
            binSel.innerHTML = '<option value="">Select Bin (Optional)</option>';
            if (!locId) return;
            fetch('../ajax_get_bins.php?location_id=' + encodeURIComponent(locId))
                .then(r => r.json())
                .then(data => {
                    data.forEach(b => {
                        const o = document.createElement('option');
                        o.value = b.bin_id;
                        o.textContent = b.bin_location;
                        if (selectedBinId && String(b.bin_id) === String(selectedBinId)) {
                            o.selected = true;
                        }
                        binSel.appendChild(o);
                    });
                })
                .catch(() => {});
        };
        warehouseSel.addEventListener('change', loadBins);
        loadBins();
    }

    // Multi-file accumulation logic
    let selectedFiles = [];
    const attachmentsInput = document.getElementById('attachments');
    const fileListDiv = document.getElementById('fileList');

    if (attachmentsInput) {
        attachmentsInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                // Check if file already exists in our list to avoid duplicates
                const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                if (!exists) {
                    selectedFiles.push(file);
                }
            });
            renderFileList();
            updateInputFiles();
        });
    }

    function renderFileList() {
        if (!fileListDiv) return;
        if (selectedFiles.length === 0) {
            fileListDiv.style.display = 'none';
            return;
        }
        fileListDiv.style.display = 'block';
        fileListDiv.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center py-1';
            item.innerHTML = `
                <span class="text-white small text-truncate" style="max-width: 80%;">${file.name}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            fileListDiv.appendChild(item);
        });
    }

    window.removeFile = function(index) {
        selectedFiles.splice(index, 1);
        renderFileList();
        updateInputFiles();
    };

    function updateInputFiles() {
        if (!attachmentsInput) return;
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        attachmentsInput.files = dataTransfer.files;
    }
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
<!-- Add Currency Modal -->
<div class="modal fade" id="addCurrencyModal" tabindex="-1" aria-labelledby="addCurrencyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-white bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addCurrencyModalLabel">Add New Currency</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="currencyAlert" class="alert d-none"></div>
                <div class="mb-3">
                    <label for="new_curr_code" class="form-label">Currency Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="new_curr_code" placeholder="e.g. TOP" maxlength="3">
                </div>
                <div class="mb-3">
                    <label for="new_curr_name" class="form-label">Currency Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="new_curr_name" placeholder="e.g. Tongan Pa'anga">
                </div>
                <div class="mb-3">
                    <label for="new_curr_symbol" class="form-label">Symbol</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="new_curr_symbol" placeholder="e.g. T$">
                </div>
                <div class="mb-3">
                    <label for="new_curr_rate" class="form-label">Exchange Rate (vs USD)</label>
                    <input type="number" class="form-control bg-dark text-white border-secondary" id="new_curr_rate" step="0.000001" value="1.000000">
                    <small class="text-muted">How many USD for 1 unit of this currency? (or vice versa depending on your convention)</small>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNewCurrency()">Save Currency</button>
            </div>
        </div>
    </div>
</div>

<script>
function submitNewCurrency() {
    const code = document.getElementById('new_curr_code').value;
    const name = document.getElementById('new_curr_name').value;
    const symbol = document.getElementById('new_curr_symbol').value;
    const rate = document.getElementById('new_curr_rate').value;
    const alertBox = document.getElementById('currencyAlert');
    
    if (!code || !name) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'Code and Name are required.';
        alertBox.classList.remove('d-none');
        return;
    }

    const formData = new FormData();
    formData.append('code', code);
    formData.append('name', name);
    formData.append('symbol', symbol);
    formData.append('exchange_rate', rate);
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');

    fetch('ajax_add_currency.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const select = document.getElementById('currency_id');
            const label = `${data.code} - ${data.name} / ${data.symbol}`;
            const option = new Option(label, data.id, true, true);
            option.setAttribute('data-symbol', data.symbol);
            option.setAttribute('data-rate', data.rate);
            select.add(option);
            
            // Toggle exchange rate visibility
            toggleExchangeRate();
            
            const modal = bootstrap.Modal.getInstance(document.getElementById('addCurrencyModal'));
            modal.hide();
            
            // Reset
            document.getElementById('new_curr_code').value = '';
            document.getElementById('new_curr_name').value = '';
            document.getElementById('new_curr_symbol').value = '';
            alertBox.classList.add('d-none');
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = data.message || 'Error adding currency.';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'An unexpected error occurred.';
        alertBox.classList.remove('d-none');
    });
}
</script>
