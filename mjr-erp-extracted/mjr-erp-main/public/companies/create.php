<?php
/**
 * Companies - Add New Company
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_super_admin();

$page_title = 'Add New Company - MJR Group ERP';
$errors = [];

// Get parent companies for dropdown
$parent_companies = db_fetch_all("SELECT id, name FROM companies WHERE type = 'parent' AND is_active = 1 ORDER BY name");

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
        
        $errors = [];
        if (empty($code)) $errors['code'] = 'Please fill Company Code that field';
        if (empty($name)) $errors['name'] = 'Please fill Company Name that field';
        if (empty($type)) $errors['type'] = 'Please fill Company Type that field';
        
        if ($type == 'subsidiary' && empty($parent_id)) {
            $errors['parent_id'] = 'Please fill Parent Company that field';
        }

        // Duplicate code check
        if (empty($errors)) {
            $dup = db_fetch("SELECT id FROM companies WHERE code = ?", [$code]);
            if ($dup) {
                $errors['code'] = "Company code '$code' is already in use.";
            }
        }

        if (empty($errors)) {
            try {
                $sql = "INSERT INTO companies (code, name, type, parent_id, email, phone, address, is_active, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $new_id = db_insert($sql, [$code, $name, $type, $parent_id, $email, $phone, $address, $is_active]);
                
                set_flash('Company added successfully!', 'success');
                redirect('view.php?id=' . $new_id);
                
            } catch (Exception $e) {
                log_error("Error adding company: " . $e->getMessage());
                set_flash('Error adding company: ' . $e->getMessage(), 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container pb-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3">
                    <h3 class="card-title mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Company</h3>
                </div>
                <div class="card-body p-4">

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="code" class="form-label fw-bold">Company Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= escape_html(post('code')) ?>" required maxlength="10" placeholder="e.g. MJR-HQ">
                                <?php if (isset($errors['code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['code'] ?></div>
                                <?php endif; ?>
                                <div class="form-text small text-muted">A unique identifier for this company.</div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="name" class="form-label fw-bold">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= escape_html(post('name')) ?>" required maxlength="100" placeholder="Full legal name">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row border-top pt-4 mt-2">
                            <div class="col-md-6 mb-4">
                                <label for="type" class="form-label fw-bold">Company Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['type']) ? 'is-invalid' : '' ?>" id="type" name="type" required>
                                    <option value="">Select Company Type</option>
                                    <option value="parent" <?= post('type') == 'parent' ? 'selected' : '' ?>>Parent Company</option>
                                    <option value="subsidiary" <?= post('type') == 'subsidiary' ? 'selected' : '' ?>>Subsidiary</option>
                                </select>
                                <?php if (isset($errors['type'])): ?>
                                    <div class="invalid-feedback"><?= $errors['type'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-4" id="parent_field" style="<?= post('type') == 'subsidiary' ? '' : 'display:none;' ?>">
                                <label for="parent_id" class="form-label fw-bold">Parent Company <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['parent_id']) ? 'is-invalid' : '' ?>" id="parent_id" name="parent_id">
                                    <option value="">Select Parent</option>
                                    <?php foreach ($parent_companies as $parent): ?>
                                        <option value="<?= $parent['id'] ?>" <?= post('parent_id') == $parent['id'] ? 'selected' : '' ?>><?= escape_html($parent['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['parent_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['parent_id'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row border-top pt-4">
                            <div class="col-md-6 mb-4">
                                <label for="phone" class="form-label fw-bold">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?= escape_html(post('phone')) ?>" maxlength="20">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <label for="email" class="form-label fw-bold">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= escape_html(post('email')) ?>" maxlength="120">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="address" class="form-label fw-bold">Office Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" placeholder="Full address including city and postal code"><?= escape_html(post('address')) ?></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <div class="card p-3 border-secondary bg-dark text-white">
                                <div class="form-check form-switch ms-1">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= !is_post() || post('is_active') ? 'checked' : '' ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                                    <label class="form-check-label fw-bold ms-3 d-inline-flex align-items-center gap-2" for="is_active" style="cursor: pointer; padding-top: 2px;">
                                        <span>Mark as Active</span>
                                        <span id="active_status_badge" class="badge <?= !is_post() || post('is_active') ? 'bg-success' : 'bg-secondary' ?>">
                                            <i class="fas <?= !is_post() || post('is_active') ? 'fa-check-circle' : 'fa-minus-circle' ?> me-1"></i>
                                            <?= !is_post() || post('is_active') ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </label>
                                    <div class="text-light opacity-75 small mt-1 ms-3">Active companies can be assigned to users and inventory.</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="index.php" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-5 shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>Create Company
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

    // Animate parent field if it appears
    $('#type').trigger('change');
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
