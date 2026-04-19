<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Payroll';
$company_id = (int) active_company_id(1);

try {
    db_query("
        CREATE TABLE IF NOT EXISTS payroll_employees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL DEFAULT 1,
            employee_name VARCHAR(150) NOT NULL,
            dob DATE NULL,
            date_started DATE NOT NULL,
            date_ended DATE NULL,
            designation VARCHAR(120) NULL,
            emp_group VARCHAR(20) NOT NULL,
            department VARCHAR(80) NOT NULL,
            photo_path VARCHAR(255) NULL,
            gross_annual DECIMAL(15,2) NOT NULL DEFAULT 0,
            weekly_pay DECIMAL(15,2) NOT NULL DEFAULT 0,
            hourly_rate DECIMAL(15,2) NOT NULL DEFAULT 0,
            standard_hours DECIMAL(10,2) NOT NULL DEFAULT 45,
            paye_rate DECIMAL(6,2) NOT NULL DEFAULT 3,
            nrbf_employee_rate DECIMAL(6,2) NOT NULL DEFAULT 5,
            nrbf_company_rate DECIMAL(6,2) NOT NULL DEFAULT 7.5,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL
        )
    ");

    db_query("
        CREATE TABLE IF NOT EXISTS payroll_runs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL DEFAULT 1,
            run_reference VARCHAR(50) NOT NULL,
            period_month INT NOT NULL,
            period_year INT NOT NULL,
            total_gross DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_deductions DECIMAL(15,2) NOT NULL DEFAULT 0,
            total_net DECIMAL(15,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Throwable $e) {
    log_error('Payroll dashboard schema ensure failed: ' . $e->getMessage());
}

$search = trim((string) get('search', ''));
$grp = trim((string) get('grp', 'all'));
$dept = trim((string) get('dept', 'all'));
$status = trim((string) get('status', 'active'));

$where = ["company_id = ?"];
$params = [$company_id];

if ($search !== '') {
    $where[] = "(employee_name LIKE ? OR designation LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($grp !== '' && $grp !== 'all') {
    $where[] = "emp_group = ?";
    $params[] = $grp;
}
if ($dept !== '' && $dept !== 'all') {
    $where[] = "department = ?";
    $params[] = $dept;
}
if ($status === 'active') {
    $where[] = "is_active = 1";
} elseif ($status === 'inactive') {
    $where[] = "is_active = 0";
}

$rows = db_fetch_all("
    SELECT *
    FROM payroll_employees
    WHERE " . implode(' AND ', $where) . "
    ORDER BY employee_name ASC
", $params) ?: [];

$groups = db_fetch_all("
    SELECT DISTINCT emp_group
    FROM payroll_employees
    WHERE company_id = ? AND TRIM(COALESCE(emp_group, '')) <> ''
    ORDER BY emp_group
", [$company_id]) ?: [];

$depts = db_fetch_all("
    SELECT DISTINCT department
    FROM payroll_employees
    WHERE company_id = ? AND TRIM(COALESCE(department, '')) <> ''
    ORDER BY department
", [$company_id]) ?: [];

$stats = db_fetch("
    SELECT
        COUNT(*) AS total_employees,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_count
    FROM payroll_employees
    WHERE company_id = ?
", [$company_id]) ?: ['total_employees' => 0, 'active_count' => 0, 'inactive_count' => 0];

$last_run = db_fetch("
    SELECT MAX(created_at) AS last_pay_run_at
    FROM payroll_runs
    WHERE company_id = ?
", [$company_id]) ?: ['last_pay_run_at' => null];

$last_pay_run_at = $last_run['last_pay_run_at'] ?? null;
$last_pay_run_label = $last_pay_run_at ? date('d M', strtotime((string) $last_pay_run_at)) : 'N/A';

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --pr-bg: #0e1119;
    --pr-panel: #232738;
    --pr-panel-2: #1f2434;
    --pr-line: #343a54;
    --pr-cyan: #11c8e8;
    --pr-soft: #97a7cd;
    --pr-text: #f5f8ff;
    --pr-title: #ffffff;
    --pr-sub: #acb8d8;
    --pr-btn-border: #39415f;
    --pr-btn-text: #dbe5ff;
    --pr-btn-bg: transparent;
    --pr-primary-text: #071118;
    --pr-input-bg: #2a3044;
    --pr-input-border: #3b4564;
    --pr-input-text: var(--pr-text);
    --pr-table-head: #8ea0ca;
    --pr-table-row-border: rgba(52,58,84,.7);
    --pr-blue: #2790ff;
    --pr-green: #4bc85d;
    --pr-red: #ff564f;
    --pr-teal: #10c7df;
}

[data-bs-theme="light"] {
    --pr-bg: #f8f9fa;
    --pr-panel: #ffffff;
    --pr-panel-2: #f8f9fa;
    --pr-line: #e0e0e0;
    --pr-cyan: #0dcaf0;
    --pr-soft: #6c757d;
    --pr-text: #212529;
    --pr-title: #212529;
    --pr-sub: #6c757d;
    --pr-btn-border: #ced4da;
    --pr-btn-text: #212529;
    --pr-btn-bg: transparent;
    --pr-primary-text: #ffffff;
    --pr-input-bg: #ffffff;
    --pr-input-border: #ced4da;
    --pr-input-text: #212529;
    --pr-table-head: #495057;
    --pr-table-row-border: rgba(226,232,240,.8);
    --pr-blue: #0d6efd;
    --pr-green: #198754;
    --pr-red: #dc3545;
    --pr-teal: #0aa8d4;
}

body { background: var(--pr-bg); color: var(--pr-text); }
.pr-screen {
    border: 1px solid rgba(17,200,232,.35);
    background: rgba(17,200,232,.12);
    color: var(--pr-cyan);
    border-radius: 10px;
    padding: .72rem 1rem;
    font-weight: 800;
}
.pr-title { color: var(--pr-title); font-weight: 900; margin-bottom: .15rem; }
.pr-sub { color: var(--pr-sub); margin-bottom: 0; }
.pr-btn {
    border: 1px solid var(--pr-btn-border);
    border-radius: 12px;
    color: var(--pr-btn-text);
    text-decoration: none;
    padding: .78rem 1.1rem;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-weight: 800;
    background: var(--pr-btn-bg);
}
.pr-btn-primary {
    background: var(--pr-cyan);
    border-color: var(--pr-cyan);
    color: var(--pr-primary-text);
}
.pr-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .9rem;
}
.pr-card {
    border-radius: 14px;
    padding: 1rem 1.2rem;
    text-align: center;
}
.pr-card h6 {
    margin: 0 0 .45rem 0;
    font-size: .78rem;
    letter-spacing: 1.8px;
    text-transform: uppercase;
    font-weight: 800;
}
.pr-card .v {
    font-size: 2rem;
    line-height: 1;
    font-weight: 900;
}
.pr-card-1 { background: #173456; }
.pr-card-2 { background: #214f1d; }
.pr-card-3 { background: #5c1f1c; }
.pr-card-4 { background: #0e666f; }
.pr-card-1 h6, .pr-card-1 .v { color: var(--pr-blue); }
.pr-card-2 h6, .pr-card-2 .v { color: var(--pr-green); }
.pr-card-3 h6, .pr-card-3 .v { color: var(--pr-red); }
.pr-card-4 h6, .pr-card-4 .v { color: var(--pr-teal); }
.pr-panel {
    border-radius: 14px;
    border: 1px solid var(--pr-line);
    background: linear-gradient(180deg, var(--pr-panel), var(--pr-panel-2));
}
.pr-filter-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.1fr 1.1fr 1.1fr auto;
    gap: .8rem;
    align-items: end;
}
.pr-label {
    color: var(--pr-sub);
    font-size: .84rem;
    margin-bottom: .35rem;
}
.pr-input, .pr-select {
    width: 100%;
    background: var(--pr-input-bg);
    border: 1px solid var(--pr-input-border);
    color: var(--pr-input-text);
    border-radius: 8px;
    padding: .68rem .85rem;
}
.pr-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}
.pr-table th {
    color: var(--pr-table-head);
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .9px;
    padding: 1rem .95rem;
    border-bottom: 1px solid var(--pr-line);
    text-align: left;
}
.pr-table td {
    color: var(--pr-text);
    padding: 1rem .95rem;
    border-bottom: 1px solid var(--pr-table-row-border);
    vertical-align: middle;
}
.pr-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 22px;
    border-radius: 5px;
    padding: 0 .45rem;
    font-size: .82rem;
    font-weight: 900;
}
.pr-badge-a { background: rgba(39,144,255,.22); color: #66b2ff; }
.pr-badge-b { background: rgba(247,162,27,.2); color: #f7a21b; }
.pr-badge-c { background: rgba(255,206,72,.18); color: #f0c13b; }
.pr-badge-d { background: rgba(185,121,255,.18); color: #c697ff; }
.pr-status {
    display: inline-block;
    border-radius: 5px;
    padding: .22rem .65rem;
    font-size: .8rem;
    font-weight: 800;
}
.pr-status-active { color: #69d56b; background: rgba(75,200,93,.16); }
.pr-status-inactive { color: #ff7c7c; background: rgba(255,86,79,.16); }
.pr-action {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    border: 1px solid #3b4564;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #a8b6d7;
    text-decoration: none;
    background: transparent;
}
.pr-action.edit { color: #ff9157; }
@media (max-width: 1200px) {
    .pr-cards { grid-template-columns: 1fr; }
    .pr-filter-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="pr-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.6rem;"></i> SCREEN: Payroll - Employee Dashboard & Pay Generation</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="pr-title"><i class="fas fa-users me-2" style="color:#8d61ff;"></i>Payroll</h2>
            <p class="pr-sub">Manage employees, salary structures, and generate pay runs.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= url('finance/add_payroll_employee.php') ?>" class="pr-btn"><i class="fas fa-plus"></i> Add New Employee</a>
            <a href="<?= url('finance/payroll_manage.php') ?>" class="pr-btn"><i class="fas fa-cog"></i> Manage</a>
            <a href="<?= url('finance/add_payroll.php') ?>" class="pr-btn pr-btn-primary"><i class="fas fa-sack-dollar"></i> Generate Pay</a>
        </div>
    </div>

    <div class="pr-cards mb-4">
        <div class="pr-card pr-card-1">
            <h6>Total Employees</h6>
            <div class="v"><?= (int) ($stats['total_employees'] ?? 0) ?></div>
        </div>
        <div class="pr-card pr-card-2">
            <h6>Active</h6>
            <div class="v"><?= (int) ($stats['active_count'] ?? 0) ?></div>
        </div>
        <div class="pr-card pr-card-3">
            <h6>Inactive</h6>
            <div class="v"><?= (int) ($stats['inactive_count'] ?? 0) ?></div>
        </div>
        <div class="pr-card pr-card-4">
            <h6>Last Pay Run</h6>
            <div class="v"><?= escape_html($last_pay_run_label) ?></div>
        </div>
    </div>

    <div class="pr-panel p-4 mb-4">
        <form method="GET" class="pr-filter-grid">
            <div>
                <label class="pr-label">Search</label>
                <input type="text" name="search" class="pr-input" value="<?= escape_html($search) ?>" placeholder="Employee name">
            </div>
            <div>
                <label class="pr-label">Group</label>
                <select name="grp" class="pr-select">
                    <option value="all">All Groups</option>
                    <?php foreach ($groups as $g): ?>
                        <?php $v = (string) $g['emp_group']; ?>
                        <option value="<?= escape_html($v) ?>" <?= $grp === $v ? 'selected' : '' ?>><?= escape_html($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="pr-label">Department</label>
                <select name="dept" class="pr-select">
                    <option value="all">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <?php $v = (string) $d['department']; ?>
                        <option value="<?= escape_html($v) ?>" <?= $dept === $v ? 'selected' : '' ?>><?= escape_html($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="pr-label">Status</label>
                <select name="status" class="pr-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div>
                <button class="pr-btn pr-btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>

    <div class="pr-panel p-0">
        <div class="d-flex align-items-center gap-2 px-4 py-3" style="border-bottom:1px solid var(--pr-line);">
            <i class="fas fa-file-alt text-light"></i>
            <h4 class="m-0 text-white fw-bold" style="font-size:1.8rem;">Employees</h4>
            <span class="badge rounded-pill" style="background:rgba(17,200,232,.95);color:#08111a;font-size:.82rem;"><?= count($rows) ?></span>
        </div>

        <div class="table-responsive">
            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Group</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Gross Salary</th>
                        <th>Weekly Pay</th>
                        <th>Rate/Hr</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5" style="color:#95a4c8;">No employees found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $group_class = match (strtoupper((string) ($r['emp_group'] ?? ''))) {
                                    'A' => 'pr-badge-a',
                                    'B' => 'pr-badge-b',
                                    'C' => 'pr-badge-c',
                                    'D' => 'pr-badge-d',
                                    default => 'pr-badge-a',
                                };
                            ?>
                            <tr>
                                <td style="font-weight:800;"><?= escape_html((string) $r['employee_name']) ?></td>
                                <td><span class="pr-badge <?= $group_class ?>"><?= escape_html((string) $r['emp_group']) ?></span></td>
                                <td><?= escape_html((string) $r['department']) ?></td>
                                <td><?= escape_html((string) ($r['designation'] ?? '')) ?></td>
                                <td>$<?= number_format((float) $r['gross_annual'], 0) ?></td>
                                <td>$<?= number_format((float) $r['weekly_pay'], 2) ?></td>
                                <td>$<?= number_format((float) $r['hourly_rate'], 2) ?></td>
                                <td>
                                    <span class="pr-status <?= ((int) $r['is_active'] === 1) ? 'pr-status-active' : 'pr-status-inactive' ?>">
                                        <?= ((int) $r['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="pr-action me-1" href="<?= url('finance/add_payroll_employee.php?id=' . (int) $r['id']) ?>" title="View"><i class="fas fa-eye"></i></a>
                                    <a class="pr-action edit" href="<?= url('finance/add_payroll_employee.php?id=' . (int) $r['id']) ?>" title="Edit"><i class="fas fa-pen"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
