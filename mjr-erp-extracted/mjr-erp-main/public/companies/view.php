<?php
/**
 * Companies - View Company Details
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_admin();

$company_id = get_param('id');
if (!$company_id) {
    redirect('index.php');
}

$page_title = 'View Company - MJR Group ERP';

// Get company details
$company = db_fetch("
    SELECT c.*, p.name as parent_name
    FROM companies c
    LEFT JOIN companies p ON c.parent_id = p.id
    WHERE c.id = ?
", [$company_id]);

if (!$company) {
    set_flash('Company not found.', 'error');
    redirect('index.php');
}

enforce_company_access($company_id, 'index.php');

// Get users in this company
$users = db_fetch_all("
    SELECT u.id, u.username, u.email, u.role, u.is_active
    FROM users u
    WHERE u.company_id = ?
    ORDER BY u.username
", [$company_id]);

// Get locations for this company
$locations = db_fetch_all("
    SELECT l.*, COUNT(s.id) as item_count
    FROM locations l
    LEFT JOIN inventory_stock_levels s ON l.id = s.location_id
    WHERE l.company_id = ?
    GROUP BY l.id
    ORDER BY l.name
", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-building me-3"></i><?= escape_html($company['name']) ?></h1>
            <p class="lead"><?= escape_html($company['code']) ?></p>
        </div>
        <div class="col-auto">
            <a href="edit.php?id=<?= $company['id'] ?>" class="btn btn-primary">
                <i class="fas fa-edit me-2"></i>Edit Company
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <!-- Company Details -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-info-circle me-2"></i>Company Information</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Company Code:</strong>
                            <p><?= escape_html($company['code']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Company Type:</strong>
                            <p>
                                <?php if ($company['type'] == 'parent'): ?>
                                    <span class="badge bg-primary">Parent Company</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Subsidiary</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($company['parent_name']): ?>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <strong>Parent Company:</strong>
                            <p><?= escape_html($company['parent_name']) ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Email:</strong>
                            <p><?= escape_html($company['email'] ?? 'Not provided') ?></p>
                        </div>
                        <div class="col-md-6">
                            <strong>Phone:</strong>
                            <p><?= escape_html($company['phone'] ?? 'Not provided') ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <strong>Address:</strong>
                            <p><?= nl2br(escape_html($company['address'] ?? 'Not provided')) ?></p>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Status:</strong>
                            <p>
                                <?php if ($company['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <strong>Created:</strong>
                            <p><?= format_date($company['created_at'], DISPLAY_DATETIME_FORMAT) ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card mb-3 bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-3x mb-2"></i>
                    <h3><?= count($users) ?></h3>
                    <p class="mb-0">Total Users</p>
                </div>
            </div>
            
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-warehouse fa-3x mb-2"></i>
                    <h3><?= count($locations) ?></h3>
                    <p class="mb-0">Locations</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Users -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-users me-2"></i>Company Users</h5>
            <a href="manage_users.php?id=<?= $company['id'] ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-user-plus me-1"></i>Manage Users
            </a>
        </div>
        <div class="card-body">
            <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= escape_html($user['username']) ?></td>
                            <td><?= escape_html($user['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= ucfirst($user['role']) ?></span></td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-muted">No users assigned to this company</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Locations -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-map-marker-alt me-2"></i>Company Locations</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($locations)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Items</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><strong><?= escape_html($location['code']) ?></strong></td>
                            <td><?= escape_html($location['name']) ?></td>
                            <td><span class="badge bg-info"><?= ucwords(str_replace('_', ' ', $location['type'])) ?></span></td>
                            <td><?= $location['item_count'] ?> items</td>
                            <td>
                                <?php if ($location['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-muted">No locations configured for this company</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
