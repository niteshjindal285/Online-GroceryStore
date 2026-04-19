<?php
/**
 * AJAX Create Inventory Item
 * Handles quick add from production/BOM screens
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
// Note: relax permission for quick-add if needed, or check 'manage_inventory'
if (!has_permission('manage_inventory') && !has_permission('manage_production')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = sanitize_input($_POST['qa_code'] ?? '');
    $name = sanitize_input($_POST['qa_name'] ?? '');
    $category_id = intval($_POST['qa_category_id'] ?? 0);
    $uom_id = intval($_POST['qa_uom_id'] ?? 0);
    $tax_id = intval($_POST['qa_tax_id'] ?? 0);
    
    if (empty($code) || empty($name) || empty($uom_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Check if code exists
    $existing = db_fetch("SELECT id FROM inventory_items WHERE code = ?", [$code]);
    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'Item code already exists']);
        exit;
    }
    
    try {
        $sql = "INSERT INTO inventory_items (
                    code, name, category_id, unit_of_measure_id, tax_class_id, 
                    is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())";
        
        $params = [
            $code, 
            $name, 
            $category_id ?: null, 
            $uom_id, 
            $tax_id ?: null
        ];
        
        db_query($sql, $params);
        $new_id = db_insert_id();
        
        echo json_encode([
            'success' => true, 
            'id' => $new_id, 
            'code' => $code, 
            'name' => $name
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
