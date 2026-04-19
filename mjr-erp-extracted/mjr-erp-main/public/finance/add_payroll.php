<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Payroll - Generate Pay Run';
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
} catch (Throwable $e) {
    log_error('Payroll preview schema ensure failed: ' . $e->getMessage());
}

$employees = db_fetch_all("
    SELECT *
    FROM payroll_employees
    WHERE company_id = ? AND is_active = 1
    ORDER BY employee_name ASC
", [$company_id]) ?: [];

$available_groups = [];
foreach ($employees as $employee) {
    $group_value = trim((string) ($employee['emp_group'] ?? ''));
    if ($group_value !== '') {
        $available_groups[$group_value] = true;
    }
}
$available_groups = array_keys($available_groups);
sort($available_groups);

if (empty($available_groups)) {
    $available_groups = ['A', 'B', 'C', 'D'];
}

$period_from = trim((string) post('period_from', ''));
$period_to = trim((string) post('period_to', ''));
$pay_duration = trim((string) post('pay_duration', 'weekly'));
$selected_group = trim((string) post('group', 'all'));
if ($selected_group !== 'all' && !in_array($selected_group, $available_groups, true)) {
    $selected_group = 'all';
}

$duration_multiplier = match ($pay_duration) {
    'fortnightly' => 2,
    'monthly' => 52 / 12,
    default => 1,
};

$errors = [];
if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }
    if ($period_from === '') {
        $errors[] = 'Period from is required.';
    }
    if ($period_to === '') {
        $errors[] = 'Period to is required.';
    }
    if ($period_from !== '' && $period_to !== '' && strtotime($period_to) < strtotime($period_from)) {
        $errors[] = 'Period to cannot be before period from.';
    }
}

$rows = [];
foreach ($employees as $employee) {
    $group_value = (string) ($employee['emp_group'] ?? '');
    if ($selected_group !== 'all' && $group_value !== $selected_group) {
        continue;
    }

    $employee_id = (int) ($employee['id'] ?? 0);
    $rate = round((float) ($employee['hourly_rate'] ?? 0), 2);
    $default_hours = round((float) ($employee['standard_hours'] ?? 45) * $duration_multiplier, 2);

    $hrs = (float) post('hrs_' . $employee_id, $default_hours);
    $leave = (float) post('leave_' . $employee_id, 0);
    $ot_hrs = (float) post('ot_hrs_' . $employee_id, 0);
    $bonus = (float) post('bonus_' . $employee_id, 0);

    $normal_wages = round($hrs * $rate, 2);
    $leave_pay = round($leave * $rate, 2);
    $total_wages = round($normal_wages + $leave_pay, 2);
    $paye = round($total_wages * (((float) ($employee['paye_rate'] ?? 0)) / 100), 2);
    $nrbf_company = round($total_wages * (((float) ($employee['nrbf_company_rate'] ?? 0)) / 100), 2);
    $nrbf_employee = round($total_wages * (((float) ($employee['nrbf_employee_rate'] ?? 0)) / 100), 2);
    $ot = round($ot_hrs * $rate, 2);
    $net_wages = round($total_wages - $paye - $nrbf_employee + $ot + $bonus, 2);

    $rows[] = [
        'employee_id' => $employee_id,
        'name' => (string) ($employee['employee_name'] ?? ''),
        'grp' => $group_value,
        'dept' => (string) ($employee['department'] ?? ''),
        'hrs' => $hrs,
        'leave' => $leave,
        'ot_hrs' => $ot_hrs,
        'rate' => $rate,
        'normal_wages' => $normal_wages,
        'leave_pay' => $leave_pay,
        'total_wages' => $total_wages,
        'paye' => $paye,
        'nrbf_company' => $nrbf_company,
        'nrbf_employee' => $nrbf_employee,
        'ot' => $ot,
        'bonus' => $bonus,
        'net_wages' => $net_wages,
    ];
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --gp-bg: #0f1119;
    --gp-panel: #222636;
    --gp-panel-2: #1d2231;
    --gp-line: #30364e;
    --gp-cyan: #15c7e5;
    --gp-soft: #99a7cc;
    --gp-text: #f6f8ff;
    --gp-accent: #18c8e8;
    --gp-green: #58c34d;
    --gp-red: #ff5454;
    --gp-title-text: #fff;
    --gp-sub-text: #adb9da;
    --gp-back-text: #d9e3ff;
    --gp-step-text: #041018;
    --gp-panel-title-text: #fff;
    --gp-label-text: #8ea1cd;
    --gp-input-bg: #282d41;
    --gp-input-border: #39425f;
    --gp-help-text: #9fb1d6;
    --gp-table-head-text: #8fa3d0;
    --gp-mini-input-bg: #2b3147;
    --gp-mini-input-border: #404965;
    --gp-mini-input-text: #fff;
    --gp-rate-text: #16d5ff;
    --gp-formula-bg: rgba(255,193,7,.12);
    --gp-formula-border: #ffd31c;
    --gp-formula-text: #f0bf24;
    --gp-btn-border: #3a4260;
    --gp-btn-text: #d9e3ff;
    --gp-btn-primary-bg: #12c3e2;
    --gp-btn-primary-border: #12c3e2;
    --gp-btn-primary-text: #051118;
    --gp-btn-success-bg: #57c54d;
    --gp-btn-success-border: #57c54d;
    --gp-btn-success-text: #fff;
    --gp-alert-border: rgba(255,84,84,.3);
    --gp-alert-bg: rgba(255,84,84,.08);
    --gp-alert-text: #ffbcbc;
    --gp-table-border: rgba(255,255,255,.06);
    --gp-table-td-border: rgba(255,255,255,.05);
}

[data-bs-theme="light"] {
    --gp-bg: #f8f9fa;
    --gp-panel: #ffffff;
    --gp-panel-2: #f8f9fa;
    --gp-line: #e0e0e0;
    --gp-cyan: #0dcaf0;
    --gp-soft: #6c757d;
    --gp-text: #212529;
    --gp-accent: #0aa8d4;
    --gp-green: #198754;
    --gp-red: #dc3545;
    --gp-title-text: #212529;
    --gp-sub-text: #6c757d;
    --gp-back-text: #0d6efd;
    --gp-step-text: #ffffff;
    --gp-panel-title-text: #212529;
    --gp-label-text: #495057;
    --gp-input-bg: #ffffff;
    --gp-input-border: #ced4da;
    --gp-help-text: #6c757d;
    --gp-table-head-text: #495057;
    --gp-mini-input-bg: #f8f9fa;
    --gp-mini-input-border: #ced4da;
    --gp-mini-input-text: #212529;
    --gp-rate-text: #0dcaf0;
    --gp-formula-bg: rgba(255,193,7,.1);
    --gp-formula-border: #ffc107;
    --gp-formula-text: #856404;
    --gp-btn-border: #ced4da;
    --gp-btn-text: #0d6efd;
    --gp-btn-primary-bg: #0dcaf0;
    --gp-btn-primary-border: #0dcaf0;
    --gp-btn-primary-text: #ffffff;
    --gp-btn-success-bg: #198754;
    --gp-btn-success-border: #198754;
    --gp-btn-success-text: #ffffff;
    --gp-alert-border: rgba(220,53,69,.3);
    --gp-alert-bg: rgba(220,53,69,.08);
    --gp-alert-text: #721c24;
    --gp-table-border: rgba(0,0,0,.06);
    --gp-table-td-border: rgba(0,0,0,.05);
}

body { background: var(--gp-bg); color: var(--gp-soft); }
.gp-screen {
    border: 1px solid rgba(21,199,229,.4);
    background: rgba(21,199,229,.12);
    color: var(--gp-cyan);
    border-radius: 10px;
    padding: .7rem 1rem;
    font-weight: 800;
}
.gp-title { color: var(--gp-title-text); font-weight: 900; margin-bottom: .25rem; }
.gp-sub { color: var(--gp-sub-text); }
.gp-back {
    border: 1px solid var(--gp-btn-border);
    border-radius: 12px;
    color: var(--gp-back-text);
    text-decoration: none;
    padding: .7rem 1rem;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-weight: 700;
}
.gp-panel {
    border: 1px solid var(--gp-line);
    border-radius: 14px;
    background: linear-gradient(180deg, var(--gp-panel), var(--gp-panel-2));
}
.gp-panel-head {
    display: flex;
    align-items: center;
    gap: .9rem;
    padding: .85rem 1.25rem;
    border-bottom: 1px solid var(--gp-line);
}
.gp-step {
    width: 34px;
    height: 28px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--gp-accent);
    color: var(--gp-step-text);
    font-weight: 900;
}
.gp-panel-title { color: var(--gp-panel-title-text); font-weight: 800; font-size: 1.05rem; }
.gp-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    padding: 1.15rem;
}
.gp-label {
    display: block;
    margin-bottom: .45rem;
    color: var(--gp-label-text);
    font-size: .78rem;
    text-transform: uppercase;
    font-weight: 800;
    letter-spacing: .8px;
}
.gp-input, .gp-select {
    width: 100%;
    background: var(--gp-input-bg);
    border: 1px solid var(--gp-input-border);
    color: var(--gp-text);
    border-radius: 8px;
    padding: .7rem .85rem;
}
.gp-help {
    margin-top: .45rem;
    font-size: .8rem;
    color: var(--gp-help-text);
}
.gp-table-wrap { overflow: auto; padding: 0 1.15rem 1.15rem; }
.gp-table {
    width: 100%;
    min-width: 1650px;
    border-collapse: collapse;
}
.gp-table th {
    color: var(--gp-table-head-text);
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .9px;
    text-align: left;
    padding: .9rem .4rem;
    border-bottom: 1px solid var(--gp-table-border);
}
.gp-table td {
    color: var(--gp-text);
    padding: .8rem .4rem;
    border-bottom: 1px solid var(--gp-table-td-border);
    vertical-align: middle;
}
.gp-mini-input {
    width: 46px;
    background: var(--gp-mini-input-bg);
    border: 1px solid var(--gp-mini-input-border);
    color: var(--gp-mini-input-text);
    border-radius: 5px;
    padding: .45rem .35rem;
    text-align: center;
    font-weight: 700;
}
.gp-money { white-space: nowrap; font-weight: 800; }
.gp-rate { color: var(--gp-rate-text); }
.gp-paye, .gp-nrbf-emp { color: var(--gp-red); }
.gp-net { color: var(--gp-green); }
.gp-formula {
    margin: 1rem 1.15rem 0;
    padding: .85rem 1rem;
    border-left: 4px solid var(--gp-formula-border);
    background: var(--gp-formula-bg);
    color: var(--gp-formula-text);
    font-weight: 700;
    border-radius: 0 8px 8px 0;
}
.gp-actions {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
    padding-top: 1rem;
}
.gp-btn-row {
    display: flex;
    gap: .7rem;
    flex-wrap: wrap;
}
.gp-btn {
    border: 1px solid var(--gp-btn-border);
    background: transparent;
    color: var(--gp-btn-text);
    border-radius: 11px;
    padding: .8rem 1.2rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    gap: .5rem;
}
.gp-btn-primary {
    background: var(--gp-btn-primary-bg);
    border-color: var(--gp-btn-primary-border);
    color: var(--gp-btn-primary-text);
}
.gp-btn-success {
    background: var(--gp-btn-success-bg);
    border-color: var(--gp-btn-success-border);
    color: var(--gp-btn-success-text);
}
.gp-alert {
    border: 1px solid var(--gp-alert-border);
    background: var(--gp-alert-bg);
    color: var(--gp-alert-text);
    border-radius: 10px;
    padding: .85rem 1rem;
}
@media (max-width: 1200px) {
    .gp-grid { grid-template-columns: 1fr; }
    .gp-actions { flex-direction: column; align-items: stretch; }
}
@media print {
    .gp-screen, .gp-actions, .gp-back, nav, .navbar, .sidebar, .no-print { display: none !important; }
    body { background: #fff !important; color: #000 !important; }
    .gp-panel { border: 1px solid #ccc !important; background: #fff !important; }
    .gp-panel-title, .gp-title, .gp-table td, .gp-table th { color: #000 !important; }
    .gp-table th, .gp-table td { border-color: #ddd !important; }
    .gp-formula { background: #fff8e1 !important; color: #7a5b00 !important; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="gp-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.6rem;"></i>SCREEN: Payroll - Generate Pay Run</div>

    <div class="d-flex justify-content-between align-items-start gap-3 mb-4 flex-wrap">
        <div>
            <h2 class="gp-title"><i class="fas fa-sack-dollar me-2" style="color:#ffb347;"></i>Generate Pay Run</h2>
            <p class="gp-sub mb-0">Calculate wages for selected employee groups and period.</p>
        </div>
        <a href="<?= url('finance/payroll.php') ?>" class="gp-back"><i class="fas fa-arrow-left"></i> Back to Payroll</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="gp-alert mb-4">
            <?= escape_html(implode(' ', $errors)) ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="payrollPreviewForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <div class="gp-panel mb-4">
            <div class="gp-panel-head">
                <span class="gp-step">01</span>
                <div class="gp-panel-title">Pay Period Setup</div>
            </div>
            <div class="gp-grid">
                <div>
                    <label class="gp-label">Period From *</label>
                    <input type="date" class="gp-input" name="period_from" value="<?= escape_html($period_from) ?>">
                </div>
                <div>
                    <label class="gp-label">Period To *</label>
                    <input type="date" class="gp-input" name="period_to" value="<?= escape_html($period_to) ?>">
                </div>
                <div>
                    <label class="gp-label">Pay Duration *</label>
                    <select class="gp-select" name="pay_duration" onchange="this.form.submit()">
                        <option value="weekly" <?= $pay_duration === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                        <option value="fortnightly" <?= $pay_duration === 'fortnightly' ? 'selected' : '' ?>>Fortnightly</option>
                        <option value="monthly" <?= $pay_duration === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                    </select>
                </div>
                <div>
                    <label class="gp-label">Select Groups</label>
                    <select class="gp-select" name="group" onchange="this.form.submit()">
                        <option value="all" <?= $selected_group === 'all' ? 'selected' : '' ?>>A, B, C, D</option>
                        <?php foreach ($available_groups as $group_value): ?>
                            <option value="<?= escape_html($group_value) ?>" <?= $selected_group === $group_value ? 'selected' : '' ?>>
                                <?= escape_html($group_value) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="gp-help">Selected: <?= escape_html($selected_group === 'all' ? 'A, B, C, D' : $selected_group) ?></div>
                </div>
            </div>
        </div>

        <div class="gp-panel">
            <div class="gp-panel-head">
                <span class="gp-step">02</span>
                <div class="gp-panel-title">Pay Calculation</div>
            </div>

            <div class="gp-table-wrap">
                <table class="gp-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Grp</th>
                            <th>Dept</th>
                            <th>Hrs</th>
                            <th>Leave</th>
                            <th>OT Hrs</th>
                            <th>Rate</th>
                            <th>Normal Wages</th>
                            <th>Leave Pay</th>
                            <th>Total Wages</th>
                            <th>PAYE</th>
                            <th>NRBF (Co)</th>
                            <th>NRBF (Emp)</th>
                            <th>OT</th>
                            <th>Bonus</th>
                            <th>Net Wages</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td style="font-weight:800;"><?= escape_html($row['name']) ?></td>
                                    <td><?= escape_html($row['grp']) ?></td>
                                    <td><?= escape_html($row['dept']) ?></td>
                                    <td><input type="number" step="0.01" min="0" class="gp-mini-input" name="hrs_<?= $row['employee_id'] ?>" value="<?= number_format((float) $row['hrs'], 2, '.', '') ?>" onchange="this.form.submit()"></td>
                                    <td><input type="number" step="0.01" min="0" class="gp-mini-input" name="leave_<?= $row['employee_id'] ?>" value="<?= number_format((float) $row['leave'], 2, '.', '') ?>" onchange="this.form.submit()"></td>
                                    <td><input type="number" step="0.01" min="0" class="gp-mini-input" name="ot_hrs_<?= $row['employee_id'] ?>" value="<?= number_format((float) $row['ot_hrs'], 2, '.', '') ?>" onchange="this.form.submit()"></td>
                                    <td class="gp-money gp-rate">$<?= number_format((float) $row['rate'], 2) ?></td>
                                    <td class="gp-money">$<?= number_format((float) $row['normal_wages'], 2) ?></td>
                                    <td class="gp-money">$<?= number_format((float) $row['leave_pay'], 2) ?></td>
                                    <td class="gp-money">$<?= number_format((float) $row['total_wages'], 2) ?></td>
                                    <td class="gp-money gp-paye">$<?= number_format((float) $row['paye'], 2) ?></td>
                                    <td class="gp-money">$<?= number_format((float) $row['nrbf_company'], 2) ?></td>
                                    <td class="gp-money gp-nrbf-emp">$<?= number_format((float) $row['nrbf_employee'], 2) ?></td>
                                    <td class="gp-money">$<?= number_format((float) $row['ot'], 2) ?></td>
                                    <td><input type="number" step="0.01" min="0" class="gp-mini-input" name="bonus_<?= $row['employee_id'] ?>" value="<?= number_format((float) $row['bonus'], 2, '.', '') ?>" onchange="this.form.submit()"></td>
                                    <td class="gp-money gp-net">$<?= number_format((float) $row['net_wages'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="16" class="text-center py-4">No active employees found for the selected groups.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="gp-formula">
                <i class="fas fa-thumbtack me-2"></i>Formula: Net Wages = Total Wages - PAYE - NRBF(Emp) + OT + Bonus. NRBF Company is employer contribution (7.5% on gross).
            </div>
        </div>

        <div class="gp-actions no-print">
            <div class="gp-btn-row">
                <button type="button" class="gp-btn gp-btn-primary" onclick="alert('Preview mode only. No payroll data will be saved.')"><i class="fas fa-save"></i> Save Pay Run</button>
                <button type="button" class="gp-btn" onclick="window.print()"><i class="fas fa-print"></i> Print Payslips</button>
            </div>
            <div class="gp-btn-row">
                <button type="button" class="gp-btn gp-btn-success" onclick="alert('Preview mode only. Approve & Post is disabled because this page does not save data.')"><i class="fas fa-check-square"></i> Approve & Post</button>
            </div>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
