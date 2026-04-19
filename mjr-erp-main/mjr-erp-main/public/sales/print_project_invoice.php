<?php
/**
 * Print Project Milestone Invoice
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$order_id = get('id');
if (!$order_id) {
    die("Order ID not provided.");
}

$order = db_fetch("
    SELECT so.*, c.customer_code, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           u.username as created_by_name, l.name as location_name,
           p.name as project_name, p.description as project_details,
           ps.stage_name, ps.amount as stage_amount
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    LEFT JOIN users u ON so.created_by = u.id
    LEFT JOIN locations l ON so.location_id = l.id
    LEFT JOIN projects p ON so.project_id = p.id
    LEFT JOIN project_stages ps ON so.project_stage_id = ps.id
    WHERE so.id = ?
", [$order_id]);

if (!$order) {
    die("Order not found.");
}

$order_items = db_fetch_all("
    SELECT sol.*, i.code, i.name as item_name
    FROM sales_order_lines sol
    JOIN inventory_items i ON sol.item_id = i.id
    WHERE sol.order_id = ?
", [$order_id]);

function amount_in_words(float $amount) {
    $amount = number_format($amount, 2, '.', '');
    $parts = explode('.', $amount);
    $num = (int)$parts[0];
    $decimal = (int)$parts[1];

    if ($num == 0) {
        $words = "Zero";
    } else {
        $words = convert_number_to_words_native($num);
    }
    
    if ($decimal > 0) {
        $words .= " and " . convert_number_to_words_native($decimal) . " Cents";
    }
    
    return $words . " Only";
}

function convert_number_to_words_native($number) {
    $dictionary = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety', 
        100 => 'Hundred', 1000 => 'Thousand', 1000000 => 'Million', 1000000000 => 'Billion'
    );
    if (!is_numeric($number)) return false;
    if ($number < 0) return 'Negative ' . convert_number_to_words_native(abs($number));
    $string = $fraction = null;
    if (strpos($number, '.') !== false) list($number, $fraction) = explode('.', $number);
    switch (true) {
        case $number < 21: $string = $dictionary[$number]; break;
        case $number < 100:
            $tens = ((int) ($number / 10)) * 10;
            $units = $number % 10;
            $string = $dictionary[$tens];
            if ($units) $string .= '-' . $dictionary[$units];
            break;
        case $number < 1000:
            $hundreds = (int)($number / 100);
            $remainder = $number % 100;
            $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
            if ($remainder) $string .= ' ' . convert_number_to_words_native($remainder);
            break;
        default:
            $baseUnit = (int) pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = convert_number_to_words_native($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? ' ' : ', ';
                $string .= convert_number_to_words_native($remainder);
            }
            break;
    }
    return $string;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Invoice - <?= escape_html($order['order_number']) ?></title>
    <style>
        :root {
            --primary: #004a99;
            --secondary: #6c757d;
            --accent: #f39c12;
            --border: #dee2e6;
        }
        body { font-family: 'Segoe UI', serif; margin: 0; padding: 20px; color: #333; background: #f0f0f0; }
        .invoice-box {
            max-width: 800px; padding: 40px; margin: auto;
            border: 1px solid #eee; background: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        }
        @media print {
            body { background: none; padding: 0; }
            .invoice-box { box-shadow: none; border: none; max-width: 100%; }
            .no-print { display: none; }
        }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 20px; }
        .logo { font-size: 32px; font-weight: bold; color: var(--primary); }
        .company-info { text-align: right; font-size: 12px; }
        .title { text-align: center; text-transform: uppercase; letter-spacing: 2px; color: var(--primary); margin: 20px 0; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .info-box h3 { font-size: 14px; text-transform: uppercase; color: var(--secondary); margin-bottom: 10px; border-bottom: 1px solid var(--border); }
        .info-box p { margin: 2px 0; font-size: 13px; }
        .project-banner { background: #f8f9fa; border: 1px solid var(--border); padding: 15px; margin-bottom: 20px; border-left: 5px solid var(--primary); }
        .project-banner h4 { margin: 0 0 5px; color: var(--primary); }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th { background: var(--primary); color: #fff; padding: 10px; text-align: left; font-size: 12px; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .summary-box { float: right; width: 300px; }
        .summary-box table { width: 100%; }
        .summary-box td { padding: 8px 5px; }
        .total-row { font-weight: bold; font-size: 18px; color: var(--primary); border-top: 2px solid var(--primary); }
        .words { font-style: italic; font-size: 12px; margin-top: 20px; }
        .footer { margin-top: 100px; display: flex; justify-content: space-between; }
        .sig { border-top: 1px solid #333; width: 200px; text-align: center; padding-top: 5px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: var(--primary); color: white; border: none; border-radius: 4px;">Print Invoice</button>
        <button onclick="window.close()" style="padding: 10px 20px; cursor: pointer; background: var(--secondary); color: white; border: none; border-radius: 4px;">Close</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <div class="logo">MJR GROUP</div>
            <div class="company-info">
                <strong>MJR GROUP ERP</strong><br>
                Industrial Fabrication Division<br>
                Phone: +1 234 567 890<br>
                Email: accounts@mjrgroup.com
            </div>
        </div>

        <h2 class="title">Project Milestone Invoice</h2>

        <div class="info-grid">
            <div class="info-box">
                <h3>Bill To:</h3>
                <p><strong><?= escape_html($order['customer_name']) ?></strong></p>
                <p><?= nl2br(escape_html($order['customer_address'])) ?></p>
                <p>Phone: <?= escape_html($order['customer_phone']) ?></p>
            </div>
            <div class="info-box" style="text-align: right;">
                <h3>Invoice Details:</h3>
                <p>Invoice #: <strong><?= escape_html($order['order_number']) ?></strong></p>
                <p>Date: <?= format_date($order['order_date']) ?></p>
                <p>Reference: <?= escape_html($order['project_name']) ?></p>
            </div>
        </div>

        <div class="project-banner">
            <h4>Project: <?= escape_html($order['project_name']) ?></h4>
            <p><strong>Billing Stage:</strong> <?= escape_html($order['stage_name'] ?: 'Full Settlement') ?></p>
            <?php if (!empty($order['project_details'])): ?>
                <p style="font-size: 11px; color: #666;"><?= escape_html($order['project_details']) ?></p>
            <?php endif; ?>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="50">#</th>
                    <th>Material / Service Description</th>
                    <th width="100" style="text-align: center;">Quantity</th>
                    <th width="100" style="text-align: center;">Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($order_items as $item): ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td>
                        <strong><?= escape_html($item['item_name']) ?></strong>
                        <?php if (!empty($item['description'])): ?>
                            <div style="font-size: 11px; color: #777;"><?= nl2br(escape_html($item['description'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?= number_format($item['quantity'], 2) ?></td>
                    <td style="text-align: center;">PCS</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="overflow: hidden;">
            <div class="summary-box">
                <table>
                    <tr>
                        <td><strong>Milestone Amount</strong></td>
                        <td style="text-align: right;">$<?= number_format($order['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($order['tax_amount'] > 0): ?>
                    <tr>
                        <td>Tax</td>
                        <td style="text-align: right;">$<?= number_format($order['tax_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <tr>
                        <td>Discount</td>
                        <td style="text-align: right;">-$<?= number_format($order['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>TOTAL CLAIM</td>
                        <td style="text-align: right;">$<?= number_format($order['total_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="words">
            Amount in words: <strong><?= amount_in_words($order['total_amount']) ?></strong>
        </div>

        <div class="footer">
            <div class="sig">
                Authorized Signatory<br>
                <strong>MJR GROUP</strong>
            </div>
            <div class="sig">
                Customer Acceptance<br>
                (Signature & Seal)
            </div>
        </div>
    </div>
</body>
</html>
