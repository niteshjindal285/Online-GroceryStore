<?php
// Path fix
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

// Database se suppliers fetch karna (db_fetch_all returns an array)
$suppliers = db_fetch_all("SELECT * FROM suppliers ORDER BY created_at DESC");

$page_title = 'Suppliers - MJR Group ERP';
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-truck me-2"></i>Supplier Management</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Supplier
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">ID</th>
                            <th>Code</th>
                            <th>Supplier Name</th>
                            <th>Contact Person</th>
                            <th>Email & Phone</th>
                            <th>City</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers)): ?>
                            <?php foreach ($suppliers as $row): ?>
                            <tr>
                                <td class="ps-3"><?php echo $row['id']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo $row['supplier_code']; ?></span></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                <td>
                                    <small class="d-block text-muted"><?php echo $row['email']; ?></small>
                                    <small class="d-block text-muted"><?php echo $row['phone']; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if($row['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                        <a href="edit.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                        <button class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $row['id']; ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No suppliers found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if(confirm('Are you sure you want to delete this supplier?')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>