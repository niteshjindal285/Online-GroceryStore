<?php
/**
 * MRP - Master Production Schedule
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Master Production Schedule - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// Get all production schedules
$schedules = db_fetch_all("
    SELECT mps.*, i.code as product_code, i.name as product_name, u.code as unit_code
    FROM master_production_schedule mps
    JOIN inventory_items i ON mps.product_id = i.id
    JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE i.company_id = ?
    ORDER BY mps.period_start DESC, mps.id DESC
", [$company_id]);

// Get manufactured products for dropdown
$products = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);


// Calculate statistics
$total_planned = array_sum(array_column($schedules, 'planned_quantity'));
$total_actual = array_sum(array_column($schedules, 'actual_quantity'));
$active_schedules = count(array_filter($schedules, fn($s) => $s['status'] === 'in_progress'));
$completion_rate = $total_planned > 0 ? ($total_actual / $total_planned) * 100 : 0;

// Handle add schedule
if (is_post() && isset($_POST['product_id'])) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $product_id = post('product_id');
            $period_start = post('period_start');
            $period_end = post('period_end');
            $planned_quantity = post('planned_quantity', 0);
            
            $sql = "INSERT INTO master_production_schedule (product_id, period_start, period_end, planned_quantity, actual_quantity, status, is_active, created_at) 
                    VALUES (?, ?, ?, ?, 0, 'planned', 1, NOW())";
            db_insert($sql, [$product_id, $period_start, $period_end, $planned_quantity]);
            
            set_flash('Production schedule created successfully!', 'success');
            redirect('master_schedule.php');
        } catch (Exception $e) {
            log_error("Error creating schedule: " . $e->getMessage());
            set_flash('Error creating schedule.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-calendar-week me-3"></i>Master Production Schedule</h1>
            <p class="text-muted mb-0">Showing schedules for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                <i class="fas fa-plus me-2"></i>Add Schedule Entry
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Planned Units</h6>
                            <h3><?= format_number($total_planned, 0) ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-cubes fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Completed Units</h6>
                            <h3><?= format_number($total_actual, 0) ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Active Schedules</h6>
                            <h3><?= $active_schedules ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Completion Rate</h6>
                            <h3><?= number_format($completion_rate, 1) ?>%</h3>
                        </div>
                        <div>
                            <i class="fas fa-percentage fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Production Schedule Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Production Schedule</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($schedules)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="scheduleTable">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Period Start</th>
                            <th>Period End</th>
                            <th>Planned Quantity</th>
                            <th>Actual Quantity</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Variance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schedules as $schedule): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?= escape_html($schedule['product_name']) ?></strong><br>
                                    <small class="text-muted"><?= escape_html($schedule['product_code']) ?></small>
                                </div>
                            </td>
                            <td><?= format_date($schedule['period_start']) ?></td>
                            <td><?= format_date($schedule['period_end']) ?></td>
                            <td>
                                <span class="badge bg-primary"><?= format_number($schedule['planned_quantity'], 0) ?> <?= escape_html($schedule['unit_code']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?= format_number($schedule['actual_quantity'], 0) ?> <?= escape_html($schedule['unit_code']) ?></span>
                            </td>
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
                            <td>
                                <?php 
                                $progress = $schedule['planned_quantity'] > 0 ? ($schedule['actual_quantity'] / $schedule['planned_quantity'] * 100) : 0;
                                $progress_class = $progress >= 100 ? 'bg-success' : ($progress >= 75 ? 'bg-warning' : 'bg-info');
                                ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar <?= $progress_class ?>" 
                                         role="progressbar" style="width: <?= min(100, $progress) ?>%">
                                        <?= number_format($progress, 0) ?>%
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php $variance = $schedule['actual_quantity'] - $schedule['planned_quantity']; ?>
                                <span class="<?= $variance >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $variance >= 0 ? '+' : '' ?><?= $variance ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-outline-primary" title="Edit Schedule">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="view_schedule.php?id=<?= $schedule['id'] ?>" class="btn btn-outline-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-calendar-week fa-3x text-muted mb-3"></i>
                <p class="text-muted">No production schedules found</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus me-2"></i>Create First Schedule
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Schedule Modal -->
<div class="modal fade" id="addScheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Production Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>"><?= escape_html($product['code']) ?> - <?= escape_html($product['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="period_start" class="form-label">Period Start <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="period_start" name="period_start" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="period_end" class="form-label">Period End <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="period_end" name="period_end" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="planned_quantity" class="form-label">Planned Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="planned_quantity" name="planned_quantity" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#scheduleTable').DataTable({
        'order': [[1, 'desc']],
        'pageLength': 25
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
