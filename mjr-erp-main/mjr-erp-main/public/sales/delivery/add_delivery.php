<?php
/**
 * Process Delivery (Full or Partial)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$delivery_id = intval(get('delivery_id', 0));
$invoice_id  = intval(get('invoice_id', 0));

// Resolve from either param
if ($delivery_id) {
    $sched = db_fetch("SELECT * FROM delivery_schedule WHERE id=?", [$delivery_id]);
    if ($sched) $invoice_id = $sched['invoice_id'];
} elseif ($invoice_id) {
    $sched = db_fetch("SELECT * FROM delivery_schedule WHERE invoice_id=?", [$invoice_id]);
}

if (!$sched) { set_flash('Delivery record not found.', 'error'); redirect('index.php'); }
$delivery_id = $sched['id'];
$invoice_id  = $sched['invoice_id'];

$inv = db_fetch("SELECT i.*, c.name AS customer_name FROM invoices i JOIN customers c ON c.id=i.customer_id WHERE i.id=?", [$invoice_id]);
$inv_lines = db_fetch_all("SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il JOIN inventory_items ii ON ii.id=il.item_id WHERE il.invoice_id=?", [$invoice_id]);

// Calculate already delivered quantity per invoice_line and fetch history
$delivered_map = [];
$delivery_history = [];
$dn_lines = db_fetch_all("
    SELECT dnl.invoice_line_id, dn.delivery_number, dn.delivery_date, dnl.quantity_delivered
    FROM delivery_note_lines dnl
    JOIN delivery_notes dn ON dn.id = dnl.delivery_note_id
    WHERE dn.delivery_schedule_id = ?
    ORDER BY dn.delivery_date DESC
", [$delivery_id]);

foreach ($dn_lines as $dl) {
    $lid = $dl['invoice_line_id'];
    $delivered_map[$lid] = ($delivered_map[$lid] ?? 0) + $dl['quantity_delivered'];
    $delivery_history[$lid][] = $dl;
}

$page_title = 'Process Delivery — ' . $inv['invoice_number'];
$errors = [];

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) { $errors[] = 'Invalid token.'; }
    else {
        $deliver_qtys  = post('deliver_qty', []);
        $delivery_date = post('delivery_date') ?: date('Y-m-d');

        // Packing data
        $pack_dead_weight = floatval(post('pack_dead_weight', 0));
        $pack_length      = floatval(post('pack_length', 0));
        $pack_breadth     = floatval(post('pack_breadth', 0));
        $pack_height      = floatval(post('pack_height', 0));
        $pack_track       = trim(post('pack_track', ''));
        $vol_weight       = round(($pack_length * $pack_breadth * $pack_height) / 5000, 4);
        $applicable_wt    = max($pack_dead_weight, $vol_weight, 0.5);

        // Filter out zero-qty lines and unselected items
        $selected_items = post('item_select', []);
        $valid_qtys = [];
        foreach ($deliver_qtys as $lid => $q) {
            if (isset($selected_items[$lid]) && floatval($q) > 0) {
                $valid_qtys[$lid] = floatval($q);
            }
        }

        if (empty($valid_qtys)) $errors[] = "Please select at least one item with a quantity greater than zero.";

        if (empty($errors)) {
            // Store everything in session and proceed to customer details step
            $_SESSION['pending_delivery'] = [
                'delivery_id'       => $delivery_id,
                'invoice_id'        => $invoice_id,
                'deliver_qtys'      => $valid_qtys,
                'delivery_date'     => $delivery_date,
                'pack_dead_weight'  => $pack_dead_weight,
                'pack_length'       => $pack_length,
                'pack_breadth'      => $pack_breadth,
                'pack_height'       => $pack_height,
                'pack_track'        => $pack_track,
                'vol_weight'        => $vol_weight,
                'applicable_wt'     => $applicable_wt,
            ];
            redirect("customer_details.php?delivery_id=$delivery_id");
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<style>
    :root {
        --surface-bg: var(--bs-body-bg, #fff);
        --surface-card: var(--bs-tertiary-bg, #f8f9fa);
        --surface-card-header: var(--bs-secondary-bg, #e9ecef);
        --surface-input: var(--bs-body-bg, #fff);
        --text-primary: var(--bs-body-color, #212529);
        --text-muted: var(--bs-secondary-color, #6c757d);
        --border-color: var(--bs-border-color, #dee2e6);
    }
    .card { background-color: var(--surface-card) !important; border: 1px solid var(--border-color) !important; color: var(--text-primary) !important; }
    .card-header { background-color: var(--surface-card-header) !important; border-bottom: 1px solid var(--border-color) !important; color: var(--text-primary) !important; }
    .table-premium { background: transparent !important; }
    .table-premium thead th { 
        background-color: var(--bs-dark, #212529) !important; 
        color: #fff !important; 
        font-weight: 800; 
        border: none;
        padding: 12px 15px;
    }
    .table-premium tbody tr { 
        background-color: var(--surface-bg) !important; 
        color: var(--text-primary) !important; 
        border-bottom: 1px solid var(--border-color); 
        transition: all 0.2s ease;
    }
    .table-premium tbody tr:hover { background-color: var(--surface-card) !important; }
    .table-premium tbody tr.fully-delivered { opacity: 0.7; border-left: 4px solid #198754; }
    
    .item-title { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
    .item-subtitle { color: #d63384; font-weight: bold; font-size: 0.75rem; letter-spacing: 0.5px; }
    
    .btn-deliver-now-pill {
        background-color: rgba(25, 135, 84, 0.15);
        color: #198754;
        border: 1px solid #198754;
        border-radius: 50rem;
        padding: 4px 15px;
        font-weight: bold;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .pack-input { background-color: var(--surface-input) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
    .pack-banner { background: var(--surface-card) !important; border: 1px solid var(--border-color) !important; }
    .igt { background-color: var(--surface-card-header) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold"><i class="fas fa-truck me-2 text-warning"></i>Process Delivery</h2>
        <p class="text-muted mb-0">Invoice: <strong class="text-info"><?= escape_html($inv['invoice_number']) ?></strong> &mdash; <?= escape_html($inv['customer_name']) ?></p>
    </div>
    <a href="index.php" class="btn btn-outline-light btn-sm px-3"><i class="fas fa-arrow-left me-1"></i>Back to Schedule</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger border-0 shadow-sm"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
    <div class="row g-4">
        <div class="col-md-8">
            <div class="card mb-4 shadow-lg border-0">
                <div class="card-header py-3"><h5 class="mb-0 fw-bold">Items to Deliver</h5></div>
                <div class="card-body p-0">
                    <table class="table table-premium align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4" style="width: 50px;"><input type="checkbox" class="form-check-input" id="selectAllItems" checked onclick="toggleAllItems(this)"></th>
                                <th>Item Particulars</th>
                                <th class="text-center">Invoiced</th>
                                <th class="text-center">Delivered</th>
                                <th class="text-center">Remaining</th>
                                <th class="text-end pe-4" style="width: 180px;">Action / Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inv_lines as $line):
                                $already    = floatval($delivered_map[$line['id']] ?? 0);
                                $remaining  = floatval($line['quantity']) - $already;
                            ?>
                            <tr class="<?= $remaining <= 0 ? 'fully-delivered' : '' ?>">
                                <td class="ps-4 text-center">
                                    <input type="checkbox" name="item_select[<?= $line['id'] ?>]" class="form-check-input row-select" 
                                        <?= $remaining > 0 ? 'checked' : 'disabled' ?> 
                                        onchange="toggleRow(this)" value="1">
                                </td>
                                <td>
                                    <div class="item-title"><?= escape_html($line['item_name']) ?></div>
                                    <div class="item-subtitle"><?= strtoupper(escape_html($line['item_code'])) ?></div>
                                </td>
                                <td class="text-center fw-bold fs-5 text-white"><?= number_format($line['quantity'], 2) ?></td>
                                <td class="text-center">
                                    <div class="text-success fw-bold fs-5"><?= number_format($already, 2) ?></div>
                                    <?php if (!empty($delivery_history[$line['id']])): ?>
                                        <div class="mt-1 d-flex flex-column gap-1">
                                            <?php foreach ($delivery_history[$line['id']] as $hist): ?>
                                                <div class="small p-1 rounded" style="font-size: 0.65rem; background: rgba(255,255,255,0.05); color: #888;">
                                                    <span class="text-info"><?= date('d M Y', strtotime($hist['delivery_date'])) ?></span>: <strong><?= number_format($hist['quantity_delivered'], 1) ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="<?= $remaining > 0 ? 'text-info fw-bold fs-5' : 'text-success opacity-50' ?>">
                                        <?= number_format($remaining, 2) ?>
                                    </span>
                                </td>
                                <td class="pe-4">
                                    <?php if ($remaining > 0): ?>
                                        <div class="input-group input-group-sm ms-auto" style="width: 140px;">
                                            <input type="number" name="deliver_qty[<?= $line['id'] ?>]"
                                                class="form-control deliver-qty-input bg-dark text-white border-secondary text-center fw-bold fs-6" 
                                                min="0" max="<?= $remaining ?>" step="0.01" 
                                                value="<?= number_format($remaining, 2, '.', '') ?>" 
                                                data-max="<?= number_format($remaining, 2, '.', '') ?>"
                                                oninput="calcPackaging()">
                                            <span class="input-group-text igt small">pcs</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-end">
                                            <div class="btn-deliver-now-pill">
                                                <i class="fas fa-check-circle"></i> Delivered
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Package Details -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Package Details</h5>
                        <small class="text-muted">Provide the details of the final package that includes all the ordered items packed together.</small>
                    </div>
                    <div class="alert alert-info mb-0 py-1 px-3 d-flex align-items-center gap-2" style="font-size:13px;">
                        <i class="fas fa-lightbulb text-info"></i>
                        <span><strong>Tip:</strong> Add correct values to avoid weight discrepancy</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-top">
                        <!-- Dead Weight -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-1">Dead Weight</label>
                            <div class="input-group">
                                <input type="number" name="pack_dead_weight" id="packDeadWeight" class="form-control pack-input" step="0.01" min="0" value="0.00" oninput="calcPackaging()">
                                <span class="input-group-text igt">kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Physical weight</small>
                        </div>

                        <!-- Package Dimensions -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-1">Dimensions (L × B × H)</label>
                            <div class="d-flex gap-2">
                                <div class="input-group">
                                    <input type="number" name="pack_length" id="packLength" class="form-control pack-input" placeholder="L" oninput="calcPackaging()">
                                    <span class="input-group-text igt small">cm</span>
                                </div>
                                <div class="input-group">
                                    <input type="number" name="pack_breadth" id="packBreadth" class="form-control pack-input" placeholder="B" oninput="calcPackaging()">
                                    <span class="input-group-text igt small">cm</span>
                                </div>
                                <div class="input-group">
                                    <input type="number" name="pack_height" id="packHeight" class="form-control pack-input" placeholder="H" oninput="calcPackaging()">
                                    <span class="input-group-text igt small">cm</span>
                                </div>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Outer dimensions for volumetric calculation</small>
                        </div>

                        <!-- Volumetric Weight -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-uppercase text-muted mb-1">Volumetric <i class="fas fa-info-circle" title="L×B×H ÷ 5000"></i></label>
                            <div class="input-group">
                                <input type="number" name="pack_volumetric_weight" id="packVolWeight" class="form-control pack-input" step="0.0001" readonly value="0">
                                <span class="input-group-text igt">kg</span>
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">Calculated weight</small>
                        </div>
                    </div>

                    <!-- Applicable Weight Banner -->
                    <div class="mt-4 p-3 rounded d-flex align-items-center gap-3 shadow-sm pack-banner">
                        <div class="bg-success bg-opacity-10 p-2 rounded-circle border border-success border-opacity-25">
                            <i class="fas fa-box-open fa-lg text-success"></i>
                        </div>
                        <div>
                            <strong>Applicable Weight: <span id="packApplicableWeight" class="text-success fs-5">0 kg</span></strong><br>
                            <small class="text-muted">Higher of dead weight or volumetric weight, used for freight charges.</small>
                        </div>
                    </div>

                    <!-- Tracking # -->
                    <div class="mt-3">
                        <label class="form-label small text-muted">Tracking / Carton # (Optional)</label>
                        <input type="text" name="pack_track" class="form-control" placeholder="Enter tracking or carton ID...">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card sticky-top" style="top:20px;">
                <div class="card-header"><h5 class="mb-0">Delivery Info</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Delivery Date *</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-arrow-right me-1"></i>Continue
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function toggleRow(cb) {
    const row = cb.closest('tr');
    const qtyInput = row.querySelector('.deliver-qty-input');
    
    if (qtyInput) {
        if (!cb.checked) {
            qtyInput.dataset.oldval = qtyInput.value;
            qtyInput.value = 0;
            qtyInput.readOnly = true;
            qtyInput.style.opacity = '0.3';
            row.style.opacity = '0.5';
        } else {
            qtyInput.value = qtyInput.dataset.oldval || qtyInput.dataset.max;
            qtyInput.readOnly = false;
            qtyInput.style.opacity = '1';
            row.style.opacity = '1';
        }
        calcPackaging();
    }
}

function toggleAllItems(masterCb) {
    document.querySelectorAll('.row-select:not(:disabled)').forEach(cb => {
        cb.checked = masterCb.checked;
        toggleRow(cb);
    });
}

function addPackingRow() {}     // retained for safety
function removePackingRow(btn) {}

function calcPackaging() {
    const dead  = parseFloat(document.getElementById('packDeadWeight')?.value) || 0;
    const l     = parseFloat(document.getElementById('packLength')?.value) || 0;
    const b     = parseFloat(document.getElementById('packBreadth')?.value) || 0;
    const h     = parseFloat(document.getElementById('packHeight')?.value) || 0;

    const vol = parseFloat(((l * b * h) / 5000).toFixed(4));
    const applicable = Math.max(dead, vol, 0.5);

    const volEl = document.getElementById('packVolWeight');
    const appEl = document.getElementById('packApplicableWeight');

    if (volEl) volEl.value = vol;
    if (appEl) appEl.textContent = applicable.toFixed(2) + ' kg';
}

</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>