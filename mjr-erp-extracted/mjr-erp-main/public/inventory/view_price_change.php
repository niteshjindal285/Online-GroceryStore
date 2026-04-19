<?php
/**
 * View Price Change Request & Approval Workflow
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('Price Change record not found.', 'error');
    redirect('price_change_history.php');
}

// Fetch Header
$pc = db_fetch("
    SELECT h.*, u.username as creator_name, u.full_name as creator_full_name,
           c.name as company_name
    FROM price_change_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN companies c ON h.company_id = c.id
    WHERE h.id = ?
", [$id]);

if (!$pc) {
    set_flash('Price Change record not found.', 'error');
    redirect('price_change_history.php');
}

// Handle Approval / Cancellation
if (is_post() && ($pc['status'] === 'Pending Approval')) {
    $action = post('action');
    $notes = sanitize_input(post('approval_notes', ''));
    $user_id = current_user_id();

    try {
        db_begin_transaction();

        if ($action === 'approve') {
            $items = db_fetch_all("SELECT * FROM price_change_items WHERE pc_header_id = ?", [$id]);
            foreach ($items as $item) {
                // Determine which column to update in inventory_items
                $column = ($pc['price_category'] === 'Selling Price') ? 'selling_price' : 'cost_price';
                
                db_query("UPDATE inventory_items SET $column = ? WHERE id = ?", [$item['new_price'], $item['item_id']]);
                
                // If it's a cost_price update, we might also want to update warehouse_inventory or other stock records?
                // For now, let's keep it simple as per Screen 5 rule.
            }
            $status = 'Approved';
        } else {
            $status = 'Cancelled';
        }

        // Update Header
        db_query("UPDATE price_change_headers SET status = ? WHERE id = ?", [$status, $id]);

        // Log History
        db_query("
            INSERT INTO price_change_history (pc_header_id, status, notes, changed_by)
            VALUES (?, ?, ?, ?)
        ", [$id, $status, $notes ?: "Price change " . strtolower($status), $user_id]);

        db_commit();
        set_flash("Price change " . strtolower($status) . " successfully!", "success");
        redirect("view_price_change.php?id=" . $id);
    } catch (Exception $e) {
        db_rollback();
        $error = $e->getMessage();
    }
}

// Fetch Items
$items = db_fetch_all("
    SELECT pi.*, i.code as item_code, i.name as item_name, cat.name as category_name
    FROM price_change_items pi
    JOIN inventory_items i ON pi.item_id = i.id
    LEFT JOIN categories cat ON i.category_id = cat.id
    WHERE pi.pc_header_id = ?
", [$id]);

// Fetch History
$history = db_fetch_all("
    SELECT h.*, u.username, u.full_name
    FROM price_change_history h
    LEFT JOIN users u ON h.changed_by = u.id
    WHERE h.pc_header_id = ?
    ORDER BY h.created_at DESC
", [$id]);

// Impact Calculation Summary
$total_diff = 0;
$avg_diff_pct = 0;
if (!empty($items)) {
    $pct_sum = 0;
    foreach ($items as $item) {
        $diff = $item['new_price'] - $item['current_price'];
        $total_diff += $diff;
        if ($item['current_price'] > 0) {
            $pct_sum += ($diff / $item['current_price'] * 100);
        }
    }
    $avg_diff_pct = $pct_sum / count($items);
}

$page_title = "Price Change Details: " . $pc['pc_number'];
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <!-- Header Summary Row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="price_change_history.php" class="text-info text-decoration-none">Price Changes</a></li>
                    <li class="breadcrumb-item active text-secondary" aria-current="page">View Request</li>
                </ol>
            </nav>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-tags me-2 text-info"></i><?= escape_html($pc['pc_number']) ?>
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="print_price_change.php?id=<?= $pc['id'] ?>" target="_blank" class="btn btn-outline-secondary">
                <i class="fas fa-print me-2"></i>Print
            </a>
            <?php if ($pc['status'] === 'Draft'): ?>
                <a href="add_price_change.php?id=<?= $pc['id'] ?>" class="btn btn-outline-info">
                    <i class="fas fa-edit me-2"></i>Edit Draft
                </a>
            <?php endif; ?>
            <a href="price_change_history.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger mb-4"><?= $error ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-lg-8">
            <!-- 1. Header Information -->
            <div class="card premium-card mb-4">
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-3">
                            <div class="info-label text-info">Price Category</div>
                            <div class="info-value">
                                <span class="badge bg-dark border border-info text-info px-3"><?= escape_html($pc['price_category']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Current Status</div>
                            <div class="info-value">
                                <?php
                                $badge = 'bg-secondary';
                                if ($pc['status'] === 'Approved') $badge = 'bg-success';
                                if ($pc['status'] === 'Pending Approval') $badge = 'bg-warning text-dark';
                                if ($pc['status'] === 'Cancelled') $badge = 'bg-danger';
                                ?>
                                <span class="badge <?= $badge ?> px-3 py-1"><?= escape_html($pc['status']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Effective From</div>
                            <div class="info-value h6 mb-0"><?= format_date($pc['effective_date']) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Subsidiary</div>
                            <div class="info-value"><?= escape_html($pc['company_name']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Reason for change</div>
                            <div class="info-value small"><?= escape_html($pc['reason'] ?: 'No reason specified') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label text-info">Scope / Remarks</div>
                            <div class="info-value small text-white-50"><?= nl2br(escape_html($pc['remarks'] ?: 'No specific scope defined')) ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Requested By</div>
                            <div class="info-value text-info fw-bold">
                                <?= escape_html($pc['creator_name']) ?>
                                <div class="x-small text-secondary opacity-50 fw-normal"><?= escape_html($pc['creator_full_name']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-label">Requested Date</div>
                            <div class="info-value text-secondary small"><?= format_date($pc['pc_date']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Items Table -->
            <div class="card premium-card">
                <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                    <h5 class="text-white mb-0">Products Affected</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-dark table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">Item Code & Name</th>
                                <th>Category</th>
                                <th class="text-end">Current Price</th>
                                <th class="text-end">New Price</th>
                                <th class="text-end">Difference</th>
                                <th class="pe-4 text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php
                                $diff = $item['new_price'] - $item['current_price'];
                                $pct = ($item['current_price'] > 0) ? ($diff / $item['current_price'] * 100) : 0;
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-info"><?= escape_html($item['item_code']) ?></div>
                                        <div class="small text-secondary"><?= escape_html($item['item_name']) ?></div>
                                    </td>
                                    <td><?= escape_html($item['category_name']) ?></td>
                                    <td class="text-end text-secondary"><?= number_format($item['current_price'], 2) ?></td>
                                    <td class="text-end text-white fw-bold"><?= number_format($item['new_price'], 2) ?></td>
                                    <td class="text-end fw-bold <?= $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : 'text-secondary') ?>">
                                        <?= ($diff >= 0 ? '+' : '') . number_format($diff, 2) ?>
                                    </td>
                                    <td class="pe-4 text-end small opacity-75">
                                        <?= ($diff >= 0 ? '+' : '') . number_format($pct, 1) ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Side Panel -->
        <div class="col-lg-4">
            <!-- 3. Impact Summary -->
            <div class="card premium-card mb-4 border-info border-opacity-25">
                <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                    <h6 class="text-white mb-0">Price Update Impact</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3 border-bottom border-secondary border-opacity-25 pb-2">
                        <span class="text-secondary small">Items Affected</span>
                        <span class="text-white fw-bold h5 mb-0"><?= count($items) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 border-bottom border-secondary border-opacity-25 pb-2">
                        <span class="text-secondary small">Average Change</span>
                        <span class="fw-bold h5 mb-0 <?= $avg_diff_pct >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($avg_diff_pct >= 0 ? '+' : '') . number_format($avg_diff_pct, 1) ?>%
                        </span>
                    </div>
                    <div class="bg-dark p-3 rounded border border-secondary border-opacity-25 mt-4">
                        <div class="text-info x-small fw-bold text-uppercase mb-2">Rule Automation</div>
                        <p class="small text-secondary mb-0">
                            Upon approval, the system will automatically update the master records for selected products effective from **<?= format_date($pc['effective_date']) ?>**.
                        </p>
                    </div>
                </div>
            </div>

            <!-- 4. Approval Actions -->
            <?php if ($pc['status'] === 'Pending Approval' && has_role('manager')): ?>
                <div class="card premium-card mb-4 border-warning border-opacity-50">
                    <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                        <h6 class="text-white mb-0">Decision Panel</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-secondary small">Approval Notes / Reasons</label>
                                <textarea name="approval_notes" class="form-control bg-dark text-white border-secondary" rows="3" placeholder="Explain your decision..."></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success btn-lg fw-bold">
                                    <i class="fas fa-check-circle me-2"></i>Approve & Update Prices
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-lg">
                                    <i class="fas fa-times-circle me-2"></i>Reject Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 5. History Audit Trail -->
            <div class="card premium-card">
                <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                    <h6 class="text-white mb-0">Audit Timeline</h6>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item border-start ps-3 pb-4 position-relative">
                                <div class="timeline-marker position-absolute rounded-circle" style="left: -6px; top: 0; width: 10px; height: 10px; background: #0dcaf0; box-shadow: 0 0 10px rgba(13, 202, 240, 0.5);"></div>
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <span class="badge bg-dark border border-secondary text-info"><?= escape_html($h['status']) ?></span>
                                    <small class="text-secondary" style="font-size: 0.65rem;"><?= format_date($h['created_at'], 'd M, H:i') ?></small>
                                </div>
                                <p class="text-white small mb-1"><?= escape_html($h['notes']) ?></p>
                                <div class="text-muted x-small">
                                    By: <span class="text-info"><?= escape_html($h['username']) ?></span> 
                                    (<?= escape_html($h['full_name']) ?>)
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .premium-card {
        background: #1a1a27;
        border: 1px solid #323248;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    }
    .info-label { color: #646c9a; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
    .info-value { color: #ffffff; font-weight: 500; }
    .bg-dark-light { background: rgba(255, 255, 255, 0.03); }
    .table-dark { background: transparent !important; }
    .table-dark thead th {
        background: #212133 !important;
        color: #a2a3b7 !important;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #323248 !important;
        padding: 12px 15px;
    }
    .table-dark tbody td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding: 12px 15px;
    }
    .timeline-item { border-left: 2px solid #323248; }
</style>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
