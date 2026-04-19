<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();
$page_title = 'Process Stock Count - MJR Group ERP';
$errors = [];

$count_id = (int)get('id', 0);

if ($count_id <= 0) {
    redirect('index.php');
}

// Get count header
$count = db_fetch("
    SELECT * FROM stock_count_headers WHERE id = ?
", [$count_id]);

if (!$count) {
    set_flash('Stock count not found.', 'danger');
    redirect('index.php');
}

if ($count['status'] !== 'Completed') {
    set_flash('Only completed counts can be processed.', 'danger');
    redirect('count_report.php?id=' . $count_id);
}

// Get items with variance
$variances = db_fetch_all("
    SELECT scd.*,
           i.code,
           i.name,
           l.name as location_name,
           (scd.counted_quantity - scd.system_quantity) as variance
    FROM stock_count_details scd
    JOIN inventory_items i ON scd.item_id = i.id
    LEFT JOIN locations l ON scd.location_id = l.id
    WHERE scd.count_header_id = ? 
      AND scd.counted_quantity IS NOT NULL
      AND scd.counted_quantity != scd.system_quantity
    ORDER BY ABS(scd.counted_quantity - scd.system_quantity) DESC
", [$count_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token.';
    } else {
        $selected_items = post('adjust_items', []);
        
        if (empty($selected_items)) {
            $errors[] = 'Please select at least one item to adjust.';
        } else {
            try {
                db_execute("START TRANSACTION");
                
                $adjusted_count = 0;
                
                foreach ($selected_items as $detail_id) {
                    $detail = db_fetch("
                        SELECT * FROM stock_count_details WHERE id = ?
                    ", [$detail_id]);
                    
                    if ($detail && $detail['counted_quantity'] !== null) {
                        inventory_assert_stock_take_allows_stock_change(
                            intval($detail['location_id'] ?? 0),
                            'Stock count adjustment'
                        );

                        $variance = $detail['counted_quantity'] - $detail['system_quantity'];
                        
                        // Update inventory stock level
                        $existing = db_fetch("
                            SELECT id, quantity_available 
                            FROM inventory_stock_levels 
                            WHERE item_id = ? AND location_id = ?
                        ", [$detail['item_id'], $detail['location_id']]);
                        
                        if ($existing) {
                            db_execute("
                                UPDATE inventory_stock_levels 
                                SET quantity_available = ?
                                WHERE id = ?
                            ", [$detail['counted_quantity'], $existing['id']]);
                        } else {
                            db_insert("
                                INSERT INTO inventory_stock_levels (item_id, location_id, quantity_available)
                                VALUES (?, ?, ?)
                            ", [$detail['item_id'], $detail['location_id'], $detail['counted_quantity']]);
                        }
                        
                        // Record adjustment
                        db_insert("
                            INSERT INTO stock_count_adjustments 
                            (count_detail_id, item_id, location_id, adjustment_quantity, reason, adjusted_by)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ", [
                            $detail_id,
                            $detail['item_id'],
                            $detail['location_id'],
                            $variance,
                            "Stock count adjustment from count #{$count['count_number']}",
                            $_SESSION['user_id']
                        ]);
                        
                        // Create inventory transaction
                        db_insert("
                            INSERT INTO inventory_transactions 
                            (item_id, location_id, transaction_type, quantity, reference_number, notes, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ", [
                            $detail['item_id'],
                            $detail['location_id'],
                            'Stock Count Adjustment',
                            $variance,
                            $count['count_number'],
                            "Physical count adjustment. Variance: {$variance}",
                            $_SESSION['user_id']
                        ]);
                        
                        $adjusted_count++;
                    }
                }
                
                // Update count header status
                db_execute("
                    UPDATE stock_count_headers 
                    SET notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] Inventory adjusted for ', ?, ' items by user ', ?)
                    WHERE id = ?
                ", [$adjusted_count, $_SESSION['user_id'], $count_id]);
                
                db_execute("COMMIT");
                
                set_flash("Successfully adjusted inventory for {$adjusted_count} item(s)!", 'success');
                redirect('count_report.php?id=' . $count_id);
                
            } catch (Exception $e) {
                db_execute("ROLLBACK");
                log_error("Error processing count adjustments: " . $e->getMessage());
                $errors[] = 'An error occurred while processing adjustments.';
            }
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="mb-4">
        <h2><i class="fas fa-exchange-alt me-2"></i>Process Stock Count</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inventory</a></li>
                <li class="breadcrumb-item"><a href="index.php">Stock Counting</a></li>
                <li class="breadcrumb-item"><a href="count_report.php?id=<?= $count_id ?>">Report</a></li>
                <li class="breadcrumb-item active">Process</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape_html($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Warning:</strong> This will adjust your inventory quantities based on the physical count. This action cannot be undone.
    </div>

    <?php if (!empty($variances)): ?>
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Items with Variance</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 5%">
                                    <input type="checkbox" id="selectAll" class="form-check-input">
                                </th>
                                <th>Code</th>
                                <th>Item Name</th>
                                <th>Location</th>
                                <th>System Qty</th>
                                <th>Counted Qty</th>
                                <th>Variance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($variances as $item): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="adjust_items[]" value="<?= $item['id'] ?>" 
                                           class="form-check-input item-checkbox" checked>
                                </td>
                                <td><code><?= escape_html($item['code']) ?></code></td>
                                <td><strong><?= escape_html($item['name']) ?></strong></td>
                                <td><?= escape_html($item['location_name']) ?></td>
                                <td><?= format_number($item['system_quantity'], 2) ?></td>
                                <td><?= format_number($item['counted_quantity'], 2) ?></td>
                                <td>
                                    <?php if ($item['variance'] > 0): ?>
                                        <span class="badge bg-success">+<?= format_number($item['variance'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><?= format_number($item['variance'], 2) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= format_number($item['system_quantity'], 2) ?> → <?= format_number($item['counted_quantity'], 2) ?>
                                    </small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="text-end mt-3">
                    <a href="count_report.php?id=<?= $count_id ?>" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-warning" 
                            onclick="return confirm('Are you sure you want to adjust inventory for selected items? This cannot be undone.')">
                        <i class="fas fa-check me-2"></i>Adjust Inventory
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        No variances found. All counts match the system quantities.
    </div>
    <a href="count_report.php?id=<?= $count_id ?>" class="btn btn-primary">Back to Report</a>
    <?php endif; ?>
</div>

<script>
// Select/Deselect all checkboxes
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.item-checkbox').forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
