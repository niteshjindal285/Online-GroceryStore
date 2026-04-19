<?php
/**
 * Stock Report - Live Inventory Visibility + Reporting
 * Based on Screen3 specification
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Stock Report - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view the stock report.', 'warning');
    // If admin but no company selected, redirect to dashboard or company list
    redirect(url('index.php'));
}

// Get filters
$filters = [
    'company_id' => $company_id, // Force active company
    'location_id' => get_param('location_id'),
    'bin_id' => get_param('bin_id'),
    'category_id' => get_param('category_id'),
    'search' => get_param('search'),
    'date_from' => to_db_date(get_param('date_from')),
    'date_to' => to_db_date(get_param('date_to')),
    'status' => get_param('status'), // 'in_stock', 'low_stock', 'out_of_stock'
    'min_stock_alert' => get_param('min_stock_alert') // 0 or 1
];

// Fetch dependencies for filters
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$bins = [];
if (!empty($filters['location_id'])) {
    $bins = db_fetch_all("
        SELECT b.id, b.code
        FROM bins b
        JOIN warehouses w ON w.id = b.warehouse_id
        WHERE COALESCE(b.is_active, 1) = 1
          AND w.location_id = ?
        ORDER BY b.code
    ", [(int)$filters['location_id']]);
}

// Build main query
$sql = "
    SELECT 
        ii.id,
        ii.code AS item_code,
        ii.name AS item_name,
        c.name AS category_name,
        l.name AS location_name,
        isl.quantity_on_hand,
        isl.quantity_reserved,
        isl.quantity_available,
        isl.bin_location,
        ii.reorder_level AS min_stock_level,
        ii.cost_price,
        ii.average_cost,
        ii.selling_price,
        (isl.quantity_available * ii.average_cost) AS total_value,
        u.code AS unit_code,
        isl.last_updated
    FROM 
        inventory_items ii
    LEFT JOIN 
        categories c ON ii.category_id = c.id
    LEFT JOIN 
        inventory_stock_levels isl ON ii.id = isl.item_id
    LEFT JOIN 
        locations l ON isl.location_id = l.id
    LEFT JOIN 
        units_of_measure u ON ii.unit_of_measure_id = u.id
    WHERE 
        ii.is_active = 1
        AND l.company_id = ?
";

$params = [$company_id];

// Filters are already applied to $params starting with $company_id
if ($filters['location_id']) {
    $sql .= " AND isl.location_id = ?";
    $params[] = $filters['location_id'];
}
if ($filters['bin_id']) {
    $selected_bin = db_fetch("SELECT code FROM bins WHERE id = ?", [$filters['bin_id']]);
    if ($selected_bin && !empty($selected_bin['code'])) {
        $sql .= " AND isl.bin_location = ?";
        $params[] = $selected_bin['code'];
    }
}
if ($filters['category_id']) {
    $sql .= " AND ii.category_id = ?";
    $params[] = $filters['category_id'];
}
if ($filters['search']) {
    $sql .= " AND (ii.name LIKE ? OR ii.code LIKE ? OR isl.bin_location LIKE ?)";
    $params[] = '%' . $filters['search'] . '%';
    $params[] = '%' . $filters['search'] . '%';
    $params[] = '%' . $filters['search'] . '%';
}

$stock_report_data = db_fetch_all($sql, $params);

// --- Historical Stock Logic ---
// If date_to is set, we adjust live stock back to that date by reversing movements after it.
if (!empty($filters['date_to'])) {
    $historical_date = $filters['date_to'] . ' 23:59:59';
    
    // Fetch all movements that occurred AFTER the requested date
    $movements_after = db_fetch_all("
        SELECT warehouse_id, product_id, movement_type, SUM(quantity) as total_qty
        FROM stock_movements
        WHERE created_at > ?
        GROUP BY warehouse_id, product_id, movement_type
    ", [$historical_date]);
    
    $adjustments = [];
    foreach ($movements_after as $mov) {
        $key = $mov['warehouse_id'] . '_' . $mov['product_id'];
        if (!isset($adjustments[$key])) $adjustments[$key] = 0;
        
        // Reverse the movement:
        // If it was IN (added to stock), we subtract it to go back in time.
        // If it was OUT (removed from stock), we add it back.
        if (strtoupper($mov['movement_type']) === 'IN') {
            $adjustments[$key] -= (float)$mov['total_qty'];
        } else {
            $adjustments[$key] += (float)$mov['total_qty'];
        }
    }
    
    // Apply adjustments to the live data
    foreach ($stock_report_data as &$row) {
        $akey = $row['location_id'] . '_' . $row['id'];
        if (isset($adjustments[$akey])) {
            $row['quantity_available'] = (float)$row['quantity_available'] + $adjustments[$akey];
            // Recalculate value based on adjusted quantity
            $row['total_value'] = (float)$row['quantity_available'] * (float)$row['average_cost'];
        }
    }
    unset($row);
}
// -----------------------------

// Post-filter logic for dynamic statuses
$filtered_data = [];
$stats = [
    'total_value' => 0,
    'total_skus' => 0,
    'total_qty' => 0,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0
];

foreach ($stock_report_data as &$row) {
    $qty = floatval($row['quantity_available']);
    $min = floatval($row['min_stock_level']);
    
    $status = 'in_stock';
    if ($qty <= 0) {
        $status = 'out_of_stock';
        $stats['out_of_stock_count']++;
    } elseif ($qty <= $min) {
        $status = 'low_stock';
        $stats['low_stock_count']++;
    }
    
    $row['status'] = $status;
    
    // Apply status filter if set
    if ($filters['status'] && $filters['status'] !== $status) continue;
    
    // Apply min stock alert filter
    if ($filters['min_stock_alert'] && $status !== 'low_stock' && $status !== 'out_of_stock') continue;
    
    $filtered_data[] = $row;
    
    // Update aggregate stats
    $stats['total_value'] += floatval($row['total_value']);
    $stats['total_qty'] += $qty;
}
unset($row);

$stats['total_skus'] = count(array_unique(array_column($filtered_data, 'id')));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-1">Live Stock Report</h2>
            <p class="text-secondary mb-0">Real-time inventory visibility and movement analysis</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info btn-sm" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
            <div class="dropdown">
                <button class="btn btn-info btn-sm text-dark fw-bold dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" onclick="exportTableToCSV('stock_report.csv'); return false;"><i class="fas fa-file-excel text-success me-2"></i>Export to Excel (CSV)</a></li>
                    <li><a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="fas fa-file-pdf text-danger me-2"></i>Export to PDF</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div class="card premium-card mb-4">
        <div class="card-header bg-transparent border-0 py-3">
            <h5 class="mb-0 text-info fw-bold"><i class="fas fa-filter me-2"></i>Stock Report Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Company</label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" value="<?= escape_html(active_company_name()) ?>" readonly>
                    <input type="hidden" name="company_id" value="<?= (int)$company_id ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Warehouse / Location</label>
                    <select name="location_id" id="location_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Locations</option>
                        <?php foreach($locations as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $filters['location_id'] == $l['id'] ? 'selected' : '' ?>><?= escape_html($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Product Category</label>
                    <select name="category_id" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filters['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= escape_html($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Bin Location</label>
                    <select name="bin_id" id="bin_id" class="form-select bg-dark text-white border-secondary">
                        <option value=""><?= !empty($filters['location_id']) ? 'All Bins' : 'Select Warehouse First' ?></option>
                        <?php foreach($bins as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $filters['bin_id'] == $b['id'] ? 'selected' : '' ?>><?= escape_html($b['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-secondary">Search Item (Code/Name)</label>
                    <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="Enter keyword..." value="<?= escape_html($filters['search']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Date From</label>
                    <input type="text" name="date_from" class="form-control bg-dark text-white border-secondary datepicker" value="<?= !empty($filters['date_from']) ? format_date($filters['date_from']) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Date To</label>
                    <input type="text" name="date_to" class="form-control bg-dark text-white border-secondary datepicker" value="<?= !empty($filters['date_to']) ? format_date($filters['date_to']) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-secondary">Stock Status</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="in_stock" <?= $filters['status'] == 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                        <option value="low_stock" <?= $filters['status'] == 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="out_of_stock" <?= $filters['status'] == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="min_stock_alert" value="1" id="minStockAlert" <?= $filters['min_stock_alert'] ? 'checked' : '' ?>>
                        <label class="form-check-label text-secondary small" for="minStockAlert">Min. Stock Alert</label>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold">Generate Report</button>
                    <a href="stock_report.php" class="btn btn-outline-secondary w-50">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card premium-card h-100 text-center py-4 border-start border-4 border-info">
                <h6 class="text-secondary small text-uppercase mb-2">Total Inventory Value</h6>
                <h3 class="text-info fw-bold mb-0"><?= format_currency($stats['total_value']) ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card premium-card h-100 text-center py-4 border-start border-4 border-primary">
                <h6 class="text-secondary small text-uppercase mb-2">Total Products</h6>
                <h3 class="text-primary fw-bold mb-0"><?= number_format($stats['total_skus']) ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card premium-card h-100 text-center py-4 border-start border-4 border-light">
                <h6 class="text-secondary small text-uppercase mb-2">Total Quantity</h6>
                <h3 class="text-white fw-bold mb-0"><?= number_format($stats['total_qty']) ?></h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card premium-card h-100 text-center py-4 border-start border-4 border-warning">
                <h6 class="text-secondary small text-uppercase mb-2">Low Stock SKUs</h6>
                <h3 class="text-warning fw-bold mb-0"><?= number_format($stats['low_stock_count']) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card premium-card h-100 text-center py-4 border-start border-4 border-danger">
                <h6 class="text-secondary small text-uppercase mb-2">Out of Stock</h6>
                <h3 class="text-danger fw-bold mb-0"><?= number_format($stats['out_of_stock_count']) ?></h3>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="card premium-card">
        <div class="card-header bg-transparent border-0 py-3">
            <h5 class="mb-0 text-white fw-bold font-heading">Stock Movement Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0" id="stockReportTable">
                    <thead>
                        <tr>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Warehouse</th>
                            <th>Bin</th>
                            <th class="text-end">Supplier Cost</th>
                            <th class="text-end">Avg Cost</th>
                            <th class="text-end">Selling Price</th>
                            <th class="text-end">Qty Available</th>
                            <th class="text-end">Total Value</th>
                            <th class="text-center">Min. Stock</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($filtered_data as $row): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= escape_html($row['item_code']) ?></span></td>
                                <td class="fw-bold"><?= escape_html($row['item_name']) ?></td>
                                <td><small class="text-secondary"><?= escape_html($row['category_name'] ?: 'N/A') ?></small></td>
                                <td><small class="text-secondary"><?= escape_html($row['location_name']) ?></small></td>
                                <td><span class="badge bg-outline-secondary border border-secondary text-white-50"><?= escape_html($row['bin_location'] ?: 'N/A') ?></span></td>
                                <td class="text-end"><?= format_currency($row['cost_price']) ?></td>
                                <td class="text-end text-info"><?= format_currency($row['average_cost']) ?></td>
                                <td class="text-end text-success"><?= format_currency($row['selling_price']) ?></td>
                                <td class="text-end"><?= number_format($row['quantity_available'] ?? 0) ?> <small class="text-muted"><?= escape_html($row['unit_code'] ?? '') ?></small></td>
                                <td class="text-end text-info fw-bold"><?= format_currency($row['total_value']) ?></td>
                                <td class="text-center"><span class="text-secondary"><?= number_format($row['min_stock_level'] ?? 0) ?></span></td>
                                <td class="text-center">
                                    <?php if($row['status'] == 'out_of_stock'): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif($row['status'] == 'low_stock'): ?>
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-outline-info view-history" data-id="<?= $row['id'] ?>" title="View Stock History">
                                        <i class="fas fa-history me-1"></i> History
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Stock History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold text-info"><i class="fas fa-history me-2"></i>Product Stock History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-info" role="status"></div>
                        <p class="mt-2">Loading transactions...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .premium-card {
        background: rgba(45, 45, 60, 0.6);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
    }
    .table-dark {
        --bs-table-bg: transparent;
        --bs-table-hover-bg: rgba(255, 255, 255, 0.05);
    }
    thead th {
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #7d8da1;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        padding: 15px !important;
    }
    tbody td {
        padding: 15px !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    @media print {
        .premium-card { box-shadow: none; border: 1px solid #ccc; background: white !important; color: black !important; }
        .navbar, .btn, .filter-panel, label, .form-check-label { display: none !important; }
        body { background: white !important; color: black !important; }
        .text-white, .text-info, .text-primary { color: black !important; }
        .card-header { border-bottom: 2px solid #333 !important; }
    }
</style>

<script>
function exportTableToCSV(filename) {
    let table = $('#stockReportTable').DataTable();
    let data = table.rows({search: 'applied'}).data();
    let headers = table.columns().header().toArray().map(h => $(h).text().trim());
    
    // Remove last column "Actions" if desired. For stock report it's "Actions"
    let actionIdx = headers.indexOf('Actions');
    if(actionIdx > -1) {
        headers.splice(actionIdx, 1);
    }
    
    let csv = [];
    csv.push(headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(","));
    
    for (let i = 0; i < data.length; i++) {
        let rowData = data[i];
        let row = [];
        for (let j = 0; j < headers.length + (actionIdx > -1 ? 1 : 0); j++) {
            if (j === actionIdx) continue;
            let text = $('<div>' + rowData[j] + '</div>').text().trim();
            row.push('"' + text.replace(/"/g, '""') + '"');
        }
        csv.push(row.join(","));
    }
    
    let csvContent = "data:text/csv;charset=utf-8,\uFEFF" + encodeURIComponent(csv.join("\n"));
    let link = document.createElement("a");
    link.setAttribute("href", csvContent);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

$(document).ready(function() {
    const locationSelect = document.getElementById('location_id');
    const binSelect = document.getElementById('bin_id');
    const selectedBinId = '<?= escape_html((string)$filters['bin_id']) ?>';

    function loadBinsByLocation(locationId, preserveSelected = false) {
        if (!binSelect) return;
        binSelect.innerHTML = '';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        if (!locationId) {
            defaultOption.textContent = 'Select Warehouse First';
            binSelect.appendChild(defaultOption);
            return;
        }

        defaultOption.textContent = 'All Bins';
        binSelect.appendChild(defaultOption);

        fetch('ajax_get_bins.php?location_id=' + encodeURIComponent(locationId))
            .then(r => r.json())
            .then(data => {
                const bins = Array.isArray(data) ? data : [];
                bins.forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.bin_id;
                    option.textContent = b.bin_location;
                    if (preserveSelected && selectedBinId && String(selectedBinId) === String(b.bin_id)) {
                        option.selected = true;
                    }
                    binSelect.appendChild(option);
                });
            })
            .catch(() => {
                // keep default option
            });
    }

    if (locationSelect && binSelect) {
        loadBinsByLocation(locationSelect.value, true);
        locationSelect.addEventListener('change', function() {
            loadBinsByLocation(this.value, false);
        });
    }

    $('#stockReportTable').DataTable({
        pageLength: 10,
        order: [[6, 'desc']], // Default sort by Total Value
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search report..."
        },
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rtip'
    });

    $(document).on('click', '.view-history', function() {
        const itemId = $(this).data('id');
        $('#historyModal').modal('show');
        $('#historyContent').load('ajax_stock_history.php?id=' + itemId + '&_=' + Date.now());
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
