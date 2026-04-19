<?php
/**
 * Edit Location
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Location - MJR Group ERP';

// Get location ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid location ID', 'error');
    redirect('locations.php');
}

// Get location details
try {
    $location = db_fetch("
        SELECT l.*, c.name as company_name 
        FROM locations l
        LEFT JOIN companies c ON l.company_id = c.id
        WHERE l.id = ?
    ", [$id]);
    
    if (!$location) {
        throw new Exception('Location not found');
    }
} catch (Exception $e) {
    log_error("Error loading location: " . $e->getMessage());
    set_flash('Error loading location: ' . $e->getMessage(), 'error');
    redirect('locations.php');
}

// Get companies for dropdown
try {
    $companies = db_fetch_all("
        SELECT id, code, name 
        FROM companies 
        WHERE is_active = 1 
        ORDER BY name
    ");
} catch (Exception $e) {
    log_error("Error loading companies: " . $e->getMessage());
    $companies = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $company_id = trim($_POST['company_id'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $custom_fields = trim($_POST['custom_fields'] ?? '');
        
        // Validate required fields
        if (empty($code) || empty($name) || empty($company_id) || empty($type)) {
            throw new Exception('Code, name, company, and type are required');
        }
        
        // Check if code already exists for another location
        $existing = db_fetch("
            SELECT id FROM locations 
            WHERE code = ? AND id != ?
        ", [$code, $id]);
        
        if ($existing) {
            throw new Exception('Location code already exists');
        }
        
        // Update location
        db_query("
            UPDATE locations 
            SET code = ?,
                name = ?,
                company_id = ?,
                type = ?,
                address = ?,
                custom_fields = ?
            WHERE id = ?
        ", [$code, $name, intval($company_id), $type, $address, $custom_fields, $id]);
        
        set_flash('Location updated successfully!', 'success');
        redirect('locations.php');
        
    } catch (Exception $e) {
        log_error("Error updating location: " . $e->getMessage());
        set_flash('Error updating location: ' . $e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1 class="display-4">
                <i class="fas fa-edit me-3"></i>Edit Location
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('inventory/index.php') ?>">Inventory</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('inventory/locations.php') ?>">Locations</a></li>
                    <li class="breadcrumb-item active">Edit: <?= escape_html($location['name']) ?></li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Location Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="code" class="form-label">Location Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="code" name="code" 
                                       value="<?= escape_html($_POST['code'] ?? $location['code']) ?>" required>
                                <small class="form-text text-muted">Unique identifier for this location</small>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="name" class="form-label">Location Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= escape_html($_POST['name'] ?? $location['name']) ?>" required>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <?php if (!empty($_SESSION['company_id'])): ?>
                                    <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                    <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                <?php else: ?>
                                    <select class="form-select" id="company_id" name="company_id" required>
                                        <option value="">Select Company</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?= $company['id'] ?>" 
                                                    <?= (($_POST['company_id'] ?? $location['company_id']) == $company['id']) ? 'selected' : '' ?>>
                                                <?= escape_html($company['code']) ?> - <?= escape_html($company['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="type" class="form-label">Location Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="warehouse" <?= (($_POST['type'] ?? $location['type']) == 'warehouse') ? 'selected' : '' ?>>Warehouse</option>
                                    <option value="factory" <?= (($_POST['type'] ?? $location['type']) == 'factory') ? 'selected' : '' ?>>Factory</option>
                                    <option value="retail" <?= (($_POST['type'] ?? $location['type']) == 'retail') ? 'selected' : '' ?>>Retail</option>
                                    <option value="office" <?= (($_POST['type'] ?? $location['type']) == 'office') ? 'selected' : '' ?>>Office</option>
                                    <option value="transit" <?= (($_POST['type'] ?? $location['type']) == 'transit') ? 'selected' : '' ?>>Transit</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= escape_html($_POST['address'] ?? $location['address'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                            <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3"><?= escape_html($_POST['custom_fields'] ?? $location['custom_fields'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?= url('inventory/locations.php') ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Location
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Location Details</h5>
                </div>
                <div class="card-body">
                    <p><strong>Current Company:</strong><br><?= escape_html($location['company_name']) ?></p>
                    <p><strong>Status:</strong><br>
                        <span class="badge bg-<?= $location['is_active'] ? 'success' : 'danger' ?>">
                            <?= $location['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong><br><?= format_date($location['created_at'], DISPLAY_DATETIME_FORMAT) ?></p>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Location Types</h5>
                </div>
                <div class="card-body">
                    <ul class="small">
                        <li><strong>Warehouse:</strong> Storage facility</li>
                        <li><strong>Factory:</strong> Manufacturing facility</li>
                        <li><strong>Retail:</strong> Retail store or shop</li>
                        <li><strong>Office:</strong> Office location</li>
                        <li><strong>Transit:</strong> Goods in transit</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>



