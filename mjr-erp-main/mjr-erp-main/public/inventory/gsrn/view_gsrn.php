<?php
/**
 * View GSRN Details
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('GSRN not found.', 'error');
    redirect('index.php');
}

// Get GSRN Header
$gsrn = db_fetch("
    SELECT h.*, u.username as creator_name, m.username as manager_name,
           w.name as warehouse_name, c.name as company_name, cat.name as category_name,
           s.name as supplier_name
    FROM gsrn_headers h
    LEFT JOIN users u ON h.created_by = u.id
    LEFT JOIN users m ON h.manager_id = m.id
    LEFT JOIN locations w ON h.warehouse_id = w.id
    LEFT JOIN companies c ON h.company_id = c.id
    LEFT JOIN categories cat ON h.category_id = cat.id
    LEFT JOIN suppliers s ON h.supplier_id = s.id
    WHERE h.id = ?
", [$id]);

if (!$gsrn) {
    set_flash('GSRN not found.', 'error');
    redirect('index.php');
}

// Handle File Deletion
if (is_post() && post('action') === 'delete_file') {
    $file_id = post('file_id');
    $file = db_fetch("SELECT * FROM gsrn_files WHERE id = ? AND gsrn_id = ?", [$file_id, $id]);
    
    if ($file) {
        $file_path = __DIR__ . '/../../../public/' . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        db_query("DELETE FROM gsrn_files WHERE id = ?", [$file_id]);
        set_flash("Attachment deleted successfully.", "success");
    } else {
        set_flash("Attachment not found.", "error");
    }
    
    redirect("view_gsrn.php?id=" . $id);
}

// Handle Approval/Rejection
if (is_post() && (has_role('manager') || has_role('admin'))) {
    $action = post('action');
    $status = ($action === 'approve') ? 'approved' : 'rejected';
    $notes = post('approval_notes');
    $user_id = current_user_id();
    $now = date('Y-m-d H:i:s');

    try {
        db_begin_transaction();

        // Update Header
        db_query("UPDATE gsrn_headers SET status = ?, approved_by = ?, approved_at = ? WHERE id = ?", [
            $status,
            $user_id,
            $now,
            $id
        ]);

        // Log History
        db_query("INSERT INTO gsrn_history (gsrn_id, status, notes, changed_by) VALUES (?, ?, ?, ?)", [
            $id,
            $status,
            $notes ?: ($action === 'approve' ? 'Approved by manager' : 'Rejected by manager'),
            $user_id
        ]);

        // Update Inventory Levels
        if ($action === 'approve') {
            inventory_assert_stock_take_allows_stock_change(
                intval($gsrn['warehouse_id'] ?? 0),
                'GSRN approval'
            );

            $gsrn_items = db_fetch_all("SELECT * FROM gsrn_items WHERE gsrn_id = ?", [$id]);

            // If header has a produced item (Production mode), add it to processing list if not already there
            if (($gsrn['transaction_type'] === 'Production' || $gsrn['transaction_type'] === 'Manufacturing') && !empty($gsrn['produced_item_id'])) {
                $already_in = false;
                foreach($gsrn_items as $gi) { if($gi['item_id'] == $gsrn['produced_item_id']) { $already_in = true; break; } }
                if(!$already_in) {
                    $gsrn_items[] = [
                        'item_id' => $gsrn['produced_item_id'],
                        'quantity' => $gsrn['production_qty'],
                        'unit_cost' => ($gsrn['production_qty'] > 0) ? ($gsrn['production_cost'] / $gsrn['production_qty']) : 0,
                        'total_value' => $gsrn['production_cost'],
                        'bin_location' => ''
                    ];
                }
            }

            // Get the true warehouse_id for stock_movements (mappings location_id -> warehouse_id)
            $resolved_warehouse_id = db_fetch("SELECT id FROM warehouses WHERE location_id = ?", [$gsrn['warehouse_id']])['id'] ?? null;

            foreach ($gsrn_items as $item) {
                // 1. Update/Insert inventory_stock_levels
                $existing_stock = db_fetch("
                    SELECT id FROM inventory_stock_levels 
                    WHERE item_id = ? AND location_id = ?
                ", [$item['item_id'], $gsrn['warehouse_id']]);

                if ($existing_stock) {
                    db_query("
                        UPDATE inventory_stock_levels 
                        SET quantity_on_hand = quantity_on_hand + ?, last_updated = ? 
                        WHERE id = ?
                    ", [$item['quantity'], $now, $existing_stock['id']]);
                } else {
                    db_query("
                        INSERT INTO inventory_stock_levels (item_id, location_id, quantity_on_hand, last_updated) 
                        VALUES (?, ?, ?, ?)
                    ", [$item['item_id'], $gsrn['warehouse_id'], $item['quantity'], $now]);
                }

                // 2. Log in stock_movements
                if ($resolved_warehouse_id) {
                    db_query("
                        INSERT INTO stock_movements (warehouse_id, product_id, movement_type, quantity, created_at) 
                        VALUES (?, ?, 'IN', ?, ?)
                    ", [$resolved_warehouse_id, $item['item_id'], $item['quantity'], $now]);
                }

                // 3. Update Item Cost Price (Weighted Average Costing)
                // New Cost = ((Current Qty * Current Cost) + (Received Qty * Landed Cost)) / (Current Qty + Received Qty)
                $item_info = db_fetch("SELECT cost_price FROM inventory_items WHERE id = ?", [$item['item_id']]);
                $current_stock = db_fetch("SELECT SUM(quantity_on_hand) as total_qty FROM inventory_stock_levels WHERE item_id = ?", [$item['item_id']]);
                
                $current_qty = (float)($current_stock['total_qty'] ?? 0);
                $current_cost = (float)($item_info['cost_price'] ?? 0);
                $received_qty = (float)$item['quantity'];
                $landed_cost = (float)$item['landed_unit_cost'];
                
                if ($received_qty > 0) {
                    $new_total_qty = $current_qty + $received_qty;
                    if ($new_total_qty > 0) {
                        $new_weighted_cost = (($current_qty * $current_cost) + ($received_qty * $landed_cost)) / $new_total_qty;
                    } else {
                        $new_weighted_cost = $landed_cost;
                    }
                    
                    db_query("UPDATE inventory_items SET cost_price = ?, updated_at = NOW() WHERE id = ?", [
                        round($new_weighted_cost, 4),
                        $item['item_id']
                    ]);
                }

                // 4. IF Production, Deduct BOM Components

                if ($gsrn['transaction_type'] === 'Production' || $gsrn['transaction_type'] === 'Manufacturing') {
                    $bom_items = db_fetch_all("
                        SELECT * FROM bill_of_materials 
                        WHERE product_id = ? AND is_active = 1
                    ", [$item['item_id']]);

                    foreach ($bom_items as $bom) {
                        $deduct_qty = (float)$bom['quantity_required'] * (float)$item['quantity'];
                        
                        // Deduct from same warehouse
                        $comp_existing = db_fetch("
                            SELECT id FROM inventory_stock_levels 
                            WHERE item_id = ? AND location_id = ?
                        ", [$bom['component_id'], $gsrn['warehouse_id']]);

                        if ($comp_existing) {
                            db_query("
                                UPDATE inventory_stock_levels 
                                SET quantity_on_hand = quantity_on_hand - ?, last_updated = ? 
                                WHERE id = ?
                            ", [$deduct_qty, $now, $comp_existing['id']]);
                        } else {
                            // Rare case: deducting something that's not in stock records yet
                            db_query("
                                INSERT INTO inventory_stock_levels (item_id, location_id, quantity_on_hand, last_updated) 
                                VALUES (?, ?, ?, ?)
                            ", [$bom['component_id'], $gsrn['warehouse_id'], -$deduct_qty, $now]);
                        }

                        // Log movement OUT for component (Consumption)
                        if ($resolved_warehouse_id) {
                            db_query("
                                INSERT INTO stock_movements (warehouse_id, product_id, movement_type, quantity, created_at, reference) 
                                VALUES (?, ?, 'OUT', ?, ?, ?)
                            ", [$resolved_warehouse_id, $bom['component_id'], $deduct_qty, $now, "Consumed for " . $gsrn['gsrn_number']]);
                        }
                    }
                }
            }
        }

        db_commit();
        set_flash("GSRN successfully " . $status . " and inventory updated.", "success");
    } catch (Exception $e) {
        db_rollback();
        set_flash("Error: " . $e->getMessage(), 'error');
    }
}

// Get Items
$items = db_fetch_all("
    SELECT gi.*, i.name as item_name, i.code as item_code
    FROM gsrn_items gi
    JOIN inventory_items i ON gi.item_id = i.id
    WHERE gi.gsrn_id = ?
", [$id]);

$page_title = "View GSRN: " . $gsrn['gsrn_number'];
include __DIR__ . '/../../../templates/header.php';
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
        background: #0dcaf0;
        border-color: #0dcaf0;
        color: #000;
        box-shadow: 0 0 20px rgba(13, 202, 240, 0.6);
        transform: scale(1.1);
    }

    .step-current .step-label {
        color: #0dcaf0;
    }

    .step-rejected .step-icon {
        background: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        margin-bottom: 25px;
        transition: all 0.3s ease;
    }

    .premium-card .card-header {
        background: rgba(255, 255, 255, 0.03);
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        font-weight: 600;
        color: #0dcaf0;
        padding: 18px 25px;
        border-radius: 12px 12px 0 0;
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
</style>

<div class="container-fluid py-4">
    <!-- Workflow Progress Bar -->
    <div class="row mb-5 no-print">
        <div class="col-12 text-center">
            <div class="workflow-steps">
                <?php
                $steps = [
                    ['id' => 'draft', 'label' => 'DRAFT', 'icon' => 'pencil-alt'],
                    ['id' => 'pending_approval', 'label' => 'PENDING APPROVAL', 'icon' => 'hourglass-half'],
                    ['id' => 'approved', 'label' => 'APPROVED', 'icon' => 'check-double'],
                    ['id' => 'accounted', 'label' => 'ACCOUNTED', 'icon' => 'envelope']
                ];

                $current_status = $gsrn['status'];
                // If it's approved, we might want to show it as "Accounted" if that's the final stage in MJR workflow
                $status_rank = [
                    'draft' => 0,
                    'pending_approval' => 1,
                    'approved' => 2,
                    'rejected' => 1,
                    'accounted' => 3
                ];

                // For GSRN, once approved it's effectively accounted for in inventory
                if ($current_status === 'approved') {
                    $current_rank = 3; // Show Accounted as current or past? Let's show it as current/active
                } else {
                    $current_rank = $status_rank[$current_status] ?? 0;
                }

                foreach ($steps as $index => $step):
                    $is_past = ($index < $current_rank);
                    $is_current = ($index === $current_rank);
                    $is_rejected = ($current_status === 'rejected' && $index === 1);
                    ?>
                    <div
                        class="workflow-step <?= $is_past ? 'step-past' : ($is_current ? 'step-current' : '') ?> <?= $is_rejected ? 'step-rejected' : '' ?>">
                        <div class="step-icon">
                            <i class="fas fa-<?= $is_rejected ? 'times' : $step['icon'] ?>"></i>
                            <?php if ($is_past): ?>
                                <div class="check-overlay"><i class="fas fa-check"></i></div><?php endif; ?>
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
    <div class="row mb-4">
        <div class="col">
            <h2 class="text-white mb-0"><?= $gsrn['gsrn_number'] ?></h2>
            <p class="text-muted">GSRN / Stock Entry Details</p>
        </div>
        <div class="col-auto">
            <div class="btn-group shadow-sm">
                <a href="index.php" class="btn btn-secondary shadow-sm"><i class="fas fa-arrow-left me-2"></i>Back</a>
                <a href="print_gsrn.php?id=<?= $id ?>" target="_blank"
                    class="btn btn-info text-dark fw-bold shadow-sm"><i class="fas fa-print me-2"></i>Print Receipt</a>
                <?php if (in_array($gsrn['status'], ['draft', 'rejected']) && ($gsrn['created_by'] == current_user_id() || has_role('admin'))): ?>
                    <a href="edit_gsrn.php?id=<?= $id ?>" class="btn btn-primary shadow-sm"><i
                            class="fas fa-edit me-2"></i>Edit GSRN</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Details -->
        <div class="col-lg-8">
            <div class="card premium-card border-start border-4 border-info">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-file-invoice me-2"></i>Document Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="text-muted small d-block">GSRN Date</label>
                            <span class="text-white fw-bold"><?= format_date($gsrn['gsrn_date']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Transaction Type</label>
                            <span
                                class="badge bg-dark text-info border border-info"><?= $gsrn['transaction_type'] ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Warehouse</label>
                            <span class="text-white"><?= escape_html($gsrn['warehouse_name']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Supplier</label>
                            <span class="text-white"><?= escape_html($gsrn['supplier_name'] ?: 'N/A') ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Company</label>
                            <span class="text-white"><?= escape_html($gsrn['company_name']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <label class="text-muted small d-block">Current Status</label>
                            <?php
                            $badge = match ($gsrn['status']) {
                                'draft' => 'secondary',
                                'pending_approval' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= strtoupper($gsrn['status']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manufacturing Details (If Production) -->
            <?php if (($gsrn['transaction_type'] === 'Production' || $gsrn['transaction_type'] === 'Manufacturing') && !empty($gsrn['produced_item_id'])): ?>
                <?php
                // Fetch produced item name
                $produced_item = db_fetch("SELECT code, name FROM inventory_items WHERE id = ?", [$gsrn['produced_item_id']]);
                ?>
                <div class="card premium-card border-info mb-4">
                    <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                        <h5 class="mb-0 text-info fw-bold"><i class="fas fa-industry me-2"></i>Manufacturing Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-5">
                                <label class="text-muted small d-block">Produced Item</label>
                                <span class="text-info fw-bold h5"><?= escape_html($produced_item['code'] . ' - ' . $produced_item['name']) ?></span>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small d-block">Produced Qty</label>
                                <span class="text-white h5 fw-bold"><?= number_format($gsrn['production_qty'], 2) ?></span>
                            </div>
                            <div class="col-md-2">
                                <label class="text-muted small d-block">Production Cost</label>
                                <span class="text-white h5 fw-bold"><?= format_currency($gsrn['production_cost'], $gsrn['currency'] ?? 'INR') ?></span>
                            </div>
                            <div class="col-md-3">
                                <label class="text-muted small d-block">Order Reference</label>
                                <span class="text-white"><?= escape_html($gsrn['production_order_ref'] ?? '--') ?></span>
                            </div>
                        </div>
                        <div class="mt-3 p-3 bg-dark rounded border border-secondary border-opacity-25 small text-secondary">
                            <i class="fas fa-info-circle me-2 text-info"></i>
                            This production entry will automatically deduct raw materials from the <strong><?= escape_html($gsrn['warehouse_name']) ?></strong> based on the Bill of Materials (BOM) upon approval.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Items Table -->
            <div class="card premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-boxes me-2"></i>Item Details</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Item Code</th>
                                    <th>Item Name</th>
                                    <th>Bin</th>
                                    <th class="text-center">Quantity</th>
                                    <th>Unit</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><code><?= escape_html($item['item_code']) ?></code></td>
                                        <td><?= escape_html($item['item_name']) ?></td>
                                        <td><?= escape_html($item['bin_location'] ?: '--') ?></td>
                                        <td class="text-center"><?= number_format($item['quantity'], 2) ?></td>
                                        <td><?= escape_html($item['uom']) ?></td>
                                        <td class="text-end"><?= format_currency($item['unit_cost'], $gsrn['currency']) ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?= format_currency($item['total_value'], $gsrn['currency']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-transparent">
                                <tr>
                                    <td colspan="6" class="text-end text-muted small">Subtotal:</td>
                                    <td class="text-end fw-bold">
                                        <?= format_currency($gsrn['invoice_value'], $gsrn['currency']) ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- History Trail -->
            <div class="card premium-card">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Approval Trail & History</h5>
                </div>
                <div class="card-body">
                    <?php
                    $history = db_fetch_all("
                        SELECT gh.*, u.username 
                        FROM gsrn_history gh 
                        LEFT JOIN users u ON gh.changed_by = u.id 
                        WHERE gh.gsrn_id = ? 
                        ORDER BY gh.created_at DESC
                    ", [$id]);
                    ?>
                    <div class="timeline small">
                        <?php foreach ($history as $h): ?>
                            <div class="mb-3 border-start border-secondary ps-3 pb-2">
                                <span class="text-muted d-block small"><?= format_datetime($h['created_at']) ?></span>
                                <span
                                    class="badge bg-<?= match ($h['status']) { 'draft' => 'secondary', 'pending_approval' => 'warning', 'approved' => 'success', 'rejected' => 'danger'} ?> py-0 mb-1"><?= strtoupper($h['status']) ?></span>
                                <p class="mb-0 py-1 text-light"><?= escape_html($h['notes']) ?></p>
                                <span class="text-muted small">-- by <?= escape_html($h['username']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Landed Cost & Approval -->
        <div class="col-lg-4">
            <!-- Costing Summary -->
            <div class="card premium-card border-top border-4 border-info">
                <div class="card-header py-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-calculator me-2"></i>Landed Cost Summary</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Invoice Value:</span>
                        <span
                            class="text-white"><?= format_currency($gsrn['invoice_value'], $gsrn['currency']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Freight & Duty:</span>
                        <span
                            class="text-white"><?= format_currency($gsrn['freight_cost'] + $gsrn['import_duty'], $gsrn['currency']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Insurance & Misc:</span>
                        <span
                            class="text-white"><?= format_currency($gsrn['insurance'] + $gsrn['handling_charges'] + $gsrn['other_sundry_costs'], $gsrn['currency']) ?></span>
                    </div>
                    <?php if ($gsrn['adjustment_amount'] != 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Adjustment (<?= $gsrn['adjustment_type'] ?>):</span>
                            <span class="text-<?= $gsrn['adjustment_type'] === 'Add' ? 'success' : 'danger' ?>">
                                <?= $gsrn['adjustment_type'] === 'Add' ? '+' : '-' ?>
                                <?= format_currency($gsrn['adjustment_amount'], $gsrn['currency']) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <hr class="border-secondary opacity-50">
                    <div class="text-center py-3 bg-dark bg-opacity-50 rounded border border-secondary">
                        <div class="text-muted small text-uppercase fw-bold mb-1">Final Landed Cost</div>
                        <div class="h2 mb-0 text-info fw-bold">
                            <?= format_currency($gsrn['final_landed_cost'], $gsrn['currency']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Approval Panel -->
            <?php if ($gsrn['status'] === 'pending_approval' && (has_role('manager') || has_role('admin')) && $gsrn['manager_id'] == current_user_id()): ?>
                <div class="card border-warning bg-dark shadow-lg">
                    <div class="card-header bg-warning text-dark py-3">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-check-double me-2"></i>Manager Approval</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold">Approval/Rejection Notes</label>
                                <textarea name="approval_notes" class="form-control bg-dark text-white border-secondary"
                                    rows="3" placeholder="Enter comments..."></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="submit" name="action" value="approve"
                                        class="btn btn-success w-100 shadow-sm"
                                        onclick="return confirm('Approve this GSRN and update inventory?')">
                                        <i class="fas fa-check me-1"></i>Approve
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="submit" name="action" value="reject"
                                        class="btn btn-danger w-100 shadow-sm"
                                        onclick="return confirm('Reject this entry?')">
                                        <i class="fas fa-times me-1"></i>Reject
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($gsrn['status'] === 'pending_approval'): ?>
                <div class="alert alert-info border-info bg-transparent">
                    <i class="fas fa-hourglass-half me-2"></i>Waiting for approval from
                    <strong><?= escape_html($gsrn['manager_name'] ?: 'Manager') ?></strong>.
                </div>
            <?php endif; ?>

            <!-- Notes & Files -->
            <div class="card premium-card">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Remarks & Attachments</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Internal Notes</label>
                            <p class="text-white small">
                                <?= nl2br(escape_html($gsrn['internal_notes'] ?: 'No internal notes.')) ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small d-block">Warehouse Remarks</label>
                            <p class="text-white small">
                                <?= nl2br(escape_html($gsrn['warehouse_remarks'] ?: 'No warehouse remarks.')) ?></p>
                        </div>
                    </div>
                    
                    <?php
                    $gsrn_files = db_fetch_all("SELECT * FROM gsrn_files WHERE gsrn_id = ?", [$id]);
                    if ($gsrn_files):
                    ?>
                    <hr class="border-secondary">
                    <label class="text-muted small d-block mb-2">Attachments</label>
                    <div class="list-group list-group-flush border border-secondary rounded overflow-hidden">
                        <?php foreach ($gsrn_files as $file): ?>
                            <div class="list-group-item bg-dark border-secondary d-flex justify-content-between align-items-center py-2">
                                <div class="text-truncate" style="max-width: 80%;">
                                    <i class="fas fa-file-alt me-2 text-info"></i>
                                    <span class="text-white small"><?= escape_html($file['file_name']) ?></span>
                                </div>
                                <div class="d-flex gap-1 align-items-center">
                                    <a href="../../../public/<?= $file['file_path'] ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="fas fa-eye small"></i>
                                    </a>
                                    <form method="POST" class="m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this attachment?');">
                                        <input type="hidden" name="action" value="delete_file">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Attachment">
                                            <i class="fas fa-trash small"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
