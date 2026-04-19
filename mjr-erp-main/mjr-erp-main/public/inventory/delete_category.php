<?php
/**
 * Delete Category
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_inventory');

// Get category ID
$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('Invalid category ID', 'error');
    redirect('categories.php');
}

try {
    // Check if category exists
    $category = db_fetch("SELECT * FROM categories WHERE id = ?", [$id]);
    
    if (!$category) {
        throw new Exception('Category not found');
    }
    
    // Check if category is used by any inventory items
    $item_count = db_fetch("SELECT COUNT(*) as count FROM inventory_items WHERE category_id = ?", [$id]);
    
    if ($item_count && $item_count['count'] > 0) {
        throw new Exception('Cannot delete category. It is assigned to ' . $item_count['count'] . ' item(s). Please reassign or delete those items first.');
    }
    
    // Safe to delete
    db_query("DELETE FROM categories WHERE id = ?", [$id]);
    set_flash('Category deleted successfully!', 'success');
    
} catch (Exception $e) {
    log_error("Error deleting category: " . $e->getMessage());
    set_flash('Error deleting category: ' . $e->getMessage(), 'error');
}

redirect('categories.php');



