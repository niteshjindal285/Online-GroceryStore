<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Debit/Credit Notes';
$can_manage_finance = has_permission('manage_finance');
$company_id = (int)active_company_id(1);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'delete'
    && $can_manage_finance
    && verify_csrf_token(post('csrf_token'))
) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        db_query("DELETE FROM debit_credit_notes WHERE id = ? AND company_id = ?", [$id, $company_id]);
        set_flash('Note deleted successfully.', 'success');
    }
    redirect('debit_credit_notes.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$note_type = trim((string)($_GET['type'] ?? ''));
$party_type = trim((string)($_GET['entity_type'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

$where = [];
$params = [];

$where[] = "n.company_id = ?";
$params[] = $company_id;

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = "(n.note_number LIKE ? OR c.name LIKE ? OR s.name LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (in_array($note_type, ['debit_note', 'credit_note'], true)) {
    $where[] = "n.type = ?";
    $params[] = $note_type;
}
if (in_array($party_type, ['customer', 'supplier'], true)) {
    $where[] = "n.entity_type = ?";
    $params[] = $party_type;
}
if (in_array($status, ['draft', 'approved', 'posted', 'cancelled'], true)) {
    $where[] = "n.status = ?";
    $params[] = $status;
}
if ($from !== '') {
    $where[] = "n.note_date >= ?";
    $params[] = $from;
}
if ($to !== '') {
    $where[] = "n.note_date <= ?";
    $params[] = $to;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$notes = db_fetch_all("
    SELECT n.*,
           comp.name AS company_name,
           CASE
               WHEN n.entity_type = 'customer' THEN c.name
               WHEN n.entity_type = 'supplier' THEN s.name
               ELSE NULL
           END AS party_name
    FROM debit_credit_notes n
    LEFT JOIN customers c ON n.entity_type = 'customer' AND n.entity_id = c.id
    LEFT JOIN suppliers s ON n.entity_type = 'supplier' AND n.entity_id = s.id
    LEFT JOIN companies comp ON n.company_id = comp.id
    {$where_sql}
    ORDER BY n.note_date DESC, n.id DESC
", $params) ?: [];

$total_count = count($notes);
$debit_count = 0;
$credit_count = 0;
$entered_count = 0;
$approved_count = 0;

foreach ($notes as $row) {
    if (($row['type'] ?? '') === 'debit_note') $debit_count++;
    if (($row['type'] ?? '') === 'credit_note') $credit_count++;
    if (($row['status'] ?? '') === 'draft') $entered_count++;
    if (($row['status'] ?? '') === 'approved') $approved_count++;
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --dn-bg: #0b0f1f;
    --dn-panel: #1a2038;
    --dn-panel-2: #202744;
    --dn-border: #2d3660;
    --dn-text-soft: #94a3c7;
    --dn-cyan: #12c6dd;
    --dn-blue: #2592ff;
    --dn-amber: #ffad14;
    --dn-green: #4ccb68;
    --dn-table-head-bg: #171c32;
    --dn-table-text: #eef2ff;
}

[data-bs-theme="light"] {
    --dn-bg: #f8f9fa;
    --dn-panel: #ffffff;
    --dn-panel-2: #f8f9fa;
    --dn-border: #e0e0e0;
    --dn-text-soft: #6c757d;
    --dn-cyan: #0dcaf0;
    --dn-blue: #0d6efd;
    --dn-amber: #ffc107;
    --dn-green: #198754;
    --dn-table-head-bg: #f8f9fa;
    --dn-table-text: #212529;
}

body { background: var(--dn-bg); color: var(--dn-text-soft); }
.dn-hero {
    border: 1px solid rgba(18,198,221,.55);
    border-radius: 12px;
    background: linear-gradient(90deg, rgba(18,198,221,.14), rgba(18,198,221,.06));
    color: var(--dn-cyan);
    font-weight: 700;
    letter-spacing: .4px;
}
.dn-card {
    border-radius: 14px;
    border: 1px solid var(--dn-border);
    background: linear-gradient(180deg, var(--dn-panel), var(--dn-panel-2));
}
.dn-create-btn {
    background: linear-gradient(135deg, #16d5e8, #11b3d1);
    color: #031018;
    border-radius: 12px;
    border: 0;
    font-weight: 700;
    padding: .75rem 1.3rem;
    text-decoration: none;
}
.dn-history-btn {
    border-radius: 12px;
    border: 1px solid #34406b;
    background: transparent;
    color: #9fb0d9;
    text-decoration: none;
    padding: .75rem 1.3rem;
    font-weight: 700;
}
.dn-kpi {
    border-radius: 14px;
    padding: 1rem 1.25rem;
    min-height: 108px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    border: 1px solid rgba(255,255,255,.05);
}
.dn-kpi .label { font-size: .9rem; text-transform: uppercase; letter-spacing: 2px; font-weight: 700; }
.dn-kpi .value { font-size: 2.3rem; line-height: 1.1; font-weight: 800; color: #fff; }
.kpi-total { background: linear-gradient(120deg, rgba(68,76,122,.55), rgba(53,59,92,.7)); }
.kpi-debit { background: linear-gradient(120deg, rgba(15,55,97,.65), rgba(20,64,108,.72)); }
.kpi-credit { background: linear-gradient(120deg, rgba(108,72,10,.65), rgba(90,58,8,.75)); }
.kpi-entered { background: linear-gradient(120deg, rgba(4,95,110,.65), rgba(9,116,133,.7)); }
.kpi-approved { background: linear-gradient(120deg, rgba(26,92,42,.55), rgba(33,115,53,.65)); }
.kpi-total .label { color: #9aa8d6; }
.kpi-debit .label { color: var(--dn-blue); }
.kpi-credit .label { color: var(--dn-amber); }
.kpi-entered .label { color: var(--dn-cyan); }
.kpi-approved .label { color: var(--dn-green); }

.dn-filter-label { color: #9ba6c9; font-size: .9rem; margin-bottom: .35rem; display: block; }
.dn-input, .dn-select {
    background: #252c49 !important;
    border: 1px solid #32406f !important;
    color: #f2f5ff !important;
    border-radius: 10px;
    padding: .62rem .85rem;
}
.dn-input:focus, .dn-select:focus {
    box-shadow: 0 0 0 .2rem rgba(18,198,221,.22) !important;
    border-color: rgba(18,198,221,.7) !important;
}
.dn-btn-filter {
    border: 0;
    border-radius: 10px;
    background: var(--dn-cyan);
    color: #04111a;
    font-weight: 700;
    padding: .62rem 1rem;
}
.dn-table-wrap { border: 1px solid var(--dn-border); border-radius: 14px; overflow: hidden; background: var(--dn-panel); }
.table-dark { --bs-table-bg: transparent; --bs-table-border-color: var(--dn-border); margin-bottom: 0; }
.table-dark th {
    color: var(--dn-text-soft);
    font-size: .86rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    border-bottom: 1px solid var(--dn-border);
    padding: 1rem;
    background: var(--dn-table-head-bg);
}
.table-dark td { color: var(--dn-table-text); border-bottom: 1px solid rgba(47,56,99,.25); padding: .95rem 1rem; vertical-align: middle; }
.dn-ref { color: #08d6ef; font-weight: 800; text-decoration: none; }
.dn-badge { padding: .28rem .66rem; border-radius: 8px; font-size: .9rem; font-weight: 700; display: inline-block; }
.type-debit { background: rgba(37,146,255,.16); color: var(--dn-blue); }
.type-credit { background: rgba(255,173,20,.16); color: var(--dn-amber); }
.st-draft { background: rgba(18,198,221,.16); color: var(--dn-cyan); }
.st-approved { background: rgba(76,203,104,.16); color: var(--dn-green); }
.st-posted { background: rgba(76,203,104,.16); color: var(--dn-green); }
.st-cancelled { background: rgba(255,84,73,.16); color: #ff5449; }
.dn-icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid #33406b;
    background: #222a4a;
    color: #a8b5dd;
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="dn-hero px-4 py-3 mb-4">
        <i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Debit / Credit Notes - List / Dashboard View
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 text-white fw-bold"><i class="fas fa-file-invoice me-2" style="color: var(--dn-cyan);"></i> Debit / Credit Notes</h2>
            <p class="mb-0 text-muted">Manage debit notes and credit notes for debtors, suppliers and inventory.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('finance/index.php') ?>" class="dn-history-btn"><i class="fas fa-scroll me-2"></i>Account History</a>
            <a href="add_note.php" class="dn-create-btn"><i class="fas fa-plus me-2"></i>Create DRN / CRN</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl col-md-6">
            <div class="dn-kpi kpi-total">
                <div class="label">Total</div>
                <div class="value"><?= (int)$total_count ?></div>
            </div>
        </div>
        <div class="col-xl col-md-6">
            <div class="dn-kpi kpi-debit">
                <div class="label">Debit Notes</div>
                <div class="value"><?= (int)$debit_count ?></div>
            </div>
        </div>
        <div class="col-xl col-md-6">
            <div class="dn-kpi kpi-credit">
                <div class="label">Credit Notes</div>
                <div class="value"><?= (int)$credit_count ?></div>
            </div>
        </div>
        <div class="col-xl col-md-6">
            <div class="dn-kpi kpi-entered">
                <div class="label">Entered</div>
                <div class="value"><?= (int)$entered_count ?></div>
            </div>
        </div>
        <div class="col-xl col-md-6">
            <div class="dn-kpi kpi-approved">
                <div class="label">Approved</div>
                <div class="value"><?= (int)$approved_count ?></div>
            </div>
        </div>
    </div>

    <div class="dn-card p-4 mb-4">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-lg-2">
                    <label class="dn-filter-label">Search</label>
                    <input type="text" class="form-control dn-input" name="q" value="<?= escape_html($q) ?>" placeholder="Ref# or party name">
                </div>
                <div class="col-lg-2">
                    <label class="dn-filter-label">Note Type</label>
                    <select class="form-select dn-select" name="type">
                        <option value="">All</option>
                        <option value="debit_note" <?= $note_type === 'debit_note' ? 'selected' : '' ?>>Debit Note</option>
                        <option value="credit_note" <?= $note_type === 'credit_note' ? 'selected' : '' ?>>Credit Note</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="dn-filter-label">Party Type</label>
                    <select class="form-select dn-select" name="entity_type">
                        <option value="">All</option>
                        <option value="customer" <?= $party_type === 'customer' ? 'selected' : '' ?>>Debtors</option>
                        <option value="supplier" <?= $party_type === 'supplier' ? 'selected' : '' ?>>Supplier</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="dn-filter-label">Status</label>
                    <select class="form-select dn-select" name="status">
                        <option value="">All</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Entered</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="posted" <?= $status === 'posted' ? 'selected' : '' ?>>Posted</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="dn-filter-label">From Date</label>
                    <input type="date" class="form-control dn-input" name="from" value="<?= escape_html($from) ?>">
                </div>
                <div class="col-lg-2">
                    <label class="dn-filter-label">To Date</label>
                    <input type="date" class="form-control dn-input" name="to" value="<?= escape_html($to) ?>">
                </div>
                <div class="col-12 d-flex gap-2 justify-content-end">
                    <a class="btn dn-history-btn py-2 px-3" href="debit_credit_notes.php">Clear</a>
                    <button type="submit" class="dn-btn-filter"><i class="fas fa-search me-1"></i>Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="dn-table-wrap">
        <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--dn-border);">
            <h5 class="mb-0 text-white"><i class="fas fa-receipt me-2" style="color:#f7b87c;"></i>Debit / Credit Notes <span class="badge ms-2" style="background:var(--dn-cyan); color:#031118;"><?= (int)$total_count ?></span></h5>
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ref #</th>
                        <th>Type</th>
                        <th>Party Type</th>
                        <th>Party Name</th>
                        <th>Date</th>
                        <th>Post Period</th>
                        <th>Amount</th>
                        <th>Company</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notes)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">No notes found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notes as $n): ?>
                            <?php
                                $is_debit = (($n['type'] ?? '') === 'debit_note');
                                $type_label = $is_debit ? 'Debit Note' : 'Credit Note';
                                $type_class = $is_debit ? 'type-debit' : 'type-credit';
                                $status_key = strtolower((string)($n['status'] ?? 'draft'));
                                $status_label = $status_key === 'draft' ? 'Entered' : ucfirst($status_key);
                                $status_class = 'st-' . $status_key;
                                $party_label = strtolower((string)($n['entity_type'] ?? '')) === 'customer' ? 'Debtors' : 'Supplier';
                            ?>
                            <tr>
                                <td><a href="view_note.php?id=<?= (int)$n['id'] ?>" class="dn-ref"><?= escape_html((string)$n['note_number']) ?></a></td>
                                <td><span class="dn-badge <?= $type_class ?>"><?= $type_label ?></span></td>
                                <td><?= escape_html($party_label) ?></td>
                                <td><?= escape_html((string)($n['party_name'] ?? 'Unknown')) ?></td>
                                <td><?= escape_html(format_date((string)$n['note_date'])) ?></td>
                                <td><?= escape_html(date('M Y', strtotime((string)$n['note_date']))) ?></td>
                                <td><?= escape_html(format_currency((float)$n['amount'])) ?></td>
                                <td><?= escape_html((string)($n['company_name'] ?? 'N/A')) ?></td>
                                <td><span class="dn-badge <?= $status_class ?>"><?= escape_html($status_label) ?></span></td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="view_note.php?id=<?= (int)$n['id'] ?>" class="btn dn-icon-btn" title="View"><i class="fas fa-eye"></i></a>
                                        <?php if ($can_manage_finance): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this note?');">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                                <button type="submit" class="btn dn-icon-btn" title="Delete"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
