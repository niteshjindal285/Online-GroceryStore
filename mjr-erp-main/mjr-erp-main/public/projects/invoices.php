<?php
/**
 * Projects Module – Phase-wise Invoice Management (Step 3)
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

$phase_id_pre = intval(get_param('phase_id', 0));  // pre-select a phase

$page_title = 'Invoices – ' . $project['name'] . ' – MJR Group ERP';

// --- Handle Create Invoice ---
if (is_post() && post('action') === 'create_invoice') {
    require_permission('manage_projects');
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid token.', 'error');
    } else {
        $data = [
            'amount'      => floatval(post('amount', 0)),
            'tax_amount'  => floatval(post('tax_amount', 0)),
            'issued_date' => post('issued_date', date('Y-m-d')),
            'due_date'    => post('due_date', ''),
            'notes'       => trim(post('notes', '')),
        ];
        $sel_stage = intval(post('stage_id', 0));
        $r = project_create_invoice($project_id, $sel_stage ?: null, $data, current_user_id());
        if ($r) {
            set_flash("Invoice <strong>" . $r['invoice_number'] . "</strong> created successfully.", 'success');
        } else {
            set_flash('Failed to create invoice.', 'error');
        }
        redirect(url('projects/invoices.php', ['id' => $project_id]));
    }
}

// --- Handle Status Update ---
if (is_post() && post('action') === 'update_status') {
    require_permission('manage_projects');
    if (verify_csrf_token(post('csrf_token'))) {
        $inv_id = intval(post('invoice_id'));
        $status = post('status');
        if (project_update_invoice_status($inv_id, $status)) {
            set_flash('Invoice status updated to ' . ucfirst($status) . '.', 'success');
        } else {
            set_flash('Failed to update invoice.', 'error');
        }
    }
    redirect(url('projects/invoices.php', ['id' => $project_id]));
}

$invoices = project_get_invoices($project_id);
$phases   = project_get_stages($project_id);

// Financial totals
$total_invoiced  = array_sum(array_map(fn($i) => in_array($i['status'],['sent','paid']) ? floatval($i['total_amount']) : 0, $invoices));
$total_paid      = array_sum(array_map(fn($i) => $i['status']==='paid' ? floatval($i['total_amount']) : 0, $invoices));
$total_drafted   = array_sum(array_map(fn($i) => $i['status']==='draft' ? floatval($i['total_amount']) : 0, $invoices));
$outstanding     = $total_invoiced - $total_paid;

// Invoiceable phases (not yet fully invoiced)
$invoiceable_phases = array_filter($phases, fn($p) => !in_array($p['status'], ['invoiced','paid']));

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-3">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= url('projects/index.php') ?>">Projects</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('projects/view.php', ['id'=>$project_id]) ?>"><?= escape_html($project['name']) ?></a></li>
                    <li class="breadcrumb-item active">Invoices</li>
                </ol>
            </nav>
            <h1 class="h2 fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-success"></i>Project Invoices</h1>
            <p class="text-muted mb-0">
                <strong><?= escape_html($project['name']) ?></strong> &bull;
                <?= escape_html($project['customer_name'] ?? '') ?> &bull;
                Contract: <strong><?= CURRENCY_DISPLAY ?> <?= number_format($project['total_value'], 2) ?></strong>
            </p>
        </div>
        <div class="col-auto d-flex gap-2 align-items-start">
            <a href="<?= url('projects/phases.php', ['id'=>$project_id]) ?>" class="btn btn-outline-primary">
                <i class="fas fa-tasks me-2"></i>Phases
            </a>
            <a href="<?= url('projects/view.php', ['id'=>$project_id]) ?>" class="btn btn-outline-info">
                <i class="fas fa-eye me-2"></i>Overview
            </a>
        </div>
    </div>

    <!-- Step indicator -->
    <div class="d-flex mb-4 gap-2">
        <div class="flex-fill text-center py-2 rounded text-white" style="background:#6c757d;opacity:.5">
            <i class="fas fa-building me-1"></i> 1. Project Details
        </div>
        <div class="flex-fill text-center py-2 rounded text-white" style="background:#6c757d;opacity:.5">
            <i class="fas fa-tasks me-1"></i> 2. Phases &amp; Milestones
        </div>
        <div class="flex-fill text-center py-2 rounded bg-success text-white fw-bold">
            <i class="fas fa-file-invoice-dollar me-1"></i> 3. Invoicing
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-3">
            <div class="card border-0 bg-warning text-dark shadow-sm">
                <div class="card-body text-center py-3">
                    <small>Invoiced (sent)</small>
                    <div class="fs-4 fw-bold"><?= CURRENCY_DISPLAY ?> <?= number_format($total_invoiced,2) ?></div>
                    <small><?= $project['total_value']>0 ? round($total_invoiced/$project['total_value']*100,1) : 0 ?>% of contract</small>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-success text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <small>Collected / Paid</small>
                    <div class="fs-4 fw-bold"><?= CURRENCY_DISPLAY ?> <?= number_format($total_paid,2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-danger text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <small>Outstanding</small>
                    <div class="fs-4 fw-bold"><?= CURRENCY_DISPLAY ?> <?= number_format($outstanding,2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="card border-0 bg-secondary text-white shadow-sm">
                <div class="card-body text-center py-3">
                    <small>Draft (unsent)</small>
                    <div class="fs-4 fw-bold"><?= CURRENCY_DISPLAY ?> <?= number_format($total_drafted,2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Invoice List -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-list me-2"></i>All Invoices (<?= count($invoices) ?>)</h5></div>
                <?php if (empty($invoices)): ?>
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-file-invoice fa-3x mb-3 opacity-25"></i>
                    <p>No invoices created yet. Use the form to create the first one.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="invoicesTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Invoice #</th>
                                <th>Phase</th>
                                <th class="text-end">Amount</th>
                                <th class="text-end">Tax</th>
                                <th class="text-end">Total</th>
                                <th>Issued</th>
                                <th>Due</th>
                                <th class="text-center">Status</th>
                                <?php if (has_permission('manage_projects')): ?><th>Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <?php
                                $ic = ['draft'=>'secondary','sent'=>'primary','paid'=>'success','cancelled'=>'danger'][$inv['status']] ?? 'secondary';
                                $next_statuses = [
                                    'draft'     => ['sent' => 'Send Invoice', 'cancelled' => 'Cancel'],
                                    'sent'      => ['paid' => 'Mark as Paid', 'cancelled' => 'Cancel'],
                                    'paid'      => [],
                                    'cancelled' => [],
                                ];
                                $actions = $next_statuses[$inv['status']] ?? [];
                            ?>
                            <tr>
                                <td><code class="text-success"><?= escape_html($inv['invoice_number']) ?></code></td>
                                <td>
                                    <?php if ($inv['stage_name']): ?>
                                    <span class="badge bg-light text-dark border"><?= escape_html($inv['stage_name']) ?></span>
                                    <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                                <td class="text-end"><?= CURRENCY_DISPLAY ?> <?= number_format($inv['amount'],2) ?></td>
                                <td class="text-end"><?= CURRENCY_DISPLAY ?> <?= number_format($inv['tax_amount'],2) ?></td>
                                <td class="text-end fw-bold"><?= CURRENCY_DISPLAY ?> <?= number_format($inv['total_amount'],2) ?></td>
                                <td><?= $inv['issued_date'] ? format_date($inv['issued_date']) : '—' ?></td>
                                <td>
                                    <?php if ($inv['due_date']): ?>
                                    <?php $overdue = strtotime($inv['due_date']) < time() && $inv['status'] !== 'paid'; ?>
                                    <span class="<?= $overdue ? 'text-danger fw-bold' : '' ?>"><?= format_date($inv['due_date']) ?></span>
                                    <?php if ($overdue): ?><span class="badge bg-danger ms-1 small">Overdue</span><?php endif; ?>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                                <td class="text-center"><span class="badge bg-<?= $ic ?> px-3"><?= ucfirst($inv['status']) ?></span></td>
                                <?php if (has_permission('manage_projects')): ?>
                                <td>
                                    <div class="d-flex gap-1">
                                    <?php foreach ($actions as $new_status => $label): ?>
                                        <form method="POST" onsubmit="return confirm('<?= addslashes($label) ?> invoice <?= $inv['invoice_number'] ?>?')">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="status" value="<?= $new_status ?>">
                                            <button type="submit" class="btn btn-sm btn-<?= $new_status==='paid' ? 'success' : ($new_status==='sent'?'primary':'danger') ?>">
                                                <?= $new_status==='paid' ? '<i class="fas fa-check me-1"></i>' : ($new_status==='sent'?'<i class="fas fa-paper-plane me-1"></i>':'<i class="fas fa-times me-1"></i>') ?><?= $label ?>
                                            </button>
                                        </form>
                                    <?php endforeach; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create Invoice Form -->
        <?php if (has_permission('manage_projects')): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i>Create Invoice</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="create_invoice">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">For Phase (optional)</label>
                            <select name="stage_id" class="form-select" id="phaseSelect" onchange="setPhaseAmount(this)">
                                <option value="">— General / Not phase-specific —</option>
                                <?php foreach ($phases as $ph): ?>
                                <option value="<?= $ph['id'] ?>"
                                    data-amount="<?= $ph['amount'] ?>"
                                    data-status="<?= $ph['status'] ?>"
                                    <?= $phase_id_pre == $ph['id'] ? 'selected' : '' ?>>
                                    <?= escape_html($ph['stage_name']) ?>
                                    (<?= CURRENCY_DISPLAY ?> <?= number_format($ph['amount'],2) ?>)
                                    <?= in_array($ph['status'],['invoiced','paid']) ? ' ⚠️ already invoiced' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_DISPLAY ?></span>
                                <input type="number" name="amount" id="invAmount" class="form-control" step="0.01" min="0" placeholder="0.00" required
                                       value="<?= $phase_id_pre ? (array_values(array_filter($phases, fn($p)=>$p['id']==$phase_id_pre))[0]['amount'] ?? '') : '' ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tax Amount</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= CURRENCY_DISPLAY ?></span>
                                <input type="number" name="tax_amount" class="form-control" step="0.01" min="0" placeholder="0.00" value="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Issue Date</label>
                            <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Payment terms, reference numbers…"></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-file-invoice-dollar me-2"></i>Generate Invoice
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$additional_scripts = "
<script>
function setPhaseAmount(sel) {
    const opt = sel.options[sel.selectedIndex];
    const amt = opt.dataset.amount;
    if (amt) document.getElementById('invAmount').value = parseFloat(amt).toFixed(2);
}
$(document).ready(function(){ 
    if($('#invoicesTable').length) $('#invoicesTable').DataTable({order:[[0,'desc']],pageLength:25}); 
    // auto-set amount if phase pre-selected
    const ps = document.getElementById('phaseSelect');
    if (ps && ps.value) setPhaseAmount(ps);
});
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>
