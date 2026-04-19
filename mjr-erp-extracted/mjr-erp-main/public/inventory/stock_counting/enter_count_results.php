<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$page_title = 'Enter Stock Count Results - MJR Group ERP';
$errors = [];
$success = '';

$count_id = (int)get('id', 0);

if ($count_id <= 0) {
    redirect('index.php');
}

// Get count header
$count = db_fetch("
    SELECT sch.*, 
           u.username as created_by_name,
           l.name as location_name,
           c.name as category_name
    FROM stock_count_headers sch
    LEFT JOIN users u ON sch.created_by = u.id
    LEFT JOIN locations l ON sch.location_id = l.id
    LEFT JOIN categories c ON sch.category_id = c.id
    WHERE sch.id = ?
", [$count_id]);

if (!$count) {
    set_flash('Stock count not found.', 'danger');
    redirect('index.php');
}

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = post('action');
        
        if ($action === 'save_counts') {
            $counted_quantities = post('counted_quantity', []);
            $notes_array = post('item_notes', []);
            
            try {
                db_execute("START TRANSACTION");
                
                foreach ($counted_quantities as $detail_id => $quantity) {
                    if ($quantity !== '' && $quantity !== null) {
                        $item_notes = $notes_array[$detail_id] ?? '';
                        
                        db_execute("
                            UPDATE stock_count_details 
                            SET counted_quantity = ?,
                                counted_by = ?,
                                counted_at = NOW(),
                                notes = ?
                            WHERE id = ?
                        ", [$quantity, $_SESSION['user_id'], $item_notes, $detail_id]);
                    }
                }
                
                // Update header status
                db_execute("
                    UPDATE stock_count_headers 
                    SET status = 'In Progress'
                    WHERE id = ? AND status = 'Draft'
                ", [$count_id]);
                
                db_execute("COMMIT");
                
                $success = 'Count results saved successfully!';
                
            } catch (Exception $e) {
                db_execute("ROLLBACK");
                log_error("Error saving count results: " . $e->getMessage());
                $errors[] = 'An error occurred while saving count results.';
            }
        } elseif ($action === 'complete_count') {
            try {
                // Check if all items are counted
                $uncounted = db_fetch("
                    SELECT COUNT(*) as count 
                    FROM stock_count_details 
                    WHERE count_header_id = ? AND counted_quantity IS NULL
                ", [$count_id])['count'];
                
                if ($uncounted > 0) {
                    $errors[] = "Cannot complete count. {$uncounted} item(s) not yet counted.";
                } else {
                    db_execute("
                        UPDATE stock_count_headers 
                        SET status = 'Completed',
                            completed_at = NOW()
                        WHERE id = ?
                    ", [$count_id]);
                    
                    set_flash('Stock count completed successfully!', 'success');
                    redirect('count_report.php?id=' . $count_id);
                }
            } catch (Exception $e) {
                log_error("Error completing count: " . $e->getMessage());
                $errors[] = 'An error occurred while completing the count.';
            }
        }
    }
}

// Get count details
$details = db_fetch_all("
    SELECT scd.*,
           i.code,
           i.name,
           l.name as location_name,
           u.code as unit_code,
           usr.username as counted_by_name
    FROM stock_count_details scd
    JOIN inventory_items i ON scd.item_id = i.id
    LEFT JOIN locations l ON scd.location_id = l.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    LEFT JOIN users usr ON scd.counted_by = usr.id
    WHERE scd.count_header_id = ?
    ORDER BY i.code
", [$count_id]);

// Calculate statistics
$total_items = count($details);
$counted_items = 0;
$total_variance = 0;

foreach ($details as $detail) {
    if ($detail['counted_quantity'] !== null) {
        $counted_items++;
        $total_variance += ($detail['counted_quantity'] - $detail['system_quantity']);
    }
}

$progress_percentage = $total_items > 0 ? round(($counted_items / $total_items) * 100) : 0;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="mb-4">
        <h2><i class="fas fa-clipboard-list me-2"></i>Enter Stock Count Results</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inventory</a></li>
                <li class="breadcrumb-item"><a href="index.php">Stock Counting</a></li>
                <li class="breadcrumb-item active">Enter Results</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape_html($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= escape_html($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Count Header Info -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card border-primary">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Count Number:</strong><br>
                            <span class="text-primary"><?= escape_html($count['count_number']) ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Date:</strong><br>
                            <?= date('Y-m-d', strtotime($count['count_date'])) ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Type:</strong><br>
                            <span class="badge bg-info"><?= $count['count_type'] ?></span>
                        </div>
                        <div class="col-md-3">
                            <strong>Status:</strong><br>
                            <?php
                            $badge_class = match($count['status']) {
                                'Draft' => 'secondary',
                                'In Progress' => 'warning',
                                'Completed' => 'success',
                                'Cancelled' => 'danger',
                                default => 'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $badge_class ?>"><?= $count['status'] ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">Progress</h6>
                    <div class="progress mb-2" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?= $progress_percentage ?>%">
                            <?= $progress_percentage ?>%
                        </div>
                    </div>
                    <p class="mb-0 text-muted">
                        <strong><?= $counted_items ?></strong> of <strong><?= $total_items ?></strong> items counted
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Count Details Form -->
    <div class="card">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Items to Count</h5>
            <?php if ($count['status'] !== 'Completed' && $count['status'] !== 'Cancelled'): ?>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="document.getElementById('countForm').submit();">
                    <i class="fas fa-save me-2"></i>Save Progress
                </button>
                <a href="count_report.php?id=<?= $count_id ?>" class="btn btn-info">
                    <i class="fas fa-chart-bar me-2"></i>View Report
                </a>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <form method="POST" id="countForm">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="save_counts">
                
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="countTable">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th style="width: 5%">#</th>
                                <th style="width: 10%">Code</th>
                                <th style="width: 20%">Item Name</th>
                                <th style="width: 10%">Location</th>
                                <th style="width: 8%">Unit</th>
                                <th style="width: 10%">System Qty</th>
                                <th style="width: 12%">Counted Qty</th>
                                <th style="width: 8%">Variance</th>
                                <th style="width: 12%">Notes</th>
                                <th style="width: 5%">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $row_num = 1; ?>
                            <?php foreach ($details as $detail): ?>
                            <?php 
                            $variance = null;
                            if ($detail['counted_quantity'] !== null) {
                                $variance = $detail['counted_quantity'] - $detail['system_quantity'];
                            }
                            $is_counted = $detail['counted_quantity'] !== null;
                            $is_disabled = $count['status'] === 'Completed' || $count['status'] === 'Cancelled';
                            ?>
                            <tr class="<?= $is_counted ? 'table-success' : '' ?>">
                                <td><?= $row_num++ ?></td>
                                <td><code><?= escape_html($detail['code']) ?></code></td>
                                <td><strong><?= escape_html($detail['name']) ?></strong></td>
                                <td><?= escape_html($detail['location_name']) ?></td>
                                <td><?= escape_html($detail['unit_code']) ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= format_number($detail['system_quantity'], 2) ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="number" 
                                           name="counted_quantity[<?= $detail['id'] ?>]" 
                                           class="form-control form-control-sm counted-input" 
                                           step="0.01"
                                           value="<?= $detail['counted_quantity'] !== null ? format_number($detail['counted_quantity'], 2) : '' ?>"
                                           data-system-qty="<?= $detail['system_quantity'] ?>"
                                           data-row-id="<?= $detail['id'] ?>"
                                           <?= $is_disabled ? 'disabled' : '' ?>
                                           placeholder="Enter count">
                                </td>
                                <td>
                                    <span id="variance_<?= $detail['id'] ?>" class="badge">
                                        <?php if ($variance !== null): ?>
                                            <?php if ($variance > 0): ?>
                                                <span class="badge bg-success">+<?= format_number($variance, 2) ?></span>
                                            <?php elseif ($variance < 0): ?>
                                                <span class="badge bg-danger"><?= format_number($variance, 2) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">0</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="text" 
                                           name="item_notes[<?= $detail['id'] ?>]" 
                                           class="form-control form-control-sm" 
                                           value="<?= escape_html($detail['notes'] ?? '') ?>"
                                           <?= $is_disabled ? 'disabled' : '' ?>
                                           placeholder="Notes">
                                </td>
                                <td class="text-center">
                                    <?php if ($is_counted): ?>
                                        <i class="fas fa-check-circle text-success" title="Counted"></i>
                                    <?php else: ?>
                                        <i class="fas fa-circle text-muted" title="Pending"></i>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <?php if ($count['status'] !== 'Completed' && $count['status'] !== 'Cancelled'): ?>
        <div class="card-footer bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>Progress:</strong> <?= $counted_items ?> / <?= $total_items ?> items counted
                </div>
                <div>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to complete this count? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="complete_count">
                        <button type="submit" class="btn btn-success" <?= $counted_items < $total_items ? 'disabled' : '' ?>>
                            <i class="fas fa-check-double me-2"></i>Complete Count
                        </button>
                    </form>
                    <a href="index.php" class="btn btn-secondary ms-2">Back to List</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Auto-calculate variance when count is entered
document.querySelectorAll('.counted-input').forEach(input => {
    input.addEventListener('input', function() {
        const rowId = this.dataset.rowId;
        const systemQty = parseFloat(this.dataset.systemQty);
        const countedQty = parseFloat(this.value);
        const varianceSpan = document.getElementById('variance_' + rowId);
        
        if (!isNaN(countedQty)) {
            const variance = countedQty - systemQty;
            
            if (variance > 0) {
                varianceSpan.innerHTML = '<span class="badge bg-success">+' + variance.toFixed(2) + '</span>';
            } else if (variance < 0) {
                varianceSpan.innerHTML = '<span class="badge bg-danger">' + variance.toFixed(2) + '</span>';
            } else {
                varianceSpan.innerHTML = '<span class="badge bg-secondary">0</span>';
            }
            
            // Highlight row
            this.closest('tr').classList.add('table-success');
        } else {
            varianceSpan.innerHTML = '<span class="text-muted">-</span>';
            this.closest('tr').classList.remove('table-success');
        }
    });
});

// Auto-save every 2 minutes
setInterval(function() {
    document.getElementById('countForm').submit();
}, 120000);
</script>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#countTable').DataTable({
        'paging': true,
        'pageLength': 50,
        'searching': true,
        'ordering': true,
        'info': true
    });
});
</script>
";

include __DIR__ . '/../../../templates/footer.php';
?>