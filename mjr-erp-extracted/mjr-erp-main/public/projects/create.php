<?php
/**
 * Projects Module – Create Project
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/project_service.php';

require_login();
require_permission('manage_projects');

$page_title = 'New Project – MJR Group ERP';
$company_id = active_company_id(1);
$errors = [];
$form   = [];

// Customers for dropdown
$customers = [];
try {
    $customers = db_fetch_all("SELECT id, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
} catch (Exception $e) {
    log_error("Error loading customers for project create: " . $e->getMessage());
    $errors[] = sanitize_db_error($e->getMessage());
}

if (is_post()) {
    if (!verify_csrf_token(post('csrf_token'))) {
        $errors[] = 'Invalid security token.';
    } else {
        $form = [
            'project_name'        => trim(post('project_name', '')),
            'project_description' => trim(post('project_description', '')),
            'customer_id'         => intval(post('customer_id', 0)),
            'project_total_value' => floatval(post('project_total_value', 0)),
            'start_date'          => post('start_date', ''),
            'end_date'            => post('end_date', ''),
            'project_manager'     => trim(post('project_manager', '')),
        ];

        if (empty($form['project_name']))   $errors[] = 'Project name is required.';
        if ($form['customer_id'] <= 0)      $errors[] = 'Please select a customer.';
        if ($form['project_total_value'] <= 0) $errors[] = 'Contract value must be greater than zero.';

        if (empty($errors)) {
            try {
                $result = project_create_with_stages($form, current_user_id());
                if ($result) {
                    $project_ref = $result['project_number'] ?? $result['code'] ?? (string)($result['project_id'] ?? '');
                    set_flash("Project <strong>" . escape_html($form['project_name']) . "</strong> created (#" . escape_html($project_ref) . "). Now add phases below.", 'success');
                    redirect(url('projects/phases.php', ['id' => $result['project_id']]));
                } else {
                    $errors[] = 'Failed to create project. Please try again.';
                }
            } catch (Exception $e) {
                log_error("Error creating project: " . $e->getMessage());
                $errors[] = sanitize_db_error($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('projects/index.php') ?>">Projects</a></li>
                    <li class="breadcrumb-item active">New Project</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold"><i class="fas fa-plus-circle me-2 text-primary"></i>Create New Project</h1>
            <p class="text-muted">Step 1 of 2 – Project Details</p>
        </div>
    </div>

    <!-- Step indicator -->
    <div class="d-flex mb-4 gap-2">
        <div class="flex-fill text-center py-2 rounded bg-primary text-white fw-bold">
            <i class="fas fa-building me-1"></i> 1. Project Details
        </div>
        <div class="flex-fill text-center py-2 rounded bg-secondary text-white opacity-50">
            <i class="fas fa-tasks me-1"></i> 2. Phases & Milestones
        </div>
        <div class="flex-fill text-center py-2 rounded bg-secondary text-white opacity-50">
            <i class="fas fa-file-invoice-dollar me-1"></i> 3. Invoicing
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-project-diagram me-2"></i>Project Information</h5></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Project Name <span class="text-danger">*</span></label>
                        <input type="text" name="project_name" class="form-control form-control-lg"
                               value="<?= escape_html($form['project_name'] ?? '') ?>"
                               placeholder="e.g. Office Building Construction Phase A" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                        <select name="customer_id" class="form-select form-select-lg" required>
                            <option value="">— Select Customer —</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($form['customer_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>>
                                <?= escape_html($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Contract / Total Value <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= CURRENCY_DISPLAY ?></span>
                            <input type="number" name="project_total_value" class="form-control form-control-lg"
                                   step="0.01" min="0"
                                   value="<?= $form['project_total_value'] ?? '' ?>"
                                   placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= escape_html($form['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Expected End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= escape_html($form['end_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Project Manager</label>
                        <input type="text" name="project_manager" class="form-control"
                               value="<?= escape_html($form['project_manager'] ?? '') ?>"
                               placeholder="Name of project manager">
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Description / Scope of Work</label>
                        <textarea name="project_description" class="form-control" rows="4"
                                  placeholder="Describe the project scope, deliverables, and objectives…"><?= escape_html($form['project_description'] ?? '') ?></textarea>
                    </div>
                </div>

                <hr class="my-4">
                <div class="d-flex justify-content-between">
                    <a href="<?= url('projects/index.php') ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        Next: Add Phases <i class="fas fa-arrow-right ms-2"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
