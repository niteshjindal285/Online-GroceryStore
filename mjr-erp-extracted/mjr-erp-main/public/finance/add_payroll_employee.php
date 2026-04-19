<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add / Edit Payroll Employee';
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
    log_error('payroll_employees table ensure failed: ' . $e->getMessage());
}

$id = (int)get('id', 0);
$is_edit = $id > 0;
$errors = [];

$groups = ['A', 'B', 'C', 'D'];
$departments = ['Factory', 'Office', 'Accounts', 'HR', 'Store', 'Logistics'];

$employee = [
    'id' => 0,
    'employee_name' => '',
    'dob' => '',
    'date_started' => '',
    'date_ended' => '',
    'designation' => '',
    'emp_group' => '',
    'department' => '',
    'photo_path' => '',
    'gross_annual' => '',
    'weekly_pay' => '',
    'hourly_rate' => '',
    'standard_hours' => '',
    'paye_rate' => '',
    'nrbf_employee_rate' => '',
    'nrbf_company_rate' => '',
];

if ($is_edit) {
    $row = db_fetch("SELECT * FROM payroll_employees WHERE id = ? AND company_id = ? LIMIT 1", [$id, $company_id]);
    if ($row) {
        $employee = array_merge($employee, $row);
    } else {
        set_flash('Employee not found.', 'error');
        redirect('finance/add_payroll_employee.php');
    }
}

if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $id = (int)post('id', 0);
    $is_edit = $id > 0;

    $employee_name = trim((string)post('employee_name', ''));
    $dob = trim((string)post('dob', ''));
    $date_started = trim((string)post('date_started', ''));
    $date_ended = trim((string)post('date_ended', ''));
    $designation = trim((string)post('designation', ''));
    $emp_group = trim((string)post('emp_group', ''));
    $department = trim((string)post('department', ''));
    $gross_annual = (float)post('gross_annual', 0);
    $weekly_pay = (float)post('weekly_pay', 0);
    $hourly_rate = (float)post('hourly_rate', 0);
    $standard_hours = (float)post('standard_hours', 0);
    $paye_rate = (float)post('paye_rate', 0);
    $nrbf_employee_rate = (float)post('nrbf_employee_rate', 0);
    $nrbf_company_rate = (float)post('nrbf_company_rate', 0);

    if ($gross_annual <= 0 && $weekly_pay > 0) {
        $gross_annual = round($weekly_pay * 52, 2);
    }
    if ($weekly_pay <= 0 && $gross_annual > 0) {
        $weekly_pay = round($gross_annual / 52, 2);
    }
    if ($weekly_pay <= 0 && $hourly_rate > 0 && $standard_hours > 0) {
        $weekly_pay = round($hourly_rate * $standard_hours, 2);
    }
    if ($gross_annual <= 0 && $weekly_pay > 0) {
        $gross_annual = round($weekly_pay * 52, 2);
    }
    if ($hourly_rate <= 0 && $weekly_pay > 0 && $standard_hours > 0) {
        $hourly_rate = round($weekly_pay / $standard_hours, 2);
    }

    if ($employee_name === '') $errors['employee_name'] = 'Employee name is required.';
    if ($date_started === '') $errors['date_started'] = 'Date started is required.';
    if ($emp_group === '') $errors['emp_group'] = 'Group is required.';
    if ($department === '') $errors['department'] = 'Department is required.';
    if ($gross_annual <= 0) $errors['gross_annual'] = 'Gross salary must be greater than 0.';
    if ($weekly_pay < 0) $errors['weekly_pay'] = 'Weekly pay cannot be negative.';
    if ($hourly_rate < 0) $errors['hourly_rate'] = 'Hourly rate cannot be negative.';
    if ($standard_hours <= 0) $errors['standard_hours'] = 'Standard hours must be greater than 0.';
    if ($date_ended !== '' && $date_started !== '' && strtotime($date_ended) < strtotime($date_started)) {
        $errors['date_ended'] = 'Date ended cannot be before date started.';
    }
    $is_active = ($date_ended === '') ? 1 : 0;

    $photo_path = (string)post('existing_photo_path', '');
    $remove_photo = (int)post('remove_photo', 0) === 1;
    $photo = $_FILES['photo'] ?? null;
    if ($remove_photo) {
        $photo_path = '';
    }
    if (!$remove_photo && $photo && (int)($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ((int)$photo['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'Photo upload failed.';
        } else {
            $ext = strtolower(pathinfo((string)$photo['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                $errors['photo'] = 'Photo must be JPG, JPEG, PNG or WEBP.';
            }
            if ((int)$photo['size'] > 5 * 1024 * 1024) {
                $errors['photo'] = 'Photo must be 5MB or smaller.';
            }
        }
    }

    if (empty($errors) && !$remove_photo && $photo && (int)$photo['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/payroll_employees/';
        if (!is_dir($upload_dir)) @mkdir($upload_dir, 0775, true);
        if (is_dir($upload_dir) && is_writable($upload_dir)) {
            $safe_name = preg_replace('/[^A-Za-z0-9_-]/', '_', $employee_name);
            $ext = strtolower(pathinfo((string)$photo['name'], PATHINFO_EXTENSION));
            $stored = $safe_name . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
            $target = $upload_dir . $stored;
            if (move_uploaded_file((string)$photo['tmp_name'], $target)) {
                $photo_path = 'uploads/payroll_employees/' . $stored;
            } else {
                $errors['photo'] = 'Unable to store photo file.';
            }
        } else {
            $errors['photo'] = 'Upload directory is not writable.';
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            db_query("
                UPDATE payroll_employees
                SET employee_name = ?, dob = ?, date_started = ?, date_ended = ?, designation = ?,
                    emp_group = ?, department = ?, photo_path = ?, gross_annual = ?, weekly_pay = ?, hourly_rate = ?,
                    standard_hours = ?, paye_rate = ?, nrbf_employee_rate = ?, nrbf_company_rate = ?, is_active = ?,
                    updated_at = NOW()
                WHERE id = ? AND company_id = ?
            ", [
                $employee_name, $dob ?: null, $date_started, $date_ended ?: null, $designation ?: null,
                $emp_group, $department, $photo_path ?: null, $gross_annual, $weekly_pay, $hourly_rate,
                $standard_hours, $paye_rate, $nrbf_employee_rate, $nrbf_company_rate, $is_active,
                $id, $company_id
            ]);
            set_flash('Employee updated successfully.', 'success');
            redirect('finance/add_payroll_employee.php?id=' . $id);
        } else {
            $new_id = db_insert("
                INSERT INTO payroll_employees
                (company_id, employee_name, dob, date_started, date_ended, designation, emp_group, department, photo_path,
                 gross_annual, weekly_pay, hourly_rate, standard_hours, paye_rate, nrbf_employee_rate, nrbf_company_rate,
                 is_active, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $company_id, $employee_name, $dob ?: null, $date_started, $date_ended ?: null, $designation ?: null,
                $emp_group, $department, $photo_path ?: null, $gross_annual, $weekly_pay, $hourly_rate, $standard_hours,
                $paye_rate, $nrbf_employee_rate, $nrbf_company_rate, $is_active, current_user_id()
            ]);
            set_flash('Employee created successfully.', 'success');
            redirect('finance/add_payroll_employee.php?id=' . (int)$new_id);
        }
    }

    $employee = [
        'id' => $id,
        'employee_name' => $employee_name,
        'dob' => $dob,
        'date_started' => $date_started,
        'date_ended' => $date_ended,
        'designation' => $designation,
        'emp_group' => $emp_group,
        'department' => $department,
        'photo_path' => $photo_path,
        'gross_annual' => number_format($gross_annual, 2, '.', ''),
        'weekly_pay' => number_format($weekly_pay, 2, '.', ''),
        'hourly_rate' => number_format($hourly_rate, 2, '.', ''),
        'standard_hours' => number_format($standard_hours, 2, '.', ''),
        'paye_rate' => number_format($paye_rate, 2, '.', ''),
        'nrbf_employee_rate' => number_format($nrbf_employee_rate, 2, '.', ''),
        'nrbf_company_rate' => number_format($nrbf_company_rate, 2, '.', ''),
    ];
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
html[data-bs-theme="dark"],
html[data-app-theme="dark"] {
    --pe-bg: #080c1a;
    --pe-panel: #1d243c;
    --pe-panel-2: #1a2035;
    --pe-line: #313a61;
    --pe-cyan: #08d0ef;
    --pe-soft: #8f9dc5;
    --pe-text-header: #ffffff;
    --pe-label: #9aa9d1;
}

html[data-bs-theme="light"],
html[data-app-theme="light"] {
    --pe-bg: #f8f9fa;
    --pe-panel: #ffffff;
    --pe-panel-2: #f8f9fa;
    --pe-line: #e0e0e0;
    --pe-cyan: #0dcaf0;
    --pe-soft: #6c757d;
    --pe-text-header: #212529;
    --pe-label: #495057;
}

body { background: var(--pe-bg); color: var(--pe-soft); }
.pe-screen { border:1px solid rgba(8,208,239,.55); border-radius:10px; background:rgba(8,208,239,.07); color:var(--pe-cyan); font-weight:700; padding:.65rem 1rem; }
.pe-title { color: var(--pe-text-header); font-weight:800; margin-bottom:.1rem; }
.pe-back { border:1px solid var(--pe-line); border-radius:12px; color: var(--pe-soft); text-decoration:none; padding:.65rem 1.1rem; font-weight:700; }
.pe-panel { border-radius:12px; border:1px solid var(--pe-line); background:linear-gradient(180deg,var(--pe-panel),var(--pe-panel-2)); }
.pe-hd { border-bottom:1px solid var(--pe-line); padding:1rem 1.5rem; display:flex; align-items:center; justify-content:space-between; gap:1rem; }
.pe-step { width:34px; height:34px; border-radius:9px; background:#18c8e8; color:#04111c; font-weight:900; display:inline-flex; align-items:center; justify-content:center; margin-right:.8rem; }
.pe-hd-title { color: var(--pe-text-header); font-weight:800; font-size:1.95rem; margin:0; display:flex; align-items:center; }
html[data-bs-theme="dark"] .pe-input, html[data-bs-theme="dark"] .pe-select, html[data-app-theme="dark"] .pe-input, html[data-app-theme="dark"] .pe-select { width:100%; background:#252d4a; border:1px solid #344271; color:#eef3ff; border-radius:8px; padding:.58rem .8rem; }
html[data-bs-theme="light"] .pe-input, html[data-bs-theme="light"] .pe-select, html[data-app-theme="light"] .pe-input, html[data-app-theme="light"] .pe-select { width:100%; background:#ffffff; border:1px solid #dee2e6; color:#212529; border-radius:8px; padding:.58rem .8rem; }
html[data-bs-theme="dark"] .pe-input[readonly], html[data-app-theme="dark"] .pe-input[readonly] { color:#08d0ef; background:#1c3047; border-color:#0a7591; font-weight:700; }
html[data-bs-theme="light"] .pe-input[readonly], html[data-app-theme="light"] .pe-input[readonly] { color:#0b7ea0; background:#e7f5ff; border-color:#0aa8d4; font-weight:700; }
.pe-label { color: var(--pe-label); font-size:.84rem; margin-bottom:.3rem; font-weight:700; letter-spacing:.8px; text-transform:uppercase; }
.pe-photo {
    border: 2px dashed var(--pe-line); border-radius: 14px; min-height: 190px; display: flex; align-items: center; justify-content: center;
    color: var(--pe-soft); background: rgba(255,255,255,.01);
}
.pe-btn-save { border:0; border-radius:10px; background:#12c7df; color:#04111c; font-weight:800; padding:.65rem 1.2rem; }
.pe-btn-edit { border:1px solid var(--pe-line); border-radius:10px; background:transparent; color: var(--pe-soft); font-weight:800; padding:.65rem 1.2rem; text-decoration:none; }
.pe-panel,
.pe-hd-title,
.pe-title { color: var(--pe-text-header); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="pe-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Payroll - Add / Edit Employee</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="pe-title">+ Add New Employee</h2>
        </div>
        <a href="<?= url('finance/payroll.php') ?>" class="pe-back">&larr; Back to Payroll</a>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3"><?= escape_html($flash['message']) ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger mb-3">
            <?php foreach ($errors as $e): ?><div><?= escape_html($e) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="empForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="id" value="<?= (int)($employee['id'] ?? 0) ?>">
        <input type="hidden" name="existing_photo_path" value="<?= escape_html((string)($employee['photo_path'] ?? '')) ?>">
        <input type="hidden" name="remove_photo" id="remove_photo" value="0">

        <div class="pe-panel mb-4">
            <div class="pe-hd">
                <h4 class="pe-hd-title"><span class="pe-step">01</span>Employee Information</h4>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-xl-6">
                        <label class="pe-label">Employee Name <span class="text-danger">*</span></label>
                        <input type="text" class="pe-input" name="employee_name" value="<?= escape_html((string)$employee['employee_name']) ?>" placeholder="Full name" required>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="pe-label">Date of Birth</label>
                                <input type="date" class="pe-input" name="dob" value="<?= escape_html((string)$employee['dob']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="pe-label">Date Started <span class="text-danger">*</span></label>
                                <input type="date" class="pe-input" name="date_started" value="<?= escape_html((string)$employee['date_started']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="pe-label">Date Ended</label>
                                <input type="date" class="pe-input" name="date_ended" value="<?= escape_html((string)$employee['date_ended']) ?>" placeholder="Leave blank if active">
                            </div>
                            <div class="col-md-6">
                                <label class="pe-label">Designation</label>
                                <input type="text" class="pe-input" name="designation" value="<?= escape_html((string)$employee['designation']) ?>" placeholder="e.g. Operator, Accountant">
                            </div>
                            <div class="col-md-6">
                                <label class="pe-label">Group <span class="text-danger">*</span></label>
                                <select class="pe-select" name="emp_group" required>
                                    <option value="">Select Group</option>
                                    <?php foreach ($groups as $g): ?>
                                        <option value="<?= escape_html($g) ?>" <?= (string)$employee['emp_group'] === $g ? 'selected' : '' ?>><?= escape_html($g) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="pe-label">Department <span class="text-danger">*</span></label>
                                <select class="pe-select" name="department" required>
                                    <option value="">Select</option>
                                    <?php foreach ($departments as $d): ?>
                                        <option value="<?= escape_html($d) ?>" <?= (string)$employee['department'] === $d ? 'selected' : '' ?>><?= escape_html($d) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 d-flex align-items-center justify-content-center">
                        <div style="width:220px;">
                            <div class="pe-photo mb-2" id="photoPreviewBox">
                                <?php if (!empty($employee['photo_path'])): ?>
                                    <img src="<?= url($employee['photo_path']) ?>" alt="Photo" style="max-width:100%;max-height:180px;border-radius:10px;">
                                <?php else: ?>
                                    <div><i class="fas fa-camera me-2"></i>Photo</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" class="pe-input" name="photo" id="photoInput" accept=".jpg,.jpeg,.png,.webp">
                            <?php if (!empty($employee['photo_path'])): ?>
                                <button type="button" class="pe-btn-edit w-100 mt-2" id="removePhotoBtn">
                                    <i class="fas fa-trash-alt me-2"></i>Remove Photo
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pe-panel mb-4">
            <div class="pe-hd">
                <h4 class="pe-hd-title"><span class="pe-step">02</span>Salary & Deductions</h4>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="pe-label">Gross Salary (Annual) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" class="pe-input" id="gross_annual" name="gross_annual" value="<?= escape_html((string)$employee['gross_annual']) ?>" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">Weekly Pay</label>
                        <input type="number" step="0.01" min="0" class="pe-input" id="weekly_pay" name="weekly_pay" value="<?= escape_html((string)$employee['weekly_pay']) ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">Hourly Rate</label>
                        <input type="number" step="0.01" min="0" class="pe-input" id="hourly_rate" name="hourly_rate" value="<?= escape_html((string)$employee['hourly_rate']) ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">Standard Hours</label>
                        <input type="number" step="0.01" min="1" class="pe-input" id="standard_hours" name="standard_hours" value="<?= escape_html((string)$employee['standard_hours']) ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">PAYE Rate</label>
                        <input type="number" step="0.01" min="0" class="pe-input" name="paye_rate" value="<?= escape_html((string)$employee['paye_rate']) ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">NRBF - Employee (5%)</label>
                        <input type="number" step="0.01" min="0" class="pe-input" id="nrbf_emp" name="nrbf_employee_rate" value="<?= escape_html((string)$employee['nrbf_employee_rate']) ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="pe-label">NRBF - Company (7.5%)</label>
                        <input type="number" step="0.01" min="0" class="pe-input" id="nrbf_comp" name="nrbf_company_rate" value="<?= escape_html((string)$employee['nrbf_company_rate']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-start align-items-center gap-2">
            <button type="submit" class="pe-btn-save"><i class="fas fa-save me-2"></i>Save</button>
            <?php if (!empty($employee['id'])): ?>
                <a href="<?= url('finance/add_payroll_employee.php?id=' . (int)$employee['id']) ?>" class="pe-btn-edit"><i class="fas fa-pen me-2"></i>Edit</a>
            <?php else: ?>
                <a href="<?= url('finance/add_payroll_employee.php') ?>" class="pe-btn-edit"><i class="fas fa-pen me-2"></i>Edit</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
function parsePayrollValue(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    const raw = el.value.trim();
    if (raw === '') return null;
    const value = parseFloat(raw);
    return Number.isFinite(value) ? value : null;
}

function setPayrollValue(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value === null ? '' : Number(value).toFixed(2);
}

function recalcSalary(source = 'gross_annual') {
    const grossEl = document.getElementById('gross_annual');
    const hoursEl = document.getElementById('standard_hours');
    const weeklyEl = document.getElementById('weekly_pay');
    const hourlyEl = document.getElementById('hourly_rate');
    if (!grossEl || !hoursEl || !weeklyEl || !hourlyEl) return;

    const hours = parsePayrollValue('standard_hours');
    if (hours === null || hours <= 0) {
        if (source !== 'weekly_pay') {
            weeklyEl.value = '';
        }
        hourlyEl.value = '';
        return;
    }

    let gross = parsePayrollValue('gross_annual');
    let weekly = parsePayrollValue('weekly_pay');
    let hourly = parsePayrollValue('hourly_rate');

    if (source === 'gross_annual') {
        if (gross === null || gross < 0) {
            weeklyEl.value = '';
            hourlyEl.value = '';
            return;
        }
        weekly = gross / 52;
        hourly = weekly / hours;
        setPayrollValue('weekly_pay', weekly);
        setPayrollValue('hourly_rate', hourly);
        return;
    }

    if (source === 'weekly_pay') {
        if (weekly === null || weekly < 0) {
            grossEl.value = '';
            hourlyEl.value = '';
            return;
        }
        gross = weekly * 52;
        hourly = weekly / hours;
        setPayrollValue('gross_annual', gross);
        setPayrollValue('hourly_rate', hourly);
        return;
    }

    if (source === 'hourly_rate') {
        if (hourly === null || hourly < 0) {
            weeklyEl.value = '';
            grossEl.value = '';
            return;
        }
        weekly = hourly * hours;
        gross = weekly * 52;
        setPayrollValue('weekly_pay', weekly);
        setPayrollValue('gross_annual', gross);
        return;
    }

    if (source === 'standard_hours') {
        if (weekly !== null && weekly >= 0) {
            hourly = weekly / hours;
            setPayrollValue('hourly_rate', hourly);
            if (gross === null || gross <= 0) {
                gross = weekly * 52;
                setPayrollValue('gross_annual', gross);
            }
            return;
        }
        if (gross !== null && gross >= 0) {
            weekly = gross / 52;
            hourly = weekly / hours;
            setPayrollValue('weekly_pay', weekly);
            setPayrollValue('hourly_rate', hourly);
            return;
        }
        if (hourly !== null && hourly >= 0) {
            weekly = hourly * hours;
            gross = weekly * 52;
            setPayrollValue('weekly_pay', weekly);
            setPayrollValue('gross_annual', gross);
            return;
        }

        return;
    }
}

document.getElementById('gross_annual')?.addEventListener('input', () => recalcSalary('gross_annual'));
document.getElementById('weekly_pay')?.addEventListener('input', () => recalcSalary('weekly_pay'));
document.getElementById('hourly_rate')?.addEventListener('input', () => recalcSalary('hourly_rate'));
document.getElementById('standard_hours')?.addEventListener('input', () => recalcSalary('standard_hours'));
recalcSalary('standard_hours');

document.getElementById('photoInput')?.addEventListener('change', function (e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const box = document.getElementById('photoPreviewBox');
    if (!box) return;
    const removeInput = document.getElementById('remove_photo');
    if (removeInput) removeInput.value = '0';
    const url = URL.createObjectURL(file);
    box.innerHTML = '<img src="' + url + '" alt="Photo" style="max-width:100%;max-height:180px;border-radius:10px;">';
});

document.getElementById('removePhotoBtn')?.addEventListener('click', function () {
    const box = document.getElementById('photoPreviewBox');
    const input = document.getElementById('photoInput');
    const removeInput = document.getElementById('remove_photo');
    if (box) {
        box.innerHTML = '<div><i class="fas fa-camera me-2"></i>Photo</div>';
    }
    if (input) {
        input.value = '';
    }
    if (removeInput) {
        removeInput.value = '1';
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
