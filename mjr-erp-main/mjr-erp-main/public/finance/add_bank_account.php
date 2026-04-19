<?php
/**
 * Add Bank Account
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Bank Account - MJR Group ERP';
ensure_finance_approval_columns('bank_accounts');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];
$errors = [];

$gl_accounts = db_fetch_all("SELECT id, code, name FROM accounts WHERE account_type IN ('asset', 'liability') AND is_active = 1 ORDER BY code");

if (is_post()) {
    $csrf_token = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        $bank_name = trim(post('bank_name', ''));
        $account_name = trim(post('account_name', ''));
        $account_number = trim(post('account_number', ''));
        $currency = trim(post('currency', 'USD'));
        $current_balance = (float)post('current_balance', 0);
        $linked_gl_account_id = post('linked_gl_account_id') ?: null;
        $approval_type = post('approval_type', 'manager');
        $manager_id = post('manager_id') ?: null;
        $admin_id = post('admin_id') ?: null;

        if (empty($bank_name))      $errors['bank_name'] = err_required();
        if (empty($account_name))   $errors['account_name'] = err_required();
        if (empty($account_number)) $errors['account_number'] = err_required();
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM bank_accounts WHERE account_number = ?", [$account_number]);
            if ($exists) {
                $errors['account_number'] = 'This account number already exists!';
            }
        }

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO bank_accounts (company_id, bank_name, account_name, account_number, currency, current_balance, linked_gl_account_id, is_active, approval_status, approval_type, manager_id, admin_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())";
                db_insert($sql, [
                    1, // company_id
                    $bank_name, 
                    $account_name, 
                    $account_number, 
                    $currency, 
                    $current_balance, 
                    $linked_gl_account_id, 
                    0,
                    $approval_type,
                    $manager_id,
                    $admin_id
                ]);

                // Create audit log
                db_insert("INSERT INTO finance_audit_log (user_id, username, action, table_name, details) VALUES (?, ?, 'ADD_BANK_ACCOUNT', 'bank_accounts', ?)",
                    [$_SESSION['user_id'], $_SESSION['username'], "Added bank account $account_number ($bank_name)"]
                );

                set_flash('Bank account submitted for approval.', 'success');
                redirect('banking_deposits.php');
            } catch (Exception $e) {
                log_error("Error adding bank account: " . $e->getMessage());
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
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-university me-2" style="color: #0dcaf0;"></i> Add Bank Account</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/banking_deposits.php') ?>" style="color: #8e8e9e; text-decoration: none;">Banking</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page">Add New</li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <div class="col-xl-8 offset-xl-2">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-sliders-h me-2" style="color: #8e8e9e;"></i> Bank Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['bank_name']) ? 'is-invalid' : '' ?>" name="bank_name" required placeholder="e.g., Chase Bank" value="<?= escape_html(post('bank_name')) ?>">
                                <?php if (isset($errors['bank_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['bank_name'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['account_name']) ? 'is-invalid' : '' ?>" name="account_name" required placeholder="e.g., Operating Account" value="<?= escape_html(post('account_name')) ?>">
                                <?php if (isset($errors['account_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['account_name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['account_number']) ? 'is-invalid' : '' ?>" name="account_number" required placeholder="Enter account number" value="<?= escape_html(post('account_number')) ?>">
                                <?php if (isset($errors['account_number'])): ?>
                                    <div class="invalid-feedback"><?= $errors['account_number'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Currency <span class="text-danger">*</span></label>
                                <select class="form-select" name="currency" required>
                                    <option value="USD" <?= post('currency', 'USD') == 'USD' ? 'selected' : '' ?>>USD</option>
                                    <option value="EUR" <?= post('currency') == 'EUR' ? 'selected' : '' ?>>EUR</option>
                                    <option value="GBP" <?= post('currency') == 'GBP' ? 'selected' : '' ?>>GBP</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Starting Balance</label>
                                <input type="number" step="0.01" class="form-control" name="current_balance" value="<?= escape_html(post('current_balance', '0.00')) ?>">
                            </div>
                        </div>

                        <div class="mb-5">
                            <label class="form-label">Linked GL Account</label>
                            <select class="form-select" name="linked_gl_account_id">
                                <option value="">Select GL Account (Optional)</option>
                                <?php foreach ($gl_accounts as $gl): ?>
                                    <option value="<?= $gl['id'] ?>" <?= post('linked_gl_account_id') == $gl['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($gl['code']) ?> &mdash; <?= escape_html($gl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted mt-1 d-block">Links this bank to the General Ledger for reporting</small>
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
                            <a href="banking_deposits.php" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check-circle me-2"></i>Create Bank Account
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
