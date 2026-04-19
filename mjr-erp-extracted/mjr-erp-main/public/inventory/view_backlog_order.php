<?php
/**
 * View Backlog Order - Full Workflow
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('Backlog Order not found.', 'error');
    redirect('backlog_orders.php');
}

// Fetch Order
$bo = db_fetch("
    SELECT bl.*, 
           i.code as product_code, i.name as product_name, cat.name as category_name,
           so.order_number as sales_order_number,
           u.username as creator_name,
           m.username as manager_name, m.full_name as manager_full_name
    FROM backlog_orders bl
    JOIN inventory_items i ON bl.product_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    LEFT JOIN sales_orders so ON bl.sales_order_id = so.id
    LEFT JOIN users u ON bl.created_by = u.id
    LEFT JOIN users m ON bl.manager_id = m.id
    WHERE bl.id = ?
", [$id]);

if (!$bo) {
    set_flash('Backlog Order not found.', 'error');
    redirect('backlog_orders.php');
}

// Handle Workflow Updates
if (is_post() && isset($_POST['update_status'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        $new_status = post('new_status');
        $valid_statuses = ['Draft', 'Pending', 'Approved', 'Open', 'Cleared'];
        
        if (in_array($new_status, $valid_statuses)) {
            // Restriction: Only assigned manager can approve (Pending -> Approved)
            // Even admin is restricted as per user request
            if ($bo['status'] === 'Pending' && $new_status === 'Approved') {
                if (current_user_id() != $bo['manager_id']) {
                    set_flash('Only the specifically assigned manager can approve this order.', 'error');
                    redirect("view_backlog_order.php?id=$id");
                }
            }

            db_query("UPDATE backlog_orders SET status = ? WHERE id = ?", [$new_status, $id]);
            set_flash("Order status moved to $new_status.", 'success');
            redirect("view_backlog_order.php?id=$id");
        }
    }
}

$page_title = "Backlog Order: " . $bo['backlog_number'];
include __DIR__ . '/../../templates/header.php';
?>

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

    .step-icon {
        width: 60px;
        height: 60px;
        background: #1a1a27;
        border: 3px solid #323248;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 1.4rem;
        color: #a2a3b7;
        position: relative;
        transition: all 0.3s ease;
    }

    .step-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: #6c757d;
        letter-spacing: 0.5px;
    }

    .step-past .step-icon {
        background: #28a745;
        border-color: #28a745;
        color: white;
    }

    .step-past .step-label {
        color: #28a745;
    }

    .step-current .step-icon {
        background: #FFC107;
        border-color: #FFC107;
        color: #000;
        box-shadow: 0 0 20px rgba(255, 193, 7, 0.4);
        transform: scale(1.1);
    }

    .step-current .step-label {
        color: #FFC107;
    }

    .step-connector {
        height: 4px;
        background: #323248;
        flex: 1;
        margin-top: -30px;
        z-index: 1;
        border-radius: 2px;
    }

    .connector-past {
        background: #28a745;
    }

    .check-overlay {
        position: absolute;
        bottom: -2px;
        right: -2px;
        background: #fff;
        color: #28a745;
        border-radius: 50%;
        font-size: 0.9rem;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid #28a745;
    }

    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        margin-bottom: 25px;
    }

    .premium-card .card-header {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        font-weight: 600;
        color: #FFC107;
        padding: 18px 25px;
        border-radius: 12px 12px 0 0;
    }
</style>

<div class="container-fluid py-4">
    <!-- Workflow Progress Bar -->
    <div class="row mb-5">
        <div class="col-12 text-center">
            <div class="workflow-steps">
                <?php
                $steps = [
                    ['id' => 'Draft', 'label' => 'DRAFT', 'icon' => 'pencil-alt'],
                    ['id' => 'Pending', 'label' => 'PENDING', 'icon' => 'hourglass-half'],
                    ['id' => 'Approved', 'label' => 'APPROVED', 'icon' => 'check-double'],
                    ['id' => 'Open', 'label' => 'OPEN', 'icon' => 'layer-group'],
                    ['id' => 'Cleared', 'label' => 'CLEARED', 'icon' => 'calendar-check']
                ];

                $status_rank = [
                    'Draft' => 0,
                    'Pending' => 1,
                    'Approved' => 2,
                    'Open' => 3,
                    'Cleared' => 4
                ];

                $current_rank = $status_rank[$bo['status']] ?? 0;

                foreach ($steps as $index => $step):
                    $is_past = ($index < $current_rank);
                    $is_current = ($index === $current_rank);
                    ?>
                    <div class="workflow-step <?= $is_past ? 'step-past' : ($is_current ? 'step-current' : '') ?>">
                        <div class="step-icon">
                            <i class="fas fa-<?= $step['icon'] ?>"></i>
                            <?php if ($is_past): ?>
                                <div class="check-overlay"><i class="fas fa-check"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="step-label"><?= $step['label'] ?></div>
                    </div>
                    <?php if ($index < count($steps) - 1): ?>
                        <div class="step-connector <?= $is_past ? 'connector-past' : '' ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Header & Quick Info -->
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h2 class="text-white mb-0"><?= $bo['backlog_number'] ?></h2>
            <p class="text-muted">Production Backlog Entry Details</p>
        </div>
        <div class="col-auto">
            <div class="btn-group">
                <a href="backlog_orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back
                </a>

                <?php if ($bo['status'] === 'Draft'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="Pending">
                        <button type="submit" class="btn btn-warning fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Submit for Planning
                        </button>
                    </form>
                <?php elseif ($bo['status'] === 'Pending'): ?>
                    <?php if (current_user_id() == $bo['manager_id']): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="new_status" value="Approved">
                            <button type="submit" class="btn btn-success fw-bold">
                                <i class="fas fa-check-double me-2"></i>Approve Order
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary disabled">
                            <i class="fas fa-lock me-2"></i>Awaiting Assigned Manager's Approval
                        </button>
                    <?php endif; ?>
                <?php elseif ($bo['status'] === 'Approved'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="Open">
                        <button type="submit" class="btn btn-info fw-bold text-dark">
                            <i class="fas fa-play me-2"></i>Open for Production
                        </button>
                    </form>
                <?php elseif ($bo['status'] === 'Open'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="new_status" value="Cleared">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fas fa-check-circle me-2"></i>Clear & Close
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card premium-card border-start border-4 border-warning">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Order Specification</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div
                            class="col-md-6 text-center py-4 bg-dark bg-opacity-25 rounded border border-secondary border-opacity-25">
                            <label class="text-muted small d-block mb-1">PRODUCT</label>
                            <h4 class="text-white fw-bold mb-0"><?= escape_html($bo['product_name']) ?></h4>
                            <code class="text-warning"><?= escape_html($bo['product_code']) ?></code>
                        </div>
                        <div
                            class="col-md-6 text-center py-4 bg-dark bg-opacity-25 rounded border border-secondary border-opacity-25">
                            <label class="text-muted small d-block mb-1">QUANTITY REQUIRED</label>
                            <div class="d-flex align-items-center justify-content-center">
                                <h3 class="text-white fw-bold mb-0 me-3"><?= number_format($bo['quantity'], 2) ?></h3>
                                <button class="btn btn-sm btn-outline-info view-stock-history" 
                                        data-id="<?= $bo['product_id'] ?>" 
                                        title="View Real Stock History">
                                    <i class="fas fa-history"></i>
                                </button>
                            </div>
                            <span class="text-muted small text-uppercase">Units</span>
                        </div>

                        <div class="col-md-4">
                            <label class="text-muted small d-block">Production Priority</label>
                            <span
                                class="badge bg-<?= match ($bo['priority']) { 'Urgent' => 'danger', 'High' => 'warning text-dark', 'Normal' => 'info text-dark', 'Low' => 'secondary', default => 'secondary'} ?> py-2 px-3 fw-bold">
                                <?= strtoupper($bo['priority']) ?>
                            </span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Expected Date</label>
                            <span
                                class="text-white fw-bold h5"><?= date('d F Y', strtotime($bo['production_date'])) ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Category</label>
                            <span class="text-white"><?= escape_html($bo['category_name']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card premium-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Production Notes</h5>
                </div>
                <div class="card-body">
                    <p class="text-light mb-0" style="white-space: pre-line;">
                        <?= $bo['notes'] ?: '<span class="text-muted">No additional instructions provided.</span>' ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card premium-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-link me-2"></i>References</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush bg-transparent">
                        <li class="list-group-item bg-transparent border-secondary text-white ps-0">
                            <label class="text-muted small d-block">Linked Sales Order</label>
                            <?php if ($bo['sales_order_number']): ?>
                                <span class="fw-bold decoration-none"><?= escape_html($bo['sales_order_number']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">Internal Inventory Demand</span>
                            <?php endif; ?>
                        </li>
                        <li class="list-group-item bg-transparent border-secondary text-white ps-0">
                            <label class="text-muted small d-block">Created By</label>
                            <span class="fw-bold"><?= escape_html($bo['creator_name']) ?></span>
                        </li>
                        <li class="list-group-item bg-transparent border-secondary text-white ps-0">
                            <label class="text-muted small d-block">Assigned Manager</label>
                            <span class="fw-bold text-info"><?= escape_html($bo['manager_full_name'] ?: $bo['manager_name']) ?></span>
                        </li>
                        <li class="list-group-item bg-transparent border-secondary text-white ps-0">
                            <label class="text-muted small d-block">Creation Date</label>
                            <span class="text-white-50"><?= format_datetime($bo['created_at']) ?></span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="alert alert-dark border-warning border-opacity-25 bg-warning bg-opacity-10 text-white-50 small">
                <i class="fas fa-shield-alt me-2 text-warning"></i>
                Tracking order. Status changes here help production staff prioritize tasks.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>