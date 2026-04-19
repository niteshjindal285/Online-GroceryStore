<?php
/**
 * View Debit / Credit Note Details
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('debit_credit_notes');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid note ID.', 'error');
    redirect('finance/debit_credit_notes.php');
}

// Handle status updates
if (is_post() && isset($_POST['action']) && verify_csrf_token(post('csrf_token'))) {
    $action = $_POST['action'];
    if ($action === 'post' || $action === 'reject') {
        $note_for_approval = db_fetch("SELECT * FROM debit_credit_notes WHERE id = ?", [$id]);
        if (!$note_for_approval) {
            set_flash('Note not found.', 'error');
            redirect('finance/debit_credit_notes.php');
        }

        $is_reject = ($action === 'reject');
        $approval = finance_process_approval_action($note_for_approval, current_user_id(), $is_reject);
        
        if (!$approval['ok']) {
            set_flash($approval['message'], 'error');
            redirect('finance/view_note.php?id=' . $id);
        }

        $set_parts = [];
        $params = [];
        foreach ($approval['fields'] as $field => $value) {
            $set_parts[] = "{$field} = ?";
            $params[] = $value;
        }
        
        if ($approval['approved']) {
            $set_parts[] = "status = 'posted'";
        } elseif ($approval['rejected'] ?? false) {
            $set_parts[] = "status = 'rejected'";
        }
        
        if (!empty($set_parts)) {
            $params[] = $id;
            db_query("UPDATE debit_credit_notes SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
        }
        set_flash($approval['message'], 'success');
    }
    redirect('finance/view_note.php?id=' . $id); 
}

// Fetch note details with party name
$note = db_fetch("
    SELECT n.*, 
           CASE 
               WHEN n.entity_type = 'customer' THEN c.name 
               WHEN n.entity_type = 'supplier' THEN s.name 
           END as party_name,
           co.name as company_name,
           u.username as created_by_name,
           m.username as manager_username, m.full_name as manager_full_name,
           a.username as admin_username, a.full_name as admin_full_name
    FROM debit_credit_notes n
    LEFT JOIN customers c ON n.entity_type = 'customer' AND n.entity_id = c.id
    LEFT JOIN suppliers s ON n.entity_type = 'supplier' AND n.entity_id = s.id
    LEFT JOIN companies co ON n.company_id = co.id
    LEFT JOIN users u ON n.created_by = u.id
    LEFT JOIN users m ON n.manager_id = m.id
    LEFT JOIN users a ON n.admin_id = a.id
    WHERE n.id = ?
", [$id]);

if (!$note) {
    set_flash('Note not found.', 'error');
    redirect('finance/debit_credit_notes.php');
}

$page_title = 'View Note: ' . $note['note_number'];
include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.6); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1.25rem 1.5rem; }
    
    .detail-label { color: #8e8e9e; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
    .detail-value { color: #fff; font-size: 1.1rem; font-weight: 500; }
    
    .status-card { padding: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; }
    .status-draft { background: rgba(255, 146, 43, 0.1); border: 1px solid rgba(255, 146, 43, 0.2); color: #ff922b; }
    .status-posted { background: rgba(60, 197, 83, 0.1); border: 1px solid rgba(60, 197, 83, 0.2); color: #3cc553; }
    .status-rejected { background: rgba(255, 82, 82, 0.1); border: 1px solid rgba(255, 82, 82, 0.2); color: #ff5252; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="fas fa-file-invoice me-2" style="color: #9061f9;"></i> <?= escape_html($note['note_number']) ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/debit_credit_notes.php') ?>" style="color: #8e8e9e; text-decoration: none;">Notes</a></li>
                    <li class="breadcrumb-item active" style="color: #9061f9;" aria-current="page">View Details</li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <a href="debit_credit_notes.php" class="btn btn-outline-light border-0 no-print"><i class="fas fa-arrow-left me-2"></i>Back to List</a>
            <button onclick="window.print()" class="btn px-4 py-2 rounded-pill no-print" style="background: rgba(255,255,255,0.05); color:#fff; border: 1px solid rgba(255,255,255,0.1);">
                <i class="fas fa-print me-2"></i>Print Note
            </button>
            <?php 
            $user_id = current_user_id();
            $manager_id = (int)($note['manager_id'] ?? 0);
            $admin_id = (int)($note['admin_id'] ?? 0);
            $approval_type = $note['approval_type'] ?? 'manager';
            $manager_done = !empty($note['manager_approved_at']);
            $admin_done = !empty($note['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if($note['status'] === 'draft' && ($can_approve_as_manager || $can_approve_as_admin)): 
            ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <button type="submit" name="action" value="post" class="btn px-4 py-2 rounded-pill fw-bold" style="background: rgba(60, 197, 83, 0.1); color: #3cc553; border: 1px solid rgba(60, 197, 83, 0.3); box-shadow: 0 4px 15px rgba(60, 197, 83, 0.1);">
                        <i class="fas fa-check-circle me-2"></i>Approve
                    </button>
                    <button type="submit" name="action" value="reject" class="btn px-4 py-2 rounded-pill fw-bold ms-2" style="background: rgba(255, 82, 82, 0.1); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.3); box-shadow: 0 4px 15px rgba(255, 82, 82, 0.1);" onclick="return confirm('Are you sure you want to REJECT this note?')">
                        <i class="fas fa-times-circle me-2"></i>Reject
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status Banner -->
    <?php 
    $status_class = 'status-draft';
    if ($note['status'] === 'posted') $status_class = 'status-posted';
    if ($note['status'] === 'rejected') $status_class = 'status-rejected';
    ?>
    <div class="status-card mb-4 <?= $status_class ?>">
        <i class="fas <?= $note['status'] === 'posted' ? 'fa-check-circle' : ($note['status'] === 'rejected' ? 'fa-times-circle' : 'fa-clock') ?> fa-2x"></i>
        <div>
            <h5 class="mb-0 fw-bold"><?= strtoupper($note['status'] ?? 'DRAFT') ?></h5>
            <small>This note is currently in <strong><?= $note['status'] ?? 'draft' ?></strong> status.</small>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card border-0 h-100">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-info-circle me-2 text-muted"></i> Basic Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-5">
                        <div class="col-md-6">
                            <div class="detail-label">Note Type</div>
                            <div class="detail-value text-capitalize <?= $note['type'] === 'debit_note' ? 'text-danger' : 'text-success' ?>">
                                <i class="fas <?= $note['type'] === 'debit_note' ? 'fa-arrow-down' : 'fa-arrow-up' ?> me-2"></i>
                                <?= str_replace('_', ' ', $note['type']) ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Note Date</div>
                            <div class="detail-value"><?= format_date($note['note_date']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Party Type</div>
                            <div class="detail-value text-capitalize"><?= escape_html($note['entity_type']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Party Name</div>
                            <div class="detail-value text-info fw-bold"><?= escape_html($note['party_name'] ?? 'Unknown Party') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Company / Subsidiary</div>
                            <div class="detail-value"><?= escape_html($note['company_name'] ?? 'N/A') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Approval Type</div>
                            <div class="detail-value text-capitalize"><?= escape_html($note['approval_type'] ?? 'manager') ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Manager</div>
                            <div class="detail-value"><?= !empty($note['manager_id']) ? escape_html(trim((string)($note['manager_full_name'] ?? '')) ?: (string)($note['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-label">Admin</div>
                            <div class="detail-value"><?= !empty($note['admin_id']) ? escape_html(trim((string)($note['admin_full_name'] ?? '')) ?: (string)($note['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-label">Reason / Narration</div>
                            <div class="p-3 rounded mt-2" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);">
                                <?= nl2br(escape_html($note['reason'] ?: 'No description provided.')) ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-label">Attachment</div>
                            <div class="p-3 rounded mt-2" style="background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.15);">
                                <?php if (!empty($note['note_attachment'])): ?>
                                    <a href="<?= escape_html(url((string)$note['note_attachment'])) ?>" target="_blank" rel="noopener" style="color:#cfe8ff; text-decoration:none;">
                                        <i class="fas fa-paperclip me-1"></i> Open uploaded supporting document
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No supporting document uploaded</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card border-0 mb-4" style="background: linear-gradient(145deg, #1a1a24, #222230);">
                <div class="card-body text-center py-5">
                    <div class="detail-label mb-3">Total Amount</div>
                    <h1 class="display-4 fw-bold mb-0 <?= $note['type'] === 'debit_note' ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency($note['amount']) ?>
                    </h1>
                </div>
            </div>

            <div class="card border-0">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-history me-2 text-muted"></i> System Info</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="detail-label">Created By</div>
                        <div class="detail-value fs-6"><?= escape_html($note['created_by_name']) ?></div>
                    </div>
                    <div>
                        <div class="detail-label">Created At</div>
                        <div class="detail-value fs-6"><?= format_datetime($note['created_at']) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    /* Hide everything non-essential */
    nav, .navbar, .sidebar, .btn, .no-print, .position-sticky, .breadcrumb, a[href*="debit_credit_notes.php"] { display: none !important; }
    
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
        <div class="print-type-label">NOTE</div>
    </div>
    <div class="print-address-bar">
        123, Industrial Area Phase-2, Jaipur, Rajasthan — 302001 | +91-98765-43210 | mjrcompany.com
    </div>
</div>

<div class="print-meta-grid">
    <div class="print-meta-item">
        <i class="fas fa-file-alt"></i>
        <div>
            <span class="print-meta-label">Note Number</span>
            <span class="print-meta-value"><?= escape_html($note['note_number']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-calendar-day"></i>
        <div>
            <span class="print-meta-label">Note Date</span>
            <span class="print-meta-value"><?= format_date($note['note_date']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-check-double"></i>
        <div>
            <span class="print-meta-label">Status</span>
            <span class="print-meta-value"><?= strtoupper($note['status']) ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-fingerprint"></i>
        <div>
            <span class="print-meta-label">Internal Ref</span>
            <span class="print-meta-value">MJR-DM-<?= $note['id'] ?></span>
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
            Steel & Metal Fabrication Division<br>
            123, Industrial Area Phase-2, Jaipur<br>
            Phone: +91-98765-43210<br>
            GSTIN: 08AABCM1234A1ZX
        </div>
    </div>
    <div class="print-section-column">
        <div class="print-section-banner">
            <i class="fas fa-user-tie"></i>
            <span>TO (RECIPIENT)</span>
        </div>
        <div class="print-section-content">
            <strong><?= escape_html($note['party_name'] ?? 'Vendor/Customer Name') ?></strong><br>
            Type: <?= ucfirst($note['entity_type']) ?><br>
            (Complete address on file)<br>
            Reference: <?= escape_html($note['reason'] ?: 'Balance Adjustment') ?>
        </div>
    </div>
</div>

<!-- Total Banner for Print -->
<div class="total-banner-premium">
    <span class="total-banner-label">TOTAL ADJUSTMENT VALUE (INR):</span>
    <span class="total-banner-value"><?= number_format($note['amount'], 2) ?></span>
</div>

<div class="print-words-box">
    <strong>Reason for Adjustment:</strong> <?= nl2br(escape_html($note['reason'] ?: 'Standard financial adjustment.')) ?><br>
    <strong>Verification:</strong> This note is a valid accounting document for balance reconciliation.
</div>

<div class="print-footer-grid">
    <div class="print-footer-column">
        <div class="print-footer-title"><i class="fas fa-clipboard-list"></i> TERMS OF NOTE</div>
        <div class="print-footer-sig" style="border: none; margin-top: 0; text-align: left; color: #333;">
            1. This note is subject to final reconciliation.<br>
            2. To be adjusted against future invoices.<br>
            3. Contact accounts@mjrcompany.com for queries.
        </div>
    </div>
    <div class="print-footer-column text-end">
        <div class="print-footer-title"><i class="fas fa-signature"></i> FOR MJR COMPANY</div>
        <div class="print-footer-sig">Authorized Finance Officer</div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
