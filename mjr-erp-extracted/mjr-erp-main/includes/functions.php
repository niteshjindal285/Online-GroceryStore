<?php
/**
 * Utility Functions
 * 
 * Common utility functions for the ERP system
 */

/**
 * Sanitize input data
 * 
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * 
 * @param string $email Email address
 * @return bool
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure password hash
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hash_password($password)
{
    return password_hash($password, PASSWORD_HASH_ALGO);
}

/**
 * Verify password against hash
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool
 */
function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generate_csrf_token()
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool
 */
function verify_csrf_token($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Redirect to URL
 * 
 * @param string $url URL to redirect to
 * @param int $status_code HTTP status code
 */
function redirect($url, $status_code = 302)
{
    header("Location: $url", true, $status_code);
    exit();
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user ID
 * 
 * @return int|null
 */
function current_user_id()
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 * 
 * @return array|null
 */
function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $sql = "SELECT id, username, email, role, company_id FROM users WHERE id = ? AND " . users_active_sql();
        $user = db_fetch($sql, [current_user_id()]);

        if ($user && function_exists('normalize_role_name')) {
            $user['role'] = normalize_role_name($user['role']);
        }
    }
    return $user;
}

/**
 * Get the active company ID from the current session context.
 * This changes when a Super Admin switches company from the header.
 *
 * @param int|null $fallback Optional fallback value.
 * @return int
 */
function active_company_id($fallback = null)
{
    // Try session first (set by company switcher)
    $session_company_id = (int) ($_SESSION['company_id'] ?? 0);
    if ($session_company_id > 0) {
        return $session_company_id;
    }

    // Fallback to user's assigned company
    $user = current_user();
    $user_company_id = (int) ($user['company_id'] ?? 0);
    if ($user_company_id > 0) {
        return $user_company_id;
    }

    return (int) ($fallback ?? 0);
}

/**
 * Generate SQL WHERE clause for company filtering
 * 
 * @param string $table_alias Table alias (optional)
 * @param string $column_name Column name (default: company_id)
 * @param bool $force_numeric Force (int) cast of active_company_id
 * @return string SQL fragment like " AND alias.company_id = 5 "
 */
function db_where_company($table_alias = '', $column_name = 'company_id', $force_numeric = true)
{
    $cid = active_company_id();
    if (!$cid) return " AND 1=1 "; // No filter if no company selected (e.g. initial setup)
    
    $prefix = $table_alias ? "$table_alias." : "";
    return " AND $prefix$column_name = " . (int)$cid . " ";
}

/**
 * Ensure the suppliers table has a company_id column and return whether it exists.
 *
 * @return bool
 */
function suppliers_table_has_company_id()
{
    static $has_company = null;

    if ($has_company !== null) {
        return $has_company;
    }

    if (!db_table_exists('suppliers')) {
        return $has_company = false;
    }

    try {
        $row = db_fetch("SHOW COLUMNS FROM `suppliers` LIKE 'company_id'");
        if (!empty($row)) {
            return $has_company = true;
        }

        // Add the company_id column for supplier records.
        // NOTE: Do NOT auto-assign existing rows — admins must assign them
        // explicitly via fix_supplier_company.php to avoid cross-company data mixing.
        db_query("ALTER TABLE suppliers ADD COLUMN company_id INT NULL");

        return $has_company = true;
    } catch (Throwable $e) {
        return $has_company = false;
    }
}

/**
 * Get the active company name from the current session context.
 *
 * @param string $fallback Optional fallback label.
 * @return string
 */
function active_company_name($fallback = 'Selected Company')
{
    return (string) ($_SESSION['company_name'] ?? $fallback);
}

/**
 * Check if the currently active company is the Parent/HQ company.
 *
 * @return bool
 */
function is_current_company_parent()
{
    static $is_parent = null;
    if ($is_parent !== null) return $is_parent;

    $cid = active_company_id();
    if (!$cid) return false;

    $comp = db_fetch("SELECT type FROM companies WHERE id = ?", [$cid]);
    return $is_parent = (($comp['type'] ?? '') === 'parent');
}

/**
 * Alias for is_current_company_parent()
 */
function is_hq()
{
    return is_current_company_parent();
}


/**
 * Check if customers table has the new discount columns
 * 
 * @return bool
 */
function customers_table_has_discounts()
{
    static $has = null;
    if ($has !== null) return $has;
    
    // We only check for one, assuming both are added together
    return $has = db_table_has_column('customers', 'default_discount_percent');
}

/**
 * Check if user has specific role
 * 
 * @param string $role Role name
 * @return bool
 */
function has_role($role)
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    $current_role = function_exists('normalize_role_name')
        ? normalize_role_name($user['role'] ?? 'user')
        : ($user['role'] ?? 'user');

    $required_role = function_exists('normalize_role_name')
        ? normalize_role_name($role)
        : $role;

    if (function_exists('role_level')) {
        if (strtolower((string) $role) === 'admin' || in_array($required_role, ['company_admin', 'super_admin'], true)) {
            return role_level($current_role) >= role_level('company_admin');
        }

        return role_level($current_role) >= role_level($required_role);
    }

    return $current_role === $required_role;
}

/**
 * Require login - redirect to login page if not logged in
 */
function require_login()
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('login.php');
    }
}

/**
 * Require specific role
 * 
 * @param string $role Required role
 */
function require_role($role)
{
    require_login();
    if (!has_role($role)) {
        die("Access denied. You don't have permission to access this page.");
    }
}

/**
 * Format date
 * 
 * @param string $date Date string
 * @param string $format Output format
 * @return string Formatted date
 */
function format_date($date, $format = DISPLAY_DATE_FORMAT)
{
    if (empty($date))
        return '';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * Convert display date (DD-MM-YYYY) back to database format (YYYY-MM-DD)
 * 
 * @param string $date Display date string
 * @return string|null Database formatted date or null if invalid
 */
function to_db_date($date)
{
    if (empty($date)) return null;
    
    // Check if it's already in Y-m-d format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    
    // Try to parse DD-MM-YYYY or DD/MM/YYYY or DD MM YYYY
    if (preg_match('/^(\d{1,2})[-\/\s](\d{1,2})[-\/\s](\d{4})$/', $date, $matches)) {
        return $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
    }
    
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

/**
 * Format datetime
 * 
 * @param string $datetime Datetime string
 * @param string $format Output format
 * @return string Formatted datetime
 */
function format_datetime($datetime, $format = DISPLAY_DATETIME_FORMAT)
{
    if (empty($datetime))
        return '';
    $timestamp = strtotime($datetime);
    return $timestamp ? date($format, $timestamp) : '';
}

/**
 * Format currency
 * 
 * @param float $amount Amount
 * @param int $decimals Number of decimal places
 * @return string Formatted currency
 */
function format_currency($amount, $currency = null, $decimals = 2)
{
    if ($amount === null || $amount === '') {
        $amount = 0;
    }

    // Use system default currency if none specified
    if ($currency === null || $currency === 'USD') {
        $currency = defined('DEFAULT_CURRENCY') ? DEFAULT_CURRENCY : 'TOP';
    }

    $symbols = [
        'TOP' => 'T$',   // Tongan Pa\'anga
        'USD' => '$',
        'AUD' => 'A$',
        'NZD' => 'NZ$',
        'FJD' => 'FJ$',
        'PGK' => 'K',    // Papua New Guinea Kina
        'SBD' => 'SI$',  // Solomon Islands Dollar
        'WST' => 'WS$',  // Samoan Tala
        'VUV' => 'Vt',   // Vanuatu Vatu
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
    ];

    $fallback = defined('CURRENCY_SYMBOL') ? CURRENCY_SYMBOL : 'T$';
    $symbol   = $symbols[strtoupper($currency)] ?? $fallback;

    return $symbol . number_format((float) $amount, $decimals, '.', ',');
}

/**
 * Format number
 * 
 * @param float $number Number
 * @param int $decimals Number of decimal places
 * @return string Formatted number
 */
function format_number($number, $decimals = 2)
{
    if ($number === null || $number === '') {
        return number_format(0, $decimals, '.', ',');
    }
    return number_format((float) $number, $decimals, '.', ',');
}

/**
 * Set flash message
 * 
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function set_flash($message, $type = 'info')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 * 
 * @return array|null Flash message array [message, type]
 */
function get_flash()
{
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type']
        ];
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return $flash;
    }
    return null;
}

/**
 * Generate unique code
 * 
 * @param string $prefix Prefix for code
 * @param int $length Length of numeric part
 * @return string Generated code
 */
function generate_code($prefix, $length = 6)
{
    return $prefix . str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Paginate results
 * 
 * @param int $total_items Total number of items
 * @param int $page Current page
 * @param int $per_page Items per page
 * @return array Pagination data
 */
function paginate($total_items, $page = 1, $per_page = ITEMS_PER_PAGE)
{
    $total_pages = ceil($total_items / $per_page);
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $per_page;

    return [
        'total_items' => $total_items,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'per_page' => $per_page,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $total_pages
    ];
}

/**
 * Escape HTML for output
 * 
 * @param string $text Text to escape
 * @return string Escaped text
 */
function escape_html($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate URL
 * 
 * @param string $path Path relative to public directory
 * @param array $params Query parameters
 * @return string Full URL
 */
function url($path = '', $params = [])
{
    $url = BASE_URL . '/' . ltrim($path, '/');
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    return $url;
}

/**
 * Asset URL
 * 
 * @param string $path Path to asset
 * @return string Asset URL
 */
function asset($path)
{
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Debug dump (only in development)
 * 
 * @param mixed $var Variable to dump
 */
function dd($var)
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * Log error to file
 * 
 * @param string $message Error message
 * @param string $file Log file name
 */
function log_error($message, $file = 'php_server.log')
{
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/' . $file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Sanitize database error messages for user display
 * 
 * @param string $message Raw error message
 * @return string User-friendly English message
 */
function sanitize_db_error($message)
{
    // If it's a known database error pattern, return a clean sentence
    $db_patterns = [
        'SQLSTATE',
        'Unknown column',
        'field list',
        'Duplicate entry',
        'foreign key constraint',
        'Syntax error',
        'Table \'.*\' doesn\'t exist',
        'Access denied for user',
        'Lost connection',
        'Column .* cannot be null',
        'Data truncation',
        'Out of range value',
        'Incorrect integer value',
        'Incorrect decimal value',
        'Cannot add or update a child row',
        'Cannot delete or update a parent row'
    ];

    foreach ($db_patterns as $pattern) {
        if (stripos($message, $pattern) !== false) {
            return "A system error occurred. Please contact the administration.";
        }
    }

    // Secondary check for the SQLSTATE prefix which is very common in raw PDO errors
    if (stripos($message, 'SQLSTATE[') !== false) {
        return "A system error occurred. Please contact the administration.";
    }

    // If no db error pattern was matched, return the original message
    return $message;
}

/**
 * Standardized "Required Field" error message
 */
function err_required($field_name = '') {
    return "Please fill that section";
}

/**
 * Get request method
 * 
 * @return string Request method (GET, POST, etc.)
 */
function request_method()
{
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Check if request is POST
 * 
 * @return bool
 */
function is_post()
{
    return request_method() === 'POST';
}

/**
 * Check if request is GET
 * 
 * @return bool
 */
function is_get()
{
    return request_method() === 'GET';
}

/**
 * Get POST data
 * 
 * @param string $key Key name
 * @param mixed $default Default value
 * @return mixed
 */
function post($key, $default = null)
{
    return $_POST[$key] ?? $default;
}

/**
 * Get GET data
 * 
 * @param string $key Key name
 * @param mixed $default Default value
 * @return mixed
 */
function get_param($key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Get GET data (alias for get_param)
 * 
 * @param string $key Key name
 * @param mixed $default Default value
 * @return mixed
 */
function get($key, $default = null)
{
    return $_GET[$key] ?? $default;
}

/**
 * Check if value exists in POST
 * 
 * @param string $key Key name
 * @return bool
 */
function has_post($key)
{
    return isset($_POST[$key]);
}

/**
 * Log action in purchase order history
 */
function log_po_history($po_id, $status, $notes = null)
{
    $user_id = current_user_id();
    $sql = "INSERT INTO po_history (po_id, status, notes, changed_by) VALUES (?, ?, ?, ?)";
    return db_query($sql, [$po_id, $status, $notes, $user_id]);
}

/**
 * Handle automated PO email sending to supplier
 */
function send_po_to_supplier($po_id)
{
    // Get PO details
    $po = db_fetch("
        SELECT po.*, s.name as supplier_name, s.email as supplier_email
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        WHERE po.id = ?
    ", [$po_id]);

    if (!$po || empty($po['supplier_email'])) {
        return false;
    }

    $to = $po['supplier_email'];
    $subject = "Purchase Order Approved - " . $po['po_number'];

    // Prepare HTML Message
    $html_message = "
    <html>
    <body>
        <h2>Purchase Order Approved</h2>
        <p>Dear " . htmlspecialchars($po['supplier_name']) . ",</p>
        <p>Your Purchase Order <strong>" . htmlspecialchars($po['po_number']) . "</strong> has been approved and is now being processed.</p>
        <p>Total Amount: " . format_currency($po['total_amount'], $po['currency_code'] ?? 'USD') . "</p>
        <p>Thank you for your partnership.</p>
    </body>
    </html>";

    // Send using PHPMailer
    require_once __DIR__ . '/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/email_config.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html_message;
        $mail->AltBody = strip_tags($html_message);

        $mail->send();
        return true;
    } catch (Exception $e) {
        log_error("Automated PO Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Calculate percentage
 * 
 * @param float $part Part value
 * @param float $total Total value
 * @param int $decimals Decimal places
 * @return float Percentage
 */
function calculate_percentage($part, $total, $decimals = 2)
{
    if ($total == 0)
        return 0;
    return round(($part / $total) * 100, $decimals);
}

/**
 * Get pending approvals for a manager
 * 
 * @param int $user_id User ID of the manager
 * @return array List of pending approvals
 */
function get_pending_approvals($user_id)
{
    if (!$user_id) return [];

    $results = [];

    // 1. GSRN Approvals
    $gsrns = db_fetch_all("
        SELECT id, gsrn_number as reference, 'GSRN' as type, created_at, 'inventory/gsrn/view_gsrn.php' as view_url 
        FROM gsrn_headers 
        WHERE status = 'pending_approval' AND manager_id = ?
    ", [$user_id]);
    foreach ($gsrns as $g) $results[] = $g;

    // 2. Purchase Orders
    $pos = db_fetch_all("
        SELECT id, po_number as reference, 'Purchase Order' as type, submitted_at as created_at, 'inventory/purchase_order/view_purchase_order.php' as view_url 
        FROM purchase_orders 
        WHERE status = 'pending_approval' AND manager_id = ?
    ", [$user_id]);
    foreach ($pos as $p) $results[] = $p;

    // 3. Stock Transfers
    $transfers = db_fetch_all("
        SELECT id, transfer_number as reference, 'Stock Transfer' as type, created_at, 'inventory/view_transfer.php' as view_url 
        FROM transfer_headers 
        WHERE status = 'pending_approval' AND manager_id = ?
    ", [$user_id]);
    foreach ($transfers as $t) $results[] = $t;

    // 4. Backlog Orders
    $backlogs = db_fetch_all("
        SELECT id, backlog_number as reference, 'Backlog Order' as type, created_at, 'inventory/view_backlog_order.php' as view_url 
        FROM backlog_orders 
        WHERE status = 'Pending' AND manager_id = ?
    ", [$user_id]);
    foreach ($backlogs as $b) $results[] = $b;

    // 5. Stock Takes (Managers see all pending)
    if (has_role('manager') || has_role('admin')) {
        $stock_take_sql = "
            SELECT st.id, st.stock_take_number as reference, 'Stock Take' as type, st.created_at, 'inventory/stock_take/view.php' as view_url
            FROM stock_take_headers st
            JOIN locations l ON st.location_id = l.id
            WHERE st.status = 'pending_approval'
        ";
        $stock_take_params = [];
        $company_id = (int) active_company_id();
        if ($company_id > 0) {
            $stock_take_sql .= " AND l.company_id = ?";
            $stock_take_params[] = $company_id;
        }
        $stock_takes = db_fetch_all($stock_take_sql, $stock_take_params);
        foreach ($stock_takes as $st) $results[] = $st;
    }

    // 6. Permission Requests for users who can manage them
    if (is_admin() || has_permission('assign_user_permissions') || has_permission('manage_permissions')) {
        $permission_requests = db_fetch_all("
            SELECT pr.id, p.name as permission_name, u.id as request_user_id, u.username, u.role, u.company_id,
                   COALESCE(pr.updated_at, pr.request_date) as created_at
            FROM permission_requests pr
            JOIN permissions p ON pr.permission_id = p.id
            JOIN users u ON pr.user_id = u.id
            WHERE pr.status = 'pending'
            ORDER BY COALESCE(pr.updated_at, pr.request_date) DESC
        ");

        foreach ($permission_requests as $request) {
            if (!can_assign_permissions_to_user([
                'id' => $request['request_user_id'],
                'role' => $request['role'],
                'company_id' => $request['company_id'],
            ])) {
                continue;
            }

            $results[] = [
                'id' => $request['id'],
                'reference' => $request['username'] . ' -> ' . $request['permission_name'],
                'type' => 'Permission Request',
                'created_at' => $request['created_at'],
                'view_url' => 'admin/permissions.php',
                'params' => ['tab' => 'requests'],
            ];
        }
    }

    // 7. Finance - Journal Entries
    $journal_entries = db_fetch_all("
        SELECT id, entry_number as reference, 'Journal Entry' as type, created_at, 'finance/view_journal_entry.php' as view_url 
        FROM journal_entries 
        WHERE status = 'draft' AND (
            (approval_type IN ('manager', 'both') AND manager_id = ? AND manager_approved_at IS NULL) OR
            (approval_type IN ('admin', 'both')   AND admin_id = ? AND admin_approved_at IS NULL)
        )
    ", [$user_id, $user_id]);
    foreach ($journal_entries as $je) $results[] = $je;

    // 8. Finance - Receipts
    $receipts = db_fetch_all("
        SELECT id, receipt_number as reference, 'Receipt' as type, created_at, 'finance/view_receipt.php' as view_url 
        FROM receipts 
        WHERE status = 'draft' AND (
            (approval_type IN ('manager', 'both') AND manager_id = ? AND manager_approved_at IS NULL) OR
            (approval_type IN ('admin', 'both')   AND admin_id = ? AND admin_approved_at IS NULL)
        )
    ", [$user_id, $user_id]);
    foreach ($receipts as $rc) $results[] = $rc;

    // 9. Finance - Payroll Runs
    $payroll = db_fetch_all("
        SELECT id, run_reference as reference, 'Payroll Run' as type, created_at, 'finance/view_payroll.php' as view_url 
        FROM payroll_runs 
        WHERE status = 'draft' AND (
            (approval_type IN ('manager', 'both') AND manager_id = ? AND manager_approved_at IS NULL) OR
            (approval_type IN ('admin', 'both')   AND admin_id = ? AND admin_approved_at IS NULL)
        )
    ", [$user_id, $user_id]);
    foreach ($payroll as $pr) $results[] = $pr;

    // Sort by created_at DESC
    usort($results, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    return $results;
}

function get_permission_request_updates($user_id, $mark_seen = false)
{
    if (!$user_id) {
        return [];
    }

    $updates = db_fetch_all("
        SELECT pr.*, p.name as perm_name
        FROM permission_requests pr
        JOIN permissions p ON pr.permission_id = p.id
        WHERE pr.user_id = ?
          AND pr.status != 'pending'
          AND pr.requester_seen_at IS NULL
        ORDER BY COALESCE(pr.updated_at, pr.request_date) DESC
    ", [$user_id]);

    if ($mark_seen && !empty($updates)) {
        $ids = array_map(static fn($row) => (int) $row['id'], $updates);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        db_query("UPDATE permission_requests SET requester_seen_at = NOW() WHERE id IN ($placeholders)", $ids);
    }

    return $updates;
}

function mark_permission_request_updates_as_seen($user_id)
{
    if (!$user_id) {
        return 0;
    }

    return db_execute("
        UPDATE permission_requests
        SET requester_seen_at = NOW()
        WHERE user_id = ?
          AND status != 'pending'
          AND requester_seen_at IS NULL
    ", [$user_id]);
}

function mark_permission_request_update_as_seen($user_id, $request_id)
{
    if (!$user_id || !$request_id) {
        return 0;
    }

    return db_execute("
        UPDATE permission_requests
        SET requester_seen_at = NOW()
        WHERE id = ?
          AND user_id = ?
          AND status != 'pending'
          AND requester_seen_at IS NULL
    ", [(int) $request_id, $user_id]);
}

/**
 * Ensure finance approval columns exist for a table.
 * Adds lightweight approver assignment fields used by finance workflows.
 *
 * @param string $table
 * @return void
 */
function ensure_finance_approval_columns($table)
{
    static $checked = [];
    if (isset($checked[$table])) {
        return;
    }
    $checked[$table] = true;

    $allowed_tables = [
        'payment_vouchers',
        'receipts',
        'project_expenses',
        'payroll_runs',
        'debit_credit_notes',
        'accounts',
        'bank_accounts',
        'tax_configurations',
        'journal_entries',
    ];
    if (!in_array($table, $allowed_tables, true)) {
        return;
    }

    try {
        $cols = db_fetch_all("SHOW COLUMNS FROM `$table`");
        $existing = [];
        foreach ($cols as $c) {
            $existing[$c['Field']] = true;
        }

        $ddl_map = [
            'approval_type' => "ALTER TABLE `$table` ADD COLUMN approval_type ENUM('manager','admin','both') DEFAULT 'manager'",
            'manager_id' => "ALTER TABLE `$table` ADD COLUMN manager_id INT NULL",
            'admin_id' => "ALTER TABLE `$table` ADD COLUMN admin_id INT NULL",
            'manager_approved_at' => "ALTER TABLE `$table` ADD COLUMN manager_approved_at DATETIME NULL",
            'admin_approved_at' => "ALTER TABLE `$table` ADD COLUMN admin_approved_at DATETIME NULL",
        ];

        if (in_array($table, ['accounts', 'bank_accounts', 'tax_configurations'], true)) {
            $ddl_map['approval_status'] = "ALTER TABLE `$table` ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'pending'";
        }
        if ($table === 'payment_vouchers') {
            $ddl_map['cheque_number'] = "ALTER TABLE `$table` ADD COLUMN cheque_number VARCHAR(100) NULL";
            $ddl_map['invoice_attachment'] = "ALTER TABLE `$table` ADD COLUMN invoice_attachment VARCHAR(255) NULL";
        }
        if ($table === 'debit_credit_notes') {
            $ddl_map['note_attachment'] = "ALTER TABLE `$table` ADD COLUMN note_attachment VARCHAR(255) NULL";
        }

        foreach ($ddl_map as $field => $ddl) {
            if (!isset($existing[$field])) {
                db_query($ddl);
            }
        }
    } catch (Exception $e) {
        log_error("Finance approval schema check failed for {$table}: " . $e->getMessage());
    }
}

/**
 * Ensure receipt-to-invoice allocation table exists.
 * Used to map one receipt across one or more invoices.
 *
 * @return void
 */
function ensure_receipt_invoice_allocations_table()
{
    static $initialized = false;
    if ($initialized) {
        return;
    }
    $initialized = true;

    try {
        db_query("
            CREATE TABLE IF NOT EXISTS receipt_invoice_allocations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                receipt_id INT NOT NULL,
                invoice_id INT NOT NULL,
                allocated_amount DECIMAL(15,2) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_receipt_invoice (receipt_id, invoice_id),
                INDEX idx_ria_receipt (receipt_id),
                INDEX idx_ria_invoice (invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (Exception $e) {
        log_error('Receipt allocation schema check failed: ' . $e->getMessage());
    }
}

/**
 * Get active manager/admin users for approval assignment.
 *
 * @return array{managers: array, admins: array}
 */
function finance_get_approver_users($company_id = null)
{
    if ($company_id === null) {
        $company_id = (int) active_company_id();
    } else {
        $company_id = (int) $company_id;
    }

    if ($company_id <= 0) {
        $cu = current_user();
        $company_id = (int) ($cu['company_id'] ?? 0);
    }

    $company_sql = $company_id > 0 ? " AND company_id = ? " : "";
    $params = $company_id > 0 ? [$company_id] : [];

    $managers = db_fetch_all("
        SELECT id, username, full_name
        FROM users
        WHERE " . users_active_sql() . " AND LOWER(role) = 'manager'
        {$company_sql}
        ORDER BY COALESCE(NULLIF(full_name, ''), username)
    ", $params) ?: [];

    $admins = db_fetch_all("
        SELECT id, username, full_name
        FROM users
        WHERE " . users_active_sql() . " AND LOWER(role) IN ('admin', 'company_admin', 'super_admin')
        {$company_sql}
        ORDER BY COALESCE(NULLIF(full_name, ''), username)
    ", $params) ?: [];

    return ['managers' => $managers, 'admins' => $admins];
}

/**
 * Validate finance approval assignment rules.
 *
 * @param string $approval_type manager|admin|both
 * @param mixed $manager_id
 * @param mixed $admin_id
 * @return array
 */
function finance_validate_approval_setup($approval_type, $manager_id, $admin_id)
{
    $errors = [];
    $approval_type = strtolower((string)$approval_type);
    if (!in_array($approval_type, ['manager', 'admin', 'both'], true)) {
        $errors['approval_type'] = 'Invalid approval type.';
        return $errors;
    }

    if (($approval_type === 'manager' || $approval_type === 'both') && empty($manager_id)) {
        $errors['manager_id'] = 'Manager is required for this approval type.';
    }
    if (($approval_type === 'admin' || $approval_type === 'both') && empty($admin_id)) {
        $errors['admin_id'] = 'Admin is required for this approval type.';
    }

    return $errors;
}

/**
 * Apply one approval action for a finance record.
 * For "both", record is fully approved only after both manager/admin approvals exist.
 *
 * @param array $record
 * @param int $user_id
 * @return array{ok:bool,message:string,approved:bool,fields:array}
 */
function finance_process_approval_action(array $record, $user_id, $is_reject = false)
{
    $approval_type = strtolower((string)($record['approval_type'] ?? 'manager'));
    $manager_id = !empty($record['manager_id']) ? (int)$record['manager_id'] : null;
    $admin_id = !empty($record['admin_id']) ? (int)$record['admin_id'] : null;
    $manager_done = !empty($record['manager_approved_at']);
    $admin_done = !empty($record['admin_approved_at']);
    $user_id = (int)$user_id;
    $now = date('Y-m-d H:i:s');

    $is_manager_actor = ($manager_id && $user_id === $manager_id);
    $is_admin_actor = ($admin_id && $user_id === $admin_id);

    // If it's a reject, we mark it as rejected immediately by an assigned actor
    if ($is_reject) {
        if (!$is_manager_actor && !$is_admin_actor) {
            return ['ok' => false, 'approved' => false, 'message' => 'Only the assigned manager or admin can reject.', 'fields' => []];
        }
        return [
            'ok' => true, 
            'approved' => false, 
            'rejected' => true, 
            'message' => 'Record has been rejected.', 
            'fields' => ['approval_status' => 'rejected']
        ];
    }

    if ($approval_type === 'manager') {
        if (!$manager_id) {
            return ['ok' => false, 'approved' => false, 'message' => 'No manager is assigned for approval.', 'fields' => []];
        }
        if (!$is_manager_actor) {
            return ['ok' => false, 'approved' => false, 'message' => 'Only the assigned manager can approve.', 'fields' => []];
        }
        return ['ok' => true, 'approved' => true, 'message' => 'Manager approval completed.', 'fields' => ['manager_approved_at' => $now]];
    }

    if ($approval_type === 'admin') {
        if (!$admin_id) {
            return ['ok' => false, 'approved' => false, 'message' => 'No admin is assigned for approval.', 'fields' => []];
        }
        if (!$is_admin_actor) {
            return ['ok' => false, 'approved' => false, 'message' => 'Only the assigned admin can approve.', 'fields' => []];
        }
        return ['ok' => true, 'approved' => true, 'message' => 'Admin approval completed.', 'fields' => ['admin_approved_at' => $now]];
    }

    // both
    if (!$manager_id || !$admin_id) {
        return ['ok' => false, 'approved' => false, 'message' => 'Both manager and admin must be assigned for approval.', 'fields' => []];
    }
    
    if (!$manager_done && $is_manager_actor) {
        $approved = $admin_done;
        return [
            'ok' => true,
            'approved' => $approved,
            'message' => $approved ? 'Manager approval completed.' : 'Manager approved. Waiting for admin approval.',
            'fields' => ['manager_approved_at' => $now]
        ];
    }

    if (!$admin_done && $is_admin_actor) {
        $approved = $manager_done;
        return [
            'ok' => true,
            'approved' => $approved,
            'message' => $approved ? 'Admin approval completed.' : 'Admin approved. Waiting for manager approval.',
            'fields' => ['admin_approved_at' => $now]
        ];
    }

    return ['ok' => false, 'approved' => false, 'message' => 'You are not assigned to approve this record or have already approved.', 'fields' => []];
}

/**
 * Get Bootstrap color class for various statuses
 */
function get_status_color($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'active':
        case 'open':
        case 'approved':
        case 'delivered':
        case 'paid':
        case 'closed':
        case 'healthy':
        case 'success':
            return 'success';
        case 'pending':
        case 'draft':
        case 'invoiced':
        case 'acceptable':
        case 'warning':
        case 'pending_discount':
            return 'warning';
        case 'cancelled':
        case 'rejected':
        case 'error':
        case 'danger':
        case 'low margin':
        case 'unpaid':
            return 'danger';
        case 'partial':
        case 'info':
            return 'info';
        case 'converted':
            return 'primary';
        default:
            return 'secondary';
    }
}

?>
