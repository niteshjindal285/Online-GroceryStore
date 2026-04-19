<?php
/**
 * AJAX Add Currency
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
require_permission('manage_procurement');

if (is_post()) {
    $code = strtoupper(sanitize_input(post('code')));
    $name = sanitize_input(post('name'));
    $symbol = post('symbol') ?: '$';
    $rate = floatval(post('exchange_rate', 1.000000));

    if (empty($code) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Code and Name are required.']);
        exit;
    }

    try {
        // Check if code exists
        $existing = db_fetch("SELECT id FROM currencies WHERE code = ?", [$code]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Currency code already exists.']);
            exit;
        }

        $id = db_insert("INSERT INTO currencies (code, name, symbol, exchange_rate, is_active, is_base) VALUES (?, ?, ?, ?, 1, 0)", [$code, $name, $symbol, $rate]);

        if ($id) {
            echo json_encode([
                'success' => true, 
                'id' => $id, 
                'code' => $code,
                'name' => $name, 
                'symbol' => $symbol,
                'rate' => $rate
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add currency.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
