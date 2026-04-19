<?php
/**
 * Print GSRN / Stock Entry Receipt
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    die("GSRN not found.");
}

// Get GSRN Header
$gsrn = db_fetch("
    SELECT h.*, u.username as creator_name, u.full_name as creator_full_name,
           m.username as manager_name, m.full_name as manager_full_name,
           w.name as warehouse_name, w.location as warehouse_address,
           c.name as company_name, c.address as company_address, c.phone as company_phone, c.email as company_email,
           cat.name as category_name,
           s.name as supplier_name, s.supplier_code, s.contact_person as supplier_contact, s.address as supplier_address, 
           s.phone as supplier_phone, s.email as supplier_email
    FROM gsrn_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN warehouses w ON h.warehouse_id = w.id
    LEFT JOIN companies c ON h.company_id = c.id
    LEFT JOIN categories cat ON h.category_id = cat.id
    LEFT JOIN suppliers s ON h.supplier_id = s.id
    WHERE h.id = ?
", [$id]);

if (!$gsrn) {
    die("GSRN not found.");
}

// Get Items
$items = db_fetch_all("
    SELECT gi.*, i.name as item_name, i.code as item_code, cat.name as category_name
    FROM gsrn_items gi
    JOIN inventory_items i ON gi.item_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    WHERE gi.gsrn_id = ?
", [$id]);

function amount_in_words(float $amount, $currency = 'INR') {
    $amount = number_format($amount, 2, '.', '');
    $parts = explode('.', $amount);
    $num = (int)$parts[0];
    $decimal = (int)$parts[1];

    if ($num == 0) {
        $words = "Zero";
    } else {
        $words = convert_number_to_words_native($num);
    }
    
    $currency_name = ($currency === 'INR') ? 'Rupees' : 'Dollars';
    $decimal_name = ($currency === 'INR') ? 'Paise' : 'Cents';

    $result = $words . " " . $currency_name;
    
    if ($decimal > 0) {
        $result .= " and " . convert_number_to_words_native($decimal) . " " . $decimal_name;
    }
    
    return trim($result) . " Only";
}

function convert_number_to_words_native($number) {
    if ($number < 0) return "Negative " . convert_number_to_words_native(abs($number));
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
        1000 => 'Thousand', 1000000 => 'Million'
    );
    
    if ($number < 21) return $dictionary[$number];
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
    <title>GSRN <?= escape_html($gsrn['gsrn_number']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        .status-badge {
            display: inline-block;
            background: var(--primary-dark);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            vertical-align: middle;
        }

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

        /* Info Grid */
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
        .summary-table td { padding: 8px 15px; font-size: 12px; }
        .grand-total-row {
            background: var(--primary-dark);
            color: white;
            font-weight: 800;
            font-size: 18px;
        }

        /* Signature Section */
        .signature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            margin-top: 50px;
            gap: 40px;
            padding: 0 20px;
        }
        .signature-line {
            border-top: 2px solid var(--primary-dark);
            text-align: center;
            padding-top: 10px;
            font-size: 11px;
            font-weight: 700;
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
            <i class="fas fa-print"></i> Print Receipt
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
                    <h1><?= escape_html($gsrn['company_name']) ?></h1>
                    <p>Advanced Inventory & Logistics Division</p>
                    <p><?= escape_html($gsrn['company_address']) ?></p>
                    <p>Phone: <?= escape_html($gsrn['company_phone']) ?> | Email: <?= escape_html($gsrn['company_email']) ?></p>
                </div>
            </div>
            <div class="doc-title-box">GSRN / STOCK RECEIPT</div>
        </header>

        <!-- Meta Bar -->
        <div class="meta-bar">
            <div class="meta-item">
                <div class="meta-label">GSRN Number</div>
                <div class="meta-value"><?= escape_html($gsrn['gsrn_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Date</div>
                <div class="meta-value"><?= format_date($gsrn['gsrn_date']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Transaction</div>
                <div class="meta-value"><?= strtoupper($gsrn['transaction_type']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="status-badge" style="background: <?= $gsrn['status'] === 'approved' ? '#28a745' : '#1a1a1a' ?>"><?= strtoupper($gsrn['status']) ?></span>
                </div>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Source / Supplier Details</span>
            <span>Target Storage / Location</span>
        </div>

        <div class="address-grid">
            <div class="address-box">
                <p><strong><?= escape_html($gsrn['supplier_name'] ?: 'Internal Production') ?></strong></p>
                <p><?= nl2br(escape_html($gsrn['supplier_address'] ?: 'N/A')) ?></p>
                <p><span class="text-muted">Contact:</span> <?= escape_html($gsrn['supplier_contact'] ?: '--') ?></p>
                <p><span class="text-muted">ID:</span> <?= escape_html($gsrn['supplier_code'] ?: '0000') ?></p>
            </div>
            <div class="address-box">
                <p><strong>Warehouse: <?= escape_html($gsrn['warehouse_name']) ?></strong></p>
                <p><?= nl2br(escape_html($gsrn['warehouse_address'] ?: 'Factory Main Store')) ?></p>
                <p><span class="text-muted">Category:</span> <?= escape_html($gsrn['category_name'] ?: 'General Stock') ?></p>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Itemized Stock Entry List</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" width="50">#</th>
                    <th>Item Specification</th>
                    <th class="text-center" width="70">Qty</th>
                    <th class="text-center" width="60">UOM</th>
                    <th class="text-right" width="100">Rate</th>
                    <th class="text-right" width="120">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 1; foreach ($items as $item): ?>
                <tr>
                    <td class="text-center text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= escape_html($item['item_name']) ?></strong><br>
                        <span style="font-size:10px; color:var(--text-muted)">CODE: <?= escape_html($item['item_code']) ?> | BATCH: <?= escape_html($item['batch_serial'] ?: 'N/A') ?></span>
                    </td>
                    <td class="text-center"><strong><?= number_format($item['quantity'], 2) ?></strong></td>
                    <td class="text-center"><?= escape_html($item['uom']) ?></td>
                    <td class="text-right"><?= number_format($item['unit_cost'], 2) ?></td>
                    <td class="text-right"><strong><?= number_format($item['total_value'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-left">
                <div class="meta-label" style="color:#d35400">Total in Words:</div>
                <div class="meta-value" style="font-size: 11px; color:darkorange"><?= amount_in_words($gsrn['final_landed_cost'], $gsrn['currency']) ?></div>
                
                <div class="meta-label" style="margin-top:20px;">Storage Remarks:</div>
                <p style="font-size: 11px; color: var(--text-muted);">
                    <?= nl2br(escape_html($gsrn['warehouse_remarks'] ?: 'Stock verified and accepted in good condition.')) ?>
                </p>
            </div>
            <div class="summary-right">
                <table class="summary-table">
                    <tr><td class="text-muted">Subtotal Val</td><td class="text-right fw-bold"><?= number_format($gsrn['invoice_value'], 2) ?></td></tr>
                    <tr><td class="text-muted">Freight/Duties</td><td class="text-right fw-bold"><?= number_format($gsrn['freight_cost'] + $gsrn['import_duty'], 2) ?></td></tr>
                    <tr><td class="text-muted">Misc Landing</td><td class="text-right fw-bold"><?= number_format($gsrn['insurance'] + $gsrn['handling_charges'], 2) ?></td></tr>
                    <tr class="grand-total-row">
                        <td style="padding: 12px;">LANDED COST</td>
                        <td class="text-right" style="color:var(--accent-gold); padding: 12px;"><?= number_format($gsrn['final_landed_cost'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-grid">
            <div class="signature-line">Store Keeper / Prepared By<br><span style="font-weight:400; font-size:10px; color:#888"><?= escape_html($gsrn['creator_full_name'] ?: $gsrn['creator_name']) ?></span></div>
            <div class="signature-line">Quality Assurance (QA)</div>
            <div class="signature-line">Authorized By<br><span style="font-weight:400; font-size:10px; color:#888"><?= escape_html($gsrn['manager_full_name'] ?: $gsrn['manager_name']) ?></span></div>
        </div>

        <div style="margin-top: 50px; text-align: center; font-size: 9px; color: #aaa;">
            MJR COMPANY | Computer Generated Receipt | Page 1 of 1
        </div>
    </div>
</body>
</html>
