<?php
/**
 * View Project Expense
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('project_expenses');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash('Invalid expense ID.', 'error');
    redirect('project_expenses.php');
}

$expense = db_fetch("
    SELECT pe.*, 
           p.name as project_name,
           p.id   as pid,
           m.username as manager_username, m.full_name as manager_full_name,
           a.username as admin_username, a.full_name as admin_full_name
    FROM project_expenses pe
    JOIN projects p ON pe.project_id = p.id
    LEFT JOIN users m ON pe.manager_id = m.id
    LEFT JOIN users a ON pe.admin_id = a.id
    WHERE pe.id = ?
", [$id]);

if (!$expense) {
    set_flash('Project expense not found.', 'error');
    redirect('project_expenses.php');
}

$page_title = 'Expense ' . $expense['expense_number'];

// Handle status updates
if (is_post() && post('action') === 'update_status' && verify_csrf_token(post('csrf_token'))) {
    $new_status = post('new_status');
    $allowed    = ['pending', 'approved', 'rejected', 'reimbursed'];
    if (in_array($new_status, $allowed)) {
        if ($new_status === 'approved' || $new_status === 'rejected') {
            $is_reject = ($new_status === 'rejected');
            $approval = finance_process_approval_action($expense, current_user_id(), $is_reject);
            
            if (!$approval['ok']) {
                set_flash($approval['message'], 'error');
                redirect('view_expense.php?id=' . $id);
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
                db_query("UPDATE project_expenses SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
            }
            set_flash($approval['message'], 'success');
        } else {
            db_query("UPDATE project_expenses SET status = ? WHERE id = ?", [$new_status, $id]);
            set_flash('Expense status updated to ' . strtoupper($new_status) . '.', 'success');
        }
        redirect('view_expense.php?id=' . $id);
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.6)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .detail-label { color: #8e8e9e; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; }
    .detail-value { color: #fff; font-size: 1rem; font-weight: 500; margin-top: 4px; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 text-white fw-bold">
                <i class="fas fa-project-diagram me-2" style="color: #e83e8c;"></i>
                <?= escape_html($expense['expense_number']) ?>
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/project_expenses.php') ?>" style="color: #8e8e9e; text-decoration: none;">Project Expenses</a></li>
                    <li class="breadcrumb-item active" style="color: #e83e8c;" aria-current="page"><?= escape_html($expense['expense_number']) ?></li>
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

    <!-- Expense Details Card -->
    <div class="card border-0 shadow-sm mb-4" style="border-top: 4px solid #e83e8c !important;">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-white">Expense Details</h5>
            <?php
            $st  = $expense['status'];
            $sc  = ['pending' => '#ff922b', 'approved' => '#0dcaf0', 'reimbursed' => '#3cc553', 'rejected' => '#ff5252'];
            $sbg = ['pending' => 'rgba(255,146,43,0.15)', 'approved' => 'rgba(13,202,240,0.15)', 'reimbursed' => 'rgba(60,197,83,0.15)', 'rejected' => 'rgba(255,82,82,0.15)'];
            $col = $sc[$st] ?? '#8e8e9e'; $bgc = $sbg[$st] ?? 'rgba(255,255,255,0.05)';
            ?>
            <span class="badge fs-6" style="background: <?= $bgc ?>; color: <?= $col ?>; border: 1px solid <?= $col ?>33;">
                <?= strtoupper($st) ?>
            </span>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="detail-label">Date</div>
                    <div class="detail-value"><?= format_date($expense['expense_date']) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Project</div>
                    <div class="detail-value">
                        <i class="fas fa-folder me-1" style="color:#e83e8c;"></i>
                        <?= escape_html($expense['project_name']) ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Category</div>
                    <div class="detail-value">
                        <?php if ($expense['category']): ?>
                            <span class="badge" style="background: rgba(232,62,140,0.12); color: #e83e8c; border: 1px solid rgba(232,62,140,0.3); font-size: 0.9rem;">
                                <?= escape_html($expense['category']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted fst-italic">Uncategorised</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Amount</div>
                    <div class="detail-value fs-3" style="color: #ff5252;">
                        -<?= format_currency($expense['amount']) ?>
                    </div>
                </div>
                <?php if ($expense['description']): ?>
                <div class="col-12">
                    <div class="detail-label">Description</div>
                    <div class="detail-value mt-1" style="color: #b0b0c0;"><?= escape_html($expense['description']) ?></div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <div class="detail-label">Approval Type</div>
                    <div class="detail-value text-capitalize"><?= escape_html($expense['approval_type'] ?? 'manager') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Manager</div>
                    <div class="detail-value"><?= !empty($expense['manager_id']) ? escape_html(trim((string)($expense['manager_full_name'] ?? '')) ?: (string)($expense['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
                <div class="col-md-3">
                    <div class="detail-label">Admin</div>
                    <div class="detail-value"><?= !empty($expense['admin_id']) ? escape_html(trim((string)($expense['admin_full_name'] ?? '')) ?: (string)($expense['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions Card -->
    <?php if (!in_array($expense['status'], ['reimbursed', 'rejected'])): ?>
    <div class="card border-0 shadow-sm no-print">
        <div class="card-header"><h5 class="mb-0 text-white"><i class="fas fa-cogs me-2 text-muted"></i>Actions</h5></div>
        <div class="card-body p-4 d-flex gap-3 flex-wrap">
            <?php 
            $user_id = current_user_id();
            $manager_id = (int)($expense['manager_id'] ?? 0);
            $admin_id = (int)($expense['admin_id'] ?? 0);
            $approval_type = $expense['approval_type'] ?? 'manager';
            $manager_done = !empty($expense['manager_approved_at']);
            $admin_done = !empty($expense['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if ($expense['status'] === 'pending' && ($can_approve_as_manager || $can_approve_as_admin)): 
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
                        onclick="return confirm('Reject this expense claim?')">
                    <i class="fas fa-times-circle me-2"></i>Reject
                </button>
            </form>
            <?php elseif ($expense['status'] === 'approved' && is_admin()): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="new_status" value="reimbursed">
                <button type="submit" class="btn px-4 py-2 fw-bold" style="background: rgba(60,197,83,0.1); color: #3cc553; border: 1px solid rgba(60,197,83,0.3); border-radius: 8px;">
                    <i class="fas fa-money-bill-wave me-2"></i>Mark as Reimbursed
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
