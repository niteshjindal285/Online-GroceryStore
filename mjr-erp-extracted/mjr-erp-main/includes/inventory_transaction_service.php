<?php
/**
 * Inventory Transaction Service
 *
 * Centralizes transaction typing, stock movement updates, schema guards,
 * sales order movement sync, and report queries.
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Canonical transaction types used across inventory reporting.
 *
 * @return array<string, array<string, mixed>>
 */
function inventory_transaction_type_catalog() {
    return [
        'purchase' => [
            'label' => 'Purchase',
            'direction' => 1,
            'requires_supplier' => true
        ],
        'sale' => [
            'label' => 'Sales',
            'direction' => -1,
            'requires_customer' => true
        ],
        'stock_count' => [
            'label' => 'Stock Count',
            'direction' => 0
        ],
        'stock_adjustment' => [
            'label' => 'Stock Adjustment',
            'direction' => 0
        ],
        'receipt_unplanned' => [
            'label' => 'Receipt Unplanned',
            'direction' => 1
        ],
        'issue_unplanned' => [
            'label' => 'Issue Unplanned',
            'direction' => -1
        ],
        'stock_return_customer' => [
            'label' => 'Stock Return',
            'direction' => 1,
            'requires_customer' => true
        ],
        'stock_return_supplier' => [
            'label' => 'Stock Return',
            'direction' => -1,
            'requires_supplier' => true
        ],
        'production_entry' => [
            'label' => 'Production Receipt',
            'direction' => 1,
            'notes' => 'Finished good entry from production'
        ],
        'consumption' => [
            'label' => 'BOM Consumption',
            'direction' => -1,
            'notes' => 'Raw material deduction for production'
        ],
        'write_off' => [
            'label' => 'Write-Off',
            'direction' => -1,
            'notes' => 'Damaged/Expired stock write-off'
        ],
        // Legacy values kept for backward compatibility
        'in' => [
            'label' => 'In Stock',
            'direction' => 1
        ],
        'out' => [
            'label' => 'Out Stock',
            'direction' => -1
        ],
        'receipt' => [
            'label' => 'Purchase',
            'direction' => 1
        ]
    ];
}

/**
 * @param string $table
 * @param string $column
 * @return bool
 */
function inventory_table_column_exists($table, $column) {
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?",
        [DB_NAME, $table, $column]
    );
    return !empty($row) && intval($row['cnt']) > 0;
}

/**
 * @param string $table
 * @param string $indexName
 * @return bool
 */
function inventory_table_index_exists($table, $indexName) {
    $row = db_fetch(
        "SELECT COUNT(*) AS cnt
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?",
        [DB_NAME, $table, $indexName]
    );
    return !empty($row) && intval($row['cnt']) > 0;
}

/**
 * Ensure transaction reporting columns exist.
 * Safe to call multiple times.
 *
 * @return void
 */
function ensure_inventory_transaction_reporting_schema() {
    static $checked = false;
    if ($checked) {
        return;
    }

    $columnsToAdd = [
        'quantity_signed' => "ALTER TABLE inventory_transactions ADD COLUMN quantity_signed INT NULL AFTER quantity",
        'unit_of_measure' => "ALTER TABLE inventory_transactions ADD COLUMN unit_of_measure VARCHAR(20) NULL AFTER quantity_signed",
        'selling_price' => "ALTER TABLE inventory_transactions ADD COLUMN selling_price DECIMAL(15,4) NULL AFTER unit_cost",
        'customer_id' => "ALTER TABLE inventory_transactions ADD COLUMN customer_id INT NULL AFTER selling_price",
        'supplier_id' => "ALTER TABLE inventory_transactions ADD COLUMN supplier_id INT NULL AFTER customer_id",
        'movement_reason' => "ALTER TABLE inventory_transactions ADD COLUMN movement_reason VARCHAR(100) NULL AFTER transaction_type"
    ];

    foreach ($columnsToAdd as $column => $sql) {
        if (!inventory_table_column_exists('inventory_transactions', $column)) {
            db_query($sql);
        }
    }

    if (!inventory_table_index_exists('inventory_transactions', 'idx_it_customer_id')) {
        db_query("ALTER TABLE inventory_transactions ADD INDEX idx_it_customer_id (customer_id)");
    }
    if (!inventory_table_index_exists('inventory_transactions', 'idx_it_supplier_id')) {
        db_query("ALTER TABLE inventory_transactions ADD INDEX idx_it_supplier_id (supplier_id)");
    }
    if (!inventory_table_index_exists('inventory_transactions', 'idx_it_reference')) {
        db_query("ALTER TABLE inventory_transactions ADD INDEX idx_it_reference (reference_type, reference_id)");
    }

    // Backfill reporting values for older rows.
    db_query(
        "UPDATE inventory_transactions
         SET quantity_signed = CASE
             WHEN transaction_type IN ('out', 'sale', 'issue_unplanned', 'stock_return_supplier') THEN -ABS(quantity)
             WHEN transaction_type IN ('in', 'purchase', 'receipt', 'receipt_unplanned', 'stock_return_customer') THEN ABS(quantity)
             ELSE quantity
         END
         WHERE quantity_signed IS NULL"
    );

    db_query(
        "UPDATE inventory_transactions t
         JOIN inventory_items i ON i.id = t.item_id
         LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
         SET t.unit_of_measure = COALESCE(NULLIF(i.unit_of_measure, ''), u.code, 'PCS')
         WHERE t.unit_of_measure IS NULL OR t.unit_of_measure = ''"
    );

    db_query(
        "UPDATE inventory_transactions t
         JOIN inventory_items i ON i.id = t.item_id
         SET t.unit_cost = i.cost_price
         WHERE t.unit_cost IS NULL"
    );

    db_query(
        "UPDATE inventory_transactions t
         JOIN inventory_items i ON i.id = t.item_id
         SET t.selling_price = i.selling_price
         WHERE t.selling_price IS NULL"
    );

    $checked = true;
}

/**
 * @param int $itemId
 * @return array|null
 */
function inventory_item_snapshot($itemId) {
    return db_fetch(
        "SELECT i.id,
                i.code,
                i.name,
                i.cost_price,
                i.selling_price,
                COALESCE(NULLIF(i.unit_of_measure, ''), u.code, 'PCS') AS unit_of_measure
         FROM inventory_items i
         LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
         WHERE i.id = ?",
        [$itemId]
    ) ?: null;
}

/**
 * @return int
 * @throws Exception
 */
function inventory_default_location_id() {
    $location = db_fetch("SELECT id FROM locations WHERE is_active = 1 ORDER BY id ASC LIMIT 1");
    if (!$location) {
        throw new Exception('No active inventory location found.');
    }
    return intval($location['id']);
}

/**
 * Resolve warehouse ID for a given location ID.
 * Returns null if no warehouse is linked to this location.
 */
function inventory_resolve_warehouse_id($locationId) {
    if (!$locationId || $locationId <= 0) return null;
    $wh = db_fetch("SELECT id FROM warehouses WHERE location_id = ?", [$locationId]);
    return $wh ? intval($wh['id']) : null;
}

/**
 * Get active stock take for a specific location.
 *
 * @param int $locationId
 * @return array|false
 */
function inventory_get_active_stock_take_for_location($locationId) {
    $locationId = intval($locationId);
    if ($locationId <= 0) {
        return false;
    }

    return db_fetch("
        SELECT st.*, l.name as warehouse_name
        FROM stock_take_headers st
        JOIN locations l ON st.location_id = l.id
        WHERE st.location_id = ?
          AND st.status IN ('open', 'pending_approval')
        LIMIT 1
    ", [$locationId]);
}

/**
 * Ensure stock changes are blocked only for the location with active stock take.
 *
 * @param int $locationId
 * @param string $actionLabel
 * @return void
 * @throws Exception
 */
function inventory_assert_stock_take_allows_stock_change($locationId, $actionLabel = 'Stock change') {
    $active = inventory_get_active_stock_take_for_location($locationId);
    if (!$active) {
        return;
    }

    throw new Exception(
        $actionLabel . ' is locked for this warehouse because stock take '
        . $active['stock_take_number'] . ' is currently ' . $active['status'] . '.'
    );
}

/**
 * @param int $itemId
 * @param int $locationId
 * @param int $quantitySigned Positive for IN, negative for OUT
 * @param int|null $bin_id
 * @param array<string,mixed> $options
 * @return void
 * @throws Exception
 */
function inventory_apply_stock_movement($itemId, $locationId, $quantitySigned, $bin_id = null, $options = []) {
     $allowDuringStockTake = !empty($options['allow_during_stock_take']);
     if (!$allowDuringStockTake) {
         inventory_assert_stock_take_allows_stock_change($locationId, 'Stock movement');
     }

     $warehouseId = inventory_resolve_warehouse_id($locationId);

     $stock = db_fetch(
        "SELECT id, quantity_on_hand, quantity_available
         FROM inventory_stock_levels
         WHERE item_id = ? AND location_id = ? AND (bin_id = ? OR (bin_id IS NULL AND ? IS NULL))
         FOR UPDATE",
        [$itemId, $locationId, $bin_id, $bin_id]
    );

    if ($stock) {
        $newOnHand = floatval($stock['quantity_on_hand']) + floatval($quantitySigned);
        $newAvailable = floatval($stock['quantity_available']) + floatval($quantitySigned);

        if ($newOnHand < 0 || $newAvailable < 0) {
            throw new Exception('Insufficient stock for this movement.');
        }

        db_query(
            "UPDATE inventory_stock_levels
             SET quantity_on_hand = ?,
                 quantity_available = ?
             WHERE id = ?",
            [$newOnHand, $newAvailable, $stock['id']]
        );

        // Sync with warehouse_inventory (ONLY if a warehouse record exists for this location)
        if ($warehouseId) {
            $wh_record = db_fetch(
                "SELECT id FROM warehouse_inventory
                 WHERE product_id = ? AND warehouse_id = ? AND (bin_id = ? OR (bin_id IS NULL AND ? IS NULL))
                 FOR UPDATE",
                [$itemId, $warehouseId, $bin_id, $bin_id]
            );

            if ($wh_record) {
                db_query(
                    "UPDATE warehouse_inventory SET quantity = quantity + ? WHERE id = ?",
                    [$quantitySigned, $wh_record['id']]
                );
            } else {
                db_query(
                    "INSERT INTO warehouse_inventory (product_id, warehouse_id, bin_id, quantity) VALUES (?, ?, ?, ?)",
                    [$itemId, $warehouseId, $bin_id, $quantitySigned]
                );
            }

            // Also sync to legacy stock_movements table
            $move_type = $quantitySigned > 0 ? 'IN' : 'OUT';
            $bin_code = null;
            if ($bin_id) {
                $bin_res = db_fetch("SELECT code FROM bins WHERE id = ?", [$bin_id]);
                $bin_code = $bin_res['code'] ?? null;
            }
            
            db_query(
                "INSERT INTO stock_movements (warehouse_id, product_id, movement_type, quantity, bin_location, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$warehouseId, $itemId, $move_type, abs($quantitySigned), $bin_code]
            );
        }
        return;
    }

    if ($quantitySigned < 0) {
        throw new Exception('Cannot issue stock from a location with no stock record.');
    }

    db_query(
        "INSERT INTO inventory_stock_levels
         (item_id, location_id, bin_id, quantity_on_hand, quantity_reserved, quantity_available)
         VALUES (?, ?, ?, ?, 0, ?)",
        [$itemId, $locationId, $bin_id, $quantitySigned, $quantitySigned]
    );

    // Sync with warehouse_inventory for new record
    if ($warehouseId) {
        $wh_record = db_fetch(
            "SELECT id FROM warehouse_inventory 
             WHERE product_id = ? AND warehouse_id = ? AND (bin_id = ? OR (bin_id IS NULL AND ? IS NULL))
             FOR UPDATE",
            [$itemId, $warehouseId, $bin_id, $bin_id]
        );

        if ($wh_record) {
            db_query("UPDATE warehouse_inventory SET quantity = quantity + ? WHERE id = ?", [$quantitySigned, $wh_record['id']]);
        } else {
            db_query("INSERT INTO warehouse_inventory (product_id, warehouse_id, bin_id, quantity) VALUES (?, ?, ?, ?)", [$itemId, $warehouseId, $bin_id, $quantitySigned]);
        }

        // Also sync to legacy stock_movements table for new record
        $move_type = $quantitySigned > 0 ? 'IN' : 'OUT';
        $bin_code = null;
        if ($bin_id) {
            $bin_res = db_fetch("SELECT code FROM bins WHERE id = ?", [$bin_id]);
            $bin_code = $bin_res['code'] ?? null;
        }

        db_query(
            "INSERT INTO stock_movements (warehouse_id, product_id, movement_type, quantity, bin_location, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$warehouseId, $itemId, $move_type, abs($quantitySigned), $bin_code]
        );
    }
}

/**
 * @param string $type
 * @return int|null
 */
function inventory_transaction_default_direction($type) {
    $catalog = inventory_transaction_type_catalog();
    return isset($catalog[$type]) ? $catalog[$type]['direction'] : null;
}

/**
 * @param array<string,mixed> $data
 * @return int inserted transaction id
 * @throws Exception
 */
function inventory_record_transaction($data) {
    ensure_inventory_transaction_reporting_schema();

    $itemId = intval($data['item_id'] ?? 0);
    $locationId = intval($data['location_id'] ?? 0);
    $transactionType = trim((string)($data['transaction_type'] ?? ''));
    $quantitySigned = floatval($data['quantity_signed'] ?? 0);
    $createdBy = intval($data['created_by'] ?? 0);

    if ($itemId <= 0 || $locationId <= 0 || $createdBy <= 0) {
        throw new Exception('Missing required transaction fields.');
    }
    if ($transactionType === '') {
        throw new Exception('Transaction type is required.');
    }
    if ($quantitySigned === 0) {
        throw new Exception('Transaction quantity cannot be zero.');
    }

    $item = inventory_item_snapshot($itemId);
    if (!$item) {
        throw new Exception('Selected inventory item not found.');
    }

    $unitOfMeasure = trim((string)($data['unit_of_measure'] ?? $item['unit_of_measure']));
    if ($unitOfMeasure === '') {
        $unitOfMeasure = 'PCS';
    }

    $unitCost = isset($data['unit_cost']) ? floatval($data['unit_cost']) : floatval($item['cost_price'] ?? 0);
    $sellingPrice = isset($data['selling_price']) ? floatval($data['selling_price']) : floatval($item['selling_price'] ?? 0);

    $movementReason = trim((string)($data['movement_reason'] ?? ''));
    $reference = trim((string)($data['reference'] ?? ''));
    $referenceType = trim((string)($data['reference_type'] ?? ''));
    $referenceId = isset($data['reference_id']) && $data['reference_id'] !== '' ? intval($data['reference_id']) : null;
    $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? intval($data['customer_id']) : null;
    $supplierId = isset($data['supplier_id']) && $data['supplier_id'] !== '' ? intval($data['supplier_id']) : null;
    $binId = isset($data['bin_id']) && $data['bin_id'] !== '' ? intval($data['bin_id']) : null;
    $notes = trim((string)($data['notes'] ?? ''));

    $quantityAbs = abs($quantitySigned);

    return db_insert(
        "INSERT INTO inventory_transactions
         (item_id, location_id, bin_id, transaction_type, movement_reason, quantity, quantity_signed,
          unit_cost, selling_price, unit_of_measure, customer_id, supplier_id,
          reference, reference_type, reference_id, notes, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
        [
            $itemId,
            $locationId,
            $binId,
            $transactionType,
            $movementReason !== '' ? $movementReason : null,
            $quantityAbs,
            $quantitySigned,
            $unitCost,
            $sellingPrice,
            $unitOfMeasure,
            $customerId,
            $supplierId,
            $reference !== '' ? $reference : null,
            $referenceType !== '' ? $referenceType : null,
            $referenceId,
            $notes !== '' ? $notes : null,
            $createdBy
        ]
    );
}

/**
 * Sync stock movements for a sales order based on current order status.
 *
 * @param int $orderId
 * @param string $status
 * @param int $customerId
 * @param int $userId
 * @param string $orderNumber
 * @return void
 * @throws Exception
 */
function inventory_sync_sales_order_movements($orderId, $status, $customerId, $userId, $orderNumber = '', $locationId = null) {
    ensure_inventory_transaction_reporting_schema();

    $isShipped = in_array($status, ['confirmed', 'in_production', 'shipped', 'delivered'], true);

    // Use provided locationId or default
    if (!$locationId || $locationId <= 0) {
        // Try to get from order if not provided
        $orderInfo = db_fetch("SELECT location_id FROM sales_orders WHERE id = ?", [$orderId]);
        $locationId = ($orderInfo && $orderInfo['location_id']) ? intval($orderInfo['location_id']) : inventory_default_location_id();
    }

    $desiredRows = [];
    if ($isShipped) {
        $desiredRows = db_fetch_all(
            "SELECT sol.item_id,
                    SUM(sol.quantity) AS qty,
                    MAX(sol.unit_price) AS unit_price
             FROM sales_order_lines sol
             WHERE sol.order_id = ?
             GROUP BY sol.item_id",
            [$orderId]
        );
    }

    $desired = [];
    foreach ($desiredRows as $row) {
        $itemId = intval($row['item_id']);
        if ($itemId <= 0) {
            continue;
        }
        $desired[$itemId] = [
            'qty' => floatval($row['qty']),
            'unit_price' => floatval($row['unit_price'] ?? 0)
        ];
    }

    $existingRows = db_fetch_all(
        "SELECT t.item_id,
                SUM(
                    CASE
                        WHEN t.transaction_type = 'sale' THEN ABS(COALESCE(t.quantity_signed, -ABS(t.quantity)))
                        WHEN t.transaction_type = 'stock_return_customer' THEN -ABS(COALESCE(t.quantity_signed, ABS(t.quantity)))
                        ELSE 0
                    END
                ) AS net_sold_qty
         FROM inventory_transactions t
         WHERE t.reference_type = 'sales_order'
           AND t.reference_id = ?
           AND t.transaction_type IN ('sale', 'stock_return_customer')
         GROUP BY t.item_id",
        [$orderId]
    );

    $existing = [];
    foreach ($existingRows as $row) {
        $existing[intval($row['item_id'])] = floatval($row['net_sold_qty']);
    }

    $allItemIds = array_unique(array_merge(array_keys($desired), array_keys($existing)));
    if (empty($allItemIds)) {
        return;
    }

    if (!$locationId || $locationId <= 0) {
        $locationId = inventory_default_location_id();
    }

    foreach ($allItemIds as $itemId) {
        $desiredQty = isset($desired[$itemId]) ? floatval($desired[$itemId]['qty']) : 0;
        $existingQty = isset($existing[$itemId]) ? floatval($existing[$itemId]) : 0;
        $delta = $desiredQty - $existingQty;

        if ($delta === 0) {
            continue;
        }

        $item = inventory_item_snapshot($itemId);
        if (!$item) {
            throw new Exception('Item not found while syncing sales movements.');
        }

        if ($delta > 0) {
            // Need additional outbound stock for sales.
            $qtySigned = -$delta;
            inventory_apply_stock_movement($itemId, $locationId, $qtySigned);

            inventory_record_transaction([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'sale',
                'movement_reason' => 'Sales order shipment',
                'quantity_signed' => $qtySigned,
                'unit_cost' => $item['cost_price'] ?? 0,
                'selling_price' => $desired[$itemId]['unit_price'] ?? ($item['selling_price'] ?? 0),
                'unit_of_measure' => $item['unit_of_measure'] ?? 'PCS',
                'customer_id' => $customerId,
                'reference' => $orderNumber !== '' ? $orderNumber : ('SO#' . $orderId),
                'reference_type' => 'sales_order',
                'reference_id' => $orderId,
                'notes' => 'Auto-posted from sales order status: ' . $status,
                'created_by' => $userId
            ]);
        } else {
            // Need inbound reversal due to de-shipping or quantity reduction.
            $returnQty = abs($delta);
            $qtySigned = $returnQty;
            inventory_apply_stock_movement($itemId, $locationId, $qtySigned);

            inventory_record_transaction([
                'item_id' => $itemId,
                'location_id' => $locationId,
                'transaction_type' => 'stock_return_customer',
                'movement_reason' => 'Sales order reversal',
                'quantity_signed' => $qtySigned,
                'unit_cost' => $item['cost_price'] ?? 0,
                'selling_price' => $desired[$itemId]['unit_price'] ?? ($item['selling_price'] ?? 0),
                'unit_of_measure' => $item['unit_of_measure'] ?? 'PCS',
                'customer_id' => $customerId,
                'reference' => $orderNumber !== '' ? $orderNumber : ('SO#' . $orderId),
                'reference_type' => 'sales_order',
                'reference_id' => $orderId,
                'notes' => 'Auto-reversal from sales order status sync',
                'created_by' => $userId
            ]);
        }
    }
}

/**
 * @param array<string,mixed> $filters
 * @param int|null $limit
 * @return array<int,array<string,mixed>>
 */
function inventory_fetch_transaction_report_rows($filters = [], $limit = null) {
    ensure_inventory_transaction_reporting_schema();

    $signedQtyExpr = "COALESCE(
        t.quantity_signed,
        CASE
            WHEN t.transaction_type IN ('out', 'sale', 'issue_unplanned', 'stock_return_supplier') THEN -ABS(t.quantity)
            WHEN t.transaction_type IN ('in', 'purchase', 'receipt', 'receipt_unplanned', 'stock_return_customer') THEN ABS(t.quantity)
            ELSE t.quantity
        END
    )";

    $where = ["1=1"];
    $params = [];

    $dateFrom = trim((string)($filters['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where[] = "t.created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }

    $dateTo = trim((string)($filters['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where[] = "t.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }

    if (!empty($filters['item_id'])) {
        $where[] = "t.item_id = ?";
        $params[] = intval($filters['item_id']);
    }

    if (!empty($filters['location_id'])) {
        $where[] = "t.location_id = ?";
        $params[] = intval($filters['location_id']);
    }

    if (!empty($filters['company_id'])) {
        $where[] = "i.company_id = ?";
        $params[] = intval($filters['company_id']);
    }

    if (!empty($filters['transaction_type'])) {
        $where[] = "t.transaction_type = ?";
        $params[] = trim((string)$filters['transaction_type']);
    }

    if (!empty($filters['customer_id'])) {
        $where[] = "COALESCE(t.customer_id, so.customer_id) = ?";
        $params[] = intval($filters['customer_id']);
    }

    if (!empty($filters['supplier_id'])) {
        $where[] = "COALESCE(t.supplier_id, po.supplier_id) = ?";
        $params[] = intval($filters['supplier_id']);
    }

    $search = trim((string)($filters['search'] ?? ''));
    if ($search !== '') {
        $where[] = "(i.code LIKE ? OR i.name LIKE ? OR t.reference LIKE ? OR so.order_number LIKE ? OR po.po_number LIKE ? OR t.notes LIKE ?)";
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql = "
        SELECT t.id,
               t.created_at,
               t.transaction_type,
               t.movement_reason,
               t.quantity,
               {$signedQtyExpr} AS quantity_signed,
               ABS({$signedQtyExpr}) AS quantity_abs,
               COALESCE(NULLIF(t.unit_of_measure, ''), NULLIF(i.unit_of_measure, ''), u.code, 'PCS') AS unit_of_measure,
               COALESCE(t.unit_cost, pol.unit_price, i.cost_price, 0) AS unit_cost,
               COALESCE(t.selling_price, soi.unit_price, i.selling_price, 0) AS selling_price,
               i.id AS item_id,
               i.code AS item_code,
               i.name AS item_name,
               l.id AS location_id,
               l.code AS location_code,
               l.name AS location_name,
               usr.username AS created_by_name,
               COALESCE(t.reference, so.order_number, po.po_number, CONCAT('TRX-', t.id)) AS reference_display,
               t.reference,
               t.reference_type,
               t.reference_id,
               t.bin_id,
               b.code AS bin_location,
               t.notes,
               COALESCE(c_direct.id, c_so.id) AS customer_id,
               COALESCE(c_direct.customer_code, c_so.customer_code, '') AS customer_code,
               COALESCE(c_direct.name, c_so.name, '') AS customer_name,
               COALESCE(s_direct.id, s_po.id) AS supplier_id,
               COALESCE(s_direct.supplier_code, s_po.supplier_code, '') AS supplier_code,
               COALESCE(s_direct.name, s_po.name, '') AS supplier_name
        FROM inventory_transactions t
        JOIN inventory_items i ON i.id = t.item_id
        LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
        JOIN locations l ON l.id = t.location_id
        LEFT JOIN users usr ON usr.id = t.created_by
        LEFT JOIN sales_orders so ON t.reference_type = 'sales_order' AND so.id = t.reference_id
        LEFT JOIN purchase_orders po ON t.reference_type = 'purchase_order' AND po.id = t.reference_id
        LEFT JOIN bins b ON t.bin_id = b.id
        LEFT JOIN customers c_direct ON c_direct.id = t.customer_id
        LEFT JOIN customers c_so ON c_so.id = so.customer_id
        LEFT JOIN suppliers s_direct ON s_direct.id = t.supplier_id
        LEFT JOIN suppliers s_po ON s_po.id = po.supplier_id
        LEFT JOIN (
            SELECT order_id, item_id, MAX(unit_price) AS unit_price
            FROM sales_order_lines
            GROUP BY order_id, item_id
        ) soi ON soi.order_id = so.id AND soi.item_id = t.item_id
        LEFT JOIN (
            SELECT po_id, item_id, MAX(unit_price) AS unit_price
            FROM purchase_order_lines
            GROUP BY po_id, item_id
        ) pol ON pol.po_id = po.id AND pol.item_id = t.item_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY t.created_at DESC, t.id DESC";

    if ($limit !== null && intval($limit) > 0) {
        $sql .= " LIMIT " . intval($limit);
    }

    $rows = db_fetch_all($sql, $params);

    $catalog = inventory_transaction_type_catalog();
    foreach ($rows as &$row) {
        $type = $row['transaction_type'];
        if (isset($catalog[$type])) {
            $row['transaction_label'] = $catalog[$type]['label'];
        } else {
            $row['transaction_label'] = ucwords(str_replace('_', ' ', $type));
        }

        $signed = intval($row['quantity_signed']);
        if ($signed > 0) {
            $row['movement_direction'] = 'IN';
        } elseif ($signed < 0) {
            $row['movement_direction'] = 'OUT';
        } else {
            $row['movement_direction'] = 'NEUTRAL';
        }

        $qtyAbs = abs($signed);
        $row['cost_value'] = $qtyAbs * floatval($row['unit_cost'] ?? 0);
        $row['sales_value'] = $qtyAbs * floatval($row['selling_price'] ?? 0);
    }
    unset($row);

    return $rows;
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @return array<string,mixed>
 */
function inventory_transaction_report_summary($rows) {
    $summary = [
        'total_rows' => count($rows),
        'total_in_qty' => 0,
        'total_out_qty' => 0,
        'total_in_cost_value' => 0.0,
        'total_out_cost_value' => 0.0,
        'total_out_sales_value' => 0.0
    ];

    foreach ($rows as $row) {
        $qtySigned = intval($row['quantity_signed'] ?? 0);
        $qtyAbs = abs($qtySigned);
        $unitCost = floatval($row['unit_cost'] ?? 0);
        $selling = floatval($row['selling_price'] ?? 0);

        if ($qtySigned >= 0) {
            $summary['total_in_qty'] += $qtyAbs;
            $summary['total_in_cost_value'] += ($qtyAbs * $unitCost);
        } else {
            $summary['total_out_qty'] += $qtyAbs;
            $summary['total_out_cost_value'] += ($qtyAbs * $unitCost);
            $summary['total_out_sales_value'] += ($qtyAbs * $selling);
        }
    }

    return $summary;
}

/**
 * Checks if any stock take is currently active (in progress or pending approval).
 * This is used to lock other inventory-modifying actions as per user requirements.
 * 
 * @return array|false Returns the first active stock take record or false if none.
 */
function inventory_get_active_stock_take() {
    return db_fetch("
        SELECT st.*, l.name as warehouse_name 
        FROM stock_take_headers st
        JOIN locations l ON st.location_id = l.id
        WHERE st.status IN ('open', 'pending_approval') 
        LIMIT 1
    ");
}
