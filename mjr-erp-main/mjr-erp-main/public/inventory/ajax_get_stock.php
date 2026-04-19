<?php
/**
 * AJAX - Get Available Stock for Item in Warehouse
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);
$item_id = (int)($_GET['item_id'] ?? 0);

if (!$warehouse_id || !$item_id) {
    echo json_encode(['quantity_available' => '0.00']);
    exit;
}

$stock = db_fetch("
    SELECT quantity_available 
    FROM inventory_stock_levels 
    WHERE location_id = ? AND item_id = ?
", [$warehouse_id, $item_id]);

if (!$stock) {
    // Check warehouse_inventory if not synced
    $wh_inv = db_fetch("
        SELECT SUM(quantity) as qty 
        FROM warehouse_inventory 
        WHERE warehouse_id = (SELECT id FROM warehouses WHERE location_id = ? LIMIT 1) 
          AND product_id = ?
    ", [$warehouse_id, $item_id]);
    
    $qty = $wh_inv ? floatval($wh_inv['qty']) : 0;
} else {
    $qty = floatval($stock['quantity_available']);
}

echo json_encode(['quantity_available' => number_format($qty, 2, '.', '')]);
exit;
