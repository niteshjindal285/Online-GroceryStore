<?php
/**
 * Sales Analytics
 * Sales performance analysis and insights
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ai_insights.php';

require_login();

$page_title = 'Sales Analytics - MJR Group ERP';

// Get AI Insights
$company_id = $_SESSION['company_id'];
$ai_insights = AIEngine::generateDashboardInsights($company_id);

// Get date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');

// Quick preset support
$preset = $_GET['preset'] ?? '';
if ($preset === 'last_month') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to   = date('Y-m-t',  strtotime('last day of last month'));
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to   = date('Y-12-31');
} elseif ($preset === 'last_year') {
    $date_from = date('Y-01-01', strtotime('-1 year'));
    $date_to   = date('Y-12-31', strtotime('-1 year'));
}

$company_id = $_SESSION['company_id'];

// ── Compute previous period (same duration, shifted back) ──────────────────────
$period_days  = (strtotime($date_to) - strtotime($date_from)) / 86400 + 1;
$prev_date_to   = date('Y-m-d', strtotime($date_from) - 86400);
$prev_date_from = date('Y-m-d', strtotime($prev_date_to) - ($period_days - 1) * 86400);

// Sales summary (current period)
$sales_summary = db_fetch("
    SELECT
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_order_value,
        SUM(CASE WHEN status IN ('delivered','paid','completed') THEN 1 ELSE 0 END) as completed_orders
    FROM sales_orders
    WHERE (order_date BETWEEN ? AND ?) AND company_id = ?
", [$date_from, $date_to, $company_id]);

// Previous period revenue (for MoM comparison)
$prev_summary = db_fetch("
    SELECT
        SUM(total_amount) as total_revenue,
        COUNT(*) as total_orders
    FROM sales_orders
    WHERE (order_date BETWEEN ? AND ?) AND company_id = ?
", [$prev_date_from, $prev_date_to, $company_id]);

$curr_rev  = floatval($sales_summary['total_revenue'] ?? 0);
$prev_rev  = floatval($prev_summary['total_revenue']  ?? 0);
$rev_change = $prev_rev > 0 ? round((($curr_rev - $prev_rev) / $prev_rev) * 100, 1) : null;

$curr_orders = intval($sales_summary['total_orders'] ?? 0);
$prev_orders = intval($prev_summary['total_orders']  ?? 0);
$orders_change = $prev_orders > 0 ? round((($curr_orders - $prev_orders) / $prev_orders) * 100, 1) : null;

// Sales trends (for chart)
$sales_trends = db_fetch_all("
    SELECT
        DATE(order_date) as date,
        SUM(total_amount) as revenue,
        COUNT(*) as order_count
    FROM sales_orders
    WHERE (order_date BETWEEN ? AND ?) AND company_id = ?
    GROUP BY DATE(order_date)
    ORDER BY date ASC
", [$date_from, $date_to, $company_id]);

$trend_labels  = json_encode(array_column($sales_trends, 'date'));
$trend_revenue = json_encode(array_column($sales_trends, 'revenue'));
$trend_orders  = json_encode(array_column($sales_trends, 'order_count'));

// Monthly revenue (bar chart — last 6 months)
$monthly_revenue = db_fetch_all("
    SELECT DATE_FORMAT(order_date,'%b %Y') as month_label,
           YEAR(order_date)*100+MONTH(order_date) as sort_key,
           SUM(total_amount) as revenue
    FROM sales_orders
    WHERE company_id = ? AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
", [$company_id]);

$monthly_labels  = json_encode(array_column($monthly_revenue, 'month_label'));
$monthly_rev_data = json_encode(array_column($monthly_revenue, 'revenue'));

// Top customers
$top_customers = db_fetch_all("
    SELECT
        c.name as customer_name,
        COUNT(so.id) as order_count,
        SUM(so.total_amount) as total_spent
    FROM customers c
    JOIN sales_orders so ON c.id = so.customer_id
    WHERE (so.order_date BETWEEN ? AND ?) AND so.company_id = ?
    GROUP BY c.id, c.name
    ORDER BY total_spent DESC
    LIMIT 10
", [$date_from, $date_to, $company_id]);

// Top products
$top_products = db_fetch_all("
    SELECT
        i.name as product_name,
        i.code as product_code,
        SUM(sol.quantity) as total_quantity,
        SUM(sol.line_total) as total_revenue
    FROM sales_order_lines sol
    JOIN inventory_items i ON sol.item_id = i.id
    JOIN sales_orders so ON sol.order_id = so.id
    WHERE (so.order_date BETWEEN ? AND ?) AND so.company_id = ?
    GROUP BY i.id, i.name, i.code
    ORDER BY total_revenue DESC
    LIMIT 10
", [$date_from, $date_to, $company_id]);

// Sales by status
$sales_by_status = db_fetch_all("
    SELECT
        status,
        COUNT(*) as count,
        SUM(total_amount) as revenue
    FROM sales_orders
    WHERE (order_date BETWEEN ? AND ?) AND company_id = ?
    GROUP BY status
    ORDER BY revenue DESC
", [$date_from, $date_to, $company_id]);

$status_labels = json_encode(array_column($sales_by_status, 'status'));
$status_counts = json_encode(array_column($sales_by_status, 'count'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="fas fa-chart-line me-2"></i>Sales Analytics</h2>
        <p class="lead mb-0">Sales performance analysis and insights</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="dropdown d-inline-block">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="export_dashboard.php?module=sales&format=pdf&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=sales&format=word&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-word text-primary me-2"></i>Export as Word</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=sales&format=excel&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-excel text-success me-2"></i>Export as Excel</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#emailReportModal"><i class="fas fa-envelope text-info me-2"></i>Email Report</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Email Modal -->
<div class="modal fade" id="emailReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="export_dashboard.php" method="POST">
                <input type="hidden" name="module" value="sales">
                <input type="hidden" name="format" value="email">
                <input type="hidden" name="date_from" value="<?= escape_html($date_from) ?>">
                <input type="hidden" name="date_to" value="<?= escape_html($date_to) ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Email Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Recipient Email</label>
                        <input type="email" name="email" class="form-control" value="<?= escape_html($_SESSION['email'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Attached Format</label>
                        <select name="email_format" class="form-select">
                            <option value="pdf">PDF Document</option>
                            <option value="excel">Excel Spreadsheet</option>
                            <option value="word">Word Document</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Email</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Date Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= escape_html($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= escape_html($date_to) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sync me-2"></i>Update
                    </button>
                </div>
                <!-- Quick presets -->
                <div class="col-md-4 d-flex gap-2 flex-wrap">
                    <a href="?preset=this_month" class="btn btn-outline-secondary btn-sm">This Month</a>
                    <a href="?preset=last_month" class="btn btn-outline-secondary btn-sm">Last Month</a>
                    <a href="?preset=this_year"  class="btn btn-outline-secondary btn-sm">This Year</a>
                    <a href="?preset=last_year"  class="btn btn-outline-secondary btn-sm">Last Year</a>
                </div>
            </form>
        </div>
    </div>

    <!-- AI Insights Engine Section -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI/ML Advanced Analytics & Insights</h5>
            <span class="badge bg-dark text-info"><i class="fas fa-robot me-1"></i>Auto-Generated</span>
        </div>
        <div class="card-body bg-dark text-light">
            <?php if (!empty($ai_insights)): ?>
            <div class="row g-3">
                <?php foreach ($ai_insights as $insight): ?>
                <div class="col-md-6 mb-2">
                    <div class="d-flex align-items-start bg-black p-3 border-start border-4 border-<?= $insight['color'] ?> rounded shadow-sm h-100">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-<?= $insight['color'] ?> bg-opacity-25 text-<?= $insight['color'] ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="fas <?= $insight['icon'] ?> fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1 text-<?= $insight['color'] ?>"><?= $insight['title'] ?></strong>
                            <p class="mb-0 text-white-50 small"><?= $insight['message'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-white-50 m-0"><i class="fas fa-info-circle me-1"></i>Not enough data to generate patterns yet. System needs more history.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Orders</h6>
                    <h2><?= number_format($curr_orders) ?></h2>
                    <?php if ($orders_change !== null): ?>
                    <small class="<?= $orders_change >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <i class="fas fa-arrow-<?= $orders_change >= 0 ? 'up' : 'down' ?>"></i>
                        <?= abs($orders_change) ?>% vs prev period
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Revenue</h6>
                    <h2><?= format_currency($curr_rev) ?></h2>
                    <?php if ($rev_change !== null): ?>
                    <small class="<?= $rev_change >= 0 ? 'text-success-emphasis' : 'text-danger-emphasis' ?>">
                        <i class="fas fa-arrow-<?= $rev_change >= 0 ? 'up' : 'down' ?>"></i>
                        <?= abs($rev_change) ?>% vs prev period
                    </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Avg Order Value</h6>
                    <h2><?= format_currency($sales_summary['avg_order_value'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Completed Orders</h6>
                    <h2><?= $sales_summary['completed_orders'] ?? 0 ?></h2>
                    <small>Delivered + Paid</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Revenue Trend (Selected Period)</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Orders by Status</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Revenue Bar Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-chart-bar me-2"></i>Monthly Revenue (Last 6 Months)</h5>
        </div>
        <div class="card-body">
            <div style="position:relative; height:240px;">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Top Customers -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Top Customers by Revenue</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_customers)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm" id="customersTable">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $customer): ?>
                                <tr>
                                    <td><?= escape_html($customer['customer_name']) ?></td>
                                    <td class="text-center"><?= $customer['order_count'] ?></td>
                                    <td class="text-end"><?= format_currency($customer['total_spent']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No customer data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-box me-2"></i>Top Products by Revenue</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_products)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Qty Sold</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td>
                                        <?= escape_html($product['product_name']) ?><br>
                                        <small class="text-muted"><?= escape_html($product['product_code']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $product['total_quantity'] ?></td>
                                    <td class="text-end"><?= format_currency($product['total_revenue']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No product data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales by Status -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-chart-pie me-2"></i>Sales by Status</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($sales_by_status)): ?>
            <div class="row">
                <?php
                $status_colors = [
                    'pending'    => 'secondary',
                    'confirmed'  => 'info',
                    'shipped'    => 'primary',
                    'delivered'  => 'success',
                    'paid'       => 'success',
                    'cancelled'  => 'danger',
                    'completed'  => 'success',
                ];
                foreach ($sales_by_status as $st):
                    $color = $status_colors[$st['status']] ?? 'secondary';
                ?>
                <div class="col-md-3 mb-3">
                    <div class="card bg-<?= $color ?>">
                        <div class="card-body text-center">
                            <h6 class="text-uppercase"><?= ucfirst(str_replace('_',' ',$st['status'])) ?></h6>
                            <h4><?= $st['count'] ?> Orders</h4>
                            <p class="mb-0"><?= format_currency($st['revenue']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-muted py-4">No sales data available</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Revenue Trend Chart
    const revCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revCtx, {
        type: 'line',
        data: {
            labels: <?= $trend_labels ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= $trend_revenue ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.1)',
                fill: true, tension: 0.3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Status Donut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $status_labels ?>,
            datasets: [{ data: <?= $status_counts ?>,
                backgroundColor: ['#198754','#ffc107','#0dcafd','#6c757d','#dc3545','#0d6efd','#20c997'] }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Monthly Revenue Bar Chart
    const monthCtx = document.getElementById('monthlyChart').getContext('2d');
    new Chart(monthCtx, {
        type: 'bar',
        data: {
            labels: <?= $monthly_labels ?>,
            datasets: [{
                label: 'Monthly Revenue',
                data: <?= $monthly_rev_data ?>,
                backgroundColor: 'rgba(13,110,253,0.7)',
                borderColor: '#0d6efd', borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // DataTables
    if ($.fn.DataTable) {
        $('#customersTable, #productsTable').DataTable({ pageLength: 10, order: [[2,'desc']] });
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
