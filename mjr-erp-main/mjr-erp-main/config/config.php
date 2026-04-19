<?php
/**
 * MJR Group ERP - Database Configuration
 * 
 * MySQL database configuration using PDO
 */

// Database Configuration
define('DB_DRIVER', 'mysql');
// Use environment variables if set, fallback to Docker 'db' host if in container, else localhost
define('DB_HOST', getenv('DB_HOST') ?: (file_exists('/.dockerenv') ? 'db' : 'localhost'));
define('DB_NAME', getenv('DB_NAME') ?: 'mjr_erp');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'Admin@1234');
define('DB_PORT', getenv('DB_PORT') ?: 3306);
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'MJR Group ERP');
define('APP_VERSION', '1.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('BASE_URL', getenv('BASE_URL') ?: 'http://89.167.47.67/mjr-erp/public');
define('ASSETS_URL', BASE_URL . '/assets');

// Session Configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'mjr_erp_session');

// Security Configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_HASH_ALGO', PASSWORD_DEFAULT);

// File Upload Configuration
define('UPLOAD_DIR', '../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Pagination
define('ITEMS_PER_PAGE', 50);

// Date/Time Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd-m-Y');
define('DISPLAY_DATETIME_FORMAT', 'd-m-Y h:i A');

// Currency Configuration
define('DEFAULT_CURRENCY', 'TOP');
define('CURRENCY_SYMBOL', 'T$');
define('CURRENCY_DISPLAY', 'TOP');


// Error Reporting
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Timezone
date_default_timezone_set('Pacific/Tongatapu');
?>
