<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();
require_permission('manage_inventory');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
    if ($id <= 0) {
        die("Invalid Warehouse ID.");
    }
    
    $name = trim($_POST['name'] ?? '');
    $manager_name = trim($_POST['manager_name'] ?? '');
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    if ($name === '') {
        die("Warehouse name is required.");
    }
    
    try {
        db_begin_transaction();
        
        // Fetch current warehouse to get location_id
        $current_wh = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$id]);
        
        // Update warehouses table
        db_query(
            "UPDATE warehouses SET name = ?, manager_name = ?, is_active = ? WHERE id = ?", 
            [$name, $manager_name, $is_active, $id]
        );
        
        // Update locations table name to keep them in sync if location_id exists
        if ($current_wh && $current_wh['location_id']) {
            db_query(
                "UPDATE locations SET name = ?, is_active = ? WHERE id = ?",
                [$name, $is_active, $current_wh['location_id']]
            );
        }
        
        db_commit();
        header("Location: index.php?msg=" . urlencode("Warehouse successfully updated."));
        exit;
    } catch (Exception $e) {
        db_rollback();
        error_log("Warehouse update error: " . $e->getMessage());
        die("An error occurred while updating the warehouse. " . $e->getMessage());
    }
} else {
    header("Location: index.php");
    exit;
}
