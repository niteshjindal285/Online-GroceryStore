<?php
/**
 * View Deposit — shows saved deposit header + selected (TRUE) receipts
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

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

$has_receipts_posted_at = $table_has_column('receipts', 'posted_at');
$has_bank_account_number = $table_has_column('bank_accounts', 'account_number');

$ref = trim((string)get('ref', ''));
if ($ref === '') {
    set_flash('No deposit reference provided.', 'error');
    redirect('banking_deposits.php');
}

$posted_at_select = $has_receipts_posted_at ? "r.posted_at AS posted_at" : "r.receipt_date AS posted_at";
$acct_no_select = $has_bank_account_number ? "b.account_number" : "b.account_name AS account_number";

// Load all receipts for this deposit ref (banked, selected ones only)
$receipts = db_fetch_all("
    SELECT
        r.id, r.receipt_number, r.receipt_date, r.amount,
        r.bank_account_id, {$posted_at_select}, r.created_by,
        c.name  AS customer_name,
        b.bank_name, b.account_name, {$acct_no_select},
        u.full_name AS deposited_by_name, u.username AS deposited_by_uname
    FROM receipts r
    LEFT JOIN customers    c ON r.customer_id    = c.id
    LEFT JOIN bank_accounts b ON r.bank_account_id = b.id
    LEFT JOIN users         u ON r.created_by     = u.id
    WHERE r.reference = ?
    ORDER BY r.receipt_date ASC, r.id ASC
", [$ref]) ?: [];

if (empty($receipts)) {
    set_flash('Deposit reference "' . $ref . '" not found.', 'error');
    redirect('banking_deposits.php');
}

// Pull header info from first row
$first        = $receipts[0];
$bank_name    = trim((string)($first['bank_name']    ?? ''));
$acct_display = trim((string)($first['account_number'] ?? $first['account_name'] ?? 'XXXX')) ?: 'XXXX';
$banking_date = $first['posted_at']    ?? $first['receipt_date'];
$dep_by_name  = trim((string)($first['deposited_by_name'] ?? '')) ?: (string)($first['deposited_by_uname'] ?? 'System');
$deposit_total = array_sum(array_column($receipts, 'amount'));
$receipt_count = count($receipts);

$page_title = 'Deposit ' . $ref . ' — MJR Group ERP';

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --vd-bg:     #070b18;
    --vd-panel:  #131929;
    --vd-panel2: #0f1520;
    --vd-border: #1f2c50;
    --vd-cyan:   #09d4f0;
    --vd-soft:   #8a9bc0;
    --vd-green:  #3ecf5b;
    --vd-gold:   #ffbf45;
    --vd-white:  #eef2ff;
}

[data-bs-theme="light"] {
    --vd-bg:     #f8f9fa;
    --vd-panel:  #ffffff;
    --vd-panel2: #f8f9fa;
    --vd-border: #e0e0e0;
    --vd-cyan:   #0dcaf0;
    --vd-soft:   #6c757d;
    --vd-green:  #198754;
    --vd-gold:   #ffc107;
    --vd-white:  #212529;
}

body { background: var(--vd-bg); color: var(--vd-soft); }

/* Screen tag */
.vd-screen { border: 1px solid rgba(9,212,240,.5); border-radius: 10px; background: rgba(9,212,240,.07); color: var(--vd-cyan); font-weight: 700; padding: .6rem 1rem; letter-spacing: .3px; }

/* Cards */
.vd-card { border-radius: 14px; border: 1px solid var(--vd-border); background: linear-gradient(180deg, var(--vd-panel), var(--vd-panel2)); overflow: hidden; }
.vd-card-hd { border-bottom: 1px solid var(--vd-border); padding: .9rem 1.5rem; display: flex; align-items: center; justify-content: space-between; }
.vd-badge { background: var(--vd-cyan); color: #031218; border-radius: 8px; font-weight: 900; padding: .18rem .55rem; font-size: .85rem; min-width: 28px; text-align: center; }
.vd-htext { color: #fff; font-weight: 800; font-size: 1.2rem; margin-left: .6rem; vertical-align: middle; }

/* Navigation */
.vd-back { border: 1px solid var(--vd-border); border-radius: 10px; color: #a4b4d8; text-decoration: none; padding: .55rem 1.1rem; font-weight: 700; }
.vd-back:hover { border-color: var(--vd-cyan); color: var(--vd-cyan); }
.vd-print { background: transparent; border: 1px solid var(--vd-border); border-radius: 10px; color: #a4b4d8; padding: .55rem 1.1rem; font-weight: 700; cursor: pointer; }
.vd-print:hover { border-color: var(--vd-cyan); color: var(--vd-cyan); }

/* Header info grid */
.vd-info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.4rem 1.8rem; }
.vd-info-label { color: var(--vd-soft); font-size: .72rem; text-transform: uppercase; letter-spacing: 1.2px; font-weight: 700; margin-bottom: .25rem; }
[data-bs-theme="dark"] .vd-info-value { color: #fff; }
[data-bs-theme="light"] .vd-info-value { color: #212529; }
.vd-info-value { color: var(--vd-white); font-weight: 700; font-size: 1rem; }

/* Deposit amount highlight */
.vd-dep-box { border: 1px solid rgba(9,212,240,.55); border-radius: 12px; padding: .7rem 1.4rem; text-align: center; background: rgba(9,212,240,.06); }
.vd-dep-box .cap { color: var(--vd-soft); font-size: .72rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.vd-dep-box .amt { color: var(--vd-cyan); font-size: 2rem; font-weight: 900; line-height: 1.2; }

.vd-by-box  { border: 1px solid rgba(62,207,91,.45); border-radius: 12px; padding: .7rem 1.4rem; text-align: center; background: rgba(62,207,91,.06); }
.vd-by-box .cap { color: var(--vd-soft); font-size: .72rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; }
.vd-by-box .nm  { color: var(--vd-green); font-size: 1.2rem; font-weight: 900; line-height: 1.3; }

/* Receipts table */
.vd-table { width: 100%; border-collapse: collapse; }
.vd-table thead th { padding: .9rem 1rem; font-size: .73rem; color: #6d7ea8; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--vd-border); font-weight: 700; white-space: nowrap; background: #0a1020; }
.vd-table tbody td { padding: .82rem 1rem; border-bottom: 1px solid rgba(31,44,80,.75); color: var(--vd-white); vertical-align: middle; }
.vd-table tbody tr:hover td { background: rgba(9,212,240,.04); }
.vd-table tfoot td { padding: .85rem 1rem; background: rgba(9,212,240,.06); font-weight: 900; border-top: 1px solid rgba(9,212,240,.25); }

.vd-rcpt-num { color: var(--vd-cyan); font-weight: 800; }
.vd-true-badge { display: inline-block; background: rgba(62,207,91,.2); color: var(--vd-green); border: 1px solid rgba(62,207,91,.4); border-radius: 6px; padding: .15rem .55rem; font-weight: 800; font-size: .82rem; }
.vd-amt-cell { font-weight: 800; text-align: right; }

/* Warning bar */
.vd-warn { background: rgba(255,191,69,.1); border-left: 3px solid rgba(255,191,69,.65); color: var(--vd-gold); border-radius: 6px; padding: .65rem 1rem; font-weight: 600; }

/* Footer */
.vd-foot { background: rgba(255,191,69,.07); border-radius: 10px; border: 1px solid rgba(255,191,69,.2); padding: .75rem 1.1rem; color: var(--vd-gold); font-weight: 600; }

@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
    .vd-card { border: 1px solid #ccc !important; background: #fff !important; }
    .vd-info-value, .vd-table tbody td, .vd-htext { color: #000 !important; }
    .vd-info-label, .vd-table thead th { color: #555 !important; }
    .vd-dep-box .amt { color: #006a7a !important; }
}
@media (max-width: 992px) {
    .vd-info-grid { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 600px) {
    .vd-info-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="vd-screen mb-4 no-print">
        <i class="fas fa-circle me-2" style="font-size:.5rem;"></i>
        SCREEN: Banking / Deposits — View Deposit Record
    </div>

    <!-- Page header -->
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
        <div>
            <h1 class="h2 fw-bold text-white mb-1">
                <i class="fas fa-university me-2" style="color:#d0daf5;"></i>
                Deposit: <span style="color:var(--vd-cyan);"><?= escape_html($ref) ?></span>
            </h1>
            <p class="mb-0"><?= (int)$receipt_count ?> receipt<?= $receipt_count !== 1 ? 's' : '' ?> consolidated into this bank deposit.</p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button class="vd-print" onclick="window.print()"><i class="fas fa-print me-1"></i>Print</button>
            <a href="banking_deposits.php" class="vd-back"><i class="fas fa-arrow-left me-1"></i>Back to Deposits</a>
        </div>
    </div>

    <!-- Deposit header card -->
    <div class="vd-card mb-4">
        <div class="vd-card-hd">
            <div>
                <span class="vd-badge">01</span>
                <span class="vd-htext">Deposit Header</span>
            </div>
            <div class="d-flex gap-3 align-items-center">
                <!-- Deposit Amount box -->
                <div class="vd-dep-box">
                    <div class="cap">Deposit Amount</div>
                    <div class="amt"><?= format_currency($deposit_total) ?></div>
                </div>
                <!-- Deposit By box -->
                <div class="vd-by-box">
                    <div class="cap">Deposit By</div>
                    <div class="nm"><?= escape_html(strtoupper($dep_by_name)) ?></div>
                </div>
            </div>
        </div>
        <div class="p-4">
            <div class="vd-info-grid">
                <div>
                    <div class="vd-info-label">Date</div>
                    <div class="vd-info-value"><?= escape_html(date('d-M-Y')) ?></div>
                </div>
                <div>
                    <div class="vd-info-label">Banking Date</div>
                    <div class="vd-info-value">
                        <?= $banking_date ? escape_html(date('d-M-Y', strtotime((string)$banking_date))) : '—' ?>
                    </div>
                </div>
                <div>
                    <div class="vd-info-label">Bank</div>
                    <div class="vd-info-value"><?= escape_html($bank_name ?: 'N/A') ?></div>
                </div>
                <div>
                    <div class="vd-info-label">Bank Acct</div>
                    <div class="vd-info-value" style="font-family:monospace;"><?= escape_html($acct_display) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Selected receipts -->
    <div class="vd-card mb-4">
        <div class="vd-card-hd">
            <div>
                <span class="vd-badge">02</span>
                <span class="vd-htext">Banked Receipts</span>
                <span class="badge rounded-pill ms-2" style="background:rgba(9,212,240,.2);color:var(--vd-cyan);font-size:.82rem;"><?= $receipt_count ?></span>
            </div>
            <div style="color:var(--vd-soft);font-size:.85rem;">
                <i class="fas fa-info-circle me-1"></i>Only selected (TRUE) receipts are included in this deposit.
            </div>
        </div>
        <div class="table-responsive">
            <table class="vd-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Pending Receipt #</th>
                        <th>Customer</th>
                        <th class="text-end">RCT AMT ($)</th>
                        <th class="text-center">Select</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receipts as $rc): ?>
                    <tr>
                        <td><?= escape_html(format_date((string)$rc['receipt_date'])) ?></td>
                        <td><span class="vd-rcpt-num"><?= escape_html((string)$rc['receipt_number']) ?></span></td>
                        <td><?= escape_html((string)($rc['customer_name'] ?? 'N/A')) ?></td>
                        <td class="vd-amt-cell">
                            $ <?= number_format((float)$rc['amount'], 2) ?>
                        </td>
                        <td class="text-center">
                            <span class="vd-true-badge">✔ TRUE</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end" style="color:#fff;">
                            TOTAL (<?= $receipt_count ?> receipt<?= $receipt_count !== 1 ? 's' : '' ?>)
                        </td>
                        <td class="text-end" style="color:var(--vd-cyan);font-size:1.1rem;">
                            $ <?= number_format($deposit_total, 2) ?>
                        </td>
                        <td class="text-center" style="color:var(--vd-soft);font-size:.8rem;">—</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Immutable warning -->
    <div class="vd-warn mb-4 no-print">
        <i class="fas fa-lock me-2"></i>Banking once saved cannot be amended. This deposit record is locked.
    </div>

    <div class="vd-foot no-print">
        <i class="fas fa-shield-alt me-2"></i>
        Deposit Reference: <strong style="color:#fff;"><?= escape_html($ref) ?></strong>
        &nbsp;|&nbsp; Total: <strong style="color:#fff;"><?= format_currency($deposit_total) ?></strong>
        &nbsp;|&nbsp; <?= (int)$receipt_count ?> receipt<?= $receipt_count !== 1 ? 's' : '' ?> banked
        &nbsp;|&nbsp; Bank: <strong style="color:#fff;"><?= escape_html($bank_name ?: 'N/A') ?></strong>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
