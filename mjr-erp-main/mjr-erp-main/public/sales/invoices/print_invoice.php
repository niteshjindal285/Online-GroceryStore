<?php
/**
 * Print Invoice — Clean Printable Format
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
$inv = db_fetch("
    SELECT i.*, c.name AS customer_name, c.email AS customer_email,
           c.phone AS customer_phone, c.address AS customer_address,
           c.tax_number AS customer_tax
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    WHERE i.id = ?
", [$id]);
if (!$inv) die('Invoice not found.');

$lines = db_fetch_all("
    SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il JOIN inventory_items ii ON ii.id = il.item_id
    WHERE il.invoice_id = ?", [$id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= escape_html($inv['invoice_number']) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size: 13px; color: #1a1a1a; background: #fff; }
    .container { max-width: 820px; margin: 30px auto; padding: 0 30px; }
    header { display:flex; justify-content:space-between; border-bottom: 3px solid #2563eb; padding-bottom: 20px; margin-bottom: 24px; }
    .company-name { font-size: 26px; font-weight: 800; color: #2563eb; }
    .inv-title { font-size: 32px; font-weight: 700; color: #1a1a1a; text-align:right; }
    .inv-number { font-size: 16px; color: #555; text-align:right; }
    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }
    .meta-box h4 { font-size:11px; text-transform:uppercase; color:#888; letter-spacing:1px; margin-bottom:6px; }
    .meta-box p { margin-bottom:2px; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th { background:#2563eb; color:#fff; padding:9px 10px; text-align:left; font-size:12px; }
    td { padding:8px 10px; border-bottom:1px solid #e5e7eb; }
    tr:nth-child(even) td { background:#f9fafb; }
    .totals { display:flex; justify-content:flex-end; margin-bottom:20px; }
    .totals table { width:280px; }
    .totals td { border:none; padding:4px 8px; }
    .totals .grand-total td { font-size:15px; font-weight:bold; border-top:2px solid #2563eb; padding-top:8px; }
    .status-badge { display:inline-block; padding:4px 14px; border-radius:20px; font-weight:bold; font-size:12px; }
    .status-open { background:#fef3c7; color:#92400e; }
    .status-closed { background:#d1fae5; color:#065f46; }
    .status-cancelled { background:#f3f4f6; color:#6b7280; }
    footer { text-align:center; font-size:11px; color:#999; border-top:1px solid #e5e7eb; padding-top:16px; margin-top:30px; }
    @media print { .no-print { display:none; } body { background:#fff; } }
</style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <div class="company-name">MJR Group ERP</div>
            <p style="color:#555;font-size:12px;">mjrgroup.com | hq@mjrgroup.com</p>
        </div>
        <div style="text-align:right;">
            <div class="inv-title">INVOICE</div>
            <div class="inv-number"><?= escape_html($inv['invoice_number']) ?></div>
            <div style="margin-top:8px;">
                <span class="status-badge status-<?= $inv['payment_status'] ?>"><?= ucfirst($inv['payment_status']) ?></span>
            </div>
        </div>
    </header>

    <div class="meta-grid">
        <div class="meta-box">
            <h4>Bill To</h4>
            <p><strong><?= escape_html($inv['customer_name']) ?></strong></p>
            <p><?= nl2br(escape_html($inv['customer_address'] ?? '')) ?></p>
            <?php if ($inv['customer_tax']): ?><p>Tax: <?= escape_html($inv['customer_tax']) ?></p><?php endif; ?>
            <p><?= escape_html($inv['customer_email'] ?? '') ?></p>
            <p><?= escape_html($inv['customer_phone'] ?? '') ?></p>
        </div>
        <div class="meta-box" style="text-align:right;">
            <h4>Invoice Details</h4>
            <p><strong>Invoice No:</strong> <?= escape_html($inv['invoice_number']) ?></p>
            <p><strong>Date:</strong> <?= format_date($inv['invoice_date']) ?></p>
            <p><strong>Due Date:</strong> <?= format_date($inv['due_date']) ?></p>
            <p><strong>Amount Due:</strong> <?= format_currency($inv['total_amount'] - $inv['amount_paid']) ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr><th>#</th><th>Item Code</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Disc%</th><th style="text-align:right;">Total</th></tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= escape_html($l['item_code']) ?></td>
                <td><?= escape_html($l['item_name']) ?><?= $l['description'] ? '<br><small style="color:#888">'.escape_html($l['description']).'</small>' : '' ?></td>
                <td><?= number_format($l['quantity'],2) ?></td>
                <td><?= format_currency($l['unit_price']) ?></td>
                <td><?= $l['discount_pct'] ?>%</td>
                <td style="text-align:right;"><?= format_currency($l['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Subtotal</td><td style="text-align:right;"><?= format_currency($inv['subtotal']) ?></td></tr>
            <tr><td>Discount</td><td style="text-align:right; color:#dc2626;">-<?= format_currency($inv['discount_amount']) ?></td></tr>
            <tr><td>Tax</td><td style="text-align:right;"><?= format_currency($inv['tax_amount']) ?></td></tr>
            <tr class="grand-total"><td>Total</td><td style="text-align:right;"><?= format_currency($inv['total_amount']) ?></td></tr>
            <tr><td style="color:#059669;">Paid</td><td style="text-align:right;color:#059669;"><?= format_currency($inv['amount_paid']) ?></td></tr>
            <tr><td><strong>Balance Due</strong></td><td style="text-align:right;"><strong><?= format_currency($inv['total_amount'] - $inv['amount_paid']) ?></strong></td></tr>
        </table>
    </div>

    <?php if ($inv['notes']): ?>
    <div style="background:#f9fafb;border-left:4px solid #2563eb;padding:12px;margin-bottom:20px;border-radius:4px;">
        <strong>Notes:</strong><br><?= nl2br(escape_html($inv['notes'])) ?>
    </div>
    <?php endif; ?>

    <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:14px;border-radius:6px;margin-bottom:24px;">
        <strong>Payment Instructions:</strong> Please reference invoice number <strong><?= escape_html($inv['invoice_number']) ?></strong> on all payments.
        Payment due by <strong><?= format_date($inv['due_date']) ?></strong>.
    </div>

    <footer>
        <p>Thank you for your business. Generated <?= date('d M Y H:i') ?> | MJR Group ERP</p>
    </footer>
</div>

<div class="no-print" style="text-align:center;margin:24px;">
    <button onclick="window.print()" style="background:#2563eb;color:#fff;border:none;padding:10px 30px;border-radius:6px;font-size:15px;cursor:pointer;">🖨 Print / Save PDF</button>
    <a href="view_invoice.php?id=<?= $id ?>" style="margin-left:12px;color:#555;text-decoration:none;">← Back to Invoice</a>
</div>
</body>
</html>
