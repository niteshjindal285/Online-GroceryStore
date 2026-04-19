<?php
/**
 * Edit Sales Order Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();

$page_title = 'Edit Sales Order - MJR Group ERP';
$company_id = active_company_id(1);

// Get order ID
$order_id = get('id');
if (!$order_id) {
    set_flash('Order ID not provided.', 'error');
    redirect('orders.php');
}

// Get order data
$order = db_fetch("
    SELECT so.*, c.customer_code, c.name as customer_name,
           p.name as project_name, p.total_value as project_total_value, p.description as project_description,
           ps.stage_name, ps.amount as stage_amount
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    LEFT JOIN projects p ON so.project_id = p.id
    LEFT JOIN project_stages ps ON so.project_stage_id = ps.id
    WHERE so.id = ? AND so.company_id = ?
", [$order_id, $company_id]);

if (!$order) {
    set_flash('Order not found.', 'error');
    redirect('orders.php');
}

// Get order items
$order_items = db_fetch_all("
    SELECT sol.*, i.code, i.name, i.selling_price
    FROM sales_order_lines sol
    JOIN inventory_items i ON sol.item_id = i.id
    WHERE sol.order_id = ?
", [$order_id]);

// Get customers for dropdown
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get inventory items for dropdown
$items = db_fetch_all("SELECT id, code, name, selling_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get active tax classes for dropdown
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_code, tax_rate, tax_type FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('sales_tax', 'both') ORDER BY tax_name");

// Get locations for dropdown
$locations = db_fetch_all("SELECT id, code, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get companies for dropdown
$companies = db_fetch_all("SELECT id, name AS company_name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$company_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        try {
            $delivery_date = post('delivery_date');
            $status = post('status');
            $location_id = post('location_id');
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

            $errors = [];
            if (empty($location_id)) $errors['location_id'] = err_required();

            if (empty($item_ids)) {
                $errors['items'] = err_required();
            } else {
                $has_items = false;
                foreach ($item_ids as $item_id) {
                    if (!empty($item_id)) {
                        $has_items = true;
                        break;
                    }
                }
                if (!$has_items) {
                    $errors['items'] = err_required();
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

            // Start transaction
            db_begin_transaction();

            // Update order header
            $sql = "UPDATE sales_orders SET 
                    delivery_date = ?, status = ?, payment_status = ?, payment_method = ?, payment_currency = ?, payment_date = ?, location_id = ?, subtotal = ?, discount_amount = ?, tax_amount = ?, total_amount = ?, tax_class_id = ?, notes = ?, custom_fields = ?, manual_discount = ?, 
                    sale_type = ?, project_id = ?, project_stage_id = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $sale_type = post('sale_type', 'normal');
            $project_id = $order['project_id'];
            $project_stage_id = post('project_stage_id');
            $order_company = post('company_id') ?: $order['company_id'];
            
            // If project name is provided, update or create project (not implementing full project edit here for brevity, assuming linking)
            // But we should at least handle the stage link
            
            db_query($sql, [
                $delivery_date, $status, $payment_status, $payment_method, $payment_currency, $payment_date, $location_id, 
                $subtotal, $order_discount_amount, $tax_amount, $total_amount, $tax_class_id, $notes, $custom_fields, $manual_discount,
                $sale_type, $project_id, $project_stage_id, $order_id
            ]);
            
            // Also update company_id separately if we need to (not in original SQL string)
            db_query("UPDATE sales_orders SET company_id = ? WHERE id = ?", [$order_company, $order_id]);

            // Delete existing order lines
            db_query("DELETE FROM sales_order_lines WHERE order_id = ?", [$order_id]);

            // Insert new order lines
            $line_sql = "INSERT INTO sales_order_lines (order_id, item_id, description, quantity, unit_price, discount_percent, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)";
            for ($i = 0; $i < count($item_ids); $i++) {
                if (!empty($item_ids[$i])) {
                    $desc = $descriptions[$i] ?? null;
                    if (trim($desc) === '') $desc = null;
                    $qty = floatval($quantities[$i]);
                    $price = floatval($unit_prices[$i]);
                    $discount_pct = floatval($discount_percents[$i] ?? 0);

                    $line_total = $qty * $price;
                    if ($discount_pct > 0) {
                        $line_total = $line_total * (1 - ($discount_pct / 100));
                    }
                    db_insert($line_sql, [$order_id, $item_ids[$i], $desc, $qty, $price, $discount_pct, $line_total]);
                }
            }

            // Keep inventory ledger synchronized with current shipping status and line quantities.
                inventory_sync_sales_order_movements(
                    intval($order_id),
                    $status,
                    intval($order['customer_id']),
                    intval($_SESSION['user_id']),
                    (string) $order['order_number'],
                    $location_id
                );

                db_commit();

                set_flash('Sales order updated successfully!', 'success');
                redirect('view_order.php?id=' . $order_id);
            }
        } catch (Exception $e) {
            db_rollback();
            log_error("Error updating order: " . $e->getMessage());
            set_flash(sanitize_db_error($e->getMessage()), 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-edit me-2"></i>Edit Sales Order: <?= escape_html($order['order_number']) ?></h2>
                <div>
                    <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-info">
                        <i class="fas fa-eye me-2"></i>View Order
                    </a>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Order Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Order Number</label>
                                        <input type="text" class="form-control"
                                            value="<?= escape_html($order['order_number']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Customer</label>
                                        <input type="text" class="form-control"
                                            value="<?= escape_html($order['customer_code']) ?> - <?= escape_html($order['customer_name']) ?>"
                                            readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">Order Date</label>
                                        <input type="date" class="form-control"
                                            value="<?= date('Y-m-d', strtotime($order['order_date'])) ?>" readonly>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="delivery_date" class="form-label">Delivery Date</label>
                                        <input type="date" class="form-control" id="delivery_date" name="delivery_date"
                                            value="<?= !empty($order['delivery_date']) ? date('Y-m-d', strtotime($order['delivery_date'])) : '' ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?= $order['status'] == 'draft' ? 'selected' : '' ?>>
                                                Draft</option>
                                            <option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                            <option value="in_production" <?= $order['status'] == 'in_production' ? 'selected' : '' ?>>In Production</option>
                                            <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>
                                                Shipped</option>
                                            <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                            <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
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
                                                <option value="<?= $disc['id'] ?>" data-type="<?= $disc['discount_type'] ?>" data-value="<?= $disc['discount_value'] ?>" <?= ($order['manual_discount'] == $disc['id']) ? 'selected' : '' ?>>
                                                    <?= $display ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="payment_status" class="form-label">Payment Status</label>
                                    <select class="form-select" id="payment_status" name="payment_status">
                                        <option value="unpaid" <?= $order['payment_status'] == 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                        <option value="partially_paid" <?= $order['payment_status'] == 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                                        <option value="paid" <?= $order['payment_status'] == 'paid' ? 'selected' : '' ?>>
                                            Paid</option>
                                        <option value="refunded" <?= $order['payment_status'] == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method">
                                        <option value="">Select Method</option>
                                        <option value="cash" <?= ($order['payment_method'] ?? '') == 'cash' ? 'selected' : '' ?>>Cash</option>
                                        <option value="bank_transfer" <?= ($order['payment_method'] ?? '') == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                        <option value="credit_card" <?= ($order['payment_method'] ?? '') == 'credit_card' ? 'selected' : '' ?>>Credit Card</option>
                                        <option value="check" <?= ($order['payment_method'] ?? '') == 'check' ? 'selected' : '' ?>>Check</option>
                                        <option value="other" <?= ($order['payment_method'] ?? '') == 'other' ? 'selected' : '' ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="payment_currency" class="form-label">Currency</label>
                                    <select class="form-select currency-select" id="payment_currency"
                                        name="payment_currency">
                                        <option value="FJD" <?= ($order['payment_currency'] ?? 'FJD') == 'FJD' ? 'selected' : '' ?>>FJD / $</option>
                                        <option value="INR" <?= ($order['payment_currency'] ?? '') == 'INR' ? 'selected' : '' ?>>INR / ₹</option>
                                        <option value="USD" <?= ($order['payment_currency'] ?? '') == 'USD' ? 'selected' : '' ?>>USD / $</option>
                                        <option value="EUR" <?= ($order['payment_currency'] ?? '') == 'EUR' ? 'selected' : '' ?>>EUR / €</option>
                                        <option value="GBP" <?= ($order['payment_currency'] ?? '') == 'GBP' ? 'selected' : '' ?>>GBP / £</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="payment_date" class="form-label">Payment Date</label>
                                    <input type="datetime-local" class="form-control" id="payment_date"
                                        name="payment_date"
                                        value="<?= !empty($order['payment_date']) ? date('Y-m-d\TH:i', strtotime($order['payment_date'])) : '' ?>">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label class="form-label d-block text-muted small fw-bold">SALE TYPE</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="sale_type" id="sale_type_normal" value="normal" autocomplete="off" <?= ($order['sale_type'] != 'project') ? 'checked' : '' ?> onchange="toggleProjectSection()">
                                        <label class="btn btn-outline-secondary" for="sale_type_normal">Standard Sale</label>

                                        <input type="radio" class="btn-check" name="sale_type" id="sale_type_project" value="project" autocomplete="off" <?= ($order['sale_type'] == 'project') ? 'checked' : '' ?> onchange="toggleProjectSection()">
                                        <label class="btn btn-outline-primary" for="sale_type_project">Project / Milestone</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Project Section (View/Pick Phase) -->
                            <div id="project_section" style="display: <?= ($order['sale_type'] == 'project') ? 'block' : 'none' ?>;" class="mt-4 p-3 border rounded shadow-sm bg-light">
                                <h6 class="border-bottom pb-2 mb-3 text-primary"><i class="fas fa-project-diagram me-2"></i>Project Details</h6>
                                <?php if ($order['project_id']): ?>
                                    <div class="mb-3">
                                        <strong>Project:</strong> <?= escape_html($order['project_name']) ?><br>
                                        <strong>Total Value:</strong> $<?= number_format($order['project_total_value'], 2) ?>
                                    </div>
                                    <div class="mb-3">
                                        <label for="project_stage_id" class="form-label fw-bold">Active Phase Claim</label>
                                        <select class="form-select border-primary" id="project_stage_id" name="project_stage_id" onchange="applyStageAmount()">
                                            <option value="">Full Invoice (Current Items Total)</option>
                                            <?php
                                            $stages = db_fetch_all("SELECT id, stage_name, amount FROM project_stages WHERE project_id = ? ORDER BY id", [$order['project_id']]);
                                            foreach ($stages as $stg):
                                            ?>
                                                <option value="<?= $stg['id'] ?>" data-amount="<?= $stg['amount'] ?>" <?= ($order['project_stage_id'] == $stg['id']) ? 'selected' : '' ?>>
                                                    <?= escape_html($stg['stage_name']) ?> - $<?= number_format($stg['amount'], 2) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning small">This order was not initially created as a project. You can link it to an existing project or convert it back to normal.</div>
                                <?php endif; ?>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-6 mb-3">
                                    <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                    <?php if (!empty($_SESSION['company_id'])): ?>
                                        <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                        <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                    <?php else: ?>
                                        <select class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" id="company_id" name="company_id" required>
                                            <option value="">Select Company</option>
                                            <?php foreach ($companies as $comp): ?>
                                                <option value="<?= $comp['id'] ?>" <?= (post('company_id') ?: $order['company_id']) == $comp['id'] ? 'selected' : '' ?>>
                                                    <?= escape_html($comp['company_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="location_id" class="form-label">Warehouse <span
                                            class="text-danger">*</span></label>
                                    <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id" required>
                                        <option value="">Select Warehouse</option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= (post('location_id') ?: $order['location_id']) == $loc['id'] ? 'selected' : '' ?>>
                                                <?= escape_html($loc['code']) ?> - <?= escape_html($loc['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['location_id'])): ?>
                                        <div class="invalid-feedback"><?= $errors['location_id'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="tax_class_id" class="form-label">Tax Class</label>
                                    <select class="form-select" id="tax_class_id" name="tax_class_id"
                                        onchange="calculateTotals()">
                                        <option value="">No Tax (0%)</option>
                                        <?php foreach ($tax_classes as $tax): ?>
                                            <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['tax_rate'] ?>"
                                                <?= isset($order['tax_class_id']) && $order['tax_class_id'] == $tax['id'] ? 'selected' : '' ?>>
                                                <?= escape_html($tax['tax_name']) ?>
                                                (<?= number_format($tax['tax_rate'] * 100, 2) ?>%) -
                                                <?= escape_html($tax['tax_code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select tax rate for this order</small>
                                </div>
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
                            <?php foreach ($order_items as $item): ?>
                                <div class="row mb-2 line-item">
                                    <div class="col-md-5">
                                        <label class="form-label">Item</label>
                                        <select class="form-select item-select" name="item_id[]" required
                                            onchange="updateItemPrice(this)">
                                            <option value="">Select Item</option>
                                            <?php foreach ($items as $inv_item): ?>
                                                <option value="<?= $inv_item['id'] ?>"
                                                    data-price="<?= $inv_item['selling_price'] ?>"
                                                    <?= $inv_item['id'] == $item['item_id'] ? 'selected' : '' ?>>
                                                    <?= escape_html($inv_item['code']) ?> -
                                                    <?= escape_html($inv_item['name']) ?>
                                                    ($<?= number_format($inv_item['selling_price'], 2) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="form-control form-control-sm mt-1 description-input" name="description[]" value="<?= isset($item['description']) ? escape_html($item['description']) : '' ?>" placeholder="Item description / dimensions (optional)">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Qty</label>
                                        <input type="number" class="form-control quantity-input" name="quantity[]" min="1"
                                            step="1" value="<?= $item['quantity'] ?>" required
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Price</label>
                                        <input type="number" class="form-control price-input" name="unit_price[]" min="0"
                                            step="0.01" value="<?= $item['unit_price'] ?>" required
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">Disc%</label>
                                        <input type="number" class="form-control discount-input" name="discount_percent[]"
                                            min="0" max="100" step="0.01" value="<?= $item['discount_percent'] ?? '0.00' ?>"
                                            oninput="calculateLineTotal(this.closest('.line-item'))">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Total</label>
                                        <input type="text" class="form-control total-input"
                                            value="<?= number_format((float) ($item['line_total'] ?? 0), 2) ?>" readonly>
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
                        </div>
                    </div>
                </div>

                <!-- Notes and Custom Fields -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes"
                                    rows="3"><?= escape_html($order['notes'] ?? '') ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                                <textarea class="form-control" id="custom_fields" name="custom_fields"
                                    rows="3" placeholder="Custom fields (e.g., specific instructions for Jones/PTH invoice)"><?= escape_html($order['custom_fields'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header">
                    <h5>Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong id="subtotal">$<?= number_format($order['subtotal'], 2) ?></strong>
                    </div>
                    <input type="hidden" id="order_discount_amount" name="order_discount_amount" value="<?= number_format($order['discount_amount'], 2, '.', '') ?>">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Discount Applied:</span>
                        <span id="discount_display"
                            class="text-danger">-$<?= number_format($order['discount_amount'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax:</span>
                        <strong id="tax">$<?= number_format($order['tax_amount'], 2) ?></strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total:</strong>
                        <strong id="total"
                            class="text-primary fs-5">$<?= number_format($order['total_amount'], 2) ?></strong>
                    </div>
                    <input type="hidden" id="subtotal_amount" name="subtotal">
                    <input type="hidden" id="order_discount_amount_hidden" name="order_discount_amount">
                    <input type="hidden" id="tax_amount" name="tax">
                    <input type="hidden" id="total_amount" name="total">
                </div>
            </div>

            <div class="d-grid gap-2 mt-3">
                <button type="submit" class="btn btn-success btn-lg">
                    <i class="fas fa-save me-2"></i>Update Order
                </button>
                <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
    </form>
</div>
</div>
</div>

<style>
    .card { border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: none; margin-bottom: 1.5rem; }
    .card-header { background: transparent; border-bottom: 1px solid rgba(0,0,0,0.05); font-weight: 600; padding: 1.25rem; }
    #project_section { transition: all 0.3s ease; animation: slideDown 0.4s ease-out; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script src="../assets/js/sales_lines.js"></script>
<script>
function toggleProjectSection() {
    const section = document.getElementById('project_section');
    const isProject = document.getElementById('sale_type_project').checked;
    section.style.display = isProject ? 'block' : 'none';
    calculateTotals();
}

function applyStageAmount() {
    calculateTotals();
}

// Override calculateTotals from sales_lines.js
const originalCalculateTotals = window.calculateTotals;
window.calculateTotals = function() {
    const isProject = document.getElementById('sale_type_project')?.checked;
    const stageSelect = document.getElementById('project_stage_id');
    const orderDiscountAmount = parseFloat(document.getElementById('order_discount_amount')?.value) || 0;
    
    if (isProject && stageSelect && stageSelect.value !== "") {
        const selectedOption = stageSelect.options[stageSelect.selectedIndex];
        const stageAmount = parseFloat(selectedOption.getAttribute('data-amount')) || 0;
        
        // Use Stage Amount as subtotal
        updateSummaryDisplays(stageAmount);
    } else {
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
    
    // Hidden Fields
    document.getElementById('subtotal_amount').value = subtotal.toFixed(2);
    document.getElementById('tax_amount').value = taxAmount.toFixed(2);
    document.getElementById('total_amount').value = total.toFixed(2);
}

$(document).ready(function() {
    // Initialize totals on load
    calculateTotals();
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>