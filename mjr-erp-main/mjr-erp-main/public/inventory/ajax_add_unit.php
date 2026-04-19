<?php
/**
 * AJAX Add Unit of Measure
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_inventory');

if (is_post()) {
    $name = sanitize_input(post('name'));
    $code = strtoupper(sanitize_input(post('code')));
    $description = sanitize_input(post('description'));

    if (empty($name) || empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Name and Code are required.']);
        exit;
    }

    try {
        // Check if code exists
        $existing = db_fetch("SELECT id FROM units_of_measure WHERE code = ?", [$code]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Unit code already exists.']);
            exit;
        }

        $id = db_insert("INSERT INTO units_of_measure (code, name, description, is_active) VALUES (?, ?, ?, 1)", [$code, $name, $description]);

        if ($id) {
            echo json_encode([
                'success' => true, 
                'id' => $id, 
                'name' => $name, 
                'code' => $code
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add unit.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
