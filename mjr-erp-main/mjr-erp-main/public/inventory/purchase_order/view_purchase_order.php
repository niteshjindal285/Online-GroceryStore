<?php
/**
 * View Purchase Order Details
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'View Purchase Order - MJR Group ERP';
$company_id = active_company_id(1);

$po_id = get_param('id');

if (!$po_id) {
    set_flash('Purchase order not found.', 'error');
    redirect('purchase_orders.php');
}

// Get PO details (Updated with approval info)
$po = db_fetch("
    SELECT po.*, s.name as supplier_name, s.supplier_code, s.email as supplier_email,
           comp.name as company_name, wh.name as warehouse_name,
           u.username as created_by_name,
           appr.username as approved_by_name,
           m.username as manager_name, m.full_name as manager_full_name,
           curr.code as currency_code, curr.symbol as currency_symbol
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN companies comp ON po.company_id = comp.id
    LEFT JOIN locations wh ON po.warehouse_id = wh.id
    LEFT JOIN users u ON po.created_by = u.id
    LEFT JOIN users appr ON po.approved_by = appr.id
    LEFT JOIN users m ON po.manager_id = m.id
    LEFT JOIN currencies curr ON po.currency_id = curr.id
    WHERE po.id = ? AND po.company_id = ?
", [$po_id, $company_id]);

if (!$po) {
    set_flash('Purchase order not found.', 'error');
    redirect('purchase_orders.php');
}

// ── Handle Workflow Actions ────────────────────────────────────────────────────
if (is_post()) {
    $action = post('action');
    $csrf_token = post('csrf_token');

    if ($action && verify_csrf_token($csrf_token)) {
        try {
            db_begin_transaction();
            $user_id = current_user_id();
            $now = date('Y-m-d H:i:s');

            if ($action === 'submit_approval' && $po['status'] === 'draft') {
                // Check if we have at least one line item
                $lines_count = db_fetch("SELECT COUNT(*) as cnt FROM purchase_order_lines WHERE po_id = ?", [$po_id]);
                if ($lines_count['cnt'] == 0) {
                    throw new Exception('Cannot submit for approval: This purchase order has no line items. Please edit and add at least one item.');
                }
                
                db_query("UPDATE purchase_orders SET status = 'pending_approval', submitted_at = ? WHERE id = ?", [$now, $po_id]);
                log_po_history($po_id, 'pending_approval', 'Submitted for manager approval');
                set_flash('Purchase order submitted for approval.', 'success');
            } 
            elseif ($action === 'approve_po' && $po['status'] === 'pending_approval') {
                $is_assigned_manager = ($po['manager_id'] == $user_id);
                
                if (!$is_assigned_manager) {
                    throw new Exception('You are not the assigned manager for this approval.');
                }
                
                db_query("UPDATE purchase_orders SET status = 'approved', approved_at = ?, approved_by = ? WHERE id = ?", [$now, $user_id, $po_id]);
                log_po_history($po_id, 'approved', 'Manager approved the PO');
                
                // Automatic send to supplier
                if (send_po_to_supplier($po_id)) {
                    db_query("UPDATE purchase_orders SET status = 'sent' WHERE id = ?", [$po_id]);
                    log_po_history($po_id, 'sent', 'System automatically sent PO to supplier email');
                    set_flash('Purchase order approved and sent to supplier.', 'success');
                } else {
                    set_flash('Purchase order approved, but failed to send email automatically. Please send manually.', 'warning');
                }
            } 
            elseif ($action === 'reject_po' && $po['status'] === 'pending_approval') {
                $is_assigned_manager = ($po['manager_id'] == $user_id);
                
                if (!$is_assigned_manager) {
                    throw new Exception('You are not the assigned manager for this approval.');
                }
                
                $reason = post('rejection_reason');
                db_query("UPDATE purchase_orders SET status = 'rejected', rejection_reason = ? WHERE id = ?", [$reason, $po_id]);
                log_po_history($po_id, 'rejected', 'Manager rejected: ' . $reason);
                set_flash('Purchase order rejected.', 'info');
            }
            elseif ($action === 'cancel_po' && !in_array($po['status'], ['received', 'cancelled'])) {
                $reason = post('cancel_reason');
                db_query("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ?", [$po_id]);
                log_po_history($po_id, 'cancelled', 'Order cancelled: ' . $reason);
                set_flash('Purchase order has been cancelled.', 'info');
            }

            db_commit();
            redirect("view_purchase_order.php?id=$po_id");
        } catch (Exception $e) {
            db_rollback();
            set_flash('Workflow Error: ' . $e->getMessage(), 'error');
        }
    }
}




if (!$po) {
    set_flash('Purchase order not found.', 'error');
    redirect('purchase_orders.php');
}

// Get PO line items
$po_lines = db_fetch_all("
    SELECT pol.*, i.code, i.name as item_name
    FROM purchase_order_lines pol
    JOIN inventory_items i ON pol.item_id = i.id
    WHERE pol.po_id = ?
", [$po_id]);

$curr_symbol = '$';
$curr_code = 'USD';

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-eye me-2"></i>Purchase Order: <?= escape_html($po['po_number']) ?></h2>
        </div>
        <div class="col-auto">
            <!-- Invoice Actions -->
            <button onclick="window.open('print_purchase_order.php?id=<?= $po_id ?>', '_blank')" class="btn btn-info me-2">
                <i class="fas fa-print me-2"></i>Print Invoice
            </button>
            <button onclick="window.open('print_purchase_order.php?id=<?= $po_id ?>', '_blank')" class="btn btn-primary me-2">
                <i class="fas fa-download me-2"></i>Download PDF
            </button>
            <button onclick="openEmailModal()" class="btn btn-warning me-2">
                <i class="fas fa-envelope me-2"></i>Email Invoice
            </button>
            <!-- Workflow Actions -->
            <?php if ($po['status'] === 'draft'): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="submit_approval">
                    <button type="submit" class="btn btn-primary me-2 shadow-sm" onclick="return confirm('Submit this PO for manager approval?')">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                    </button>
                </form>
            <?php endif; ?>

            <?php 
            $is_assigned_manager = ($po['manager_id'] == current_user_id());
            
            if ($po['status'] === 'pending_approval' && $is_assigned_manager): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="approve_po">
                    <button type="submit" class="btn btn-success me-2 shadow-sm" onclick="return confirm('Approve this purchase order?')">
                        <i class="fas fa-check-circle me-2"></i>Approve PO
                    </button>
                </form>
                <button type="button" class="btn btn-danger me-2 shadow-sm" onclick="openRejectModal()">
                    <i class="fas fa-times-circle me-2"></i>Reject PO
                </button>
            <?php endif; ?>

            <style>
                .premium-card {
                    border: none;
                    border-radius: 12px;
                    background: #1e1e2d;
                    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
                    margin-bottom: 25px;
                    transition: all 0.3s ease;
                }
                .premium-card .card-header {
                    background: rgba(255,255,255,0.03);
                    border-bottom: 1px solid rgba(255,255,255,0.08);
                    font-weight: 600;
                    color: #0dcaf0;
                    padding: 18px 25px;
                    border-radius: 12px 12px 0 0;
                }
            </style>

            <?php if (in_array($po['status'], ['approved', 'sent', 'confirmed'])): ?>
                <a href="../gsrn/add_gsrn.php?po_id=<?= $po_id ?>" class="btn btn-success me-2 shadow-sm">
                    <i class="fas fa-truck-loading me-2"></i>Receive Items
                </a>
            <?php endif; ?>

            <?php if (!in_array($po['status'], ['received', 'cancelled'])): ?>
                <button type="button" class="btn btn-outline-danger me-2 shadow-sm" onclick="openCancelModal()">
                    <i class="fas fa-ban me-2"></i>Cancel Order
                </button>
            <?php endif; ?>

            <?php if (in_array($po['status'], ['draft', 'rejected'])): ?>
                <a href="edit_purchase_order.php?id=<?= $po_id ?>" class="btn btn-outline-primary me-2 shadow-sm">
                    <i class="fas fa-edit me-2"></i>Edit Order
                </a>
            <?php endif; ?>

            <a href="purchase_orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <!-- Workflow Progress Bar -->
    <div class="row mb-4 no-print">
        <div class="col-12">
            <div class="card premium-card">
                <div class="card-body py-4">
                    <div class="workflow-steps">
                        <?php
                        $steps = [
                            ['id' => 'draft', 'label' => 'Draft', 'icon' => 'pencil-alt'],
                            ['id' => 'pending_approval', 'label' => 'Pending Approval', 'icon' => 'hourglass-half'],
                            ['id' => 'approved', 'label' => 'Approved', 'icon' => 'check-double'],
                            ['id' => 'sent', 'label' => 'Sent to Supplier', 'icon' => 'envelope-open-text']
                        ];
                        
                        $current_status = $po['status'];
                        $found_current = false;
                        $step_index = 0;
                        
                        // Map rejected to a special view if needed, but here we just show progress
                        foreach ($steps as $index => $step) {
                            $is_past = false;
                            $is_current = false;
                            
                            // Simple logic for progress
                            $status_rank = [
                                'draft' => 0,
                                'pending_approval' => 1,
                                'approved' => 2,
                                'rejected' => 1, // Stay at step 1 if rejected
                                'sent' => 3,
                                'confirmed' => 3,
                                'partially_received' => 3,
                                'received' => 3
                            ];
                            
                            $current_rank = $status_rank[$current_status] ?? 0;
                            
                            $is_current = ($index === $current_rank);
                            $is_past = ($index < $current_rank);
                            if ($current_status === 'rejected' && $index === 1) $is_current = false; // Override for reject
                        ?>
                            <div class="workflow-step <?= $is_past ? 'step-past' : ($is_current ? 'step-current' : '') ?> <?= ($current_status === 'rejected' && $index === 1) ? 'step-rejected' : '' ?>">
                                <div class="step-icon">
                                    <i class="fas fa-<?= $step['icon'] ?>"></i>
                                    <?php if ($is_past): ?><i class="fas fa-check check-overlay"></i><?php endif; ?>
                                </div>
                                <div class="step-label text-uppercase"><?= $step['label'] ?></div>
                                <?php if ($index === 1 && $current_status === 'rejected'): ?>
                                    <div class="small text-danger fw-bold">REJECTED</div>
                                <?php endif; ?>
                            </div>
                            <?php if ($index < count($steps) - 1): ?>
                                <div class="step-connector <?= $is_past ? 'connector-past' : '' ?>"></div>
                            <?php endif; ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <!-- Purchase Order Header -->
            <div class="card premium-card mb-4 border-start border-4 border-info">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="mb-0 text-primary">Purchase Order Details</h4>
                            <p class="text-muted small mb-0">Reviewing details for order <?= escape_html($po['po_number']) ?></p>
                        </div>
                        <div class="col-auto">
                            <div class="bg-dark p-2 rounded border border-secondary text-center" style="min-width: 150px;">
                                <label class="small text-muted d-block">Status</label>
                                <?php
                                    $badge_class = match($po['status']) {
                                        'draft'              => 'secondary',
                                        'pending_approval'   => 'warning',
                                        'approved'           => 'success',
                                        'rejected'           => 'danger',
                                        'sent'               => 'primary',
                                        'confirmed'          => 'info',
                                        'partially_received' => 'warning',
                                        'received'           => 'success',
                                        'cancelled'          => 'danger',
                                        default              => 'secondary',
                                    };
                                ?>
                                <span class="badge bg-<?= $badge_class ?> fs-6"><?= strtoupper(str_replace('_', ' ', $po['status'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Supplier Info -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-truck me-2 text-info"></i>Supplier Info</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="small text-muted d-block">Supplier</label>
                        <span class="fw-bold"><?= escape_html($po['supplier_code']) ?> - <?= escape_html($po['supplier_name']) ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted d-block">Supplier Contact</label>
                        <span><?= escape_html($po['supplier_contact'] ?: 'N/A') ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted d-block">Supplier Email</label>
                        <span><?= escape_html($po['supplier_email'] ?: 'N/A') ?></span>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted d-block">Supplier Address</label>
                        <span class="text-muted"><?= nl2br(escape_html($po['supplier_address'] ?: 'N/A')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Info -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Order Info</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Order Date</label>
                            <span class="fw-bold"><?= format_date($po['order_date']) ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Expected Delivery</label>
                            <span class="fw-bold"><?= format_date($po['expected_delivery_date']) ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Company</label>
                            <span><?= escape_html($po['company_name'] ?: 'N/A') ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Warehouse Location</label>
                            <span><?= escape_html($po['warehouse_name'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Purchase Type</label>
                            <span class="badge bg-dark text-info border border-info"><?= $po['purchase_type'] ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Order Category</label>
                            <span><?= $po['order_category'] ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Currency</label>
                            <span><?= escape_html($curr_code) ?> (Rate: <?= number_format($po['exchange_rate'] ?? 1, 4) ?>)</span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Reference No</label>
                            <span><?= escape_html($po['reference_no'] ?: 'N/A') ?></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Landed Cost Method</label>
                            <span class="fw-bold text-success text-uppercase"><?= escape_html($po['landed_cost_method'] ?: 'Value') ?></span>
                        </div>
                    </div>
                    <hr class="my-3 opacity-10">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Created By</label>
                            <span class="text-primary fw-bold"><i class="fas fa-user me-1"></i> <?= escape_html($po['created_by_name'] ?? 'System') ?></span>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="small text-muted d-block">Assigned Manager (for Approval)</label>
                            <span class="text-info fw-bold"><i class="fas fa-user-shield me-1"></i> <?= escape_html($po['manager_full_name'] ?: $po['manager_name'] ?: 'Not Assigned') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card mb-4 premium-card">
        <div class="card-header py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2 text-info"></i>Items Table</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-dark">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th>Item Name</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Unit Landed (Local)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($po_lines as $line): ?>
                        <tr>
                            <td><code><?= escape_html($line['code']) ?></code></td>
                            <td><?= escape_html($line['item_name']) ?></td>
                            <td class="text-center"><?= number_format($line['quantity'], 2) ?></td>
                            <td class="text-end"><?= $curr_symbol ?><?= number_format($line['unit_price'], 2) ?></td>
                            <td class="text-end fw-bold"><?= $curr_symbol ?><?= number_format($line['line_total'], 2) ?></td>
                            <td class="text-end text-info"><?= $curr_symbol ?><?= number_format($line['landed_unit_cost'] ?: 0, 4) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- International Cost Section (If International) -->
            <?php if ($po['purchase_type'] === 'International'): 
                $exchange_rate = $po['exchange_rate'] ?: 1;
                $converted_value = $po['subtotal'] * $exchange_rate;
            ?>
            <div class="card mb-4 premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-globe me-2 text-info"></i>International Cost Section</h5>
                </div>
                <div class="card-body">
                    <div class="row border-bottom border-secondary pb-3 mb-3">
                        <div class="col-md-3">
                            <label class="small text-muted d-block">Invoice Value (Foreign)</label>
                            <span class="fs-5 fw-bold"><?= $po['currency_symbol'] ?><?= number_format($po['subtotal'], 2) ?></span>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted d-block">Exchange Rate</label>
                            <span class="fs-5"><?= number_format($exchange_rate, 6) ?></span>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted d-block">Converted Value (Base)</label>
                            <span class="fs-5 fw-bold text-success">$<?= number_format($converted_value, 2) ?></span>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted d-block">Freight Cost</label>
                            <span class="fs-5 text-success fw-bold">$<?= number_format($po['freight'], 2) ?></span>
                        </div>
                    </div>
                    
                    <h6 class="text-info fw-bold mb-3 px-2">Local Costs (Base Currency)</h6>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Custom import duty</label>
                            <span class="fs-6">$<?= number_format($po['custom_duty'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Customs processing fees</label>
                            <span class="fs-6">$<?= number_format($po['customs_processing_fees'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Quarantine fees</label>
                            <span class="fs-6">$<?= number_format($po['quarantine_fees'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Excise tax</label>
                            <span class="fs-6">$<?= number_format($po['excise_tax'] ?? 0, 2) ?></span>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Shipping Line - ANL</label>
                            <span class="fs-6">$<?= number_format($po['shipping_line_anl'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Brokerage</label>
                            <span class="fs-6">$<?= number_format($po['brokerage'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Container Detention</label>
                            <span class="fs-6">$<?= number_format($po['container_detention'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Bond refund</label>
                            <span class="fs-6">$<?= number_format($po['bond_refund'] ?? 0, 2) ?></span>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Cartage</label>
                            <span class="fs-6">$<?= number_format($po['cartage'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Inspection fees - Transport</label>
                            <span class="fs-6">$<?= number_format($po['inspection_fees'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Insurance Cost</label>
                            <span class="fs-6">$<?= number_format($po['insurance'] ?? 0, 2) ?></span>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="small text-muted d-block">Other Charges</label>
                            <span class="fs-6">$<?= number_format($po['other_charges'] ?? 0, 2) ?></span>
                        </div>

                        <div class="col-md-4 mb-3 mt-3 offset-md-8">
                            <label class="small text-muted d-block">Total Landed Cost</label>
                            <span class="fs-4 fw-bold text-primary">$<?= number_format($po['landed_cost'] ?? 0, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes Section -->
            <div class="card mb-4 premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-sticky-note me-2 text-info"></i>Notes</h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(escape_html($po['notes'] ?: 'No additional notes.')) ?></p>
                </div>
            </div>

            <!-- Attachments Section -->
            <div class="card mb-4 premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-paperclip me-2 text-info"></i>Attachments</h5>
                </div>
                <div class="card-body">
                    <?php
                    $attachments = db_fetch_all("
                        SELECT a.*, u.username as uploaded_by_name 
                        FROM purchase_order_attachments a
                        LEFT JOIN users u ON a.uploaded_by = u.id
                        WHERE a.po_id = ?
                        ORDER BY a.uploaded_at DESC
                    ", [$po_id]);
                    
                    if ($attachments):
                    ?>
                    <div class="list-group list-group-flush border rounded">
                        <?php foreach ($attachments as $att): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-alt me-2 text-primary"></i>
                                <a href="/MJR/public/<?= $att['file_path'] ?>" target="_blank" class="text-decoration-none fw-bold">
                                    <?= escape_html($att['file_name']) ?>
                                </a>
                                <div class="small text-muted">
                                    Uploaded by <?= escape_html($att['uploaded_by_name'] ?: 'System') ?> on <?= format_datetime($att['uploaded_at']) ?>
                                </div>
                            </div>
                            <div>
                                <a href="/MJR/public/<?= $att['file_path'] ?>" download="<?= escape_html($att['file_name']) ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-download"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted mb-0 small">No attachments uploaded for this order.</p>
                    <?php endif; ?>
                </div>
            </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-4">
            <div class="card premium-card">
                <div class="card-header py-3 text-center">
                    <h5 class="mb-0 fw-bold text-uppercase text-info">Order Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <td class="fw-bold">Subtotal:</td>
                            <td class="text-end"><?= $curr_symbol ?><?= number_format($po['subtotal'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Tax Amount:</td>
                            <td class="text-end"><?= $curr_symbol ?><?= number_format($po['tax_amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Shipping Cost:</td>
                            <td class="text-end"><?= $curr_symbol ?><?= number_format($po['shipping_cost'], 2) ?></td>
                        </tr>
                        <?php if ($po['purchase_type'] === 'International'): ?>
                        <tr>
                            <td class="fw-bold">Freight (Local):</td>
                            <td class="text-end">$<?= number_format($po['freight'], 2) ?></td>
                        </tr>
                        <?php 
                            $other_local = ($po['custom_duty']??0) + ($po['customs_processing_fees']??0) + ($po['quarantine_fees']??0) + ($po['excise_tax']??0) + ($po['shipping_line_anl']??0) + ($po['brokerage']??0) + ($po['container_detention']??0) + ($po['bond_refund']??0) + ($po['cartage']??0) + ($po['inspection_fees']??0) + ($po['insurance']??0) + ($po['other_charges']??0);
                        ?>
                        <tr>
                            <td class="fw-bold">Other Local Costs:</td>
                            <td class="text-end">$<?= number_format($other_local, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-top border-bottom border-primary">
                            <td class="fw-bold fs-5 text-primary">Grand Total:</td>
                            <td class="text-end fw-bold fs-5 text-primary"><?= $curr_symbol ?><?= number_format($po['total_amount'], 2) ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>

    <!-- Order History -->
    <div class="row">
        <div class="col-12">
            <div class="card premium-card mb-4">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2 text-info"></i>Order History & Approval Trail</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <thead class="text-muted small text-uppercase">
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Action / Notes</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $history = db_fetch_all("
                                    SELECT h.*, u.username 
                                    FROM po_history h 
                                    LEFT JOIN users u ON h.changed_by = u.id 
                                    WHERE h.po_id = ? 
                                    ORDER BY h.created_at DESC
                                ", [$po_id]);
                                
                                if ($history):
                                    foreach ($history as $h):
                                ?>
                                <tr>
                                    <td><span class="text-muted"><?= format_datetime($h['created_at']) ?></span></td>
                                    <td>
                                        <span class="badge bg-<?= match($h['status']){
                                            'draft' => 'secondary',
                                            'pending_approval' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            'sent' => 'primary',
                                            'received' => 'success',
                                            default => 'info'
                                        } ?> small"><?= strtoupper($h['status']) ?></span>
                                    </td>
                                    <td><?= escape_html($h['notes']) ?></td>
                                    <td><i class="fas fa-user-circle me-1 text-muted"></i><?= escape_html($h['username'] ?: 'System') ?></td>
                                </tr>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No history available for this order.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

        </div>
    </div>
</div>

<!-- Reject PO Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-danger fw-bold"><i class="fas fa-times-circle me-2"></i>Reject Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="reject_po">
                    
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="rejection_reason" name="rejection_reason" rows="4" placeholder="Please explain why this PO is being rejected..." required></textarea>
                    </div>
                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        The creator will see this reason and may need to edit the PO before resubmitting.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel PO Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-danger fw-bold"><i class="fas fa-ban me-2"></i>Cancel Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="cancel_po">
                    
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">Reason for Cancellation <span class="text-danger">*</span></label>
                        <textarea class="form-control bg-dark text-white border-secondary" id="cancel_reason" name="cancel_reason" rows="4" placeholder="Explain why this order is being cancelled..." required></textarea>
                    </div>
                    <div class="alert alert-danger small">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This action cannot be undone. The stock expectations will be removed.
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Purchase Order Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="emailForm" method="POST" action="send_po_email.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="po_id" value="<?= $po_id ?>">
                    
                    <div class="mb-3">
                        <label for="recipient_email" class="form-label">Recipient Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="recipient_email" name="recipient_email" 
                               value="<?= escape_html($po['email'] ?? '') ?>"
                               placeholder="supplier@example.com" required>
                        <small class="text-muted">Enter the email address to send the invoice to</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cc_email" class="form-label">CC Email (Optional)</label>
                        <input type="text" class="form-control" id="cc_email" name="cc_email" placeholder="cc@example.com, other@example.com">
                        <small class="text-muted">Separate multiple emails with commas</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="email_subject" name="email_subject" 
                               value="Purchase Order - <?= escape_html($po['po_number']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Message</label>
                        <textarea class="form-control" id="email_message" name="email_message" rows="4">Dear <?= escape_html($po['supplier_name']) ?>,

Please find the purchase order details for <?= escape_html($po['po_number']) ?>.

Purchase Order Details:
- PO Number: <?= escape_html($po['po_number']) ?>
- Order Date: <?= format_date($po['order_date']) ?>
- Expected Delivery: <?= format_date($po['expected_delivery_date']) ?>
- Total Amount: <?= $curr_symbol ?><?= number_format($po['total_amount'], 2) ?> <?= $curr_code ?>

Please confirm receipt of this order.

Best regards,
MJR Group ERP</textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        The purchase order will be sent as a formatted email.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendEmail()">
                    <i class="fas fa-paper-plane me-2"></i>Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Workflow Progress Bar Styles */
.workflow-steps {
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
    max-width: 900px;
    margin: 0 auto;
}
.workflow-step {
    text-align: center;
    z-index: 2;
    flex: 1;
}
.step-number {
    font-size: 1rem;
    font-weight: 700;
}
.step-icon {
    width: 50px;
    height: 50px;
    background: #f8f9fa;
    border: 3px solid #dee2e6;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
    font-size: 1.2rem;
    color: #adb5bd;
    position: relative;
    transition: all 0.3s ease;
}
.step-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #6c757d;
}
.step-past .step-icon {
    background: #28a745;
    border-color: #28a745;
    color: white;
}
.step-past .step-label { color: #28a745; }
.step-current .step-icon {
    background: #ffc107;
    border-color: #ffc107;
    color: #212529;
    box-shadow: 0 0 15px rgba(255, 193, 7, 0.4);
    transform: scale(1.1);
}
.step-current .step-label { color: #212529; font-weight: 800; }
.step-rejected .step-icon {
    background: #dc3545;
    border-color: #dc3545;
    color: white;
}
.step-connector {
    height: 4px;
    background: #dee2e6;
    flex: 1;
    margin-top: -30px;
    z-index: 1;
    border-radius: 2px;
}
.connector-past { background: #28a745; }
.check-overlay {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background: white;
    color: #28a745;
    border-radius: 50%;
    font-size: 0.8rem;
    padding: 2px;
    border: 1px solid #28a745;
}

@media print {
    .btn, .no-print, .modal {
        display: none !important;
    }
    .container-fluid {
        width: 100% !important;
        max-width: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        page-break-inside: avoid;
    }
}
</style>


<script>
function openRejectModal() {
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function openCancelModal() {
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function openEmailModal() {
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
}

function sendEmail() {
    const form = document.getElementById('emailForm');
    if (form.checkValidity()) {
        form.submit();
    } else {
        form.reportValidity();
    }
}

function downloadPDF() {
    window.open('print_purchase_order.php?id=<?= $po_id ?>', '_blank');
}

</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>


