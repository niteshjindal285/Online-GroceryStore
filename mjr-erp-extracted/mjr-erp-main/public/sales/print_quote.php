<?php
/**
 * Print Quotation
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$quote_id = get('id');
if (!$quote_id) {
    die("Quote ID not provided.");
}

$quote = db_fetch("
    SELECT q.*, c.customer_code, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           u.username as created_by_name
    FROM quotes q
    JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
", [$quote_id]);

if (!$quote) {
    die("Quote not found.");
}

$quote_items = db_fetch_all("
    SELECT qi.*, i.code, i.name as item_name, qi.description as line_description, i.description as item_desc
    FROM quote_lines qi
    JOIN inventory_items i ON qi.item_id = i.id
    WHERE qi.quote_id = ?
", [$quote_id]);

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
    
    return trim($words) . " Only";
}

function convert_number_to_words_native($number) {
    if ($number < 0) return "Negative " . convert_number_to_words_native(abs($number));
    $words = [];
    $dictionary = array(
        0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three',
        4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven',
        8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen',
        14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen',
        17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
        20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty',
        50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy',
        80 => 'Eighty', 90 => 'Ninety', 100 => 'Hundred',
        1000 => 'Thousand', 1000000 => 'Million',
        1000000000 => 'Billion'
    );
    
    if ($number < 21) {
        return $dictionary[$number];
    }
    if ($number < 100) {
        $tens = ((int) ($number / 10)) * 10;
        $units = $number % 10;
        $string = $dictionary[$tens];
        if ($units) $string .= "-" . $dictionary[$units];
        return $string;
    }
    if ($number < 1000) {
        $hundreds  = (int)($number / 100);
        $remainder = $number % 100;
        $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
        if ($remainder) $string .= ' ' . convert_number_to_words_native($remainder);
        return $string;
    }
    $baseUnit = (int) pow(1000, floor(log($number, 1000)));
    $numBaseUnits = (int) ($number / $baseUnit);
    $remainder = $number % $baseUnit;
    $string = convert_number_to_words_native($numBaseUnits) . ' ' . $dictionary[$baseUnit];
    if ($remainder) {
        $string .= ($remainder < 100 ? ' ' : ', ') . convert_number_to_words_native($remainder);
    }
    return $string;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Quote - <?= escape_html($quote['quote_number']) ?></title>
    <style>
        :root {
            --primary-dark: #1a1a1a;
            --accent-gold: #FFC107;
            --text-main: #333333;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --bg-light: #f8f9fa;
        }

        * { box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            margin: 0; padding: 0; 
            color: var(--text-main); 
            background: #eee;
            line-height: 1.5;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 12mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            position: relative;
        }

        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; height: 100%; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Header Styles */
        .header {
            background-color: var(--primary-dark);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 5px solid var(--accent-gold);
        }

        .brand { display: flex; align-items: center; }
        .logo-box {
            background: var(--accent-gold);
            color: var(--primary-dark);
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 800;
            margin-right: 20px;
        }

        .company-details h1 { margin: 0; font-size: 28px; font-weight: 800; letter-spacing: -0.5px; }
        .company-details p { margin: 2px 0 0; font-size: 11px; opacity: 0.8; }

        .doc-title-box {
            background: var(--accent-gold);
            color: var(--primary-dark);
            padding: 15px 25px;
            font-weight: 800;
            font-size: 20px;
            text-transform: uppercase;
        }

        /* Meta Bar */
        .meta-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-light);
        }

        .meta-item { padding: 15px; border-right: 1px solid var(--border-color); }
        .meta-item:last-child { border-right: none; }
        .meta-label { font-size: 10px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 5px; }
        .meta-value { font-size: 13px; font-weight: 700; }

        /* Section Header */
        .section-header-bar {
            background: var(--primary-dark);
            color: var(--accent-gold);
            padding: 8px 15px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        /* Address Grid */
        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border-color);
        }
        .address-box { padding: 20px; border-right: 1px solid var(--border-color); }
        .address-box:last-child { border-right: none; }
        .address-box h3 { margin: 0 0 10px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .address-box p { margin: 0 0 5px; font-size: 12px; }

        /* Table Styles */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead { background: #f4f6f9; }
        .items-table th { padding: 12px 15px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid var(--primary-dark); }
        .items-table td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 12px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Summary Section */
        .summary-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
        }
        .summary-left { padding: 20px; background: #fffef0; border-right: 1px solid var(--border-color); }
        .summary-right { padding: 0; }

        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 10px 15px; font-size: 13px; }
        .grand-total-row {
            background: var(--primary-dark);
            color: white;
            font-weight: 800;
            font-size: 18px;
        }

        /* Signature Section */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 50px;
            gap: 100px;
            padding: 0 50px;
        }
        .signature-line {
            border-top: 2px solid var(--primary-dark);
            text-align: center;
            padding-top: 10px;
        }

        /* Toolbar */
        .toolbar {
            position: fixed; top: 20px; right: 20px;
            display: flex; gap: 10px; z-index: 1000;
        }
        .btn {
            padding: 10px 20px; background: var(--primary-dark); color: white;
            border: none; border-radius: 4px; font-weight: 700; cursor: pointer;
            text-decoration: none; display: flex; align-items: center; gap: 8px;
        }
        .btn-secondary { background: #6c757d; }
    </style>
</head>
<body onload="window.print()">

    <div class="toolbar no-print">
        <button onclick="window.print()" class="btn">
            <i class="fas fa-print"></i> Print Document
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="page">
        <!-- Header -->
        <header class="header">
            <div class="brand">
                <div class="logo-box">MJR</div>
                <div class="company-details">
                    <h1>MJR COMPANY</h1>
                    <p>Steel & Metal Fabrication Division</p>
                    <p>Quality • Precision • Reliability</p>
                    <p>123, Industrial Area Phase-2, Jaipur | +91-98765-43210</p>
                </div>
            </div>
            <div class="doc-title-box">Quotation</div>
        </header>

        <!-- Meta Bar -->
        <div class="meta-bar">
            <div class="meta-item">
                <div class="meta-label">Quote Number</div>
                <div class="meta-value"><?= escape_html($quote['quote_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Date</div>
                <div class="meta-value"><?= format_date($quote['quote_date']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label" style="color:#d35400">Valid Till</div>
                <div class="meta-value"><?= $quote['valid_until'] ? format_date($quote['valid_until']) : '30 Days' ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="status-badge"><?= strtoupper(str_replace('_', ' ', $quote['status'] ?? 'Active')) ?></span>
                </div>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Quotation From</span>
            <span>Prepared For (Customer)</span>
        </div>

        <div class="address-grid">
            <div class="address-box">
                <p><strong>MJR Company</strong></p>
                <p>Steel & Metal Fabrication Division<br>Industrial Area, Jaipur, Rajasthan</p>
                <p><span class="text-muted">Contact:</span> sales@mjrcompany.com</p>
                <p><span class="text-muted">Prepared By:</span> <?= escape_html($quote['created_by_name'] ?: 'Sales Team') ?></p>
            </div>
            <div class="address-box">
                <p><strong><?= escape_html($quote['customer_name']) ?></strong></p>
                <p><?= nl2br(escape_html($quote['customer_address'])) ?></p>
                <p><span class="text-muted">Phone:</span> <?= escape_html($quote['customer_phone']) ?></p>
                <p><span class="text-muted">Email:</span> <?= escape_html($quote['customer_email']) ?></p>
                <p><span class="text-muted">Cust. Code:</span> <?= escape_html($quote['customer_code']) ?></p>
            </div>
        </div>

        <?php if (!empty($quote['custom_fields'])): ?>
        <div style="margin-top: 20px; padding: 15px; border: 1px dashed var(--accent-gold); background: #fffdf5;">
            <strong>Specific Instructions / Notes:</strong><br>
            <?= nl2br(escape_html($quote['custom_fields'])) ?>
        </div>
        <?php endif; ?>

        <div class="section-header-bar">
            <span>Items Specification & Pricing</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" width="50">#</th>
                    <th>Product / Material Description</th>
                    <th class="text-center" width="80">Qty</th>
                    <th class="text-right" width="120">Unit Rate</th>
                    <th class="text-right" width="150">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 1; foreach ($quote_items as $item): ?>
                <tr>
                    <td class="text-center text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= escape_html($item['item_name']) ?></strong><br>
                        <span style="font-size:10px; color:var(--text-muted)">CODE: <?= escape_html($item['code']) ?></span>
                        <?php if (!empty($item['line_description'])): ?>
                            <div style="font-size:9px; color:#888; margin-top:3px;"><?= nl2br(escape_html($item['line_description'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><strong><?= number_format($item['quantity'], 0) ?></strong></td>
                    <td class="text-right">$<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                    <td class="text-right"><strong>$<?= number_format((float)($item['line_total'] ?? ($item['quantity'] * $item['unit_price'])), 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-left">
                <div class="meta-label" style="color:#d35400">Amount in Words:</div>
                <div class="meta-value" style="color:darkorange"><?= amount_in_words((float)$quote['total_amount']) ?></div>
                
                <div class="meta-label" style="margin-top:20px;">Terms & Conditions:</div>
                <p style="font-size: 10px; color: var(--text-muted);">
                    1. Quotation valid for 30 days. 2. Advance payment required. 3. Prices are inclusive of tax.<br>
                    <?= escape_html($quote['notes'] ?: '') ?>
                </p>
            </div>
            <div class="summary-right">
                <table class="summary-table">
                    <tr>
                        <td class="text-muted">Net Subtotal</td>
                        <td class="text-right fw-bold">$<?= number_format((float)$quote['subtotal'], 2) ?></td>
                    </tr>
                    <?php if ($quote['discount_amount'] > 0): ?>
                    <tr>
                        <td class="text-muted text-danger">Discount</td>
                        <td class="text-right fw-bold text-danger">-$<?= number_format((float)$quote['discount_amount'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted">Tax Charges</td>
                        <td class="text-right fw-bold">$<?= number_format((float)$quote['tax_amount'], 2) ?></td>
                    </tr>
                    <tr class="grand-total-row">
                        <td style="padding: 15px;">TOTAL QUOTED</td>
                        <td class="text-right" style="color:var(--accent-gold); padding: 15px;">$<?= number_format((float)$quote['total_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer Signatures -->
        <div class="signature-grid">
            <div class="signature-line">
                <span style="font-weight: 800;">Prepared By</span><br>
                <span style="font-size: 11px; color: var(--text-muted);"><?= escape_html($quote['created_by_name'] ?: 'Manager') ?></span>
            </div>
            <div class="signature-line">
                <span style="font-weight: 800;">Authorized Signatory</span><br>
                <span style="font-size: 11px; color: var(--text-muted);">Customer / Authorized Manager</span>
            </div>
        </div>

        <div style="margin-top: 50px; text-align: center; font-size: 9px; color: #aaa;">
            MJR COMPANY | Quotation — Valid for 30 days from date of issue | Page 1 of 1
        </div>
    </div>
</body>
</html>
