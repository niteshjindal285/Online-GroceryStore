<?php
/**
 * Income Statement (Profit & Loss Statement)
 * Shows revenues and expenses for a period
 * Modern MJR Group ERP Aesthetic
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Income Statement - MJR Group ERP';

// Get date parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$company_id = $_GET['company_id'] ?? ($_SESSION['company_id'] ?? '');

$where_gl = ["gl.transaction_date BETWEEN ? AND ?"];
$params = [$date_from, $date_to];

if ($company_id) {
    $where_gl[] = "gl.company_id = ?";
    $params[] = $company_id;
}
$where_gl_str = implode(' AND ', $where_gl);

// Get revenue accounts
$revenue_accounts = db_fetch_all("
    SELECT 
        a.id, a.code, a.name,
        COALESCE(SUM(gl.credit) - SUM(gl.debit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id 
        AND $where_gl_str
    WHERE a.account_type = 'revenue' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
", $params);

// Get expense accounts
$expense_accounts = db_fetch_all("
    SELECT 
        a.id, a.code, a.name,
        COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) as balance
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id 
        AND $where_gl_str
    WHERE a.account_type = 'expense' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    ORDER BY a.code
", $params);

// Calculate totals
$total_revenue = array_sum(array_column($revenue_accounts, 'balance'));
$total_expense = array_sum(array_column($expense_accounts, 'balance'));
$net_income_before_tax = $total_revenue - $total_expense;

// Income Tax Deduction (25% of Gross Profit/Net Income Before Tax)
$tax_rate = 0.25;
$income_tax = $net_income_before_tax > 0 ? ($net_income_before_tax * $tax_rate) : 0;
$net_income_after_tax = $net_income_before_tax - $income_tax;

// Get all active companies for the filter
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

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

    .stat-card-report {
        border: none;
        padding: 1.5rem;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .stat-card-report i {
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

    /* Print Overrides */
    @media print {
        body { background: white!important; color: black!important; }
        .card { background: white!important; border: 1px solid #ddd!important; color: black!important; }
        .table-premium td, .table-premium th { color: black!important; border-bottom: 1px solid #ddd!important; }
        .d-print-none { display: none!important; }
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 mt-2">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white"><i class="fas fa-chart-line me-3" style="color: var(--mjr-primary);"></i>Income Statement</h1>
            <p class="text-muted mb-0">Financial Performance: <?= format_date($date_from) ?> &mdash; <?= format_date($date_to) ?></p>
        </div>
        <div class="d-flex gap-2 d-print-none">
            <button onclick="window.print()" class="btn report-action-btn">
                <i class="fas fa-print me-2"></i>Generate PDF
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 mb-4 d-print-none shadow-sm">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">From Date</label>
                    <input type="date" name="date_from" class="form-control bg-dark border-0 text-white" value="<?= escape_html($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">To Date</label>
                    <input type="date" name="date_to" class="form-control bg-dark border-0 text-white" value="<?= escape_html($date_to) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted text-uppercase fw-bold letter-spacing-1">Entity / Subsidiary</label>
                    <select name="company_id" class="form-select bg-dark border-0 text-white">
                        <option value="">Consolidated View</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $company_id == $co['id'] ? 'selected' : '' ?>>
                            <?= escape_html($co['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Execute Analysis</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Metrics Sidebar-style across top -->
    <div class="row g-3 mb-5">
        <div class="col-md-4">
            <div class="stat-card-report bg-info bg-opacity-10 border border-info border-opacity-25 text-info">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Gross Revenue</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_revenue) ?></div>
                <i class="fas fa-arrow-trend-up"></i>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card-report bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Operating Expenses</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($total_expense) ?></div>
                <i class="fas fa-arrow-trend-down"></i>
            </div>
        </div>
        <div class="col-md-4">
            <?php $is_profit = $net_income_after_tax >= 0; ?>
            <div class="stat-card-report bg-<?= $is_profit ? 'success' : 'danger' ?> bg-opacity-10 border border-<?= $is_profit ? 'success' : 'danger' ?> border-opacity-25 text-<?= $is_profit ? 'success' : 'danger' ?>">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Net Performance</small>
                <div class="fs-2 fw-bold mt-1 font-monospace"><?= format_currency($net_income_after_tax) ?></div>
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
    </div>

    <!-- detailed Report -->
    <div class="card border-0 shadow-lg">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white fw-bold">Statement of Profit and Loss</h5>
            <div class="input-group input-group-sm d-print-none" style="width: 250px;">
                <span class="input-group-text bg-dark border-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="reportSearch" class="form-control bg-dark border-0 text-white" placeholder="Filter ledger accounts...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-premium mb-0">
                    <!-- REVENUE SECTION -->
                    <thead>
                        <tr class="report-section-header">
                            <th colspan="2" class="ps-4 border-0 text-info">OPERATING REVENUE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($revenue_accounts)): ?>
                            <?php foreach ($revenue_accounts as $account): ?>
                            <tr class="report-row">
                                <td class="ps-4">
                                    <span class="text-muted font-monospace me-3 small opacity-50"><?= escape_html($account['code']) ?></span>
                                    <span class="fw-bold text-white"><?= escape_html($account['name']) ?></span>
                                </td>
                                <td class="text-end pe-4 font-monospace text-info"><?= format_currency($account['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center py-4 text-muted border-0">No revenue data available for this criteria.</td></tr>
                        <?php endif; ?>
                        <tr style="background: rgba(13, 202, 240, 0.05);">
                            <td class="ps-4 border-0"><strong>Total Revenue</strong></td>
                            <td class="text-end pe-4 font-monospace fs-5 text-info border-0"><strong><?= format_currency($total_revenue) ?></strong></td>
                        </tr>
                    </tbody>

                    <!-- SPACER -->
                    <tbody><tr><td colspan="2" class="p-4 border-0" style="background: var(--mjr-deep-bg);"></td></tr></tbody>

                    <!-- EXPENSE SECTION -->
                    <thead>
                        <tr class="report-section-header">
                            <th colspan="2" class="ps-4 border-0 text-warning">OPERATING EXPENSES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($expense_accounts)): ?>
                            <?php foreach ($expense_accounts as $account): ?>
                            <tr class="report-row">
                                <td class="ps-4">
                                    <span class="text-muted font-monospace me-3 small opacity-50"><?= escape_html($account['code']) ?></span>
                                    <span class="fw-bold text-white"><?= escape_html($account['name']) ?></span>
                                </td>
                                <td class="text-end pe-4 font-monospace text-warning"><?= format_currency($account['balance']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" class="text-center py-4 text-muted border-0">No expense data available for this criteria.</td></tr>
                        <?php endif; ?>
                        <tr style="background: rgba(255, 193, 7, 0.05);">
                            <td class="ps-4 border-0"><strong>Total Expenses</strong></td>
                            <td class="text-end pe-4 font-monospace fs-5 text-warning border-0"><strong><?= format_currency($total_expense) ?></strong></td>
                        </tr>
                    </tbody>

                    <!-- SUMMARY TOTALS -->
                    <tfoot>
                        <tr><td colspan="2" class="p-4 border-0" style="background: var(--mjr-deep-bg);"></td></tr>
                        <tr style="background: rgba(255, 255, 255, 0.02); border-top: 2px solid rgba(255, 255, 255, 0.1);">
                            <th class="ps-4 py-3 border-0">OPERATING INCOME (BEFORE TAX)</th>
                            <th class="text-end pe-4 font-monospace fs-3 border-0" style="color: var(--mjr-primary);"><?= format_currency($net_income_before_tax) ?></th>
                        </tr>
                        <?php if ($net_income_before_tax > 0): ?>
                        <tr style="border-top: 1px solid rgba(255, 255, 255, 0.05);">
                            <td class="ps-4 py-3 border-0 text-muted">Provision for Corporate Tax (25%)</td>
                            <td class="text-end pe-4 font-monospace text-danger border-0">&ndash; <?= format_currency($income_tax) ?></td>
                        </tr>
                        <tr style="background: rgba(60, 197, 83, 0.1); border-top: 4px double var(--mjr-success);">
                            <th class="ps-4 py-4 border-0"><h4 class="mb-0 text-white fw-bold">RETAINED EARNINGS (NET PROFIT)</h4></th>
                            <th class="text-end pe-4 font-monospace fs-2 border-0 text-success fw-bold"><?= format_currency($net_income_after_tax) ?></th>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('reportSearch').addEventListener('keyup', function() {
        let val = this.value.toLowerCase();
        document.querySelectorAll('.report-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
