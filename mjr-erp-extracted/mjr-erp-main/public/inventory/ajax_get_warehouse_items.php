<?php
/**
 * AJAX - Get Items available in a specific Warehouse
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);

if (!$warehouse_id) {
    echo json_encode([]);
    exit;
}

// Fetch items that have stock in this location
// We check both inventory_stock_levels and warehouse_inventory for robustness
$sql = "
    SELECT DISTINCT i.id, i.code, i.name, s.quantity_available
    FROM inventory_items i
    JOIN inventory_stock_levels s ON i.id = s.item_id
    WHERE s.location_id = ? AND s.quantity_available > 0 AND i.is_active = 1
    
    UNION
    
    SELECT DISTINCT i.id, i.code, i.name, wi.quantity as quantity_available
    FROM inventory_items i
    JOIN warehouse_inventory wi ON i.id = wi.product_id
    WHERE wi.warehouse_id = (SELECT id FROM warehouses WHERE location_id = ? LIMIT 1) 
      AND wi.quantity > 0 AND i.is_active = 1
";

$items = db_fetch_all($sql, [$warehouse_id, $warehouse_id]);

echo json_encode($items);
exit;
