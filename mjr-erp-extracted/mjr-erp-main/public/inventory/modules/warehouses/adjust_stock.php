<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

$id  = isset($_GET['id'])  ? (int)$_GET['id']  : 0;
$qty = isset($_GET['qty']) ? (int)$_GET['qty']  : -1;

if ($id <= 0 || $qty < 0) {
    die("Invalid parameters.");
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
    $old_qty = (int)$item['quantity'];
    $new_qty = (int)$qty;
    $delta = $new_qty - $old_qty;

    if ($delta !== 0) {
        // Get location_id from warehouse
        $warehouse_data = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$warehouse_id]);
        $location_id = $warehouse_data['location_id'] ?? null;

        if ($location_id) {
            // --- NEW: Sync with main inventory system ---
            inventory_apply_stock_movement($product_id, $location_id, $delta);
            inventory_record_transaction([
                'item_id' => $product_id,
                'location_id' => $location_id,
                'transaction_type' => 'stock_adjustment',
                'movement_reason' => 'Warehouse stock adjustment',
                'quantity_signed' => $delta,
                'reference' => 'WH-ADJ-' . $warehouse_id,
                'reference_type' => 'warehouse_adjustment',
                'reference_id' => $warehouse_id,
                'notes' => "Adjusted in bin: " . ($item['bin_location'] ?? 'N/A') . ". Old Qty: $old_qty, New Qty: $new_qty",
                'created_by' => $_SESSION['user_id']
            ]);
            // ---------------------------------------------
        }

        // Update the local warehouse quantity
        db_query("UPDATE warehouse_inventory SET quantity = ? WHERE id = ?", [$new_qty, $id]);

        // Log the local movement
        $direction = $delta > 0 ? 'IN' : 'OUT';
        db_query("INSERT INTO stock_movements 
                  (warehouse_id, product_id, movement_type, quantity, bin_location, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())",
                  [$warehouse_id, $product_id, $direction, abs($delta), $item['bin_location']]);
    }

    db_commit();
    header("Location: inventory.php?id=$warehouse_id&msg=success&details=" . urlencode("Stock quantity updated successfully!"));
    exit;
} catch (Exception $e) {
    db_rollback();
    error_log("Stock adjustment error: " . $e->getMessage());
    die("Error processing stock adjustment: " . $e->getMessage());
}
