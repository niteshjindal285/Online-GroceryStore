<?php
/**
 * Sales Order Discount Approval Dashboard
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

// Only managers and admins can access this dashboard
if (!is_admin() && $_SESSION['role'] !== 'manager') {
    set_flash('Access Denied. Managerial privileges required.', 'error');
    redirect('../../index.php');
}

$page_title = 'Order Discount Approval - MJR Group ERP';

// Fetch orders pending discount approval
$pending_orders = db_fetch_all("
    SELECT so.*, c.name as customer_name, c.customer_code, u.username as created_by_name
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    LEFT JOIN users u ON so.created_by = u.id
    WHERE so.status = 'pending_discount'
    ORDER BY so.created_at DESC
");

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-percentage me-2 text-primary"></i>Order Discount Approval</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../../index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="../orders.php">Sales</a></li>
                <li class="breadcrumb-item active">Discount Approval</li>
            </ol>
        </nav>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Pending Approval Requests</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Created By</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_orders)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x mb-3 d-block"></i>
                                    No pending discount requests found.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary"><?= escape_html($order['order_number']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= escape_html($order['customer_name']) ?></div>
                                        <small class="text-muted"><?= escape_html($order['customer_code']) ?></small>
                                    </td>
                                    <td><?= format_date($order['order_date']) ?></td>
                                    <td><?= escape_html($order['created_by_name']) ?></td>
                                    <td class="text-end fw-bold">
                                        <?= format_currency($order['total_amount']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark px-3 py-2">Pending Approval</span>
                                    </td>
                                    <td class="text-center">
                                        <a href="adjust.php?id=<?= $order['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-edit me-1"></i>Get Discount
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
