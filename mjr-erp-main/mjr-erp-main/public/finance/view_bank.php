<?php
/**
 * View Bank Account
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('bank_accounts');

$id = $_GET['id'] ?? null;
if (!$id) {
    set_flash('Bank account ID not provided.', 'error');
    redirect('banking_deposits.php');
}

if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $action = post('action');
    $record_id = (int)post('id');
    if (($action === 'approve_bank' || $action === 'reject_bank') && $record_id > 0) {
        $row = db_fetch("SELECT * FROM bank_accounts WHERE id = ?", [$record_id]);
        if ($row) {
            $is_reject = ($action === 'reject_bank');
            $approval = finance_process_approval_action($row, current_user_id(), $is_reject);
            
            if ($approval['ok']) {
                $set_parts = [];
                $params = [];
                foreach ($approval['fields'] as $field => $value) {
                    $set_parts[] = "{$field} = ?";
                    $params[] = $value;
                }
                
                if ($approval['approved']) {
                    $set_parts[] = "approval_status = 'approved'";
                    $set_parts[] = "is_active = 1";
                } elseif ($approval['rejected'] ?? false) {
                    $set_parts[] = "approval_status = 'rejected'";
                    $set_parts[] = "is_active = 0";
                }
                
                if (!empty($set_parts)) {
                    $params[] = $record_id;
                    db_query("UPDATE bank_accounts SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
                }
                set_flash($approval['message'], 'success');
            } else {
                set_flash($approval['message'], 'error');
            }
        }
        redirect('view_bank.php?id=' . $record_id);
    }
}

$bank = db_fetch("
    SELECT b.*, a.name as gl_account_name, a.code as gl_account_code,
           m.username as manager_username, m.full_name as manager_full_name,
           ad.username as admin_username, ad.full_name as admin_full_name
    FROM bank_accounts b 
    LEFT JOIN accounts a ON b.linked_gl_account_id = a.id 
    LEFT JOIN users m ON b.manager_id = m.id
    LEFT JOIN users ad ON b.admin_id = ad.id
    WHERE b.id = ?
", [$id]);

if (!$bank) {
    set_flash('Bank account not found.', 'error');
    redirect('banking_deposits.php');
}

$page_title = 'View Bank Account - ' . escape_html($bank['bank_name']);

$transactions = db_fetch_all("
    SELECT * FROM bank_transactions 
    WHERE bank_account_id = ? 
    ORDER BY transaction_date DESC, id DESC
", [$id]);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --vb-bg: #1a1a24;
        --vb-panel: #222230;
        --vb-text: #b0b0c0;
        --vb-text-white: #ffffff;
        --vb-border: rgba(255,255,255,0.05);
        --vb-label: #8e8e9e;
        --vb-table-head: #1a1a24;
    }

    [data-bs-theme="light"] {
        --vb-bg: #f8f9fa;
        --vb-panel: #ffffff;
        --vb-text: #495057;
        --vb-text-white: #212529;
        --vb-border: #dee2e6;
        --vb-label: #6c757d;
        --vb-table-head: #f8f9fa;
    }

    body { background-color: var(--vb-bg); color: var(--vb-text); }
    .card { background-color: var(--vb-panel); border-color: var(--vb-border); border-radius: 12px; }
    .card-header { background-color: var(--vb-panel)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--vb-border); }
    
    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); --bs-table-border-color: var(--vb-border); }
    .table-dark th { color: var(--vb-label); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--vb-border); padding: 1.25rem 1rem; }
    .table-dark td { padding: 1rem; border-bottom: 1px solid var(--vb-border); color: var(--vb-text-white); vertical-align: middle; }
    
    .detail-label { color: var(--vb-label); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-value { color: var(--vb-text-white); font-size: 1.1rem; font-weight: 500; font-family: monospace; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 fw-bold" style="color: var(--vb-text-white);"><i class="fas fa-university me-2" style="color: #0dcaf0;"></i> <?= escape_html($bank['bank_name']) ?></h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/banking_deposits.php') ?>" style="color: #8e8e9e; text-decoration: none;">Banking</a></li>
                    <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page"><?= escape_html($bank['account_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div>
            <?php 
            $status = $bank['approval_status'] ?? 'approved';
            $is_pending = ($status === 'pending' || $status === 'draft');
            $user_id = current_user_id();
            $manager_id = (int)($bank['manager_id'] ?? 0);
            $admin_id = (int)($bank['admin_id'] ?? 0);
            $approval_type = $bank['approval_type'] ?? 'manager';
            
            $manager_done = !empty($bank['manager_approved_at']);
            $admin_done = !empty($bank['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if ($is_pending && ($can_approve_as_manager || $can_approve_as_admin)): 
            ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="approve_bank">
                <input type="hidden" name="id" value="<?= (int)$bank['id'] ?>">
                <button type="submit" class="btn btn-success rounded-pill px-4 me-2 fw-bold">
                    <i class="fas fa-check-circle me-2"></i>Approve
                </button>
            </form>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="reject_bank">
                <input type="hidden" name="id" value="<?= (int)$bank['id'] ?>">
                <button type="submit" class="btn btn-danger rounded-pill px-4 me-2 fw-bold" onclick="return confirm('Are you sure you want to REJECT this bank account?')">
                    <i class="fas fa-times-circle me-2"></i>Reject
                </button>
            </form>
            <?php endif; ?>
            <a href="<?= url('finance/add_bank_reconciliation.php?bank_id=' . (int)$bank['id']) ?>" class="btn btn-outline-info rounded-pill px-4">
                <i class="fas fa-check-double me-2"></i>Reconcile
            </a>
        </div>
    </div>

    <!-- Details Card -->
    <div class="card border-0 shadow-sm mb-5" style="border-top: 4px solid #0dcaf0 !important;">
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="detail-label">Account Name</div>
                    <div class="fs-5 mt-1" style="color: var(--vb-text-white);"><?= escape_html($bank['account_name']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Account Number</div>
                    <div class="detail-value mt-1">
                        <i class="fas fa-hashtag text-muted me-1"></i><?= escape_html($bank['account_number']) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Current Balance</div>
                    <div class="detail-value mt-1 fs-4" style="color: <?= $bank['current_balance'] >= 0 ? '#3cc553' : '#ff5252' ?>;">
                        <?= format_currency($bank['current_balance']) ?> <span class="badge bg-secondary ms-2" style="font-size: 0.5em; vertical-align: middle;"><?= escape_html($bank['currency']) ?></span>
                    </div>
                </div>
                <div class="col-md-3 border-start" style="border-color: rgba(255,255,255,0.05)!important; padding-left: 2rem;">
                    <div class="detail-label">Linked GL Account</div>
                    <div class="mt-1">
                        <?php if ($bank['linked_gl_account_id']): ?>
                            <span class="badge bg-transparent border border-info text-info">
                                <?= escape_html($bank['gl_account_code']) ?> - <?= escape_html($bank['gl_account_name']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted fst-italic">None</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Approval Status</div>
                    <div class="mt-1">
                        <?php
                        $st = $bank['approval_status'] ?? 'approved';
                        $col = $st === 'approved' ? '#3cc553' : ($st === 'rejected' ? '#ff5252' : '#ff922b');
                        $bgc = $st === 'approved' ? 'rgba(60,197,83,0.1)' : ($st === 'rejected' ? 'rgba(255,82,82,0.1)' : 'rgba(255,146,43,0.1)');
                        ?>
                        <span class="badge" style="background: <?= $bgc ?>; color: <?= $col ?>; border: 1px solid <?= $col ?>44; padding: 0.5rem 1rem;">
                            <?= strtoupper($st) ?>
                        </span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Manager</div>
                    <div class="detail-value mt-1"><?= !empty($bank['manager_id']) ? escape_html(trim((string)($bank['manager_full_name'] ?? '')) ?: (string)($bank['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Admin</div>
                    <div class="detail-value mt-1"><?= !empty($bank['admin_id']) ? escape_html(trim((string)($bank['admin_full_name'] ?? '')) ?: (string)($bank['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0" style="color: var(--vb-text-white);"><i class="fas fa-list me-2 text-muted"></i> Transaction History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="txTable">
                    <thead style="background-color: var(--vb-table-head);">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Type</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center pe-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                        <tr>
                            <td class="ps-4"><span style="color: #b0b0c0;"><?= format_date($t['transaction_date']) ?></span></td>
                            <td style="color: var(--vb-text-white);"><?= escape_html($t['description']) ?: '<span class="text-muted fst-italic">No desc</span>' ?></td>
                            <td><span class="font-monospace text-muted"><?= escape_html($t['reference']) ?></span></td>
                            <td>
                                <?php if($t['type'] == 'deposit'): ?>
                                    <span class="text-success"><i class="fas fa-arrow-up me-1"></i> In</span>
                                <?php else: ?>
                                    <span class="text-danger"><i class="fas fa-arrow-down me-1"></i> Out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace fw-bold <?= $t['type'] == 'deposit' ? 'text-success' : 'text-danger' ?>">
                                <?= format_currency($t['amount']) ?>
                            </td>
                            <td class="text-center pe-4">
                                <?php if($t['is_reconciled']): ?>
                                    <span class="badge" style="background: rgba(60, 197, 83, 0.15); color: #3cc553; border: 1px solid rgba(60, 197, 83, 0.3);">Reconciled</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255, 146, 43, 0.15); color: #ff922b; border: 1px solid rgba(255, 146, 43, 0.3);">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#txTable').DataTable({
        "order": [[ 0, "desc" ]],
        "pageLength": 25
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
