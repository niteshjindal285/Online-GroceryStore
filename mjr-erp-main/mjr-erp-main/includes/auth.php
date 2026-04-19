<?php
/**
 * Authentication System
 * 
 * Handles user authentication, login, logout, and session management
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Normalize legacy and hierarchical role names.
 */
function normalize_role_name($role) {
    $role = strtolower(trim((string) $role));

    return match ($role) {
        'admin', 'super admin', 'super-admin' => 'super_admin',
        'company admin', 'company-admin' => 'company_admin',
        default => $role !== '' ? $role : 'user',
    };
}

/**
 * Get comparable role level for hierarchy checks.
 */
function role_level($role) {
    return [
        'user' => 10,
        'manager' => 20,
        'company_admin' => 30,
        'super_admin' => 40,
    ][normalize_role_name($role)] ?? 0;
}

function is_super_admin($user = null) {
    $role = is_array($user) ? ($user['role'] ?? null) : ($_SESSION['role'] ?? null);
    return normalize_role_name($role) === 'super_admin';
}

function is_company_admin($user = null) {
    $role = is_array($user) ? ($user['role'] ?? null) : ($_SESSION['role'] ?? null);
    return normalize_role_name($role) === 'company_admin';
}

function current_user_company_id() {
    return (int) ($_SESSION['company_id'] ?? 0);
}

function get_manageable_roles($user = null) {
    $role = normalize_role_name(is_array($user) ? ($user['role'] ?? null) : ($_SESSION['role'] ?? null));

    return match ($role) {
        'super_admin' => ['company_admin', 'manager', 'user'],
        'company_admin' => ['manager', 'user'],
        'manager' => ['user'],
        default => [],
    };
}

function get_accessible_company_ids($user = null) {
    $actor = is_array($user) ? $user : current_user();
    if (!$actor) {
        return [];
    }

    if (is_super_admin($actor)) {
        $rows = db_fetch_all("SELECT id FROM companies");
        return array_map('intval', array_column($rows, 'id'));
    }

    $actor_company_id = (int) ($actor['company_id'] ?? 0);
    if ($actor_company_id <= 0) {
        return [];
    }

    $company = db_fetch("SELECT id, type FROM companies WHERE id = ?", [$actor_company_id]);
    if (!$company) {
        return [$actor_company_id];
    }

    $accessible_ids = [$actor_company_id];

    if (($company['type'] ?? '') === 'parent') {
        $subsidiaries = db_fetch_all("SELECT id FROM companies WHERE parent_id = ?", [$actor_company_id]);
        $accessible_ids = array_merge($accessible_ids, array_map('intval', array_column($subsidiaries, 'id')));
    }

    return array_values(array_unique(array_filter($accessible_ids)));
}

function can_access_company($company_id, $user = null) {
    $company_id = (int) $company_id;
    if ($company_id <= 0) {
        return false;
    }

    return in_array($company_id, get_accessible_company_ids($user), true);
}

function enforce_company_access($company_id, $redirect = null) {
    require_login();

    if (!can_access_company($company_id)) {
        set_flash('Access denied. You can only access data for your allowed company.', 'error');
        redirect($redirect ?: url('index.php'));
    }
}

function can_manage_user_role($target_role, $user = null) {
    return in_array(normalize_role_name($target_role), get_manageable_roles($user), true);
}

function can_manage_user_account($target_user, $user = null) {
    if (empty($target_user) || !is_array($target_user)) {
        return false;
    }

    $actor = $user ?? current_user();
    if (!$actor) {
        return false;
    }

    if (!is_super_admin($actor)) {
        $target_company_id = (int) ($target_user['company_id'] ?? 0);
        $accessible_company_ids = get_accessible_company_ids($actor);

        if ($target_company_id > 0 && !in_array($target_company_id, $accessible_company_ids, true)) {
            return false;
        }
    }

    return can_manage_user_role($target_user['role'] ?? 'user', $actor);
}

function can_assign_permissions_to_user($target_user, $user = null) {
    return can_manage_user_account($target_user, $user);
}

function require_super_admin() {
    require_login();

    if (!is_super_admin()) {
        set_flash('Access denied. Super Admin privileges required.', 'error');
        redirect(url('index.php'));
    }
}

function ensure_rbac_schema() {
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $initialized = true;

    try {
        db_query("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_permissions_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS role_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(50) NOT NULL,
            permission_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_role_permission (role, permission_id),
            INDEX idx_role_permissions_role (role),
            CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            granted_by INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_permission (user_id, permission_id),
            INDEX idx_user_permissions_user (user_id),
            CONSTRAINT fk_user_permissions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("CREATE TABLE IF NOT EXISTS permission_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            request_reason TEXT NULL,
            notes TEXT NULL,
            request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            admin_notes TEXT NULL,
            updated_at DATETIME NULL,
            INDEX idx_permission_requests_status (status),
            CONSTRAINT fk_permission_requests_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_permission_requests_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        db_query("ALTER TABLE user_permissions ADD COLUMN IF NOT EXISTS granted_by INT NULL AFTER permission_id");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS request_reason TEXT NULL AFTER status");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS notes TEXT NULL AFTER request_reason");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS admin_notes TEXT NULL AFTER notes");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER admin_notes");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS requester_seen_at DATETIME NULL AFTER updated_at");
        db_query("ALTER TABLE permission_requests ADD COLUMN IF NOT EXISTS admin_seen_at DATETIME NULL AFTER requester_seen_at");
        db_query("UPDATE permission_requests SET updated_at = request_date WHERE updated_at IS NULL");
        db_query("UPDATE permission_requests SET requester_seen_at = NOW() WHERE status != 'pending' AND requester_seen_at IS NULL AND updated_at = request_date");

        db_insert("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES ('last_permissions_update', '0')");

        $permissions = [
            'view_inventory' => 'View inventory items and stock levels',
            'manage_inventory' => 'Create, edit, and manage inventory records',
            'view_finance' => 'View finance data and reports',
            'manage_finance' => 'Create and manage finance transactions',
            'view_sales' => 'View sales records and orders',
            'manage_sales' => 'Create and manage sales records',
            'view_procurement' => 'View procurement documents and suppliers',
            'manage_procurement' => 'Create and manage procurement records',
            'view_production' => 'View production orders and BOM data',
            'manage_production' => 'Create and manage production orders',
            'view_projects' => 'View project information',
            'manage_projects' => 'Create and manage projects',
            'view_analytics' => 'View dashboards and analytics',
            'manage_companies' => 'Manage company records within allowed scope',
            'manage_users' => 'Create and manage users within allowed scope',
            'assign_user_permissions' => 'Assign custom permissions to lower-level users',
            'manage_permissions' => 'Maintain the global role permission matrix',
            'switch_company' => 'Switch active company context',
        ];

        foreach ($permissions as $name => $description) {
            db_insert(
                "INSERT IGNORE INTO permissions (name, description) VALUES (?, ?)",
                [$name, $description]
            );
        }

        $roleSeeds = [
            'company_admin' => [
                'view_inventory', 'manage_inventory',
                'view_finance', 'manage_finance', 'view_sales', 'manage_sales',
                'view_procurement', 'manage_procurement', 'view_production', 'manage_production',
                'view_projects', 'manage_projects', 'view_analytics',
                'manage_companies', 'manage_users', 'assign_user_permissions'
            ],
            'manager' => [
                'view_inventory', 'manage_inventory', 'view_sales', 'manage_sales',
                'view_production', 'manage_production', 'view_projects', 'view_analytics',
                'manage_users', 'assign_user_permissions'
            ],
            'user' => [
                'view_inventory', 'view_sales', 'view_production', 'view_projects', 'view_analytics'
            ],
        ];

        foreach ($roleSeeds as $role => $permissionNames) {
            foreach ($permissionNames as $permissionName) {
                db_insert(
                    "INSERT IGNORE INTO role_permissions (role, permission_id)
                     SELECT ?, id FROM permissions WHERE name = ?",
                    [$role, $permissionName]
                );
            }
        }

        unset($_SESSION['permissions'], $_SESSION['perms_version']);
    } catch (Exception $e) {
        log_error('RBAC bootstrap failed: ' . $e->getMessage());
    }
}

/**
 * Initialize session
 */
function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerate session ID periodically for security
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_unset();
            session_destroy();
            session_start();

            // Redirect to public root page after session expiry
            header("Location: " . BASE_URL . "/");
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Authenticate user login
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array|false User data or false on failure
 */
function authenticate_user($username, $password) {
    $sql = "SELECT id, username, email, password_hash, role, company_id 
            FROM users 
            WHERE username = ? AND " . users_active_sql() . " 
            LIMIT 1";
    
    $user = db_fetch($sql, [$username]);
    
    if ($user && verify_password($password, $user['password_hash'])) {
        // Update last login
        db_query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);
        return $user;
    }
    
    return false;
}

/**
 * Login user - create session
 * 
 * @param array $user User data
 */
function login_user($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = normalize_role_name($user['role'] ?? 'user');
    $_SESSION['company_id'] = $user['company_id'];
    $_SESSION['logged_in'] = true;
    
    // Regenerate session ID for security
    session_regenerate_id(true);
}

/**
 * Logout user - destroy session
 */
function logout_user() {
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Register new user
 * 
 * @param array $data User registration data
 * @return int|false User ID or false on failure
 */
function register_user($data) {
    try {
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
        $existing = db_fetch($check_sql, [$data['username'], $data['email']]);
        
        if ($existing) {
            return false;
        }
        
        $role = normalize_role_name($data['role'] ?? 'user');

        // Insert new user
        $sql = "INSERT INTO users (username, email, password_hash, role, company_id, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW())";
        
        $params = [
            $data['username'],
            $data['email'],
            hash_password($data['password']),
            $role,
            $data['company_id'] ?? null
        ];
        
        return db_insert($sql, $params);
        
    } catch (Exception $e) {
        log_error("User registration failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Change user password
 * 
 * @param int $user_id User ID
 * @param string $old_password Old password
 * @param string $new_password New password
 * @return bool Success status
 */
function change_password($user_id, $old_password, $new_password) {
    // Get current password hash
    $sql = "SELECT password_hash FROM users WHERE id = ?";
    $user = db_fetch($sql, [$user_id]);
    
    if (!$user || !verify_password($old_password, $user['password_hash'])) {
        return false;
    }
    
    // Update password
    $update_sql = "UPDATE users SET password_hash = ? WHERE id = ?";
    db_query($update_sql, [hash_password($new_password), $user_id]);
    
    return true;
}

/**
 * Get user by ID
 * 
 * @param int $user_id User ID
 * @return array|false User data
 */
function get_user_by_id($user_id) {
    $active_column = users_active_column_name();
    $sql = "SELECT id, username, email, role, company_id, {$active_column} AS is_active, created_at, last_login 
            FROM users 
            WHERE id = ?";
    return db_fetch($sql, [$user_id]);
}

/**
 * Get all active users
 * 
 * @return array List of users
 */
function get_all_users() {
    $active_column = users_active_column_name();
    $sql = "SELECT id, username, email, role, company_id, {$active_column} AS is_active, created_at 
            FROM users 
            WHERE " . users_active_sql() . " 
            ORDER BY username";
    return db_fetch_all($sql);
}

// Initialize session on file include
init_session();
ensure_rbac_schema();

/**
 * Check if current user is an administrator in the RBAC hierarchy.
 * Company Admin and Super Admin are treated as administrators.
 *
 * @return bool
 */
function is_admin() {
    return role_level($_SESSION['role'] ?? null) >= role_level('manager');
}

/**
 * Get global permissions version from database
 */
function get_global_permissions_version() {
    $sql = "SELECT setting_value FROM system_settings WHERE setting_key = 'last_permissions_update' LIMIT 1";
    $res = db_fetch($sql);
    return $res['setting_value'] ?? '0';
}

/**
 * Update global permissions version to trigger refresh for all users
 */
function update_global_permissions_version() {
    $new_ver = time();
    db_query("UPDATE system_settings SET setting_value = ? WHERE setting_key = 'last_permissions_update'", [$new_ver]);
    return $new_ver;
}

/**
 * Refresh permissions in session from database
 */
function refresh_user_permissions() {
    if (!isset($_SESSION['user_id'])) {
        return;
    }

    $raw_role = strtolower(trim((string) ($_SESSION['role'] ?? 'user')));
    $role = normalize_role_name($raw_role);
    $user_id = (int) $_SESSION['user_id'];

    try {
        if ($role === 'super_admin') {
            $all_rows = db_fetch_all("SELECT name FROM permissions ORDER BY name");
            $_SESSION['permissions'] = array_column($all_rows, 'name');
            $_SESSION['perms_version'] = get_global_permissions_version();
            return;
        }

        $role_rows = db_fetch_all(
            "SELECT DISTINCT p.name
             FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role IN (?, ?)",
            [$role, $raw_role]
        );

        $user_rows = db_fetch_all(
            "SELECT p.name
             FROM permissions p
             JOIN user_permissions up ON p.id = up.permission_id
             WHERE up.user_id = ?",
            [$user_id]
        );

        $all_perms = array_merge(
            array_column($role_rows, 'name'),
            array_column($user_rows, 'name')
        );

        $_SESSION['permissions'] = array_values(array_unique($all_perms));
        $_SESSION['perms_version'] = get_global_permissions_version();
    } catch (Exception $e) {
        log_error('Failed to load permissions: ' . $e->getMessage());
        $_SESSION['permissions'] = [];
    }
}

/**
 * Check if the current user has a specific permission.
 * Super Admin has full access by default.
 */
function has_permission($permission) {
    if (is_super_admin()) {
        return true;
    }

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $permission = trim((string) $permission);
    if ($permission === '') {
        return false;
    }

    $session_ver = $_SESSION['perms_version'] ?? '0';
    $global_ver = get_global_permissions_version();

    if (!isset($_SESSION['permissions']) || $session_ver !== $global_ver) {
        refresh_user_permissions();
    }

    return in_array($permission, $_SESSION['permissions'] ?? [], true);
}

/**
 * Require administrator role.
 */
function require_admin() {
    require_login();
    if (!is_admin()) {
        set_flash('Access Denied. Administrator privileges required.', 'error');
        redirect(url('index.php'));
    }
}

/**
 * Require specific permission.
 * Redirects to dashboard if user lacks permission.
 *
 * @param string $permission Permission name
 */
function require_permission($permission) {
    require_login();
    if (!has_permission($permission)) {
        set_flash('Access Denied. You do not have the required permission.', 'error');
        redirect(url('index.php'));
    }
}
?>
