<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$page_title = 'Stock Counting - MJR Group ERP';

// Get all stock counts
$counts = db_fetch_all("
    SELECT sch.*, 
           u.username as created_by_name,
           l.name as location_name,
           c.name as category_name,
           COUNT(scd.id) as total_items,
           SUM(CASE WHEN scd.counted_quantity IS NOT NULL THEN 1 ELSE 0 END) as counted_items
    FROM stock_count_headers sch
    LEFT JOIN users u ON sch.created_by = u.id
    LEFT JOIN locations l ON sch.location_id = l.id
    LEFT JOIN categories c ON sch.category_id = c.id
    LEFT JOIN stock_count_details scd ON sch.id = scd.count_header_id
    GROUP BY sch.id
    ORDER BY sch.created_at DESC
");

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="fas fa-clipboard-check me-3"></i>Stock Counting</h1>
            <p class="text-muted">Manage physical inventory counts and variance reports</p>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="create_count_sheet.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Stock Count
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($counts)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="countsTable">
                    <thead>
                        <tr>
                            <th>Count #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($counts as $count): ?>
                        <tr>
                            <td><strong><?= escape_html($count['count_number']) ?></strong></td>
                            <td><?= format_date($count['count_date']) ?></td>
                            <td><span class="badge bg-info"><?= $count['count_type'] ?></span></td>
                            <td><?= escape_html($count['location_name'] ?? 'All') ?></td>
                            <td><?= escape_html($count['category_name'] ?? 'All') ?></td>
                            <td>
                                <?php 
                                $progress = $count['total_items'] > 0 ? round(($count['counted_items'] / $count['total_items']) * 100) : 0;
                                ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $progress ?>%">
                                        <?= $count['counted_items'] ?> / <?= $count['total_items'] ?>
                                    </div>
                                </div>
                            </td>
                            <td>
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
                            </td>
                            <td><?= escape_html($count['created_by_name']) ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <?php if ($count['status'] !== 'Completed'): ?>
                                    <a href="enter_count_results.php?id=<?= $count['id'] ?>" 
                                       class="btn btn-outline-primary" title="Enter Counts">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="count_report.php?id=<?= $count['id'] ?>" 
                                       class="btn btn-outline-info" title="View Report">
                                        <i class="fas fa-chart-bar"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-check fa-4x text-muted mb-3"></i>
                <h3>No Stock Counts</h3>
                <p class="text-muted mb-3">Create your first stock counting sheet</p>
                <a href="create_count_sheet.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>New Stock Count
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
    $('#countsTable').DataTable({
        'order': [[1, 'desc']],
        'pageLength': 25
    });
});
</script>
";
include __DIR__ . '/../../../templates/footer.php';
?>