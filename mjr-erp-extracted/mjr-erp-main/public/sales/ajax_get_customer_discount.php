<?php
/**
 * AJAX Endpoint to get customer specific discounts
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';

require_login();

header('Content-Type: application/json');

$customer_id = get('customer_id');
$item_id = get('item_id');

if (!$customer_id || !$item_id) {
    echo json_encode(['error' => 'Missing parameters', 'discount_percent' => 0]);
    exit;
}

try {
    // Check for specific item discount first
    $item_discount = db_fetch("
        SELECT discount_percent 
        FROM customer_discounts 
        WHERE customer_id = ? AND item_id = ? AND is_active = 1
    ", [$customer_id, $item_id]);

    if ($item_discount) {
        echo json_encode(['discount_percent' => (float)$item_discount['discount_percent']]);
        exit;
    }

    // IF no item discount, check for CATEGORY-level discount
    // First, find what category this item belongs to
    $item_info = db_fetch("SELECT category_id FROM inventory_items WHERE id = ?", [$item_id]);
    if ($item_info && $item_info['category_id']) {
        $category_discount = db_fetch("
            SELECT discount_percent 
            FROM customer_discounts 
            WHERE customer_id = ? AND category_id = ? AND is_active = 1
        ", [$customer_id, $item_info['category_id']]);

        if ($category_discount) {
            echo json_encode(['discount_percent' => (float)$category_discount['discount_percent']]);
            exit;
        }
    }

    // If no specific item or category discount, check for global customer discount
    $global_discount = db_fetch("
        SELECT discount_percent 
        FROM customer_discounts 
        WHERE customer_id = ? AND item_id IS NULL AND category_id IS NULL AND is_active = 1
    ", [$customer_id]);

    if ($global_discount) {
        echo json_encode(['discount_percent' => (float)$global_discount['discount_percent']]);
        exit;
    }

    // No discount
    echo json_encode(['discount_percent' => 0]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage(), 'discount_percent' => 0]);
}
