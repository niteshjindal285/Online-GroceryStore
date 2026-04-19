<?php
/**
 * View Sales Order Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'View Sales Order - MJR Group ERP';

// Get order ID
$order_id = get('id');
if (!$order_id) {
    set_flash('Order ID not provided.', 'error');
    redirect('orders.php');
}

// Get order data with customer info
$order = db_fetch("
    SELECT so.*, c.customer_code, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           u.username as created_by_name, l.name as location_name, b.code as bin_name,
           p.name as project_name, ps.stage_name
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    LEFT JOIN users u ON so.created_by = u.id
    LEFT JOIN locations l ON so.location_id = l.id
    LEFT JOIN bins b ON so.bin_id = b.id
    LEFT JOIN projects p ON so.project_id = p.id
    LEFT JOIN project_stages ps ON so.project_stage_id = ps.id
    WHERE so.id = ?
", [$order_id]);

if (!$order) {
    set_flash('Order not found.', 'error');
    redirect('orders.php');
}

// Fetch Project Stages if applicable
$project_stages = [];
if ($order['sale_type'] === 'project' && $order['project_id']) {
    $project_stages = db_fetch_all("SELECT * FROM project_stages WHERE project_id = ? ORDER BY id ASC", [$order['project_id']]);
}

// Get order items
$order_items = db_fetch_all("
    SELECT sol.*, i.code, i.name as item_name
    FROM sales_order_lines sol
    JOIN inventory_items i ON sol.item_id = i.id
    WHERE sol.order_id = ?
", [$order_id]);

// Handle Payment Update POST
if (is_post() && post('action') === 'record_payment') {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            $p_status = post('payment_status');
            $p_method = post('payment_method');
            $p_date = post('payment_date');
            $p_currency = post('payment_currency');
            
            db_query("
                UPDATE sales_orders 
                SET payment_status = ?, payment_method = ?, payment_date = ?, payment_currency = ? 
                WHERE id = ? AND company_id = ?
            ", [$p_status, $p_method, $p_date, $p_currency, $order_id, $_SESSION['company_id']]);
            
            set_flash('Payment recorded successfully!', 'success');
            redirect("view_order.php?id=$order_id");
        } catch (Exception $e) {
            log_error("Error recording payment: " . $e->getMessage());
            set_flash('Error recording payment.', 'error');
        }
    }
}

// Handle Finalize for Discount POST
if (is_post() && post('action') === 'finalize_discount') {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            db_query("UPDATE sales_orders SET status = 'pending_discount' WHERE id = ?", [$order_id]);
            set_flash('Order finalized and sent for discount approval.', 'success');
            redirect("view_order.php?id=$order_id");
        } catch (Exception $e) {
            log_error("Error finalizing order: " . $e->getMessage());
            set_flash('Error finalizing order.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-eye me-2"></i>Sales Order Details</h2>
                <div>
                    <!-- Check if already posted to GL -->
                    <?php
                    $gl_posted = db_fetch("SELECT COUNT(*) as cnt FROM general_ledger WHERE reference_type = 'sales_order' AND reference_id = ?", [$order_id]);
                    $is_gl_posted = ($gl_posted['cnt'] ?? 0) > 0;
                    $can_post = in_array($order['status'], ['confirmed','shipped','delivered']) || $order['payment_status'] === 'paid';
                    ?>

                    <?php if ($order['status'] === 'draft'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Finalize this order and send for discount approval?')">
                            <input type="hidden" name="action" value="finalize_discount">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <button type="submit" class="btn btn-warning me-2">
                                <i class="fas fa-file-signature me-2"></i>Finalize for Discount
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($is_gl_posted): ?>
                        <span class="btn btn-success me-2 disabled">
                            <i class="fas fa-check-circle me-2"></i>Posted to GL
                        </span>
                    <?php elseif ($can_post): ?>
                        <form method="POST" action="../finance/post_sale_to_gl.php" class="d-inline"
                              onsubmit="return confirm('Post this sale to the General Ledger? This will create accounting entries.')">
                            <input type="hidden" name="order_id" value="<?= $order_id ?>">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <button type="submit" class="btn btn-success me-2">
                                <i class="fas fa-book me-2"></i>Post to GL
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Invoice Actions -->
                    <?php if ($order['status'] !== 'invoiced' && $order['status'] !== 'cancelled'): ?>
                    <a href="invoices/add_invoice.php?from_so=<?= $order_id ?>" class="btn btn-primary me-2">
                        <i class="fas fa-file-invoice me-2"></i>Convert to Invoice
                    </a>
                    <?php endif; ?>
                    <a href="edit_order.php?id=<?= $order['id'] ?>" class="btn btn-success me-2">
                        <i class="fas fa-edit me-2"></i>Edit Order
                    </a>
                    <button type="button" class="btn btn-dark me-2" onclick="openPaymentModal()">
                        <i class="fas fa-money-bill-wave me-2"></i>Record Payment
                    </button>
                    <?php if ($order['sale_type'] === 'project'): ?>
                        <a href="print_project_invoice.php?id=<?= $order_id ?>" target="_blank" class="btn btn-primary me-2">
                            <i class="fas fa-print me-2"></i>Print Project Invoice
                        </a>
                    <?php endif; ?>
                    <a href="orders.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Order Information -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between">
                            <h5>Order Information</h5>
                            <?php if ($order['sale_type'] === 'project'): ?>
                                <span class="badge bg-primary">Project Sale</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Normal Sale</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($order['sale_type'] === 'project'): ?>
                                <div class="alert alert-info border-primary mb-4">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong><i class="fas fa-project-diagram me-2"></i>Project:</strong> 
                                            <?= escape_html($order['project_name'] ?: 'Unnamed Project') ?>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <strong><i class="fas fa-layer-group me-2"></i>Stage/Phase:</strong> 
                                            <?= escape_html($order['stage_name'] ?: 'Full Settlement') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

<style>
    .status-badge-premium {
        padding: 6px 16px;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        font-size: 0.75rem;
        border-radius: 50rem;
    }
</style>

                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="text-muted small fw-bold d-block mb-1">DELIVERY STATUS</label>
                                    <?php
                                    $d_status = $order['delivery_status'] ?? 'open';
                                    $d_badge = ($d_status == 'completed' ? 'success' : ($d_status == 'pending' ? 'warning text-dark' : 'secondary text-white'));
                                    ?>
                                    <span class="badge status-badge-premium bg-<?= $d_badge ?> shadow-sm"><?= ucfirst($d_status) ?></span>
                                </div>
                                <div class="col-md-9 text-end align-self-end">
                                    <?php if ($d_status != 'completed'): ?>
                                        <a href="ship_order.php?invoice_id=<?= db_fetch('SELECT id FROM invoices WHERE so_id = ? LIMIT 1', [$order['id']])['id'] ?? '' ?>" class="btn btn-warning fw-bold px-4">
                                            <i class="fas fa-truck-loading me-2"></i>Deliver Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>SO Number:</strong></td>
                                            <td><?= escape_html($order['order_number']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Customer:</strong></td>
                                            <td><?= escape_html($order['customer_name']) ?> (<?= escape_html($order['customer_code']) ?>)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Order Date:</strong></td>
                                            <td><?= format_date($order['order_date']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Delivery Date:</strong></td>
                                            <td><?= format_date($order['delivery_date']) ?: 'Not set' ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Status:</strong></td>
                                            <td>
                                                <?php
                                                $status_badges = [
                                                    'draft' => 'secondary',
                                                    'confirmed' => 'primary',
                                                    'in_production' => 'warning',
                                                    'shipped' => 'info',
                                                    'delivered' => 'success',
                                                    'cancelled' => 'danger'
                                                ];
                                                $badge_class = $status_badges[$order['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $badge_class ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created By:</strong></td>
                                            <td><?= escape_html($order['created_by_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created At:</strong></td>
                                            <td><?= format_datetime($order['created_at']) ?></td>
                                        </tr>
                                        <?php if (!empty($order['location_name'])): ?>
                                        <tr>
                                            <td><strong>Warehouse:</strong></td>
                                            <td><?= escape_html($order['location_name']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($order['bin_name'])): ?>
                                        <tr>
                                            <td><strong>Bin Location:</strong></td>
                                            <td><?= escape_html($order['bin_name']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Payment Status:</strong></td>
                                            <td>
                                                <?php
                                                $payment_badge = 'secondary';
                                                if ($order['payment_status'] == 'paid') $payment_badge = 'success';
                                                elseif ($order['payment_status'] == 'partially_paid') $payment_badge = 'info';
                                                elseif ($order['payment_status'] == 'refunded') $payment_badge = 'danger';
                                                elseif ($order['payment_status'] == 'unpaid') $payment_badge = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $payment_badge ?>"><?= ucfirst(str_replace('_', ' ', $order['payment_status'])) ?></span>
                                            </td>
                                        </tr>
                                        <?php if ($order['payment_method']): ?>
                                        <tr>
                                            <td><strong>Payment Method:</strong></td>
                                            <td>
                                                <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?>
                                                <?php if (!empty($order['payment_currency'])): ?>
                                                    <span class="currency-badge currency-<?= strtolower($order['payment_currency']) ?>">
                                                        <?= $order['payment_currency'] ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if ($order['payment_date']): ?>
                                        <tr>
                                            <td><strong>Payment Date:</strong></td>
                                            <td><?= format_datetime($order['payment_date']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <?php if ($order['notes']): ?>
                            <div class="mt-3">
                                <strong>Notes:</strong>
                                <p class="text-muted"><?= escape_html($order['notes']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>

                    <div class="row mb-4">
        <!-- Main Order Info -->
        <div class="col-md-<?= ($order['sale_type'] === 'project') ? '8' : '12' ?>">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>Order Information</h5>
                        <?php if ($order['sale_type'] === 'project'): ?>
                            <span class="badge bg-primary rounded-pill px-3">Project Order</span>
                        <?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1 text-muted small text-uppercase fw-bold">Order Number</p>
                            <p class="fw-bold fs-5 mb-3"><?= escape_html($order['order_number']) ?></p>
                            
                            <p class="mb-1 text-muted small text-uppercase fw-bold">Customer</p>
                            <p class="mb-3"><strong><?= escape_html($order['customer_name']) ?></strong></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1 text-muted small text-uppercase fw-bold">Order Date</p>
                            <p class="mb-3"><?= format_date($order['order_date']) ?></p>

                            <p class="mb-1 text-muted small text-uppercase fw-bold">Required Date</p>
                            <p class="mb-3"><?= format_date($order['required_date']) ?: 'Not set' ?></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1 text-muted small text-uppercase fw-bold">Status</p>
                            <p class="mb-3">
                                <span class="badge bg-<?= get_status_color($order['status']) ?> shadow-sm">
                                    <?= strtoupper(escape_html($order['status'])) ?>
                                </span>
                            </p>

                            <p class="mb-1 text-muted small text-uppercase fw-bold">Payment Status</p>
                            <p class="mb-0">
                                <span class="badge border border-<?= get_status_color($order['payment_status']) ?> text-<?= get_status_color($order['payment_status']) ?>">
                                    <?= strtoupper(escape_html($order['payment_status'])) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($order['sale_type'] === 'project'): ?>
        <!-- Project Progress Widget -->
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 bg-dark text-white">
                <div class="card-body">
                    <h5 class="card-title mb-3 d-flex justify-content-between align-items-center">
                        <span class="small text-uppercase tracking-wider">Project Timeline</span>
                        <i class="fas fa-tasks text-primary"></i>
                    </h5>
                    <div class="project-steps">
                        <?php foreach ($project_stages as $stage): 
                            $is_current = ($stage['id'] == $order['project_stage_id']);
                            $icon = ($stage['status'] === 'paid') ? 'check-circle' : (($stage['status'] === 'invoiced') ? 'file-invoice-dollar' : 'circle');
                            $color = ($stage['status'] === 'paid') ? 'success' : (($stage['status'] === 'invoiced') ? 'info' : 'secondary text-opacity-25');
                        ?>
                            <div class="d-flex align-items-center mb-3 <?= $is_current ? 'p-2 bg-secondary bg-opacity-25 rounded' : '' ?>">
                                <div class="me-3 position-relative">
                                    <i class="fas fa-<?= $icon ?> fa-lg text-<?= $color ?>"></i>
                                    <?php if ($is_current): ?>
                                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-primary border border-light rounded-circle"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="fw-bold <?= $is_current ? 'text-primary' : '' ?>"><?= escape_html($stage['stage_name']) ?></small>
                                        <small class="text-muted"><?= number_format($stage['percentage'], 0) ?>%</small>
                                    </div>
                                    <div class="progress mt-1" style="height: 4px;">
                                        <div class="progress-bar bg-<?= $color ?>" style="width: <?= ($stage['status'] !== 'pending') ? '100%' : '0%' ?>"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (is_admin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
    <!-- BOSS COPY — Internal Financials -->
    <div class="card mb-4 shadow-sm border-0 border-start border-warning border-5 bg-light">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="text-warning fw-bold mb-0"><i class="fas fa-user-shield me-2"></i>BOSS COPY — INTERNAL FINANCIAL ANALYSIS</h6>
                <span class="badge bg-secondary text-uppercase" style="font-size: 10px; letter-spacing: 1px;">Confidential</span>
            </div>
            <hr class="my-2 opacity-10">
            <div class="row g-4 text-center">
                <div class="col-md-3 border-end">
                    <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Total Project Value</small>
                    <span class="fw-bold fs-5 text-dark"><?= format_currency($order['total_amount']) ?></span>
                </div>
                <div class="col-md-3 border-end">
                    <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Estimated Unit Cost</small>
                    <?php 
                        $total_cost = 0;
                        foreach ($order_items as $it) { // Changed $items to $order_items
                            $it_cost = db_fetch("SELECT cost_price FROM inventory_items WHERE id=?", [$it['item_id']]);
                            $total_cost += ($it_cost['cost_price'] ?? 0) * $it['quantity'];
                        }
                        $margin = ($order['total_amount'] > 0) ? (($order['total_amount'] - $total_cost) / $order['total_amount']) * 100 : 0;
                    ?>
                    <span class="fw-bold fs-5 text-dark"><?= format_currency($total_cost) ?></span>
                </div>
                <div class="col-md-3 border-end">
                    <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Gross Margin %</small>
                    <span class="fw-bold fs-5 text-<?= ($margin >= 30) ? 'success' : (($margin >= 15) ? 'warning' : 'danger') ?>">
                        <?= number_format($margin, 1) ?>%
                    </span>
                </div>
                <div class="col-md-3">
                    <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Margin Status</small>
                    <span class="badge bg-<?= ($margin >= 30) ? 'success' : (($margin >= 15) ? 'warning' : 'danger') ?> text-uppercase">
                        <?= ($margin >= 30) ? 'Healthy' : (($margin >= 15) ? 'Acceptable' : 'Low Margin') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
                    <!-- Order Items -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Order Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item Code</th>
                                            <th>Item Name</th>
                                            <th>Quantity</th>
                                            <th>Unit Price</th>
                                            <th>Disc%</th>
                                            <th>Total Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td><?= escape_html($item['code']) ?></td>
                                            <td>
                                                <strong><?= escape_html($item['item_name']) ?></strong>
                                                <?php if (!empty($item['description'])): ?>
                                                    <div class="text-muted small mt-1"><?= nl2br(escape_html($item['description'])) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $item['quantity'] ?></td>
                                            <td>$<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                                            <td><?= number_format((float)($item['discount_percent'] ?? 0), 2) ?>%</td>
                                            <td>$<?= number_format((float)($item['line_total'] ?? ($item['quantity'] * $item['unit_price'])), 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <td>Subtotal:</td>
                                    <td class="text-end">$<?= number_format($order['subtotal'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td>Tax:</td>
                                    <td class="text-end">$<?= number_format($order['tax_amount'], 2) ?></td>
                                </tr>
                                <?php if ($order['discount_amount'] > 0): ?>
                                <tr>
                                    <td>Order Discount:</td>
                                    <td class="text-end text-danger">-$<?= number_format($order['discount_amount'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="border-top">
                                    <td><strong>Total:</strong></td>
                                    <td class="text-end"><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Customer Information -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong><?= escape_html($order['customer_name']) ?></strong></p>
                            <p>Code: <?= escape_html($order['customer_code']) ?></p>
                            <?php if ($order['customer_email']): ?>
                            <p>Email: <?= escape_html($order['customer_email']) ?></p>
                            <?php endif; ?>
                            <?php if ($order['customer_phone']): ?>
                            <p>Phone: <?= escape_html($order['customer_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($order['customer_address']): ?>
                            <p>Address: <?= escape_html($order['customer_address']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record / Update Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="record_payment">
                    
                    <div class="mb-3">
                        <label for="payment_status" class="form-label">Payment Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="pending" <?= $order['payment_status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="unpaid" <?= $order['payment_status'] == 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                            <option value="partially_paid" <?= $order['payment_status'] == 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                            <option value="paid" <?= $order['payment_status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="refunded" <?= $order['payment_status'] == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="">Select Method</option>
                            <option value="cash" <?= $order['payment_method'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                            <option value="bank_transfer" <?= $order['payment_method'] == 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                            <option value="cheque" <?= $order['payment_method'] == 'cheque' ? 'selected' : '' ?>>Cheque</option>
                            <option value="online" <?= $order['payment_method'] == 'online' ? 'selected' : '' ?>>Online Payment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="payment_currency" class="form-label">Currency</label>
                        <select class="form-select currency-select" id="payment_currency" name="payment_currency">
                            <option value="FJD" <?= ($order['payment_currency'] ?? 'FJD') == 'FJD' ? 'selected' : '' ?>>FJD - Fijian Dollar</option>
                            <option value="USD" <?= ($order['payment_currency'] ?? '') == 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                            <option value="EUR" <?= ($order['payment_currency'] ?? '') == 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                            <option value="GBP" <?= ($order['payment_currency'] ?? '') == 'GBP' ? 'selected' : '' ?>>GBP - British Pound</option>
                            <option value="INR" <?= ($order['payment_currency'] ?? '') == 'INR' ? 'selected' : '' ?>>INR - Indian Rupee</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="datetime-local" class="form-control" id="payment_date" name="payment_date" 
                               value="<?= $order['payment_date'] ? date('Y-m-d\TH:i', strtotime($order['payment_date'])) : date('Y-m-d\TH:i') ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('paymentForm').submit()">
                    <i class="fas fa-save me-2"></i>Save Payment Info
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .no-print {
        display: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        page-break-inside: avoid;
    }
}
</style>

<script>
function openPaymentModal() {
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function downloadPDF() {
    window.open('print_order.php?id=<?= $order_id ?>', '_blank');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
