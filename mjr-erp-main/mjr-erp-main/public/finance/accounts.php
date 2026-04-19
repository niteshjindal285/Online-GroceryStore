<?php
/**
 * Chart of Accounts
 * Financial account management and hierarchy
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
ensure_finance_approval_columns('accounts');

$page_title = 'Chart of Accounts - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view the chart of accounts.', 'warning');
    redirect(url('index.php'));
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('Invalid security token', 'error');
    } else {
        $account_id = (int)($_POST['id'] ?? 0);
        
        db_begin_transaction();
        try {
            // Check if account has children
            $has_children = db_fetch("SELECT COUNT(*) as count FROM accounts WHERE parent_id = ?", [$account_id]);
            if (($has_children['count'] ?? 0) > 0) {
                throw new Exception('Cannot delete account with sub-accounts. Delete sub-accounts first.');
            }
            
            // Check if account has transactions
            $has_transactions = db_fetch("SELECT COUNT(*) as count FROM general_ledger WHERE account_id = ?", [$account_id]);
            if (($has_transactions['count'] ?? 0) > 0) {
                throw new Exception('Cannot delete account with existing transactions.');
            }
            
            db_query("DELETE FROM accounts WHERE id = ? AND company_id = ?", [$account_id, $company_id]);
            db_commit();
            set_flash('Account deleted successfully', 'success');
        } catch (Exception $e) {
            db_rollback();
            set_flash($e->getMessage(), 'error');
        }
    }
    header('Location: accounts.php');
    exit;
}

// Get filter parameters
$account_type = $_GET['type'] ?? '';

// Build query
$where = [];
$params = [];

$where_sql = ' WHERE 1=1 ' . implode(' AND ', $where) . db_where_company('a');

// Get all accounts
$accounts = db_fetch_all("
    SELECT a.*, 
           p.name as parent_name,
           (SELECT COUNT(*) FROM accounts WHERE parent_id = a.id) as child_count
    FROM accounts a
    LEFT JOIN accounts p ON a.parent_id = p.id
    $where_sql
    ORDER BY a.account_type, a.code
", $params);

// Get account types for filter
$account_types = ['asset', 'liability', 'equity', 'revenue', 'expense'];

include __DIR__ . '/../../templates/header.php';

// Metrics
$metric_assets = 0; $metric_liabs = 0; $metric_rev = 0; $metric_exp = 0;
foreach ($accounts as $acc) {
    if ($acc['account_type'] == 'asset') $metric_assets++;
    if ($acc['account_type'] == 'liability') $metric_liabs++;
    if ($acc['account_type'] == 'revenue') $metric_rev++;
    if ($acc['account_type'] == 'expense') $metric_exp++;
}
?>

<style>
    html[data-bs-theme="dark"] {
        --mjr-deep-bg: #1a1a24;
        --mjr-card-bg: #222230;
        --mjr-border: rgba(255, 255, 255, 0.05);
        --mjr-text: #b0b0c0;
        --mjr-text-muted: #8e8e9e;
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
    }

    html[data-bs-theme="light"] {
        --mjr-deep-bg: #f8f9fa;
        --mjr-card-bg: #ffffff;
        --mjr-border: rgba(0, 0, 0, 0.1);
        --mjr-text: #212529;
        --mjr-text-muted: #6c757d;
        --mjr-primary: #0dcaf0;
        --mjr-success: #3cc553;
        --mjr-warning: #ffc107;
        --mjr-danger: #ff5252;
    }

    body { background-color: var(--mjr-deep-bg); color: var(--mjr-text); }
    
    .card { background-color: var(--mjr-card-bg); border: 1px solid var(--mjr-border); border-radius: 12px; }
    .card-header { background-color: rgba(34, 34, 48, 0.5); border-bottom: 1px solid var(--mjr-border); padding: 1.25rem 1.5rem; }

    .stat-card-coa {
        border: none;
        padding: 1.5rem;
        border-radius: 12px;
        position: relative;
        overflow: hidden;
    }
    .stat-card-coa i {
        position: absolute;
        right: -5px;
        bottom: -5px;
        font-size: 3rem;
        opacity: 0.1;
    }

    .table-premium { --bs-table-bg: transparent; }
    .table-premium th { 
        color: var(--mjr-text-muted); 
        font-weight: 600; 
        font-size: 0.75rem; 
        text-transform: uppercase; 
        letter-spacing: 1.5px; 
        border-bottom: 1px solid var(--mjr-border);
        padding: 1.25rem 1rem; 
    }
    .table-premium td { 
        padding: 1rem; 
        border-bottom: 1px solid var(--mjr-border); 
        color: var(--mjr-text); 
        vertical-align: middle; 
    }

    .account-badge-asset { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }
    .account-badge-liability { background: rgba(255, 82, 82, 0.1); color: #ff5252; }
    .account-badge-equity { background: rgba(144, 97, 249, 0.1); color: #9061f9; }
    .account-badge-revenue { background: rgba(60, 197, 83, 0.1); color: #3cc553; }
    .account-badge-expense { background: rgba(255, 193, 7, 0.1); color: #ffc107; }

    .btn-create-coa { background-color: var(--mjr-primary); color: #000; font-weight: 600; padding: 0.6rem 1.5rem; border-radius: 50px; border: none; transition: all 0.3s ease; }
    .btn-create-coa:hover { background-color: #0baccc; color: #000; transform: scale(1.05); }

    html[data-bs-theme="dark"] .btn-icon-coa { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.02); color: #8e8e9e; }
    html[data-bs-theme="dark"] .btn-icon-coa:hover { background: rgba(255,255,255,0.08); color: #fff; }
    html[data-bs-theme="light"] .btn-icon-coa { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 0.85rem; border: 1px solid rgba(0,0,0,0.1); background: rgba(0,0,0,0.05); color: #6c757d; }
    html[data-bs-theme="light"] .btn-icon-coa:hover { background: rgba(0,0,0,0.1); color: #212529; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-5 mt-2">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white"><i class="fas fa-sitemap me-3" style="color: var(--mjr-primary);"></i>Chart of Accounts</h1>
            <p class="text-muted mb-0">Structural hierarchy of financial reporting entities</p>
        </div>
        <?php if (has_permission('manage_finance')): ?>
        <a href="add_account.php" class="btn-create-coa">
            <i class="fas fa-plus-circle me-2"></i>New Account Mapping
        </a>
        <?php endif; ?>
    </div>

    <!-- Quick Metrics -->
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="stat-card-coa bg-primary bg-opacity-10 border border-primary border-opacity-25 text-primary">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Assets</small>
                <div class="fs-2 fw-bold mt-1"><?= $metric_assets ?></div>
                <i class="fas fa-money-check-alt"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-coa bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Liabilities</small>
                <div class="fs-2 fw-bold mt-1"><?= $metric_liabs ?></div>
                <i class="fas fa-hand-holding-usd"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-coa bg-success bg-opacity-10 border border-success border-opacity-25 text-success">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Revenue</small>
                <div class="fs-2 fw-bold mt-1"><?= $metric_rev ?></div>
                <i class="fas fa-chart-line"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-coa bg-warning bg-opacity-10 border border-warning border-opacity-25 text-warning">
                <small class="text-uppercase fw-bold opacity-75 letter-spacing-1">Expenses</small>
                <div class="fs-2 fw-bold mt-1"><?= $metric_exp ?></div>
                <i class="fas fa-receipt"></i>
            </div>
        </div>
    </div>

    <!-- List -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3">
            <h5 class="mb-0 text-white fw-bold">Financial Account Directory</h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($accounts)): ?>
            <div class="table-responsive">
                <table class="table table-premium table-hover mb-0" id="coaTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Code</th>
                            <th>Designation</th>
                            <th>Class</th>
                            <th>Parentage</th>
                            <th class="text-center">Integrity</th>
                            <th class="text-end pe-4">Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td class="ps-4">
                                <span class="font-monospace fw-bold" style="color: var(--mjr-primary);"><?= escape_html($account['code']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if ($account['is_main_account']): ?>
                                        <i class="fas fa-bookmark text-warning me-2 small"></i>
                                    <?php endif; ?>
                                    <span class="fw-bold text-white"><?= escape_html($account['name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge account-badge-<?= $account['account_type'] ?> px-2 py-1 rounded small">
                                    <?= strtoupper($account['account_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($account['parent_name']): ?>
                                    <small style="color: #666677;"><i class="fas fa-level-up-alt fa-rotate-90 me-2"></i><?= escape_html($account['parent_name']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if (($account['approval_status'] ?? 'approved') === 'pending'): ?>
                                    <span class="badge" style="background: rgba(255, 193, 7, 0.12); color: #ffc107; font-size: 0.65rem;">PENDING APPROVAL</span>
                                <?php elseif (($account['approval_status'] ?? '') === 'rejected'): ?>
                                    <span class="badge" style="background: rgba(255,82,82,0.12); color: #ff5252; font-size: 0.65rem;">REJECTED</span>
                                <?php elseif ($account['is_active']): ?>
                                    <span class="badge" style="background: rgba(60, 197, 83, 0.1); color: #3cc553; font-size: 0.65rem;">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(255,255,255,0.05); color: #666677; font-size: 0.65rem;">INACTIVE</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="view_account.php?id=<?= $account['id'] ?>" class="btn-icon-coa" title="View Audit"><i class="fas fa-eye"></i></a>
                                    <?php if (has_permission('manage_finance')): ?>
                                    <a href="edit_account.php?id=<?= $account['id'] ?>" class="btn-icon-coa" style="color: var(--mjr-primary); border-color: rgba(13,202,240,0.1);" title="Modify"><i class="fas fa-pen"></i></a>
                                    <?php if (($account['child_count'] ?? 0) == 0): ?>
                                    <button type="button" class="btn-icon-coa" style="color: var(--mjr-danger); border-color: rgba(255,82,82,0.1);" title="Archive" onclick="confirmDelete(<?= $account['id'] ?>, '<?= escape_html($account['name']) ?>')"><i class="fas fa-times"></i></button>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">No accounts mapped for this company.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<script>
function confirmDelete(id, name) {
    if (confirm('Permanently delete account mapping "' + name + '"?\n\nThis will only proceed if no transactions are linked.')) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

$(document).ready(function() {
    $('#coaTable').DataTable({
        pageLength: 50,
        dom: "fti",
        language: { search: "", searchPlaceholder: "Fast Search Chart..." }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
