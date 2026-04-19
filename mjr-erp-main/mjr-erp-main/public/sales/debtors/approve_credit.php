<?php
/**
 * Approve / Reject Credit Limit & Release Hold
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

if (!has_permission('approve_credit') && !is_admin()) {
    set_flash('Access denied — manager permission required.', 'error');
    redirect('../debtors/index.php');
}

$id     = intval(get('id'));
$action = get('action', 'approve'); // approve | release | reject

$debtor = db_fetch("SELECT * FROM customers WHERE id = ?", [$id]);
if (!$debtor) { set_flash('Debtor not found.', 'error'); redirect('index.php'); }

if (is_post()) {
    $csrf = post('csrf_token');
    if (!verify_csrf_token($csrf)) { set_flash('Invalid token.','error'); redirect("view_debtor.php?id=$id"); }

    $act = post('action');
    $uid = $_SESSION['user_id'];

    if ($act === 'approve') {
        db_query("UPDATE customers SET
            credit_pending_approval=0,
            credit_approved_by=?,
            credit_approved_at=NOW()
            WHERE id=?", [$uid, $id]);
        set_flash('Credit terms approved successfully.', 'success');
    } elseif ($act === 'release') {
        db_query("UPDATE customers SET credit_hold=0, hold_reason=NULL, credit_approved_by=?, credit_approved_at=NOW() WHERE id=?", [$uid, $id]);
        set_flash('Credit hold released.', 'success');
    } elseif ($act === 'reject') {
        db_query("UPDATE customers SET credit_pending_approval=0 WHERE id=?", [$id]);
        set_flash('Credit change rejected.', 'warning');
    }
    redirect("view_debtor.php?id=$id");
}

$page_title = 'Approve Credit — ' . $debtor['name'];
include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-check-circle me-2 text-success"></i>Credit Approval</h2>
    <a href="view_debtor.php?id=<?= $id ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= escape_html($debtor['name']) ?> — <?= escape_html($debtor['code']) ?></h5></div>
            <div class="card-body">
                <table class="table table-sm mb-4">
                    <tr><td>Credit Limit</td><td class="fw-bold"><?= format_currency($debtor['credit_limit']) ?></td></tr>
                    <tr><td>Credit Term</td><td><?= intval($debtor['credit_term_days']) ?> days</td></tr>
                    <tr><td>Credit Hold</td><td><?= $debtor['credit_hold'] ? '<span class="badge bg-danger">Active</span>' : '<span class="badge bg-success">None</span>' ?></td></tr>
                    <tr><td>Hold Reason</td><td><?= escape_html($debtor['hold_reason'] ?? '—') ?></td></tr>
                </table>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                    <?php if ($debtor['credit_pending_approval']): ?>
                    <div class="alert alert-warning">Pending credit limit / term approval requested.</div>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="approve" class="btn btn-success flex-fill">
                            <i class="fas fa-check me-1"></i>Approve Credit Terms
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-danger flex-fill">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if ($debtor['credit_hold']): ?>
                    <div class="alert alert-danger mt-3">This account is on Credit Hold.</div>
                    <button type="submit" name="action" value="release" class="btn btn-warning w-100">
                        <i class="fas fa-unlock me-1"></i>Release Credit Hold
                    </button>
                    <?php endif; ?>

                    <?php if (!$debtor['credit_pending_approval'] && !$debtor['credit_hold']): ?>
                    <div class="alert alert-success">No pending approvals for this debtor.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
