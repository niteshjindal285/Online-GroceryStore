<?php
/**
 * AJAX - Search items for Price Change module
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category_id'] ?? 0);

$where = ["i.is_active = 1"];
$params = [];

if ($search !== '') {
    $where[] = "(i.code LIKE ? OR i.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_id > 0) {
    $where[] = "i.category_id = ?";
    $params[] = $category_id;
}

$sql = "
    SELECT i.id, i.code, i.name, i.cost_price, i.selling_price, c.name as category_name
    FROM inventory_items i
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    LIMIT 50
";

$items = db_fetch_all($sql, $params);

header('Content-Type: application/json');
echo json_encode($items);
exit;
