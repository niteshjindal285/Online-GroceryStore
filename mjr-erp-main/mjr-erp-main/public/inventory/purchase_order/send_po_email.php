<?php
/**
 * Send Purchase Order Email
 * Sends purchase order via email
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$company_id = active_company_id(1);

if (!is_post()) {
    set_flash('Invalid request method.', 'error');
    redirect('purchase_orders.php');
}

$csrf_token = post('csrf_token');

if (!verify_csrf_token($csrf_token)) {
    set_flash('Invalid security token.', 'error');
    redirect('purchase_orders.php');
}

try {
    $po_id = post('po_id');
    $recipient_email = post('recipient_email');
    $cc_email = post('cc_email');
    $email_subject = post('email_subject');
    $email_message = post('email_message');
    
    // Validate inputs
    if (empty($po_id) || empty($recipient_email)) {
        throw new Exception('Purchase order ID and recipient email are required');
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Get purchase order details
    $po = db_fetch("
        SELECT po.*, s.name as supplier_name, s.supplier_code, s.contact_person, s.email, s.phone, s.address,
               u.username as created_by_name
        FROM purchase_orders po
        LEFT JOIN suppliers s ON po.supplier_id = s.id
        LEFT JOIN users u ON po.created_by = u.id
        WHERE po.id = ? AND po.company_id = ?
    ", [$po_id, $company_id]);
    
    if (!$po) {
        throw new Exception('Purchase order not found');
    }
    
    // Get PO line items
    $po_lines = db_fetch_all("
        SELECT pol.*, i.code, i.name as item_name
        FROM purchase_order_lines pol
        JOIN inventory_items i ON pol.item_id = i.id
        WHERE pol.po_id = ?
    ", [$po_id]);
    
    // Prepare email
    $to = $recipient_email;
    $subject = !empty($email_subject) ? $email_subject : "Purchase Order - " . $po['po_number'];
    $message = !empty($email_message) ? $email_message : "Please find the purchase order details below.";
    
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
            .text-end { text-align: right; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>MJR Group ERP - Purchase Order</h2>
        </div>
        <div class='content'>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            
            <div class='invoice-details'>
                <h3>Purchase Order Information</h3>
                <table>
                    <tr>
                        <th>PO Number</th>
                        <td>" . htmlspecialchars($po['po_number']) . "</td>
                    </tr>
                    <tr>
                        <th>Supplier</th>
                        <td>" . htmlspecialchars($po['supplier_name']) . "</td>
                    </tr>
                    <tr>
                        <th>Order Date</th>
                        <td>" . format_date($po['order_date']) . "</td>
                    </tr>
                    <tr>
                        <th>Expected Delivery</th>
                        <td>" . format_date($po['expected_delivery_date']) . "</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>" . ucfirst($po['status']) . "</td>
                    </tr>
                </table>
                
                <h3>Order Items</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th class='text-end'>Quantity</th>
                            <th class='text-end'>Unit Price</th>
                            <th class='text-end'>Total</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($po_lines as $line) {
        $html_message .= "
                        <tr>
                            <td>" . htmlspecialchars($line['code']) . "</td>
                            <td><strong>" . htmlspecialchars($line['item_name']) . "</strong></td>
                            <td class='text-end'>" . $line['quantity'] . "</td>
                            <td class='text-end'>$" . number_format((float)($line['unit_price'] ?? 0), 2) . "</td>
                            <td class='text-end'>$" . number_format((float)($line['line_total'] ?? 0), 2) . "</td>
                        </tr>";
    }
    
    $html_message .= "
                    </tbody>
                </table>
                
                <h3>Order Summary</h3>
                <table>
                    <tr>
                        <th>Subtotal</th>
                        <td class='text-end'>$" . number_format($po['subtotal'], 2) . "</td>
                    </tr>
                    <tr>
                        <th>Tax Amount</th>
                        <td class='text-end'>$" . number_format($po['tax_amount'], 2) . "</td>
                    </tr>
                    <tr>
                        <th>Shipping Cost</th>
                        <td class='text-end'>$" . number_format($po['shipping_cost'], 2) . "</td>
                    </tr>
                    <tr style='background-color: #fff3cd;'>
                        <th><strong>Total Amount</strong></th>
                        <td class='text-end'><strong>$" . number_format($po['total_amount'], 2) . "</strong></td>
                    </tr>
                </table>
            </div>";
    
    if ($po['notes']) {
        $html_message .= "
            <div style='margin-top: 20px;'>
                <h4>Notes</h4>
                <p>" . nl2br(htmlspecialchars($po['notes'])) . "</p>
            </div>";
    }
    
    $html_message .= "
        </div>
        <div class='footer'>
            <p>This is an automated email from MJR Group ERP System.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </body>
    </html>
    ";
    
    // Send email using PHPMailer
    require_once __DIR__ . '/../../../includes/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../../../includes/PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../../../includes/PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/../../../includes/email_config.php';

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'tls' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);

        if (!empty($cc_email)) {
            $cc_list = array_map('trim', explode(',', $cc_email));
            foreach ($cc_list as $cc) {
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = strip_tags($message);

        $mail->send();
        
        // Update PO status to 'sent' and log history
        db_query("UPDATE purchase_orders SET status = 'sent' WHERE id = ?", [$po_id]);
        log_po_history($po_id, 'sent', "PO manually emailed to: $recipient_email");
        
        set_flash('Purchase order sent successfully to ' . $recipient_email, 'success');
    } catch (PHPMailer\PHPMailer\Exception $e) {
        // Log the error
        log_error("PHPMailer Error for PO {$po['po_number']}: " . $mail->ErrorInfo);
        set_flash('The Purchase Order has been processed, but the email could not be sent. Mailer Error: ' . $mail->ErrorInfo, 'warning');
    }
    
} catch (Exception $e) {
    log_error("Error sending purchase order email: " . $e->getMessage());
    set_flash('Error sending email: ' . $e->getMessage(), 'error');
}

// Redirect back to purchase order view
redirect('view_purchase_order.php?id=' . $po_id);

