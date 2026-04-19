<?php
/**
 * Balance Sheet
 * Shows assets, liabilities, and equity at a specific date
 * Modern MJR Group ERP Aesthetic
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Balance Sheet - MJR Group ERP';

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

// Get asset accounts
$asset_accounts = db_fetch_all("
    SELECT 
        a.id, a.code, a.name,
        COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id AND $where_gl_str
    WHERE a.account_type = 'asset' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
", $params);

// Get liability accounts
$liability_accounts = db_fetch_all("
    SELECT 
        a.id, a.code, a.name,
        COALESCE(SUM(gl.credit) - SUM(gl.debit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id AND $where_gl_str
    WHERE a.account_type = 'liability' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
", $params);

// Get equity accounts
$equity_accounts = db_fetch_all("
    SELECT 
        a.id, a.code, a.name,
        COALESCE(SUM(gl.credit) - SUM(gl.debit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id AND $where_gl_str
    WHERE a.account_type = 'equity' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
", $params);

// Get all active companies for the filter
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Calculate totals
$total_assets = array_sum(array_column($asset_accounts, 'balance'));
$total_liabilities = array_sum(array_column($liability_accounts, 'balance'));
$total_equity = array_sum(array_column($equity_accounts, 'balance'));
$total_liabilities_equity = $total_liabilities + $total_equity;
$is_balanced = abs($total_assets - $total_liabilities_equity) < 0.01;

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
        --mjr-equity: #9061f9;
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
        --mjr-equity: #9061f9;
        --mjr-text: #6c757d;
        --mjr-text-header: #212529;
    }

    body { background-color: var(--mjr-deep-bg); color: var(--mjr-text); }
    
    .card { background-color: var(--mjr-card-bg); border: 1px solid var(--mjr-border); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.5); border-bottom: 1px solid var(--mjr-border); padding: 1.25rem 1.5rem; }

    .stat-card-bs {
        border: none;
        padding: 1.5rem;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .stat-card-bs i {
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

    .bs-footer {
        padding: 1.5rem;
        border-radius: 0 0 12px 12px;
    }

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
        .card { border: 1px solid #ddd!important; break-inside: avoid; }
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 mt-2">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white"><i class="fas fa-balance-scale me-3" style="color: var(--mjr-primary);"></i>Balance Sheet</h1>
            <p class="text-muted mb-0">Financial Position as of: <?= format_date($as_of_date) ?></p>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button onclick="window.print()" class="btn report-action-btn">
                <i class="fas fa-print me-2"></i>Export Statement
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
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Company / Entity</label>
                    <select name="company_id" class="form-select bg-dark border-0 text-white">
                        <option value="">Consolidated Position</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $company_id == $co['id'] ? 'selected' : '' ?>>
                            <?= escape_html($co['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Update Snapshot</button>
                </div>
                <div class="col-md-3 ms-auto">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Filter Accounts</label>
                    <input type="text" id="bsSearch" class="form-control bg-dark border-0 text-white" placeholder="Search entries...">
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Equations -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="stat-card-bs bg-info bg-opacity-10 border border-info border-opacity-25 text-info">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Total Assets</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_assets) ?></div>
                <i class="fas fa-building"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-bs bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Liabilities & Equity</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_liabilities_equity) ?></div>
                <i class="fas fa-hand-holding-dollar"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-bs bg-<?= $is_balanced ? 'success' : 'danger' ?> bg-opacity-10 border border-<?= $is_balanced ? 'success' : 'danger' ?> border-opacity-25 text-<?= $is_balanced ? 'success' : 'danger' ?>">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Equation Integrity</small>
                <div class="fs-2 fw-bold mt-1"><?= $is_balanced ? 'BALANCED' : 'IMBALANCED' ?></div>
                <i class="fas fa-check-double"></i>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ASSETS -->
        <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-white fw-bold"><i class="fas fa-plus-circle text-info me-2"></i>Assets</h5>
                </div>
                <div class="card-body p-0 d-flex flex-column h-100">
                    <div class="table-responsive flex-grow-1">
                        <table class="table table-premium mb-0">
                            <thead>
                                <tr class="report-section-header">
                                    <th class="ps-4">Designation</th>
                                    <th class="text-end pe-4">Carrying Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($asset_accounts as $account): ?>
                                <tr class="bs-row">
                                    <td class="ps-4">
                                        <small class="text-muted font-monospace me-2"><?= $account['code'] ?></small>
                                        <span class="text-white"><?= $account['name'] ?></span>
                                    </td>
                                    <td class="text-end pe-4 font-monospace text-info"><?= format_currency($account['balance']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($asset_accounts)): ?>
                                <tr><td colspan="2" class="text-center py-5 text-muted small">No asset data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="bs-footer bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
                        <div class="text-info fw-bold text-uppercase letter-spacing-1">Total Assets</div>
                        <div class="fs-4 fw-bold text-white font-monospace"><?= format_currency($total_assets) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LIABILITIES & EQUITY -->
        <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-dark">
                    <h5 class="mb-0 text-white fw-bold"><i class="fas fa-minus-circle text-warning me-2"></i>Liabilities & Equity</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-premium mb-0">
                            <thead>
                                <tr class="report-section-header">
                                    <th class="ps-4 text-warning">Liabilities</th>
                                    <th class="text-end pe-4 text-warning">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liability_accounts as $account): ?>
                                <tr class="bs-row">
                                    <td class="ps-4">
                                        <small class="text-muted font-monospace me-2"><?= $account['code'] ?></small>
                                        <span class="text-white"><?= $account['name'] ?></span>
                                    </td>
                                    <td class="text-end pe-4 font-monospace text-warning"><?= format_currency($account['balance']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: rgba(255,193,7,0.05);">
                                    <td class="ps-4 border-0 text-warning opacity-75 small fw-bold text-uppercase">Total Liabilities</td>
                                    <td class="text-end pe-4 border-0 font-monospace text-warning fw-bold"><?= format_currency($total_liabilities) ?></td>
                                </tr> 

                                <tr class="report-section-header"><th class="ps-4 text-purple" style="color: var(--mjr-equity)!important;">Equity</th><th class="text-end pe-4" style="color: var(--mjr-equity)!important;">Reserves</th></tr>
                                <?php foreach ($equity_accounts as $account): ?>
                                <tr class="bs-row">
                                    <td class="ps-4">
                                        <small class="text-muted font-monospace me-2"><?= $account['code'] ?></small>
                                        <span class="text-white"><?= $account['name'] ?></span>
                                    </td>
                                    <td class="text-end pe-4 font-monospace text-purple" style="color: var(--mjr-equity)!important;"><?= format_currency($account['balance']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background: rgba(144, 97, 249, 0.05);">
                                    <td class="ps-4 border-0 text-purple opacity-75 small fw-bold text-uppercase" style="color: var(--mjr-equity)!important;">Total Shareholders' Equity</td>
                                    <td class="text-end pe-4 border-0 font-monospace text-purple fw-bold" style="color: var(--mjr-equity)!important;"><?= format_currency($total_equity) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="bs-footer bg-warning bg-opacity-10 d-flex justify-content-between align-items-center mt-auto">
                        <div class="text-warning fw-bold text-uppercase letter-spacing-1">Total L&E</div>
                        <div class="fs-4 fw-bold text-white font-monospace"><?= format_currency($total_liabilities_equity) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('bsSearch').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.bs-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
