<?php
/**
 * Companies - Manage Users
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_permission('manage_users');

$requested_company_id = (int) get_param('id');
$company_id = $requested_company_id > 0 ? $requested_company_id : active_company_id();
if (!$company_id) {
    if (is_admin()) {
        $accessible_company_ids = get_accessible_company_ids();
        $first_company = !empty($accessible_company_ids)
            ? ['id' => $accessible_company_ids[0]]
            : null;
        if ($first_company) {
            redirect('manage_users.php?id=' . $first_company['id']);
        }
    }
    redirect('index.php');
}

$page_title = 'Manage Users - MJR Group ERP';

// Get company
$company = db_fetch("SELECT * FROM companies WHERE id = ?", [$company_id]);

if (!$company) {
    set_flash('Company not found.', 'error');
    redirect('index.php');
}

enforce_company_access($company_id);
$accessible_company_ids = get_accessible_company_ids();
$accessible_companies = !empty($accessible_company_ids)
    ? db_fetch_all(
        "SELECT id, name, type FROM companies WHERE id IN (" . implode(',', array_fill(0, count($accessible_company_ids), '?')) . ") ORDER BY type DESC, name",
        $accessible_company_ids
    )
    : [];

// Get users in this company
$users = db_fetch_all("
    SELECT u.*, m.username AS manager_username
    FROM users u
    LEFT JOIN users m ON m.id = u.manager_id
    WHERE u.company_id = ?
    ORDER BY FIELD(u.role, 'super_admin', 'company_admin', 'manager', 'user'), u.username
", [$company_id]);

// Get available users for assignment based on scope
if (!empty($accessible_company_ids)) {
    $available_scope_placeholders = implode(',', array_fill(0, count($accessible_company_ids), '?'));
    $available_users = db_fetch_all("
        SELECT u.id, u.username, u.email, u.role, u.company_id, c.name AS company_name
        FROM users u
        LEFT JOIN companies c ON c.id = u.company_id
        WHERE u.is_active = 1
          AND (u.company_id IS NULL OR u.company_id IN ($available_scope_placeholders))
          AND (u.company_id IS NULL OR u.company_id != ?)
        ORDER BY username
    ", array_merge($accessible_company_ids, [$company_id]));
} else {
    $available_users = [];
}

$available_users = array_values(array_filter($available_users, function ($user) {
    return can_manage_user_role($user['role'] ?? 'user');
}));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-users me-3"></i>Manage Users</h1>
            <p class="lead mb-0"><?= escape_html($company['name']) ?></p>
        </div>
        <div class="col-auto">
            <?php if (count($accessible_companies) > 1): ?>
            <form method="GET" class="mb-2">
                <label for="company_switcher" class="form-label small text-muted mb-1 d-block">Switch company</label>
                <select class="form-select" id="company_switcher" name="id" onchange="this.form.submit()">
                    <?php foreach ($accessible_companies as $accessible_company): ?>
                    <option value="<?= (int) $accessible_company['id'] ?>" <?= (int) $accessible_company['id'] === (int) $company_id ? 'selected' : '' ?>>
                        <?= escape_html($accessible_company['name']) ?><?= ($accessible_company['type'] ?? '') === 'subsidiary' ? ' - Subsidiary' : ' - Parent' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <?php if (can_manage_user_role('user')): ?>
                <a href="create_user.php?company_id=<?= $company_id ?>&role=user" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Create User
                </a>
                <?php endif; ?>

                <?php if (can_manage_user_role('manager')): ?>
                <a href="create_user.php?company_id=<?= $company_id ?>&role=manager" class="btn btn-warning">
                    <i class="fas fa-id-badge me-2"></i>Create Manager ID
                </a>
                <?php endif; ?>

                <?php if (can_manage_user_role('company_admin')): ?>
                <a href="create_user.php?company_id=<?= $company_id ?>&role=company_admin" class="btn btn-info">
                    <i class="fas fa-user-shield me-2"></i>Create Company Admin
                </a>
                <?php endif; ?>

                <a href="view.php?id=<?= $company_id ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Company
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Current Users (<?= count($users) ?>)</h5>
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
                                    <th>Manager</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?= escape_html($user['username']) ?></td>
                                    <td><?= escape_html($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucwords(str_replace('_', ' ', escape_html($user['role'] ?? 'user'))) ?></span>
                                        <button class="btn btn-sm btn-link p-0 ms-1" onclick="showEditRole(<?= $user['id'] ?>, '<?= escape_html(addslashes($user['username'])) ?>', '<?= escape_html($user['role']) ?>')" title="Change Role">
                                            <i class="fas fa-exchange-alt small text-warning"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php 
                                        echo !empty($user['manager_username']) ? escape_html($user['manager_username']) : '<span class="text-muted italic">No Manager</span>';
                                        ?>
                                        <button class="btn btn-sm btn-link p-0 ms-1" onclick="showAssignManager(<?= $user['id'] ?>, '<?= escape_html($user['username']) ?>', <?= $user['manager_id'] ?: 'null' ?>)" title="Assign Manager">
                                            <i class="fas fa-edit small text-primary"></i>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_user.php?id=<?= $user['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-outline-info" title="Edit User profile">
                                                <i class="fas fa-user-edit"></i> Edit
                                            </a>
                                            <?php if (can_manage_user_account($user) && $user['id'] != current_user_id()): ?>
                                            <button class="btn btn-outline-danger" onclick="removeUser(<?= $user['id'] ?>)" title="Remove from Company">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No users assigned to this company</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-user-plus me-2"></i>Add Users</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($available_users)): ?>
                    <form method="POST" action="assign_user.php">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="company_id" value="<?= $company_id ?>">
                        
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select User <span class="text-danger">*</span></label>
                            <select class="form-select <?= (isset($flash) && $flash['type'] == 'error' && $flash['message'] == 'Please fill Select User that field') ? 'is-invalid' : '' ?>" id="user_id" name="user_id" required>
                                <option value="">Choose a user...</option>
                                <?php foreach ($available_users as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= escape_html($user['username']) ?> (<?= escape_html($user['email']) ?>) - <?= ucfirst($user['role']) ?><?= !empty($user['company_name']) ? ' - Currently in ' . escape_html($user['company_name']) : ' - Unassigned' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($flash) && $flash['type'] == 'error' && $flash['message'] == 'Please fill Select User that field'): ?>
                                <div class="invalid-feedback">Please fill Select User that field</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Assign to Company
                            </button>
                            <button type="submit"
                                    class="btn btn-outline-danger w-100"
                                    formaction="delete_user.php"
                                    formmethod="POST"
                                    onclick="return confirm('Delete the selected user account permanently? This cannot be undone if the account has no linked records.');">
                                <i class="fas fa-trash-alt me-2"></i>Delete Selected User ID
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> You can move active users between companies that are inside your access scope. Parent-company admins can map users into their subsidiaries from here. The delete option only works for accounts you are allowed to manage.
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No available users to assign</p>
                        <small class="text-muted">All users in your scope are already assigned to this company or are outside your role limits</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assign Manager Modal -->
<div class="modal fade" id="assignManagerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="assign_manager.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="company_id" value="<?= $company_id ?>">
                <input type="hidden" name="user_id" id="modal_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Assign Manager for <span id="modal_username" class="text-info"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="manager_id" class="form-label">Select Manager</label>
                        <select class="form-select" name="manager_id" id="modal_manager_id">
                            <option value="">-- No Manager --</option>
                            <?php 
                            // Managers for this company
                            $company_managers = db_fetch_all("
                                SELECT id, username FROM users 
                                WHERE company_id = ? AND (role = 'manager' OR role = 'company_admin' OR role = 'super_admin') 
                                AND is_active = 1
                            ", [$company_id]);
                            foreach ($company_managers as $mgr): ?>
                                <option value="<?= $mgr['id'] ?>"><?= escape_html($mgr['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text mt-2">Only users with Manager or Admin roles can be assigned as managers.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Manager</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="edit_user_role.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="company_id" value="<?= $company_id ?>">
                <input type="hidden" name="user_id" id="edit_modal_user_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role for <span id="edit_modal_username" class="text-info"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_modal_role" class="form-label">New Account Type</label>
                        <select class="form-select" name="role" id="edit_modal_role" required>
                            <?php 
                            $allowed_edit_roles = get_manageable_roles();
                            if (in_array('user', $allowed_edit_roles, true)): ?>
                                <option value="user">User</option>
                            <?php endif; ?>
                            <?php if (in_array('manager', $allowed_edit_roles, true)): ?>
                                <option value="manager">Manager</option>
                            <?php endif; ?>
                            <?php if (in_array('company_admin', $allowed_edit_roles, true)): ?>
                                <option value="company_admin">Company Admin</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text mt-2">You can only assign roles that are equal to or beneath your current permission level.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
function showEditRole(userId, username, currentRole) {
    document.getElementById('edit_modal_user_id').value = userId;
    document.getElementById('edit_modal_username').innerText = username;
    
    let roleSelect = document.getElementById('edit_modal_role');
    for (let i = 0; i < roleSelect.options.length; i++) {
        if (roleSelect.options[i].value === currentRole) {
            roleSelect.selectedIndex = i;
            break;
        }
    }
    
    var myModal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    myModal.show();
}

function showAssignManager(userId, username, currentManagerId) {
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_username').innerText = username;
    document.getElementById('modal_manager_id').value = currentManagerId === null ? '' : currentManagerId;
    var myModal = new bootstrap.Modal(document.getElementById('assignManagerModal'));
    myModal.show();
}

function removeUser(userId) {
    if (confirm('Are you sure you want to remove this user from the company?')) {
        // Create form and submit
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'remove_user.php';
        
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '" . generate_csrf_token() . "';
        
        var userInput = document.createElement('input');
        userInput.type = 'hidden';
        userInput.name = 'user_id';
        userInput.value = userId;
        
        var companyInput = document.createElement('input');
        companyInput.type = 'hidden';
        companyInput.name = 'company_id';
        companyInput.value = " . $company_id . ";
        
        form.appendChild(csrfInput);
        form.appendChild(userInput);
        form.appendChild(companyInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
