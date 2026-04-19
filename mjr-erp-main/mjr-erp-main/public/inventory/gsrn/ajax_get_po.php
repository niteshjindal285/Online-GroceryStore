<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$po_number = get_param('po_number');

if (!$po_number) {
    echo json_encode(['success' => false, 'message' => 'PO number is required']);
    exit;
}

// Fetch PO header
$po = db_fetch("
    SELECT po.*, s.name as supplier_name, s.id as supplier_id
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.po_number = ? AND po.status IN ('approved', 'sent', 'confirmed')
", [$po_number]);

if (!$po) {
    echo json_encode(['success' => false, 'message' => 'Approved PO not found']);
    exit;
}

// Fetch PO lines
$lines = db_fetch_all("
    SELECT pol.*, i.code, i.name, i.unit_of_measure, i.cost_price
    FROM purchase_order_lines pol
    JOIN inventory_items i ON pol.item_id = i.id
    WHERE pol.po_id = ?
", [$po['id']]);

echo json_encode([
    'success' => true,
    'po' => $po,
    'lines' => $lines
]);
?>
