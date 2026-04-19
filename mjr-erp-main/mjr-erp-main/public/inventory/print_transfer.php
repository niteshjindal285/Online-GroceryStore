<?php
/**
 * Print Stock Transfer - Premium MJR Theme
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    die("Transfer not found.");
}

$transfer = db_fetch("
    SELECT h.*, u.username as requester_name, u.full_name as requester_full_name,
           m.username as manager_name, m.full_name as manager_full_name,
           sl.name as source_name, dl.name as dest_name,
           c.name as company_name
    FROM transfer_headers h
    LEFT JOIN users u ON h.requested_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN locations sl ON h.source_location_id = sl.id
    LEFT JOIN locations dl ON h.dest_location_id = dl.id
    LEFT JOIN companies c ON h.company_id = c.id
    WHERE h.id = ?
", [$id]);

if (!$transfer) {
    die("Transfer not found.");
}

$items = db_fetch_all("
    SELECT ti.*, ii.name as item_name, ii.code as item_code
    FROM transfer_items ti
    JOIN inventory_items ii ON ti.item_id = ii.id
    WHERE ti.transfer_id = ?
", [$id]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock Transfer - <?= escape_html($transfer['transfer_number']) ?></title>
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

        /* Detail Grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border-color);
        }
        .info-box { padding: 20px; border-right: 1px solid var(--border-color); }
        .info-box:last-child { border-right: none; }
        .info-box p { margin: 0 0 5px; font-size: 12px; }

        /* Table Styles */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead { background: #f4f6f9; }
        .items-table th { padding: 12px 15px; text-align: left; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid var(--primary-dark); }
        .items-table td { padding: 15px; border-bottom: 1px solid var(--border-color); font-size: 12px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Summary Bar */
        .summary-bar {
            background: var(--primary-dark);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-value { color: var(--accent-gold); font-size: 20px; font-weight: 800; }

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
            <i class="fas fa-print"></i> Print Transfer
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
                    <h1><?= escape_html($transfer['company_name'] ?: 'MJR COMPANY') ?></h1>
                    <p>Internal Logistics & Warehouse Management</p>
                    <p>Quality • Precision • Reliability</p>
                    <p>123, Industrial Area Phase-2, Jaipur | +91-98765-43210</p>
                </div>
            </div>
            <div class="doc-title-box">Stock Transfer</div>
        </header>

        <!-- Meta Bar -->
        <div class="meta-bar">
            <div class="meta-item">
                <div class="meta-label">Transfer Doc #</div>
                <div class="meta-value"><?= escape_html($transfer['transfer_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Req. Date</div>
                <div class="meta-value"><?= format_date($transfer['transfer_date']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Transfer Type</div>
                <div class="meta-value"><?= strtoupper($transfer['transfer_type'] ?: 'Internal') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="status-badge"><?= strtoupper(str_replace('_', ' ', $transfer['status'])) ?></span>
                </div>
            </div>
        </div>

        <div class="section-header-bar">
            <span>From (Source)</span>
            <span>To (Destination)</span>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <p><strong><?= escape_html($transfer['source_name'] ?: 'Main Warehouse') ?></strong></p>
                <p><span class="text-muted">Bin Loc:</span> <?= escape_html($transfer['source_bin_head'] ?: 'Default') ?></p>
                <p><span class="text-muted">Issued By:</span> <?= escape_html($transfer['requester_full_name'] ?: $transfer['requester_name']) ?></p>
            </div>
            <div class="info-box">
                <p><strong><?= escape_html($transfer['dest_name'] ?: 'Production Floor') ?></strong></p>
                <p><span class="text-muted">Bin Loc:</span> <?= escape_html($transfer['dest_bin_head'] ?: 'Default') ?></p>
                <p><span class="text-muted">Authorized By:</span> <?= escape_html($transfer['manager_full_name'] ?: $transfer['manager_name'] ?: 'Pending') ?></p>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Itemized Transfer List</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="text-center" width="50">#</th>
                    <th>Item Description & Code</th>
                    <th class="text-center" width="100">Qty Moved</th>
                    <th class="text-right" width="120">Unit Cost</th>
                    <th class="text-right" width="150">Line Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_total = 0;
                $n = 1; 
                foreach ($items as $item): 
                    $line_total = $item['quantity'] * ($item['unit_cost'] ?? 0);
                    $grand_total += $line_total;
                ?>
                <tr>
                    <td class="text-center text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                    <td>
                        <strong><?= escape_html($item['item_name']) ?></strong><br>
                        <span style="font-size:10px; color:var(--text-muted)">CODE: <?= escape_html($item['item_code']) ?></span>
                    </td>
                    <td class="text-center"><strong><?= number_format($item['quantity'], 2) ?></strong></td>
                    <td class="text-right">$<?= number_format($item['unit_cost'] ?? 0, 2) ?></td>
                    <td class="text-right"><strong>$<?= number_format($line_total, 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Summary Bar -->
        <div class="summary-bar">
            <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 1px;">Estimated Total Movement Value:</div>
            <div class="total-value">$ <?= number_format($grand_total, 2) ?></div>
        </div>

        <div class="section-header-bar">
            <span>Transfer Remarks</span>
        </div>
        <div style="padding: 20px; border-bottom: 1px solid var(--border-color); font-size: 12px;">
            <p><strong>Requester Notes:</strong> <?= nl2br(escape_html($transfer['warehouse_remarks'] ?: 'N/A')) ?></p>
            <p><strong>Supervisor Instructions:</strong> <?= nl2br(escape_html($transfer['supervisor_notes'] ?: 'N/A')) ?></p>
        </div>

        <!-- Signature Section -->
        <div class="signature-grid">
            <div class="signature-line">Warehouse Issuer (Source)</div>
            <div class="signature-line">Authorized Manager</div>
            <div class="signature-line">Receiver Stamp (Dest)</div>
        </div>

        <div style="margin-top: 50px; text-align: center; font-size: 9px; color: #aaa;">
            MJR COMPANY | Internal Stock Transfer Document — Unauthorized duplication prohibited | Page 1 of 1
        </div>
    </div>
</body>
</html>
