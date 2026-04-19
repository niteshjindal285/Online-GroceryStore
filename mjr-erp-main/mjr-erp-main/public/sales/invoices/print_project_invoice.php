<?php
/**
 * Print Project Invoice — Phase Claim Format
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
$inv = db_fetch("
    SELECT i.*, c.name AS customer_name, c.email AS customer_email,
           c.phone AS customer_phone, c.address AS customer_address,
           c.tax_number AS customer_tax,
           ps.stage_name, ps.percentage AS stage_pct, ps.amount AS stage_amount,
           p.name AS project_name
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    LEFT JOIN project_stages ps ON ps.id = i.project_stage_id
    LEFT JOIN projects p ON p.id = i.project_id
    WHERE i.id = ?
", [$id]);

if (!$inv) die('Invoice not found.');

$lines = db_fetch_all("
    SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il JOIN inventory_items ii ON ii.id = il.item_id
    WHERE il.invoice_id = ?", [$id]);

// Calculate Variations (If total_amount != stage_amount)
$variation_amount = $inv['subtotal'] - ($inv['stage_amount'] ?? $inv['subtotal']);
if ($variation_amount < 0.01) $variation_amount = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Phase Claim - <?= escape_html($inv['invoice_number']) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
    .container { max-width: 820px; margin: 30px auto; padding: 0 30px; }
    header { display:flex; justify-content:space-between; border-bottom: 4px solid #1e40af; padding-bottom: 20px; margin-bottom: 24px; }
    .company-name { font-size: 26px; font-weight: 800; color: #1e40af; }
    .inv-title { font-size: 28px; font-weight: 700; color: #1a1a1a; text-align:right; text-transform: uppercase; }
    .inv-number { font-size: 16px; color: #666; text-align:right; font-weight: bold; }
    .project-header { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 24px; }
    .project-header h3 { color: #1e40af; font-size: 16px; margin-bottom: 5px; }
    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .meta-box h4 { font-size:11px; text-transform:uppercase; color:#64748b; letter-spacing:1px; margin-bottom:6px; font-weight: 800; }
    .meta-box p { margin-bottom:2px; line-height: 1.4; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; border: 1px solid #e2e8f0; }
    th { background:#f1f5f9; color:#475569; padding:12px 10px; text-align:left; font-size:11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #cbd5e1; }
    td { padding:10px 10px; border-bottom:1px solid #e2e8f0; }
    .totals { display:flex; justify-content:flex-end; margin-bottom:20px; }
    .totals table { width:320px; border: none; }
    .totals td { border:none; padding:6px 10px; text-align: right; }
    .totals .label { text-align: left; color: #64748b; font-weight: 600; }
    .totals .grand-total td { font-size:18px; font-weight:800; border-top:2px solid #1e40af; padding-top:12px; color: #1e40af; }
    .footer-note { background:#f0f9ff; border:1px solid #bae6fd; padding:14px; border-radius:8px; margin-bottom:24px; font-size: 12px; color: #0369a1; }
    footer { text-align:center; font-size:11px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:20px; margin-top:40px; }
    @media print { .no-print { display:none; } body { background:#fff; } .container { margin: 0; padding: 0; } }
</style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <div class="company-name">MJR Group ERP</div>
            <p style="color:#64748b;font-size:12px;">Premium Quality Construction & Engineering</p>
        </div>
        <div style="text-align:right;">
            <div class="inv-title">PHASE CLAIM</div>
            <div class="inv-number"><?= escape_html($inv['invoice_number']) ?></div>
            <div style="color: #64748b; font-size: 12px; margin-top: 4px;">Date: <?= format_date($inv['invoice_date']) ?></div>
        </div>
    </header>

    <div class="project-header">
        <h3>PROJECT: <?= escape_html($inv['project_name'] ?? 'General Project') ?></h3>
        <p style="font-weight: 600; color: #475569;">CLAIM FOR: <?= escape_html($inv['stage_name'] ?? 'Work Progress') ?> (<?= number_format($inv['stage_pct'] ?? 0, 0) ?>%)</p>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <h4>Customer Details</h4>
            <p><strong><?= escape_html($inv['customer_name']) ?></strong></p>
            <p><?= nl2br(escape_html($inv['customer_address'] ?? '')) ?></p>
            <?php if ($inv['customer_tax']): ?><p>Tax ID: <?= escape_html($inv['customer_tax']) ?></p><?php endif; ?>
        </div>
        <div class="meta-box" style="text-align:right;">
            <h4>Billing Summary</h4>
            <p><strong>Invoice No:</strong> <?= escape_html($inv['invoice_number']) ?></p>
            <p><strong>Due Date:</strong> <?= format_date($inv['due_date']) ?></p>
            <p><strong>Status:</strong> <?= strtoupper($inv['payment_status']) ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Material / Work Description</th>
                <th style="text-align: center; width: 100px;">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td>
                    <strong><?= escape_html($l['item_name']) ?></strong>
                    <?php if ($l['description']): ?>
                        <div style="color:#64748b; font-size: 11px; margin-top: 3px;"><?= nl2br(escape_html($l['description'])) ?></div>
                    <?php endif; ?>
                </td>
                <td style="text-align: center; font-weight: 600;"><?= number_format($l['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td class="label"><?= escape_html($inv['stage_name'] ?? 'Phase') ?> Amount (<?= number_format($inv['stage_pct'] ?? 0, 0) ?>%):</td>
                <td><?= format_currency($inv['stage_amount'] ?? $inv['subtotal']) ?></td>
            </tr>
            <?php if ($variation_amount > 0): ?>
            <tr>
                <td class="label">Project Variations / Add-ons:</td>
                <td><?= format_currency($variation_amount) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($inv['tax_amount'] > 0): ?>
            <tr>
                <td class="label">Tax:</td>
                <td><?= format_currency($inv['tax_amount']) ?></td>
            </tr>
            <?php endif; ?>
            <tr class="grand-total">
                <td class="label">TOTAL CLAIM:</td>
                <td><?= format_currency($inv['total_amount']) ?></td>
            </tr>
        </table>
    </div>

    <div class="footer-note">
        <strong>Notes:</strong><br>
        <?= $inv['notes'] ? nl2br(escape_html($inv['notes'])) : 'This invoice is a claim for work completed as per project milestones. Please settle as per agreed terms.' ?>
    </div>

    <footer>
        <p>This is a computer generated document. | MJR Group ERP | <?= date('Y') ?></p>
    </footer>
</div>

<div class="no-print" style="text-align:center;margin:40px 0;">
    <button onclick="window.print()" style="background:#1e40af;color:#fff;border:none;padding:12px 40px;border-radius:8px;font-size:16px;font-weight:bold;cursor:pointer;box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">🖨 PRINT CLAIM DOCUMENT</button>
    <a href="view_invoice.php?id=<?= $id ?>" style="display:block;margin-top:15px;color:#64748b;text-decoration:none;font-weight:600;">← Return to Invoice View</a>
</div>
</body>
</html>
