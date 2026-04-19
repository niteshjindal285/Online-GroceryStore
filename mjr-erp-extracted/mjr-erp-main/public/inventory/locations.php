<?php
/**
 * Inventory - Locations Management
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view locations.', 'warning');
    redirect(url('index.php'));
}

// Get all locations
$locations = db_fetch_all("
    SELECT l.*, c.name as company_name
    FROM locations l
    JOIN companies c ON l.company_id = c.id
    WHERE l.company_id = ?
    ORDER BY l.code
", [$company_id]);

// Get companies for dropdown
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Handle add location
if (is_post() && isset($_POST['code'])) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $code = sanitize_input(post('code'));
            $name = sanitize_input(post('name'));
            $loc_company_id = $company_id; // Use forced company
            $type = post('type');
            $address = sanitize_input(post('address'));
            $custom_fields = sanitize_input(post('custom_fields'));
            
            $sql = "INSERT INTO locations (code, name, company_id, type, address, custom_fields, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
            db_insert($sql, [$code, $name, $loc_company_id, $type, $address, $custom_fields]);
            
            set_flash('Location added successfully!', 'success');
            redirect('locations.php');
        } catch (Exception $e) {
            log_error("Error adding location: " . $e->getMessage());
            set_flash('Error adding location.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-map-marker-alt me-3"></i>Warehouse Locations</h1>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                <i class="fas fa-plus me-2"></i>Add Location
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($locations)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><strong><?= escape_html($location['code']) ?></strong></td>
                            <td><?= escape_html($location['name']) ?></td>
                            <td><?= escape_html($location['company_name']) ?></td>
                            <td>
                                <span class="badge bg-info"><?= ucwords(str_replace('_', ' ', $location['type'])) ?></span>
                            </td>
                            <td><?= escape_html($location['address'] ?? 'N/A') ?></td>
                            <td>
                                <?php if ($location['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit_location.php?id=<?= $location['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_location.php?id=<?= $location['id'] ?>" class="btn btn-outline-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this location?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-warehouse fa-3x text-muted mb-3"></i>
                <p class="text-muted">No locations configured</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                    <i class="fas fa-plus me-2"></i>Add First Location
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="code" class="form-label">Location Code</label>
                        <input type="text" class="form-control" id="code" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label for="name" class="form-label">Location Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="company_id" class="form-label">Company</label>
                        <input type="text" class="form-control" value="<?= escape_html(active_company_name()) ?>" readonly>
                        <input type="hidden" name="company_id" value="<?= (int)$company_id ?>">
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="production">Production Floor</option>
                            <option value="warehouse">Warehouse</option>
                            <option value="store">Store</option>
                            <option value="distribution_center">Distribution Center</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                        <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3" placeholder="Enter custom details for this location..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>



