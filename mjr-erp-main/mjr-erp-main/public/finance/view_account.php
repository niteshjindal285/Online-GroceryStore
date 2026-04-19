<?php
/**
 * View Account Details
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
ensure_finance_approval_columns('accounts');

$page_title = 'View Account - MJR Group ERP';

// Get account ID from URL
$account_id = get('id');
if (!$account_id) {
    set_flash('Account ID not provided.', 'error');
    redirect('accounts.php');
}

if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $action = post('action');
    $record_id = (int)post('id');
    
    if (($action === 'approve_account' || $action === 'reject_account') && $record_id > 0) {
        $row = db_fetch("SELECT * FROM accounts WHERE id = ?", [$record_id]);
        if ($row) {
            $is_reject = ($action === 'reject_account');
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
                    db_query("UPDATE accounts SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
                }
                set_flash($approval['message'], 'success');
            } else {
                set_flash($approval['message'], 'error');
            }
        }
        redirect('view_account.php?id=' . $record_id);
    }
}

// Get account data with parent info
$account = db_fetch("
    SELECT a.*, p.code as parent_code, p.name as parent_name,
           m.username as manager_username, m.full_name as manager_full_name,
           ad.username as admin_username, ad.full_name as admin_full_name
    FROM accounts a
    LEFT JOIN accounts p ON a.parent_id = p.id
    LEFT JOIN users m ON a.manager_id = m.id
    LEFT JOIN users ad ON a.admin_id = ad.id
    WHERE a.id = ?
", [$account_id]);

if (!$account) {
    set_flash('Account not found.', 'error');
    redirect('accounts.php');
}

// Get sub-accounts
$sub_accounts = db_fetch_all("
    SELECT id, code, name, account_type, is_active
    FROM accounts
    WHERE parent_id = ?
    ORDER BY code
", [$account_id]);

// Get recent transactions for this account (last 20)
$transactions = db_fetch_all("
    SELECT gl.*,
           CASE 
               WHEN gl.reference_type = 'journal_entry' THEN (SELECT entry_number FROM journal_entries WHERE id = gl.reference_id)
               ELSE NULL
           END as entry_number,
           gl.transaction_date as entry_date,
           gl.description as je_description
    FROM general_ledger gl
    WHERE gl.account_id = ?
    ORDER BY gl.transaction_date DESC, gl.id DESC
    LIMIT 20
", [$account_id]);

// Calculate account balance
$balance = db_fetch("
    SELECT 
        COALESCE(SUM(debit), 0) as total_debit,
        COALESCE(SUM(credit), 0) as total_credit
    FROM general_ledger
    WHERE account_id = ?
", [$account_id]);

$total_debit = $balance['total_debit'] ?? 0;
$total_credit = $balance['total_credit'] ?? 0;
$net_balance = $total_debit - $total_credit;

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.6); padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .card-body { padding: 1.5rem; }
    
    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); --bs-table-border-color: rgba(255,255,255,0.05); }
    .table-dark th { color: #8e8e9e; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid #333344; padding: 1.25rem 1rem; }
    .table-dark td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; vertical-align: middle; }
    
    .btn-action { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.3); font-weight: 600; transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1rem; }
    .btn-action:hover { background-color: rgba(13, 202, 240, 0.2); border-color: rgba(13, 202, 240, 0.4); color: #0dcaf0; }

    .btn-edit { background-color: rgba(255, 146, 43, 0.1); color: #ff922b; border: 1px solid rgba(255, 146, 43, 0.3); font-weight: 600; transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1rem; }
    .btn-edit:hover { background-color: rgba(255, 146, 43, 0.2); border-color: rgba(255, 146, 43, 0.4); color: #ff922b; }

    .btn-reject { background-color: rgba(255, 82, 82, 0.1); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.3); font-weight: 600; transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1rem; }
    .btn-reject:hover { background-color: rgba(255, 82, 82, 0.2); border-color: rgba(255, 82, 82, 0.4); color: #ff5252; }

    .btn-clear { background-color: rgba(255, 255, 255, 0.05); color: #b0b0c0; border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1rem; text-decoration: none; }
    .btn-clear:hover { background-color: rgba(255, 255, 255, 0.1); color: #fff; }

    .balance-card { background: rgba(34, 34, 48, 0.9); border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; padding: 2rem 1.5rem; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="fas fa-eye me-2" style="color: #9061f9;"></i> Account Overview</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/accounts.php') ?>" style="color: #8e8e9e; text-decoration: none;">Chart of Accounts</a></li>
                    <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page"><?= escape_html($account['code']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <?php 
            $status = $account['approval_status'] ?? 'approved';
            $is_pending = ($status === 'pending' || $status === 'draft');
            $user_id = current_user_id();
            $manager_id = (int)($account['manager_id'] ?? 0);
            $admin_id = (int)($account['admin_id'] ?? 0);
            $approval_type = $account['approval_type'] ?? 'manager';
            
            $manager_done = !empty($account['manager_approved_at']);
            $admin_done = !empty($account['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if ($is_pending && ($can_approve_as_manager || $can_approve_as_admin)): 
            ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="approve_account">
                <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                <button type="submit" class="btn btn-action text-decoration-none">
                    <i class="fas fa-check me-2"></i>Approve
                </button>
            </form>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="reject_account">
                <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
                <button type="submit" class="btn btn-reject text-decoration-none" onclick="return confirm('Are you sure you want to REJECT this account?')">
                    <i class="fas fa-times me-2"></i>Reject
                </button>
            </form>
            <?php endif; ?>
            <a href="edit_account.php?id=<?= $account['id'] ?>" class="btn btn-edit text-decoration-none">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <a href="accounts.php" class="btn btn-clear text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <!-- Account Information Card -->
    <div class="row g-4 mb-5">
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0 text-white"><i class="fas fa-info-circle me-2" style="color: #8e8e9e;"></i> Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <table class="table table-borderless table-dark mb-0">
                                <tr>
                                    <td style="color: #8e8e9e; width: 140px; padding: 0.5rem 0;">Account Code:</td>
                                    <td style="padding: 0.5rem 0;"><strong class="text-white"><?= escape_html($account['code']) ?></strong></td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Account Name:</td>
                                    <td style="padding: 0.5rem 0;"><?= escape_html($account['name']) ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Account Type:</td>
                                    <td style="padding: 0.5rem 0;">
                                        <?php
                                        $type_badges = [
                                            'asset' => ['color' => '#3cc553', 'bg' => 'rgba(60, 197, 83, 0.15)'],
                                            'liability' => ['color' => '#ff5252', 'bg' => 'rgba(255, 82, 82, 0.15)'],
                                            'equity' => ['color' => '#9061f9', 'bg' => 'rgba(144, 97, 249, 0.15)'],
                                            'revenue' => ['color' => '#0dcaf0', 'bg' => 'rgba(13, 202, 240, 0.15)'],
                                            'expense' => ['color' => '#ff922b', 'bg' => 'rgba(255, 146, 43, 0.15)']
                                        ];
                                        $style = $type_badges[$account['account_type']] ?? ['color' => '#b0b0c0', 'bg' => 'rgba(255,255,255,0.05)'];
                                        ?>
                                        <span class="badge" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; border: 1px solid <?= str_replace('0.15)', '0.3)', $style['bg']) ?>; padding: 6px 10px;">
                                            <?= ucfirst($account['account_type']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Parent Account:</td>
                                    <td style="padding: 0.5rem 0;">
                                        <?php if ($account['parent_name']): ?>
                                            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= escape_html($account['parent_code']) ?></span> <?= escape_html($account['parent_name']) ?>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-level-up-alt"></i> Top Level</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless table-dark mb-0">
                                <tr>
                                    <td style="color: #8e8e9e; width: 140px; padding: 0.5rem 0;">Level:</td>
                                    <td style="padding: 0.5rem 0;"><?= $account['level'] ?></td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Status:</td>
                                    <td style="padding: 0.5rem 0;">
                                        <?php if (($account['approval_status'] ?? 'approved') === 'pending'): ?>
                                            <span style="color: #ffc107;"><i class="fas fa-clock me-1"></i> Pending Approval</span>
                                        <?php elseif (($account['approval_status'] ?? '') === 'rejected'): ?>
                                            <span style="color: #ff5252;"><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                        <?php elseif ($account['is_active']): ?>
                                            <span style="color: #3cc553;"><i class="fas fa-check-circle me-1"></i> Active</span>
                                        <?php else: ?>
                                            <span style="color: #8e8e9e;"><i class="fas fa-times-circle me-1"></i> Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Main Account:</td>
                                    <td style="padding: 0.5rem 0;">
                                        <?php if ($account['is_main_account']): ?>
                                            <span style="color: #0dcaf0;"><i class="fas fa-star me-1"></i> Yes</span>
                                        <?php else: ?>
                                            <span style="color: #8e8e9e;">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Created On:</td>
                                    <td style="padding: 0.5rem 0;"><span style="color: #b0b0c0;"><?= format_date($account['created_at'], DISPLAY_DATETIME_FORMAT) ?></span></td>
                                </tr>
                                <tr>
                                    <td style="color: #8e8e9e; padding: 0.5rem 0;">Approval Type:</td>
                                    <td style="padding: 0.5rem 0;" class="text-capitalize"><?= escape_html($account['approval_type'] ?? 'manager') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="balance-card">
                <div class="row w-100 mx-0 mb-4">
                    <div class="col-6" style="border-right: 1px solid rgba(255,255,255,0.05);">
                        <div style="font-size: 0.85rem; color: #8e8e9e; text-transform: uppercase;">Total Debits</div>
                        <div class="font-monospace mt-1" style="color: #3cc553; font-size: 1.25rem;"><?= format_currency($total_debit) ?></div>
                    </div>
                    <div class="col-6">
                        <div style="font-size: 0.85rem; color: #8e8e9e; text-transform: uppercase;">Total Credits</div>
                        <div class="font-monospace mt-1" style="color: #ff5252; font-size: 1.25rem;"><?= format_currency($total_credit) ?></div>
                    </div>
                </div>
                
                <hr style="border-color: rgba(255,255,255,0.05); width: 80%; margin: 0 auto 1.5rem auto;">
                
                <div style="font-size: 0.9rem; color: #b0b0c0; text-transform: uppercase; letter-spacing: 1px;">Net Balance</div>
                <div class="font-monospace fw-bold mt-2" style="font-size: 2.5rem; color: <?= $net_balance >= 0 ? '#3cc553' : '#ff5252' ?>;">
                    <?= format_currency(abs($net_balance)) ?>
                    <span style="font-size: 1.2rem; margin-left: 5px; opacity: 0.8;"><?= $net_balance >= 0 ? 'Dr' : 'Cr' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Sub-Accounts Section -->
    <?php if (!empty($sub_accounts)): ?>
    <div class="card border-0 shadow-sm mb-5" style="overflow: hidden;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white"><i class="fas fa-sitemap me-2" style="color: #8e8e9e;"></i> Sub-Accounts</h5>
            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= count($sub_accounts) ?> Linked</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead style="background-color: #1a1a24;">
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sub_accounts as $sub): ?>
                        <tr>
                            <td class="ps-4"><strong style="color: #0dcaf0;"><?= escape_html($sub['code']) ?></strong></td>
                            <td style="color: #fff;"><?= escape_html($sub['name']) ?></td>
                            <td>
                                <?php
                                $badge_style = $type_badges[$sub['account_type']] ?? ['color' => '#b0b0c0', 'bg' => 'rgba(255,255,255,0.05)'];
                                ?>
                                <span class="badge" style="background: <?= $badge_style['bg'] ?>; color: <?= $badge_style['color'] ?>;">
                                    <?= ucfirst($sub['account_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($sub['is_active']): ?>
                                    <span style="color: #3cc553; font-size: 0.9rem;"><i class="fas fa-check-circle"></i> Active</span>
                                <?php else: ?>
                                    <span style="color: #8e8e9e; font-size: 0.9rem;"><i class="fas fa-times-circle"></i> Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="view_account.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0; border: 1px solid rgba(13,202,240,0.2);">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Transactions Section -->
    <div class="card border-0 shadow-sm mb-4" style="overflow: hidden;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white"><i class="fas fa-history me-2" style="color: #8e8e9e;"></i> Recent Transactions</h5>
            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e;">Last 20</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($transactions)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead style="background-color: #1a1a24;">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Entry Number</th>
                            <th>Description</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end pe-4">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                        <tr>
                            <td class="ps-4"><span style="color: #b0b0c0;"><?= format_date($txn['entry_date']) ?></span></td>
                            <td>
                                <?php if (!empty($txn['entry_number']) && !empty($txn['reference_id'])): ?>
                                    <a href="view_journal_entry.php?id=<?= $txn['reference_id'] ?>" style="color: #0dcaf0; text-decoration: none; font-weight: 600;">
                                        <?= escape_html($txn['entry_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: #fff;"><?= escape_html($txn['je_description'] ?? $txn['description']) ?></td>
                            <td class="text-end font-monospace" style="color: #3cc553;">
                                <?= (!empty($txn['debit']) && $txn['debit'] > 0) ? format_currency($txn['debit']) : '<span style="color: rgba(255,255,255,0.1);">-</span>' ?>
                            </td>
                            <td class="text-end pe-4 font-monospace" style="color: #ff5252;">
                                <?= (!empty($txn['credit']) && $txn['credit'] > 0) ? format_currency($txn['credit']) : '<span style="color: rgba(255,255,255,0.1);">-</span>' ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3 opacity-25"></i>
                <p class="text-white mb-1 fs-5">No Transactions Found</p>
                <p class="text-muted">This account currently has no general ledger entries.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
