<?php
/**
 * BOM System Functions
 * Core logic for Bill of Materials management
 */

require_once __DIR__ . '/database.php';

/**
 * Calculate and update BOM totals
 * 
 * @param int $bom_id
 * @return array The calculated totals
 */
function calculate_bom_costs($bom_id) {
    // 1. Get Material Totals
    $material_sum = db_fetch("SELECT SUM(quantity_required * unit_cost) as total FROM bom_items WHERE bom_id=?", [$bom_id]);
    $total_material_cost = floatval($material_sum['total'] ?? 0);
    
    // 2. Get Header for Additional Costs
    $header = db_fetch("SELECT labor_cost, electricity_cost, machine_cost, maintenance_cost, other_cost FROM bom_headers WHERE id=?", [$bom_id]);
    
    if (!$header) return [];
    
    $total_additional_cost = floatval($header['labor_cost']) + 
                             floatval($header['electricity_cost']) + 
                             floatval($header['machine_cost']) + 
                             floatval($header['maintenance_cost']) + 
                             floatval($header['other_cost']);
                             
    $total_production_cost = $total_material_cost + $total_additional_cost;
    
    // Assuming 1 unit for simplicity, or we could fetch a batch size if added to header
    $cost_per_unit = $total_production_cost; 

    // 3. Update Header
    db_execute("UPDATE bom_headers SET 
                total_material_cost = ?, 
                total_additional_cost = ?, 
                total_production_cost = ?, 
                cost_per_unit = ? 
                WHERE id = ?", 
                [$total_material_cost, $total_additional_cost, $total_production_cost, $cost_per_unit, $bom_id]);
                
    return [
        'material_cost' => $total_material_cost,
        'additional_cost' => $total_additional_cost,
        'total_cost' => $total_production_cost,
        'cost_per_unit' => $cost_per_unit
    ];
}

/**
 * Validate material availability for a BOM
 * 
 * @param int $bom_id
 * @return array [is_available, shortage_items]
 */
function validate_material_availability($bom_id) {
    $items = db_fetch_all("
        SELECT 
            bi.item_id, 
            ii.name as item_name, 
            bi.quantity_required, 
            IFNULL(SUM(inv.quantity), 0) as available_stock
        FROM bom_items bi
        JOIN inventory_items ii ON bi.item_id = ii.id
        LEFT JOIN warehouse_inventory inv ON bi.item_id = inv.product_id
        WHERE bi.bom_id = ?
        GROUP BY bi.item_id
    ", [$bom_id]);
    
    $shortage_items = [];
    $all_items = [];
    $is_available = true;
    
    foreach ($items as $item) {
        $status = 'Available';
        if ($item['available_stock'] < $item['quantity_required']) {
            $is_available = false;
            $status = 'Shortage';
            $shortage_items[] = [
                'name' => $item['item_name'],
                'required' => $item['quantity_required'],
                'available' => $item['available_stock'],
                'shortage' => $item['quantity_required'] - $item['available_stock']
            ];
        }
        $all_items[] = [
            'name' => $item['item_name'],
            'status' => $status
        ];
    }
    
    return [
        'is_available' => $is_available,
        'shortage_items' => $shortage_items,
        'all_items' => $all_items
    ];
}

/**
 * Duplicate an existing BOM
 * 
 * @param int $bom_id Original BOM ID
 * @param int $user_id User ID performing duplication
 * @return int|bool New BOM ID or false
 */
function duplicate_bom($bom_id, $user_id) {
    db_begin_transaction();
    try {
        // 1. Fetch Header
        $header = db_fetch("SELECT * FROM bom_headers WHERE id=?", [$bom_id]);
        if (!$header) throw new Exception("BOM not found");
        
        // 2. Generate new BOM Number
        $last_bom = db_fetch("SELECT bom_number FROM bom_headers ORDER BY id DESC LIMIT 1");
        $num = 1;
        if ($last_bom) {
            $parts = explode('-', $last_bom['bom_number']);
            if (count($parts) == 2) {
                $num = intval($parts[1]) + 1;
            }
        }
        $new_bom_number = 'BOM-' . str_pad($num, 5, '0', STR_PAD_LEFT);
        
        // 3. Insert new Header
        $sql_h = "INSERT INTO bom_headers 
                  (bom_number, product_id, category_id, product_capacity, unit_id, location_id, 
                   status, remarks, labor_cost, electricity_cost, machine_cost, maintenance_cost, other_cost,
                   created_by) 
                  VALUES (?, ?, ?, ?, ?, ?, 'Draft', ?, ?, ?, ?, ?, ?, ?)";
        
        $new_id = db_insert($sql_h, [
            $new_bom_number, $header['product_id'], $header['category_id'], $header['product_capacity'],
            $header['unit_id'], $header['location_id'], "Duplicate of " . $header['bom_number'],
            $header['labor_cost'], $header['electricity_cost'], $header['machine_cost'],
            $header['maintenance_cost'], $header['other_cost'], $user_id
        ]);
        
        // 4. Duplicate Items
        $items = db_fetch_all("SELECT * FROM bom_items WHERE bom_id=?", [$bom_id]);
        foreach ($items as $item) {
            db_execute("INSERT INTO bom_items (bom_id, item_id, warehouse_id, quantity_required, unit_cost, notes) 
                        VALUES (?, ?, ?, ?, ?, ?)", 
                        [$new_id, $item['item_id'], $item['warehouse_id'], $item['quantity_required'], $item['unit_cost'], $item['notes']]);
        }
        
        // 5. Recalculate
        calculate_bom_costs($new_id);
        
        db_commit();
        return $new_id;
        
    } catch (Exception $e) {
        db_rollback();
        error_log("Error duplicating BOM: " . $e->getMessage());
        return false;
    }
}

/**
 * Get next BOM number
 * 
 * @return string
 */
function get_next_bom_number() {
    $last_bom = db_fetch("SELECT bom_number FROM bom_headers WHERE bom_number LIKE 'BOM-%' ORDER BY id DESC LIMIT 1");
    $num = 1;
    if ($last_bom) {
        $parts = explode('-', $last_bom['bom_number']);
        if (count($parts) == 2) {
            $num = intval($parts[1]) + 1;
        }
    }
    return 'BOM-' . str_pad($num, 5, '0', STR_PAD_LEFT);
}
