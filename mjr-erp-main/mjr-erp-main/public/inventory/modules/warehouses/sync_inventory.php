<?php
require_once __DIR__ . '/../../../../includes/auth.php';
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php';
require_once __DIR__ . '/../../../../includes/inventory_transaction_service.php';
require_login();
require_permission('manage_inventory');

$warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;
$product_id   = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($warehouse_id <= 0) {
    die("Invalid Warehouse ID.");
}

$warehouse = db_fetch("SELECT id, location_id, name FROM warehouses WHERE id = ?", [$warehouse_id]);
if (!$warehouse || !$warehouse['location_id']) {
    die("Warehouse not found or not linked to a location.");
}

try {
    inventory_assert_stock_take_allows_stock_change(
        intval($warehouse['location_id']),
        'Warehouse stock sync'
    );

    db_begin_transaction();

    // If product_id is provided, sync only that product. Otherwise sync all in warehouse.
    $where_clause = $product_id > 0 ? "AND product_id = $product_id" : "";
    $legacy_stocks = db_fetch_all("SELECT product_id, quantity, bin_location, bin_id FROM warehouse_inventory WHERE warehouse_id = ? $where_clause", [$warehouse_id]);

    foreach ($legacy_stocks as $ls) {
        $pid = $ls['product_id'];
        $qty = floatval($ls['quantity']);
        $bin_id = $ls['bin_id'];

        // Update inventory_stock_levels (Modern Table)
        $existing = db_fetch("SELECT id FROM inventory_stock_levels WHERE item_id = ? AND location_id = ? AND (bin_id = ? OR (bin_id IS NULL AND ? IS NULL))", [$pid, $warehouse['location_id'], $bin_id, $bin_id]);

        if ($existing) {
            db_query("UPDATE inventory_stock_levels SET quantity_on_hand = ?, quantity_available = ? WHERE id = ?", [$qty, $qty, $existing['id']]);
        } else {
            db_query("INSERT INTO inventory_stock_levels (item_id, location_id, bin_id, quantity_on_hand, quantity_available) VALUES (?, ?, ?, ?, ?)", [$pid, $warehouse['location_id'], $bin_id, $qty, $qty]);
        }
    }

    db_commit();
    $msg = $product_id > 0 ? "Stock synced for item." : "All items in warehouse synced.";
    header("Location: inventory.php?id=$warehouse_id&msg=success&details=" . urlencode($msg));
} catch (Exception $e) {
    db_rollback();
    die("Sync failed: " . $e->getMessage());
}
