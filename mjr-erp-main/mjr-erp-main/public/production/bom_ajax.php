<?php
/**
 * BOM AJAX Handlers
 * Real-time stock checks and cost updates
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/bom_functions.php';

require_login();

header('Content-Type: application/json');

$action = get_param('action');

switch ($action) {
    case 'get_item_details':
        $item_id = intval(get_param('item_id'));
        $item = db_fetch("
            SELECT i.id, i.code, i.name, i.cost_price, u.code as unit, c.name as category_name
            FROM inventory_items i 
            LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id 
            LEFT JOIN categories c ON i.category_id = c.id 
            WHERE i.id = ?
        ", [$item_id]);
        
        $stock = db_fetch("SELECT SUM(quantity) as total FROM warehouse_inventory WHERE product_id = ?", [$item_id]);
        
        if ($item) {
            $item['available_stock'] = floatval($stock['total'] ?? 0);
            echo json_encode(['success' => true, 'data' => $item]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Item not found']);
        }
        break;

    case 'check_availability':
        $bom_id = intval(get_param('bom_id'));
        $result = validate_material_availability($bom_id);
        echo json_encode(['success' => true, 'data' => $result]);
        break;

    case 'duplicate':
        $bom_id = intval(get_param('bom_id'));
        $new_id = duplicate_bom($bom_id, $_SESSION['user_id']);
        if ($new_id) {
            echo json_encode(['success' => true, 'new_id' => $new_id]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to duplicate BOM']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
