<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_once __DIR__ . '/../../../../includes/inventory_transaction_service.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $wh_id = isset($_POST['warehouse_id']) ? (int)$_POST['warehouse_id'] : 0;
    $prod_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    $inventory_id = isset($_POST['inventory_id']) ? (int)$_POST['inventory_id'] : 0;
    $sys_qty = isset($_POST['system_qty']) ? (int)$_POST['system_qty'] : 0;
    $phy_qty = isset($_POST['physical_qty']) ? (int)$_POST['physical_qty'] : 0;
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    // Validation
    if ($wh_id <= 0 || $prod_id <= 0) {
        die("Error: Invalid warehouse or product ID.");
    }
    
    if ($phy_qty < 0) {
        die("Error: Physical quantity cannot be negative.");
    }
    
    // Calculate variance
    $variance = $phy_qty - $sys_qty;
    
    // Get current user ID
    $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1; // Default to 1 if not set
    
    // Sanitize notes for SQL
    $safe_notes = addslashes($notes);
    
    try {
        $warehouse = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$wh_id]);
        if (!$warehouse || empty($warehouse['location_id'])) {
            throw new Exception("Warehouse location mapping not found.");
        }
        inventory_assert_stock_take_allows_stock_change(
            intval($warehouse['location_id']),
            'Cycle count adjustment'
        );

        // Check if inventory_audits table exists, if not we'll just update inventory directly
        $table_exists = db_fetch_all("SHOW TABLES LIKE 'inventory_audits'");
        
        if (!empty($table_exists)) {
            // Insert audit record
            $sql = "INSERT INTO inventory_audits 
                    (warehouse_id, product_id, system_qty, physical_qty, variance, audited_by, notes, audit_date) 
                    VALUES ($wh_id, $prod_id, $sys_qty, $phy_qty, $variance, $user_id, '$safe_notes', NOW())";
            
            db_query($sql);
        }
        
        // If there's a variance (or if it's a new record), update or insert the inventory
        if ($variance != 0 || $inventory_id == 0) {
            if ($inventory_id > 0) {
                // Update existing record
                $update_result = db_query("UPDATE warehouse_inventory 
                                          SET quantity = $phy_qty 
                                          WHERE id = $inventory_id");
            } else {
                // Check if a record was created in the meantime
                $existing = db_fetch("SELECT id FROM warehouse_inventory WHERE warehouse_id = ? AND product_id = ?", [$wh_id, $prod_id]);
                if ($existing) {
                    $update_result = db_query("UPDATE warehouse_inventory SET quantity = ? WHERE id = ?", [$phy_qty, $existing['id']]);
                } else {
                    // Insert new record
                    $update_result = db_query("INSERT INTO warehouse_inventory (warehouse_id, product_id, quantity) VALUES (?, ?, ?)", [$wh_id, $prod_id, $phy_qty]);
                }
            }
            
            if (!$update_result) {
                throw new Exception("Failed to update inventory quantity.");
            }
            
            // Log the adjustment in stock_movements
            $movement_type = ($variance > 0) ? 'ADJUST' : 'ADJUST';
            $movement_notes = "Cycle count adjustment. Variance: " . ($variance > 0 ? '+' : '') . $variance . " units. " . $safe_notes;
            
            db_query("INSERT INTO stock_movements 
                     (warehouse_id, product_id, movement_type, quantity, notes, created_at) 
                     VALUES ($wh_id, $prod_id, '$movement_type', $variance, '$movement_notes', NOW())");
        }
        
        // Redirect with success message
        $msg = urlencode("Cycle count completed. Variance: " . ($variance > 0 ? '+' : '') . "$variance units");
        header("Location: inventory.php?id=$wh_id&msg=audit_success&details=$msg");
        exit;
        
    } catch (Exception $e) {
        error_log("Audit process error: " . $e->getMessage());
        die("Error processing audit: " . $e->getMessage());
    }
    
} else {
    // If accessed directly without POST
    header("Location: index.php");
    exit;
}
?>
