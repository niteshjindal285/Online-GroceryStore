<?php
/**
 * Sales Order Discount Adjustment UI
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

// Only managers and admins can perform adjustments
$is_authorized = is_admin() || $_SESSION['role'] === 'manager';
if (!$is_authorized) {
    set_flash('Access Denied. Managerial privileges required.', 'error');
    redirect('order_dashboard.php');
}

$order_id = get('id');
if (!$order_id) {
    set_flash('Order ID not provided.', 'error');
    redirect('order_dashboard.php');
}

// Fetch order header
$order = db_fetch("
    SELECT so.*, c.name as customer_name, c.customer_code, c.id as customer_id
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE so.id = ?
", [$order_id]);

if (!$order) {
    set_flash('Order not found.', 'error');
    redirect('order_dashboard.php');
}

// Handle Approval POST
if (is_post() && post('action') === 'approve_discount') {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $line_ids = post('line_id', []);
            $manual_discounts = post('manual_discount_pct', []);
            $new_subtotal = 0;

            db_begin_transaction();

            for ($i = 0; $i < count($line_ids); $i++) {
                $lid = intval($line_ids[$i]);
                $disc_pct = floatval($manual_discounts[$i] ?? 0);

                // Fetch line details for recalculation
                $line = db_fetch("SELECT quantity, unit_price FROM sales_order_lines WHERE id = ?", [$lid]);
                if ($line) {
                    $qty = $line['quantity'];
                    $price = $line['unit_price'];
                    $line_total = ($qty * $price) * (1 - ($disc_pct / 100));
                    
                    db_query("
                        UPDATE sales_order_lines 
                        SET discount_percent = ?, line_total = ? 
                        WHERE id = ?
                    ", [$disc_pct, $line_total, $lid]);
                    
                    $new_subtotal += $line_total;
                }
            }

            // Update SO Header
            $tax_amount = 0;
            if ($order['tax_class_id']) {
                $tax_class = db_fetch("SELECT tax_rate FROM tax_configurations WHERE id = ?", [$order['tax_class_id']]);
                if ($tax_class) {
                    $tax_amount = ($new_subtotal - $order['discount_amount']) * floatval($tax_class['tax_rate']);
                }
            }
            $total_amount = ($new_subtotal - $order['discount_amount']) + $tax_amount;

            db_query("
                UPDATE sales_orders 
                SET subtotal = ?, tax_amount = ?, total_amount = ?, status = 'confirmed' 
                WHERE id = ?
            ", [$new_subtotal, $tax_amount, $total_amount, $order_id]);

            db_commit();
            set_flash('Discount adjustments applied and order confirmed!', 'success');
            redirect('../view_order.php?id='.$order_id);
        } catch (Exception $e) {
            db_rollback();
            log_error("Error applying discounts: " . $e->getMessage());
            $error = "Error applying discounts: " . $e->getMessage();
        }
    }
}

// Fetch order items with cost and fixed discounts
$items = db_fetch_all("
    SELECT sol.*, i.code as item_code, i.name as item_name, i.cost_price, i.selling_price as normal_selling_price
    FROM sales_order_lines sol
    JOIN inventory_items i ON sol.item_id = i.id
    WHERE sol.order_id = ?
", [$order_id]);

// Enhance items with customer-specific fixed discount
foreach ($items as &$item) {
    // Specific item discount
    $specific = db_fetch("
        SELECT discount_percent FROM customer_discounts 
        WHERE customer_id = ? AND item_id = ? AND is_active = 1
    ", [$order['customer_id'], $item['item_id']]);
    
    if ($specific) {
        $item['fixed_discount_pct'] = (float)$specific['discount_percent'];
    } else {
        // Global customer discount
        $global = db_fetch("
            SELECT discount_percent FROM customer_discounts 
            WHERE customer_id = ? AND item_id IS NULL AND is_active = 1
        ", [$order['customer_id']]);
        $item['fixed_discount_pct'] = $global ? (float)$global['discount_percent'] : 0;
    }
    
    // Calculated Selling Price after fixed discount
    $item['base_sp'] = $item['normal_selling_price'] * (1 - ($item['fixed_discount_pct'] / 100));
}

$page_title = 'Adjust Order Discount - ' . $order['order_number'];
include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-calculator me-2 text-warning"></i>Adjust Pricing & Discounts</h2>
            <p class="text-muted mb-0">Order: <strong><?= $order['order_number'] ?></strong> for <strong><?= $order['customer_name'] ?></strong></p>
        </div>
        <a href="order_dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
        </a>
    </div>

    <form method="POST" id="discountForm">
        <input type="hidden" name="action" value="approve_discount">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="pricingTable">
                        <thead class="bg-dark text-white">
                            <tr>
                                <th style="width: 120px;">Item Code</th>
                                <th>Prod Name</th>
                                <th style="width: 80px;" class="text-center">Qty</th>
                                <th class="text-end">Base Price (Order)</th>
                                <th class="text-center">Fixed Disc% (Cust)</th>
                                <th class="text-end">SP (Base)</th>
                                <th class="text-center" style="width: 150px;">Final Disc% (Manual)</th>
                                <th class="text-end">Final SP</th>
                                <?php if ($is_authorized): ?>
                                    <th class="text-end bg-secondary text-white">Cost</th>
                                    <th class="text-center bg-secondary text-white">Margin%</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $idx => $item): ?>
                                <tr data-idx="<?= $idx ?>">
                                    <input type="hidden" name="line_id[]" value="<?= $item['id'] ?>">
                                    <td class="small fw-bold"><?= escape_html($item['item_code']) ?></td>
                                    <td class="small"><?= escape_html($item['item_name']) ?></td>
                                    <td class="text-center"><?= $item['quantity'] ?></td>
                                    <td class="text-end" data-normal-price="<?= $item['unit_price'] ?>">
                                        <?= format_currency($item['unit_price']) ?>
                                    </td>
                                    <td class="text-center text-muted"><?= number_format($item['fixed_discount_pct'], 1) ?>%</td>
                                    <td class="text-end fw-bold" data-base-sp="<?= $item['base_sp'] ?>">
                                        <?= format_currency($item['base_sp']) ?>
                                    </td>
                                    <td>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="manual_discount_pct[]" 
                                                   class="form-control text-center manual-disc-input" 
                                                   value="<?= $item['discount_percent'] ?: $item['fixed_discount_pct'] ?>" 
                                                   step="0.1" min="0" max="100">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-primary final-sp">
                                        <?= format_currency($item['line_total'] / ($item['quantity'] ?: 1)) ?>
                                    </td>
                                    <?php if ($is_authorized): ?>
                                        <td class="text-end text-muted small" data-cost="<?= $item['cost_price'] ?>">
                                            <?= format_currency($item['cost_price']) ?>
                                        </td>
                                        <td class="text-center fw-bold margin-cell">
                                            <!-- Calculated by JS -->
                                            0%
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="7" class="text-end">SUBTOTAL</td>
                                <td class="text-end" id="grandSubtotal">0.00</td>
                                <td colspan="2" class="bg-secondary"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light py-3 text-end">
                <button type="submit" class="btn btn-success btn-lg px-5 shadow-sm">
                    <i class="fas fa-check-circle me-2"></i>Approve & Confirm Order
                </button>
            </div>
        </div>
    </form>
</div>

<style>
    .bg-dark { background-color: #1a1d21 !important; }
    #pricingTable td { font-size: 0.9rem; }
    .manual-disc-input:focus { border-color: #ffc107; box-shadow: 0 0 0 0.25rem rgba(255, 193, 7, 0.25); }
    .margin-low { color: #dc3545; }
    .margin-healthy { color: #198754; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.manual-disc-input');
    
    function updateRow(row) {
        const idx = row.dataset.idx;
        const qty = parseFloat(row.querySelector('td:nth-child(3)').textContent);
        const normalPrice = parseFloat(row.querySelector('[data-normal-price]').dataset.normalPrice);
        const discPct = parseFloat(row.querySelector('.manual-disc-input').value) || 0;
        const cost = parseFloat(row.querySelector('[data-cost]')?.dataset.cost) || 0;
        
        // Final SP = Normal Price * (1 - Disc%)
        const finalSP = normalPrice * (1 - (discPct / 100));
        row.querySelector('.final-sp').textContent = formatCurrency(finalSP);
        
        // Margin = (SP - Cost) / SP
        if (cost > 0) {
            const margin = ((finalSP - cost) / finalSP) * 100;
            const marginCell = row.querySelector('.margin-cell');
            if (marginCell) {
                marginCell.textContent = margin.toFixed(1) + '%';
                marginCell.classList.remove('margin-low', 'margin-healthy');
                marginCell.classList.add(margin < 20 ? 'margin-low' : 'margin-healthy');
            }
        }
        
        return finalSP * qty;
    }
    
    function updateTotals() {
        let grandSub = 0;
        document.querySelectorAll('#pricingTable tbody tr').forEach(row => {
            grandSub += updateRow(row);
        });
        document.getElementById('grandSubtotal').textContent = formatCurrency(grandSub);
    }
    
    function formatCurrency(val) {
        return '$' + val.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    inputs.forEach(input => {
        input.addEventListener('input', updateTotals);
    });
    
    // Initial calc
    updateTotals();
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
