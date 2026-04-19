<?php
/**
 * GSRN / Stock Entry List
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Manage GSRNs - MJR Group ERP';

// Filter logic
$status_filter = get_param('status');
$params = [];
$where = "WHERE 1=1" . db_where_company('h');

if ($status_filter) {
    $where .= " AND h.status = ?";
    $params[] = $status_filter;
}

// If user is not manager/admin, only show their own GSRNs
if (!has_role('manager') && !has_role('admin')) {
    $where .= " AND h.created_by = ?";
    $params[] = current_user_id();
}

// Get GSRNs
$gsrns = db_fetch_all("
    SELECT h.*, u.username as creator_name, m.username as manager_name,
           w.name as warehouse_name
    FROM gsrn_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN locations w ON h.warehouse_id = w.id
    $where
    ORDER BY h.created_at DESC
", $params);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="row align-items-center mb-4">
        <div class="col">
            <h2 class="text-white mb-0">Manage GSRN / Stock Entries</h2>
            <p class="text-muted mb-0">View, edit, and approve Goods Received Notes</p>
        </div>
        <div class="col-auto">
            <a href="add_gsrn.php" class="btn btn-info shadow-sm text-dark fw-bold">
                <i class="fas fa-plus me-2"></i>New Stock Entry
            </a>
        </div>
    </div>

    <style>
        .premium-card {
            border: none;
            border-radius: 12px;
            background: #1e1e2d;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        .premium-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.4);
        }
    </style>

    <!-- Filters -->
    <div class="card premium-card">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label text-info small fw-bold">Status Filter</label>
                    <select name="status" class="form-select bg-dark text-white border-secondary">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="pending_approval" <?= $status_filter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
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

    <!-- GSRN Table -->
    <div class="card premium-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead class="table-dark border-secondary">
                        <tr>
                            <th>GSRN #</th>
                            <th>Date</th>
                            <th>Warehouse</th>
                            <th>Type</th>
                            <th>Created By</th>
                            <th>Pending With</th>
                            <th class="text-end">Landed Cost</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($gsrns)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                                    <p>No GSRN entries found matching your filters.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($gsrns as $g): ?>
                                <tr>
                                    <td class="fw-bold text-info"><?= escape_html($g['gsrn_number']) ?></td>
                                    <td><?= format_date($g['gsrn_date']) ?></td>
                                    <td><?= escape_html($g['warehouse_name']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $g['transaction_type'] === 'Purchase' ? 'info' : 'warning' ?> text-dark">
                                            <?= $g['transaction_type'] ?>
                                        </span>
                                    </td>
                                    <td><?= escape_html($g['creator_name']) ?></td>
                                    <td>
                                        <?php if ($g['status'] === 'pending_approval'): ?>
                                            <span class="text-warning"><i class="fas fa-user-clock me-1"></i><?= escape_html($g['manager_name'] ?: 'N/A') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">--</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold"><?= format_currency($g['final_landed_cost'], $g['currency']) ?></td>
                                    <td class="text-center">
                                        <?php
                                        $badge = match($g['status']) {
                                            'draft' => 'secondary',
                                            'pending_approval' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= strtoupper($g['status']) ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="view_gsrn.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-info text-white" title="View Details">
                                                <i class="fas fa-eye me-1"></i>View
                                            </a>
                                            <?php if (in_array($g['status'], ['draft', 'rejected']) && ($g['created_by'] == current_user_id() || has_role('admin'))): ?>
                                                <a href="edit_gsrn.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-primary" title="Edit GSRN">
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($g['status'] === 'pending_approval' && (has_role('manager') || has_role('admin')) && $g['manager_id'] == current_user_id()): ?>
                                                <a href="view_gsrn.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-success" title="Approve/Reject">
                                                    <i class="fas fa-check-circle me-1"></i>Approve
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

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
