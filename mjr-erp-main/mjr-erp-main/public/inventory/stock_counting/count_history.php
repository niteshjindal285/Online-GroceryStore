<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$page_title = 'Stock Count History - MJR Group ERP';

$item_id = (int)get('item_id', 0);

// Get item details if specified
$item = null;
if ($item_id > 0) {
    $item = db_fetch("SELECT * FROM inventory_items WHERE id = ?", [$item_id]);
}

// Build query
$where = [];
$params = [];

if ($item_id > 0) {
    $where[] = "scd.item_id = ?";
    $params[] = $item_id;
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get count history
$history = db_fetch_all("
    SELECT sch.count_number,
           sch.count_date,
           sch.count_type,
           sch.status,
           i.code,
           i.name,
           l.name as location_name,
           scd.system_quantity,
           scd.counted_quantity,
           (scd.counted_quantity - scd.system_quantity) as variance,
           u.username as counted_by,
           scd.counted_at
    FROM stock_count_details scd
    JOIN stock_count_headers sch ON scd.count_header_id = sch.id
    JOIN inventory_items i ON scd.item_id = i.id
    LEFT JOIN locations l ON scd.location_id = l.id
    LEFT JOIN users u ON scd.counted_by = u.id
    {$where_sql}
    ORDER BY scd.counted_at DESC
", $params);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="mb-4">
        <h2><i class="fas fa-history me-2"></i>Stock Count History</h2>
        <?php if ($item): ?>
        <p class="lead">Item: <strong><?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?></strong></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="historyTable">
                    <thead>
                        <tr>
                            <th>Count #</th>
                            <th>Date</th>
                            <th>Type</th>
                            <?php if (!$item): ?>
                            <th>Item</th>
                            <?php endif; ?>
                            <th>Location</th>
                            <th>System Qty</th>
                            <th>Counted Qty</th>
                            <th>Variance</th>
                            <th>Counted By</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $record): ?>
                        <tr>
                            <td><?= escape_html($record['count_number']) ?></td>
                            <td><?= format_date($record['count_date']) ?></td>
                            <td><span class="badge bg-info"><?= $record['count_type'] ?></span></td>
                            <?php if (!$item): ?>
                            <td>
                                <code><?= escape_html($record['code']) ?></code><br>
                                <small><?= escape_html($record['name']) ?></small>
                            </td>
                            <?php endif; ?>
                            <td><?= escape_html($record['location_name']) ?></td>
                            <td><?= format_number($record['system_quantity'], 2) ?></td>
                            <td>
                                <?php if ($record['counted_quantity'] !== null): ?>
                                    <?= format_number($record['counted_quantity'], 2) ?>
                                <?php else: ?>
                                    <span class="text-muted">Not Counted</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['counted_quantity'] !== null): ?>
                                    <?php if ($record['variance'] > 0): ?>
                                        <span class="badge bg-success">+<?= format_number($record['variance'], 2) ?></span>
                                    <?php elseif ($record['variance'] < 0): ?>
                                        <span class="badge bg-danger"><?= format_number($record['variance'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">0</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><small><?= escape_html($record['counted_by'] ?? '-') ?></small></td>
                            <td>
                                <?php
                                $badge_class = match($record['status']) {
                                    'Completed' => 'success',
                                    'In Progress' => 'warning',
                                    'Draft' => 'secondary',
                                    'Cancelled' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $badge_class ?>"><?= $record['status'] ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-4x text-muted mb-3"></i>
                <h3>No History Found</h3>
                <p class="text-muted">No stock counting history available</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#historyTable').DataTable({
        'order': [[1, 'desc']],
        'pageLength': 50
    });
});
</script>
";

include __DIR__ . '/../../../templates/footer.php';
?>