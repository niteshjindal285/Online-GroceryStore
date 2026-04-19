<?php
/**
 * Production Orders Management
 * List, filter, and manage production orders
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';
require_once __DIR__ . '/../../includes/production_functions.php';

require_login();

$page_title = 'Production Orders - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view production orders.', 'warning');
    redirect(url('index.php'));
}
$company_name = active_company_name('Current Company');

// ─── Handle Delete ─────────────────────────────────────────────────────────────
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $delete_id = intval(post('delete_id'));
            $wo = db_fetch("SELECT wo.status, wo.wo_number FROM work_orders wo LEFT JOIN inventory_items i ON wo.product_id = i.id WHERE wo.id = ? AND i.company_id = ?", [$delete_id, $company_id]);
            
            if (!$wo) {
                throw new Exception('Production order not found.');
            }
            if ($wo['status'] === 'completed') {
                throw new Exception("Cannot delete completed Production Order {$wo['wo_number']}. Completed production records must be preserved.");
            }
            
            db_begin_transaction();
            db_query("DELETE FROM work_order_progress WHERE work_order_id = ?", [$delete_id]); // Table names usually stay but I'll check if table renamed too (user didn't ask)
            db_query("DELETE FROM work_orders WHERE id = ?", [$delete_id]);
            db_commit();
            
            set_flash('Production order deleted successfully!', 'success');
            redirect('production_orders.php');
        } catch (Exception $e) {
            db_rollback();
            log_error("Error deleting production order: " . $e->getMessage());
            set_flash($e->getMessage(), 'error');
        }
    }
    redirect('production_orders.php');
}

// ─── Handle Status Update (Start / Complete / Cancel) ─────────────────────────
if (is_post() && isset($_POST['update_status'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        $wo_id     = intval(post('wo_id'));
        $new_status = post('new_status');
        
        db_begin_transaction();
        try {
            $wo = db_fetch("
                SELECT wo.*, i.name as product_name
                FROM work_orders wo
                LEFT JOIN inventory_items i ON wo.product_id = i.id
                WHERE wo.id = ? AND i.company_id = ?
            ", [$wo_id, $company_id]);
            if (!$wo) throw new Exception('Production order not found.');
            
            // ── On COMPLETION: deduct BOM materials + add finished product to inventory
            if ($new_status === 'completed' && in_array($wo['status'], ['in_progress', 'paused'], true)) {
                $bom_items = db_fetch_all("
                    SELECT bom.component_id, bom.quantity_required,
                           comp.name as component_name, comp.unit_of_measure,
                           comp.cost_price
                    FROM bill_of_materials bom
                    JOIN inventory_items comp ON bom.component_id = comp.id
                    WHERE bom.product_id = ? AND bom.is_active = 1
                ", [$wo['product_id']]);
                
                $location_id = intval($wo['location_id']);
                if ($location_id <= 0) {
                    $location_id = inventory_default_location_id();
                }
                $user_id = current_user_id();
                $ref     = $wo['wo_number'];
                
                // 1. Deduct BOM materials + 2. Add finished product
                deduct_production_stock($wo_id);
                add_finished_goods_stock($wo_id);
                
                $finished_qty = floatval($wo['quantity']);
                
                db_query("UPDATE work_orders SET status = 'completed', completion_date = CURRENT_DATE WHERE id = ?", [$wo_id]);
                set_flash("Production Order {$wo['wo_number']} completed! BOM materials deducted and {$finished_qty} unit(s) added to inventory.", 'success');
                
            } else {
                // Simple status change (planned → in_progress, or cancel)
                $allowed = ['draft', 'planned', 'in_progress', 'paused', 'cancelled'];
                if (!in_array($new_status, $allowed)) {
                    throw new Exception('Invalid status.');
                }
                if ($wo['status'] === 'completed') {
                    throw new Exception('Completed production orders cannot be changed.');
                }
                db_query("UPDATE work_orders SET status = ? WHERE id = ?", [$new_status, $wo_id]);
                set_flash('Production order status updated successfully!', 'success');
            }
            
            db_commit();
        } catch (Exception $e) {
            db_rollback();
            log_error("Error updating WO status: " . $e->getMessage());
            set_flash('Error: ' . $e->getMessage(), 'error');
        }
    }
    redirect('production_orders.php');
}

// Get filter parameters
$status = get_param('status', '');
$priority = get_param('priority', '');
$date_from = get_param('date_from', '');

// Build query with filters
$sql = "
    SELECT wo.*, 
           i.code as product_code, i.name as product_name, i.unit_of_measure,
           l.name as location_name,
           u.username as created_by_name
    FROM work_orders wo
    LEFT JOIN inventory_items i ON wo.product_id = i.id
    LEFT JOIN locations l ON wo.location_id = l.id
    LEFT JOIN users u ON wo.created_by = u.id
    WHERE i.company_id = ?
";

$params = [$company_id];

if (!empty($status)) {
    $sql .= " AND wo.status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $sql .= " AND wo.priority = ?";
    $params[] = $priority;
}

if (!empty($date_from)) {
    $sql .= " AND wo.due_date >= ?";
    $params[] = to_db_date($date_from);
}

$sql .= " ORDER BY wo.due_date ASC, wo.created_at DESC";

$work_orders = db_fetch_all($sql, $params);

// Get products for dropdown
$products = db_fetch_all("SELECT id, code, name, unit_of_measure FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Get locations for dropdown
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<style>
html[data-bs-theme="dark"],
html[data-app-theme="dark"] {
    --po-panel-bg: #212529;
    --po-panel-border: rgba(255,255,255,0.12);
    --po-panel-text: #f8fafc;
    --po-panel-muted: #9aa4b2;
    --po-table-bg: #212529;
}

html[data-bs-theme="light"],
html[data-app-theme="light"] {
    --po-panel-bg: #ffffff;
    --po-panel-border: #d0d7de;
    --po-panel-text: #212529;
    --po-panel-muted: #5f6b7a;
    --po-table-bg: #ffffff;
}

.production-history-panel.bg-dark {
    background-color: var(--po-panel-bg) !important;
}

.production-history-panel.border-secondary,
.production-history-panel .border-secondary {
    border-color: var(--po-panel-border) !important;
}

.production-history-panel .card-header,
.production-history-panel .card-body,
.production-history-panel .text-white,
.production-history-panel .table-dark,
.production-history-panel .table-dark th,
.production-history-panel .table-dark td {
    color: var(--po-panel-text) !important;
}

.production-history-panel .text-muted {
    color: var(--po-panel-muted) !important;
}

.production-history-panel .table-dark,
.production-history-panel .table.table-dark {
    --bs-table-bg: var(--po-table-bg);
    --bs-table-color: var(--po-panel-text);
    --bs-table-border-color: var(--po-panel-border);
}
</style>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-tasks me-2"></i>Production Orders</h2>
            <p class="text-muted mb-0">Showing records for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <?php if (has_permission('manage_production')): ?>
        <div class="col-auto">
            <a href="add_production_order.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Production Order
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="planned" <?= $status === 'planned' ? 'selected' : '' ?>>Planned</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="paused" <?= $status === 'paused' ? 'selected' : '' ?>>Paused</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="">All Priorities</option>
                        <option value="urgent" <?= $priority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        <option value="high" <?= $priority === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="normal" <?= $priority === 'normal' ? 'selected' : '' ?>>Normal</option>
                        <option value="low" <?= $priority === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">Due Date From</label>
                    <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?= !empty($date_from) ? format_date($date_from) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="production_orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>



<!-- Production History Panel -->
<div class="container-fluid mt-4 mb-4">
    <div class="card bg-dark border-secondary production-history-panel">
        <div class="card-header d-flex justify-content-between align-items-center border-secondary">
            <h5 class="mb-0 text-white"><i class="fas fa-history me-2"></i>Production History</h5>
            <small class="text-muted">Last 10 production orders</small>
        </div>
        <div class="card-body p-0">
            <?php
            $history = db_fetch_all("
                SELECT wo.id, wo.wo_number, wo.quantity, wo.status, wo.created_at,
                       i.code as product_code, i.name as product_name
                FROM work_orders wo
                LEFT JOIN inventory_items i ON wo.product_id = i.id
                WHERE i.company_id = ?
                ORDER BY wo.id DESC
                LIMIT 10
            ", [$company_id]);
            ?>
            <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Production Order</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td>
                                <a href="view_production_order.php?id=<?= $h['id'] ?>" class="text-info fw-semibold text-decoration-none">
                                    <?= escape_html($h['wo_number']) ?>
                                </a>
                            </td>
                            <td><?= escape_html($h['product_code']) ?> - <?= escape_html($h['product_name']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark"><?= number_format($h['quantity'], 2) ?></span>
                            </td>
                            <td>
                                <?php
                                $s = $h['status'];
                                $badge = match($s) {
                                    'completed' => 'success',
                                    'in_progress' => 'primary',
                                    'planned' => 'secondary',
                                    'cancelled' => 'danger',
                                    'paused' => 'warning',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badge ?>"><?= ucfirst(str_replace('_', ' ', $s)) ?></span>
                            </td>
                            <td><?= date('d-M-Y', strtotime($h['created_at'])) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="view_production_order.php?id=<?= $h['id'] ?>" class="btn btn-outline-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_production_order.php?id=<?= $h['id'] ?>" class="btn btn-outline-success" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" title="Delete" 
                                            onclick="if(confirm('Are you sure you want to delete this production order?')) { document.getElementById('delete-history-<?= $h['id'] ?>').submit(); }">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <form id="delete-history-<?= $h['id'] ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="delete_id" value="<?= $h['id'] ?>">
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4 text-muted">
                <i class="fas fa-history fa-2x mb-2"></i>
                <p class="mb-0">No production history yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#workOrdersTable').DataTable({
        'order': [[4, 'asc']], // Sort by due date
        'pageLength': 25
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
