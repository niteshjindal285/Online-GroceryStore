<?php
/**
 * Export Inventory Report Handler - NO CSV
 * 
 * Supports: Excel, PDF, Word, Email only
 * Updated to match your actual database structure
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/export_functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Check if user is logged in
require_login();

// Get current user
$user = current_user();
$company_id = $user['company_id'];

// Get export parameters
$format = get_param('format', 'excel'); // excel, pdf, word, email ONLY
$filters = [
    'category_id' => get_param('category_id', ''),
    'search' => get_param('search', ''),
    'low_stock' => get_param('low_stock', ''),
    'location_id' => get_param('location_id', ''),
    'date_from' => get_param('date_from', ''),
    'date_to' => get_param('date_to', '')
];

try {
    // Build query - CORRECTED FOR YOUR DATABASE STRUCTURE
    $sql = "SELECT 
        ii.id,
        ii.code AS item_code,
        ii.name AS item_name,
        ii.description,
        c.name AS category,
        COALESCE(SUM(isl.quantity_on_hand), 0) AS quantity,
        ii.unit_of_measure AS unit,
        ii.cost_price,
        ii.selling_price AS unit_price,
        ii.reorder_level,
        ii.reorder_quantity,
        ii.max_stock_level,
        ii.barcode,
        (COALESCE(SUM(isl.quantity_on_hand), 0) * ii.selling_price) AS total_value,
        ii.updated_at AS last_updated,
        CASE 
            WHEN COALESCE(SUM(isl.quantity_on_hand), 0) <= ii.reorder_level THEN 'Low Stock'
            WHEN COALESCE(SUM(isl.quantity_on_hand), 0) = 0 THEN 'Out of Stock'
            ELSE 'In Stock'
        END AS stock_status
    FROM 
        inventory_items ii
    LEFT JOIN 
        categories c ON ii.category_id = c.id
    LEFT JOIN 
        inventory_stock_levels isl ON ii.id = isl.item_id
    WHERE 
        ii.is_active = 1";
    
    $params = [];
    
    // Apply filters
    if (!empty($filters['category_id'])) {
        $sql .= " AND ii.category_id = ?";
        $params[] = $filters['category_id'];
    }
    
    if (!empty($filters['search'])) {
        $sql .= " AND (ii.name LIKE ? OR ii.code LIKE ? OR ii.description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($filters['location_id'])) {
        $sql .= " AND isl.location_id = ?";
        $params[] = $filters['location_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $sql .= " AND ii.updated_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $sql .= " AND ii.updated_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    $sql .= " GROUP BY ii.id";
    
    // Apply low stock filter after GROUP BY
    if (!empty($filters['low_stock'])) {
        $sql .= " HAVING quantity <= ii.reorder_level";
    }
    
    $sql .= " ORDER BY ii.name";
    
    // Fetch data
    $inventory_data = db_fetch_all($sql, $params);
    
    if (empty($inventory_data)) {
        set_flash("No data available to export.", "warning");
        redirect("inventory_list.php");
    }
    
    // Get company name for export
    $company_sql = "SELECT name FROM companies WHERE id = ?";
    $company = db_fetch($company_sql, [$company_id]);
    $company_name = $company ? $company['name'] : 'Your Company';
    
    // Export options
    $export_options = [
        'title' => 'Inventory Report',
        'company_name' => $company_name,
        'include_summary' => true
    ];
    
    // Add filter info to title if filters are applied
    $titleSuffix = [];
    if (!empty($filters['category_id'])) {
        $cat_name = db_fetch("SELECT name FROM categories WHERE id = ?", [$filters['category_id']]);
        if ($cat_name) {
            $titleSuffix[] = $cat_name['name'];
        }
    }
    if (!empty($filters['low_stock'])) {
        $titleSuffix[] = 'Low Stock Items';
    }
    if (!empty($filters['location_id'])) {
        $loc_name = db_fetch("SELECT name FROM locations WHERE id = ?", [$filters['location_id']]);
        if ($loc_name) {
            $titleSuffix[] = 'Location: ' . $loc_name['name'];
        }
    }
    if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
        $titleSuffix[] = 'Date Filtered';
    }
    
    if (!empty($titleSuffix)) {
        $export_options['title'] .= ' - ' . implode(', ', $titleSuffix);
    }
    
    // Handle different export formats - NO CSV
    switch ($format) {
        case 'excel':
            $export_options['filename'] = 'inventory_report_' . date('Y-m-d_His') . '.xlsx';
            $filepath = export_inventory_to_excel($inventory_data, $export_options);
            download_file($filepath, $export_options['filename']);
            break;
            
        case 'pdf':
            $export_options['filename'] = 'inventory_report_' . date('Y-m-d_His') . '.pdf';
            $filepath = export_inventory_to_pdf($inventory_data, $export_options);
            download_file($filepath, $export_options['filename']);
            break;
            
        case 'word':
            $export_options['filename'] = 'inventory_report_' . date('Y-m-d_His') . '.docx';
            $filepath = export_inventory_to_word($inventory_data, $export_options);
            download_file($filepath, $export_options['filename']);
            break;
            
        case 'email':
            // Get recipient email (from POST or session)
            $recipient = post('email', $user['email']);
            
            if (!validate_email($recipient)) {
                set_flash("Invalid email address.", "error");
                redirect("inventory_list.php");
            }
            
            // Get preferred format for email attachment
            $email_format = post('email_format', 'excel');
            $export_options['format'] = $email_format;
            $export_options['subject'] = 'Inventory Report - ' . date('F j, Y');
            $export_options['message'] = "Dear " . ($user['username'] ?? 'User') . ",\n\n";
            $export_options['message'] .= "Please find attached the inventory report as requested.\n\n";
            $export_options['message'] .= "Report Summary:\n";
            $export_options['message'] .= "- Total Items: " . count($inventory_data) . "\n";
            
            // Calculate totals
            $total_quantity = 0;
            $total_value = 0;
            $low_stock_count = 0;
            
            foreach ($inventory_data as $item) {
                $total_quantity += $item['quantity'];
                $total_value += $item['total_value'];
                if ($item['quantity'] <= $item['reorder_level']) {
                    $low_stock_count++;
                }
            }
            
            $export_options['message'] .= "- Total Quantity: " . number_format($total_quantity) . "\n";
            $export_options['message'] .= "- Total Value: $" . number_format($total_value, 2) . "\n";
            $export_options['message'] .= "- Low Stock Items: " . $low_stock_count . "\n";
            $export_options['message'] .= "- Generated on: " . date('F j, Y g:i A') . "\n\n";
            $export_options['message'] .= "Best regards,\n";
            $export_options['message'] .= $company_name;
            
            $success = email_inventory_report($inventory_data, $recipient, $export_options);
            
            if ($success) {
                set_flash("Report emailed successfully to " . $recipient, "success");
            } else {
                set_flash("Failed to email report. Please try again.", "error");
            }
            redirect("inventory_list.php");
            break;
            
        default:
            set_flash("Invalid export format. Please choose Excel, PDF, Word, or Email.", "error");
            redirect("inventory_list.php");
    }
    
} catch (Exception $e) {
    log_error("Export error: " . $e->getMessage());
    set_flash("Export failed: " . $e->getMessage(), "error");
    redirect("inventory_list.php");
}
?>
