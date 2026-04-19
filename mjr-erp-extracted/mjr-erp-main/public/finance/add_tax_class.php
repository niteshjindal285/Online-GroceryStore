<?php

/**
 * Add Tax Class
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Tax Class - MJR Group ERP';
ensure_finance_approval_columns('tax_configurations');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];
$errors = [];

// Get tax accounts (liability accounts typically used for taxes)
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
            $approval_type = post('approval_type', 'manager');
            $manager_id = post('manager_id') ?: null;
            $admin_id = post('admin_id') ?: null;

            if (empty($tax_name))
                $errors['tax_name'] = err_required();
            if (empty($tax_code))
                $errors['tax_code'] = err_required();
            if (empty($tax_type))
                $errors['tax_type'] = err_required();
            if (empty($tax_rate))
                $errors['tax_rate'] = err_required();
            if (empty($tax_account_id))
                $errors['tax_account_id'] = err_required();
            $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

            if (empty($errors)) {
                // Validate tax rate
                if (!is_numeric($tax_rate) || floatval($tax_rate) < 0 || floatval($tax_rate) > 100) {
                    $errors['tax_rate'] = 'Tax rate must be a number between 0 and 100';
                }
            }

            if (empty($errors)) {
                // Convert tax rate from percentage to decimal
                $tax_rate_decimal = floatval($tax_rate) / 100.0;

                // Insert tax class
                db_query("
                    INSERT INTO tax_configurations 
                    (tax_name, tax_code, tax_type, tax_rate, tax_account_id, is_active, approval_status, approval_type, manager_id, admin_id, created_at)
                    VALUES (?, ?, ?, ?, ?, 0, 'pending', ?, ?, ?, NOW())
                ", [$tax_name, strtoupper($tax_code), $tax_type, $tax_rate_decimal, intval($tax_account_id), $approval_type, $manager_id, $admin_id]);

                set_flash('Tax class submitted for approval.', 'success');
                redirect('finance/tax_classes.php');
            } else {
                $error = err_required();
            }
        } catch (Exception $e) {
            log_error("Error adding tax class: " . $e->getMessage());
            set_flash(sanitize_db_error($e->getMessage()), 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    html[data-bs-theme="dark"],
    html[data-app-theme="dark"] {
        --tc-bg: #1a1a24;
        --tc-panel: #222230;
        --tc-text: #b0b0c0;
        --tc-text-white: #ffffff;
        --tc-border: rgba(255,255,255,0.05);
        --tc-input-bg: #1a1a24;
        --tc-input-border: rgba(255,255,255,0.1);
        --tc-label: #8e8e9e;
        --tc-help-bg: rgba(255, 146, 43, 0.05);
        --tc-help-border: rgba(255, 146, 43, 0.2);
    }

    html[data-bs-theme="light"],
    html[data-app-theme="light"] {
        --tc-bg: #f8f9fa;
        --tc-panel: #ffffff;
        --tc-text: #495057;
        --tc-text-white: #212529;
        --tc-border: #dee2e6;
        --tc-input-bg: #ffffff;
        --tc-input-border: #ced4da;
        --tc-label: #6c757d;
        --tc-help-bg: #fffbf1;
        --tc-help-border: #ffeeba;
        --tc-subtle: #5f6b7a;
    }

    body {
        background-color: var(--tc-bg);
        color: var(--tc-text);
    }

    .card {
        background-color: var(--tc-panel);
        border-color: var(--tc-border);
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .card-header {
        background-color: var(--tc-panel);
        border-bottom: 1px solid var(--tc-border);
        padding: 1.25rem 1.5rem;
    }

    .card-body {
        padding: 2rem;
    }

    .form-control,
    .form-select,
    .input-group-text {
        background-color: var(--tc-input-bg) !important;
        border: 1px solid var(--tc-input-border) !important;
        color: var(--tc-text-white) !important;
        border-radius: 8px;
        padding: 0.6rem 1rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #0dcaf0 !important;
        box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25) !important;
    }

    .form-label {
        color: var(--tc-label);
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.5rem;
    }

    .btn-create {
        background-color: #0dcaf0;
        color: #000;
        font-weight: 600;
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-create:hover {
        background-color: #0baccc;
        color: #000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3);
    }

    .btn-cancel {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--tc-text-white);
        border: 1px solid var(--tc-input-border);
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn-cancel:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: var(--tc-text-white);
    }

    .help-icon {
        color: #ff922b;
        font-size: 1.2rem;
    }

    .help-section {
        padding: 1.5rem;
        background: var(--tc-help-bg);
        border: 1px dashed var(--tc-help-border);
        border-radius: 12px;
    }

    .tc-heading,
    .tc-card-title,
    .tc-help-title,
    .tc-strong {
        color: var(--tc-text-white) !important;
    }

    .tc-muted,
    .tc-breadcrumb-link {
        color: var(--tc-subtle, var(--tc-label)) !important;
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 fw-bold tc-heading"><i class="fas fa-plus-circle me-2" style="color: #0dcaf0;"></i> Add Tax Class</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" class="tc-breadcrumb-link" style="text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/tax_classes.php') ?>" class="tc-breadcrumb-link" style="text-decoration: none;">Tax Classes</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page">Add New</li>
            </ol>
        </nav>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 tc-card-title"><i class="fas fa-sliders-h me-2 tc-muted"></i> Configuration Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label for="tax_name" class="form-label">Tax Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['tax_name']) ? 'is-invalid' : '' ?>" id="tax_name" name="tax_name"
                                    value="<?= escape_html(post('tax_name')) ?>" required placeholder="e.g., Value Added Tax">
                                <?php if (isset($errors['tax_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['tax_name'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="tax_code" class="form-label">Tax Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control text-uppercase <?= isset($errors['tax_code']) ? 'is-invalid' : '' ?>" id="tax_code" name="tax_code"
                                    value="<?= escape_html(post('tax_code')) ?>" required placeholder="e.g., VAT">
                                <?php if (isset($errors['tax_code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['tax_code'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-6">
                                <label for="tax_type" class="form-label">Tax Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['tax_type']) ? 'is-invalid' : '' ?>" id="tax_type" name="tax_type" required>
                                    <option value="" disabled selected>Select Tax Type...</option>
                                    <option value="sales_tax" <?= (post('tax_type') == 'sales_tax') ? 'selected' : '' ?>>Sales Tax (Collected)</option>
                                    <option value="purchase_tax" <?= (post('tax_type') == 'purchase_tax') ? 'selected' : '' ?>>Purchase Tax (Paid)</option>
                                    <option value="withholding" <?= (post('tax_type') == 'withholding') ? 'selected' : '' ?>>Withholding Tax</option>
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
                                        value="<?= escape_html(post('tax_rate')) ?>" required placeholder="0.00" style="border-right: none;">
                                    <span class="input-group-text" style="background-color: transparent!important; border-left: none; color: #8e8e9e!important;"><i class="fas fa-percent"></i></span>
                                    <?php if (isset($errors['tax_rate'])): ?>
                                        <div class="invalid-feedback d-block mt-1"><?= $errors['tax_rate'] ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted mt-1 d-block">Enter as percentage (e.g., 10 for 10%)</small>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label for="tax_account_id" class="form-label">Linked Ledger Account <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['tax_account_id']) ? 'is-invalid' : '' ?>" id="tax_account_id" name="tax_account_id" required>
                                <option value="" disabled selected>Select Tax Account...</option>
                                <?php foreach ($tax_accounts as $account): ?>
                                    <option value="<?= $account['id'] ?>"
                                        <?= (post('tax_account_id') == $account['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($account['code']) ?> &mdash; <?= escape_html($account['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['tax_account_id'])): ?>
                                <div class="invalid-feedback"><?= $errors['tax_account_id'] ?></div>
                            <?php endif; ?>
                            <small class="text-muted mt-1 d-block">This account will be credited/debited automatically when this tax is applied.</small>
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
                            <a href="<?= url('finance/tax_classes.php') ?>" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check-circle me-2"></i>Create Tax Class
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 tc-card-title"><i class="fas fa-info-circle me-2 help-icon"></i> Usage Guide</h5>
                </div>
                <div class="card-body">
                    <div class="help-section mb-4">
                        <h6 class="mb-2 tc-help-title"><i class="fas fa-tags me-2" style="color: #3cc553;"></i>Tax Types Explained</h6>
                        <ul class="mb-0 ps-3 tc-muted" style="font-size: 0.9rem;">
                            <li class="mb-2"><strong class="tc-strong">Sales Tax:</strong> Collected from customers. Usually linked to a Liability account.</li>
                            <li class="mb-2"><strong class="tc-strong">Purchase Tax:</strong> Paid to vendors. Often linked to an Asset or Expense account if recoverable/non-recoverable.</li>
                            <li><strong class="tc-strong">Withholding:</strong> Tax withheld from payments.</li>
                        </ul>
                    </div>

                    <div class="help-section mb-4">
                        <h6 class="mb-2 tc-help-title"><i class="fas fa-calculator me-2" style="color: #0dcaf0;"></i>Tax Rate Formatting</h6>
                        <p class="mb-0 tc-muted" style="font-size: 0.9rem;">Enter the numerical percentage value. The system will automatically convert this to decimal for calculations. (e.g., input <code>7.5</code> for 7.5% tax rate).</p>
                    </div>

                    <div class="help-section">
                        <h6 class="mb-2 tc-help-title"><i class="fas fa-book me-2" style="color: #9061f9;"></i>Account Linking</h6>
                        <p class="mb-0 tc-muted" style="font-size: 0.9rem;">Every tax class must map to a General Ledger account to ensure the Trial Balance and Income Statements reflect accurate liabilities.</p>
                    </div>
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
