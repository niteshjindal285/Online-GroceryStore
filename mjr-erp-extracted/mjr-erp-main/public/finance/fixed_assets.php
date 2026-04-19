<?php
/**
 * Fixed Assets Management
 * List and manage company assets and depreciation
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Fixed Assets - MJR Group ERP';
$company_id = (int)active_company_id(1);

// Get assets
$assets = db_fetch_all("
    SELECT fa.*, c.name as company_name
    FROM fixed_assets fa
    LEFT JOIN companies c ON fa.company_id = c.id
    WHERE fa.company_id = ?
    ORDER BY fa.asset_code
", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<?php
// Calculate summary metrics
$metric_total_assets = count($assets);
$metric_purchase_value = 0;
$metric_accum_depr = 0;
$metric_net_book = 0;

foreach ($assets as $a) {
    if ($a['status'] === 'active') { // Assuming we only sum active assets, or all? Let's sum all non-disposed
        $metric_purchase_value += $a['purchase_price'];
        $metric_accum_depr += $a['accumulated_depreciation'];
        $metric_net_book += $a['net_book_value'];
    }
}
?>


<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 text-white">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-tools me-2 text-primary"></i>Fixed Assets</h1>
            <p class="text-muted mb-0">Manage company machinery, vehicles, and equipment</p>
        </div>
        <div class="d-flex gap-2">
            <a href="calculate_depreciation.php" class="btn btn-outline-warning px-3 py-2 rounded-pill fw-bold shadow-sm">
                <i class="fas fa-calculator me-2"></i>Depreciation
            </a>
            <a href="add_fixed_asset.php" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-plus me-2"></i>New Asset
            </a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card border-0 bg-info text-white shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Total Assets</small><div class="fs-4 fw-bold"><?= $metric_total_assets ?></div></div>
                        <i class="fas fa-boxes fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-primary text-white shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Purchase Value</small><div class="fs-4 fw-bold"><?= format_currency($metric_purchase_value) ?></div></div>
                        <i class="fas fa-money-check-alt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-danger text-white shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Accum. Depr.</small><div class="fs-4 fw-bold"><?= format_currency($metric_accum_depr) ?></div></div>
                        <i class="fas fa-chart-line fa-rotate-180 fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-success text-white shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75 text-uppercase fw-bold" style="font-size: 0.7rem; letter-spacing: 1px;">Net Book Value</small><div class="fs-4 fw-bold"><?= format_currency($metric_net_book) ?></div></div>
                        <i class="fas fa-building fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-tools me-2"></i>Asset Inventory</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($assets)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="assetsTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Asset Name / Details</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Accum. Depr.</th>
                            <th class="text-end">Net Book</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td class="ps-4"><strong style="color: #0dcaf0;"><?= escape_html($asset['asset_code']) ?></strong></td>
                            <td>
                                <div class="fw-bold"><?= escape_html($asset['asset_name']) ?></div>
                                <div style="font-size: 0.85rem; color: #8e8e9e;"><i class="fas fa-building me-1"></i><?= escape_html($asset['company_name'] ?? 'Main') ?></div>
                            </td>
                            <td><?= format_date($asset['purchase_date']) ?></td>
                            <td class="text-end font-monospace" style="color: #3cc553;"><?= format_currency($asset['purchase_price']) ?></td>
                            <td class="text-end font-monospace" style="color: #ff5252;"><?= format_currency($asset['accumulated_depreciation']) ?></td>
                            <td class="text-end font-monospace">
                                <strong class="fs-6" style="color: #9061f9;"><?= format_currency($asset['net_book_value']) ?></strong>
                            </td>
                            <td>
                                <?php if ($asset['status'] == 'active'): ?>
                                    <span class="badge bg-success-soft text-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-soft text-muted"><?= ucfirst($asset['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="btn-group">
                                    <a href="view_asset.php?id=<?= $asset['id'] ?>" class="btn btn-sm btn-icon" style="background: rgba(255,255,255,0.05); color: #8e8e9e; border: 1px solid rgba(255,255,255,0.05);" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_asset.php?id=<?= $asset['id'] ?>" class="btn btn-sm btn-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0; border: 1px solid rgba(13,202,240,0.2);" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            $(document).ready(function() {
                $('#assetsTable').DataTable({
                    order: [[0, 'asc']],
                    pageLength: 25,
                    language: {
                        search: "",
                        searchPlaceholder: "Search assets...",
                        lengthMenu: "Show _MENU_"
                    },
                    dom: "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>"
                });
                
                $('.dataTables_filter input').css('width', '250px');
            });
            </script>
            
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tools fa-3x mb-3" style="color: #333344;"></i>
                <h5 class="text-white">No fixed assets found</h5>
                <p class="text-muted">Register vehicles, machinery, or equipment to track depreciation.</p>
                <a href="add_fixed_asset.php" class="btn btn-create px-4 py-2 mt-2 rounded-pill">
                    <i class="fas fa-plus me-2"></i>Add First Asset
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
