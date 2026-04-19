<?php
/**
 * Companies - Edit Company
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_admin();

$company_id = get_param('id');
if (!$company_id) {
    redirect('index.php');
}

$page_title = 'Edit Company - MJR Group ERP';
$errors = [];

// Get company
$company = db_fetch("SELECT * FROM companies WHERE id = ?", [$company_id]);

if (!$company) {
    set_flash('Company not found.', 'error');
    redirect('index.php');
}

enforce_company_access($company_id, 'index.php');

// Get parent companies
$parent_companies = is_super_admin()
    ? db_fetch_all("SELECT id, name FROM companies WHERE type = 'parent' AND is_active = 1 AND id != ? ORDER BY name", [$company_id])
    : [];

if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $code = trim(post('code', ''));
        $name = trim(post('name', ''));
        $type = post('type', '');
        $parent_id = post('parent_id') ?: null;
        $email = trim(post('email', ''));
        $phone = trim(post('phone', ''));
        $address = trim(post('address', ''));
        $is_active = post('is_active') ? 1 : 0;

        if (!is_super_admin()) {
            $type = $company['type'];
            $parent_id = $company['parent_id'];
            $is_active = $company['is_active'];
        }
        
        $errors = [];
        if (empty($code)) $errors['code'] = 'Please fill Company Code that field';
        if (empty($name)) $errors['name'] = 'Please fill Company Name that field';
        if (empty($type)) $errors['type'] = 'Please fill Company Type that field';
        
        if ($type == 'subsidiary' && empty($parent_id)) {
            $errors['parent_id'] = 'Please fill Parent Company that field';
        }

        // Duplicate code check (excluding current company)
        if (empty($errors)) {
            $dup = db_fetch("SELECT id FROM companies WHERE code = ? AND id != ?", [$code, $company_id]);
            if ($dup) {
                $errors['code'] = "Company code '$code' is already used by another company.";
            }
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE companies
                        SET code = ?, name = ?, type = ?, parent_id = ?, email = ?, phone = ?, address = ?, is_active = ?
                        WHERE id = ?";
                db_query($sql, [$code, $name, $type, $parent_id, $email, $phone, $address, $is_active, $company_id]);
                
                set_flash('Company updated successfully!', 'success');
                redirect('view.php?id=' . $company_id);
                
            } catch (Exception $e) {
                log_error("Error updating company: " . $e->getMessage());
                set_flash('Error updating company.', 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
        }
    }
}
 else {
    // Pre-populate form
    $_POST = $company;
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit me-2"></i>Edit Company</h3>
                </div>
                <div class="card-body">

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="code" class="form-label">Company Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= escape_html(post('code', $company['code'])) ?>" required maxlength="10">
                                <?php if (isset($errors['code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['code'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= escape_html(post('name', $company['name'])) ?>" required maxlength="100">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="type" class="form-label">Company Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="parent" <?= post('type', $company['type']) == 'parent' ? 'selected' : '' ?>>Parent Company</option>
                                    <option value="subsidiary" <?= post('type', $company['type']) == 'subsidiary' ? 'selected' : '' ?>>Subsidiary</option>
                                </select>
                                <?php if (isset($errors['type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['type'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3" id="parent_field" style="<?= post('type', $company['type']) == 'subsidiary' ? '' : 'display:none;' ?>">
                                <label for="parent_id" class="form-label">Parent Company <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['parent_id']) ? 'is-invalid' : '' ?>" id="parent_id" name="parent_id">
                                    <option value="">Select Parent</option>
                                    <?php foreach ($parent_companies as $parent): ?>
                                        <option value="<?= $parent['id'] ?>" <?= post('parent_id', $company['parent_id']) == $parent['id'] ? 'selected' : '' ?>><?= escape_html($parent['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['parent_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['parent_id'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= escape_html(post('phone', $company['phone'])) ?>" maxlength="20">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= escape_html(post('email', $company['email'])) ?>" maxlength="120">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= escape_html(post('address', $company['address'])) ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <div class="card p-3 border-secondary bg-dark text-white">
                                <div class="form-check form-switch ms-1">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= post('is_active', $company['is_active']) ? 'checked' : '' ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                                    <label class="form-check-label fw-bold ms-3 d-inline-flex align-items-center gap-2" for="is_active" style="cursor: pointer; padding-top: 2px;">
                                        <span>Mark as Active</span>
                                        <span id="active_status_badge" class="badge <?= post('is_active', $company['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                                            <i class="fas <?= post('is_active', $company['is_active']) ? 'fa-check-circle' : 'fa-minus-circle' ?> me-1"></i>
                                            <?= post('is_active', $company['is_active']) ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </label>
                                    <div class="text-light opacity-75 small mt-1 ms-3">Active companies can be assigned to users and inventory.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="view.php?id=<?= $company_id ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Company
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    function updateActiveBadge() {
        const isActive = $('#is_active').is(':checked');
        const badge = $('#active_status_badge');
        badge
            .toggleClass('bg-success', isActive)
            .toggleClass('bg-secondary', !isActive)
            .html(isActive
                ? '<i class=\"fas fa-check-circle me-1\"></i>Active'
                : '<i class=\"fas fa-minus-circle me-1\"></i>Inactive');
    }

    // Show/hide parent company field based on type selection
    $('#type').change(function() {
        if ($(this).val() === 'subsidiary') {
            $('#parent_field').show();
            $('#parent_id').prop('required', true);
        } else {
            $('#parent_field').hide();
            $('#parent_id').prop('required', false);
            $('#parent_id').val('');
        }
    });

    $('#is_active').on('change', updateActiveBadge);
    updateActiveBadge();
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
