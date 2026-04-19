<?php
/**
 * Sales Reports — Aging, Outstanding, Invoice Summary
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$page_title = 'Sales Reports - MJR Group ERP';
$company_id = $_SESSION['company_id'];
$report     = get('report', 'aging');

include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-bar me-2"></i>Sales Reports</h2>
    <a href="../index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Sales Dashboard</a>
</div>

<!-- Report Selector -->
<div class="card mb-4">
    <div class="card-body d-flex gap-2 flex-wrap">
        <?php
        $tabs = ['aging'=>'Aging Report', 'outstanding'=>'Outstanding Invoices', 'summary'=>'Invoice Summary'];
        foreach ($tabs as $k => $v): ?>
        <a href="?report=<?= $k ?>" class="btn btn-<?= $report===$k?'primary':'outline-primary' ?>"><?= $v ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($report === 'aging'): ?>
<!-- ====== AGING REPORT ====== -->
<?php
$rows = db_fetch_all("
    SELECT c.id, c.code, c.name,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) <= 0 THEN i.total_amount-i.amount_paid ELSE 0 END),0) AS cur,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 1 AND 30 THEN i.total_amount-i.amount_paid ELSE 0 END),0) AS d30,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 31 AND 60 THEN i.total_amount-i.amount_paid ELSE 0 END),0) AS d60,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) BETWEEN 61 AND 90 THEN i.total_amount-i.amount_paid ELSE 0 END),0) AS d90,
        COALESCE(SUM(CASE WHEN DATEDIFF(CURDATE(), i.due_date) > 90 THEN i.total_amount-i.amount_paid ELSE 0 END),0) AS d180,
        COALESCE(SUM(i.total_amount-i.amount_paid),0) AS total
    FROM customers c
    JOIN invoices i ON i.customer_id=c.id AND i.payment_status='open'
    WHERE (c.company_id=? OR c.company_id IS NULL)
    GROUP BY c.id HAVING total > 0
    ORDER BY total DESC
", [$company_id]);
$totals = ['cur'=>0,'d30'=>0,'d60'=>0,'d90'=>0,'d180'=>0,'total'=>0];
foreach ($rows as $r) foreach ($totals as $k => &$v) $v += $r[$k];
?>
<div class="d-flex justify-content-end mb-3">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Print</button>
</div>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Debtor Aging Report — <?= date('d M Y') ?></h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="table-dark">
                    <tr><th>Code</th><th>Customer</th><th>Current</th><th>1-30 Days</th><th>31-60 Days</th><th>61-90 Days</th><th>90+ Days</th><th class="text-end">Total Due</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $r): ?>
                    <tr class="<?= $r['d90'] > 0 || $r['d180'] > 0 ? 'table-danger' : ($r['d60'] > 0 ? 'table-warning' : '') ?>">
                        <td><code><?= escape_html($r['code']) ?></code></td>
                        <td><?= escape_html($r['name']) ?></td>
                        <td class="text-success"><?= format_currency($r['cur']) ?></td>
                        <td class="<?= $r['d30']>0?'text-warning fw-semibold':'' ?>"><?= format_currency($r['d30']) ?></td>
                        <td class="<?= $r['d60']>0?'text-orange fw-semibold':'' ?>"><?= format_currency($r['d60']) ?></td>
                        <td class="<?= $r['d90']>0?'text-danger fw-bold':'' ?>"><?= format_currency($r['d90']) ?></td>
                        <td class="<?= $r['d180']>0?'text-danger fw-bold':'' ?>"><?= format_currency($r['d180']) ?></td>
                        <td class="text-end fw-bold"><?= format_currency($r['total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-dark fw-bold">
                        <td colspan="2">TOTALS</td>
                        <td><?= format_currency($totals['cur']) ?></td>
                        <td><?= format_currency($totals['d30']) ?></td>
                        <td><?= format_currency($totals['d60']) ?></td>
                        <td><?= format_currency($totals['d90']) ?></td>
                        <td><?= format_currency($totals['d180']) ?></td>
                        <td class="text-end"><?= format_currency($totals['total']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($report === 'outstanding'): ?>
<!-- ====== OUTSTANDING INVOICES ====== -->
<?php
$invs = db_fetch_all("
    SELECT i.*, c.name AS customer_name, (i.total_amount-i.amount_paid) AS outstanding
    FROM invoices i JOIN customers c ON c.id=i.customer_id
    WHERE i.payment_status='open' AND (i.company_id=? OR i.company_id IS NULL)
    ORDER BY i.due_date ASC
", [$company_id]);
$grand = array_sum(array_column($invs, 'outstanding'));
?>
<div class="d-flex justify-content-between mb-3">
    <span class="fw-bold fs-5 text-danger">Total Outstanding: <?= format_currency($grand) ?></span>
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="fas fa-print me-1"></i>Print</button>
</div>
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark"><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Outstanding</th></tr></thead>
                <tbody>
                    <?php foreach ($invs as $inv):
                        $overdue = !empty($inv['due_date']) && $inv['due_date'] < date('Y-m-d');
                    ?>
                    <tr class="<?= $overdue?'table-danger':'' ?>">
                        <td><a href="../invoices/view_invoice.php?id=<?= $inv['id'] ?>"><code><?= escape_html($inv['invoice_number']) ?></code></a></td>
                        <td><?= escape_html($inv['customer_name']) ?></td>
                        <td><?= format_date($inv['invoice_date']) ?></td>
                        <td><?= format_date($inv['due_date']) ?></td>
                        <td><?= format_currency($inv['total_amount']) ?></td>
                        <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                        <td class="fw-bold <?= $overdue?'text-danger':'' ?>"><?= format_currency($inv['outstanding']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php elseif ($report === 'summary'): ?>
<!-- ====== INVOICE SUMMARY ====== -->
<?php
$summary = db_fetch_all("
    SELECT DATE_FORMAT(i.invoice_date,'%Y-%m') AS month_label,
           COUNT(*) AS count,
           SUM(i.total_amount) AS revenue,
           SUM(i.amount_paid) AS collected,
           SUM(i.total_amount-i.amount_paid) AS outstanding
    FROM invoices i
    WHERE i.payment_status != 'cancelled' AND (i.company_id=? OR i.company_id IS NULL)
    GROUP BY DATE_FORMAT(i.invoice_date,'%Y-%m')
    ORDER BY month_label DESC
    LIMIT 12
", [$company_id]);
?>
<div class="card">
    <div class="card-header"><h5 class="mb-0">Invoice Summary — Last 12 Months</h5></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-dark"><tr><th>Month</th><th># Invoices</th><th>Revenue</th><th>Collected</th><th>Outstanding</th></tr></thead>
                <tbody>
                    <?php foreach ($summary as $s): ?>
                    <tr>
                        <td><?= $s['month_label'] ?></td>
                        <td><?= $s['count'] ?></td>
                        <td><?= format_currency($s['revenue']) ?></td>
                        <td class="text-success"><?= format_currency($s['collected']) ?></td>
                        <td class="<?= $s['outstanding']>0?'text-danger':'' ?>"><?= format_currency($s['outstanding']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
