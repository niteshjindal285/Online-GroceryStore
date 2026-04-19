<?php
/**
 * Company Management - Main Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_admin();

$page_title = 'Company Management - MJR Group ERP';

// Get companies within the current RBAC scope
if (is_super_admin()) {
    $companies = db_fetch_all("
        SELECT c.*, p.name as parent_name
        FROM companies c
        LEFT JOIN companies p ON c.parent_id = p.id
        ORDER BY c.type DESC, c.name
    ");
    $total_users = db_fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'] ?? 0;
} else {
    $accessible_company_ids = get_accessible_company_ids();
    if (!empty($accessible_company_ids)) {
        $scope_placeholders = implode(',', array_fill(0, count($accessible_company_ids), '?'));
        $companies = db_fetch_all("
            SELECT c.*, p.name as parent_name
            FROM companies c
            LEFT JOIN companies p ON c.parent_id = p.id
            WHERE c.id IN ($scope_placeholders)
            ORDER BY c.type DESC, c.name
        ", $accessible_company_ids);
        $total_users = db_fetch("
            SELECT COUNT(*) as count
            FROM users
            WHERE company_id IN ($scope_placeholders)
              AND is_active = 1
        ", $accessible_company_ids)['count'] ?? 0;
    } else {
        $companies = [];
        $total_users = 0;
    }
}

// Calculate statistics
$total_companies = count($companies);
$active_companies = count(array_filter($companies, fn($c) => $c['is_active']));
$subsidiaries = count(array_filter($companies, fn($c) => $c['type'] === 'subsidiary'));


// Handle delete company
if (is_post() && isset($_POST['delete_company_id'])) {
    $csrf_token = post('csrf_token');

    if (verify_csrf_token($csrf_token)) {
        try {
            $del_id = intval(post('delete_company_id'));

            if (!is_super_admin()) {
                throw new Exception('Only Super Admin can delete companies.');
            }

            // Guard: no users assigned
            $user_count = db_fetch("SELECT COUNT(*) as c FROM users WHERE company_id = ?", [$del_id])['c'] ?? 0;
            if ($user_count > 0) {
                throw new Exception("Cannot delete: $user_count user(s) are assigned to this company.");
            }

            // Guard: no locations assigned
            $loc_count = db_fetch("SELECT COUNT(*) as c FROM locations WHERE company_id = ?", [$del_id])['c'] ?? 0;
            if ($loc_count > 0) {
                throw new Exception("Cannot delete: $loc_count location(s) are assigned to this company.");
            }

            // Guard: no subsidiaries (for parent companies)
            $sub_count = db_fetch("SELECT COUNT(*) as c FROM companies WHERE parent_id = ?", [$del_id])['c'] ?? 0;
            if ($sub_count > 0) {
                throw new Exception("Cannot delete: this company has $sub_count subsidiary/subsidiaries. Remove them first.");
            }

            db_query("DELETE FROM companies WHERE id = ?", [$del_id]);
            set_flash('Company deleted successfully.', 'success');
            redirect('index.php');
        } catch (Exception $e) {
            log_error("Error deleting company: " . $e->getMessage());
            set_flash($e->getMessage(), 'error');
        }
    } else {
        set_flash('Invalid security token (CSRF). Please refresh and try again.', 'error');
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-building me-3"></i>Company Management</h1>
            <p class="lead">Manage MJR Group companies and subsidiaries</p>
        </div>
        <div class="col-auto">
            <?php if (is_super_admin()): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Company
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Company Hierarchy -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-sitemap me-2"></i>MJR Group Structure</h5>
                </div>
                <div class="card-body">
                    <?php 
                    $parents = array_filter($companies, fn($c) => $c['type'] === 'parent');
                    if (!empty($parents)): 
                    ?>
                        <?php foreach ($parents as $parent): ?>
                        <div class="company-hierarchy">
                            <div class="d-flex align-items-center mb-3 p-3 border rounded bg-primary text-white">
                                <i class="fas fa-building fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1"><?= escape_html($parent['name']) ?></h5>
                                    <small><?= escape_html($parent['code']) ?> • Parent Company</small>
                                    <div class="mt-1">
                                        <small><i class="fas fa-envelope me-1"></i><?= escape_html($parent['email'] ?? 'Not set') ?></small>
                                        <small class="ms-3"><i class="fas fa-phone me-1"></i><?= escape_html($parent['phone'] ?? 'Not set') ?></small>
                                    </div>
                                </div>
                                <div class="ms-auto">
                                    <?php if ($parent['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Subsidiaries -->
                            <?php 
                            $subs = array_filter($companies, fn($c) => $c['parent_id'] == $parent['id']);
                            if (!empty($subs)): 
                            ?>
                            <div class="row ps-4">
                                <?php foreach ($subs as $subsidiary): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card border-start border-3 border-info">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="card-title">
                                                        <i class="fas fa-building me-2 text-info"></i>
                                                        <?= escape_html($subsidiary['name']) ?>
                                                    </h6>
                                                    <p class="card-text">
                                                        <small class="text-muted"><?= escape_html($subsidiary['code']) ?> • Subsidiary</small>
                                                    </p>
                                                    <?php if ($subsidiary['address']): ?>
                                                    <p class="card-text">
                                                        <small><i class="fas fa-map-marker-alt me-1"></i><?= escape_html(substr($subsidiary['address'], 0, 50)) ?>...</small>
                                                    </p>
                                                    <?php endif; ?>
                                                    <div class="mt-2">
                                                        <?php if ($subsidiary['email']): ?>
                                                        <small class="text-muted me-3">
                                                            <i class="fas fa-envelope me-1"></i><?= escape_html($subsidiary['email']) ?>
                                                        </small>
                                                        <?php endif; ?>
                                                        <?php if ($subsidiary['phone']): ?>
                                                        <small class="text-muted">
                                                            <i class="fas fa-phone me-1"></i><?= escape_html($subsidiary['phone']) ?>
                                                        </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div>
                                                    <?php if ($subsidiary['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                                <div class="mt-2">
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="edit.php?id=<?= $subsidiary['id'] ?>" class="btn btn-outline-primary" title="Edit Company">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="view.php?id=<?= $subsidiary['id'] ?>" class="btn btn-outline-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="manage_users.php?id=<?= $subsidiary['id'] ?>" class="btn btn-outline-success" title="Manage Users">
                                                            <i class="fas fa-users"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-danger" title="Delete"
                                                                onclick="deleteCompany(<?= $subsidiary['id'] ?>, <?= htmlspecialchars(json_encode($subsidiary['name']), ENT_QUOTES, 'UTF-8') ?>)">  
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No companies configured</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Company Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Companies</h6>
                            <h3><?= $total_companies ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-building fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Active Companies</h6>
                            <h3><?= $active_companies ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Subsidiaries</h6>
                            <h3><?= $subsidiaries ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-sitemap fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Users</h6>
                            <h3><?= $total_users ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Company Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-table me-2"></i>Company Details</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($companies)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="companiesTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Company Name</th>
                            <th>Type</th>
                            <th>Parent Company</th>
                            <th>Contact Info</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                        <tr>
                            <td>
                                <strong><?= escape_html($company['code']) ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= escape_html($company['name']) ?></strong>
                                <?php if ($company['address']): ?>
                                <br><small class="text-muted"><?= escape_html(strlen($company['address']) > 50 ? substr($company['address'], 0, 50) . '...' : $company['address']) ?></small>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($company['type'] == 'parent'): ?>
                                    <span class="badge bg-primary">Parent</span>
                                <?php else: ?>
                                    <span class="badge bg-info">Subsidiary</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($company['parent_name']): ?>
                                    <?= escape_html($company['parent_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($company['email']): ?>
                                    <div><i class="fas fa-envelope me-1"></i><?= escape_html($company['email']) ?></div>
                                <?php endif; ?>
                                <?php if ($company['phone']): ?>
                                    <div><i class="fas fa-phone me-1"></i><?= escape_html($company['phone']) ?></div>
                                <?php endif; ?>
                                <?php if (!$company['email'] && !$company['phone']): ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($company['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $company['created_at'] ? format_date($company['created_at']) : 'N/A' ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit.php?id=<?= $company['id'] ?>" class="btn btn-outline-primary" title="Edit Company">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view.php?id=<?= $company['id'] ?>" class="btn btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="manage_users.php?id=<?= $company['id'] ?>" class="btn btn-outline-success" title="Manage Users">
                                        <i class="fas fa-users"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                            onclick="deleteCompany(<?= $company['id'] ?>, <?= htmlspecialchars(json_encode($company['name']), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <p class="text-muted">No companies found</p>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Company
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Company Form (hidden) -->
<form id="deleteCompanyForm" method="POST" action="">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <input type="hidden" name="delete_company_id" id="deleteCompanyId">
</form>


<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#companiesTable').DataTable({
        'order': [[ 1, 'asc' ]],
        'pageLength': 25
    });

    // Show/hide parent company field based on type selection
    $('#type').change(function() {
        if ($(this).val() === 'subsidiary') {
            $('#parent_field').show();
            $('#parent_id').prop('required', true);
        } else {
            $('#parent_field').hide();
            $('#parent_id').prop('required', false);
            $('#parent_id').val('');
        }
    });
});

function deleteCompany(id, name) {
    if (confirm('Delete company \"' + name + '\"?\\n\\nThis will fail if the company has users or locations assigned.')) {
        try {
            document.getElementById('deleteCompanyId').value = id;
            var form = document.getElementById('deleteCompanyForm');
            if (form) {
                form.action = window.location.href; // ensure self-submission
                form.submit();
            } else {
                alert('Error: Delete form not found on the page.');
            }
        } catch (e) {
            console.error(e);
            alert('Error submitting deletion form.');
        }
    }
}
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
