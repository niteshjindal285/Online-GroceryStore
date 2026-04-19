<?php
/**
 * Price Change History - List of all price change requests
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Filters handling
$status_filter = get_param('status', '');
$category_filter = get_param('category', '');
$search_filter = get_param('search', '');

$where = ["1=1"];
$company_id = active_company_id();
if ($company_id) {
    $where[] = "h.company_id = " . (int)$company_id;
}
$params = [];

if ($status_filter) {
    $where[] = "h.status = ?";
    $params[] = $status_filter;
}
if ($search_filter) {
    if (strpos($search_filter, 'PC-') === 0) {
        $where[] = "h.pc_number LIKE ?";
        $params[] = "%$search_filter%";
    } else {
        $where[] = "EXISTS (SELECT 1 FROM price_change_items pi JOIN inventory_items ii ON pi.item_id = ii.id WHERE pi.pc_header_id = h.id AND (ii.name LIKE ? OR ii.code LIKE ?))";
        $params[] = "%$search_filter%";
        $params[] = "%$search_filter%";
    }
}

$sql = "
    SELECT h.*, u.username as creator_name, c.name as company_name,
           (SELECT COUNT(*) FROM price_change_items WHERE pc_header_id = h.id) as item_count
    FROM price_change_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN companies c ON h.company_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY h.created_at DESC
";

$price_changes = db_fetch_all($sql, $params);

$page_title = "Price Change History";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-tags me-2 text-info"></i>Price Change History
            </h2>
            <p class="text-secondary small mb-0">Audit previous price updates and their impacts.</p>
        </div>
        <a href="add_price_change.php" class="btn btn-info btn-lg px-4 text-dark fw-bold">
            <i class="fas fa-plus-circle me-2"></i>New Price Change
        </a>
    </div>

    <!-- Filters Panel -->
    <div class="card premium-card mb-4 card-overflow-visible">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" name="search" class="form-control bg-dark text-white border-secondary" 
                               placeholder="Search Ref No or Product..." value="<?= escape_html($search_filter) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Pending Approval" <?= $status_filter === 'Pending Approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-info w-100">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                </div>
                <div class="col-md-3 text-end">
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary">
                            <i class="fas fa-file-export me-1"></i>Export
                        </button>
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark">
                            <li><a class="dropdown-item" href="#" onclick="exportTableToCSV('price_changes.csv'); return false;"><i class="fas fa-file-csv me-2"></i>CSV</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportTableToCSV('price_changes.csv'); return false;"><i class="fas fa-file-excel me-2"></i>Excel</a></li>
                            <li><a class="dropdown-item" href="#" onclick="window.print(); return false;"><i class="fas fa-file-pdf me-2"></i>PDF</a></li>
                        </ul>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card premium-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th class="ps-4">Ref Number</th>
                            <th>Date</th>
                            <th>Effective Date</th>
                            <th>Company</th>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($price_changes)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <div class="opacity-25 mb-3">
                                        <i class="fas fa-tags fa-4x"></i>
                                    </div>
                                    <p class="text-secondary">No price change requests found matching filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($price_changes as $pc): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-info"><?= escape_html($pc['pc_number']) ?></td>
                                    <td class="text-secondary small"><?= format_date($pc['pc_date']) ?></td>
                                    <td class="text-info small"><?= format_date($pc['effective_date']) ?></td>
                                    <td><?= escape_html($pc['company_name']) ?></td>
                                    <td>
                                        <span class="badge bg-dark border border-secondary text-light">
                                            <?= escape_html($pc['price_category']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark fw-bold"><?= $pc['item_count'] ?> Products</span>
                                    </td>
                                    <td>
                                        <span class="text-secondary small">ID: <?= escape_html($pc['creator_name']) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        if ($pc['status'] === 'Approved') $badge_class = 'bg-success';
                                        if ($pc['status'] === 'Pending Approval') $badge_class = 'bg-warning text-dark';
                                        if ($pc['status'] === 'Cancelled') $badge_class = 'bg-danger';
                                        ?>
                                        <span class="badge <?= $badge_class ?> px-3 py-2"><?= escape_html($pc['status']) ?></span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="view_price_change.php?id=<?= $pc['id'] ?>" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <?php if ($pc['status'] === 'Draft'): ?>
                                            <a href="add_price_change.php?id=<?= $pc['id'] ?>" class="btn btn-outline-secondary btn-sm ms-1">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .premium-card {
        background: #1a1a27;
        border: 1px solid #323248;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }
    
    .card-overflow-visible {
        overflow: visible !important;
    }
    
    .table-dark {
        background: transparent !important;
    }
    
    .table-dark thead th {
        background: #212133 !important;
        color: #a2a3b7 !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #323248 !important;
        padding: 15px 20px;
    }
    
    .table-dark tbody td {
        background: transparent !important;
        color: #e1e1e3 !important;
        border-bottom: 1px solid #323248 !important;
        padding: 15px 20px;
    }
    
    .table-hover tbody tr:hover td {
        background: rgba(255, 255, 255, 0.02) !important;
    }
</style>

<script>
function exportTableToCSV(filename) {
    let table = document.querySelector(".table");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) {
            // skip the Actions column (last column)
            if (j === cols.length - 1) continue; 
            row.push('"' + cols[j].innerText.replace(/"/g, '""').trim() + '"');
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
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
