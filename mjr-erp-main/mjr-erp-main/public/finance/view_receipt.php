<?php
/**
 * View Receipt
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('receipts');
ensure_receipt_invoice_allocations_table();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash('Invalid receipt ID.', 'error');
    redirect('receipts.php');
}

$receipt = db_fetch("
    SELECT r.*, 
           b.bank_name, b.account_name, b.currency,
           c.name as customer_name,
           m.username as manager_username, m.full_name as manager_full_name,
           a.username as admin_username, a.full_name as admin_full_name
    FROM receipts r
    LEFT JOIN bank_accounts b ON r.bank_account_id = b.id
    LEFT JOIN customers c ON r.customer_id = c.id
    LEFT JOIN users m ON r.manager_id = m.id
    LEFT JOIN users a ON r.admin_id = a.id
    WHERE r.id = ?
", [$id]);

if (!$receipt) {
    set_flash('Receipt not found.', 'error');
    redirect('receipts.php');
}

$page_title = 'Receipt ' . $receipt['receipt_number'];

$allocations = db_fetch_all("
    SELECT ria.invoice_id, ria.allocated_amount, i.invoice_number, i.invoice_date, i.due_date
    FROM receipt_invoice_allocations ria
    JOIN invoices i ON i.id = ria.invoice_id
    WHERE ria.receipt_id = ?
    ORDER BY i.invoice_date ASC, i.id ASC
", [$id]) ?? [];

// Receipts are immutable once saved.
if (is_post()) {
    set_flash('Saved receipts are locked and cannot be edited.', 'error');
    redirect('view_receipt.php?id=' . $id);
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --vr-bg: #1a1a24;
        --vr-panel: #222230;
        --vr-text: #b0b0c0;
        --vr-text-white: #ffffff;
        --vr-border: rgba(255,255,255,0.05);
        --vr-label: #8e8e9e;
    }

    [data-bs-theme="light"] {
        --vr-bg: #f8f9fa;
        --vr-panel: #ffffff;
        --vr-text: #495057;
        --vr-text-white: #212529;
        --vr-border: #dee2e6;
        --vr-label: #6c757d;
    }

    body { background-color: var(--vr-bg); color: var(--vr-text); }
    .card { background-color: var(--vr-panel); border-color: var(--vr-border); border-radius: 12px; }
    .card-header { background-color: var(--vr-panel)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--vr-border); }
    .detail-label { color: var(--vr-label); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-value { color: var(--vr-text-white); font-size: 1rem; font-weight: 500; margin-top: 4px; }
    .table-dark { --bs-table-bg: var(--vr-panel); --bs-table-striped-bg: var(--vr-bg); --bs-table-border-color: var(--vr-border); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 fw-bold" style="color: var(--vr-text-white);">
                <i class="fas fa-hand-holding-usd me-2" style="color: #3cc553;"></i>
                <?= escape_html($receipt['receipt_number']) ?>
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/receipts.php') ?>" style="color: #8e8e9e; text-decoration: none;">Receipts</a></li>
                    <li class="breadcrumb-item active" style="color: #3cc553;" aria-current="page"><?= escape_html($receipt['receipt_number']) ?></li>
                </ol>
            </nav>
        </div>
        <button onclick="window.print()" class="btn px-4 py-2 rounded-pill no-print" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>

    <!-- Receipt Card -->
    <div class="card border-0 shadow-sm mb-5" style="border-top: 4px solid #3cc553 !important;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" style="color: var(--vr-text-white);">Receipt Details</h5>
            <?php
            $st = $receipt['status'];
            $sc = ['draft' => '#ff922b', 'posted' => '#3cc553', 'rejected' => '#ff5252', 'cancelled' => '#ff5252'];
            $bg = ['draft' => 'rgba(255,146,43,0.15)', 'posted' => 'rgba(60,197,83,0.15)', 'rejected' => 'rgba(255,82,82,0.15)', 'cancelled' => 'rgba(255,82,82,0.15)'];
            $col = $sc[$st] ?? '#8e8e9e';
            $bgc = $bg[$st] ?? 'rgba(255,255,255,0.05)';
            ?>
            <span class="badge fs-6" style="background: <?= $bgc ?>; color: <?= $col ?>; border: 1px solid <?= $col ?>33; padding: 0.5rem 1rem;">
                <?= strtoupper($st) ?>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?= format_date($receipt['receipt_date']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Customer</div>
                    <div class="detail-value"><?= $receipt['customer_name'] ? escape_html($receipt['customer_name']) : '<span class="text-muted fst-italic">N/A</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Bank Account</div>
                    <div class="detail-value">
                        <?= $receipt['bank_name'] ? escape_html($receipt['bank_name'] . ' - ' . $receipt['account_name']) : '<span class="text-muted fst-italic">Cash</span>' ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Payment Method</div>
                    <div class="detail-value"><?= escape_html($receipt['payment_method']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Reference</div>
                    <div class="detail-value font-monospace"><?= $receipt['reference'] ? escape_html($receipt['reference']) : '<span class="text-muted">—</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Amount Received</div>
                    <div class="detail-value fs-3" style="color: #3cc553;">+<?= format_currency($receipt['amount']) ?></div>
                </div>
                <div class="col-12">
                    <div class="detail-label">Description</div>
                    <div class="detail-value mt-1" style="color: #b0b0c0;"><?= $receipt['description'] ? escape_html($receipt['description']) : '<span class="text-muted fst-italic">No description provided.</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Approval Type</div>
                    <div class="detail-value text-capitalize"><?= escape_html($receipt['approval_type'] ?? 'manager') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Manager</div>
                    <div class="detail-value"><?= !empty($receipt['manager_id']) ? escape_html(trim((string)($receipt['manager_full_name'] ?? '')) ?: (string)($receipt['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Admin</div>
                    <div class="detail-value"><?= !empty($receipt['admin_id']) ? escape_html(trim((string)($receipt['admin_full_name'] ?? '')) ?: (string)($receipt['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($allocations)): ?>
    <div class="card border-0 shadow-sm mb-5">
        <div class="card-header">
            <h5 class="mb-0" style="color: var(--vr-text-white);"><i class="fas fa-link me-2 text-muted"></i> Applied Invoices</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-sm mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Invoice #</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th class="text-end pe-4">Allocated Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allocations as $alloc): ?>
                        <tr>
                            <td class="ps-4">
                                <a href="<?= url('sales/invoices/view_invoice.php', ['id' => (int)$alloc['invoice_id']]) ?>" class="text-decoration-none fw-bold">
                                    <?= escape_html($alloc['invoice_number']) ?>
                                </a>
                            </td>
                            <td><?= format_date($alloc['invoice_date']) ?></td>
                            <td><?= format_date($alloc['due_date']) ?></td>
                            <td class="text-end pe-4">+<?= format_currency($alloc['allocated_amount']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Immutable notice -->
    <div class="card border-0 shadow-sm no-print">
        <div class="card-header"><h5 class="mb-0" style="color: var(--vr-text-white);"><i class="fas fa-cogs me-2 text-muted"></i> Actions</h5></div>
        <div class="card-body p-4">
            <div class="alert alert-info mb-0">
                This receipt is locked after save. Edit and delete are disabled.
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
