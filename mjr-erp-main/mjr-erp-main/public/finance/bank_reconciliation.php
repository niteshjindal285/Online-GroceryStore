<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Bank Reconciliation';
$company_id = (int)active_company_id(1);

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

$has_bank_accounts_company = $table_has_column('bank_accounts', 'company_id');
$has_bank_transactions_company = $table_has_column('bank_transactions', 'company_id');
$has_bank_transactions_reconciled = $table_has_column('bank_transactions', 'is_reconciled');

$bank_filter = (int)get('bank_id', 0);
$period_filter = trim((string)get('period', 'all'));
$status_filter = strtolower(trim((string)get('status', 'all')));

$banks_sql = "
    SELECT id, bank_name, account_name
    FROM bank_accounts
    WHERE is_active = 1
";
$banks_params = [];
if ($has_bank_accounts_company) {
    $banks_sql .= " AND company_id = ? ";
    $banks_params[] = $company_id;
}
$banks_sql .= " ORDER BY bank_name, account_name ";
$banks = db_fetch_all($banks_sql, $banks_params) ?: [];

$where = "WHERE 1=1";
$params = [];

if ($has_bank_transactions_company) {
    $where .= " AND t.company_id = ? ";
    $params[] = $company_id;
}

if ($bank_filter > 0) {
    $where .= " AND t.bank_account_id = ? ";
    $params[] = $bank_filter;
}

if ($period_filter !== '' && $period_filter !== 'all' && preg_match('/^\d{4}\-\d{2}$/', $period_filter)) {
    $where .= " AND DATE_FORMAT(t.transaction_date, '%Y-%m') = ? ";
    $params[] = $period_filter;
}

$history = [];
try {
    $history = db_fetch_all("
        SELECT
            DATE_FORMAT(t.transaction_date, '%Y-%m') AS period_key,
            DATE_FORMAT(t.transaction_date, '%b %Y') AS period_label,
            t.bank_account_id,
            b.bank_name,
            b.account_name,
            SUM(CASE WHEN LOWER(COALESCE(t.type, '')) = 'deposit' THEN t.amount ELSE -t.amount END) AS bank_balance,
            " . ($has_bank_transactions_reconciled
                ? "SUM(CASE WHEN t.is_reconciled = 1 THEN (CASE WHEN LOWER(COALESCE(t.type, '')) = 'deposit' THEN t.amount ELSE -t.amount END) ELSE 0 END)"
                : "SUM(CASE WHEN LOWER(COALESCE(t.type, '')) = 'deposit' THEN t.amount ELSE -t.amount END)") . " AS system_balance,
            " . ($has_bank_transactions_reconciled
                ? "SUM(CASE WHEN t.is_reconciled = 0 THEN 1 ELSE 0 END)"
                : "0") . " AS unreconciled_count
        FROM bank_transactions t
        INNER JOIN bank_accounts b ON b.id = t.bank_account_id
        {$where}
        GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m'), DATE_FORMAT(t.transaction_date, '%b %Y'), t.bank_account_id, b.bank_name, b.account_name
        ORDER BY DATE_FORMAT(t.transaction_date, '%Y-%m') DESC, b.bank_name ASC
    ", $params) ?: [];
} catch (Exception $e) {
    $history = [];
}

$rows = [];
foreach ($history as $row) {
    $bank_balance = (float)($row['bank_balance'] ?? 0);
    $system_balance = (float)($row['system_balance'] ?? 0);
    $variance = $bank_balance - $system_balance;
    $unreconciled_count = (int)($row['unreconciled_count'] ?? 0);
    $status = $unreconciled_count > 0 ? 'in_progress' : 'authorized';

    if ($status_filter !== 'all' && $status_filter !== '' && $status_filter !== $status) {
        continue;
    }

    $row['bank_balance'] = $bank_balance;
    $row['system_balance'] = $system_balance;
    $row['variance'] = $variance;
    $row['status_key'] = $status;
    $row['status_label'] = $status === 'in_progress' ? 'In Progress' : 'Authorized';
    $rows[] = $row;
}

$in_progress_count = 0;
$authorized_count = 0;
$variance_found_count = 0;

foreach ($rows as $r) {
    if ($r['status_key'] === 'in_progress') {
        $in_progress_count++;
    } else {
        $authorized_count++;
    }
    if (abs((float)$r['variance']) > 0.0001) {
        $variance_found_count++;
    }
}

$periods_sql = "
    SELECT DISTINCT DATE_FORMAT(transaction_date, '%Y-%m') AS period_key,
           DATE_FORMAT(transaction_date, '%b %Y') AS period_label
    FROM bank_transactions
    WHERE 1=1
";
$periods_params = [];
if ($has_bank_transactions_company) {
    $periods_sql .= " AND company_id = ? ";
    $periods_params[] = $company_id;
}
$periods_sql .= " ORDER BY period_key DESC ";
try {
    $periods = db_fetch_all($periods_sql, $periods_params) ?: [];
} catch (Exception $e) {
    $periods = [];
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
html[data-bs-theme="dark"],
html[data-app-theme="dark"] {
    --br-bg: #080c1a;
    --br-panel: #1d243c;
    --br-panel-2: #1a2035;
    --br-line: #313a61;
    --br-cyan: #08d0ef;
    --br-soft: #8f9dc5;
    --br-warn: #ffc11b;
    --br-green: #41c95b;
    --br-text-header: #ffffff;
    --br-label: #9aa9d1;
}

html[data-bs-theme="light"],
html[data-app-theme="light"] {
    --br-bg: #f8f9fa;
    --br-panel: #ffffff;
    --br-panel-2: #f8f9fa;
    --br-line: #e0e0e0;
    --br-cyan: #0dcaf0;
    --br-soft: #6c757d;
    --br-warn: #ffc107;
    --br-green: #198754;
    --br-text-header: #212529;
    --br-label: #495057;
}

body {
    background: var(--br-bg);
    color: var(--br-soft);
}
.br-screen {
    border: 1px solid rgba(8, 208, 239, .55);
    border-radius: 10px;
    background: rgba(8, 208, 239, .07);
    color: var(--br-cyan);
    font-weight: 700;
    padding: .65rem 1rem;
}
.br-title {
    color: var(--br-text-header);
    font-weight: 800;
}
.br-sub {
    color: var(--br-label);
    margin-bottom: 0;
}
.br-btn {
    background: #0fc7df;
    color: #04111c;
    border-radius: 10px;
    border: 0;
    font-weight: 700;
    text-decoration: none;
    padding: .75rem 1.2rem;
    display: inline-flex;
    align-items: center;
    gap: .45rem;
}
.br-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .9rem;
}
.br-card {
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid rgba(255, 255, 255, .03);
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.br-card h6 {
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: .72rem;
    margin: 0 0 .35rem 0;
    font-weight: 800;
}
.br-card .v {
    font-size: 2.05rem;
    font-weight: 900;
    line-height: 1;
}
.br-card-progress {
    background: linear-gradient(90deg, rgba(5, 86, 95, .82), rgba(8, 116, 129, .76));
}
.br-card-progress h6, .br-card-progress .v { color: var(--br-cyan); }
.br-card-auth {
    background: linear-gradient(90deg, rgba(26, 78, 25, .82), rgba(34, 98, 33, .76));
}
.br-card-auth h6, .br-card-auth .v { color: var(--br-green); }
.br-card-var {
    background: linear-gradient(90deg, rgba(90, 73, 0, .82), rgba(118, 95, 0, .74));
}
.br-card-var h6, .br-card-var .v { color: var(--br-warn); }
.br-panel {
    border-radius: 12px;
    border: 1px solid var(--br-line);
    background: linear-gradient(180deg, var(--br-panel), var(--br-panel-2));
}
.br-filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: .8rem;
    align-items: end;
}
.br-label {
    color: var(--br-label);
    font-size: .85rem;
    margin-bottom: .3rem;
}
.br-input, .br-select {
    width: 100%;
    background: #252d4a;
    border: 1px solid #344271;
    color: #eef3ff;
    border-radius: 8px;
    padding: .58rem .8rem;
}
.br-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    font-weight: 800;
    font-size: .85rem;
    padding: .12rem .6rem;
    background: rgba(8, 208, 239, .95);
    color: #04111c;
}
.br-table-wrap { overflow: auto; }
.br-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 980px;
}
.br-table thead th {
    padding: 1rem .95rem;
    font-size: .76rem;
    color: #8f9cc0;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid var(--br-line);
    white-space: nowrap;
}
.br-table tbody td {
    padding: .9rem .95rem;
    border-bottom: 1px solid rgba(49, 58, 97, .55);
    color: #f2f5ff;
    white-space: nowrap;
}
.br-status {
    display: inline-block;
    border-radius: 6px;
    font-weight: 700;
    font-size: .84rem;
    padding: .2rem .7rem;
}
.br-status-progress {
    color: #06b8ff;
    background: rgba(6, 184, 255, .18);
}
.br-status-auth {
    color: var(--br-green);
    background: rgba(65, 201, 91, .18);
}
.br-var-positive {
    color: var(--br-warn);
    font-weight: 800;
}
.br-var-zero {
    color: var(--br-green);
    font-weight: 800;
}
.br-action {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #334171;
    background: transparent;
    color: #a7b4d8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.br-action.edit {
    color: #ff8e41;
}
@media (max-width: 1200px) {
    .br-cards { grid-template-columns: 1fr; }
    .br-filter-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="br-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Bank Reconciliation - List / Dashboard View</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="br-title mb-1"><i class="fas fa-landmark me-2" style="color: var(--br-text-header);"></i>Bank Reconciliation</h2>
            <p class="br-sub">Reconcile bank statements with system records for each bank account.</p>
        </div>
        <a href="<?= url('finance/add_bank_reconciliation.php') ?>" class="br-btn">
            <i class="fas fa-plus"></i> New Bank Reconciliation
        </a>
    </div>

    <div class="br-cards mb-4">
        <div class="br-card br-card-progress">
            <div>
                <h6>In Progress</h6>
                <div class="v"><?= (int)$in_progress_count ?></div>
            </div>
        </div>
        <div class="br-card br-card-auth">
            <div>
                <h6>Authorized</h6>
                <div class="v"><?= (int)$authorized_count ?></div>
            </div>
        </div>
        <div class="br-card br-card-var">
            <div>
                <h6>Variance Found</h6>
                <div class="v"><?= (int)$variance_found_count ?></div>
            </div>
        </div>
    </div>

    <div class="br-panel p-4 mb-4">
        <form method="GET" class="br-filter-grid">
            <div>
                <label class="br-label">Bank</label>
                <select name="bank_id" class="br-select">
                    <option value="0">All Banks</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $bank_filter === (int)$b['id'] ? 'selected' : '' ?>>
                            <?= escape_html($b['bank_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="br-label">Period</label>
                <select name="period" class="br-select">
                    <option value="all">All Periods</option>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= escape_html($p['period_key']) ?>" <?= $period_filter === $p['period_key'] ? 'selected' : '' ?>>
                            <?= escape_html($p['period_label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="br-label">Status</label>
                <select name="status" class="br-select">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="authorized" <?= $status_filter === 'authorized' ? 'selected' : '' ?>>Authorized</option>
                </select>
            </div>
            <div>
                <button class="br-btn" type="submit"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>

    <div class="br-panel p-0">
        <div class="d-flex align-items-center gap-2 px-4 py-3" style="border-bottom:1px solid var(--br-line);">
            <i class="fas fa-file-alt text-light"></i>
            <h4 class="m-0 fw-bold" style="font-size:1.95rem; color: var(--br-text-header);">Bank Reconciliation History</h4>
            <span class="br-badge"><?= count($rows) ?></span>
        </div>

        <div class="br-table-wrap">
            <table class="br-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Bank</th>
                        <th>Bank Balance</th>
                        <th>System Balance</th>
                        <th>Variance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><?= escape_html($row['period_label']) ?></td>
                                <td><?= escape_html($row['bank_name']) ?></td>
                                <td><?= format_currency($row['bank_balance']) ?></td>
                                <td><?= format_currency($row['system_balance']) ?></td>
                                <td class="<?= abs((float)$row['variance']) > 0.0001 ? 'br-var-positive' : 'br-var-zero' ?>">
                                    <?= format_currency($row['variance']) ?>
                                </td>
                                <td>
                                    <span class="br-status <?= $row['status_key'] === 'in_progress' ? 'br-status-progress' : 'br-status-auth' ?>">
                                        <?= escape_html($row['status_label']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="br-action me-1" href="<?= url('finance/view_bank.php?id=' . (int)$row['bank_account_id']) ?>" title="View"><i class="fas fa-eye"></i></a>
                                    <a class="br-action edit" href="<?= url('finance/add_bank_reconciliation.php?bank_id=' . (int)$row['bank_account_id'] . '&period_from=' . urlencode($row['period_key'] . '-01') . '&period_to=' . urlencode(date('Y-m-t', strtotime($row['period_key'] . '-01')))) ?>" title="Edit"><i class="fas fa-pen"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5" style="color:#95a4c8;">No reconciliation records found for the selected filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
