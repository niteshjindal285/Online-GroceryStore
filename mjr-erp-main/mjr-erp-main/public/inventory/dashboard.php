<?php
require_once __DIR__ . '/../../includes/auth.php'; 
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php'; 
require_login();

$page_title = 'WMS Master Dashboard - MJR Group';
include __DIR__ . '/../../templates/header.php';

$inventory_quick_links = [
    [
        'title' => 'Purchase Order',
        'description' => 'Create and manage supplier purchase orders.',
        'href' => 'purchase_order/purchase_orders.php',
        'icon' => 'fa-file-invoice',
        'btn_class' => 'btn-outline-primary',
        'icon_bg' => 'bg-primary text-white',
    ],
    [
        'title' => 'Stock Entry',
        'description' => 'Receive and post incoming stock entries.',
        'href' => 'gsrn/index.php',
        'icon' => 'fa-arrow-down-short-wide',
        'btn_class' => 'btn-outline-secondary',
        'icon_bg' => 'bg-secondary text-white',
    ],
    [
        'title' => 'Stock Report',
        'description' => 'Review live stock levels and movement summaries.',
        'href' => 'stock_report.php',
        'icon' => 'fa-chart-column',
        'btn_class' => 'btn-outline-success',
        'icon_bg' => 'bg-success text-white',
    ],
    [
        'title' => 'Stock Transfer',
        'description' => 'Move stock between warehouses and locations.',
        'href' => 'transfer_history.php',
        'icon' => 'fa-right-left',
        'btn_class' => 'btn-outline-dark',
        'icon_bg' => 'bg-dark text-white',
    ],
    [
        'title' => 'Price Change',
        'description' => 'Manage item price revisions and approvals.',
        'href' => 'price_change_history.php',
        'icon' => 'fa-tags',
        'btn_class' => 'btn-outline-info',
        'icon_bg' => 'bg-info text-dark',
    ],
    [
        'title' => 'Suppliers',
        'description' => 'View and maintain supplier master records.',
        'href' => 'supplier/suppliers.php',
        'icon' => 'fa-truck-field',
        'btn_class' => 'btn-outline-warning',
        'icon_bg' => 'bg-warning text-dark',
    ],
    [
        'title' => 'Customers',
        'description' => 'Access inventory-side customer records.',
        'href' => 'customer/customers.php',
        'icon' => 'fa-users',
        'btn_class' => 'btn-outline-primary',
        'icon_bg' => 'bg-primary text-white',
    ],
    [
        'title' => 'Stock Take (Physical Count)',
        'description' => 'Start or review warehouse physical stock counts.',
        'href' => 'stock_take/index.php',
        'icon' => 'fa-clipboard-check',
        'btn_class' => 'btn-outline-warning',
        'icon_bg' => 'bg-warning text-dark',
    ],
    [
        'title' => 'Backlog Orders',
        'description' => 'Track backlog orders awaiting fulfillment.',
        'href' => 'backlog_orders.php',
        'icon' => 'fa-layer-group',
        'btn_class' => 'btn-outline-secondary',
        'icon_bg' => 'bg-secondary text-white',
    ],
];
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold"><i class="fas fa-th-large me-2 text-primary"></i>Inventory Management Hub</h2>
            <p class="text-muted">Quick access to inventory operations, purchasing, transfers, and stock control tools.</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-compass me-2 text-primary"></i>Inventory Quick Access</h5>
                            <p class="text-muted mb-0 small">All options from the Inventory dropdown are available here as dashboard shortcuts.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($inventory_quick_links as $quick_link): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded-3 h-100 p-3 bg-body-tertiary">
                                    <div class="d-flex align-items-start gap-3">
                                        <div class="<?= $quick_link['icon_bg'] ?> rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 48px; height: 48px;">
                                            <i class="fas <?= $quick_link['icon'] ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= escape_html($quick_link['title']) ?></h6>
                                            <p class="text-muted small mb-3"><?= escape_html($quick_link['description']) ?></p>
                                            <a href="<?= escape_html($quick_link['href']) ?>" class="btn <?= $quick_link['btn_class'] ?> btn-sm w-100">Open</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
