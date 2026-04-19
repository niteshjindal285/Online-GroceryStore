<?php
/**
 * Delivery Schedule — Queue of pending/partial deliveries
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Delivery Schedule - MJR Group ERP';
$company_id = $_SESSION['company_id'];
$filter     = get('status', 'pending');

$where = "WHERE (i.company_id = ? OR i.company_id IS NULL)";
$params = [$company_id];

if ($filter === 'delivered') {
    $where .= " AND ds.status = 'delivered'";
} elseif ($filter === 'all') {
    // Show all
} else {
    // Default: Pending/Partial
    $where .= " AND ds.status != 'delivered'";
}

$deliveries = db_fetch_all("
    SELECT ds.*, i.invoice_number, i.total_amount, i.customer_id, c.name AS customer_name,
           i.invoice_date, i.due_date
    FROM delivery_schedule ds
    JOIN invoices i ON i.id = ds.invoice_id
    JOIN customers c ON c.id = i.customer_id
    $where
    ORDER BY ds.created_at ASC
", $params);

$pending_c = db_fetch("SELECT COUNT(*) c FROM delivery_schedule ds JOIN invoices i ON i.id=ds.invoice_id WHERE ds.status='pending' AND (i.company_id=? OR i.company_id IS NULL)", [$company_id])['c'] ?? 0;
$partial_c = db_fetch("SELECT COUNT(*) c FROM delivery_schedule ds JOIN invoices i ON i.id=ds.invoice_id WHERE ds.status='partial' AND (i.company_id=? OR i.company_id IS NULL)", [$company_id])['c'] ?? 0;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-truck me-2"></i>Delivery Schedule</h2>
        <p class="text-muted mb-0">Manage pending and partial deliveries</p>
    </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-white" style="background:linear-gradient(135deg,#d97706,#ea580c);">
            <div class="card-body"><h6>Pending Delivery</h6><h3><?= $pending_c ?></h3><small>Awaiting dispatch</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">
            <div class="card-body"><h6>Partial Delivery</h6><h3><?= $partial_c ?></h3><small>Remaining items</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body"><h6 class="text-success">All Deliveries</h6>
                <a href="?status=all" class="btn btn-sm btn-outline-success">View All</a>
                <a href="?status=delivered" class="btn btn-sm btn-outline-secondary ms-1">Completed</a>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <?= $filter === 'delivered' ? 'Completed Deliveries' : ($filter === 'all' ? 'All Deliveries' : 'To-Be-Delivered Queue') ?>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Invoice #</th><th>Customer</th><th>Invoice Date</th>
                        <th>Scheduled</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr class="<?= $d['status']==='partial' ? 'table-warning' : '' ?>">
                        <td><a href="../invoices/view_invoice.php?id=<?= $d['invoice_id'] ?>"><code><?= escape_html($d['invoice_number']) ?></code></a></td>
                        <td><?= escape_html($d['customer_name']) ?></td>
                        <td><?= format_date($d['invoice_date']) ?></td>
                        <td><?= $d['scheduled_date'] ? format_date($d['scheduled_date']) : '<em class="text-muted">Not set</em>' ?></td>
                        <td>
                            <?php 
                            $badge_class = 'info';
                            if ($d['status'] === 'partial') $badge_class = 'warning text-dark';
                            if ($d['status'] === 'delivered') $badge_class = 'success';
                            ?>
                            <span class="badge bg-<?= $badge_class ?>">
                                <?= ucfirst($d['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($d['status'] !== 'delivered'): ?>
                                <a href="add_delivery.php?delivery_id=<?= $d['id'] ?>" class="btn btn-sm btn-success me-1">
                                    <i class="fas fa-truck me-1"></i>Deliver
                                </a>
                            <?php endif; ?>
                            <a href="view_delivery.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-info">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deliveries)): ?>
    <tr><td colspan="6" class="text-center py-4 text-success"><i class="fas fa-check-circle me-2"></i>No pending deliveries! All caught up.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
