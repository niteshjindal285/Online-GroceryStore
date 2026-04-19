<?php
/**
 * Export Orders to CSV
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$company_id = $_SESSION['company_id'];

// Get orders with customer details
$orders = db_fetch_all("
    SELECT so.order_number, c.name as customer_name, so.order_date, so.required_date, so.status, so.payment_status, so.total_amount
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.company_id = ?
    ORDER BY so.order_date DESC
", [$company_id]);

// Headers for CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=sales_orders_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Order Number', 'Customer', 'Order Date', 'Required Date', 'Status', 'Payment Status', 'Total Amount']);

foreach ($orders as $order) {
    fputcsv($output, [
        $order['order_number'],
        $order['customer_name'],
        $order['order_date'],
        $order['required_date'],
        ucfirst($order['status']),
        ucfirst(str_replace('_', ' ', $order['payment_status'])),
        number_format($order['total_amount'], 2)
    ]);
}

fclose($output);
exit;
