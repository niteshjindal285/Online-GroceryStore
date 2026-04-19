<?php
/**
 * Make a Deposit
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Make a Deposit - MJR Group ERP';
$company_id = (int)active_company_id(1);

$table_has_column = function (string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return $cache[$key] = false;
    }
    try {
        $row = db_fetch("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
        return $cache[$key] = !empty($row);
    } catch (Exception $e) {
        return $cache[$key] = false;
    }
};

$has_receipts_company = $table_has_column('receipts', 'company_id');
$has_receipts_posted_at = $table_has_column('receipts', 'posted_at');
$has_receipts_status = $table_has_column('receipts', 'status');
$has_receipts_description = $table_has_column('receipts', 'description');
$has_bank_account_number = $table_has_column('bank_accounts', 'account_number');
$bank_account_number_select = $has_bank_account_number ? "account_number" : "NULL AS account_number";

$banks = db_fetch_all("
    SELECT id, bank_name, account_name, {$bank_account_number_select}
    FROM bank_accounts
    WHERE is_active = 1
    ORDER BY bank_name, account_name
") ?: [];

$errors = [];

if (is_post() && post('action') === 'make_deposit') {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $banking_date = trim((string)post('banking_date', ''));
        $bank_id = (int)post('bank_id', 0);
        $comments = trim((string)post('comments', ''));
        $receipt_ids = array_values(array_unique(array_filter(array_map('intval', (array)post('receipt_ids', [])), function ($v) {
            return $v > 0;
        })));

        if ($banking_date === '') $errors['banking_date'] = 'Banking date is required.';
        if ($bank_id <= 0) $errors['bank_id'] = 'Please select a bank.';
        if (empty($receipt_ids)) $errors['receipt_ids'] = 'Please select at least one pending receipt.';

        if (empty($errors)) {
            $ph = implode(',', array_fill(0, count($receipt_ids), '?'));
            $company_clause = $has_receipts_company ? " AND (company_id = ? OR company_id IS NULL) " : "";
            $to_bank_params = $receipt_ids;
            if ($has_receipts_company) $to_bank_params[] = $company_id;

            $to_bank = db_fetch_all("
                SELECT id, receipt_number, amount
                FROM receipts
                WHERE id IN ($ph)
                  {$company_clause}
                  AND (TRIM(COALESCE(reference,'')) = '' OR reference IS NULL)
            ", $to_bank_params) ?: [];

            if (empty($to_bank)) {
                $errors['receipt_ids'] = 'No valid unbanked receipts found for selection.';
            }
        }

        if (empty($errors)) {
            $last_ref = db_fetch("SELECT MAX(reference) AS last_ref FROM receipts WHERE reference LIKE 'REF%'");
            $last_num = 3000;
            if ($last_ref && preg_match('/REF(\d+)/', (string)($last_ref['last_ref'] ?? ''), $m)) {
                $last_num = (int)$m[1];
            }
            $dep_ref = 'REF' . ($last_num + 1);

            $dep_total = array_sum(array_map(function ($r) {
                return (float)($r['amount'] ?? 0);
            }, $to_bank));

            try {
                db_begin_transaction();
                foreach ($to_bank as $row) {
                    $set_parts = ["reference = ?", "bank_account_id = ?"];
                    $set_params = [$dep_ref, $bank_id];
                    if ($has_receipts_status) $set_parts[] = "status = 'banked'";
                    if ($has_receipts_posted_at) $set_parts[] = "posted_at = NOW()";
                    if ($has_receipts_description) {
                        $set_parts[] = "description = CASE WHEN description IS NULL OR description = '' THEN ? ELSE CONCAT(description, ' | ', ?) END";
                        $set_params[] = $comments;
                        $set_params[] = $comments;
                    }
                    $set_params[] = (int)$row['id'];
                    db_query("UPDATE receipts SET " . implode(', ', $set_parts) . " WHERE id = ?", $set_params);
                }
                db_commit();
                set_flash("Deposit {$dep_ref} created successfully! Total: " . format_currency($dep_total), 'success');
                redirect("view_deposit.php?ref=" . urlencode($dep_ref));
            } catch (Exception $e) {
                db_rollback();
                log_error('Make deposit error: ' . $e->getMessage());
                $errors['save'] = sanitize_db_error($e->getMessage());
            }
        }
    } else {
        $errors['csrf'] = 'Invalid request. Please refresh and try again.';
    }
}

$pending_receipts = db_fetch_all("
    SELECT r.id, r.receipt_number, r.receipt_date, r.amount, c.name AS customer_name
    FROM receipts r
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE (TRIM(COALESCE(r.reference,'')) = '' OR r.reference IS NULL)
      " . ($has_receipts_company ? "AND (r.company_id = ? OR r.company_id IS NULL)" : "") . "
    ORDER BY r.receipt_date DESC, r.id DESC
", $has_receipts_company ? [$company_id] : []) ?: [];

$posted_ids = array_map('intval', (array)post('receipt_ids', []));

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --md-bg: #080c1a;
    --md-panel: #1d243c;
    --md-panel2: #1a2035;
    --md-border: #313a61;
    --md-cyan: #09d4f0;
    --md-soft: #8c9dc3;
    --md-red: #ff5b5b;
    --md-text-header: #fff;
    --md-glass: rgba(255, 255, 255, 0.05);
}

[data-bs-theme="light"] {
    --md-bg: #f8f9fa;
    --md-panel: #ffffff;
    --md-panel2: #f8f9fa;
    --md-border: #e0e0e0;
    --md-cyan: #0dcaf0;
    --md-soft: #6c757d;
    --md-red: #dc3545;
    --md-text-header: #212529;
    --md-glass: rgba(0, 0, 0, 0.04);
}

body { background: var(--md-bg); color: var(--md-soft); }
.md-screen { border: 1px solid rgba(9,212,240,.5); border-radius: 10px; background: rgba(9,212,240,.07); color: var(--md-cyan); font-weight: 700; padding: .6rem 1rem; }
.md-back { border: 1px solid var(--md-border); border-radius: 10px; color: var(--md-soft); text-decoration: none; padding: .55rem 1.1rem; font-weight: 700; transition: all 0.2s; }
.md-back:hover { background: var(--md-glass); color: var(--md-cyan); }
.md-card { border-radius: 14px; border: 1px solid var(--md-border); background: linear-gradient(180deg, var(--md-panel), var(--md-panel2)); overflow: hidden; }
.md-card-hd { border-bottom: 1px solid var(--md-border); padding: .9rem 1.4rem; display: flex; align-items: center; justify-content: space-between; background: rgba(128, 128, 128, 0.05); }
.md-badge { background: var(--md-cyan); color: #031218; border-radius: 8px; font-weight: 900; padding: .18rem .55rem; min-width: 28px; text-align: center; }
.md-htext { color: var(--md-text-header); font-weight: 800; font-size: 1.05rem; margin-left: .55rem; }
.md-label { color: var(--md-soft); font-size: .74rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: .3rem; }
[data-bs-theme="dark"] .md-input, [data-bs-theme="dark"] .md-select { width: 100%; background: #252d4a; border: 1px solid #344271; color: #eef3ff; border-radius: 8px; padding: .58rem .8rem; }
[data-bs-theme="light"] .md-input, [data-bs-theme="light"] .md-select { width: 100%; background: #ffffff; border: 1px solid #dee2e6; color: #212529; border-radius: 8px; padding: .58rem .8rem; }
.md-amount-box { border: 1px solid rgba(9,212,240,.55); border-radius: 12px; padding: .7rem 1.4rem; text-align: center; background: var(--md-glass); min-width: 240px; }
.md-amount-box .cap { color: var(--md-soft); font-size: .72rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.md-amount-box .amt { color: var(--md-cyan); font-size: 2rem; font-weight: 900; line-height: 1.2; }
.md-table { width: 100%; border-collapse: collapse; }
.md-table th { padding: .9rem .8rem; font-size: .73rem; color: var(--md-soft); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--md-border); background: rgba(128, 128, 128, 0.1); }
.md-table td { padding: .75rem .8rem; border-bottom: 1px solid var(--md-border); color: var(--md-text-header); }
.md-total-row td { background: rgba(9,212,240,.06); font-weight: 900; border-top: 1px solid rgba(9,212,240,.25); }
.md-save { background: #0fc7df; color: #04111c; border-radius: 10px; border: 0; font-weight: 800; padding: .65rem 1.2rem; transition: all 0.2s; }
.md-save:hover { background: #0baccc; }
.md-warn { color: var(--md-red); font-weight: 700; }
.md-wire { float:right; background: rgba(9,212,240,.88); color:#00141d; padding:.2rem .65rem; border-radius:999px; font-weight:800; font-size:.82rem; }
.md-error { background: rgba(255,79,69,.16); color:#ff9b95; border:1px solid rgba(255,79,69,.35); border-radius:10px; padding:.75rem 1rem; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="md-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i> SCREEN: Banking / Deposits - Make a Deposit</div>

    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h2 fw-bold mb-1" style="color: var(--md-text-header);">+ Make a Deposit</h1>
            <p class="mb-0">Select pending receipts to consolidate into a single bank deposit.</p>
        </div>
        <a href="banking_deposits.php" class="md-back">&larr; Back to Deposits</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="md-error mb-3"><?php foreach ($errors as $e): ?><div><?= escape_html((string)$e) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="POST" id="makeDepositForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="action" value="make_deposit">

        <div class="md-card mb-4">
            <div class="md-card-hd">
                <div><span class="md-badge">01</span><span class="md-htext">Deposit Details</span></div>
                <div class="md-amount-box">
                    <div class="cap">Deposit Amount</div>
                    <div class="amt" id="depositAmount">$0.00</div>
                </div>
            </div>
            <div class="p-4">
                <div class="row g-3 mb-3">
                    <div class="col-lg-3">
                        <label class="md-label">Date</label>
                        <input class="md-input" type="date" value="<?= escape_html(date('Y-m-d')) ?>" readonly>
                    </div>
                    <div class="col-lg-3">
                        <label class="md-label">Banking Date <span class="text-danger">*</span></label>
                        <input class="md-input" type="date" name="banking_date" value="<?= escape_html((string)post('banking_date', '')) ?>" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="md-label">Select Bank <span class="text-danger">*</span></label>
                        <select class="md-select" name="bank_id" id="bank_id" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" data-acct="<?= escape_html(trim((string)($b['account_number'] ?? $b['account_name'] ?? 'Auto-filled'))) ?>" <?= (int)post('bank_id') === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= escape_html((string)$b['bank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-3">
                        <label class="md-label">Bank Account</label>
                        <input class="md-input" type="text" id="bank_acct_view" placeholder="Auto-filled from bank selection" readonly>
                    </div>
                </div>
                <div>
                    <label class="md-label">Comments</label>
                    <input class="md-input" type="text" name="comments" value="<?= escape_html((string)post('comments', '')) ?>" placeholder="Optional deposit notes...">
                </div>
            </div>
        </div>

        <div class="md-card mb-4">
            <div class="md-card-hd">
                <div><span class="md-badge">02</span><span class="md-htext">Select Pending Receipts</span><span class="ms-2" style="color: var(--md-soft); opacity: 0.8;">- Check the receipts to include in this deposit</span></div>
            </div>
            <div class="p-3">
                <div class="table-responsive">
                    <table class="md-table">
                        <thead>
                            <tr>
                                <th style="width:70px;">Select</th>
                                <th>Date</th>
                                <th>Receipt #</th>
                                <th>Customer</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_receipts)): ?>
                                <tr><td colspan="5" class="text-center py-3" style="color: var(--md-soft);">No pending receipts found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_receipts as $r): ?>
                                    <?php $is_checked = in_array((int)$r['id'], $posted_ids, true); ?>
                                    <tr>
                                        <td><input type="checkbox" class="receipt-check" name="receipt_ids[]" value="<?= (int)$r['id'] ?>" data-amount="<?= number_format((float)$r['amount'], 2, '.', '') ?>" <?= $is_checked ? 'checked' : '' ?>></td>
                                        <td><?= escape_html(date('d-m-Y', strtotime((string)$r['receipt_date']))) ?></td>
                                        <td style="color:var(--md-cyan);font-weight:800;"><?= escape_html((string)$r['receipt_number']) ?></td>
                                        <td><?= escape_html((string)($r['customer_name'] ?: 'N/A')) ?></td>
                                        <td class="text-end">$<?= number_format((float)$r['amount'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <tfoot>
                            <tr class="md-total-row">
                                <td colspan="4" class="text-end">SELECTED TOTAL</td>
                                <td class="text-end" id="selectedTotalCell">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="border-top:1px solid rgba(49,58,97,.6);padding-top:1rem;">
            <button type="submit" class="md-save"><i class="fas fa-save me-2"></i>Save Deposit</button>
            <div class="md-warn"><i class="fas fa-exclamation-triangle me-1"></i>Once saved, deposit reference is generated and cannot be amended</div>
        </div>
        
    </form>
</div>

<script>
(function () {
    const checks = Array.from(document.querySelectorAll('.receipt-check'));
    const depositAmount = document.getElementById('depositAmount');
    const selectedTotalCell = document.getElementById('selectedTotalCell');
    const bankSelect = document.getElementById('bank_id');
    const bankAcctView = document.getElementById('bank_acct_view');

    function fmt(v) {
        return '$' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function updateTotal() {
        let total = 0;
        checks.forEach((c) => {
            if (c.checked) total += parseFloat(c.getAttribute('data-amount') || '0') || 0;
        });
        if (depositAmount) depositAmount.textContent = fmt(total);
        if (selectedTotalCell) selectedTotalCell.textContent = fmt(total);
    }

    function updateBankAccount() {
        const opt = bankSelect?.selectedOptions?.[0];
        const acct = opt ? (opt.getAttribute('data-acct') || '') : '';
        if (bankAcctView) bankAcctView.value = acct;
    }

    checks.forEach((c) => c.addEventListener('change', updateTotal));
    bankSelect?.addEventListener('change', updateBankAccount);

    updateTotal();
    updateBankAccount();
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
