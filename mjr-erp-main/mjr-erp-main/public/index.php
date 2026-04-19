<?php
/**
 * Dashboard - Main Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$page_title = 'Dashboard - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    // If no company selected, we can still show a blank dashboard or redirect to company selection
    // But for MJR, we usually want them to see *something* or the header will handle it.
    // However, to be strict:
    set_flash('Please select a company to view the dashboard.', 'info');
}
$company_name = active_company_name('Current Company');

// Get dashboard statistics (matching Flask exactly)
try {
    // Count total active inventory items for the selected company
    $total_items_result = db_fetch("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1 AND company_id = ?", [$company_id]);
    $total_items = $total_items_result ? (int)$total_items_result['count'] : 0;
    
    // Count low stock items (Matching stock_levels.php: an item is low stock if ANY location is below reorder level)
    $low_stock_result = db_fetch("
        SELECT COUNT(DISTINCT item_id) as count
        FROM (
            SELECT s.item_id, s.quantity_available as q, i.reorder_level
            FROM inventory_stock_levels s
            JOIN inventory_items i ON s.item_id = i.id
            JOIN locations l ON s.location_id = l.id
            WHERE i.is_active = 1
              AND i.reorder_level > 0
              AND i.company_id = ?
              AND l.company_id = ?

            UNION ALL

            SELECT wi.product_id as item_id, wi.quantity as q, i.reorder_level
            FROM warehouse_inventory wi
            JOIN inventory_items i ON wi.product_id = i.id
            JOIN warehouses w ON wi.warehouse_id = w.id
            WHERE i.is_active = 1
              AND i.reorder_level > 0
              AND i.company_id = ?
              AND w.company_id = ?
        ) AS all_locations
        WHERE q < reorder_level
    ", [$company_id, $company_id, $company_id, $company_id]);
    $low_stock = $low_stock_result ? (int)$low_stock_result['count'] : 0;
    
    // Get pending orders
    $pending_orders_result = db_fetch("SELECT COUNT(*) as count FROM sales_orders WHERE status = 'confirmed' AND company_id = ?", [$company_id]);
    $pending_orders = $pending_orders_result ? (int)$pending_orders_result['count'] : 0;
    
    // Get pending purchase orders (any status that isn't completed or cancelled)
    $pending_pos_result = db_fetch("
        SELECT COUNT(*) as count 
        FROM purchase_orders po
        WHERE po.status IN ('draft', 'pending_approval', 'approved', 'sent') AND po.company_id = ?
    ", [$company_id]);
    $pending_pos = $pending_pos_result ? (int)$pending_pos_result['count'] : 0;
    
    // Get active production orders
    $active_wo_result = db_fetch("
        SELECT COUNT(*) as count 
        FROM work_orders wo
        JOIN users u ON wo.created_by = u.id
        WHERE wo.status IN ('planned', 'in_progress') AND u.company_id = ?
    ", [$company_id]);
    $active_work_orders = $active_wo_result ? (int)$active_wo_result['count'] : 0;
    
    // Get total customers and suppliers for the selected company
    $customers_result = db_fetch("SELECT COUNT(*) as count FROM customers WHERE is_active = 1 AND (company_id = ? OR company_id IS NULL)", [$company_id]);
    $total_customers = $customers_result ? (int)$customers_result['count'] : 0;

    $suppliers_result = db_fetch("
        SELECT COUNT(*) as count
        FROM suppliers
        WHERE is_active = 1 
          AND (company_id = ? OR company_id IS NULL)
    ", [$company_id]);
    $total_suppliers = $suppliers_result ? (int)$suppliers_result['count'] : 0;
    
    $stats = [
        'total_items' => $total_items,
        'low_stock_items' => $low_stock,
        'pending_orders' => $pending_orders,
        'pending_pos' => $pending_pos,
        'active_production_orders' => $active_work_orders,
        'total_customers' => $total_customers,
        'total_suppliers' => $total_suppliers,
    ];
    
    // Get recent transactions for this company
    $recent_transactions = db_fetch_all("
        SELECT t.*, 
               i.code as item_code, i.name as item_name,
               l.name as location_name,
               u.username
        FROM inventory_transactions t
        JOIN inventory_items i ON t.item_id = i.id
        JOIN locations l ON t.location_id = l.id
        JOIN users u ON t.created_by = u.id
        WHERE l.company_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ", [$company_id]);
    
    // // Get monthly sales data for chart (last 6 months)
    // $monthly_sales = db_fetch_all("
    //     SELECT DATE_FORMAT(order_date, '%Y-%m') as month,
    //            SUM(total_amount) as total
    //     FROM sales_orders
    //     WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    //       AND status = 'completed'
    //     GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    //     ORDER BY month
    // ");

    $monthly_sales = db_fetch_all("
        SELECT DATE_FORMAT(order_date, '%Y-%m') as month,
            SUM(total_amount) as total
        FROM sales_orders
        WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          AND status IN ('confirmed', 'processing', 'completed', 'shipped', 'delivered')
          AND company_id = ?
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month
    ", [$company_id]);
    
} catch (Exception $e) {
    log_error("Dashboard error: " . $e->getMessage());
    $stats = ['total_items' => 0, 'low_stock_items' => 0, 'pending_orders' => 0, 
              'pending_pos' => 0, 'active_production_orders' => 0, 'total_customers' => 0, 'total_suppliers' => 0];
    $recent_transactions = [];
    $monthly_sales = [];
}

include __DIR__ . '/../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1 class="display-4">
                <i class="fas fa-tachometer-alt me-3"></i>Dashboard
            </h1>
            <p class="lead">Welcome to MJR Group ERP System — showing data for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <?php if (has_permission('view_inventory')): ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Inventory Items</h6>
                            <h3><?= $stats['total_items'] ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-boxes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Low Stock Items</h6>
                            <h3><?= $stats['low_stock_items'] ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-exclamation-triangle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('view_sales')): ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Pending Orders</h6>
                            <h3><?= $stats['pending_orders'] ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-shopping-cart fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (has_permission('view_production')): ?>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Active Production Orders</h6>
                            <h3><?= $stats['active_production_orders'] ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-cogs fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Charts and Tables Row -->
    <div class="row">
        <?php if (has_permission('view_sales')): ?>
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line me-2"></i>Monthly Sales Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="100"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="<?= has_permission('view_sales') ? 'col-md-4' : 'col-md-12' ?> mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Quick Stats</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted">Total Customers</small>
                        <div class="h4 text-primary"><?= $stats['total_customers'] ?></div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">Total Suppliers</small>
                        <div class="h4 text-success"><?= $stats['total_suppliers'] ?></div>
                    </div>
                    <?php if (has_permission('view_procurement')): ?>
                    <div class="mb-3">
                        <small class="text-muted">Pending Purchase Orders</small>
                        <div class="h4 text-warning"><?= $stats['pending_pos'] ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <?php if (has_permission('view_inventory')): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history me-2"></i>Recent Inventory Transactions</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_transactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Item</th>
                                    <th>Type</th>
                                    <th>Quantity</th>
                                    <th>Location</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td><?= format_date($transaction['created_at'], DISPLAY_DATETIME_FORMAT) ?></td>
                                    <td><?= escape_html($transaction['item_code']) ?> - <?= escape_html($transaction['item_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $transaction['transaction_type'] == 'in' ? 'success' : 'danger' ?>">
                                            <?= ucfirst($transaction['transaction_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= format_number($transaction['quantity'], 0) ?></td>
                                    <td><?= escape_html($transaction['location_name']) ?></td>
                                    <td><?= escape_html($transaction['username']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent transactions</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
// Sales Chart JavaScript
if (has_permission('view_sales')) {
    $additional_scripts = "
    <script>
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesData = " . json_encode($monthly_sales) . ";
    
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: salesData.map(item => item.month),
            datasets: [{
                label: 'Monthly Sales',
                data: salesData.map(item => parseFloat(item.total)),
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
    </script>
    ";
} else {
    $additional_scripts = "";
}

include __DIR__ . '/../templates/footer.php';
?>
