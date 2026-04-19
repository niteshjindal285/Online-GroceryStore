<?php
/**
 * Discounts Management
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Sales Discounts - MJR Group ERP';
$company_id = active_company_id(1);
$errors = [];

// Handle approve/reject
if (is_post() && post('action') && (has_permission('approve_discount') || is_admin())) {
    if (verify_csrf_token(post('csrf_token'))) {
        $disc_id = intval(post('discount_id'));
        $act     = post('action');
        if ($act === 'approve') {
            db_query("UPDATE sales_discounts SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
                [$_SESSION['user_id'], $disc_id]);
            set_flash('Discount approved.', 'success');
        } elseif ($act === 'reject') {
            db_query("UPDATE sales_discounts SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?",
                [$_SESSION['user_id'], $disc_id]);
            set_flash('Discount rejected.', 'warning');
        }
        redirect('index.php');
    }
}

// Handle create
if (is_post() && !post('action')) {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid token.'; }
    else {
        $name           = trim(post('name'));
        $discount_code  = trim(post('discount_code'));
        $customer_id    = intval(post('customer_id')) ?: null;
        $item_id        = intval(post('item_id')) ?: null;
        $discount_type  = post('discount_type', 'percentage');
        $discount_value = floatval(post('discount_value', 0));
        $expiry_date    = post('expiry_date') ?: null;
        $notes          = trim(post('notes'));

        if (empty($name)) $errors[] = 'Discount Name is required.';
        if ($discount_value <= 0) $errors[] = 'Discount value must be greater than 0.';
        if ($discount_type === 'percentage' && $discount_value > 100) $errors[] = 'Percentage value cannot exceed 100.';

        if (empty($errors)) {
            db_insert("INSERT INTO sales_discounts (name, discount_code, customer_id, item_id, discount_type, discount_value, expiry_date, notes, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,'pending',?)",
                [$name, $discount_code, $customer_id, $item_id, $discount_type, $discount_value, $expiry_date, $notes, $_SESSION['user_id']]);
            set_flash('Discount submitted for approval.', 'success');
            redirect('index.php');
        }
    }
}

// Auto-expire
db_query("UPDATE sales_discounts SET status='expired' WHERE status='approved' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()");

$discounts  = db_fetch_all("
    SELECT sd.*, c.name AS customer_name, ii.name AS item_name, u.username AS approved_by_name
    FROM sales_discounts sd
    LEFT JOIN customers c ON c.id=sd.customer_id
    LEFT JOIN inventory_items ii ON ii.id=sd.item_id
    LEFT JOIN users u ON u.id=sd.approved_by
    WHERE (c.company_id = ? OR ii.company_id = ?)
    ORDER BY sd.created_at DESC LIMIT 100", [$company_id, $company_id]);

$customers  = db_fetch_all("SELECT id, customer_code AS code, name FROM customers WHERE is_active=1 AND company_id = ? ORDER BY name", [$company_id]);
$items      = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active=1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tags me-2"></i>Sales Discounts</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newDiscountModal">
        <i class="fas fa-plus me-1"></i>New Discount
    </button>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr><th>Name & Code</th><th>Customer</th><th>Item</th><th>Type</th><th>Value</th><th>Expiry</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($discounts as $d): ?>
                    <tr>
                        <td>
                            <strong><?= escape_html($d['name']) ?></strong><br>
                            <?php if ($d['discount_code']): ?><span class="badge bg-secondary"><?= escape_html($d['discount_code']) ?></span><?php endif; ?>
                        </td>
                        <td><?= escape_html($d['customer_name'] ?? 'All Customers') ?></td>
                        <td><?= escape_html($d['item_name'] ?? 'All Items') ?></td>
                        <td><?= ucfirst($d['discount_type']) ?></td>
                        <td class="fw-bold"><?= $d['discount_type'] === 'percentage' ? number_format($d['discount_value'], 2).'%' : format_currency($d['discount_value']) ?></td>
                        <td><?= $d['expiry_date'] ? format_date($d['expiry_date']) : '<em class="text-muted">None</em>' ?></td>
                        <td>
                            <span class="badge bg-<?= ['pending'=>'warning text-dark','approved'=>'success','rejected'=>'danger','expired'=>'secondary'][$d['status']] ?? 'light' ?>">
                                <?= ucfirst($d['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($d['status']==='pending' && (has_permission('approve_discount') || is_admin())): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="discount_id" value="<?= $d['id'] ?>">
                                <button name="action" value="approve" class="btn btn-xs btn-success btn-sm me-1"><i class="fas fa-check"></i></button>
                                <button name="action" value="reject" class="btn btn-xs btn-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($discounts)): ?><tr><td colspan="7" class="text-center py-4 text-muted">No discounts found.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Discount Modal -->
<div class="modal fade" id="newDiscountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-header"><h5 class="modal-title">New Discount</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-3">Discounts require manager approval before taking effect.</div>
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Discount Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">Discount Code</label>
                            <input type="text" name="discount_code" class="form-control" placeholder="e.g. SUMMER20">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Customer (optional)</label>
                        <select name="customer_id" class="form-select"><option value="">— All Customers —</option>
                            <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>"><?= escape_html($c['code']) ?> — <?= escape_html($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Item (optional)</label>
                        <select name="item_id" class="form-select"><option value="">— All Items —</option>
                            <?php foreach ($items as $it): ?><option value="<?= $it['id'] ?>"><?= escape_html($it['code']) ?> — <?= escape_html($it['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Discount Type</label>
                            <select name="discount_type" class="form-select">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount ($)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Value *</label>
                            <input type="number" name="discount_value" id="discount_value" class="form-control" min="0.01" step="0.01" required>
                            <div class="invalid-feedback" id="discount_error_msg">You can't enter above hundred.</div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control">
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Reason for discount..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.querySelector('select[name="discount_type"]');
    const valueInput = document.getElementById('discount_value');
    const nameInput = document.querySelector('input[name="name"]');
    const codeInput = document.querySelector('input[name="discount_code"]');

    function updateValidation() {
        if (typeSelect.value === 'percentage' && parseFloat(valueInput.value) > 100) {
            valueInput.classList.add('is-invalid');
            valueInput.setCustomValidity("You can't enter above hundred");
        } else {
            valueInput.classList.remove('is-invalid');
            valueInput.setCustomValidity("");
        }
    }

    typeSelect.addEventListener('change', updateValidation);
    valueInput.addEventListener('input', updateValidation);

    // Auto-generate discount code from name
    nameInput.addEventListener('input', function() {
        // Strip non-alphanumeric characters, convert to uppercase, and take up to 20 chars
        codeInput.value = this.value.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().substring(0, 20);
    });
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
