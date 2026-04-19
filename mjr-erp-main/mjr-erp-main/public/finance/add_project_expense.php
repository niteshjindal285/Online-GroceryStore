<?php
/**
 * Add Project Expense
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Log Project Expense - MJR Group ERP';
ensure_finance_approval_columns('project_expenses');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];

// Load active projects
$projects = db_fetch_all("SELECT id, name FROM projects WHERE is_active = 1 ORDER BY name") ?? [];

// Auto-generate expense number
$last = db_fetch("SELECT expense_number FROM project_expenses ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last) {
    preg_match('/(\d+)$/', $last['expense_number'], $m);
    $next_num = (int)($m[1] ?? 0) + 1;
}
$default_expense_number = 'EXP-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);

$expense_categories = [
    'Labour', 'Materials', 'Equipment', 'Transport', 'Subcontractor',
    'Accommodation', 'Communication', 'Utilities', 'Permits & Fees', 'Other'
];

$errors = [];

if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $expense_number = trim(post('expense_number', ''));
        $expense_date   = post('expense_date', '');
        $project_id     = (int)post('project_id', 0);
        $category       = trim(post('category', ''));
        $amount         = (float)post('amount', 0);
        $description    = trim(post('description', ''));
        $approval_type  = post('approval_type', 'manager');
        $manager_id     = post('manager_id') ?: null;
        $admin_id       = post('admin_id') ?: null;

        if (empty($expense_number))  $errors['expense_number'] = err_required();
        if (empty($expense_date))    $errors['expense_date']   = err_required();
        if ($project_id <= 0)        $errors['project_id']     = 'Please select a valid project.';
        if ($amount <= 0)            $errors['amount']         = 'Amount must be greater than 0.';
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM project_expenses WHERE expense_number = ?", [$expense_number]);
            if ($exists) $errors['expense_number'] = 'This expense number already exists!';
        }

        // Verify project exists
        if (empty($errors) && $project_id > 0) {
            $proj = db_fetch("SELECT id FROM projects WHERE id = ?", [$project_id]);
            if (!$proj) $errors['project_id'] = 'Selected project not found.';
        }

        if (empty($errors)) {
            try {
                db_insert(
                    "INSERT INTO project_expenses (company_id, expense_number, expense_date, project_id, amount, category, description, status, approval_type, manager_id, admin_id, created_by, created_at)
                     VALUES (1, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, NOW())",
                    [$expense_number, $expense_date, $project_id, $amount, $category ?: null, $description, $approval_type, $manager_id, $admin_id, $_SESSION['user_id']]
                );
                set_flash('Project expense ' . $expense_number . ' logged successfully!', 'success');
                redirect('project_expenses.php');
            } catch (Exception $e) {
                log_error("Error creating project expense: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --pe-bg: #1a1a24;
        --pe-panel: #222230;
        --pe-text: #b0b0c0;
        --pe-text-white: #ffffff;
        --pe-border: rgba(255,255,255,0.05);
        --pe-input-bg: #1a1a24;
        --pe-input-border: rgba(255,255,255,0.1);
        --pe-label: #8e8e9e;
        --pe-chip-bg: rgba(255,255,255,0.03);
        --pe-chip-border: rgba(255,255,255,0.1);
    }

    [data-bs-theme="light"] {
        --pe-bg: #f8f9fa;
        --pe-panel: #ffffff;
        --pe-text: #495057;
        --pe-text-white: #212529;
        --pe-border: #dee2e6;
        --pe-input-bg: #ffffff;
        --pe-input-border: #ced4da;
        --pe-label: #6c757d;
        --pe-chip-bg: #f8f9fa;
        --pe-chip-border: #dee2e6;
    }

    body { background-color: var(--pe-bg); color: var(--pe-text); }
    .card { background-color: var(--pe-panel); border-color: var(--pe-border); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .card-header { background-color: var(--pe-panel); border-bottom: 1px solid var(--pe-border); padding: 1.25rem 1.5rem; }
    .card-body { padding: 2rem; }
    .form-control, .form-select { background-color: var(--pe-input-bg)!important; border: 1px solid var(--pe-input-border)!important; color: var(--pe-text-white)!important; border-radius: 8px; padding: 0.6rem 1rem; }
    .form-control:focus, .form-select:focus { border-color: #e83e8c!important; box-shadow: 0 0 0 0.25rem rgba(232, 62, 140, 0.25)!important; }
    .form-label { color: var(--pe-label); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .btn-create { background-color: #e83e8c; color: #fff; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; border: none; }
    .btn-create:hover { background-color: #d63384; color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(232, 62, 140, 0.3); }
    .btn-cancel { background-color: rgba(255,255,255,0.05); color: var(--pe-text-white); border: 1px solid var(--pe-input-border); padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; text-decoration: none; }
    .btn-cancel:hover { background-color: rgba(255,255,255,0.1); color: var(--pe-text-white); }

    /* Category chips */
    .category-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .chip-input { display: none; }
    .chip-label {
        cursor: pointer; padding: 0.35rem 0.85rem; border-radius: 20px; font-size: 0.82rem;
        border: 1px solid var(--pe-chip-border); background: var(--pe-chip-bg);
        color: var(--pe-label); transition: all 0.2s;
    }
    .chip-input:checked + .chip-label { background: rgba(232, 62, 140, 0.15); border-color: #e83e8c; color: #e83e8c; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-project-diagram me-2" style="color: #e83e8c;"></i> Log Project Expense</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/project_expenses.php') ?>" style="color: #8e8e9e; text-decoration: none;">Project Expenses</a></li>
                <li class="breadcrumb-item active" style="color: #e83e8c;" aria-current="page">Log New</li>
            </ol>
        </nav>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4">
            <?= escape_html($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($projects)): ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            No active projects found. Please create a project first before logging expenses.
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-8 offset-xl-2">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-sliders-h me-2" style="color: #8e8e9e;"></i> Expense Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <!-- Ref & Date -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Expense Number <span class="text-danger">*</span></label>
                                <input type="text" name="expense_number"
                                       class="form-control <?= isset($errors['expense_number']) ? 'is-invalid' : '' ?>"
                                       value="<?= escape_html(post('expense_number', $default_expense_number)) ?>" required>
                                <?php if (isset($errors['expense_number'])): ?><div class="invalid-feedback"><?= $errors['expense_number'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="expense_date"
                                       class="form-control <?= isset($errors['expense_date']) ? 'is-invalid' : '' ?>"
                                       value="<?= escape_html(post('expense_date', date('Y-m-d'))) ?>" required>
                                <?php if (isset($errors['expense_date'])): ?><div class="invalid-feedback"><?= $errors['expense_date'] ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Project & Amount -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-7">
                                <label class="form-label">Project <span class="text-danger">*</span></label>
                                <select name="project_id" class="form-select <?= isset($errors['project_id']) ? 'is-invalid' : '' ?>" required>
                                    <option value="">— Select Project —</option>
                                    <?php foreach ($projects as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= post('project_id') == $p['id'] ? 'selected' : '' ?>><?= escape_html($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['project_id'])): ?><div class="invalid-feedback"><?= $errors['project_id'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="amount"
                                       class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                       value="<?= escape_html(post('amount', '')) ?>" placeholder="0.00" required>
                                <?php if (isset($errors['amount'])): ?><div class="invalid-feedback"><?= $errors['amount'] ?></div><?php endif; ?>
                            </div>
                        </div>

                        <!-- Category Chips -->
                        <div class="mb-4">
                            <label class="form-label d-block">Category</label>
                            <div class="category-chips">
                                <?php foreach ($expense_categories as $cat): ?>
                                    <input type="radio" class="chip-input" name="category" id="cat_<?= preg_replace('/\W+/', '_', $cat) ?>" value="<?= $cat ?>"
                                           <?= post('category') === $cat ? 'checked' : '' ?>>
                                    <label class="chip-label" for="cat_<?= preg_replace('/\W+/', '_', $cat) ?>"><?= $cat ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-5">
                            <label class="form-label">Description / Notes</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Describe this expense..."><?= escape_html(post('description')) ?></textarea>
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Approval Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['approval_type']) ? 'is-invalid' : '' ?>" name="approval_type" id="approval_type">
                                    <option value="manager" <?= post('approval_type', 'manager') === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    <option value="admin" <?= post('approval_type') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="both" <?= post('approval_type') === 'both' ? 'selected' : '' ?>>Manager + Admin</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="manager_group">
                                <label class="form-label">Manager</label>
                                <select class="form-select <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" name="manager_id">
                                    <option value="">Select Manager</option>
                                    <?php foreach ($managers as $m): ?>
                                        <?php $manager_name = trim((string)($m['full_name'] ?? '')) ?: (string)$m['username']; ?>
                                        <option value="<?= $m['id'] ?>" <?= post('manager_id') == $m['id'] ? 'selected' : '' ?>><?= escape_html($manager_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['manager_id'])): ?><div class="invalid-feedback"><?= $errors['manager_id'] ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-4" id="admin_group">
                                <label class="form-label">Admin</label>
                                <select class="form-select <?= isset($errors['admin_id']) ? 'is-invalid' : '' ?>" name="admin_id">
                                    <option value="">Select Admin</option>
                                    <?php foreach ($admins as $a): ?>
                                        <?php $admin_name = trim((string)($a['full_name'] ?? '')) ?: (string)$a['username']; ?>
                                        <option value="<?= $a['id'] ?>" <?= post('admin_id') == $a['id'] ? 'selected' : '' ?>><?= escape_html($admin_name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['admin_id'])): ?><div class="invalid-feedback"><?= $errors['admin_id'] ?></div><?php endif; ?>
                            </div>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.05); margin-bottom: 2rem;">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="project_expenses.php" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check-circle me-2"></i>Log Expense
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleApprovalColumns() {
    const type = document.getElementById('approval_type')?.value || 'manager';
    const managerGroup = document.getElementById('manager_group');
    const adminGroup = document.getElementById('admin_group');
    if (!managerGroup || !adminGroup) return;
    managerGroup.style.display = (type === 'manager' || type === 'both') ? '' : 'none';
    adminGroup.style.display = (type === 'admin' || type === 'both') ? '' : 'none';
}
document.getElementById('approval_type')?.addEventListener('change', toggleApprovalColumns);
toggleApprovalColumns();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
