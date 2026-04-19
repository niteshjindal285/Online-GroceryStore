<?php
/**
 * AJAX - Get Unique Bin Locations for a Warehouse
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);
$location_id = (int)($_GET['location_id'] ?? 0);

if (!$warehouse_id && !$location_id) {
    echo json_encode([]);
    exit;
}

// Fetch unique bin locations from bins table for this location/warehouse
$sql = "
    SELECT b.id as bin_id, b.code as bin_location 
    FROM bins b 
    WHERE b.is_active = 1
";
$params = [];

if ($warehouse_id) {
    $sql .= " AND b.warehouse_id = ?";
    $params[] = $warehouse_id;
} else if ($location_id) {
    $sql .= " AND b.warehouse_id = (SELECT id FROM warehouses WHERE location_id = ? LIMIT 1)";
    $params[] = $location_id;
}

$sql .= " ORDER BY b.code";

$bins = db_fetch_all($sql, $params);

echo json_encode($bins);
exit;
