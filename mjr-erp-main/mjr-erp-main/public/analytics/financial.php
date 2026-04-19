<?php
/**
 * Financial Analytics
 * Financial performance analysis and metrics
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Financial Analytics - MJR Group ERP';

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');

// Quick presets
$preset = $_GET['preset'] ?? '';
if ($preset === 'last_month') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to   = date('Y-m-t',  strtotime('last day of last month'));
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to   = date('Y-12-31');
} elseif ($preset === 'last_year') {
    $date_from = date('Y-01-01', strtotime('-1 year'));
    $date_to   = date('Y-12-31', strtotime('-1 year'));
}

// Revenue and expense summary from GL
$revenue_expense = db_fetch("
    SELECT
        COALESCE(SUM(CASE WHEN a.account_type = 'revenue' THEN gl.credit - gl.debit ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN a.account_type = 'expense' THEN gl.debit - gl.credit ELSE 0 END), 0) as total_expense
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE gl.transaction_date BETWEEN ? AND ?
", [$date_from, $date_to]);

$net_income = floatval($revenue_expense['total_revenue'] ?? 0) - floatval($revenue_expense['total_expense'] ?? 0);
$gross_margin = floatval($revenue_expense['total_revenue'] ?? 0) > 0
    ? round(($net_income / floatval($revenue_expense['total_revenue'])) * 100, 1)
    : 0;

// Account balances (balance sheet summary)
$account_balances = db_fetch("
    SELECT
        COALESCE(SUM(CASE WHEN a.account_type = 'asset'     THEN gl.debit  - gl.credit ELSE 0 END), 0) as total_assets,
        COALESCE(SUM(CASE WHEN a.account_type = 'liability' THEN gl.credit - gl.debit  ELSE 0 END), 0) as total_liabilities,
        COALESCE(SUM(CASE WHEN a.account_type = 'equity'    THEN gl.credit - gl.debit  ELSE 0 END), 0) as total_equity
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE gl.transaction_date <= ?
", [$date_to]);

// Top revenue accounts
$top_revenue = db_fetch_all("
    SELECT a.code, a.name,
           SUM(gl.credit - gl.debit) as amount
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE a.account_type = 'revenue'
      AND gl.transaction_date BETWEEN ? AND ?
    GROUP BY a.id, a.code, a.name
    ORDER BY amount DESC
    LIMIT 5
", [$date_from, $date_to]);

// Top expense accounts
$top_expenses = db_fetch_all("
    SELECT a.code, a.name,
           SUM(gl.debit - gl.credit) as amount
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE a.account_type = 'expense'
      AND gl.transaction_date BETWEEN ? AND ?
    GROUP BY a.id, a.code, a.name
    ORDER BY amount DESC
    LIMIT 5
", [$date_from, $date_to]);

// Monthly Revenue vs Expenses for bar chart (last 6 months from date_to)
$monthly_gl = db_fetch_all("
    SELECT DATE_FORMAT(gl.transaction_date,'%b %Y') as month_label,
           YEAR(gl.transaction_date)*100+MONTH(gl.transaction_date) as sort_key,
           COALESCE(SUM(CASE WHEN a.account_type='revenue' THEN gl.credit-gl.debit ELSE 0 END),0) as revenue,
           COALESCE(SUM(CASE WHEN a.account_type='expense' THEN gl.debit-gl.credit ELSE 0 END),0) as expense
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE gl.transaction_date >= DATE_SUB(?, INTERVAL 6 MONTH)
      AND gl.transaction_date <= ?
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
", [$date_to, $date_to]);

$fin_month_labels   = json_encode(array_column($monthly_gl, 'month_label'));
$fin_revenue_data   = json_encode(array_column($monthly_gl, 'revenue'));
$fin_expense_data   = json_encode(array_column($monthly_gl, 'expense'));

// Expense breakdown for donut chart
$expense_breakdown = db_fetch_all("
    SELECT a.name,
           SUM(gl.debit - gl.credit) as amount
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE a.account_type = 'expense'
      AND gl.transaction_date BETWEEN ? AND ?
    GROUP BY a.id, a.name
    HAVING amount > 0
    ORDER BY amount DESC
    LIMIT 8
", [$date_from, $date_to]);

$expense_donut_labels = json_encode(array_column($expense_breakdown, 'name'));
$expense_donut_data   = json_encode(array_column($expense_breakdown, 'amount'));

// Cash flow: use GL cash-type accounts (asset accounts with 'cash' or 'bank' in name)
$cash_flow = db_fetch_all("
    SELECT a.name as account_name,
           SUM(gl.debit - gl.credit) as balance
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE a.account_type = 'asset'
      AND (LOWER(a.name) LIKE '%cash%' OR LOWER(a.name) LIKE '%bank%')
      AND gl.transaction_date BETWEEN ? AND ?
    GROUP BY a.id, a.name
    ORDER BY balance DESC
", [$date_from, $date_to]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="fas fa-chart-pie me-2"></i>Financial Analytics</h2>
        <p class="lead mb-0">Financial performance analysis and key metrics</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="dropdown d-inline-block">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="export_dashboard.php?module=financial&format=pdf&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=financial&format=word&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-word text-primary me-2"></i>Export as Word</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=financial&format=excel&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-excel text-success me-2"></i>Export as Excel</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailReportModal"><i class="fas fa-envelope text-info me-2"></i>Email Report</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="export_dashboard.php" method="POST">
                <input type="hidden" name="module" value="financial">
                <input type="hidden" name="format" value="email">
                <input type="hidden" name="date_from" value="<?= escape_html($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= escape_html($date_to) ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Email Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient Email</label>
                        <input type="email" name="email" class="form-control" value="<?= escape_html($_SESSION['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attached Format</label>
                        <select name="email_format" class="form-select">
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel Spreadsheet</option>
                            <option value="word">Word Document</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Date Filter with Presets -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= escape_html($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= escape_html($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync me-2"></i>Update
                    </button>
                </div>
                <div class="col-md-4 d-flex gap-2 flex-wrap">
                    <a href="?preset=this_month" class="btn btn-outline-secondary btn-sm">This Month</a>
                    <a href="?preset=last_month" class="btn btn-outline-secondary btn-sm">Last Month</a>
                    <a href="?preset=this_year"  class="btn btn-outline-secondary btn-sm">This Year</a>
                    <a href="?preset=last_year"  class="btn btn-outline-secondary btn-sm">Last Year</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Financial Summary (Revenue / Expense / Net / Margin) -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Revenue</h6>
                    <h2><?= format_currency($revenue_expense['total_revenue'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Expenses</h6>
                    <h2><?= format_currency($revenue_expense['total_expense'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= $net_income >= 0 ? 'bg-success' : 'bg-danger' ?>">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Net <?= $net_income >= 0 ? 'Income' : 'Loss' ?></h6>
                    <h2><?= format_currency(abs($net_income)) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= $gross_margin >= 0 ? 'bg-info' : 'bg-danger' ?>">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Net Margin</h6>
                    <h2><?= $gross_margin ?>%</h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Balance Sheet Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Assets</h6>
                    <h2><?= format_currency($account_balances['total_assets'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-danger">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Liabilities</h6>
                    <h2><?= format_currency($account_balances['total_liabilities'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Equity</h6>
                    <h2><?= format_currency($account_balances['total_equity'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Revenue vs Expenses by Month</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="revenueExpenseChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Expense Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($expense_breakdown)): ?>
                    <div style="position:relative; height:280px;">
                        <canvas id="expenseDonut"></canvas>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No expense data for this period</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top Revenue Sources -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5><i class="fas fa-arrow-up me-2"></i>Top Revenue Sources</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_revenue)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_revenue as $rev): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= escape_html($rev['code']) ?></small><br>
                                        <?= escape_html($rev['name']) ?>
                                    </td>
                                    <td class="text-end text-success"><?= format_currency($rev['amount']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No revenue data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Expenses -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5><i class="fas fa-arrow-down me-2"></i>Top Expense Accounts</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_expenses)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead><tr><th>Account</th><th class="text-end">Amount</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_expenses as $exp): ?>
                                <tr>
                                    <td>
                                        <small class="text-muted"><?= escape_html($exp['code']) ?></small><br>
                                        <?= escape_html($exp['name']) ?>
                                    </td>
                                    <td class="text-end text-danger"><?= format_currency($exp['amount']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No expense data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cash / Bank Accounts -->
    <?php if (!empty($cash_flow)): ?>
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-money-bill-wave me-2"></i>Cash & Bank Account Balances</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php foreach ($cash_flow as $cash): ?>
                <div class="col-md-3 mb-3">
                    <div class="card <?= floatval($cash['balance']) >= 0 ? 'bg-success' : 'bg-danger' ?>">
                        <div class="card-body text-center">
                            <h6><?= escape_html($cash['account_name']) ?></h6>
                            <h4><?= format_currency($cash['balance']) ?></h4>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Revenue vs Expenses Bar Chart
    const reCtx = document.getElementById('revenueExpenseChart').getContext('2d');
    new Chart(reCtx, {
        type: 'bar',
        data: {
            labels: <?= $fin_month_labels ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?= $fin_revenue_data ?>,
                    backgroundColor: 'rgba(25,135,84,0.8)',
                    borderColor: '#198754', borderWidth: 1
                },
                {
                    label: 'Expenses',
                    data: <?= $fin_expense_data ?>,
                    backgroundColor: 'rgba(255,193,7,0.8)',
                    borderColor: '#ffc107', borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    <?php if (!empty($expense_breakdown)): ?>
    // Expense Breakdown Donut
    const expCtx = document.getElementById('expenseDonut').getContext('2d');
    new Chart(expCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $expense_donut_labels ?>,
            datasets: [{
                data: <?= $expense_donut_data ?>,
                backgroundColor: [
                    '#dc3545','#ffc107','#fd7e14','#6f42c1',
                    '#0dcaf0','#20c997','#0d6efd','#6c757d'
                ]
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
