<?php
/**
 * Suppliers List
 * Manage suppliers for purchase orders
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Suppliers - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view suppliers.', 'warning');
    redirect(url('index.php'));
}
$company_name = active_company_name('Current Company');
$has_company_id = suppliers_table_has_company_id();

// Handle delete
if (is_post() && isset($_POST['delete_id'])) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $delete_id = post('delete_id');

            // Check if supplier has purchase orders in the selected company
            $po_sql = "SELECT COUNT(*) as count FROM purchase_orders WHERE supplier_id = ?";
            $po_params = [$delete_id];
            if ($has_company_id) {
                $po_sql .= " AND company_id = ?";
                $po_params[] = $company_id;
            }
            $po_count = db_fetch($po_sql, $po_params);
            
            if ($po_count && $po_count['count'] > 0) {
                throw new Exception('Cannot delete supplier. They have existing purchase orders.');
            }
            
            $delete_sql = "DELETE FROM suppliers WHERE id = ?";
            $delete_params = [$delete_id];
            if ($has_company_id) {
                $delete_sql .= " AND company_id = ?";
                $delete_params[] = $company_id;
            }
            db_query($delete_sql, $delete_params);
            
            set_flash('Supplier deleted successfully!', 'success');
            redirect('suppliers.php');
        } catch (Exception $e) {
            log_error("Error deleting supplier: " . $e->getMessage());
            set_flash($e->getMessage(), 'error');
        }
    }
}

// Get filter parameters
$search = get_param('search', '');
$status_filter = get_param('status', '');

// Build query
$sql = "
    SELECT s.*, c.name as company_name
    FROM suppliers s
    LEFT JOIN companies c ON s.company_id = c.id
    WHERE 1 = 1
";
$params = [];
if ($has_company_id && !is_hq()) {
    $sql .= " AND s.company_id = ?\n";
    $params[] = $company_id;
}

if (!empty($search)) {
    $sql .= " AND (s.supplier_code LIKE ? OR s.name LIKE ? OR s.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($status_filter !== '') {
    $sql .= " AND s.is_active = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY s.name";

$suppliers = db_fetch_all($sql, $params);

// Check if there are unassigned suppliers (company_id = NULL) — shows admin hint
$unassigned_count = 0;
if ($has_company_id) {
    $unassigned_row = db_fetch("SELECT COUNT(*) as cnt FROM suppliers WHERE company_id IS NULL");
    $unassigned_count = (int) ($unassigned_row['cnt'] ?? 0);
}


include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-truck me-2"></i>Suppliers</h2>
            <p class="text-muted mb-0">Showing suppliers for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <?php if (is_hq()): ?>
                <button class="btn btn-secondary" onclick="alert('Suppliers cannot be created at HQ. Please switch to a subsidiary company.')" title="Creation restricted at HQ level">
                    <i class="fas fa-lock me-2"></i>Add Supplier
                </button>
            <?php else: ?>
                <a href="import_supplier.php" class="btn btn-outline-info me-2">
                    <i class="fas fa-search me-2"></i>Import Existing
                </a>
                <a href="add_supplier.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add Supplier
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($unassigned_count > 0 && is_admin()): ?>
    <div class="alert alert-warning border-warning shadow-sm">
        <div class="d-flex align-items-center">
            <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
            <div>
                <strong>Action Required:</strong> There are <strong><?= $unassigned_count ?></strong> suppliers in the system that are not assigned to any company.
                They will not appear in company-specific views until they are assigned.
                <a href="fix_supplier_company.php" class="alert-link text-decoration-underline ms-2">Run the Assignment Tool</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= escape_html($search) ?>" placeholder="Code, name, or email">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Suppliers</option>
                        <option value="1" <?= $status_filter === '1' ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $status_filter === '0' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="suppliers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Suppliers List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Supplier List</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($suppliers)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover" id="suppliersTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Supplier Name</th>
                            <?php if (is_hq()): ?>
                            <th>Company</th>
                            <?php endif; ?>
                            <th>Contact Person</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?= escape_html($supplier['supplier_code']) ?></td>
                            <td>
                                <strong><?= escape_html($supplier['name']) ?></strong>
                            </td>
                            <?php if (is_hq()): ?>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= escape_html($supplier['company_name'] ?: 'Global / Unassigned') ?>
                                </span>
                            </td>
                            <?php endif; ?>
                            <td><?= escape_html($supplier['contact_person'] ?? '-') ?></td>
                            <td><?= escape_html($supplier['email'] ?? '-') ?></td>
                            <td><?= escape_html($supplier['phone'] ?? '-') ?></td>
                            <td>
                                <?php if ($supplier['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_supplier.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_supplier.php?id=<?= $supplier['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            onclick="if(confirm('Are you sure you want to delete this supplier?')) { document.getElementById('delete-form-<?= $supplier['id'] ?>').submit(); }">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <form id="delete-form-<?= $supplier['id'] ?>" method="POST" style="display:none;">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="delete_id" value="<?= $supplier['id'] ?>">
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-truck fa-3x text-muted mb-3"></i>
                <p class="text-muted">No suppliers found</p>
                <a href="add_supplier.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create First Supplier
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
    $('#suppliersTable').DataTable({
        'order': [[1, 'asc']],
        'pageLength': 25
    });
});
</script>
";

include __DIR__ . '/../../../templates/footer.php';
?>

