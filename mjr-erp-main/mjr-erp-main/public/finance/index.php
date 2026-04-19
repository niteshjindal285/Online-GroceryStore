<?php
/**
 * Finance Module - Main Page
 * Modern MJR Group ERP Aesthetic
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Finance - MJR Group ERP';
$company_id = (int)active_company_id(1);

// Get finance statistics
$total_accounts = db_fetch("SELECT COUNT(*) as count FROM accounts WHERE is_active = 1 AND company_id = ?", [$company_id])['count'] ?? 0;
$total_journal_entries = db_fetch("SELECT COUNT(*) as count FROM journal_entries WHERE company_id = ?", [$company_id])['count'] ?? 0;
$total_assets = db_fetch("SELECT COUNT(*) as count FROM fixed_assets WHERE company_id = ?", [$company_id])['count'] ?? 0;
$total_vouchers = db_fetch("SELECT COUNT(*) as count FROM payment_vouchers WHERE company_id = ?", [$company_id])['count'] ?? 0;

// Get recent journal entries
$recent_entries = db_fetch_all("
    SELECT je.*, u.username as created_by_name
    FROM journal_entries je
    LEFT JOIN users u ON je.created_by = u.id
    WHERE je.company_id = ?
    ORDER BY je.entry_date DESC, je.created_at DESC
    LIMIT 10
", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --mjr-deep-bg: #1a1a24;
        --mjr-card-bg: #222230;
        --mjr-border: rgba(255, 255, 255, 0.05);
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
        --mjr-text: #b0b0c0;
        --mjr-text-header: #fff;
        --mjr-glass: rgba(255, 255, 255, 0.05);
    }

    [data-bs-theme="light"] {
        --mjr-deep-bg: #f8f9fa;
        --mjr-card-bg: #ffffff;
        --mjr-border: rgba(0, 0, 0, 0.08);
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
        --mjr-text: #6c757d;
        --mjr-text-header: #212529;
        --mjr-glass: rgba(0, 0, 0, 0.04);
    }

    body { background-color: var(--mjr-deep-bg); color: var(--mjr-text); }
    
    .card { 
        background-color: var(--mjr-card-bg); 
        border: 1px solid var(--mjr-border); 
        border-radius: 12px; 
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .card:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }

    .stat-card {
        border: none;
        position: relative;
        overflow: hidden;
    }
    .stat-card i {
        position: absolute;
        right: -10px;
        bottom: -10px;
        font-size: 5rem;
        opacity: 0.15;
        transform: rotate(-15deg);
        transition: all 0.5s ease;
    }
    .stat-card:hover i { transform: rotate(0deg) scale(1.1); opacity: 0.25; }

    .card-header { 
        background-color: rgba(34, 34, 48, 0.5); 
        border-bottom: 1px solid var(--mjr-border); 
        padding: 1.25rem 1.5rem; 
    }

    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); }
    .table-dark th { color: var(--mjr-text); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid var(--mjr-border); padding: 1.25rem 1rem; }
    .table-dark td { padding: 1.1rem 1rem; border-bottom: 1px solid var(--mjr-border); color: var(--mjr-text-header); vertical-align: middle; }

    .btn-finance-action {
        background: var(--mjr-glass);
        color: var(--mjr-text-header)!important;
        border: 1px solid var(--mjr-border);
        padding: 0.85rem 1rem;
        border-radius: 10px;
        text-decoration: none;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
        font-weight: 500;
        margin-bottom: 0.75rem;
    }
    .btn-finance-action:hover {
        background: var(--mjr-glass);
        transform: translateX(5px);
        border-color: var(--mjr-primary);
        box-shadow: -5px 0 15px rgba(13, 202, 240, 0.1);
        color: var(--mjr-primary)!important;
    }
    .btn-finance-action i {
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        margin-right: 15px;
        font-size: 1rem;
    }

    .badge-premium { padding: 0.5em 1em; border-radius: 6px; font-weight: 600; font-size: 0.75rem; border: 1px solid rgba(255,255,255,0.1); }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Welcome Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 mt-2">
        <div>
            <h1 class="h2 fw-bold mb-1" style="color: var(--mjr-text-header);"><i class="fas fa-wallet me-3" style="color: var(--mjr-primary);"></i>Department of Finance</h1>
            <p class="text-muted mb-0">Strategic financial management and real-time ledger auditing</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info rounded-pill px-4 border-0" style="background: rgba(13, 202, 240, 0.1);"><i class="fas fa-file-export me-2"></i>Export Report</button>
            <a href="audit_log.php" class="btn btn-outline-secondary rounded-pill px-4 border-0" style="background: var(--mjr-glass); color: var(--mjr-text-header);"><i class="fas fa-history me-2"></i>Audit Log</a>
        </div>
    </div>

    <!-- Main Metrics -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="card stat-card bg-primary text-white h-100 shadow-lg">
                <div class="card-body py-4">
                    <small class="opacity-75 text-uppercase fw-bold letter-spacing-1">General Ledger</small>
                    <div class="fs-1 fw-bold my-2"><?= format_number($total_journal_entries, 0) ?></div>
                    <div class="small opacity-75 mt-2"><i class="fas fa-arrow-up me-1"></i> Entries Recorded</div>
                    <i class="fas fa-book-open"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-success text-white h-100 shadow-lg">
                <div class="card-body py-4">
                    <small class="opacity-75 text-uppercase fw-bold letter-spacing-1">Active Accounts</small>
                    <div class="fs-1 fw-bold my-2"><?= format_number($total_accounts, 0) ?></div>
                    <div class="small opacity-75 mt-2"><i class="fas fa-check-circle me-1"></i> Verified in COA</div>
                    <i class="fas fa-university"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-info text-white h-100 shadow-lg" style="background: linear-gradient(135deg, #0dcaf0 0%, #0baccc 100%)!important;">
                <div class="card-body py-4">
                    <small class="opacity-75 text-uppercase fw-bold letter-spacing-1">Asset Value</small>
                    <div class="fs-1 fw-bold my-2"><?= format_number($total_assets, 0) ?></div>
                    <div class="small opacity-75 mt-2"><i class="fas fa-boxes me-1"></i> Tracked Assets</div>
                    <i class="fas fa-building"></i>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-warning text-dark h-100 shadow-lg">
                <div class="card-body py-4">
                    <small class="opacity-75 text-uppercase fw-bold letter-spacing-1">Pending Vouchers</small>
                    <div class="fs-1 fw-bold my-2"><?= format_number($total_vouchers, 0) ?></div>
                    <div class="small opacity-50 mt-2"><i class="fas fa-clock me-1"></i> Awaiting Approval</div>
                    <i class="fas fa-stamp"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Main Content -->
        <div class="col-xl-9">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-history me-2 text-primary"></i>Recent Transaction Ledger</h5>
                    <a href="journal_entries.php" class="btn btn-sm btn-outline-info rounded-pill px-3">Full Ledger</a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_entries)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-invoice fa-4x text-muted mb-4 opacity-10"></i>
                            <h5 class="text-muted">No recent activity detected</h5>
                            <p class="text-muted small">Your financial transactions will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4">No.</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Operator</th>
                                        <th class="text-end pe-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_entries as $entry): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <a href="view_journal_entry.php?id=<?= $entry['id'] ?>" class="text-info text-decoration-none fw-bold font-monospace">
                                                    <?= escape_html($entry['entry_number']) ?>
                                                </a>
                                            </td>
                                            <td><?= format_date($entry['entry_date']) ?></td>
                                            <td class="text-truncate" style="max-width: 300px;"><?= escape_html($entry['description']) ?></td>
                                            <td><span class="text-white-50"><i class="fas fa-user-circle me-1 small"></i> <?= escape_html($entry['created_by_name'] ?? 'System') ?></span></td>
                                            <td class="text-end pe-4">
                                                <?php if ($entry['status'] == 'posted'): ?>
                                                    <span class="badge badge-premium" style="background: rgba(60, 197, 83, 0.1); color: #3cc553; border-color: rgba(60, 197, 83, 0.2);">
                                                        <i class="fas fa-check-double me-1"></i> POSTED
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge badge-premium" style="background: rgba(255, 146, 43, 0.1); color: #ff922b; border-color: rgba(255, 146, 43, 0.2);">
                                                        <i class="fas fa-edit me-1"></i> DRAFT
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions -->
        <div class="col-xl-3">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-header">
                    <h5 class="mb-0 fw-bold text-white"><i class="fas fa-tools me-2 text-warning"></i>Operations</h5>
                </div>
                <div class="card-body px-3">
                    <div class="mb-4 mt-2">
                        <small class="text-uppercase text-muted fw-bold letter-spacing-1 px-1 mb-3 d-block" style="font-size: 0.65rem;">Accounting Control</small>
                        <a href="accounts.php" class="btn-finance-action">
                            <i class="fas fa-sitemap bg-primary bg-opacity-25 text-primary"></i>
                            Chart of Accounts
                        </a>
                        <a href="journal_entries.php" class="btn-finance-action">
                            <i class="fas fa-file-contract bg-success bg-opacity-25 text-success"></i>
                            Journal Entries
                        </a>
                        <a href="general_ledger.php" class="btn-finance-action">
                            <i class="fas fa-book bg-info bg-opacity-25 text-info"></i>
                            General Ledger
                        </a>
                    </div>

                    <div class="mb-4">
                        <small class="text-uppercase text-muted fw-bold letter-spacing-1 px-1 mb-3 d-block" style="font-size: 0.65rem;">Treasury & Banking</small>
                        <a href="banking_deposits.php" class="btn-finance-action">
                            <i class="fas fa-university bg-primary bg-opacity-25 text-primary"></i>
                            Bank Deposits
                        </a>
                        <a href="bank_reconciliation.php" class="btn-finance-action">
                            <i class="fas fa-sync bg-warning bg-opacity-25 text-warning"></i>
                            Reconciliation
                        </a>
                        <a href="payment_vouchers.php" class="btn-finance-action">
                            <i class="fas fa-money-check-alt bg-danger bg-opacity-25 text-danger"></i>
                            Payment Vouchers
                        </a>
                        <a href="receipts.php" class="btn-finance-action">
                            <i class="fas fa-receipt bg-success bg-opacity-25 text-success"></i>
                            Cash Receipts
                        </a>
                    </div>

                    <div>
                        <small class="text-uppercase text-muted fw-bold letter-spacing-1 px-1 mb-3 d-block" style="font-size: 0.65rem;">Reports & Analytics</small>
                        <a href="income_statement.php" class="btn-finance-action">
                            <i class="fas fa-chart-bar bg-info bg-opacity-25 text-info"></i>
                            Income Statement
                        </a>
                        <a href="balance_sheet.php" class="btn-finance-action">
                            <i class="fas fa-balance-scale bg-primary bg-opacity-25 text-primary"></i>
                            Balance Sheet
                        </a>
                        <a href="trial_balance.php" class="btn-finance-action">
                            <i class="fas fa-list-ol bg-warning bg-opacity-25 text-warning"></i>
                            Trial Balance
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
