<?php

/**
 * Accounts Receivable Tracker
 * Shows all unpaid / partially paid sales orders grouped by customer
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Accounts Receivable - MJR Group ERP';
$company_id = (int)active_company_id(1);

// All unpaid / partially paid orders
$ar_orders = db_fetch_all("
    SELECT 
        so.id, so.order_number, so.order_date,
        so.total_amount, so.subtotal, so.tax_amount,
        so.payment_status, so.payment_method, so.payment_date,
        so.status as order_status,
        c.name as customer_name, c.customer_code, c.email as customer_email, c.phone as customer_phone,
        DATEDIFF(NOW(), so.order_date) as days_outstanding
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.payment_status IN ('unpaid', 'pending', 'partially_paid')
      AND so.status NOT IN ('cancelled')
      AND so.company_id = ?
    ORDER BY so.order_date ASC
", [$company_id]);

// Summary totals
$total_outstanding = array_sum(array_column($ar_orders, 'total_amount'));
$overdue_30  = array_filter($ar_orders, fn($o) => $o['days_outstanding'] > 30);
$overdue_60  = array_filter($ar_orders, fn($o) => $o['days_outstanding'] > 60);
$overdue_90  = array_filter($ar_orders, fn($o) => $o['days_outstanding'] > 90);

// Group by customer
$by_customer = [];
foreach ($ar_orders as $order) {
    $key = $order['customer_code'];
    if (!isset($by_customer[$key])) {
        $by_customer[$key] = [
            'name' => $order['customer_name'],
            'code' => $order['customer_code'],
            'email' => $order['customer_email'],
            'orders' => [],
            'total' => 0
        ];
    }
    $by_customer[$key]['orders'][] = $order;
    $by_customer[$key]['total']   += $order['total_amount'];
}
uasort($by_customer, fn($a, $b) => $b['total'] <=> $a['total']);

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --ar-bg: #1a1a24;
    --ar-text: #b0b0c0;
    --ar-card-bg: #222230;
    --ar-card-border: rgba(255, 255, 255, 0.05);
    --ar-summary-value: #fff;
    --ar-summary-label: #8e8e9e;
    --ar-table-bg: #222230;
    --ar-table-striped: #262635;
    --ar-table-border: rgba(255, 255, 255, 0.05);
    --ar-table-th: #8e8e9e;
    --ar-table-td: #fff;
    --ar-table-th-border: #333344;
    --ar-table-td-border: #333344;
    --ar-btn-create-bg: #0dcaf0;
    --ar-btn-create-text: #000;
    --ar-btn-create-hover: #0baccc;
    --ar-cust-header-bg: rgba(34, 34, 48, 0.6);
    --ar-cust-header-border: rgba(255, 255, 255, 0.05);
    --ar-print-btn-bg: rgba(255,255,255,0.05);
    --ar-print-btn-text: #fff;
    --ar-print-btn-border: rgba(255,255,255,0.1);
    --ar-icon-red: #ff5252;
    --ar-icon-orange: #ff922b;
    --ar-icon-yellow: #f59e0b;
    --ar-icon-purple: #9061f9;
    --ar-success: #3cc553;
}

[data-bs-theme="light"] {
    --ar-bg: #f8f9fa;
    --ar-text: #6c757d;
    --ar-card-bg: #ffffff;
    --ar-card-border: #dee2e6;
    --ar-summary-value: #212529;
    --ar-summary-label: #6c757d;
    --ar-table-bg: #ffffff;
    --ar-table-striped: #f8f9fa;
    --ar-table-border: #dee2e6;
    --ar-table-th: #495057;
    --ar-table-td: #212529;
    --ar-table-th-border: #dee2e6;
    --ar-table-td-border: #dee2e6;
    --ar-btn-create-bg: #0dcaf0;
    --ar-btn-create-text: #ffffff;
    --ar-btn-create-hover: #0baccc;
    --ar-cust-header-bg: rgba(248, 249, 250, 0.8);
    --ar-cust-header-border: #dee2e6;
    --ar-print-btn-bg: #f8f9fa;
    --ar-print-btn-text: #212529;
    --ar-print-btn-border: #dee2e6;
    --ar-icon-red: #dc3545;
    --ar-icon-orange: #fd7e14;
    --ar-icon-yellow: #ffc107;
    --ar-icon-purple: #6f42c1;
    --ar-success: #198754;
}

body {
    background-color: var(--ar-bg);
    color: var(--ar-text);
}

.card {
    background-color: var(--ar-card-bg);
    border-color: var(--ar-card-border);
    border-radius: 10px;
}

.summary-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--ar-summary-value);
    margin-bottom: 5px;
}

.summary-label {
    color: var(--ar-summary-label);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.table-dark {
    --bs-table-bg: var(--ar-table-bg);
    --bs-table-striped-bg: var(--ar-table-striped);
    --bs-table-border-color: var(--ar-table-border);
}

.table-dark th {
    color: var(--ar-table-th);
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--ar-table-th-border);
    padding: 1rem;
}

.table-dark td {
    padding: 0.75rem 1rem;
    vertical-align: middle;
    border-bottom: 1px solid var(--ar-table-td-border);
    color: var(--ar-table-td);
}

.btn-create {
    background-color: var(--ar-btn-create-bg);
    color: var(--ar-btn-create-text);
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-create:hover {
    background-color: var(--ar-btn-create-hover);
    color: var(--ar-btn-create-text);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3);
}

.cust-header {
    background: var(--ar-cust-header-bg);
    padding: 1rem;
    border-bottom: 1px solid var(--ar-cust-header-border);
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="fas fa-file-invoice-dollar me-2" style="color: var(--ar-icon-red);"></i> Accounts Receivable</h2>
            <p class="text-muted mb-0">Outstanding customer balances — unpaid and partially paid orders</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn px-4 py-2 rounded-pill no-print" style="background: var(--ar-print-btn-bg); color: var(--ar-print-btn-text); border: 1px solid var(--ar-print-btn-border);">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-4 mb-5 no-print">
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid var(--ar-icon-red);">
                <i class="fas fa-file-invoice-dollar icon-bg" style="color: var(--ar-icon-red);"></i>
                <div class="summary-label">Total Outstanding</div>
                <div class="summary-value text-white"><?= format_currency($total_outstanding) ?></div>
                <div style="font-size: 0.85rem; color: var(--ar-summary-label);"><?= count($ar_orders) ?> total orders pending</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid var(--ar-icon-orange);">
                <i class="fas fa-clock icon-bg" style="color: var(--ar-icon-orange);"></i>
                <div class="summary-label">Overdue 30+ Days</div>
                <div class="summary-value text-white"><?= format_currency(array_sum(array_column($overdue_30, 'total_amount'))) ?></div>
                <div style="font-size: 0.85rem; color: var(--ar-summary-label);"><?= count($overdue_30) ?> orders</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid var(--ar-icon-yellow);">
                <i class="fas fa-exclamation-circle icon-bg" style="color: var(--ar-icon-yellow);"></i>
                <div class="summary-label">Overdue 60+ Days</div>
                <div class="summary-value text-white"><?= format_currency(array_sum(array_column($overdue_60, 'total_amount'))) ?></div>
                <div style="font-size: 0.85rem; color: var(--ar-summary-label);"><?= count($overdue_60) ?> orders</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card summary-card" style="border-left: 4px solid var(--ar-icon-purple);">
                <i class="fas fa-calendar-times icon-bg" style="color: var(--ar-icon-purple);"></i>
                <div class="summary-label">Overdue 90+ Days</div>
                <div class="summary-value text-white"><?= format_currency(array_sum(array_column($overdue_90, 'total_amount'))) ?></div>
                <div style="font-size: 0.85rem; color: var(--ar-summary-label);"><?= count($overdue_90) ?> orders</div>
            </div>
        </div>
    </div>

    <?php if (empty($ar_orders)): ?>
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="fas fa-check-circle fa-4x mb-3" style="color: var(--ar-success);"></i>
                <h4 class="text-white">All Clear!</h4>
                <p class="text-muted">No outstanding receivables. All orders are paid.</p>
            </div>
        </div>
    <?php else: ?>

        <!-- By Customer -->
        <?php foreach ($by_customer as $cust): ?>
            <div class="card border-0 shadow-sm mb-4" style="overflow: hidden;">
                <div class="cust-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div style="width: 40px; height: 40px; border-radius: 8px; background: rgba(13,202,240,0.1); color: #0dcaf0; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.2rem; margin-right: 15px;">
                            <?= strtoupper(substr($cust['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong class="text-white fs-5"><?= escape_html($cust['name']) ?></strong>
                            <span class="badge ms-2" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($cust['code']) ?></span>
                            <?php if ($cust['email']): ?>
                                <div style="font-size: 0.85rem; color: #8e8e9e; margin-top: 2px;"><i class="fas fa-envelope me-1"></i><?= escape_html($cust['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div style="font-size: 0.85rem; color: #8e8e9e; text-transform: uppercase;">Customer Balance</div>
                        <div class="font-monospace fw-bold fs-4" style="color: #ff5252;"><?= format_currency($cust['total']) ?></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead style="background-color: #1a1a24;">
                                <tr>
                                    <th class="ps-4">Order No.</th>
                                    <th>Order Date</th>
                                    <th>Aging</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th class="text-end">Amount Due</th>
                                    <th class="text-end pe-4 no-print">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cust['orders'] as $ord): ?>
                                    <?php
                                    $days = $ord['days_outstanding'];
                                    // Highlighting older rows lightly
                                    $row_bg = '';
                                    if ($days > 90) $row_bg = 'background-color: rgba(255, 82, 82, 0.05);';
                                    else if ($days > 60) $row_bg = 'background-color: rgba(245, 158, 11, 0.05);';
                                    ?>
                                    <tr style="<?= $row_bg ?>">
                                        <td class="ps-4"><strong style="color: #0dcaf0;"><?= escape_html($ord['order_number']) ?></strong></td>
                                        <td><?= format_date($ord['order_date']) ?></td>
                                        <td>
                                            <?php if ($days > 90): ?>
                                                <span class="badge" style="background: rgba(255,82,82,0.15); color: #ff5252; border: 1px solid rgba(255,82,82,0.3);"><i class="fas fa-fire me-1"></i><?= $days ?> days</span>
                                            <?php elseif ($days > 60): ?>
                                                <span class="badge" style="background: rgba(245,158,11,0.15); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3);"><i class="fas fa-exclamation-triangle me-1"></i><?= $days ?> days</span>
                                            <?php elseif ($days > 30): ?>
                                                <span class="badge" style="background: rgba(255,146,43,0.15); color: #ff922b; border: 1px solid rgba(255,146,43,0.3);"><?= $days ?> days</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(60,197,83,0.15); color: #3cc553; border: 1px solid rgba(60,197,83,0.3);"><?= $days ?> days</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge" style="background: rgba(255,255,255,0.05); color: #b0b0c0;"><?= ucfirst(str_replace('_', ' ', $ord['order_status'])) ?></span></td>
                                        <td><span class="badge" style="background: rgba(255,146,43,0.1); color: #ff922b; border: 1px solid rgba(255,146,43,0.2);"><?= ucfirst(str_replace('_', ' ', $ord['payment_status'])) ?></span></td>
                                        <td class="text-end font-monospace" style="color: #ff5252; font-weight: 600;"><?= format_currency($ord['total_amount']) ?></td>
                                        <td class="text-end pe-4 no-print">
                                            <a href="../sales/view_order.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0; border: 1px solid rgba(13,202,240,0.2);" title="View Order">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<style>
    @media print {

        nav,
        .navbar,
        .sidebar,
        .btn,
        .no-print,
        .cust-header {
            display: none !important
        }

        body {
            background: #fff !important;
            color: #000 !important;
            font-size: 12pt
        }

        .card {
            border: 1px solid #ccc !important
        }

        table {
            color: #000 !important;
            border-collapse: collapse !important;
            width: 100%
        }

        th,
        td {
            border: 1px solid #ccc !important;
            padding: 4px 8px !important;
            color: #000 !important;
            text-align: left !important
        }

        .badge {
            border: 1px solid #ccc;
            color: #000 !important;
            background: none !important
        }
    }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
