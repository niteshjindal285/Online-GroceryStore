<?php
/**
 * Add Customer Page
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('manage_sales');

$page_title = 'Add Customer - MJR Group ERP';

$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company before adding a customer.', 'warning');
    redirect(url('index.php'));
}

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $customer_code = trim(post('customer_code', '')); // Need to get it even if empty to check
        $name = trim(post('name', ''));
        $email = trim(post('email', ''));
        $phone = trim(post('phone', ''));
        $payment_terms = trim(post('payment_terms', ''));
        $address = trim(post('address', ''));
        $city = trim(post('city', ''));
        $state = trim(post('state', ''));
        $postal_code = trim(post('postal_code', ''));
        $country = trim(post('country', ''));
        $credit_limit = (float)post('credit_limit', 0);
        $default_discount_percent = (float)post('default_discount_percent', 0);
        $default_discount_amount = (float)post('default_discount_amount', 0);

        $errors = [];
        if (empty($customer_code)) $errors['customer_code'] = err_required();
        if (empty($name))          $errors['name']          = err_required();
        if (empty($email))         $errors['email']         = err_required();

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM customers WHERE customer_code = ?", [$customer_code]);
            if ($exists) {
                $errors['customer_code'] = 'Customer code already exists!';
            }
        }

        if (empty($errors)) {
            try {
                if (customers_table_has_discounts()) {
                    $sql = "INSERT INTO customers (customer_code, code, name, email, phone, payment_terms, address, city, state, postal_code, country, credit_limit, default_discount_percent, default_discount_amount, is_active, created_at, company_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
                    db_insert($sql, [$customer_code, $customer_code, $name, $email, $phone, $payment_terms, $address, $city, $state, $postal_code, $country, $credit_limit, $default_discount_percent, $default_discount_amount, $company_id]);
                } else {
                    $sql = "INSERT INTO customers (customer_code, code, name, email, phone, payment_terms, address, city, state, postal_code, country, credit_limit, is_active, created_at, company_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?)";
                    db_insert($sql, [$customer_code, $customer_code, $name, $email, $phone, $payment_terms, $address, $city, $state, $postal_code, $country, $credit_limit, $company_id]);
                }
                
                set_flash('Customer added successfully!', 'success');
                redirect('customers.php');
            } catch (Exception $e) {
                log_error("Error adding customer: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-plus me-2"></i>Add New Customer</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['customer_code']) ? 'is-invalid' : '' ?>" name="customer_code" required placeholder="e.g., CUST-001" value="<?= escape_html(post('customer_code')) ?>">
                                <?php if (isset($errors['customer_code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['customer_code'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" required placeholder="Enter customer name" value="<?= escape_html(post('name')) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" required placeholder="customer@example.com" value="<?= escape_html(post('email')) ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= $errors['email'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" placeholder="Phone number" value="<?= post('phone') ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <input type="text" class="form-control" name="payment_terms" placeholder="e.g., Net 30" value="<?= post('payment_terms') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" class="form-control" name="credit_limit" step="0.01" min="0" value="<?= post('credit_limit', '0.00') ?>" placeholder="Credit limit amount">
                            </div>
                        </div>
                        
                        <?php if (customers_table_has_discounts()): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Discount (%)</label>
                                <input type="number" class="form-control" name="default_discount_percent" step="0.01" min="0" max="100" value="<?= post('default_discount_percent', '0.00') ?>" placeholder="e.g. 10.00">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Discount (Fixed Amount)</label>
                                <input type="number" class="form-control" name="default_discount_amount" step="0.01" min="0" value="<?= post('default_discount_amount', '0.00') ?>" placeholder="e.g. 5.00">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2" placeholder="Street address"><?= post('address') ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" placeholder="City" value="<?= post('city') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">State/Province</label>
                                <input type="text" class="form-control" name="state" placeholder="State" value="<?= post('state') ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" placeholder="Postal code" value="<?= post('postal_code') ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" placeholder="Country" value="<?= post('country') ?>">
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="customers.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

