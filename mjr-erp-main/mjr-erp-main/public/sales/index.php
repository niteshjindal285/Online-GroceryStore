<?php
/**
 * Sales Module - Main Page (Enhanced with CRM & Pipeline)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Sales - MJR Group ERP';

$company_id = $_SESSION['company_id'];

$total_customers = db_fetch("SELECT COUNT(*) as count FROM customers WHERE is_active = 1 AND company_id = ?", [$company_id])['count'] ?? 0;
$total_orders = db_fetch("SELECT COUNT(*) as count FROM sales_orders WHERE company_id = ?", [$company_id])['count'] ?? 0;
$total_sales = db_fetch("SELECT COALESCE(SUM(total_amount), 0) as total FROM sales_orders WHERE status = 'completed' AND company_id = ?", [$company_id])['total'] ?? 0;

$monthly_sales = db_fetch("SELECT SUM(total_amount) as total FROM sales_orders WHERE status != 'cancelled' AND MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE()) AND company_id = ?", [$company_id])['total'] ?? 0;
$pending_count = db_fetch("SELECT COUNT(*) as count FROM sales_orders WHERE status IN ('confirmed', 'in_production') AND company_id = ?", [$company_id])['count'] ?? 0;
$overdue_count = db_fetch("SELECT COUNT(*) as count FROM sales_orders WHERE required_date < CURRENT_DATE() AND status NOT IN ('delivered', 'shipped', 'cancelled') AND company_id = ?", [$company_id])['count'] ?? 0;

$total_quotes = db_fetch("SELECT COUNT(*) as count FROM quotes WHERE company_id = ?", [$company_id])['count'] ?? 0;
$accepted_quotes = db_fetch("SELECT COUNT(*) as count FROM quotes WHERE status = 'accepted' AND company_id = ?", [$company_id])['count'] ?? 0;
$conversion_rate = $total_quotes > 0 ? ($accepted_quotes / $total_quotes) * 100 : 0;


// Pipeline funnel data
$pipeline = [
    'draft' => db_fetch("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as v FROM quotes WHERE status='draft' AND company_id=?", [$company_id]),
    'sent' => db_fetch("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as v FROM quotes WHERE status='sent' AND company_id=?", [$company_id]),
    'accepted' => db_fetch("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as v FROM quotes WHERE status='accepted' AND company_id=?", [$company_id]),
    'orders' => db_fetch("SELECT COUNT(*) as c, COALESCE(SUM(total_amount),0) as v FROM sales_orders WHERE status NOT IN ('cancelled') AND company_id=?", [$company_id]),
];

// Recent orders
$recent_orders = db_fetch_all("
    SELECT so.*, c.name as customer_name, u.username as created_by_name
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    JOIN users u ON so.created_by = u.id
    WHERE so.company_id = ?
    ORDER BY so.created_at DESC
    LIMIT 8
", [$company_id]);

// Top customers this month
$top_customers = db_fetch_all("
    SELECT c.id, c.name,
           SUM(so.total_amount) as revenue,
           COUNT(so.id) as order_count
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.company_id = ?
      AND so.status != 'cancelled'
      AND MONTH(so.order_date) = MONTH(CURDATE())
      AND YEAR(so.order_date) = YEAR(CURDATE())
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
    LIMIT 5
", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-shopping-cart me-2"></i>Sales Module</h2>
        <p class="text-muted mb-0">Overview &amp; Quick Actions</p>
    </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4 g-3">
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#2563eb,#4f46e5);">
            <div class="card-body">
                <h6 class="card-title">Monthly Sales</h6>
                <h2>
                    <?= format_currency($monthly_sales) ?>
                </h2>
                <div class="small opacity-75">Total for
                    <?= date('F Y') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#d97706,#ef4444);">
            <div class="card-body">
                <h6 class="card-title">Pending Orders</h6>
                <h2>
                    <?= $pending_count ?>
                </h2>
                <div class="small opacity-75">
                    <?= $overdue_count ?> overdue orders
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#059669,#10b981);">
            <div class="card-body">
                <h6 class="card-title">Total Sales (Completed)</h6>
                <h2>
                    <?= format_currency($total_sales) ?>
                </h2>
                <div class="small opacity-75">Lifetime value</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#0ea5e9,#06b6d4);">
            <div class="card-body">
                <h6 class="card-title">Quote Conversion</h6>
                <h2>
                    <?= number_format($conversion_rate, 1) ?>%
                </h2>
                <div class="small opacity-75">
                    <?= $accepted_quotes ?> of
                    <?= $total_quotes ?> quotes
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row g-4 mb-4">
    <!-- Sales Pipeline Funnel -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2 text-info"></i>Sales Pipeline</h5>
            </div>
            <div class="card-body">
                <canvas id="pipelineChart" style="max-height:220px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Customers This Month -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-star me-2 text-warning"></i>Top Customers (
                    <?= date('F') ?>)
                </h5>
                <a href="../inventory/customer/customers.php" class="btn btn-sm btn-outline-secondary">All Customers</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($top_customers)): ?>
                    <div class="text-center py-4 text-muted">No orders this month yet</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php $max = $top_customers[0]['revenue'] ?? 1; ?>
                        <?php foreach ($top_customers as $i => $c): ?>
                            <a href="../inventory/customer/view_customer.php?id=<?= $c['id'] ?>"
                                class="list-group-item list-group-item-action px-3 py-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold">
                                        <?= escape_html($c['name']) ?>
                                    </span>
                                    <span class="text-success fw-bold">
                                        <?= format_currency($c['revenue']) ?>
                                    </span>
                                </div>
                                <div class="progress" style="height:4px;">
                                    <div class="progress-bar bg-success" style="width:<?= ($c['revenue'] / $max * 100) ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?= $c['order_count'] ?> order
                                    <?= $c['order_count'] != 1 ? 's' : '' ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Secondary KPI Row -->
<div class="row mb-4 g-3">
    <div class="col-md-4">
        <div class="card border-primary">
            <div class="card-body">
                <h6 class="text-primary">Total Customers</h6>
                <h3>
                    <?= format_number($total_customers, 0) ?>
                </h3>
                <a href="../inventory/customer/customers.php" class="small">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="text-success">Total Orders</h6>
                <h3>
                    <?= format_number($total_orders, 0) ?>
                </h3>
                <a href="orders.php" class="small">View All <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- New Module Navigation Tiles -->
<div class="row mb-4 g-3">
    <div class="col-12"><h5 class="text-muted fw-semibold border-bottom pb-2"><i class="fas fa-th-large me-2"></i>Financial & Credit Modules</h5></div>

    <div class="col-md-4 col-lg-2">
        <a href="debtors/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#2563eb,#4f46e5);">
            <div class="card-body text-center py-3">
                <i class="fas fa-users fa-2x mb-2"></i>
                <div class="fw-bold">Debtors</div>
                <small class="opacity-75">Credit Control</small>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="invoices/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#d97706,#ea580c);">
            <div class="card-body text-center py-3">
                <i class="fas fa-file-invoice-dollar fa-2x mb-2"></i>
                <div class="fw-bold">Invoices</div>
                <small class="opacity-75">Create & Manage</small>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="delivery/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#059669,#10b981);">
            <div class="card-body text-center py-3">
                <i class="fas fa-truck fa-2x mb-2"></i>
                <div class="fw-bold">Delivery</div>
                <small class="opacity-75">Schedule & Notes</small>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="discounts/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
            <div class="card-body text-center py-3">
                <i class="fas fa-tags fa-2x mb-2"></i>
                <div class="fw-bold">Discounts</div>
                <small class="opacity-75">Approval Workflow</small>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="price_changes/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#be185d,#e11d48);">
            <div class="card-body text-center py-3">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <div class="fw-bold">Price Changes</div>
                <small class="opacity-75">Manager Approval</small>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="returns/index.php" class="card text-decoration-none text-white h-100" style="background:linear-gradient(135deg,#b45309,#d97706);">
            <div class="card-body text-center py-3">
                <i class="fas fa-undo fa-2x mb-2"></i>
                <div class="fw-bold">Sales Returns</div>
                <small class="opacity-75">Stock Restore</small>
            </div>
        </a>
    </div>
</div>
<div class="row mb-4 g-3">
    <div class="col-md-4 col-lg-2">
        <a href="reports/index.php" class="card text-decoration-none h-100 border-info">
            <div class="card-body text-center py-3 text-info">
                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                <div class="fw-bold">Sales Reports</div>
                <small class="text-muted">Aging & Outstanding</small>
            </div>
        </a>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Orders</h5>
        <a href="orders.php" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent_orders)): ?>
            <p class="text-muted text-center py-4">No sales orders yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order):
                            $sc = ['completed' => 'success', 'cancelled' => 'danger', 'confirmed' => 'primary', 'delivered' => 'success', 'draft' => 'secondary'];
                            $badge = $sc[$order['status']] ?? 'warning';
                            ?>
                            <tr>
                                <td>
                                    <?= escape_html($order['order_number']) ?>
                                </td>
                                <td>
                                    <?= escape_html($order['customer_name']) ?>
                                </td>
                                <td>
                                    <?= format_date($order['order_date']) ?>
                                </td>
                                <td>
                                    <?= format_currency($order['total_amount']) ?>
                                </td>
                                <td><span class="badge bg-<?= $badge ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span></td>
                                <td><a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-info"><i
                                            class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Links -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Quick Actions</h5>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2 col-6">
                <a href="../inventory/customer/customers.php" class="btn btn-outline-primary w-100"><i
                        class="fas fa-users d-block mb-1"></i>Customers</a>
            </div>
            <div class="col-md-2 col-6">
                <a href="orders.php" class="btn btn-outline-success w-100"><i
                        class="fas fa-shopping-cart d-block mb-1"></i>Orders</a>
            </div>
            <div class="col-md-2 col-6">
                <a href="quotes.php" class="btn btn-outline-info w-100"><i
                        class="fas fa-file-invoice d-block mb-1"></i>Quotes</a>
            </div>

        </div>
    </div>
</div>

<?php
$pipeline_labels = json_encode(['Draft Quotes', 'Sent Quotes', 'Accepted Quotes', 'Active Orders']);
$pipeline_counts = json_encode([(int) $pipeline['draft']['c'], (int) $pipeline['sent']['c'], (int) $pipeline['accepted']['c'], (int) $pipeline['orders']['c']]);
$pipeline_values = json_encode([round($pipeline['draft']['v'], 2), round($pipeline['sent']['v'], 2), round($pipeline['accepted']['v'], 2), round($pipeline['orders']['v'], 2)]);

$additional_scripts = "
<script>
const plCtx = document.getElementById('pipelineChart');
if (plCtx) {
    new Chart(plCtx, {
        type: 'bar',
        data: {
            labels: $pipeline_labels,
            datasets: [
                {
                    label: 'Count',
                    data: $pipeline_counts,
                    backgroundColor: ['#6b7280','#0ea5e9','#10b981','#4f46e5'],
                    borderRadius: 6,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        afterLabel: function(ctx) {
                            const vals = $pipeline_values;
                            return 'Value: \$' + Number(vals[ctx.dataIndex]).toLocaleString('en-US',{minimumFractionDigits:2});
                        }
                    }
                }
            },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
    });
}
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
