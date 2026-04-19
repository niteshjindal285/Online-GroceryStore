<?php

/**
 * Add Account Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Account - MJR Group ERP';
ensure_finance_approval_columns('accounts');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];
$errors = [];

// Get parent accounts for dropdown
$parent_accounts = db_fetch_all("SELECT id, code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code");

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        $code = trim(post('code', ''));
        $name = trim(post('name', ''));
        $account_type = post('account_type', '');
        $parent_id = post('parent_id') ?: null;
        $description = trim(post('description', ''));
        $approval_type = post('approval_type', 'manager');
        $manager_id = post('manager_id') ?: null;
        $admin_id = post('admin_id') ?: null;
        $is_active = 0;

        if (empty($code))         $errors['code']         = err_required();
        if (empty($name))         $errors['name']         = err_required();
        if (empty($account_type)) $errors['account_type'] = err_required();
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        if (empty($errors)) {
            // Check if account code already exists
            $exists = db_fetch("SELECT id FROM accounts WHERE code = ?", [$code]);
            if ($exists) {
                $errors['code'] = 'Account code already exists!';
            }
        }

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO accounts (code, name, account_type, parent_id, description, is_active, approval_status, approval_type, manager_id, admin_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())";
                db_insert($sql, [$code, $name, $account_type, $parent_id, $description, $is_active, $approval_type, $manager_id, $admin_id]);

                set_flash('Account submitted for approval.', 'success');
                redirect('accounts.php');
            } catch (Exception $e) {
                log_error("Error adding account: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .card-header { background-color: rgba(34, 34, 48, 0.6); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1.25rem 1.5rem; }
    .card-body { padding: 2rem; }
    
    .form-control, .form-select, .input-group-text { background-color: #1a1a24!important; border: 1px solid rgba(255,255,255,0.1)!important; color: #fff!important; border-radius: 8px; padding: 0.6rem 1rem; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    .form-label { color: #8e8e9e; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    
    .btn-create { background-color: #0dcaf0; color: #000; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; border: none; }
    .btn-create:hover { background-color: #0baccc; color: #000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3); }
    
    .btn-cancel { background-color: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; text-decoration: none; }
    .btn-cancel:hover { background-color: rgba(255,255,255,0.1); color: #fff; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-plus-circle me-2" style="color: #0dcaf0;"></i> Add New Account</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/accounts.php') ?>" style="color: #8e8e9e; text-decoration: none;">Chart of Accounts</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page">Add New</li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <div class="col-xl-8 offset-xl-2">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-sliders-h me-2" style="color: #8e8e9e;"></i> Account Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" required placeholder="e.g., 1000" value="<?= escape_html(post('code')) ?>">
                                <?php if (isset($errors['code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['code'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted mt-1 d-block">Unique account identifier</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" required placeholder="Enter account name" value="<?= escape_html(post('name')) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['account_type']) ? 'is-invalid' : '' ?>" name="account_type" required>
                                    <option value="" disabled selected>Select Type...</option>
                                    <option value="asset" <?= post('account_type') == 'asset' ? 'selected' : '' ?>>Asset</option>
                                    <option value="liability" <?= post('account_type') == 'liability' ? 'selected' : '' ?>>Liability</option>
                                    <option value="equity" <?= post('account_type') == 'equity' ? 'selected' : '' ?>>Equity</option>
                                    <option value="revenue" <?= post('account_type') == 'revenue' ? 'selected' : '' ?>>Revenue</option>
                                    <option value="expense" <?= post('account_type') == 'expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                                <?php if (isset($errors['account_type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['account_type'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Parent Account</label>
                                <select class="form-select" name="parent_id">
                                    <option value="">None (Top Level)</option>
                                    <?php foreach ($parent_accounts as $pa): ?>
                                        <option value="<?= $pa['id'] ?>" <?= post('parent_id') == $pa['id'] ? 'selected' : '' ?>>
                                            <?= escape_html($pa['code']) ?> &mdash; <?= escape_html($pa['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Account description"><?= post('description') ?></textarea>
                        </div>

                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Approval Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="approval_type" id="approval_type">
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
                            <a href="accounts.php" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check-circle me-2"></i>Create Account
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
