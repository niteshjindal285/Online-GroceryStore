<?php
/**
 * Add Receipt
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Receipt - MJR Group ERP';
$company_id = (int)active_company_id(1);
ensure_finance_approval_columns('receipts');
ensure_receipt_invoice_allocations_table();
$approvers = finance_get_approver_users($company_id);
$managers = $approvers['managers'];
$admins = $approvers['admins'];
$errors = [];

$bank_accounts = db_fetch_all("SELECT id, bank_name, account_name, currency FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name");
$customers = db_fetch_all("SELECT id, name FROM customers WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL) ORDER BY name", [$company_id]) ?? [];
$open_invoices = db_fetch_all("
    SELECT i.id, i.invoice_number, i.customer_id, c.name AS customer_name, i.invoice_date, i.due_date,
           (i.total_amount - i.amount_paid) AS outstanding
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    WHERE i.payment_status = 'open'
      AND (i.total_amount - i.amount_paid) > 0
      AND (i.company_id = ? OR i.company_id IS NULL)
    ORDER BY COALESCE(i.due_date, i.invoice_date) ASC, i.id ASC
", [$company_id]) ?? [];

// Auto-generate receipt number
$last = db_fetch("SELECT receipt_number FROM receipts ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last) {
    preg_match('/(\d+)$/', $last['receipt_number'], $m);
    $next_num = (int)($m[1] ?? 0) + 1;
}
$default_receipt_number = 'RCP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $receipt_number  = trim(post('receipt_number', ''));
        $receipt_date    = post('receipt_date', '');
        $company_id      = (int)active_company_id(1);
        $customer_id     = post('customer_id') ?: null;
        $bank_account_id = post('bank_account_id') ?: null;
        $amount          = (float)post('amount', 0);
        $payment_method  = post('payment_method', 'Bank Transfer');
        $reference       = trim(post('reference', ''));
        $description     = trim(post('description', ''));
        $approval_type   = post('approval_type', 'manager');
        $manager_id      = post('manager_id') ?: ((int)($managers[0]['id'] ?? 0) ?: null);
        $admin_id        = post('admin_id') ?: ((int)($admins[0]['id'] ?? 0) ?: null);
        $selected_invoice_ids = array_values(array_unique(array_filter(array_map('intval', (array)post('invoice_ids', [])), function ($v) {
            return $v > 0;
        })));

        if (empty($receipt_number)) $errors['receipt_number'] = err_required();
        if (empty($receipt_date))   $errors['receipt_date']   = err_required();
        if ($amount <= 0)           $errors['amount']         = 'Amount must be greater than 0.';
        if ($company_id <= 0)       $errors['company_id']     = 'Company is required.';
        if (empty($selected_invoice_ids)) $errors['invoice_ids'] = 'Please select at least one invoice.';
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        $selected_invoices = [];
        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM receipts WHERE receipt_number = ?", [$receipt_number]);
            if ($exists) $errors['receipt_number'] = 'Receipt number already exists!';

            if (empty($errors)) {
                $placeholders = implode(',', array_fill(0, count($selected_invoice_ids), '?'));
                $selected_invoices = db_fetch_all("
                    SELECT i.id, i.customer_id, i.payment_status, i.total_amount, i.amount_paid, (i.total_amount - i.amount_paid) AS outstanding
                    FROM invoices i
                    WHERE i.id IN ($placeholders)
                ", $selected_invoice_ids) ?? [];

                if (count($selected_invoices) !== count($selected_invoice_ids)) {
                    $errors['invoice_ids'] = 'One or more selected invoices no longer exist.';
                } else {
                    $customer_ids = array_values(array_unique(array_map(function ($row) {
                        return (int)($row['customer_id'] ?? 0);
                    }, $selected_invoices)));

                    if (count($customer_ids) > 1) {
                        $errors['invoice_ids'] = 'Please select invoices for only one customer at a time.';
                    } else {
                        $invoice_customer_id = (int)($customer_ids[0] ?? 0);
                        if (empty($customer_id)) {
                            $customer_id = $invoice_customer_id;
                        } elseif ((int)$customer_id !== $invoice_customer_id) {
                            $errors['customer_id'] = 'Selected invoices do not belong to the chosen customer.';
                        }
                    }

                    foreach ($selected_invoices as $inv_row) {
                        if (($inv_row['payment_status'] ?? '') !== 'open' || (float)($inv_row['outstanding'] ?? 0) <= 0) {
                            $errors['invoice_ids'] = 'One or more selected invoices are not open.';
                            break;
                        }
                    }
                }
            }
        }

        if (empty($errors)) {
            try {
                usort($selected_invoices, function ($a, $b) {
                    return ($a['id'] ?? 0) <=> ($b['id'] ?? 0);
                });

                $remaining = (float)$amount;
                $allocations = [];
                foreach ($selected_invoices as $inv_row) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $invoice_id = (int)$inv_row['id'];
                    $outstanding = max(0, (float)($inv_row['outstanding'] ?? 0));
                    $allocated = min($remaining, $outstanding);
                    if ($allocated > 0) {
                        $allocations[] = [
                            'invoice_id' => $invoice_id,
                            'amount' => round($allocated, 2),
                        ];
                        $remaining -= $allocated;
                    }
                }

                if (empty($allocations) || $remaining > 0.0001) {
                    throw new Exception('Receipt amount exceeds total outstanding of selected invoices.');
                }

                db_begin_transaction();

                $receipt_id = db_insert(
                    "INSERT INTO receipts (company_id, receipt_number, receipt_date, customer_id, bank_account_id, amount, payment_method, reference, description, status, approval_type, manager_id, admin_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'posted', ?, ?, ?, ?, NOW())",
                    [$company_id, $receipt_number, $receipt_date, $customer_id, $bank_account_id, $amount, $payment_method, $reference, $description, $approval_type, $manager_id, $admin_id, $_SESSION['user_id']]
                );

                foreach ($allocations as $alloc) {
                    db_insert(
                        "INSERT INTO receipt_invoice_allocations (receipt_id, invoice_id, allocated_amount, created_at)
                         VALUES (?, ?, ?, NOW())",
                        [$receipt_id, $alloc['invoice_id'], $alloc['amount']]
                    );

                    db_query(
                        "UPDATE invoices
                         SET amount_paid = LEAST(total_amount, COALESCE(amount_paid, 0) + ?),
                             payment_status = CASE
                                 WHEN LEAST(total_amount, COALESCE(amount_paid, 0) + ?) >= total_amount THEN 'closed'
                                 ELSE 'open'
                             END
                         WHERE id = ? AND payment_status <> 'cancelled'",
                        [$alloc['amount'], $alloc['amount'], $alloc['invoice_id']]
                    );
                }

                db_commit();
                set_flash('Receipt created successfully!', 'success');
                redirect('receipts.php');
            } catch (Exception $e) {
                db_rollback();
                log_error("Error creating receipt: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --rc-bg: #080c1a;
    --rc-panel: #1d243c;
    --rc-panel-2: #1a2035;
    --rc-line: #313a61;
    --rc-cyan: #08d0ef;
    --rc-soft: #8f9dc5;
    --rc-gold: #ffbf45;
    --rc-green: #41c95b;
    --rc-text-header: #ffffff;
    --rc-table-text: #f2f5ff;
    --rc-label: #9ca8cc;
}

[data-bs-theme="light"] {
    --rc-bg: #f8f9fa;
    --rc-panel: #ffffff;
    --rc-panel-2: #f8f9fa;
    --rc-line: #e0e0e0;
    --rc-cyan: #0dcaf0;
    --rc-soft: #6c757d;
    --rc-gold: #ffc107;
    --rc-green: #198754;
    --rc-text-header: #212529;
    --rc-table-text: #212529;
    --rc-label: #495057;
}

body {
    background: var(--rc-bg);
    color: var(--rc-soft);
}
.rc-screen {
    border: 1px solid rgba(8,208,239,.55);
    border-radius: 10px;
    background: rgba(8,208,239,.07);
    color: var(--rc-cyan);
    font-weight: 700;
    padding: .65rem 1rem;
}
.rc-title { color: var(--rc-text-header); font-weight: 800; margin-bottom: .1rem; }
.rc-sub { color: var(--rc-soft); margin-bottom: 0; }
.rc-back {
    border: 1px solid var(--rc-line);
    border-radius: 10px;
    color: var(--rc-soft);
    text-decoration: none;
    padding: .55rem 1rem;
    font-weight: 700;
}
.rc-card {
    border-radius: 12px;
    border: 1px solid var(--rc-line);
    background: linear-gradient(180deg, var(--rc-panel), var(--rc-panel-2));
    overflow: hidden;
}
.rc-card-h {
    border-bottom: 1px solid var(--rc-line);
    padding: .85rem 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.rc-hl {
    display: inline-block;
    min-width: 28px;
    text-align: center;
    border-radius: 7px;
    padding: .2rem .4rem;
    background: #0fc7df;
    color: #00141d;
    font-weight: 900;
    margin-right: .5rem;
}
.rc-htext { color: var(--rc-text-header); font-size: 1.55rem; font-weight: 800; vertical-align: middle; }
.rc-mini {
    border: 1px solid rgba(8,208,239,.45);
    border-radius: 8px;
    padding: .55rem .75rem;
    min-width: 250px;
    text-align: center;
    background: rgba(8,208,239,.05);
}
.rc-mini .cap { color: var(--rc-soft); font-size: .75rem; text-transform: uppercase; }
.rc-mini .val { color: var(--rc-cyan); font-size: 1.1rem; font-weight: 900; line-height: 1.25; }
.rc-label {
    color: var(--rc-label);
    font-size: .76rem;
    text-transform: uppercase;
    letter-spacing: .8px;
    font-weight: 700;
    margin-bottom: .35rem;
}
[data-bs-theme="dark"] .rc-input, [data-bs-theme="dark"] .rc-select {
    width: 100%;
    background: #252d4a;
    border: 1px solid #344271;
    color: #eef3ff;
    border-radius: 8px;
    padding: .58rem .8rem;
}
[data-bs-theme="light"] .rc-input, [data-bs-theme="light"] .rc-select {
    width: 100%;
    background: #ffffff;
    border: 1px solid #dee2e6;
    color: #212529;
    border-radius: 8px;
    padding: .58rem .8rem;
}
.rc-input:focus, .rc-select:focus {
    border-color: rgba(8,208,239,.7);
    box-shadow: 0 0 0 .2rem rgba(8,208,239,.2);
    outline: none;
}
.rc-balance {
    border: 1px solid rgba(8,208,239,.45);
    border-radius: 8px;
    padding: .35rem .75rem;
    color: var(--rc-cyan);
    font-weight: 800;
    background: rgba(8,208,239,.08);
}
.rc-table-wrap { overflow: auto; }
.rc-table {
    width: 100%;
    min-width: 980px;
    border-collapse: collapse;
}
.rc-table thead th {
    padding: .85rem .7rem;
    color: var(--rc-soft);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-size: .72rem;
    border-bottom: 1px solid var(--rc-line);
}
.rc-table tbody td {
    padding: .72rem .7rem;
    border-bottom: 1px solid var(--rc-line);
    color: var(--rc-table-text);
    vertical-align: middle;
}
.rc-link { color: var(--rc-cyan); text-decoration: none; font-weight: 800; }
.rc-num { text-align: right; font-weight: 800; }
[data-bs-theme="dark"] .rc-applied {
    width: 106px;
    text-align: right;
    background: #242b45;
    border: 1px solid #344271;
    color: #eef3ff;
    border-radius: 6px;
    padding: .25rem .45rem;
    font-weight: 700;
}
[data-bs-theme="light"] .rc-applied {
    width: 106px;
    text-align: right;
    background: #ffffff;
    border: 1px solid #dee2e6;
    color: #212529;
    border-radius: 6px;
    padding: .25rem .45rem;
    font-weight: 700;
}
.rc-current { color: var(--rc-green); font-weight: 800; text-align: right; }
.rc-footnote {
    border-left: 2px solid var(--rc-gold);
    background: rgba(255,191,69,.12);
    color: var(--rc-gold);
    border-radius: 6px;
    padding: .5rem .7rem;
    font-size: .88rem;
    font-weight: 600;
}
.rc-actions {
    border-top: 1px solid var(--rc-line);
    padding-top: .85rem;
    margin-top: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.rc-save {
    background: #0fc7df;
    color: #00141d;
    border: 0;
    border-radius: 8px;
    font-weight: 800;
    padding: .58rem 1.2rem;
}
.rc-warn { color: #ff8a5d; font-weight: 700; }
.rc-hidden-check { display: none; }
.rc-error {
    background: rgba(255,79,69,.16);
    color: #ff9b95;
    border: 1px solid rgba(255,79,69,.35);
    border-radius: 10px;
    padding: .75rem 1rem;
}
@media (max-width: 1100px) {
    .rc-htext { font-size: 1.25rem; }
    .rc-card-h { flex-direction: column; align-items: flex-start; gap: .8rem; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="rc-screen mb-3"><i class="fas fa-circle me-2" style="font-size:.55rem;"></i> SCREEN: Receipts - Generate Receipt / Apply to Invoices</div>

    <div class="d-flex justify-content-between align-items-start mb-4 gap-3 flex-wrap">
        <div>
            <h2 class="rc-title">+ Generate Receipt</h2>
            <p class="rc-sub">Receive payment from customer and allocate against open invoices.</p>
        </div>
        <a href="receipts.php" class="rc-back">&larr; Back to Receipts</a>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="receipt_number" value="<?= escape_html(post('receipt_number', $default_receipt_number)) ?>">
        <input type="hidden" name="payment_method" value="<?= escape_html(post('payment_method', 'Bank Transfer')) ?>">
        <input type="hidden" name="bank_account_id" value="<?= escape_html((string)post('bank_account_id', '')) ?>">
        <input type="hidden" name="reference" value="<?= escape_html(post('reference')) ?>">
        <input type="hidden" name="description" value="<?= escape_html(post('description')) ?>">
        <input type="hidden" name="company_id" value="<?= (int)$company_id ?>">
        <input type="hidden" name="approval_type" value="<?= escape_html((string)post('approval_type', 'manager')) ?>">
        <input type="hidden" name="manager_id" value="<?= (int)(post('manager_id') ?: ($managers[0]['id'] ?? 0)) ?>">
        <input type="hidden" name="admin_id" value="<?= (int)(post('admin_id') ?: ($admins[0]['id'] ?? 0)) ?>">

        <?php if (!empty($errors)): ?>
            <div class="rc-error mb-3">
                <?php foreach ($errors as $err): ?>
                    <div><?= escape_html((string)$err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="rc-card mb-3">
            <div class="rc-card-h">
                <div>
                    <span class="rc-hl">01</span><span class="rc-htext">Receipt Header</span>
                </div>
                <div class="rc-mini">
                    <div class="cap">Receipt Number</div>
                    <div class="val">Auto Generated (<?= escape_html(post('receipt_number', $default_receipt_number)) ?>)</div>
                </div>
            </div>
            <div class="p-3">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="rc-label">Date</label>
                        <input type="date" class="rc-input <?= isset($errors['receipt_date']) ? 'is-invalid' : '' ?>" name="receipt_date" value="<?= escape_html(post('receipt_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="rc-label">Customer <span class="text-danger">*</span></label>
                        <select class="rc-select <?= isset($errors['customer_id']) ? 'is-invalid' : '' ?>" id="customer_id" name="customer_id" required>
                            <option value="">Select Customer / Debtor</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= post('customer_id') == $c['id'] ? 'selected' : '' ?>><?= escape_html($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="rc-label">Amount to Apply <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="rc-input <?= isset($errors['amount']) ? 'is-invalid' : '' ?>" id="receipt_amount" name="amount" value="<?= escape_html(post('amount', '')) ?>" placeholder="10,000.00" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="rc-label">Company / Subsidiary</label>
                        <input type="text" class="rc-input" value="<?= escape_html(active_company_name('Selected Company')) ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="rc-card mb-2">
            <div class="rc-card-h">
                <div>
                    <span class="rc-hl">02</span><span class="rc-htext">Apply to Open Invoices</span>
                    <span style="color:#8ea0cd; margin-left:.5rem;">- Customer: <span id="customer_label">N/A</span></span>
                </div>
                <div class="rc-balance">Balance Left to Apply: <span id="balance_left">$0.00</span></div>
            </div>

            <div class="p-3">
                <?php if (isset($errors['invoice_ids'])): ?>
                    <div class="text-danger small mb-2"><?= escape_html($errors['invoice_ids']) ?></div>
                <?php endif; ?>
                <div class="rc-table-wrap">
                    <table class="rc-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice #</th>
                                <th class="text-end">Balance Due</th>
                                <th class="text-end">Applied</th>
                                <th class="text-end">Current Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($open_invoices)): ?>
                                <tr><td colspan="5" class="text-center py-3" style="color:#9aa9d1;">No open invoices available.</td></tr>
                            <?php else: ?>
                                <?php $posted_ids = array_map('intval', (array)post('invoice_ids', [])); ?>
                                <?php foreach ($open_invoices as $inv): ?>
                                    <tr class="invoice-row" data-customer-id="<?= (int)$inv['customer_id'] ?>" data-outstanding="<?= number_format((float)$inv['outstanding'], 2, '.', '') ?>">
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="invoice-checkbox rc-hidden-check"
                                                name="invoice_ids[]"
                                                value="<?= (int)$inv['id'] ?>"
                                                data-outstanding="<?= number_format((float)$inv['outstanding'], 2, '.', '') ?>"
                                                <?= in_array((int)$inv['id'], $posted_ids, true) ? 'checked' : '' ?>
                                            >
                                            <?= escape_html(date('d-m-Y', strtotime((string)$inv['invoice_date']))) ?>
                                        </td>
                                        <td><a class="rc-link" href="javascript:void(0)"><?= escape_html($inv['invoice_number']) ?></a></td>
                                        <td class="rc-num invoice-outstanding"><?= format_currency((float)$inv['outstanding']) ?></td>
                                        <td class="rc-num">
                                            <input type="text" class="rc-applied applied-input" value="0.00" readonly>
                                        </td>
                                        <td class="rc-current invoice-current"><?= format_currency((float)$inv['outstanding']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td></td>
                                <td style="font-weight:800;color:#fff;">TOTAL</td>
                                <td class="rc-num" id="total_balance_due"><?= format_currency(0) ?></td>
                                <td class="rc-num" id="total_applied" style="color:#08d0ef;"><?= format_currency(0) ?></td>
                                <td class="rc-num" id="total_current"><?= format_currency(0) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="rc-footnote mt-3">
                    <i class="fas fa-lightbulb me-1"></i> Once full payment is applied to an invoice, its status automatically changes to "Closed". Partial payments keep the invoice "Open".
                </div>
                <div class="small mt-2" id="allocation_hint" style="color:#8ea0cd;">Select customer and amount to auto-calculate allocations.</div>
            </div>
        </div>

        <div class="rc-card p-3">
            <div class="rc-actions">
                <button type="submit" class="rc-save"><i class="fas fa-save me-2"></i>Save Receipt</button>
                <div class="rc-warn"><i class="fas fa-exclamation-triangle me-1"></i>Receipt once saved cannot be amended</div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleApprovalColumns() {
    const type = document.getElementById('approval_type')?.value || 'manager';
    const managerGroup = document.getElementById('manager_group');
    const adminGroup = document.getElementById('admin_group');
    if (!managerGroup || !adminGroup) return;
    managerGroup.style.display = (type === 'manager' || type === 'both') ? '' : 'none';
    adminGroup.style.display = (type === 'admin' || type === 'both') ? '' : 'none';
}
document.getElementById('approval_type')?.addEventListener('change', toggleApprovalColumns);
toggleApprovalColumns();

function updateInvoiceSelectionUI() {
    const customerSelect = document.getElementById('customer_id');
    const customerId = customerSelect?.value || '';
    const customerLabel = document.getElementById('customer_label');
    if (customerLabel) {
        const optionText = customerSelect?.selectedOptions?.[0]?.textContent || '';
        customerLabel.textContent = customerId ? optionText : 'N/A';
    }

    const amount = parseFloat(document.getElementById('receipt_amount')?.value || '0') || 0;
    let selectedOutstanding = 0;
    let selectedCount = 0;
    let remaining = amount;
    let totalDue = 0;
    let totalApplied = 0;
    let totalCurrent = 0;

    document.querySelectorAll('.invoice-row').forEach((row) => {
        const rowCustomer = row.getAttribute('data-customer-id') || '';
        const checkbox = row.querySelector('.invoice-checkbox');
        const visible = !!customerId && customerId === rowCustomer;
        const outstanding = parseFloat(row.getAttribute('data-outstanding') || '0') || 0;
        const appliedInput = row.querySelector('.applied-input');
        const currentEl = row.querySelector('.invoice-current');

        row.style.display = visible ? '' : 'none';
        if (!checkbox) return;
        checkbox.checked = visible;

        let applied = 0;
        if (visible) {
            selectedCount += 1;
            selectedOutstanding += outstanding;
            applied = Math.max(0, Math.min(remaining, outstanding));
            remaining -= applied;
        }

        const current = Math.max(0, outstanding - applied);
        if (appliedInput) appliedInput.value = applied.toFixed(2);
        if (currentEl) currentEl.textContent = '$' + current.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        if (visible) {
            totalDue += outstanding;
            totalApplied += applied;
            totalCurrent += current;
        }
    });

    const left = Math.max(0, remaining);
    const balanceLeft = document.getElementById('balance_left');
    if (balanceLeft) {
        balanceLeft.textContent = '$' + left.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const dueEl = document.getElementById('total_balance_due');
    const appliedEl = document.getElementById('total_applied');
    const currentEl = document.getElementById('total_current');
    if (dueEl) dueEl.textContent = '$' + totalDue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (appliedEl) appliedEl.textContent = '$' + totalApplied.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (currentEl) currentEl.textContent = '$' + totalCurrent.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const hint = document.getElementById('allocation_hint');
    if (hint) {
        hint.textContent = `Visible invoices: ${selectedCount} | Total outstanding: ${selectedOutstanding.toFixed(2)} | Receipt amount: ${amount.toFixed(2)} | Balance left: ${left.toFixed(2)}`;
        hint.style.color = (amount > selectedOutstanding && selectedCount > 0) ? '#ff9b95' : '#8ea0cd';
    }
}

document.getElementById('customer_id')?.addEventListener('change', updateInvoiceSelectionUI);
document.getElementById('receipt_amount')?.addEventListener('input', updateInvoiceSelectionUI);
updateInvoiceSelectionUI();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
