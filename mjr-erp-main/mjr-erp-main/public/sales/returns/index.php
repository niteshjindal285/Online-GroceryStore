<?php
/**
 * Sales Returns — Full Workflow with Inventory Restoration
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Sales Returns - MJR Group ERP';
$errors     = [];

// Approve/Reject returns
if (is_post() && in_array(post('action'), ['approve','reject']) && (has_permission('approve_return') || is_admin())) {
    if (verify_csrf_token(post('csrf_token'))) {
        $rid = intval(post('return_id'));
        $act = post('action');
        $ret = db_fetch("SELECT * FROM sales_returns WHERE id=?", [$rid]);
        if ($ret) {
            if ($act === 'approve' && !$ret['stock_restored']) {
                db_begin_transaction();
                db_query("UPDATE sales_returns SET status='approved', approved_by=?, approved_at=NOW(), stock_restored=1 WHERE id=?",
                    [$_SESSION['user_id'], $rid]);

                // Restore stock for each return line
                $rl = db_fetch_all("SELECT * FROM sales_return_lines WHERE return_id=?", [$rid]);
                // Get invoice → location from delivery
                $inv_row = db_fetch("SELECT i.id, ds.invoice_id, i.customer_id FROM sales_returns sr JOIN invoices i ON i.id=sr.invoice_id JOIN delivery_schedule ds ON ds.invoice_id=i.id WHERE sr.id=? LIMIT 1", [$rid]);

                foreach ($rl as $line) {
                    // Add stock back to first available location
                    $loc = db_fetch("SELECT location_id FROM inventory_stock_levels WHERE item_id=? LIMIT 1", [$line['item_id']]);
                    if ($loc) {
                        db_query("UPDATE inventory_stock_levels SET quantity_on_hand=quantity_on_hand+?, quantity_available=quantity_available+? WHERE item_id=? AND location_id=?",
                            [$line['quantity'], $line['quantity'], $line['item_id'], $loc['location_id']]);
                        db_insert("INSERT INTO inventory_transactions (item_id, location_id, transaction_type, quantity, reference_type, reference_id, notes, created_by)
                            VALUES (?,?,'in',?,'sales_return',?,?,?)",
                            [$line['item_id'], $loc['location_id'], $line['quantity'], $rid, 'Stock restored via sales return', $_SESSION['user_id']]);
                    }
                }
                db_commit();
                set_flash('Return approved and stock restored.', 'success');
            } elseif ($act === 'reject') {
                db_query("UPDATE sales_returns SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=?",
                    [$_SESSION['user_id'], $rid]);
                set_flash('Return rejected.', 'warning');
            }
        }
        redirect('index.php');
    }
}

// CREATE return
if (is_post() && post('action') === 'create') {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid token.'; }
    else {
        $invoice_id = intval(post('invoice_id'));
        $reason     = trim(post('reason'));
        $notes      = trim(post('notes'));
        $qty_returns = post('return_qty', []);

        if (!$invoice_id) $errors[] = 'Invoice is required.';
        if (empty($reason)) $errors[] = 'Reason is required.';

        if (empty($errors)) {
            $last_ret = db_fetch("SELECT return_number FROM sales_returns ORDER BY id DESC LIMIT 1");
            $next_r = 1;
            if ($last_ret && preg_match('/RET(\d+)/', $last_ret['return_number'], $m)) $next_r = intval($m[1]) + 1;
            $ret_number = 'RET' . str_pad($next_r, 5, '0', STR_PAD_LEFT);

            db_begin_transaction();
            $ret_id = db_insert("INSERT INTO sales_returns (return_number, invoice_id, reason, notes, status, stock_restored, created_by)
                VALUES (?,?,?,?,'pending',0,?)", [$ret_number, $invoice_id, $reason, $notes, $_SESSION['user_id']]);

            // Insert return lines
            $inv_lines = db_fetch_all("SELECT * FROM invoice_lines WHERE invoice_id=?", [$invoice_id]);
            foreach ($inv_lines as $il) {
                $qty = floatval($qty_returns[$il['id']] ?? 0);
                if ($qty <= 0) continue;
                db_insert("INSERT INTO sales_return_lines (return_id, invoice_line_id, item_id, quantity, unit_price)
                    VALUES (?,?,?,?,?)", [$ret_id, $il['id'], $il['item_id'], $qty, $il['unit_price']]);
            }
            db_commit();
            set_flash("Return $ret_number submitted for approval.", 'success');
            redirect('index.php');
        }
    }
}

$returns = db_fetch_all("
    SELECT sr.*, i.invoice_number, c.name AS customer_name, u.username AS approved_by_name
    FROM sales_returns sr
    JOIN invoices i ON i.id=sr.invoice_id
    JOIN customers c ON c.id=i.customer_id
    LEFT JOIN users u ON u.id=sr.approved_by
    ORDER BY sr.created_at DESC LIMIT 100");

// For create form — recent open invoices
$open_invs = db_fetch_all("SELECT i.id, i.invoice_number, c.name AS customer_name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.payment_status='open' ORDER BY i.invoice_date DESC LIMIT 50");

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-undo me-2"></i>Sales Returns</h2>
    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#newReturnModal">
        <i class="fas fa-plus me-1"></i>New Return
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
                    <tr><th>Return #</th><th>Invoice</th><th>Customer</th><th>Reason</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($returns as $r): ?>
                    <tr>
                        <td><code><?= escape_html($r['return_number']) ?></code></td>
                        <td><a href="../invoices/view_invoice.php?id=<?= $r['invoice_id'] ?>"><code><?= escape_html($r['invoice_number']) ?></code></a></td>
                        <td><?= escape_html($r['customer_name']) ?></td>
                        <td><?= escape_html(substr($r['reason'] ?? '—', 0, 50)) ?></td>
                        <td><?= $r['stock_restored'] ? '<span class="badge bg-success">Restored</span>' : '<span class="badge bg-secondary">Pending</span>' ?></td>
                        <td><span class="badge bg-<?= ['pending'=>'warning text-dark','approved'=>'success','rejected'=>'danger'][$r['status']] ?? 'light' ?>"><?= ucfirst($r['status']) ?></span></td>
                        <td>
                            <?php if ($r['status']==='pending' && (has_permission('approve_return') || is_admin())): ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="return_id" value="<?= $r['id'] ?>">
                                <button name="action" value="approve" class="btn btn-success btn-sm me-1" onclick="return confirm('Approve and restore stock?')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button name="action" value="reject" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($returns)): ?><tr><td colspan="7" class="text-center py-4 text-muted">No sales returns yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- New Return Modal -->
<div class="modal fade" id="newReturnModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">New Sales Return</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Invoice <span class="text-danger">*</span></label>
                        <select name="invoice_id" class="form-select" required id="returnInvSel" onchange="loadReturnLines(this.value)">
                            <option value="">Select Invoice</option>
                            <?php foreach ($open_invs as $inv): ?>
                            <option value="<?= $inv['id'] ?>"><?= escape_html($inv['invoice_number']) ?> — <?= escape_html($inv['customer_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="returnLinesContainer" class="mb-3" style="display:none;">
                        <label class="form-label">Return Quantities</label>
                        <div id="returnLines"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Return Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required placeholder="e.g. Damaged goods, wrong item delivered..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo me-1"></i>Submit Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function loadReturnLines(invId) {
    if (!invId) { document.getElementById('returnLinesContainer').style.display='none'; return; }
    fetch('<?= url('sales/returns/get_invoice_lines.php') ?>?invoice_id='+invId)
        .then(r => r.json())
        .then(data => {
            let html = '<table class="table table-sm"><thead><tr><th>Item</th><th>Qty Invoiced</th><th>Return Qty</th></tr></thead><tbody>';
            data.forEach(l => {
                html += `<tr><td>${l.item_code} — ${l.item_name}</td><td>${parseFloat(l.quantity).toFixed(2)}</td>
                         <td><input type="number" name="return_qty[${l.id}]" class="form-control form-control-sm" min="0" max="${l.quantity}" step="0.01" value="${l.quantity}" style="width:90px;"></td></tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('returnLines').innerHTML = html;
            document.getElementById('returnLinesContainer').style.display = 'block';
        })
        .catch(() => {
            document.getElementById('returnLines').innerHTML = '<p class="text-muted small">Could not load lines. Enter quantities manually.</p>';
            document.getElementById('returnLinesContainer').style.display = 'block';
        });
}
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
