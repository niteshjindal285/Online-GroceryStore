<?php
/**
 * AJAX - Get all unique bins across all warehouses
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Fetch unique bins grouped by warehouse
$sql = "
    SELECT w.id as warehouse_id, w.name as warehouse_name, b.id as bin_id, b.code as bin_location 
    FROM bins b 
    JOIN warehouses w ON b.warehouse_id = w.id 
    WHERE b.is_active = 1
    ORDER BY w.name, b.code
";

$bins = db_fetch_all($sql);

echo json_encode($bins);
exit;
