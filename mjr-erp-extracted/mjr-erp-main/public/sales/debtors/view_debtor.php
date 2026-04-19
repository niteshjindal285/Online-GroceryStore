<?php
/**
 * View Debtor Profile - Statement, Aging, Documents
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
if (!$id) { set_flash('Invalid debtor.', 'error'); redirect('index.php'); }

$debtor = db_fetch("SELECT c.*, u.username AS approved_by_name
    FROM customers c
    LEFT JOIN users u ON u.id = c.credit_approved_by
    WHERE c.id = ?", [$id]);
if (!$debtor) { set_flash('Debtor not found.', 'error'); redirect('index.php'); }

// Statement — all invoices
$invoices = db_fetch_all("
    SELECT i.*, (i.total_amount - i.amount_paid) AS outstanding
    FROM invoices i
    WHERE i.customer_id = ?
    ORDER BY i.invoice_date DESC
", [$id]);

// Aging breakdown
$aging = db_fetch("
    SELECT
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN i.total_amount - i.amount_paid ELSE 0 END),0) AS current_amt,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1  AND 30 THEN i.total_amount - i.amount_paid ELSE 0 END),0) AS aged_30,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN i.total_amount - i.amount_paid ELSE 0 END),0) AS aged_60,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN i.total_amount - i.amount_paid ELSE 0 END),0) AS aged_90,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) >  90 THEN i.total_amount - i.amount_paid ELSE 0 END),0) AS aged_180
    FROM invoices i
    WHERE i.customer_id = ? AND i.payment_status = 'open'
", [$id]);

// KYC Documents
$documents = db_fetch_all("SELECT * FROM debtor_documents WHERE customer_id = ? ORDER BY uploaded_at DESC", [$id]);

$total_due = array_sum(array_column($invoices, 'outstanding'));
$page_title = 'Debtor: ' . $debtor['name'] . ' - MJR Group ERP';

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-user me-2"></i><?= escape_html($debtor['name']) ?></h2>
        <p class="text-muted mb-0">Code: <?= escape_html($debtor['code']) ?> &nbsp;|&nbsp; <?= escape_html($debtor['payment_terms'] ?? 'Standard') ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="edit_debtor.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i>Edit</a>
        <a href="statement.php?id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank"><i class="fas fa-print me-1"></i>Statement</a>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<!-- Credit Status Banner -->
<?php if ($debtor['credit_hold']): ?>
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fas fa-lock fa-lg me-3"></i>
    <div>
        <strong>Credit Hold Active</strong> — <?= escape_html($debtor['hold_reason'] ?? 'Account locked') ?>
        <?php if (has_permission('approve_credit') || is_admin()): ?>
        <a href="approve_credit.php?id=<?= $id ?>&action=release" class="btn btn-sm btn-danger ms-3">Release Hold</a>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($debtor['credit_pending_approval']): ?>
<div class="alert alert-warning d-flex align-items-center mb-4">
    <i class="fas fa-clock fa-lg me-3"></i>
    <div>
        <strong>Credit Terms Pending Approval</strong>
        <?php if (has_permission('approve_credit') || is_admin()): ?>
        <a href="approve_credit.php?id=<?= $id ?>" class="btn btn-sm btn-warning ms-3">Approve Now</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Debtor Info -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Account Details</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td class="text-muted">Email</td><td><?= escape_html($debtor['email'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Phone</td><td><?= escape_html($debtor['phone'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Tax No.</td><td><?= escape_html($debtor['tax_number'] ?? '—') ?></td></tr>
                    <tr><td class="text-muted">Credit Limit</td><td class="fw-bold"><?= format_currency($debtor['credit_limit']) ?></td></tr>
                    <tr><td class="text-muted">Credit Term</td><td><?= intval($debtor['credit_term_days']) ?> days</td></tr>
                    <tr><td class="text-muted">Tier 1 Disc.</td><td><?= number_format($debtor['discount_tier1_pct'],2) ?>%</td></tr>
                    <tr><td class="text-muted">Tier 2 Flat</td><td><?= format_currency($debtor['discount_tier2_amt']) ?></td></tr>
                    <?php if ($debtor['approved_by_name']): ?>
                    <tr><td class="text-muted">Approved By</td><td><?= escape_html($debtor['approved_by_name']) ?><br><small class="text-muted"><?= format_datetime($debtor['credit_approved_at']) ?></small></td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <!-- Aging Summary -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">Aging Summary</h6></div>
            <div class="card-body">
                <div class="row text-center g-2">
                    <?php $buckets = [
                        ['label'=>'Current', 'val'=>$aging['current_amt'], 'cls'=>'success'],
                        ['label'=>'1-30 Days', 'val'=>$aging['aged_30'], 'cls'=>'warning'],
                        ['label'=>'31-60 Days', 'val'=>$aging['aged_60'], 'cls'=>'orange'],
                        ['label'=>'61-90 Days', 'val'=>$aging['aged_90'], 'cls'=>'danger'],
                        ['label'=>'90+ Days', 'val'=>$aging['aged_180'], 'cls'=>'danger'],
                    ]; ?>
                    <?php foreach ($buckets as $b): ?>
                    <div class="col">
                        <div class="border rounded p-2 <?= $b['val'] > 0 ? 'border-'.$b['cls'] : '' ?>">
                            <div class="small text-muted"><?= $b['label'] ?></div>
                            <div class="fw-bold text-<?= $b['cls'] ?>"><?= format_currency($b['val']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col">
                        <div class="border rounded p-2 border-dark bg-dark text-white">
                            <div class="small">Total Due</div>
                            <div class="fw-bold"><?= format_currency($total_due) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices / Statement -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Transaction Statement</h6>
        <a href="../invoices/add_invoice.php?customer_id=<?= $id ?>" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i>New Invoice
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th><th>Date</th><th>Due Date</th>
                        <th>Total</th><th>Paid</th><th>Outstanding</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><code><?= escape_html($inv['invoice_number']) ?></code></td>
                        <td><?= format_date($inv['invoice_date']) ?></td>
                        <td><?= format_date($inv['due_date']) ?></td>
                        <td><?= format_currency($inv['total_amount']) ?></td>
                        <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                        <td class="fw-bold <?= $inv['outstanding'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= format_currency($inv['outstanding']) ?>
                        </td>
                        <td><span class="badge bg-<?= $inv['payment_status'] === 'closed' ? 'success' : ($inv['payment_status'] === 'cancelled' ? 'secondary' : 'warning text-dark') ?>"><?= ucfirst($inv['payment_status']) ?></span></td>
                        <td><a href="../invoices/view_invoice.php?id=<?= $inv['id'] ?>" class="btn btn-xs btn-outline-info btn-sm">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($invoices)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">No invoices on record.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- KYC Documents -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-file-alt me-1"></i>KYC Documents</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-upload me-1"></i>Upload
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <p class="text-muted text-center py-2">No documents uploaded.</p>
        <?php else: ?>
            <div class="list-group">
            <?php foreach ($documents as $doc): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div><i class="fas fa-file me-2 text-primary"></i><?= escape_html($doc['file_name']) ?>
                        <small class="text-muted ms-2"><?= escape_html($doc['doc_type'] ?? '') ?></small>
                    </div>
                    <small class="text-muted"><?= format_date($doc['uploaded_at']) ?></small>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="upload_document.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="customer_id" value="<?= $id ?>">
                <div class="modal-header"><h5 class="modal-title">Upload KYC Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select name="doc_type" class="form-select">
                            <option>Credit Application</option>
                            <option>Tax Certificate</option>
                            <option>ID / Passport</option>
                            <option>Business Registration</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <input type="file" name="document" class="form-control" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                        <small class="text-muted">PDF, JPG, PNG, DOC up to 5MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i>Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
