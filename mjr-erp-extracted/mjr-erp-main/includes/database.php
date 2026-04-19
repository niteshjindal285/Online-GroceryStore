<?php
/**
 * Database Connection Handler
 * 
 * Provides PDO database connection with error handling
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Check whether a table contains a given column.
 */
function db_table_has_column($table, $column) {
    static $cache = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        return false;
    }

    $cache_key = $table . '.' . $column;
    if (array_key_exists($cache_key, $cache)) {
        return $cache[$cache_key];
    }

    try {
        // Do not use ? binding for SHOW statements as some PDO drivers reject it
        $clean_column = addslashes($column);
        $row = db_fetch("SHOW COLUMNS FROM `$table` LIKE '$clean_column'");
        $cache[$cache_key] = !empty($row);
    } catch (Throwable $e) {
        $cache[$cache_key] = false;
    }

    return $cache[$cache_key];
}

/**
 * Check whether a table exists in the active database.
 */
function db_table_exists($table) {
    static $cache = [];

    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        return false;
    }

    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        // Do not use ? binding for SHOW statements as some PDO drivers reject it
        $clean_table = addslashes($table);
        $row = db_fetch("SHOW TABLES LIKE '$clean_table'");
        $cache[$table] = !empty($row);
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

/**
 * Resolve the active-status column used by the users table across old/new schemas.
 */
function users_active_column_name() {
    static $column = null;

    if ($column !== null) {
        return $column;
    }

    foreach (['is_active', 'active'] as $candidate) {
        if (db_table_has_column('users', $candidate)) {
            $column = $candidate;
            return $column;
        }
    }

    $column = 'is_active';
    return $column;
}

/**
 * Build an SQL predicate that checks whether a user row is active.
 */
function users_active_sql($alias = '') {
    $column = users_active_column_name();
    $alias = trim((string) $alias);

    if ($alias !== '' && preg_match('/^[A-Za-z0-9_]+$/', $alias)) {
        return "`{$alias}`.`{$column}` = 1";
    }

    return "`{$column}` = 1";
}

/**
 * Return ordered, unique connection candidates from defaults and env.
 */
function db_connection_candidates() {
    $hosts = [];
    $configured_host = trim((string) DB_HOST);

    if ($configured_host !== '') {
        foreach (preg_split('/\s*,\s*/', $configured_host) as $host_item) {
            $host_item = trim($host_item);
            if ($host_item !== '') {
                $hosts[] = $host_item;
            }
        }
    }

    $primary_host = $hosts[0] ?? '';
    if ($primary_host === 'localhost') {
        $hosts[] = '127.0.0.1';
    } elseif ($primary_host === '127.0.0.1') {
        $hosts[] = 'localhost';
    } else {
        $hosts[] = '127.0.0.1';
        $hosts[] = 'localhost';
    }

    if (file_exists('/.dockerenv') || strpos($_SERVER['DOCUMENT_ROOT'] ?? '', '/var/www/html') !== false) {
        $hosts[] = 'db';
        $hosts[] = 'mysql';
    }

    $db_names = [
        trim((string) DB_NAME),
        trim((string) getenv('DB_NAME_FALLBACK')),
        'kustom_mjr',
        'mjr_erp',
        'mjr_group_erp',
    ];

    $users = [
        trim((string) DB_USER),
        trim((string) getenv('DB_USER_FALLBACK')),
        'root',
    ];

    $passwords = [
        (string) DB_PASS,
        (string) getenv('DB_PASS_FALLBACK'),
        '',
        'Admin@1234',
    ];

    $normalize = function ($values, $allow_empty = false) {
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value === '' && !$allow_empty) {
                continue;
            }
            $out[] = $value;
        }
        return array_values(array_unique($out, SORT_REGULAR));
    };

    return [
        'hosts' => $normalize($hosts),
        'db_names' => $normalize($db_names),
        'users' => $normalize($users),
        'passwords' => $normalize($passwords, true),
    ];
}

/**
 * Get database connection
 * 
 * @param bool $force_new Force a new connection (reset static cache)
 * @return PDO Database connection object
 * @throws Exception
 */
function db_connect($force_new = false) {
    static $pdo = null;
    
    if ($pdo !== null && !$force_new) {
        return $pdo;
    }
    
    $candidates = db_connection_candidates();
    $hosts = $candidates['hosts'];
    $db_names = $candidates['db_names'];
    $users = $candidates['users'];
    $passwords = $candidates['passwords'];

    $last_exception = null;
    $attempted = [];

    foreach ($hosts as $host) {
        foreach ($db_names as $db_name) {
            foreach ($users as $user) {
                foreach ($passwords as $password) {
                    $attempted[] = $host . '/' . $db_name . '/' . $user;
                    try {
                        $dsn = DB_DRIVER . ":host=" . $host . ";port=" . DB_PORT . ";dbname=" . $db_name . ";charset=" . DB_CHARSET;
                        $options = [
                            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                            PDO::ATTR_EMULATE_PREPARES   => false,
                            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                            PDO::ATTR_TIMEOUT            => 2
                        ];

                        $pdo = new PDO($dsn, $user, $password, $options);
                        return $pdo;

                    } catch (PDOException $e) {
                        $last_exception = $e;
                        continue;
                    }
                }
            }
        }
    }

    $error_msg = $last_exception ? $last_exception->getMessage() : "Unknown connection error";
    $error_code = $last_exception ? $last_exception->getCode() : 0;
    $attempted_str = implode(', ', array_slice(array_unique($attempted), 0, 12));
    
    error_log("Database connection failed after trying [$attempted_str]: [{$error_code}] {$error_msg}");
    
    throw new Exception("Database connection failed. Tried hosts: [$attempted_str]. Last Error: {$error_msg}");
}

/**
 * Handle database error consistently
 * Logs raw error and throws sanitized Exception
 */
function handle_db_error($e, $sql = '') {
    // Log raw error for debugging
    $raw_msg = "Database Error: " . $e->getMessage() . ($sql ? " | SQL: " . $sql : "");
    if (function_exists('log_error')) {
        log_error($raw_msg);
    } else {
        error_log($raw_msg);
    }

    // Always sanitize the error message thrown to the application
    $msg = $e->getMessage();
    if (function_exists('sanitize_db_error')) {
        $msg = sanitize_db_error($msg);
    } else {
        // Fallback if functions.php is not loaded
        $msg = "A database error occurred. Please try again or contact support.";
    }
    
    throw new Exception($msg);
}

/**
 * Execute a query
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters for prepared statement
 * @return PDOStatement
 */
function db_query($sql, $params = []) {
    try {
        $pdo = db_connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (Throwable $e) {
        // Handle "MySQL server has gone away" (2006) or "Lost connection" (2013)
        $is_lost_conn = ($e instanceof PDOException) &&
            (strpos($e->getMessage(), '2006') !== false || strpos($e->getMessage(), '2013') !== false);
        if ($is_lost_conn && !db_in_transaction()) {
            try {
                $pdo = db_connect(true);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (Throwable $e2) {
                handle_db_error($e2, $sql);
            }
        }
        
        handle_db_error($e, $sql);
    }
}

/**
 * Fetch single row
 */
function db_fetch($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetch();
}

/**
 * Fetch all rows
 */
function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert record and return last insert ID
 */
function db_insert($sql, $params = []) {
    try {
        $pdo = db_connect();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    } catch (Throwable $e) {
        // Handle reconnection
        $is_lost_conn = ($e instanceof PDOException) &&
            (strpos($e->getMessage(), '2006') !== false || strpos($e->getMessage(), '2013') !== false);
        if ($is_lost_conn) {
            try {
                $pdo = db_connect(true);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $pdo->lastInsertId();
            } catch (Throwable $e2) {
                handle_db_error($e2, $sql);
            }
        }

        handle_db_error($e, $sql);
    }
}

/**
 * Execute update/delete and return affected rows
 */
function db_execute($sql, $params = []) {
    $stmt = db_query($sql, $params);
    return $stmt->rowCount();
}

/**
 * Get the last inserted ID
 */
function db_insert_id() {
    return db_connect()->lastInsertId();
}

/**
 * Transaction management
 */
function db_begin_transaction() {
    $pdo = db_connect();
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }
}

function db_commit() {
    $pdo = db_connect();
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
}

function db_rollback() {
    $pdo = db_connect();
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

function db_in_transaction() {
    return db_connect()->inTransaction();
}
?>
