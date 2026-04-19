<?php
/**
 * MRP Module - Dashboard
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'MRP - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// ── KPI Stats ─────────────────────────────────────────────────────────────────
$total_mrp_runs      = db_fetch("SELECT COUNT(DISTINCT mr.id) as c FROM mrp_runs mr JOIN planned_orders po ON po.mrp_run_id = mr.id JOIN inventory_items i ON po.item_id = i.id WHERE i.company_id = ?", [$company_id])['c'] ?? 0;
$total_planned       = db_fetch("SELECT COUNT(*) as c FROM planned_orders po JOIN inventory_items i ON po.item_id = i.id WHERE po.status='planned' AND i.company_id = ?", [$company_id])['c'] ?? 0;
$total_converted     = db_fetch("SELECT COUNT(*) as c FROM planned_orders po JOIN inventory_items i ON po.item_id = i.id WHERE po.status='converted' AND i.company_id = ?", [$company_id])['c'] ?? 0;
$active_schedules    = db_fetch("SELECT COUNT(*) as c FROM master_production_schedule mps JOIN inventory_items i ON mps.product_id = i.id WHERE mps.status='in_progress' AND mps.is_active=1 AND i.company_id = ?", [$company_id])['c'] ?? 0;

// Overdue schedules (period_end < today and not completed/cancelled)
$overdue_schedules   = db_fetch("SELECT COUNT(*) as c FROM master_production_schedule mps JOIN inventory_items i ON mps.product_id = i.id WHERE mps.period_end < CURDATE() AND mps.status NOT IN ('completed','cancelled') AND mps.is_active=1 AND i.company_id = ?", [$company_id])['c'] ?? 0;

// MPS completion rate
$mps_stats = db_fetch("SELECT COALESCE(SUM(mps.planned_quantity),0) as planned, COALESCE(SUM(mps.actual_quantity),0) as actual FROM master_production_schedule mps JOIN inventory_items i ON mps.product_id = i.id WHERE mps.is_active=1 AND i.company_id = ?", [$company_id]);
$completion_rate = ($mps_stats['planned'] > 0) ? round(($mps_stats['actual'] / $mps_stats['planned']) * 100, 1) : 0;

// Recent MRP runs
$recent_runs = db_fetch_all("
    SELECT DISTINCT mr.*, u.username as created_by_name
    FROM mrp_runs mr
    LEFT JOIN users u ON mr.created_by = u.id
    JOIN planned_orders po ON po.mrp_run_id = mr.id
    JOIN inventory_items i ON po.item_id = i.id
    WHERE i.company_id = ?
    ORDER BY mr.run_date DESC
    LIMIT 8
", [$company_id]);

// Pending planned orders by type
$pending_breakdown = db_fetch("
    SELECT
        SUM(CASE WHEN po.order_type='purchase'   THEN 1 ELSE 0 END) as purchase_cnt,
        SUM(CASE WHEN po.order_type='production' THEN 1 ELSE 0 END) as production_cnt
    FROM planned_orders po
    JOIN inventory_items i ON po.item_id = i.id
    WHERE po.status='planned' AND i.company_id = ?
", [$company_id]);

// Monthly planned orders created trend (last 6 months)
$monthly_trend = db_fetch_all("
    SELECT DATE_FORMAT(po.planned_date,'%b %Y') as month_label,
           YEAR(po.planned_date)*100+MONTH(po.planned_date) as sort_key,
           COUNT(*) as cnt
    FROM planned_orders po
    JOIN inventory_items i ON po.item_id = i.id
    WHERE i.company_id = ?
      AND po.planned_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY sort_key, month_label
    ORDER BY sort_key ASC
", [$company_id]);
$trend_labels = json_encode(array_column($monthly_trend, 'month_label'));
$trend_data   = json_encode(array_column($monthly_trend, 'cnt'));

// MPS status breakdown for donut
$mps_status = db_fetch_all("SELECT mps.status, COUNT(*) as cnt FROM master_production_schedule mps JOIN inventory_items i ON mps.product_id = i.id WHERE mps.is_active=1 AND i.company_id = ? GROUP BY mps.status", [$company_id]);
$mps_status_labels = json_encode(array_column($mps_status, 'status'));
$mps_status_counts = json_encode(array_column($mps_status, 'cnt'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-project-diagram me-2"></i>MRP Dashboard</h2>
            <p class="lead mb-0">Material Requirements Planning for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="master_schedule.php" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>New Schedule
            </a>
            <a href="planned_orders.php" class="btn btn-outline-secondary">
                <i class="fas fa-list-ul me-1"></i>Planned Orders
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
                            <div class="small text-uppercase opacity-75">MRP Runs</div>
                            <h3 class="mb-0"><?= intval($total_mrp_runs) ?></h3>
                        </div>
                        <i class="fas fa-cogs fa-2x opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,#713f12,#d97706);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">Pending Orders</div>
                            <h3 class="mb-0"><?= intval($total_planned) ?></h3>
                            <small class="opacity-75">
                                <?= intval($pending_breakdown['purchase_cnt'] ?? 0) ?> purchase /
                                <?= intval($pending_breakdown['production_cnt'] ?? 0) ?> production
                            </small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-40"></i>
                    </div>
                    <div class="mt-2">
                        <a href="planned_orders.php?status=planned" class="btn btn-light btn-sm">Convert →</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,<?= intval($overdue_schedules)>0?'#7f1d1d,#dc2626':'#065f46,#059669' ?>);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">Overdue Schedules</div>
                            <h3 class="mb-0"><?= intval($overdue_schedules) ?></h3>
                            <small class="opacity-75"><?= intval($active_schedules) ?> active in-progress</small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card border-0 h-100" style="background:linear-gradient(135deg,#164e63,#0891b2);">
                <div class="card-body text-white">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="small text-uppercase opacity-75">MPS Completion</div>
                            <h3 class="mb-0"><?= $completion_rate ?>%</h3>
                            <small class="opacity-75">actual vs planned</small>
                        </div>
                        <i class="fas fa-percentage fa-2x opacity-40"></i>
                    </div>
                    <div class="progress mt-2" style="height:5px;background:rgba(255,255,255,.2);">
                        <div class="progress-bar bg-white" style="width:<?= min(100,$completion_rate) ?>%;"></div>
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
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Planned Orders Created (Last 6 Months)</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative;height:240px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>MPS Status</h5>
                </div>
                <div class="card-body">
                    <div style="position:relative;height:240px;">
                        <canvas id="mpsDonut"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent MRP Runs -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent MRP Runs</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($recent_runs)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Run #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Orders Created</th>
                                    <th>By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_runs as $run): ?>
                                <tr>
                                    <td><strong><?= escape_html($run['run_number']) ?></strong></td>
                                    <td><?= format_date($run['run_date']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $run['status'] === 'completed' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($run['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= intval($run['planned_orders_created']) ?></td>
                                    <td><?= escape_html($run['created_by_name'] ?? 'System') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted py-4">No MRP runs yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Access</h5></div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="master_schedule.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-week text-primary me-2"></i>Master Production Schedule
                            <span class="float-end text-muted small">Plan production periods</span>
                        </a>
                        <a href="planned_orders.php?status=planned" class="list-group-item list-group-item-action">
                            <i class="fas fa-list-ul text-warning me-2"></i>Pending Planned Orders
                            <span class="float-end badge bg-warning"><?= intval($total_planned) ?></span>
                        </a>
                        <a href="planned_orders.php?status=converted" class="list-group-item list-group-item-action">
                            <i class="fas fa-check-circle text-success me-2"></i>Converted Orders
                            <span class="float-end badge bg-success"><?= intval($total_converted) ?></span>
                        </a>
                        <a href="material_requirements.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clipboard-list text-info me-2"></i>Material Requirements
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Trend bar chart
    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= $trend_labels ?>,
            datasets: [{
                label: 'Planned Orders',
                data: <?= $trend_data ?>,
                backgroundColor: 'rgba(37,99,235,0.75)',
                borderColor: '#2563eb', borderWidth: 1
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // MPS Status donut
    new Chart(document.getElementById('mpsDonut').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= $mps_status_labels ?>,
            datasets: [{ data: <?= $mps_status_counts ?>,
                backgroundColor: ['#6c757d','#ffc107','#198754','#dc3545'] }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
