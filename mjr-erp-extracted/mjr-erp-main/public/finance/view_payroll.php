<?php
/**
 * View Payroll Run
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('payroll_runs');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash('Invalid payroll run ID.', 'error');
    redirect('payroll.php');
}

$run = db_fetch("
    SELECT pr.*, b.bank_name, b.account_name, b.currency,
           m.username as manager_username, m.full_name as manager_full_name,
           a.username as admin_username, a.full_name as admin_full_name
    FROM payroll_runs pr
    LEFT JOIN bank_accounts b ON pr.bank_account_id = b.id
    LEFT JOIN users m ON pr.manager_id = m.id
    LEFT JOIN users a ON pr.admin_id = a.id
    WHERE pr.id = ?
", [$id]);

if (!$run) {
    set_flash('Payroll run not found.', 'error');
    redirect('payroll.php');
}

$page_title = 'Payroll Run ' . $run['run_reference'];

// Handle status updates
if (is_post() && post('action') === 'update_status' && verify_csrf_token(post('csrf_token'))) {
    $new_status = post('new_status');
    $allowed    = ['draft', 'approved', 'rejected', 'paid'];
    if (in_array($new_status, $allowed)) {
        if ($new_status === 'approved' || $new_status === 'rejected') {
            $is_reject = ($new_status === 'rejected');
            $approval = finance_process_approval_action($run, current_user_id(), $is_reject);
            
            if (!$approval['ok']) {
                set_flash($approval['message'], 'error');
                redirect('view_payroll.php?id=' . $id);
            }

            $set_parts = [];
            $params = [];
            foreach ($approval['fields'] as $field => $value) {
                $set_parts[] = "{$field} = ?";
                $params[] = $value;
            }
            
            if ($approval['approved']) {
                $set_parts[] = "status = 'approved'";
            } elseif ($approval['rejected'] ?? false) {
                $set_parts[] = "status = 'rejected'";
            }
            
            $params[] = $id;
            if (!empty($set_parts)) {
                db_query("UPDATE payroll_runs SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
            }
            set_flash($approval['message'], 'success');
        } else {
            db_query("UPDATE payroll_runs SET status = ? WHERE id = ?", [$new_status, $id]);
            set_flash('Payroll status updated to ' . strtoupper($new_status) . '.', 'success');
        }
        redirect('view_payroll.php?id=' . $id);
    }
}

$months = ['', 'January', 'February', 'March', 'April', 'May', 'June',
           'July', 'August', 'September', 'October', 'November', 'December'];

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --vp-bg: #1a1a24;
        --vp-panel: #222230;
        --vp-text: #b0b0c0;
        --vp-text-white: #ffffff;
        --vp-border: rgba(255,255,255,0.05);
        --vp-label: #8e8e9e;
        --vp-stat-bg: rgba(255,255,255,0.02);
    }

    [data-bs-theme="light"] {
        --vp-bg: #f8f9fa;
        --vp-panel: #ffffff;
        --vp-text: #495057;
        --vp-text-white: #212529;
        --vp-border: #dee2e6;
        --vp-label: #6c757d;
        --vp-stat-bg: #f8f9fa;
    }

    body { background-color: var(--vp-bg); color: var(--vp-text); }
    .card { background-color: var(--vp-panel); border-color: var(--vp-border); border-radius: 12px; }
    .card-header { background-color: var(--vp-panel)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--vp-border); }
    .detail-label { color: var(--vp-label); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-value { color: var(--vp-text-white); font-size: 1rem; font-weight: 500; margin-top: 4px; }
    .stat-card { background: var(--vp-stat-bg); border: 1px solid var(--vp-border); border-radius: 10px; padding: 1.25rem; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 fw-bold" style="color: var(--vp-text-white);">
                <i class="fas fa-users-cog me-2" style="color: #ff922b;"></i>
                <?= escape_html($run['run_reference']) ?>
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/payroll.php') ?>" style="color: #8e8e9e; text-decoration: none;">Payroll</a></li>
                    <li class="breadcrumb-item active" style="color: #ff922b;" aria-current="page"><?= escape_html($run['run_reference']) ?></li>
                </ol>
            </nav>
        </div>
        <button onclick="window.print()" class="btn px-4 py-2 rounded-pill no-print" style="background: rgba(255,255,255,0.05); color:#fff; border: 1px solid rgba(255,255,255,0.1);">
            <i class="fas fa-print me-2"></i>Print
        </button>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4">
            <?= escape_html($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Details Card -->
    <div class="card border-0 shadow-sm mb-4" style="border-top: 4px solid #ff922b !important;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" style="color: var(--vp-text-white);">Payroll Run Details</h5>
            <?php
            $st  = $run['status'];
            $sc  = ['draft' => '#ff922b', 'approved' => '#0dcaf0', 'rejected' => '#ff5252', 'paid' => '#3cc553'];
            $sbg = ['draft' => 'rgba(255,146,43,0.15)', 'approved' => 'rgba(13,202,240,0.15)', 'rejected' => 'rgba(255,82,82,0.15)', 'paid' => 'rgba(60,197,83,0.15)'];
            $col = $sc[$st] ?? '#8e8e9e'; $bgc = $sbg[$st] ?? 'rgba(255,255,255,0.05)';
            ?>
            <span class="badge fs-6" style="background: <?= $bgc ?>; color: <?= $col ?>; border: 1px solid <?= $col ?>33; padding: 0.5rem 1rem;">
                <?= strtoupper($st) ?>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="detail-label">Pay Period</div>
                    <div class="detail-value"><?= $months[(int)$run['period_month']] ?> <?= $run['period_year'] ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Bank Account</div>
                    <div class="detail-value">
                        <?= $run['bank_name'] ? escape_html($run['bank_name'] . ' - ' . $run['account_name']) : '<span class="text-muted fst-italic">Cash</span>' ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Created</div>
                    <div class="detail-value"><?= format_datetime($run['created_at']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Approval Type</div>
                    <div class="detail-value text-capitalize"><?= escape_html($run['approval_type'] ?? 'manager') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Manager</div>
                    <div class="detail-value"><?= !empty($run['manager_id']) ? escape_html(trim((string)($run['manager_full_name'] ?? '')) ?: (string)($run['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Admin</div>
                    <div class="detail-value"><?= !empty($run['admin_id']) ? escape_html(trim((string)($run['admin_full_name'] ?? '')) ?: (string)($run['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
            </div>

            <!-- Pay Summary Stats -->
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="detail-label mb-2">Gross Pay</div>
                        <div class="fs-3 fw-bold" style="color: #b0b0c0; font-family: monospace;"><?= format_currency($run['total_gross']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center">
                        <div class="detail-label mb-2">Deductions</div>
                        <div class="fs-3 fw-bold text-danger" style="font-family: monospace;">-<?= format_currency($run['total_deductions']) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card text-center" style="border-color: rgba(13,202,240,0.2); background: rgba(13,202,240,0.05);">
                        <div class="detail-label mb-2" style="color: #0dcaf0;">Net Pay</div>
                        <div class="fs-2 fw-bold" style="color: #0dcaf0; font-family: monospace;"><?= format_currency($run['total_net']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Card -->
    <?php if ($run['status'] !== 'paid' && $run['status'] !== 'rejected'): ?>
    <div class="card border-0 shadow-sm no-print">
        <div class="card-header"><h5 class="mb-0" style="color: var(--vp-text-white);"><i class="fas fa-cogs me-2 text-muted"></i>Actions</h5></div>
        <div class="card-body p-4 d-flex gap-3 flex-wrap">
            <?php 
            $user_id = current_user_id();
            $manager_id = (int)($run['manager_id'] ?? 0);
            $admin_id = (int)($run['admin_id'] ?? 0);
            $approval_type = $run['approval_type'] ?? 'manager';
            $manager_done = !empty($run['manager_approved_at']);
            $admin_done = !empty($run['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if ($run['status'] === 'draft' && ($can_approve_as_manager || $can_approve_as_admin)): 
            ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="new_status" value="approved">
                <button type="submit" class="btn px-4 py-2 fw-bold" style="background: rgba(60,197,83,0.1); color: #3cc553; border: 1px solid rgba(60,197,83,0.3); border-radius: 8px;">
                    <i class="fas fa-check-circle me-2"></i>Approve
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="new_status" value="rejected">
                <button type="submit" class="btn px-4 py-2 fw-bold" style="background: rgba(255,82,82,0.1); color: #ff5252; border: 1px solid rgba(255,82,82,0.3); border-radius: 8px;"
                        onclick="return confirm('Reject this payroll run?')">
                    <i class="fas fa-times-circle me-2"></i>Reject
                </button>
            </form>
            <?php elseif ($run['status'] === 'approved' && is_admin()): ?>
            <form method="POST" onsubmit="return confirm('Mark this payroll run as PAID? This cannot be undone.')">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="new_status" value="paid">
                <button type="submit" class="btn px-4 py-2 fw-bold" style="background: rgba(60,197,83,0.1); color: #3cc553; border: 1px solid rgba(60,197,83,0.3); border-radius: 8px;">
                    <i class="fas fa-money-bill-wave me-2"></i>Mark as Paid
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
@media print {
    /* Hide everything non-essential */
    nav, .navbar, .sidebar, .btn, .no-print, .position-sticky, .breadcrumb, a[href*="payroll.php"] { display: none !important; }
    
    body { background: #fff !important; color: #000 !important; font-size: 10pt; padding: 0 !important; margin: 0 !important; font-family: 'Segoe UI', Roboto, sans-serif; }
    .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    
    /* Document Container */
    .card { background: #fff !important; border: 1px solid #ddd !important; box-shadow: none !important; color: #000 !important; border-radius: 0 !important; margin-bottom: 0 !important; }
    .card-body { padding: 0 !important; }
    
    /* Header (Exact Quotation Design) */
    .print-header-premium { display: block !important; border-top: 10px solid #ffcc00; }
    .print-top-bar { background: #1a1a24; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; position: relative; }
    .print-logo-circle { width: 70px; height: 70px; background: #ffcc00; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #1a1a24; font-weight: 900; font-size: 1.5rem; margin-right: 20px; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    .print-company-info h2 { margin: 0; font-size: 1.8rem; font-weight: 800; letter-spacing: 1px; color: #fff; }
    .print-company-info p { margin: 0; font-size: 0.85rem; opacity: 1; font-weight: 500; }
    .print-type-label { background: #ffcc00; color: #1a1a24; font-weight: 800; padding: 12px 40px; border-radius: 8px; font-size: 1.2rem; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    
    .print-address-bar { background: #1a1a24; color: #fff; border-top: 1px solid rgba(255,255,255,0.1); padding: 10px 30px; font-size: 0.8rem; opacity: 0.9; }

    /* Metadata Bar (Grey strip with icons) */
    .print-meta-grid { display: grid !important; grid-template-columns: repeat(4, 1fr); border-bottom: 2px solid #1a1a24; background: #f8f9fa; margin-bottom: 0; }
    .print-meta-item { border-right: 1px solid #ddd; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
    .print-meta-item:last-child { border-right: none; }
    .print-meta-item i { color: #1a1a24; font-size: 1rem; }
    .print-meta-label { font-size: 0.7rem; color: #333; text-transform: uppercase; font-weight: 700; display: block; }
    .print-meta-value { font-size: 0.9rem; font-weight: 600; color: #000; }

    /* Section Banners (From/To) */
    .print-section-grid { display: grid !important; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #ddd; }
    .print-section-column { padding: 0; border-right: 1px solid #ddd; }
    .print-section-column:last-child { border-right: none; }
    .print-section-banner { background: #1a1a24; color: #fff; padding: 12px 20px; font-size: 0.85rem; font-weight: 800; display: flex; justify-content: space-between; align-items: center; text-transform: uppercase; }
    .print-section-content { padding: 20px; font-size: 0.9rem; color: #333; line-height: 1.5; }

    /* Table Styling */
    .table-dark { border-collapse: collapse !important; width: 100% !important; margin: 0 !important; }
    .table-dark thead tr { background: #1a1a24 !important; color: #fff !important; }
    .table-dark th { padding: 15px 12px !important; font-size: 0.85rem !important; border: 1px solid rgba(255,255,255,0.1) !important; text-transform: uppercase; font-weight: 800; color: #fff !important; }
    .table-dark td { padding: 12px !important; border: 1px solid #eee !important; color: #000 !important; font-size: 0.9rem !important; background: transparent !important; }
    .table-dark tbody tr:nth-child(even) { background: #f9f9f9 !important; }
    
    /* Total Quoted Price Banner */
    .total-banner-premium { display: flex !important; background: #1a1a24 !important; color: #fff !important; justify-content: space-between; align-items: center; padding: 20px 30px !important; margin-top: 0; border-top: 5px solid #ffcc00; }
    .total-banner-label { font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    .total-banner-value { font-size: 2rem; font-weight: 900; color: #ffcc00; display: flex; gap: 15px; align-items: center; }
    .total-banner-value::before { content: '₹'; font-size: 1.5rem; opacity: 0.7; }

    /* Amount in Words box */
    .print-words-box { display: block !important; background: #fffde7; border: 1px solid #fff9c4; padding: 12px 30px; margin: 15px 0; font-style: italic; color: #555; font-size: 0.95rem; border-left: 5px solid #ffcc00; }

    /* Footer / Terms Section */
    .print-footer-grid { display: grid !important; grid-template-columns: 1fr 1fr; margin-top: 20px; border-top: 1px solid #ddd; }
    .print-footer-column { padding: 20px 30px; border-right: 1px solid #ddd; }
    .print-footer-column:last-child { border-right: none; }
    .print-footer-title { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .print-footer-title i { color: #1a1a24; }
    .print-footer-sig { font-size: 0.9rem; font-weight: 500; color: #666; margin-top: 60px; border-top: 1px solid #333; display: inline-block; padding-top: 5px; min-width: 200px; text-align: center; }
}

.print-header-premium, .total-banner-premium, .print-footer-grid, .print-meta-grid, .print-section-grid, .print-words-box { display: none; }
</style>

<!-- HIDDEN PREMIUM PRINT LAYOUT -->
<div class="print-header-premium">
    <div class="print-top-bar">
        <div class="d-flex align-items-center">
            <div class="print-logo-circle">MJR</div>
            <div class="print-company-info">
                <h2>MJR COMPANY</h2>
                <p>Steel & Metal Fabrication Division</p>
                <p>Quality • Precision • Reliability</p>
            </div>
        </div>
        <div class="print-type-label">PAYROLL</div>
    </div>
    <div class="print-address-bar">
        123, Industrial Area Phase-2, Jaipur, Rajasthan — 302001 | +91-98765-43210 | mjrcompany.com
    </div>
</div>

<div class="print-meta-grid">
    <div class="print-meta-item">
        <i class="fas fa-file-invoice-dollar"></i>
        <div>
            <span class="print-meta-label">Payroll Run</span>
            <span class="print-meta-value"><?= $months[(int)$run['period_month']] ?> <?= $run['period_year'] ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-calendar-check"></i>
        <div>
            <span class="print-meta-label">Status</span>
            <span class="print-meta-value"><?= strtoupper($run['status']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-users"></i>
        <div>
            <span class="print-meta-label">Reference</span>
            <span class="print-meta-value"><?= escape_html($run['run_reference']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-info-circle"></i>
        <div>
            <span class="print-meta-label">System ID</span>
            <span class="print-meta-value">MJR-PAY-<?= $run['id'] ?></span>
        </div>
    </div>
</div>

<div class="print-section-grid">
    <div class="print-section-column">
        <div class="print-section-banner">
            <span>FROM (ISSUED BY)</span>
            <i class="fas fa-arrow-right"></i>
        </div>
        <div class="print-section-content">
            <strong>MJR Company</strong><br>
            Human Resources & Payroll Dept<br>
            123, Industrial Area Phase-2, Jaipur<br>
            Phone: +91-98765-43210<br>
            Email: hr@mjrcompany.com
        </div>
    </div>
    <div class="print-section-column">
        <div class="print-section-banner">
            <i class="fas fa-university"></i>
            <span>BANKING INSTRUCTION</span>
        </div>
        <div class="print-section-content">
            <strong>Salary Disbursement Advice</strong><br>
            To: Payroll Banking Partner<br>
            Ref: MJR/PAY/SLIP/<?= $run['id'] ?><br>
            Total Net Transfer Amount calculated below.
        </div>
    </div>
</div>

<!-- Total Banner for Print -->
<div class="total-banner-premium">
    <span class="total-banner-label">TOTAL DISBURSEMENT (INR):</span>
    <span class="total-banner-value"><?= number_format($run['total_net'], 2) ?></span>
</div>

<div class="print-words-box">
    <strong>Payroll Note:</strong> This document summarizes the net salary disbursements for the period specified above.<br>
    <strong>Verification:</strong> All calculations have been cross-checked by the MJR Internal Audit team.
</div>

<div class="print-footer-grid">
    <div class="print-footer-column">
        <div class="print-footer-title"><i class="fas fa-list-alt"></i> PAYROLL TERMS</div>
        <div class="print-footer-sig" style="border: none; margin-top: 0; text-align: left; color: #333;">
            1. Disbursement subject to statutory deductions.<br>
            2. Verified by MJR HR & Operations Manager.<br>
            3. Confidential internal payroll record.
        </div>
    </div>
    <div class="print-footer-column text-end">
        <div class="print-footer-title"><i class="fas fa-signature"></i> FOR MJR COMPANY</div>
        <div class="print-footer-sig">HR & Payroll Manager</div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
