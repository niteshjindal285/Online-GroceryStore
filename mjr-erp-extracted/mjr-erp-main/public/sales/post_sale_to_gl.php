<?php
/**
 * Post Sale Order to General Ledger
 * Auto-creates GL journal entries when a Sales Order is paid/delivered
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('Invalid request.', 'error');
    redirect('sales/orders.php');
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('Invalid security token.', 'error');
    redirect('sales/orders.php');
    exit;
}

$order_id = intval($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    set_flash('Invalid order ID.', 'error');
    redirect('sales/orders.php');
    exit;
}

db_begin_transaction();
try {
    // Fetch the order
    $order = db_fetch("
        SELECT so.*, c.name as customer_name
        FROM sales_orders so
        JOIN customers c ON so.customer_id = c.id
        WHERE so.id = ?
    ", [$order_id]);

    if (!$order) throw new Exception('Order not found.');

    // Check if already posted to GL
    $already_posted = db_fetch(
        "SELECT COUNT(*) as cnt FROM general_ledger WHERE reference_type = 'sales_order' AND reference_id = ?",
        [$order_id]
    );
    if ($already_posted['cnt'] > 0) {
        throw new Exception('This order has already been posted to the General Ledger.');
    }

    // Only allow posting for confirmed/delivered/paid orders
    $allowed_statuses = ['confirmed', 'delivered', 'shipped'];
    if (!in_array($order['status'], $allowed_statuses) && $order['payment_status'] !== 'paid') {
        throw new Exception('Order must be Confirmed, Shipped, Delivered, or Paid before posting to GL.');
    }

    $post_date       = $order['order_date'];
    $subtotal        = floatval($order['subtotal']);
    $tax_amount      = floatval($order['tax_amount']);
    $discount_amount = floatval($order['discount_amount'] ?? 0);
    $total_amount    = floatval($order['total_amount']);

    // Find the required accounts (use main accounts by name pattern)
    $cash_account = db_fetch("SELECT id FROM accounts WHERE name LIKE '%Cash%' AND account_type = 'asset' AND is_active = 1 ORDER BY level ASC LIMIT 1");
    $ar_account   = db_fetch("SELECT id FROM accounts WHERE name LIKE '%Receivable%' AND account_type = 'asset' AND is_active = 1 ORDER BY level ASC LIMIT 1");
    $rev_account  = db_fetch("SELECT id FROM accounts WHERE name LIKE '%Sales%Revenue%' AND account_type = 'revenue' AND is_active = 1 ORDER BY level ASC LIMIT 1");
    $tax_account  = db_fetch("SELECT id FROM accounts WHERE (name LIKE '%Tax%Payable%' OR name LIKE '%GST%') AND account_type = 'liability' AND is_active = 1 ORDER BY level ASC LIMIT 1");

    // Fallback: use accounts receivable if no cash account found
    $debit_account = $cash_account ?? $ar_account;
    if (!$debit_account) throw new Exception('No Cash or Accounts Receivable account found. Please create one in Chart of Accounts first.');
    if (!$rev_account) throw new Exception('No Sales Revenue account found. Please create one in Chart of Accounts first.');

    $desc = "Sales Order {$order['order_number']} – {$order['customer_name']}";

    // Helper: get running balance for an account
    $get_balance = function($account_id) {
        $row = db_fetch("SELECT COALESCE(SUM(debit)-SUM(credit),0) AS bal FROM general_ledger WHERE account_id = ?", [$account_id]);
        return floatval($row['bal'] ?? 0);
    };

    // 1) Debit: Cash/AR for full total
    $bal = $get_balance($debit_account['id']) + $total_amount;
    db_insert("INSERT INTO general_ledger (account_id,transaction_date,description,debit,credit,balance,reference_type,reference_id,created_at) VALUES (?,?,?,?,?,?,'sales_order',?,NOW())",
        [$debit_account['id'], $post_date, $desc, $total_amount, 0, $bal, $order_id]);

    // 2) Credit: Sales Revenue for subtotal
    $bal = $get_balance($rev_account['id']) - $subtotal;
    db_insert("INSERT INTO general_ledger (account_id,transaction_date,description,debit,credit,balance,reference_type,reference_id,created_at) VALUES (?,?,?,?,?,?,'sales_order',?,NOW())",
        [$rev_account['id'], $post_date, $desc, 0, $subtotal, $bal, $order_id]);

    // 3) Credit: Tax Payable (if tax exists and tax account found)
    if ($tax_amount > 0 && $tax_account) {
        $bal = $get_balance($tax_account['id']) - $tax_amount;
        db_insert("INSERT INTO general_ledger (account_id,transaction_date,description,debit,credit,balance,reference_type,reference_id,created_at) VALUES (?,?,?,?,?,?,'sales_order',?,NOW())",
            [$tax_account['id'], $post_date, $desc . ' (Tax)', 0, $tax_amount, $bal, $order_id]);
    }

    db_commit();

    // Write audit log
    try {
        $current_user = db_fetch("SELECT username FROM users WHERE id = ?", [current_user_id()]);
        db_query(
            "INSERT IGNORE INTO finance_audit_log (user_id, username, action, table_name, record_id, details, created_at)
             VALUES (?, ?, 'POST_SALE_TO_GL', 'sales_orders', ?, ?, NOW())",
            [current_user_id(), $current_user['username'] ?? 'unknown', $order_id,
             "Posted Sales Order {$order['order_number']} to General Ledger. Total: {$order['total_amount']}"]
        );
    } catch (Exception $ae) { /* silently skip if table not yet created */ }

    set_flash("Sales Order {$order['order_number']} posted to General Ledger successfully!", 'success');

} catch (Exception $e) {
    db_rollback();
    log_error("Error posting sale to GL (Order #{$order_id}): " . $e->getMessage());
    set_flash('Error: ' . $e->getMessage(), 'error');
}

redirect("view_order.php?id={$order_id}");
