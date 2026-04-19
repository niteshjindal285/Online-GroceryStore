<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();
require_permission('manage_inventory');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    die("Invalid inventory ID.");
}

// Get the inventory row so we can redirect back to the correct warehouse
$item = db_fetch_all("SELECT * FROM warehouse_inventory WHERE id = $id")[0] ?? null;

if (!$item) {
    die("Inventory item not found.");
}

try {
    require_once __DIR__ . '/../../../../includes/inventory_transaction_service.php';
    
    db_begin_transaction();

    $warehouse_id = (int)$item['warehouse_id'];
    $product_id = (int)$item['product_id'];
    $qty = (int)$item['quantity'];

    // Get location_id from warehouse
    $warehouse_data = db_fetch("SELECT location_id, name FROM warehouses WHERE id = ?", [$warehouse_id]);
    $location_id = $warehouse_data['location_id'] ?? null;

    if ($location_id && $qty > 0) {
        // Check actual stock level before attempting to deduct
        $stock_level = db_fetch(
            "SELECT id, quantity_on_hand FROM inventory_stock_levels
             WHERE item_id = ? AND location_id = ?",
            [$product_id, $location_id]
        );

        $available_on_hand = $stock_level ? intval($stock_level['quantity_on_hand']) : 0;
        $deduct_qty = min($qty, $available_on_hand); // only deduct what actually exists in stock levels

        if ($deduct_qty > 0) {
            // --- Sync with main inventory system ---
            inventory_apply_stock_movement($product_id, $location_id, -$deduct_qty);
            inventory_record_transaction([
                'item_id'          => $product_id,
                'location_id'      => $location_id,
                'transaction_type' => 'issue_unplanned',
                'movement_reason'  => 'Warehouse stock removal',
                'quantity_signed'  => -$deduct_qty,
                'reference'        => 'WH-REM-' . $warehouse_id,
                'reference_type'   => 'warehouse_removal',
                'reference_id'     => $warehouse_id,
                'notes'            => "Removed from bin: " . ($item['bin_location'] ?? 'N/A'),
                'created_by'       => $_SESSION['user_id']
            ]);
            // ----------------------------------------
        }
    }


    // Delete the inventory record
    db_query("DELETE FROM warehouse_inventory WHERE id = $id");

    // Log the movement in the warehouse module's local log
    db_query("INSERT INTO stock_movements 
              (warehouse_id, product_id, movement_type, quantity, bin_location, created_at) 
              VALUES (?, ?, 'OUT', ?, ?, NOW())",
              [$warehouse_id, $product_id, $qty, $item['bin_location']]);

    db_commit();
    header("Location: inventory.php?id=$warehouse_id&msg=success&details=" . urlencode("Stock item removed successfully!"));
    exit;
} catch (Exception $e) {
    db_rollback();
    error_log("Stock removal error: " . $e->getMessage());
    die("Error processing stock removal: " . $e->getMessage());
}
