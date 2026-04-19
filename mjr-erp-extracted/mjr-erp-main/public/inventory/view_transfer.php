<?php
/**
 * View Warehouse Transfer Details with Approval Workflow
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('Transfer record not found.', 'error');
    redirect('transfer_history.php');
}

// Fetch Header with all potential fields
$transfer = db_fetch("
    SELECT h.*, u.username as requester_name, u.full_name as requester_full_name,
           m.username as manager_name, m.full_name as manager_full_name,
           sl.name as source_name, dl.name as dest_name,
           c.name as company_name
    FROM transfer_headers h
    LEFT JOIN users u ON h.requested_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN locations sl ON h.source_location_id = sl.id
    LEFT JOIN locations dl ON h.dest_location_id = dl.id
    LEFT JOIN companies c ON h.company_id = c.id
    WHERE h.id = ?
", [$id]);

if (!$transfer) {
    set_flash('Transfer record not found.', 'error');
    redirect('transfer_history.php');
}

// Handle Approval / Execution
if (is_post() && ($transfer['status'] === 'pending_approval')) {
    $action = post('action');
    $user_id = current_user_id();
    $is_assigned_manager = ($transfer['manager_id'] == $user_id);

    // ONLY strictly the assigned manager can approve/reject
    if (!$is_assigned_manager) {
        set_flash('Only the assigned manager can approve or reject this transfer.', 'error');
        redirect("view_transfer.php?id=" . $id);
    }

    $status = ($action === 'approve') ? 'approved' : 'cancelled';
    $notes = sanitize_input(post('approval_notes', ''));

    try {
        db_begin_transaction();

        if ($action === 'approve') {
            // Execute the actual stock movement
            $items = db_fetch_all("SELECT * FROM transfer_items WHERE transfer_id = ?", [$id]);
            $is_write_off = ($transfer['transfer_type'] === 'write_off' || $transfer['write_off_required'] == 1);

            foreach ($items as $item) {
                // 1. Deduct from Source (Always)
                inventory_apply_stock_movement($item['item_id'], $transfer['source_location_id'], -$item['quantity']);
                inventory_record_transaction([
                    'item_id' => $item['item_id'],
                    'location_id' => $transfer['source_location_id'],
                    'transaction_type' => $is_write_off ? 'write_off' : 'issue_unplanned',
                    'movement_reason' => $is_write_off ? 'Write-Off' : 'Transfer OUT',
                    'quantity_signed' => -$item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'reference' => $transfer['transfer_number'],
                    'reference_type' => 'warehouse_transfer',
                    'notes' => $is_write_off ? "Stock Write-off: " . $transfer['damage_reason'] : "Approved Transfer to " . $transfer['dest_name'],
                    'created_by' => current_user_id()
                ]);

                // 2. Add to Destination (Only if NOT write-off)
                if (!$is_write_off) {
                    inventory_apply_stock_movement($item['item_id'], $transfer['dest_location_id'], $item['quantity']);
                    inventory_record_transaction([
                        'item_id' => $item['item_id'],
                        'location_id' => $transfer['dest_location_id'],
                        'transaction_type' => 'receipt_unplanned',
                        'movement_reason' => 'Transfer IN',
                        'quantity_signed' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'reference' => $transfer['transfer_number'],
                        'reference_type' => 'warehouse_transfer',
                        'notes' => "Approved Transfer from " . $transfer['source_name'],
                        'created_by' => current_user_id()
                    ]);
                } else {
                    // IF write-off, maybe log to a loss account if exists, or just log in transaction notes
                }
            }
            $status = 'completed';
        }

        // Update Header
        db_query("UPDATE transfer_headers SET status = ?, approved_by = ? WHERE id = ?", [
            $status,
            current_user_id(),
            $id
        ]);

        // Log History
        db_query("INSERT INTO transfer_history (transfer_id, status, notes, changed_by) VALUES (?, ?, ?, ?)", [
            $id,
            $status,
            $notes ?: "Transfer " . $status . " by manager",
            current_user_id()
        ]);

        db_commit();
        set_flash("Transfer " . $status . " successfully.", "success");
        redirect("view_transfer.php?id=" . $id);
    } catch (Exception $e) {
        db_rollback();
        set_flash("Error: " . $e->getMessage(), "error");
    }
}

// Fetch Items
$items = db_fetch_all("
    SELECT ti.*, ii.name as item_name, ii.code as item_code
    FROM transfer_items ti
    JOIN inventory_items ii ON ti.item_id = ii.id
    WHERE ti.transfer_id = ?
", [$id]);

// Fetch History
$history = db_fetch_all("
    SELECT th.*, u.full_name, u.username
    FROM transfer_history th
    LEFT JOIN users u ON th.changed_by = u.id
    WHERE th.transfer_id = ?
    ORDER BY th.created_at DESC
", [$id]);

$page_title = "Transfer Request: " . $transfer['transfer_number'];
include __DIR__ . '/../../templates/header.php';
?>

<style>
    .premium-card {
        background: #1a1a27;
        border: 1px solid #323248;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .section-badge {
        background: #212133;
        color: #00cfd5;
        font-weight: 800;
        font-size: 0.7rem;
        padding: 4px 12px;
        border-radius: 4px;
        letter-spacing: 1px;
        border-left: 3px solid #00cfd5;
    }

    .table-dark {
        background: transparent !important;
    }

    .table-dark thead th {
        background: #212133 !important;
        color: #a2a3b7 !important;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #323248 !important;
        padding: 15px 20px;
    }

    .table-dark tbody td {
        background: transparent !important;
        color: #e1e1e3 !important;
        border-bottom: 1px solid #323248 !important;
        padding: 15px 20px;
        vertical-align: middle;
    }

    .info-label {
        color: #646c9a;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }

    .info-value {
        color: #ffffff;
        font-weight: 500;
    }

    .status-badge {
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.5px;
    }

    .timeline-item {
        position: relative;
        padding-left: 30px;
        padding-bottom: 20px;
        border-left: 2px solid #323248;
        margin-left: 10px;
    }

    .timeline-item::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 0;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #323248;
        border: 2px solid #1a1a27;
    }

    .timeline-item.active::before {
        background: #00cfd5;
        box-shadow: 0 0 0 4px rgba(0, 207, 213, 0.2);
    }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header Summary Row -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="transfer_history.php" class="text-info text-decoration-none">Stock Transfers</a></li>
                    <li class="breadcrumb-item active text-secondary" aria-current="page">View Details</li>
                </ol>
            </nav>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas fa-exchange-alt me-2 text-info"></i><?= escape_html($transfer['transfer_number']) ?>
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="print_transfer.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary">
                <i class="fas fa-print me-2"></i>Print
            </a>
            <a href="transfer_history.php" class="btn btn-outline-info">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>

    <!-- 1 STOCK TRANSFER HEADER -->
    <div class="section-container mb-4">
        <div class="mb-3">
            <span class="section-badge text-uppercase">Section 1: Stock Transfer Header</span>
        </div>
        <div class="card premium-card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="info-label">Transfer Number</div>
                        <div class="info-value h5 text-info mb-0"><?= escape_html($transfer['transfer_number']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Transfer Date</div>
                        <div class="info-value"><?= format_date($transfer['transfer_date']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Company / Subsidiary</div>
                        <div class="info-value"><?= escape_html($transfer['company_name'] ?: 'MJR Group HQ') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Transfer Type</div>
                        <div class="info-value">
                            <span class="badge bg-dark border border-info text-info px-3"><?= escape_html($transfer['transfer_type'] ?: 'Internal Transfer') ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <?php
                            $status_class = match ($transfer['status']) {
                                'draft' => 'secondary',
                                'pending_approval' => 'warning',
                                'approved' => 'info',
                                'completed' => 'success',
                                'cancelled' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="status-badge bg-<?= $status_class ?> text-uppercase"><?= str_replace('_', ' ', $transfer['status']) ?></span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Created By</div>
                        <div class="info-value">
                            <span class="text-info fw-bold"><?= escape_html($transfer['requester_name']) ?></span>
                            <div class="small text-secondary opacity-75"><?= escape_html($transfer['requester_full_name']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Approving Manager</div>
                        <div class="info-value">
                            <?php if ($transfer['manager_name']): ?>
                                <span class="text-warning fw-bold"><?= escape_html($transfer['manager_name']) ?></span>
                                <div class="small text-secondary opacity-75"><?= escape_html($transfer['manager_full_name']) ?></div>
                            <?php else: ?>
                                <span class="text-muted">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Reference Document</div>
                        <div class="info-value"><?= escape_html($transfer['ref_doc'] ?: '--') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2 SOURCE & DESTINATION LOCATION -->
    <div class="section-container mb-4">
        <div class="mb-3">
            <span class="section-badge text-uppercase">Section 2: Source & Destination Location</span>
        </div>
        <div class="card premium-card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-3">
                        <div class="info-label text-danger"><i class="fas fa-sign-out-alt me-2"></i>Source Warehouse</div>
                        <div class="info-value h6 mb-0"><?= escape_html($transfer['source_name'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Source Bin</div>
                        <div class="info-value text-secondary"><?= escape_html($transfer['source_bin_head'] ?: 'Default Bin') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label text-success"><i class="fas fa-sign-in-alt me-2"></i>Destination Warehouse</div>
                        <div class="info-value h6 mb-0"><?= escape_html($transfer['dest_name'] ?: 'N/A') ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Destination Bin</div>
                        <div class="info-value text-secondary"><?= escape_html($transfer['dest_bin_head'] ?: 'Default Bin') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 3 ITEM TRANSFER TABLE -->
    <div class="section-container mb-4">
        <div class="mb-3">
            <span class="section-badge text-uppercase">Section 3: Item Transfer Table</span>
        </div>
        <div class="card premium-card">
            <div class="table-responsive">
                <table class="table table-dark mb-0">
                    <thead>
                        <tr>
                            <th>Item Code & Name</th>
                            <th>Category</th>
                            <th class="text-center">Available Stock</th>
                            <th class="text-center">Transfer Qty</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Total Value</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($items as $item): 
                            $line_total = $item['quantity'] * ($item['unit_cost'] ?? 0);
                            $grand_total += $line_total;
                        ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-white"><?= $item['item_name'] ?></div>
                                    <small class="text-secondary"><?= $item['item_code'] ?></small>
                                </td>
                                <td><span class="badge bg-dark border border-secondary">General</span></td>
                                <td class="text-center">--</td>
                                <td class="text-center fw-bold text-info fs-6"><?= number_format($item['quantity'], 2) ?></td>
                                <td class="text-end text-secondary">$<?= number_format($item['unit_cost'] ?? 0, 2) ?></td>
                                <td class="text-end fw-bold">$<?= number_format($line_total, 2) ?></td>
                                <td><small class="text-secondary"><?= $item['remarks'] ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="border-top border-secondary">
                        <tr>
                            <td colspan="5" class="text-end text-secondary text-uppercase py-3">Grand Total Value</td>
                            <td class="text-end fw-bold text-info fs-5 py-3">$<?= number_format($grand_total, 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <!-- 4 STOCK AVAILABILITY PANEL -->
            <div class="section-container">
                <div class="mb-3">
                    <span class="section-badge text-uppercase">Section 4: Stock Availability Panel</span>
                </div>
                <div class="card premium-card">
                    <div class="card-body">
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-info-circle mb-2 d-block fs-3"></i>
                            Stock distribution will be verified upon final completion.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <!-- 5 WRITE-OFF / DAMAGE SECTION -->
            <div class="section-container">
                <div class="mb-3">
                    <span class="section-badge text-uppercase">Section 5: Write-off / Damage Section</span>
                </div>
                <div class="card premium-card border-danger">
                    <div class="card-body">
                        <?php if (!empty($transfer['damage_reason'])): ?>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="info-label">Damage Reason</div>
                                    <div class="info-value text-danger"><?= escape_html($transfer['damage_reason']) ?></div>
                                </div>
                                <div class="col-6">
                                    <div class="info-label">Category</div>
                                    <div class="info-value"><?= escape_html($transfer['damage_category'] ?: 'N/A') ?></div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3 text-secondary italic">
                                <i class="fas fa-shield-alt me-2"></i>No damage reported for this transfer
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6 ATTACHMENTS & NOTES -->
    <div class="section-container mb-4">
        <div class="mb-3">
            <span class="section-badge text-uppercase">Section 6: Attachments & Notes</span>
        </div>
        <div class="card premium-card">
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-label">Warehouse Remarks</div>
                        <div class="bg-dark p-3 rounded text-secondary small border border-secondary border-opacity-25" style="min-height: 80px;">
                            <?= nl2br(escape_html($transfer['warehouse_remarks'] ?: 'No warehouse remarks provided.')) ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-label">Supervisor Notes</div>
                        <div class="bg-dark p-3 rounded text-secondary small border border-secondary border-opacity-25" style="min-height: 80px;">
                            <?= nl2br(escape_html($transfer['supervisor_notes'] ?: 'No supervisor notes recorded.')) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 7 APPROVAL WORKFLOW PANEL -->
    <div class="section-container mb-5">
        <div class="mb-3">
            <span class="section-badge text-uppercase">Section 7: Approval Workflow Panel</span>
        </div>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card premium-card">
                    <div class="card-header bg-transparent py-3">
                        <h6 class="mb-0 text-white"><i class="fas fa-history me-2 text-info"></i>Action History</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($history as $h): ?>
                            <div class="timeline-item <?= $h['status'] === $transfer['status'] ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <strong class="text-info text-uppercase"><?= str_replace('_', ' ', $h['status']) ?></strong>
                                    <small class="text-secondary"><?= format_datetime($h['created_at']) ?></small>
                                </div>
                                <div class="text-white small mb-1"><?= escape_html($h['notes']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    By: <span class="text-info fw-bold"><?= escape_html($h['username'] ?: 'system') ?></span>
                                    <span class="opacity-50 ms-1">(<?= escape_html($h['full_name'] ?: 'System Process') ?>)</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <?php
                $is_assigned_manager = ($transfer['manager_id'] == current_user_id());
                if ($transfer['status'] === 'pending_approval' && $is_assigned_manager): ?>
                    <div class="card premium-card border-warning">
                        <div class="card-header bg-warning text-dark fw-bold py-3 text-uppercase" style="font-size: 0.8rem; letter-spacing: 1px;">
                            <i class="fas fa-gavel me-2"></i>Manager Approval Required
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <label class="info-label">Approval/Rejection Notes</label>
                                <textarea name="approval_notes" class="form-control bg-dark text-white border-secondary mb-3" rows="3" placeholder="Enter reason for approval or rejection..."></textarea>
                                <div class="d-grid gap-2">
                                    <button type="submit" name="action" value="approve" class="btn btn-success fw-bold py-2">
                                        <i class="fas fa-check-circle me-2"></i>Approve & Execute
                                    </button>
                                    <button type="submit" name="action" value="cancel" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times-circle me-2"></i>Reject Transfer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php elseif ($transfer['status'] === 'pending_approval'): ?>
                    <div class="alert alert-info bg-dark border-info text-white-50 p-4">
                        <div class="text-center mb-3">
                            <i class="fas fa-hourglass-half fa-3x text-warning opacity-50"></i>
                        </div>
                        <h6 class="text-white text-center mb-2">Pending Approval</h6>
                        <p class="small text-center mb-0">This request is currently awaiting action from:</p>
                        <p class="text-info text-center fw-bold"><?= escape_html($transfer['manager_full_name'] ?: $transfer['manager_name'] ?: 'Assigned Manager') ?></p>
                    </div>
                <?php else: ?>
                    <div class="card premium-card bg-dark bg-opacity-50">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-shield-check fa-4x text-success mb-3 opacity-25"></i>
                            <h5 class="text-white opacity-50">Transfer Process Finalized</h5>
                            <p class="text-secondary small">No further actions required.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>