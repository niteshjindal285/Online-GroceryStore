<?php
/**
 * Edit Tax Class
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Tax Class - MJR Group ERP';

// Get tax class ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid tax class ID', 'error');
    redirect('finance/tax_classes.php');
}

// Get tax class details
try {
    $tax_class = db_fetch("SELECT * FROM tax_configurations WHERE id = ?", [$id]);
    if (!$tax_class) {
        throw new Exception('Tax class not found');
    }
} catch (Exception $e) {
    log_error("Error loading tax class: " . $e->getMessage());
    set_flash('Error loading tax class: ' . $e->getMessage(), 'error');
    redirect('finance/tax_classes.php');
}

// Get tax accounts
try {
    $tax_accounts = db_fetch_all("
        SELECT id, code, name 
        FROM accounts 
        WHERE is_active = 1 
        AND account_type IN ('liability', 'asset')
        ORDER BY code
    ");
} catch (Exception $e) {
    log_error("Error loading accounts: " . $e->getMessage());
    $tax_accounts = [];
}

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $tax_name = trim(post('tax_name', ''));
            $tax_code = trim(post('tax_code', ''));
            $tax_type = post('tax_type', '');
            $tax_rate = post('tax_rate', '');
            $tax_account_id = post('tax_account_id', '');
            
            $errors = [];
            if (empty($tax_name))       $errors['tax_name']       = err_required();
            if (empty($tax_code))       $errors['tax_code']       = err_required();
            if (empty($tax_type))       $errors['tax_type']       = err_required();
            if (empty($tax_rate))       $errors['tax_rate']       = err_required();
            if (empty($tax_account_id)) $errors['tax_account_id'] = err_required();
            
            if (empty($errors)) {
                // Validate tax rate
                if (!is_numeric($tax_rate) || floatval($tax_rate) < 0 || floatval($tax_rate) > 100) {
                    $errors['tax_rate'] = 'Tax rate must be a number between 0 and 100';
                }
            }
            
            if (empty($errors)) {
                // Convert tax rate from percentage to decimal
                $tax_rate_decimal = floatval($tax_rate) / 100.0;
                
                // Update tax class
                db_query("
                    UPDATE tax_configurations 
                    SET tax_name = ?, 
                        tax_code = ?, 
                        tax_type = ?, 
                        tax_rate = ?, 
                        tax_account_id = ?
                    WHERE id = ?
                ", [$tax_name, strtoupper($tax_code), $tax_type, $tax_rate_decimal, intval($tax_account_id), $id]);
                
                set_flash('Tax class updated successfully!', 'success');
                redirect('finance/tax_classes.php');
            } else {
                $error = err_required();
            }
            
        } catch (Exception $e) {
            log_error("Error updating tax class: " . $e->getMessage());
            set_flash(sanitize_db_error($e->getMessage()), 'error');
        }
    }
}

// Convert tax rate to percentage for display
$display_tax_rate = number_format(floatval($tax_class['tax_rate']) * 100, 2);

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

    .help-icon { color: #ffc107; font-size: 1.2rem; }
    .help-section { padding: 1.5rem; background: rgba(255, 255, 255, 0.02); border: 1px dashed rgba(255, 255, 255, 0.05); border-radius: 12px; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-edit me-2" style="color: #0dcaf0;"></i> Edit Tax Class</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/tax_classes.php') ?>" style="color: #8e8e9e; text-decoration: none;">Tax Classes</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page"><?= escape_html($tax_class['tax_name']) ?></li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-sliders-h me-2" style="color: #8e8e9e;"></i> Update Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label for="tax_name" class="form-label">Tax Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['tax_name']) ? 'is-invalid' : '' ?>" id="tax_name" name="tax_name" 
                                       value="<?= escape_html(post('tax_name', $tax_class['tax_name'])) ?>" required>
                                <?php if (isset($errors['tax_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['tax_name'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="tax_code" class="form-label">Tax Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase <?= isset($errors['tax_code']) ? 'is-invalid' : '' ?>" id="tax_code" name="tax_code" 
                                       value="<?= escape_html(post('tax_code', $tax_class['tax_code'])) ?>" required>
                                <?php if (isset($errors['tax_code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['tax_code'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label for="tax_type" class="form-label">Tax Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['tax_type']) ? 'is-invalid' : '' ?>" id="tax_type" name="tax_type" required>
                                    <option value="" disabled>Select Tax Type...</option>
                                    <option value="sales_tax" <?= (post('tax_type', $tax_class['tax_type']) == 'sales_tax') ? 'selected' : '' ?>>Sales Tax (Collected)</option>
                                    <option value="purchase_tax" <?= (post('tax_type', $tax_class['tax_type']) == 'purchase_tax') ? 'selected' : '' ?>>Purchase Tax (Paid)</option>
                                    <option value="withholding" <?= (post('tax_type', $tax_class['tax_type']) == 'withholding') ? 'selected' : '' ?>>Withholding Tax</option>
                                </select>
                                <?php if (isset($errors['tax_type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['tax_type'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="tax_rate" class="form-label">Tax Rate <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" max="100" class="form-control <?= isset($errors['tax_rate']) ? 'is-invalid' : '' ?>" 
                                           id="tax_rate" name="tax_rate" 
                                           value="<?= escape_html(post('tax_rate', $display_tax_rate)) ?>" required style="border-right: none;">
                                    <span class="input-group-text" style="background-color: transparent!important; border-left: none; color: #8e8e9e!important;"><i class="fas fa-percent"></i></span>
                                    <?php if (isset($errors['tax_rate'])): ?>
                                        <div class="invalid-feedback d-block mt-1"><?= $errors['tax_rate'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label for="tax_account_id" class="form-label">Linked Ledger Account <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['tax_account_id']) ? 'is-invalid' : '' ?>" id="tax_account_id" name="tax_account_id" required>
                                <option value="" disabled>Select Tax Account...</option>
                                <?php foreach ($tax_accounts as $account): ?>
                                    <option value="<?= $account['id'] ?>" 
                                            <?= (post('tax_account_id', $tax_class['tax_account_id']) == $account['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($account['code']) ?> &mdash; <?= escape_html($account['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['tax_account_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['tax_account_id'] ?></div>
                            <?php endif; ?>
                        </div>

                        <hr style="border-color: rgba(255,255,255,0.05); margin-bottom: 2rem;">

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="<?= url('finance/tax_classes.php') ?>" class="btn-cancel">
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

        <div class="col-xl-4">
            <div class="card border-0 h-100">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-info-circle me-2 help-icon"></i> Overview</h5>
                </div>
                <div class="card-body">
                    <div class="help-section mb-4 text-center">
                        <div style="font-size: 3rem; color: #0dcaf0; font-weight: 700; font-family: monospace;">
                            <?= number_format(floatval($tax_class['tax_rate']) * 100, 2) ?>%
                        </div>
                        <h6 class="text-white mt-2 mb-0">Current Tax Rate</h6>
                    </div>
                    
                    <div class="help-section">
                        <p class="mb-2"><strong class="text-white">Created On:</strong><br><span style="color: #8e8e9e;"><?= format_date($tax_class['created_at'], DISPLAY_DATETIME_FORMAT) ?></span></p>
                        <p class="mb-0"><strong class="text-white">Status:</strong><br>
                            <?php if ($tax_class['is_active']): ?>
                                <span class="badge mt-2" style="background: rgba(60, 197, 83, 0.15); color: #3cc553; border: 1px solid rgba(60, 197, 83, 0.3); padding: 8px 12px; font-size: 0.85rem;"><i class="fas fa-check-circle me-1"></i> Active</span>
                            <?php else: ?>
                                <span class="badge mt-2" style="background: rgba(255, 82, 82, 0.15); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.3); padding: 8px 12px; font-size: 0.85rem;"><i class="fas fa-times-circle me-1"></i> Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
