<?php
/**
 * Send Sales Order Invoice Email
 * Sends order invoice via email
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if (!is_post()) {
    set_flash('Invalid request method.', 'error');
    redirect('orders.php');
}

$csrf_token = post('csrf_token');

if (!verify_csrf_token($csrf_token)) {
    set_flash('Invalid security token.', 'error');
    redirect('orders.php');
}

try {
    $order_id = post('order_id');
    $recipient_email = post('recipient_email');
    $cc_email = post('cc_email');
    $email_subject = post('email_subject');
    $email_message = post('email_message');
    
    // Validate inputs
    if (empty($order_id) || empty($recipient_email)) {
        throw new Exception('Order ID and recipient email are required');
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Get order data and verify ownership
    $order = db_fetch("
        SELECT so.*, c.customer_code, c.name as customer_name, c.email as customer_email, 
               c.phone as customer_phone, c.address as customer_address,
               u.username as created_by_name
        FROM sales_orders so
        JOIN customers c ON so.customer_id = c.id
        LEFT JOIN users u ON so.created_by = u.id
        WHERE so.id = ? AND so.company_id = ?
    ", [$order_id, $_SESSION['company_id']]);
    
    if (!$order) {
        throw new Exception('Order not found or access denied.');
    }
    
    // Get order items
    $order_items = db_fetch_all("
        SELECT sol.*, i.code, i.name as item_name
        FROM sales_order_lines sol
        JOIN inventory_items i ON sol.item_id = i.id
        WHERE sol.order_id = ?
    ", [$order_id]);
    
    // Prepare email
    $to = $recipient_email;
    $subject = !empty($email_subject) ? $email_subject : "Sales Order Invoice - " . $order['order_number'];
    $message = !empty($email_message) ? $email_message : "Please find the sales order invoice details below.";
    
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
            <h2>MJR Group ERP - Sales Order Invoice</h2>
        </div>
        <div class='content'>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            
            <div class='invoice-details'>
                <h3>Order Information</h3>
                <table>
                    <tr>
                        <th>Order Number</th>
                        <td>" . htmlspecialchars($order['order_number']) . "</td>
                    </tr>
                    <tr>
                        <th>Customer</th>
                        <td>" . htmlspecialchars($order['customer_name']) . "</td>
                    </tr>
                    <tr>
                        <th>Order Date</th>
                        <td>" . date('Y-m-d', strtotime($order['order_date'])) . "</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>" . ucfirst($order['status']) . "</td>
                    </tr>
                </table>
                
                <h3>Order Items</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Description</th>
                            <th class='text-end'>Quantity</th>
                            <th class='text-end'>Unit Price</th>
                            <th class='text-end'>Total</th>
                        </tr>
                    </thead>
                    <tbody>";
    
    foreach ($order_items as $item) {
        $html_message .= "
                        <tr>
                            <td><strong>" . htmlspecialchars($item['item_name']) . "</strong></td>
                            <td>" . htmlspecialchars($item['description'] ?? '-') . "</td>
                            <td class='text-end'>" . $item['quantity'] . "</td>
                            <td class='text-end'>$" . number_format((float)($item['unit_price'] ?? 0), 2) . "</td>
                            <td class='text-end'>$" . number_format((float)($item['line_total'] ?? 0), 2) . "</td>
                        </tr>";
    }
    
    $html_message .= "
                    </tbody>
                    <tfoot>
                        <tr style='background-color: #fff3cd;'>
                            <th colspan='4' class='text-end'><strong>Total Amount</strong></th>
                            <th class='text-end'><strong>$" . number_format($order['total_amount'], 2) . "</strong></th>
                        </tr>
                    </tfoot>
                </table>
            </div>";
    
    if (!empty($order['notes'])) {
        $html_message .= "
            <div style='margin-top: 20px;'>
                <h4>Notes</h4>
                <p>" . nl2br(htmlspecialchars($order['notes'])) . "</p>
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
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/SMTP.php';
    require_once __DIR__ . '/../../includes/email_config.php';

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
        set_flash('Order invoice sent successfully to ' . htmlspecialchars($recipient_email), 'success');
    } catch (PHPMailer\PHPMailer\Exception $e) {
        throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
    
} catch (Exception $e) {
    log_error("Error sending order email: " . $e->getMessage());
    set_flash('Error sending email: ' . $e->getMessage(), 'error');
}

// Redirect back to order view
redirect('view_order.php?id=' . $order_id);
