<?php
/**
 * Generic Dashboard Export Handler
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/export_functions.php';

require_login();
$company_id = $_SESSION['company_id'];

$module = get_param('module');
$format = get_param('format', 'excel'); // pdf, word, excel, email

// Email handling mapping
if ($format === 'email') {
    $email = post('email');
    $format = post('email_format', 'excel');
    $subject = "Export Report: " . ucfirst($module);
    $recipient = $email;
}

$date_from = get_param('date_from', date('Y-m-01'));
$date_to   = get_param('date_to', date('Y-m-t'));

// Dummy Data Gathering Based on Module (in a real scenario, we would use the actual queries 
// from the modules, but since we are generating reports, we will summarize them here).
$data = [];
$options = [
    'title' => ucfirst($module) . ' Analytics Report',
    'company_name' => 'MJR Group ERP',
    'include_summary' => true,
    'filename' => $module . '_report_' . date('Ymd_His') . '.' . ($format == 'excel' ? 'xlsx' : ($format == 'word' ? 'docx' : 'pdf'))
];

// Instead of rewriting logic manually, we will use our export_inventory logic as base, 
// just fetching the raw summarized data.
if ($module === 'sales') {
    $data = db_fetch_all("
        SELECT so.order_number as item_code, c.name as item_name, so.status as category, so.total_amount as total_value, so.order_date as description, 1 as quantity, 0 as reorder_level, 'Completed' as status
        FROM sales_orders so JOIN customers c ON so.customer_id = c.id
        WHERE so.company_id = ? AND so.order_date BETWEEN ? AND ?
    ", [$company_id, $date_from, $date_to]);
} else if ($module === 'financial') {
     $data = db_fetch_all("
        SELECT a.code as item_code, a.name as item_name, a.account_type as category, gl.debit - gl.credit as total_value, gl.transaction_date as description, 1 as quantity, 0 as reorder_level, 'Processed' as status
        FROM general_ledger gl JOIN accounts a ON gl.account_id = a.id
        WHERE gl.transaction_date BETWEEN ? AND ?
    ", [$date_from, $date_to]);
} else if ($module === 'production') {
     $data = db_fetch_all("
        SELECT wo.id as item_code, i.name as item_name, wo.status as category, wo.quantity as quantity, wo.start_date as description, 0 as total_value, 0 as reorder_level, wo.status as status
        FROM work_orders wo JOIN inventory_items i ON wo.product_id = i.id
        WHERE wo.start_date BETWEEN ? AND ?
    ", [$date_from, $date_to]);
} else {
    // Inventory
    $data = db_fetch_all("
        SELECT ii.code as item_code, ii.name as item_name, 'Inventory' as category, COALESCE(SUM(isl.quantity_on_hand),0) as quantity, ii.selling_price as unit_price, (COALESCE(SUM(isl.quantity_on_hand),0)*ii.selling_price) as total_value, ii.reorder_level, 'In Stock' as status
        FROM inventory_items ii LEFT JOIN inventory_stock_levels isl ON ii.id = isl.item_id 
        WHERE ii.is_active = 1
        GROUP BY ii.id
    ");
}

if(empty($data)) {
    set_flash("No data found for this period to export.", "warning");
    redirect($module . '.php');
}

if (isset($email)) {
    // Handle emailing
    $options['subject'] = $subject;
    $options['format'] = $format;
    $options['message'] = "Please find attached the generating $module report.";
    try {
        email_inventory_report($data, $email, $options);
        set_flash("Report emailed successfully to $email.", "success");
    } catch(Exception $e) {
         set_flash("Failed to email: " . $e->getMessage(), "error");
    }
    redirect($module . '.php');
} else {
    try {
        if ($format === 'excel') {
            $filepath = export_inventory_to_excel($data, $options);
        } else if ($format === 'word') {
             $filepath = export_inventory_to_word($data, $options);
        } else if ($format === 'pdf') {
             $filepath = export_inventory_to_pdf($data, $options);
        }
        
        if (isset($filepath)) {
            download_file($filepath, $options['filename']);
        }
    } catch(Exception $e) {
        set_flash("Failed to export: " . $e->getMessage(), "error");
        redirect($module . '.php');
    }
}
?>
