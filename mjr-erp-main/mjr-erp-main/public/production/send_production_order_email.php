<?php
/**
 * Send Production Order Email
 * Sends production order details via email
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if (!is_post()) {
    set_flash('Invalid request method.', 'error');
    redirect('production_orders.php');
}

$csrf_token = post('csrf_token');

if (!verify_csrf_token($csrf_token)) {
    set_flash('Invalid security token.', 'error');
    redirect('production_orders.php');
}

try {
    $wo_id = post('wo_id');
    $recipient_email = post('recipient_email');
    $email_subject = post('email_subject');
    $email_message = post('email_message');
    
    // Validate inputs
    if (empty($wo_id) || empty($recipient_email)) {
        throw new Exception('Production order ID and recipient email are required');
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Get production order details
    $work_order = db_fetch("
        SELECT wo.*, 
               i.code as product_code, i.name as product_name, i.unit_of_measure,
               l.name as location_name,
               u.username as created_by_name
        FROM work_orders wo
        LEFT JOIN inventory_items i ON wo.product_id = i.id
        LEFT JOIN locations l ON wo.location_id = l.id
        LEFT JOIN users u ON wo.created_by = u.id
        WHERE wo.id = ?
    ", [$wo_id]);
    
    if (!$work_order) {
        throw new Exception('Production order not found');
    }
    
    // Prepare email
    $to = $recipient_email;
    $subject = !empty($email_subject) ? $email_subject : "Production Order - " . $work_order['wo_number'];
    $message = !empty($email_message) ? $email_message : "Please find the production order details below.";
    
    // Add email headers for HTML format
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: MJR Group ERP <noreply@mjrgroup.com>" . "\r\n";
    
    // Create HTML email body
    $html_message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .header { background-color: #343a40; color: white; padding: 20px; }
            .content { padding: 20px; }
            .invoice-details { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; }
            .footer { background-color: #e9ecef; padding: 10px; text-align: center; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 10px; border: 1px solid #dee2e6; text-align: left; }
            th { background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>MJR Group ERP - Production Order</h2>
        </div>
        <div class='content'>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            
            <div class='invoice-details'>
                <h3>Production Order Details</h3>
                <table>
                    <tr>
                        <th>Production Order Number</th>
                        <td>" . htmlspecialchars($work_order['wo_number']) . "</td>
                    </tr>
                    <tr>
                        <th>Product</th>
                        <td>" . htmlspecialchars($work_order['product_code']) . " - " . htmlspecialchars($work_order['product_name']) . "</td>
                    </tr>
                    <tr>
                        <th>Quantity</th>
                        <td>" . $work_order['quantity'] . " " . htmlspecialchars($work_order['unit_of_measure']) . "</td>
                    </tr>
                    <tr>
                        <th>Production Location</th>
                        <td>" . htmlspecialchars($work_order['location_name']) . "</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>" . ucfirst(str_replace('_', ' ', $work_order['status'])) . "</td>
                    </tr>
                    <tr>
                        <th>Priority</th>
                        <td>" . ucfirst($work_order['priority']) . "</td>
                    </tr>";
    
    if ($work_order['start_date']) {
        $html_message .= "
                    <tr>
                        <th>Start Date</th>
                        <td>" . format_date($work_order['start_date']) . "</td>
                    </tr>";
    }
    
    if ($work_order['due_date']) {
        $html_message .= "
                    <tr>
                        <th>Due Date</th>
                        <td>" . format_date($work_order['due_date']) . "</td>
                    </tr>";
    }
    
    if ($work_order['estimated_cost'] > 0) {
        $html_message .= "
                    <tr>
                        <th>Estimated Cost</th>
                        <td>$" . number_format($work_order['estimated_cost'], 2) . "</td>
                    </tr>
                    <tr>
                        <th>Tax Amount</th>
                        <td>$" . number_format($work_order['tax_amount'] ?: 0, 2) . "</td>
                    </tr>
                    <tr style='background-color: #fff3cd;'>
                        <th><strong>Total Cost</strong></th>
                        <td><strong>$" . number_format($work_order['total_cost'] ?: 0, 2) . "</strong></td>
                    </tr>";
    }
    
    if ($work_order['notes']) {
        $html_message .= "
                    <tr>
                        <th>Notes</th>
                        <td>" . nl2br(htmlspecialchars($work_order['notes'])) . "</td>
                    </tr>";
    }
    
    $html_message .= "
                </table>
            </div>
        </div>
        <div class='footer'>
            <p>This is an automated email from MJR Group ERP System.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </body>
    </html>
    ";
    
    // Send email using PHP mail function
    // Note: In production, you should use a proper email library like PHPMailer or SwiftMailer
    // and configure SMTP settings properly
    $email_sent = mail($to, $subject, $html_message, $headers);
    
    if ($email_sent) {
        set_flash('Production order sent successfully to ' . $recipient_email, 'success');
    } else {
        throw new Exception('Failed to send email. Please check your email configuration.');
    }
    
} catch (Exception $e) {
    log_error("Error sending production order email: " . $e->getMessage());
    set_flash('Error sending email: ' . $e->getMessage(), 'error');
}

// Redirect back to production order view
redirect('view_production_order.php?id=' . $wo_id);
