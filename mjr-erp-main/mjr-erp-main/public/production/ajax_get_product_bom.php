<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_production');

header('Content-Type: application/json');

$product_id = intval(get_param('product_id', 0));
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product id', 'rows' => []]);
    exit;
}

try {
    $rows = db_fetch_all("
        SELECT b.component_id,
               b.quantity_required,
               i.code AS component_code,
               i.name AS component_name,
               COALESCE(i.cost_price, 0) AS unit_cost,
               COALESCE(SUM(wi.quantity), 0) AS stock_available
        FROM bill_of_materials b
        JOIN inventory_items i ON b.component_id = i.id
        LEFT JOIN warehouse_inventory wi ON wi.product_id = i.id
        WHERE b.product_id = ?
        GROUP BY b.id, i.id
        ORDER BY i.name
    ", [$product_id]);

    echo json_encode(['success' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage(), 'rows' => []]);
}

