<?php
/**
 * Edit Master Production Schedule
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Production Schedule - MJR Group ERP';
$company_id = active_company_id(1);

// Get schedule ID from URL
$schedule_id = get('id');
if (!$schedule_id) {
    set_flash('Schedule ID not provided.', 'error');
    redirect('master_schedule.php');
}

// Get schedule data
$schedule = db_fetch("
    SELECT mps.*, i.code as product_code, i.name as product_name
    FROM master_production_schedule mps
    JOIN inventory_items i ON mps.product_id = i.id
    WHERE mps.id = ? AND i.company_id = ?
", [$schedule_id, $company_id]);

if (!$schedule) {
    set_flash('Schedule not found.', 'error');
    redirect('master_schedule.php');
}

// Get manufactured products for dropdown
$products = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $product_id       = post('product_id', '');
        $period_start_val = post('period_start', '');
        $period_end_val   = post('period_end', '');
        $planned_quantity = post('planned_quantity', '');
        $actual_quantity  = post('actual_quantity', '0');
        $status           = post('status', '');
        $is_active        = post('is_active') ? 1 : 0;

        $errors = [];
        if (empty($product_id)) $errors['product_id'] = err_required();
        if (empty($status)) $errors['status'] = err_required();
        if (empty($period_start_val)) $errors['period_start'] = err_required();
        if (empty($period_end_val)) $errors['period_end'] = err_required();
        if ($planned_quantity === '') $errors['planned_quantity'] = err_required();
        
        if ($planned_quantity !== '' && floatval($planned_quantity) < 0) {
            $errors['planned_quantity'] = 'Planned Quantity must be 0 or greater';
        }
        if ($actual_quantity !== '' && floatval($actual_quantity) < 0) {
            $errors['actual_quantity'] = 'Actual Quantity must be 0 or greater';
        }

        if (empty($errors)) {
            try {
                $sql = "UPDATE master_production_schedule SET 
                        product_id = ?, period_start = ?, period_end = ?, planned_quantity = ?, 
                        actual_quantity = ?, status = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                db_query($sql, [
                    intval($product_id), 
                    $period_start_val, 
                    $period_end_val, 
                    floatval($planned_quantity), 
                    floatval($actual_quantity), 
                    $status, 
                    $is_active, 
                    $schedule_id
                ]);
                
                set_flash('Production schedule updated successfully!', 'success');
                redirect('master_schedule.php');
            } catch (Exception $e) {
                log_error("Error updating schedule: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-edit me-2"></i>Edit Production Schedule</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['product_id']) ? 'is-invalid' : '' ?>" name="product_id" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>" <?= post('product_id', $schedule['product_id']) == $product['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($product['code']) ?> - <?= escape_html($product['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['product_id'])): ?>
                                    <div class="invalid-feedback"><?= $errors['product_id'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" name="status" required>
                                    <option value="planned" <?= post('status', $schedule['status']) == 'planned' ? 'selected' : '' ?>>Planned</option>
                                    <option value="in_progress" <?= post('status', $schedule['status']) == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="completed" <?= post('status', $schedule['status']) == 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= post('status', $schedule['status']) == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <?php if (isset($errors['status'])): ?>
                                    <div class="invalid-feedback"><?= $errors['status'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period Start <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?= isset($errors['period_start']) ? 'is-invalid' : '' ?>" name="period_start" required value="<?= escape_html(post('period_start', $schedule['period_start'])) ?>">
                                <?php if (isset($errors['period_start'])): ?>
                                    <div class="invalid-feedback"><?= $errors['period_start'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Period End <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?= isset($errors['period_end']) ? 'is-invalid' : '' ?>" name="period_end" required value="<?= escape_html(post('period_end', $schedule['period_end'])) ?>">
                                <?php if (isset($errors['period_end'])): ?>
                                    <div class="invalid-feedback"><?= $errors['period_end'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Planned Quantity <span class="text-danger">*</span></label>
                                <input type="number" class="form-control <?= isset($errors['planned_quantity']) ? 'is-invalid' : '' ?>" name="planned_quantity" required min="0" value="<?= escape_html(post('planned_quantity', $schedule['planned_quantity'])) ?>">
                                <?php if (isset($errors['planned_quantity'])): ?>
                                    <div class="invalid-feedback"><?= $errors['planned_quantity'] ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Actual Quantity</label>
                                <input type="number" class="form-control <?= isset($errors['actual_quantity']) ? 'is-invalid' : '' ?>" name="actual_quantity" min="0" value="<?= escape_html(post('actual_quantity', $schedule['actual_quantity'])) ?>">
                                <?php if (isset($errors['actual_quantity'])): ?>
                                    <div class="invalid-feedback"><?= $errors['actual_quantity'] ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= post('is_active', $schedule['is_active']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Active Schedule
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="master_schedule.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Schedule
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
