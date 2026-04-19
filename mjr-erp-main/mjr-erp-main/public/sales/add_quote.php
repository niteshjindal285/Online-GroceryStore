<?php
/**
 * Add Quote Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_sales');

$page_title = 'Add Quote - MJR Group ERP';
$company_id = active_company_id(1);

// Get customers for dropdown
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get inventory items for dropdown
$items = db_fetch_all("SELECT id, code, name, selling_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get active tax classes for dropdown
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_code, tax_rate, tax_type FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('sales_tax', 'both') ORDER BY tax_name");

// Pre-select customer if coming from customer view
$selected_customer = get('customer_id');

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $customer_id = post('customer_id');
            $quote_date = post('quote_date');
            $expiry_date = post('expiry_date');
            $status = post('status', 'draft');
            $tax_class_id = post('tax_class_id'); // Selected tax class
            $notes = post('notes');
            $custom_fields = post('custom_fields');
            
            // Get line items
            $item_ids = post('item_id', []);
            $descriptions = post('description', []);
            $quantities = post('quantity', []);
            $unit_prices = post('unit_price', []);
            $discount_pcts = post('discount_pct', []);
            
            $errors = [];
            if (empty($customer_id)) $errors['customer_id'] = err_required();
            if (empty($quote_date))  $errors['quote_date']  = err_required();

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
                    $disc = floatval($discount_pcts[$i] ?? 0);
                    $line_total = ($qty * $price) * (1 - ($disc / 100));
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
                    $tax_amount = $subtotal * floatval($tax_class['tax_rate']);
                }
            }
            
            $discount_id = post('discount_id');
            $discount_amount = floatval(post('discount_amount', 0));
            $total_amount = ($subtotal - $discount_amount) + $tax_amount;
            
            // Generate quote number
            $last_quote = db_fetch("SELECT quote_number FROM quotes ORDER BY id DESC LIMIT 1");
            if ($last_quote && preg_match('/QT-(\d+)/', $last_quote['quote_number'], $matches)) {
                $next_num = intval($matches[1]) + 1;
            } else {
                $next_num = 1;
            }
            $quote_number = 'QT-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
            
            // Start transaction
            db_begin_transaction();
            
            // Insert quote header
            $sql = "INSERT INTO quotes (quote_number, customer_id, quote_date, expiry_date, status, subtotal, discount_amount, manual_discount, tax_amount, total_amount, tax_class_id, notes, custom_fields, created_by, company_id, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $quote_id = db_insert($sql, [$quote_number, $customer_id, $quote_date, $expiry_date, $status, $subtotal, $discount_amount, $discount_id, $tax_amount, $total_amount, $tax_class_id, $notes, $custom_fields, $_SESSION['user_id'], $_SESSION['company_id']]);
            
            // Insert quote lines
            $line_sql = "INSERT INTO quote_lines (quote_id, item_id, description, quantity, unit_price, discount_pct, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)";
            for ($i = 0; $i < count($item_ids); $i++) {
                if (!empty($item_ids[$i])) {
                    $desc = $descriptions[$i] ?? null;
                    if (trim($desc) === '') $desc = null;
                    
                    $qty = floatval($quantities[$i]);
                    $price = floatval($unit_prices[$i]);
                    $disc = floatval($discount_pcts[$i] ?? 0);
                    $line_total = ($qty * $price) * (1 - ($disc / 100));
                    
                    db_insert($line_sql, [$quote_id, $item_ids[$i], $desc, $qty, $price, $disc, $line_total]);
                }
            }
            
                db_commit();
                
                set_flash('Quote created successfully!', 'success');
                redirect('view_quote.php?id=' . $quote_id);
            }
        } catch (Exception $e) {
            db_rollback();
            log_error("Error creating quote: " . $e->getMessage());
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
                <h2><i class="fas fa-plus me-2"></i>Add Quote</h2>
                <a href="quotes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Quotes
                </a>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5>Quote Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>" id="customer_id" name="customer_id" required>
                                            <option value="">Select Customer</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= $customer['id'] ?>" <?= (post('customer_id') ?: $selected_customer) == $customer['id'] ? 'selected' : '' ?>>
                                                    <?= escape_html($customer['customer_code']) ?> - <?= escape_html($customer['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['customer_id'])): ?>
                                            <div class="invalid-feedback"><?= $errors['customer_id'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="quote_date" class="form-label">Quote Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control <?= isset($errors['quote_date']) ? 'is-invalid' : '' ?>" id="quote_date" name="quote_date" value="<?= post('quote_date', date('Y-m-d')) ?>" required>
                                        <?php if (isset($errors['quote_date'])): ?>
                                            <div class="invalid-feedback"><?= $errors['quote_date'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft">Draft</option>
                                            <option value="sent">Sent</option>
                                            <option value="accepted">Accepted</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="expired">Expired</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tax_class_id" class="form-label">Tax Class</label>
                                        <select class="form-select" id="tax_class_id" name="tax_class_id" onchange="calculateTotals()">
                                            <option value="">No Tax (0%)</option>
                                            <?php foreach ($tax_classes as $tax): ?>
                                                <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['tax_rate'] ?>" <?= (floatval($tax['tax_rate']) == 0.15) ? 'selected' : '' ?>>
                                                    <?= escape_html($tax['tax_name']) ?> (<?= number_format($tax['tax_rate'] * 100, 2) ?>%) - <?= escape_html($tax['tax_code']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Select tax rate for this quote</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="discount_id" class="form-label">Discount</label>
                                        <select class="form-select" id="discount_dropdown" name="discount_id" onchange="applyDiscount()">
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
                                                <option value="<?= $disc['id'] ?>" data-type="<?= $disc['discount_type'] ?>" data-value="<?= $disc['discount_value'] ?>">
                                                    <?= $display ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" id="order_discount_amount" name="discount_amount" value="0.00">
                                        <small class="text-muted">Select an approved discount</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quote Items -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Quote Items</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addLineItem()">
                                    <i class="fas fa-plus me-1"></i>Add Item
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (isset($errors['items'])): ?>
                                    <div class="alert alert-danger"><?= $errors['items'] ?></div>
                                <?php endif; ?>
                                <div id="lineItems">
                                    <div class="row mb-2 line-item">
                                        <div class="col-md-4">
                                            <label class="form-label">Item</label>
                                            <select class="form-select item-select" name="item_id[]" required onchange="updateItemPrice(this)">
                                                <option value="">Select Item</option>
                                                <?php foreach ($items as $item): ?>
                                                    <option value="<?= $item['id'] ?>" data-price="<?= $item['selling_price'] ?>">
                                                        <?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?> ($<?= number_format($item['selling_price'], 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1 description-input" name="description[]" placeholder="Item description / dimensions (optional)">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" class="form-control quantity-input" name="quantity[]" min="1" step="1" value="1" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Unit Price ($)</label>
                                            <input type="number" class="form-control price-input" name="unit_price[]" min="0" step="0.01" value="0.00" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Disc(%)</label>
                                            <input type="number" class="form-control discount-input" name="discount_pct[]" min="0" max="100" step="0.01" value="0.00">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Total</label>
                                            <input type="text" class="form-control total-input" value="0.00" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeLineItem(this)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes and Custom Fields -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Special instructions or notes"></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                                        <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3" placeholder="Custom fields (e.g., specific instructions for Jones/PTH invoice)"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quote Summary -->
                    <div class="col-md-4">
                        <div class="card sticky-top" style="top: 20px;">
                            <div class="card-header">
                                <h5>Quote Summary</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <strong id="subtotal">$0.00</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>Discount:</span>
                                    <strong id="discount_display">-$0.00</strong>
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
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>Create Quote
                            </button>
                            <a href="quotes.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/sales_lines.js"></script>


<?php include __DIR__ . '/../../templates/footer.php'; ?>
