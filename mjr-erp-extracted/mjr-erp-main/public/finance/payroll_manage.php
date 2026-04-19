<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Payroll - Manage Employees';
$company_id = (int)active_company_id(1);

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
} catch (Exception $e) {
    log_error('payroll_employees ensure failed: ' . $e->getMessage());
}

if (is_post() && verify_csrf_token(post('csrf_token')) && post('action') === 'toggle_status') {
    $id = (int)post('id', 0);
    $row = db_fetch("SELECT id, is_active FROM payroll_employees WHERE id = ? AND company_id = ? LIMIT 1", [$id, $company_id]);
    if ($row) {
        $new_status = ((int)$row['is_active'] === 1) ? 0 : 1;
        db_query("UPDATE payroll_employees SET is_active = ?, updated_at = NOW() WHERE id = ? AND company_id = ?", [$new_status, $id, $company_id]);
        set_flash($new_status ? 'Employee marked Active.' : 'Employee marked Inactive.', 'success');
    }
    redirect('finance/payroll_manage.php');
}

$search = trim((string)get('search', ''));
$grp = trim((string)get('grp', 'all'));
$dept = trim((string)get('dept', 'all'));
$status = trim((string)get('status', 'all'));

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

$where_sql = implode(' AND ', $where);

$rows = db_fetch_all("
    SELECT *
    FROM payroll_employees
    WHERE {$where_sql}
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

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --pm-bg: #080c1a;
    --pm-panel: #1d243c;
    --pm-panel-2: #1a2035;
    --pm-line: #313a61;
    --pm-cyan: #08d0ef;
    --pm-soft: #8f9dc5;
    --pm-green: #41c95b;
    --pm-red: #ff4747;
}

[data-bs-theme="light"] {
    --pm-bg: #f8f9fa;
    --pm-panel: #ffffff;
    --pm-panel-2: #f8f9fa;
    --pm-line: #e0e0e0;
    --pm-cyan: #0dcaf0;
    --pm-soft: #6c757d;
    --pm-green: #198754;
    --pm-red: #dc3545;
}

body { background: var(--pm-bg); color: var(--pm-soft); }
.pm-screen { border:1px solid rgba(8,208,239,.55); border-radius:10px; background:rgba(8,208,239,.07); color:var(--pm-cyan); font-weight:700; padding:.65rem 1rem; }
.pm-title { color:#fff; font-weight:800; margin-bottom:.1rem; }
.pm-sub { color:#9fb0da; margin-bottom:0; }
.pm-btn { border:1px solid #344271; border-radius:10px; color:#a8b6de; text-decoration:none; padding:.6rem 1rem; font-weight:700; display:inline-flex; align-items:center; gap:.4rem; background:transparent; }
.pm-btn-primary { background:#10c8df; color:#04111c; border-color:#10c8df; }
.pm-panel { border-radius:12px; border:1px solid var(--pm-line); background:linear-gradient(180deg,var(--pm-panel),var(--pm-panel-2)); }
.pm-filter-grid { display:grid; grid-template-columns:1.2fr 1fr 1fr 1fr auto; gap:.8rem; align-items:end; }
.pm-label { color:#9aa9d1; font-size:.85rem; margin-bottom:.3rem; }
.pm-input, .pm-select { width:100%; background:#252d4a; border:1px solid #344271; color:#eef3ff; border-radius:8px; padding:.58rem .8rem; }
.pm-table { width:100%; border-collapse:collapse; min-width:1100px; }
.pm-table thead th { padding:1rem .9rem; font-size:.76rem; color:#8f9cc0; text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--pm-line); }
.pm-table tbody td { padding:.85rem .9rem; border-bottom:1px solid rgba(49,58,97,.55); color:#f2f5ff; }
.pm-status { display:inline-block; border-radius:6px; font-weight:700; font-size:.84rem; padding:.2rem .7rem; }
.pm-active { color:var(--pm-green); background:rgba(65,201,91,.16); }
.pm-inactive { color:var(--pm-red); background:rgba(255,71,71,.16); }
.pm-action { width:34px; height:34px; border-radius:8px; border:1px solid #334171; background:transparent; color:#a7b4d8; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
.pm-action.edit { color:#ff8e41; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="pm-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Payroll - Manage Employees</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="pm-title"><i class="fas fa-cog me-2 text-light"></i>Manage Employees</h2>
            <p class="pm-sub">Edit payroll employees and control active/inactive status.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('finance/add_payroll_employee.php') ?>" class="pm-btn"><i class="fas fa-plus"></i> Add Employee</a>
            <a href="<?= url('finance/payroll.php') ?>" class="pm-btn pm-btn-primary"><i class="fas fa-arrow-left"></i> Back to Payroll</a>
        </div>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= escape_html($flash['message']) ?></div>
    <?php endif; ?>

    <div class="pm-panel p-4 mb-4">
        <form method="GET" class="pm-filter-grid">
            <div>
                <label class="pm-label">Search</label>
                <input type="text" name="search" class="pm-input" value="<?= escape_html($search) ?>" placeholder="Employee name or designation">
            </div>
            <div>
                <label class="pm-label">Group</label>
                <select name="grp" class="pm-select">
                    <option value="all">All Groups</option>
                    <?php foreach ($groups as $g): ?>
                        <?php $v = (string)$g['emp_group']; ?>
                        <option value="<?= escape_html($v) ?>" <?= $grp === $v ? 'selected' : '' ?>><?= escape_html($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="pm-label">Department</label>
                <select name="dept" class="pm-select">
                    <option value="all">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <?php $v = (string)$d['department']; ?>
                        <option value="<?= escape_html($v) ?>" <?= $dept === $v ? 'selected' : '' ?>><?= escape_html($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="pm-label">Status</label>
                <select name="status" class="pm-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div>
                <button class="pm-btn pm-btn-primary" type="submit"><i class="fas fa-search"></i> Filter</button>
            </div>
        </form>
    </div>

    <div class="pm-panel p-0">
        <div class="table-responsive">
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Group</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Gross Annual</th>
                        <th>Weekly</th>
                        <th>Rate/Hr</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center py-4" style="color:#7f8db4;">No employees found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td style="font-weight:800;"><?= escape_html((string)$r['employee_name']) ?></td>
                                <td><?= escape_html((string)$r['emp_group']) ?></td>
                                <td><?= escape_html((string)$r['department']) ?></td>
                                <td><?= escape_html((string)($r['designation'] ?? '')) ?></td>
                                <td><?= format_currency((float)$r['gross_annual']) ?></td>
                                <td><?= format_currency((float)$r['weekly_pay']) ?></td>
                                <td>$<?= number_format((float)$r['hourly_rate'], 2) ?></td>
                                <td>
                                    <span class="pm-status <?= ((int)$r['is_active'] === 1) ? 'pm-active' : 'pm-inactive' ?>">
                                        <?= ((int)$r['is_active'] === 1) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="pm-action edit me-1" href="<?= url('finance/add_payroll_employee.php?id=' . (int)$r['id']) ?>" title="Edit"><i class="fas fa-pen"></i></a>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="pm-action" title="<?= ((int)$r['is_active'] === 1) ? 'Mark Inactive' : 'Mark Active' ?>">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    </form>
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
