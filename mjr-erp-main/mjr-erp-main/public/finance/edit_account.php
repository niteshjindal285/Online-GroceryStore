<?php
/**
 * Edit Account Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Account - MJR Group ERP';

// Get account ID from URL
$account_id = get('id');
if (!$account_id) {
    set_flash('Account ID not provided.', 'error');
    redirect('accounts.php');
}

// Get account data
$account = db_fetch("SELECT * FROM accounts WHERE id = ?", [$account_id]);
if (!$account) {
    set_flash('Account not found.', 'error');
    redirect('accounts.php');
}

// Get parent accounts for dropdown (exclude current account and its children)
$parent_accounts = db_fetch_all("SELECT id, code, name, account_type FROM accounts WHERE is_active = 1 AND id != ? ORDER BY code", [$account_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $code = trim(post('code', ''));
        $name = trim(post('name', ''));
        $account_type = post('account_type', '');
        $parent_id = post('parent_id') ?: null;
        $description = trim(post('description', ''));
        $is_active = post('is_active') ? 1 : 0;

        $errors = [];
        if (empty($code))         $errors['code']         = err_required();
        if (empty($name))         $errors['name']         = err_required();
        if (empty($account_type)) $errors['account_type'] = err_required();

        if (empty($errors)) {
            // Check if account code already exists for other accounts
            $exists = db_fetch("SELECT id FROM accounts WHERE code = ? AND id != ?", [$code, $account_id]);
            if ($exists) {
                $errors['code'] = 'Account code already exists!';
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE accounts SET 
                        code = ?, name = ?, account_type = ?, parent_id = ?, description = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                db_query($sql, [$code, $name, $account_type, $parent_id, $description, $is_active, $account_id]);
                
                set_flash('Account updated successfully!', 'success');
                redirect('accounts.php');
            } catch (Exception $e) {
                log_error("Error updating account: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: var(--mjr-deep-bg); color: var(--mjr-text); }
    .card { background-color: var(--mjr-card-bg); border: 1px solid var(--mjr-border); border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); }
    .card-header { background-color: rgba(34, 34, 48, 0.4); border-bottom: 1px solid var(--mjr-border); padding: 1.25rem 1.5rem; }
    .card-body { padding: 2rem; }
    
    [data-bs-theme="dark"] .form-control, 
    [data-bs-theme="dark"] .form-select, 
    [data-bs-theme="dark"] .form-check-input { 
        background-color: #1a1a24!important; 
        border: 1px solid rgba(255,255,255,0.1)!important; 
        color: #fff!important; 
    }
    
    .form-control, .form-select, .form-check-input { border-radius: 8px; padding: 0.6rem 1rem; }
    .form-check-input { padding: 0.5rem; }
    .form-control:focus, .form-select:focus, .form-check-input:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    .form-label, .form-check-label { color: var(--mjr-text); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    .form-check-input:checked { background-color: #0dcaf0!important; border-color: #0dcaf0!important; }
    
    .btn-create { background-color: #0dcaf0; color: #000; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; border: none; }
    .btn-create:hover { background-color: #0baccc; color: #000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3); }
    
    .btn-cancel { background-color: rgba(128, 128, 128, 0.1); color: var(--mjr-text-header); border: 1px solid var(--mjr-border); padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; text-decoration: none; }
    .btn-cancel:hover { background-color: rgba(128, 128, 128, 0.2); color: var(--mjr-text-header); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-edit me-2" style="color: #0dcaf0;"></i> Edit Account</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/accounts.php') ?>" style="color: #8e8e9e; text-decoration: none;">Chart of Accounts</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page"><?= escape_html($account['name']) ?></li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <div class="col-xl-8 offset-xl-2">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-sliders-h me-2" style="color: #8e8e9e;"></i> Update Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" name="code" required value="<?= escape_html(post('code', $account['code'])) ?>">
                                <?php if (isset($errors['code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['code'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Account Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" required value="<?= escape_html(post('name', $account['name'])) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Account Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['account_type']) ? 'is-invalid' : '' ?>" name="account_type" required>
                                    <option value="" disabled>Select Type...</option>
                                    <option value="asset" <?= post('account_type', $account['account_type']) == 'asset' ? 'selected' : '' ?>>Asset</option>
                                    <option value="liability" <?= post('account_type', $account['account_type']) == 'liability' ? 'selected' : '' ?>>Liability</option>
                                    <option value="equity" <?= post('account_type', $account['account_type']) == 'equity' ? 'selected' : '' ?>>Equity</option>
                                    <option value="revenue" <?= post('account_type', $account['account_type']) == 'revenue' ? 'selected' : '' ?>>Revenue</option>
                                    <option value="expense" <?= post('account_type', $account['account_type']) == 'expense' ? 'selected' : '' ?>>Expense</option>
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
                                    <option value="<?= $pa['id'] ?>" <?= post('parent_id', $account['parent_id']) == $pa['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($pa['code']) ?> &mdash; <?= escape_html($pa['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"><?= escape_html(post('description', $account['description'] ?? '')) ?></textarea>
                        </div>
                        
                        <div class="mb-5 p-3" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 8px;">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input ms-0" type="checkbox" name="is_active" id="is_active" <?= $account['is_active'] ? 'checked' : '' ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                                <label class="form-check-label fw-bold ms-3" for="is_active" style="display:inline-block; cursor: pointer; padding-top: 2px;">
                                    Make Account Active
                                </label>
                            </div>
                        </div>
                        
                        <hr style="border-color: rgba(255,255,255,0.05); margin-bottom: 2rem;">

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="accounts.php" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-save me-2"></i>Update Configuration
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
