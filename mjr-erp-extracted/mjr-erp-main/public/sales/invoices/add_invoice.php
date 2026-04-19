<?php
/**
 * Create Invoice (from SO, Quote, or fresh)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';
require_login();

require_once __DIR__ . '/../../../includes/project_service.php';
$page_title  = 'New Invoice - MJR Group ERP';
$company_id  = active_company_id(1);
$errors      = [];

// Load source data
$from_so_raw    = get('from_so');
$from_quote_raw = get('from_quote');
$customer_id_pre = intval(get('customer_id', 0));

$source_so = null;
$from_so_id = 0;
if ($from_so_raw) {
    if (is_numeric($from_so_raw)) {
        $source_so = db_fetch("SELECT so.*, c.name as customer_name FROM sales_orders so JOIN customers c ON c.id=so.customer_id WHERE so.id=? AND so.company_id = ?", [intval($from_so_raw), $company_id]);
    } else {
        $source_so = db_fetch("SELECT so.*, c.name as customer_name FROM sales_orders so JOIN customers c ON c.id=so.customer_id WHERE so.order_number=? AND so.company_id = ?", [$from_so_raw, $company_id]);
    }
    if ($source_so) $from_so_id = $source_so['id'];
}

$source_quote = null;
$from_quote_id = 0;
if ($from_quote_raw) {
    if (is_numeric($from_quote_raw)) {
        $source_quote = db_fetch("SELECT q.*, c.name as customer_name FROM quotes q JOIN customers c ON c.id=q.customer_id WHERE q.id=? AND q.company_id = ?", [intval($from_quote_raw), $company_id]);
    } else {
        $source_quote = db_fetch("SELECT q.*, c.name as customer_name FROM quotes q JOIN customers c ON c.id=q.customer_id WHERE q.quote_number=? AND q.company_id = ?", [$from_quote_raw, $company_id]);
    }
    if ($source_quote) $from_quote_id = $source_quote['id'];
}

$source_lines = [];
if ($source_so) {
    $source_lines = db_fetch_all("SELECT sol.*, ii.name as item_name, ii.code as item_code FROM sales_order_lines sol JOIN inventory_items ii ON ii.id=sol.item_id WHERE sol.order_id=?", [$from_so_id]);
} elseif ($source_quote) {
    $source_lines = db_fetch_all("SELECT ql.*, ii.name as item_name, ii.code as item_code FROM quote_lines ql JOIN inventory_items ii ON ii.id=ql.item_id WHERE ql.quote_id=?", [$from_quote_id]);
}

$customers = db_fetch_all("SELECT id, code, name FROM customers WHERE is_active=1 AND credit_hold=0 AND company_id = ? ORDER BY name", [$company_id]);
$items     = db_fetch_all("SELECT ii.id, ii.code, ii.name, ii.selling_price, ii.cost_price, ii.price_includes_tax, ii.tax_class_id, COALESCE(tc.tax_rate, 0) AS tax_rate
    FROM inventory_items ii
    LEFT JOIN tax_configurations tc ON tc.id = ii.tax_class_id
    WHERE ii.is_active=1 AND ii.company_id = ?
    ORDER BY ii.name", [$company_id]);
$active_discounts = db_fetch_all("SELECT id, name, discount_code, notes, discount_type, discount_value FROM sales_discounts WHERE status = 'approved' AND (expiry_date IS NULL OR expiry_date >= CURDATE())");

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $cust_id       = intval(post('customer_id'));
        $inv_date      = post('invoice_date');
        $due_date      = post('due_date') ?: null;
        $discount_amt  = floatval(post('discount_amount', 0));
        $tax_amt       = floatval(post('tax_amount', 0));
        $notes         = trim(post('notes'));
        $so_id         = intval(post('so_id')) ?: null;
        $quote_id      = intval(post('quote_id')) ?: null;
        $item_ids      = post('item_id', []);
        $descriptions  = post('description', []);
        $quantities    = post('quantity', []);
        $unit_prices   = post('unit_price', []);
        $disc_pcts     = post('discount_pct', []);
        
        // Project Fields
        $sale_type     = post('sale_type', 'normal');
        $proj_id       = intval(post('project_id')) ?: null;
        $stage_id      = intval(post('project_stage_id')) ?: null;
        
        if ($sale_type === 'project' && !$proj_id) {
            // New project created on-the-fly from Invoice screen
            $proj_res = project_create_with_stages($_POST, $_SESSION['user_id']);
            if ($proj_res) {
                $proj_id = $proj_res['project_id'];
                $stage_idx = intval(post('project_stage_id'));
                if ($stage_idx && isset($proj_res['stage_ids'][$stage_idx - 1])) {
                    $stage_id = $proj_res['stage_ids'][$stage_idx - 1];
                }
            }
        }

        if (!$cust_id)    $errors[] = 'Customer is required.';
        if (!$inv_date)   $errors[] = 'Invoice date is required.';
        if (empty($item_ids)) $errors[] = 'At least one line item is required.';

        // Check credit hold
        if ($cust_id) {
            $chk = db_fetch("SELECT credit_hold, credit_limit FROM customers WHERE id=?", [$cust_id]);
            if ($chk && $chk['credit_hold']) $errors[] = 'Customer account is on credit hold. Invoice cannot be created.';
        }

        if (empty($errors)) {
            $subtotal = 0;
            $lines = [];
            foreach ($item_ids as $i => $iid) {
                if (!$iid) continue;
                $qty   = floatval($quantities[$i] ?? 1);
                $price = floatval($unit_prices[$i] ?? 0);
                $disc  = floatval($disc_pcts[$i] ?? 0);
                $lt    = $qty * $price * (1 - $disc/100);
                $subtotal += $lt;
                $lines[] = ['item_id'=>$iid,'desc'=>$descriptions[$i]??'','qty'=>$qty,'price'=>$price,'disc'=>$disc,'total'=>$lt];
            }
            $tax_amount  = $tax_amt;
            $total       = $subtotal - $discount_amt + $tax_amount;

            // Generate invoice number
            $last = db_fetch("SELECT invoice_number FROM invoices ORDER BY id DESC LIMIT 1");
            if ($last && preg_match('/INV(\d+)/', $last['invoice_number'], $m)) {
                $next = intval($m[1]) + 1;
            } else {
                $next = 7001;
            }
            $inv_number = 'INV' . $next;

            db_begin_transaction();
            $inv_id = db_insert("INSERT INTO invoices
                (invoice_number, so_id, quote_id, customer_id, company_id,
                 invoice_date, due_date, subtotal, tax_amount, discount_amount, total_amount,
                 amount_paid, payment_status, is_locked, notes, created_by, sale_type, project_id, project_stage_id)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,0,'open',1,?,?,?,?,?)",
                [$inv_number, $so_id, $quote_id, $cust_id, $company_id,
                 $inv_date, $due_date, $subtotal, $tax_amount, $discount_amt, $total,
                 $notes, $_SESSION['user_id'], $sale_type, $proj_id, $stage_id]);

            foreach ($lines as $l) {
                db_insert("INSERT INTO invoice_lines (invoice_id, item_id, description, quantity, unit_price, discount_pct, line_total) VALUES (?,?,?,?,?,?,?)",
                    [$inv_id, $l['item_id'], $l['desc'], $l['qty'], $l['price'], $l['disc'], $l['total']]);
            }

            // Auto-create delivery schedule
            db_insert("INSERT INTO delivery_schedule (invoice_id, status, created_by) VALUES (?,'pending',?)",
                [$inv_id, $_SESSION['user_id']]);

            // If from SO, mark SO as invoiced
            if ($so_id) {
                db_query("UPDATE sales_orders SET status='invoiced' WHERE id=?", [$so_id]);
            }
            if ($quote_id) {
                db_query("UPDATE quotes SET status='invoiced', converted_to_order_id=? WHERE id=?", [$inv_id, $quote_id]);
            }

            // Mark Project Stage as invoiced
            if ($stage_id) {
                db_query("UPDATE project_stages SET status='invoiced' WHERE id=?", [$stage_id]);
            }

            db_commit();
            set_flash("Invoice $inv_number created and added to delivery schedule.", 'success');
            redirect("view_invoice.php?id=$inv_id");
        }
    }
}

// Pre-fill customer and totals
$default_customer_id = $source_so['customer_id'] ?? $source_quote['customer_id'] ?? $customer_id_pre;
$default_inv_date    = date('Y-m-d');
$default_due_date    = $source_so['required_date'] ?? $source_quote['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
$default_discount    = $source_so['discount_amount'] ?? $source_quote['discount_amount'] ?? 0;
$default_tax         = $source_so['tax_amount'] ?? $source_quote['tax_amount'] ?? 0;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-file-invoice me-2"></i>New Invoice</h2>
        <?php if ($source_so): ?>
            <p class="text-muted mb-0">Pulled from Sales Order: <strong><?= escape_html($source_so['order_number']) ?></strong></p>
        <?php elseif ($source_quote): ?>
            <p class="text-muted mb-0">Pulled from Quote: <strong><?= escape_html($source_quote['quote_number'] ?? '') ?></strong></p>
        <?php endif; ?>
    </div>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="so_id"    value="<?= $from_so_id ?>">
    <input type="hidden" name="quote_id" value="<?= $from_quote_id ?>">

    <!-- Quick Links to pull source -->
    <?php if (!$from_so_id && !$from_quote_id): ?>
    <div class="alert alert-info d-flex gap-3 align-items-center py-2 mb-3">
        <span><i class="fas fa-lightbulb me-1"></i>Pull from existing:</span>
        <a href="?from_so=<?= get('from_so',0) ?>" class="btn btn-sm btn-outline-primary" onclick="this.href='?from_so='+prompt('Enter Sales Order ID:','');return true;">
            Import from SO
        </a>
        <a href="?from_quote=<?= get('from_quote',0) ?>" class="btn btn-sm btn-outline-secondary" onclick="this.href='?from_quote='+prompt('Enter Quote ID:','');return true;">
            Import from Quote
        </a>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Invoice Details</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $c['id'] == $default_customer_id ? 'selected' : '' ?>>
                                    <?= escape_html($c['code']) ?> — <?= escape_html($c['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Invoice Date *</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= $default_inv_date ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?= $default_due_date ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Payment instructions, references..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="manual_discount" class="form-label">Sales Discount</label>
                            <select class="form-select" id="manual_discount" onchange="calcInvTotals()">
                                <option value="" data-type="fixed" data-value="0">No Discount</option>
                                <?php 
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
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sale Type & Project Section -->
            <div class="card mb-4 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <label class="form-label d-block small fw-bold text-muted">INVOICE TYPE</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="sale_type" id="sale_type_normal" value="normal" autocomplete="off" checked onchange="toggleProjectSection()">
                                <label class="btn btn-outline-secondary" for="sale_type_normal">Standard Invoice</label>

                                <input type="radio" class="btn-check" name="sale_type" id="sale_type_project" value="project" autocomplete="off" onchange="toggleProjectSection()">
                                <label class="btn btn-outline-primary" for="sale_type_project">Project Phase Claim</label>
                            </div>
                        </div>
                    </div>

                    <div id="project_section" style="display: none;" class="mt-4 p-4 rounded-4 bg-light border">
                        <h6 class="text-primary mb-4 border-bottom pb-2"><i class="fas fa-project-diagram me-2"></i>Project Claim Settings</h6>
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" id="project_name" name="project_name" placeholder="Name">
                                    <label for="project_name">Project Title</label>
                                </div>
                                <div class="card bg-white border-0 shadow-sm p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 small fw-bold">Billing Breakdown (Stages)</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProjectStage()">+ Add</button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered mb-0" style="font-size: 0.85rem;">
                                            <thead class="table-dark">
                                                <tr><th>Stage</th><th style="width:70px;">%</th><th style="width:120px;">Amount</th></tr>
                                            </thead>
                                            <tbody id="project_stages_body"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-body">
                                        <label class="small fw-bold text-muted">TOTAL PROJECT VALUE</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control fw-bold fs-5" id="project_total_value" name="project_total_value" step="0.01" value="0.00" oninput="calculateProjectStages()">
                                        </div>
                                        
                                        <div class="mt-3">
                                            <label class="small fw-bold text-primary">SELECT PHASE FOR THIS INVOICE</label>
                                            <select class="form-select border-primary fw-bold" id="project_stage_id" name="project_stage_id" onchange="applyStageAmount()">
                                                <option value="">Full / Itemized Invoice</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <?php if (is_admin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
                                <div class="card bg-dark text-white border-0 shadow-sm">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-warning small fw-bold"><i class="fas fa-user-shield me-1"></i>BOSS COPY</span>
                                            <span class="badge bg-secondary" style="font-size: 9px;">CONFIDENTIAL</span>
                                        </div>
                                        <div class="row g-1 text-center border-top border-secondary pt-2 mt-1">
                                            <div class="col-6">
                                                <div class="opacity-75" style="font-size: 10px;">TOTAL COST</div>
                                                <div class="fw-bold" id="internal_cost_display">$0.00</div>
                                            </div>
                                            <div class="col-6">
                                                <div class="opacity-75" style="font-size: 10px;">MARGIN</div>
                                                <div class="fw-bold text-success" id="internal_margin_display">0%</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- Line Items -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Line Items</h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="addInvLine()"><i class="fas fa-plus me-1"></i>Add Item</button>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-2 d-none d-md-flex text-muted small fw-bold">
                        <div class="col-md-5">Item / Description</div>
                        <div class="col-md-1">Qty</div>
                        <div class="col-md-2">Unit Price</div>
                        <div class="col-md-1">Disc %</div>
                        <div class="col-md-2">Total</div>
                        <div class="col-md-1 text-center"><i class="fas fa-cog"></i></div>
                    </div>
                    <div id="invLines">
                        <?php if (!empty($source_lines)): ?>
                            <?php foreach ($source_lines as $sl): ?>
                            <div class="row g-2 mb-2 inv-line">
                                <div class="col-md-5">
                                    <select name="item_id[]" class="form-select form-select-sm" required onchange="updateInvPrice(this)">
                                        <option value="">Select Item</option>
                                        <?php foreach ($items as $it): ?>
                                        <option value="<?= $it['id'] ?>" data-price="<?= $it['selling_price'] ?>" data-cost="<?= $it['cost_price'] ?>" <?= $it['id']==$sl['item_id']?'selected':'' ?>>
                                            <?= escape_html($it['code']) ?> — <?= escape_html($it['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" name="description[]" class="form-control form-control-sm mt-1" value="<?= escape_html($sl['description']??'') ?>" placeholder="Description">
                                </div>
                                <div class="col-md-1"><input type="number" name="quantity[]" class="form-control form-control-sm" value="<?= $sl['quantity'] ?>" min="0.01" step="0.01" oninput="calcInvLine(this)" required></div>
                                <div class="col-md-2"><input type="number" name="unit_price[]" class="form-control form-control-sm" value="<?= $sl['unit_price'] ?>" min="0" step="0.01" oninput="calcInvLine(this)" required></div>
                                <input type="hidden" name="item_includes_tax[]" value="0">
                                <input type="hidden" name="item_tax_rate[]" value="0">
                                <input type="hidden" name="line_tax[]" value="0.00">
                                <div class="col-md-1"><input type="number" name="discount_pct[]" class="form-control form-control-sm" value="<?= $sl['discount_percent']??0 ?>" min="0" max="100" step="0.01" oninput="calcInvLine(this)"></div>
                                <div class="col-md-2"><input type="text" class="form-control form-control-sm inv-line-total" value="<?= number_format($sl['line_total'],2) ?>" readonly></div>
                                <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.inv-line').remove(); calcInvTotals()"><i class="fas fa-trash"></i></button></div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                        <div class="row g-2 mb-2 inv-line">
                            <div class="col-md-5">
                                <select name="item_id[]" class="form-select form-select-sm" required onchange="updateInvPrice(this)">
                                    <option value="">Select Item</option>
                                    <?php foreach ($items as $it): ?>
                                    <option value="<?= $it['id'] ?>" data-price="<?= $it['selling_price'] ?>" data-cost="<?= $it['cost_price'] ?>">
                                        <?= escape_html($it['code']) ?> — <?= escape_html($it['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="description[]" class="form-control form-control-sm mt-1" placeholder="Description">
                            </div>
                            <div class="col-md-1"><input type="number" name="quantity[]" class="form-control form-control-sm" value="1" min="0.01" step="0.01" oninput="calcInvLine(this)" required></div>
                            <div class="col-md-2"><input type="number" name="unit_price[]" class="form-control form-control-sm" value="0.00" min="0" step="0.01" oninput="calcInvLine(this)" required></div>
                            <input type="hidden" name="item_includes_tax[]" value="0">
                            <input type="hidden" name="item_tax_rate[]" value="0">
                            <input type="hidden" name="line_tax[]" value="0.00">
                            <div class="col-md-1"><input type="number" name="discount_pct[]" class="form-control form-control-sm" value="0" min="0" max="100" step="0.01" oninput="calcInvLine(this)"></div>
                            <div class="col-md-2"><input type="text" class="form-control form-control-sm inv-line-total" value="0.00" readonly></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Panel -->
        <div class="col-md-4">
            <div class="card sticky-top" style="top:20px;">
                <div class="card-header"><h5 class="mb-0">Invoice Summary</h5></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2"><span>Subtotal:</span><strong id="inv_subtotal">$0.00</strong></div>
                    <input type="hidden" name="discount_amount" id="inv_discount_inp" value="<?= number_format($default_discount, 2, '.', '') ?>">
                    <div class="d-flex justify-content-between mb-2"><span>Discount:</span><span class="text-danger" id="inv_discount_disp">-$0.00</span></div>
                    <div class="mb-3">
                        <label class="form-label small">Tax Amount ($)</label>
                        <input type="number" name="tax_amount" class="form-control form-control-sm" id="inv_tax_inp" value="<?= number_format($default_tax, 2, '.', '') ?>" min="0" step="0.01" oninput="calcInvTotals()">
                    </div>
                    <div class="d-flex justify-content-between mb-2"><span>Tax:</span><span id="inv_tax_disp">$0.00</span></div>
                    <hr>
                    <div class="d-flex justify-content-between"><strong>Total:</strong><strong class="text-primary fs-5" id="inv_total">$0.00</strong></div>
                    <div class="alert alert-info mt-3 py-2 small">
                        <i class="fas fa-info-circle me-1"></i>Invoice is <strong>locked</strong> after saving. Use Cancel or Sales Return to amend.
                    </div>
                    <div class="d-grid mt-3">
                        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save me-1"></i>Create Invoice</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Project Logic
function toggleProjectSection() {
    const isProject = document.getElementById('sale_type_project').checked;
    document.getElementById('project_section').style.display = isProject ? 'block' : 'none';
    calcInvTotals();
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
    `;
    container.appendChild(row);
    updateStageDropdown();
    return row;
}

function calculateProjectStages(input = null, source = null) {
    const totalValue = parseFloat(document.getElementById('project_total_value').value) || 0;
    const stages = document.querySelectorAll('#project_stages_body tr');
    if (input && source && totalValue > 0) {
        const row = input.closest('tr');
        const pct = row.querySelector('.stage-pct');
        const amt = row.querySelector('.stage-amt');
        if (source === 'pct') amt.value = (totalValue * (parseFloat(pct.value) / 100)).toFixed(2);
        else pct.value = ((parseFloat(amt.value) / totalValue) * 100).toFixed(2);
    }
    updateStageDropdown();
}

function updateStageDropdown() {
    const dropdown = document.getElementById('project_stage_id');
    const currentValue = dropdown.value;
    const stages = document.querySelectorAll('#project_stages_body tr');
    dropdown.innerHTML = '<option value="">Full / Itemized Invoice</option>';
    stages.forEach((row, index) => {
        const name = row.querySelector('input[name="stage_name[]"]').value;
        const amt = row.querySelector('.stage-amt').value;
        const opt = document.createElement('option');
        opt.value = index + 1;
        opt.setAttribute('data-amount', amt);
        opt.textContent = `${name} - $${parseFloat(amt).toLocaleString(undefined, {minimumFractionDigits: 2})}`;
        dropdown.appendChild(opt);
    });
    dropdown.value = currentValue;
}

function applyStageAmount() {
    calcInvTotals();
}

function updateBossCopy(projectValue) {
    const costDisp = document.getElementById('internal_cost_display');
    const margDisp = document.getElementById('internal_margin_display');
    if (!costDisp || !margDisp) return;
    let cost = 0;
    document.querySelectorAll('.inv-line').forEach(row => {
        const sel = row.querySelector('select[name="item_id[]"]');
        const qty = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
        const opt = sel.options[sel.selectedIndex];
        if (opt && opt.value) cost += (parseFloat(opt.dataset.cost) || 0) * qty;
    });
    const margin = projectValue > 0 ? ((projectValue - cost) / projectValue) * 100 : 0;
    costDisp.textContent = '$' + cost.toLocaleString(undefined, {minimumFractionDigits: 2});
    margDisp.textContent = margin.toFixed(1) + '%';
    margDisp.className = 'fw-bold ' + (margin >= 30 ? 'text-success' : (margin >= 15 ? 'text-warning' : 'text-danger'));
}

const INV_ITEMS_JSON = <?= json_encode(array_map(fn($i) => ['id'=>$i['id'],'price'=>$i['selling_price']], $items)) ?>;

function updateInvPrice(sel) {
    const opt  = sel.options[sel.selectedIndex];
    const price = opt.dataset.price || '0';
    const row  = sel.closest('.inv-line');
    row.querySelector('[name="unit_price[]"]').value = parseFloat(price).toFixed(2);
    calcInvLine(sel);
}
function calcInvLine(el) {
    const row   = el.closest('.inv-line');
    const qty   = parseFloat(row.querySelector('[name="quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name="unit_price[]"]').value) || 0;
    const disc  = parseFloat(row.querySelector('[name="discount_pct[]"]').value) || 0;
    const taxIncluded = row.querySelector('[name="item_includes_tax[]"]').value === '1';
    const taxRate = parseFloat(row.querySelector('[name="item_tax_rate[]"]').value) || 0;
    let netPrice = price;
    let lineTax = 0;
    if (taxIncluded && taxRate > 0) {
        const inclusiveBase = 1 + taxRate;
        const exclusivePrice = price / inclusiveBase;
        netPrice = exclusivePrice;
        lineTax = qty * (price - exclusivePrice) * (1 - disc/100);
    }
    const total = qty * netPrice * (1 - disc/100);
    const taxField = row.querySelector('[name="line_tax[]"]');
    if (taxField) taxField.value = lineTax.toFixed(2);
    row.querySelector('.inv-line-total').value = total.toFixed(2);
    calcInvTotals();
}
function calcInvTotals() {
    let sub = 0;
    document.querySelectorAll('.inv-line-total').forEach(i => sub += parseFloat(i.value)||0);
    
    // Check manual_discount dropdown
    const manualDiscount = document.getElementById('manual_discount');
    const discInp = document.getElementById('inv_discount_inp');
    let disc = 0;
    
    if (manualDiscount && manualDiscount.value) {
        const sel = manualDiscount.options[manualDiscount.selectedIndex];
        const type = sel.getAttribute('data-type');
        const val = parseFloat(sel.getAttribute('data-value')) || 0;
        if (type === 'percentage') {
            disc = sub * (val / 100);
        } else {
            disc = val;
        }
        if (discInp) discInp.value = disc.toFixed(2);
    } else {
        if (discInp) disc = parseFloat(discInp.value) || 0;
    }

    let tax   = parseFloat(document.getElementById('inv_tax_inp').value)||0;
    const lineTaxInputs = document.querySelectorAll('[name="line_tax[]"]');
    let computedLineTax = 0;
    lineTaxInputs.forEach(input => computedLineTax += parseFloat(input.value) || 0);
    const hasIncludedTaxLines = Array.from(document.querySelectorAll('[name="item_includes_tax[]"]')).some(input => input.value === '1');
    if (hasIncludedTaxLines) {
        tax = computedLineTax;
        if (document.getElementById('inv_tax_inp')) {
            document.getElementById('inv_tax_inp').value = tax.toFixed(2);
        }
    }
    
    // Override subtotal if Stage is selected
    const isProject = document.getElementById('sale_type_project').checked;
    const stageSelect = document.getElementById('project_stage_id');
    if (isProject && stageSelect && stageSelect.value !== "") {
        const sel = stageSelect.options[stageSelect.selectedIndex];
        sub = parseFloat(sel.getAttribute('data-amount')) || 0;
    }

    const total = sub - disc + tax;
    document.getElementById('inv_subtotal').textContent = '$' + sub.toFixed(2);
    document.getElementById('inv_discount_disp').textContent = '-$' + disc.toFixed(2);
    document.getElementById('inv_tax_disp').textContent = '$' + tax.toFixed(2);
    document.getElementById('inv_total').textContent = '$' + total.toFixed(2);

    updateBossCopy(sub);
}

function addInvLine() {
    const opts = <?= json_encode(array_map(fn($it) => ['id'=>$it['id'],'code'=>$it['code'],'name'=>$it['name'],'price'=>$it['selling_price']], $items)) ?>;
    let optHtml = '<option value="">Select Item</option>';
    opts.forEach(o => optHtml += `<option value="${o.id}" data-price="${o.price}">${o.code} — ${o.name}</option>`);
    const div = document.createElement('div');
    div.className = 'row g-2 mb-2 inv-line';
    div.innerHTML = `
        <div class="col-md-5">
            <select name="item_id[]" class="form-select form-select-sm" required onchange="updateInvPrice(this)">${optHtml}</select>
            <input type="text" name="description[]" class="form-control form-control-sm mt-1" placeholder="Description">
        </div>
        <div class="col-md-1"><input type="number" name="quantity[]" class="form-control form-control-sm" value="1" min="0.01" step="0.01" oninput="calcInvLine(this)" required></div>
        <div class="col-md-2"><input type="number" name="unit_price[]" class="form-control form-control-sm" value="0.00" min="0" step="0.01" oninput="calcInvLine(this)" required></div>
        <div class="col-md-1"><input type="number" name="discount_pct[]" class="form-control form-control-sm" value="0" min="0" max="100" step="0.01" oninput="calcInvLine(this)"></div>
        <div class="col-md-2"><input type="text" class="form-control form-control-sm inv-line-total" value="0.00" readonly></div>
        <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.inv-line').remove(); calcInvTotals()"><i class="fas fa-trash"></i></button></div>`;
    document.getElementById('invLines').appendChild(div);
}

document.addEventListener('DOMContentLoaded', function() {
    calcInvTotals();
    
    // Init Project Stages
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

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
