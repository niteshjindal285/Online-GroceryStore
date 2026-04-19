<?php
/**
 * Budget vs Actual Report
 * Compare budgeted amounts against actual GL totals per account/period
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Budget vs Actual - MJR Group ERP';

// Auto-create budgets table if not exists
try {
    db_query("
        CREATE TABLE IF NOT EXISTS account_budgets (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            account_id  INT NOT NULL,
            period_year INT NOT NULL,
            period_month INT NOT NULL COMMENT '1-12',
            budget_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            created_by  INT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_budget (account_id, period_year, period_month),
            INDEX idx_period (period_year, period_month)
        )
    ");
} catch (Exception $e) { /* table exists */ }

// Handle budget save
if (is_post() && post('action') === 'save_budget') {
    if (verify_csrf_token(post('csrf_token'))) {
        $acc_id = intval(post('account_id', 0));
        $year   = intval(post('period_year', 0));
        $month  = intval(post('period_month', 0));
        $amount = post('budget_amount', '');
        
        $errors = [];
        if (empty($acc_id)) $errors['account_id'] = 'Please fill Account that field';
        if ($amount === '') $errors['budget_amount'] = 'Please fill Budget Amount that field';
        
        if (empty($errors)) {
            $amount = floatval($amount);
            try {
                db_query("
                    INSERT INTO account_budgets (account_id, period_year, period_month, budget_amount, created_by)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE budget_amount = ?, updated_at = NOW()
                ", [$acc_id, $year, $month, $amount, current_user_id(), $amount]);
                set_flash('Budget saved!', 'success');
                redirect("budget_report.php?year={$year}&month={$month}");
            } catch (Exception $e) {
                set_flash('Error: ' . $e->getMessage(), 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
        }
    }
}

$sel_year  = intval($_GET['year'] ?? date('Y'));
$sel_month = intval($_GET['month'] ?? date('m'));
$period_start = sprintf('%04d-%02d-01', $sel_year, $sel_month);
$period_end   = date('Y-m-t', strtotime($period_start));

// Get all active accounts with budget and actual
$rows = db_fetch_all("
    SELECT 
        a.id, a.code, a.name, a.account_type,
        COALESCE(b.budget_amount, 0) AS budget_amount,
        COALESCE(
            (SELECT SUM(gl.debit) - SUM(gl.credit)
             FROM general_ledger gl
             WHERE gl.account_id = a.id
               AND gl.transaction_date BETWEEN ? AND ?
            ), 0
        ) AS actual_amount
    FROM accounts a
    LEFT JOIN account_budgets b ON a.id = b.account_id
        AND b.period_year = ? AND b.period_month = ?
    WHERE a.is_active = 1 AND a.account_type IN ('revenue','expense')
    ORDER BY a.account_type, a.code
", [$period_start, $period_end, $sel_year, $sel_month]);

// For revenue: actual = credits - debits (positive = earned)
foreach ($rows as &$row) {
    if ($row['account_type'] === 'revenue') {
        $row['actual_amount'] = -$row['actual_amount']; // flip for revenue
    }
    $row['variance']        = $row['actual_amount'] - $row['budget_amount'];
    $row['variance_pct']    = $row['budget_amount'] != 0 ? ($row['variance'] / $row['budget_amount']) * 100 : 0;
}
unset($row);

$all_accounts = db_fetch_all("SELECT id, code, name, account_type FROM accounts WHERE is_active=1 AND account_type IN ('revenue','expense') ORDER BY code");

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 10px; }
    
    /* Summary Cards */
    .summary-card { padding: 20px; border-radius: 12px; position: relative; overflow: hidden; }
    .summary-card .icon-bg { position: absolute; right: -10px; bottom: -20px; font-size: 6rem; opacity: 0.05; }
    .summary-value { font-size: 1.8rem; font-weight: 700; color: #fff; margin-bottom: 5px; }
    .summary-label { color: #8e8e9e; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
    
    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); --bs-table-border-color: rgba(255,255,255,0.05); }
    .table-dark th { color: #8e8e9e; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid #333344; padding: 1.25rem 1rem; }
    .table-dark td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; vertical-align: middle; }
    
    /* DataTable overrides */
    div.dataTables_wrapper div.dataTables_processing { background-color: rgba(34,34,48,0.9); color: #fff; }
    .dataTables_wrapper .dataTables_length select, .dataTables_wrapper .dataTables_filter input { 
        background-color: #1a1a24; border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 6px; padding: 6px 10px; 
    }
    .dataTables_wrapper .dataTables_info, .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { color: #8e8e9e!important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button { color: #8e8e9e!important; }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current { background: #0dcaf0!important; color: #000!important; border-color: #0dcaf0!important; }

    .form-control, .form-select { background-color: #1a1a24!important; border-color: rgba(255,255,255,0.1)!important; color: #fff!important; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    
    .btn-action { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.3); font-weight: 600; transition: all 0.2s ease; }
    .btn-action:hover { background-color: rgba(13, 202, 240, 0.2); border-color: rgba(13, 202, 240, 0.4); color: #0dcaf0; }

    .btn-create { background-color: #0dcaf0; color: #000; font-weight: 600; transition: all 0.3s ease; }
    .btn-create:hover { background-color: #0baccc; color: #000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3); }

    /* Modal styling */
    .modal-content { background-color: #222230; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; }
    .modal-header { border-bottom: 1px solid rgba(255,255,255,0.05); }
    .modal-footer { border-top: 1px solid rgba(255,255,255,0.05); }
    .modal-title { color: #fff; font-weight: 600; }
    .btn-close { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.5; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="fas fa-chart-bar me-2" style="color: #ff922b;"></i> Budget vs Actual</h2>
            <p class="text-muted mb-0">Compare budgeted amounts against actual spending by account</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn px-3 py-2 rounded-pill no-print" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                <i class="fas fa-print me-2"></i>Print
            </button>
            <button class="btn btn-create px-4 py-2 rounded-pill no-print" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
                <i class="fas fa-plus me-2"></i>Set Budget
            </button>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="card mb-5 border-0 shadow-sm no-print" style="background: rgba(34, 34, 48, 0.4)!important; border: 1px dashed rgba(255,255,255,0.1)!important;">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 text-muted">
                <div class="col-md-3">
                    <label class="form-label" style="font-size: 0.85rem;">Year</label>
                    <select name="year" class="form-select shadow-none">
                        <?php for ($y = date('Y')-2; $y <= date('Y')+1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $sel_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size: 0.85rem;">Month</label>
                    <select name="month" class="form-select shadow-none">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $sel_month ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-action w-100 py-2">
                        <i class="fas fa-sync-alt me-2"></i>View
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Metrics -->
    <?php
    $rev_rows = array_filter($rows, fn($r) => $r['account_type'] === 'revenue');
    $exp_rows = array_filter($rows, fn($r) => $r['account_type'] === 'expense');
    $total_budget_rev = array_sum(array_column(array_values($rev_rows),'budget_amount'));
    $total_actual_rev = array_sum(array_column(array_values($rev_rows),'actual_amount'));
    $total_budget_exp = array_sum(array_column(array_values($exp_rows),'budget_amount'));
    $total_actual_exp = array_sum(array_column(array_values($exp_rows),'actual_amount'));
    ?>
    <div class="row g-4 mb-5 no-print">
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid #0dcaf0;">
                <i class="fas fa-bullseye icon-bg" style="color: #0dcaf0;"></i>
                <div class="summary-label">Budgeted Rev.</div>
                <div class="summary-value text-white"><?= format_currency($total_budget_rev) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid #3cc553;">
                <i class="fas fa-check-circle icon-bg" style="color: #3cc553;"></i>
                <div class="summary-label">Actual Rev.</div>
                <div class="summary-value text-white"><?= format_currency($total_actual_rev) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid #ff922b;">
                <i class="fas fa-crosshairs icon-bg" style="color: #ff922b;"></i>
                <div class="summary-label">Budgeted Exp.</div>
                <div class="summary-value text-white"><?= format_currency($total_budget_exp) ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid #ff5252;">
                <i class="fas fa-times-circle icon-bg" style="color: #ff5252;"></i>
                <div class="summary-label">Actual Exp.</div>
                <div class="summary-value text-white"><?= format_currency($total_actual_exp) ?></div>
            </div>
        </div>
    </div>

    <!-- Table Report -->
    <div class="card border-0 shadow-sm mb-5" style="overflow: hidden;">
        <div class="card-header d-flex justify-content-between align-items-center" style="background: rgba(34, 34, 48, 0.6); padding: 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
            <h5 class="mb-0 text-white"><i class="fas fa-table me-2" style="color: #8e8e9e;"></i> Activity vs Budget</h5>
            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e; font-size: 0.9rem;"><?= date('F Y', strtotime($period_start)) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="budgetTable">
                    <thead style="background-color: #1a1a24;">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Account Name</th>
                            <th>Type</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Actual</th>
                            <th class="text-end">Variance</th>
                            <th class="text-end pe-4">% Reached</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                        <?php 
                        $var_color = $row['account_type'] === 'revenue'
                            ? ($row['variance'] >= 0 ? '#3cc553' : '#ff5252')
                            : ($row['variance'] <= 0 ? '#3cc553' : '#ff5252');
                        
                        $is_over = $row['account_type'] === 'revenue' ? $row['variance'] < 0 : $row['variance'] > 0;
                        ?>
                        <tr>
                            <td class="ps-4"><strong style="color: #0dcaf0;"><?= escape_html($row['code']) ?></strong></td>
                            <td><?= escape_html($row['name']) ?></td>
                            <td>
                                <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e; border: 1px solid rgba(255,255,255,0.1);">
                                    <?= ucfirst($row['account_type']) ?>
                                </span>
                            </td>
                            <td class="text-end font-monospace"><?= format_currency($row['budget_amount']) ?></td>
                            <td class="text-end font-monospace"><?= format_currency($row['actual_amount']) ?></td>
                            <td class="text-end font-monospace">
                                <span style="background: <?= $is_over ? 'rgba(255, 82, 82, 0.1)' : 'rgba(60, 197, 83, 0.1)' ?>; color: <?= $var_color ?>; padding: 4px 8px; border-radius: 4px;">
                                    <strong><?= format_currency($row['variance']) ?></strong>
                                </span>
                            </td>
                            <td class="text-end pe-4 font-monospace" style="color: <?= $var_color ?>;"><?= number_format($row['variance_pct'], 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            $(document).ready(function() {
                $('#budgetTable').DataTable({ 
                    pageLength: 50, 
                    order: [[2,'asc'],[0,'asc']],
                    language: {
                        search: "",
                        searchPlaceholder: "Search accounts...",
                        lengthMenu: "Show _MENU_"
                    },
                    dom: "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>"
                });
                
                $('.dataTables_filter input').css('width', '250px');
            });
            </script>
        </div>
    </div>
</div>

<!-- Add Budget Modal -->
<div class="modal fade" id="addBudgetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bullseye me-2" style="color: #0dcaf0;"></i> Set Budget Amount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="budgetForm">
                    <input type="hidden" name="action" value="save_budget">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="period_year" value="<?= $sel_year ?>">
                    <input type="hidden" name="period_month" value="<?= $sel_month ?>">
                    
                    <div class="alert mb-4" style="background: rgba(13, 202, 240, 0.1); border: 1px solid rgba(13, 202, 240, 0.2); color: #0dcaf0;">
                        Setting budget for <strong><?= date('F Y', strtotime($period_start)) ?></strong>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-muted">Account <span class="text-danger">*</span></label>
                        <select name="account_id" class="form-select <?= isset($errors['account_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">Select Account...</option>
                            <optgroup label="Revenue">
                                <?php foreach ($all_accounts as $a): if ($a['account_type'] !== 'revenue') continue; ?>
                                <option value="<?= $a['id'] ?>" <?= post('account_id') == $a['id'] ? 'selected' : '' ?>><?= escape_html($a['code'] . ' – ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Expense">
                                <?php foreach ($all_accounts as $a): if ($a['account_type'] !== 'expense') continue; ?>
                                <option value="<?= $a['id'] ?>" <?= post('account_id') == $a['id'] ? 'selected' : '' ?>><?= escape_html($a['code'] . ' – ' . $a['name']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <?php if (isset($errors['account_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['account_id'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted">Budget Amount Limit <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background-color: #1a1a24; border-color: rgba(255,255,255,0.1); color: #8e8e9e;">$</span>
                            <input type="number" name="budget_amount" class="form-control <?= isset($errors['budget_amount']) ? 'is-invalid' : '' ?>" 
                                   step="0.01" min="0" value="<?= escape_html(post('budget_amount')) ?>" required placeholder="0.00" style="border-left: none;">
                        </div>
                        <?php if (isset($errors['budget_amount'])): ?>
                            <div class="invalid-feedback d-block mt-1"><?= $errors['budget_amount'] ?></div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="padding: 1rem 1.5rem;">
                <button type="button" class="btn" data-bs-dismiss="modal" style="color: #8e8e9e;">Cancel</button>
                <button type="button" class="btn btn-create px-4 rounded-pill" onclick="document.getElementById('budgetForm').submit()">
                    <i class="fas fa-save me-1"></i> Save Budget
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    nav,.navbar,.sidebar,.btn,.no-print{display:none!important}
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
    <h2 style="margin-bottom: 5px;"><strong>MJR Group ERP &mdash; Budget vs Actual</strong></h2>
    <p>Period: <?= date('F Y', strtotime($period_start)) ?></p>
    <hr style="margin-bottom: 20px;">
</div>

<script>
$(document).ready(function() {
    <?php if (!empty($errors)): ?>
    var addBudgetModal = new bootstrap.Modal(document.getElementById('addBudgetModal'));
    addBudgetModal.show();
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
