<?php
/**
 * AJAX: Get Invoice Lines for Return Modal
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

header('Content-Type: application/json');
$invoice_id = intval(get('invoice_id'));
if (!$invoice_id) { echo json_encode([]); exit; }

$lines = db_fetch_all("
    SELECT il.id, il.quantity, il.unit_price, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il
    JOIN inventory_items ii ON ii.id = il.item_id
    WHERE il.invoice_id = ?
", [$invoice_id]);

echo json_encode($lines);
