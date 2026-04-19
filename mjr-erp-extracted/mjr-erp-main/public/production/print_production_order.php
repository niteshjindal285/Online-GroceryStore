<?php
/**
 * Print Production Order
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$wo_id = get_param('id');
if (!$wo_id) {
    die("Production order not found.");
}

$work_order = db_fetch("
    SELECT wo.*, 
           i.code as product_code, i.name as product_name, i.unit_of_measure,
           l.name as location_name,
           u.username as created_by_name
    FROM work_orders wo
    LEFT JOIN inventory_items i ON wo.product_id = i.id
    LEFT JOIN locations l ON wo.location_id = l.id
    LEFT JOIN users u ON wo.created_by = u.id
    WHERE wo.id = ?
", [$wo_id]);

if (!$work_order) {
    die("Production order not found.");
}

$bom_items = db_fetch_all("
    SELECT bom.*,
           comp.code as component_code,
           comp.name as component_name,
           comp.unit_of_measure,
           comp.cost_price as unit_cost,
           comp.description,
           COALESCE(SUM(wi.quantity), 0) as stock_available
    FROM bill_of_materials bom
    LEFT JOIN inventory_items comp ON bom.component_id = comp.id
    LEFT JOIN warehouse_inventory wi ON comp.id = wi.product_id
    WHERE bom.product_id = ? AND bom.is_active = 1
    GROUP BY bom.id, comp.id
", [$work_order['product_id']]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Production Order - <?= escape_html($work_order['wo_number']) ?></title>
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
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border-color);
        }
        .detail-box { padding: 20px; border-right: 1px solid var(--border-color); }
        .detail-box:last-child { border-right: none; }
        .detail-box h3 { margin: 0 0 10px; font-size: 11px; color: var(--text-muted); text-transform: uppercase; }
        .detail-box p { margin: 0 0 5px; font-size: 12px; }

        /* Table Styles */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table thead { background: #f4f6f9; }
        .items-table th { padding: 10px 15px; text-align: left; font-size: 10px; text-transform: uppercase; border-bottom: 2px solid var(--primary-dark); }
        .items-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); font-size: 12px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }

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
    </style>
</head>
<body onload="window.print()">

    <div class="toolbar no-print">
        <button onclick="window.print()" class="btn">
            <i class="fas fa-print"></i> Print Production Order
        </button>
        <button onclick="window.close()" class="btn" style="background:#6c757d;">
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
            <div class="doc-title-box">Production Order</div>
        </header>

        <!-- Meta Bar -->
        <div class="meta-bar">
            <div class="meta-item">
                <div class="meta-label">Production Order Number</div>
                <div class="meta-value"><?= escape_html($work_order['wo_number']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Issued Date</div>
                <div class="meta-value"><?= format_date($work_order['start_date'] ?? $work_order['created_at']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label" style="color:#d35400;">Due Deadline</div>
                <div class="meta-value"><?= $work_order['due_date'] ? format_date($work_order['due_date']) : 'Open' ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Current Status</div>
                <div class="meta-value">
                    <span class="status-badge"><?= strtoupper(str_replace('_', ' ', $work_order['status'])) ?></span>
                </div>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Production Specification</span>
            <span>Assignment & Location</span>
        </div>

        <div class="detail-grid">
            <div class="detail-box">
                <p><strong>Item To Manufacture:</strong></p>
                <p><?= escape_html($work_order['product_name']) ?> (<?= escape_html($work_order['product_code']) ?>)</p>
                <p><strong>Target Quantity:</strong> <?= number_format($work_order['quantity'], 0) ?> <?= escape_html($work_order['unit_of_measure']) ?></p>
                <p><strong>Priority Level:</strong> <span style="color:red; font-weight:800;"><?= strtoupper($work_order['priority']) ?></span></p>
                <p><strong>Production Type:</strong> <?= escape_html($work_order['production_type'] ?? 'Stock') ?></p>
            </div>
            <div class="detail-box">
                <p><strong>Production Unit:</strong> <?= escape_html($work_order['location_name'] ?: 'Main Factory') ?></p>
                <p><strong>Issued By:</strong> <?= escape_html($work_order['created_by_name'] ?: 'Production Admin') ?></p>
                <div style="margin-top:5px; font-size:11px; border-top: 1px dashed #ccc; padding-top:5px;">
                    <strong>Costing Summary:</strong><br>
                    Labor: <?= number_format($work_order['labor_cost'] ?? 0, 2) ?> | 
                    Elec: <?= number_format($work_order['electricity_cost'] ?? 0, 2) ?> | 
                    Machine: <?= number_format($work_order['machine_cost'] ?? 0, 2) ?><br>
                    Total Est: <strong><?= number_format($work_order['total_cost'] ?? 0, 2) ?> <?= $work_order['cost_currency'] ?></strong>
                </div>
            </div>
        </div>

        <div class="section-header-bar">
            <span>Critical Tasks & Bill of Materials (BOM)</span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="50" class="text-center">#</th>
                    <th>Component / Material Specification</th>
                    <th width="100" class="text-center">Qty Required</th>
                    <th width="120">Source Store</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bom_items)): ?>
                    <?php $n=1; foreach ($bom_items as $item): 
                        $total_required = (float)$item['quantity_required'] * (float)$work_order['quantity'];
                    ?>
                    <tr>
                        <td class="text-center text-muted"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <strong><?= escape_html($item['component_name']) ?></strong><br>
                            <span style="font-size:10px; color:var(--text-muted)">CODE: <?= escape_html($item['component_code']) ?></span>
                        </td>
                        <td class="text-center"><strong><?= number_format($total_required, 2) ?> <?= escape_html($item['unit_of_measure']) ?></strong></td>
                        <td>Warehouse Stock</td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="text-center text-muted">No BOM requirements linked to this product.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Production Progress Checkpoints -->
        <div class="section-header-bar">
            <span>Production Checkpoints</span>
        </div>
        <table class="items-table" style="margin-top:0;">
            <tr>
                <td width="30"><input type="checkbox"></td>
                <td><strong>Phase 1: Raw Material Issuance</strong> — Material check and store release.</td>
            </tr>
            <tr>
                <td><input type="checkbox"></td>
                <td><strong>Phase 2: Fabrication & Assembly</strong> — Core production and welding.</td>
            </tr>
            <tr>
                <td><input type="checkbox"></td>
                <td><strong>Phase 3: Quality Control (QC)</strong> — Final inspection and dimensional check.</td>
            </tr>
        </table>

        <!-- Footer Signatures -->
        <div class="signature-grid">
            <div class="signature-line">
                <span style="font-weight: 800;">Production Manager</span><br>
                <span style="font-size: 11px; color: var(--text-muted);">Shift Supervisor Signature</span>
            </div>
            <div class="signature-line">
                <span style="font-weight: 800;">QC Inspector</span><br>
                <span style="font-size: 11px; color: var(--text-muted);">Inspection Stamp / Signature</span>
            </div>
        </div>

        <div style="margin-top: 50px; text-align: center; font-size: 9px; color: #aaa;">
            MJR COMPANY | Internal Production Order — Confidential | Page 1 of 1
        </div>
    </div>
</body>
</html>
