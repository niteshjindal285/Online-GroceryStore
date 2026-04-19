<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();
require_permission('manage_inventory');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Check if stock exists
    $stock_check_array = db_fetch_all("SELECT SUM(quantity) as total FROM warehouse_inventory WHERE warehouse_id = ?", [$id]);
    $stock_check = !empty($stock_check_array) ? $stock_check_array[0] : null;
    $total_stock = $stock_check['total'] ?? 0;

    if ($total_stock > 0) {
        die("<script>alert('Cannot delete! Warehouse has active stock. Empty it first.'); window.location.href='index.php';</script>");
    }

    // Attempt soft deletion (archiving) instead of hard deletion to avoid Foreign Key violations
    // with historical records, bins, and transactions.
    db_query("UPDATE warehouses SET is_active = 0 WHERE id = ?", [$id]);
    
    // Also deactivate the linked location if applicable
    $wh = db_fetch("SELECT location_id FROM warehouses WHERE id = ?", [$id]);
    if ($wh && $wh['location_id']) {
        db_query("UPDATE locations SET is_active = 0 WHERE id = ?", [$wh['location_id']]);
    }

    header("Location: index.php?msg=" . urlencode("Warehouse successfully deactivated."));
    exit;

} catch (Throwable $e) {
    // Catch ANY fatal error or exception and output it to avoid a blank 500
    $errorMsg = addslashes($e->getMessage());
    die("<script>
        console.error('Fatal PHP Error: " . $errorMsg . "');
        alert('Cannot delete warehouse. Error: " . $errorMsg . "');
        window.location.href='index.php';
    </script>");
}