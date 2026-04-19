<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Project Expense';
$company_id = (int)active_company_id(1);

$table_has_column = function (string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) return $cache[$key];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        $cache[$key] = false;
        return false;
    }
    try {
        $row = db_fetch("SHOW COLUMNS FROM `{$table}` LIKE ?", [$column]);
        $cache[$key] = !empty($row);
    } catch (Exception $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
};

$has_projects_company = $table_has_column('projects', 'company_id');
$has_projects_status = $table_has_column('projects', 'status');
$has_projects_total_value = $table_has_column('projects', 'total_value');
$has_project_expenses_company = $table_has_column('project_expenses', 'company_id');
$has_project_expenses_status = $table_has_column('project_expenses', 'status');

$projects_where = " WHERE 1=1 ";
if ($has_projects_company) {
    $projects_where .= " AND (p.company_id = ? OR p.company_id IS NULL) ";
}

$projects_params = [];
if ($has_projects_company) $projects_params[] = $company_id;

$projects = db_fetch_all("
    SELECT
        p.id,
        p.name AS project_name,
        " . ($has_projects_total_value ? "COALESCE(p.total_value, 0)" : "0") . " AS budget_amount,
        " . ($has_projects_status ? "LOWER(COALESCE(p.status, 'active'))" : "'active'") . " AS status,
        COALESCE(sp.spent_amount, 0) AS spent_amount,
        COALESCE(sp.expense_count, 0) AS pvs_linked,
        c.name AS company_name
    FROM projects p
    LEFT JOIN companies c ON " . ($has_projects_company ? "p.company_id = c.id" : "1=0") . "
    LEFT JOIN (
        SELECT
            pe.project_id,
            SUM(pe.amount) AS spent_amount,
            COUNT(*) AS expense_count
        FROM project_expenses pe
        " . ($has_project_expenses_company ? "WHERE (pe.company_id = " . (int)$company_id . " OR pe.company_id IS NULL)" : "") . "
        GROUP BY pe.project_id
    ) sp ON sp.project_id = p.id
    {$projects_where}
    ORDER BY p.id DESC
", $projects_params) ?: [];

$active_projects = 0;
$completed_projects = 0;
$total_budget = 0.0;
$total_spent = 0.0;

foreach ($projects as &$p) {
    $p['remaining'] = (float)$p['budget_amount'] - (float)$p['spent_amount'];
    $st = strtolower((string)($p['status'] ?? 'active'));
    if (in_array($st, ['completed', 'closed'], true)) {
        $completed_projects++;
    } else {
        $active_projects++;
    }
    $total_budget += (float)$p['budget_amount'];
    $total_spent += (float)$p['spent_amount'];
}
unset($p);

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --pe-bg: #080c1a;
    --pe-panel: #1d243c;
    --pe-panel-2: #1a2035;
    --pe-line: #313a61;
    --pe-cyan: #08d0ef;
    --pe-soft: #8f9dc5;
    --pe-text: #eef3ff;
    --pe-title: #ffffff;
    --pe-sub: #9fb0da;
    --pe-btn-bg: #10c8df;
    --pe-btn-text: #04111c;
    --pe-card-border: rgba(255,255,255,.03);
    --pe-input-bg: #252d4a;
    --pe-input-border: #344271;
    --pe-input-text: #eef3ff;
    --pe-label: #9aa9d1;
    --pe-table-head: #8f9cc0;
    --pe-table-row: #eef2ff;
    --pe-blue: #1f95ff;
    --pe-green: #45c85a;
    --pe-gold: #ffc11b;
}

[data-bs-theme="light"] {
    --pe-bg: #f8f9fa;
    --pe-panel: #ffffff;
    --pe-panel-2: #f8f9fa;
    --pe-line: #e0e0e0;
    --pe-cyan: #0dcaf0;
    --pe-soft: #6c757d;
    --pe-text: #212529;
    --pe-title: #212529;
    --pe-sub: #6c757d;
    --pe-btn-bg: #0dcaf0;
    --pe-btn-text: #04111c;
    --pe-card-border: #dee2e6;
    --pe-input-bg: #ffffff;
    --pe-input-border: #ced4da;
    --pe-input-text: #212529;
    --pe-label: #6c757d;
    --pe-table-head: #495057;
    --pe-table-row: #212529;
    --pe-blue: #0d6efd;
    --pe-green: #198754;
    --pe-gold: #ffc107;
}

body { background: var(--pe-bg); color: var(--pe-text); }
.pe-screen { border:1px solid rgba(8,208,239,.55); border-radius:10px; background:rgba(8,208,239,.07); color:var(--pe-cyan); font-weight:700; padding:.65rem 1rem; }
.pe-title { color: var(--pe-title); font-weight:800; margin-bottom:.1rem; }
.pe-sub { color: var(--pe-sub); margin-bottom:0; }
.pe-btn {
    background: var(--pe-btn-bg); color: var(--pe-btn-text); border-radius:12px; border:0; font-weight:700; text-decoration:none;
    padding:.75rem 1.2rem; display:inline-flex; align-items:center; gap:.45rem;
}
.pe-cards { display:grid; grid-template-columns:repeat(4,1fr); gap:.9rem; }
.pe-card { border-radius:12px; padding:1rem 1.2rem; border:1px solid var(--pe-card-border); min-height:102px; display:flex; align-items:center; justify-content:center; text-align:center; }
.pe-card h6 { text-transform:uppercase; letter-spacing:2px; font-size:.72rem; margin:0 0 .35rem 0; font-weight:800; }
.pe-card .v { font-size:2.05rem; font-weight:900; line-height:1; }
.pe-card-1 { background:linear-gradient(90deg, rgba(10,51,97,.88), rgba(16,64,116,.78)); }
.pe-card-2 { background:linear-gradient(90deg, rgba(20,84,19,.88), rgba(34,102,33,.78)); }
.pe-card-3 { background:linear-gradient(90deg, rgba(4,92,102,.88), rgba(8,118,131,.78)); }
.pe-card-4 { background:linear-gradient(90deg, rgba(90,73,0,.82), rgba(118,95,0,.74)); }
.pe-card-1 h6, .pe-card-1 .v { color: var(--pe-blue); }
.pe-card-2 h6, .pe-card-2 .v { color: var(--pe-green); }
.pe-card-3 h6, .pe-card-3 .v { color: var(--pe-cyan); }
.pe-card-4 h6, .pe-card-4 .v { color: var(--pe-gold); }

.pe-panel { border-radius:12px; border:1px solid var(--pe-line); background:linear-gradient(180deg,var(--pe-panel),var(--pe-panel-2)); overflow:hidden; }
.pe-table { width:100%; border-collapse:collapse; min-width:1100px; }
.pe-table thead th { padding:1rem .95rem; font-size:.76rem; color:var(--pe-table-head); text-transform:uppercase; letter-spacing:1px; border-bottom:1px solid var(--pe-line); }
.pe-table tbody td { padding:.9rem .95rem; border-bottom:1px solid var(--pe-line); color:var(--pe-table-row); white-space:nowrap; }
.pe-status { display:inline-block; border-radius:6px; font-weight:700; font-size:.84rem; padding:.2rem .7rem; }
.st-active { color: var(--pe-blue); background: rgba(31,149,255,.18); }
.st-completed { color: var(--pe-green); background: rgba(69,200,90,.18); }
.pe-action { width:34px; height:34px; border-radius:8px; border:1px solid #334171; background:transparent; color:#a7b4d8; display:inline-flex; align-items:center; justify-content:center; text-decoration:none; }
.pe-note { border-top:1px solid rgba(255,193,44,.3); background:rgba(255,193,44,.1); color:var(--pe-gold); border-radius:8px; padding:.7rem 1rem; font-weight:600; }
@media (max-width: 1200px) { .pe-cards { grid-template-columns:1fr; } }
</style>

<div class="container-fluid px-4 py-4">
    <div class="pe-screen mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem;"></i> SCREEN: Project Expense - Summary Dashboard</div>

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="pe-title"><i class="fas fa-folder me-2" style="color:#ffcc55;"></i>Project Expense</h2>
            <p class="pe-sub">Track project costs and automatically pull related payment vouchers.</p>
        </div>
        <a href="<?= url('projects/create.php') ?>" class="pe-btn"><i class="fas fa-plus"></i> New Project</a>
    </div>

    <div class="pe-cards mb-4">
        <div class="pe-card pe-card-1"><div><h6>Active Projects</h6><div class="v"><?= (int)$active_projects ?></div></div></div>
        <div class="pe-card pe-card-2"><div><h6>Completed</h6><div class="v"><?= (int)$completed_projects ?></div></div></div>
        <div class="pe-card pe-card-3"><div><h6>Total Budget</h6><div class="v"><?= format_currency($total_budget) ?></div></div></div>
        <div class="pe-card pe-card-4"><div><h6>Total Spent</h6><div class="v"><?= format_currency($total_spent) ?></div></div></div>
    </div>

    <div class="pe-panel mb-3">
        <div class="d-flex align-items-center gap-2 px-4 py-3" style="border-bottom:1px solid var(--pe-line);">
            <i class="fas fa-file-alt text-light"></i>
            <h4 class="m-0 text-white fw-bold" style="font-size:2rem;">Projects</h4>
            <span class="badge rounded-pill" style="background:rgba(8,208,239,.95);color:#04111c;font-size:.86rem;"><?= count($projects) ?></span>
        </div>
        <div class="table-responsive">
            <table class="pe-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Company</th>
                        <th>Budget</th>
                        <th>Spent</th>
                        <th>Remaining</th>
                        <th>PVs Linked</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="8" class="text-center py-4" style="color:#7f8db4;">No projects found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p): ?>
                            <?php
                                $st = strtolower((string)($p['status'] ?? 'active'));
                                $is_completed = in_array($st, ['completed', 'closed'], true);
                                $status_class = $is_completed ? 'st-completed' : 'st-active';
                                $remaining = (float)$p['remaining'];
                            ?>
                            <tr>
                                <td style="font-weight:800;"><?= escape_html((string)$p['project_name']) ?></td>
                                <td><?= escape_html(trim((string)($p['company_name'] ?? 'MJR Group')) ?: 'MJR Group') ?></td>
                                <td><?= format_currency((float)$p['budget_amount']) ?></td>
                                <td><?= format_currency((float)$p['spent_amount']) ?></td>
                                <td style="font-weight:800;color:<?= $remaining > 0 ? 'var(--pe-green)' : ($remaining < 0 ? '#ff7b7b' : '#7f8db4') ?>;"><?= format_currency($remaining) ?></td>
                                <td><?= (int)$p['pvs_linked'] ?></td>
                                <td><span class="pe-status <?= $status_class ?>"><?= $is_completed ? 'Completed' : 'Active' ?></span></td>
                                <td><a class="pe-action" href="<?= url('projects/view.php?id=' . (int)$p['id']) ?>"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pe-note">
        <i class="fas fa-lightbulb me-2"></i>Payment vouchers posted to project expense accounts are automatically pulled into the project summary. Each project shows a reconciliation of all linked PVs.
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
