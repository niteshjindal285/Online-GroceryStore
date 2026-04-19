<?php
/**
 * Cash Flow Statement
 * Shows operating, investing, and financing activities for a period
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Cash Flow Statement - MJR Group ERP';

// Date and Company parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');
$company_id = $_GET['company_id'] ?? ($_SESSION['company_id'] ?? '');

$where_company = "";
$params_base = [$date_from, $date_to];
if ($company_id) {
    $where_company = " AND gl.company_id = ? ";
    $params_base[] = $company_id;
}

// ─── Operating Activities ─────────────────────────────────────────────────────
// Revenue accounts (cash inflows from operations)
$operating_inflows = db_fetch_all("
    SELECT a.code, a.name,
           COALESCE(SUM(gl.credit) - SUM(gl.debit), 0) AS amount
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id
        AND gl.transaction_date BETWEEN ? AND ? $where_company
    WHERE a.account_type = 'revenue' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    HAVING ABS(COALESCE(SUM(gl.credit) - SUM(gl.debit), 0)) > 0.001
    ORDER BY a.code
", $params_base);

// Expense accounts (cash outflows from operations)
$operating_outflows = db_fetch_all("
    SELECT a.code, a.name,
           COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) AS amount
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id
        AND gl.transaction_date BETWEEN ? AND ? $where_company
    WHERE a.account_type = 'expense' AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    HAVING ABS(COALESCE(SUM(gl.debit) - SUM(gl.credit), 0)) > 0.001
    ORDER BY a.code
", $params_base);

// ─── Investing Activities ─────────────────────────────────────────────────────
// Asset accounts that are NOT current (long-term assets)
$investing_activities = db_fetch_all("
    SELECT a.code, a.name,
           COALESCE(SUM(gl.debit) - SUM(gl.credit), 0) AS amount
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id
        AND gl.transaction_date BETWEEN ? AND ? $where_company
    WHERE a.account_type = 'asset'
      AND a.is_active = 1
      AND a.level > 1
      AND (a.name LIKE '%fixed%' OR a.name LIKE '%equipment%'
           OR a.name LIKE '%property%' OR a.name LIKE '%investment%'
           OR a.name LIKE '%machine%')
    GROUP BY a.id, a.code, a.name
    HAVING ABS(COALESCE(SUM(gl.debit) - SUM(gl.credit), 0)) > 0.001
    ORDER BY a.code
", $params_base);

// ─── Financing Activities ─────────────────────────────────────────────────────
$financing_activities = db_fetch_all("
    SELECT a.code, a.name,
           COALESCE(SUM(gl.credit) - SUM(gl.debit), 0) AS amount
    FROM accounts a
    LEFT JOIN general_ledger gl ON a.id = gl.account_id
        AND gl.transaction_date BETWEEN ? AND ? $where_company
    WHERE a.account_type IN ('liability', 'equity')
      AND a.is_active = 1
    GROUP BY a.id, a.code, a.name
    HAVING ABS(COALESCE(SUM(gl.credit) - SUM(gl.debit), 0)) > 0.001
    ORDER BY a.code
", [$date_from, $date_to]);

// ─── Totals ───────────────────────────────────────────────────────────────────
$total_inflows     = array_sum(array_column($operating_inflows,  'amount'));
$total_outflows    = array_sum(array_column($operating_outflows, 'amount'));
$net_operating     = $total_inflows - $total_outflows;

$net_investing     = -array_sum(array_column($investing_activities,  'amount'));
$net_financing     =  array_sum(array_column($financing_activities, 'amount'));

$net_cash_change   = $net_operating + $net_investing + $net_financing;

// Get all active companies for information and filtering
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-water me-2 text-primary"></i>Cash Flow Statement</h1>
            <p class="text-muted mb-0">Summary of cash inflows and outflows from <?= format_date($date_from) ?> to <?= format_date($date_to) ?></p>
        </div>
        <div class="d-print-none">
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>


    <!-- Filters -->
    <div class="card mb-4 d-print-none">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        <input type="date" name="date_from" class="form-control" value="<?= escape_html($date_from) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                        <input type="date" name="date_to" class="form-control" value="<?= escape_html($date_to) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $company_id == $co['id'] ? 'selected' : '' ?>>
                            <?= escape_html($co['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                    <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card border-0 bg-info text-white shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75">Operating</small><div class="fs-5 fw-bold"><?= format_currency($net_operating) ?></div></div>
                        <i class="fas fa-running fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-warning text-dark shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small>Investing</small><div class="fs-5 fw-bold"><?= format_currency($net_investing) ?></div></div>
                        <i class="fas fa-seedling fa-2x opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-primary text-white shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75">Financing</small><div class="fs-5 fw-bold"><?= format_currency($net_financing) ?></div></div>
                        <i class="fas fa-hands-holding-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-<?= $net_cash_change >= 0 ? 'success' : 'danger' ?> text-white shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75">Net Change</small><div class="fs-5 fw-bold"><?= format_currency($net_cash_change) ?></div></div>
                        <i class="fas fa-coins fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statement Report -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Detailed Cash Flow</h5>
            <span class="badge bg-secondary"><?= format_date($date_from) ?> &mdash; <?= format_date($date_to) ?></span>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    
                    <!-- OPERATING -->
                    <thead>
                        <tr style="background-color: rgba(13, 202, 240, 0.05);">
                            <th colspan="2" class="ps-4 border-0">
                                <h6 class="mb-0" style="color: #0dcaf0; letter-spacing: 2px;"><i class="fas fa-cogs me-2"></i> OPERATING ACTIVITIES</h6>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="2" class="ps-4 border-0 pb-0" style="color: #8e8e9e; font-size: 0.8rem; text-transform: uppercase;">Cash Inflows (Revenue)</td></tr>
                        <?php foreach ($operating_inflows as $row): ?>
                        <tr class="statement-row">
                            <td class="ps-4">
                                <span class="badge me-2" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($row['code']) ?></span>
                                <span style="color: #fff;"><?= escape_html($row['name']) ?></span>
                            </td>
                            <td class="text-end pe-4 font-monospace" style="color: #3cc553; width: 250px;">
                                <?= format_currency($row['amount']) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr><td colspan="2" class="ps-4 border-0 pb-0 pt-4" style="color: #8e8e9e; font-size: 0.8rem; text-transform: uppercase;">Cash Outflows (Expenses)</td></tr>
                        <?php foreach ($operating_outflows as $row): ?>
                        <tr class="statement-row">
                            <td class="ps-4">
                                <span class="badge me-2" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($row['code']) ?></span>
                                <span style="color: #fff;"><?= escape_html($row['name']) ?></span>
                            </td>
                            <td class="text-end pe-4 font-monospace" style="color: #ff5252; width: 250px;">
                                (<?= format_currency($row['amount']) ?>)
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <tr style="background: rgba(13, 202, 240, 0.05); border-top: 2px solid rgba(13, 202, 240, 0.3);">
                            <td class="ps-4 border-0"><strong style="color: #0dcaf0;">Net Cash from Operating Activities</strong></td>
                            <td class="text-end pe-4 font-monospace border-0 fs-5"><strong style="color: #0dcaf0;"><?= format_currency($net_operating) ?></strong></td>
                        </tr>
                    </tbody>

                    <!-- SPACER -->
                    <tbody>
                        <tr><td colspan="2" class="border-0 p-3" style="background-color: #1a1a24;"></td></tr>
                    </tbody>

                    <!-- INVESTING -->
                    <thead>
                        <tr style="background-color: rgba(255, 146, 43, 0.05);">
                            <th colspan="2" class="ps-4 border-0">
                                <h6 class="mb-0" style="color: #ff922b; letter-spacing: 2px;"><i class="fas fa-chart-bar me-2"></i> INVESTING ACTIVITIES</h6>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($investing_activities)): ?>
                            <?php foreach ($investing_activities as $row): ?>
                            <tr class="statement-row">
                                <td class="ps-4">
                                    <span class="badge me-2" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($row['code']) ?></span>
                                    <span style="color: #fff;"><?= escape_html($row['name']) ?></span>
                                </td>
                                <td class="text-end pe-4 font-monospace" style="color: #b0b0c0; width: 250px;">
                                    <?= format_currency($row['amount']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted border-0">No investing activities in this period.</td>
                            </tr>
                        <?php endif; ?>
                        <tr style="background: rgba(255, 146, 43, 0.05); border-top: 2px solid rgba(255, 146, 43, 0.3);">
                            <td class="ps-4 border-0"><strong style="color: #ff922b;">Net Cash from Investing Activities</strong></td>
                            <td class="text-end pe-4 font-monospace border-0 fs-5"><strong style="color: #ff922b;"><?= format_currency($net_investing) ?></strong></td>
                        </tr>
                    </tbody>

                    <!-- SPACER -->
                    <tbody>
                        <tr><td colspan="2" class="border-0 p-3" style="background-color: #1a1a24;"></td></tr>
                    </tbody>

                    <!-- FINANCING -->
                    <thead>
                        <tr style="background-color: rgba(144, 97, 249, 0.05);">
                            <th colspan="2" class="ps-4 border-0">
                                <h6 class="mb-0" style="color: #9061f9; letter-spacing: 2px;"><i class="fas fa-hand-holding-usd me-2"></i> FINANCING ACTIVITIES</h6>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($financing_activities)): ?>
                            <?php foreach ($financing_activities as $row): ?>
                            <tr class="statement-row">
                                <td class="ps-4">
                                    <span class="badge me-2" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($row['code']) ?></span>
                                    <span style="color: #fff;"><?= escape_html($row['name']) ?></span>
                                </td>
                                <td class="text-end pe-4 font-monospace" style="color: #b0b0c0; width: 250px;">
                                    <?= format_currency($row['amount']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="2" class="text-center py-4 text-muted border-0">No financing activities in this period.</td>
                            </tr>
                        <?php endif; ?>
                        <tr style="background: rgba(144, 97, 249, 0.05); border-top: 2px solid rgba(144, 97, 249, 0.3);">
                            <td class="ps-4 border-0"><strong style="color: #9061f9;">Net Cash from Financing Activities</strong></td>
                            <td class="text-end pe-4 font-monospace border-0 fs-5"><strong style="color: #9061f9;"><?= format_currency($net_financing) ?></strong></td>
                        </tr>
                    </tbody>

                    <!-- SPACER -->
                    <tbody>
                        <tr><td colspan="2" class="border-0 p-3" style="background-color: #1a1a24;"></td></tr>
                    </tbody>

                    <!-- NET CHANGE -->
                    <tfoot>
                        <?php 
                        $status_color = $net_cash_change >= 0 ? '#3cc553' : '#ff5252';
                        $status_bg = $net_cash_change >= 0 ? 'rgba(60, 197, 83, 0.1)' : 'rgba(255, 82, 82, 0.1)';
                        ?>
                        <tr style="background-color: <?= $status_bg ?>; border-top: 2px solid <?= $status_color ?>;">
                            <th class="ps-4 border-0"><h4 class="mb-0 text-white">NET CHANGE IN CASH</h4></th>
                            <th class="text-end pe-4 font-monospace fs-3 border-0"><strong style="color: <?= $status_color ?>;"><?= format_currency($net_cash_change) ?></strong></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    nav,.navbar,.sidebar,.btn,form,.no-print,.statement-section-header{display:none!important}
    body{background:#fff!important;color:#000!important;font-size:10pt}
    .card{border:none!important; margin-bottom: 20px;}
    table{color:#000!important;border-collapse:collapse!important; width: 100%;}
    th,td{border:1px solid #ccc!important;padding:6px 12px!important;color:#000!important; text-align: left;}
    .badge{border:1px solid #ccc;color:#000!important;background:none!important}
    .print-header{display:block!important; text-align:center; margin-bottom: 20px;}
}
.print-header { display: none; }
</style>

<div class="print-header">
    <h2 style="margin-bottom: 5px;"><strong>MJR Group ERP &mdash; Cash Flow Statement</strong></h2>
    <p>Period: <?= format_date($date_from) ?> to <?= format_date($date_to) ?></p>
    <hr style="margin-bottom: 20px;">
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
