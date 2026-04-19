<?php
/**
 * Delivery Step 2 — Customer / Shipping Details
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

$pd          = $_SESSION['pending_delivery'];
$delivery_id = intval($pd['delivery_id']);
$invoice_id  = intval($pd['invoice_id']);

$sched = db_fetch("SELECT * FROM delivery_schedule WHERE id=?", [$delivery_id]);
$inv   = db_fetch("SELECT i.*, c.name AS customer_name, c.address AS customer_address, c.phone AS customer_phone,
                    l.name AS location_name, l.address AS location_address
    FROM invoices i 
    JOIN customers c ON c.id=i.customer_id 
    LEFT JOIN sales_orders so ON so.id = i.so_id
    LEFT JOIN locations l ON l.id = so.location_id
    WHERE i.id=?", [$invoice_id]);
$inv_lines = db_fetch_all("SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il JOIN inventory_items ii ON ii.id=il.item_id WHERE il.invoice_id=?", [$invoice_id]);

if (!$sched || !$inv) {
    set_flash('Delivery record not found.', 'error');
    redirect('index.php');
}

$page_title = 'Delivery Details — ' . $inv['invoice_number'];
$errors = [];
$pickup_name = '';
$pickup_phone = '';
$pickup_address = '';

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        // Pickup address
        $pickup_name    = trim(post('pickup_name', ''));
        $pickup_phone   = trim(post('pickup_phone', ''));
        $pickup_address = trim(post('pickup_address', ''));

        // Delivery (buyer) details
        $buyer_mobile   = trim(post('buyer_mobile', ''));
        $buyer_name     = trim(post('buyer_name', ''));
        $buyer_address  = trim(post('buyer_address', ''));
        $buyer_landmark = trim(post('buyer_landmark', ''));
        $buyer_pincode  = trim(post('buyer_pincode', ''));
        $buyer_city     = trim(post('buyer_city', ''));
        $buyer_state    = trim(post('buyer_state', ''));

        // Billing same as delivery?
        $billing_same   = post('billing_same') ? 1 : 0;

        // Billing details (if different)
        $bill_name      = $billing_same ? $buyer_name    : trim(post('bill_name', ''));
        $bill_phone     = $billing_same ? $buyer_mobile  : trim(post('bill_phone', ''));
        $bill_address   = $billing_same ? $buyer_address : trim(post('bill_address', ''));
        $bill_city      = $billing_same ? $buyer_city    : trim(post('bill_city', ''));
        $bill_state     = $billing_same ? $buyer_state   : trim(post('bill_state', ''));

        // Driver / vehicle
        $driver_name  = trim(post('driver_name', ''));
        $vehicle_num  = trim(post('vehicle_number', ''));

        if (!$buyer_name)    $errors[] = 'Buyer Full Name is required.';
        if (!$buyer_mobile)  $errors[] = 'Buyer Mobile Number is required.';
        if (!$buyer_address) $errors[] = 'Buyer Address is required.';

        if (empty($errors)) {
            // Update session with address details
            $_SESSION['pending_delivery'] = array_merge($_SESSION['pending_delivery'], [
                'pickup_name'    => $pickup_name,
                'pickup_phone'   => $pickup_phone,
                'pickup_address' => $pickup_address,
                'buyer_mobile'   => $buyer_mobile,
                'buyer_name'     => $buyer_name,
                'buyer_address'  => $buyer_address,
                'buyer_landmark' => $buyer_landmark,
                'buyer_pincode'  => $buyer_pincode,
                'buyer_city'     => $buyer_city,
                'buyer_state'    => $buyer_state,
                'billing_same'   => $billing_same,
                'bill_name'      => $bill_name,
                'bill_phone'     => $bill_phone,
                'bill_address'   => $bill_address,
                'bill_city'      => $bill_city,
                'bill_state'     => $bill_state,
                'driver_name'    => $driver_name,
                'vehicle_number' => $vehicle_num
            ]);

            redirect("review_delivery.php");
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<style>
    .card { background-color: var(--bs-tertiary-bg, #f8f9fa) !important; border: 1px solid var(--bs-border-color, #dee2e6) !important; color: var(--bs-body-color, #212529) !important; }
    .card-body h5 { color: var(--bs-body-color, #212529) !important; letter-spacing: 0.5px; }
    .form-control, .form-select { background-color: var(--bs-body-bg, #fff) !important; border: 1px solid var(--bs-border-color, #dee2e6) !important; color: var(--bs-body-color, #212529) !important; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd !important; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important; }
    .input-group-text { background-color: var(--bs-secondary-bg, #e9ecef) !important; border: 1px solid var(--bs-border-color, #dee2e6) !important; color: var(--bs-body-color, #212529) !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="fw-bold"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Delivery Details</h2>
        <p class="text-muted mb-0">Invoice: <strong class="text-info"><?= escape_html($inv['invoice_number']) ?></strong> &mdash; <?= escape_html($inv['customer_name']) ?></p>
    </div>
    <a href="add_delivery.php?delivery_id=<?= $delivery_id ?>" class="btn btn-outline-light btn-sm px-3"><i class="fas fa-arrow-left me-1"></i>Back to Items</a>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

    <div class="row g-4">
        <div class="col-12">

            <!-- Pickup Address -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><i class="fas fa-store me-2 text-primary"></i>Pickup Address</h5>
                    <p class="text-muted small mb-3">Your warehouse or dispatch location for this shipment.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Contact Name</label>
                            <input type="text" name="pickup_name" class="form-control" placeholder="e.g. MJR Warehouse"
                                value="<?= escape_html($pickup_name ?: $inv['location_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="pickup_phone" class="form-control" placeholder="+679 XXX XXXX"
                                value="<?= escape_html($pickup_phone ?: '') ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Full Pickup Address</label>
                            <input type="text" name="pickup_address" class="form-control" placeholder="Street, City, State"
                                value="<?= escape_html($pickup_address ?: $inv['location_address'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Details -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><i class="fas fa-shipping-fast me-2 text-success"></i>Delivery Details</h5>
                    <p class="text-muted small mb-3">Enter the delivery details of your buyer for whom you are making this order.</p>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Mobile Number <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">+679</span>
                                <input type="text" name="buyer_mobile" class="form-control" placeholder="Enter mobile number"
                                    value="<?= escape_html($inv['customer_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="buyer_name" class="form-control" placeholder="Enter Full Name"
                                value="<?= escape_html($inv['customer_name']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Complete Address <span class="text-danger">*</span></label>
                            <input type="text" name="buyer_address" class="form-control" placeholder="Enter Buyer's full address"
                                value="<?= escape_html($inv['customer_address'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Landmark <span class="text-muted small">(Optional)</span></label>
                            <input type="text" name="buyer_landmark" class="form-control" placeholder="Enter any nearby landmark">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="buyer_pincode" class="form-control" placeholder="Enter pincode">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">City</label>
                            <input type="text" name="buyer_city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="buyer_state" class="form-control" placeholder="State">
                        </div>
                    </div>

                    <!-- Billing same as delivery -->
                    <div class="form-check mt-4">
                        <input type="checkbox" name="billing_same" id="billingSame" class="form-check-input" value="1" checked onchange="toggleBilling(this)">
                        <label class="form-check-label fw-semibold" for="billingSame">Billing Details are same as Delivery Details</label>
                    </div>
                </div>
            </div>

            <!-- Billing Details (hidden by default) -->
            <div class="card mb-4" id="billingCard" style="display:none;">
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><i class="fas fa-file-invoice me-2 text-warning"></i>Billing Details</h5>
                    <p class="text-muted small mb-3">Enter billing address if different from delivery address.</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Billing Name</label>
                            <input type="text" name="bill_name" class="form-control" placeholder="Full Name">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="bill_phone" class="form-control" placeholder="Mobile Number">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Billing Address</label>
                            <input type="text" name="bill_address" class="form-control" placeholder="Full billing address">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="bill_city" class="form-control" placeholder="City">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" name="bill_state" class="form-control" placeholder="State">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Driver & Vehicle (optional) -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-1"><i class="fas fa-truck me-2 text-secondary"></i>Driver & Vehicle <span class="text-muted small fw-normal">(Optional)</span></h5>
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Driver Name</label>
                            <input type="text" name="driver_name" class="form-control" placeholder="Optional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vehicle #</label>
                            <input type="text" name="vehicle_number" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary banner -->
            <div class="alert alert-info d-flex gap-3 align-items-start">
                <i class="fas fa-info-circle fa-lg mt-1"></i>
                <div>
                    <strong>Delivery Date:</strong> <?= date('d M Y', strtotime($pd['delivery_date'])) ?> &nbsp;|&nbsp;
                    <strong>Package Wt:</strong> <?= $pd['applicable_wt'] ?> kg &nbsp;|&nbsp;
                    <strong>Items:</strong> <?= count($pd['deliver_qtys']) ?> line(s)
                </div>
            </div>

            <div class="d-flex gap-3 justify-content-end">
                <a href="add_delivery.php?delivery_id=<?= $delivery_id ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    Continue <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>

        </div>
    </div>
</form>

<script>
function toggleBilling(cb) {
    document.getElementById('billingCard').style.display = cb.checked ? 'none' : 'block';
}
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
