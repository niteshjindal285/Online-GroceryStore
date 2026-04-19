<?php
/**
 * Projects Module – Project Overview / Detail View
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/project_service.php';

require_login();
require_permission('view_projects');

$project_id = intval(get_param('id', 0));
if ($project_id <= 0) { set_flash('Invalid project.', 'error'); redirect(url('projects/index.php')); }

$project = project_get_by_id($project_id);
if (!$project) { set_flash('Project not found.', 'error'); redirect(url('projects/index.php')); }

$phases   = project_get_stages($project_id);
$invoices = project_get_invoices($project_id);

// Financials
$total_invoiced = array_sum(array_map(fn($i) => in_array($i['status'], ['sent','paid']) ? $i['total_amount'] : 0, $invoices));
$total_paid     = array_sum(array_map(fn($i) => $i['status'] === 'paid' ? $i['total_amount'] : 0, $invoices));
$outstanding    = $total_invoiced - $total_paid;
$invoiced_pct   = $project['total_value'] > 0 ? min(100, round($total_invoiced / $project['total_value'] * 100, 1)) : 0;
$paid_pct       = $project['total_value'] > 0 ? min(100, round($total_paid    / $project['total_value'] * 100, 1)) : 0;

$page_title = $project['name'] . ' – Projects – MJR Group ERP';

// Status color
$status_color = ['active'=>'success','completed'=>'primary','cancelled'=>'danger'][$project['status']] ?? 'secondary';

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('projects/index.php') ?>">Projects</a></li>
                    <li class="breadcrumb-item active"><?= escape_html($project['name']) ?></li>
                </ol>
            </nav>
            <div class="d-flex align-items-center gap-3">
                <div>
                    <h1 class="h2 fw-bold mb-0"><?= escape_html($project['name']) ?></h1>
                    <p class="text-muted mb-0">
                        <code><?= escape_html($project['project_number'] ?? '') ?></code> &bull;
                        <?= escape_html($project['customer_name'] ?? '') ?>
                    </p>
                </div>
                <span class="badge bg-<?= $status_color ?> fs-6 ms-2"><?= ucfirst($project['status']) ?></span>
            </div>
        </div>
        <?php if (has_permission('manage_projects')): ?>
        <div class="col-auto d-flex gap-2">
            <a href="<?= url('projects/phases.php', ['id'=>$project_id]) ?>" class="btn btn-primary">
                <i class="fas fa-tasks me-2"></i>Manage Phases
            </a>
            <a href="<?= url('projects/invoices.php', ['id'=>$project_id]) ?>" class="btn btn-success">
                <i class="fas fa-file-invoice-dollar me-2"></i>Invoices
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Financial Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <small class="text-muted d-block">Contract Value</small>
                    <div class="fs-4 fw-bold text-primary"><?= CURRENCY_DISPLAY ?> <?= number_format($project['total_value'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <small class="text-muted d-block">Invoiced</small>
                    <div class="fs-4 fw-bold text-warning"><?= CURRENCY_DISPLAY ?> <?= number_format($total_invoiced, 2) ?></div>
                    <small class="text-muted"><?= $invoiced_pct ?>% of contract</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <small class="text-muted d-block">Collected / Paid</small>
                    <div class="fs-4 fw-bold text-success"><?= CURRENCY_DISPLAY ?> <?= number_format($total_paid, 2) ?></div>
                    <small class="text-muted"><?= $paid_pct ?>% of contract</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <small class="text-muted d-block">Outstanding</small>
                    <div class="fs-4 fw-bold text-danger"><?= CURRENCY_DISPLAY ?> <?= number_format($outstanding, 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-1">
                <span class="fw-semibold small">Overall Progress</span>
                <span class="small text-muted">Invoiced <?= $invoiced_pct ?>% &bull; Paid <?= $paid_pct ?>%</span>
            </div>
            <div class="progress" style="height:16px; border-radius:8px;">
                <div class="progress-bar bg-success" style="width:<?= $paid_pct ?>%" title="Paid: <?= $paid_pct ?>%"></div>
                <div class="progress-bar bg-warning" style="width:<?= max(0, $invoiced_pct - $paid_pct) ?>%" title="Invoiced but unpaid"></div>
            </div>
            <div class="d-flex gap-4 mt-2 small text-muted">
                <span><span class="badge bg-success me-1">&nbsp;</span>Paid</span>
                <span><span class="badge bg-warning me-1">&nbsp;</span>Invoiced</span>
                <span><span class="badge bg-secondary me-1">&nbsp;</span>Pending</span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Phases Timeline -->
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Phases / Milestones (<?= count($phases) ?>)</h5></div>
                <div class="card-body p-0">
                    <?php if (empty($phases)): ?>
                    <div class="text-center py-4 text-muted"><i class="fas fa-layer-group fa-2x mb-2 opacity-25"></i><p>No phases defined yet.</p></div>
                    <?php else: ?>
                    <?php foreach ($phases as $i => $ph): ?>
                    <?php
                        $sc = ['pending'=>'secondary','in_progress'=>'primary','complete'=>'success','invoiced'=>'warning','paid'=>'dark'][$ph['status']] ?? 'secondary';
                        $icon = ['pending'=>'fa-clock','in_progress'=>'fa-spinner','complete'=>'fa-check-circle','invoiced'=>'fa-file-invoice','paid'=>'fa-check-double'][$ph['status']] ?? 'fa-circle';
                    ?>
                    <div class="d-flex align-items-start p-3 border-bottom">
                        <div class="me-3 text-center" style="min-width:40px;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white bg-<?= $sc ?>" style="width:36px;height:36px;">
                                <i class="fas <?= $icon ?> fa-sm"></i>
                            </div>
                            <?php if ($i < count($phases)-1): ?><div class="border-start border-2 mx-auto mt-1" style="height:24px;width:0;border-color:var(--bs-border-color)!important"></div><?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <strong><?= escape_html($ph['stage_name']) ?></strong>
                                <span class="badge bg-<?= $sc ?> ms-2"><?= ucfirst(str_replace('_',' ',$ph['status'])) ?></span>
                            </div>
                            <div class="text-muted small mt-1 d-flex gap-3">
                                <span><?= number_format($ph['percentage'],1) ?>% &rarr; <?= CURRENCY_DISPLAY ?> <?= number_format($ph['amount'],2) ?></span>
                                <?php if ($ph['due_date']): ?><span><i class="fas fa-calendar me-1"></i><?= format_date($ph['due_date']) ?></span><?php endif; ?>
                                <?php if ($ph['invoice_number']): ?><span class="text-success"><i class="fas fa-receipt me-1"></i><?= escape_html($ph['invoice_number']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($ph['details']): ?><p class="small text-muted mb-0 mt-1"><?= escape_html($ph['details']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Info & Recent Invoices -->
        <div class="col-lg-5">
            <!-- Project Info -->
            <div class="card shadow-sm mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Project Info</h5></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th class="text-muted fw-normal" width="40%">Customer</th><td><?= escape_html($project['customer_name'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Project #</th><td><code><?= escape_html($project['project_number'] ?? '—') ?></code></td></tr>
                        <tr><th class="text-muted fw-normal">Manager</th><td><?= escape_html($project['project_manager'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Start Date</th><td><?= $project['start_date'] ? format_date($project['start_date']) : '—' ?></td></tr>
                        <tr><th class="text-muted fw-normal">End Date</th><td><?= $project['end_date'] ? format_date($project['end_date']) : '—' ?></td></tr>
                        <tr><th class="text-muted fw-normal">Created By</th><td><?= escape_html($project['created_by_name'] ?? '—') ?></td></tr>
                        <tr><th class="text-muted fw-normal">Created</th><td><?= format_date($project['created_at']) ?></td></tr>
                    </table>
                    <?php if ($project['description']): ?>
                    <hr><p class="small text-muted mb-0"><?= nl2br(escape_html($project['description'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2 text-success"></i>Invoices</h5>
                    <a href="<?= url('projects/invoices.php', ['id'=>$project_id]) ?>" class="btn btn-sm btn-outline-success">View All</a>
                </div>
                <?php if (empty($invoices)): ?>
                <div class="card-body text-center text-muted py-3"><p class="mb-0">No invoices yet.</p></div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach (array_slice($invoices, 0, 5) as $inv): ?>
                    <?php $ic = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','cancelled'=>'danger'][$inv['status']] ?? 'secondary'; ?>
                    <div class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <code class="small"><?= escape_html($inv['invoice_number']) ?></code>
                                <?php if ($inv['stage_name']): ?><small class="text-muted ms-1">(<?= escape_html($inv['stage_name']) ?>)</small><?php endif; ?>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="fw-semibold small"><?= CURRENCY_DISPLAY ?> <?= number_format($inv['total_amount'],2) ?></span>
                                <span class="badge bg-<?= $ic ?>"><?= ucfirst($inv['status']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
