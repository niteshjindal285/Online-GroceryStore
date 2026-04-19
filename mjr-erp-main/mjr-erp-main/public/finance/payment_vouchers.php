<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('view_finance');

$page_title = 'Payment Vouchers';
$can_manage_finance = has_permission('manage_finance');
$company_id = (int)active_company_id(1);

// Handle delete
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'delete'
    && $can_manage_finance
    && verify_csrf_token(post('csrf_token'))
) {
    $id = (int)$_POST['id'];
    db_query("DELETE FROM payment_vouchers WHERE id = ? AND company_id = ?", [$id, $company_id]);
    set_flash('Voucher deleted successfully.', 'success');
    redirect('payment_vouchers.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$method = trim((string)($_GET['method'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$period = trim((string)($_GET['period'] ?? ''));

if ($period === 'this_month') {
    $from = date('Y-m-01');
    $to = date('Y-m-t');
} elseif ($period === 'last_month') {
    $from = date('Y-m-01', strtotime('first day of last month'));
    $to = date('Y-m-t', strtotime('last day of last month'));
} elseif ($period === 'this_year') {
    $from = date('Y-01-01');
    $to = date('Y-12-31');
}

$where = [];
$params = [];

$where[] = "pv.company_id = ?";
$params[] = $company_id;

if ($q !== '') {
    $where[] = "(pv.voucher_number LIKE ? OR pv.reference LIKE ? OR pv.description LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($status !== '' && in_array($status, ['draft', 'approved', 'posted', 'cancelled'], true)) {
    $where[] = "pv.status = ?";
    $params[] = $status;
}
if ($method !== '') {
    $where[] = "pv.payment_method = ?";
    $params[] = $method;
}
if ($from !== '') {
    $where[] = "pv.voucher_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $where[] = "pv.voucher_date <= ?";
    $params[] = $to;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$vouchers = db_fetch_all("
    SELECT pv.*, b.bank_name, b.account_name, c.name AS company_name
    FROM payment_vouchers pv
    LEFT JOIN bank_accounts b ON pv.bank_account_id = b.id
    LEFT JOIN companies c ON pv.company_id = c.id
    {$where_sql}
    ORDER BY pv.voucher_date DESC, pv.id DESC
", $params);

$stats = db_fetch("
    SELECT
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) AS posted_count,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM payment_vouchers
    WHERE company_id = ?
", [$company_id]);

$draft_count = (int)($stats['draft_count'] ?? 0);
$approved_count = (int)($stats['approved_count'] ?? 0);
$posted_count = (int)($stats['posted_count'] ?? 0);
$cancelled_count = (int)($stats['cancelled_count'] ?? 0);

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --pv-bg: #0b0f1f;
        --pv-panel: #1a2038;
        --pv-panel-2: #202744;
        --pv-border: #2d3660;
        --pv-text-soft: #94a3c7;
        --pv-cyan: #12c6dd;
        --pv-cyan-deep: #0b6173;
        --pv-green: #4ccb68;
        --pv-red: #ff5449;
        --pv-amber: #ffb02e;
        --pv-table-text: #eef2ff;
        --pv-table-head-bg: #171c32;
    }

    [data-bs-theme="light"] {
        --pv-bg: #f8f9fa;
        --pv-panel: #ffffff;
        --pv-panel-2: #f8f9fa;
        --pv-border: #e0e0e0;
        --pv-text-soft: #6c757d;
        --pv-cyan: #0dcaf0;
        --pv-cyan-deep: #0aa8d4;
        --pv-green: #198754;
        --pv-red: #dc3545;
        --pv-amber: #ffc107;
        --pv-table-text: #212529;
        --pv-table-head-bg: #f8f9fa;
    }

    body { background: var(--pv-bg); color: var(--pv-text-soft); }
    .pv-hero {
        border: 1px solid rgba(18, 198, 221, 0.55);
        border-radius: 12px;
        background: linear-gradient(90deg, rgba(18,198,221,0.14), rgba(18,198,221,0.06));
        color: var(--pv-cyan);
        font-weight: 700;
        letter-spacing: 0.4px;
    }
    .pv-create-btn {
        background: linear-gradient(135deg, #16d5e8, #11b3d1);
        color: #031018;
        border-radius: 12px;
        border: 0;
        font-weight: 700;
        padding: 0.75rem 1.3rem;
        text-decoration: none;
    }
    .pv-card {
        border-radius: 14px;
        border: 1px solid var(--pv-border);
        background: linear-gradient(180deg, var(--pv-panel), var(--pv-panel-2));
    }
    .pv-kpi {
        border-radius: 14px;
        padding: 1rem 1.25rem;
        min-height: 108px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        border: 1px solid rgba(255,255,255,0.05);
    }
    .pv-kpi .label { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 2px; font-weight: 700; color: var(--pv-text-soft); }
    .pv-kpi .value { font-size: 2.3rem; line-height: 1.1; font-weight: 800; color: var(--pv-cyan); }
    .kpi-draft { background: linear-gradient(120deg, rgba(68,76,122,0.55), rgba(53,59,92,0.7)); }
    .kpi-approved { background: linear-gradient(120deg, rgba(26,92,42,0.55), rgba(33,115,53,0.65)); }
    .kpi-posted { background: linear-gradient(120deg, rgba(4,95,110,0.65), rgba(9,116,133,0.7)); }
    .kpi-cancel { background: linear-gradient(120deg, rgba(108,27,27,0.65), rgba(87,21,21,0.75)); }
    .kpi-draft .label { color: #9aa8d6; }
    .kpi-approved .label { color: var(--pv-green); }
    .kpi-posted .label { color: var(--pv-cyan); }
    .kpi-cancel .label { color: var(--pv-red); }

    .pv-filter-label { color: #9ba6c9; font-size: 0.9rem; margin-bottom: 0.35rem; display: block; }
    .pv-input, .pv-select {
        background: #252c49 !important;
        border: 1px solid #32406f !important;
        color: #f2f5ff !important;
        border-radius: 10px;
        padding: 0.62rem 0.85rem;
    }
    .pv-input:focus, .pv-select:focus {
        box-shadow: 0 0 0 0.2rem rgba(18,198,221,0.22) !important;
        border-color: rgba(18,198,221,0.7) !important;
    }
    .pv-chip {
        border-radius: 8px;
        border: 1px solid #33406b;
        color: #9fb0d9;
        padding: 0.25rem 0.7rem;
        text-decoration: none;
        font-size: 0.9rem;
    }
    .pv-chip.active { border-color: rgba(18,198,221,0.65); color: var(--pv-cyan); background: rgba(18,198,221,0.1); }
    .pv-btn-filter {
        border: 0;
        border-radius: 10px;
        background: var(--pv-cyan);
        color: #04111a;
        font-weight: 700;
        padding: 0.62rem 1rem;
    }
    .pv-btn-clear {
        border-radius: 10px;
        border: 1px solid #34406b;
        background: transparent;
        color: #9fb0d9;
        text-decoration: none;
        padding: 0.62rem 1rem;
        font-weight: 600;
    }

    .pv-table-wrap { border: 1px solid var(--pv-border); border-radius: 14px; overflow: hidden; background: var(--pv-panel); }
    .table-dark { --bs-table-bg: transparent; --bs-table-border-color: var(--pv-border); margin-bottom: 0; }
    .table-dark th {
        color: var(--pv-label-text);
        font-size: 0.86rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        border-bottom: 1px solid var(--pv-border);
        padding: 1rem;
        background: var(--pv-table-head-bg);
    }
    .table-dark td { color: var(--pv-table-text); border-bottom: 1px solid rgba(47,56,99,0.25); padding: 0.95rem 1rem; vertical-align: middle; }
    .pv-vno { color: var(--pv-cyan); font-weight: 800; text-decoration: none; }
    .pv-status { padding: 0.28rem 0.66rem; border-radius: 8px; font-size: 0.9rem; font-weight: 700; display: inline-block; }
    .st-draft { background: rgba(255,176,46,0.16); color: var(--pv-amber); }
    .st-approved { background: rgba(76,203,104,0.16); color: var(--pv-green); }
    .st-posted { background: rgba(18,198,221,0.16); color: var(--pv-cyan); }
    .st-cancelled { background: rgba(255,84,73,0.16); color: var(--pv-red); }
    .pv-icon-btn {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1px solid #33406b;
        background: #222a4a;
        color: #a8b5dd;
    }
</style>

<div class="container-fluid px-4 py-4">
    <div class="pv-hero px-4 py-3 mb-4">
        <i class="fas fa-circle me-2" style="font-size: 0.65rem;"></i> SCREEN: Payment Vouchers - List View
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="far fa-file-alt me-2" style="color: var(--pv-cyan);"></i> Payment Vouchers</h2>
            <p class="mb-0 text-muted">Manage outgoing supplier payment vouchers across subsidiaries.</p>
        </div>
        <?php if ($can_manage_finance): ?>
            <a href="add_payment_voucher.php" class="pv-create-btn"><i class="fas fa-plus me-2"></i>Create Payment Voucher</a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="pv-kpi kpi-draft">
                <div class="label">Drafts</div>
                <div class="value"><?= $draft_count ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pv-kpi kpi-posted">
                <div class="label">Posted</div>
                <div class="value"><?= $posted_count ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pv-kpi kpi-approved">
                <div class="label">Approved</div>
                <div class="value"><?= $approved_count ?></div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="pv-kpi kpi-cancel">
                <div class="label">Cancelled</div>
                <div class="value"><?= $cancelled_count ?></div>
            </div>
        </div>
    </div>

    <div class="pv-card p-4 mb-4">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3">
                    <label class="pv-filter-label">Search</label>
                    <input type="text" class="form-control pv-input" name="q" value="<?= escape_html($q) ?>" placeholder="PV# or reference">
                </div>
                <div class="col-lg-2">
                    <label class="pv-filter-label">Status</label>
                    <select class="form-select pv-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="posted" <?= $status === 'posted' ? 'selected' : '' ?>>Posted</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="pv-filter-label">Method</label>
                    <select class="form-select pv-select" name="method">
                        <option value="">All Methods</option>
                        <option value="Bank Transfer" <?= $method === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                        <option value="Cheque" <?= $method === 'Cheque' ? 'selected' : '' ?>>Cheque</option>
                        <option value="Cash" <?= $method === 'Cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="Online Payment" <?= $method === 'Online Payment' ? 'selected' : '' ?>>Online Payment</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="pv-filter-label">From Date</label>
                    <input type="date" class="form-control pv-input" name="from" value="<?= escape_html($from) ?>">
                </div>
                <div class="col-lg-2">
                    <label class="pv-filter-label">To Date</label>
                    <input type="date" class="form-control pv-input" name="to" value="<?= escape_html($to) ?>">
                </div>
                <div class="col-lg-1 d-flex gap-2">
                    <button type="submit" class="pv-btn-filter"><i class="fas fa-search me-1"></i>Filter</button>
                </div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a class="pv-chip <?= $period === 'this_month' ? 'active' : '' ?>" href="?period=this_month">This Month</a>
                <a class="pv-chip <?= $period === 'last_month' ? 'active' : '' ?>" href="?period=last_month">Last Month</a>
                <a class="pv-chip <?= $period === 'this_year' ? 'active' : '' ?>" href="?period=this_year">This Year</a>
                <a class="pv-btn-clear py-1 px-3" href="payment_vouchers.php">Clear</a>
            </div>
        </form>
    </div>

    <div class="pv-table-wrap">
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--pv-border);">
            <h5 class="mb-0 text-white"><i class="fas fa-receipt me-2" style="color:#f7b87c;"></i>Payment Vouchers <span class="badge ms-2" style="background:var(--pv-cyan); color:#031118;"><?= count($vouchers) ?></span></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0" id="voucherTable">
                    <thead>
                        <tr>
                            <th class="ps-4">Voucher #</th>
                            <th>Date</th>
                            <th>Company</th>
                            <th>Bank Account</th>
                            <th>Method</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vouchers as $v): ?>
                        <tr>
                            <td class="ps-4 font-monospace"><a class="pv-vno" href="view_voucher.php?id=<?= $v['id'] ?>"><?= escape_html($v['voucher_number']) ?></a></td>
                            <td><?= format_date($v['voucher_date']) ?></td>
                            <td><?= $v['company_name'] ? escape_html($v['company_name']) : '<span class="text-muted">-</span>' ?></td>
                            <td><?= $v['bank_name'] ? escape_html($v['bank_name'] . ' - ' . $v['account_name']) : '<span class="text-muted">Cash</span>' ?></td>
                            <td><?= escape_html($v['payment_method']) ?></td>
                            <td class="text-end font-monospace" style="color: var(--pv-amber);"><?= format_currency($v['amount']) ?></td>
                            <td class="text-center">
                                <span class="pv-status st-<?= escape_html($v['status']) ?>"><?= strtoupper($v['status']) ?></span>
                            </td>
                            <td class="text-end pe-4">
                                <a href="view_voucher.php?id=<?= $v['id'] ?>" class="btn btn-sm pv-icon-btn"><i class="fas fa-eye"></i></a>
                                <?php if ($can_manage_finance && $v['status'] == 'draft'): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this voucher?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <button type="submit" class="btn btn-sm pv-icon-btn" style="color:#ff8e8e;"><i class="fas fa-trash"></i></button>
                                    </form>
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
    $('#voucherTable').DataTable({
        "order": [[ 1, "desc" ]],
        "pageLength": 25,
        "dom": "t<'d-flex justify-content-between align-items-center p-3'ip>",
        "language": {
            "info": "Showing _START_ to _END_ of _TOTAL_ vouchers",
            "emptyTable": "No payment vouchers found."
        }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
