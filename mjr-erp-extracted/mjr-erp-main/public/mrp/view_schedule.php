<?php
/**
 * View Master Production Schedule Details
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'View Production Schedule - MJR Group ERP';
$company_id = active_company_id(1);

// Get schedule ID from URL
$schedule_id = get('id');
if (!$schedule_id) {
    set_flash('Schedule ID not provided.', 'error');
    redirect('master_schedule.php');
}

// Get schedule data
$schedule = db_fetch("
    SELECT mps.*, i.code as product_code, i.name as product_name, i.unit_of_measure
    FROM master_production_schedule mps
    JOIN inventory_items i ON mps.product_id = i.id
    WHERE mps.id = ? AND i.company_id = ?
", [$schedule_id, $company_id]);

if (!$schedule) {
    set_flash('Schedule not found.', 'error');
    redirect('master_schedule.php');
}

// Get related production orders
$work_orders = db_fetch_all("
    SELECT wo.*, l.name as location_name
    FROM work_orders wo
    LEFT JOIN locations l ON wo.location_id = l.id
    JOIN inventory_items i ON wo.product_id = i.id
    WHERE wo.product_id = ?
      AND i.company_id = ?
      AND wo.due_date BETWEEN ? AND ?
    ORDER BY wo.due_date
", [$schedule['product_id'], $company_id, $schedule['period_start'], $schedule['period_end']]);

// Calculate progress
$progress = $schedule['planned_quantity'] > 0 ? ($schedule['actual_quantity'] / $schedule['planned_quantity'] * 100) : 0;
$variance = $schedule['actual_quantity'] - $schedule['planned_quantity'];

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <!-- Schedule Details -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-calendar-week me-2"></i>Production Schedule Details</h3>
                    <div>
                        <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Schedule
                        </a>
                        <a href="master_schedule.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Product:</strong></td>
                                    <td><?= escape_html($schedule['product_code']) ?> - <?= escape_html($schedule['product_name']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Period Start:</strong></td>
                                    <td><?= format_date($schedule['period_start']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Period End:</strong></td>
                                    <td><?= format_date($schedule['period_end']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Planned Quantity:</strong></td>
                                    <td><?= format_number($schedule['planned_quantity'], 0) ?> <?= escape_html($schedule['unit_of_measure']) ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Actual Quantity:</strong></td>
                                    <td><?= format_number($schedule['actual_quantity'], 0) ?> <?= escape_html($schedule['unit_of_measure']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Variance:</strong></td>
                                    <td>
                                        <span class="<?= $variance >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?= $variance >= 0 ? '+' : '' ?><?= format_number($variance, 0) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'planned' => 'secondary',
                                            'in_progress' => 'warning',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $badge_class = $status_badges[$schedule['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= ucwords(str_replace('_', ' ', $schedule['status'])) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Progress:</strong></td>
                                    <td>
                                        <div class="progress" style="height: 20px; width: 200px;">
                                            <div class="progress-bar <?= $progress >= 100 ? 'bg-success' : 'bg-info' ?>" 
                                                 role="progressbar" style="width: <?= min(100, $progress) ?>%">
                                                <?= number_format($progress, 0) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Production Orders -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-tasks me-2"></i>Related Production Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($work_orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-striped">
                            <thead>
                                <tr>
                                    <th>Order Number #</th>
                                    <th>Location</th>
                                    <th>Quantity</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($work_orders as $wo): ?>
                                <tr>
                                    <td><a href="../production/view_production_order.php?id=<?= $wo['id'] ?>"><?= escape_html($wo['wo_number']) ?></a></td>
                                    <td><?= escape_html($wo['location_name']) ?></td>
                                    <td><?= format_number($wo['quantity'], 0) ?></td>
                                    <td><?= format_date($wo['due_date']) ?></td>
                                    <td>
                                        <?php
                                        $wo_status_badges = [
                                            'planned' => 'secondary',
                                            'in_progress' => 'warning',
                                            'completed' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $wo_badge = $wo_status_badges[$wo['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $wo_badge ?>"><?= ucwords(str_replace('_', ' ', $wo['status'])) ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No related production orders found for this schedule period</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
