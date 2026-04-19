<?php
/**
 * Edit Debtor
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
if (!$id) { set_flash('Invalid debtor.', 'error'); redirect('index.php'); }
$debtor = db_fetch("SELECT * FROM customers WHERE id = ?", [$id]);
if (!$debtor) { set_flash('Debtor not found.', 'error'); redirect('index.php'); }

$errors = [];

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid CSRF token.'; }
    else {
        $name             = trim(post('name'));
        $email            = trim(post('email'));
        $phone            = trim(post('phone'));
        $address          = trim(post('address'));
        $credit_limit     = floatval(post('credit_limit', 0));
        $credit_term_days = intval(post('credit_term_days', 30));
        $discount_t1      = floatval(post('discount_tier1_pct', 0));
        $discount_t2      = floatval(post('discount_tier2_amt', 0));
        $tax_number       = trim(post('tax_number'));
        $payment_terms    = trim(post('payment_terms'));
        $release_hold     = post('release_hold') === '1';

        if (empty($name)) $errors[] = 'Name is required.';

        // Detect credit change → needs re-approval
        $credit_changed = ($credit_limit != $debtor['credit_limit'] || $credit_term_days != $debtor['credit_term_days']);

        if (empty($errors)) {
            $pending = $credit_changed ? 1 : (int)$debtor['credit_pending_approval'];
            $hold    = $release_hold ? 0 : (int)$debtor['credit_hold'];
            $reason  = $release_hold ? null : $debtor['hold_reason'];

            db_query("UPDATE customers SET
                name=?, email=?, phone=?, address=?, payment_terms=?,
                credit_limit=?, credit_term_days=?,
                discount_tier1_pct=?, discount_tier2_amt=?,
                tax_number=?, credit_pending_approval=?,
                credit_hold=?, hold_reason=?, updated_at=NOW()
                WHERE id=?",
                [$name, $email, $phone, $address, $payment_terms,
                 $credit_limit, $credit_term_days,
                 $discount_t1, $discount_t2,
                 $tax_number, $pending,
                 $hold, $reason, $id]);

            $msg = 'Debtor updated.';
            if ($credit_changed) $msg .= ' Credit changes pending manager approval.';
            set_flash($msg, 'success');
            redirect("view_debtor.php?id=$id");
        }
        // repopulate from post
        $debtor = array_merge($debtor, [
            'name'=>$name,'email'=>$email,'phone'=>$phone,'address'=>$address,
            'credit_limit'=>$credit_limit,'credit_term_days'=>$credit_term_days,
            'discount_tier1_pct'=>$discount_t1,'discount_tier2_amt'=>$discount_t2,
            'tax_number'=>$tax_number,'payment_terms'=>$payment_terms
        ]);
    }
}

$page_title = 'Edit Debtor — ' . $debtor['name'];
include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-edit me-2"></i>Edit Debtor — <?= escape_html($debtor['name']) ?></h2>
    <a href="view_debtor.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
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
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?= escape_html($debtor['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= escape_html($debtor['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= escape_html($debtor['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tax Number</label>
                            <input type="text" name="tax_number" class="form-control" value="<?= escape_html($debtor['tax_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <?php foreach (['Net 30','Net 45','Net 60','COD','Prepaid'] as $t): ?>
                                <option <?= $debtor['payment_terms']==$t?'selected':'' ?>><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= escape_html($debtor['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-shield-alt me-1 text-warning"></i>Credit Terms</h5></div>
                <div class="card-body">
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Changing credit limit or term will require manager re-approval.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Credit Limit ($)</label>
                            <input type="number" name="credit_limit" class="form-control" min="0" step="0.01" value="<?= $debtor['credit_limit'] ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Credit Term (Days)</label>
                            <input type="number" name="credit_term_days" class="form-control" min="0" value="<?= $debtor['credit_term_days'] ?>">
                        </div>
                    </div>
                    <?php if ($debtor['credit_hold'] && (has_permission('approve_credit') || is_admin())): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="release_hold" value="1" id="releaseHold">
                        <label class="form-check-label text-danger fw-bold" for="releaseHold">Release Credit Hold</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Discount Tiers</h5></div>
                <div class="card-body">
                    <label class="form-label">Tier 1 — % Discount</label>
                    <input type="number" name="discount_tier1_pct" class="form-control mb-3" min="0" max="100" step="0.01" value="<?= $debtor['discount_tier1_pct'] ?>">
                    <label class="form-label">Tier 2 — Flat Off ($)</label>
                    <input type="number" name="discount_tier2_amt" class="form-control" min="0" step="0.01" value="<?= $debtor['discount_tier2_amt'] ?>">
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-warning btn-lg"><i class="fas fa-save me-1"></i>Save Changes</button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
