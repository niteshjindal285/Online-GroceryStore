<?php
/**
 * Invoices List
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title     = 'Invoices - MJR Group ERP';
$company_id     = $_SESSION['company_id'];
$filter_status  = get('status', '');
$filter_search  = trim(get('q', ''));

$where  = "WHERE (i.company_id = ? OR i.company_id IS NULL)";
$params = [$company_id];

if ($filter_status) {
    $where  .= " AND i.payment_status = ?";
    $params[] = $filter_status;
}
if ($filter_search) {
    $where  .= " AND (i.invoice_number LIKE ? OR c.name LIKE ?)";
    $params[] = '%'.$filter_search.'%';
    $params[] = '%'.$filter_search.'%';
}

$invoices = db_fetch_all("
    SELECT i.*, c.name AS customer_name
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    $where
    ORDER BY i.created_at DESC
    LIMIT 200
", $params);

$open_count   = db_fetch("SELECT COUNT(*) c FROM invoices WHERE payment_status='open' AND (company_id=? OR company_id IS NULL)", [$company_id])['c'] ?? 0;
$closed_count = db_fetch("SELECT COUNT(*) c FROM invoices WHERE payment_status='closed' AND (company_id=? OR company_id IS NULL)", [$company_id])['c'] ?? 0;
$total_open   = db_fetch("SELECT COALESCE(SUM(total_amount-amount_paid),0) v FROM invoices WHERE payment_status='open' AND (company_id=? OR company_id IS NULL)", [$company_id])['v'] ?? 0;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-file-invoice-dollar me-2"></i>Invoices</h2>
        <p class="text-muted mb-0">Create and manage customer invoices</p>
    </div>
    <a href="add_invoice.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>New Invoice</a>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-white" style="background:linear-gradient(135deg,#d97706,#ea580c);">
            <div class="card-body">
                <h6>Open Invoices</h6>
                <h3><?= $open_count ?></h3>
                <small class="opacity-75">Outstanding: <?= format_currency($total_open) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white" style="background:linear-gradient(135deg,#059669,#10b981);">
            <div class="card-body">
                <h6>Closed Invoices</h6>
                <h3><?= $closed_count ?></h3>
                <small class="opacity-75">Fully paid</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-info">
            <div class="card-body">
                <h6 class="text-info">Quick Actions</h6>
                <a href="../delivery/index.php" class="btn btn-sm btn-outline-info w-100 mb-1"><i class="fas fa-truck me-1"></i>Delivery Schedule</a>
                <a href="../reports/index.php" class="btn btn-sm btn-outline-secondary w-100"><i class="fas fa-chart-bar me-1"></i>Reports</a>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control" placeholder="Search invoice # or customer..." value="<?= escape_html($filter_search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="open" <?= $filter_status==='open'?'selected':'' ?>>Open</option>
                    <option value="closed" <?= $filter_status==='closed'?'selected':'' ?>>Closed</option>
                    <option value="cancelled" <?= $filter_status==='cancelled'?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="index.php" class="btn btn-secondary ms-1">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Invoice #</th><th>Customer</th><th>Date</th><th>Due Date</th>
                        <th>Total</th><th>Paid</th><th>Outstanding</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv):
                        $outstanding = $inv['total_amount'] - $inv['amount_paid'];
                        $sc = ['open'=>'warning text-dark', 'closed'=>'success', 'cancelled'=>'secondary'];
                        $badge = $sc[$inv['payment_status']] ?? 'light';
                    ?>
                    <tr>
                        <td><a href="view_invoice.php?id=<?= $inv['id'] ?>" class="fw-bold text-decoration-none"><code><?= escape_html($inv['invoice_number']) ?></code></a></td>
                        <td><?= escape_html($inv['customer_name']) ?></td>
                        <td><?= format_date($inv['invoice_date']) ?></td>
                        <td class="<?= (!empty($inv['due_date']) && $inv['due_date'] < date('Y-m-d') && $inv['payment_status']==='open') ? 'text-danger fw-bold' : '' ?>">
                            <?= format_date($inv['due_date']) ?>
                        </td>
                        <td><?= format_currency($inv['total_amount']) ?></td>
                        <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                        <td class="fw-bold <?= $outstanding > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($outstanding) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= ucfirst($inv['payment_status']) ?></span></td>
                        <td>
                            <a href="view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-info me-1"><i class="fas fa-eye"></i></a>
                            <a href="print_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-print"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="9" class="text-center py-4 text-muted">No invoices found. <a href="add_invoice.php">Create one.</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
