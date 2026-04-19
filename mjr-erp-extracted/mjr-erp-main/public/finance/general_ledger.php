<?php
/**
 * General Ledger
 * View all financial transactions and account balances
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'General Ledger - MJR Group ERP';
$company_id = $_GET['company_id'] ?? active_company_id();
// Get all active companies for information and filtering
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

if (!$company_id && !is_super_admin()) {
    set_flash('Please select a company to view the general ledger.', 'warning');
    redirect(url('index.php'));
}

// Get filter parameters
$account_id = $_GET['account'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$cost_center_id = $_GET['cost_center'] ?? '';
$project_id = $_GET['project'] ?? '';

// Build query
$where = ["1=1"];
$params = [];

if ($account_id) {
    $where[] = "gl.account_id = ?";
    $params[] = $account_id;
}

if ($date_from) {
    $where[] = "gl.transaction_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where[] = "gl.transaction_date <= ?";
    $params[] = $date_to;
}

if ($cost_center_id) {
    $where[] = "gl.cost_center_id = ?";
    $params[] = $cost_center_id;
}

if ($project_id) {
    $where[] = "gl.project_id = ?";
    $params[] = $project_id;
}

$where[] = "gl.company_id = ?";
$params[] = $company_id;
// db_where_company helper could be used here too, but since we already have $where array:

$where_sql = implode(' AND ', $where);

// Get ledger entries
$entries = db_fetch_all("
    SELECT gl.*, a.code as account_code, a.name as account_name, a.account_type,
           cc.name as cc_name, p.name as p_name, comp.name as comp_name
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    LEFT JOIN cost_centers cc ON gl.cost_center_id = cc.id
    LEFT JOIN projects p ON gl.project_id = p.id
    LEFT JOIN companies comp ON gl.company_id = comp.id
    WHERE $where_sql
    ORDER BY gl.transaction_date DESC, gl.id DESC
    LIMIT 500
", $params);

// Calculate totals
$totals = db_fetch("
    SELECT 
        SUM(debit) as total_debit,
        SUM(credit) as total_credit
    FROM general_ledger gl
    WHERE $where_sql
", $params);

// Get all accounts for filter
$accounts = db_fetch_all("SELECT id, code, name FROM accounts WHERE is_active = 1 ORDER BY code");
// Fetch additional filters
$cost_centers = db_fetch_all("SELECT id, code, name FROM cost_centers WHERE is_active = 1 ORDER BY name");
$projects = db_fetch_all("SELECT id, code, name FROM projects WHERE is_active = 1 ORDER BY name");

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-book me-2 text-primary"></i>General Ledger</h1>
            <p class="text-muted mb-0">Financial transactions and account activity for <?= escape_html($entries[0]['comp_name'] ?? 'Selected Company') ?></p>
        </div>
        <div class="d-print-none">
            <button onclick="window.print()" class="btn btn-outline-secondary">
                <i class="fas fa-print me-2"></i>Print Ledger
            </button>
        </div>
    </div>


    <!-- Summary Metrics -->
    <!-- Summary Stats -->
    <div class="row g-3 mb-4 text-white">
        <div class="col-sm-4">
            <div class="card border-0 bg-info shadow-sm">
                <div class="card-body py-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-white">Total Debits</small><div class="fs-4 fw-bold"><?= format_currency($totals['total_debit'] ?? 0) ?></div></div>
                        <i class="fas fa-plus-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 bg-success shadow-sm">
                <div class="card-body py-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-white">Total Credits</small><div class="fs-4 fw-bold"><?= format_currency($totals['total_credit'] ?? 0) ?></div></div>
                        <i class="fas fa-minus-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <?php $net_bal = ($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0); ?>
            <div class="card border-0 bg-primary shadow-sm">
                <div class="card-body py-3 text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-white">Net Balance</small><div class="fs-4 fw-bold"><?= format_currency($net_bal) ?></div></div>
                        <i class="fas fa-scale-balanced fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 d-print-none">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="account" class="form-select form-select-sm">
                        <option value="">All Accounts</option>
                        <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>" <?= $account_id == $acc['id'] ? 'selected' : '' ?>>
                            <?= escape_html($acc['code'] . ' - ' . $acc['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="company_id" class="form-select form-select-sm">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= $co['id'] ?>" <?= $company_id == $co['id'] ? 'selected' : '' ?>>
                            <?= escape_html($co['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?= escape_html($date_from) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?= escape_html($date_to) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="?" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Transaction Ledger</h5>
            <span class="badge bg-secondary"><?= count($entries) ?> Records</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($entries)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="ledgerTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Account/Company</th>
                            <th>Info</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end pe-4">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td class="ps-4"><?= format_date($entry['transaction_date']) ?></td>
                            <td>
                                <span class="badge mb-1" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($entry['comp_name'] ?? 'Main') ?></span><br>
                                <strong style="color: #0dcaf0; font-size: 0.9rem;"><?= escape_html($entry['account_code']) ?></strong><br>
                                <span><?= escape_html($entry['account_name']) ?></span>
                            </td>
                            <td>
                                <?php if ($entry['cc_name'] || $entry['p_name']): ?>
                                    <div style="font-size: 0.85rem;"><i class="fas fa-bullseye me-1 text-muted"></i> <span style="color: #b0b0c0;"><?= escape_html($entry['cc_name'] ?? 'No CC') ?></span></div>
                                    <div style="font-size: 0.85rem;"><i class="fas fa-project-diagram me-1 text-muted"></i> <span style="color: #8e8e9e;"><?= escape_html($entry['p_name'] ?? 'No Project') ?></span></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span style="color: #b0b0c0;"><?= escape_html($entry['description']) ?></span></td>
                            <td>
                                <?php if ($entry['reference_type']): ?>
                                    <span class="badge" style="background: rgba(255,146,43,0.1); color: #ff922b; border: 1px solid rgba(255,146,43,0.2);">
                                        <?= escape_html($entry['reference_type'] . ' #' . $entry['reference_id']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace">
                                <?php if ($entry['debit'] > 0): ?>
                                    <span style="color: #ff922b;"><?= format_currency($entry['debit']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace">
                                <?php if ($entry['credit'] > 0): ?>
                                    <span style="color: #0dcaf0;"><?= format_currency($entry['credit']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace pe-4">
                                <strong style="color: #9061f9;"><?= format_currency($entry['balance']) ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="5" class="text-end" style="color: #8e8e9e;"><strong>TOTALS:</strong></th>
                            <th class="text-end font-monospace fs-5" style="color: #ff922b; background: rgba(255,146,43,0.05); border-left: 1px solid rgba(255,255,255,0.05); border-bottom: none;"><strong><?= format_currency($totals['total_debit'] ?? 0) ?></strong></th>
                            <th class="text-end font-monospace fs-5" style="color: #0dcaf0; background: rgba(13,202,240,0.05); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: none;"><strong><?= format_currency($totals['total_credit'] ?? 0) ?></strong></th>
                            <th class="text-end font-monospace fs-5 pe-4" style="color: #9061f9; border-bottom: none;"><strong><?= format_currency(($totals['total_debit'] ?? 0) - ($totals['total_credit'] ?? 0)) ?></strong></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-book-open fa-3x mb-3" style="color: #333344;"></i>
                <h5 class="text-white">No entries found</h5>
                <p class="text-muted">Adjust your filters to see ledger transactions</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    nav,.navbar,.sidebar,.btn,.no-print{display:none!important}
    body{background:#fff!important;color:#000!important;font-size:10pt}
    .card{border:1px solid #ccc!important; margin-bottom: 20px;}
    table{color:#000!important;border-collapse:collapse!important; width: 100%;}
    th,td{border:1px solid #ccc!important;padding:4px 8px!important;color:#000!important; text-align: left;}
    .badge{border:1px solid #ccc;color:#000!important;background:none!important}
}
</style>

<script>
$(document).ready(function() {
    <?php if (!empty($entries)): ?>
    $('#ledgerTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        language: {
            search: "",
            searchPlaceholder: "Search ledger...",
            lengthMenu: "Show _MENU_"
        },
        dom: "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>"
    });
    
    $('.dataTables_filter input').css('width', '250px');
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
