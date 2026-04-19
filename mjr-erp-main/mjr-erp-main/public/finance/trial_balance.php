<?php
/**
 * Trial Balance
 * Shows debit and credit balances for all accounts
 * Modern MJR Group ERP Aesthetic
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Trial Balance - MJR Group ERP';

// Get date parameter
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');
$company_id = $_GET['company_id'] ?? ($_SESSION['company_id'] ?? '');

$where_gl = ["gl.transaction_date <= ?"];
$params = [$as_of_date];

if ($company_id) {
    $where_gl[] = "gl.company_id = ?";
    $params[] = $company_id;
}
$where_gl_str = implode(' AND ', $where_gl);

// Calculate trial balance
$trial_balance = db_fetch_all("
    SELECT 
        a.id, a.code, a.name, a.account_type,
        COALESCE(SUM(gl.debit), 0) as total_debit,
        COALESCE(SUM(gl.credit), 0) as total_credit,
        COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id AND $where_gl_str
    WHERE a.is_active = 1
    GROUP BY a.id, a.code, a.name, a.account_type
    ORDER BY a.account_type, a.code
", $params);

// Get all active companies for the filter
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Calculate totals
$total_debit = 0;
$total_credit = 0;

foreach ($trial_balance as $account) {
    if ($account['balance'] > 0) {
        $total_debit += $account['balance'];
    } elseif ($account['balance'] < 0) {
        $total_credit += abs($account['balance']);
    }
}

// Group by account type
$grouped_accounts = [];
foreach ($trial_balance as $account) {
    $type = $account['account_type'];
    if (!isset($grouped_accounts[$type])) {
        $grouped_accounts[$type] = [];
    }
    $grouped_accounts[$type][] = $account;
}

$is_balanced = abs($total_debit - $total_credit) < 0.01;

include __DIR__ . '/../../templates/header.php';
?>

<style>
    html[data-bs-theme="dark"],
    html[data-app-theme="dark"] {
        --mjr-deep-bg: #1a1a24;
        --mjr-card-bg: #222230;
        --mjr-border: rgba(255, 255, 255, 0.05);
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
        --mjr-text: #b0b0c0;
        --mjr-text-header: #fff;
    }

    html[data-bs-theme="light"],
    html[data-app-theme="light"] {
        --mjr-deep-bg: #f8f9fa;
        --mjr-card-bg: #ffffff;
        --mjr-border: rgba(0, 0, 0, 0.05);
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
        --mjr-text: #6c757d;
        --mjr-text-header: #212529;
    }

    body { background-color: var(--mjr-deep-bg); color: var(--mjr-text); }
    
    .card { background-color: var(--mjr-card-bg); border: 1px solid var(--mjr-border); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.5); border-bottom: 1px solid var(--mjr-border); padding: 1.25rem 1.5rem; }

    .stat-card-tb {
        border: none;
        padding: 1.5rem;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .stat-card-tb i {
        position: absolute;
        right: -5px;
        bottom: -5px;
        font-size: 3rem;
        opacity: 0.1;
    }

    .table-premium { --bs-table-bg: transparent; }
    .table-premium th { 
        color: var(--mjr-text); 
        font-weight: 600; 
        font-size: 0.75rem; 
        text-transform: uppercase; 
        letter-spacing: 1.5px; 
        border-bottom: 1px solid var(--mjr-border);
        padding: 1.25rem 1rem; 
    }
    .table-premium td { 
        padding: 1rem; 
        border-bottom: 1px solid var(--mjr-border); 
        color: var(--mjr-text-header); 
        vertical-align: middle; 
    }
    .table-premium tr:hover { background-color: rgba(255,255,255,0.02); }

    .report-section-header {
        background-color: rgba(255, 255, 255, 0.02);
        font-weight: 700;
        letter-spacing: 2px;
        font-size: 0.8rem;
    }

    .font-monospace { font-family: 'JetBrains Mono', 'Fira Code', monospace!important; }

    .report-action-btn {
        border-radius: 999px;
        padding: 0.7rem 1.25rem;
        font-weight: 700;
        border: 1px solid var(--mjr-border);
        background: var(--mjr-card-bg);
        color: var(--mjr-text-header);
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
    }

    .report-action-btn:hover {
        color: var(--mjr-text-header);
        border-color: var(--mjr-primary);
        background: color-mix(in srgb, var(--mjr-card-bg) 88%, var(--mjr-primary) 12%);
    }

    @media print {
        .d-print-none { display: none!important; }
        body { background: white!important; color: black!important; }
        .card { border: 1px solid #ddd!important; }
        .table-premium td, .table-premium th { color: black!important; border-bottom: 1px solid #ddd!important; }
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 mt-2">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white"><i class="fas fa-scale-balanced me-3" style="color: var(--mjr-primary);"></i>Trial Balance</h1>
            <p class="text-muted mb-0">Entity Ledger Summary &mdash; Consolidated as of <?= format_date($as_of_date) ?></p>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button onclick="window.print()" class="btn report-action-btn">
                <i class="fas fa-print me-2"></i>Generate Report
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 mb-4 d-print-none shadow-sm">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Snapshot Date</label>
                    <input type="date" name="as_of_date" class="form-control bg-dark border-0 text-white" value="<?= escape_html($as_of_date) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Unit / Subsidiary</label>
                    <select name="company_id" class="form-select bg-dark border-0 text-white">
                        <option value="">Full Group Coverage</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $company_id == $co['id'] ? 'selected' : '' ?>>
                            <?= escape_html($co['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Executive Review</button>
                </div>
                <div class="col-md-3 ms-auto">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Live Search</label>
                    <input type="text" id="tbSearch" class="form-control bg-dark border-0 text-white" placeholder="Filter ledgers...">
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Indicators -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="stat-card-tb bg-info bg-opacity-10 border border-info border-opacity-25 text-info">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Aggregate Debits</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_debit) ?></div>
                <i class="fas fa-plus-circle"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-tb bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Aggregate Credits</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_credit) ?></div>
                <i class="fas fa-minus-circle"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-tb bg-<?= $is_balanced ? 'success' : 'danger' ?> bg-opacity-10 border border-<?= $is_balanced ? 'success' : 'danger' ?> border-opacity-25 text-<?= $is_balanced ? 'success' : 'danger' ?>">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Tally Status</small>
                <div class="fs-2 fw-bold mt-1"><?= $is_balanced ? 'RECONCILED' : 'DISCREPANCY' ?></div>
                <i class="fas fa-check-double"></i>
            </div>
        </div>
    </div>

    <!-- Detailed Ledger Statement -->
    <div class="card border-0 shadow-lg mb-5 overflow-hidden">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white fw-bold">Consolidated Activity Statement</h5>
            <span class="badge rounded-pill bg-dark py-2 px-3 border border-secondary border-opacity-25 opacity-75">Verification Mode</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Ledger Code</th>
                            <th>Account Designation</th>
                            <th class="text-end">Debit Balance</th>
                            <th class="text-end pe-4">Credit Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $type): ?>
                            <?php if (isset($grouped_accounts[$type])): ?>
                                <tr class="report-section-header">
                                    <td colspan="4" class="ps-4 py-3 text-info text-uppercase letter-spacing-2 small fw-bold">
                                        <?= $type ?> Accounts Portfolio
                                    </td>
                                </tr>
                                <?php foreach ($grouped_accounts[$type] as $account): ?>
                                <tr class="tb-row">
                                    <td class="ps-4">
                                        <span class="badge bg-dark border border-secondary border-opacity-25 text-muted fw-bold font-monospace"><?= $account['code'] ?></span>
                                    </td>
                                    <td class="text-white fw-bold"><?= $account['name'] ?></td>
                                    <td class="text-end font-monospace text-info">
                                        <?= $account['balance'] > 0 ? format_currency($account['balance']) : '<span class="opacity-10">&ndash;</span>' ?>
                                    </td>
                                    <td class="text-end pe-4 font-monospace text-warning">
                                        <?= $account['balance'] < 0 ? format_currency(abs($account['balance'])) : '<span class="opacity-10">&ndash;</span>' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if (empty($trial_balance)): ?>
                        <tr><td colspan="4" class="text-center py-5 text-muted">No accounting activity detected for the specified period.</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: rgba(255,255,255,0.03); border-top: 2px solid rgba(255,255,255,0.1);">
                            <th colspan="2" class="text-end text-white py-4 pe-4 text-uppercase letter-spacing-1 opacity-75">Tally Aggregates</th>
                            <th class="text-end font-monospace fs-4 text-info py-4"><?= format_currency($total_debit) ?></th>
                            <th class="text-end pe-4 font-monospace fs-4 text-warning py-4"><?= format_currency($total_credit) ?></th>
                        </tr>
                        <?php if (!$is_balanced): ?>
                        <tr style="background: rgba(255, 82, 82, 0.1); border-top: 4px double var(--mjr-danger);">
                            <th colspan="2" class="text-end text-danger py-3 pe-4 fw-black">UNBALANCED VARIANCE</th>
                            <th colspan="2" class="text-end pe-4 font-monospace fs-3 text-danger py-3"><?= format_currency(abs($total_debit - $total_credit)) ?></th>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('tbSearch').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.tb-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
