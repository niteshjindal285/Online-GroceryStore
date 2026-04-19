<?php
/**
 * Print Purchase Order - Premium Hybrid Layout
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$company_id = active_company_id(1);
$po_id = get_param('id');
if (!$po_id) {
    die("Purchase order not found.");
}

$po = db_fetch("
    SELECT po.*, s.name as supplier_name, s.supplier_code, s.contact_person as supplier_contact_person,
           s.email as supplier_email, s.phone as supplier_phone, s.address as supplier_address,
           s.payment_terms as supplier_payment_terms,
           comp.name as company_name, comp.address as company_address, comp.phone as company_phone, comp.email as company_email,
           wh.name as warehouse_name, wh.location as warehouse_address,
           u.username as created_by_name
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN companies comp ON po.company_id = comp.id
    LEFT JOIN warehouses wh ON po.warehouse_id = wh.id
    LEFT JOIN users u ON po.created_by = u.id
    WHERE po.id = ? AND po.company_id = ?
", [$po_id, $company_id]);

if (!$po) {
    die("Purchase order not found.");
}

$po_lines = db_fetch_all("
    SELECT pol.*, i.code, i.name as item_name, cat.name as category_name
    FROM purchase_order_lines pol
    JOIN inventory_items i ON pol.item_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    WHERE pol.po_id = ?
", [$po_id]);

$curr_symbol = '$';
$curr_code = 'USD';

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
    <title>PO <?= escape_html($po['po_number']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a1a1a;
            --accent-yellow: #FFC107;
            --text-main: #333333;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --bg-light: #f8f9fa;
        }

        * { box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif; 
            margin: 0; 
            padding: 0; 
            color: var(--text-main); 
            background: #fff;
            line-height: 1.5;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
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
            border-bottom: 5px solid var(--accent-yellow);
        }

        .brand { display: flex; align-items: center; }
        .logo-box {
            background: var(--accent-yellow);
            color: var(--primary-dark);
            width: 70px;
            height: 70px;
            border-radius: 50%;
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
            background: var(--accent-yellow);
            color: var(--primary-dark);
            padding: 15px 25px;
            font-weight: 800;
            font-size: 20px;
            text-transform: uppercase;
        }

        /* Meta Bar */
        .meta-bar {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-light);
        }

        .meta-item {
            padding: 15px;
            border-right: 1px solid var(--border-color);
        }
        .meta-item:last-child { border-right: none; }
        .meta-label { font-size: 10px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 5px; }
        .meta-value { font-size: 13px; font-weight: 700; }

        .status-badge {
            display: inline-block;
            background: var(--primary-dark);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            vertical-align: middle;
        }
        .status-dot {
            width: 6px;
            height: 6px;
            background: var(--accent-yellow);
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        /* Info Section Header */
        .section-header-bar {
            background: var(--primary-dark);
            color: var(--accent-yellow);
            padding: 8px 15px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            justify-content: space-between;
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
        .address-box strong { font-size: 14px; }

        /* Table Styles */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead { background: #2c3e50; color: white; }
        .items-table th { padding: 12px 15px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .items-table td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 12px; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }

        .sku-tag { font-family: monospace; font-size: 10px; color: var(--text-muted); }

        /* Cost Sections */
        .landed-cost-section {
            background: var(--bg-light);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        /* Summary Section */
        .summary-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
        }
        .summary-left { padding: 20px; border-right: 1px solid var(--border-color); }
        .summary-right { padding: 0; }

        .amount-words-box {
            background: #fffef0;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 8px 15px; font-size: 12px; }
        .summary-table .grand-total-row {
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

        /* Workflow Panel */
        .workflow-panel {
            margin-top: 40px;
            padding: 20px;
            background: var(--bg-light);
            border-radius: 8px;
        }
        .workflow-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 40px;
        }
        .workflow-timeline::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: #ddd;
            z-index: 1;
        }
        .workflow-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 60px;
        }
        .step-circle {
            width: 40px;
            height: 40px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-size: 14px;
            position: relative;
            z-index: 2;
        }
        .step-label { font-size: 9px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }

        .step-completed .step-circle { border-color: #28a745; color: #28a745; }
        .step-completed .step-label { color: #28a745; }
        
        .step-active .step-circle { border-color: var(--accent-yellow); background: var(--accent-yellow); color: var(--primary-dark); }
        .step-active .step-label { color: var(--primary-dark); }

        .step-rejected .step-circle { border-color: #dc3545; color: #dc3545; }
        .step-rejected .step-label { color: #dc3545; }

        .check-checkmark {
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: white;
            color: #28a745;
            font-size: 10px;
            border: 1px solid #28a745;
            border-radius: 50%;
            padding: 1px;
        }
        .step-active .step-label { color: var(--primary-dark); }
        
        .step-completed .step-circle {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }
        /* Buttons */
        .btn-toolbar {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        .btn-print { background: var(--primary-dark); color: white; }
        .btn-close { background: #6c757d; color: white; }
    </style>
</head>
<body>

    <div class="btn-toolbar no-print">
        <button onclick="window.print()" class="btn btn-print">
            <i class="fas fa-print"></i> Print Document
        </button>
        <button onclick="window.close()" class="btn btn-close">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="page">
        <!-- Premium Header -->
        <header class="header">
            <div class="brand">
                <div class="logo-box">MJR</div>
                <div class="company-details">
                    <h1><?= escape_html($po['company_name'] ?: 'MJR COMPANY') ?></h1>
                    <p>Steel & Metal Fabrication Division</p>
                    <p>Quality • Precision • Reliability</p>
                    <p><?= escape_html($po['company_address'] ?: '123, Industrial Area Phase-2, Jaipur') ?> | <?= escape_html($po['company_phone'] ?: '+91-98765-43210') ?></p>
                </div>
            </div>
            <div class="doc-title-box">
                Purchase Order
            </div>
        </header>

        <!-- Meta Bar -->
        <div class="meta-bar">
            <div class="meta-item">
                <div class="meta-label">PO Number</div>
                <div class="meta-value"><?= escape_html($po['po_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Date</div>
                <div class="meta-value"><?= format_date($po['order_date']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Expected Delivery</div>
                <div class="meta-value"><?= $po['expected_delivery_date'] ? format_date($po['expected_delivery_date']) : 'NA' ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Payment Terms</div>
                <div class="meta-value"><?= escape_html($po['supplier_payment_terms'] ?: 'Net 30') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="status-badge">
                        <span class="status-dot"></span>
                        <?= strtoupper(str_replace('_', ' ', $po['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Info Bar -->
        <div class="section-header-bar">
            <span>Supplier Info</span>
            <span>Delivery To (Order Info)</span>
        </div>

        <div class="address-grid">
            <div class="address-box">
                <p><strong><?= escape_html($po['supplier_name']) ?></strong></p>
                <p><?= nl2br(escape_html($po['supplier_address'])) ?></p>
                <?php if ($po['supplier_contact_person']): ?>
                    <p><span class="text-muted">Contact:</span> <?= escape_html($po['supplier_contact_person']) ?></p>
                <?php endif; ?>
                <p><span class="text-muted">Phone:</span> <?= escape_html($po['supplier_phone']) ?></p>
                <p><span class="text-muted">Email:</span> <?= escape_html($po['supplier_email']) ?></p>
            </div>
            <div class="address-box">
                <p><strong><?= escape_html($po['company_name'] ?: 'MJR COMPANY') ?></strong></p>
                <p>Division: Steel & Metal Fabrication Division</p>
                <p>Address: <?= escape_html($po['warehouse_address'] ?: '123, Industrial Area Phase-2, Jaipur') ?></p>
                <p>Contact: Purchase Department</p>
                <p>Phone: <?= escape_html($po['company_phone'] ?: '+91-98765-43210') ?></p>
            </div>
        </div>

        <!-- Items Table Headers -->
        <div class="section-header-bar">
            <span>Items Table</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" width="50">#</th>
                    <th>Item Description</th>
                    <th class="text-center" width="100">Qty</th>
                    <th class="text-end" width="120">Unit Price</th>
                    <th class="text-end" width="80">Tax</th>
                    <th class="text-end" width="150">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php $n = 1; foreach ($po_lines as $line): ?>
                <tr>
                    <td class="text-center text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= escape_html($line['item_name']) ?></strong><br>
                        <span class="sku-tag">SKU: <?= escape_html($line['code']) ?> | <?= escape_html($line['category_name'] ?: 'Uncategorized') ?></span>
                    </td>
                    <td class="text-center"><strong><?= number_format($line['quantity'], 0) ?></strong></td>
                    <td class="text-end"><?= $curr_symbol ?><?= number_format($line['unit_price'], 2) ?></td>
                    <td class="text-end"><?= $curr_symbol ?>0.00</td>
                    <td class="text-end"><strong><?= $curr_symbol ?><?= number_format($line['line_total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- International Cost Section -->
        <?php if ($po['purchase_type'] === 'International'): ?>
        <div class="section-header-bar" style="margin-top:0;">
            <span>International Cost Section</span>
        </div>
        <div class="landed-cost-section">
            <div>
                <div class="meta-label">Freight</div>
                <div class="meta-value"><?= $curr_symbol ?><?= number_format($po['freight'], 2) ?></div>
                <div class="sku-tag">Sea freight - 14 days</div>
            </div>
            <div>
                <div class="meta-label">Duty / Customs</div>
                <div class="meta-value"><?= $curr_symbol ?><?= number_format($po['duty'], 2) ?></div>
                <div class="sku-tag">Duty rate: 0%</div>
            </div>
            <div>
                <div class="meta-label">Insurance</div>
                <div class="meta-value"><?= $curr_symbol ?><?= number_format($po['insurance'], 2) ?></div>
                <div class="sku-tag">Not applicable</div>
            </div>
            <div>
                <div class="meta-label" style="color:var(--accent-yellow)">Total Landed Cost</div>
                <div class="meta-value" style="color:red; font-size: 18px;"><?= $curr_symbol ?><?= number_format($po['landed_cost'], 2) ?></div>
                <div class="sku-tag">Freight + Duty + Ins.</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Summary Section -->
        <div class="section-header-bar" style="margin-top:0;">
            <span>Order Summary</span>
        </div>
        <div class="summary-grid">
            <div class="summary-left">
                <div class="amount-words-box">
                    <div class="meta-label" style="color:var(--accent-yellow)">Amount in Words:</div>
                    <div class="meta-value" style="color:darkorange"><?= amount_in_words($po['total_amount']) ?></div>
                </div>
                <div class="meta-label">Terms & Conditions:</div>
                <p style="font-size: 10px; color: var(--text-muted);">
                    Payment due within 30 days. Goods must match specifications.<br>
                    All disputes subject to Jaipur jurisdiction.
                </p>
            </div>
            <div class="summary-right">
                <table class="summary-table">
                    <tr>
                        <td class="text-muted">Items Subtotal</td>
                        <td class="text-end fw-bold"><?= $curr_symbol ?><?= number_format($po['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Tax Amount</td>
                        <td class="text-end fw-bold"><?= $curr_symbol ?><?= number_format($po['tax_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Shipping</td>
                        <td class="text-end fw-bold"><?= $curr_symbol ?><?= number_format($po['shipping_cost'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Discount</td>
                        <td class="text-end fw-bold" style="color:#28a745;">— <?= $curr_symbol ?>0.00</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td style="padding: 15px;">GRAND TOTAL (<?= $curr_code ?>)</td>
                        <td class="text-end" style="color:var(--accent-yellow); padding: 15px;"><?= $curr_symbol ?><?= number_format($po['total_amount'], 2) ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="section-header-bar" style="margin-top:0;">
            <span>Attachments | Notes</span>
        </div>
        <div class="address-grid" style="border-bottom:none;">
            <div class="address-box">
                <h3>Attachments</h3>
                <div style="font-size: 11px; opacity: 0.5;">No digital attachments linked.</div>
            </div>
            <div class="address-box">
                <h3>Remarks / Notes</h3>
                <p><?= nl2br(escape_html($po['notes'] ?: 'N/A')) ?></p>
            </div>
        </div>

        <!-- Workflow Panel -->
        <div class="section-header-bar" style="margin-top:0;">
            <span>Workflow Panel</span>
        </div>
        <div class="workflow-panel">
            <div class="workflow-timeline">
                <?php
                $status_map = [
                    'draft' => 0,
                    'pending_approval' => 1,
                    'approved' => 2,
                    'rejected' => 1,
                    'sent' => 3,
                    'confirmed' => 3,
                    'partially_received' => 3,
                    'received' => 3
                ];
                $current_idx = $status_map[$po['status']] ?? 0;
                $steps = [
                    ['Draft', 'pencil-alt'],
                    ['Pending Approval', 'hourglass-half'],
                    ['Approved', 'check-double'],
                    ['Sent to Supplier', 'envelope-open-text']
                ];
                foreach ($steps as $idx => $step):
                    $is_past = $idx < $current_idx;
                    $is_current = $idx == $current_idx;
                    $is_rejected = ($po['status'] === 'rejected' && $idx === 1);
                    
                    $class = '';
                    if ($is_past) $class = 'step-completed';
                    elseif ($is_current) $class = 'step-active';
                    if ($is_rejected) $class = 'step-rejected';
                ?>
                <div class="workflow-step <?= $class ?>">
                    <div class="step-circle">
                        <i class="fas fa-<?= $step[1] ?>"></i>
                        <?php if ($is_past): ?><i class="fas fa-check check-checkmark"></i><?php endif; ?>
                    </div>
                    <div class="step-label"><?= $step[0] ?></div>
                    <?php if ($is_rejected): ?>
                        <div style="font-size: 8px; color: #dc3545; font-weight: 800;">REJECTED</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Footer Signatures -->
        <div class="signature-grid">
            <div>
                <div class="signature-line">
                    <span style="font-size: 14px; font-weight: 800;">Prepared By</span><br>
                    <span style="font-size: 11px; color: var(--text-muted);"><?= escape_html($po['created_by_name']) ?></span>
                </div>
            </div>
            <div>
                <div class="signature-line">
                    <span style="font-size: 14px; font-weight: 800;">Authorized Signatory</span><br>
                    <span style="font-size: 11px; color: var(--text-muted);">Company Stamp & Signature</span>
                </div>
            </div>
        </div>

        <!-- Simple Footer -->
        <div style="margin-top: 40px; text-align: center; font-size: 9px; color: #aaa;">
            <?= escape_html($po['company_name'] ?: 'MJR COMPANY') ?> | Purchase Order — System Generated | Page 1 of 1
        </div>
    </div>

</body>
</html>

