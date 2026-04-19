<?php
/**
 * Purchase Requisitions List
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Purchase Requisitions - MJR Group ERP';

// Fetch requisitions
$requisitions = db_fetch_all("
    SELECT r.*, u.username as created_by_name, p.po_number 
    FROM purchase_requisitions r
    LEFT JOIN users u ON r.created_by = u.id
    LEFT JOIN purchase_orders p ON r.po_id = p.id
    ORDER BY r.request_date DESC, r.id DESC
");

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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-clipboard-list me-2"></i>Purchase Requisitions</h2>
            <p class="text-muted">Internal requests for goods and services</p>
        </div>
        <div>
            <a href="add_requisition.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> New Request
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (!empty($requisitions)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Req Number</th>
                            <th>Request Date</th>
                            <th>Department</th>
                            <th>Requested By</th>
                            <th>Estimated Total</th>
                            <th>Status</th>
                            <th>Converted PO</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requisitions as $r): ?>
                        <tr>
                            <td><strong><?= escape_html($r['requisition_number']) ?></strong></td>
                            <td><?= format_date($r['request_date']) ?></td>
                            <td><?= escape_html($r['department']) ?></td>
                            <td><?= escape_html($r['created_by_name']) ?></td>
                            <td><?= format_currency($r['total_estimated_amount']) ?></td>
                            <td>
                                <span class="badge bg-<?= req_badge_color($r['status']) ?>">
                                    <?= ucfirst($r['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r['po_number']): ?>
                                    <a href="../inventory/purchase_order/view_purchase_order.php?id=<?= $r['po_id'] ?>" class="text-info"><?= escape_html($r['po_number']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="view_requisition.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center p-5">
                <i class="fas fa-clipboard fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No requisitions found</h5>
                <p>Click "New Request" to create the first purchase requisition.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

