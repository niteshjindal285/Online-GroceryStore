<?php
/**
 * System Permissions Management – Full Detail View
 * Only accessible by Administrators
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if (!is_admin() && !has_permission('assign_user_permissions') && !has_permission('manage_permissions')) {
    set_flash('Access denied. Permission management privileges required.', 'error');
    redirect(url('index.php'));
}

$page_title = 'Permissions – MJR Group ERP';
$can_edit_role_matrix = is_super_admin() || has_permission('manage_permissions');
$manageable_roles = get_manageable_roles();
$scope_company_id = current_user_company_id();
$accessible_company_ids = get_accessible_company_ids();
$editable_roles = ['company_admin', 'manager', 'user'];
$role_labels = [
    'company_admin' => ['label' => 'Company Admin', 'icon' => 'fa-user-shield', 'class' => 'text-primary'],
    'manager' => ['label' => 'Manager', 'icon' => 'fa-user-tie', 'class' => 'text-warning'],
    'user' => ['label' => 'User', 'icon' => 'fa-user', 'class' => 'text-info'],
];

// ─── Permission & Module Definitions ──────────────────────────────────────────
// Each module has granular actions: view, manage (and where applicable: approve, export)
$MODULES = [
    'Inventory'   => [
        'icon'  => 'fa-boxes',
        'color' => 'primary',
        'perms' => [
            'view_inventory'    => ['label' => 'View',    'desc' => 'See inventory items and stock levels'],
            'manage_inventory'  => ['label' => 'Manage',  'desc' => 'Create, edit, delete inventory items'],
            'view_master_config' => ['label' => 'Config', 'desc' => 'View & use Master Config menu'],
        ],
    ],
    'Finance'     => [
        'icon'  => 'fa-chart-line',
        'color' => 'success',
        'perms' => [
            'view_finance'      => ['label' => 'View',    'desc' => 'View accounts, ledger, reports'],
            'manage_finance'    => ['label' => 'Manage',  'desc' => 'Create journal entries, manage accounts'],
        ],
    ],
    'Sales'       => [
        'icon'  => 'fa-shopping-cart',
        'color' => 'info',
        'perms' => [
            'view_sales'        => ['label' => 'View',    'desc' => 'View sales orders, quotes, invoices'],
            'manage_sales'      => ['label' => 'Manage',  'desc' => 'Create and edit sales orders and quotes'],
        ],
    ],
    'Procurement' => [
        'icon'  => 'fa-truck',
        'color' => 'warning',
        'perms' => [
            'view_procurement'  => ['label' => 'View',    'desc' => 'View purchase orders and suppliers'],
            'manage_procurement'=> ['label' => 'Manage',  'desc' => 'Create and edit purchase orders'],
        ],
    ],
    'Production'  => [
        'icon'  => 'fa-cogs',
        'color' => 'secondary',
        'perms' => [
            'view_production'   => ['label' => 'View',    'desc' => 'View production orders and BOMs'],
            'manage_production' => ['label' => 'Manage',  'desc' => 'Create and manage production orders'],
        ],
    ],
    'Projects'    => [
        'icon'  => 'fa-project-diagram',
        'color' => 'purple',
        'perms' => [
            'view_projects'     => ['label' => 'View',    'desc' => 'View projects, phases and timelines'],
            'manage_projects'   => ['label' => 'Manage',  'desc' => 'Create and manage projects, phases, invoices'],
        ],
    ],
    'Analytics'   => [
        'icon'  => 'fa-chart-bar',
        'color' => 'danger',
        'perms' => [
            'view_analytics'    => ['label' => 'View',    'desc' => 'View dashboards and analytics reports'],
        ],
    ],
    'Companies'   => [
        'icon'  => 'fa-building',
        'color' => 'dark',
        'perms' => [
            'manage_companies'   => ['label' => 'Manage', 'desc' => 'Create and edit company profiles within allowed scope'],
            'switch_company'     => ['label' => 'Scope',  'desc' => 'Switch active company context (Super Admin only)'],
        ],
    ],
    'Users'       => [
        'icon'  => 'fa-users',
        'color' => 'dark',
        'perms' => [
            'manage_users'            => ['label' => 'Manage', 'desc' => 'Create, edit and deactivate users'],
            'assign_user_permissions' => ['label' => 'Assign', 'desc' => 'Assign custom permissions to lower-level users'],
            'manage_permissions'      => ['label' => 'Admin',  'desc' => 'Maintain the global role permission matrix'],
        ],
    ],
];

$ALL_PERM_NAMES = [];
foreach ($MODULES as $m) foreach (array_keys($m['perms']) as $pn) $ALL_PERM_NAMES[] = $pn;

// Load DB permission IDs
$db_permissions = db_fetch_all("SELECT * FROM permissions ORDER BY name");
$perm_id_by_name = []; $perm_name_by_id = [];
foreach ($db_permissions as $p) {
    $perm_id_by_name[$p['name']] = $p['id'];
    $perm_name_by_id[$p['id']]   = $p['name'];
}

// ─── Handle: Save Role Permissions ─────────────────────────────────────────────
if (is_post() && post('action') === 'save_role_perms') {
    if (!$can_edit_role_matrix) {
        set_flash('Only Super Admin can change the role permission matrix.', 'error');
        redirect('permissions.php');
    }
    if (!verify_csrf_token(post('csrf_token'))) { set_flash('Invalid token.','error'); redirect('permissions.php'); }
    try {
        db_query("BEGIN");
        foreach ($editable_roles as $role) {
            db_query("DELETE FROM role_permissions WHERE role = ?", [$role]);
            $selected = $_POST['role_perm'][$role] ?? [];
            foreach ($selected as $perm_name) {
                if (isset($perm_id_by_name[$perm_name])) {
                    db_insert("INSERT INTO role_permissions (role, permission_id) VALUES (?,?)", [$role, $perm_id_by_name[$perm_name]]);
                }
            }
        }
        db_query("COMMIT");
        update_global_permissions_version();
        unset($_SESSION['permissions']);
        set_flash('Role permissions saved successfully.', 'success');
    } catch (Exception $e) {
        db_query("ROLLBACK");
        set_flash('Error: ' . $e->getMessage(), 'error');
    }
    redirect('permissions.php');
}

// ─── Handle: Grant Permission to User ──────────────────────────────────────────
if (is_post() && post('action') === 'grant_user_perm') {
    if (!verify_csrf_token(post('csrf_token'))) { set_flash('Invalid token.','error'); redirect('permissions.php?tab=users'); }
    $uid = intval(post('user_id'));
    $perm_name = post('perm_name');
    $target_user = $uid > 0 ? db_fetch("SELECT id, role, company_id FROM users WHERE id = ?", [$uid]) : null;

    if (!$target_user || !can_assign_permissions_to_user($target_user)) {
        set_flash('You cannot assign permissions to that account.', 'error');
        redirect('permissions.php?tab=users');
    }

    if ($uid > 0 && isset($perm_id_by_name[$perm_name])) {
        try {
            db_insert("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (?,?,?)", [$uid, $perm_id_by_name[$perm_name], current_user_id()]);
            update_global_permissions_version();
            set_flash("Permission <strong>$perm_name</strong> granted.", 'success');
        } catch (Exception $e) { set_flash('Error: '.$e->getMessage(),'error'); }
    }
    redirect('permissions.php?tab=users&user_id='.$uid);
}

// ─── Handle: Revoke Permission from User ────────────────────────────────────────
if (is_post() && post('action') === 'revoke_user_perm') {
    if (!verify_csrf_token(post('csrf_token'))) { set_flash('Invalid token.','error'); redirect('permissions.php?tab=users'); }
    $uid = intval(post('user_id'));
    $perm_name = post('perm_name');
    $target_user = $uid > 0 ? db_fetch("SELECT id, role, company_id FROM users WHERE id = ?", [$uid]) : null;

    if (!$target_user || !can_assign_permissions_to_user($target_user)) {
        set_flash('You cannot change permissions for that account.', 'error');
        redirect('permissions.php?tab=users');
    }

    if ($uid > 0 && isset($perm_id_by_name[$perm_name])) {
        db_query("DELETE FROM user_permissions WHERE user_id=? AND permission_id=?", [$uid, $perm_id_by_name[$perm_name]]);
        update_global_permissions_version();
        set_flash("Permission <strong>$perm_name</strong> revoked.", 'info');
    }
    redirect('permissions.php?tab=users&user_id='.$uid);
}

// ─── Handle: Save ALL permissions for a user at once ──────────────────────────
if (is_post() && post('action') === 'save_user_perms') {
    if (!verify_csrf_token(post('csrf_token'))) { set_flash('Invalid token.','error'); redirect('permissions.php?tab=users'); }
    $uid = intval(post('user_id'));
    $target_user = $uid > 0 ? db_fetch("SELECT id, role, company_id FROM users WHERE id = ?", [$uid]) : null;

    if (!$target_user || !can_assign_permissions_to_user($target_user)) {
        set_flash('You cannot change permissions for that account.', 'error');
        redirect('permissions.php?tab=users');
    }

    if ($uid > 0) {
        try {
            db_query("BEGIN");
            db_query("DELETE FROM user_permissions WHERE user_id=?", [$uid]);
            $selected = $_POST['user_perm'] ?? [];
            foreach ($selected as $perm_name) {
                if (isset($perm_id_by_name[$perm_name])) {
                    db_insert("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (?,?,?)", [$uid, $perm_id_by_name[$perm_name], current_user_id()]);
                }
            }
            db_query("COMMIT");
            update_global_permissions_version();
            set_flash('User permissions saved successfully.', 'success');
        } catch (Exception $e) { db_query("ROLLBACK"); set_flash('Error: '.$e->getMessage(),'error'); }
    }
    redirect('permissions.php?tab=users&user_id='.$uid);
}

// ─── Handle: Approve/Reject Permission Request ─────────────────────────────────
if (is_post() && post('action') === 'process_request') {
    if (verify_csrf_token(post('csrf_token'))) {
        $request_id = intval(post('request_id'));
        $decision   = post('decision');
        $notes      = sanitize_input(post('admin_notes',''));
        try {
            $req = db_fetch("SELECT * FROM permission_requests WHERE id=?", [$request_id]);
            if (!$req) throw new Exception("Request not found.");

            $target_user = db_fetch("SELECT id, role, company_id FROM users WHERE id = ?", [$req['user_id']]);
            if (!$target_user || !can_assign_permissions_to_user($target_user)) {
                throw new Exception('You cannot process permission requests for that account.');
            }

            if ($decision === 'approve') {
                db_execute("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (?,?,?)", [$req['user_id'], $req['permission_id'], current_user_id()]);
                db_execute("UPDATE permission_requests SET status='approved', admin_notes=?, updated_at=NOW() WHERE id=?", [$notes, $request_id]);
                update_global_permissions_version();
                set_flash('Request approved and permission granted.', 'success');
            } else {
                db_execute("UPDATE permission_requests SET status='rejected', admin_notes=?, updated_at=NOW() WHERE id=?", [$notes, $request_id]);
                set_flash('Permission request rejected.', 'info');
            }
        } catch (Exception $e) { set_flash('Error: '.$e->getMessage(),'error'); }
    }
    redirect('permissions.php?tab=requests');
}

// ─── Load Data ─────────────────────────────────────────────────────────────────

// Role permissions
$role_perms_raw = db_fetch_all("SELECT role, permission_id FROM role_permissions WHERE role IN ('company_admin', 'manager', 'user')");
$role_perms = ['company_admin' => [], 'manager' => [], 'user' => []];
foreach ($role_perms_raw as $rp) {
    $role_perms[$rp['role']][] = $perm_name_by_id[$rp['permission_id']] ?? '';
}

// All users within current RBAC scope
$all_users = [];
if (is_super_admin() || !empty($manageable_roles)) {
    $all_users_sql = "
        SELECT u.*, c.name AS company_name
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE u.is_active = 1
    ";
    $all_user_params = [];

    if (is_super_admin()) {
        $all_users_sql .= " AND u.role NOT IN ('admin', 'super_admin')";
    } else {
        if (empty($accessible_company_ids)) {
            $all_users_sql .= " AND 1 = 0";
        } else {
            $company_placeholders = implode(',', array_fill(0, count($accessible_company_ids), '?'));
            $role_placeholders = implode(',', array_fill(0, count($manageable_roles), '?'));
            $all_users_sql .= " AND u.company_id IN ($company_placeholders) AND u.role IN ($role_placeholders)";
            $all_user_params = array_merge($all_user_params, $accessible_company_ids, $manageable_roles);
        }
    }

    $all_users_sql .= " ORDER BY FIELD(u.role, 'company_admin', 'manager', 'user'), u.username";
    $all_users = db_fetch_all($all_users_sql, $all_user_params);
}

// User individual permissions
$user_perms_raw = db_fetch_all("
    SELECT up.user_id, p.name AS perm_name
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
");
$user_perms = [];
foreach ($user_perms_raw as $up) $user_perms[$up['user_id']][] = $up['perm_name'];

// Pending requests
$pending_sql = "
    SELECT pr.*, u.username, u.role AS user_role, p.name AS perm_name, c.name AS company_name
    FROM permission_requests pr
    JOIN users u ON pr.user_id = u.id
    JOIN permissions p ON pr.permission_id = p.id
    LEFT JOIN companies c ON u.company_id = c.id
    WHERE pr.status = 'pending'
";
$pending_params = [];
if (!is_super_admin()) {
    if (empty($manageable_roles) || empty($accessible_company_ids)) {
        $pending_sql .= " AND 1 = 0";
    } else {
        $company_placeholders = implode(',', array_fill(0, count($accessible_company_ids), '?'));
        $role_placeholders = implode(',', array_fill(0, count($manageable_roles), '?'));
        $pending_sql .= " AND u.company_id IN ($company_placeholders) AND u.role IN ($role_placeholders)";
        $pending_params = array_merge($pending_params, $accessible_company_ids, $manageable_roles);
    }
}
$pending_sql .= " ORDER BY pr.request_date ASC";
$pending_requests = db_fetch_all($pending_sql, $pending_params);

// Request history (recent 30)
$history_sql = "
    SELECT pr.*, u.username, p.name AS perm_name
    FROM permission_requests pr
    JOIN users u ON pr.user_id = u.id
    JOIN permissions p ON pr.permission_id = p.id
    WHERE pr.status != 'pending'
";
$history_params = [];
if (!is_super_admin()) {
    if (empty($manageable_roles) || empty($accessible_company_ids)) {
        $history_sql .= " AND 1 = 0";
    } else {
        $company_placeholders = implode(',', array_fill(0, count($accessible_company_ids), '?'));
        $role_placeholders = implode(',', array_fill(0, count($manageable_roles), '?'));
        $history_sql .= " AND u.company_id IN ($company_placeholders) AND u.role IN ($role_placeholders)";
        $history_params = array_merge($history_params, $accessible_company_ids, $manageable_roles);
    }
}
$history_sql .= " ORDER BY pr.updated_at DESC LIMIT 30";
$request_history = db_fetch_all($history_sql, $history_params);

$active_tab   = get_param('tab', $can_edit_role_matrix ? 'roles' : 'users');
if ($active_tab === 'roles' && !$can_edit_role_matrix) {
    $active_tab = 'users';
}

$selected_uid = intval(get_param('user_id', 0));
if ($selected_uid > 0) {
    $selected_user_check = db_fetch("SELECT id, role, company_id FROM users WHERE id = ?", [$selected_uid]);
    if (!$selected_user_check || !can_assign_permissions_to_user($selected_user_check)) {
        $selected_uid = 0;
    }
}
if (!$selected_uid && !empty($all_users)) $selected_uid = $all_users[0]['id'];

$pending_count = count($pending_requests);

include __DIR__ . '/../../templates/header.php';
?>

<style>
.perm-badge-view    { background: rgba(13,110,253,.15); color:#4da3ff; border:1px solid rgba(13,110,253,.3); }
.perm-badge-manage  { background: rgba(255,193,7,.15);  color:#ffc107; border:1px solid rgba(255,193,7,.3); }
.perm-badge-config  { background: rgba(108,117,125,.15);color:#adb5bd; border:1px solid rgba(108,117,125,.3);}
.perm-row-active    { background:rgba(255,255,255,.04) !important; }
.perm-toggle        { width:1.35rem; height:1.35rem; cursor:pointer; accent-color:#fca311; appearance:auto !important; pointer-events:auto; position:relative; z-index:2; }
.perm-toggle:checked{ background-color:#fca311; border-color:#fca311; }
.perm-check-wrap    { display:inline-flex; align-items:center; justify-content:center; min-width:2.75rem; min-height:2.75rem; cursor:pointer; border-radius:.65rem; padding:.4rem; margin:0; }
.perm-check-wrap:hover { background:rgba(252,163,17,.12); }
.perm-check-cell    { cursor:pointer; user-select:none; }
.user-pill.active   { background:#0d6efd; color:#fff; }
.user-pill          { background:rgba(255,255,255,.07); color:inherit; transition:.15s; }
.user-pill:hover    { background:rgba(255,255,255,.14); }
.eff-yes            { color:#28a745; font-size:1.1rem; }
.eff-no             { color:rgba(255,255,255,.2); font-size:1.1rem; }
.module-header td   { background:rgba(255,255,255,.05); font-weight:600; font-size:.8rem; letter-spacing:.06em; text-transform:uppercase; }
.tab-counts .badge  { font-size:.65rem; transform:translateY(-1px); }
</style>

<div class="container-fluid">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-shield-alt me-2 text-primary"></i>Permissions</h1>
            <p class="text-muted mb-0">Configure role-level access and manage individual user permissions.</p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-primary fs-6"><?= count($all_users) ?> Users</span>
            <?php if ($pending_count): ?><span class="badge bg-warning text-dark fs-6"><?= $pending_count ?> Pending</span><?php endif; ?>
        </div>
    </div>

    <!-- Tab Nav -->
    <ul class="nav nav-tabs mb-4 tab-counts" id="permTabs">
        <?php if ($can_edit_role_matrix): ?>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab==='roles' ? 'active' : '' ?>" href="?tab=roles">
                <i class="fas fa-users-cog me-2"></i>Role Permissions
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab==='users' ? 'active' : '' ?>" href="?tab=users">
                <i class="fas fa-user-shield me-2"></i>User Permissions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab==='requests' ? 'active' : '' ?>" href="?tab=requests">
                <i class="fas fa-inbox me-2"></i>Requests
                <?php if ($pending_count): ?><span class="badge bg-danger ms-1"><?= $pending_count ?></span><?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab==='history' ? 'active' : '' ?>" href="?tab=history">
                <i class="fas fa-history me-2"></i>History
            </a>
        </li>
    </ul>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- TAB 1: ROLE PERMISSIONS                                         -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php if ($active_tab === 'roles'): ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>Role-Level Permission Matrix</h5>
                <small class="text-muted">Applies to all users with that role. Super Admin always has full access.</small>
            </div>
        </div>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="save_role_perms">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:200px;">Module</th>
                            <th>Permission</th>
                            <th class="text-muted small" style="width:120px;">Description</th>
                            <?php foreach ($editable_roles as $role_key): ?>
                            <th class="text-center" style="width:140px;">
                                <i class="fas <?= $role_labels[$role_key]['icon'] ?> me-1 <?= $role_labels[$role_key]['class'] ?>"></i><?= $role_labels[$role_key]['label'] ?>
                            </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($MODULES as $mod_name => $mod): ?>
                        <tr class="module-header">
                            <td colspan="<?= 3 + count($editable_roles) ?>">
                                <i class="fas <?= $mod['icon'] ?> me-2 text-<?= $mod['color'] ?>"></i>
                                <?= $mod_name ?>
                            </td>
                        </tr>
                        <?php foreach ($mod['perms'] as $perm_name => $perm_info): ?>
                        <?php
                            $label_class = match($perm_info['label']) {
                                'View'   => 'perm-badge-view',
                                'Manage' => 'perm-badge-manage',
                                default  => 'perm-badge-config'
                            };
                        ?>
                        <tr>
                            <td></td>
                            <td>
                                <span class="badge <?= $label_class ?> me-2"><?= $perm_info['label'] ?></span>
                                <code class="small"><?= $perm_name ?></code>
                            </td>
                            <td><small class="text-muted"><?= $perm_info['desc'] ?></small></td>
                            <?php foreach ($editable_roles as $role_key): ?>
                            <?php $role_has = in_array($perm_name, $role_perms[$role_key] ?? [], true); ?>
                            <td class="text-center">
                                <?php $role_checkbox_id = 'role_perm_' . $role_key . '_' . preg_replace('/[^a-z0-9_]+/i', '_', $perm_name); ?>
                                <label class="perm-check-wrap" for="<?= $role_checkbox_id ?>">
                                    <input type="checkbox" class="form-check-input perm-toggle"
                                           id="<?= $role_checkbox_id ?>"
                                           name="role_perm[<?= $role_key ?>][]" value="<?= $perm_name ?>"
                                           <?= $role_has ? 'checked' : '' ?>
                                           onclick="event.stopPropagation()">
                                </label>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="fas fa-info-circle me-1"></i>Changes apply to all users of that role on their next page load.</small>
                <button type="submit" class="btn btn-primary px-5">
                    <i class="fas fa-save me-2"></i>Save Role Permissions
                </button>
            </div>
        </form>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- TAB 2: USER PERMISSIONS                                          -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php elseif ($active_tab === 'users'): ?>
    <div class="row g-4">

        <!-- Left: User List -->
        <div class="col-lg-3">
            <div class="card shadow-sm">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-users me-2"></i>Select User</h6></div>
                <div class="list-group list-group-flush" style="max-height:75vh; overflow-y:auto;">
                    <?php
                    $grouped = [];
                    foreach ($all_users as $u) $grouped[$u['role']][] = $u;
                    foreach (['company_admin','manager','user'] as $role):
                        if (empty($grouped[$role])) continue;
                    ?>
                    <div class="list-group-item py-1 px-3 bg-dark">
                        <small class="text-muted text-uppercase fw-bold" style="font-size:.65rem; letter-spacing:.08em;">
                            <?= $role === 'company_admin' ? 'Company Admins' : ucfirst($role) . 's' ?>
                        </small>
                    </div>
                    <?php foreach ($grouped[$role] as $u): ?>
                    <?php
                        $individual_count = count($user_perms[$u['id']] ?? []);
                        $is_selected = $u['id'] == $selected_uid;
                    ?>
                    <a href="?tab=users&user_id=<?= $u['id'] ?>"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 <?= $is_selected ? 'active' : '' ?>">
                        <div>
                            <div class="fw-semibold small"><?= escape_html($u['username']) ?></div>
                            <small class="opacity-75"><?= escape_html($u['company_name'] ?? '—') ?></small>
                        </div>
                        <?php if ($individual_count): ?>
                        <span class="badge bg-warning text-dark rounded-pill"><?= $individual_count ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Right: Selected User Permissions -->
        <div class="col-lg-9">
        <?php
        $sel_user = $selected_uid ? db_fetch("SELECT u.*, c.name AS company_name FROM users u LEFT JOIN companies c ON u.company_id=c.id WHERE u.id=?", [$selected_uid]) : null;
        if ($sel_user):
            $u_individual = $user_perms[$selected_uid] ?? [];
            $u_role_perms = $role_perms[normalize_role_name($sel_user['role'])] ?? [];
            $u_effective  = array_unique(array_merge($u_role_perms, $u_individual));
        ?>
        <!-- User Info Bar -->
        <div class="card shadow-sm mb-3">
            <div class="card-body py-2 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                         style="width:44px;height:44px;font-size:1.2rem;">
                        <?= strtoupper(substr($sel_user['username'],0,1)) ?>
                    </div>
                    <div>
                        <div class="fw-bold fs-5"><?= escape_html($sel_user['username']) ?></div>
                        <div class="text-muted small">
                            <span class="badge bg-secondary me-1"><?= ucwords(str_replace('_', ' ', normalize_role_name($sel_user['role']))) ?></span>
                            <?= escape_html($sel_user['email'] ?? '') ?>
                            <?php if ($sel_user['company_name']): ?>&bull; <?= escape_html($sel_user['company_name']) ?><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="small text-muted">Effective permissions</div>
                    <div class="fs-4 fw-bold text-success"><?= count($u_effective) ?></div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="d-flex gap-3 mb-3 small">
            <span><span class="badge bg-success me-1">✓ Role</span> Inherited from <?= ucwords(str_replace('_', ' ', normalize_role_name($sel_user['role']))) ?> role</span>
            <span><span class="badge bg-warning text-dark me-1">✓ Extra</span> Individually granted (override)</span>
            <span><span class="badge bg-danger me-1">✗</span> Not granted</span>
        </div>

        <!-- Full Permission Matrix for This User -->
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="save_user_perms">
            <input type="hidden" name="user_id" value="<?= $selected_uid ?>">

            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-user-shield me-2"></i>Permissions for <strong><?= escape_html($sel_user['username']) ?></strong></h6>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="checkAllUser(true)">
                            <i class="fas fa-check-double me-1"></i>Grant All
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="checkAllUser(false)">
                            <i class="fas fa-times me-1"></i>Revoke All Extra
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:180px;">Module</th>
                                <th>Permission</th>
                                <th class="text-center" style="width:110px;">Via Role</th>
                                <th class="text-center" style="width:120px;">Extra Grant</th>
                                <th class="text-center" style="width:110px;">Effective</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($MODULES as $mod_name => $mod): ?>
                            <tr class="module-header">
                                <td colspan="5">
                                    <i class="fas <?= $mod['icon'] ?> me-2 text-<?= $mod['color'] ?>"></i>
                                    <?= $mod_name ?>
                                </td>
                            </tr>
                            <?php foreach ($mod['perms'] as $perm_name => $perm_info): ?>
                            <?php
                                $via_role  = in_array($perm_name, $u_role_perms);
                                $extra     = in_array($perm_name, $u_individual);
                                $effective = in_array($perm_name, $u_effective);
                                $label_class = match($perm_info['label']) {
                                    'View'   => 'perm-badge-view',
                                    'Manage' => 'perm-badge-manage',
                                    default  => 'perm-badge-config'
                                };
                            ?>
                            <tr class="<?= $effective ? 'perm-row-active' : '' ?>">
                                <td></td>
                                <td>
                                    <span class="badge <?= $label_class ?> me-2"><?= $perm_info['label'] ?></span>
                                    <code class="small"><?= $perm_name ?></code>
                                    <div class="text-muted" style="font-size:.72rem;"><?= $perm_info['desc'] ?></div>
                                </td>
                                <td class="text-center">
                                    <?php if ($via_role): ?>
                                    <span class="badge bg-success px-3"><i class="fas fa-check"></i></span>
                                    <?php else: ?>
                                    <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center perm-check-cell" data-checkbox-cell>
                                    <?php $user_checkbox_id = 'user_perm_' . $selected_uid . '_' . preg_replace('/[^a-z0-9_]+/i', '_', $perm_name); ?>
                                    <label class="perm-check-wrap" for="<?= $user_checkbox_id ?>">
                                        <input type="checkbox" class="form-check-input perm-toggle user-extra-perm"
                                               id="<?= $user_checkbox_id ?>"
                                               name="user_perm[]" value="<?= $perm_name ?>"
                                               <?= $extra ? 'checked' : '' ?>
                                               onclick="event.stopPropagation()">
                                    </label>
                                </td>
                                <td class="text-center">
                                    <?php if ($effective): ?>
                                    <i class="fas fa-check-circle eff-yes" title="Has this permission"></i>
                                    <?php else: ?>
                                    <i class="fas fa-times-circle eff-no" title="Does not have this permission"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        "Extra Grant" adds individual permissions on top of the role. Unchecking only removes the extra — role permissions remain.
                    </small>
                    <button type="submit" class="btn btn-warning text-dark px-5 fw-bold">
                        <i class="fas fa-save me-2"></i>Save User Permissions
                    </button>
                </div>
            </div>
        </form>

        <!-- Summary: Effective Permission Tags -->
        <div class="card shadow-sm mt-3">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-id-badge me-2"></i>Effective Permissions Summary</h6></div>
            <div class="card-body d-flex flex-wrap gap-2">
                <?php foreach ($u_effective as $ep): ?>
                <?php
                    $epinfo = null;
                    foreach ($MODULES as $m) if (isset($m['perms'][$ep])) { $epinfo = $m['perms'][$ep]; break; }
                    $label_class = match($epinfo['label'] ?? '') {
                        'View' => 'perm-badge-view', 'Manage' => 'perm-badge-manage', default => 'perm-badge-config'
                    };
                    $is_extra = in_array($ep, $u_individual) && !in_array($ep, $u_role_perms);
                ?>
                <span class="badge <?= $label_class ?> px-3 py-2" style="font-size:.8rem;">
                    <?= $ep ?>
                    <?php if ($is_extra): ?><sup title="Individually granted">★</sup><?php endif; ?>
                </span>
                <?php endforeach; ?>
                <?php if (empty($u_effective)): ?>
                <span class="text-muted">No permissions assigned.</span>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <div class="text-center py-5 text-muted"><i class="fas fa-user fa-3x mb-3 opacity-25"></i><p>Select a user from the left.</p></div>
        <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- TAB 3: REQUESTS                                                  -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php elseif ($active_tab === 'requests'): ?>

    <?php if (empty($pending_requests)): ?>
    <div class="text-center py-5 text-muted">
        <i class="fas fa-inbox fa-4x mb-3 opacity-25"></i>
        <h5>No pending permission requests</h5>
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Requests (<?= $pending_count ?>)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Company</th>
                        <th>Role</th>
                        <th>Requested Permission</th>
                        <th>Purpose / Notes</th>
                        <th>Date</th>
                        <th style="min-width:280px;">Admin Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_requests as $req): ?>
                    <tr>
                        <td><strong><?= escape_html($req['username']) ?></strong></td>
                        <td><small class="text-muted"><?= escape_html($req['company_name'] ?? '—') ?></small></td>
                        <td><span class="badge bg-secondary"><?= ucfirst($req['user_role']) ?></span></td>
                        <td>
                            <?php
                                $reqinfo = null;
                                foreach ($MODULES as $m) if (isset($m['perms'][$req['perm_name']])) { $reqinfo = $m['perms'][$req['perm_name']]; break; }
                                $lbl_class = match($reqinfo['label'] ?? '') { 'View'=>'perm-badge-view','Manage'=>'perm-badge-manage',default=>'perm-badge-config' };
                            ?>
                            <span class="badge <?= $lbl_class ?> me-1"><?= $reqinfo['label'] ?? '' ?></span>
                            <code><?= escape_html($req['perm_name']) ?></code>
                            <?php if ($reqinfo): ?><div class="text-muted small"><?= $reqinfo['desc'] ?></div><?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= escape_html($req['request_reason'] ?? $req['notes'] ?? '—') ?></small></td>
                        <td><small><?= date('d M Y, H:i', strtotime($req['request_date'])) ?></small></td>
                        <td>
                            <form method="POST" class="d-flex gap-2 align-items-center">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="action" value="process_request">
                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                <input type="text" name="admin_notes" class="form-control form-control-sm" placeholder="Notes (optional)" style="max-width:160px;">
                                <button type="submit" name="decision" value="approve" class="btn btn-success btn-sm">
                                    <i class="fas fa-check me-1"></i>Approve
                                </button>
                                <button type="submit" name="decision" value="reject" class="btn btn-danger btn-sm">
                                    <i class="fas fa-times me-1"></i>Reject
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- TAB 4: HISTORY                                                   -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <?php elseif ($active_tab === 'history'): ?>
    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Request History (Last 30)</h5></div>
        <?php if (empty($request_history)): ?>
        <div class="card-body text-center text-muted py-4"><p>No history yet.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="histTable">
                <thead class="table-dark">
                    <tr><th>User</th><th>Permission</th><th>Status</th><th>Admin Notes</th><th>Resolved</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($request_history as $h): ?>
                    <tr>
                        <td><strong><?= escape_html($h['username']) ?></strong></td>
                        <td><code><?= escape_html($h['perm_name']) ?></code></td>
                        <td>
                            <span class="badge bg-<?= $h['status']==='approved'?'success':'danger' ?>">
                                <?= ucfirst($h['status']) ?>
                            </span>
                        </td>
                        <td><small class="text-muted"><?= escape_html($h['admin_notes'] ?? '—') ?></small></td>
                        <td><small><?= isset($h['updated_at']) ? date('d M Y H:i', strtotime($h['updated_at'])) : '—' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php
$additional_scripts = "
<script>
function checkAllUser(state) {
    document.querySelectorAll('.user-extra-perm').forEach(c => c.checked = state);
}
$(document).ready(function(){
    document.querySelectorAll('[data-checkbox-cell]').forEach(function(cell) {
        cell.addEventListener('click', function(event) {
            if (event.target.closest('input, label, button, a')) {
                return;
            }

            const checkbox = cell.querySelector('input[type=\"checkbox\"]');
            if (!checkbox || checkbox.disabled) {
                return;
            }

            checkbox.checked = !checkbox.checked;
        });
    });

    if($('#histTable').length) $('#histTable').DataTable({order:[[4,'desc']],pageLength:25});
});
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>
