<?php
/**
 * Edit Quote Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Quote - MJR Group ERP';
$company_id = active_company_id(1);

// Get quote ID
$quote_id = get('id');
if (!$quote_id) {
    set_flash('Quote ID not provided.', 'error');
    redirect('quotes.php');
}

// Get quote data
$quote = db_fetch("
    SELECT q.*, c.customer_code, c.name as customer_name
    FROM quotes q
    JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ? AND q.company_id = ?
", [$quote_id, $company_id]);

if (!$quote) {
    set_flash('Quote not found.', 'error');
    redirect('quotes.php');
}

// Get quote items
$quote_items = db_fetch_all("
    SELECT qi.*, i.code, i.name, i.selling_price
    FROM quote_lines qi
    JOIN inventory_items i ON qi.item_id = i.id
    WHERE qi.quote_id = ?
", [$quote_id]);

// Get customers for dropdown
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get inventory items for dropdown
$items = db_fetch_all("SELECT id, code, name, selling_price FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get active tax classes for dropdown
$tax_classes = db_fetch_all("SELECT id, tax_name, tax_code, tax_rate, tax_type FROM tax_configurations WHERE is_active = 1 AND tax_type IN ('sales_tax', 'both') ORDER BY tax_name");

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $quote_date = post('quote_date');
            $expiry_date = post('expiry_date');
            $status = post('status');
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
            
            // Start transaction
            db_begin_transaction();
            
            // Update quote header
            $sql = "UPDATE quotes SET 
                    quote_date = ?, expiry_date = ?, status = ?, subtotal = ?, discount_amount = ?, manual_discount = ?, tax_amount = ?, total_amount = ?, tax_class_id = ?, notes = ?, custom_fields = ?, updated_at = NOW() 
                    WHERE id = ?";
            db_query($sql, [$quote_date, $expiry_date, $status, $subtotal, $discount_amount, $discount_id, $tax_amount, $total_amount, $tax_class_id, $notes, $custom_fields, $quote_id]);
            
            // Delete existing quote lines
            db_query("DELETE FROM quote_lines WHERE quote_id = ?", [$quote_id]);
            
            // Insert new quote lines
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
                
                set_flash('Quote updated successfully!', 'success');
                redirect('view_quote.php?id=' . $quote_id);
            }
        } catch (Exception $e) {
            db_rollback();
            log_error("Error updating quote: " . $e->getMessage());
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
                <h2><i class="fas fa-edit me-2"></i>Edit Quote: <?= escape_html($quote['quote_number']) ?></h2>
                <div>
                    <a href="view_quote.php?id=<?= $quote['id'] ?>" class="btn btn-info">
                        <i class="fas fa-eye me-2"></i>View Quote
                    </a>
                    <a href="quotes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Quotes
                    </a>
                </div>
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
                                        <label class="form-label">Quote Number</label>
                                        <input type="text" class="form-control" value="<?= escape_html($quote['quote_number']) ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Customer</label>
                                        <input type="text" class="form-control" value="<?= escape_html($quote['customer_code']) ?> - <?= escape_html($quote['customer_name']) ?>" readonly>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="quote_date" class="form-label">Quote Date</label>
                                        <input type="date" class="form-control <?= isset($errors['quote_date']) ? 'is-invalid' : '' ?>" id="quote_date" name="quote_date" value="<?= post('quote_date', date('Y-m-d', strtotime($quote['quote_date']))) ?>" required>
                                        <?php if (isset($errors['quote_date'])): ?>
                                            <div class="invalid-feedback"><?= $errors['quote_date'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="expiry_date" class="form-label">Expiry Date</label>
                                        <input type="date" class="form-control" id="expiry_date" name="expiry_date" value="<?= $quote['expiry_date'] ? date('Y-m-d', strtotime($quote['expiry_date'])) : '' ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?= $quote['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="sent" <?= $quote['status'] == 'sent' ? 'selected' : '' ?>>Sent</option>
                                            <option value="accepted" <?= $quote['status'] == 'accepted' ? 'selected' : '' ?>>Accepted</option>
                                            <option value="rejected" <?= $quote['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                            <option value="expired" <?= $quote['status'] == 'expired' ? 'selected' : '' ?>>Expired</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tax_class_id" class="form-label">Tax Class</label>
                                        <select class="form-select" id="tax_class_id" name="tax_class_id" onchange="calculateTotals()">
                                            <option value="">No Tax (0%)</option>
                                            <?php foreach ($tax_classes as $tax): ?>
                                                <option value="<?= $tax['id'] ?>" data-rate="<?= $tax['tax_rate'] ?>" <?= isset($quote['tax_class_id']) && $quote['tax_class_id'] == $tax['id'] ? 'selected' : '' ?>>
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
                                                UNION
                                                SELECT id, name, discount_code, notes, discount_type, discount_value
                                                FROM sales_discounts
                                                WHERE id = ?
                                            ", [$quote['manual_discount']]);
                                            foreach ($active_discounts as $disc): 
                                                $display_val = ($disc['discount_type'] == 'percentage') ? $disc['discount_value'] . '%' : '$' . $disc['discount_value'];
                                                $display = escape_html($disc['name']);
                                                if ($disc['discount_code']) $display .= ' (' . escape_html($disc['discount_code']) . ')';
                                                $display .= ' - ' . $display_val;
                                            ?>
                                                <option value="<?= $disc['id'] ?>" data-type="<?= $disc['discount_type'] ?>" data-value="<?= $disc['discount_value'] ?>" <?= (isset($quote['manual_discount']) && $quote['manual_discount'] == $disc['id']) ? 'selected' : '' ?>>
                                                    <?= $display ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" id="order_discount_amount" name="discount_amount" value="<?= number_format($quote['discount_amount'] ?? 0, 2, '.', '') ?>">
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
                                    <?php foreach ($quote_items as $item): ?>
                                    <div class="row mb-2 line-item">
                                        <div class="col-md-4">
                                            <label class="form-label">Item</label>
                                            <select class="form-select item-select" name="item_id[]" required onchange="updateItemPrice(this)">
                                                <option value="">Select Item</option>
                                                <?php foreach ($items as $inv_item): ?>
                                                    <option value="<?= $inv_item['id'] ?>" data-price="<?= $inv_item['selling_price'] ?>" <?= $inv_item['id'] == $item['item_id'] ? 'selected' : '' ?>>
                                                        <?= escape_html($inv_item['code']) ?> - <?= escape_html($inv_item['name']) ?> ($<?= number_format($inv_item['selling_price'], 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1 description-input" name="description[]" value="<?= isset($item['description']) ? escape_html($item['description']) : '' ?>" placeholder="Item description / dimensions (optional)">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" class="form-control quantity-input" name="quantity[]" min="1" step="1" value="<?= $item['quantity'] ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Unit Price ($)</label>
                                            <input type="number" class="form-control price-input" name="unit_price[]" min="0" step="0.01" value="<?= number_format($item['unit_price'], 2, '.', '') ?>" required>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">Disc(%)</label>
                                            <input type="number" class="form-control discount-input" name="discount_pct[]" min="0" max="100" step="0.01" value="<?= number_format($item['discount_pct'] ?? 0, 2, '.', '') ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Total</label>
                                            <input type="text" class="form-control total-input" value="<?= number_format($item['line_total'] ?? 0, 2) ?>" readonly>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeLineItem(this)">
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
                                        <textarea class="form-control" id="notes" name="notes" rows="3"><?= escape_html($quote['notes'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                                        <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3" placeholder="Custom fields (e.g., specific instructions for Jones/PTH invoice)"><?= escape_html($quote['custom_fields'] ?? '') ?></textarea>
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
                                    <strong id="subtotal">$<?= number_format($quote['subtotal'], 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>Discount:</span>
                                    <strong id="discount_display">-$<?= number_format($quote['discount_amount'] ?? 0, 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax:</span>
                                    <strong id="tax">$<?= number_format($quote['tax_amount'], 2) ?></strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <strong id="total" class="text-primary fs-5">$<?= number_format($quote['total_amount'], 2) ?></strong>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mt-3">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save me-2"></i>Update Quote
                            </button>
                            <a href="view_quote.php?id=<?= $quote['id'] ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="../assets/js/sales_lines.js"></script>


<?php include __DIR__ . '/../../templates/footer.php'; ?>
