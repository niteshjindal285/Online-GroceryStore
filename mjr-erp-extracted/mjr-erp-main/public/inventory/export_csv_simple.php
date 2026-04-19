<?php
/**
 * Simple CSV export for inventory page filters.
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$search = get_param('search', '');
$category = get_param('category', '');
$categoryId = get_param('category_id', '');

$sql = "SELECT
    ii.id,
    ii.code AS item_code,
    ii.name AS item_name,
    COALESCE(c.name, '') AS category,
    COALESCE(u.code, ii.unit_of_measure, '') AS unit,
    ii.cost_price,
    ii.selling_price AS unit_price,
    ii.reorder_level,
    COALESCE(SUM(isl.quantity_on_hand), 0) AS quantity,
    (COALESCE(SUM(isl.quantity_on_hand), 0) * ii.selling_price) AS total_value,
    CASE
        WHEN COALESCE(SUM(isl.quantity_on_hand), 0) = 0 THEN 'Out of Stock'
        WHEN COALESCE(SUM(isl.quantity_on_hand), 0) <= ii.reorder_level THEN 'Low Stock'
        ELSE 'In Stock'
    END AS stock_status
FROM inventory_items ii
LEFT JOIN categories c ON c.id = ii.category_id
LEFT JOIN units_of_measure u ON u.id = ii.unit_of_measure_id
LEFT JOIN inventory_stock_levels isl ON isl.item_id = ii.id
WHERE ii.is_active = 1";

$params = [];

if (!empty($categoryId)) {
    $sql .= " AND ii.category_id = ?";
    $params[] = $categoryId;
} elseif (!empty($category)) {
    $sql .= " AND c.name = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (ii.code LIKE ? OR ii.name LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " GROUP BY
    ii.id,
    ii.code,
    ii.name,
    c.name,
    u.code,
    ii.unit_of_measure,
    ii.cost_price,
    ii.selling_price,
    ii.reorder_level
ORDER BY ii.code";

$rows = db_fetch_all($sql, $params);

$filename = 'inventory_report_' . date('Y-m-d_His') . '.csv';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

fputcsv($output, [
    'Item Code',
    'Item Name',
    'Category',
    'Quantity',
    'Unit',
    'Cost Price',
    'Selling Price',
    'Total Value',
    'Reorder Level',
    'Status'
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['item_code'] ?? '',
        $row['item_name'] ?? '',
        $row['category'] ?? '',
        (int) ($row['quantity'] ?? 0),
        $row['unit'] ?? '',
        number_format((float) ($row['cost_price'] ?? 0), 2, '.', ''),
        number_format((float) ($row['unit_price'] ?? 0), 2, '.', ''),
        number_format((float) ($row['total_value'] ?? 0), 2, '.', ''),
        (int) ($row['reorder_level'] ?? 0),
        $row['stock_status'] ?? ''
    ]);
}

fclose($output);
exit;
