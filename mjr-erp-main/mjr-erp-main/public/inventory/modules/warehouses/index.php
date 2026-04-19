<?php
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_login();

// Create bin location for a specific warehouse (different bin per warehouse)
if (is_post() && post('action') === 'create_warehouse_bin') {
    $warehouse_id = intval(post('warehouse_id'));
    $bin_code = trim((string)post('bin_code'));

    if ($warehouse_id <= 0 || $bin_code === '') {
        set_flash('Warehouse and bin code are required.', 'error');
        redirect('index.php');
    }

    $warehouse_exists = db_fetch("SELECT id, name FROM warehouses WHERE id = ? LIMIT 1", [$warehouse_id]);
    if (!$warehouse_exists) {
        set_flash('Invalid warehouse selected.', 'error');
        redirect('index.php');
    }

    $exists = db_fetch("SELECT id FROM bins WHERE warehouse_id = ? AND code = ? LIMIT 1", [$warehouse_id, $bin_code]);
    if ($exists) {
        db_query("UPDATE bins SET is_active = 1 WHERE id = ?", [$exists['id']]);
        set_flash('Bin "' . escape_html($bin_code) . '" already existed and is now active.', 'info');
        redirect('index.php');
    }

    db_query("INSERT INTO bins (warehouse_id, code, is_active) VALUES (?, ?, 1)", [$warehouse_id, $bin_code]);
    set_flash('Bin "' . escape_html($bin_code) . '" created for warehouse "' . escape_html($warehouse_exists['name']) . '".', 'success');
    redirect('index.php');
}

// Auto-sync: ensure every warehouse location has a warehouses row
$missing_locations = db_fetch_all("
    SELECT l.id, l.name, l.address, l.company_id, l.is_active
    FROM locations l
    LEFT JOIN warehouses w ON w.location_id = l.id
    WHERE l.type = 'warehouse' AND w.id IS NULL
");

foreach ($missing_locations as $loc) {
    db_query("
        INSERT INTO warehouses (name, manager_name, capacity, location, location_id, company_id, is_active)
        VALUES (?, '', 0, ?, ?, ?, ?)
    ", [
        $loc['name'],
        $loc['address'],
        $loc['id'],
        $loc['company_id'] ?: ($_SESSION['company_id'] ?? 1),
        $loc['is_active'] ?? 1
    ]);
}

// Ensure existing warehouses also have bin locations.
// 1) Always have a default "General" bin.
// 2) Bring over distinct bin locations from inventory_stock_levels for the mapped location.
$warehouse_bin_sync = db_fetch_all("
    SELECT w.id AS warehouse_id, w.location_id
    FROM warehouses w
    JOIN locations l ON l.id = w.location_id
    WHERE l.type = 'warehouse'
");

foreach ($warehouse_bin_sync as $row) {
    $warehouse_id = intval($row['warehouse_id']);
    $location_id = intval($row['location_id']);

    $has_general = db_fetch("SELECT id FROM bins WHERE warehouse_id = ? AND code = 'General' LIMIT 1", [$warehouse_id]);
    if (!$has_general) {
        db_query("INSERT INTO bins (warehouse_id, code, is_active) VALUES (?, 'General', 1)", [$warehouse_id]);
    }

    $loc_bins = db_fetch_all("
        SELECT DISTINCT TRIM(bin_location) AS bin_code
        FROM inventory_stock_levels
        WHERE location_id = ?
          AND bin_location IS NOT NULL
          AND TRIM(bin_location) <> ''
    ", [$location_id]);

    foreach ($loc_bins as $bin) {
        $code = trim((string)$bin['bin_code']);
        if ($code === '') {
            continue;
        }
        $exists = db_fetch("SELECT id FROM bins WHERE warehouse_id = ? AND code = ? LIMIT 1", [$warehouse_id, $code]);
        if (!$exists) {
            db_query("INSERT INTO bins (warehouse_id, code, is_active) VALUES (?, ?, 1)", [$warehouse_id, $code]);
        }
    }
}

// Show all real warehouses present in the project.
// Primary source: locations where type = 'warehouse'
// Optional enrichment: warehouses table (manager/capacity/details)
$warehouses = db_fetch_all("
    SELECT
        COALESCE(w.id, 0) AS warehouse_id,
        l.id AS location_id,
        COALESCE(NULLIF(w.name, ''), l.name) AS warehouse_name,
        COALESCE(NULLIF(w.location, ''), NULLIF(l.address, ''), l.name) AS warehouse_location,
        COALESCE(NULLIF(w.manager_name, ''), 'Not Assigned') AS manager_name,
        COALESCE(w.capacity, 0) AS capacity,
        COALESCE(w.is_active, l.is_active, 1) AS is_active,
        l.code AS location_code,
        (SELECT COUNT(*) FROM bins b WHERE b.warehouse_id = w.id AND COALESCE(b.is_active, 1) = 1) AS bin_count
    FROM locations l
    LEFT JOIN warehouses w ON w.location_id = l.id
    WHERE l.type = 'warehouse' 
      AND COALESCE(w.is_active, l.is_active, 1) = 1
      " . db_where_company('l') . "
    ORDER BY COALESCE(NULLIF(w.name, ''), l.name) ASC
");

$page_title = 'Warehouse Management - MJR Group';
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-warehouse me-2"></i>Warehousing &amp; Storage</h1>
        <a href="create.php" class="btn btn-success">
            <i class="fas fa-plus-circle me-2"></i>Add New Warehouse
        </a>
    </div>

    <?php if (get_param('msg') === 'success'): ?>
        <div class="alert alert-success">Warehouse saved successfully.</div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Warehouse Name</th>
                            <th>Location</th>
                            <th>Manager</th>
                            <th>Capacity (sq.ft)</th>
                            <th class="text-center">Bins</th>
                            <th>Create Bin</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($warehouses)): ?>
                            <?php foreach ($warehouses as $wh): ?>
                                <?php $has_warehouse_row = intval($wh['warehouse_id']) > 0; ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary-subtle text-primary p-2 rounded me-3">
                                                <i class="fas fa-warehouse"></i>
                                            </div>
                                            <div>
                                                <strong><?= escape_html($wh['warehouse_name']) ?></strong>
                                                <div class="small text-muted"><?= escape_html($wh['location_code'] ?: 'N/A') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?= escape_html($wh['warehouse_location'] ?: 'N/A') ?>
                                        </small>
                                    </td>
                                    <td><?= escape_html($wh['manager_name']) ?></td>
                                    <td><?= number_format(floatval($wh['capacity'])) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info border border-info">
                                            <?= number_format(intval($wh['bin_count'] ?? 0)) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($has_warehouse_row): ?>
                                            <form method="POST" class="d-flex gap-2">
                                                <input type="hidden" name="action" value="create_warehouse_bin">
                                                <input type="hidden" name="warehouse_id" value="<?= intval($wh['warehouse_id']) ?>">
                                                <input type="text" name="bin_code" class="form-control form-control-sm" placeholder="e.g. A-01" required>
                                                <button type="submit" class="btn btn-sm btn-primary">Add</button>
                                            </form>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (intval($wh['is_active']) === 1): ?>
                                            <span class="badge rounded-pill bg-success-subtle text-success border border-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <?php if ($has_warehouse_row): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="inventory.php?id=<?= intval($wh['warehouse_id']) ?>" class="btn btn-outline-primary" title="WMS Inventory">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
                                                <a href="edit.php?id=<?= intval($wh['warehouse_id']) ?>" class="btn btn-outline-secondary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" onclick="deleteWarehouse(<?= intval($wh['warehouse_id']) ?>)" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Sync Needed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                                    No warehouses found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function deleteWarehouse(id) {
    if (confirm('Are you sure? Removing a warehouse will affect linked inventory records.')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>
