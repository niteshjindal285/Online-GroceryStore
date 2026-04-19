<?php
/**
 * Delivery Step 3 — Review and Final Confirmation
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

// Guard: must have pending delivery in session
if (empty($_SESSION['pending_delivery'])) {
    set_flash('Please start from the delivery screen.', 'error');
    redirect('index.php');
}

$pd = $_SESSION['pending_delivery'];
$delivery_id = intval($pd['delivery_id']);
$invoice_id  = intval($pd['invoice_id']);

$sched = db_fetch("SELECT * FROM delivery_schedule WHERE id=?", [$delivery_id]);
$inv   = db_fetch("SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=?", [$invoice_id]);
$inv_lines = db_fetch_all("SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il JOIN inventory_items ii ON ii.id=il.item_id WHERE il.invoice_id=?", [$invoice_id]);

if (!$sched || !$inv) {
    set_flash('Delivery record not found.', 'error');
    redirect('index.php');
}

$page_title = 'Review Delivery — ' . $inv['invoice_number'];

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid CSRF token.', 'error');
    } else {
        // BUILD NOTES
        $dn_notes  = "Pickup: " . ($pd['pickup_name'] ? $pd['pickup_name'] . " | " : "") . $pd['pickup_phone'] . "\n" . $pd['pickup_address'];
        $dn_notes .= "\n\nDeliver To: {$pd['buyer_name']} | {$pd['buyer_mobile']}\n{$pd['buyer_address']}";
        if ($pd['buyer_landmark']) $dn_notes .= " (Near: {$pd['buyer_landmark']})";
        $dn_notes .= "\n{$pd['buyer_city']}, {$pd['buyer_state']}" . ($pd['buyer_pincode'] ? " - {$pd['buyer_pincode']}" : "");

        if (!$pd['billing_same']) {
            $dn_notes .= "\n\nBilling: {$pd['bill_name']} | {$pd['bill_phone']}\n{$pd['bill_address']}, {$pd['bill_city']}, {$pd['bill_state']}";
        }

        // Package Details string
        if ($pd['pack_dead_weight'] > 0 || $pd['pack_length'] > 0) {
            $dn_notes .= "\n\nPackage Details:";
            $dn_notes .= "\n- Dead Weight: {$pd['pack_dead_weight']} kg";
            if ($pd['pack_length'] > 0) {
                $dn_notes .= "\n- Dimensions: {$pd['pack_length']} x {$pd['pack_breadth']} x {$pd['pack_height']} cm";
                $dn_notes .= "\n- Volumetric Weight: {$pd['vol_weight']} kg";
            }
            $dn_notes .= "\n- Applicable Weight: {$pd['applicable_wt']} kg";
            if ($pd['pack_track']) $dn_notes .= "\n- Tracking/Carton: {$pd['pack_track']}";
        }

        // Generate delivery note number
        $last_dn = db_fetch("SELECT delivery_number FROM delivery_notes ORDER BY id DESC LIMIT 1");
        $next_dn = 1;
        if ($last_dn && preg_match('/DN(\d+)/', $last_dn['delivery_number'], $m)) $next_dn = intval($m[1]) + 1;
        $dn_number = 'DN' . str_pad($next_dn, 5, '0', STR_PAD_LEFT);

        db_begin_transaction();
        try {
            $dn_id = db_insert("INSERT INTO delivery_notes
                (delivery_schedule_id, delivery_number, delivery_date, driver_name, vehicle_number, notes, created_by)
                VALUES (?,?,?,?,?,?,?)",
                [$delivery_id, $dn_number, $pd['delivery_date'], $pd['driver_name'], $pd['vehicle_number'], $dn_notes, $_SESSION['user_id']]);

            $all_delivered = true;
            foreach ($pd['deliver_qtys'] as $line_id => $qty_to_deliver) {
                // Find matching invoice line
                $line = null;
                foreach ($inv_lines as $l) {
                    if ($l['id'] == $line_id) { $line = $l; break; }
                }
                if (!$line) continue;

                // Check remaining
                $delivered_res = db_fetch("
                    SELECT SUM(dnl.quantity_delivered) AS total_delivered
                    FROM delivery_note_lines dnl
                    JOIN delivery_notes dn ON dn.id = dnl.delivery_note_id
                    WHERE dn.delivery_schedule_id = ? AND dnl.invoice_line_id = ?
                ", [$delivery_id, $line_id]);
                $already   = floatval($delivered_res['total_delivered'] ?? 0);
                $remaining = floatval($line['quantity']) - $already;
                
                if ($qty_to_deliver > $remaining) $qty_to_deliver = $remaining;
                if ($qty_to_deliver <= 0) continue;

                db_insert("INSERT INTO delivery_note_lines (delivery_note_id, invoice_line_id, item_id, quantity_delivered)
                    VALUES (?,?,?,?)", [$dn_id, $line_id, $line['item_id'], $qty_to_deliver]);

                if (($already + $qty_to_deliver) < floatval($line['quantity'])) $all_delivered = false;
            }

            $new_status = $all_delivered ? 'delivered' : 'partial';
            db_query("UPDATE delivery_schedule SET status=?, delivered_date=? WHERE id=?",
                [$new_status, $all_delivered ? $pd['delivery_date'] : null, $delivery_id]);

            db_commit();
            unset($_SESSION['pending_delivery']);
            set_flash("Delivery note $dn_number created successfully.", 'success');
            redirect("view_delivery.php?id=$delivery_id");
        } catch (Exception $e) {
            db_rollback();
            set_flash("Error creating delivery note: " . $e->getMessage(), 'error');
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<style>
    .card { background-color: var(--bs-tertiary-bg, #f8f9fa) !important; border: 1px solid var(--bs-border-color, #dee2e6) !important; color: var(--bs-body-color, #212529) !important; }
    .card-header { background-color: var(--bs-secondary-bg, #e9ecef) !important; border-bottom: 1px solid var(--bs-border-color, #dee2e6) !important; color: var(--bs-body-color, #212529) !important; }
    .table-premium { background: transparent !important; }
    .table-premium thead th { 
        background-color: var(--bs-dark, #212529) !important; 
        color: #fff !important; 
        font-weight: 800; 
        border: none;
        padding: 10px 15px;
    }
    .table-premium tbody tr { 
        background-color: var(--bs-body-bg, #fff) !important; 
        color: var(--bs-body-color, #212529) !important; 
        border-bottom: 1px solid var(--bs-border-color, #dee2e6); 
    }
    .address-header-primary { background: linear-gradient(45deg, #0d6efd, #0b5ed7) !important; color: #fff !important; }
    .address-header-success { background: linear-gradient(45deg, #198754, #157347) !important; color: #fff !important; }
    .address-header-warning { background: linear-gradient(45deg, #ffc107, #e0a800) !important; color: #000 !important; }
    .addr-card-body { color: var(--bs-body-color, #212529) !important; }
    .addr-card-body .text-muted-addr { color: var(--bs-secondary-color, #6c757d) !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold"><i class="fas fa-clipboard-check me-2 text-info"></i>Review Delivery</h2>
        <p class="text-muted mb-0">Invoice: <strong class="text-info"><?= escape_html($inv['invoice_number']) ?></strong> &mdash; <?= escape_html($inv['customer_name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="customer_details.php" class="btn btn-outline-light btn-sm px-3"><i class="fas fa-edit me-1"></i> Address</a>
        <a href="add_delivery.php?delivery_id=<?= $delivery_id ?>" class="btn btn-outline-light btn-sm px-3"><i class="fas fa-edit me-1"></i> Items</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-8">
        <!-- Items Summary -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header py-3"><h5 class="mb-0 fw-bold">Items to be Delivered</h5></div>
            <div class="card-body p-0">
                <table class="table table-premium mb-0">
                    <thead>
                        <tr><th class="ps-4">Item Particulars</th><th class="text-end pe-4">Qty to Deliver</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pd['deliver_qtys'] as $lid => $qty): 
                            $item_info = "";
                            foreach($inv_lines as $il) if($il['id'] == $lid) $item_info = strtoupper($il['item_code']) . " - " . $il['item_name'];
                        ?>
                        <tr>
                            <td class="ps-4"><?= escape_html($item_info) ?></td>
                            <td class="text-end pe-4 fw-bold text-info fs-5"><?= number_format($qty, 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header address-header-primary"><h6 class="mb-0"><i class="fas fa-store me-2"></i>Pickup From</h6></div>
                    <div class="card-body addr-card-body">
                        <strong class="text-primary"><?= escape_html($pd['pickup_name']) ?></strong><br>
                        <span class="text-muted-addr"><?= nl2br(escape_html($pd['pickup_address'])) ?></span><br>
                        <div class="mt-2"><i class="fas fa-phone-alt me-1 text-muted"></i> <?= escape_html($pd['pickup_phone']) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header address-header-success"><h6 class="mb-0"><i class="fas fa-shipping-fast me-2"></i>Deliver To</h6></div>
                    <div class="card-body addr-card-body">
                        <strong class="text-success"><?= escape_html($pd['buyer_name']) ?></strong><br>
                        <span class="text-muted-addr"><?= nl2br(escape_html($pd['buyer_address'])) ?></span><br>
                        <?php if($pd['buyer_landmark']): ?><small class="text-muted italic">Landmark: <?= escape_html($pd['buyer_landmark']) ?></small><br><?php endif; ?>
                        <div class="fw-bold mt-1"><?= escape_html($pd['buyer_city']) ?>, <?= escape_html($pd['buyer_state']) ?> <?= escape_html($pd['buyer_pincode']) ?></div>
                        <div class="mt-1"><i class="fas fa-mobile-alt me-1 text-muted"></i> <?= escape_html($pd['buyer_mobile']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!$pd['billing_same']): ?>
        <div class="card mt-4 shadow-sm border-0">
            <div class="card-header address-header-warning"><h6 class="mb-0"><i class="fas fa-file-invoice me-2"></i>Billing Address</h6></div>
            <div class="card-body addr-card-body">
                <strong class="text-warning"><?= escape_html($pd['bill_name']) ?></strong><br>
                <div class="text-muted-addr"><?= nl2br(escape_html($pd['bill_address'])) ?></div>
                <div class="fw-bold mt-1"><?= escape_html($pd['bill_city']) ?>, <?= escape_html($pd['bill_state']) ?></div>
                <div class="mt-1"><i class="fas fa-phone-alt me-1 text-muted"></i> <?= escape_html($pd['bill_phone']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <!-- Package and Logistics -->
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0">Logistics & Packing</h5></div>
            <div class="card-body py-2">
                <table class="table table-sm table-borderless mb-0">
                    <tr><td>Delivery Date</td><td class="text-end fw-bold"><?= date('d M Y', strtotime($pd['delivery_date'])) ?></td></tr>
                    <tr><td>Dead Weight</td><td class="text-end"><?= number_format($pd['pack_dead_weight'], 2) ?> kg</td></tr>
                    <tr><td>Dimensions</td><td class="text-end"><?= $pd['pack_length'] ?>x<?= $pd['pack_breadth'] ?>x<?= $pd['pack_height'] ?> cm</td></tr>
                    <tr class="table-info"><td>Applicable Weight</td><td class="text-end fw-bold"><?= number_format($pd['applicable_wt'], 2) ?> kg</td></tr>
                    <tr><td>Tracking #</td><td class="text-end text-muted small"><?= escape_html($pd['pack_track'] ?: 'N/A') ?></td></tr>
                    <hr class="my-1">
                    <tr><td>Driver Name</td><td class="text-end"><?= escape_html($pd['driver_name'] ?: 'N/A') ?></td></tr>
                    <tr><td>Vehicle Num</td><td class="text-end"><?= escape_html($pd['vehicle_number'] ?: 'N/A') ?></td></tr>
                </table>
            </div>
        </div>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-success btn-lg py-3">
                    <i class="fas fa-check-circle me-2"></i>Confirm & Process Delivery
                </button>
                <a href="customer_details.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Back
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
