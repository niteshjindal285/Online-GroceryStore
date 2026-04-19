<?php
/**
 * Debtors Index - Credit Control Management
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Debtors Management - MJR Group ERP';
$company_id = $_SESSION['company_id'];

// Auto-lock accounts overdue by more than credit_term_days
db_query("
    UPDATE customers c
    SET c.credit_hold = 1,
        c.hold_reason = 'Auto-locked: payment overdue'
    WHERE c.is_active = 1
      AND c.credit_hold = 0
      AND c.credit_limit > 0
      AND EXISTS (
          SELECT 1 FROM invoices i
          WHERE i.customer_id = c.id
            AND i.payment_status = 'open'
            AND i.due_date < DATE_SUB(CURDATE(), INTERVAL c.credit_term_days DAY)
      )
");

// Fetch debtors with aging info
$debtors = db_fetch_all("
    SELECT c.*,
           COALESCE(SUM(i.total_amount - i.amount_paid), 0)              AS balance_due,
           COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30
                               THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS aged_30,
           COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60
                               THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS aged_60,
           COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90
                               THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS aged_90,
           COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90
                               THEN i.total_amount - i.amount_paid ELSE 0 END), 0) AS aged_180
    FROM customers c
    LEFT JOIN invoices i ON i.customer_id = c.id AND i.payment_status = 'open'
    WHERE c.is_active = 1
      AND (c.company_id = ? OR c.company_id IS NULL)
    GROUP BY c.id
    ORDER BY c.name
", [$company_id]);

$total_outstanding = array_sum(array_column($debtors, 'balance_due'));
$on_hold_count     = count(array_filter($debtors, fn($d) => $d['credit_hold']));
$pending_count     = count(array_filter($debtors, fn($d) => $d['credit_pending_approval']));

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2><i class="fas fa-users me-2"></i>Debtors Management</h2>
        <p class="text-muted mb-0">Credit control & customer account management</p>
    </div>
    <a href="add_debtor.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>Add Debtor
    </a>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#2563eb,#4f46e5);">
            <div class="card-body">
                <h6>Total Outstanding</h6>
                <h3><?= format_currency($total_outstanding) ?></h3>
                <small class="opacity-75"><?= count($debtors) ?> active debtors</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#dc2626,#b91c1c);">
            <div class="card-body">
                <h6><i class="fas fa-lock me-1"></i>Credit Hold</h6>
                <h3><?= $on_hold_count ?></h3>
                <small class="opacity-75">Accounts blocked</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#d97706,#ea580c);">
            <div class="card-body">
                <h6><i class="fas fa-clock me-1"></i>Pending Approval</h6>
                <h3><?= $pending_count ?></h3>
                <small class="opacity-75">Credit limit changes</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white" style="background:linear-gradient(135deg,#059669,#10b981);">
            <div class="card-body">
                <h6>Total Credit Granted</h6>
                <h3><?= format_currency(array_sum(array_column($debtors, 'credit_limit'))) ?></h3>
                <small class="opacity-75">Across all debtors</small>
            </div>
        </div>
    </div>
</div>

<!-- Search -->
<div class="card mb-3">
    <div class="card-body py-2">
        <input type="text" id="searchDebtors" class="form-control" placeholder="🔍  Search by name, code, phone...">
    </div>
</div>

<!-- Debtors Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Debtor Accounts</h5>
        <a href="../reports/index.php" class="btn btn-sm btn-outline-info">
            <i class="fas fa-chart-bar me-1"></i>Aging Report
        </a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="debtorTable">
                <thead class="table-dark">
                    <tr>
                        <th>Code</th>
                        <th>Debtor Name</th>
                        <th>Credit Limit</th>
                        <th>Term (Days)</th>
                        <th>Balance Due</th>
                        <th>30d</th>
                        <th>60d</th>
                        <th>90d</th>
                        <th>90d+</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debtors as $d): ?>
                    <tr class="<?= $d['credit_hold'] ? 'table-danger' : ($d['credit_pending_approval'] ? 'table-warning' : '') ?>">
                        <td><code><?= escape_html($d['code']) ?></code></td>
                        <td>
                            <strong><?= escape_html($d['name']) ?></strong>
                            <?php if ($d['credit_hold']): ?>
                                <span class="badge bg-danger ms-1"><i class="fas fa-lock"></i> Hold</span>
                            <?php elseif ($d['credit_pending_approval']): ?>
                                <span class="badge bg-warning ms-1">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_currency($d['credit_limit']) ?></td>
                        <td><?= intval($d['credit_term_days']) ?> days</td>
                        <td class="fw-bold <?= $d['balance_due'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= format_currency($d['balance_due']) ?>
                        </td>
                        <td class="<?= $d['aged_30'] > 0 ? 'text-warning fw-semibold' : '' ?>"><?= format_currency($d['aged_30']) ?></td>
                        <td class="<?= $d['aged_60'] > 0 ? 'text-orange fw-semibold' : '' ?>"><?= format_currency($d['aged_60']) ?></td>
                        <td class="<?= $d['aged_90'] > 0 ? 'text-danger' : '' ?>"><?= format_currency($d['aged_90']) ?></td>
                        <td class="<?= $d['aged_180'] > 0 ? 'text-danger fw-bold' : '' ?>"><?= format_currency($d['aged_180']) ?></td>
                        <td>
                            <?php if ($d['credit_hold']): ?>
                                <span class="badge bg-danger">Credit Hold</span>
                            <?php elseif ($d['credit_pending_approval']): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_debtor.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-info me-1" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_debtor.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($d['credit_pending_approval'] && (has_permission('approve_credit') || is_admin())): ?>
                            <a href="approve_credit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-success" title="Approve Credit">
                                <i class="fas fa-check"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($debtors)): ?>
                    <tr><td colspan="11" class="text-center py-4 text-muted">No debtors found. <a href="add_debtor.php">Add one now.</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('searchDebtors').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#debtorTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
