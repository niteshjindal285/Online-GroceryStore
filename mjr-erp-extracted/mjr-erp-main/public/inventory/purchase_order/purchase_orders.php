<?php
/**
 * Purchase Orders List
 * Manage purchase orders
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Purchase Orders - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view purchase orders.', 'warning');
    redirect(url('index.php'));
}
$company_name = active_company_name('Current Company');

// ── Helper: clean badge class ──────────────────────────────────────────────────
function po_badge($status)
{
    return match ($status) {
        'draft' => 'secondary',
        'pending_approval' => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
        'sent' => 'primary',
        'confirmed' => 'info',
        'partially_received' => 'warning',
        'received' => 'success',
        'cancelled' => 'danger',
        default => 'secondary',
    };
}

function po_can_delete($status)
{
    return strtolower(trim($status)) === 'draft';
}


// ── Handle DELETE ──────────────────────────────────────────────────────────────
if (is_post() && isset($_POST['delete_id'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $delete_id = intval(post('delete_id'));

            // Server-side guard: only DRAFT POs from the selected company may be deleted
            $check = db_fetch("SELECT status FROM purchase_orders WHERE id = ? AND company_id = ?", [$delete_id, $company_id]);
            if (!$check) {
                throw new Exception('Purchase order not found.');
            }
            if (!po_can_delete($check['status'])) {
                throw new Exception('Only Draft purchase orders can be deleted.');
            }

            db_begin_transaction();
            db_query("DELETE FROM purchase_order_lines WHERE po_id = ?", [$delete_id]);
            db_query("DELETE FROM purchase_orders WHERE id = ?", [$delete_id]);
            db_commit();

            set_flash('Purchase order deleted successfully!', 'success');
            redirect('purchase_orders.php');
        } catch (Exception $e) {
            db_rollback();
            log_error("Error deleting PO: " . $e->getMessage());
            set_flash($e->getMessage(), 'error');
        }
    }
}

// ── Filters ────────────────────────────────────────────────────────────────────
$search = get_param('search', '');
$status_filter = get_param('status', '');
$supplier_filter = get_param('supplier_id', '');
$date_from = get_param('date_from', '');
$date_to = get_param('date_to', '');

// Quick presets
$preset = get_param('preset', '');
if ($preset === 'this_month') {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
} elseif ($preset === 'last_month') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to = date('Y-m-t', strtotime('last day of last month'));
} elseif ($preset === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to = date('Y-12-31');
}

// ── Build query ───────────────────────────────────────────────────────────────
$sql = "
    SELECT po.*, s.name as supplier_name, s.supplier_code,
           (SELECT COUNT(*) FROM purchase_order_lines WHERE po_id = po.id) as line_count
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.company_id = ?
";
$params = [$company_id];

if (!empty($search)) {
    $sql .= " AND (po.po_number LIKE ? OR s.name LIKE ?)";
    $t = "%$search%";
    $params[] = $t;
    $params[] = $t;
}
if ($status_filter !== '') {
    $sql .= " AND po.status = ?";
    $params[] = $status_filter;
}
if ($supplier_filter !== '') {
    $sql .= " AND po.supplier_id = ?";
    $params[] = $supplier_filter;
}
if (!empty($date_from)) {
    $sql .= " AND po.order_date >= ?";
    $params[] = to_db_date($date_from);
}
if (!empty($date_to)) {
    $sql .= " AND po.order_date <= ?";
    $params[] = to_db_date($date_to);
}

$sql .= " ORDER BY po.order_date DESC, po.po_number DESC";
$purchase_orders = db_fetch_all($sql, $params);

// Suppliers for filter (can be global or company specific, but here we show all active)
$suppliers = db_fetch_all("SELECT id, supplier_code, name FROM suppliers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Quick summary counts
$summary = db_fetch("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status='draft'              THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN status='confirmed'          THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status='partially_received' THEN 1 ELSE 0 END) as partial,
        SUM(CASE WHEN status IN ('draft','sent','confirmed','partially_received') AND expected_delivery_date < CURDATE() THEN 1 ELSE 0 END) as overdue
    FROM purchase_orders
    WHERE company_id = ?
", [$company_id]);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3 align-items-center">
        <div class="col">
            <h2><i class="fas fa-file-invoice me-2"></i>Purchase Orders</h2>
            <p class="text-muted mb-0">Showing records for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <?php if (has_permission('manage_procurement')): ?>
            <div class="col-auto">
                <a href="add_purchase_order.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Purchase Order
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Summary KPIs -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-2">
            <div class="card bg-secondary text-center py-2">
                <div class="small text-uppercase opacity-75">Drafts</div>
                <div class="fs-4 fw-bold"><?= $summary['drafts'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card bg-info text-center py-2">
                <div class="small text-uppercase opacity-75">Confirmed</div>
                <div class="fs-4 fw-bold"><?= $summary['confirmed'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card bg-warning text-center py-2">
                <div class="small text-uppercase opacity-75">Partial</div>
                <div class="fs-4 fw-bold"><?= $summary['partial'] ?? 0 ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="card <?= ($summary['overdue'] ?? 0) > 0 ? 'bg-danger' : 'bg-success' ?> text-center py-2">
                <div class="small text-uppercase opacity-75">Overdue</div>
                <div class="fs-4 fw-bold"><?= $summary['overdue'] ?? 0 ?></div>
            </div>
        </div>
    </div>

    <!-- Filter Controls -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search" value="<?= escape_html($search) ?>"
                        placeholder="PO# or supplier">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Supplier</label>
                    <select class="form-select" name="supplier_id">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $supplier_filter == $s['id'] ? 'selected' : '' ?>>
                                <?= escape_html($s['supplier_code']) ?> - <?= escape_html($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= $status_filter === 'pending_approval' ? 'selected' : '' ?>>Pending
                            Approval</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="sent" <?= $status_filter === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="partially_received" <?= $status_filter === 'partially_received' ? 'selected' : '' ?>>
                            Partial</option>
                        <option value="received" <?= $status_filter === 'received' ? 'selected' : '' ?>>Received</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>

                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="text" class="form-control datepicker" name="date_from" value="<?= !empty($date_from) ? format_date($date_from) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="text" class="form-control datepicker" name="date_to" value="<?= !empty($date_to) ? format_date($date_to) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2 d-flex gap-1 flex-wrap">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                    <a href="purchase_orders.php" class="btn btn-outline-secondary btn-sm">Clear</a>
                </div>
            </form>
            <!-- Quick Presets -->
            <div class="mt-2 d-flex gap-2">
                <a href="?preset=this_month" class="btn btn-outline-secondary btn-sm">This Month</a>
                <a href="?preset=last_month" class="btn btn-outline-secondary btn-sm">Last Month</a>
                <a href="?preset=this_year" class="btn btn-outline-secondary btn-sm">This Year</a>
            </div>
        </div>
    </div>

    <!-- Purchase Orders List -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Purchase Orders
                <span class="badge bg-secondary ms-1"><?= count($purchase_orders) ?></span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($purchase_orders)): ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover" id="purchaseOrdersTable">
                        <thead>
                            <tr>
                                <th>PO Number</th>
                                <th>Supplier</th>
                                <th>Type</th>
                                <th>Order Date</th>
                                <th>Expected Delivery</th>
                                <th>Items</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchase_orders as $po):
                                $is_overdue = !in_array($po['status'], ['received', 'cancelled'])
                                    && !empty($po['expected_delivery_date'])
                                    && strtotime($po['expected_delivery_date']) < time();
                                ?>
                                <tr <?= $is_overdue ? 'class="table-danger"' : '' ?>>
                                    <td><strong><?= escape_html($po['po_number']) ?></strong></td>
                                    <td>
                                        <?= escape_html($po['supplier_name']) ?><br>
                                        <small class="text-muted"><?= escape_html($po['supplier_code']) ?></small>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?= $po['purchase_type'] ?></span></td>
                                    <td data-order="<?= $po['order_date'] ?>"><?= format_date($po['order_date']) ?></td>
                                    <td data-order="<?= $po['expected_delivery_date'] ?>">
                                        <?= format_date($po['expected_delivery_date']) ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="badge bg-danger ms-1">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-info"><?= $po['line_count'] ?></span></td>
                                    <td>
                                        <strong>
                                            $<?= number_format($po['total_amount'], 2) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= po_badge($po['status']) ?>">
                                            <?= ucfirst(str_replace('_', ' ', $po['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_purchase_order.php?id=<?= $po['id'] ?>" class="btn btn-outline-info"
                                                title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (has_permission('manage_procurement')): ?>
                                                <?php if (in_array($po['status'], ['draft', 'sent'])): ?>
                                                    <a href="edit_purchase_order.php?id=<?= $po['id'] ?>"
                                                        class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (in_array($po['status'], ['confirmed', 'partially_received'])): ?>
                                                    <a href="receive_po.php?id=<?= $po['id'] ?>" class="btn btn-outline-success"
                                                        title="Receive Goods">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (po_can_delete($po['status'])): ?>
                                                    <button type="button" class="btn btn-outline-danger" title="Delete"
                                                        onclick="if(confirm('Delete this draft PO?')) { document.getElementById('del-<?= $po['id'] ?>').submit(); }">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <form id="del-<?= $po['id'] ?>" method="POST" style="display:none;">
                                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                        <input type="hidden" name="delete_id" value="<?= $po['id'] ?>">
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No purchase orders found</p>
                    <a href="add_purchase_order.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create First Purchase Order
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#purchaseOrdersTable').DataTable({
        'order': [[3, 'desc'], [0, 'desc']],
        'pageLength': 25
    });
});
</script>
";
include __DIR__ . '/../../../templates/footer.php';
?>
