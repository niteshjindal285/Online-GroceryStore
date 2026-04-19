<?php
/**
 * AJAX Delete Unit of Measure
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_inventory');

if (is_post()) {
    $id = intval(post('id'));

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid unit ID.']);
        exit;
    }

    try {
        // Check if unit is in use
        $in_use = db_fetch("SELECT id FROM inventory_items WHERE unit_of_measure_id = ? LIMIT 1", [$id]);
        if ($in_use) {
            echo json_encode(['success' => false, 'message' => 'This unit is currently in use by inventory items and cannot be deleted.']);
            exit;
        }

        // Delete the unit
        $success = db_query("DELETE FROM units_of_measure WHERE id = ?", [$id]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Unit deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete unit.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
