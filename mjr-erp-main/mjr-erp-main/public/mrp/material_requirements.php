<?php
/**
 * Material Requirements Planning
 * View and analyze material requirements from MRP runs
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Material Requirements - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// Get filter parameters
$mrp_run_id = $_GET['mrp_run'] ?? '';
$item_id = $_GET['item'] ?? '';

// Build query
$where = ["i.company_id = ?"];
$params = [$company_id];

if ($mrp_run_id) {
    $where[] = "po.mrp_run_id = ?";
    $params[] = $mrp_run_id;
}

if ($item_id) {
    $where[] = "po.item_id = ?";
    $params[] = $item_id;
}

$where_sql = implode(' AND ', $where);

// Get material requirements (planned orders)
$requirements = db_fetch_all("
    SELECT 
        po.*,
        i.code as item_code, i.name as item_name, i.unit_of_measure,
        mr.run_number, mr.run_date,
        CASE 
            WHEN po.order_type = 'purchase' AND po.converted_to_po_id IS NOT NULL THEN CONCAT('PO-', po.converted_to_po_id)
            WHEN po.order_type = 'production' AND po.converted_to_wo_id IS NOT NULL THEN CONCAT('WO-', po.converted_to_wo_id)
            ELSE 'N/A'
        END as converted_reference
    FROM planned_orders po
    JOIN inventory_items i ON po.item_id = i.id
    JOIN mrp_runs mr ON po.mrp_run_id = mr.id
    WHERE $where_sql
    ORDER BY po.planned_date ASC, i.code ASC
", $params);

// Calculate summary
$total_requirements = count($requirements);
$pending_requirements = 0;
$converted_requirements = 0;

foreach ($requirements as $req) {
    if ($req['status'] === 'planned') {
        $pending_requirements++;
    } elseif ($req['status'] === 'converted') {
        $converted_requirements++;
    }
}

// Get MRP runs for filter
$mrp_runs = db_fetch_all("SELECT DISTINCT mr.id, mr.run_number, mr.run_date FROM mrp_runs mr JOIN planned_orders po ON po.mrp_run_id = mr.id JOIN inventory_items i ON po.item_id = i.id WHERE i.company_id = ? ORDER BY mr.run_date DESC LIMIT 20", [$company_id]);

// Get items for filter
$items = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY code", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-clipboard-list me-2"></i>Material Requirements Planning</h2>
            <p class="lead">View and manage material requirements for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <a href="master_schedule.php" class="btn btn-secondary">
                <i class="fas fa-calendar-alt me-2"></i>Master Schedule
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Total Requirements</h6>
                    <h2><?= $total_requirements ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Pending</h6>
                    <h2><?= $pending_requirements ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success">
                <div class="card-body text-center">
                    <h6 class="text-uppercase mb-2">Converted</h6>
                    <h2><?= $converted_requirements ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">MRP Run</label>
                    <select name="mrp_run" class="form-select">
                        <option value="">All MRP Runs</option>
                        <?php foreach ($mrp_runs as $run): ?>
                        <option value="<?= $run['id'] ?>" <?= $mrp_run_id == $run['id'] ? 'selected' : '' ?>>
                            <?= escape_html($run['run_number']) ?> - <?= format_date($run['run_date']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Item</label>
                    <select name="item" class="form-select">
                        <option value="">All Items</option>
                        <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id'] ?>" <?= $item_id == $item['id'] ? 'selected' : '' ?>>
                            <?= escape_html($item['code'] . ' - ' . $item['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                    <a href="material_requirements.php" class="btn btn-outline-secondary" title="Clear filters">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Requirements Table -->
    <div class="card">
        <div class="card-body">
            <?php if (!empty($requirements)): ?>
            <div class="table-responsive">
                <table class="table table-dark table-hover" id="requirementsTable">
                    <thead>
                        <tr>
                            <th>MRP Run</th>
                            <th>Item</th>
                            <th>Order Type</th>
                            <th class="text-end">Quantity</th>
                            <th>Planned Date</th>
                            <th>Status</th>
                            <th>Converted To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requirements as $req): ?>
                        <tr>
                            <td>
                                <small><?= escape_html($req['run_number']) ?></small><br>
                                <small class="text-muted"><?= format_date($req['run_date']) ?></small>
                            </td>
                            <td>
                                <strong><?= escape_html($req['item_code']) ?></strong><br>
                                <small><?= escape_html($req['item_name']) ?></small>
                            </td>
                            <td>
                                <?php
                                $type_badges = [
                                    'purchase' => 'info',
                                    'production' => 'primary'
                                ];
                                $badge = $type_badges[$req['order_type']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge ?>">
                                    <?= ucfirst($req['order_type']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?= format_number($req['quantity']) ?> <?= escape_html($req['unit_of_measure']) ?>
                            </td>
                            <td><?= format_date($req['planned_date']) ?></td>
                            <td>
                                <?php
                                $status_badges = [
                                    'planned' => 'warning',
                                    'converted' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $status_badge = $status_badges[$req['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $status_badge ?>">
                                    <?= ucfirst($req['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (isset($req['converted_to_po_id']) && $req['converted_to_po_id']): ?>
                                    <span class="badge bg-success"><?= escape_html($req['converted_reference']) ?></span>
                                <?php elseif (isset($req['converted_to_wo_id']) && $req['converted_to_wo_id']): ?>
                                    <span class="badge bg-success"><?= escape_html($req['converted_reference']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($req['status'] === 'planned'): ?>
                                <div class="btn-group btn-group-sm">
                                    <a href="planned_orders.php?convert=<?= $req['id'] ?>" class="btn btn-outline-success" title="Convert to Order">
                                        <i class="fas fa-check"></i> Convert
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                <p class="text-muted">No material requirements found</p>
                <a href="master_schedule.php" class="btn btn-primary">
                    <i class="fas fa-play me-2"></i>Run MRP
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (!empty($requirements)): ?>
    $('#requirementsTable').DataTable({
        order: [[4, 'asc']],
        pageLength: 25,
        language: {
            search: "Search requirements:"
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
