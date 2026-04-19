<?php
/**
 * Add Sales Order Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';
require_once __DIR__ . '/../../includes/project_service.php';

require_login();
require_permission('manage_sales');

$page_title = 'Add Sales Order - MJR Group ERP';
$company_id = active_company_id(1);

// Get customers for dropdown
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get inventory items for dropdown
$items = db_fetch_all("SELECT id, code, name, selling_price, cost_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get active tax classes for dropdown
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_code, tax_rate, tax_type FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('sales_tax', 'both') ORDER BY tax_name");

// Get companies for dropdown
$companies = is_super_admin()
    ? db_fetch_all("SELECT id, name AS company_name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$company_id])
    : db_fetch_all("SELECT id, name AS company_name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$company_id]);

// Pre-select customer if coming from customer view
$selected_customer = get('customer_id');

// Handle Quote → Order conversion
$from_quote_id = get('from_quote');
$quote_data = null;
$quote_items = [];

if ($from_quote_id) {
    $quote_data = db_fetch("SELECT * FROM quotes WHERE id = ? AND company_id = ?", [$from_quote_id, $company_id]);
    if ($quote_data) {
        $selected_customer = $quote_data['customer_id'];
        $quote_items = db_fetch_all("SELECT * FROM quote_lines WHERE quote_id = ?", [$from_quote_id]);
    }
}

// Get locations for dropdown
$locations = db_fetch_all("SELECT id, code, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        try {
            $customer_id = post('customer_id');
            $location_id = post('location_id');
            $bin_id = !empty(post('bin_id')) ? intval(post('bin_id')) : null;
            $order_date = post('order_date');
            $required_date = post('required_date');
            $status = post('status', 'draft');
            $tax_class_id = post('tax_class_id'); // Selected tax class
            $notes = post('notes');
            $custom_fields = post('custom_fields');
            $manual_discount = !empty(post('manual_discount')) ? intval(post('manual_discount')) : null;

            // Get line items
            $item_ids = post('item_id', []);
            $descriptions = post('description', []);
            $quantities = post('quantity', []);
            $unit_prices = post('unit_price', []);
            $discount_percents = post('discount_percent', []);
            $order_discount_amount = floatval(post('order_discount_amount', 0));
            $payment_status = post('payment_status', 'unpaid');
            $payment_method = post('payment_method');
            $payment_currency = post('payment_currency', 'USD');
            $payment_date = !empty(post('payment_date')) ? post('payment_date') : null;
            
            // New Project Fields
            $sale_type = post('sale_type', 'normal');
            $project_id = post('project_id');
            $project_stage_id = post('project_stage_id');
            $is_new_project = post('is_new_project') === '1';

            $errors = [];
            if (empty($customer_id)) $errors['customer_id'] = err_required();
            if (empty($order_date))  $errors['order_date']  = err_required();
            if (empty($location_id)) $errors['location_id'] = err_required();

            if (empty($item_ids)) {
                $errors['items'] = 'Please fill at least one Item that field';
            } else {
                $has_items = false;
                foreach ($item_ids as $item_id) {
                    if (!empty($item_id)) {
                        $has_items = true;
                        break;
                    }
                }
                if (!$has_items) {
                    $errors['items'] = 'Please fill at least one Item that field';
                }
            }

            if (!empty($errors)) {
                $error = "Please fix the validation errors.";
            } else {

            // Calculate subtotal
            $subtotal = 0;

            for ($i = 0; $i < count($item_ids); $i++) {
                if (!empty($item_ids[$i])) {
                    $qty = floatval($quantities[$i]);
                    $price = floatval($unit_prices[$i]);
                    $discount_pct = floatval($discount_percents[$i] ?? 0);

                    $line_total = $qty * $price;
                    if ($discount_pct > 0) {
                        $line_total = $line_total * (1 - ($discount_pct / 100));
                    }
                    $subtotal += $line_total;
                }
            }

            // Calculate tax based on selected tax class (transaction-level)
            $tax_amount = 0;
            if (!empty($tax_class_id)) {
                $tax_class = db_fetch("
                    SELECT tax_rate 
                    FROM tax_configurations 
                    WHERE id = ? AND is_active = 1
                ", [$tax_class_id]);

                if ($tax_class && !empty($tax_class['tax_rate'])) {
                    $tax_amount = ($subtotal - $order_discount_amount) * floatval($tax_class['tax_rate']);
                }
            }

            $total_amount = ($subtotal - $order_discount_amount) + $tax_amount;

            // Generate order number
            $last_order = db_fetch("SELECT order_number FROM sales_orders ORDER BY id DESC LIMIT 1");
            if ($last_order && preg_match('/SO-(\d+)/', $last_order['order_number'], $matches)) {
                $next_num = intval($matches[1]) + 1;
            } else {
                $next_num = 1;
            }
            $order_number = 'SO-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

            // Start transaction
            db_begin_transaction();

            // Handle Project Creation if new project
            $sale_type = post('sale_type', 'normal');
            $project_id = null;
            $project_stage_id = null;

            if ($sale_type === 'project') {
                $project_result = project_create_with_stages($_POST, $_SESSION['user_id']);
                if (is_array($project_result)) {
                    $project_id = $project_result['project_id'];
                    $stage_idx = post('project_stage_id'); // Phase index 1, 2, 3...
                    if ($stage_idx && isset($project_result['stage_ids'][$stage_idx - 1])) {
                        $project_stage_id = $project_result['stage_ids'][$stage_idx - 1];
                    }
                }
            }

            // Normalize optional fields before inserting into sales_orders
            $order_company = post('company_id') ?: $_SESSION['company_id'];
            $order_company = is_numeric($order_company) ? intval($order_company) : ($_SESSION['company_id'] ?? null);
            $required_date = trim((string)$required_date) !== '' ? $required_date : null;
            $payment_date = trim((string)$payment_date) !== '' ? $payment_date : null;
            $tax_class_id = trim((string)$tax_class_id) !== '' ? intval($tax_class_id) : null;
            $order_company = !empty($order_company) ? intval($order_company) : null;
            $project_id = trim((string)$project_id) !== '' ? intval($project_id) : null;
            $project_stage_id = trim((string)$project_stage_id) !== '' ? intval($project_stage_id) : null;
            $manual_discount = !empty($manual_discount) ? intval($manual_discount) : null;

            // Build sales_orders insert data dynamically to match the current DB schema
            $order_columns = ['order_number', 'customer_id', 'order_date'];
            $order_values = [$order_number, $customer_id, $order_date];

            $optional_order_fields = [
                'location_id' => $location_id,
                'bin_id' => $bin_id,
                'required_date' => $required_date,
                'status' => $status,
                'payment_status' => $payment_status,
                'payment_method' => $payment_method,
                'payment_currency' => $payment_currency,
                'payment_date' => $payment_date,
                'subtotal' => $subtotal,
                'discount_amount' => $order_discount_amount,
                'tax_amount' => $tax_amount,
                'total_amount' => $total_amount,
                'tax_class_id' => $tax_class_id,
                'notes' => trim((string)$notes) !== '' ? $notes : null,
                'custom_fields' => trim((string)$custom_fields) !== '' ? $custom_fields : null,
                'manual_discount' => $manual_discount,
                'sale_type' => $sale_type,
                'project_id' => $project_id,
                'project_stage_id' => $project_stage_id,
                'created_by' => $_SESSION['user_id'],
                'company_id' => $order_company,
            ];

            foreach ($optional_order_fields as $column => $value) {
                if (db_table_has_column('sales_orders', $column)) {
                    $order_columns[] = $column;
                    $order_values[] = $value;
                }
            }

            $order_placeholders = implode(', ', array_fill(0, count($order_columns), '?'));
            $order_sql = sprintf(
                'INSERT INTO sales_orders (%s) VALUES (%s)',
                implode(', ', $order_columns),
                $order_placeholders
            );
            $order_id = db_insert($order_sql, $order_values);

            // Insert order lines with schema-aware columns
            $line_columns = ['order_id', 'item_id', 'quantity', 'unit_price', 'line_total'];

            $use_description = db_table_has_column('sales_order_lines', 'description');
            $use_notes = db_table_has_column('sales_order_lines', 'notes');
            $use_discount_percent = db_table_has_column('sales_order_lines', 'discount_percent');
            $use_tax_rate = db_table_has_column('sales_order_lines', 'tax_rate');

            if ($use_description) {
                array_splice($line_columns, 2, 0, 'description');
            } elseif ($use_notes) {
                array_splice($line_columns, 2, 0, 'notes');
            }
            if ($use_discount_percent) {
                $line_columns[] = 'discount_percent';
            }
            if ($use_tax_rate) {
                $line_columns[] = 'tax_rate';
            }

            $line_placeholders = implode(', ', array_fill(0, count($line_columns), '?'));
            $line_sql = sprintf('INSERT INTO sales_order_lines (%s) VALUES (%s)', implode(', ', $line_columns), $line_placeholders);

            $tax_rate_value = 0;
            if (!empty($tax_class_id)) {
                $tax_class = db_fetch("SELECT tax_rate FROM tax_configurations WHERE id = ? AND is_active = 1", [$tax_class_id]);
                $tax_rate_value = $tax_class ? floatval($tax_class['tax_rate']) : 0;
            }

            for ($i = 0; $i < count($item_ids); $i++) {
                if (!empty($item_ids[$i])) {
                    $desc = $descriptions[$i] ?? null;
                    if (is_string($desc) && trim($desc) === '') {
                        $desc = null;
                    }
                    $qty = floatval($quantities[$i]);
                    $price = floatval($unit_prices[$i]);
                    $discount_pct = null;
                    if ($use_discount_percent) {
                        $discount_pct = trim((string)($discount_percents[$i] ?? '')) !== '' ? floatval($discount_percents[$i]) : null;
                    }

                    $line_total = $qty * $price;
                    if ($discount_pct !== null && $discount_pct > 0) {
                        $line_total = $line_total * (1 - ($discount_pct / 100));
                    }

                    $line_insert_values = [$order_id, intval($item_ids[$i]), $qty, $price, $line_total];
                    if ($use_description || $use_notes) {
                        $line_insert_values = array_merge(
                            array_slice($line_insert_values, 0, 2),
                            [$desc],
                            array_slice($line_insert_values, 2)
                        );
                    }
                    if ($use_discount_percent) {
                        $line_insert_values[] = $discount_pct;
                    }
                    if ($use_tax_rate) {
                        $line_insert_values[] = $tax_rate_value;
                    }

                    db_insert($line_sql, $line_insert_values);
                }
            }

            // Auto-sync inventory movements when order is shipped/delivered.
            inventory_sync_sales_order_movements(
                intval($order_id),
                $status,
                intval($customer_id),
                intval($_SESSION['user_id']),
                $order_number,
                $location_id
            );

            // Update original quote if conversion
            if ($from_quote_id) {
                db_query("UPDATE quotes SET status = 'converted', converted_to_order_id = ? WHERE id = ?", [$order_id, $from_quote_id]);
            }

                db_commit();

                set_flash('Sales order created successfully!', 'success');
                redirect('view_order.php?id=' . $order_id);
            }
        } catch (Exception $e) {
            db_rollback();
            log_error("Error creating order: " . $e->getMessage());
            set_flash('Error creating order: ' . $e->getMessage(), 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-plus me-2"></i>Add Sales Order</h2>
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Order Information</h5>
                                <div class="btn-group" role="group">
                                    <input type="radio" class="btn-check" name="term" id="term_cash" value="cash" autocomplete="off" checked>
                                    <label class="btn btn-outline-warning" for="term_cash">Cash</label>

                                    <input type="radio" class="btn-check" name="term" id="term_credit" value="credit" autocomplete="off">
                                    <label class="btn btn-outline-info" for="term_credit">Credit</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>" 
                                               id="customer_name" name="customer_name" list="customer_list" 
                                               value="<?= $selected_customer ? escape_html(db_fetch("SELECT name FROM customers WHERE id = ?", [$selected_customer])['name'] ?? '') : '' ?>"
                                               required placeholder="Type name or select...">
                                        <datalist id="customer_list">
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= escape_html($customer['name']) ?>" data-id="<?= $customer['id'] ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                        <input type="hidden" id="customer_id" name="customer_id" value="<?= $selected_customer ?>">
                                        <?php if (isset($errors['customer_id'])): ?>
                                            <div class="invalid-feedback"><?= $errors['customer_id'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="order_date" class="form-label">Order Date <span
                                                class="text-danger">*</span></label>
                                        <input type="date" class="form-control <?= isset($errors['order_date']) ? 'is-invalid' : '' ?>" id="order_date" name="order_date"
                                            value="<?= post('order_date') ?: ($quote_data ? $quote_data['quote_date'] : date('Y-m-d')) ?>"
                                            required>
                                        <?php if (isset($errors['order_date'])): ?>
                                            <div class="invalid-feedback"><?= $errors['order_date'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="required_date" class="form-label">Required Date</label>
                                        <input type="date" class="form-control" id="required_date" name="required_date"
                                            value="<?= $quote_data ? $quote_data['expiry_date'] : '' ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                        <?php if (!empty($_SESSION['company_id'])): ?>
                                            <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                            <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                        <?php else: ?>
                                            <select class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" id="company_id" name="company_id" required>
                                                <option value="">Select Company</option>
                                                <?php foreach ($companies as $comp): ?>
                                                    <option value="<?= $comp['id'] ?>" <?= post('company_id') == $comp['id'] ? 'selected' : '' ?>>
                                                        <?= escape_html($comp['company_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="location_id" class="form-label">Warehouse <span
                                                class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id" required onchange="updateBins(this.value)">
                                            <option value="">Select Warehouse</option>
                                            <?php foreach ($locations as $loc): ?>
                                                <option value="<?= $loc['id'] ?>" <?= post('location_id') == $loc['id'] ? 'selected' : '' ?>>
                                                    <?= escape_html($loc['code']) ?> - <?= escape_html($loc['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['location_id'])): ?>
                                            <div class="invalid-feedback"><?= $errors['location_id'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="bin_id" class="form-label">Bin Location</label>
                                        <select class="form-select" id="bin_id" name="bin_id">
                                            <option value="">Select Bin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?= $quote_data ? 'selected' : '' ?>>Draft</option>
                                            <option value="confirmed">Confirmed</option>
                                            <option value="in_production">In Production</option>
                                            <option value="shipped">Shipped</option>
                                            <option value="delivered">Delivered</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="tax_class_id" class="form-label">Tax Class</label>
                                        <select class="form-select" id="tax_class_id" name="tax_class_id"
                                            onchange="calculateTotals()">
                                            <option value="">No Tax (0%)</option>
                                            <?php foreach ($tax_classes as $tax): ?>
                                                <?php
                                                $is_selected = false;
                                                if ($quote_data && $quote_data['tax_class_id'] == $tax['id']) {
                                                    $is_selected = true;
                                                } elseif (!$quote_data && floatval($tax['tax_rate']) == 0.15) {
                                                    $is_selected = true;
                                                }
                                                ?>
                                                <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['tax_rate'] ?>"
                                                    <?= $is_selected ? 'selected' : '' ?>>
                                                    <?= escape_html($tax['tax_name']) ?>
                                                    (<?= number_format($tax['tax_rate'] * 100, 2) ?>%) -
                                                    <?= escape_html($tax['tax_code']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Select tax rate for this order</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="manual_discount" class="form-label">Sales Discount</label>
                                        <select class="form-select" id="manual_discount" name="manual_discount" onchange="applyDiscount()">
                                            <option value="" data-type="fixed" data-value="0">No Discount</option>
                                            <?php 
                                            $active_discounts = db_fetch_all("
                                                SELECT id, name, discount_code, notes, discount_type, discount_value 
                                                FROM sales_discounts 
                                                WHERE status = 'approved' AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                                            ");
                                            foreach ($active_discounts as $disc): 
                                                $display_val = ($disc['discount_type'] == 'percentage') ? $disc['discount_value'] . '%' : '$' . $disc['discount_value'];
                                                $display = escape_html($disc['name']);
                                                if ($disc['discount_code']) $display .= ' (' . escape_html($disc['discount_code']) . ')';
                                                $display .= ' - ' . $display_val;
                                            ?>
                                                <option value="<?= $disc['id'] ?>" data-type="<?= $disc['discount_type'] ?>" data-value="<?= $disc['discount_value'] ?>" <?= ($quote_data && $quote_data['manual_discount'] == $disc['id']) ? 'selected' : '' ?>>
                                                    <?= $display ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="payment_status" class="form-label">Payment Status</label>
                                    <select class="form-select" id="payment_status" name="payment_status">
                                        <option value="unpaid">Unpaid</option>
                                        <option value="partially_paid">Partially Paid</option>
                                        <option value="paid">Paid</option>
                                        <option value="refunded">Refunded</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method">
                                        <option value="">Select Method</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="check">Check</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="payment_currency" class="form-label">Currency</label>
                                    <select class="form-select currency-select" id="payment_currency"
                                        name="payment_currency">
                                        <option value="FJD" selected>FJD / $</option>
                                        <option value="INR">INR / ₹</option>
                                        <option value="USD">USD / $</option>
                                        <option value="EUR">EUR / €</option>
                                        <option value="GBP">GBP / £</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="datetime-local" class="form-control" id="payment_date"
                                        name="payment_date">
                                </div>
                                
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                    <label class="form-label d-block small fw-bold mb-2">SALE TYPE</label>
                                    <div class="btn-group w-100 shadow-sm" role="group">
                                        <input type="radio" class="btn-check" name="sale_type" id="sale_type_normal" value="normal" autocomplete="off" checked onchange="toggleProjectSection()">
                                        <label class="btn btn-outline-secondary py-2" for="sale_type_normal">Standard Sale</label>

                                        <input type="radio" class="btn-check" name="sale_type" id="sale_type_project" value="project" autocomplete="off" onchange="toggleProjectSection()">
                                        <label class="btn btn-outline-primary py-2" for="sale_type_project">Project / Milestone</label>
                                    </div>
                                    </div>
                                </div>

                                <!-- Project Section (Dynamic) -->
                                <div id="project_section" style="display: none;" class="mt-4 p-4 border-0 rounded-4 shadow-lg bg-light position-relative">
                                    <div class="position-absolute top-0 end-0 m-3 d-none d-md-block">
                                        <span class="badge bg-primary px-3 py-2 rounded-pill shadow-sm"><i class="fas fa-layer-group me-2"></i>Project Mode Active</span>
                                    </div>
                                    
                                    <h5 class="pb-2 mb-4 text-primary border-bottom border-primary border-2" style="width: fit-content;">
                                        <i class="fas fa-project-diagram me-2"></i>Project Configuration
                                    </h5>

                                    <div class="row g-4">
                                        <div class="col-md-7">
                                            <div class="form-floating mb-3">
                                                <input type="text" class="form-control" id="project_name" name="project_name" placeholder="Name">
                                                <label for="project_name">Project Title / Name</label>
                                            </div>
                                            <div class="form-floating">
                                                <textarea class="form-control" id="project_description" name="project_description" style="height: 100px" placeholder="Details"></textarea>
                                                <label for="project_description">Project Scope & Material Details</label>
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="card bg-white border-0 shadow-sm h-100">
                                                <div class="card-body">
                                                    <label for="project_total_value" class="form-label fw-bold text-muted small">TOTAL PROJECT CONTRACT VALUE</label>
                                                    <div class="input-group input-group-lg">
                                                        <span class="input-group-text border-0 bg-success text-white">$</span>
                                                        <input type="number" class="form-control border-0 fw-bold text-success" id="project_total_value" name="project_total_value" step="0.01" value="0.00" oninput="calculateProjectStages()" style="background: #f0fdf4;">
                                                    </div>
                                                    
                                                    <?php if (is_admin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
                                                    <!-- BOSS COPY / Internal Financials -->
                                                    <div class="mt-4 p-3 rounded-3 bg-dark text-white shadow-sm border-start border-warning border-4">
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <small class="text-warning fw-bold"><i class="fas fa-user-shield me-1"></i>BOSS COPY — INTERNAL ONLY</small>
                                                            <span class="badge bg-secondary">Confidential</span>
                                                        </div>
                                                        <div class="row g-2 border-top border-secondary pt-2 mt-1">
                                                            <div class="col-6">
                                                                <div class="small opacity-75 text-uppercase" style="font-size: 10px;">Total Cost</div>
                                                                <div class="fw-bold" id="internal_cost_display">$0.00</div>
                                                            </div>
                                                            <div class="col-6 text-end">
                                                                <div class="small opacity-75 text-uppercase" style="font-size: 10px;">Project Margin</div>
                                                                <div class="fw-bold text-success" id="internal_margin_display">0%</div>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="internal_authorization" value="1">
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">Billing Stages / Milestones</h6>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="addProjectStage()">
                                            <i class="fas fa-plus me-1"></i>Add Billing Stage
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered">
                                            <thead class="table-dark">
                                                <tr>
                                                    <th style="width: 40%;">Stage Name</th>
                                                    <th style="width: 15%;">%</th>
                                                    <th style="width: 25%;">Amount ($)</th>
                                                    <th style="width: 20%;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="project_stages_body">
                                                <!-- Stages added via JS -->
                                            </tbody>
                                            <tfoot class="table-light">
                                                <tr class="fw-bold">
                                                    <td class="text-end">Total Allocated:</td>
                                                    <td id="total_pct_display">0%</td>
                                                    <td id="total_amt_display">$0.00</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>

                                    <div class="row mt-3 border-top pt-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="project_stage_id" class="form-label fw-bold">INVOICE NOW: Select Active Phase</label>
                                            <select class="form-select border-primary" id="project_stage_id" name="project_stage_id" onchange="applyStageAmount()">
                                                <option value="">Full Invoice (Current Items Total)</option>
                                            </select>
                                            <div class="form-text text-primary"><i class="fas fa-info-circle me-1"></i>Selecting a stage will automatically set the invoice amount based on the project breakdown.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label d-block">&nbsp;</label>
                                            <div class="form-check form-switch mt-2">
                                                <input class="form-check-input" type="checkbox" id="add_variation_cost" onchange="toggleVariationCost()">
                                                <label class="form-check-label" for="add_variation_cost">Include Variation Cost / Add-ons</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div id="variation_cost_section" style="display: none;" class="p-3 mb-3 border rounded bg-white">
                                        <div class="row">
                                            <div class="col-md-8 mb-2">
                                                <label class="form-label small">Variation Description</label>
                                                <input type="text" class="form-control form-control-sm" name="variation_desc" placeholder="E.g. Extra materials for Phase 2">
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Variation Amount ($)</label>
                                                <input type="number" class="form-control form-control-sm" name="variation_amount" step="0.01" value="0.00" oninput="calculateTotals()">
                                            </div>
                                        </div>
                                    </div>

                                    <input type="hidden" name="is_new_project" id="is_new_project" value="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5>Order Items</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addLineItem()">
                                <i class="fas fa-plus me-1"></i>Add Item
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (isset($errors['items'])): ?>
                                <div class="alert alert-danger"><?= $errors['items'] ?></div>
                            <?php endif; ?>
                            <div id="lineItems">
                                <?php if (!empty($quote_items)): ?>
                                    <?php foreach ($quote_items as $q_item): ?>
                                        <div class="row mb-2 line-item">
                                            <div class="col-md-5">
                                                <label class="form-label">Item</label>
                                                <select class="form-select item-select" name="item_id[]" required
                                                    onchange="updateItemPrice(this)">
                                                    <option value="">Select Item</option>
                                                    <?php foreach ($items as $item): ?>
                                                        <option value="<?= $item['id'] ?>" data-price="<?= $item['selling_price'] ?>" data-cost="<?= $item['cost_price'] ?>"
                                                            <?= $q_item['item_id'] == $item['id'] ? 'selected' : '' ?>>
                                                            <?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?>
                                                            ($<?= number_format($item['selling_price'], 2) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="text" class="form-control form-control-sm mt-1 description-input" name="description[]" value="<?= escape_html($q_item['description'] ?? '') ?>" placeholder="Item description / dimensions (optional)">
                                            </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Qty</label>
                                            <input type="number" class="form-control quantity-input" name="quantity[]" min="1"
                                                step="1" value="<?= $q_item['quantity'] ?>" required
                                                oninput="calculateLineTotal(this.closest('.line-item'))">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Price</label>
                                            <input type="number" class="form-control price-input" name="unit_price[]" min="0"
                                                step="0.01" value="<?= $q_item['unit_price'] ?>" required
                                                oninput="calculateLineTotal(this.closest('.line-item'))">
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Disc%</label>
                                            <input type="number" class="form-control discount-input" name="discount_percent[]"
                                                min="0" max="100" step="0.01" value="<?= number_format($q_item['discount_pct'] ?? 0, 2, '.', '') ?>"
                                                oninput="calculateLineTotal(this.closest('.line-item'))">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Total</label>
                                            <input type="text" class="form-control total-input"
                                                value="<?= number_format($q_item['line_total'], 2) ?>" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100"
                                                onclick="removeLineItem(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="row mb-2 line-item">
                                    <div class="col-md-5">
                                        <label class="form-label">Item</label>
                                        <select class="form-select item-select" name="item_id[]" required
                                            onchange="updateItemPrice(this)">
                                            <option value="">Select Item</option>
                                            <?php foreach ($items as $item): ?>
                                                <option value="<?= $item['id'] ?>" data-price="<?= $item['selling_price'] ?>" data-cost="<?= $item['cost_price'] ?>">
                                                    <?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?>
                                                    ($<?= number_format($item['selling_price'], 2) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="form-control form-control-sm mt-1 description-input" name="description[]" placeholder="Item description / dimensions (optional)">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Qty</label>
                                        <input type="number" class="form-control quantity-input" name="quantity[]" min="1"
                                            step="1" value="1" required
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price</label>
                                        <input type="number" class="form-control price-input" name="unit_price[]" min="0"
                                            step="0.01" value="0.00" required
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Disc%</label>
                                        <input type="number" class="form-control discount-input" name="discount_percent[]"
                                            min="0" max="100" step="0.01" value="0.00"
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Total</label>
                                        <input type="text" class="form-control total-input" value="0.00" readonly>
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-danger btn-sm w-100"
                                            onclick="removeLineItem(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notes and Custom Fields -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                    placeholder="Special instructions or notes"><?= $quote_data ? $quote_data['notes'] : '' ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                                <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3"
                                    placeholder="Custom fields (e.g., specific instructions for Jones/PTH invoice)"><?= $quote_data ? $quote_data['custom_fields'] : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-4 sticky-summary">
            <div class="card shadow-sm border-0">
                <div class="card-header">
                    <h5>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="subtotal">$0.00</strong>
                    </div>
                    <input type="hidden" id="order_discount_amount" name="order_discount_amount" value="<?= $quote_data ? number_format($quote_data['discount_amount'], 2, '.', '') : '0.00' ?>">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount Applied:</span>
                        <span id="discount_display" class="text-danger">-$0.00</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax:</span>
                        <strong id="tax">$0.00</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong id="total" class="text-primary fs-5">$0.00</strong>
                    </div>
                    <input type="hidden" id="subtotal_amount" name="subtotal">
                    <input type="hidden" id="order_discount_amount_hidden" name="order_discount_amount">
                    <input type="hidden" id="tax_amount" name="tax">
                    <input type="hidden" id="total_amount" name="total">
                </div>
            </div>

            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save me-2"></i>Create Order
                </button>
                <a href="orders.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
    </form>
</div>
</div>
<style>
    html[data-bs-theme="dark"] {
        --mjr-primary: #0d6efd;
        --mjr-secondary: #6c757d;
        --mjr-accent: #1f2937;
        --mjr-dark: #f8f9fa;
        --mjr-glass: rgba(20, 20, 30, 0.95);
    }
    
    html[data-bs-theme="light"] {
        --mjr-primary: #0d6efd;
        --mjr-secondary: #6c757d;
        --mjr-accent: #f8f9fa;
        --mjr-dark: #2c3e50;
        --mjr-glass: rgba(255, 255, 255, 0.95);
    }
    
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        background: var(--mjr-glass);
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    
    .card-header {
        background: rgba(13, 110, 253, 0.05);
        border-bottom: 1px solid rgba(0,0,0,0.05);
        padding: 1rem 1.25rem;
    }

    .card-header h5 {
        color: var(--mjr-dark);
        font-weight: 700;
        margin: 0;
        font-size: 1.1rem;
    }

    html[data-bs-theme="light"] .form-label {
        color: #4a5568 !important;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    html[data-bs-theme="dark"] .form-label {
        color: #cbd5e0 !important;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    html[data-bs-theme="light"] .form-control, html[data-bs-theme="light"] .form-select {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s;
        background-color: #fff !important;
        color: #1a202c !important;
    }

    html[data-bs-theme="dark"] .form-control, html[data-bs-theme="dark"] .form-select {
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 0.6rem 1rem;
        font-size: 0.95rem;
        transition: all 0.2s;
        background-color: #1f2937 !important;
        color: #f8f9fa !important;
    }

    /* Button Contrast Overrides */
    .btn-outline-warning {
        border-color: #f59e0b !important;
        color: #d97706 !important;
    }
    .btn-outline-warning:hover, .btn-check:checked + .btn-outline-warning {
        background-color: #f59e0b !important;
        color: #fff !important;
    }
    .btn-outline-info {
        border-color: #0ea5e9 !important;
        color: #0284c7 !important;
    }
    .btn-outline-info:hover, .btn-check:checked + .btn-outline-info {
        background-color: #0ea5e9 !important;
        color: #fff !important;
    }

    html[data-bs-theme="light"] .form-control:focus, html[data-bs-theme="light"] .form-select:focus {
        border-color: var(--mjr-primary);
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.1);
    }

    html[data-bs-theme="dark"] .form-control:focus, html[data-bs-theme="dark"] .form-select:focus {
        border-color: #0dcaf0;
        box-shadow: 0 0 0 3px rgba(13, 202, 240, 0.1);
    }

    /* Sale Type Toggle Styling */
    .btn-group .btn-check + .btn-outline-secondary {
        border-color: #cbd5e0;
        color: #718096;
    }
    .btn-group .btn-check:checked + .btn-outline-secondary {
        background-color: #718096;
        color: #fff;
    }
    .btn-group .btn-check + .btn-outline-primary {
        border-color: var(--mjr-primary);
        color: var(--mjr-primary);
    }
    .btn-group .btn-check:checked + .btn-outline-primary {
        background-color: var(--mjr-primary);
        box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
    }

    /* Project Section Aesthetics */
    #project_section {
        background: #f8fafc;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px;
        animation: slideDown 0.3s ease-out;
    }
    
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Summary Card Labels */
    .card-body .d-flex span {
        color: #718096;
        font-weight: 500;
    }
    .card-body .d-flex strong {
        color: var(--mjr-dark);
    }

    /* Sticky Summary Column */
    @media (min-width: 768px) {
        .sticky-summary {
            position: sticky;
            top: 2rem;
            z-index: 100;
        }
    }
</style>

<script src="../assets/js/sales_lines.js"></script>
<script>
// Project Management Logic
function toggleProjectSection() {
    const section = document.getElementById('project_section');
    const isProject = document.getElementById('sale_type_project').checked;
    section.style.display = isProject ? 'block' : 'none';
    
    // Toggle required fields
    document.getElementById('project_name').required = isProject;
    
    calculateTotals();
}

function addProjectStage(defaultName = null) {
    const container = document.getElementById('project_stages_body');
    const rowCount = container.children.length;
    const stageName = defaultName || `Stage ${rowCount + 1}`;
    
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="form-control form-control-sm" name="stage_name[]" value="${stageName}" required oninput="updateStageDropdown()"></td>
        <td><input type="number" class="form-control form-control-sm stage-pct" name="stage_percent[]" step="0.01" value="0" oninput="calculateProjectStages(this, 'pct')"></td>
        <td><input type="number" class="form-control form-control-sm stage-amt" name="stage_amount[]" step="0.01" value="0" oninput="calculateProjectStages(this, 'amt')"></td>
        <td>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); calculateProjectStages();">
                <i class="fas fa-times"></i>
            </button>
        </td>
        <input type="hidden" name="stage_details[]" value="">
    `;
    container.appendChild(row);
    updateStageDropdown();
    return row;
}

function calculateProjectStages(input = null, source = null) {
    const totalValue = parseFloat(document.getElementById('project_total_value').value) || 0;
    const stages = document.querySelectorAll('#project_stages_body tr');
    
    let totalPct = 0;
    let totalAmt = 0;

    // If a specific input changed, sync its counterpart
    if (input && source && totalValue > 0) {
        const row = input.closest('tr');
        const pctInput = row.querySelector('.stage-pct');
        const amtInput = row.querySelector('.stage-amt');
        
        if (source === 'pct') {
            amtInput.value = (totalValue * (parseFloat(pctInput.value) / 100)).toFixed(2);
        } else {
            pctInput.value = ((parseFloat(amtInput.value) / totalValue) * 100).toFixed(2);
        }
    }

    stages.forEach(row => {
        totalPct += parseFloat(row.querySelector('.stage-pct').value) || 0;
        totalAmt += parseFloat(row.querySelector('.stage-amt').value) || 0;
    });

    document.getElementById('total_pct_display').textContent = totalPct.toFixed(2) + '%';
    document.getElementById('total_amt_display').textContent = '$' + totalAmt.toLocaleString(undefined, {minimumFractionDigits: 2});
    
    if (totalPct > 100) {
        document.getElementById('total_pct_display').classList.add('text-danger');
    } else {
        document.getElementById('total_pct_display').classList.remove('text-danger');
    }

    updateStageDropdown();
}

function updateStageDropdown() {
    const dropdown = document.getElementById('project_stage_id');
    const currentValue = dropdown.value;
    const stages = document.querySelectorAll('#project_stages_body tr');
    
    // Keep the "Full Invoice" option
    dropdown.innerHTML = '<option value="">Full Invoice (Current Items Total)</option>';
    
    stages.forEach((row, index) => {
        const name = row.querySelector('input[name="stage_name[]"]').value;
        const amt = row.querySelector('.stage-amt').value;
        const option = document.createElement('option');
        option.value = index + 1; // Temporary ID for new project stages
        option.setAttribute('data-amount', amt);
        option.textContent = `${name} - $${parseFloat(amt).toLocaleString()}`;
        dropdown.appendChild(option);
    });
    
    dropdown.value = currentValue;
}

function applyStageAmount() {
    calculateTotals();
}

function toggleVariationCost() {
    const section = document.getElementById('variation_cost_section');
    const isChecked = document.getElementById('add_variation_cost').checked;
    section.style.display = isChecked ? 'block' : 'none';
    calculateTotals();
}

// Override or Hook into calculateTotals from sales_lines.js
const originalCalculateTotals = window.calculateTotals;
window.calculateTotals = function() {
    const isProject = document.getElementById('sale_type_project').checked;
    const stageSelect = document.getElementById('project_stage_id');
    const variationAmt = parseFloat(document.querySelector('input[name="variation_amount"]')?.value) || 0;
    
    if (isProject && stageSelect && stageSelect.value !== "") {
        const selectedOption = stageSelect.options[stageSelect.selectedIndex];
        const stageAmount = parseFloat(selectedOption.getAttribute('data-amount')) || 0;
        const subtotal = stageAmount + variationAmt;
        
        // Update summary displays with Stage Amount
        updateSummaryDisplays(subtotal);
    } else {
        // Standard calculation
        if (typeof originalCalculateTotals === 'function') {
            originalCalculateTotals();
        }
    }
};

function updateSummaryDisplays(subtotal) {
    const orderDiscountAmount = parseFloat(document.getElementById('order_discount_amount')?.value) || 0;
    const taxClassSelect = document.getElementById('tax_class_id');
    let taxRate = 0;
    if (taxClassSelect && taxClassSelect.value) {
        taxRate = parseFloat(taxClassSelect.options[taxClassSelect.selectedIndex].getAttribute('data-rate')) || 0;
    }
    
    const taxAmount = (subtotal - orderDiscountAmount) * taxRate;
    const total = (subtotal - orderDiscountAmount) + taxAmount;
    
    // Visual Updates
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('tax').textContent = '$' + taxAmount.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
    
    // BOSS COPY Updates
    updateBossCopy(subtotal);

    // Hidden Fields
    document.getElementById('subtotal_amount').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('total_amount').value = total.toFixed(2);
}

function updateBossCopy(projectValue) {
    const costDisplay = document.getElementById('internal_cost_display');
    const marginDisplay = document.getElementById('internal_margin_display');
    if (!costDisplay || !marginDisplay) return;

    let totalCost = 0;
    document.querySelectorAll('.line-item').forEach(row => {
        const itemSelect = row.querySelector('.item-select');
        const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
        
        if (selectedOption && selectedOption.value) {
            // We'll need the cost price from the data attribute (update item-select rendering to include it)
            const cost = parseFloat(selectedOption.getAttribute('data-cost')) || 0;
            totalCost += (cost * qty);
        }
    });

    const marginPct = projectValue > 0 ? ((projectValue - totalCost) / projectValue) * 100 : 0;
    
    costDisplay.textContent = '$' + totalCost.toLocaleString(undefined, {minimumFractionDigits: 2});
    marginDisplay.textContent = marginPct.toFixed(1) + '%';
    
    marginDisplay.className = 'fw-bold ' + 
        (marginPct >= 30 ? 'text-success' : (marginPct >= 15 ? 'text-warning' : 'text-danger'));
}

// Customer Datalist / Placeholder handling
document.getElementById('customer_name').addEventListener('input', function(e) {
    const val = e.target.value;
    const opts = document.getElementById('customer_list').childNodes;
    let foundId = "";
    
    for (let i = 0; i < opts.length; i++) {
        if (opts[i].value === val) {
            foundId = opts[i].getAttribute('data-id');
            break;
        }
    }
    
    document.getElementById('customer_id').value = foundId;
});

function updateBins(locationId) {
    const binSelect = document.getElementById('bin_id');
    if (!binSelect) return;
    binSelect.innerHTML = '<option value="">Loading...</option>';
    if (!locationId) {
        binSelect.innerHTML = '<option value="">Select Bin</option>';
        return;
    }
    fetch(`../inventory/ajax_get_bins.php?location_id=${locationId}`)
        .then(response => response.json())
        .then(data => {
            binSelect.innerHTML = '<option value="">Select Bin</option>';
            data.forEach(bin => {
                const option = document.createElement('option');
                option.value = bin.bin_id;
                option.textContent = bin.bin_location;
                binSelect.appendChild(option);
            });
            const selectedBin = "<?= post('bin_id') ?: '' ?>";
            if (selectedBin) binSelect.value = selectedBin;
        })
        .catch(error => {
            console.error('Error fetching bins:', error);
            binSelect.innerHTML = '<option value="">Error loading bins</option>';
        });
}

$(document).ready(function () {
    const locId = $('#location_id').val();
    if (locId) updateBins(locId);
    
    // Initial Project Stages (Targeting 20/30/40/10% split)
    const initStages = [
        { name: "Stage 1 (Advance)", pct: 20 },
        { name: "Stage 2 (Progress)", pct: 30 },
        { name: "Stage 3 (Progress)", pct: 40 },
        { name: "Stage 4 (Completion)", pct: 10 }
    ];
    
    initStages.forEach(s => {
        const row = addProjectStage(s.name);
        row.querySelector('.stage-pct').value = s.pct;
    });
    calculateProjectStages();
});
</script>
<?php if (!empty($quote_items)): ?>
    <script>
        $(document).ready(function () {
            calculateTotals();
        });
    </script>
<?php endif; ?>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
```