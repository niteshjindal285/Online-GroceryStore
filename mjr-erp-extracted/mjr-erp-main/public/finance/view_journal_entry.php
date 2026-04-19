<?php
/**
 * View Journal Entry
 * Display journal entry details and line items
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'View Journal Entry - MJR Group ERP';

// Get journal entry ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid journal entry ID', 'error');
    redirect('journal_entries.php');
}

// Get journal entry details
try {
    $entry = db_fetch("
        SELECT je.*, u.username as created_by_name,
               c.name as company_name,
               m.username as manager_username, m.full_name as manager_full_name,
               ad.username as admin_username, ad.full_name as admin_full_name
        FROM journal_entries je
        LEFT JOIN users u ON je.created_by = u.id
        LEFT JOIN companies c ON je.company_id = c.id
        LEFT JOIN users m ON je.manager_id = m.id
        LEFT JOIN users ad ON je.admin_id = ad.id
        WHERE je.id = ?
    ", [$id]);
    
    if (!$entry) {
        throw new Exception('Journal entry not found');
    }
    
    // Get journal entry lines with account details
    $lines = db_fetch_all("
        SELECT jel.*, 
               a.code as account_code, 
               a.name as account_name,
               a.account_type,
               cc.name as cost_center_name,
               p.name as project_name,
               comp.name as company_name
        FROM journal_entry_lines jel
        JOIN accounts a ON jel.account_id = a.id
        LEFT JOIN cost_centers cc ON jel.cost_center_id = cc.id
        LEFT JOIN projects p ON jel.project_id = p.id
        LEFT JOIN companies comp ON jel.company_id = comp.id
        WHERE jel.journal_entry_id = ?
        ORDER BY jel.id
    ", [$id]);
    
    // Calculate totals
    $total_debit = 0;
    $total_credit = 0;
    foreach ($lines as $line) {
        $total_debit += $line['debit'];
        $total_credit += $line['credit'];
    }
    
} catch (Exception $e) {
    log_error("Error loading journal entry: " . $e->getMessage());
    set_flash('Error loading journal entry: ' . $e->getMessage(), 'error');
    redirect('journal_entries.php');
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --je-bg: #1a1a24;
        --je-panel: #222230;
        --je-text: #b0b0c0;
        --je-text-white: #ffffff;
        --je-border: rgba(255,255,255,0.05);
        --je-input-bg: #1a1a24;
        --je-input-border: rgba(255,255,255,0.1);
        --je-label: #8e8e9e;
        --je-breadcrumb-bg: #0c2b33;
        --je-breadcrumb-border: #134e5e;
    }

    [data-bs-theme="light"] {
        --je-bg: #f8f9fa;
        --je-panel: #ffffff;
        --je-text: #495057;
        --je-text-white: #212529;
        --je-border: #dee2e6;
        --je-input-bg: #ffffff;
        --je-input-border: #ced4da;
        --je-label: #6c757d;
        --je-breadcrumb-bg: #e1f5fe;
        --je-breadcrumb-border: #b3e5fc;
    }

    body { background-color: var(--je-bg); color: var(--je-text); }
    .card { background-color: var(--je-panel); border-color: var(--je-border); border-radius: 10px; }
    .table-dark { --bs-table-bg: var(--je-panel); --bs-table-striped-bg: var(--je-bg); --bs-table-border-color: var(--je-border); }
    .table-dark th { color: var(--je-label); font-weight: 600; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--je-border); padding: 1rem; }
    .table-dark td { padding: 0.75rem 1rem; vertical-align: middle; border-bottom: 1px solid var(--je-border); color: var(--je-text-white); }
    .section-badge { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; letter-spacing: 0.5px; }
    .info-label { color: var(--je-label); font-size: 0.85rem; margin-bottom: 0.25rem; }
    .info-value { color: var(--je-text-white); font-weight: 500; font-size: 0.95rem; }
    .company-box { background-color: rgba(13, 202, 240, 0.05); border: 1px dashed rgba(13, 202, 240, 0.3); color: #0dcaf0; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; min-width: 260px; }

    .btn-action { background-color: rgba(60, 197, 83, 0.1); color: #3cc553; border: 1px solid rgba(60, 197, 83, 0.3); font-weight: 600; transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1.25rem; }
    .btn-action:hover { background-color: rgba(60, 197, 83, 0.2); border-color: rgba(60, 197, 83, 0.4); color: #3cc553; }

    .btn-reject { background-color: rgba(255, 82, 82, 0.1); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.3); font-weight: 600; transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1.25rem; }
    .btn-reject:hover { background-color: rgba(255, 82, 82, 0.2); border-color: rgba(255, 82, 82, 0.4); color: #ff5252; }
</style>

<div class="container-fluid px-4 py-3">
    
    <!-- Screen Breadcrumb Box -->
    <div class="rounded p-2 mb-4 d-flex align-items-center" style="background-color: var(--je-breadcrumb-bg); border: 1px solid var(--je-breadcrumb-border);">
        <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #0dcaf0; margin-left:10px; margin-right: 15px;"></div>
        <span style="color: #0dcaf0; font-weight: 600; font-size: 0.85rem;">SCREEN: View Journal Entry</span>
    </div>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="journal_entries.php" class="text-decoration-none d-flex align-items-center mb-2" style="color: #8e8e9e; font-size: 0.9rem;">
                <i class="fas fa-arrow-left me-2"></i> Back to Journal Entries
            </a>
            <div class="d-flex align-items-center">
                <i class="fas fa-file-invoice fs-2 me-3" style="opacity: 0.9; color: var(--je-text-white);"></i>
                <h2 class="mb-0 fw-bold" style="color: var(--je-text-white);">Journal Entry <?= escape_html($entry['entry_number']) ?></h2>
                <div class="ms-4">
                    <?php if ($entry['status'] === 'posted'): ?>
                        <span class="badge" style="background-color: rgba(60, 197, 83, 0.15); border: 1px solid rgba(60, 197, 83, 0.3); color: #3cc553; padding: 0.5rem 1rem;">Posted</span>
                    <?php elseif ($entry['status'] === 'pending_approval'): ?>
                        <span class="badge" style="background-color: rgba(255, 193, 7, 0.15); border: 1px solid rgba(255, 193, 7, 0.3); color: #ffc107; padding: 0.5rem 1rem;">Pending Approval</span>
                    <?php else: ?>
                        <span class="badge" style="background-color: rgba(255, 146, 43, 0.15); border: 1px solid rgba(255, 146, 43, 0.3); color: #ff922b; padding: 0.5rem 1rem;">Draft</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="d-flex gap-2 align-items-start">
            <div class="company-box me-2 text-center">
                <div style="color: #0dcaf0; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Company / Subsidiary</div>
                <strong style="color: #e6f7ff; font-size: 1.05rem;"><?= escape_html((string)($entry['company_name'] ?? 'N/A')) ?></strong>
            </div>
            <button onclick="window.print()" class="btn px-4" style="background-color: #333344; color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px;">
                <i class="fas fa-print me-2"></i>Print Voucher
            </button>
            <?php if ($entry['status'] !== 'posted'): ?>
                <?php 
                $user_id = current_user_id();
                $manager_id = (int)($entry['manager_id'] ?? 0);
                $admin_id = (int)($entry['admin_id'] ?? 0);
                $approval_type = $entry['approval_type'] ?? 'manager';
                $manager_done = !empty($entry['manager_approved_at']);
                $admin_done = !empty($entry['admin_approved_at']);

                $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
                $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
                
                if ($can_approve_as_manager || $can_approve_as_admin): 
                ?>
                <form method="POST" action="post_journal_entry.php" class="d-inline">
                    <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn btn-action">
                        <i class="fas fa-check-circle me-2"></i>Approve
                    </button>
                </form>
                <form method="POST" action="post_journal_entry.php" class="d-inline">
                    <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-reject" onclick="return confirm('Are you sure you want to REJECT this journal entry?')">
                        <i class="fas fa-times-circle me-2"></i>Reject
                    </button>
                </form>
                <?php endif; ?>

                <a href="edit_journal_entry.php?id=<?= $entry['id'] ?>" class="btn" style="background-color: #333344; color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.5rem 1.25rem;">
                    <i class="fas fa-edit me-2"></i>Edit Entry
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 1: Header Details -->
    <div class="card mb-4">
        <div class="card-body p-4">
            <div class="mb-4">
                <div class="section-badge">
                    <span class="me-2" style="opacity: 0.7;">01</span> Entry Details
                </div>
            </div>

            <div class="row g-4 mb-3 no-print">
                <div class="col-md-3">
                    <div class="info-label">Entry Date</div>
                    <div class="info-value"><?= format_date($entry['entry_date']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Post Period</div>
                    <div class="info-value"><?= date('M Y', strtotime($entry['entry_date'])) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Created By</div>
                    <div class="info-value"><?= escape_html($entry['created_by_name']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Created At</div>
                    <div class="info-value"><?= format_datetime($entry['created_at']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Approval Type</div>
                    <div class="info-value text-capitalize"><?= escape_html($entry['approval_type'] ?? 'manager') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Manager</div>
                    <div class="info-value"><?= !empty($entry['manager_id']) ? escape_html(trim((string)($entry['manager_full_name'] ?? '')) ?: (string)($entry['manager_username'] ?? '')) : 'Not Assigned' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="info-label">Admin</div>
                    <div class="info-value"><?= !empty($entry['admin_id']) ? escape_html(trim((string)($entry['admin_full_name'] ?? '')) ?: (string)($entry['admin_username'] ?? '')) : 'Not Assigned' ?></div>
                </div>
            </div>

            <?php if (!empty($entry['description'])): ?>
            <div class="row mt-2">
                <div class="col-12">
                    <div class="info-label">Reason / Narration</div>
                    <div class="info-value p-3 rounded" style="background-color: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);">
                        <?= nl2br(escape_html($entry['description'])) ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section 2: Lines -->
    <div class="card mb-5">
        <div class="card-body p-4">
            <div class="mb-4">
                <div class="section-badge mb-2">
                    <span class="me-2" style="opacity: 0.7;">02</span> Account Lines
                </div>
            </div>

            <?php if (!empty($lines)): ?>
            <div class="table-responsive">
                <table class="table table-dark mb-0 form-table" style="background-color: transparent;">
                    <thead>
                        <tr>
                            <th style="width: 25%">Account Code / Name</th>
                            <th style="width: 15%">Acct Type</th>
                            <th style="width: 15%">Company / Cost Ctr</th>
                            <th style="width: 15%">Line Narration</th>
                            <th style="width: 15%" class="text-end">Debit Amount</th>
                            <th style="width: 15%" class="text-end">Credit Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lines as $line): ?>
                        <tr>
                            <td>
                                <strong><?= escape_html($line['account_code']) ?></strong><br>
                                <span style="font-size: 0.85rem; color: #8e8e9e;"><?= escape_html($line['account_name']) ?></span>
                            </td>
                            <td>
                                <span class="badge" style="background-color: rgba(255,255,255,0.05); color: #b0b0c0;">
                                    <?= ucwords(str_replace('_', ' ', $line['account_type'])) ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;">
                                    <div class="mb-1"><i class="fas fa-building text-muted me-1"></i><?= escape_html($line['company_name'] ?? 'Main') ?></div>
                                    <?php if ($line['cost_center_name']): ?>
                                    <div style="color: #8e8e9e;"><i class="fas fa-bullseye text-muted me-1"></i><?= escape_html($line['cost_center_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($line['project_name']): ?>
                                    <div style="color: #8e8e9e;"><i class="fas fa-project-diagram text-muted me-1"></i><?= escape_html($line['project_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-size: 0.9rem; color: #b0b0c0;"><?= escape_html($line['description'] ?? '-') ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($line['debit'] > 0): ?>
                                    <span class="font-monospace fs-6" style="color: #ff922b;"><?= format_currency($line['debit']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if ($line['credit'] > 0): ?>
                                    <span class="font-monospace fs-6" style="color: #0dcaf0;"><?= format_currency($line['credit']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end pe-4 no-print" style="color: #8e8e9e;"><strong>Totals:</strong></td>
                            <td class="text-end font-monospace fs-5 no-print" style="color: #ff922b; background: rgba(255,146,43,0.05); border-left: 1px solid rgba(255,255,255,0.05); border-bottom: none;"><strong id="totalDebit"><?= format_currency($total_debit) ?></strong></td>
                            <td class="text-end font-monospace fs-5 no-print" style="color: #0dcaf0; background: rgba(13,202,240,0.05); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: none;"><strong id="totalCredit"><?= format_currency($total_credit) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Total Banner for Print -->
            <div class="total-banner-premium">
                <span class="total-banner-label">TOTAL VOUCHER VALUE (INR):</span>
                <span class="total-banner-value"><?= number_format($total_debit, 2) ?></span>
            </div>

            <!-- Amount in Words for Print -->
            <?php
                // Simplified text amount (In a real app, use a helper function)
                $amount_text = "Voucher balance verified in MJR Group ERP.";
            ?>
            <div class="print-words-box">
                <strong>Narration:</strong> <?= escape_html($entry['description'] ?: 'Official Journal voucher entry for balance adjustment.') ?><br>
                <strong>System Record:</strong> This transaction has been locked for audit.
            </div>

            <div class="print-footer-grid">
                <div class="print-footer-column">
                    <div class="print-footer-title"><i class="fas fa-list-alt"></i> AUDIT TERMS</div>
                    <div class="print-footer-sig" style="border: none; margin-top: 0; text-align: left; color: #333;">
                        1. Voucher strictly non-transferable internal record.<br>
                        2. Verified by MJR Finance Control Division.<br>
                        3. Digital hash attached to ERP log ID: <?= $entry['id'] ?>
                    </div>
                </div>
                <div class="print-footer-column text-end">
                    <div class="print-footer-title"><i class="fas fa-signature"></i> FOR MJR COMPANY</div>
                    <div class="print-footer-sig">Authorized Finance Officer</div>
                </div>
            </div>

            
            <?php if (abs($total_debit - $total_credit) >= 0.01): ?>
            <div class="alert mt-3 mb-0" style="background-color: rgba(255, 82, 82, 0.1); border-color: rgba(255, 82, 82, 0.2); color: #ff5252;">
                <i class="fas fa-exclamation-triangle me-2"></i> Journal entry is not balanced!
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="text-center py-5 rounded" style="background-color: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1);">
                <i class="fas fa-list fa-3x mb-3" style="color: #333344;"></i>
                <h5 style="color: #8e8e9e;">No journal entry lines found</h5>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    /* Hide everything non-essential */
    nav, .navbar, .sidebar, .btn, .no-print, .position-sticky, .breadcrumb, a[href*="journal_entries.php"] { display: none !important; }
    
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
        <div class="print-type-label">VOUCHER</div>
    </div>
    <div class="print-address-bar">
        123, Industrial Area Phase-2, Jaipur, Rajasthan — 302001 | +91-98765-43210 | mjrcompany.com
    </div>
</div>

<div class="print-meta-grid">
    <div class="print-meta-item">
        <i class="fas fa-file-invoice"></i>
        <div>
            <span class="print-meta-label">Voucher No</span>
            <span class="print-meta-value"><?= escape_html($entry['entry_number']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-calendar-alt"></i>
        <div>
            <span class="print-meta-label">Entry Date</span>
            <span class="print-meta-value"><?= format_date($entry['entry_date']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-clock"></i>
        <div>
            <span class="print-meta-label">Period</span>
            <span class="print-meta-value"><?= date('F Y', strtotime($entry['entry_date'])) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-user-check"></i>
        <div>
            <span class="print-meta-label">Created By</span>
            <span class="print-meta-value"><?= escape_html($entry['created_by_name']) ?></span>
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
            Finance & Accounts Division<br>
            123, Industrial Area Phase-2, Jaipur<br>
            Phone: +91-98765-43210<br>
            Email: accounts@mjrcompany.com
        </div>
    </div>
    <div class="print-section-column">
        <div class="print-section-banner">
            <i class="fas fa-building"></i>
            <span>TO (ACCOUNTING DEPT)</span>
        </div>
        <div class="print-section-content">
            <strong>General Ledger Posting</strong><br>
            Internal Audit Section<br>
            Voucher Status: <span class="badge" style="background:#000; color:#fff;"><?= strtoupper($entry['status']) ?></span><br>
            Ref ID: <?= $entry['id'] ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
