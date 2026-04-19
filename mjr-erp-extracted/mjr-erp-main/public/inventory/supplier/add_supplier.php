<?php
/**
 * Add Supplier
 * Create new supplier for purchasing
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('manage_procurement');

$page_title = 'Add Supplier - MJR Group ERP';

$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company before adding a supplier.', 'warning');
    redirect(url('index.php'));
}

// RESTRICTION: No creation at HQ
if (is_hq()) {
    set_flash('Supplier creation is not allowed at the HQ level. All suppliers must be created at the subsidiary level.', 'error');
    redirect('suppliers.php');
}

$has_company_id = suppliers_table_has_company_id();

if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $errors = [];
        try {
            $supplier_code = post('supplier_code');
            $name = post('name');
            $contact_person = post('contact_person');
            $email = post('email');
            $phone = post('phone');
            $address = post('address');
            $city = post('city');
            $country = post('country');
            $payment_terms = post('payment_terms');
            $tax_id = post('tax_id');
            $is_active = has_post('is_active') ? 1 : 0;
            $notes = post('notes');
            
            // Validate
            if (empty($supplier_code)) $errors['supplier_code'] = err_required();
            if (empty($name))          $errors['name']          = err_required();
            
            if (!empty($errors)) {
                throw new Exception(err_required());
            }
            
            // Check if code exists globally (group-wide)
            $global_check = db_fetch("SELECT s.*, c.name as company_name FROM suppliers s LEFT JOIN companies c ON s.company_id = c.id WHERE s.supplier_code = ? LIMIT 1", [$supplier_code]);
            
            if ($global_check) {
                if ((int)$global_check['company_id'] === (int)$company_id) {
                    $errors['supplier_code'] = 'Supplier code already exists for your company';
                } else {
                    $errors['supplier_code'] = "This supplier already exists in <strong>{$global_check['company_name']}</strong>. Please use the <strong><a href='import_supplier.php?search=" . urlencode($supplier_code) . "'>Import</a></strong> feature instead of creating a duplicate.";
                }
                throw new Exception('A supplier with this code already exists in the system.');
            }
            
            // Insert supplier for the currently selected company
            $fields = 'supplier_code, name, contact_person, email, phone, address, city, country, payment_terms, tax_id, is_active, notes';
            $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
            $params = [
                $supplier_code,
                $name,
                $contact_person,
                $email,
                $phone,
                $address,
                $city,
                $country,
                $payment_terms,
                $tax_id,
                $is_active,
                $notes
            ];

            if ($has_company_id) {
                $fields .= ', company_id';
                $placeholders .= ', ?';
                $params[] = $company_id;
            }

            $sql = "INSERT INTO suppliers ($fields) VALUES ($placeholders)";
            db_query($sql, $params);
            
            set_flash('Supplier added successfully!', 'success');
            redirect('suppliers.php');
            
        } catch (Exception $e) {
            set_flash(sanitize_db_error($e->getMessage()), 'error');
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-plus me-2"></i>Add Supplier</h2>
        </div>
        <div class="col-auto">
            <a href="suppliers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Suppliers
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <h5 class="mb-3">Basic Information</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="supplier_code" class="form-label">Supplier Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['supplier_code']) ? 'is-invalid' : '' ?>" id="supplier_code" name="supplier_code" value="<?= escape_html(post('supplier_code')) ?>" required>
                        <?php if (isset($errors['supplier_code'])): ?>
                            <div class="invalid-feedback"><?= $errors['supplier_code'] ?></div>
                        <?php endif; ?>
                        <small class="text-muted">Unique identifier for this supplier</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Supplier Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= escape_html(post('name')) ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?= $errors['name'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contact_person" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="tax_id" class="form-label">Tax ID / VAT Number</label>
                        <input type="text" class="form-control" id="tax_id" name="tax_id">
                    </div>
                </div>
                
                <h5 class="mb-3 mt-4">Address Information</h5>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="city" class="form-label">City</label>
                        <input type="text" class="form-control" id="city" name="city">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="country" class="form-label">Country</label>
                        <input type="text" class="form-control" id="country" name="country">
                    </div>
                </div>
                
                <h5 class="mb-3 mt-4">Payment & Other Details</h5>
                <div class="mb-3">
                    <label for="payment_terms" class="form-label">Payment Terms</label>
                    <select class="form-select" id="payment_terms" name="payment_terms">
                        <option value="">Select Payment Terms</option>
                        <option value="Net 30">Net 30</option>
                        <option value="Net 60">Net 60</option>
                        <option value="Net 90">Net 90</option>
                        <option value="COD">Cash on Delivery</option>
                        <option value="Advance">Advance Payment</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <div class="card p-3 border-secondary bg-dark text-white">
                        <div class="form-check form-switch ms-1">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= (is_post() ? (has_post('is_active') ? 'checked' : '') : 'checked') ?> style="width: 2.5rem; height: 1.25rem; cursor: pointer;">
                            <label class="form-check-label fw-bold ms-3 d-inline-flex align-items-center gap-2" for="is_active" style="cursor: pointer; padding-top: 2px;">
                                <span>Active Supplier</span>
                                <span id="supplier_active_badge" class="badge <?= (is_post() ? (has_post('is_active') ? 'bg-success' : 'bg-secondary') : 'bg-success') ?>">
                                    <i class="fas <?= (is_post() ? (has_post('is_active') ? 'fa-check-circle' : 'fa-minus-circle') : 'fa-check-circle') ?> me-1"></i>
                                    <?= (is_post() ? (has_post('is_active') ? 'Active' : 'Inactive') : 'Active') ?>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <a href="suppliers.php" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Add Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    function updateSupplierActiveBadge() {
        const isActive = $('#is_active').is(':checked');
        const badge = $('#supplier_active_badge');
        badge
            .toggleClass('bg-success', isActive)
            .toggleClass('bg-secondary', !isActive)
            .html(isActive
                ? '<i class=\"fas fa-check-circle me-1\"></i>Active'
                : '<i class=\"fas fa-minus-circle me-1\"></i>Inactive');
    }

    $('#is_active').on('change', updateSupplierActiveBadge);
    updateSupplierActiveBadge();
});
</script>
";
include __DIR__ . '/../../../templates/footer.php';
?>
