<?php
/**
 * Print Price Change - Premium Template
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    die("Price Change record not found.");
}

// Fetch Header
$pc = db_fetch("
    SELECT h.*, u.username as creator_name, u.full_name as creator_full_name,
           c.name as company_name, c.address as company_address, c.phone as company_phone, c.email as company_email
    FROM price_change_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN companies c ON h.company_id = c.id
    WHERE h.id = ?
", [$id]);

if (!$pc) {
    die("Price Change record not found.");
}

// Fetch Items
$items = db_fetch_all("
    SELECT pi.*, i.code as item_code, i.name as item_name, cat.name as category_name
    FROM price_change_items pi
    JOIN inventory_items i ON pi.item_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    WHERE pi.pc_header_id = ?
", [$id]);

// Impact Calculation Summary
$total_diff = 0;
$avg_diff_pct = 0;
if (!empty($items)) {
    $pct_sum = 0;
    foreach ($items as $item) {
        $diff = $item['new_price'] - $item['current_price'];
        $total_diff += $diff;
        if ($item['current_price'] > 0) {
            $pct_sum += ($diff / $item['current_price'] * 100);
        }
    }
    $avg_diff_pct = $pct_sum / count($items);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Price Change: <?= escape_html($pc['pc_number']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a1a1a;
            --accent-blue: #0dcaf0;
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
            background: #eee;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 10mm;
            margin: 10mm auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        @media print {
            body { background: none; }
            .page { margin: 0; box-shadow: none; width: 100%; }
            .no-print { display: none !important; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        /* Header */
        .header {
            background-color: var(--primary-dark);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 5px solid var(--accent-blue);
        }

        .brand { display: flex; align-items: center; }
        .logo-box {
            background: var(--accent-blue);
            color: var(--primary-dark);
            width: 60px; height: 60px;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; font-weight: 800;
            margin-right: 20px;
        }

        .company-details h1 { margin: 0; font-size: 24px; font-weight: 800; }
        .company-details p { margin: 2px 0 0; font-size: 11px; opacity: 0.8; }

        .doc-title-box {
            background: var(--accent-blue);
            color: var(--primary-dark);
            padding: 12px 20px;
            font-weight: 800; font-size: 18px;
            text-transform: uppercase;
        }

        /* Meta Bar */
        .meta-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-light);
            margin-bottom: 20px;
        }
        .meta-item { padding: 15px; border-right: 1px solid var(--border-color); }
        .meta-item:last-child { border-right: none; }
        .meta-label { font-size: 10px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 4px; }
        .meta-value { font-size: 13px; font-weight: 700; }

        .status-badge {
            background: var(--primary-dark);
            color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px;
        }

        /* Section Header */
        .section-header {
            background: var(--primary-dark);
            color: var(--accent-blue);
            padding: 8px 15px;
            font-size: 10px; font-weight: 800;
            text-transform: uppercase;
            margin-top: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border-color);
        }
        .info-box { padding: 15px; border-right: 1px solid var(--border-color); }
        .info-box:last-child { border-right: none; }
        .info-box h3 { margin: 0 0 10px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .info-box p { margin: 0 0 4px; font-size: 12px; }

        /* Table */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .items-table th { 
            padding: 12px 10px; background: #eee; 
            text-align: left; font-size: 11px; text-transform: uppercase;
            border-bottom: 2px solid var(--primary-dark);
        }
        .items-table td { padding: 10px; border-bottom: 1px solid var(--border-color); font-size: 12px; }
        
        .change-plus { color: #28a745; font-weight: 700; }
        .change-minus { color: #dc3545; font-weight: 700; }

        /* Summary */
        .summary-box {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            margin-top: 0;
            border-top: none;
        }
        .summary-left { padding: 15px; background: #fffef0; border: 1px solid #ffeeba; }
        .summary-right { padding: 0; }
        
        .summary-table { width: 100%; border-collapse: collapse; }
        .summary-table td { padding: 10px 15px; font-size: 13px; }
        .total-row { background: var(--primary-dark); color: white; font-weight: 800; }

        /* Signatures */
        .sig-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 60px;
            padding: 0 40px;
            gap: 80px;
        }
        .sig-box { border-top: 2px solid var(--primary-dark); text-align: center; padding-top: 10px; }

        /* Buttons bar */
        .toolbar {
            position: fixed; top: 20px; right: 20px;
            display: flex; gap: 10px;
        }
        .btn {
            padding: 10px 20px; background: var(--primary-dark); color: white;
            border: none; border-radius: 4px; font-weight: 700; cursor: pointer;
            text-decoration: none; display: flex; align-items: center; gap: 10px;
        }
        .btn-secondary { background: #6c757d; }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <button onclick="window.print()" class="btn">
        <i class="fas fa-print"></i> Print Document
    </button>
    <a href="view_price_change.php?id=<?= $id ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to System
    </a>
</div>

<div class="page">
    <header class="header">
        <div class="brand">
            <div class="logo-box">MJR</div>
            <div class="company-details">
                <h1><?= escape_html($pc['company_name'] ?: 'MJR GROUP ERP') ?></h1>
                <p>Industrial Solutions • Quality Fabrication • Precision Logistics</p>
                <p><?= escape_html($pc['company_address'] ?: 'Corporate Office, Jaipur') ?></p>
            </div>
        </div>
        <div class="doc-title-box">Price Change</div>
    </header>

    <div class="meta-bar">
        <div class="meta-item">
            <div class="meta-label">Reference Number</div>
            <div class="meta-value"><?= escape_html($pc['pc_number']) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Request Date</div>
            <div class="meta-value"><?= format_date($pc['pc_date']) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Effective Date</div>
            <div class="meta-value" style="color:var(--accent-blue)"><?= format_date($pc['effective_date']) ?></div>
        </div>
        <div class="meta-item">
            <div class="meta-label">Current Status</div>
            <div class="meta-value">
                <span class="status-badge"><?= strtoupper($pc['status']) ?></span>
            </div>
        </div>
    </div>

    <div class="section-header">Request Overview</div>
    <div class="info-grid">
        <div class="info-box">
            <h3>Requested By</h3>
            <p><strong><?= escape_html($pc['creator_full_name'] ?: $pc['creator_name']) ?></strong></p>
            <p>Account ID: <?= escape_html($pc['creator_name']) ?></p>
            <p>Module: Inventory Management</p>
        </div>
        <div class="info-box">
            <h3>Subsidiary Details</h3>
            <p><strong><?= escape_html($pc['company_name']) ?></strong></p>
            <p>Price Category: <?= escape_html($pc['price_category']) ?></p>
            <p>Reason: <?= escape_html($pc['reason'] ?: 'Standard Price Adjustment') ?></p>
        </div>
    </div>

    <div class="section-header">Affected Products Table</div>
    <table class="items-table">
        <thead>
            <tr>
                <th width="40">#</th>
                <th>Product Description</th>
                <th>Category</th>
                <th style="text-align:right">Old Price</th>
                <th style="text-align:right">New Price</th>
                <th style="text-align:right">Change %</th>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($items as $item): ?>
                <?php 
                $diff = $item['new_price'] - $item['current_price'];
                $pct = ($item['current_price'] > 0) ? ($diff / $item['current_price'] * 100) : 0;
                ?>
                <tr>
                    <td style="color:#888"><?= str_pad($i++, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= escape_html($item['item_name']) ?></strong><br>
                        <span style="font-size:10px; color:#777">CODE: <?= escape_html($item['item_code']) ?></span>
                    </td>
                    <td><?= escape_html($item['category_name']) ?></td>
                    <td style="text-align:right"><?= number_format($item['current_price'], 2) ?></td>
                    <td style="text-align:right; font-weight:700"><?= number_format($item['new_price'], 2) ?></td>
                    <td style="text-align:right" class="<?= $pct >= 0 ? 'change-plus' : 'change-minus' ?>">
                        <?= ($pct >= 0 ? '+' : '') . number_format($pct, 1) ?>%
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="summary-box">
        <div class="summary-left">
            <div class="meta-label">Verifier Remarks:</div>
            <p style="font-size:11px; margin:5px 0 0"><?= nl2br(escape_html($pc['remarks'] ?: 'No internal remarks provided.')) ?></p>
        </div>
        <div class="summary-right">
            <table class="summary-table">
                <tr>
                    <td>Total Products Affected</td>
                    <td style="text-align:right; font-weight:700"><?= count($items) ?> Items</td>
                </tr>
                <tr class="total-row">
                    <td>Average Price Impact</td>
                    <td style="text-align:right"><?= ($avg_diff_pct >= 0 ? '+' : '') . number_format($avg_diff_pct, 1) ?>%</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="sig-grid">
        <div class="sig-box">
            <div style="font-size:13px; font-weight:800">PREPARED BY</div>
            <div style="font-size:11px; color:#777; margin-top:4px"><?= escape_html($pc['creator_full_name']) ?></div>
        </div>
        <div class="sig-box">
            <div style="font-size:13px; font-weight:800">AUTHORIZED SIGNATORY</div>
            <div style="font-size:11px; color:#777; margin-top:4px">Management Approval Stamp</div>
        </div>
    </div>

    <footer style="margin-top:60px; text-align:center; font-size:9px; color:#aaa; border-top:1px solid #eee; padding-top:10px">
        <?= escape_html($pc['company_name']) ?> | Price Change Document — Confidential | Page 1 of 1
    </footer>
</div>

</body>
</html>
