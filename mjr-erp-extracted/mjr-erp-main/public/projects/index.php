<?php
/**
 * Projects Module – Index (List)
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/project_service.php';

require_login();
require_permission('view_projects');

$page_title     = 'Projects – MJR Group ERP';
$company_id     = active_company_id(1);

// Filters
$filter_status = get_param('status', '');
$search        = trim(get_param('q', ''));

// Build query
$where  = ['p.company_id = ?'];
$params = [$company_id];
if ($filter_status) { $where[] = 'p.status = ?'; $params[] = $filter_status; }
if ($search)        { $where[] = '(p.name LIKE ? OR c.name LIKE ? OR p.code LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$where_sql = 'WHERE ' . implode(' AND ', $where);

$projects = db_fetch_all("
    SELECT p.*,
           c.name  AS customer_name,
           u.username AS created_by_name
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.id
    LEFT JOIN users u     ON p.created_by  = u.id
    $where_sql
    ORDER BY p.created_at DESC
", $params);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-project-diagram me-2 text-primary"></i>Projects</h1>
            <p class="text-muted mb-0">Phase-wise project management &amp; invoicing</p>
        </div>
        <?php if (has_permission('manage_projects')): ?>
        <a href="<?= url('projects/create.php') ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Project
        </a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search project name, code or customer…" value="<?= escape_html($search) ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="active"    <?= $filter_status === 'active'    ? 'selected' : '' ?>>Active</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                    <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Project Cards / Table -->
    <?php if (empty($projects)): ?>
    <div class="text-center py-5">
        <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
        <h5 class="text-muted">No projects found</h5>
        <?php if (has_permission('manage_projects')): ?>
        <a href="<?= url('projects/create.php') ?>" class="btn btn-primary mt-2"><i class="fas fa-plus me-2"></i>Create First Project</a>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- Summary Stats -->
    <?php
    $total_val  = array_sum(array_column($projects, 'total_value'));
    $active_cnt = count(array_filter($projects, fn($p) => $p['status'] === 'active'));
    ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-4">
            <div class="card border-0 bg-primary text-white shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75">Total Projects</small><div class="fs-4 fw-bold"><?= count($projects) ?></div></div>
                        <i class="fas fa-project-diagram fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 bg-success text-white shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small class="opacity-75">Active</small><div class="fs-4 fw-bold"><?= $active_cnt ?></div></div>
                        <i class="fas fa-play-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 bg-warning text-dark shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div><small>Total Project Value</small><div class="fs-4 fw-bold"><?= CURRENCY_SYMBOL ?> <?= number_format($total_val, 0) ?></div></div>
                        <i class="fas fa-dollar-sign fa-2x opacity-40"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="projectsTable">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3">Code</th>
                            <th>Project Name</th>
                            <th>Customer</th>
                            <th class="text-end pe-3">Contract Value</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p): ?>
                        <?php $status_color = ['active'=>'success','completed'=>'primary','cancelled'=>'danger'][$p['status']] ?? 'secondary'; ?>
                        <tr>
                            <td class="ps-3"><code class="text-primary"><?= escape_html($p['code'] ?? 'N/A') ?></code></td>
                            <td>
                                <a href="<?= url('projects/view.php', ['id' => $p['id']]) ?>" class="fw-semibold text-decoration-none">
                                    <?= escape_html($p['name']) ?>
                                </a>
                                <?php if ($p['created_at']): ?>
                                <br><small class="text-muted"><i class="fas fa-calendar me-1"></i><?= format_date($p['created_at']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= escape_html($p['customer_name'] ?? '—') ?></td>
                            <td class="text-end pe-3 fw-semibold"><?= CURRENCY_SYMBOL ?> <?= number_format($p['total_value'], 2) ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= $status_color ?>"><?= ucfirst($p['status']) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('projects/view.php', ['id' => $p['id']]) ?>" class="btn btn-outline-info" title="View"><i class="fas fa-eye"></i></a>
                                    <?php if (has_permission('manage_projects')): ?>
                                    <a href="<?= url('projects/edit.php', ['id' => $p['id']]) ?>" class="btn btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
include __DIR__ . '/../../templates/footer.php';
?>
<script>
$(document).ready(function(){ $('#projectsTable').DataTable({order:[[0,'desc']],pageLength:25}); });
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>
