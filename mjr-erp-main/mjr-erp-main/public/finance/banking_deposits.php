<?php
/**
 * Banking & Deposits
 * Full workflow: Dashboard + Make a Deposit form with pending receipts
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Banking & Deposits - MJR Group ERP';
$company_id = (int)active_company_id(1);

// Schema-safe column checks (production servers may have slight DB differences)
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
$has_receipts_status = $table_has_column('receipts', 'status');
$has_receipts_description = $table_has_column('receipts', 'description');
$has_bank_account_number = $table_has_column('bank_accounts', 'account_number');
$bank_account_number_select = $has_bank_account_number ? "account_number" : "NULL AS account_number";
$posted_date_expr = $has_receipts_posted_at ? "r.posted_at" : "r.receipt_date";

// Load banks
$banks = db_fetch_all("
    SELECT id, bank_name, account_name, {$bank_account_number_select}
    FROM bank_accounts
    WHERE is_active = 1
    ORDER BY bank_name, account_name
") ?: [];

// ── POST: Save a new deposit ──────────────────────────────────────────────────
$errors = [];
if (is_post() && post('action') === 'make_deposit') {
    $csrf_token    = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        $banking_date  = trim((string)post('banking_date', ''));
        $bank_id       = (int)post('bank_id', 0);
        $comments      = trim((string)post('comments', ''));
        $receipt_ids   = array_values(array_unique(array_filter(array_map('intval', (array)post('receipt_ids', [])), fn($v) => $v > 0)));

        if ($banking_date === '')       $errors['banking_date'] = 'Banking date is required.';
        if ($bank_id <= 0)              $errors['bank_id']      = 'Please select a bank.';
        if (empty($receipt_ids))        $errors['receipt_ids']  = 'Please select at least one receipt to bank.';

        if (empty($errors)) {
            // Verify receipts belong to this company and are unbanked
            $ph = implode(',', array_fill(0, count($receipt_ids), '?'));
            $company_clause = $has_receipts_company ? " AND company_id = ? " : "";
            $to_bank_params = $receipt_ids;
            if ($has_receipts_company) {
                $to_bank_params[] = $company_id;
            }
            $to_bank = db_fetch_all("
                SELECT id, receipt_number, amount, customer_id
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
            // Generate next deposit REF number
            $last_ref = db_fetch("SELECT MAX(reference) AS last_ref FROM receipts WHERE reference LIKE 'REF%'");
            $last_num = 3000;
            if ($last_ref && preg_match('/REF(\d+)/', (string)($last_ref['last_ref'] ?? ''), $m)) {
                $last_num = (int)$m[1];
            }
            $dep_ref    = 'REF' . ($last_num + 1);
            $deposit_by = current_user_id();
            $dep_total  = array_sum(array_column($to_bank, 'amount'));

            try {
                db_begin_transaction();

                // Mark each selected receipt with the deposit reference and bank
                $ids_to_update = array_column($to_bank, 'id');
                foreach ($ids_to_update as $rid) {
                    $set_parts = [
                        "reference = ?",
                        "bank_account_id = ?",
                    ];
                    $set_params = [$dep_ref, $bank_id];
                    if ($has_receipts_status) {
                        $set_parts[] = "status = 'banked'";
                    }
                    if ($has_receipts_posted_at) {
                        $set_parts[] = "posted_at = NOW()";
                    }
                    if ($has_receipts_description) {
                        $set_parts[] = "description = CASE WHEN description IS NULL OR description = '' THEN ? ELSE CONCAT(description, ' | ', ?) END";
                        $set_params[] = $comments;
                        $set_params[] = $comments;
                    }
                    $set_params[] = $rid;
                    db_query("
                        UPDATE receipts
                        SET " . implode(', ', $set_parts) . "
                        WHERE id = ?
                    ", $set_params);
                }

                db_commit();

                set_flash("Deposit {$dep_ref} created successfully! Total: " . format_currency($dep_total), 'success');
                redirect("view_deposit.php?ref=" . urlencode($dep_ref));
            } catch (Exception $e) {
                db_rollback();
                log_error('Banking deposit error: ' . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    } else {
        $errors['csrf'] = 'Invalid request. Please refresh and try again.';
    }
}

// ── Load deposit history ──────────────────────────────────────────────────────
$search    = trim((string)get('search', ''));
$bank_id_f = (int)get('bank_id', 0);
$from_date = trim((string)get('from_date', ''));
$to_date   = trim((string)get('to_date', ''));

$h_where  = "WHERE TRIM(COALESCE(r.reference,'')) <> '' ";
$h_params = [];
if ($has_receipts_company) {
    $h_where .= " AND r.company_id = ? ";
    $h_params[] = $company_id;
}

if ($search !== '') {
    $h_where .= " AND (r.reference LIKE ? OR b.bank_name LIKE ?) ";
    $like = '%' . $search . '%';
    $h_params[] = $like;
    $h_params[] = $like;
}
if ($bank_id_f > 0) {
    $h_where .= " AND r.bank_account_id = ? ";
    $h_params[] = $bank_id_f;
}
if ($from_date !== '') {
    $h_where .= " AND DATE({$posted_date_expr}) >= ? ";
    $h_params[] = $from_date;
}
if ($to_date !== '') {
    $h_where .= " AND DATE({$posted_date_expr}) <= ? ";
    $h_params[] = $to_date;
}

$deposits = db_fetch_all("
    SELECT
        r.reference            AS deposit_ref,
        b.bank_name,
        b.account_name,
        " . ($has_bank_account_number ? "b.account_number" : "NULL AS account_number") . ",
        DATE(MIN({$posted_date_expr})) AS banking_date,
        DATE(MIN(r.receipt_date)) AS receipt_date,
        COUNT(*)               AS receipts_count,
        SUM(r.amount)          AS deposit_amount,
        r.bank_account_id,
        MAX({$posted_date_expr})       AS posted_at
    FROM receipts r
    LEFT JOIN bank_accounts b ON r.bank_account_id = b.id
    {$h_where}
    GROUP BY r.reference, r.bank_account_id, b.bank_name, b.account_name" . ($has_bank_account_number ? ", b.account_number" : "") . "
    ORDER BY MAX({$posted_date_expr}) DESC
", $h_params) ?: [];

// ── Load pending (unbanked) receipts for deposit form ─────────────────────────
$pending_receipts = db_fetch_all("
    SELECT r.id, r.receipt_number, r.receipt_date, r.amount, c.name AS customer_name
    FROM receipts r
    LEFT JOIN customers c ON r.customer_id = c.id
    WHERE (TRIM(COALESCE(r.reference,'')) = '' OR r.reference IS NULL)
      " . ($has_receipts_company ? "AND r.company_id = ?" : "") . "
    ORDER BY r.receipt_date DESC, r.id DESC
", $has_receipts_company ? [$company_id] : []) ?: [];

$pending_count = count($pending_receipts);
$pending_total = array_sum(array_column($pending_receipts, 'amount'));

// Stats
$month_stats = db_fetch("
    SELECT
        COUNT(DISTINCT r.reference) AS deposits_this_month,
        COALESCE(SUM(r.amount), 0)  AS total_this_month
    FROM receipts r
    WHERE TRIM(COALESCE(r.reference,'')) <> ''
      AND YEAR({$posted_date_expr}) = YEAR(CURDATE())
      AND MONTH({$posted_date_expr}) = MONTH(CURDATE())
      " . ($has_receipts_company ? " AND r.company_id = " . (int)$company_id : "") . "
") ?: [];

$overall_total = db_fetch("
    SELECT COALESCE(SUM(r.amount), 0) AS total
    FROM receipts r
    WHERE TRIM(COALESCE(r.reference,'')) <> ''
      " . ($has_receipts_company ? " AND r.company_id = " . (int)$company_id : "") . "
") ?: ['total' => 0];

// Bank account history (safe fallback if bank_transactions differs on server)
try {
    $bank_account_history = db_fetch_all("
        SELECT
            b.id,
            b.bank_name,
            b.account_name,
            " . ($has_bank_account_number ? "b.account_number" : "NULL AS account_number") . ",
            " . ($table_has_column('bank_accounts', 'current_balance') ? "COALESCE(b.current_balance, 0)" : "0") . " AS current_balance,
            COALESCE(tx.total_in, 0) AS total_in,
            COALESCE(tx.total_out, 0) AS total_out,
            COALESCE(tx.net_amount, 0) AS net_amount,
            tx.last_txn_date
        FROM bank_accounts b
        LEFT JOIN (
            SELECT
                bank_account_id,
                SUM(CASE WHEN LOWER(COALESCE(type, '')) = 'deposit' THEN amount ELSE 0 END) AS total_in,
                SUM(CASE WHEN LOWER(COALESCE(type, '')) = 'deposit' THEN 0 ELSE amount END) AS total_out,
                SUM(CASE WHEN LOWER(COALESCE(type, '')) = 'deposit' THEN amount ELSE -amount END) AS net_amount,
                MAX(transaction_date) AS last_txn_date
            FROM bank_transactions
            GROUP BY bank_account_id
        ) tx ON tx.bank_account_id = b.id
        ORDER BY b.bank_name, b.account_name
    ") ?: [];
} catch (Exception $e) {
    $bank_account_history = db_fetch_all("
        SELECT
            b.id,
            b.bank_name,
            b.account_name,
            " . ($has_bank_account_number ? "b.account_number" : "NULL AS account_number") . ",
            " . ($table_has_column('bank_accounts', 'current_balance') ? "COALESCE(b.current_balance, 0)" : "0") . " AS current_balance,
            0 AS total_in,
            0 AS total_out,
            0 AS net_amount,
            NULL AS last_txn_date
        FROM bank_accounts b
        ORDER BY b.bank_name, b.account_name
    ") ?: [];
}

// Show deposit form when button clicked or on POST error
$show_form = (isset($_GET['form']) && $_GET['form'] === '1') || !empty($errors);

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --bd-bg: #080c1a;
    --bd-panel: #151c30;
    --bd-panel2: #111827;
    --bd-border: #232d52;
    --bd-cyan: #09d4f0;
    --bd-soft: #8c9dc3;
    --bd-text: #ecf0ff;
    --bd-heading: #ffffff;
    --bd-input-bg: #1c2540;
    --bd-input-border: #2a3660;
    --bd-input-text: #eef2ff;
    --bd-row-border: rgba(35,45,82,.65);
    --bd-gold: #ffbf45;
    --bd-green: #3ecf5b;
    --bd-red: #ff5b5b;
}

[data-bs-theme="light"] {
    --bd-bg: #f8f9fa;
    --bd-panel: #ffffff;
    --bd-panel2: #f8f9fa;
    --bd-border: #e0e0e0;
    --bd-cyan: #0dcaf0;
    --bd-soft: #6c757d;
    --bd-text: #212529;
    --bd-heading: #212529;
    --bd-input-bg: #ffffff;
    --bd-input-border: #dee2e6;
    --bd-input-text: #212529;
    --bd-row-border: rgba(0,0,0,.08);
    --bd-gold: #ffc107;
    --bd-green: #198754;
    --bd-red: #dc3545;
}

body { background: var(--bd-bg); color: var(--bd-text); }

/* Screen tag */
.bd-screen { border: 1px solid rgba(9,212,240,.5); border-radius: 10px; background: rgba(9,212,240,.07); color: var(--bd-cyan); font-weight: 700; padding: .6rem 1rem; letter-spacing: .3px; }

/* Stat cards */
.bd-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .9rem; }
.bd-stat { border-radius: 14px; padding: 1.1rem 1.4rem; display: flex; flex-direction: column; justify-content: center; border: 1px solid rgba(255,255,255,.04); }
.bd-stat .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: 2px; font-weight: 800; margin-bottom: .3rem; }
.bd-stat .val { font-size: 2.1rem; font-weight: 900; line-height: 1.1; }
.bd-stat-pending  { background: linear-gradient(135deg, rgba(90,57,0,.85), rgba(120,78,0,.7)); }
.bd-stat-pending .lbl, .bd-stat-pending .val { color: var(--bd-gold); }
.bd-stat-month    { background: linear-gradient(135deg, rgba(4,80,92,.9), rgba(6,110,127,.75)); }
.bd-stat-month .lbl, .bd-stat-month .val { color: var(--bd-cyan); }
.bd-stat-total    { background: linear-gradient(135deg, rgba(22,73,22,.9), rgba(30,96,30,.75)); }
.bd-stat-total .lbl, .bd-stat-total .val { color: var(--bd-green); }

/* Panel card */
.bd-card { border-radius: 14px; border: 1px solid var(--bd-border); background: linear-gradient(180deg, var(--bd-panel), var(--bd-panel2)); overflow: hidden; }
.bd-card-hd { border-bottom: 1px solid var(--bd-border); padding: .9rem 1.4rem; display: flex; align-items: center; justify-content: space-between; background: transparent; }
.bd-card-hd-left { display: flex; align-items: center; gap: .7rem; color: var(--bd-heading); }
.bd-badge { background: var(--bd-cyan); color: #031218; border-radius: 8px; font-weight: 900; padding: .18rem .55rem; font-size: .85rem; }
.bd-htext { color: var(--bd-heading) !important; font-weight: 800; font-size: 1.25rem; }

/* Filter bar */
.bd-filter { display: grid; grid-template-columns: 1.3fr 1fr 1fr 1fr auto; gap: .7rem; align-items: end; }
.bd-label { color: #8fa4cc; font-size: .8rem; text-transform: uppercase; letter-spacing: .7px; font-weight: 700; margin-bottom: .3rem; display: block; }
.bd-input, .bd-select {
    width: 100%; background: var(--bd-input-bg); border: 1px solid var(--bd-input-border); color: var(--bd-input-text);
    border-radius: 9px; padding: .55rem .8rem; transition: border-color .2s;
}
.bd-input:focus, .bd-select:focus { border-color: rgba(9,212,240,.65); outline: none; box-shadow: 0 0 0 .18rem rgba(9,212,240,.18); }
.bd-btn { background: var(--bd-cyan); color: #031218; border-radius: 10px; border: 0; font-weight: 800; padding: .6rem 1.1rem; display: inline-flex; align-items: center; gap: .4rem; text-decoration: none; cursor: pointer; }
.bd-btn-outline { background: transparent; color: var(--bd-text); border: 1px solid var(--bd-input-border); border-radius: 10px; font-weight: 700; padding: .55rem 1rem; text-decoration: none; }
.bd-btn-green { background: var(--bd-green); color: #031218; }

/* Deposit history table */
.bd-table { width: 100%; border-collapse: collapse; }
.bd-table thead th { padding: .9rem 1rem; font-size: .74rem; color: #7d8fb8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--bd-border); font-weight: 700; white-space: nowrap; }
.bd-table tbody td { padding: .82rem 1rem; border-bottom: 1px solid var(--bd-row-border); color: var(--bd-text); vertical-align: middle; }
.bd-table tbody tr:hover td { background: rgba(9,212,240,.04); }
.bd-ref { color: var(--bd-cyan); font-weight: 800; text-decoration: none; }
.bd-ref:hover { text-decoration: underline; }
.bd-amount { font-weight: 800; color: var(--bd-heading); }
.bd-eye { width: 32px; height: 32px; border-radius: 7px; border: 1px solid var(--bd-input-border); background: transparent; color: var(--bd-text); display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.bd-eye:hover { border-color: var(--bd-cyan); color: var(--bd-cyan); }

/* Deposit form */
.bd-form-overlay { position: fixed; inset: 0; background: rgba(5,9,26,.88); z-index: 1040; display: flex; align-items: flex-start; justify-content: center; overflow-y: auto; padding: 2rem 1rem; }
.bd-form-box { background: var(--bd-panel); border: 1px solid var(--bd-border); border-radius: 16px; width: 100%; max-width: 1100px; overflow: hidden; position: relative; animation: slideDown .25s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-24px); } to { opacity: 1; transform: translateY(0); } }
.bd-form-hd { background: linear-gradient(90deg, rgba(9,212,240,.14), rgba(9,212,240,.06)); border-bottom: 1px solid var(--bd-border); padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.bd-form-hd .title { color: #fff; font-weight: 800; font-size: 1.15rem; }
.bd-close { background: transparent; border: 0; color: #8fa4cc; font-size: 1.4rem; cursor: pointer; line-height: 1; }
.bd-close:hover { color: #fff; }

/* Deposit info row */
.bd-info-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 1rem; }

/* Receipt selection table */
.bd-receipt-table { width: 100%; border-collapse: collapse; }
.bd-receipt-table thead th { padding: .75rem 1rem; font-size: .73rem; color: #7d8fb8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--bd-border); font-weight: 700; }
.bd-receipt-table tbody td { padding: .7rem 1rem; border-bottom: 1px solid rgba(35,45,82,.6); color: #ecf0ff; vertical-align: middle; }
.bd-receipt-table tbody tr:hover td { background: rgba(9,212,240,.05); }
.bd-receipt-table tbody tr.selected td { background: rgba(9,212,240,.09); }
.bd-check-cell { width: 46px; text-align: center; }
.bd-checkbox { width: 18px; height: 18px; accent-color: var(--bd-cyan); cursor: pointer; }

/* Deposit amount display */
.bd-dep-amt { border: 1px solid rgba(9,212,240,.6); border-radius: 12px; padding: .65rem 1.2rem; text-align: center; min-width: 220px; }
.bd-dep-amt .cap { color: #7d8fb8; font-size: .8rem; text-transform: uppercase; letter-spacing: 1px; }
.bd-dep-amt .val { color: var(--bd-cyan); font-size: 1.8rem; font-weight: 900; line-height: 1.2; }

/* Warning bar */
.bd-warn { background: rgba(255,191,69,.12); border-left: 3px solid rgba(255,191,69,.7); color: var(--bd-gold); border-radius: 6px; padding: .55rem .9rem; font-weight: 600; }

/* Footer notice */
.bd-foot { border-top: 1px solid rgba(255,191,69,.2); background: rgba(255,191,69,.07); color: #ffc850; border-radius: 10px; padding: .7rem 1rem; font-weight: 600; }

/* Error alert */
.bd-alert-err { background: rgba(255,70,70,.14); border: 1px solid rgba(255,70,70,.35); color: #ff9898; border-radius: 10px; padding: .75rem 1rem; margin-bottom: 1rem; }

@media (max-width: 1100px) {
    .bd-stats { grid-template-columns: 1fr; }
    .bd-filter { grid-template-columns: 1fr; }
    .bd-info-grid { grid-template-columns: 1fr 1fr; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="bd-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.5rem;"></i> SCREEN: Banking / Deposits — Dashboard & Deposit Entry</div>

    <div class="d-flex justify-content-between align-items-start mb-4 gap-3 flex-wrap">
        <div>
            <h1 class="h2 fw-bold text-white mb-1"><i class="fas fa-university me-2" style="color:#d0daf5;"></i>Banking / Deposits</h1>
            <p class="mb-0">Consolidate pending receipts into a bank deposit and generate a deposit reference number.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="add_bank_account.php" class="bd-btn" style="background:#202944;color:#cfe0ff;border:1px solid #344271;">
                <i class="fas fa-building-columns"></i> Make Bank Account
            </a>
            <a href="make_deposit.php" class="bd-btn" id="makeDepositBtn">
                <i class="fas fa-plus-circle"></i> Make a Deposit
            </a>
        </div>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : ($flash['type'] === 'error' ? 'danger' : 'warning') ?> mb-4" style="background:rgba(<?= $flash['type']==='success'?'62,207,91':'255,91,91' ?>,.15);border-color:rgba(<?= $flash['type']==='success'?'62,207,91':'255,91,91' ?>,.35);color:<?= $flash['type']==='success'?'#3ecf5b':'#ff9898' ?>;">
            <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-triangle' ?> me-2"></i><?= escape_html($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="bd-stats mb-4">
        <div class="bd-stat bd-stat-pending">
            <div class="lbl">Pending Receipts</div>
            <div class="val"><?= (int)$pending_count ?></div>
            <div style="color:rgba(255,191,69,.7);font-size:.82rem;margin-top:.2rem;"><?= format_currency($pending_total) ?> awaiting banking</div>
        </div>
        <div class="bd-stat bd-stat-month">
            <div class="lbl">Deposits This Month</div>
            <div class="val"><?= (int)($month_stats['deposits_this_month'] ?? 0) ?></div>
            <div style="color:rgba(9,212,240,.7);font-size:.82rem;margin-top:.2rem;"><?= format_currency((float)($month_stats['total_this_month'] ?? 0)) ?> deposited</div>
        </div>
        <div class="bd-stat bd-stat-total">
            <div class="lbl">Total Deposited (All Time)</div>
            <div class="val"><?= format_currency((float)($overall_total['total'] ?? 0)) ?></div>
            <div style="color:rgba(62,207,91,.65);font-size:.82rem;margin-top:.2rem;"><?= count($deposits) ?> deposit records</div>
        </div>
    </div>

    <!-- Filter + Deposit History -->
    <div class="bd-card mb-4">
        <div class="bd-card-hd">
            <div class="bd-card-hd-left">
                <span class="bd-badge">01</span>
                <span class="bd-htext">Deposit History</span>
                <span class="badge rounded-pill ms-1" style="background:#232d52;color:#a4b4d8;"><?= count($deposits) ?></span>
            </div>
        </div>
        <div class="p-3">
            <form method="GET" action="">
                <div class="bd-filter mb-3">
                    <div>
                        <label class="bd-label">Search</label>
                        <input class="bd-input" type="text" name="search" value="<?= escape_html($search) ?>" placeholder="Deposit ref # or bank...">
                    </div>
                    <div>
                        <label class="bd-label">Bank</label>
                        <select class="bd-select" name="bank_id">
                            <option value="0">All Banks</option>
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= (int)$b['id'] ?>" <?= $bank_id_f === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= escape_html((string)$b['bank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="bd-label">From Date</label>
                        <input class="bd-input" type="date" name="from_date" value="<?= escape_html($from_date) ?>">
                    </div>
                    <div>
                        <label class="bd-label">To Date</label>
                        <input class="bd-input" type="date" name="to_date" value="<?= escape_html($to_date) ?>">
                    </div>
                    <div>
                        <button type="submit" class="bd-btn" style="padding:.55rem 1rem;"><i class="fas fa-search"></i></button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="bd-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Deposit Ref</th>
                            <th>Bank</th>
                            <th>Acct</th>
                            <th>Receipts</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">View</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($deposits)): ?>
                        <tr><td colspan="7" class="text-center py-4" style="color:#6d7ea8;">No deposit records found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deposits as $d): ?>
                        <tr>
                            <td><?= escape_html($d['banking_date'] ? date('d-M-Y', strtotime($d['banking_date'])) : '—') ?></td>
                            <td><a class="bd-ref" href="view_deposit.php?ref=<?= urlencode((string)$d['deposit_ref']) ?>"><?= escape_html((string)$d['deposit_ref']) ?></a></td>
                            <td><?= escape_html(trim((string)($d['bank_name'] ?? '')) ?: 'N/A') ?></td>
                            <td><?= escape_html(trim((string)($d['account_number'] ?? $d['account_name'] ?? 'XXXX')) ?: 'XXXX') ?></td>
                            <td><span style="color:#a4b4d8;"><?= (int)$d['receipts_count'] ?> receipt<?= $d['receipts_count'] != 1 ? 's' : '' ?></span></td>
                            <td class="text-end bd-amount"><?= format_currency((float)$d['deposit_amount']) ?></td>
                            <td class="text-center">
                                <a href="view_deposit.php?ref=<?= urlencode((string)$d['deposit_ref']) ?>" class="bd-eye"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bank Account History -->
    <div class="bd-card mb-4">
        <div class="bd-card-hd">
            <div class="bd-card-hd-left">
                <span class="bd-badge">02</span>
                <span class="bd-htext">Bank Account History</span>
                <span class="badge rounded-pill ms-1" style="background:#232d52;color:#a4b4d8;"><?= count($bank_account_history) ?></span>
            </div>
        </div>
        <div class="p-3">
            <div class="table-responsive">
                <table class="bd-table">
                    <thead>
                        <tr>
                            <th>Bank</th>
                            <th>Account</th>
                            <th>Acct #</th>
                            <th class="text-end">Current Balance</th>
                            <th class="text-end">In</th>
                            <th class="text-end">Out</th>
                            <th class="text-end">Net Movement</th>
                            <th>Last Txn</th>
                            <th class="text-center">View</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bank_account_history)): ?>
                        <tr><td colspan="9" class="text-center py-4" style="color:#6d7ea8;">No bank account history found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bank_account_history as $bh): ?>
                            <tr>
                                <td><?= escape_html(trim((string)$bh['bank_name']) ?: 'N/A') ?></td>
                                <td><?= escape_html(trim((string)$bh['account_name']) ?: 'N/A') ?></td>
                                <td><?= escape_html(trim((string)($bh['account_number'] ?? 'XXXX')) ?: 'XXXX') ?></td>
                                <td class="text-end bd-amount"><?= format_currency((float)$bh['current_balance']) ?></td>
                                <td class="text-end" style="color:var(--bd-green);font-weight:700;"><?= format_currency((float)$bh['total_in']) ?></td>
                                <td class="text-end" style="color:var(--bd-red);font-weight:700;"><?= format_currency((float)$bh['total_out']) ?></td>
                                <td class="text-end" style="font-weight:800;color:<?= ((float)$bh['net_amount'] >= 0 ? 'var(--bd-cyan)' : 'var(--bd-red)') ?>;">
                                    <?= format_currency((float)$bh['net_amount']) ?>
                                </td>
                                <td><?= !empty($bh['last_txn_date']) ? escape_html(date('d-M-Y', strtotime((string)$bh['last_txn_date']))) : '—' ?></td>
                                <td class="text-center">
                                    <a href="view_bank.php?id=<?= (int)$bh['id'] ?>" class="bd-eye"><i class="fas fa-eye"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bd-foot">
        <i class="fas fa-lock me-2"></i>Banking once saved cannot be amended or cancelled.
    </div>
</div>

<!-- ── MAKE A DEPOSIT MODAL FORM ─────────────────────────────────────────────── -->
<?php if ($show_form): ?>
<div class="bd-form-overlay" id="depositOverlay">
    <div class="bd-form-box">
        <div class="bd-form-hd">
            <div class="title"><i class="fas fa-university me-2"></i>Make a Deposit — Select Receipts to Bank</div>
            <button class="bd-close" onclick="closeDepositForm()" title="Close">&times;</button>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bd-alert-err mx-4 mt-3">
                <?php foreach ($errors as $e): ?>
                    <div><i class="fas fa-exclamation-circle me-1"></i><?= escape_html($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="?form=1" id="depositForm">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="make_deposit">

            <!-- Header row -->
            <div class="p-4 border-bottom" style="border-color:var(--bd-border)!important;">
                <div class="bd-info-grid">
                    <div>
                        <label class="bd-label">Date</label>
                        <input type="text" class="bd-input" value="<?= date('d-M-Y') ?>" readonly style="opacity:.7;">
                    </div>
                    <div>
                        <label class="bd-label">Banking Date *</label>
                        <input type="date" class="bd-input" name="banking_date" id="banking_date"
                               value="<?= escape_html(post('banking_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div>
                        <label class="bd-label">Select Bank *</label>
                        <select class="bd-select" name="bank_id" id="bank_id" required>
                            <option value="">TDB / ANZ / BSP / MBF</option>
                            <?php foreach ($banks as $b): ?>
                                <option value="<?= (int)$b['id'] ?>"
                                        data-acct="<?= escape_html(trim((string)($b['account_number'] ?? $b['account_name'] ?? 'XXXX'))) ?>"
                                    <?= (int)post('bank_id') === (int)$b['id'] ? 'selected' : '' ?>>
                                    <?= escape_html((string)$b['bank_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="bd-label">Bank Acct</label>
                        <input type="text" class="bd-input" id="bank_acct_display" value="—" readonly style="opacity:.7;">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="bd-label">Comments</label>
                    <input type="text" class="bd-input" name="comments" value="<?= escape_html(post('comments', '')) ?>" placeholder="Optional notes for this deposit...">
                </div>
            </div>

            <!-- Pending receipts table -->
            <div class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div style="color:#fff;font-weight:700;">
                        <i class="fas fa-list-check me-2" style="color:var(--bd-cyan);"></i>
                        Pending Receipts
                        <span class="badge ms-1" style="background:rgba(9,212,240,.2);color:var(--bd-cyan);"><?= $pending_count ?></span>
                    </div>
                    <div class="bd-dep-amt">
                        <div class="cap">Deposit Amount</div>
                        <div class="val" id="depositTotal">$0.00</div>
                    </div>
                </div>

                <?php if (empty($pending_receipts)): ?>
                    <div class="text-center py-4" style="color:#6d7ea8;">
                        <i class="fas fa-inbox fa-2x mb-2 d-block" style="opacity:.4;"></i>
                        No pending receipts available for banking.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="bd-receipt-table">
                            <thead>
                                <tr>
                                    <th class="bd-check-cell">
                                        <input type="checkbox" class="bd-checkbox" id="checkAll" title="Select All">
                                    </th>
                                    <th>Date</th>
                                    <th>Pending Receipt #</th>
                                    <th>Customer</th>
                                    <th class="text-end">RCT AMT</th>
                                    <th class="text-center">Select</th>
                                </tr>
                            </thead>
                            <tbody id="receiptBody">
                                <?php
                                $prev_selected = array_map('intval', (array)post('receipt_ids', []));
                                foreach ($pending_receipts as $rc):
                                    $is_checked = !empty($prev_selected) ? in_array((int)$rc['id'], $prev_selected, true) : false;
                                ?>
                                <tr class="receipt-row <?= $is_checked ? 'selected' : '' ?>"
                                    data-amount="<?= number_format((float)$rc['amount'], 2, '.', '') ?>">
                                    <td class="bd-check-cell">
                                        <input type="checkbox" class="bd-checkbox receipt-check"
                                               name="receipt_ids[]"
                                               value="<?= (int)$rc['id'] ?>"
                                               <?= $is_checked ? 'checked' : '' ?>>
                                    </td>
                                    <td><?= escape_html(date('d-Mar', strtotime((string)$rc['receipt_date']))) ?></td>
                                    <td><span style="color:var(--bd-cyan);font-weight:800;"><?= escape_html((string)$rc['receipt_number']) ?></span></td>
                                    <td><?= escape_html((string)($rc['customer_name'] ?? 'N/A')) ?></td>
                                    <td class="text-end" style="font-weight:700;">
                                        $ <?= number_format((float)$rc['amount'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="status-label" style="font-size:.82rem;font-weight:700;color:<?= $is_checked ? 'var(--bd-green)' : '#6d7ea8' ?>;">
                                            <?= $is_checked ? 'TRUE' : 'FALSE' ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background:rgba(9,212,240,.05);">
                                    <td colspan="4" class="text-end" style="font-weight:800;color:#fff;padding:.8rem 1rem;">TOTAL</td>
                                    <td class="text-end" style="font-weight:900;color:var(--bd-cyan);padding:.8rem 1rem;" id="footerTotal">$0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="bd-warn mt-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Once saved it will give a Deposit Reference Number (e.g. REF3006). Banking once saved cannot be amended.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="px-4 pb-4 d-flex justify-content-between align-items-center">
                <button type="button" class="bd-btn-outline" onclick="closeDepositForm()">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <?php if (!empty($pending_receipts)): ?>
                <button type="submit" class="bd-btn bd-btn-green" id="saveDepositBtn">
                    <i class="fas fa-save me-1"></i>Save Deposit
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    // Bank → account number display
    const bankSel = document.getElementById('bank_id');
    const acctDisplay = document.getElementById('bank_acct_display');
    if (bankSel) {
        function updateAcct() {
            const opt = bankSel.selectedOptions[0];
            acctDisplay.value = opt ? (opt.getAttribute('data-acct') || '—') : '—';
        }
        bankSel.addEventListener('change', updateAcct);
        updateAcct();
    }

    // Checkbox logic + total
    const checks = document.querySelectorAll('.receipt-check');
    const checkAll = document.getElementById('checkAll');
    const totalDisplay = document.getElementById('depositTotal');
    const footerTotal = document.getElementById('footerTotal');

    function fmt(n) {
        return '$' + Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalc() {
        let total = 0;
        checks.forEach(cb => {
            const row = cb.closest('.receipt-row');
            const lbl = row.querySelector('.status-label');
            if (cb.checked) {
                total += parseFloat(row.getAttribute('data-amount') || '0');
                row.classList.add('selected');
                if (lbl) { lbl.textContent = 'TRUE'; lbl.style.color = 'var(--bd-green)'; }
            } else {
                row.classList.remove('selected');
                if (lbl) { lbl.textContent = 'FALSE'; lbl.style.color = '#6d7ea8'; }
            }
        });
        if (totalDisplay) totalDisplay.textContent = fmt(total);
        if (footerTotal) footerTotal.textContent = fmt(total);
    }

    checks.forEach(cb => cb.addEventListener('change', recalc));

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            checks.forEach(cb => { cb.checked = checkAll.checked; });
            recalc();
        });
    }

    // Init
    recalc();
})();

function closeDepositForm() {
    window.location.href = 'banking_deposits.php';
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
