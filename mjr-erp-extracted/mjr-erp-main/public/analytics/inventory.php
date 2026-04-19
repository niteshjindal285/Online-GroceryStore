<?php
/**
 * Inventory Analytics
 * Inventory performance and stock analysis
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ai_insights.php';

require_login();

$page_title = 'Inventory Analytics - MJR Group ERP';

// Get AI Insights
$company_id = $_SESSION['company_id'];
$ai_insights = AIEngine::getInventoryDepletionInsights($company_id);

// Date range (for transactions)
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-t');

$preset = $_GET['preset'] ?? '';
if ($preset === 'last_month') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to   = date('Y-m-t',  strtotime('last day of last month'));
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to   = date('Y-12-31');
}

// Inventory summary (all-time stock snapshot)
$inventory_summary = db_fetch("
    SELECT
        COUNT(DISTINCT ii.id) as total_items,
        COALESCE(SUM(isl.quantity_on_hand), 0) as total_qty,
        COALESCE(SUM(isl.quantity_on_hand * ii.cost_price), 0) as total_value,
        COUNT(DISTINCT isl.location_id) as active_locations
    FROM inventory_items ii
    LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id
    WHERE ii.is_active = 1
");

// Low stock items
$low_stock = db_fetch_all("
    SELECT
        ii.code, ii.name, ii.reorder_level,
        COALESCE(SUM(isl.quantity_on_hand), 0) as current_stock
    FROM inventory_items ii
    LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id
    WHERE ii.is_active = 1 AND ii.reorder_level > 0
    GROUP BY ii.id, ii.code, ii.name, ii.reorder_level
    HAVING COALESCE(SUM(isl.quantity_on_hand), 0) <= ii.reorder_level
    ORDER BY current_stock ASC
    LIMIT 10
");

// Stock by category
$stock_by_category = db_fetch_all("
    SELECT
        c.name as category_name,
        COUNT(ii.id) as item_count,
        COALESCE(SUM(isl.quantity_on_hand), 0) as total_quantity,
        COALESCE(SUM(isl.quantity_on_hand * ii.cost_price), 0) as total_value
    FROM categories c
    LEFT JOIN inventory_items ii ON c.id = ii.category_id AND ii.is_active = 1
    LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id
    WHERE c.is_active = 1
    GROUP BY c.id, c.name
    ORDER BY total_value DESC
");

// Category chart data
$category_labels = json_encode(array_column($stock_by_category, 'category_name'));
$category_values = json_encode(array_column($stock_by_category, 'total_value'));

// Stock by location
$stock_by_location = db_fetch_all("
    SELECT
        l.name as location_name,
        COUNT(DISTINCT isl.item_id) as item_count,
        COALESCE(SUM(isl.quantity_on_hand), 0) as total_quantity,
        COALESCE(SUM(isl.quantity_on_hand * ii.cost_price), 0) as total_value
    FROM locations l
    LEFT JOIN inventory_stock_levels isl ON l.id = isl.location_id
    LEFT JOIN inventory_items ii ON isl.item_id = ii.id
    WHERE l.is_active = 1
    GROUP BY l.id, l.name
    ORDER BY total_quantity DESC
");

// Recent transactions (date-filtered) — use quantity_signed for IN/OUT direction
$recent_transactions = db_fetch_all("
    SELECT
        it.created_at,
        it.transaction_type,
        it.movement_reason,
        COALESCE(it.quantity_signed, it.quantity) as qty_signed,
        ABS(COALESCE(it.quantity_signed, it.quantity)) as qty_abs,
        ii.code, ii.name as item_name,
        l.name as location_name
    FROM inventory_transactions it
    JOIN inventory_items ii ON it.item_id = ii.id
    JOIN locations l ON it.location_id = l.id
    WHERE DATE(it.created_at) BETWEEN ? AND ?
    ORDER BY it.created_at DESC
    LIMIT 20
", [$date_from, $date_to]);

// Transaction IN vs OUT summary for chart
$txn_summary = db_fetch_all("
    SELECT DATE_FORMAT(it.created_at,'%b %Y') as month_label,
           YEAR(it.created_at)*100+MONTH(it.created_at) as sort_key,
           SUM(CASE WHEN COALESCE(it.quantity_signed, it.quantity) > 0 THEN ABS(it.quantity) ELSE 0 END) as total_in,
           SUM(CASE WHEN COALESCE(it.quantity_signed, it.quantity) < 0 THEN ABS(it.quantity) ELSE 0 END) as total_out
    FROM inventory_transactions it
    WHERE it.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
");

$txn_month_labels = json_encode(array_column($txn_summary, 'month_label'));
$txn_in_data      = json_encode(array_column($txn_summary, 'total_in'));
$txn_out_data     = json_encode(array_column($txn_summary, 'total_out'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2><i class="fas fa-boxes me-2"></i>Inventory Analytics</h2>
        <p class="lead mb-0">Inventory performance and stock analysis</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="dropdown d-inline-block">
            <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-download me-2"></i>Export Report
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="export_dashboard.php?module=inventory&format=pdf&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-pdf text-danger me-2"></i>Export as PDF</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=inventory&format=word&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-word text-primary me-2"></i>Export as Word</a></li>
                <li><a class="dropdown-item" href="export_dashboard.php?module=inventory&format=excel&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><i class="fas fa-file-excel text-success me-2"></i>Export as Excel</a></li>
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
                <input type="hidden" name="module" value="inventory">
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

    <!-- Date Filter (for transactions) -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Transactions From</label>
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
                </div>
            </form>
        </div>
    </div>

    <!-- AI Insights Engine Section (Inventory) -->
    <?php if (!empty($ai_insights)): ?>
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-brain me-2"></i>AI/ML Inventory Predictions</h5>
            <span class="badge bg-light text-info"><i class="fas fa-robot me-1"></i>Auto-Generated</span>
        </div>
        <div class="card-body bg-light">
            <div class="row g-3">
                <?php foreach ($ai_insights as $insight): ?>
                <div class="col-md-6 mb-2">
                    <div class="d-flex align-items-start bg-white p-3 border-start border-4 border-<?= $insight['color'] ?> rounded shadow-sm h-100">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-<?= $insight['color'] ?> bg-opacity-10 text-<?= $insight['color'] ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <i class="fas <?= $insight['icon'] ?> fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1 text-<?= $insight['color'] ?>"><?= $insight['title'] ?></strong>
                            <p class="mb-0 text-muted small"><?= $insight['message'] ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Items</h6>
                    <h2><?= number_format($inventory_summary['total_items'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Quantity</h6>
                    <h2><?= number_format($inventory_summary['total_qty'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Value</h6>
                    <h2><?= format_currency($inventory_summary['total_value'] ?? 0) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?= count($low_stock) > 0 ? 'bg-danger' : 'bg-warning' ?>">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Low Stock Alerts</h6>
                    <h2><?= count($low_stock) ?></h2>
                    <small><?= count($low_stock) === 0 ? 'All OK' : 'Items below reorder' ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-bar me-2"></i>Stock IN vs OUT (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative; height:280px;">
                        <canvas id="txnChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie me-2"></i>Value by Category</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stock_by_category)): ?>
                    <div style="position:relative; height:280px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No data</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Low Stock Alert -->
        <div class="col-md-6 mb-4">
            <div class="card border-danger">
                <div class="card-header bg-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($low_stock)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-center">Current</th>
                                    <th class="text-center">Reorder Level</th>
                                    <th class="text-center">Shortage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($low_stock as $item): ?>
                                <tr>
                                    <td>
                                        <?= escape_html($item['name']) ?><br>
                                        <small class="text-muted"><?= escape_html($item['code']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?= intval($item['current_stock']) ?></span>
                                    </td>
                                    <td class="text-center"><?= $item['reorder_level'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-warning text-dark">
                                            <?= intval($item['reorder_level']) - intval($item['current_stock']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">
                        <i class="fas fa-check-circle fa-2x mb-2 text-success"></i><br>
                        All items are above reorder level
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stock by Category -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tags me-2"></i>Stock by Category</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stock_by_category)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Items</th>
                                    <th class="text-end">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stock_by_category as $cat): ?>
                                <tr>
                                    <td><?= escape_html($cat['category_name']) ?></td>
                                    <td class="text-center"><?= $cat['item_count'] ?></td>
                                    <td class="text-end"><?= format_currency($cat['total_value']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No category data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock by Location -->
    <?php
    $grand_total_qty = array_sum(array_column($stock_by_location, 'total_quantity'));
    $grand_total_val = array_sum(array_column($stock_by_location, 'total_value'));
    $loc_gradients = [
        ['#1e3a8a','#2563eb'],
        ['#065f46','#059669'],
        ['#7c2d12','#c2410c'],
        ['#581c87','#7c3aed'],
        ['#164e63','#0891b2'],
        ['#713f12','#d97706'],
    ];
    ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Stock by Location</h5>
            <span class="badge bg-secondary"><?= count($stock_by_location) ?> Locations</span>
        </div>
        <div class="card-body">
            <?php if (!empty($stock_by_location)): ?>
            <div class="row g-3">
                <?php foreach ($stock_by_location as $i => $loc):
                    $grad  = $loc_gradients[$i % count($loc_gradients)];
                    $share = $grand_total_qty > 0 ? round(($loc['total_quantity'] / $grand_total_qty) * 100, 1) : 0;
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card border-0 h-100" style="background:linear-gradient(135deg,<?= $grad[0] ?>,<?= $grad[1] ?>);">
                        <div class="card-body text-white p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="text-uppercase fw-bold small opacity-75" style="letter-spacing:.5px;">
                                    <?= escape_html($loc['location_name']) ?>
                                </div>
                                <i class="fas fa-warehouse opacity-50"></i>
                            </div>
                            <div class="mb-1">
                                <span class="fs-4 fw-bold"><?= number_format($loc['total_quantity']) ?></span>
                                <span class="opacity-75 ms-1">Units</span>
                            </div>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between" style="font-size:.75rem;opacity:.8;">
                                    <span><?= $share ?>% of total</span>
                                </div>
                                <div class="progress mt-1" style="height:5px;background:rgba(255,255,255,.2);">
                                    <div class="progress-bar bg-white" style="width:<?= $share ?>%;"></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between" style="font-size:.8rem;opacity:.85;">
                                <span><i class="fas fa-box me-1"></i><?= intval($loc['item_count']) ?> SKUs</span>
                                <span><?= format_currency($loc['total_value']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 pt-3 border-top border-secondary d-flex flex-wrap gap-4">
                <div><span class="text-muted small">Total Stock</span><br><strong><?= number_format($grand_total_qty) ?> Units</strong></div>
                <div><span class="text-muted small">Total Value</span><br><strong><?= format_currency($grand_total_val) ?></strong></div>
                <div><span class="text-muted small">Active</span><br><strong><?= count(array_filter($stock_by_location, fn($l) => $l['total_quantity'] > 0)) ?> / <?= count($stock_by_location) ?></strong></div>
            </div>
            <?php else: ?>
            <p class="text-center text-muted py-4">No location data available</p>
            <?php endif; ?>
        </div>
    </div>


    <!-- Recent Transactions (date-filtered, quantity_signed) -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-exchange-alt me-2"></i>Transactions
                <small class="text-muted">(<?= escape_html($date_from) ?> → <?= escape_html($date_to) ?>)</small>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($recent_transactions)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-sm" id="txnTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Reason</th>
                            <th class="text-end">Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $t): ?>
                        <tr>
                            <td><?= format_date($t['created_at']) ?></td>
                            <td>
                                <?= escape_html($t['item_name']) ?><br>
                                <small class="text-muted"><?= escape_html($t['code']) ?></small>
                            </td>
                            <td><small><?= escape_html($t['location_name']) ?></small></td>
                            <td><span class="badge bg-<?= intval($t['qty_signed']) >= 0 ? 'success' : 'warning' ?>">
                                <?= ucwords(str_replace('_',' ', $t['transaction_type'])) ?>
                            </span></td>
                            <td><small><?= escape_html($t['movement_reason'] ?? '') ?></small></td>
                            <td class="text-end">
                                <?php $qs = intval($t['qty_signed']); ?>
                                <span class="<?= $qs > 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $qs > 0 ? '+' : '' ?><?= $qs ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-muted py-4">No transactions in this date range</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // IN vs OUT bar chart
    const txCtx = document.getElementById('txnChart').getContext('2d');
    new Chart(txCtx, {
        type: 'bar',
        data: {
            labels: <?= $txn_month_labels ?>,
            datasets: [
                { label: 'Stock IN',  data: <?= $txn_in_data  ?>, backgroundColor: 'rgba(25,135,84,0.8)',  borderColor: '#198754', borderWidth:1 },
                { label: 'Stock OUT', data: <?= $txn_out_data ?>, backgroundColor: 'rgba(220,53,69,0.8)',  borderColor: '#dc3545', borderWidth:1 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });

    <?php if (!empty($stock_by_category)): ?>
    // Category value donut
    const catCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(catCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $category_labels ?>,
            datasets: [{ data: <?= $category_values ?>,
                backgroundColor: ['#0d6efd','#198754','#ffc107','#dc3545','#0dcaf0','#6f42c1','#20c997','#fd7e14'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
    <?php endif; ?>

    if ($.fn.DataTable) {
        $('#txnTable').DataTable({ pageLength: 15, order: [[0,'desc']] });
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
