<?php
/**
 * Inventory - Reorder Check
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Reorder Check - MJR Group ERP';

// Get items below reorder level — ONE ROW PER PRODUCT PER WAREHOUSE
$low_stock_items = db_fetch_all("
    SELECT src.item_code AS code,
           src.item_name AS name,
           src.category_name,
           src.location_label,
           src.qty_available AS total_available,
           src.reorder_level,
           src.reorder_quantity,
           src.unit_code
    FROM (
        -- Source 1: stock tracked in inventory_stock_levels (per location)
        SELECT i.id AS item_id,
               i.code AS item_code,
               i.name AS item_name,
               c.name AS category_name,
               l.name AS location_label,
               COALESCE(s.quantity_available, 0) AS qty_available,
               i.reorder_level,
               i.reorder_quantity,
               COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
        FROM inventory_stock_levels s
        JOIN inventory_items i ON i.id = s.item_id
        LEFT JOIN categories c ON c.id = i.category_id
        JOIN locations l ON l.id = s.location_id
        LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
        WHERE i.is_active = 1 AND i.reorder_level > 0

        UNION ALL

        -- Source 2: stock only in warehouse_inventory (no linked stock_levels record)
        SELECT i.id AS item_id,
               i.code AS item_code,
               i.name AS item_name,
               c.name AS category_name,
               CONCAT(w.name, ' (Warehouse)') AS location_label,
               SUM(wi.quantity) AS qty_available,
               i.reorder_level,
               i.reorder_quantity,
               COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
        FROM warehouse_inventory wi
        JOIN inventory_items i ON i.id = wi.product_id
        LEFT JOIN categories c ON c.id = i.category_id
        JOIN warehouses w ON w.id = wi.warehouse_id
        LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
        WHERE i.is_active = 1 AND i.reorder_level > 0
          AND (
              w.location_id IS NULL
              OR NOT EXISTS (
                  SELECT 1 FROM inventory_stock_levels s2
                  WHERE s2.item_id = wi.product_id AND s2.location_id = w.location_id
              )
          )
        GROUP BY i.id, i.code, i.name, c.name, w.id, w.name, i.reorder_level, i.reorder_quantity, u.code, i.unit_of_measure
    ) src
    WHERE src.qty_available < src.reorder_level
    ORDER BY (src.qty_available / src.reorder_level) ASC
");


// -----------------------------------------------------------------------
// Diagnostic: Warehouse products with NO reorder_level configured
// These items are invisible to the reorder system until reorder_level is set
// -----------------------------------------------------------------------
$no_reorder_level_items = db_fetch_all("
    SELECT DISTINCT i.code, i.name,
           c.name as category_name,
           w.name as warehouse_name,
           SUM(wi.quantity) as warehouse_qty,
           COALESCE(u.code, i.unit_of_measure, 'PCS') as unit_code,
           i.reorder_level
    FROM warehouse_inventory wi
    JOIN inventory_items i ON wi.product_id = i.id
    LEFT JOIN categories c ON c.id = i.category_id
    JOIN warehouses w ON wi.warehouse_id = w.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE i.is_active = 1
      AND (i.reorder_level IS NULL OR i.reorder_level = 0)
    GROUP BY i.id, i.code, i.name, c.name, w.id, w.name, u.code, i.unit_of_measure, i.reorder_level
    ORDER BY w.name, i.name
");

// Build a unique list of items for the filter
$unique_items = [];
$all_items = array_merge($low_stock_items ?? [], $no_reorder_level_items ?? []);
foreach ($all_items as $row) {
    if (!empty($row['name']) && !empty($row['code'])) {
        $unique_items[$row['code']] = $row['name'];
    }
}
asort($unique_items);

$search_param = get_param('search', '');


include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4 align-items-center">
        <div class="col-md-7">
            <h1 class="mb-1"><i class="fas fa-exclamation-triangle me-3"></i>Reorder Point Check</h1>
            <p class="lead mb-0">Items requiring reorder</p>
        </div>
        <div class="col-md-5 text-end">
            <!-- New Item Filter Dropdown -->
            <label for="itemFilter" class="form-label d-none">Select Item</label>
            <select id="itemFilter" class="form-select form-select-sm d-inline-block" style="max-width: 250px;">
                <option value="">All Items</option>
                <?php foreach ($unique_items as $code => $name): ?>
                    <option value="<?= escape_html($code) ?>" <?= ($search_param === $code) ? 'selected' : '' ?>>
                        <?= escape_html($name) ?> (<?= escape_html($code) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <?php if (!empty($low_stock_items)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong><?= count($low_stock_items) ?></strong> item(s) are below their reorder level
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="reorderTable">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Warehouse / Location</th>
                            <th>Stock in Location</th>
                            <th>Reorder Level</th>
                            <th>Reorder Qty</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock_items as $item): ?>
                        <?php 
                            $percentage = ($item['total_available'] / $item['reorder_level']) * 100;
                            if ($percentage < 25) {
                                $priority_class = 'danger';
                                $priority_text = 'Critical';
                            } elseif ($percentage < 50) {
                                $priority_class = 'warning';
                                $priority_text = 'High';
                            } else {
                                $priority_class = 'info';
                                $priority_text = 'Medium';
                            }
                        ?>
                        <tr>
                            <td><strong><?= escape_html($item['code']) ?></strong></td>
                            <td><?= escape_html($item['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= escape_html($item['category_name'] ?: 'Uncategorized') ?></span></td>
                            <td>
                                <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                <small><?= escape_html($item['location_label']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $priority_class ?>">
                                    <?= format_number($item['total_available'], 0) ?> <?= escape_html($item['unit_code']) ?>
                                </span>
                            </td>
                            <td><?= format_number($item['reorder_level'], 0) ?></td>
                            <td><strong><?= format_number($item['reorder_quantity'], 0) ?></strong></td>
                            <td><span class="badge bg-<?= $priority_class ?>"><?= $priority_text ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
            <h3>All Good!</h3>
            <p class="text-muted">All items are above their reorder levels</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($no_reorder_level_items)): ?>
    <div class="card mt-4 border-warning">
        <div class="card-header bg-warning bg-opacity-10">
            <h5 class="mb-0 text-warning">
                <i class="fas fa-exclamation-circle me-2"></i>
                Warehouse Products Without Reorder Level
                <span class="badge bg-warning text-dark ms-2"><?= count($no_reorder_level_items) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning mb-3">
                <i class="fas fa-info-circle me-2"></i>
                The following products exist in your warehouses but have <strong>no reorder level configured</strong>.
                They will <strong>never appear</strong> in reorder reports until you set a reorder level on each item.
                <a href="index.php" class="alert-link ms-2">Go to Inventory Items →</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead class="table-warning">
                        <tr>
                            <th>SKU Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Warehouse</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($no_reorder_level_items as $item): ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?= escape_html($item['code']) ?></span></td>
                            <td><?= escape_html($item['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= escape_html($item['category_name'] ?: 'Uncategorized') ?></span></td>
                            <td><i class="fas fa-warehouse me-1 text-muted"></i><?= escape_html($item['warehouse_name']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= format_number($item['warehouse_qty'], 0) ?> <?= escape_html($item['unit_code']) ?>
                                </span>
                            </td>
                            <td><span class="badge bg-secondary">Not Set</span></td>
                            <td>
                                <a href="index.php?search=<?= urlencode($item['code']) ?>" class="btn btn-sm btn-outline-warning">
                                    <i class="fas fa-edit me-1"></i>Set Reorder Level
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>


<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    const table = $('#reorderTable').DataTable({
        'order': [[7, 'desc']],
        'pageLength': 50
    });

    const initSearch = <?= json_encode($search_param) ?>;
    if (initSearch) {
        table.column(0).search('^' + $.fn.dataTable.util.escapeRegex(initSearch) + '$', true, false).draw();
    }

    // Filter by item map
    $('#itemFilter').on('change', function() {
        const itemCode = $(this).val();
        if (itemCode) {
            // Filter by exact item code in column 0
            table.column(0).search('^' + $.fn.dataTable.util.escapeRegex(itemCode) + '$', true, false).draw();
            
            // If the URL didn't have the param, we can optionally update the URL history
            const url = new URL(window.location.href);
            url.searchParams.set('search', itemCode);
            window.history.replaceState({}, '', url);
        } else {
            table.column(0).search('').draw();
            
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.history.replaceState({}, '', url);
        }
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
