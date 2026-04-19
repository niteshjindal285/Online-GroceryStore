<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Receipts';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    set_flash('Saved receipts are locked and cannot be deleted.', 'error');
    redirect('finance/receipts.php');
}

$search = trim((string)get('search', ''));
$status_filter = strtolower(trim((string)get('status', 'all')));
$from_date = trim((string)get('from_date', ''));
$to_date = trim((string)get('to_date', ''));

$where_sql = "WHERE 1=1 " . db_where_company('r');
$params = [];

if ($search !== '') {
    $where_sql .= " AND (r.receipt_number LIKE ? OR c.name LIKE ? OR COALESCE(r.reference, '') LIKE ?) ";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($status_filter !== '' && $status_filter !== 'all') {
    if ($status_filter === 'open') {
        $where_sql .= " AND LOWER(COALESCE(r.status, 'draft')) IN ('draft', 'open', 'pending') ";
    } elseif ($status_filter === 'banked') {
        $where_sql .= " AND LOWER(COALESCE(r.status, '')) IN ('posted', 'banked', 'approved') ";
    } else {
        $where_sql .= " AND LOWER(COALESCE(r.status, '')) = ? ";
        $params[] = $status_filter;
    }
}

if ($from_date !== '') {
    $where_sql .= " AND DATE(r.receipt_date) >= ? ";
    $params[] = $from_date;
}
if ($to_date !== '') {
    $where_sql .= " AND DATE(r.receipt_date) <= ? ";
    $params[] = $to_date;
}

$receipts = db_fetch_all("
    SELECT r.*, b.bank_name, b.account_name, c.name AS customer_name
    FROM receipts r
    LEFT JOIN bank_accounts b ON r.bank_account_id = b.id
    LEFT JOIN customers c ON r.customer_id = c.id
    {$where_sql}
    ORDER BY r.receipt_date DESC, r.id DESC
", $params) ?: [];

$open_count = 0;
$banked_count = 0;
foreach ($receipts as $r) {
    $status = strtolower((string)($r['status'] ?? 'draft'));
    if (in_array($status, ['posted', 'banked', 'approved'], true)) {
        $banked_count++;
    } else {
        $open_count++;
    }
}

$month_total = db_fetch("
    SELECT COALESCE(SUM(r.amount), 0) AS total_amount
    FROM receipts r
    WHERE YEAR(r.receipt_date) = YEAR(CURDATE())
      AND MONTH(r.receipt_date) = MONTH(CURDATE())
      " . db_where_company('r') . "
") ?: ['total_amount' => 0];

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --rc-bg: #080c1a;
    --rc-panel: #1d243c;
    --rc-panel-2: #1a2035;
    --rc-line: #313a61;
    --rc-cyan: #08d0ef;
    --rc-soft: #8f9dc5;
    --rc-text: #eef3ff;
    --rc-title: #ffffff;
    --rc-sub: #9fb0da;
    --rc-btn-bg: #0fc7df;
    --rc-btn-text: #04111c;
    --rc-card-border: rgba(255,255,255,.03);
    --rc-input-bg: #252d4a;
    --rc-input-border: #344271;
    --rc-input-text: #eef3ff;
    --rc-label: #9aa9d1;
    --rc-table-head: #8f9cc0;
    --rc-table-row: #edf2ff;
    --rc-table-border: rgba(49,58,97,.55);
    --rc-open: #ff9b1a;
    --rc-open-bg: rgba(255,155,26,.18);
    --rc-banked: #06c8e3;
    --rc-banked-bg: rgba(6,200,227,.18);
    --rc-month: #41c95b;
}

[data-bs-theme="light"] {
    --rc-bg: #f8f9fa;
    --rc-panel: #ffffff;
    --rc-panel-2: #f8f9fa;
    --rc-line: #e0e0e0;
    --rc-cyan: #0dcaf0;
    --rc-soft: #6c757d;
    --rc-text: #212529;
    --rc-title: #212529;
    --rc-sub: #6c757d;
    --rc-btn-bg: #0dcaf0;
    --rc-btn-text: #04111c;
    --rc-card-border: #dee2e6;
    --rc-input-bg: #ffffff;
    --rc-input-border: #ced4da;
    --rc-input-text: #212529;
    --rc-label: #6c757d;
    --rc-table-head: #495057;
    --rc-table-row: #212529;
    --rc-table-border: #dee2e6;
    --rc-open: #ff9b1a;
    --rc-open-bg: rgba(255,155,26,.12);
    --rc-banked: #06c8e3;
    --rc-banked-bg: rgba(6,200,227,.12);
    --rc-month: #198754;
}

body {
    background: var(--rc-bg);
    color: var(--rc-text);
}
.rc-screen {
    border: 1px solid rgba(8,208,239,.55);
    border-radius: 10px;
    background: rgba(8,208,239,.07);
    color: var(--rc-cyan);
    font-weight: 700;
    padding: .65rem 1rem;
}
.rc-title {
    color: var(--rc-title);
    font-weight: 800;
    letter-spacing: .2px;
}
.rc-sub {
    color: var(--rc-sub);
    margin-bottom: 0;
}
.rc-btn {
    background: var(--rc-btn-bg);
    color: var(--rc-btn-text);
    border-radius: 10px;
    border: 0;
    font-weight: 700;
    text-decoration: none;
    padding: .75rem 1.2rem;
    display: inline-flex;
    align-items: center;
    gap: .45rem;
}
.rc-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: .9rem;
}
.rc-card {
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 1px solid var(--rc-card-border);
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}
.rc-card h6 {
    text-transform: uppercase;
    letter-spacing: 2px;
    font-size: .72rem;
    margin: 0 0 .35rem 0;
    font-weight: 800;
}
.rc-card .v {
    font-size: 2.05rem;
    font-weight: 900;
    line-height: 1;
}
.rc-card-open {
    background: linear-gradient(90deg, rgba(90,57,0,.82), rgba(118,76,0,.68));
}
.rc-card-open h6, .rc-card-open .v { color: var(--rc-open); }
.rc-card-banked {
    background: linear-gradient(90deg, rgba(5,86,95,.82), rgba(8,116,129,.76));
}
.rc-card-banked h6, .rc-card-banked .v { color: var(--rc-banked); }
.rc-card-month {
    background: linear-gradient(90deg, rgba(26,78,25,.82), rgba(34,98,33,.76));
}
.rc-card-month h6, .rc-card-month .v { color: var(--rc-month); }
.rc-panel {
    border-radius: 12px;
    border: 1px solid var(--rc-line);
    background: linear-gradient(180deg, var(--rc-panel), var(--rc-panel-2));
}
.rc-filter-grid {
    display: grid;
    grid-template-columns: 1.2fr 1.2fr 1.2fr 1.2fr auto;
    gap: .8rem;
    align-items: end;
}
.rc-label {
    color: var(--rc-label);
    font-size: .85rem;
    margin-bottom: .3rem;
}
.rc-input, .rc-select {
    width: 100%;
    background: var(--rc-input-bg);
    border: 1px solid var(--rc-input-border);
    color: var(--rc-input-text);
    border-radius: 8px;
    padding: .58rem .8rem;
}
.rc-table-wrap {
    overflow: auto;
}
.rc-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}
.rc-table thead th {
    padding: 1rem .95rem;
    font-size: .76rem;
    color: var(--rc-table-head);
    text-transform: uppercase;
    letter-spacing: 1px;
    border-bottom: 1px solid var(--rc-table-border);
    white-space: nowrap;
}
.rc-table tbody td {
    padding: .9rem .95rem;
    border-bottom: 1px solid var(--rc-table-border);
    color: var(--rc-table-row);
    white-space: nowrap;
}
.rc-receipt-link {
    color: var(--rc-cyan);
    text-decoration: none;
    font-weight: 800;
}
.rc-status {
    display: inline-block;
    border-radius: 6px;
    font-weight: 700;
    font-size: .84rem;
    padding: .2rem .6rem;
}
.rc-status-open { color: var(--rc-open); background: var(--rc-open-bg); }
.rc-status-banked { color: var(--rc-banked); background: var(--rc-banked-bg); }
.rc-eye {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #334171;
    background: transparent;
    color: #a7b4d8;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}
.rc-foot {
    border-top: 1px solid rgba(255,179,47,.25);
    background: rgba(255,179,47,.08);
    color: #ffbf45;
    border-radius: 10px;
    padding: .7rem 1rem;
    font-weight: 600;
}
.rc-wire {
    float: right;
    background: rgba(8,208,239,.88);
    color: #00141d;
    padding: .2rem .7rem;
    border-radius: 999px;
    font-size: .85rem;
    font-weight: 800;
}
@media (max-width: 1200px) {
    .rc-cards { grid-template-columns: 1fr; }
    .rc-filter-grid { grid-template-columns: 1fr; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="rc-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.55rem;"></i> SCREEN: Receipts - List / Dashboard View</div>

    <div class="d-flex justify-content-between align-items-start mb-4 gap-3 flex-wrap">
        <div>
            <h2 class="rc-title mb-1"><i class="fas fa-receipt me-2" style="color:#d3d9f1;"></i>Receipts</h2>
            <p class="rc-sub">Record incoming receipts from customers and apply against open invoices.</p>
        </div>
        <a href="add_receipt.php" class="rc-btn">+ Generate Receipt</a>
    </div>

    <div class="rc-cards mb-4">
        <div class="rc-card rc-card-open">
            <div>
                <h6>Open (Unbanked)</h6>
                <div class="v"><?= (int)$open_count ?></div>
            </div>
        </div>
        <div class="rc-card rc-card-banked">
            <div>
                <h6>Banked</h6>
                <div class="v"><?= (int)$banked_count ?></div>
            </div>
        </div>
        <div class="rc-card rc-card-month">
            <div>
                <h6>This Month Total</h6>
                <div class="v"><?= format_currency((float)($month_total['total_amount'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <form method="GET" class="rc-panel p-3 mb-4">
        <div class="rc-filter-grid">
            <div>
                <label class="rc-label">Search</label>
                <input class="rc-input" type="text" name="search" value="<?= escape_html($search) ?>" placeholder="Receipt# or customer">
            </div>
            <div>
                <label class="rc-label">Status</label>
                <select class="rc-select" name="status">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="open" <?= $status_filter === 'open' ? 'selected' : '' ?>>Open</option>
                    <option value="banked" <?= $status_filter === 'banked' ? 'selected' : '' ?>>Banked</option>
                    <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="posted" <?= $status_filter === 'posted' ? 'selected' : '' ?>>Posted</option>
                </select>
            </div>
            <div>
                <label class="rc-label">From Date</label>
                <input class="rc-input" type="date" name="from_date" value="<?= escape_html($from_date) ?>">
            </div>
            <div>
                <label class="rc-label">To Date</label>
                <input class="rc-input" type="date" name="to_date" value="<?= escape_html($to_date) ?>">
            </div>
            <div>
                <button type="submit" class="rc-btn" style="padding:.55rem 1rem;"><i class="fas fa-search"></i> Filter</button>
            </div>
        </div>
    </form>

    <div class="rc-panel mb-3">
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--rc-line);">
            <div class="text-white fw-bold"><i class="fas fa-file-invoice me-2" style="color:#ffd8be;"></i> Receipt History <span class="badge rounded-pill ms-1" style="background:#0fc7df;color:#00131d;"><?= count($receipts) ?></span></div>
        </div>

        <div class="rc-table-wrap">
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Receipt #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Deposit Ref</th>
                        <th>Deposit Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($receipts)): ?>
                    <tr><td colspan="8" class="text-center py-4" style="color:#98a7cd;">No receipts found for selected filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($receipts as $r): ?>
                        <?php
                        $status_raw = strtolower((string)($r['status'] ?? 'draft'));
                        $is_banked = in_array($status_raw, ['posted', 'banked', 'approved'], true);
                        $status_label = $is_banked ? 'Banked' : 'Open';
                        $deposit_ref = trim((string)($r['reference'] ?? ''));
                        $deposit_date_source = $r['posted_at'] ?? null;
                        ?>
                        <tr>
                            <td><?= escape_html(date('d-m-Y', strtotime((string)$r['receipt_date']))) ?></td>
                            <td><a class="rc-receipt-link" href="view_receipt.php?id=<?= (int)$r['id'] ?>"><?= escape_html((string)$r['receipt_number']) ?></a></td>
                            <td><?= escape_html((string)($r['customer_name'] ?: 'N/A')) ?></td>
                            <td><?= format_currency((float)$r['amount']) ?></td>
                            <td>
                                <span class="rc-status <?= $is_banked ? 'rc-status-banked' : 'rc-status-open' ?>">
                                    <?= $status_label ?>
                                </span>
                            </td>
                            <td><?= $deposit_ref !== '' ? escape_html($deposit_ref) : '&mdash;' ?></td>
                            <td><?= !empty($deposit_date_source) ? escape_html(date('d-m-Y', strtotime((string)$deposit_date_source))) : '&mdash;' ?></td>
                            <td class="text-center">
                                <a href="view_receipt.php?id=<?= (int)$r['id'] ?>" class="rc-eye"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="rc-foot">
        <i class="fas fa-lock me-2"></i>Receipts once saved cannot be amended or cancelled. Once applied, invoice status changes to Closed.
        
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

