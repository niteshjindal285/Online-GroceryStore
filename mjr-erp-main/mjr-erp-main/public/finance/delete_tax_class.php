<?php
/**
 * Delete Tax Class (POST + CSRF protected)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request method.', 'error');
    redirect('tax_classes.php');
    exit;
}

// Verify CSRF token
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid security token.', 'error');
    redirect('tax_classes.php');
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('Invalid tax class ID', 'error');
    redirect('tax_classes.php');
    exit;
}

try {
    // Check if tax class is used by any inventory items
    $item_count = db_fetch("SELECT COUNT(*) as count FROM inventory_items WHERE tax_class_id = ?", [$id]);

    if ($item_count && $item_count['count'] > 0) {
        // If used, just deactivate
        db_query("UPDATE tax_configurations SET is_active = 0 WHERE id = ?", [$id]);
        set_flash('Tax class is assigned to items and has been deactivated instead of deleted.', 'warning');
    } else {
        // Safe to actually delete from database
        db_query("DELETE FROM tax_configurations WHERE id = ?", [$id]);
        set_flash('Tax class deleted successfully!', 'success');
    }
} catch (Exception $e) {
    log_error("Error deleting tax class: " . $e->getMessage());
    set_flash('Error deleting tax class: ' . $e->getMessage(), 'error');
}

redirect('tax_classes.php');
