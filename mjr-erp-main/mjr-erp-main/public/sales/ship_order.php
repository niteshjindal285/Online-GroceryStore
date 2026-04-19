<?php
/**
 * Ship Order - Detailed item-level delivery entry
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/delivery_service.php';

require_login();

$invoice_id = get_param('invoice_id');
if (!$invoice_id) {
    set_flash('Invoice ID is required.', 'error');
    redirect('delivery_schedule.php');
}

// Get invoice details
$invoice = db_fetch("
    SELECT i.*, so.id AS order_id, so.order_number, so.location_id, c.name as customer_name, c.customer_code
    FROM invoices i
    LEFT JOIN sales_orders so ON i.so_id = so.id
    JOIN customers c ON i.customer_id = c.id
    WHERE i.id = ? AND i.company_id = ?
", [$invoice_id, $_SESSION['company_id']]);

if (!$invoice) {
    set_flash('Invoice not found.', 'error');
    redirect('delivery_schedule.php');
}

// Handle Form Submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $data = [
            'invoice_id' => $invoice_id,
            'order_id' => $invoice['order_id'] ?? null,
            'courier_name' => post('courier_name'),
            'tracking_number' => post('tracking_number'),
            'notes' => post('notes'),
            'location_id' => intval(post('location_id'))
        ];
        
        $post_items = $_POST['items'] ?? [];
        $selected_items = $_POST['item_select'] ?? [];
        $delivery_items = [];
        
        foreach ($post_items as $line_id => $qty) {
            // Only process if the item was selected/checked
            if (!isset($selected_items[$line_id])) continue;
            
            $qty = floatval($qty);
            if ($qty > 0) {
                // Get item_id for this line
                $line = db_fetch("SELECT item_id FROM sales_order_lines WHERE id = ?", [$line_id]);
                if ($line) {
                    $delivery_items[] = [
                        'line_id' => $line_id,
                        'item_id' => $line['item_id'],
                        'qty'     => $qty
                    ];
                }
            }
        }
        
        if (empty($delivery_items)) {
            set_flash('Please enter quantities for at least one item.', 'warning');
        } else {
            $delivery_id = delivery_create($data, $delivery_items);
            if ($delivery_id) {
                set_flash('Delivery recorded successfully!', 'success');
                redirect('delivery_schedule.php');
            } else {
                set_flash('Failed to record delivery. Please check logs.', 'error');
            }
        }
    }
}

// Get fulfillment items
$items = delivery_get_fulfillment($invoice_id, 'invoice');

$page_title = 'Deliver Now - ' . $invoice['invoice_number'];
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .card { background-color: #1a1d21; border: 1px solid rgba(255,255,255,0.05); }
    .card-header { background-color: #212529 !important; border-bottom: 1px solid rgba(255,255,255,0.1); }
    .table-premium thead th { 
        background-color: #fff !important; 
        color: #1a1d21 !important; 
        font-weight: 800; 
        text-transform: none;
        border: none;
        padding: 12px 15px;
    }
    .table-premium tbody tr { background-color: #dcfce7 !important; color: #1a1d21 !important; border-bottom: 1px solid rgba(0,0,0,0.05); }
    .table-premium tbody tr.fully-delivered { opacity: 0.8; }
    
    .item-code-tag { color: #d63384; font-size: 0.75rem; font-weight: bold; }
    .qty-big { font-size: 1.1rem; font-weight: 800; color: #198754; }
    
    .date-pill {
        background: rgba(0,0,0,0.2);
        color: #6c757d;
        padding: 2px 12px;
        border-radius: 10px;
        font-size: 0.7rem;
        display: inline-block;
        margin-top: 4px;
    }
    .date-pill strong { color: #000; }
    
    .btn-delivered-status {
        background-color: #a3cfbb;
        color: #0f5132;
        border: 1px solid #0f5132;
        border-radius: 8px;
        padding: 4px 12px;
        font-weight: bold;
        font-size: 0.85rem;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    
    .delivery-input-premium {
        background-color: #fff !important;
        border: 1px solid #ced4da !important;
        color: #000 !important;
        font-weight: bold;
        text-align: center;
    }
</style>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="delivery_schedule.php" class="text-info text-decoration-none">Delivery Schedule</a></li>
                    <li class="breadcrumb-item active text-white opacity-50">Deliver Items</li>
                </ol>
            </nav>
            <h2 class="text-white fw-bold"><i class="fas fa-shipping-fast me-2 text-warning"></i>Deliver Now: <?= escape_html($invoice['invoice_number']) ?></h2>
        </div>
        <a href="delivery_schedule.php" class="btn btn-outline-light btn-sm px-3">
            <i class="fas fa-times me-2"></i>Cancel
        </a>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div class="row">
            <div class="col-md-9">
                <!-- Items Card -->
                <div class="card shadow-lg mb-4">
                    <div class="card-header py-3">
                        <h5 class="mb-0 text-white fw-bold">Items to Deliver</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-premium align-middle mb-0" id="deliveryGrid">
                                <thead>
                                    <tr>
                                        <th class="ps-4" style="width: 40px;"><input type="checkbox" checked class="form-check-input"></th>
                                        <th>Item</th>
                                        <th class="text-center">Invoiced</th>
                                        <th class="text-center">Delivered</th>
                                        <th class="text-center">Remaining</th>
                                        <th class="text-center" style="width: 180px;">Deliver Now</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr class="<?= $item['balance'] <= 0 ? 'fully-delivered' : '' ?>">
                                            <td class="ps-4">
                                                <input type="checkbox" name="item_select[<?= $item['line_id'] ?>]" checked class="form-check-input">
                                            </td>
                                            <td class="ps-1">
                                                <div class="fw-bold text-white fs-5" style="filter: drop-shadow(0 0 1px #000); -webkit-text-stroke: 0.2px #fff;"><?= escape_html($item['item_name']) ?></div>
                                                <div class="item-code-tag"><?= strtoupper(escape_html($item['item_code'])) ?></div>
                                            </td>
                                            <td class="text-center fw-bold fs-5"><?= number_format($item['ordered_qty'], 2) ?></td>
                                            <td class="text-center">
                                                <div class="qty-big"><?= number_format($item['delivered_qty'], 2) ?></div>
                                                <?php if ($item['delivered_qty'] > 0): ?>
                                                    <div class="date-pill">27 Mar 2026: <strong><?= number_format($item['delivered_qty'], 1) ?></strong></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center text-info fw-bold fs-5 item-balance" data-balance="<?= $item['balance'] ?>">
                                                <?= number_format($item['balance'], 2) ?>
                                            </td>
                                            <td class="pe-4 text-center">
                                                <?php if ($item['balance'] <= 0): ?>
                                                    <div class="btn-delivered-status">
                                                        <i class="fas fa-check-circle"></i> Delivered
                                                    </div>
                                                <?php else: ?>
                                                    <input type="number" 
                                                           name="items[<?= $item['line_id'] ?>]" 
                                                           class="form-control form-control-sm delivery-input-premium delivery-input" 
                                                           value="<?= $item['balance'] ?>" 
                                                           min="0" 
                                                           max="<?= $item['balance'] ?>" 
                                                           step="1">
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Delivery Info Card -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Delivery Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-bold">CUSTOMER</label>
                            <div class="p-2 bg-light rounded">
                                <strong><?= escape_html($invoice['customer_name']) ?></strong><br>
                                <small class="text-muted"><?= escape_html($invoice['customer_code']) ?></small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="location_id" class="form-label text-muted small fw-bold">WAREHOUSE / LOCATION</label>
                            <select class="form-select" id="location_id" name="location_id" required>
                                <?php
                                $locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ?", [$_SESSION['company_id']]);
                                foreach ($locations as $loc) {
                                    $selected = ($invoice['location_id'] ?? null) == $loc['id'] ? 'selected' : '';
                                    echo "<option value='{$loc['id']}' {$selected}>{$loc['name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="courier_name" class="form-label text-muted small fw-bold">COURIER / DRIVER</label>
                            <input type="text" class="form-control" id="courier_name" name="courier_name" placeholder="Name of driver or company">
                        </div>

                        <div class="mb-3">
                            <label for="tracking_number" class="form-label text-muted small fw-bold">TRACKING / VEHICLE #</label>
                            <input type="text" class="form-control" id="tracking_number" name="tracking_number" placeholder="Reference number">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label text-muted small fw-bold">DELIVERY NOTES</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any special instructions..."></textarea>
                        </div>
                        
                        <hr>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg fw-bold">
                                <i class="fas fa-check-circle me-2"></i>Confirm Shipment
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Summary -->
                <div class="alert alert-info border-0 shadow-sm">
                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Automatic Calculation</h6>
                    <p class="mb-0 small">As you type in the "Deliver Now" column, the system calculates the remaining balance automatically.</p>
                </div>
            </div>
        </div>
    </form>
</div>

<?php 
$additional_scripts = "
<script>
$(document).ready(function() {
    $('.delivery-input').on('input', function() {
        const row = $(this).closest('tr');
        const inputVal = parseFloat($(this).val()) || 0;
        const balanceCell = row.find('.item-balance');
        const originalBalance = parseFloat(balanceCell.data('balance'));
        
        // Calculate new balance
        const newBalance = originalBalance - inputVal;
        
        balanceCell.text(newBalance.toLocaleString());
        
        if (newBalance < 0) {
            balanceCell.addClass('text-danger').removeClass('text-success');
            $(this).addClass('is-invalid');
        } else if (newBalance === 0) {
            balanceCell.removeClass('text-danger').addClass('text-success');
            $(this).removeClass('is-invalid');
        } else {
            balanceCell.addClass('text-danger').removeClass('text-success');
            $(this).removeClass('is-invalid');
        }
    });

    $('form').on('submit', function(e) {
        let hasError = false;
        $('.delivery-input').each(function() {
            const row = $(this).closest('tr');
            const balanceCell = row.find('.item-balance');
            const currentBalance = parseFloat(balanceCell.text().replace(/,/g, ''));
            
            if (currentBalance < 0) {
                hasError = true;
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('Cannot deliver more than the remaining balance for an item.');
        }
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php'; 
?>
