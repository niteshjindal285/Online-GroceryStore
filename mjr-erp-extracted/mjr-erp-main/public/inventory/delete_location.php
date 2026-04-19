<?php
/**
 * Delete Location
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Get location ID
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('Invalid location ID', 'error');
    redirect('locations.php');
}

try {
    // Check if location exists
    $location = db_fetch("SELECT * FROM locations WHERE id = ?", [$id]);
    
    if (!$location) {
        throw new Exception('Location not found');
    }
    
    // Check if location is used in inventory stock levels
    $inventory_count = db_fetch("SELECT COUNT(*) as count FROM inventory_stock_levels WHERE location_id = ?", [$id]);
    
    if ($inventory_count && $inventory_count['count'] > 0) {
        throw new Exception('Cannot delete location. It has inventory items. Please move or remove inventory first.');
    }
    
    // Check if location is used in inventory transactions
    $transaction_count = db_fetch("SELECT COUNT(*) as count FROM inventory_transactions WHERE location_id = ?", [$id]);
    
    if ($transaction_count && $transaction_count['count'] > 0) {
        // Don't delete if has transactions, just deactivate
        db_query("UPDATE locations SET is_active = 0 WHERE id = ?", [$id]);
        set_flash('Location has transaction history and cannot be deleted. It has been deactivated instead.', 'warning');
    } else {
        // Safe to delete
        db_query("DELETE FROM locations WHERE id = ?", [$id]);
        set_flash('Location deleted successfully!', 'success');
    }
    
} catch (Exception $e) {
    log_error("Error deleting location: " . $e->getMessage());
    set_flash('Error deleting location: ' . $e->getMessage(), 'error');
}

redirect('locations.php');



