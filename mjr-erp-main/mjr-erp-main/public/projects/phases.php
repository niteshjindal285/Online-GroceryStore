<?php
/**
 * Projects Module – Phases / Milestones Manager (Step 2)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/project_service.php';

require_login();
require_permission('manage_projects');

$project_id = intval(get_param('id', 0));
if ($project_id <= 0) { set_flash('Invalid project.', 'error'); redirect(url('projects/index.php')); }

$project = project_get_by_id($project_id);
if (!$project) { set_flash('Project not found.', 'error'); redirect(url('projects/index.php')); }

$page_title = 'Phases – ' . $project['name'] . ' – MJR Group ERP';

// --- Handle Add Phase ---
if (is_post() && post('action') === 'add_phase') {
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid token.', 'error');
    } else {
        $data = [
            'stage_name' => trim(post('stage_name', '')),
            'percentage' => floatval(post('percentage', 0)),
            'amount'     => floatval(post('amount', 0)),
            'details'    => trim(post('details', '')),
            'due_date'   => post('due_date', ''),
        ];
        if (empty($data['stage_name'])) {
            set_flash('Phase name is required.', 'error');
        } else {
            if (project_add_phase($data, $project_id)) {
                set_flash('Phase added successfully.', 'success');
            } else {
                set_flash('Failed to add phase.', 'error');
            }
        }
    }
    redirect(url('projects/phases.php', ['id' => $project_id]));
}

// --- Handle Delete Phase ---
if (is_post() && post('action') === 'delete_phase') {
    if (verify_csrf_token(post('csrf_token'))) {
        $phase_id = intval(post('phase_id'));
        if ($phase_id > 0) { project_delete_phase($phase_id); set_flash('Phase removed.', 'success'); }
    }
    redirect(url('projects/phases.php', ['id' => $project_id]));
}

// --- Handle Status Update ---
if (is_post() && post('action') === 'update_status') {
    if (verify_csrf_token(post('csrf_token'))) {
        project_update_phase_status(intval(post('phase_id')), post('status'));
        set_flash('Phase status updated.', 'success');
    }
    redirect(url('projects/phases.php', ['id' => $project_id]));
}

$phases = project_get_stages($project_id);
$total_phase_pct = array_sum(array_column($phases, 'percentage'));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('projects/index.php') ?>">Projects</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('projects/view.php', ['id'=>$project_id]) ?>"><?= escape_html($project['name']) ?></a></li>
                    <li class="breadcrumb-item active">Phases</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold"><i class="fas fa-tasks me-2 text-primary"></i>Phases &amp; Milestones</h1>
            <p class="text-muted mb-0">
                <strong><?= escape_html($project['name']) ?></strong> &bull;
                <?= escape_html($project['customer_name'] ?? '') ?> &bull;
                Total: <strong><?= CURRENCY_DISPLAY ?> <?= number_format($project['total_value'], 2) ?></strong>
            </p>
        </div>
        <div class="col-auto d-flex gap-2 align-items-start">
            <a href="<?= url('projects/invoices.php', ['id'=>$project_id]) ?>" class="btn btn-success">
                <i class="fas fa-file-invoice-dollar me-2"></i>View Invoices
            </a>
            <a href="<?= url('projects/view.php', ['id'=>$project_id]) ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i>Overview
            </a>
        </div>
    </div>

    <!-- Step indicator -->
    <div class="d-flex mb-4 gap-2">
        <a href="<?= url('projects/create.php') ?>" class="flex-fill text-center py-2 rounded text-white text-decoration-none" style="background:#6c757d;opacity:.5">
            <i class="fas fa-building me-1"></i> 1. Project Details
        </a>
        <div class="flex-fill text-center py-2 rounded bg-primary text-white fw-bold">
            <i class="fas fa-tasks me-1"></i> 2. Phases &amp; Milestones
        </div>
        <a href="<?= url('projects/invoices.php', ['id'=>$project_id]) ?>" class="flex-fill text-center py-2 rounded text-white text-decoration-none" style="background:#6c757d;opacity:.5">
            <i class="fas fa-file-invoice-dollar me-1"></i> 3. Invoicing
        </a>
    </div>

    <div class="row">
        <!-- Phases List -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Project Phases</h5>
                    <span class="badge bg-<?= abs($total_phase_pct - 100) < 0.1 ? 'success' : ($total_phase_pct > 100 ? 'danger' : 'warning') ?> fs-6">
                        <?= number_format($total_phase_pct, 1) ?>% allocated
                    </span>
                </div>

                <?php if (empty($phases)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-layer-group fa-3x mb-3 opacity-25"></i>
                    <p>No phases yet. Add your first milestone below.</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($phases as $ph): ?>
                    <?php
                        $sc = ['pending'=>'secondary','in_progress'=>'primary','complete'=>'success','invoiced'=>'warning','paid'=>'dark'][$ph['status']] ?? 'secondary';
                        $icons = ['pending'=>'fa-clock','in_progress'=>'fa-spinner','complete'=>'fa-check-circle','invoiced'=>'fa-file-invoice','paid'=>'fa-check-double'];
                        $icon = $icons[$ph['status']] ?? 'fa-circle';
                    ?>
                    <div class="list-group-item px-4 py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas <?= $icon ?> text-<?= $sc ?>"></i>
                                    <strong class="fs-6"><?= escape_html($ph['stage_name']) ?></strong>
                                    <span class="badge bg-<?= $sc ?>"><?= ucfirst(str_replace('_',' ',$ph['status'])) ?></span>
                                </div>
                                <div class="d-flex gap-4 text-muted small">
                                    <span><i class="fas fa-percent me-1"></i><?= number_format($ph['percentage'], 1) ?>%</span>
                                    <span><i class="fas fa-dollar-sign me-1"></i><?= CURRENCY_DISPLAY ?> <?= number_format($ph['amount'], 2) ?></span>
                                    <?php if (!empty($ph['due_date'] ?? null)): ?><span><i class="fas fa-calendar me-1"></i><?= format_date($ph['due_date']) ?></span><?php endif; ?>
                                    <?php if (!empty($ph['invoice_number'] ?? null)): ?><span class="text-success"><i class="fas fa-receipt me-1"></i><?= escape_html($ph['invoice_number']) ?></span><?php endif; ?>
                                </div>
                                <?php if (!empty($ph['details'] ?? null)): ?><p class="text-muted small mb-0 mt-1"><?= escape_html($ph['details']) ?></p><?php endif; ?>
                            </div>
                            <div class="d-flex gap-1 ms-3">
                                <!-- Status change dropdown -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="phase_id" value="<?= $ph['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()" style="width:140px">
                                        <?php foreach (['pending','in_progress','complete','invoiced','paid'] as $s): ?>
                                        <option value="<?= $s ?>" <?= $ph['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                                <!-- Invoice this phase -->
                                <?php if (!in_array($ph['status'], ['invoiced','paid'])): ?>
                                <a href="<?= url('projects/invoices.php', ['id'=>$project_id,'phase_id'=>$ph['id']]) ?>"
                                   class="btn btn-sm btn-outline-success" title="Create Invoice for this Phase">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </a>
                                <?php endif; ?>
                                <!-- Delete -->
                                <?php if ($ph['status'] === 'pending'): ?>
                                <form method="POST" onsubmit="return confirm('Remove this phase?')" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="action" value="delete_phase">
                                    <input type="hidden" name="phase_id" value="<?= $ph['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Phase">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Phase Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Add New Phase</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="addPhaseForm">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="add_phase">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phase Name <span class="text-danger">*</span></label>
                            <input type="text" name="stage_name" class="form-control" placeholder="e.g. Foundation Work" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">% of Contract Value</label>
                            <div class="input-group">
                                <input type="number" name="percentage" id="phasePercent" class="form-control" step="0.01" min="0" max="100" placeholder="0.00" oninput="calcPhaseAmount(this)">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Phase Amount</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_DISPLAY ?></span>
                                <input type="number" name="amount" id="phaseAmount" class="form-control" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <small class="text-muted">Auto-calculated from % or enter manually</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes / Details</label>
                            <textarea name="details" class="form-control" rows="2" placeholder="Deliverables for this phase…"></textarea>
                        </div>

                        <!-- Remaining allocation indicator -->
                        <div class="alert alert-<?= abs($total_phase_pct - 100) < 0.1 ? 'success' : 'info' ?> py-2 small">
                            <i class="fas fa-info-circle me-1"></i>
                            <?= number_format(max(0, 100 - $total_phase_pct), 1) ?>% remaining to allocate
                            (<?= CURRENCY_DISPLAY ?> <?= number_format(max(0, $project['total_value'] * (100 - $total_phase_pct) / 100), 2) ?>)
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add Phase
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
const totalValue = " . floatval($project['total_value']) . ";
function calcPhaseAmount(el) {
    const pct = parseFloat(el.value) || 0;
    document.getElementById('phaseAmount').value = (totalValue * pct / 100).toFixed(2);
}
// Also calc % from amount
document.getElementById('phaseAmount').addEventListener('input', function() {
    const amt = parseFloat(this.value) || 0;
    if (totalValue > 0) document.getElementById('phasePercent').value = (amt / totalValue * 100).toFixed(2);
});
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>
