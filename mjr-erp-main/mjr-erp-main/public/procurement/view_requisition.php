<?php
/**
 * View Purchase Requisition
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    set_flash('Invalid Requisition ID.', 'error');
    redirect('requisitions.php');
}

$req = db_fetch("
    SELECT r.*, u.username as created_by_name, p.po_number 
    FROM purchase_requisitions r
    LEFT JOIN users u ON r.created_by = u.id
    LEFT JOIN purchase_orders p ON r.po_id = p.id
    WHERE r.id = ?
", [$id]);

if (!$req) {
    set_flash('Requisition not found.', 'error');
    redirect('requisitions.php');
}

$lines = db_fetch_all("
    SELECT l.*, i.code, i.name as item_name, u.code as unit_code
    FROM purchase_requisition_lines l
    JOIN inventory_items i ON l.item_id = i.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE l.requisition_id = ?
", [$id]);

$can_approve = has_permission('approve_purchases'); // Use appropriate perm
$can_convert = has_permission('manage_purchasing') && $req['status'] === 'approved';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve' && $can_approve) {
        db_query("UPDATE purchase_requisitions SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?", [current_user_id(), $id]);
        set_flash('Requisition Approved.', 'success');
        redirect("view_requisition.php?id=$id");
    } elseif ($action === 'reject' && $can_approve) {
        db_query("UPDATE purchase_requisitions SET status = 'rejected' WHERE id = ?", [$id]);
        set_flash('Requisition Rejected.', 'warning');
        redirect("view_requisition.php?id=$id");
    }
}

$page_title = 'View Requisition - ' . $req['requisition_number'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-file-alt me-2 text-primary"></i>Requisition: <?= escape_html($req['requisition_number']) ?></h2>
            <span class="badge bg-<?= req_badge_color($req['status']) ?> fs-6 mt-1"><?= ucfirst($req['status']) ?></span>
        </div>
        <div class="d-flex gap-2">
            <a href="requisitions.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            
            <?php if ($req['status'] === 'submitted' && $can_approve): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-1"></i>Approve</button>
                </form>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times me-1"></i>Reject</button>
                </form>
            <?php endif; ?>
            
            <?php if ($can_convert): ?>
                <a href="convert_requisition_to_po.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-exchange-alt me-1"></i>Convert to PO</a>
            <?php endif; ?>
            
            <?php if ($req['status'] === 'converted'): ?>
                <a href="../inventory/purchase_order/view_purchase_order.php?id=<?= $req['po_id'] ?>" class="btn btn-info text-white"><i class="fas fa-eye me-1"></i>View PO</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Requisition Details -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr><th width="30%">Req Number:</th><td><?= escape_html($req['requisition_number']) ?></td></tr>
                        <tr><th>Request Date:</th><td><?= format_date($req['request_date']) ?></td></tr>
                        <tr><th>Required Date:</th><td><strong class="text-danger"><?= format_date($req['required_date']) ?></strong></td></tr>
                        <tr><th>Department:</th><td><?= escape_html($req['department']) ?></td></tr>
                        <tr><th>Requested By:</th><td><?= escape_html($req['created_by_name']) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-2">Justification / Notes</h6>
                    <p class="mb-0 fst-italic">"<?= nl2br(escape_html($req['notes'] ?: 'No notes provided.')) ?>"</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Line Items -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white pt-4 pb-0">
            <h5 class="text-primary mb-0">Requested Items</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Item Code</th>
                            <th>Description</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Est. Unit Price</th>
                            <th class="text-end">Est. Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                        <tr>
                            <td><?= escape_html($line['code']) ?></td>
                            <td><?= escape_html($line['item_name']) ?></td>
                            <td><?= floatval($line['quantity']) ?></td>
                            <td><?= escape_html($line['unit_code'] ?: 'PCS') ?></td>
                            <td><?= format_currency($line['estimated_unit_price']) ?></td>
                            <td class="text-end"><?= format_currency($line['estimated_line_total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="5" class="text-end">Total Estimated Value:</th>
                            <th class="text-end fs-5 text-primary"><?= format_currency($req['total_estimated_amount']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php 
function req_badge_color($status) {
    return match($status) {
        'draft'     => 'secondary',
        'submitted' => 'primary',
        'approved'  => 'success',
        'rejected'  => 'danger',
        'converted' => 'info',
        default     => 'secondary',
    };
}
include __DIR__ . '/../../templates/header.php'; 
?>

