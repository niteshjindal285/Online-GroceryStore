<?php
/**
 * Cancel Invoice (requires manager approval)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

if (!has_permission('cancel_invoice') && !is_admin()) {
    set_flash('Access denied — manager permission required to cancel invoices.', 'error');
    redirect('index.php');
}

$id = intval(get('id'));
if (!$id) { set_flash('Invalid invoice.', 'error'); redirect('index.php'); }

$inv = db_fetch("SELECT * FROM invoices WHERE id=?", [$id]);
if (!$inv) { set_flash('Invoice not found.', 'error'); redirect('index.php'); }

if ($inv['payment_status'] === 'cancelled') { set_flash('Invoice already cancelled.', 'warning'); redirect("view_invoice.php?id=$id"); }
if ($inv['payment_status'] === 'closed')    { set_flash('Cannot cancel a paid invoice. Issue a Sales Return instead.', 'error'); redirect("view_invoice.php?id=$id"); }

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid token.', 'error');
        redirect("view_invoice.php?id=$id");
    }

    $reason = trim(post('reason'));
    if (empty($reason)) { $error = 'Please provide a cancellation reason.'; }
    else {
        db_query("UPDATE invoices SET payment_status='cancelled', cancelled_at=NOW(), cancelled_by=?, notes=CONCAT(COALESCE(notes,''), '\n[CANCELLED] ', ?) WHERE id=?",
            [$_SESSION['user_id'], $reason, $id]);

        // Remove from pending delivery
        db_query("DELETE FROM delivery_schedule WHERE invoice_id=? AND status='pending'", [$id]);

        set_flash('Invoice cancelled successfully.', 'success');
        redirect("view_invoice.php?id=$id");
    }
}

$page_title = 'Cancel Invoice ' . $inv['invoice_number'];
include __DIR__ . '/../../../templates/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-ban me-2"></i>Cancel Invoice — <?= escape_html($inv['invoice_number']) ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Cancelling an invoice is <strong>irreversible</strong>. The invoice record is preserved for audit trail purposes.
                    If the customer has already paid, issue a <strong>Sales Return</strong> instead.
                </div>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <table class="table table-sm mb-4">
                    <tr><td>Invoice #</td><td><strong><?= escape_html($inv['invoice_number']) ?></strong></td></tr>
                    <tr><td>Total</td><td><?= format_currency($inv['total_amount']) ?></td></tr>
                    <tr><td>Status</td><td><?= ucfirst($inv['payment_status']) ?></td></tr>
                </table>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Cancellation Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="State the reason for cancellation..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger flex-fill"><i class="fas fa-ban me-1"></i>Confirm Cancel</button>
                        <a href="view_invoice.php?id=<?= $id ?>" class="btn btn-secondary flex-fill">Go Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
