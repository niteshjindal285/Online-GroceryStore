<?php
/**
 * Production Analytics
 * Production performance and efficiency metrics
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Production Analytics - MJR Group ERP';

// Date range
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');

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

// Production summary — use start_date (or due_date) for meaningful period filtering
$production_summary = db_fetch("
    SELECT
        COUNT(*) as total_production_orders,
        COALESCE(SUM(quantity), 0) as total_quantity,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN quantity ELSE 0 END), 0) as completed_quantity,
        SUM(CASE WHEN status IN ('planned','in_progress') THEN 1 ELSE 0 END) as active_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM work_orders
    WHERE start_date BETWEEN ? AND ?
       OR (start_date IS NULL AND created_at BETWEEN ? AND ?)
", [$date_from, $date_to, $date_from, $date_to]);

// Production orders by status
$orders_by_status = db_fetch_all("
    SELECT
        status,
        COUNT(*) as count,
        COALESCE(SUM(quantity), 0) as total_quantity
    FROM work_orders
    WHERE (start_date BETWEEN ? AND ?)
       OR (start_date IS NULL AND created_at BETWEEN ? AND ?)
    GROUP BY status
    ORDER BY count DESC
", [$date_from, $date_to, $date_from, $date_to]);

$status_labels_arr = array_column($orders_by_status, 'status');
$status_counts_arr = array_column($orders_by_status, 'count');
$status_labels_json = json_encode($status_labels_arr);
$status_counts_json = json_encode($status_counts_arr);

// Production by location
$production_by_location = db_fetch_all("
    SELECT
        l.name as location_name,
        COUNT(wo.id) as order_count,
        COALESCE(SUM(wo.quantity),0) as total_quantity,
        SUM(CASE WHEN wo.status='completed' THEN 1 ELSE 0 END) as completed_count
    FROM locations l
    LEFT JOIN work_orders wo ON l.id = wo.location_id
        AND (
            (wo.start_date BETWEEN ? AND ?)
            OR (wo.start_date IS NULL AND wo.created_at BETWEEN ? AND ?)
        )
    WHERE l.is_active = 1
    GROUP BY l.id, l.name
    ORDER BY order_count DESC
", [$date_from, $date_to, $date_from, $date_to]);

// Top produced products
$top_products = db_fetch_all("
    SELECT
        i.code, i.name,
        COUNT(wo.id) as order_count,
        COALESCE(SUM(wo.quantity),0) as total_quantity
    FROM work_orders wo
    JOIN inventory_items i ON wo.product_id = i.id
    WHERE wo.status = 'completed'
      AND (
          (wo.start_date BETWEEN ? AND ?)
          OR (wo.start_date IS NULL AND wo.created_at BETWEEN ? AND ?)
      )
    GROUP BY i.id, i.code, i.name
    ORDER BY total_quantity DESC
    LIMIT 10
", [$date_from, $date_to, $date_from, $date_to]);

// On-time delivery rate
$on_time_orders = db_fetch("
    SELECT COUNT(*) as count
    FROM work_orders
    WHERE status = 'completed'
      AND completion_date IS NOT NULL
      AND due_date IS NOT NULL
      AND completion_date <= due_date
      AND (
          (start_date BETWEEN ? AND ?)
          OR (start_date IS NULL AND created_at BETWEEN ? AND ?)
      )
", [$date_from, $date_to, $date_from, $date_to]);

$total_completed    = intval($production_summary['completed_orders'] ?? 0);
$on_time_percentage = $total_completed > 0
    ? round((intval($on_time_orders['count']) / $total_completed) * 100, 1)
    : 0;

// Monthly completion trend (last 6 months)
$monthly_completions = db_fetch_all("
    SELECT DATE_FORMAT(completion_date,'%b %Y') as month_label,
           YEAR(completion_date)*100+MONTH(completion_date) as sort_key,
           COUNT(*) as completed_orders,
           COALESCE(SUM(quantity),0) as units_produced
    FROM work_orders
    WHERE status='completed' AND completion_date IS NOT NULL
      AND completion_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
");

$comp_month_labels = json_encode(array_column($monthly_completions, 'month_label'));
$comp_units_data   = json_encode(array_column($monthly_completions, 'units_produced'));
$comp_orders_data  = json_encode(array_column($monthly_completions, 'completed_orders'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="fas fa-cogs me-2"></i>Production Analytics</h2>
        <p class="lead mb-0">Production performance and efficiency metrics</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="dropdown d-inline-block">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="export_dashboard.php?module=production&format=pdf&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=production&format=word&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-word text-primary me-2"></i>Export as Word</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=production&format=excel&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-excel text-success me-2"></i>Export as Excel</a></li>
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
                <input type="hidden" name="module" value="production">
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
                <div class="col-md-4 d-flex gap-2 flex-wrap">
                    <a href="?preset=this_month" class="btn btn-outline-secondary btn-sm">This Month</a>
                    <a href="?preset=last_month" class="btn btn-outline-secondary btn-sm">Last Month</a>
                    <a href="?preset=this_year"  class="btn btn-outline-secondary btn-sm">This Year</a>
                    <a href="?preset=last_year"  class="btn btn-outline-secondary btn-sm">Last Year</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Production Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Production Orders</h6>
                    <h2><?= intval($production_summary['total_production_orders'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Completed Orders</h6>
                    <h2><?= $total_completed ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Units Produced</h6>
                    <h2><?= number_format($production_summary['completed_quantity'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= $on_time_percentage >= 80 ? 'bg-success' : 'bg-warning' ?>">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">On-Time Delivery</h6>
                    <h2><?= $on_time_percentage ?>%</h2>
                    <small><?= intval($on_time_orders['count']) ?> of <?= $total_completed ?> completed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Monthly Units Produced (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="productionChart"></canvas>
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

    <div class="row">
        <!-- Production Orders by Status -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tasks me-2"></i>Production Orders by Status</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($orders_by_status)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-end">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders_by_status as $status): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $badges = ['planned'=>'secondary','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger'];
                                        $badge  = $badges[$status['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst(str_replace('_',' ', $status['status'])) ?></span>
                                    </td>
                                    <td class="text-center"><?= $status['count'] ?></td>
                                    <td class="text-end"><?= number_format($status['total_quantity']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No production order data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Produced Products -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-industry me-2"></i>Top Produced Products</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_products)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center">Orders</th>
                                    <th class="text-end">Qty Produced</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_products as $product): ?>
                                <tr>
                                    <td>
                                        <?= escape_html($product['name']) ?><br>
                                        <small class="text-muted"><?= escape_html($product['code']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $product['order_count'] ?></td>
                                    <td class="text-end"><?= number_format($product['total_quantity']) ?></td>
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

    <!-- Production by Location -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-warehouse me-2"></i>Production by Location</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($production_by_location)): ?>
            <div class="row">
                <?php foreach ($production_by_location as $loc):
                    $rate = $loc['order_count'] > 0
                        ? round(($loc['completed_count'] / $loc['order_count']) * 100)
                        : 0;
                ?>
                <div class="col-md-3 mb-3">
                    <div class="card bg-secondary">
                        <div class="card-body text-center">
                            <h6><?= escape_html($loc['location_name']) ?></h6>
                            <h4><?= $loc['order_count'] ?> Orders</h4>
                            <p class="mb-1"><small><?= number_format($loc['total_quantity']) ?> Units</small></p>
                            <div class="progress" style="height:8px;">
                                <div class="progress-bar bg-success" style="width:<?= $rate ?>%"></div>
                            </div>
                            <small class="text-success"><?= $loc['completed_count'] ?> completed (<?= $rate ?>%)</small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-center text-muted py-4">No location data available</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Monthly production bar chart
    const prodCtx = document.getElementById('productionChart').getContext('2d');
    new Chart(prodCtx, {
        type: 'bar',
        data: {
            labels: <?= $comp_month_labels ?>,
            datasets: [
                { label: 'Units Produced', data: <?= $comp_units_data ?>, backgroundColor: 'rgba(13,110,253,0.7)', borderColor: '#0d6efd', borderWidth: 1, yAxisID: 'y' },
                { label: 'Orders Completed', data: <?= $comp_orders_data ?>, backgroundColor: 'rgba(25,135,84,0.7)', borderColor: '#198754', borderWidth: 1, yAxisID: 'y1', type: 'line', tension: 0.3 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                y:  { beginAtZero: true, position: 'left' },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });

    // Status donut chart
    const stCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(stCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $status_labels_json ?>,
            datasets: [{ data: <?= $status_counts_json ?>,
                backgroundColor: ['#6c757d','#0d6efd','#198754','#dc3545'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
