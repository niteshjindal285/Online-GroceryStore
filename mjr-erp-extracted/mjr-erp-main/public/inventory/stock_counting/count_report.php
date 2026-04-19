<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$page_title = 'Stock Count Report - MJR Group ERP';

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

// Get count details with variance
$details = db_fetch_all("
    SELECT scd.*,
           i.code,
           i.name,
           i.cost_price,
           l.name as location_name,
           u.code as unit_code,
           usr.username as counted_by_name,
           (scd.counted_quantity - scd.system_quantity) as variance,
           ((scd.counted_quantity - scd.system_quantity) * i.cost_price) as variance_value
    FROM stock_count_details scd
    JOIN inventory_items i ON scd.item_id = i.id
    LEFT JOIN locations l ON scd.location_id = l.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    LEFT JOIN users usr ON scd.counted_by = usr.id
    WHERE scd.count_header_id = ?
    ORDER BY ABS(scd.counted_quantity - scd.system_quantity) DESC
", [$count_id]);

// Calculate statistics
$total_items = count($details);
$counted_items = 0;
$items_with_variance = 0;
$total_variance_value = 0;
$positive_variance = 0;
$negative_variance = 0;

foreach ($details as $detail) {
    if ($detail['counted_quantity'] !== null) {
        $counted_items++;
        
        $variance = $detail['variance'];
        if ($variance != 0) {
            $items_with_variance++;
            $total_variance_value += $detail['variance_value'];
            
            if ($variance > 0) {
                $positive_variance++;
            } else {
                $negative_variance++;
            }
        }
    }
}

$accuracy_percentage = $total_items > 0 ? round((($total_items - $items_with_variance) / $total_items) * 100, 2) : 0;

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-chart-bar me-2"></i>Stock Count Report</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Inventory</a></li>
                        <li class="breadcrumb-item"><a href="index.php">Stock Counting</a></li>
                        <li class="breadcrumb-item active">Report</li>
                    </ol>
                </nav>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print me-2"></i>Print Report
                </button>
                <?php if ($count['status'] === 'Completed'): ?>
                <a href="process_count.php?id=<?= $count_id ?>" class="btn btn-warning">
                    <i class="fas fa-exchange-alt me-2"></i>Adjust Inventory
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>

    <!-- Count Header Info -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Count Information</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <strong>Count Number:</strong><br>
                    <?= escape_html($count['count_number']) ?>
                </div>
                <div class="col-md-3">
                    <strong>Date:</strong><br>
                    <?= date('Y-m-d', strtotime($count['count_date'])) ?>
                </div>
                <div class="col-md-2">
                    <strong>Type:</strong><br>
                    <span class="badge bg-info"><?= $count['count_type'] ?></span>
                </div>
                <div class="col-md-2">
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
                <div class="col-md-2">
                    <strong>Created By:</strong><br>
                    <?= escape_html($count['created_by_name']) ?>
                </div>
            </div>
            <?php if ($count['location_name'] || $count['category_name']): ?>
            <hr>
            <div class="row">
                <?php if ($count['location_name']): ?>
                <div class="col-md-6">
                    <strong>Location:</strong> <?= escape_html($count['location_name']) ?>
                </div>
                <?php endif; ?>
                <?php if ($count['category_name']): ?>
                <div class="col-md-6">
                    <strong>Category:</strong> <?= escape_html($count['category_name']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($count['notes']): ?>
            <hr>
            <strong>Notes:</strong> <?= escape_html($count['notes']) ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h3 class="text-primary"><?= $counted_items ?> / <?= $total_items ?></h3>
                    <p class="mb-0 text-muted">Items Counted</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h3 class="text-success"><?= $accuracy_percentage ?>%</h3>
                    <p class="mb-0 text-muted">Accuracy Rate</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h3 class="text-warning"><?= $items_with_variance ?></h3>
                    <p class="mb-0 text-muted">Items with Variance</p>
                    <small>(+<?= $positive_variance ?> / -<?= $negative_variance ?>)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <h3 class="<?= $total_variance_value >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= format_currency($total_variance_value) ?>
                    </h3>
                    <p class="mb-0 text-muted">Total Variance Value</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Variance Details -->
    <div class="card">
        <div class="card-header bg-transparent">
            <h5 class="mb-0"><i class="fas fa-table me-2"></i>Count Details</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="reportTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Item Name</th>
                            <th>Location</th>
                            <th>Unit</th>
                            <th>System Qty</th>
                            <th>Counted Qty</th>
                            <th>Variance</th>
                            <th>Variance %</th>
                            <th>Value Impact</th>
                            <th>Counted By</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $detail): ?>
                        <?php 
                        $variance_pct = $detail['system_quantity'] > 0 
                            ? round(($detail['variance'] / $detail['system_quantity']) * 100, 2)
                            : 0;
                        ?>
                        <tr class="<?= $detail['counted_quantity'] === null ? 'table-warning' : ($detail['variance'] == 0 ? '' : 'table-danger') ?>">
                            <td><code><?= escape_html($detail['code']) ?></code></td>
                            <td><strong><?= escape_html($detail['name']) ?></strong></td>
                            <td><?= escape_html($detail['location_name']) ?></td>
                            <td><?= escape_html($detail['unit_code']) ?></td>
                            <td><?= format_number($detail['system_quantity'], 2) ?></td>
                            <td>
                                <?php if ($detail['counted_quantity'] !== null): ?>
                                    <strong><?= format_number($detail['counted_quantity'], 2) ?></strong>
                                <?php else: ?>
                                    <span class="text-muted">Not Counted</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($detail['counted_quantity'] !== null): ?>
                                    <?php if ($detail['variance'] > 0): ?>
                                        <span class="badge bg-success">+<?= format_number($detail['variance'], 2) ?></span>
                                    <?php elseif ($detail['variance'] < 0): ?>
                                        <span class="badge bg-danger"><?= format_number($detail['variance'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($detail['counted_quantity'] !== null): ?>
                                    <?= $variance_pct ?>%
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($detail['counted_quantity'] !== null): ?>
                                    <span class="<?= $detail['variance_value'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= format_currency($detail['variance_value']) ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><small><?= escape_html($detail['counted_by_name'] ?? '-') ?></small></td>
                            <td><small><?= escape_html($detail['notes'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="8" class="text-end">Total Variance Value:</th>
                            <th>
                                <strong class="<?= $total_variance_value >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= format_currency($total_variance_value) ?>
                                </strong>
                            </th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, nav, .breadcrumb { display: none; }
    .card { border: 1px solid #ddd; }
}
</style>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#reportTable').DataTable({
        'order': [[6, 'desc']],
        'pageLength': 50,
        'dom': 'Bfrtip',
        'buttons': [
            'copy', 'csv', 'excel', 'pdf'
        ]
    });
});
</script>
";

include __DIR__ . '/../../../templates/footer.php';
?>