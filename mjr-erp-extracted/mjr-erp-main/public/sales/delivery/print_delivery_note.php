<?php
/**
 * Print Delivery Note (Packing Slip)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
$dn = db_fetch("SELECT dn.*, ds.invoice_id, i.invoice_number, c.name AS customer_name, c.address AS customer_address
    FROM delivery_notes dn
    JOIN delivery_schedule ds ON ds.id = dn.delivery_schedule_id
    JOIN invoices i ON i.id = ds.invoice_id
    JOIN customers c ON c.id = i.customer_id
    WHERE dn.id=?", [$id]);
if (!$dn) die('Delivery note not found.');

$dn_lines = db_fetch_all("SELECT dnl.*, ii.name AS item_name, ii.code AS item_code
    FROM delivery_note_lines dnl
    JOIN inventory_items ii ON ii.id = dnl.item_id
    WHERE dnl.delivery_note_id=?", [$id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Delivery Note <?= escape_html($dn['delivery_number']) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: Arial, sans-serif; font-size:13px; color:#1a1a1a; background:#fff; }
    .container { max-width:760px; margin:30px auto; padding:0 30px; }
    header { display:flex; justify-content:space-between; border-bottom:3px solid #059669; padding-bottom:18px; margin-bottom:22px; }
    .co-name { font-size:24px; font-weight:800; color:#059669; }
    .dn-title { font-size:28px; font-weight:700; text-align:right; }
    .dn-number { color:#555; text-align:right; font-size:15px; }
    .meta { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:22px; }
    .meta h4 { font-size:10px; text-transform:uppercase; color:#888; letter-spacing:1px; margin-bottom:5px; }
    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th { background:#059669; color:#fff; padding:8px 10px; text-align:left; font-size:12px; }
    td { padding:7px 10px; border-bottom:1px solid #e5e7eb; }
    tr:nth-child(even) td { background:#f9fafb; }
    .sig { display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:40px; }
    .sig-box { border-top:1px solid #333; padding-top:8px; font-size:12px; }
    footer { text-align:center; font-size:11px; color:#999; border-top:1px solid #e5e7eb; padding-top:14px; margin-top:28px; }
    @media print { .no-print { display:none; } }
</style>
</head>
<body>
<div class="container">
    <header>
        <div>
            <div class="co-name">MJR Group ERP</div>
            <p style="color:#555;font-size:12px;">mjrgroup.com</p>
        </div>
        <div>
            <div class="dn-title">DELIVERY NOTE</div>
            <div class="dn-number"><?= escape_html($dn['delivery_number']) ?></div>
            <div class="dn-number" style="margin-top:4px;">Ref Invoice: <strong><?= escape_html($dn['invoice_number']) ?></strong></div>
        </div>
    </header>

    <div class="meta">
        <div>
            <h4>Deliver To</h4>
            <p><strong><?= escape_html($dn['customer_name']) ?></strong></p>
            <p><?= nl2br(escape_html($dn['customer_address'] ?? '')) ?></p>
        </div>
        <div style="text-align:right;">
            <h4>Delivery Details</h4>
            <p><strong>Date:</strong> <?= format_date($dn['delivery_date']) ?></p>
            <p><strong>Driver:</strong> <?= escape_html($dn['driver_name'] ?? '—') ?></p>
            <p><strong>Vehicle:</strong> <?= escape_html($dn['vehicle_number'] ?? '—') ?></p>
        </div>
    </div>

    <table>
        <thead><tr><th>#</th><th>Item Code</th><th>Description</th><th style="text-align:right;">Qty Delivered</th></tr></thead>
        <tbody>
            <?php foreach ($dn_lines as $i => $l): ?>
            <tr>
                <td><?= $i+1 ?></td>
                <td><?= escape_html($l['item_code']) ?></td>
                <td><?= escape_html($l['item_name']) ?></td>
                <td style="text-align:right;font-weight:bold;"><?= number_format($l['quantity_delivered'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($dn['notes']): ?>
    <p style="background:#f0fdf4;border-left:4px solid #059669;padding:10px;margin-bottom:20px;">
        <strong>Notes:</strong> <?= nl2br(escape_html($dn['notes'])) ?>
    </p>
    <?php endif; ?>

    <div class="sig">
        <div class="sig-box">Delivered By<br><br><?= escape_html($dn['driver_name'] ?? '________________') ?></div>
        <div class="sig-box">Received By (Customer Signature)<br><br>________________</div>
    </div>

    <footer><p>Generated <?= date('d M Y H:i') ?> | MJR Group ERP | Invoice Ref: <?= escape_html($dn['invoice_number']) ?></p></footer>
</div>
<div class="no-print" style="text-align:center;margin:24px;">
    <button onclick="window.print()" style="background:#059669;color:#fff;border:none;padding:10px 30px;border-radius:6px;font-size:15px;cursor:pointer;">🖨 Print Packing Slip</button>
    <a href="view_delivery.php?id=<?= $dn['delivery_schedule_id'] ?>" style="margin-left:12px;color:#555;text-decoration:none;">← Back</a>
</div>
</body>
</html>
