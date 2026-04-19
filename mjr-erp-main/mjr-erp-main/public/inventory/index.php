<?php
/**
 * Inventory Management - Main Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Inventory Management - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// Get search and filter parameters
$search = get_param('search', '');
$category = get_param('category', '');
$page_num = max(1, intval(get_param('page', 1)));
$per_page = 20;

// Get categories for filter
$categories = db_fetch_all("SELECT DISTINCT c.name FROM categories c JOIN inventory_items i ON c.id = i.category_id WHERE c.is_active = 1 AND i.company_id = ? ORDER BY c.name", [$company_id]);

// Build query
$where_conditions = ["i.is_active = 1", "i.company_id = ?"];
$params = [$company_id];

if (!empty($search)) {
    $where_conditions[] = "(i.code LIKE ? OR i.name LIKE ? OR i.barcode LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($category)) {
    $where_conditions[] = "c.name = ?";
    $params[] = $category;
}

$where_sql = implode(' AND ', $where_conditions);

$selected_category_id = '';
if (!empty($category)) {
    $selected_category = db_fetch(
        "SELECT id FROM categories WHERE name = ? AND is_active = 1 LIMIT 1",
        [$category]
    );
    $selected_category_id = $selected_category['id'] ?? '';
}

$csv_export_params = [];
if (!empty($search)) {
    $csv_export_params['search'] = $search;
}
if (!empty($category)) {
    $csv_export_params['category'] = $category;
}
$csv_export_query = http_build_query($csv_export_params);
$csv_export_url = '../inventory/export_csv_simple.php' . ($csv_export_query ? '?' . $csv_export_query : '');

$report_export_params = [];
if (!empty($search)) {
    $report_export_params['search'] = $search;
}
if (!empty($selected_category_id)) {
    $report_export_params['category_id'] = $selected_category_id;
}
$report_export_suffix = $report_export_params ? '&' . http_build_query($report_export_params) : '';
$excel_export_url = '../inventory/export_inventory.php?format=excel' . $report_export_suffix;
$pdf_export_url = '../inventory/export_inventory.php?format=pdf' . $report_export_suffix;
$word_export_url = '../inventory/export_inventory.php?format=word' . $report_export_suffix;

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM inventory_items i LEFT JOIN categories c ON i.category_id = c.id WHERE {$where_sql}";
$total_items = db_fetch($count_sql, $params)['count'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

// Get items
$sql = "
    SELECT i.*, 
           c.name as category_name, 
           u.code as unit_code,
           s.name as supplier_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    LEFT JOIN suppliers s ON i.supplier_id = s.id
    WHERE {$where_sql}
    ORDER BY i.code
    LIMIT {$per_page} OFFSET {$offset}
";

$items = db_fetch_all($sql, $params);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-boxes me-3"></i>Inventory </h1>
            <p class="text-muted mb-0">Showing items for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <?php if (has_permission('manage_inventory')): ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-plus me-2"></i>Add
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="create.php">
                        <i class="fas fa-box me-2"></i>Add Item
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/add_transaction.php">
                        <i class="fas fa-exchange-alt me-2"></i>Add Transaction
                    </a></li>
                </ul>
            </div>
            <?php endif; ?>
            <div class="btn-group me-2">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= escape_html($csv_export_url) ?>">
                        <i class="fas fa-file-csv me-2 text-success"></i>Export to CSV
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($excel_export_url) ?>">
                        <i class="fas fa-file-excel me-2 text-success"></i>Export to Excel
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($pdf_export_url) ?>">
                        <i class="fas fa-file-pdf me-2 text-danger"></i>Export to PDF
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($word_export_url) ?>">
                        <i class="fas fa-file-word me-2 text-primary"></i>Export to Word
                    </a></li>
                </ul>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-tools me-2"></i>Tools
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="stock_levels.php">
                        <i class="fas fa-boxes me-2"></i>Stock Levels
                    </a></li>
                    <li><a class="dropdown-item" href="transactions.php">
                        <i class="fas fa-history me-2"></i>Transaction Report
                    </a></li>
                    <li><a class="dropdown-item" href="locations.php">
                        <i class="fas fa-map-marker-alt me-2"></i>Locations
                    </a></li>
                    <li><a class="dropdown-item" href="categories.php">
                        <i class="fas fa-tags me-2"></i>Categories
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Advanced Tools</h6></li>
                    <li><a class="dropdown-item" href="../inventory/reorder_check.php">
                        <i class="fas fa-exclamation-triangle me-2"></i>Reorder Check
                    </a></li>
                    <li><a class="dropdown-item" href="reorder.php">
                        <i class="fas fa-clipboard-list me-2"></i>Reorder Report
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/abc_analysis.php">
                        <i class="fas fa-chart-pie me-2"></i>ABC Analysis
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/inventory_valuation.php">
                        <i class="fas fa-dollar-sign me-2"></i>Inventory Valuation
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/supplier/suppliers.php">
                        <i class="fas fa-truck me-2"></i>Suppliers
                    </a></li>

                    <li><a class="dropdown-item" href="../inventory/purchase_order/purchase_orders.php">
                        <i class="fas fa-file-invoice me-2"></i>Purchase Orders
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/modules/warehouses/index.php">
                        <i class="fas fa-file-invoice me-2"></i>warehouses
                    </a></li>
                    <li><a class="dropdown-item" href="../inventory/stock_counting/index.php">
                        <i class="fas fa-clipboard-check me-2"></i>Stock Counting
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search Items</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= escape_html($search) ?>" placeholder="Search by name or code...">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= escape_html($cat['name']) ?>" <?= $cat['name'] == $category ? 'selected' : '' ?>>
                            <?= escape_html($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Inventory Items Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Inventory Items</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($items)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>UOM</th>
                            <th>Supplier Cost Price</th>
                            <th>Average Unit Price</th>
                            <th>Selling Price</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= escape_html($item['code']) ?></strong></td>
                            <td><?= escape_html($item['name']) ?></td>
                            <td>
                                <?php if ($item['category_name']): ?>
                                    <span class="badge bg-secondary"><?= escape_html($item['category_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= escape_html($item['supplier_name'] ?? '-') ?></td>
                            <td><?= escape_html($item['unit_code']) ?></td>
                            <td><?= format_currency($item['cost_price']) ?></td>
                            <td><?= format_currency($item['average_cost']) ?></td>
                            <td><?= format_currency($item['selling_price']) ?></td>
                            <td><?= format_number($item['reorder_level'], 0) ?></td>
                            <td>
                                <?php if ($item['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="reorder.php?add_item=<?= urlencode($item['code']) ?>" 
                                       class="btn btn-outline-info" title="Reorder Report">
                                        <i class="fas fa-clipboard-list"></i>
                                    </a>
                                    <?php if (has_permission('manage_inventory')): ?>
                                    <a href="edit.php?id=<?= $item['id'] ?>" 
                                       class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete.php?id=<?= $item['id'] ?>" 
                                       class="btn btn-outline-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this item?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page_num): ?>
                            <li class="page-item active">
                                <span class="page-link"><?= $i ?></span>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>"><?= $i ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">No inventory items found</p>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Item
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#inventoryTable').DataTable({
        'paging': false,
        'info': false,
        'ordering': true
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>





