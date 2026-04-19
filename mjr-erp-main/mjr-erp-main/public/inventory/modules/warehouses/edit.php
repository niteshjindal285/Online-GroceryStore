<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();
require_permission('manage_inventory');

$id = $_GET['id'] ?? 0;
$wh = db_fetch_all("SELECT * FROM warehouses WHERE id = $id")[0] ?? null;

include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container mt-4">
    <div class="card shadow-sm col-md-8 mx-auto">
        <div class="card-header"><strong>Edit Warehouse</strong></div>
        <div class="card-body">
            <form action="edit_process.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $wh['id']; ?>">
                <div class="mb-3">
                    <label>Warehouse Name</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $wh['name']; ?>" required>
                </div>
                <div class="mb-3">
                    <label>Manager</label>
                    <input type="text" name="manager_name" class="form-control" value="<?php echo $wh['manager_name']; ?>">
                </div>
                <div class="mb-3">
                    <label>Status</label>
                    <select name="is_active" class="form-select">
                        <option value="1" <?php echo $wh['is_active'] ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo !$wh['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Warehouse</button>
            </form>
        </div>
    </div>
</div>