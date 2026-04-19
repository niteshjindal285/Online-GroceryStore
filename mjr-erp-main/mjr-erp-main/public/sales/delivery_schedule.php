<?php
/**
 * Delivery Schedule Dashboard
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/delivery_service.php';

require_login();

$page_title = 'Delivery Schedule - MJR Group ERP';

// Get filter parameters
$delivery_status = get_param('delivery_status', ''); // Filter by 'open', 'pending', 'completed'
$search = get_param('search', '');
$page_num = max(1, intval(get_param('page', 1)));
$per_page = 20;

$status_map = [
    'open' => 'pending',
    'pending' => 'partial',
    'completed' => 'delivered',
];

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($delivery_status)) {
    $where_conditions[] = "COALESCE(ds.status, 'pending') = ?";
    $params[] = $status_map[$delivery_status] ?? $delivery_status;
} else {
    // By default, show non-completed deliveries
    $where_conditions[] = "COALESCE(ds.status, 'pending') != 'delivered'";
}

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE ? OR so.order_number LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_conditions[] = "i.company_id = ?";
$params[] = $_SESSION['company_id'];

$where_sql = implode(' AND ', $where_conditions);

// Get total count
$count_sql = "SELECT COUNT(*) as count
    FROM invoices i
    LEFT JOIN sales_orders so ON i.so_id = so.id
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN delivery_schedule ds ON ds.invoice_id = i.id
    WHERE {$where_sql}";
$total_items = db_fetch($count_sql, $params)['count'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

// Get Invoices for Delivery
$sql = "
    SELECT i.id as invoice_id, i.invoice_number, i.invoice_date,
           so.id as order_id, so.order_number, 
           c.name as customer_name,
           COALESCE(ds.status, 'pending') as schedule_status,
           (SELECT MAX(delivery_date) FROM deliveries WHERE invoice_id = i.id) as last_delivery_date
    FROM invoices i
    LEFT JOIN sales_orders so ON i.so_id = so.id
    JOIN customers c ON i.customer_id = c.id
    LEFT JOIN delivery_schedule ds ON ds.invoice_id = i.id
    WHERE {$where_sql}
    ORDER BY i.invoice_date DESC, i.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$invoices = db_fetch_all($sql, $params);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    .card { background-color: #1a1d21; border: 1px solid rgba(255,255,255,0.05); }
    .card-header { background-color: #212529 !important; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .table-premium thead th { 
        background-color: #fff !important; 
        color: #1a1d21 !important; 
        font-weight: 800; 
        padding: 12px 15px;
    }
    .table-premium tbody tr { border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.2s; }
    .table-premium tbody tr:hover { background-color: rgba(255,255,255,0.02); }
    
    .status-badge-premium {
        padding: 6px 16px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.7rem;
    }
    .btn-deliver-now {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
        color: #000 !important;
        font-weight: 800;
        border: none;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
    }
    .btn-deliver-now:hover {
        background: linear-gradient(135deg, #ffca2c 0%, #ffb300 100%);
        transform: translateY(-1px);
    }
</style>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="text-white fw-bold"><i class="fas fa-truck-loading me-2 text-primary"></i>Delivery Schedule</h2>
        <div class="btn-group shadow-sm">
            <a href="?delivery_status=open" class="btn btn-dark border-secondary <?= $delivery_status == 'open' ? 'active bg-secondary' : '' ?>">Open</a>
            <a href="?delivery_status=pending" class="btn btn-dark border-secondary <?= $delivery_status == 'pending' ? 'active bg-warning text-dark' : '' ?>">Pending</a>
            <a href="?delivery_status=completed" class="btn btn-dark border-secondary <?= $delivery_status == 'completed' ? 'active bg-success' : '' ?>">Completed</a>
            <a href="delivery_schedule.php" class="btn btn-dark border-secondary <?= empty($delivery_status) ? 'active bg-primary' : '' ?>">All Active</a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card mb-4 shadow-lg border-0">
        <div class="card-body py-3">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary border-end-0 text-muted"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control bg-dark text-white border-secondary border-start-0" name="search" value="<?= escape_html($search) ?>" placeholder="Search Invoice #, Order # or Customer...">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="delivery_schedule.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery List -->
    <div class="card shadow-lg border-0">
        <div class="card-header py-3">
            <h5 class="mb-0 text-white fw-bold">Active Delivery Queue</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-premium align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Invoice #</th>
                            <th>Sales Order #</th>
                            <th>Inv Date</th>
                            <th>Customer</th>
                            <th>Delivery Date</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="text-white-50">
                        <?php if (!empty($invoices)): ?>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="text-white fw-bold fs-5"><?= escape_html($inv['invoice_number']) ?></span>
                                    </td>
                                    <td><span class="badge bg-dark border border-secondary"><?= escape_html($inv['order_number'] ?: '-') ?></span></td>
                                    <td><?= format_date($inv['invoice_date']) ?></td>
                                    <td>
                                        <div class="text-white fw-semibold"><?= escape_html($inv['customer_name']) ?></div>
                                    </td>
                                    <td>
                                        <?= $inv['last_delivery_date'] ? format_date($inv['last_delivery_date']) : '<span class="opacity-25">-</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $schedule_status = $inv['schedule_status'] ?? 'pending';
                                        $status = 'open';
                                        $badge = 'secondary';
                                        $label = 'Open';
                                        if ($schedule_status == 'partial') { $status = 'pending'; $badge = 'warning text-dark'; $label = 'Pending'; }
                                        elseif ($schedule_status == 'delivered') { $status = 'completed'; $badge = 'success'; $label = 'Completed'; }
                                        ?>
                                        <span class="badge rounded-pill status-badge-premium bg-<?= $badge ?>"><?= $label ?></span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($status != 'completed'): ?>
                                            <a href="ship_order.php?invoice_id=<?= $inv['invoice_id'] ?>" class="btn btn-sm btn-deliver-now px-4 py-2">
                                                DELIVER NOW <i class="fas fa-chevron-right ms-2 small"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-success small fw-bold"><i class="fas fa-check-circle me-1"></i> LOGGED</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="opacity-25 mb-3">
                                        <i class="fas fa-box-open fa-4x"></i>
                                    </div>
                                    <p class="fs-5">No deliveries in this queue.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
