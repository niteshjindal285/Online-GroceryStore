<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();
require_permission('view_inventory');

$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

$wh_res = db_fetch("SELECT COUNT(*) as count FROM warehouses WHERE is_active = 1 AND company_id = ?", [$company_id]);
$total_warehouses = $wh_res ? $wh_res['count'] : 0;

$item_res = db_fetch("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1 AND company_id = ?", [$company_id]);
$total_items = $item_res ? $item_res['count'] : 0;

$low_stock_query = db_fetch("
    SELECT COUNT(DISTINCT item_id) as count
    FROM (
        SELECT s.item_id, s.quantity_available as q, i.reorder_level
        FROM inventory_stock_levels s
        JOIN inventory_items i ON s.item_id = i.id
        JOIN locations l ON s.location_id = l.id
        WHERE i.is_active = 1 AND i.reorder_level > 0 AND i.company_id = ? AND l.company_id = ?

        UNION ALL

        SELECT wi.product_id as item_id, wi.quantity as q, i.reorder_level
        FROM warehouse_inventory wi
        JOIN inventory_items i ON wi.product_id = i.id
        JOIN warehouses w ON wi.warehouse_id = w.id
        WHERE i.is_active = 1 AND i.reorder_level > 0 AND i.company_id = ? AND w.company_id = ?
    ) AS all_locations
    WHERE q <= reorder_level
", [$company_id, $company_id, $company_id, $company_id]);
$low_stock_count = $low_stock_query ? $low_stock_query['count'] : 0;

$wh_list = db_fetch_all("SELECT id, name FROM warehouses WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

$master_config_cards = [
    [
        'title' => 'Warehouses',
        'description' => 'Manage locations, managers, and storage capacity.',
        'href' => '../inventory/modules/warehouses/index.php',
        'button_text' => 'Open Module',
        'icon' => 'fa-warehouse',
        'icon_bg' => 'bg-primary text-white',
        'button_class' => 'btn btn-outline-primary w-100',
    ],
    [
        'title' => 'Inventory Items',
        'description' => 'Manage SKUs, Barcodes, and Pricing details.',
        'href' => '../inventory/index.php',
        'button_text' => 'Manage Items',
        'icon' => 'fa-boxes',
        'icon_bg' => 'bg-info text-white',
        'button_class' => 'btn btn-outline-info w-100',
    ],
    [
        'title' => 'Optimization & Reports',
        'description' => 'Live stock movements and inventory visibility.',
        'href' => '../inventory/stock_report.php',
        'button_text' => 'Stock Report',
        'icon' => 'fa-chart-pie',
        'icon_bg' => 'bg-success text-white',
        'button_class' => 'btn btn-outline-success w-100',
        'secondary_href' => '../inventory/modules/warehouses/wms_health.php',
        'secondary_text' => 'WMS Health',
    ],
    [
        'title' => 'Reorder Alerts',
        'description' => 'Sends email alerts to the manager when stock is low.',
        'href' => '../inventory/reorder.php',
        'button_text' => 'View Reports',
        'icon' => 'fa-sync-alt',
        'icon_bg' => 'bg-success text-white',
        'button_class' => 'btn btn-outline-success w-100',
    ],
    [
        'title' => 'Price Change',
        'description' => 'Multi-item price updates with approval workflow.',
        'href' => '../inventory/price_change_history.php',
        'button_text' => 'Manage Prices',
        'icon' => 'fa-tags',
        'icon_bg' => 'bg-info text-dark',
        'button_class' => 'btn btn-info w-100',
        'card_class' => 'border-top border-info border-4',
    ],
];

$page_title = 'Item Menu of Inventory - MJR Group';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold"><i class="fas fa-sliders-h me-2 text-primary"></i>Item Menu of Inventory</h2>
            <p class="text-muted">Configure core inventory masters and warehouse setup for <strong><?= escape_html($company_name) ?></strong>.</p>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase">Total Warehouses</h6>
                    <h2 class="mb-0"><?= $total_warehouses ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-info border-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase">Catalog Items</h6>
                    <h2 class="mb-0"><?= $total_items ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-danger border-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase">Low Stock Alerts</h6>
                    <h2 class="mb-0 text-danger"><?= $low_stock_count ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <?php foreach ($master_config_cards as $card): ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100 shadow-sm border-0 <?= $card['card_class'] ?? '' ?>">
                    <div class="card-body text-center p-4">
                        <div class="<?= $card['icon_bg'] ?> rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas <?= $card['icon'] ?> fa-lg"></i>
                        </div>
                        <h5><?= escape_html($card['title']) ?></h5>
                        <p class="text-muted small"><?= escape_html($card['description']) ?></p>
                        <a href="<?= escape_html($card['href']) ?>" class="<?= escape_html($card['button_class']) ?>"><?= escape_html($card['button_text']) ?></a>
                        <?php if (!empty($card['secondary_href']) && !empty($card['secondary_text'])): ?>
                            <a href="<?= escape_html($card['secondary_href']) ?>" class="btn btn-link btn-sm text-success mt-2"><?= escape_html($card['secondary_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body text-center p-4">
                    <div class="bg-warning text-dark rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fas fa-clipboard-check fa-lg"></i>
                    </div>
                    <h5>Cycle Counting</h5>
                    <p class="text-muted small">Audit physical stock and reconcile variances.</p>

                    <div class="mb-3">
                        <select id="auditWarehouseSelect" class="form-select form-select-sm">
                            <option value="">-- Select Warehouse --</option>
                            <?php foreach ($wh_list as $wh): ?>
                                <option value="<?= $wh['id'] ?>"><?= escape_html($wh['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button onclick="goToAudit()" class="btn btn-warning w-100" id="auditBtn" disabled>
                        Start Audit
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const auditWarehouseSelect = document.getElementById('auditWarehouseSelect');
const auditBtn = document.getElementById('auditBtn');

if (auditWarehouseSelect && auditBtn) {
    auditWarehouseSelect.addEventListener('change', function() {
        auditBtn.disabled = !this.value;
    });
}

function goToAudit() {
    if (auditWarehouseSelect && auditWarehouseSelect.value) {
        window.location.href = "../inventory/modules/warehouses/inventory.php?id=" + auditWarehouseSelect.value;
    }
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
