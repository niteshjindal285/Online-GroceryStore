<?php
/**
 * Journal Entries
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Journal Entries - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view journal entries.', 'warning');
    redirect(url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (post('action') === 'delete')) {
    if (!verify_csrf_token(post('csrf_token'))) {
        set_flash('Invalid security token', 'error');
    } else {
        $entry_id = (int)post('id', 0);
        db_begin_transaction();
        try {
            $entry = db_fetch("SELECT status FROM journal_entries WHERE id = ? AND company_id = ?", [$entry_id, $company_id]);
            if (!$entry) throw new Exception('Journal entry not found or access denied.');
            if (($entry['status'] ?? '') === 'posted') throw new Exception('Cannot delete posted journal entries');
            db_query("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$entry_id]);
            db_query("DELETE FROM journal_entries WHERE id = ? AND company_id = ?", [$entry_id, $company_id]);
            db_commit();
            set_flash('Journal entry deleted successfully', 'success');
        } catch (Exception $e) {
            db_rollback();
            set_flash($e->getMessage(), 'error');
        }
    }
    redirect('journal_entries.php');
}

$status = trim((string)($_GET['status'] ?? ''));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));
$type = strtolower(trim((string)($_GET['type'] ?? '')));
$company_filter = (int)($_GET['company_id'] ?? 0);
$period = trim((string)($_GET['period'] ?? ''));

if ($period === 'this_month') {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-t');
} elseif ($period === 'last_month') {
    $date_from = date('Y-m-01', strtotime('first day of last month'));
    $date_to = date('Y-m-t', strtotime('last day of last month'));
} elseif ($period === 'this_year') {
    $date_from = date('Y-01-01');
    $date_to = date('Y-12-31');
}

$where = ["1=1"];
$params = [];

if ($status !== '') {
    $where[] = "je.status = ?";
    $params[] = $status;
}
if ($date_from !== '') {
    $where[] = "je.entry_date >= ?";
    $params[] = to_db_date($date_from);
}
if ($date_to !== '') {
    $where[] = "je.entry_date <= ?";
    $params[] = to_db_date($date_to);
}
if ($search !== '') {
    $where[] = "(je.entry_number LIKE ? OR je.description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
if ($type === 'reversal') {
    $where[] = "LOWER(je.description) LIKE ?";
    $params[] = '%reversal%';
} elseif ($type === 'general') {
    $where[] = "LOWER(je.description) NOT LIKE ?";
    $params[] = '%reversal%';
}
if ($company_filter > 0) {
    $where[] = "je.company_id = ?";
    $params[] = $company_filter;
}

$where_sql = implode(' AND ', $where) . db_where_company('je');
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name") ?: [];

$base_where_sql = "1=1" . db_where_company('je');
$counts = db_fetch("SELECT
    SUM(CASE WHEN je.status = 'draft' THEN 1 ELSE 0 END) as draft_count,
    SUM(CASE WHEN je.status IN ('entered','pending_approval') THEN 1 ELSE 0 END) as entered_count,
    SUM(CASE WHEN je.status = 'posted' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN LOWER(je.description) LIKE '%reversal%' THEN 1 ELSE 0 END) as reversed_count,
    COUNT(je.id) as total_count
    FROM journal_entries je
    WHERE $base_where_sql");

$entries = db_fetch_all("SELECT je.*, u.username as created_by_name, c.name as company_name,
    CASE WHEN LOWER(je.description) LIKE '%reversal%' THEN 'reversal' ELSE 'general' END as journal_type,
    (SELECT SUM(debit) FROM journal_entry_lines WHERE journal_entry_id = je.id) as total_debit,
    (SELECT SUM(credit) FROM journal_entry_lines WHERE journal_entry_id = je.id) as total_credit
    FROM journal_entries je
    LEFT JOIN users u ON je.created_by = u.id
    LEFT JOIN companies c ON je.company_id = c.id
    WHERE $where_sql
    ORDER BY je.entry_date DESC, je.id DESC", $params);

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"]{--je-bg:#090d1d;--je-panel:#1f2540;--je-panel-2:#222946;--je-border:#313a61;--je-cyan:#10c5df;--je-green:#4ac95e;--je-red:#ff4f45;--je-blue:#2b8fff;--je-soft:#8f9bbf}
[data-bs-theme="light"]{--je-bg:#f8f9fa;--je-panel:#ffffff;--je-panel-2:#f8f9fa;--je-border:#e0e0e0;--je-cyan:#0dcaf0;--je-green:#198754;--je-red:#dc3545;--je-blue:#0d6efd;--je-soft:#6c757d}
body{background:var(--je-bg);color:var(--je-soft)}
.je-hero{border:1px solid rgba(16,197,223,.55);border-radius:12px;background:linear-gradient(90deg,rgba(16,197,223,.16),rgba(16,197,223,.06));color:var(--je-cyan);font-weight:700;letter-spacing:.4px}
.je-create{background:linear-gradient(135deg,#16d6e8,#11b3d0);color:#06121b;border:0;border-radius:12px;font-weight:700;padding:.74rem 1.3rem;text-decoration:none}
.je-kpi{border-radius:14px;min-height:110px;padding:1rem 1.2rem;border:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;justify-content:center}
.je-kpi .k-label{text-transform:uppercase;letter-spacing:2px;font-size:.95rem;font-weight:700}.je-kpi .k-value{font-size:2.25rem;font-weight:800;line-height:1.1;color:#fff}
.k-draft{background:linear-gradient(120deg,rgba(70,75,110,.6),rgba(56,61,95,.72))}.k-entered{background:linear-gradient(120deg,rgba(7,99,114,.72),rgba(9,117,133,.8))}.k-approved{background:linear-gradient(120deg,rgba(26,93,33,.72),rgba(33,119,44,.75))}.k-reversed{background:linear-gradient(120deg,rgba(101,22,22,.72),rgba(92,18,18,.78))}
.k-draft .k-label{color:#a4b0d4}.k-entered .k-label{color:var(--je-cyan)}.k-approved .k-label{color:var(--je-green)}.k-reversed .k-label{color:var(--je-red)}
.je-card{border-radius:14px;border:1px solid var(--je-border);background:linear-gradient(180deg,var(--je-panel),var(--je-panel-2))}
.je-label{color:#9ca8cc;font-size:.9rem;margin-bottom:.35rem;display:block}.je-input,.je-select{width:100%;background:#252d4a!important;border:1px solid #344271!important;color:#f2f5ff!important;border-radius:10px;padding:.62rem .85rem}
.je-input:focus,.je-select:focus{border-color:rgba(16,197,223,.7)!important;box-shadow:0 0 0 .2rem rgba(16,197,223,.2)!important}
.je-chip{border:1px solid #344271;color:#a6b3da;border-radius:8px;padding:.25rem .72rem;text-decoration:none;font-size:.95rem}.je-chip.active{border-color:rgba(16,197,223,.7);color:var(--je-cyan);background:rgba(16,197,223,.1)}
.je-btn-filter{border:0;border-radius:10px;background:var(--je-cyan);color:#06121b;font-weight:700;padding:.62rem 1rem}.je-btn-clear{border:1px solid #344271;border-radius:10px;color:#a6b3da;text-decoration:none;padding:.62rem 1rem;font-weight:600}
.je-table-wrap{border-radius:14px;border:1px solid var(--je-border);overflow:hidden;background:#1d2340}.table-je{--bs-table-bg:transparent;--bs-table-border-color:#313a61;margin-bottom:0}
.table-je th{color:#8f9bc1;font-size:.86rem;text-transform:uppercase;letter-spacing:1px;font-weight:700;border-bottom:1px solid #313a61;padding:1rem}.table-je td{color:#eef2ff;border-bottom:1px solid rgba(49,58,97,.65);padding:.95rem 1rem;vertical-align:middle}
.je-no{color:#06d3ec;font-weight:800;text-decoration:none}.je-type,.je-st{display:inline-block;padding:.24rem .66rem;border-radius:7px;font-weight:700;font-size:.9rem}
.type-general{background:rgba(43,143,255,.2);color:var(--je-blue)}.type-reversal{background:rgba(255,176,46,.2);color:#ffb53a}
.st-draft{background:rgba(255,176,46,.16);color:#ffb53a}.st-entered,.st-pending_approval{background:rgba(16,197,223,.16);color:var(--je-cyan)}.st-posted{background:rgba(74,201,94,.16);color:var(--je-green)}.st-rejected,.st-cancelled{background:rgba(255,79,69,.16);color:var(--je-red)}
.je-icon-btn{width:34px;height:34px;border-radius:8px;border:1px solid #344271;background:#252d4a;color:#a8b5dd}
</style>

<div class="container-fluid px-4 py-4">
    <div class="je-hero px-4 py-3 mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem"></i> SCREEN: Journal Entries - List View</div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white"><i class="far fa-file-alt me-2" style="color:var(--je-cyan)"></i>Journal Entries</h1>
            <p class="mb-0">Manage general, reversal, debtor and creditor journal entries across all subsidiaries.</p>
        </div>
        <?php if (has_permission('manage_finance')): ?>
            <a href="add_journal_entry.php" class="je-create"><i class="fas fa-plus me-2"></i>Create Journal Entry</a>
        <?php endif; ?>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6"><div class="je-kpi k-draft"><div class="k-label">Drafts</div><div class="k-value"><?= (int)($counts['draft_count'] ?? 0) ?></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="je-kpi k-entered"><div class="k-label">Entered</div><div class="k-value"><?= (int)($counts['entered_count'] ?? 0) ?></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="je-kpi k-approved"><div class="k-label">Approved</div><div class="k-value"><?= (int)($counts['approved_count'] ?? 0) ?></div></div></div>
        <div class="col-xl-3 col-md-6"><div class="je-kpi k-reversed"><div class="k-label">Reversed</div><div class="k-value"><?= (int)($counts['reversed_count'] ?? 0) ?></div></div></div>
    </div>

    <div class="je-card p-4 mb-4 d-print-none">
        <form method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-lg-3"><label class="je-label">Search</label><input type="text" name="search" class="je-input" value="<?= escape_html($search) ?>" placeholder="JE# or narration"></div>
                <div class="col-lg-2"><label class="je-label">Type</label><select name="type" class="je-select"><option value="">All Types</option><option value="general" <?= $type === 'general' ? 'selected' : '' ?>>General</option><option value="reversal" <?= $type === 'reversal' ? 'selected' : '' ?>>Reversal</option></select></div>
                <div class="col-lg-2"><label class="je-label">Status</label><select name="status" class="je-select"><option value="">All Statuses</option><option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option><option value="entered" <?= $status === 'entered' ? 'selected' : '' ?>>Entered</option><option value="pending_approval" <?= $status === 'pending_approval' ? 'selected' : '' ?>>Pending</option><option value="posted" <?= $status === 'posted' ? 'selected' : '' ?>>Approved</option></select></div>
                <div class="col-lg-2"><label class="je-label">Company</label><select name="company_id" class="je-select"><option value="">All Companies</option><?php foreach ($companies as $comp): ?><option value="<?= (int)$comp['id'] ?>" <?= $company_filter === (int)$comp['id'] ? 'selected' : '' ?>><?= escape_html($comp['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-lg-2"><label class="je-label">From Date</label><input type="date" name="date_from" class="je-input" value="<?= escape_html($date_from) ?>"></div>
                <div class="col-lg-2"><label class="je-label">To Date</label><input type="date" name="date_to" class="je-input" value="<?= escape_html($date_to) ?>"></div>
                <div class="col-lg-1 d-flex gap-2"><button type="submit" class="je-btn-filter"><i class="fas fa-search me-1"></i>Filter</button><a href="journal_entries.php" class="je-btn-clear">Clear</a></div>
            </div>
            <div class="d-flex gap-2 mt-3">
                <a class="je-chip <?= $period === 'this_month' ? 'active' : '' ?>" href="?period=this_month">This Month</a>
                <a class="je-chip <?= $period === 'last_month' ? 'active' : '' ?>" href="?period=last_month">Last Month</a>
                <a class="je-chip <?= $period === 'this_year' ? 'active' : '' ?>" href="?period=this_year">This Year</a>
            </div>
        </form>
    </div>

    <div class="je-table-wrap">
        <div class="d-flex justify-content-between align-items-center px-4 py-3" style="border-bottom:1px solid var(--je-border)">
            <h5 class="mb-0 text-white"><i class="fas fa-receipt me-2" style="color:#f7b87c"></i>Journal Entries <span class="badge ms-2" style="background:var(--je-cyan);color:#04121b"><?= count($entries) ?></span></h5>
            <div style="width:220px"><input id="tableSearch" type="text" class="je-input" placeholder="Search..."></div>
        </div>
        <div class="p-0">
            <?php if (!empty($entries)): ?>
                <div class="table-responsive">
                    <table class="table table-je table-hover mb-0" id="jeTable">
                        <thead>
                            <tr>
                                <th class="ps-4">JE Number</th><th>Type</th><th>Date</th><th>Post Period</th><th>Narration</th><th class="text-end">Debit Total</th><th class="text-end">Credit Total</th><th>Company</th><th>Status</th><th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry): ?>
                                <tr>
                                    <td class="ps-4"><a href="view_journal_entry.php?id=<?= $entry['id'] ?>" class="je-no font-monospace"><?= escape_html($entry['entry_number']) ?></a></td>
                                    <td><span class="je-type type-<?= $entry['journal_type'] === 'reversal' ? 'reversal' : 'general' ?>"><?= ucfirst($entry['journal_type']) ?></span></td>
                                    <td><?= date('d-m-Y', strtotime($entry['entry_date'])) ?></td>
                                    <td><?= date('M Y', strtotime($entry['entry_date'])) ?></td>
                                    <td><?= escape_html($entry['description'] ?: '-') ?></td>
                                    <td class="text-end font-monospace"><?= format_currency($entry['total_debit']) ?></td>
                                    <td class="text-end font-monospace"><?= format_currency($entry['total_credit']) ?></td>
                                    <td><?= escape_html($entry['company_name'] ?? active_company_name('Selected Company')) ?></td>
                                    <td><span class="je-st st-<?= escape_html((string)$entry['status']) ?>"><?= strtoupper(str_replace('_', ' ', (string)$entry['status'])) ?></span></td>
                                    <td class="text-end pe-4"><div class="d-flex justify-content-end gap-1"><a href="view_journal_entry.php?id=<?= $entry['id'] ?>" class="btn btn-sm je-icon-btn" title="View"><i class="fas fa-eye"></i></a><?php if (has_permission('manage_finance') && $entry['status'] !== 'posted'): ?><a href="edit_journal_entry.php?id=<?= $entry['id'] ?>" class="btn btn-sm je-icon-btn" style="color:#f6a14d" title="Edit"><i class="fas fa-pen"></i></a><button type="button" class="btn btn-sm je-icon-btn" style="color:#ff8a8a" title="Delete" onclick="confirmDelete(<?= $entry['id'] ?>, '<?= escape_html($entry['entry_number']) ?>')"><i class="fas fa-trash"></i></button><?php endif; ?></div></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5"><i class="fas fa-file-invoice fa-3x mb-3 opacity-25"></i><h5 class="text-white">No entries found matching filters.</h5></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="deleteForm" method="POST" style="display:none">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
</form>

<script>
$(document).ready(function(){
    const dt = $('#jeTable').DataTable({order:[[2,'desc']],pageLength:25,dom:"t<'d-flex justify-content-between align-items-center p-3'ip>"});
    $('#tableSearch').on('keyup', function(){ dt.search(this.value).draw(); });
});
function confirmDelete(id, number){ if(confirm('Permanently delete journal entry "'+number+'"?')){ document.getElementById('deleteId').value=id; document.getElementById('deleteForm').submit(); } }
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
