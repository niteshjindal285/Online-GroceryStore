<?php
/**
 * Stock Take List / Dashboard
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();

$page_title = 'Stock Take Management - MJR Group ERP';

// Fetch stock takes with variance summary
$stock_takes = db_fetch_all("
    SELECT st.*, l.name as location_name, u.username as creator_name,
           COUNT(sti.id) as item_count,
           SUM(sti.total_variance_value) as total_variance,
           app.username as approver_name,
           st.notes as st_notes
    FROM stock_take_headers st
    JOIN locations l ON st.location_id = l.id
    LEFT JOIN users u ON st.created_by = u.id
    LEFT JOIN users app ON st.approved_by = app.id
    LEFT JOIN stock_take_items sti ON sti.stock_take_id = st.id
    GROUP BY st.id, l.name, u.username, app.username
    ORDER BY st.created_at DESC
");

// Check for active stock take
$active_stock_take = inventory_get_active_stock_take();

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-clipboard-check me-2 text-warning"></i>Physical Stock Take
            </h2>
            <p class="text-muted mb-0">Verify and adjust inventory levels across locations</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="damaged_items.php" class="btn btn-outline-danger shadow-sm">
                <i class="fas fa-trash-alt me-2"></i>Damaged Bin
            </a>
            <?php if (!$active_stock_take): ?>
            <a href="create.php" class="btn btn-warning fw-bold shadow-sm">
                <i class="fas fa-plus me-2"></i>Generate Stock Take
            </a>
            <?php else: ?>
            <div class="alert alert-warning py-1 px-3 mb-0 border-warning d-inline-block small">
                <i class="fas fa-lock me-2"></i>Active: <?= escape_html($active_stock_take['stock_take_number']) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DASHBOARD TABLE -->
    <div class="card premium-card">
        <div class="card-header py-3 bg-dark-light d-flex align-items-center">
            <i class="fas fa-table me-2 text-warning"></i>
            <h5 class="mb-0 fw-bold text-white">DASHBOARD</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle" id="stockTakeTable">
                    <thead>
                        <tr>
                            <th>Stock Take Ref</th>
                            <th>Date</th>
                            <th>Initiated By</th>
                            <th>Store</th>
                            <th>Status</th>
                            <th class="text-center">No. of Counts</th>
                            <th class="text-center">Analyze Now</th>
                            <th>Action</th>
                            <th>Approval</th>
                            <th class="text-end">Variance</th>
                            <th class="text-center">Print</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock_takes)): ?>
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">No stock take records found.</td>
                        </tr>
                        <?php endif; ?>
                        <?php foreach ($stock_takes as $st):
                            $display_status = match($st['status']) {
                                'approved', 'completed' => 'approved',
                                'cancelled', 'rejected' => 'cancelled',
                                default                 => 'pending'
                            };
                            $badge = match($display_status) {
                                'approved'  => 'success',
                                'cancelled' => 'danger',
                                default     => 'warning'
                            };

                            $can_analyze = ($st['status'] === 'pending_approval') && has_role('manager');
                            $is_finalized = in_array($st['status'], ['approved', 'completed', 'cancelled']);
                            $variance_val = floatval($st['total_variance'] ?? 0);
                        ?>
                        <tr>
                            <!-- Stock Take Ref -->
                            <td>
                                <a href="view.php?id=<?= $st['id'] ?>" class="fw-bold text-info text-decoration-none">
                                    <?= escape_html($st['stock_take_number']) ?>
                                </a>
                            </td>

                            <!-- Date -->
                            <td class="text-nowrap"><?= format_date($st['created_at']) ?></td>

                            <!-- Initiated By -->
                            <td><?= escape_html($st['creator_name'] ?? 'N/A') ?></td>

                            <!-- Store -->
                            <td><span class="text-white-75"><?= escape_html($st['location_name']) ?></span></td>

                            <!-- Status -->
                            <td>
                                <span class="badge bg-<?= $badge ?>">
                                    <?= strtoupper($display_status) ?>
                                </span>
                            </td>

                            <!-- No. of Counts -->
                            <td class="text-center">
                                <?php
                                    $count_phases = 'Count 1';
                                    if (in_array($st['status'], ['pending_approval', 'rejected'])) {
                                        $count_phases = 'Count 1, 2';
                                    } elseif ($is_finalized) {
                                        $count_phases = 'Finalized';
                                    }
                                ?>
                                <small class="text-muted"><?= $count_phases ?></small>
                            </td>

                            <!-- Analyze Now -->
                            <td class="text-center">
                                <?php if ($can_analyze): ?>
                                    <a href="view.php?id=<?= $st['id'] ?>" class="btn btn-sm analyze-btn px-3 py-1 fw-bold">
                                        Analyze
                                    </a>
                                <?php elseif ($is_finalized): ?>
                                    <small class="text-muted fst-italic">Analyzed &ndash; Cannot run<br>report once approved</small>
                                <?php else: ?>
                                    <small class="text-muted">&ndash;</small>
                                <?php endif; ?>
                            </td>

                            <!-- Action -->
                            <td>
                                <?php if ($st['status'] === 'open'): ?>
                                    <a href="details.php?id=<?= $st['id'] ?>" class="btn btn-xs btn-outline-info">Edit</a>
                                <?php elseif ($st['status'] === 'pending_approval'): ?>
                                    <?php if (has_role('manager')): ?>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="view.php?id=<?= $st['id'] ?>" class="btn btn-xs btn-outline-success">Approve</a>
                                        <a href="view.php?id=<?= $st['id'] ?>" class="btn btn-xs btn-outline-danger">Reject</a>
                                    </div>
                                    <?php else: ?>
                                        <a href="details.php?id=<?= $st['id'] ?>" class="btn btn-xs btn-outline-info">Edit</a>
                                    <?php endif; ?>
                                <?php elseif ($st['status'] === 'approved' || $st['status'] === 'completed'): ?>
                                    <small class="text-success"><i class="fas fa-check-circle me-1"></i>Approved &amp; Applied</small>
                                <?php elseif ($st['status'] === 'rejected'): ?>
                                    <small class="text-muted">&ndash;</small>
                                <?php elseif ($st['status'] === 'cancelled'): ?>
                                    <small class="text-danger"><i class="fas fa-times-circle me-1"></i>Cancelled</small>
                                <?php else: ?>
                                    <a href="details.php?id=<?= $st['id'] ?>" class="btn btn-xs btn-outline-info">Enter Counts</a>
                                <?php endif; ?>
                            </td>

                            <!-- Approval date/by -->
                            <td class="text-nowrap">
                                <?php if (!empty($st['approved_by'])): ?>
                                    <small class="text-success fw-bold"><?= format_date($st['approved_at']) ?></small><br>
                                    <small class="text-muted"><?= escape_html($st['approver_name']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">&ndash;</small>
                                <?php endif; ?>
                            </td>

                            <!-- Variance -->
                            <td class="text-end fw-bold">
                                <?php if ($st['item_count'] > 0): ?>
                                    <span class="<?= $variance_val < 0 ? 'text-danger' : ($variance_val > 0 ? 'text-success' : 'text-muted') ?><?= $variance_val != 0 ? ' variance-nonzero' : '' ?>">
                                        <?= format_currency($variance_val) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">&ndash;</span>
                                <?php endif; ?>
                            </td>

                            <!-- Print -->
                            <td class="text-center">
                                <a href="view.php?id=<?= $st['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print / View Report">
                                    <i class="fas fa-print"></i>
                                </a>
                            </td>

                            <!-- Comment -->
                            <td>
                                <small class="text-muted fst-italic">
                                    <?= escape_html($st['st_notes'] ? (strlen($st['st_notes']) > 40 ? substr($st['st_notes'], 0, 40) . '...' : $st['st_notes']) : '-') ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.premium-card {
    border: none;
    border-radius: 12px;
    background: #1e1e2d;
    box-shadow: 0 8px 24px rgba(0,0,0,0.3);
    overflow: hidden;
}
.bg-dark-light {
    background: rgba(255,255,255,0.02);
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.table-dark { background: transparent; }
.table-dark thead th {
    background: rgba(255,255,255,0.03);
    border-bottom: 2px solid rgba(255,255,255,0.08);
    padding: 13px 16px;
    font-size: 0.78rem;
    text-transform: uppercase;
    color: #0dcaf0;
    white-space: nowrap;
}
.table-dark tbody td {
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    vertical-align: middle;
}
.analyze-btn {
    background: #FFD600;
    color: #000;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
}
.analyze-btn:hover { background: #FFC300; color: #000; }
.variance-nonzero {
    background: rgba(255,214,0,0.15);
    padding: 2px 8px;
    border-radius: 4px;
}
.btn-xs {
    font-size: 0.72rem;
    padding: 2px 8px;
    border-radius: 3px;
}
.text-white-75 { color: rgba(255,255,255,0.75); }
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
