<?php
/**
 * View Invoice (Locked — Audit Trail)
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
if (!$id) { set_flash('Invalid invoice.', 'error'); redirect('index.php'); }

$inv = db_fetch("
    SELECT i.*, c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
           c.address AS customer_address, u.username AS created_by_name,
           p.name AS project_name, ps.stage_name, ps.percentage AS stage_pct
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    JOIN users u ON u.id = i.created_by
    LEFT JOIN projects p ON p.id = i.project_id
    LEFT JOIN project_stages ps ON ps.id = i.project_stage_id
    WHERE i.id = ?
", [$id]);

// Fetch all stages for the progress widget
$project_stages = [];
if ($inv['sale_type'] === 'project' && $inv['project_id']) {
    $project_stages = db_fetch_all("SELECT * FROM project_stages WHERE project_id = ? ORDER BY id ASC", [$inv['project_id']]);
}

if (!$inv) { set_flash('Invoice not found.', 'error'); redirect('index.php'); }

$lines = db_fetch_all("
    SELECT il.*, ii.name AS item_name, ii.code AS item_code
    FROM invoice_lines il
    JOIN inventory_items ii ON ii.id = il.item_id
    WHERE il.invoice_id = ?
", [$id]);

// Delivery status
$delivery = db_fetch("SELECT * FROM delivery_schedule WHERE invoice_id = ?", [$id]);
// Returns
$returns = db_fetch_all("SELECT * FROM sales_returns WHERE invoice_id = ?", [$id]);

$page_title = 'Invoice ' . $inv['invoice_number'] . ' - MJR Group ERP';
include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-file-invoice-dollar me-2"></i><?= escape_html($inv['invoice_number']) ?></h2>
        <p class="text-muted mb-0">
            <span class="badge bg-<?= $inv['payment_status']==='closed'?'success':($inv['payment_status']==='cancelled'?'secondary':'warning text-dark') ?>">
                <?= ucfirst($inv['payment_status']) ?>
            </span>
            &nbsp;<i class="fas fa-lock text-secondary ms-1" title="Locked"></i> Locked Invoice
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($inv['sale_type'] === 'project'): ?>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-print me-1"></i>Print Phase Claim
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="print_project_invoice.php?id=<?= $id ?>" target="_blank">Phase Claim View (No Prices)</a></li>
                    <li><a class="dropdown-item" href="print_invoice.php?id=<?= $id ?>" target="_blank">Standard Detailed View</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="print_invoice.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Print</a>
        <?php endif; ?>
        <?php if ($inv['payment_status'] !== 'cancelled' && $inv['payment_status'] !== 'closed'): ?>
            <?php if (has_permission('cancel_invoice') || is_admin()): ?>
                <a href="cancel_invoice.php?id=<?= $id ?>" class="btn btn-outline-danger"><i class="fas fa-ban me-1"></i>Cancel</a>
            <?php endif; ?>
            <a href="../returns/add_return.php?invoice_id=<?= $id ?>" class="btn btn-outline-warning"><i class="fas fa-undo me-1"></i>Sales Return</a>
            <a href="../delivery/add_delivery.php?invoice_id=<?= $id ?>" class="btn btn-outline-success"><i class="fas fa-truck me-1"></i>Deliver</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<?php if ($inv['sale_type'] === 'project'): ?>
<!-- Project Progress Header -->
<div class="card mb-4 border-0 shadow-sm bg-dark text-white overflow-hidden">
    <div class="card-body p-0">
        <div class="row g-0">
            <div class="col-md-3 p-4 bg-primary bg-opacity-10 d-flex flex-column justify-content-center border-end border-secondary border-opacity-25">
                <small class="text-uppercase tracking-wider opacity-75 fw-bold mb-1" style="font-size: 10px;">Project Title</small>
                <h5 class="mb-0 fw-bold"><?= escape_html($inv['project_name'] ?? 'General Project') ?></h5>
            </div>
            <div class="col-md-9 p-3">
                <div class="d-flex justify-content-between align-items-center mb-2 px-2">
                    <small class="text-uppercase tracking-wider opacity-75 fw-bold" style="font-size: 10px;">Billing Progress (Stages)</small>
                    <span class="badge bg-primary"><?= count($project_stages) ?> Stages Defined</span>
                </div>
                <div class="d-flex align-items-center px-2">
                    <?php 
                    $total_stages = count($project_stages);
                    foreach ($project_stages as $idx => $stage): 
                        $status_class = ($stage['status'] === 'paid') ? 'bg-success' : (($stage['status'] === 'invoiced') ? 'bg-info' : 'bg-secondary opacity-50');
                        $is_current = ($stage['id'] == $inv['project_stage_id']);
                    ?>
                        <div class="flex-grow-1 position-relative px-1" title="<?= escape_html($stage['stage_name']) ?> (<?= $stage['percentage'] ?>%)">
                            <div class="progress" style="height: 10px; border-radius: 5px;">
                                <div class="progress-bar <?= $status_class ?> <?= $is_current ? 'progress-bar-striped progress-bar-animated' : '' ?>" style="width: 100%"></div>
                            </div>
                            <small class="d-block text-center mt-1 position-absolute w-100" style="font-size: 8px; left:0; <?= $is_current ? 'color: #0d6efd; font-weight: bold;' : 'opacity: 0.6;' ?>">
                                <?= $idx + 1 ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (is_admin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
<!-- BOSS COPY — Internal Financials -->
<div class="card mb-4 shadow-sm border-0 border-start border-warning border-5 bg-light">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="text-warning fw-bold mb-0"><i class="fas fa-user-shield me-2"></i>BOSS COPY — INTERNAL FINANCIAL ANALYSIS</h6>
            <span class="badge bg-secondary text-uppercase" style="font-size: 10px; letter-spacing: 1px;">Confidential</span>
        </div>
        <hr class="my-2 opacity-10">
        <div class="row g-4 text-center">
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Total Project Value</small>
                <span class="fw-bold fs-5 text-dark"><?= format_currency($inv['total_amount']) ?></span>
            </div>
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Estimated Unit Cost</small>
                <?php 
                    $total_cost = 0;
                    foreach ($lines as $l) {
                        $it_cost = db_fetch("SELECT cost_price FROM inventory_items WHERE id=?", [$l['item_id']]);
                        $total_cost += ($it_cost['cost_price'] ?? 0) * $l['quantity'];
                    }
                    $margin = ($inv['total_amount'] > 0) ? (($inv['total_amount'] - $total_cost) / $inv['total_amount']) * 100 : 0;
                ?>
                <span class="fw-bold fs-5 text-dark"><?= format_currency($total_cost) ?></span>
            </div>
            <div class="col-md-3 border-end">
                <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Gross Margin %</small>
                <span class="fw-bold fs-5 text-<?= ($margin >= 30) ? 'success' : (($margin >= 15) ? 'warning' : 'danger') ?>">
                    <?= number_format($margin, 1) ?>%
                </span>
            </div>
            <div class="col-md-3">
                <small class="text-muted text-uppercase d-block mb-1" style="font-size: 10px;">Margin Status</small>
                <span class="badge bg-<?= ($margin >= 30) ? 'success' : (($margin >= 15) ? 'warning' : 'danger') ?> text-uppercase">
                    <?= ($margin >= 30) ? 'Healthy' : (($margin >= 15) ? 'Acceptable' : 'Low Margin') ?>
                </span>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- Customer Info -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Bill To</h6></div>
            <div class="card-body">
                <p class="fw-bold mb-1"><?= escape_html($inv['customer_name']) ?></p>
                <p class="text-muted small mb-1"><?= escape_html($inv['customer_address'] ?? '') ?></p>
                <p class="mb-1"><i class="fas fa-envelope me-1 text-muted"></i><?= escape_html($inv['customer_email'] ?? '') ?></p>
                <p class="mb-0"><i class="fas fa-phone me-1 text-muted"></i><?= escape_html($inv['customer_phone'] ?? '') ?></p>
            </div>
        </div>
    </div>

    <!-- Invoice Meta -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Invoice Details</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Invoice #</td><td><strong><?= escape_html($inv['invoice_number']) ?></strong></td></tr>
                    <tr><td class="text-muted">Date</td><td><?= format_date($inv['invoice_date']) ?></td></tr>
                    <tr><td class="text-muted">Due Date</td><td class="<?= (!empty($inv['due_date']) && $inv['due_date'] < date('Y-m-d') && $inv['payment_status']==='open') ? 'text-danger fw-bold' : '' ?>"><?= format_date($inv['due_date']) ?></td></tr>
                    <tr><td class="text-muted">Created By</td><td><?= escape_html($inv['created_by_name']) ?></td></tr>
                    <?php if ($inv['so_id']): ?><tr><td class="text-muted">SO Ref</td><td><a href="../../sales/view_order.php?id=<?= $inv['so_id'] ?>">View SO</a></td></tr><?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment Summary -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Payment Summary</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= format_currency($inv['subtotal']) ?></span></div>
                <div class="d-flex justify-content-between"><span>Discount</span><span class="text-danger">-<?= format_currency($inv['discount_amount']) ?></span></div>
                <div class="d-flex justify-content-between"><span>Tax</span><span><?= format_currency($inv['tax_amount']) ?></span></div>
                <hr>
                <div class="d-flex justify-content-between fw-bold"><span>Total</span><span class="fs-5"><?= format_currency($inv['total_amount']) ?></span></div>
                <div class="d-flex justify-content-between text-success"><span>Paid</span><span><?= format_currency($inv['amount_paid']) ?></span></div>
                <div class="d-flex justify-content-between fw-bold <?= ($inv['total_amount'] - $inv['amount_paid']) > 0 ? 'text-danger' : 'text-success' ?>">
                    <span>Outstanding</span><span><?= format_currency($inv['total_amount'] - $inv['amount_paid']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Line Items -->
<div class="card mt-4">
    <div class="card-header"><h6 class="mb-0">Invoice Lines</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Item</th><th>Description</th><th>Qty</th><th>Unit Price</th><th>Disc%</th><th>Line Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($lines as $i => $l): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><code><?= escape_html($l['item_code']) ?></code> <?= escape_html($l['item_name']) ?></td>
                        <td><?= escape_html($l['description'] ?? '') ?></td>
                        <td><?= number_format($l['quantity'],2) ?></td>
                        <td><?= format_currency($l['unit_price']) ?></td>
                        <td><?= $l['discount_pct'] ?>%</td>
                        <td class="fw-bold"><?= format_currency($l['line_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delivery Status -->
<?php if ($delivery): ?>
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-truck me-1"></i>Delivery Status</h6>
        <a href="../delivery/add_delivery.php?invoice_id=<?= $id ?>" class="btn btn-sm btn-outline-success">Process Delivery</a>
    </div>
    <div class="card-body">
        <span class="badge bg-<?= $delivery['status']==='delivered'?'success':($delivery['status']==='partial'?'warning text-dark':'info') ?> fs-6">
            <?= ucfirst($delivery['status']) ?>
        </span>
        <?php if ($delivery['delivered_date']): ?>
            <span class="ms-2 text-muted">Delivered: <?= format_date($delivery['delivered_date']) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Returns -->
<?php if (!empty($returns)): ?>
<div class="card mt-4">
    <div class="card-header"><h6 class="mb-0"><i class="fas fa-undo me-1"></i>Sales Returns</h6></div>
    <div class="card-body">
        <?php foreach ($returns as $r): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom py-2">
            <span><a href="../returns/view_return.php?id=<?= $r['id'] ?>"><?= escape_html($r['return_number']) ?></a></span>
            <span class="badge bg-<?= $r['status']==='approved'?'success':($r['status']==='rejected'?'danger':'warning text-dark') ?>"><?= ucfirst($r['status']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($inv['notes']): ?>
<div class="card mt-4">
    <div class="card-header"><h6 class="mb-0">Notes</h6></div>
    <div class="card-body"><p class="mb-0"><?= nl2br(escape_html($inv['notes'])) ?></p></div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
