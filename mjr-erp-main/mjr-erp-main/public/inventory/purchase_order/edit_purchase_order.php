<?php
/**
 * Edit Purchase Order
 * Modify existing purchase order (only for draft/sent status)
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Edit Purchase Order - MJR Group ERP';
$selected_company_id = active_company_id(1);

// Load tax classes for dropdown
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_code, tax_rate, tax_type FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('purchase_tax','both') ORDER BY tax_name");


// Get suppliers — scoped to the active company
$suppliers = db_fetch_all("SELECT id, supplier_code, name, contact_person, email, address FROM suppliers WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL) ORDER BY name", [$selected_company_id]);

// Get companies
$companies = is_super_admin()
    ? db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")
    : db_fetch_all("SELECT id, name FROM companies WHERE id = ? AND is_active = 1 ORDER BY name", [$selected_company_id]);

// Get managers for workflow
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$selected_company_id]);

// Get warehouses
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);

// Get active currencies
$currencies = db_fetch_all("SELECT id, code, name, symbol, exchange_rate, is_base FROM currencies WHERE is_active = 1 ORDER BY is_base DESC, code ASC");
$base_currency = db_fetch("SELECT * FROM currencies WHERE is_base = 1 AND is_active = 1 LIMIT 1");



$po_id = get_param('id');

if (!$po_id) {
    set_flash('Purchase order not found.', 'error');
    redirect('purchase_orders.php');
}

// Get PO details (with creator name)
$po = db_fetch("
    SELECT po.*, u.username as creator_name 
    FROM purchase_orders po
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ? AND po.company_id = ? AND po.status IN ('draft', 'sent', 'rejected', 'pending_approval')
", [$po_id, $selected_company_id]);


if (!$po) {
    set_flash('Purchase order not found or cannot be edited.', 'error');
    redirect('purchase_orders.php');
}

// Get currency symbol for initial display
$curr_symbol = '$';
foreach ($currencies as $c) {
    if ($c['id'] == $po['currency_id']) {
        $curr_symbol = $c['symbol'];
        break;
    }
}

// Get existing PO lines (prioritize POST data if submission failed)
$existing_lines = [];
if (is_post()) {
    $item_ids = post('item_id') ?: [];
    $quantities = post('quantity') ?: [];
    $unit_prices = post('unit_price') ?: [];
    
    foreach ($item_ids as $index => $item_id) {
        $qty = $quantities[$index] ?? '';
        $prc = $unit_prices[$index] ?? '';
        
        // Skip completely empty rows if any
        if (empty($item_id) && empty($qty) && (empty($prc) || $prc == "0")) continue;
        
        $existing_lines[] = [
            'item_id' => $item_id,
            'quantity' => $qty,
            'unit_price' => $prc,
            'landed_unit_cost' => post('landed_unit_cost')[$index] ?? 0,
            'has_error' => (empty($item_id) && (!empty($qty) || !empty($prc)))
        ];
    }
} else {
    // Initial load: get from DB
    $db_lines = db_fetch_all("SELECT * FROM purchase_order_lines WHERE po_id = ?", [$po_id]);
    foreach ($db_lines as $l) {
        $existing_lines[] = [
            'item_id' => $l['item_id'],
            'quantity' => $l['quantity'],
            'unit_price' => $l['unit_price'],
            'landed_unit_cost' => $l['landed_unit_cost'],
            'cbm_length' => $l['cbm_length'],
            'cbm_width' => $l['cbm_width'],
            'cbm_height' => $l['cbm_height'],
            'cbm_total' => $l['cbm_total'],
            'manual_pct' => $l['manual_pct'],
            'has_error' => false
        ];
    }
}

// Get existing attachments
$existing_attachments = db_fetch_all("SELECT * FROM purchase_order_attachments WHERE po_id = ?", [$po_id]);

// Handle form submission
$errors = [];
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $supplier_id = post('supplier_id');
            $supplier_contact = post('supplier_contact');
            $supplier_email = post('supplier_email');
            $supplier_address = post('supplier_address');
            $company_id = post('company_id');
            $purchase_type = post('purchase_type');
            $order_category = post('order_category');
            $reference_no = post('reference_no');
            $order_date = to_db_date(post('order_date'));
            $expected_delivery_date = to_db_date(post('expected_delivery_date'));
            $warehouse_id = post('warehouse_id');
            $warehouse_bin_id = post('warehouse_bin_id');
            $shipping_cost = floatval(post('shipping_cost') ?: 0);
            $freight = floatval(post('freight') ?: 0);
            $custom_duty = floatval(post('custom_duty') ?: 0);
            $customs_processing_fees = floatval(post('customs_processing_fees') ?: 0);
            $quarantine_fees = floatval(post('quarantine_fees') ?: 0);
            $excise_tax = floatval(post('excise_tax') ?: 0);
            $shipping_line_anl = floatval(post('shipping_line_anl') ?: 0);
            $brokerage = floatval(post('brokerage') ?: 0);
            $container_detention = floatval(post('container_detention') ?: 0);
            $bond_refund = floatval(post('bond_refund') ?: 0);
            $cartage = floatval(post('cartage') ?: 0);
            $inspection_fees = floatval(post('inspection_fees') ?: 0);
            $insurance = floatval(post('insurance') ?: 0);
            $other_charges = floatval(post('other_charges') ?: 0);
            $landed_cost = floatval(post('landed_cost') ?: 0);
            $landed_cost_method = post('landed_cost_method') ?: 'value';
            $tax_class_id = post('tax_class_id');
            $currency_id = post('currency_id');
            $status = $po['status'];
            // If it was rejected, editing it reverts it to draft
            if ($status === 'rejected') {
                $status = 'draft';
            }
            $notes = post('notes');
            $exchange_rate = floatval(post('exchange_rate') ?: 1.000000);
            $manager_id = post('manager_id');

            if (!empty($warehouse_bin_id)) {
                $bin_row = db_fetch("SELECT code FROM bins WHERE id = ?", [$warehouse_bin_id]);
                if ($bin_row && !empty($bin_row['code'])) {
                    $notes = trim(($notes ? $notes . "\n" : '') . 'Preferred Bin: ' . $bin_row['code']);
                }
            }

            $item_ids = post('item_id') ?: [];
            $quantities = post('quantity') ?: [];
            $unit_prices = post('unit_price') ?: [];
            $landed_costs = post('landed_unit_cost') ?: [];
            $cbm_lengths = post('cbm_length') ?: [];
            $cbm_widths = post('cbm_width') ?: [];
            $cbm_heights = post('cbm_height') ?: [];
            $cbm_totals = post('cbm_total') ?: [];
            $manual_pcts = post('manual_pct') ?: [];
            
            // Field validation
            if (empty($supplier_id)) $errors['supplier_id'] = 'Supplier is required';
            if (empty($order_date)) $errors['order_date'] = 'Order date is required';
            if (empty($manager_id)) $errors['manager_id'] = 'Manager selection is required';
            
            // Calculate totals and build line items first
            $subtotal = 0;
            $line_items = [];
            
            foreach ($item_ids as $index => $item_id) {
                $qty_raw = $quantities[$index] ?? '';
                $price_raw = $unit_prices[$index] ?? '';
                
                // If it's a completely empty row, just skip it
                if (empty($item_id) && empty($qty_raw) && (empty($price_raw) || $price_raw == "0.00" || $price_raw == "0")) {
                    continue;
                }
                
                $quantity = floatval($qty_raw);
                $unit_price = floatval($price_raw);
                $line_total = $quantity * $unit_price;
                
                if (!empty($item_id)) {
                    $subtotal += $line_total;
                    $line_items[] = [
                        'item_id' => $item_id,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'line_total' => $line_total,
                        'landed_unit_cost' => floatval($landed_costs[$index] ?? 0),
                        'cbm_length' => floatval($cbm_lengths[$index] ?? 0),
                        'cbm_width' => floatval($cbm_widths[$index] ?? 0),
                        'cbm_height' => floatval($cbm_heights[$index] ?? 0),
                        'cbm_total' => floatval($cbm_totals[$index] ?? 0),
                        'manual_pct' => floatval($manual_pcts[$index] ?? 0)
                    ];
                }
            }
            
            // Validate: must have at least one successfully processed line item
            if (empty($line_items)) {
                $errors['line_items'] = 'At least one Product must be selected and filled.';
            }

            if (!empty($errors)) {
                throw new Exception('Please correct the errors highlighted below.');
            }

            // Calculate tax from selected tax class
            $tax_amount = 0;
            if (!empty($tax_class_id)) {
                $tc = db_fetch("SELECT tax_rate FROM tax_configurations WHERE id = ? AND is_active = 1", [$tax_class_id]);
                if ($tc && !empty($tc['tax_rate'])) {
                    $tax_amount = $subtotal * floatval($tc['tax_rate']);
                }
            }

            $total_amount = $subtotal + $tax_amount + $shipping_cost + $freight + $custom_duty + $customs_processing_fees + $quarantine_fees + $excise_tax + $shipping_line_anl + $brokerage + $container_detention + $bond_refund + $cartage + $inspection_fees + $insurance + $other_charges;
            
            // Begin transaction with retry logic for connection stability
            $max_attempts = 3;
            for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
                try {
                    db_begin_transaction();
                    
                    db_query("
                        UPDATE purchase_orders
                        SET supplier_id = ?, supplier_contact = ?, supplier_email = ?, supplier_address = ?,
                            company_id = ?, purchase_type = ?, order_category = ?, reference_no = ?,
                            order_date = ?, expected_delivery_date = ?, warehouse_id = ?,
                            status = ?, subtotal = ?, tax_amount = ?, shipping_cost = ?, 
                            freight = ?, custom_duty = ?, customs_processing_fees = ?, quarantine_fees = ?, excise_tax = ?, shipping_line_anl = ?, brokerage = ?, container_detention = ?, bond_refund = ?, cartage = ?, inspection_fees = ?, insurance = ?, other_charges = ?, landed_cost = ?,
                            total_amount = ?, tax_class_id = ?, currency_id = ?, landed_cost_method = ?, notes = ?, exchange_rate = ?, manager_id = ?
                        WHERE id = ?
                    ", [
                        $supplier_id, $supplier_contact, $supplier_email, $supplier_address,
                        $company_id, $purchase_type, $order_category, $reference_no,
                        $order_date, $expected_delivery_date, $warehouse_id,
                        $status, $subtotal, $tax_amount, $shipping_cost,
                        $freight, $custom_duty, $customs_processing_fees, $quarantine_fees, $excise_tax, $shipping_line_anl, $brokerage, $container_detention, $bond_refund, $cartage, $inspection_fees, $insurance, $other_charges, $landed_cost,
                        $total_amount, $tax_class_id ?: null, $currency_id, $landed_cost_method, $notes, $exchange_rate, $manager_id,
                        $po_id
                    ]);
                    
                    // Delete existing lines
                    db_query("DELETE FROM purchase_order_lines WHERE po_id = ?", [$po_id]);
                    
                    // Insert new PO lines
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
                    
                    // Log history
                    log_po_history($po_id, $status, 'Purchase order details updated');

                    // Handle new attachments
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

                                $current_user = current_user();
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

                    db_commit();
                    
                    set_flash("Purchase Order updated successfully!", 'success');
                    redirect('view_purchase_order.php?id=' . $po_id);
                    break;

                } catch (Exception $e) {
                    db_rollback();
                    
                    $error_msg = strtolower($e->getMessage());
                    $is_retryable = (
                        strpos($error_msg, 'gone away') !== false || 
                        strpos($error_msg, 'lost connection') !== false ||
                        strpos($error_msg, 'deadlock') !== false ||
                        strpos($error_msg, 'child row') !== false
                    );
                    
                    if ($attempt < $max_attempts - 1 && $is_retryable) {
                        usleep(100000); // 100ms delay
                        continue;
                    }
                    
                    log_error("Error updating PO (Attempt " . ($attempt + 1) . "): " . $e->getMessage());
                    throw $e;
                }
            }

            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            log_error("Error updating PO: " . $error_message);
            set_flash($error_message, 'error');
        }
    }
}

// Get inventory items
$inventory_items = db_fetch_all("SELECT id, code, name, cost_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-edit me-2"></i>Edit Purchase Order: <?= escape_html($po['po_number']) ?></h2>
        </div>
        <div class="col-auto">
            <a href="purchase_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Purchase Orders
            </a>
        </div>
    </div>

    <form method="POST" id="poForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div class="row">
            <!-- Supplier Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-truck me-2 text-info"></i>Supplier Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="supplier_id" class="form-label fw-semibold">Supplier <span class="text-danger">*</span></label>
                            <select class="form-select select2 <?= isset($errors['supplier_id']) ? 'is-invalid' : '' ?>" id="supplier_id" name="supplier_id" required onchange="onSupplierChange(this)">
                                <option value="">Select Supplier</option>
                                <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" 
                                        <?= $po['supplier_id'] == $supplier['id'] ? 'selected' : '' ?>
                                        data-contact="<?= escape_html($supplier['contact_person']) ?>"
                                        data-email="<?= escape_html($supplier['email']) ?>"
                                        data-address="<?= escape_html($supplier['address']) ?>">
                                    <?= escape_html($supplier['supplier_code']) ?> - <?= escape_html($supplier['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="supplier_contact" class="form-label">Supplier Contact</label>
                            <input type="text" class="form-control" id="supplier_contact" name="supplier_contact" value="<?= escape_html($po['supplier_contact'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="supplier_email" class="form-label">Supplier Email</label>
                            <input type="email" class="form-control" id="supplier_email" name="supplier_email" value="<?= escape_html($po['supplier_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="supplier_address" class="form-label">Supplier Address</label>
                            <textarea class="form-control" id="supplier_address" name="supplier_address" rows="3"><?= escape_html($po['supplier_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Order Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="order_date" class="form-label">Order Date <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['order_date']) ? 'is-invalid' : '' ?>" id="order_date" name="order_date" value="<?= post('order_date') ?: format_date($po['order_date']) ?>" placeholder="DD-MM-YYYY" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expected_delivery_date" class="form-label">Expected Delivery</label>
                                <input type="text" class="form-control" id="expected_delivery_date" name="expected_delivery_date" value="<?= post('expected_delivery_date') ?: format_date($po['expected_delivery_date']) ?>" placeholder="DD-MM-YYYY">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_id" class="form-label">Company</label>
                                <div class="input-group">
                                    <?php if (!empty($_SESSION['company_id'])): ?>
                                        <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                        <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                    <?php else: ?>
                                        <select class="form-select" id="company_id" name="company_id">
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $company): ?>
                                            <option value="<?= $company['id'] ?>" <?= ($po['company_id'] ?? '') == $company['id'] ? 'selected' : '' ?>><?= escape_html($company['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <a href="../../companies/index.php" class="btn btn-outline-primary" title="Create New Company">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="warehouse_id" class="form-label">Warehouse Location</label>
                                <select class="form-select" id="warehouse_id" name="warehouse_id">
                                    <option value="">Select Warehouse</option>
                                    <?php foreach ($warehouses as $warehouse): ?>
                                    <option value="<?= $warehouse['id'] ?>" <?= ($po['warehouse_id'] ?? '') == $warehouse['id'] ? 'selected' : '' ?>><?= escape_html($warehouse['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                <label for="purchase_type" class="form-label">Purchase Type</label>
                                <select class="form-select" id="purchase_type" name="purchase_type" onchange="toggleExchangeRate()">
                                    <option value="Local" <?= ($po['purchase_type'] ?? '') == 'Local' ? 'selected' : '' ?>>Local</option>
                                    <option value="International" <?= ($po['purchase_type'] ?? '') == 'International' ? 'selected' : '' ?>>International</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="currency_id" class="form-label">Currency</label>
                                <select class="form-select" id="currency_id" name="currency_id" onchange="updateCurrencyInfo()">
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
                                            <?= ($po['currency_id'] ?? '') == $curr['id'] ? 'selected' : (($curr['code'] === 'FJD' || $curr['is_base']) && !($po['currency_id'] ?? '') ? 'selected' : '') ?>>
                                        <?= escape_html($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3" id="exchange_rate_container" style="display: <?= ($po['purchase_type'] ?? '') == 'International' ? 'block' : 'none' ?>;">
                                <label for="exchange_rate" class="form-label">Exchange Rate</label>
                                <input type="number" class="form-control" id="exchange_rate" name="exchange_rate" step="0.000001" value="<?= number_format($po['exchange_rate'] ?? 1, 6, '.', '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="landed_cost_method" class="form-label">Landed Cost Distribution Method</label>
                                <select class="form-select" id="landed_cost_method" name="landed_cost_method" onchange="calculateTotal()">
                                    <option value="value" <?= ($po['landed_cost_method'] ?? 'value') === 'value' ? 'selected' : '' ?>>Cost / Invoice Value</option>
                                    <option value="cbm" <?= ($po['landed_cost_method'] ?? '') === 'cbm' ? 'selected' : '' ?>>CBM - Meters (Volume)</option>
                                    <option value="manual" <?= ($po['landed_cost_method'] ?? '') === 'manual' ? 'selected' : '' ?>>Manual Percentage</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Line Items - SYNCED STRUCTURE -->
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold">Line Items</h5>
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
                        <tbody id="lineItemsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <!-- International Cost restored -->
                <div id="internationalSection" class="card mb-4 shadow-sm border-0 premium-card" style="display: <?= ($po['purchase_type'] ?? '') == 'International' ? 'block' : 'none' ?>;">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-globe me-2 text-info"></i>International Cost Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row border-bottom border-secondary pb-3 mb-3">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Invoice Value (Foreign)</label>
                                <input type="number" class="form-control bg-dark text-white" id="invoice_value_foreign" value="<?= number_format($po['subtotal'] ?? 0, 2, '.', '') ?>" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="exchange_rate_intl" class="form-label">Exchange Rate</label>
                                <input type="number" class="form-control" id="exchange_rate_intl" step="0.000001" value="<?= number_format($po['exchange_rate'] ?? 1, 6, '.', '') ?>" onchange="syncExchangeRate(this)" onkeyup="syncExchangeRate(this)">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Converted Value (Base)</label>
                                <input type="number" class="form-control bg-dark text-success" id="converted_value" value="0.00" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Freight Cost</label>
                                <input type="number" class="form-control text-success fw-bold offset-local" id="freight" name="freight" step="0.01" value="<?= number_format($po['freight'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                        </div>

                        <h6 class="text-info fw-bold mb-3 px-2">All the below is in Local Currency</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="custom_duty" class="form-label">Custom import duty</label>
                                <input type="number" class="form-control offset-local" id="custom_duty" name="custom_duty" step="0.01" value="<?= number_format($po['custom_duty'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="customs_processing_fees" class="form-label">Customs processing fees</label>
                                <input type="number" class="form-control offset-local" id="customs_processing_fees" name="customs_processing_fees" step="0.01" value="<?= number_format($po['customs_processing_fees'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="quarantine_fees" class="form-label">Quarantine fees</label>
                                <input type="number" class="form-control offset-local" id="quarantine_fees" name="quarantine_fees" step="0.01" value="<?= number_format($po['quarantine_fees'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="excise_tax" class="form-label">Excise tax</label>
                                <input type="number" class="form-control offset-local" id="excise_tax" name="excise_tax" step="0.01" value="<?= number_format($po['excise_tax'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="shipping_line_anl" class="form-label">Shipping Line - ANL</label>
                                <input type="number" class="form-control offset-local" id="shipping_line_anl" name="shipping_line_anl" step="0.01" value="<?= number_format($po['shipping_line_anl'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="brokerage" class="form-label">Brokerage</label>
                                <input type="number" class="form-control offset-local" id="brokerage" name="brokerage" step="0.01" value="<?= number_format($po['brokerage'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="container_detention" class="form-label">Container Detention</label>
                                <input type="number" class="form-control offset-local" id="container_detention" name="container_detention" step="0.01" value="<?= number_format($po['container_detention'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="bond_refund" class="form-label">Bond refund</label>
                                <input type="number" class="form-control offset-local" id="bond_refund" name="bond_refund" step="0.01" value="<?= number_format($po['bond_refund'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label for="cartage" class="form-label">Cartage</label>
                                <input type="number" class="form-control offset-local" id="cartage" name="cartage" step="0.01" value="<?= number_format($po['cartage'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="inspection_fees" class="form-label">Inspection fees - Transport</label>
                                <input type="number" class="form-control offset-local" id="inspection_fees" name="inspection_fees" step="0.01" value="<?= number_format($po['inspection_fees'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="insurance" class="form-label">Insurance Cost</label>
                                <input type="number" class="form-control offset-local" id="insurance" name="insurance" step="0.01" value="<?= number_format($po['insurance'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="other_charges" class="form-label">Other Charges</label>
                                <input type="number" class="form-control offset-local" id="other_charges" name="other_charges" step="0.01" value="<?= number_format($po['other_charges'] ?? 0, 2, '.', '') ?>" onchange="calculateTotal()" onkeyup="calculateTotal()">
                            </div>

                            <div class="col-md-4 mb-3 mt-3 offset-md-8">
                                <label class="form-label text-primary">Total Landed Cost <small class="text-muted">(Local Currency)</small></label>
                                <input type="number" class="form-control bg-dark border-primary fw-bold text-primary fs-5" id="landed_cost" name="landed_cost" step="0.01" value="<?= number_format($po['landed_cost'] ?? 0, 2, '.', '') ?>" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-paperclip me-2 text-info"></i>Attachments | Notes</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="attachments" class="form-label">Upload New Attachments</label>
                                <input type="file" class="form-control" id="attachments" name="attachments[]" multiple>
                                <small class="text-muted">You can upload multiple files (Invoice, Delivery Note, etc.)</small>
                                <div id="newFileList" class="mt-2 list-group list-group-flush border border-secondary rounded" style="display:none;"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?= escape_html($po['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <?php if (!empty($existing_attachments)): ?>
                            <hr class="my-4">
                            <h6 class="fw-bold mb-3">Existing Attachments</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>File Name</th>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($existing_attachments as $file): ?>
                                            <tr>
                                                <td><?= escape_html($file['file_name']) ?></td>
                                                <td><?= strtoupper(pathinfo($file['file_name'], PATHINFO_EXTENSION)) ?></td>
                                                <td><?= round($file['file_size'] / 1024, 2) ?> KB</td>
                                                <td>
                                                    <a href="../../../public/<?= $file['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold">Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless mb-0">
                            <tr><td>Subtotal</td><td class="text-end"><span id="subtotalDisplay">$0.00</span></td></tr>
                            <tr><td>Tax Class</td><td class="text-end">
                                <select class="form-select form-select-sm" id="tax_class_id" name="tax_class_id" onchange="calculateTotal()">
                                    <option value="">No Tax (0%)</option>
                                    <?php foreach ($tax_classes as $tax): ?>
                                    <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['tax_rate'] ?>" <?= ($po['tax_class_id'] ?? '') == $tax['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($tax['tax_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <span id="taxDisplay" class="small text-muted">$0.00</span>
                            </td></tr>
                            <tr><td>Shipping</td><td class="text-end">
                                <input type="number" class="form-control form-control-sm text-end" id="shipping_cost" name="shipping_cost" value="<?= $po['shipping_cost'] ?? '0.00' ?>" step="0.01" onchange="calculateTotal()">
                            </td></tr>
                            <tr class="border-top"><td><h5 class="fw-bold">Grand Total</h5></td><td class="text-end"><h5 class="fw-bold text-primary" id="totalDisplay">$0.00</h5></td></tr>
                        </table>
                        
                        <div class="mt-4">
                            <label class="form-label small fw-bold">Approval Workflow Manager *</label>
                            <select name="manager_id" class="form-select" required>
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $po['manager_id'] == $m['id'] ? 'selected' : '' ?>>
                                    <?= escape_html($m['full_name'] ?: $m['username']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2 mb-5">
                    <button type="submit" class="btn btn-primary btn-lg shadow"><i class="fas fa-save me-2"></i>Update Purchase Order</button>
                    <a href="view_purchase_order.php?id=<?= $po_id ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const inventoryItems = <?= json_encode($inventory_items) ?>;
const existingLines = <?= json_encode($existing_lines ?? []) ?>;
let lineItemCount = 0;

function onSupplierChange(select) {
    if (!select || select.selectedIndex < 0) return;
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption && selectedOption.value) {
        document.getElementById('supplier_contact').value = selectedOption.getAttribute('data-contact') || '';
        document.getElementById('supplier_email').value = selectedOption.getAttribute('data-email') || '';
        document.getElementById('supplier_address').value = selectedOption.getAttribute('data-address') || '';
    }
}

function updateCurrencyInfo() {
    const currencySelect = document.getElementById('currency_id');
    const selectedOption = currencySelect.options[currencySelect.selectedIndex];
    const currencySymbol = selectedOption.getAttribute('data-symbol') || '$';
    const rate = selectedOption.getAttribute('data-rate') || 1;
    const purchaseType = document.getElementById('purchase_type').value;

    if (purchaseType === 'International') {
        document.getElementById('exchange_rate').value = parseFloat(rate).toFixed(6);
    } else {
        document.getElementById('exchange_rate').value = '1.000000';
    }
    calculateTotal();
}

function syncExchangeRate(input) {
    const val = input.value;
    document.getElementById('exchange_rate').value = val;
    const intl = document.getElementById('exchange_rate_intl');
    if (intl && intl !== input) intl.value = val;
    calculateTotal();
}

function toggleExchangeRate() {
    const purchaseType = document.getElementById('purchase_type').value;
    const container = document.getElementById('exchange_rate_container');
    const internationalSection = document.getElementById('internationalSection');
    
    if (purchaseType === 'International') {
        if (container) container.style.display = 'block';
        if (internationalSection) internationalSection.style.display = 'block';
    } else {
        if (container) container.style.display = 'none';
        if (internationalSection) internationalSection.style.display = 'none';
        document.querySelectorAll('.offset-local').forEach(el => el.value = '0.00');
        document.getElementById('landed_cost').value = '0.00';
    }
    calculateTotal();
}

function addLineItem(itemId = '', quantity = '', unitPrice = '', landedCost = '0.0000', cbmLen='0.0000', cbmWid='0.0000', cbmHgt='0.0000', cbmTot='0.0000', manPct='0.00') {
    lineItemCount++;
    const tbody = document.getElementById('lineItemsBody');
    const row = document.createElement('tr');
    row.id = 'line-' + lineItemCount;
    
    row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" name="item_id[]" required onchange="updatePrice(this, ${lineItemCount})">
                <option value="">Select Item</option>
                ${inventoryItems.map(item => `
                    <option value="${item.id}" data-price="${item.cost_price}" ${item.id == itemId ? 'selected' : ''}>
                        ${item.code} - ${item.name}
                    </option>`).join('')}
            </select>
            <div class="cbm-fields mt-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-secondary text-white border-secondary">L</span>
                    <input type="number" step="0.0001" class="form-control cbm-length" name="cbm_length[]" value="${cbmLen}" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                    <span class="input-group-text bg-secondary text-white border-secondary">W</span>
                    <input type="number" step="0.0001" class="form-control cbm-width" name="cbm_width[]" value="${cbmWid}" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                    <span class="input-group-text bg-secondary text-white border-secondary">H</span>
                    <input type="number" step="0.0001" class="form-control cbm-height" name="cbm_height[]" value="${cbmHgt}" onchange="calcLineCBM(${lineItemCount})" onkeyup="calcLineCBM(${lineItemCount})">
                </div>
                <div class="mt-1">
                    <small class="text-info">Total CBM: <span class="cbm-total-display">${Number(cbmTot).toFixed(4)}</span></small>
                    <input type="hidden" class="cbm-total-input" name="cbm_total[]" value="${cbmTot}">
                </div>
            </div>
            <div class="manual-fields mt-2">
                <div class="input-group input-group-sm w-50">
                    <span class="input-group-text bg-secondary text-white border-secondary">Manual %</span>
                    <input type="number" step="0.01" max="100" class="form-control manual-pct" name="manual_pct[]" value="${manPct}" onchange="calculateTotal()" onkeyup="calculateTotal()">
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm quantity-input" name="quantity[]" 
                   value="${quantity}" step="0.01" min="0.01" required onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})">
        </td>
        <td>
            <input type="number" class="form-control form-control-sm price-input" name="unit_price[]" 
                   value="${unitPrice}" step="0.01" min="0" required onchange="calculateLineTotal(${lineItemCount})" onkeyup="calculateLineTotal(${lineItemCount})" id="price-${lineItemCount}">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm total-input" readonly id="total-${lineItemCount}" value="0.00">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm landed-input bg-dark text-info border-info" name="landed_unit_cost[]" readonly id="landed-${lineItemCount}" value="${landedCost}">
        </td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLineItem(${lineItemCount})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(row);
    if (itemId) calculateLineTotal(lineItemCount);
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
    const price = opt.getAttribute('data-price') || 0;
    document.getElementById('price-' + lineId).value = parseFloat(price).toFixed(2);
    calculateLineTotal(lineId);
    calcLineCBM(lineId);
}

function calculateLineTotal(lineId) {
    const row = document.getElementById('line-' + lineId);
    if (!row) return;
    const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
    const prc = parseFloat(row.querySelector('.price-input').value) || 0;
    const total = qty * prc;
    const symbol = document.getElementById('currency_id').options[document.getElementById('currency_id').selectedIndex].getAttribute('data-symbol') || '$';
    row.querySelector('.total-input').value = symbol + total.toFixed(2);
    calcLineCBM(lineId);
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    let totalCbmAll = 0;
    let totalPctAll = 0;
    const method = document.getElementById('landed_cost_method')?.value || 'value';

    document.querySelectorAll('#lineItemsBody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const prc = parseFloat(row.querySelector('.price-input').value) || 0;
        const cbm = parseFloat(row.querySelector('.cbm-total-input')?.value) || 0;
        const pct = parseFloat(row.querySelector('.manual-pct')?.value) || 0;
        
        subtotal += qty * prc;
        totalCbmAll += cbm;
        totalPctAll += pct;

        const cbmFields = row.querySelector('.cbm-fields');
        const manFields = row.querySelector('.manual-fields');
        if (cbmFields) cbmFields.style.display = method === 'cbm' ? 'block' : 'none';
        if (manFields) manFields.style.display = method === 'manual' ? 'block' : 'none';
    });

    let taxAmount = 0;
    const taxSel = document.getElementById('tax_class_id');
    if (taxSel && taxSel.value) {
        const rate = parseFloat(taxSel.options[taxSel.selectedIndex].getAttribute('data-rate')) || 0;
        taxAmount = subtotal * rate;
    }

    const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
    const exchangeRate = parseFloat(document.getElementById('exchange_rate').value) || 1;
    
    if (document.getElementById('invoice_value_foreign')) document.getElementById('invoice_value_foreign').value = subtotal.toFixed(2);

    const convertedValue = subtotal * exchangeRate;
    const freight        = parseFloat(document.getElementById('freight')?.value) || 0;
    // User formula: (Invoice Value * Exchange Rate) + Freight Cost
    const convertedValueDisplay = convertedValue + freight;
    
    let localCosts = 0;
    document.querySelectorAll('.offset-local').forEach(el => {
        localCosts += parseFloat(el.value || 0);
    });

    const totalLandedCost = convertedValue + localCosts;
    const totalGrand = subtotal + taxAmount + shipping + localCosts;

    const convertedEl = document.getElementById('converted_value');
    if (convertedEl) convertedEl.value = convertedValueDisplay.toFixed(2);
    const landedCostEl = document.getElementById('landed_cost');
    if (landedCostEl) landedCostEl.value = totalLandedCost.toFixed(2);

    document.querySelectorAll('#lineItemsBody tr').forEach(row => {
        const lineId = row.id.split('-')[1];
        const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const lineTotalRaw = row.querySelector('.total-input').value.replace(/[^0-9.-]+/g, "");
        const totalLine = parseFloat(lineTotalRaw) || 0;
        const cbm = parseFloat(row.querySelector('.cbm-total-input')?.value) || 0;
        const pct = parseFloat(row.querySelector('.manual-pct')?.value) || 0;
        
        let unitLanded = 0;
        if (qty > 0) {
            const lineValueLocal = totalLine * exchangeRate;
            let lineShare = 0;
            
            if (method === 'cbm') {
                lineShare = totalCbmAll > 0 ? (cbm / totalCbmAll) : 0;
            } else if (method === 'manual') {
                lineShare = (pct / 100);
            } else {
                lineShare = subtotal > 0 ? (totalLine / subtotal) : 0;
            }
            
            const lineLandedValueLocal = lineValueLocal + (lineShare * localCosts);
            unitLanded = lineLandedValueLocal / qty;
        }
        
        const landedInp = document.getElementById('landed-' + lineId);
        if (landedInp) landedInp.value = unitLanded.toFixed(4);
    });

    const symbol = document.getElementById('currency_id').options[document.getElementById('currency_id').selectedIndex]?.getAttribute('data-symbol') || '$';
    const stEl = document.getElementById('subtotalDisplay'); if (stEl) stEl.textContent = symbol + subtotal.toFixed(2);
    const txEl = document.getElementById('taxDisplay'); if (txEl) txEl.textContent = symbol + taxAmount.toFixed(2);
    const totEl = document.getElementById('totalDisplay'); if (totEl) totEl.textContent = symbol + totalGrand.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    if (existingLines.length > 0) {
        existingLines.forEach(line => addLineItem(line.item_id, line.quantity, line.unit_price, line.landed_unit_cost, line.cbm_length, line.cbm_width, line.cbm_height, line.cbm_total, line.manual_pct));
    } else {
        addLineItem();
    }
    calculateTotal();
    toggleExchangeRate();
    onSupplierChange(document.getElementById('supplier_id'));
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
    const fileListDiv = document.getElementById('newFileList');

    if (attachmentsInput) {
        attachmentsInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
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
        fileListDiv.innerHTML = '<div class="list-group-item bg-light py-1 small fw-bold">New files to upload:</div>';
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'list-group-item bg-white d-flex justify-content-between align-items-center py-1';
            item.innerHTML = `
                <span class="text-dark small text-truncate" style="max-width: 80%;">${file.name}</span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeNewFile(${index})">
                    <i class="fas fa-times"></i>
                </button>
            `;
            fileListDiv.appendChild(item);
        });
    }

    window.removeNewFile = function(index) {
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
