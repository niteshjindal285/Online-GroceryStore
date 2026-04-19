<?php
/**
 * Add Backlog Order
 * Create a new internal production tracking entry
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'New Backlog Order - MJR Group ERP';
$company_id = active_company_id(1);

// Handle Form Submission
if (is_post()) {
    if (verify_csrf_token(post('csrf_token'))) {
        $errors = [];
        try {
            $product_id = intval(post('product_id'));
            $quantity = floatval(post('quantity'));
            $priority = post('priority');
            $production_date = post('production_date');
            $sales_order_id = post('sales_order_id') ? intval(post('sales_order_id')) : null;
            $manager_id = post('manager_id') ? intval(post('manager_id')) : null;
            $notes = post('notes');
            $location_id = post('location_id') ? intval(post('location_id')) : null;
            $bin_id = post('bin_id') ? intval(post('bin_id')) : null;
            $company_id = active_company_id(1);
            $user_id = current_user_id();

            if (!$product_id)      $errors['product_id']      = err_required();
            if ($quantity <= 0)    $errors['quantity']        = err_required();
            if (!$production_date) $errors['production_date'] = err_required();
            if (!$manager_id)      $errors['manager_id']      = err_required();

            if (!empty($errors)) {
                throw new Exception(err_required());
            }

            // Generate Backlog Number
            $prefix = 'BL-' . date('Y');
            $last = db_fetch("SELECT backlog_number FROM backlog_orders WHERE backlog_number LIKE ? ORDER BY id DESC LIMIT 1", [$prefix . '%']);
            $num = 1;
            if ($last) {
                $last_num = intval(substr($last['backlog_number'], -4));
                $num = $last_num + 1;
            }
            $backlog_number = $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);

            $bin_note = '';
            if (!empty($location_id)) {
                $loc = db_fetch("SELECT name FROM locations WHERE id = ? AND company_id = ?", [$location_id, $company_id]);
                if ($loc) {
                    $bin_note .= 'Location: ' . $loc['name'];
                }
            }
            if (!empty($bin_id)) {
                $bin = db_fetch("SELECT code FROM bins WHERE id = ?", [$bin_id]);
                if ($bin) {
                    $bin_note .= ($bin_note ? ', ' : '') . 'Bin: ' . $bin['code'];
                }
            }
            if ($bin_note !== '') {
                $notes = trim(($notes ? $notes . "\n" : '') . '[' . $bin_note . ']');
            }

            db_query("
                INSERT INTO backlog_orders (backlog_number, product_id, quantity, priority, production_date, sales_order_id, manager_id, notes, created_by, company_id, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
            ", [$backlog_number, $product_id, $quantity, $priority, $production_date, $sales_order_id, $manager_id, $notes, $user_id, $company_id]);

            set_flash("Backlog order $backlog_number created successfully.", 'success');
            redirect('backlog_orders.php');
        } catch (Exception $e) {
            set_flash(sanitize_db_error($e->getMessage()), 'error');
        }
    }
}

// Fetch Data for Dropdowns
$products = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$sales_orders = db_fetch_all("SELECT id, order_number FROM sales_orders WHERE status NOT IN ('delivered', 'cancelled') AND company_id = ? ORDER BY order_date DESC", [$company_id]);
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role = 'manager' AND is_active = 1 AND company_id = ? ORDER BY username", [$company_id]);
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="text-white fw-bold mb-0">
                    <i class="fas fa-plus-circle me-2 text-warning"></i>New Backlog Order
                </h2>
                <a href="backlog_orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <div class="card bg-dark border-secondary shadow-lg">
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row g-4">
                            <!-- Product Selection -->
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold text-uppercase">Product to Produce <span class="text-danger">*</span></label>
                                <select class="form-select bg-dark text-white border-secondary select2 <?= isset($errors['product_id']) ? 'is-invalid' : '' ?>" name="product_id" required>
                                    <option value="">-- Search and Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?= $p['id'] ?>" <?= post('product_id') == $p['id'] ? 'selected' : '' ?>><?= escape_html($p['code']) ?> - <?= escape_html($p['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['product_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['product_id'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Quantity & Priority -->
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Quantity Required <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control bg-dark text-white border-secondary <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" name="quantity" value="<?= escape_html(post('quantity')) ?>" required placeholder="0.00">
                                <?php if (isset($errors['quantity'])): ?>
                                    <div class="invalid-feedback"><?= $errors['quantity'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Production Priority</label>
                                <select class="form-select bg-dark text-white border-secondary" name="priority">
                                    <option value="Normal">Normal</option>
                                    <option value="Low">Low</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>

                            <!-- Dates & Approval -->
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold text-uppercase">Expected Production Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control bg-dark text-white border-secondary <?= isset($errors['production_date']) ? 'is-invalid' : '' ?>" name="production_date" required value="<?= post('production_date') ?: date('Y-m-d') ?>">
                                <?php if (isset($errors['production_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['production_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold text-uppercase">Approving Manager <span class="text-danger">*</span></label>
                                <select class="form-select bg-dark text-white border-secondary <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" name="manager_id" required>
                                    <option value="">-- Select Manager --</option>
                                    <?php foreach ($managers as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= post('manager_id') == $m['id'] ? 'selected' : '' ?>><?= escape_html($m['full_name'] ?: $m['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['manager_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['manager_id'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold text-uppercase">Linked Sales Order (Optional)</label>
                                <select class="form-select bg-dark text-white border-secondary select2" name="sales_order_id">
                                    <option value="">-- Internal Stock Order --</option>
                                    <?php foreach ($sales_orders as $so): ?>
                                        <option value="<?= $so['id'] ?>">SO: <?= escape_html($so['order_number']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Location (Optional)</label>
                                <select class="form-select bg-dark text-white border-secondary" name="location_id" id="bo_location_id">
                                    <option value="">-- Select Location --</option>
                                    <?php foreach ($locations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"><?= escape_html($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold text-uppercase">Bin Location (Optional)</label>
                                <select class="form-select bg-dark text-white border-secondary" name="bin_id" id="bo_bin_id">
                                    <option value="">-- Select Bin --</option>
                                </select>
                            </div>

                            <!-- Notes -->
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold text-uppercase">Production Notes / Instructions</label>
                                <textarea class="form-control bg-dark text-white border-secondary" name="notes" rows="4" placeholder="Enter any specific requirements for this batch..."></textarea>
                            </div>
                        </div>

                        <div class="mt-5 border-top border-secondary pt-4 d-flex justify-content-end gap-3">
                            <a href="backlog_orders.php" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" class="btn btn-warning fw-bold px-5">
                                <i class="fas fa-save me-2"></i>Create Backlog Entry
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-4 p-3 bg-dark bg-opacity-50 border border-secondary rounded small text-muted text-center">
                <i class="fas fa-info-circle me-1 text-info"></i> This entry is for <strong>tracking only</strong> and will not impact stock levels.
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const loc = document.getElementById('bo_location_id');
    const bin = document.getElementById('bo_bin_id');
    if (!loc || !bin) return;
    loc.addEventListener('change', function() {
        const locId = this.value;
        bin.innerHTML = '<option value="">-- Select Bin --</option>';
        if (!locId) return;
        fetch('ajax_get_bins.php?location_id=' + encodeURIComponent(locId))
            .then(r => r.json())
            .then(data => {
                data.forEach(b => {
                    const o = document.createElement('option');
                    o.value = b.bin_id;
                    o.textContent = b.bin_location;
                    bin.appendChild(o);
                });
            })
            .catch(() => {});
    });
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
