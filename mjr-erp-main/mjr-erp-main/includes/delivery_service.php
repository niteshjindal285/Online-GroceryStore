<?php
/**
 * Delivery Management Service
 * 
 * Handles partial fulfillment, delivery tracking, and balance calculations.
 */

/**
 * Resolve the linked sales order ID for an invoice.
 */
function delivery_resolve_order_id_from_invoice($invoice_id) {
    if (!$invoice_id) {
        return null;
    }

    $invoice = db_fetch("SELECT * FROM invoices WHERE id = ?", [$invoice_id]);
    if (!$invoice) {
        return null;
    }

    if (!empty($invoice['so_id'])) {
        return (int) $invoice['so_id'];
    }

    if (!empty($invoice['order_id'])) {
        return (int) $invoice['order_id'];
    }

    return null;
}

/**
 * Ensure there is a delivery_schedule row for the invoice.
 */
function delivery_ensure_schedule($invoice_id) {
    if (!$invoice_id) {
        return null;
    }

    $schedule = db_fetch("SELECT * FROM delivery_schedule WHERE invoice_id = ? LIMIT 1", [$invoice_id]);
    if ($schedule) {
        return $schedule['id'];
    }

    $schedule_id = db_insert(
        "INSERT INTO delivery_schedule (invoice_id, status, created_by) VALUES (?, 'pending', ?)",
        [$invoice_id, $_SESSION['user_id']]
    );

    return $schedule_id;
}

/**
 * Get fulfillment details for an invoice or order
 * 
 * @param int $id The ID of the sales order or invoice
 * @param string $type 'order' or 'invoice'
 * @return array List of items with ordered, delivered, and balance quantities
 */
function delivery_get_fulfillment($id, $type = 'order') {
    $order_id = ($type === 'invoice')
        ? delivery_resolve_order_id_from_invoice($id)
        : (int) $id;

    if (!$order_id) {
        return [];
    }

    // Get all line items for the order
    $sql = "SELECT sol.id as line_id, sol.item_id, sol.quantity as ordered_qty, 
                   i.name as item_name, i.code as item_code,
                   (SELECT COALESCE(SUM(di.quantity_delivered), 0) 
                    FROM delivery_items di 
                    WHERE di.sales_order_line_id = sol.id) as delivered_qty
            FROM sales_order_lines sol
            JOIN inventory_items i ON sol.item_id = i.id
            WHERE sol.order_id = ?";
            
    $items = db_fetch_all($sql, [$order_id]);
    
    foreach ($items as &$item) {
        $item['balance'] = $item['ordered_qty'] - $item['delivered_qty'];
    }
    
    return $items;
}

/**
 * Record a new delivery
 * 
 * @param array $data Header info (invoice_id, order_id, notes, courier, tracking)
 * @param array $items List of items (line_id, item_id, qty)
 * @return int|bool Delivery ID or false on failure
 */
function delivery_create($data, $items) {
    db_begin_transaction();
    try {
        // 1. Generate delivery number
        $last_id = db_fetch("SELECT id FROM deliveries ORDER BY id DESC LIMIT 1");
        $next_num = ($last_id ? $last_id['id'] + 1 : 1);
        $delivery_num = "DLV-" . str_pad($next_num, 6, "0", STR_PAD_LEFT);
        
        // 2. Insert delivery header
        $sql_header = "INSERT INTO deliveries (delivery_number, invoice_id, order_id, courier_name, tracking_number, notes, created_by, company_id) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $delivery_id = db_insert($sql_header, [
            $delivery_num,
            $data['invoice_id'] ?? null,
            $data['order_id'] ?? null,
            $data['courier_name'] ?? null,
            $data['tracking_number'] ?? null,
            $data['notes'] ?? null,
            $_SESSION['user_id'],
            $_SESSION['company_id'] ?? 1
        ]);
        
        // 3. Insert delivery items and record stock movements
        $sql_item = "INSERT INTO delivery_items (delivery_id, sales_order_line_id, item_id, quantity_delivered) 
                     VALUES (?, ?, ?, ?)";
        
        // Load inventory service if needed
        if (!function_exists('inventory_record_transaction')) {
            require_once __DIR__ . '/inventory_transaction_service.php';
        }

        foreach ($items as $item) {
            $qty = floatval($item['qty']);
            if ($qty > 0) {
                db_insert($sql_item, [
                    $delivery_id,
                    $item['line_id'],
                    $item['item_id'],
                    $qty
                ]);

                // Record Inventory Transaction
                $snapshot = inventory_item_snapshot($item['item_id']);
                $location_id = $data['location_id'] ?? 1; // Default to main
                
                // Direction is -1 for sale/delivery
                inventory_apply_stock_movement($item['item_id'], $location_id, -$qty);
                
                inventory_record_transaction([
                    'item_id' => $item['item_id'],
                    'location_id' => $location_id,
                    'transaction_type' => 'sale',
                    'quantity_signed' => -$qty,
                    'unit_cost' => $snapshot['cost_price'] ?? 0,
                    'selling_price' => $snapshot['selling_price'] ?? 0,
                    'reference' => $delivery_num,
                    'reference_type' => 'delivery',
                    'reference_id' => $delivery_id,
                    'notes' => 'Shipment for ' . ($data['invoice_id'] ? "Invoice " . $data['invoice_id'] : "Order " . $data['order_id']),
                    'created_by' => $_SESSION['user_id']
                ]);
            }
        }
        
        // 4. Update overall status
        delivery_update_overall_status($data['order_id'] ?? null, $data['invoice_id'] ?? null);
        
        db_commit();
        return $delivery_id;
        
    } catch (Exception $e) {
        db_rollback();
        error_log("Delivery creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Update the delivery_status for sales_orders and invoices
 */
function delivery_update_overall_status($order_id = null, $invoice_id = null) {
    if (!$order_id && $invoice_id) {
        $order_id = delivery_resolve_order_id_from_invoice($invoice_id);
    }
    
    if (!$order_id) return;
    
    $items = delivery_get_fulfillment($order_id, 'order');
    
    $total_ordered = 0;
    $total_delivered = 0;
    
    foreach ($items as $item) {
        $total_ordered += $item['ordered_qty'];
        $total_delivered += $item['delivered_qty'];
    }
    
    $status = 'open';
    $schedule_status = 'pending';
    if ($total_delivered >= $total_ordered) {
        $status = 'completed';
        $schedule_status = 'delivered';
    } elseif ($total_delivered > 0) {
        $status = 'pending';
        $schedule_status = 'partial';
    }
    
    db_execute("UPDATE sales_orders SET delivery_status = ? WHERE id = ?", [$status, $order_id]);

    if ($invoice_id) {
        db_execute("UPDATE invoices SET delivery_status = ? WHERE id = ?", [$status, $invoice_id]);
        delivery_ensure_schedule($invoice_id);
        $last_delivery = db_fetch(
            "SELECT MAX(delivery_date) AS last_delivery_date FROM deliveries WHERE invoice_id = ?",
            [$invoice_id]
        );
        db_execute(
            "UPDATE delivery_schedule SET status = ?, delivered_date = ? WHERE invoice_id = ?",
            [
                $schedule_status,
                $schedule_status === 'delivered' ? ($last_delivery['last_delivery_date'] ?? date('Y-m-d H:i:s')) : null,
                $invoice_id
            ]
        );
    }
}
