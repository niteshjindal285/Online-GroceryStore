<?php
/**
 * Add New Fixed Asset
 * Register a company asset for depreciation tracking
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Fixed Asset - MJR Group ERP';

// Fetch companies for selection
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Fetch active managers and admins for approval selection
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 ORDER BY username");
$admins = db_fetch_all("SELECT id, username, full_name FROM users WHERE role IN ('company_admin', 'super_admin') AND is_active = 1 ORDER BY username");

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        db_begin_transaction();
        $asset_code       = trim(post('asset_code', ''));
        $asset_name       = trim(post('asset_name', ''));
        $description      = post('description', '');
        $company_id       = post('company_id', '');
        $purchase_date    = post('purchase_date', '');
        $purchase_price   = post('purchase_price', '');
        $salvage_value    = post('salvage_value', 0);
        $useful_life      = post('useful_life_years', '');
        $depr_method      = post('depreciation_method', 'straight_line');
        $location        = post('location', '');
        $approval_type   = post('approval_type', 'manager');
        $manager_id      = post('manager_id') ?: null;
        $admin_id        = post('admin_id') ?: null;


        $errors = [];
        if (empty($asset_code))       $errors['asset_code']        = err_required();
        if (empty($asset_name))       $errors['asset_name']        = err_required();
        if (empty($company_id))       $errors['company_id']        = err_required();
        if (empty($purchase_date))    $errors['purchase_date']     = err_required();
        if (empty($purchase_price))   $errors['purchase_price']    = err_required();
        if (empty($useful_life))      $errors['useful_life_years'] = err_required();

        if (empty($errors)) {
            // Check if code exists
            $exists = db_fetch("SELECT id FROM fixed_assets WHERE asset_code = ?", [$asset_code]);
            if ($exists) {
                $errors['asset_code'] = 'Asset code already exists!';
            }
        }

        if (empty($errors)) {
            // Validate numbers
            if ($purchase_price < 0) {
                $errors['purchase_price'] = 'Purchase price cannot be negative.';
            }
            if ($useful_life < 1) {
                $errors['useful_life_years'] = 'Useful life must be at least 1 year.';
            }
        }

        if (empty($errors)) {
            db_begin_transaction();
            try {
                // Insert asset
                $sql = "INSERT INTO fixed_assets 
                        (asset_code, asset_name, description, company_id, purchase_date, purchase_price, 
                         salvage_value, useful_life_years, depreciation_method, net_book_value, 
                         status, location, approval_type, manager_id, admin_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)";
                
                db_insert($sql, [
                    $asset_code, $asset_name, $description, $company_id, $purchase_date, $purchase_price,
                    $salvage_value, $useful_life, $depr_method, $purchase_price, 
                    $location, $approval_type, $manager_id, $admin_id
                ]);
                
                db_commit();
                set_flash('Fixed asset added successfully!', 'success');
                redirect('fixed_assets.php');
                
            } catch (Exception $e) {
                db_rollback();
                log_error("Error adding fixed asset: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        } else {
            $error = err_required();
        }
    }
}

// Generate default asset code
$last_asset = db_fetch("SELECT asset_code FROM fixed_assets ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last_asset && preg_match('/AST-(\d+)/', $last_asset['asset_code'], $matches)) {
    $next_num = intval($matches[1]) + 1;
}
$default_code = 'AST-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --fa-bg: #1a1a24;
        --fa-panel: #222230;
        --fa-text: #b0b0c0;
        --fa-text-white: #ffffff;
        --fa-border: rgba(255,255,255,0.05);
        --fa-input-bg: #1a1a24;
        --fa-input-border: rgba(255,255,255,0.1);
        --fa-label: #8e8e9e;
    }

    [data-bs-theme="light"] {
        --fa-bg: #f8f9fa;
        --fa-panel: #ffffff;
        --fa-text: #495057;
        --fa-text-white: #212529;
        --fa-border: #dee2e6;
        --fa-input-bg: #ffffff;
        --fa-input-border: #ced4da;
        --fa-label: #6c757d;
    }

    body { background-color: var(--fa-bg); color: var(--fa-text); }
    .card { background-color: var(--fa-panel); border-color: var(--fa-border); border-radius: 12px; }
    .card-header { background-color: var(--fa-panel)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--fa-border); }
    .card-body { padding: 2rem; }
    
    .form-control, .form-select, .input-group-text { background-color: var(--fa-input-bg)!important; border: 1px solid var(--fa-input-border)!important; color: var(--fa-text-white)!important; border-radius: 8px; padding: 0.6rem 1rem; }
    .input-group-text { color: var(--fa-label)!important; background-color: var(--fa-input-bg)!important; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    .form-label { color: var(--fa-label); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    
    .btn-create { background-color: #0dcaf0; color: #000; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; border: none; }
    .btn-create:hover { background-color: #0baccc; color: #000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3); }
    
    .btn-cancel { background-color: rgba(255,255,255,0.05); color: var(--fa-text-white); border: 1px solid var(--fa-input-border); padding: 0.6rem 1.5rem; border-radius: 8px; transition: all 0.3s ease; text-decoration: none; }
    .btn-cancel:hover { background-color: rgba(255,255,255,0.1); color: var(--fa-text-white); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="mb-5">
        <h2 class="mb-1 text-white fw-bold"><i class="fas fa-plus-circle me-2" style="color: #0dcaf0;"></i> Register Fixed Asset</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                <li class="breadcrumb-item"><a href="<?= url('finance/fixed_assets.php') ?>" style="color: #8e8e9e; text-decoration: none;">Fixed Assets</a></li>
                <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page">Add New</li>
            </ol>
        </nav>
    </div>

    <div class="row">
        <div class="col-xl-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-boxes me-2" style="color: #8e8e9e;"></i> Asset Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Asset Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['asset_code']) ? 'is-invalid' : '' ?>" name="asset_code" value="<?= post('asset_code', $default_code) ?>" required>
                                <?php if (isset($errors['asset_code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['asset_code'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['asset_name']) ? 'is-invalid' : '' ?>" name="asset_name" placeholder="e.g. CNC Machine - Model X" value="<?= escape_html(post('asset_name')) ?>" required>
                                <?php if (isset($errors['asset_name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['asset_name'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Company/Branch <span class="text-danger">*</span></label>
                                <?php if (!empty($_SESSION['company_id'])): ?>
                                    <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                    <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                <?php else: ?>
                                    <select name="company_id" class="form-select <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" required>
                                        <option value="">Select Company</option>
                                        <?php foreach ($companies as $comp): ?>
                                        <option value="<?= $comp['id'] ?>" <?= post('company_id') == $comp['id'] ? 'selected' : '' ?>><?= escape_html($comp['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['company_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['company_id'] ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="More details about the machine/vehicle"><?= escape_html(post('description')) ?></textarea>
                        </div>
                        
                        <hr style="border-color: rgba(255,255,255,0.05); margin: 2rem 0;">

                        <div class="row g-4 mb-5">
                            <div class="col-md-4">
                                <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?= isset($errors['purchase_date']) ? 'is-invalid' : '' ?>" name="purchase_date" value="<?= post('purchase_date', date('Y-m-d')) ?>" required>
                                <?php if (isset($errors['purchase_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['purchase_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Price <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control <?= isset($errors['purchase_price']) ? 'is-invalid' : '' ?>" name="purchase_price" step="0.01" min="0" value="<?= escape_html(post('purchase_price')) ?>" required>
                                    <?php if (isset($errors['purchase_price'])): ?>
                                        <div class="invalid-feedback"><?= $errors['purchase_price'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Salvage Value <span class="text-muted text-lowercase" style="font-size: 0.75rem;">(at end of life)</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" name="salvage_value" step="0.01" min="0" value="<?= escape_html(post('salvage_value', 0)) ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Useful Life (Years) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control <?= isset($errors['useful_life_years']) ? 'is-invalid' : '' ?>" name="useful_life_years" min="1" value="<?= escape_html(post('useful_life_years')) ?>" required>
                                <?php if (isset($errors['useful_life_years'])): ?>
                                    <div class="invalid-feedback"><?= $errors['useful_life_years'] ?></div>
                                <?php endif; ?>
                            </div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Approval Required By</label>
                                <select name="approval_type" class="form-select" id="approval_type" onchange="toggleApprovalFields()">
                                    <option value="manager" <?= post('approval_type') == 'manager' ? 'selected' : '' ?>>Manager Only</option>
                                    <option value="admin" <?= post('approval_type') == 'admin' ? 'selected' : '' ?>>Admin Only</option>
                                    <option value="both" <?= post('approval_type') == 'both' ? 'selected' : '' ?>>Both Manager & Admin</option>
                                </select>
                            </div>
                            <div class="col-md-4" id="manager_div">
                                <label class="form-label">Select Manager</label>
                                <select name="manager_id" class="form-select">
                                    <option value="">-- Choose Manager --</option>
                                    <?php foreach ($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>" <?= post('manager_id') == $m['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($m['full_name'] ?: $m['username']) ?> (ID: <?= $m['id'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4" id="admin_div">
                                <label class="form-label">Select Admin</label>
                                <select name="admin_id" class="form-select">
                                    <option value="">-- Choose Admin --</option>
                                    <?php foreach ($admins as $ad): ?>
                                    <option value="<?= $ad['id'] ?>" <?= post('admin_id') == $ad['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($ad['full_name'] ?: $ad['username']) ?> (ID: <?= $ad['id'] ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <script>
                        function toggleApprovalFields() {
                            const type = document.getElementById('approval_type').value;
                            document.getElementById('manager_div').style.display = (type === 'admin') ? 'none' : 'block';
                            document.getElementById('admin_div').style.display = (type === 'manager') ? 'none' : 'block';
                        }
                        document.addEventListener('DOMContentLoaded', toggleApprovalFields);
                        </script>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="fixed_assets.php" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-create">
                                <i class="fas fa-check-circle me-2"></i>Register Asset
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
