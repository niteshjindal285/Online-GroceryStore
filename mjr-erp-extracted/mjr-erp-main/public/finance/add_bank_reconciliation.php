<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'New Bank Reconciliation';
$company_id = (int)active_company_id(1);
$approvers = finance_get_approver_users($company_id);
$managers = $approvers['managers'] ?? [];
$admins = $approvers['admins'] ?? [];

$table_has_column = function (string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        $cache[$key] = false;
        return false;
    }
    try {
        $row = db_fetch("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
        $cache[$key] = !empty($row);
    } catch (Exception $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
};

$has_receipts_company = $table_has_column('receipts', 'company_id');
$has_receipts_posted_at = $table_has_column('receipts', 'posted_at');
$has_vouchers_company = $table_has_column('payment_vouchers', 'company_id');
$has_vouchers_cheque = $table_has_column('payment_vouchers', 'cheque_number');
$has_vouchers_status = $table_has_column('payment_vouchers', 'status');
$has_bank_current_balance = $table_has_column('bank_accounts', 'current_balance');

$banks = db_fetch_all("
    SELECT id, bank_name, account_name
    FROM bank_accounts
    WHERE is_active = 1
    ORDER BY bank_name, account_name
") ?: [];

$today = date('Y-m-d');
$period_from_default = date('Y-m-01');
$period_to_default = date('Y-m-t');

$is_post_request = is_post();
$selected_bank = (int)post('bank_id', (int)get('bank_id', 0));
$period_from = trim((string)post('period_from', (string)get('period_from', '')));
$period_to = trim((string)post('period_to', (string)get('period_to', '')));

// If opened from View Bank reconcile button (bank_id present but no period),
// default to current month so data is fetched immediately.
if (!$is_post_request && $selected_bank > 0 && $period_from === '' && $period_to === '') {
    $period_from = $period_from_default;
    $period_to = $period_to_default;
}

$date = trim((string)post('reco_date', $today));
$narration_default = $period_to !== '' ? ('Bank Reconciliation as at ' . date('d/m/Y', strtotime($period_to))) : '';
$narration = trim((string)post('narration', $narration_default));
$approval_type = strtolower(trim((string)post('approval_type', 'manager')));
$manager_id = post('manager_id') ?: null;
$admin_id = post('admin_id') ?: null;

$default_adj_rows = [
    ['date' => $period_to !== '' ? $period_to : $today, 'code' => '', 'name' => '', 'dr' => '0.00', 'cr' => '0.00'],
];

$adj_date = (array)post('adj_date', array_column($default_adj_rows, 'date'));
$adj_code = (array)post('adj_code', array_column($default_adj_rows, 'code'));
$adj_name = (array)post('adj_name', array_column($default_adj_rows, 'name'));
$adj_dr = (array)post('adj_dr', array_column($default_adj_rows, 'dr'));
$adj_cr = (array)post('adj_cr', array_column($default_adj_rows, 'cr'));

$adjustments = [];
$adj_len = max(count($adj_date), count($adj_code), count($adj_name), count($adj_dr), count($adj_cr));
for ($i = 0; $i < $adj_len; $i++) {
    $a_date = trim((string)($adj_date[$i] ?? ''));
    $a_code = trim((string)($adj_code[$i] ?? ''));
    $a_name = trim((string)($adj_name[$i] ?? ''));
    $a_dr = (float)($adj_dr[$i] ?? 0);
    $a_cr = (float)($adj_cr[$i] ?? 0);
    if ($a_date === '' && $a_code === '' && $a_name === '' && abs($a_dr) < 0.0001 && abs($a_cr) < 0.0001) {
        continue;
    }
    $adjustments[] = [
        'date' => $a_date !== '' ? $a_date : $period_to,
        'code' => $a_code,
        'name' => $a_name,
        'dr' => $a_dr,
        'cr' => $a_cr,
    ];
}
if (empty($adjustments)) {
    $adjustments = $default_adj_rows;
    foreach ($adjustments as &$adj) {
        $adj['dr'] = (float)$adj['dr'];
        $adj['cr'] = (float)$adj['cr'];
    }
    unset($adj);
}

$deposits = [];
$payments = [];
$deposits_selected = [];
$payments_selected = [];
$errors = [];

if ($selected_bank > 0 && $period_from !== '' && $period_to !== '') {
    $receipt_date_expr = $has_receipts_posted_at ? 'r.posted_at' : 'r.receipt_date';
    $receipt_company_clause = $has_receipts_company ? "AND (r.company_id = ? OR r.company_id IS NULL)" : "";
    $receipt_params = [$selected_bank, $period_from, $period_to];
    if ($has_receipts_company) {
        $receipt_params[] = $company_id;
    }

    $deposits = db_fetch_all("
        SELECT
            DATE(MAX({$receipt_date_expr})) AS deposit_date,
            r.reference AS deposit_ref,
            SUM(r.amount) AS amount
        FROM receipts r
        WHERE r.bank_account_id = ?
          AND TRIM(COALESCE(r.reference, '')) <> ''
          AND r.reference LIKE 'REF%'
          AND DATE({$receipt_date_expr}) BETWEEN ? AND ?
          {$receipt_company_clause}
        GROUP BY r.reference
        ORDER BY DATE(MAX({$receipt_date_expr})) DESC, r.reference DESC
    ", $receipt_params) ?: [];

    $voucher_company_clause = $has_vouchers_company ? "AND (pv.company_id = ? OR pv.company_id IS NULL)" : "";
    $voucher_status_clause = $has_vouchers_status ? "AND LOWER(COALESCE(pv.status, 'draft')) IN ('approved', 'posted', 'cancelled')" : "";
    $voucher_params = [$selected_bank, $period_from, $period_to];
    if ($has_vouchers_company) {
        $voucher_params[] = $company_id;
    }

    $payments = db_fetch_all("
        SELECT
            pv.id,
            pv.voucher_date,
            pv.voucher_number,
            " . ($has_vouchers_cheque ? "COALESCE(pv.cheque_number, '')" : "''") . " AS cheque_number,
            COALESCE(s.name, pv.reference, '') AS payee,
            pv.amount,
            " . ($has_vouchers_status ? "LOWER(COALESCE(pv.status, 'draft'))" : "'draft'") . " AS status
        FROM payment_vouchers pv
        LEFT JOIN suppliers s ON s.id = pv.supplier_id
        WHERE pv.bank_account_id = ?
          AND DATE(pv.voucher_date) BETWEEN ? AND ?
          {$voucher_company_clause}
          {$voucher_status_clause}
        ORDER BY pv.voucher_date DESC, pv.id DESC
    ", $voucher_params) ?: [];
}

if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $deposits_selected = array_values(array_unique(array_filter((array)post('deposits_selected', []), fn($v) => trim((string)$v) !== '')));
    $payments_selected = array_values(array_unique(array_map('intval', (array)post('payments_selected', []))));

    if ($selected_bank <= 0) {
        $errors[] = 'Please select bank.';
    }
    if ($period_from === '' || $period_to === '') {
        $errors[] = 'Please enter period from and to.';
    }
    if ($period_from !== '' && $period_to !== '' && strtotime($period_from) > strtotime($period_to)) {
        $errors[] = 'Period From cannot be after Period To.';
    }
    $errors = array_merge($errors, array_values(finance_validate_approval_setup($approval_type, $manager_id, $admin_id)));

    if (empty($errors)) {
        $action = post('action', 'save');
        if ($action === 'authorize') {
            set_flash('Bank reconciliation authorized. (Form prepared and ready for persistence)', 'success');
        } else {
            set_flash('Bank reconciliation saved. (Form prepared and ready for persistence)', 'success');
        }
        redirect('finance/add_bank_reconciliation.php?bank_id=' . (int)$selected_bank . '&period_from=' . urlencode($period_from) . '&period_to=' . urlencode($period_to));
    }
} else {
    $deposits_selected = [];
    $payments_selected = [];
}

$deposits_map = [];
foreach ($deposits as $d) {
    $deposits_map[(string)$d['deposit_ref']] = (float)$d['amount'];
}
$payments_map = [];
$payments_status = [];
foreach ($payments as $p) {
    $payments_map[(int)$p['id']] = (float)$p['amount'];
    $payments_status[(int)$p['id']] = strtolower((string)($p['status'] ?? 'draft'));
}

$deposits_total = 0.0;
foreach ($deposits_selected as $ref) {
    if (isset($deposits_map[$ref])) {
        $deposits_total += (float)$deposits_map[$ref];
    }
}

$payments_total = 0.0;
foreach ($payments_selected as $pid) {
    if (isset($payments_map[$pid]) && (($payments_status[$pid] ?? '') !== 'cancelled')) {
        $payments_total += (float)$payments_map[$pid];
    }
}

$other_items_total = 0.0;
foreach ($adjustments as $adj) {
    $other_items_total += (float)$adj['dr'];
    $other_items_total -= (float)$adj['cr'];
}

$bank_statement_balance = 0.0;
$system_balance = 0.0;
if ($selected_bank > 0 && $period_to !== '') {
    $bank_statement = db_fetch("
        SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(type, '')) = 'deposit' THEN amount ELSE -amount END), 0) AS bal
        FROM bank_transactions
        WHERE bank_account_id = ?
          AND DATE(transaction_date) <= ?
    ", [$selected_bank, $period_to]);
    $bank_statement_balance = (float)($bank_statement['bal'] ?? 0);

    $system = db_fetch("
        SELECT COALESCE(SUM(CASE WHEN LOWER(COALESCE(type, '')) = 'deposit' THEN amount ELSE -amount END), 0) AS bal
        FROM bank_transactions
        WHERE bank_account_id = ?
          AND is_reconciled = 1
          AND DATE(transaction_date) <= ?
    ", [$selected_bank, $period_to]);
    $system_balance = (float)($system['bal'] ?? 0);

    if ($has_bank_current_balance) {
        $bank_row = db_fetch("SELECT current_balance FROM bank_accounts WHERE id = ? LIMIT 1", [$selected_bank]);
        if ($bank_row && isset($bank_row['current_balance'])) {
            $bank_statement_balance = (float)$bank_row['current_balance'];
        }
    }
}

$reconciled_balance = $system_balance + $deposits_total - $payments_total - $other_items_total;
$variance = $bank_statement_balance - $reconciled_balance;

include __DIR__ . '/../../templates/header.php';
?>

<style>
html[data-bs-theme="dark"],
html[data-app-theme="dark"] {
    --ar-bg: #080c1a;
    --ar-panel: #1d243c;
    --ar-panel-2: #1a2035;
    --ar-line: #313a61;
    --ar-cyan: #08d0ef;
    --ar-soft: #8f9dc5;
    --ar-green: #41c95b;
    --ar-red: #ff4747;
    --ar-gold: #ffc11b;
    --ar-text-header: #ffffff;
    --ar-table-text: #f2f5ff;
    --ar-label: #9aa9d1;
}

html[data-bs-theme="light"],
html[data-app-theme="light"] {
    --ar-bg: #f8f9fa;
    --ar-panel: #ffffff;
    --ar-panel-2: #f8f9fa;
    --ar-line: #e0e0e0;
    --ar-cyan: #0dcaf0;
    --ar-soft: #6c757d;
    --ar-green: #198754;
    --ar-red: #dc3545;
    --ar-gold: #ffc107;
    --ar-text-header: #212529;
    --ar-table-text: #212529;
    --ar-label: #495057;
}

body { background: var(--ar-bg); color: var(--ar-soft); }
.ar-screen { border:1px solid rgba(8,208,239,.55); border-radius:10px; background:rgba(8,208,239,.07); color:var(--ar-cyan); font-weight:700; padding:.65rem 1rem; }
.ar-title { color: var(--ar-text-header); font-weight:800; margin-bottom:.15rem; }
.ar-sub { color: var(--ar-label); margin-bottom:0; font-weight:600; }
.ar-back { border:1px solid var(--ar-line); border-radius:12px; color: var(--ar-soft); text-decoration:none; padding:.65rem 1.1rem; font-weight:700; }
.ar-panel { border-radius:12px; border:1px solid var(--ar-line); background:linear-gradient(180deg,var(--ar-panel),var(--ar-panel-2)); }
.ar-hd { border-bottom:1px solid var(--ar-line); padding:1rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
.ar-step { width:34px; height:34px; border-radius:9px; background:#18c8e8; color:#04111c; font-weight:900; display:inline-flex; align-items:center; justify-content:center; margin-right:.8rem; }
.ar-hd-title { color: var(--ar-text-header); font-weight:800; font-size:2rem; margin:0; display:flex; align-items:center; }
.ar-hd-sub { color: var(--ar-label); margin-left:.5rem; font-weight:600; font-size:1.9rem; }
.ar-grid4 { display:grid; grid-template-columns:repeat(4,1fr); gap:.85rem; }
html[data-bs-theme="dark"] .ar-input, html[data-bs-theme="dark"] .ar-select, html[data-app-theme="dark"] .ar-input, html[data-app-theme="dark"] .ar-select { width:100%; background:#252d4a; border:1px solid #344271; color:#eef3ff; border-radius:8px; padding:.58rem .8rem; }
html[data-bs-theme="light"] .ar-input, html[data-bs-theme="light"] .ar-select, html[data-app-theme="light"] .ar-input, html[data-app-theme="light"] .ar-select { width:100%; background:#ffffff; border:1px solid #dee2e6; color:#212529; border-radius:8px; padding:.58rem .8rem; }
html[data-bs-theme="dark"] .ar-input[readonly], html[data-app-theme="dark"] .ar-input[readonly] { color:#08d0ef; background:#1c3047; border-color:#0a7591; font-weight:700; }
html[data-bs-theme="light"] .ar-input[readonly], html[data-app-theme="light"] .ar-input[readonly] { color:#0b7ea0; background:#e7f5ff; border-color:#0aa8d4; font-weight:700; }
.ar-label { color: var(--ar-label); font-size:.84rem; margin-bottom:.3rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase; }
.ar-table { width:100%; border-collapse:collapse; }
.ar-table thead th { padding:.85rem .9rem; color: var(--ar-soft); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--ar-line); font-size:.76rem; }
.ar-table tbody td { padding:.65rem .9rem; border-bottom:1px solid var(--ar-line); color: var(--ar-table-text); }
.ar-table tfoot td { padding:.75rem .9rem; border-top:2px solid var(--ar-line); font-weight:800; color: var(--ar-text-header); }
.ar-box { border:1px solid var(--ar-line); border-radius:10px; background:rgba(255,255,255,.02); padding:1rem 1.2rem; }
.ar-sum-row { display:flex; justify-content:space-between; gap:1rem; margin:.35rem 0; font-size:1.55rem; color: var(--ar-table-text); }
.ar-sum-row strong { color: var(--ar-text-header); }
.ar-cyan { color:var(--ar-cyan) !important; font-weight:800; }
.ar-green { color:var(--ar-green) !important; font-weight:800; }
.ar-red { color:var(--ar-red) !important; font-weight:800; }
.ar-gold { color:var(--ar-gold) !important; font-weight:800; }
.ar-check { width:22px; height:22px; border-radius:6px; accent-color:#18c8e8; }
.ar-note { border-top:1px solid rgba(255,193,44,.3); background:rgba(255,193,44,.1); color:var(--ar-gold); border-radius:8px; padding:.55rem .9rem; font-weight:600; }
.ar-btn-save { border:0; border-radius:10px; background:#12c7df; color:#04111c; font-weight:800; padding:.65rem 1.2rem; }
.ar-btn-auth { border:0; border-radius:10px; background:#46b84c; color:#fff; font-weight:800; padding:.65rem 1.2rem; }
.ar-balance-chip { border:1px solid var(--ar-cyan); border-radius:10px; background:rgba(8,208,239,.08); padding:.55rem 1rem; text-align:center; min-width:230px; }
.ar-balance-chip .k { color: var(--ar-soft); font-weight:700; }
.ar-balance-chip .v { color:var(--ar-cyan); font-weight:900; font-size:2.15rem; }
.ar-muted { color: var(--ar-soft); opacity: 0.7; }
.ar-link-btn { width:100%; border:1px dashed var(--ar-line); background:transparent; color:var(--ar-cyan); border-radius:8px; padding:.45rem; font-weight:700; }
@media (max-width:1200px) {
    .ar-grid4 { grid-template-columns:1fr; }
}

</style>

<div class="container-fluid px-4 py-4">
    <div class="ar-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Bank Reconciliation - Reconciliation Form</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="ar-title">+ New Bank Reconciliation</h2>
            <p class="ar-sub">Reconcile bank statement with system records for the selected period.</p>
        </div>
        <a href="<?= url('finance/bank_reconciliation.php') ?>" class="ar-back">&larr; Back to Reconciliations</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?= escape_html(implode(' ', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" id="recoForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <div class="ar-panel mb-4">
            <div class="ar-hd">
                <h4 class="ar-hd-title"><span class="ar-step">01</span>Reconciliation Setup</h4>
            </div>
            <div class="p-4">
                <div class="ar-grid4 mb-3">
                    <div>
                        <label class="ar-label">Date</label>
                        <input type="text" class="ar-input" name="reco_date" value="<?= escape_html(format_date($date)) ?>" readonly>
                    </div>
                    <div>
                        <label class="ar-label">Period From <span class="text-danger">*</span></label>
                        <input type="date" class="ar-input" id="period_from" name="period_from" value="<?= escape_html($period_from) ?>" required>
                    </div>
                    <div>
                        <label class="ar-label">Period To <span class="text-danger">*</span></label>
                        <input type="date" class="ar-input" id="period_to" name="period_to" value="<?= escape_html($period_to) ?>" required>
                    </div>
                    <div>
                        <label class="ar-label">Select Bank <span class="text-danger">*</span></label>
                        <select class="ar-select" id="bank_id" name="bank_id" required>
                            <option value="">Select Bank</option>
                            <?php foreach ($banks as $bank): ?>
                                <option value="<?= (int)$bank['id'] ?>" <?= $selected_bank === (int)$bank['id'] ? 'selected' : '' ?>>
                                    <?= escape_html($bank['bank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="ar-btn-save" id="loadDataBtn"><i class="fas fa-sync-alt me-2"></i>Load Data</button>
                </div>
                <div>
                    <label class="ar-label">Narration</label>
                    <input type="text" class="ar-input" name="narration" value="<?= escape_html($narration) ?>" placeholder="Bank Reconciliation narration">
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-lg-4">
                        <label class="ar-label">Approval Type <span class="text-danger">*</span></label>
                        <select class="ar-select" name="approval_type" id="approval_type">
                            <option value="manager" <?= $approval_type === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= $approval_type === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="both" <?= $approval_type === 'both' ? 'selected' : '' ?>>Manager + Admin</option>
                        </select>
                    </div>
                    <div class="col-lg-4" id="managerWrap">
                        <label class="ar-label">Manager</label>
                        <select class="ar-select" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $m): ?>
                                <?php $manager_name = trim((string)($m['full_name'] ?? '')) ?: (string)($m['username'] ?? 'Manager'); ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)$manager_id === (int)$m['id'] ? 'selected' : '' ?>><?= escape_html($manager_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4" id="adminWrap">
                        <label class="ar-label">Admin</label>
                        <select class="ar-select" name="admin_id">
                            <option value="">Select Admin</option>
                            <?php foreach ($admins as $a): ?>
                                <?php $admin_name = trim((string)($a['full_name'] ?? '')) ?: (string)($a['username'] ?? 'Admin'); ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (int)$admin_id === (int)$a['id'] ? 'selected' : '' ?>><?= escape_html($admin_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="ar-panel mb-4" id="recoCalcRoot"
             data-bank-statement="<?= escape_html(number_format((float)$bank_statement_balance, 2, '.', '')) ?>"
             data-system-balance="<?= escape_html(number_format((float)$system_balance, 2, '.', '')) ?>">
            <div class="ar-hd">
                <h4 class="ar-hd-title"><span class="ar-step">02</span>Balance Summary</h4>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="ar-box">
                            <div class="ar-sum-row"><span>Balance as per Bank Statement</span><strong id="sumBankStatement"><?= format_currency($bank_statement_balance) ?></strong></div>
                            <div class="ar-sum-row"><span>Balance as per System</span><strong id="sumSystemBalance"><?= format_currency($system_balance) ?></strong></div>
                            <div class="ar-sum-row"><span>Add: Receipts / Deposits (ticked)</span><span class="ar-green" id="sumDeposits">+<?= format_currency($deposits_total) ?></span></div>
                            <div class="ar-sum-row"><span>Less: Payments (ticked)</span><span class="ar-red" id="sumPayments">-<?= format_currency($payments_total) ?></span></div>
                            <div class="ar-sum-row"><span>Add/Less: Other Items</span><span id="sumOtherItems" class="<?= $other_items_total >= 0 ? 'ar-red' : 'ar-green' ?>"><?= $other_items_total >= 0 ? '-' : '+' ?><?= format_currency(abs($other_items_total)) ?></span></div>
                            <hr style="border-color:#344271;">
                            <div class="ar-sum-row"><span>Reconciled Balance</span><span class="ar-cyan" id="sumReconciled"><?= format_currency($reconciled_balance) ?></span></div>
                            <div class="ar-sum-row"><span>Variance</span><span id="sumVariance" class="<?= abs($variance) < 0.0001 ? 'ar-green' : 'ar-red' ?>"><?= format_currency($variance) ?></span></div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="mb-2 ar-label">Other Adjustments (Bank Charges / Interest)</div>
                        <table class="ar-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Acc Code</th>
                                    <th>Acc Name</th>
                                    <th>Dr</th>
                                    <th>Cr</th>
                                </tr>
                            </thead>
                            <tbody id="adjRows">
                                <?php foreach ($adjustments as $idx => $adj): ?>
                                    <tr>
                                        <td><input type="date" name="adj_date[]" class="ar-input" value="<?= escape_html((string)$adj['date']) ?>"></td>
                                        <td><input type="text" name="adj_code[]" class="ar-input" value="<?= escape_html((string)$adj['code']) ?>"></td>
                                        <td><input type="text" name="adj_name[]" class="ar-input" value="<?= escape_html((string)$adj['name']) ?>"></td>
                                        <td><input type="number" step="0.01" min="0" name="adj_dr[]" class="ar-input adj-dr" value="<?= escape_html(number_format((float)$adj['dr'], 2, '.', '')) ?>"></td>
                                        <td><input type="number" step="0.01" min="0" name="adj_cr[]" class="ar-input adj-cr" value="<?= escape_html(number_format((float)$adj['cr'], 2, '.', '')) ?>"></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end">Total</td>
                                    <td class="ar-cyan" id="adjDrTotal"><?= format_currency(array_sum(array_column($adjustments, 'dr'))) ?></td>
                                    <td id="adjCrTotal"><?= format_currency(array_sum(array_column($adjustments, 'cr'))) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" class="ar-link-btn mt-2" id="addAdjBtn">+ Add Adjustment</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="ar-panel mb-4">
            <div class="ar-hd">
                <h4 class="ar-hd-title"><span class="ar-step">03</span>Deposits <span class="ar-hd-sub">- Tick deposits that have cleared the bank</span></h4>
                <div class="ar-balance-chip">
                    <div class="k">Ticked Total</div>
                    <div class="v" id="depChipTotal"><?= format_currency($deposits_total) ?></div>
                </div>
            </div>
            <div class="p-4">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th style="width:52px;">&#10003;</th>
                            <th>Date</th>
                            <th>Deposit Ref</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($deposits)): ?>
                            <?php foreach ($deposits as $dep): ?>
                                <?php $dep_ref = (string)$dep['deposit_ref']; ?>
                                <tr>
                                    <td><input class="ar-check dep-check" type="checkbox" name="deposits_selected[]" value="<?= escape_html($dep_ref) ?>" data-amount="<?= escape_html(number_format((float)$dep['amount'], 2, '.', '')) ?>" <?= in_array($dep_ref, $deposits_selected, true) ? 'checked' : '' ?>></td>
                                    <td><?= format_date((string)$dep['deposit_date']) ?></td>
                                    <td class="ar-cyan"><?= escape_html($dep_ref) ?></td>
                                    <td class="text-end"><?= format_currency((float)$dep['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center ar-muted">No deposits found for selected bank and period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end">Ticked Total</td>
                            <td class="text-end ar-cyan" id="depFooterTotal"><?= format_currency($deposits_total) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="ar-note mt-2"><i class="fas fa-lightbulb me-2"></i>Unticked deposits will appear as outstanding lodgements in next month's reconciliation.</div>
            </div>
        </div>

        <div class="ar-panel mb-4">
            <div class="ar-hd">
                <h4 class="ar-hd-title"><span class="ar-step">04</span>Payments / Cheques <span class="ar-hd-sub">- Tick payments that have cleared the bank</span></h4>
                <div class="ar-balance-chip">
                    <div class="k">Ticked Total</div>
                    <div class="v" id="payChipTotal"><?= format_currency($payments_total) ?></div>
                </div>
            </div>
            <div class="p-4">
                <table class="ar-table">
                    <thead>
                        <tr>
                            <th style="width:52px;">&#10003;</th>
                            <th>Date</th>
                            <th>PV Ref</th>
                            <th>CHQ #</th>
                            <th>Payee</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $pay): ?>
                                <?php
                                    $pid = (int)$pay['id'];
                                    $is_cancelled = strtolower((string)($pay['status'] ?? '')) === 'cancelled';
                                ?>
                                <tr style="<?= $is_cancelled ? 'opacity:.55;' : '' ?>">
                                    <td><input class="ar-check pay-check" type="checkbox" name="payments_selected[]" value="<?= $pid ?>" data-amount="<?= escape_html(number_format((float)$pay['amount'], 2, '.', '')) ?>" <?= (in_array($pid, $payments_selected, true) && !$is_cancelled) ? 'checked' : '' ?> <?= $is_cancelled ? 'disabled' : '' ?>></td>
                                    <td><?= format_date((string)$pay['voucher_date']) ?></td>
                                    <td><?= escape_html((string)$pay['voucher_number']) ?></td>
                                    <td><?= escape_html((string)$pay['cheque_number']) ?></td>
                                    <td class="<?= $is_cancelled ? 'ar-red' : '' ?>"><?= $is_cancelled ? 'CANCELLED' : escape_html((string)$pay['payee']) ?></td>
                                    <td class="text-end"><?= $is_cancelled ? '&#8212;' : format_currency((float)$pay['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center ar-muted">No payment vouchers found for selected bank and period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" class="text-end">Ticked Total</td>
                            <td class="text-end ar-cyan" id="payFooterTotal"><?= format_currency($payments_total) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="ar-note mt-2"><i class="fas fa-lightbulb me-2"></i>Displays approved/posted vouchers. Unticked items carry forward as unpresented.</div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-3">
            <button type="submit" name="action" value="save" class="ar-btn-save"><i class="fas fa-save me-2"></i>Save</button>
            <button type="submit" name="action" value="authorize" class="ar-btn-auth"><i class="fas fa-check-square me-2"></i>Authorize</button>
        </div>
        <div class="ar-note mt-3"><i class="fas fa-lock me-2"></i>Once authorized, bank reconciliation cannot be amended or cancelled.</div>
    </form>
</div>

<script>
document.getElementById('addAdjBtn')?.addEventListener('click', function () {
    const tbody = document.getElementById('adjRows');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.innerHTML = '' +
        '<td><input type="date" name="adj_date[]" class="ar-input"></td>' +
        '<td><input type="text" name="adj_code[]" class="ar-input" placeholder="Code"></td>' +
        '<td><input type="text" name="adj_name[]" class="ar-input" placeholder="Account Name"></td>' +
        '<td><input type="number" step="0.01" min="0" name="adj_dr[]" class="ar-input adj-dr" value="0.00"></td>' +
        '<td><input type="number" step="0.01" min="0" name="adj_cr[]" class="ar-input adj-cr" value="0.00"></td>';
    tbody.appendChild(tr);
    bindRecalcListeners();
    recalcSummary();
});

function toggleApprovalColumns() {
    const typeEl = document.getElementById('approval_type');
    const type = typeEl ? typeEl.value : 'manager';
    const managerWrap = document.getElementById('managerWrap');
    const adminWrap = document.getElementById('adminWrap');
    if (managerWrap) managerWrap.style.display = (type === 'manager' || type === 'both') ? '' : 'none';
    if (adminWrap) adminWrap.style.display = (type === 'admin' || type === 'both') ? '' : 'none';
}

document.getElementById('approval_type')?.addEventListener('change', toggleApprovalColumns);
toggleApprovalColumns();

function fmtCurrency(n) {
    const v = Number.isFinite(n) ? n : 0;
    return '$' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function toNum(v) {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
}

function sumCheckedAmounts(selector) {
    let total = 0;
    document.querySelectorAll(selector).forEach(function (el) {
        if (el.checked && !el.disabled) {
            total += toNum(el.getAttribute('data-amount'));
        }
    });
    return total;
}

function sumInputs(selector) {
    let total = 0;
    document.querySelectorAll(selector).forEach(function (el) {
        total += toNum(el.value);
    });
    return total;
}

function recalcSummary() {
    const root = document.getElementById('recoCalcRoot');
    if (!root) return;

    const bankStatement = toNum(root.getAttribute('data-bank-statement'));
    const systemBalance = toNum(root.getAttribute('data-system-balance'));
    const depositsTotal = sumCheckedAmounts('.dep-check');
    const paymentsTotal = sumCheckedAmounts('.pay-check');
    const adjDrTotal = sumInputs('.adj-dr');
    const adjCrTotal = sumInputs('.adj-cr');
    const otherItems = adjDrTotal - adjCrTotal;
    const reconciled = systemBalance + depositsTotal - paymentsTotal - otherItems;
    const variance = bankStatement - reconciled;

    const depChip = document.getElementById('depChipTotal');
    const depFooter = document.getElementById('depFooterTotal');
    const payChip = document.getElementById('payChipTotal');
    const payFooter = document.getElementById('payFooterTotal');
    const sumDep = document.getElementById('sumDeposits');
    const sumPay = document.getElementById('sumPayments');
    const adjDr = document.getElementById('adjDrTotal');
    const adjCr = document.getElementById('adjCrTotal');
    const sumOther = document.getElementById('sumOtherItems');
    const sumReconciled = document.getElementById('sumReconciled');
    const sumVariance = document.getElementById('sumVariance');

    if (depChip) depChip.textContent = fmtCurrency(depositsTotal);
    if (depFooter) depFooter.textContent = fmtCurrency(depositsTotal);
    if (payChip) payChip.textContent = fmtCurrency(paymentsTotal);
    if (payFooter) payFooter.textContent = fmtCurrency(paymentsTotal);
    if (sumDep) sumDep.textContent = '+' + fmtCurrency(depositsTotal);
    if (sumPay) sumPay.textContent = '-' + fmtCurrency(paymentsTotal);
    if (adjDr) adjDr.textContent = fmtCurrency(adjDrTotal);
    if (adjCr) adjCr.textContent = fmtCurrency(adjCrTotal);
    if (sumOther) {
        sumOther.textContent = (otherItems >= 0 ? '-' : '+') + fmtCurrency(Math.abs(otherItems));
        sumOther.classList.remove('ar-green', 'ar-red');
        sumOther.classList.add(otherItems >= 0 ? 'ar-red' : 'ar-green');
    }
    if (sumReconciled) sumReconciled.textContent = fmtCurrency(reconciled);
    if (sumVariance) {
        sumVariance.textContent = fmtCurrency(variance);
        sumVariance.classList.remove('ar-green', 'ar-red');
        sumVariance.classList.add(Math.abs(variance) < 0.0001 ? 'ar-green' : 'ar-red');
    }
}

function bindRecalcListeners() {
    document.querySelectorAll('.dep-check, .pay-check, .adj-dr, .adj-cr').forEach(function (el) {
        el.removeEventListener('change', recalcSummary);
        el.removeEventListener('input', recalcSummary);
        el.addEventListener('change', recalcSummary);
        el.addEventListener('input', recalcSummary);
    });
}

document.getElementById('loadDataBtn')?.addEventListener('click', function () {
    const bank = document.getElementById('bank_id')?.value || '';
    const from = document.getElementById('period_from')?.value || '';
    const to = document.getElementById('period_to')?.value || '';

    if (!bank) {
        alert('Please select bank first.');
        return;
    }
    if (!from || !to) {
        alert('Please select period from and period to.');
        return;
    }
    if (new Date(from) > new Date(to)) {
        alert('Period From cannot be after Period To.');
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('bank_id', bank);
    url.searchParams.set('period_from', from);
    url.searchParams.set('period_to', to);
    window.location.href = url.toString();
});

bindRecalcListeners();
recalcSummary();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
