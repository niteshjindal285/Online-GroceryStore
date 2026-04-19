<?php
/**
 * Warehouse Transfer Index / Management Page
 * Mimics GSRN Index style for consistency
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Manage Warehouse Transfers - MJR Group ERP';

// Filter logic
$status_filter = get_param('status');
$params = [];
$where = "WHERE 1=1" . db_where_company('h');

if ($status_filter) {
    if ($status_filter === 'pending') {
        $where .= " AND h.status = 'pending_approval'";
    } else {
        $where .= " AND h.status = ?";
        $params[] = $status_filter;
    }
}

// If user is not manager/admin, only show their own requests
if (!has_role('manager') && !has_role('admin')) {
    $where .= " AND h.requested_by = ?";
    $params[] = current_user_id();
}

// Get Transfers
$transfers = db_fetch_all("
    SELECT h.*, u.username as requester_name, m.username as manager_name,
           sl.name as source_warehouse, dl.name as dest_warehouse,
           (SELECT COUNT(*) FROM transfer_items WHERE transfer_id = h.id) as item_count
    FROM transfer_headers h
    LEFT JOIN users u ON h.requested_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN locations sl ON h.source_location_id = sl.id
    LEFT JOIN locations dl ON h.dest_location_id = dl.id
    $where
    ORDER BY h.created_at DESC
", $params);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4 text-white">
        <div class="col">
            <h2 class="fw-bold mb-0">Manage Warehouse Transfers</h2>
            <p class="text-secondary mb-0">Internal inventory movements and approval workflow</p>
        </div>
        <div class="col-auto">
            <a href="transfer_stock.php" class="btn btn-info shadow-sm text-dark fw-bold">
                <i class="fas fa-plus me-2"></i>New Transfer Request
            </a>
        </div>
    </div>

    <style>
        .premium-card {
            border: none;
            border-radius: 12px;
            background: #1e1e2d;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
        }
    </style>

    <!-- Filters -->
    <div class="card premium-card border-top border-4 border-info">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end text-white">
                <div class="col-md-3">
                    <label class="form-label text-info small fw-bold">Status Filter</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending" <?= ($status_filter === 'pending' || $status_filter === 'pending_approval') ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed
                        </option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transfers Table -->
    <div class="card premium-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead class="table-dark border-secondary">
                        <tr>
                            <th>Transfer #</th>
                            <th>Date</th>
                            <th>From (Source)</th>
                            <th>To (Dest)</th>
                            <th class="text-center">Items</th>
                            <th>Requested By</th>
                            <th>Pending With</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transfers)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p>No transfer requests found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transfers as $t): ?>
                                <tr>
                                    <td class="fw-bold text-info"><?= escape_html($t['transfer_number']) ?></td>
                                    <td><?= format_date($t['transfer_date']) ?></td>
                                    <td><i
                                            class="fas fa-warehouse me-1 text-secondary small"></i><?= escape_html($t['source_warehouse']) ?>
                                    </td>
                                    <td><i
                                            class="fas fa-warehouse me-1 text-secondary small"></i><?= escape_html($t['dest_warehouse']) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-dark border border-secondary"><?= $t['item_count'] ?></span>
                                    </td>
                                    <td><?= escape_html($t['requester_name']) ?></td>
                                    <td>
                                        <?php if ($t['status'] === 'pending_approval'): ?>
                                            <span class="text-warning small"><i
                                                    class="fas fa-user-clock me-1"></i><?= escape_html($t['manager_name'] ?: 'N/A') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php
                                        $badge = match ($t['status']) {
                                            'draft' => 'secondary',
                                            'pending_approval' => 'warning',
                                            'approved', 'completed' => 'success',
                                            'cancelled', 'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span
                                            class="badge bg-<?= $badge ?>"><?= strtoupper(str_replace('_', ' ', $t['status'])) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="view_transfer.php?id=<?= $t['id'] ?>"
                                                class="btn btn-sm btn-info text-dark fw-bold" title="View Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            <?php if ($t['status'] === 'pending_approval' && $t['manager_id'] == current_user_id()): ?>
                                                <a href="view_transfer.php?id=<?= $t['id'] ?>"
                                                    class="btn btn-sm btn-success text-dark fw-bold" title="Approve/Reject">
                                                    <i class="fas fa-check-circle me-1"></i>Action
                                                </a>
                                            <?php endif; ?>
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
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>