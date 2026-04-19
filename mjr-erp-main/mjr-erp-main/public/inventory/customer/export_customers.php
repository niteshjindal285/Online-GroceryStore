<?php
/**
 * Export Customers to CSV
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$company_id = $_SESSION['company_id'];

// Get customers
$customers = db_fetch_all("
    SELECT customer_code, name, email, phone, address, city, state, postal_code, country, payment_terms, credit_limit, is_active
    FROM customers
    WHERE company_id = ?
    ORDER BY name ASC
", [$company_id]);

// Headers for CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=customers_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

// Column headers
fputcsv($output, ['Code', 'Name', 'Email', 'Phone', 'Address', 'City', 'State', 'Postal Code', 'Country', 'Payment Terms', 'Credit Limit', 'Status']);

foreach ($customers as $c) {
    fputcsv($output, [
        $c['customer_code'],
        $c['name'],
        $c['email'],
        $c['phone'],
        $c['address'],
        $c['city'],
        $c['state'],
        $c['postal_code'],
        $c['country'],
        $c['payment_terms'],
        number_format($c['credit_limit'], 2),
        $c['is_active'] ? 'Active' : 'Inactive'
    ]);
}

fclose($output);
exit;

