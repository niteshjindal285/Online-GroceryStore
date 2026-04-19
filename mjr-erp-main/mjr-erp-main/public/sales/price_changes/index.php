<?php
/**
 * Price Changes — Request & Approve
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Price Changes - MJR Group ERP';
$errors = [];

// Handle approve/reject
if (is_post() && post('action') && (has_permission('approve_price_change') || is_admin())) {
    if (verify_csrf_token(post('csrf_token'))) {
        $pcid = intval(post('pc_id'));
        $act  = post('action');
        $pc   = db_fetch("SELECT * FROM price_changes WHERE id=?", [$pcid]);
        if ($pc) {
            if ($act === 'approve') {
                db_begin_transaction();
                db_query("UPDATE price_changes SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
                    [$_SESSION['user_id'], $pcid]);
                db_query("UPDATE inventory_items SET selling_price=? WHERE id=?",
                    [$pc['new_price'], $pc['item_id']]);
                db_commit();
                set_flash('Price change approved and applied.', 'success');
            } else {
                db_query("UPDATE price_changes SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?",
                    [$_SESSION['user_id'], $pcid]);
                set_flash('Price change rejected.', 'warning');
            }
        }
        redirect('index.php');
    }
}

// Handle create
if (is_post() && !post('action')) {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid token.'; }
    else {
        $item_id    = intval(post('item_id'));
        $new_price  = floatval(post('new_price'));
        $reason     = trim(post('reason'));
        $eff_date   = post('effective_date') ?: null;

        if (!$item_id)    $errors[] = 'Item is required.';
        if ($new_price < 0) $errors[] = 'Price must be >= 0.';
        if (empty($reason)) $errors[] = 'Reason is required.';

        if (empty($errors)) {
            $item = db_fetch("SELECT selling_price FROM inventory_items WHERE id=?", [$item_id]);
            db_insert("INSERT INTO price_changes (item_id, old_price, new_price, reason, effective_date, status, created_by)
                VALUES (?,?,?,?,?,'pending',?)",
                [$item_id, $item['selling_price'], $new_price, $reason, $eff_date, $_SESSION['user_id']]);
            set_flash('Price change request submitted for approval.', 'success');
            redirect('index.php');
        }
    }
}

$changes = db_fetch_all("
    SELECT pc.*, ii.name AS item_name, ii.code AS item_code, u.username AS approved_by_name
    FROM price_changes pc
    JOIN inventory_items ii ON ii.id=pc.item_id
    LEFT JOIN users u ON u.id=pc.approved_by
    ORDER BY pc.created_at DESC LIMIT 100");
$items = db_fetch_all("SELECT id, code, name, selling_price FROM inventory_items WHERE is_active=1 ORDER BY name");

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-dollar-sign me-2"></i>Price Changes</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#pcModal">
        <i class="fas fa-plus me-1"></i>Request Change
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
                    <tr><th>Item</th><th>Old Price</th><th>New Price</th><th>Change</th><th>Effective</th><th>Status</th><th>Approved By</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($changes as $pc):
                        $diff = $pc['new_price'] - $pc['old_price'];
                    ?>
                    <tr>
                        <td><code><?= escape_html($pc['item_code']) ?></code> <?= escape_html($pc['item_name']) ?></td>
                        <td><?= format_currency($pc['old_price']) ?></td>
                        <td class="fw-bold"><?= format_currency($pc['new_price']) ?></td>
                        <td class="<?= $diff > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $diff > 0 ? '▲' : '▼' ?> <?= format_currency(abs($diff)) ?>
                        </td>
                        <td><?= $pc['effective_date'] ? format_date($pc['effective_date']) : '—' ?></td>
                        <td><span class="badge bg-<?= ['pending'=>'warning text-dark','approved'=>'success','rejected'=>'danger'][$pc['status']] ?? 'light' ?>"><?= ucfirst($pc['status']) ?></span></td>
                        <td><?= escape_html($pc['approved_by_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($pc['status']==='pending' && (has_permission('approve_price_change') || is_admin())): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="pc_id" value="<?= $pc['id'] ?>">
                                <button name="action" value="approve" class="btn btn-success btn-sm me-1"><i class="fas fa-check"></i></button>
                                <button name="action" value="reject" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($changes)): ?><tr><td colspan="8" class="text-center py-4 text-muted">No price change requests yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Price Change Modal -->
<div class="modal fade" id="pcModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-header"><h5 class="modal-title">Request Price Change</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-3">Price changes require manager approval before the selling price is updated.</div>
                    <div class="mb-3">
                        <label class="form-label">Item *</label>
                        <select name="item_id" class="form-select" required id="pcItemSel" onchange="showCurrentPrice(this)">
                            <option value="">Select Item</option>
                            <?php foreach ($items as $it): ?>
                            <option value="<?= $it['id'] ?>" data-price="<?= $it['selling_price'] ?>"><?= escape_html($it['code']) ?> — <?= escape_html($it['name']) ?> (Current: <?= format_currency($it['selling_price']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Selling Price ($) *</label>
                        <input type="number" name="new_price" class="form-control" min="0" step="0.0001" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Effective Date</label>
                        <input type="date" name="effective_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason *</label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="Justify the price change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Submit for Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
