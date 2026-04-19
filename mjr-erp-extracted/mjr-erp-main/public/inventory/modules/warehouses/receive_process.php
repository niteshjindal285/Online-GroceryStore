<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $wh_id = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $prod_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $qty = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $bin = isset($_POST['bin_location']) ? trim($_POST['bin_location']) : '';

    if ($wh_id <= 0) {
        die("Error: Invalid warehouse ID.");
    }
    
    if ($prod_id <= 0) {
        die("Error: Please select a valid product.");
    }
    
    if ($qty <= 0) {
        die("Error: Quantity must be greater than 0.");
    }
    
    if (empty($bin)) {
        die("Error: Bin location is required.");
    }

    $warehouse_check = db_fetch_all("SELECT id, name FROM warehouses WHERE id = $wh_id");
    if (empty($warehouse_check)) {
        die("Error: Warehouse not found.");
    }

    $product_check = db_fetch_all("SELECT id, name, code FROM inventory_items WHERE id = $prod_id");
    if (empty($product_check)) {
        die("Error: Product not found.");
    }

    $bin = addslashes($bin);

    try {
        require_once __DIR__ . '/../../../../includes/inventory_transaction_service.php';

        db_begin_transaction();

        // Get location_id from warehouse
        $warehouse_data = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$wh_id]);
        $location_id = $warehouse_data['location_id'] ?? null;

        if (!$location_id) {
            // Check if a location with the same name exists, otherwise create it
            $loc = db_fetch("SELECT id FROM locations WHERE name = ?", [$warehouse_check[0]['name']]);
            if ($loc) {
                $location_id = $loc['id'];
                db_query("UPDATE warehouses SET location_id = ? WHERE id = ?", [$location_id, $wh_id]);
            } else {
                // Should have been created by create_process, but for legacy data we might need it
                $base_code = 'WH-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $warehouse_check[0]['name']), 0, 10));
                $code = $base_code;
                $count = 1;
                while (db_fetch("SELECT id FROM locations WHERE code = ?", [$code])) {
                    $code = $base_code . $count++;
                }
                $location_id = db_insert("INSERT INTO locations (code, name, company_id, type, address, is_active, created_at) VALUES (?, ?, ?, 'warehouse', ?, 1, NOW())", [$code, $warehouse_check[0]['name'], $_SESSION['company_id'] ?? 1, '']);
                db_query("UPDATE warehouses SET location_id = ? WHERE id = ?", [$location_id, $wh_id]);
            }
        }

        $existing = db_fetch_all("SELECT id, quantity 
                                  FROM warehouse_inventory 
                                  WHERE warehouse_id = $wh_id 
                                  AND product_id = $prod_id 
                                  AND bin_location = '$bin'");

        if (!empty($existing)) {

            $current_qty = $existing[0]['quantity'];
            $new_qty = $current_qty + $qty;
            $inventory_id = $existing[0]['id'];
            
            $update_result = db_query("UPDATE warehouse_inventory 
                                       SET quantity = $new_qty 
                                       WHERE id = $inventory_id");
            
            if (!$update_result) {
                throw new Exception("Failed to update inventory quantity.");
            }
            
            $action_type = "updated";

        } else {

            $insert_result = db_query("INSERT INTO warehouse_inventory 
                                       (warehouse_id, product_id, quantity, bin_location) 
                                       VALUES ($wh_id, $prod_id, $qty, '$bin')");
            
            if (!$insert_result) {
                throw new Exception("Failed to add new inventory record.");
            }
            
            $action_type = "added";
        }

        // --- NEW: Sync with main inventory system ---
        inventory_apply_stock_movement($prod_id, $location_id, $qty);
        inventory_record_transaction([
            'item_id' => $prod_id,
            'location_id' => $location_id,
            'transaction_type' => 'receipt_unplanned',
            'movement_reason' => 'Warehouse stock receipt',
            'quantity_signed' => $qty,
            'reference' => 'WH-REC-' . $wh_id,
            'reference_type' => 'warehouse_receipt',
            'reference_id' => $wh_id,
            'notes' => "Received in bin: $bin",
            'created_by' => $_SESSION['user_id']
        ]);
        // ---------------------------------------------

        $movement_result = db_query("INSERT INTO stock_movements 
                                     (warehouse_id, product_id, movement_type, quantity, bin_location, created_at) 
                                     VALUES ($wh_id, $prod_id, 'IN', $qty, '$bin', NOW())");
        
        if (!$movement_result) {
            throw new Exception("Failed to log stock movement.");
        }

        db_commit();

        $product_name = $product_check[0]['name'];
        $success_msg = urlencode("Successfully $action_type $qty units of $product_name in bin $bin");
        header("Location: inventory.php?id=$wh_id&msg=success&details=$success_msg");
        exit;

    } catch (Exception $e) {
        error_log("Stock receive error: " . $e->getMessage());
        die("Error processing stock receipt: " . $e->getMessage() . " Please contact system administrator.");
    }

} else {
    header("Location: index.php");
    exit;
}
?>
