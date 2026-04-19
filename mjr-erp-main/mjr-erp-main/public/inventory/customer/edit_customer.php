<?php
/**
 * Edit Customer Page
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Edit Customer - MJR Group ERP';

// Get active company
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company before editing a customer.', 'warning');
    redirect(url('index.php'));
}

// Get customer ID from URL
$customer_id = get('id');
if (!$customer_id) {
    set_flash('Customer ID not provided.', 'error');
    redirect('customers.php');
}

// Get customer data
$customer = db_fetch("SELECT * FROM customers WHERE id = ? AND company_id = ?", [$customer_id, $company_id]);
if (!$customer) {
    set_flash('Customer not found.', 'error');
    redirect('customers.php');
}

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $customer_code = trim(post('customer_code', ''));
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
        $is_active = post('is_active') ? 1 : 0;

        $errors = [];
        if (empty($customer_code)) $errors['customer_code'] = 'Please fill Customer Code that field';
        if (empty($name))          $errors['name']          = 'Please fill Customer Name that field';
        if (empty($email))         $errors['email']         = 'Please fill Email that field';

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM customers WHERE customer_code = ? AND id != ?", [$customer_code, $customer_id]);
            if ($exists) {
                $errors['customer_code'] = 'Customer code already exists!';
            }
        }

        if (empty($errors)) {
            try {
                if (customers_table_has_discounts()) {
                    $sql = "UPDATE customers SET 
                            customer_code = ?, code = ?, name = ?, email = ?, phone = ?, payment_terms = ?, 
                            address = ?, city = ?, state = ?, postal_code = ?, country = ?, 
                            credit_limit = ?, default_discount_percent = ?, default_discount_amount = ?, is_active = ?, updated_at = NOW() 
                            WHERE id = ? AND company_id = ?";
                    db_query($sql, [$customer_code, $customer_code, $name, $email, $phone, $payment_terms, $address, $city, $state, $postal_code, $country, $credit_limit, $default_discount_percent, $default_discount_amount, $is_active, $customer_id, $company_id]);
                } else {
                    $sql = "UPDATE customers SET 
                            customer_code = ?, code = ?, name = ?, email = ?, phone = ?, payment_terms = ?, 
                            address = ?, city = ?, state = ?, postal_code = ?, country = ?, 
                            credit_limit = ?, is_active = ?, updated_at = NOW() 
                            WHERE id = ? AND company_id = ?";
                    db_query($sql, [$customer_code, $customer_code, $name, $email, $phone, $payment_terms, $address, $city, $state, $postal_code, $country, $credit_limit, $is_active, $customer_id, $company_id]);
                }
                
                set_flash('Customer updated successfully!', 'success');
                redirect('view_customer.php?id=' . $customer_id);
            } catch (Exception $e) {
                log_error("Error updating customer: " . $e->getMessage());
                set_flash('Error updating customer.', 'error');
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
                    <h3><i class="fas fa-edit me-2"></i>Edit Customer</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['customer_code']) ? 'is-invalid' : '' ?>" name="customer_code" required value="<?= escape_html(post('customer_code', $customer['customer_code'])) ?>">
                                <?php if (isset($errors['customer_code'])): ?>
                                    <div class="invalid-feedback"><?= $errors['customer_code'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" name="name" required value="<?= escape_html(post('name', $customer['name'])) ?>">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?= $errors['name'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" name="email" required value="<?= escape_html(post('email', $customer['email'])) ?>">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= $errors['email'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?= escape_html($customer['phone']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Terms</label>
                                <input type="text" class="form-control" name="payment_terms" value="<?= escape_html($customer['payment_terms']) ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Credit Limit</label>
                                <input type="number" class="form-control" name="credit_limit" step="0.01" min="0" value="<?= $customer['credit_limit'] ?>">
                            </div>
                        </div>
                        
                        <?php if (customers_table_has_discounts()): ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Discount (%)</label>
                                <input type="number" class="form-control" name="default_discount_percent" step="0.01" min="0" max="100" value="<?= escape_html(post('default_discount_percent', $customer['default_discount_percent'])) ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Discount (Fixed Amount)</label>
                                <input type="number" class="form-control" name="default_discount_amount" step="0.01" min="0" value="<?= escape_html(post('default_discount_amount', $customer['default_discount_amount'])) ?>">
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?= escape_html($customer['address']) ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" value="<?= escape_html($customer['city']) ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">State/Province</label>
                                <input type="text" class="form-control" name="state" value="<?= escape_html($customer['state']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" value="<?= escape_html($customer['postal_code']) ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="<?= escape_html($customer['country']) ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="is_active" id="is_active" <?= $customer['is_active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Active Status</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="customers.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Customer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>

