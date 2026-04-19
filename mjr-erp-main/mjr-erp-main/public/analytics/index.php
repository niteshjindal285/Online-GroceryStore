<?php
/**
 * Analytics Module - Main Dashboard
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ai_insights.php';

require_login();

$page_title = 'Analytics - MJR Group ERP';

$company_id = $_SESSION['company_id'];

// Get AI Insights
$ai_insights = AIEngine::generateDashboardInsights($company_id);

// ── Live KPI Numbers ────────────────────────────────────────────────────────────
// MTD revenue
$mtd_revenue = db_fetch("
    SELECT COALESCE(SUM(total_amount),0) as revenue
    FROM sales_orders
    WHERE company_id = ? AND order_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
", [$company_id]);

// Active Production Orders
$active_wo = db_fetch("SELECT COUNT(*) as cnt FROM work_orders WHERE status IN ('planned','in_progress')");

// Low Stock Alerts
$low_stock_count = db_fetch("
    SELECT COUNT(*) as cnt FROM (
        SELECT ii.id
        FROM inventory_items ii
        LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id
        WHERE ii.is_active = 1 AND ii.reorder_level > 0
        GROUP BY ii.id, ii.reorder_level
        HAVING COALESCE(SUM(isl.quantity_on_hand), 0) <= ii.reorder_level
    ) t
");

// Net Income MTD (from GL)
$mtd_net = db_fetch("
    SELECT
        COALESCE(SUM(CASE WHEN a.account_type='revenue' THEN gl.credit-gl.debit ELSE 0 END),0)
      - COALESCE(SUM(CASE WHEN a.account_type='expense' THEN gl.debit-gl.credit ELSE 0 END),0) as net
    FROM general_ledger gl
    JOIN accounts a ON gl.account_id = a.id
    WHERE gl.transaction_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
");

// Overdue production orders
$overdue_wo = db_fetch("
    SELECT COUNT(*) as cnt FROM work_orders
    WHERE due_date < CURDATE() AND status NOT IN ('completed','cancelled')
");

include __DIR__ . '/../../templates/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h2><i class="fas fa-chart-bar me-2"></i>Business Analytics</h2>
        <p class="lead mb-0">Month-to-date performance overview</p>
    </div>
    <div class="text-end">
        <small class="text-muted d-block mb-2">As of <?= format_date(date('Y-m-d')) ?></small>
        <!-- Not exporting whole index dashboard easily, let users export individual reports -->
    </div>
</div>

<!-- Live KPI Row -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0" style="background: linear-gradient(135deg,#1e3a8a,#2563eb);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small opacity-75">MTD Revenue</div>
                        <h3 class="mb-0"><?= format_currency($mtd_revenue['revenue'] ?? 0) ?></h3>
                    </div>
                    <i class="fas fa-shopping-cart fa-2x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <a href="sales.php?preset=this_month" class="btn btn-light btn-sm">View Sales →</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0" style="background: linear-gradient(135deg,#065f46,#059669);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small opacity-75">MTD Net Income</div>
                        <h3 class="mb-0 <?= floatval($mtd_net['net'] ?? 0) < 0 ? 'text-danger' : '' ?>">
                            <?= format_currency(abs($mtd_net['net'] ?? 0)) ?>
                        </h3>
                        <?php if (floatval($mtd_net['net'] ?? 0) < 0): ?>
                        <small class="text-warning">⚠ Net Loss</small>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <a href="financial.php?preset=this_month" class="btn btn-light btn-sm">View Financial →</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0" style="background: linear-gradient(135deg,#7c2d12,#c2410c);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small opacity-75">Active Production Orders</div>
                        <h3 class="mb-0"><?= intval($active_wo['cnt'] ?? 0) ?></h3>
                        <?php if (intval($overdue_wo['cnt'] ?? 0) > 0): ?>
                        <small class="text-warning">⚠ <?= $overdue_wo['cnt'] ?> overdue</small>
                        <?php endif; ?>
                    </div>
                    <i class="fas fa-industry fa-2x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <a href="production.php" class="btn btn-light btn-sm">View Production →</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0" style="background: linear-gradient(135deg,<?= intval($low_stock_count['cnt'] ?? 0) > 0 ? '#7f1d1d,#dc2626' : '#14532d,#16a34a' ?>);">
            <div class="card-body text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-uppercase small opacity-75">Low Stock Alerts</div>
                        <h3 class="mb-0"><?= intval($low_stock_count['cnt'] ?? 0) ?></h3>
                        <small><?= intval($low_stock_count['cnt'] ?? 0) === 0 ? '✓ All stock OK' : 'Items below reorder' ?></small>
                    </div>
                    <i class="fas fa-boxes fa-2x opacity-50"></i>
                </div>
                <div class="mt-2">
                    <a href="inventory.php" class="btn btn-light btn-sm">View Inventory →</a>
                </div>
            </div>
        </div>
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

<!-- Analytics Modules Grid -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white h-100" style="background-color: #1e3a8a;">
            <div class="card-body text-center">
                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                <h6>Sales Analytics</h6>
                <p class="small opacity-75">Revenue trends, top customers, order performance</p>
                <a href="sales.php" class="btn btn-light btn-sm mt-2">View Reports</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white h-100" style="background-color: #065f46;">
            <div class="card-body text-center">
                <i class="fas fa-boxes fa-3x mb-3"></i>
                <h6>Inventory Analytics</h6>
                <p class="small opacity-75">Stock levels, turnover, low stock alerts</p>
                <a href="inventory.php" class="btn btn-light btn-sm mt-2">View Reports</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white h-100" style="background-color: #7c2d12;">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-3x mb-3"></i>
                <h6>Financial Analytics</h6>
                <p class="small opacity-75">Income statement, balance sheet, expenses</p>
                <a href="financial.php" class="btn btn-light btn-sm mt-2">View Reports</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white h-100" style="background-color: #713f12;">
            <div class="card-body text-center">
                <i class="fas fa-industry fa-3x mb-3"></i>
                <h6>Production Analytics</h6>
                <p class="small opacity-75">Production orders, completion rate, efficiency</p>
                <a href="production.php" class="btn btn-light btn-sm mt-2">View Reports</a>
            </div>
        </div>
    </div>
</div>

<!-- Quick Access List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Quick Access — Report Pages</h5>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <a href="sales.php?preset=this_month" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Sales Trends &amp; Performance</h6>
                        <p class="mb-0 text-muted small">Revenue trends, top products, customer insights, month-over-month</p>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="inventory.php?preset=this_month" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-warehouse text-success me-2"></i>Inventory Analysis</h6>
                        <p class="mb-0 text-muted small">Stock levels, IN/OUT movements, low stock alerts by category</p>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="financial.php?preset=this_month" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-file-invoice-dollar text-warning me-2"></i>Financial Reports</h6>
                        <p class="mb-0 text-muted small">Revenue vs expenses chart, net margin, balance sheet, cash accounts</p>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </a>
            <a href="production.php?preset=this_month" class="list-group-item list-group-item-action">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-cogs text-danger me-2"></i>Production Efficiency</h6>
                        <p class="mb-0 text-muted small">Production order completion, on-time delivery %, location performance</p>
                    </div>
                    <i class="fas fa-chevron-right text-muted"></i>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
