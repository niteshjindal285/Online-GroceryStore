<?php
/**
 * Procurement Dashboard
 * Overview of purchase orders, suppliers, and spending KPIs
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Procurement - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// ── Live KPIs ──────────────────────────────────────────────────────────────────
// MTD spending
$mtd_spend = db_fetch("
    SELECT COALESCE(SUM(total_amount),0) as spend
    FROM purchase_orders
    WHERE company_id = ?
      AND order_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
      AND status NOT IN ('cancelled','draft')
", [$company_id]);

// Pending POs (sent + confirmed + partially_received)
$pending_pos = db_fetch("
    SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as value
    FROM purchase_orders
    WHERE company_id = ?
      AND status IN ('sent','confirmed','partially_received')
", [$company_id]);

// Overdue POs
$overdue_pos = db_fetch("
    SELECT COUNT(*) as cnt
    FROM purchase_orders
    WHERE company_id = ?
      AND status NOT IN ('received','cancelled')
      AND expected_delivery_date < CURDATE()
", [$company_id]);

// Active suppliers tied to the selected company
$active_suppliers = db_fetch("
    SELECT COUNT(DISTINCT s.id) as cnt
    FROM suppliers s
    LEFT JOIN purchase_orders po ON po.supplier_id = s.id AND po.company_id = ?
    LEFT JOIN inventory_items i ON i.supplier_id = s.id AND i.company_id = ?
    WHERE s.is_active = 1
      AND (po.id IS NOT NULL OR i.id IS NOT NULL)
", [$company_id, $company_id]);

// Recent POs (last 10)
$recent_pos = db_fetch_all("
    SELECT po.*, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.company_id = ?
    ORDER BY po.order_date DESC, po.id DESC
    LIMIT 10
", [$company_id]);

// Monthly spend trend (last 6 months)
$monthly_spend = db_fetch_all("
    SELECT DATE_FORMAT(order_date,'%b %Y') as month_label,
           YEAR(order_date)*100+MONTH(order_date) as sort_key,
           COALESCE(SUM(total_amount),0) as spend
    FROM purchase_orders
    WHERE company_id = ?
      AND status NOT IN ('cancelled','draft')
      AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
", [$company_id]);
$spend_labels = json_encode(array_column($monthly_spend, 'month_label'));
$spend_data   = json_encode(array_column($monthly_spend, 'spend'));

// Top suppliers by spend (last 90 days)
$top_suppliers = db_fetch_all("
    SELECT s.name, COALESCE(SUM(po.total_amount),0) as total_spend, COUNT(po.id) as po_count
    FROM suppliers s
    JOIN purchase_orders po ON s.id = po.supplier_id
    WHERE po.company_id = ?
      AND po.status NOT IN ('cancelled','draft')
      AND po.order_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    GROUP BY s.id, s.name
    ORDER BY total_spend DESC
    LIMIT 5
", [$company_id]);

// Overdue POs list
$overdue_list = db_fetch_all("
    SELECT po.po_number, po.expected_delivery_date, po.total_amount, po.status, s.name as supplier_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.company_id = ?
      AND po.status NOT IN ('received','cancelled')
      AND po.expected_delivery_date < CURDATE()
    ORDER BY po.expected_delivery_date ASC
    LIMIT 5
", [$company_id]);

// Status breakdown for donut chart
$status_breakdown = db_fetch_all("
    SELECT status, COUNT(*) as cnt
    FROM purchase_orders
    WHERE company_id = ?
    GROUP BY status
    ORDER BY cnt DESC
", [$company_id]);
$status_labels_json = json_encode(array_column($status_breakdown, 'status'));
$status_counts_json = json_encode(array_column($status_breakdown, 'cnt'));

function po_badge_color($status) {
    return match($status) {
        'draft'              => 'secondary',
        'sent'               => 'primary',
        'confirmed'          => 'info',
        'partially_received' => 'warning',
        'received'           => 'success',
        'cancelled'          => 'danger',
        default              => 'secondary',
    };
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-shopping-cart me-2"></i>Procurement Dashboard</h2>
            <p class="lead mb-0">Purchase orders & supplier management for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="../inventory/purchase_order/add_purchase_order.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>New PO
            </a>
            <a href="../inventory/supplier/add_supplier.php" class="btn btn-outline-secondary">
                <i class="fas fa-truck me-1"></i>Add Supplier
            </a>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,#1e3a8a,#2563eb);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">MTD Spend</div>
                            <h3 class="mb-0"><?= format_currency($mtd_spend['spend'] ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign fa-2x opacity-40"></i>
                    </div>
                    <div class="mt-2">
                        <a href="../inventory/purchase_order/purchase_orders.php?preset=this_month" class="btn btn-light btn-sm">View →</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,#065f46,#059669);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">Pending POs</div>
                            <h3 class="mb-0"><?= intval($pending_pos['cnt'] ?? 0) ?></h3>
                            <small class="opacity-75"><?= format_currency($pending_pos['value'] ?? 0) ?> value</small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-40"></i>
                    </div>
                    <div class="mt-2">
                        <a href="../inventory/purchase_order/purchase_orders.php?status=confirmed" class="btn btn-light btn-sm">View →</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,<?= intval($overdue_pos['cnt']??0)>0?'#7f1d1d,#dc2626':'#14532d,#16a34a' ?>);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">Overdue POs</div>
                            <h3 class="mb-0"><?= intval($overdue_pos['cnt'] ?? 0) ?></h3>
                            <small class="opacity-75"><?= intval($overdue_pos['cnt']??0)===0?'All on time':'Need attention' ?></small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,#581c87,#7c3aed);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">Active Suppliers</div>
                            <h3 class="mb-0"><?= intval($active_suppliers['cnt'] ?? 0) ?></h3>
                        </div>
                        <i class="fas fa-truck fa-2x opacity-40"></i>
                    </div>
                    <div class="mt-2">
                        <a href="../inventory/supplier/suppliers.php" class="btn btn-light btn-sm">View →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Monthly Spend (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative;height:260px;">
                        <canvas id="spendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>PO Status Breakdown</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative;height:260px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent POs -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Recent Purchase Orders</h5>
                    <a href="../inventory/purchase_order/purchase_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_pos)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>PO#</th>
                                    <th>Supplier</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_pos as $po): ?>
                                <tr>
                                    <td><strong><?= escape_html($po['po_number']) ?></strong></td>
                                    <td><?= escape_html($po['supplier_name']) ?></td>
                                    <td><?= format_date($po['order_date']) ?></td>
                                    <td><?= format_currency($po['total_amount']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= po_badge_color($po['status']) ?>">
                                            <?= ucfirst(str_replace('_',' ',$po['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../inventory/purchase_order/view_purchase_order.php?id=<?= $po['id'] ?>" class="btn btn-xs btn-outline-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No purchase orders yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Overdue POs -->
            <?php if (!empty($overdue_list)): ?>
            <div class="card mb-3 border-danger">
                <div class="card-header bg-danger">
                    <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Overdue POs</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($overdue_list as $po): ?>
                        <li class="list-group-item bg-transparent">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?= escape_html($po['po_number']) ?></strong><br>
                                    <small class="text-muted"><?= escape_html($po['supplier_name']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger"><?= format_date($po['expected_delivery_date']) ?></span><br>
                                    <small><?= format_currency($po['total_amount']) ?></small>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top Suppliers -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Suppliers (90 Days)</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($top_suppliers)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top_suppliers as $sup): ?>
                        <li class="list-group-item bg-transparent d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= escape_html($sup['name']) ?></strong><br>
                                <small class="text-muted"><?= $sup['po_count'] ?> POs</small>
                            </div>
                            <span class="badge bg-primary"><?= format_currency($sup['total_spend']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-center text-muted py-3 small">No data in last 90 days</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card mt-2">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Access</h5></div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <a href="../inventory/purchase_order/purchase_orders.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-file-invoice text-primary me-2"></i>Purchase Orders
                    <span class="float-end text-muted small">Create, edit and manage POs</span>
                </a>
                <a href="../inventory/supplier/suppliers.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-truck text-success me-2"></i>Suppliers
                    <span class="float-end text-muted small">Manage supplier list</span>
                </a>
                <a href="requisitions.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-clipboard-list text-warning me-2"></i>Purchase Requisitions
                    <span class="float-end text-muted small">Internal requests before PO</span>
                </a>
                <a href="../inventory/purchase_order/purchase_orders.php?status=confirmed" class="list-group-item list-group-item-action">
                    <i class="fas fa-check text-info me-2"></i>Ready to Receive
                    <span class="float-end text-muted small">Confirmed POs awaiting receipt</span>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Monthly Spend Bar Chart
    const spendCtx = document.getElementById('spendChart').getContext('2d');
    new Chart(spendCtx, {
        type: 'bar',
        data: {
            labels: <?= $spend_labels ?>,
            datasets: [{
                label: 'Spend',
                data: <?= $spend_data ?>,
                backgroundColor: 'rgba(37,99,235,0.75)',
                borderColor: '#2563eb', borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    // Status Donut Chart
    const stCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(stCtx, {
        type: 'doughnut',
        data: {
            labels: <?= $status_labels_json ?>,
            datasets: [{ data: <?= $status_counts_json ?>,
                backgroundColor: ['#6c757d','#0d6efd','#0dcaf0','#ffc107','#198754','#dc3545'] }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>


