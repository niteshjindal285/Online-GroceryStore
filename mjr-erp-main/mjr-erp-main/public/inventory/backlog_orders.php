<?php
/**
 * Backlog Orders Management - Internal Production Tracker
 * Track production demand without affecting inventory
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Backlog Orders - MJR Group ERP';

// ─── Handle Clearance (Closing Order) ──────────────────────────────────────────
if (is_post() && isset($_POST['clear_id'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $clear_id = intval(post('clear_id'));

            db_query("UPDATE backlog_orders SET status = 'Cleared' WHERE id = ?", [$clear_id]);
            set_flash('Backlog order cleared and closed.', 'success');
        } catch (Exception $e) {
            set_flash($e->getMessage(), 'error');
        }
    }
    redirect('backlog_orders.php');
}

// ─── Handle Delete ─────────────────────────────────────────────────────────────
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        $delete_id = intval(post('delete_id'));
        db_query("DELETE FROM backlog_orders WHERE id = ?", [$delete_id]);
        set_flash('Backlog order removed.', 'success');
    }
    redirect('backlog_orders.php');
}

// Get filter parameters
$status_filter = get_param('status', 'Open');
$priority_filter = get_param('priority', '');

// Build query
$sql = "
    SELECT bl.*, 
           i.code as product_code, i.name as product_name, cat.name as category_name,
           so.order_number as sales_order_number,
           u.username as creator_name
    FROM backlog_orders bl
    JOIN inventory_items i ON bl.product_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    LEFT JOIN sales_orders so ON bl.sales_order_id = so.id
    LEFT JOIN users u ON bl.created_by = u.id
    WHERE 1=1
";

$params = [];
if (!empty($status_filter)) {
    $sql .= " AND bl.status = ?";
    $params[] = $status_filter;
}
if (!empty($priority_filter)) {
    $sql .= " AND bl.priority = ?";
    $params[] = $priority_filter;
}

$sql .= " ORDER BY bl.production_date ASC, bl.created_at DESC";
$backlog_orders = db_fetch_all($sql, $params);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-layer-group me-2 text-warning"></i>Backlog Orders
            </h2>
            <p class="text-muted mb-0">Internal Production Tracking (Non-Inventory)</p>
        </div>
        <div>
            <a href="add_backlog_order.php" class="btn btn-warning fw-bold">
                <i class="fas fa-plus me-2"></i>New Backlog Entry
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card bg-dark border-secondary mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Status</label>
                    <select class="form-select bg-dark text-white border-secondary" name="status">
                        <option value="">All Statuses</option>
                        <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Approved" <?= $status_filter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="Open" <?= $status_filter === 'Open' ? 'selected' : '' ?>>Open (Active)</option>
                        <option value="Cleared" <?= $status_filter === 'Cleared' ? 'selected' : '' ?>>Cleared (Closed)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small fw-bold">Priority</label>
                    <select class="form-select bg-dark text-white border-secondary" name="priority">
                        <option value="">All Priorities</option>
                        <option value="Low" <?= $priority_filter === 'Low' ? 'selected' : '' ?>>Low</option>
                        <option value="Normal" <?= $priority_filter === 'Normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="High" <?= $priority_filter === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Urgent" <?= $priority_filter === 'Urgent' ? 'selected' : '' ?>>Urgent</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-info w-100 fw-bold">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="backlog_orders.php" class="btn btn-outline-secondary w-100">
                        <i class="fas fa-undo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Backlog Table -->
    <div class="card bg-dark border-secondary shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr class="text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">
                            <th class="ps-4">Order No</th>
                            <th>Product Details</th>
                            <th class="text-center">Qty</th>
                            <th>Target Date</th>
                            <th class="text-center">Priority</th>
                            <th>Reference</th>
                            <th class="text-center">Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($backlog_orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
                                    <p>No backlog orders found for the selected criteria.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backlog_orders as $bo): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-info"><?= escape_html($bo['backlog_number']) ?></td>
                                    <td>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold"><?= escape_html($bo['product_name']) ?></div>
                                                <small class="text-muted"><?= escape_html($bo['product_code']) ?> | <?= escape_html($bo['category_name']) ?></small>
                                            </div>
                                            <button class="btn btn-link text-info p-0 ms-2 view-stock-history" 
                                                    data-id="<?= $bo['product_id'] ?>" 
                                                    title="View Real Stock History">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary px-3"><?= number_format($bo['quantity'], 2) ?></span>
                                    </td>
                                    <td>
                                        <div class="<?= (strtotime($bo['production_date']) < time() && $bo['status'] === 'Open') ? 'text-danger fw-bold' : '' ?>">
                                            <?= format_date($bo['production_date']) ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $p_class = match($bo['priority']) {
                                            'Urgent' => 'bg-danger',
                                            'High' => 'bg-warning text-dark',
                                            'Normal' => 'bg-info text-dark',
                                            'Low' => 'bg-secondary',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $p_class ?>"><?= $bo['priority'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($bo['sales_order_number']): ?>
                                            <span class="text-white-50 small"><i class="fas fa-file-invoice me-1"></i>SO: <?= escape_html($bo['sales_order_number']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Internal Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($bo['status'] === 'Draft'): ?>
                                            <span class="badge bg-secondary px-3">DRAFT</span>
                                        <?php elseif ($bo['status'] === 'Pending'): ?>
                                            <span class="badge bg-warning text-dark px-3">PENDING</span>
                                        <?php elseif ($bo['status'] === 'Approved'): ?>
                                            <span class="badge bg-success px-3">APPROVED</span>
                                        <?php elseif ($bo['status'] === 'Open'): ?>
                                            <span class="badge bg-outline-info border border-info text-info px-3">OPEN</span>
                                        <?php else: ?>
                                            <span class="badge bg-success px-3">CLEARED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_backlog_order.php?id=<?= $bo['id'] ?>" class="btn btn-outline-info" title="View & Manage Workflow">
                                                <i class="fas fa-eye me-1"></i> View
                                            </a>
                                            <?php if ($bo['status'] === 'Open'): ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Mark this production as finished?')">
                                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                    <input type="hidden" name="clear_id" value="<?= $bo['id'] ?>">
                                                    <button type="submit" class="btn btn-success" title="Clear / Close">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this record?')">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="delete_id" value="<?= $bo['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Delete">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Notes Panel -->
    <div class="alert alert-dark mt-4 border-warning border-opacity-25 bg-warning bg-opacity-10 text-white-50 small">
        <i class="fas fa-info-circle me-2 text-warning"></i>
        <strong>Note:</strong> Backlog orders are purely for tracking production tasks. Clearing an order does <strong>not</strong> deduct materials or add items to inventory. Use <strong>Production Orders</strong> if inventory movement is required.
    </div>
</div>

<!-- Stock History Modal -->
<div class="modal fade" id="stockHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-history me-2 text-info"></i>Product Stock History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="historyModalBody">
                <!-- Content loaded via AJAX -->
                <div class="text-center py-5">
                    <div class="spinner-border text-info" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('.view-stock-history').on('click', function() {
        const itemId = $(this).data('id');
        $('#historyModalBody').html('<div class="text-center py-5"><div class="spinner-border text-info" role="status"></div></div>');
        $('#stockHistoryModal').modal('show');
        
        $.get('ajax_stock_history.php', { id: itemId, _: Date.now() }, function(data) {
            $('#historyModalBody').html(data);
        }).fail(function() {
            $('#historyModalBody').html('<div class="alert alert-danger">Failed to load history data.</div>');
        });
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
