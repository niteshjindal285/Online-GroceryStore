<?php
/**
 * Inventory Module - Delete Item
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_inventory');

$item_id = intval(get_param('id'));

if (!$item_id) {
    set_flash('Invalid item ID.', 'error');
    redirect('index.php');
}

// Get item
$item = db_fetch("SELECT * FROM inventory_items WHERE id = ?", [$item_id]);

if (!$item) {
    set_flash('Item not found.', 'error');
    redirect('index.php');
}

// Soft delete (set is_active = 0) instead of hard delete
try {
    db_execute("UPDATE inventory_items SET is_active = 0 WHERE id = ?", [$item_id]);
    set_flash('Item deleted successfully.', 'success');
} catch (Exception $e) {
    log_error("Error deleting inventory item: " . $e->getMessage());
    set_flash('An error occurred while deleting the item.', 'error');
}

redirect('index.php');
?>



