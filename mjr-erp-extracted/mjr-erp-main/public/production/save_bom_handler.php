<?php
/**
 * BOM Save Handler
 * Persist BOM Header and Items to the database
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/bom_functions.php';

require_login();
require_permission('manage_production');

header('Content-Type: application/json');

if (!is_post()) {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

db_begin_transaction();

try {
    $bom_id = intval(post('bom_id', 0));
    $product_id = intval(post('product_id'));
    $bom_number = post('bom_number');
    $status = post('status', 'Draft');
    $category_id = post('category_id') ?: null;
    $product_capacity = post('product_capacity');
    $unit_id = intval(post('unit_id')) ?: null;
    $location_id = intval(post('location_id')) ?: null;
    $remarks = post('remarks');
    
    $range_name = post('range_name');
    $variant_name = post('variant_name');
    $capacity_range = post('capacity_range');

    $labor_cost = floatval(post('labor_cost', 0));
    $electricity_cost = floatval(post('electricity_cost', 0));
    $machine_cost = floatval(post('machine_cost', 0));
    $maintenance_cost = floatval(post('maintenance_cost', 0));
    $other_cost = floatval(post('other_cost', 0));

    if (empty($product_id)) throw new Exception("Product is required");

    if ($bom_id > 0) {
        // UPDATE Header
        $sql = "UPDATE bom_headers SET 
                    product_id = ?, category_id = ?, product_capacity = ?, unit_id = ?, 
                    location_id = ?, status = ?, remarks = ?, 
                    range_name = ?, variant_name = ?, capacity_range = ?,
                    labor_cost = ?, electricity_cost = ?, machine_cost = ?, 
                    maintenance_cost = ?, other_cost = ?
                WHERE id = ?";
        db_execute($sql, [
            $product_id, $category_id, $product_capacity, $unit_id, 
            $location_id, $status, $remarks, 
            $range_name, $variant_name, $capacity_range,
            $labor_cost, $electricity_cost, $machine_cost, 
            $maintenance_cost, $other_cost, $bom_id
        ]);
        
        // Clear existing items
        db_execute("DELETE FROM bom_items WHERE bom_id = ?", [$bom_id]);
        $new_id = $bom_id;
    } else {
        // INSERT Header
        $sql = "INSERT INTO bom_headers 
                (bom_number, product_id, category_id, product_capacity, unit_id, location_id, 
                 status, remarks, range_name, variant_name, capacity_range,
                 labor_cost, electricity_cost, machine_cost, maintenance_cost, other_cost, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $new_id = db_insert($sql, [
            $bom_number, $product_id, $category_id, $product_capacity, $unit_id, $location_id, 
            $status, $remarks, $range_name, $variant_name, $capacity_range,
            $labor_cost, $electricity_cost, $machine_cost, $maintenance_cost, $other_cost, $_SESSION['user_id']
        ]);
    }

    // Save Items
    $items = $_POST['items'] ?? [];
    foreach ($items as $item) {
        if (empty($item['item_id'])) continue;
        
        db_execute("INSERT INTO bom_items (bom_id, item_id, warehouse_id, quantity_required, unit_cost) 
                    VALUES (?, ?, ?, ?, ?)", 
                    [$new_id, intval($item['item_id']), intval($item['warehouse_id']), floatval($item['quantity_required']), floatval($item['unit_cost'])]);
    }

    // Recalculate totals
    calculate_bom_costs($new_id);

    db_commit();
    echo json_encode(['success' => true, 'id' => $new_id, 'message' => 'BOM saved successfully']);

} catch (Exception $e) {
    db_rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
