<?php
/**
 * Add Debtor
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Add Debtor - MJR Group ERP';
$errors = [];
$success = '';

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name             = trim(post('name'));
        $code             = strtoupper(trim(post('code')));
        $email            = trim(post('email'));
        $phone            = trim(post('phone'));
        $address          = trim(post('address'));
        $credit_limit     = floatval(post('credit_limit', 0));
        $credit_term_days = intval(post('credit_term_days', 30));
        $discount_t1      = floatval(post('discount_tier1_pct', 0));
        $discount_t2      = floatval(post('discount_tier2_amt', 0));
        $tax_number       = trim(post('tax_number'));
        $payment_terms    = trim(post('payment_terms'));

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($code)) $errors[] = 'Code is required.';
        if ($credit_term_days < 0) $errors[] = 'Credit term must be >= 0.';

        // Check duplicate code
        $existing = db_fetch("SELECT id FROM customers WHERE code = ?", [$code]);
        if ($existing) $errors[] = "Customer code '$code' already exists.";

        if (empty($errors)) {
            $company_id = $_SESSION['company_id'];
            $user_id    = $_SESSION['user_id'];

            $sql = "INSERT INTO customers 
                    (code, customer_code, name, email, phone, address, company_id,
                     credit_limit, credit_term_days, payment_terms,
                     discount_tier1_pct, discount_tier2_amt, tax_number,
                     credit_pending_approval, is_active, created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,1,NOW())";
            $id = db_insert($sql, [
                $code, $code, $name, $email, $phone, $address, $company_id,
                $credit_limit, $credit_term_days, $payment_terms,
                $discount_t1, $discount_t2, $tax_number
            ]);

            set_flash('Debtor created. Credit limit pending manager approval.', 'success');
            redirect("view_debtor.php?id=$id");
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-user-plus me-2"></i>Add Debtor</h2>
    <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Basic Information</h5></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Debtor Code <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control text-uppercase" value="<?= escape_html(post('code','')) ?>" required placeholder="e.g. CUST-010">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= escape_html(post('name','')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= escape_html(post('email','')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= escape_html(post('phone','')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tax / VAT Number</label>
                            <input type="text" name="tax_number" class="form-control" value="<?= escape_html(post('tax_number','')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="Net 30" <?= post('payment_terms')=='Net 30'?'selected':'' ?>>Net 30</option>
                                <option value="Net 45" <?= post('payment_terms')=='Net 45'?'selected':'' ?>>Net 45</option>
                                <option value="Net 60" <?= post('payment_terms')=='Net 60'?'selected':'' ?>>Net 60</option>
                                <option value="COD">Cash on Delivery</option>
                                <option value="Prepaid">Prepaid</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= escape_html(post('address','')) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt me-1 text-warning"></i>Credit Terms</h5></div>
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Credit limit and term changes require <strong>manager approval</strong> before taking effect.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Credit Limit ($)</label>
                            <input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="<?= post('credit_limit',0) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Term (Days)</label>
                            <input type="number" name="credit_term_days" class="form-control" min="0" value="<?= post('credit_term_days',30) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-tags me-1 text-success"></i>Discount Tiers</h5></div>
                <div class="card-body">
                    <label class="form-label">Tier 1 — Percentage Discount (%)</label>
                    <input type="number" name="discount_tier1_pct" class="form-control mb-3" min="0" max="100" step="0.01" value="<?= post('discount_tier1_pct',0) ?>">
                    <label class="form-label">Tier 2 — Flat Amount Off ($)</label>
                    <input type="number" name="discount_tier2_amt" class="form-control" min="0" step="0.01" value="<?= post('discount_tier2_amt',0) ?>">
                    <small class="text-muted">Applied on top of Tier 1.</small>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i>Create Debtor
                </button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
