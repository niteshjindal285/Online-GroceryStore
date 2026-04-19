<?php
/**
 * Production Functions
 * Helper functions for inventory automation and costing
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Get total estimated BOM cost for a product
 */
function get_product_bom_cost($product_id, $quantity) {
    $sql = "
        SELECT SUM(bom.quantity_required * COALESCE(i.cost_price, 0)) as total_cost
        FROM bill_of_materials bom
        JOIN inventory_items i ON bom.component_id = i.id
        WHERE bom.product_id = ?
    ";
    $result = db_fetch($sql, [$product_id]);
    return ($result['total_cost'] ?? 0) * $quantity;
}

/**
 * Deduct Raw Materials from inventory
 */
function deduct_production_stock($work_order_id) {
    $wo = db_fetch("SELECT * FROM work_orders WHERE id = ?", [$work_order_id]);
    if (!$wo) return false;

    require_once __DIR__ . '/inventory_transaction_service.php';
    $userId = $_SESSION['user_id'] ?? 0;
    $model = $wo['manufacturing_model'] ?? 'fixed';

    if ($model === 'fixed') {
        $bom = db_fetch_all("
            SELECT bom.component_id, bom.quantity_required as req_qty, i.name
            FROM bill_of_materials bom
            JOIN inventory_items i ON bom.component_id = i.id
            WHERE bom.product_id = ?
        ", [$wo['product_id']]);

        foreach ($bom as $item) {
            $total_deduct = $item['req_qty'] * $wo['quantity'];
            try {
                inventory_apply_stock_movement($item['component_id'], $wo['location_id'], -$total_deduct, null);
                inventory_record_transaction([
                    'item_id'          => $item['component_id'],
                    'location_id'      => $wo['location_id'],
                    'quantity_signed'  => -$total_deduct,
                    'transaction_type' => 'consumption',
                    'reference_id'     => $work_order_id,
                    'reference_type'   => 'work_order',
                    'reference'        => $wo['wo_number'],
                    'created_by'       => $userId,
                    'notes'            => "Raw material issue for WO #" . $wo['wo_number']
                ]);
            } catch (Exception $e) {
                error_log("Error deducting fixed production stock: " . $e->getMessage());
                throw $e;
            }
        }
    } else if ($model === 'variable') {
        if (!empty($wo['input_raw_material_id'])) {
            $outputs = db_fetch_all("SELECT SUM(allocated_consumption) as total_cons FROM work_order_outputs WHERE work_order_id = ?", [$work_order_id]);
            $total_deduct = floatval($outputs[0]['total_cons'] ?? 0);
            if ($total_deduct > 0) {
                try {
                    inventory_apply_stock_movement($wo['input_raw_material_id'], $wo['location_id'], -$total_deduct, null);
                    inventory_record_transaction([
                        'item_id'          => $wo['input_raw_material_id'],
                        'location_id'      => $wo['location_id'],
                        'quantity_signed'  => -$total_deduct,
                        'transaction_type' => 'consumption',
                        'reference_id'     => $work_order_id,
                        'reference_type'   => 'work_order',
                        'reference'        => $wo['wo_number'],
                        'created_by'       => $userId,
                        'notes'            => "Raw material coil issue for WO #" . $wo['wo_number']
                    ]);
                } catch (Exception $e) {
                    error_log("Error deducting variable production stock: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }
    return true;
}

/**
 * Add Finished Goods to inventory
 */
function add_finished_goods_stock($work_order_id) {
    $wo = db_fetch("SELECT * FROM work_orders WHERE id = ?", [$work_order_id]);
    $warehouse_id = !empty($wo['fg_warehouse_id']) ? $wo['fg_warehouse_id'] : ($wo['location_id'] ?? null);
    if (!$wo || empty($warehouse_id)) return false;

    require_once __DIR__ . '/inventory_transaction_service.php';
    $userId = $_SESSION['user_id'] ?? 0;
    
    $bin_id = !empty($wo['fg_bin_id']) ? $wo['fg_bin_id'] : null;
    $model = $wo['manufacturing_model'] ?? 'fixed';

    try {
        if ($model === 'fixed') {
            $qty = ($wo['actual_qty'] ?? 0) > 0 ? $wo['actual_qty'] : $wo['quantity'];
            inventory_apply_stock_movement($wo['product_id'], $warehouse_id, $qty, $bin_id);
            inventory_record_transaction([
                'item_id'          => $wo['product_id'],
                'location_id'      => $warehouse_id,
                'bin_id'           => $bin_id,
                'quantity_signed'  => $qty,
                'transaction_type' => 'production_entry',
                'reference_id'     => $work_order_id,
                'reference_type'   => 'work_order',
                'reference'        => $wo['wo_number'],
                'created_by'       => $userId,
                'notes'            => "Finished goods receipt from WO #" . $wo['wo_number']
            ]);
        } else if ($model === 'variable') {
            $outputs = db_fetch_all("SELECT * FROM work_order_outputs WHERE work_order_id = ?", [$work_order_id]);
            foreach ($outputs as $out) {
                $qty = $out['quantity'];
                if ($qty > 0) {
                    inventory_apply_stock_movement($out['product_id'], $warehouse_id, $qty, $bin_id);
                    inventory_record_transaction([
                        'item_id'          => $out['product_id'],
                        'location_id'      => $warehouse_id,
                        'bin_id'           => $bin_id,
                        'quantity_signed'  => $qty,
                        'transaction_type' => 'production_entry',
                        'reference_id'     => $work_order_id,
                        'reference_type'   => 'work_order',
                        'reference'        => $wo['wo_number'],
                        'created_by'       => $userId,
                        'notes'            => "Variable finished goods receipt from WO #" . $wo['wo_number']
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error adding finished goods stock: " . $e->getMessage());
        throw $e;
    }

    return true;
}
