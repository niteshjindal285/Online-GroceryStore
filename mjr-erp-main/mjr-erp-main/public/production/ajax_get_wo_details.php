<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$wo_id = get_param('wo_id');
if (!$wo_id) {
    echo json_encode(['success' => false, 'message' => 'No WO ID provided']);
    exit;
}

try {
    // 1. Get Work Order Details
    $wo = db_fetch("
        SELECT wo.*, i.code as product_code, i.name as product_name, i.unit_of_measure,
               l.name as location_name
        FROM work_orders wo
        JOIN inventory_items i ON wo.product_id = i.id
        LEFT JOIN locations l ON wo.location_id = l.id
        WHERE wo.id = ?
    ", [$wo_id]);

    if (!$wo) {
        throw new Exception("Work Order not found");
    }

    // 2. Get BOM Details
    $bom = db_fetch_all("
        SELECT bom.component_id, bom.quantity_required as req_qty, i.code, i.name, i.unit_of_measure, i.cost_price as unit_cost,
               COALESCE(wi.quantity, 0) as available_stock, l.name as warehouse_name, l.id as warehouse_id
        FROM bill_of_materials bom
        JOIN inventory_items i ON bom.component_id = i.id
        LEFT JOIN locations l ON l.id = ?
        LEFT JOIN warehouse_inventory wi ON bom.component_id = wi.product_id AND wi.warehouse_id = l.id
        WHERE bom.product_id = ?
    ", [$wo['location_id'], $wo['product_id']]);

    // Format results
    echo json_encode([
        'success' => true,
        'wo' => $wo,
        'bom' => $bom
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
