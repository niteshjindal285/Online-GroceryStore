<?php
/**
 * MJR Group ERP - Automated Reorder Email Script (Cron Job)
 * 
 * This script is designed to be executed via a scheduled task (Cron Job in Linux, 
 * or Task Scheduler in Windows) without requiring a user to be logged in.
 * 
 * Usage from Command Line: 
 * php C:\xampp\htdocs\MJR\cron_auto_reorder.php
 */

// Since this is a CLI script, we don't start a session or require login.
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

// PHPMailer setup
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once __DIR__ . '/includes/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/includes/PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/includes/PHPMailer-master/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "[CRON] Starting Auto-Reorder Check...\n";

// 1. Get Managers & Admins emails
function get_recipients(): array {
    $rows = db_fetch_all("
        SELECT email
        FROM users
        WHERE is_active = 1
          AND email IS NOT NULL
          AND email <> ''
          AND role IN ('manager', 'admin')
        ORDER BY email
    ");

    $emails = array_values(array_unique(array_map(static fn($r) => $r['email'], $rows)));
    return array_values(array_filter($emails, static fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
}

// 2. Query all items where available quantity is below reorder_level
function get_critical_items(): array {
    return db_fetch_all("
        SELECT src.item_code AS code,
               src.item_name AS name,
               src.category_name,
               src.location_label AS stock_by_location,
               src.qty_available AS total_available,
               src.reorder_level,
               src.reorder_quantity,
               src.unit_code
        FROM (
            -- Source 1: stock tracked in inventory_stock_levels (per location)
            SELECT i.id AS item_id,
                   i.code AS item_code,
                   i.name AS item_name,
                   c.name AS category_name,
                   l.name AS location_label,
                   COALESCE(s.quantity_available, 0) AS qty_available,
                   i.reorder_level,
                   i.reorder_quantity,
                   COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
            FROM inventory_stock_levels s
            JOIN inventory_items i ON i.id = s.item_id
            LEFT JOIN categories c ON c.id = i.category_id
            JOIN locations l ON l.id = s.location_id
            LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
            WHERE i.is_active = 1

            UNION ALL

            -- Source 2: stock only in warehouse_inventory (no linked stock_levels record)
            SELECT i.id AS item_id,
                   i.code AS item_code,
                   i.name AS item_name,
                   c.name AS category_name,
                   CONCAT(w.name, ' (Warehouse)') AS location_label,
                   SUM(wi.quantity) AS qty_available,
                   i.reorder_level,
                   i.reorder_quantity,
                   COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
            FROM warehouse_inventory wi
            JOIN inventory_items i ON i.id = wi.product_id
            LEFT JOIN categories c ON c.id = i.category_id
            JOIN warehouses w ON w.id = wi.warehouse_id
            LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
            WHERE i.is_active = 1
              AND (
                  w.location_id IS NULL
                  OR NOT EXISTS (
                      SELECT 1 FROM inventory_stock_levels s2
                      WHERE s2.item_id = wi.product_id AND s2.location_id = w.location_id
                  )
              )
            GROUP BY i.id, i.code, i.name, c.name, w.id, w.name, i.reorder_level, i.reorder_quantity, u.code, i.unit_of_measure
        ) src
        WHERE src.qty_available <= src.reorder_level  -- Core Logic: Find strict shortages
          AND src.reorder_level > 0                   -- Ignore items without reorder tracking
        ORDER BY src.qty_available ASC
    ");
}

function build_html_report(array $items): string {
    $today = date('Y-m-d H:i');

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td style="padding: 8px; border: 1px solid #ddd;"><strong>' . htmlspecialchars($item['code']) . '</strong></td>'
            . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['name']) . '</td>'
            . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars($item['category_name'] ?: 'Uncategorized') . '</td>'
            . '<td style="padding: 8px; border: 1px solid #ddd; text-align:right; color: red;"><strong>' . number_format($item['total_available'], 0) . ' ' . htmlspecialchars((string)$item['unit_code']) . '</strong></td>'
            . '<td style="padding: 8px; border: 1px solid #ddd; text-align:right;">' . number_format($item['reorder_level'], 0) . '</td>'
            . '<td style="padding: 8px; border: 1px solid #ddd; text-align:right; color: green;"><strong>' . number_format($item['reorder_quantity'], 0) . '</strong></td>'
            . '<td style="padding: 8px; border: 1px solid #ddd;">' . htmlspecialchars((string)$item['stock_by_location']) . '</td>'
            . '</tr>';
    }

    return '
        <div style="font-family: Arial, sans-serif; color: #333;">
            <h2 style="color: #dc3545;">🚨 Automated Inventory Reorder Alert</h2>
            <p><strong>' . count($items) . '</strong> item(s) have dropped below their critical reorder thresholds as of ' . htmlspecialchars($today) . '.</p>
            <p>Please review the automated purchasing suggestions below:</p>

            <table style="width: 100%; border-collapse:collapse; text-align: left; margin-top: 20px;">
                <thead style="background-color: #f8f9fa;">
                    <tr>
                        <th style="padding: 10px; border: 1px solid #ddd;">Item Code</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Item Name</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Category</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Available Stock</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Reorder Level (Min)</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Suggested PO Qty</th>
                        <th style="padding: 10px; border: 1px solid #ddd;">Current Location</th>
                    </tr>
                </thead>
                <tbody>' . $rows . '</tbody>
            </table>
            
            <br>
            <p style="font-size: 12px; color: #777;">This is an automated system message generated by the MJR Group ERP Engine. Do not reply directly to this email.</p>
        </div>
    ';
}

function send_automated_email(array $recipients, string $subject, string $htmlBody): bool {
    require_once __DIR__ . '/includes/email_config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "[ERROR] Email could not be sent. Mailer Error: {$mail->ErrorInfo}\n";
        return false;
    }
}

// ---- EXECUTION FLOW ----

$recipients = get_recipients();
if (empty($recipients)) {
    echo "[CRON] No valid manager or admin emails found. Aborting.\n";
    exit(1);
}

$shortages = get_critical_items();
if (empty($shortages)) {
    echo "[CRON] Inventory is healthy! No items below reorder thresholds today.\n";
    exit(0);
}

$subject = "🚨 [MJR Auto-Reorder] " . count($shortages) . " Items Require Purchasing";
$htmlBody = build_html_report($shortages);

echo "[CRON] Found " . count($shortages) . " items under threshold. Sending email to " . count($recipients) . " users...\n";

if (send_automated_email($recipients, $subject, $htmlBody)) {
    echo "[CRON] ✅ Success! Reorder report generated and emailed successfully.\n";
} else {
    echo "[CRON] ❌ Failed to send emails.\n";
}
