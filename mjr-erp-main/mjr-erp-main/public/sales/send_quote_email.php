<?php
/**
 * Send Quote Invoice Email
 * Sends quote invoice via email
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

if (!is_post()) {
    set_flash('Invalid request method.', 'error');
    redirect('quotes.php');
}

$csrf_token = post('csrf_token');

if (!verify_csrf_token($csrf_token)) {
    set_flash('Invalid security token.', 'error');
    redirect('quotes.php');
}

try {
    $quote_id = post('quote_id');
    $recipient_email = post('recipient_email');
    $cc_email = post('cc_email');
    $email_subject = post('email_subject');
    $email_message = post('email_message');
    
    // Validate inputs
    if (empty($quote_id) || empty($recipient_email)) {
        throw new Exception('Quote ID and recipient email are required');
    }
    
    if (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Get quote data and verify ownership
    $quote = db_fetch("
        SELECT q.*, c.customer_code, c.name as customer_name, c.email as customer_email, 
               c.phone as customer_phone, c.address as customer_address,
               u.username as created_by_name
        FROM quotes q
        JOIN customers c ON q.customer_id = c.id
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.id = ? AND q.company_id = ?
    ", [$quote_id, $_SESSION['company_id']]);
    
    if (!$quote) {
        throw new Exception('Quote not found or access denied.');
    }
    
    // Get quote items
    $quote_items = db_fetch_all("
        SELECT qi.*, i.code, i.name as item_name, i.description
        FROM quote_lines qi
        JOIN inventory_items i ON qi.item_id = i.id
        WHERE qi.quote_id = ?
    ", [$quote_id]);
    
    // Prepare email
    $to = $recipient_email;
    $subject = !empty($email_subject) ? $email_subject : "Quote Invoice - " . $quote['quote_number'];
    $message = !empty($email_message) ? $email_message : "Please find the quote invoice details below.";
    
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
            <h2>MJR Group ERP - Quote Invoice</h2>
        </div>
        <div class='content'>
            <p>" . nl2br(htmlspecialchars($message)) . "</p>
            
            <div class='invoice-details'>
                <h3>Quote Information</h3>
                <table>
                    <tr>
                        <th>Quote Number</th>
                        <td>" . htmlspecialchars($quote['quote_number']) . "</td>
                    </tr>
                    <tr>
                        <th>Customer</th>
                        <td>" . htmlspecialchars($quote['customer_name']) . "</td>
                    </tr>
                    <tr>
                        <th>Quote Date</th>
                        <td>" . date('Y-m-d', strtotime($quote['quote_date'])) . "</td>
                    </tr>";
    
    if (!empty($quote['valid_until'])) {
        $html_message .= "
                    <tr>
                        <th>Valid Until</th>
                        <td>" . date('Y-m-d', strtotime($quote['valid_until'])) . "</td>
                    </tr>";
    }
    
    $html_message .= "
                    <tr>
                        <th>Status</th>
                        <td>" . ucfirst($quote['status']) . "</td>
                    </tr>
                </table>
                
                <h3>Quote Items</h3>
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
    
    foreach ($quote_items as $item) {
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
                            <th class='text-end'><strong>$" . number_format($quote['total_amount'], 2) . "</strong></th>
                        </tr>
                    </tfoot>
                </table>
            </div>";
    
    if ($quote['notes']) {
        $html_message .= "
            <div style='margin-top: 20px;'>
                <h4>Notes</h4>
                <p>" . nl2br(htmlspecialchars($quote['notes'])) . "</p>
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
        set_flash('Quote invoice sent successfully to ' . $recipient_email, 'success');
    } catch (PHPMailer\PHPMailer\Exception $e) {
        throw new Exception("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
    
} catch (Exception $e) {
    log_error("Error sending quote email: " . $e->getMessage());
    set_flash('Error sending email: ' . $e->getMessage(), 'error');
}

// Redirect back to quote view
redirect('view_quote.php?id=' . $quote_id);
