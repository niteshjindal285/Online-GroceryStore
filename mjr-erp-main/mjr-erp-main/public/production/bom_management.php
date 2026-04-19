<?php
/**
 * BOM Management Screen
 * Screen 2: Production Configuration / BOM Screen
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/bom_functions.php';

require_login();
require_permission('manage_production');

$page_title = 'BOM Management - MJR Group ERP';
$company_id = active_company_id(1);

// Data for dropdowns
$products = db_fetch_all("SELECT id, code, name, category_id FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories ORDER BY name");
$units = db_fetch_all("SELECT id, name FROM units_of_measure ORDER BY name");
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$warehouses = db_fetch_all("SELECT id, name FROM warehouses WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Handle Duplication if requested
if (get_param('duplicate_id')) {
    $new_id = duplicate_bom(intval(get_param('duplicate_id')), $_SESSION['user_id']);
    if ($new_id) {
        set_flash("BOM duplicated successfully!", "success");
        redirect("bom_management.php?id=" . $new_id);
    } else {
        set_flash("Failed to duplicate BOM.", "error");
    }
}

// Handle Deletion if requested
if (get_param('delete_id')) {
    $del_id = intval(get_param('delete_id'));
    // Check status first
    $check = db_fetch("SELECT status FROM bom_headers WHERE id = ?", [$del_id]);
    if ($check && $check['status'] === 'Draft') {
        db_begin_transaction();
        try {
            db_query("DELETE FROM bom_items WHERE bom_id = ?", [$del_id]);
            db_query("DELETE FROM bom_headers WHERE id = ?", [$del_id]);
            db_commit();
            set_flash("Draft BOM deleted successfully!", "success");
        } catch (Exception $e) {
            db_rollback();
            set_flash("Failed to delete BOM: " . $e->getMessage(), "error");
        }
    } else {
        set_flash("Only Draft BOMs can be deleted.", "error");
    }
    redirect("bom_management.php");
}

// Get current BOM if editing
$bom_id = intval(get_param('id', 0));
$bom = null;
$bom_items = [];

if ($bom_id) {
    $bom = db_fetch("SELECT * FROM bom_headers WHERE id = ?", [$bom_id]);
    if ($bom) {
        $bom_items = db_fetch_all("
            SELECT bi.*, ii.code, ii.name as item_name, c.name as category_name, w.name as warehouse_name, u.code as unit
            FROM bom_items bi
            JOIN inventory_items ii ON bi.item_id = ii.id
            LEFT JOIN categories c ON ii.category_id = c.id
            LEFT JOIN warehouses w ON bi.warehouse_id = w.id
            LEFT JOIN units_of_measure u ON ii.unit_of_measure_id = u.id
            WHERE bi.bom_id = ?
        ", [$bom_id]);
    }
}

$next_bom_number = $bom ? $bom['bom_number'] : get_next_bom_number();

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <h1 class="h3 mb-0 text-white"><i class="fas fa-layer-group me-2"></i>BOM Configuration</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../index.php">Production</a></li>
                    <li class="breadcrumb-item active">BOM Screen</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-6 text-end">
            <?php if ($bom_id): ?>
            <a href="bom_management.php?duplicate_id=<?= $bom_id ?>" class="btn btn-outline-warning me-2" onclick="return confirm('Duplicate this BOM to create a variant?');">
                <i class="fas fa-copy me-1"></i> Duplicate BOM
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#historyModal">
                <i class="fas fa-history me-1"></i> BOM History
            </button>
            <button type="button" class="btn btn-primary" onclick="saveBOM()">
                <i class="fas fa-save me-1"></i> Save BOM
            </button>
        </div>
    </div>

    <form id="bomForm">
        <input type="hidden" name="bom_id" id="bom_id" value="<?= $bom_id ?>">
        
        <div class="row">
            <!-- Left Column: Main Configuration -->
            <div class="col-lg-8">
                <!-- 1️⃣ PRODUCT CONFIGURATION HEADER -->
                <div class="card mb-4 border-0 shadow-sm bg-dark">
                    <div class="card-header bg-primary bg-opacity-10 py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-id-badge me-2"></i>Product Configuration Header</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">BOM Reference Number</label>
                                <input type="text" class="form-control bg-dark border-secondary text-info fw-bold" name="bom_number" value="<?= $next_bom_number ?>" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Product Code (Search)</label>
                                <select class="form-select select2" name="product_id" id="product_id" required onchange="autoFillProductName(this)">
                                    <option value="">Search finished product...</option>
                                    <?php foreach ($products as $p): ?>
                                    <option value="<?= $p['id'] ?>" data-name="<?= escape_html($p['name']) ?>" data-category-id="<?= $p['category_id'] ?>" <?= ($bom && $bom['product_id'] == $p['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($p['code']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-1 d-flex align-items-end">
                                <a href="../inventory/create.php" class="btn btn-outline-secondary w-100" target="_blank" title="Create New Product">
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Product Name (Auto)</label>
                                <input type="text" class="form-control bg-dark border-secondary" id="product_name_display" value="<?= $bom ? escape_html(db_fetch("SELECT name FROM inventory_items WHERE id=?", [$bom['product_id']])['name']) : '' ?>" readonly>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Product Category</label>
                                <select class="form-select bg-dark border-secondary" name="category_id" id="category_id_select">
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($bom && $bom['category_id'] == $c['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($c['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Product Capacity</label>
                                <input type="text" class="form-control bg-dark border-secondary" name="product_capacity" value="<?= $bom ? $bom['product_capacity'] : '' ?>" placeholder="e.g. 500L / 1000L">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-muted small fw-bold">Unit</label>
                                <select class="form-select bg-dark border-secondary" name="unit_id">
                                    <?php foreach ($units as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($bom && $bom['unit_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($u['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small fw-bold">Production Location</label>
                                <select class="form-select bg-dark border-secondary" name="location_id">
                                    <option value="">Select factory...</option>
                                    <?php foreach ($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>" <?= ($bom && $bom['location_id'] == $l['id']) ? 'selected' : '' ?>>
                                        <?= escape_html($l['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Created Date</label>
                                <input type="text" class="form-control bg-dark border-secondary" value="<?= $bom ? date('Y-m-d', strtotime($bom['created_at'])) : date('Y-m-d') ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Created By</label>
                                <input type="text" class="form-control bg-dark border-secondary" value="<?= $bom ? escape_html(db_fetch("SELECT username FROM users WHERE id=?", [$bom['created_by']])['username']) : escape_html($_SESSION['username']) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Status</label>
                                <select class="form-select bg-dark border-secondary" name="status">
                                    <option value="Draft" <?= ($bom && $bom['status'] == 'Draft') ? 'selected' : '' ?>>Draft</option>
                                    <option value="Active" <?= ($bom && $bom['status'] == 'Active') ? 'selected' : '' ?>>Active</option>
                                    <option value="Archived" <?= ($bom && $bom['status'] == 'Archived') ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label text-muted small fw-bold">Remarks</label>
                                <textarea class="form-control bg-dark border-secondary" name="remarks" rows="1"><?= $bom ? $bom['remarks'] : '' ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2️⃣ RAW MATERIAL REQUIREMENTS TABLE -->
                <div class="card mb-4 border-0 shadow-sm bg-dark">
                    <div class="card-header bg-success bg-opacity-10 py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-success"><i class="fas fa-microchip me-2"></i>Raw Material Requirements</h5>
                        <button type="button" class="btn btn-sm btn-success" onclick="addRow()">
                            <i class="fas fa-plus me-1"></i> Add Material
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0" id="materialTable">
                                <thead style="background-color: #2d3748; color: #e2e8f0;">
                                    <tr>
                                        <th style="width: 250px; background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Material Code / Name</th>
                                        <th style="background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Category</th>
                                        <th style="background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Warehouse</th>
                                        <th style="width: 120px; background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Required Qty</th>
                                        <th style="width: 80px; background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Unit</th>
                                        <th style="width: 100px; background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Unit Cost</th>
                                        <th style="width: 120px; background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Total Cost</th>
                                        <th style="background-color: #2d3748; color: #e2e8f0; border-color: rgba(255,255,255,0.1);">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($bom_items)): ?>
                                    <tr id="no-items-row">
                                        <td colspan="8" class="text-center py-4 text-muted">No materials added yet. Click "Add Material" to start.</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($bom_items as $index => $item): ?>
                                        <tr class="material-row">
                                            <td>
                                                <input type="hidden" name="items[<?= $index ?>][item_id]" value="<?= $item['item_id'] ?>" class="item-id">
                                                <div class="fw-bold"><?= escape_html($item['code']) ?></div>
                                                <small class="text-muted"><?= escape_html($item['item_name']) ?></small>
                                            </td>
                                            <td><?= escape_html($item['category_name']) ?></td>
                                            <td>
                                                <select class="form-select form-select-sm bg-dark border-secondary wh-id" name="items[<?= $index ?>][warehouse_id]">
                                                    <?php foreach ($warehouses as $w): ?>
                                                    <option value="<?= $w['id'] ?>" <?= $item['warehouse_id'] == $w['id'] ? 'selected' : '' ?>><?= $w['name'] ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm bg-dark border-secondary req-qty" 
                                                       name="items[<?= $index ?>][quantity_required]" value="<?= $item['quantity_required'] ?>" 
                                                       step="0.0001" onchange="updateRowTotal(this)" readonly>
                                            </td>
                                            <td><?= escape_html($item['unit'] ?: 'pcs') ?></td>
                                            <td>
                                                <input type="number" class="form-control form-control-sm bg-dark border-secondary unit-cost" 
                                                       name="items[<?= $index ?>][unit_cost]" value="<?= $item['unit_cost'] ?>" 
                                                       step="0.01" onchange="updateRowTotal(this)" readonly>
                                            </td>
                                            <td class="row-total text-info fw-bold">0.00</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="editRow(this)" title="Edit Material">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" title="Delete Material">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 6️⃣ PRODUCT RANGE MANAGEMENT -->
                <div class="card mb-4 border-0 shadow-sm bg-dark">
                    <div class="card-header bg-warning bg-opacity-10 py-3">
                        <h5 class="mb-0 text-warning"><i class="fas fa-tags me-2"></i>Product Range Management</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-muted small fw-bold">Product Range Name</label>
                                <input type="text" class="form-control bg-dark border-secondary" name="range_name" value="<?= $bom ? escape_html($bom['range_name']) : '' ?>" placeholder="e.g. Water Tank Series">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Product Variant</label>
                                <input type="text" class="form-control bg-dark border-secondary" name="variant_name" value="<?= $bom ? escape_html($bom['variant_name']) : '' ?>" placeholder="e.g. Standard / Premium">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-muted small fw-bold">Capacity Range</label>
                                <input type="text" class="form-control bg-dark border-secondary" name="capacity_range" value="<?= $bom ? escape_html($bom['capacity_range']) : '' ?>" placeholder="e.g. 300L - 2000L">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Costs and Validation -->
            <div class="col-lg-4">
                <!-- 3️⃣ PRODUCTION COST CONFIGURATION -->
                <div class="card mb-4 border-0 shadow-sm bg-dark">
                    <div class="card-header bg-info bg-opacity-10 py-3">
                        <h5 class="mb-0 text-info"><i class="fas fa-coins me-2"></i>Production Costs</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Labor Cost</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-0 text-white">$</span>
                                <input type="number" class="form-control bg-dark border-secondary add-cost" name="labor_cost" value="<?= $bom ? $bom['labor_cost'] : '0.00' ?>" step="0.01" onchange="updateSummary()">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Electricity Cost</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-0 text-white">$</span>
                                <input type="number" class="form-control bg-dark border-secondary add-cost" name="electricity_cost" value="<?= $bom ? $bom['electricity_cost'] : '0.00' ?>" step="0.01" onchange="updateSummary()">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">Machine Usage Cost</label>
                            <div class="input-group">
                                <span class="input-group-text bg-secondary border-0 text-white">$</span>
                                <input type="number" class="form-control bg-dark border-secondary add-cost" name="machine_cost" value="<?= $bom ? $bom['machine_cost'] : '0.00' ?>" step="0.01" onchange="updateSummary()">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">Maintenance</label>
                                <input type="number" class="form-control bg-dark border-secondary add-cost" name="maintenance_cost" value="<?= $bom ? $bom['maintenance_cost'] : '0.00' ?>" step="0.01" onchange="updateSummary()">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small fw-bold">Other Costs</label>
                                <input type="number" class="form-control bg-dark border-secondary add-cost" name="other_cost" value="<?= $bom ? $bom['other_cost'] : '0.00' ?>" step="0.01" onchange="updateSummary()">
                            </div>
                        </div>
                        <hr class="border-secondary border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-muted small fw-bold">TOTAL ADDITIONAL COST</h6>
                            <h5 class="mb-0 text-info" id="totalAdditionalLabel">$ 0.00</h5>
                        </div>
                    </div>
                </div>

                <!-- 4️⃣ TOTAL PRODUCT COST CALCULATION -->
                <div class="card mb-4 border-0 shadow-sm bg-primary bg-opacity-10 border-primary border-opacity-25">
                    <div class="card-header py-3">
                        <h5 class="mb-0 text-primary"><i class="fas fa-calculator me-2"></i>Total Product Cost</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Raw Material Cost</span>
                            <span class="fw-bold" id="totalMaterialLabel">$ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Additional Cost</span>
                            <span class="fw-bold" id="additionalCostLabel">$ 0.00</span>
                        </div>
                        <hr class="border-secondary border-opacity-25">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="h5 mb-0">Total Production Cost</span>
                            <span class="h5 mb-0 text-primary" id="totalProductionLabel">$ 0.00</span>
                        </div>
                        <div class="alert alert-primary py-2 mt-3 text-center mb-0">
                            <small class="text-muted d-block">Cost per Unit</small>
                            <span class="h4 mb-0 fw-bold" id="costPerUnitLabel">$ 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- 5️⃣ MATERIAL AVAILABILITY VALIDATION -->
                <div class="card mb-4 border-0 shadow-sm bg-dark">
                    <div class="card-header bg-danger bg-opacity-10 py-3">
                        <h5 class="mb-0 text-danger"><i class="fas fa-check-circle me-2"></i>Material Availability Validation</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Before production starts, ERP should check stock.</p>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-dark table-bordered border-secondary mb-0">
                                <tbody>
                                    <tr>
                                        <th class="text-muted w-50 fw-bold">Material Availability</th>
                                        <td id="mat_avail_val">
                                            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-bold">Shortage Items</th>
                                        <td id="mat_short_val">-</td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted fw-bold">Production Ready</th>
                                        <td id="prod_ready_val">
                                            <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div id="availability_example" class="p-3 bg-black border border-secondary rounded font-monospace small text-muted mb-3" style="display: none;">
                            <!-- populated by JS -->
                        </div>

                        <div id="availability_blocked" class="p-3 bg-black border border-secondary text-danger rounded fw-bold small" style="display: none;">
                            Production order blocked
                        </div>

                        <div class="d-grid mt-3">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="checkAvailability()">
                                <i class="fas fa-sync me-1"></i> Re-check Stock
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 9️⃣ BOM HISTORY MODAL -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">BOM History Panel</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- 8️⃣ PRODUCT SEARCH PANEL -->
                <div class="card mb-3 bg-dark border-secondary">
                    <div class="card-header bg-primary bg-opacity-10 text-primary py-2 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-search me-2"></i>8. Product Search Panel</h6>
                        <button class="btn btn-sm btn-outline-secondary py-0" onclick="resetFilters()">Reset</button>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="small text-muted mb-1">Product (Search)</label>
                                <input type="text" id="filterProduct" class="form-control form-control-sm bg-dark border-secondary filter-input" placeholder="Search product...">
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Category</label>
                                <select id="filterCategory" class="form-select form-select-sm bg-dark border-secondary filter-input">
                                    <option value="">All</option>
                                    <?php foreach ($categories as $c): ?>
                                    <option value="<?= escape_html($c['name']) ?>"><?= escape_html($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Capacity</label>
                                <select id="filterCapacity" class="form-select form-select-sm bg-dark border-secondary filter-input">
                                    <option value="">All</option>
                                    <?php 
                                    $capacities = db_fetch_all("SELECT DISTINCT product_capacity FROM bom_headers WHERE product_capacity IS NOT NULL AND product_capacity != '' ORDER BY product_capacity");
                                    foreach ($capacities as $cap): ?>
                                    <option value="<?= escape_html($cap['product_capacity']) ?>"><?= escape_html($cap['product_capacity']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="small text-muted mb-1">Date Created</label>
                                <input type="date" id="filterDate" class="form-control form-control-sm bg-dark border-secondary filter-input">
                            </div>
                            <div class="col-md-2">
                                <label class="small text-muted mb-1">Status</label>
                                <select id="filterStatus" class="form-select form-select-sm bg-dark border-secondary filter-input">
                                    <option value="">All</option>
                                    <option value="Active">Active</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Archived">Archived</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark table-hover" id="historyTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>BOM Ref</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Capacity</th>
                                <th>Items</th>
                                <th>Total Cost</th>
                                <th>Created Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $history = db_fetch_all("
                                SELECT bh.*, ii.code as product_code, ii.name as product_name, c.name as cat_name,
                                      (SELECT COUNT(*) FROM bom_items WHERE bom_id = bh.id) as item_count
                                FROM bom_headers bh
                                JOIN inventory_items ii ON bh.product_id = ii.id
                                LEFT JOIN categories c ON bh.category_id = c.id
                                ORDER BY bh.created_at DESC
                            ");
                            foreach ($history as $h): 
                            ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= $h['bom_number'] ?></span></td>
                                <td>
                                    <div class="small fw-bold"><?= $h['product_code'] ?></div>
                                    <div class="small text-muted"><?= $h['product_name'] ?></div>
                                </td>
                                <td><?= $h['cat_name'] ?></td>
                                <td><?= $h['product_capacity'] ?></td>
                                <td><?= $h['item_count'] ?> items</td>
                                <td class="text-info"><?= number_format($h['total_production_cost'], 2) ?></td>
                                <td class="small" data-order="<?= $h['created_at'] ?>"><?= date('Y-m-d', strtotime($h['created_at'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $h['status'] == 'Active' ? 'success' : ($h['status'] == 'Draft' ? 'warning' : 'secondary') ?>">
                                        <?= $h['status'] ?>
                                    </span>
                                </td>
                                 <td>
                                     <a href="bom_management.php?id=<?= $h['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                     <a href="bom_management.php?duplicate_id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-info">Duplicate</a>
                                     <?php if ($h['status'] === 'Draft'): ?>
                                     <a href="bom_management.php?delete_id=<?= $h['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this draft BOM?');">
                                         <i class="fas fa-trash"></i>
                                     </a>
                                     <?php endif; ?>
                                 </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Material Selector Modal -->
<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Select Raw Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Search Material</label>
                    <select class="form-select select2-material" id="material_selector">
                        <option value="">Search materials...</option>
                        <?php 
                        $materials = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
                        foreach ($materials as $m): 
                        ?>
                        <option value="<?= $m['id'] ?>"><?= $m['code'] ?> — <?= $m['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control bg-dark border-secondary text-white" id="material_qty" value="1" step="0.0001">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Unit Cost (0 for default)</label>
                        <input type="number" class="form-control bg-dark border-secondary text-white" id="material_cost" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Warehouse Source</label>
                    <select class="form-select bg-dark border-secondary text-white" id="material_warehouse">
                        <?php foreach ($warehouses as $w): ?>
                        <option value="<?= $w['id'] ?>"><?= escape_html($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmAddMaterial()">Add to BOM</button>
            </div>
        </div>
    </div>
</div>

<script>
let rowIndex = <?= count($bom_items) ?>;
let editTargetRow = null;

function autoFillProductName(select) {
    const selectedOption = select.options[select.selectedIndex];
    const productName = selectedOption.getAttribute('data-name');
    const categoryId = selectedOption.getAttribute('data-category-id');
    
    document.getElementById('product_name_display').value = productName || '';
    
    if (categoryId) {
        document.getElementById('category_id_select').value = categoryId;
    }
}

function addRow() {
    editTargetRow = null;
    $('#materialModalTitle').text('Add Material');
    $('#material_selector').val('').trigger('change');
    $('#material_qty').val(1);
    $('#material_cost').val('0.00');
    $('#materialModal').modal('show');
}

function editRow(btn) {
    const row = $(btn).closest('tr');
    editTargetRow = row;
    
    const itemId = row.find('.item-id').val();
    const qty = row.find('.req-qty').val();
    const cost = row.find('.unit-cost').val();
    const warehouseId = row.find('.wh-id').val();
    
    $('#materialModalTitle').text('Edit Material');
    $('#material_selector').val(itemId).trigger('change');
    $('#material_qty').val(qty);
    $('#material_cost').val(cost);
    $('#material_warehouse').val(warehouseId);
    
    $('#materialModal').modal('show');
}

function confirmAddMaterial() {
    const itemId = $('#material_selector').val();
    const qty = parseFloat($('#material_qty').val()) || 1;
    let cost = parseFloat($('#material_cost').val()) || 0;
    const warehouseId = $('#material_warehouse').val();
    
    if (!itemId) {
        alert("Please select a material first.");
        return;
    }

    // Only fetch details if we changed material or adding new.
    // To simplify, just fetch every time to render row accurately.
    fetch('bom_ajax.php?action=get_item_details&item_id=' + itemId)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(res => {
            if (res.success) {
                const item = res.data;
                // If user didn't manually set a cost (0.00) and we are adding, use default cost
                if (cost === 0 && !editTargetRow) {
                    cost = item.cost_price || 0;
                }

                // Prepare warehouse options string
                let whOptions = '';
                const whSelect = document.getElementById('material_warehouse');
                for (let i = 0; i < whSelect.options.length; i++) {
                    const opt = whSelect.options[i];
                    whOptions += `<option value="${opt.value}" ${opt.value == warehouseId ? 'selected' : ''}>${opt.text}</option>`;
                }

                const html = `
                    <tr class="material-row">
                        <td>
                            <input type="hidden" name="items[${rowIndex}][item_id]" value="${item.id}" class="item-id">
                            <div class="fw-bold">${item.code || ''}</div>
                            <small class="text-muted">${item.name || ''}</small>
                        </td>
                        <td>${item.category_name || '-'}</td>
                        <td>
                            <select class="form-select form-select-sm bg-dark border-secondary wh-id" name="items[${rowIndex}][warehouse_id]">
                                ${whOptions}
                            </select>
                        </td>
                        <td>
                            <input type="number" class="form-control form-control-sm bg-dark border-secondary req-qty" 
                                   name="items[${rowIndex}][quantity_required]" value="${qty}" 
                                   step="0.0001" onchange="updateRowTotal(this)" readonly>
                        </td>
                        <td>${item.unit || 'pcs'}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm bg-dark border-secondary unit-cost" 
                                   name="items[${rowIndex}][unit_cost]" value="${cost}" 
                                   step="0.01" onchange="updateRowTotal(this)" readonly>
                        </td>
                        <td class="row-total text-info fw-bold">${(qty * cost).toFixed(2)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info me-1" onclick="editRow(this)" title="Edit Material">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" title="Delete Material">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
                
                if (editTargetRow) {
                    // Update existing row
                    editTargetRow.replaceWith(html);
                } else {
                    // Append new row
                    $('#no-items-row').hide();
                    $('#materialTable tbody').append(html);
                    rowIndex++;
                }
                
                $('#materialModal').modal('hide');
                updateSummary();
            } else {
                alert("Error: " + res.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Failed to fetch item details. " + err.message);
        });
}

function removeRow(btn) {
    $(btn).closest('tr').remove();
    if ($('#materialTable tbody tr.material-row').length === 0) {
        $('#no-items-row').show();
    }
    updateSummary();
}

function updateRowTotal(el) {
    const row = $(el).closest('tr');
    const qty = parseFloat(row.find('.req-qty').val()) || 0;
    const cost = parseFloat(row.find('.unit-cost').val()) || 0;
    row.find('.row-total').text((qty * cost).toFixed(2));
    updateSummary();
}

function updateSummary() {
    let materialTotal = 0;
    $('#materialTable tbody tr.material-row').each(function() {
        const qty = parseFloat($(this).find('.req-qty').val()) || 0;
        const cost = parseFloat($(this).find('.unit-cost').val()) || 0;
        materialTotal += (qty * cost);
    });

    let additionalTotal = 0;
    $('.add-cost').each(function() {
        additionalTotal += (parseFloat($(this).val()) || 0);
    });

    const total = materialTotal + additionalTotal;

    $('#totalMaterialLabel').text('$ ' + materialTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#additionalCostLabel').text('$ ' + additionalTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#totalAdditionalLabel').text('$ ' + additionalTotal.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#totalProductionLabel').text('$ ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
    $('#costPerUnitLabel').text('$ ' + total.toLocaleString(undefined, {minimumFractionDigits: 2}));
}

function checkAvailability() {
    $('#mat_avail_val, #prod_ready_val').html('<div class="spinner-border spinner-border-sm text-secondary" role="status"></div>');
    $('#mat_short_val').text('-');
    $('#availability_example').hide();
    $('#availability_blocked').hide();
    
    // In a real scenario, we'd send the current UI data or the saved BOM ID
    const bomId = $('#bom_id').val();
    if (!bomId) {
        $('#mat_avail_val, #prod_ready_val').html('<span class="text-warning fw-bold">Save BOM First</span>');
        return;
    }

    fetch('bom_ajax.php?action=check_availability&bom_id=' + bomId)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                const data = res.data;
                const isAvail = data.is_available ? 'Yes' : 'No';
                const textColor = data.is_available ? 'text-success' : 'text-danger';
                
                $('#mat_avail_val').html(`<span class="fw-bold ${textColor}">${isAvail}</span>`);
                $('#prod_ready_val').html(`<span class="fw-bold ${textColor}">${isAvail}</span>`);
                
                if (data.shortage_items && data.shortage_items.length > 0) {
                    const shortList = data.shortage_items.map(item => item.name).join(', ');
                    $('#mat_short_val').text(shortList);
                } else {
                    $('#mat_short_val').text('None');
                }

                if (data.all_items && data.all_items.length > 0) {
                    let exampleHtml = '';
                    data.all_items.forEach(item => {
                        exampleHtml += `<div>${item.name} &mdash; ${item.status}</div>`;
                    });
                    $('#availability_example').html(exampleHtml).show();
                }

                if (!data.is_available) {
                    $('#availability_blocked').show();
                }
            }
        });
}

function saveBOM() {
    const form = document.getElementById('bomForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const formData = new FormData(form);
    
    fetch('save_bom_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            window.location.href = 'bom_management.php?id=' + res.id;
        } else {
            alert('Error: ' + res.message);
        }
    })
    .catch(err => {
        alert('Server Error: ' + err.message);
    });
}

$(document).ready(function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });
    
    $('.select2-material').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#materialModal')
    });

    updateSummary();
    if ($('#bom_id').val()) {
        checkAvailability();
    } else {
        $('#availabilityStatus').html('<small class="text-muted">Save to enable stock check</small>');
    }

    if ($.fn.DataTable) {
        const historyTable = $('#historyTable').DataTable({
            pageLength: 10,
            lengthChange: false,
            info: true,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"p>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            order: [[6, 'desc']] // Sort by Date Created desc
        });

        $('#filterProduct').on('keyup', function() {
            historyTable.column(1).search(this.value).draw();
        });
        
        $('#filterCategory').on('change', function() {
            historyTable.column(2).search(this.value ? '^'+this.value+'$' : '', true, false).draw();
        });
        
        $('#filterCapacity').on('change', function() {
            historyTable.column(3).search(this.value ? '^'+this.value+'$' : '', true, false).draw();
        });
        
        $('#filterDate').on('change', function() {
            historyTable.column(6).search(this.value).draw();
        });
        
        $('#filterStatus').on('change', function() {
            historyTable.column(7).search(this.value ? '^'+this.value+'$' : '', true, false).draw();
        });

        window.resetFilters = function() {
            $('.filter-input').val('');
            historyTable.search('').columns().search('').draw();
        };

        // Fix column sizing when modal opens
        $('#historyModal').on('shown.bs.modal', function() {
            historyTable.columns.adjust();
        });
    }
});
</script>

<style>
.card { border-radius: 12px; overflow: hidden; }
.form-label { text-transform: uppercase; letter-spacing: 0.5px; }
.input-group-text { min-width: 40px; justify-content: center; }
.badge { font-weight: 600; }
.dropdown-item { cursor: pointer; }
.table thead th { font-size: 0.75rem; text-transform: uppercase; color: #888; border-bottom: 2px solid #333; }
.material-row td { vertical-align: middle; border-bottom: 1px solid #333; }
.select2-container--default .select2-selection--single { background-color: #1a1a1a!important; border-color: #333!important; color: #fff!important; height: 38px!important; }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
