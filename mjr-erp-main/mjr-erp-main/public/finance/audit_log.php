<?php
/**
 * Finance Audit Log
 * View all tracked finance actions
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Finance Audit Log - MJR Group ERP';

// Create table if not exists (auto-setup on first visit)
try {
    db_query("
        CREATE TABLE IF NOT EXISTS finance_audit_log (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            user_id       INT NOT NULL,
            username      VARCHAR(100) NOT NULL,
            action        VARCHAR(100) NOT NULL,
            table_name    VARCHAR(100),
            record_id     INT,
            details       TEXT,
            created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        )
    ");
} catch (Exception $e) {
    // Table may already exist – ignore
}

// Filters
$filter_action = $_GET['action'] ?? '';
$filter_from   = $_GET['date_from'] ?? date('Y-m-01');
$filter_to     = $_GET['date_to']   ?? date('Y-m-d');

$where  = ["created_at >= ?", "created_at <= ?"];
$params[] = to_db_date($filter_from) . ' 00:00:00';
$params[] = to_db_date($filter_to) . ' 23:59:59';

if ($filter_action) {
    $where[]  = "action = ?";
    $params[] = $filter_action;
}

$logs = db_fetch_all(
    "SELECT * FROM finance_audit_log WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT 500",
    $params
);

// Distinct actions for filter dropdown
$available_actions = db_fetch_all("SELECT DISTINCT action FROM finance_audit_log ORDER BY action");

include __DIR__ . '/../../templates/header.php';
?>


<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 text-white">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-history me-2 text-primary"></i>Audit Log</h1>
            <p class="text-muted mb-0">Track all finance actions &mdash; who did what and when</p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-outline-secondary px-4 py-2 rounded-pill no-print shadow-sm fw-bold">
                <i class="fas fa-print me-2"></i>Print Log
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 border-0 shadow-sm no-print">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end text-muted">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Action Type</label>
                    <select name="action" class="form-select form-select-sm border-0">
                        <option value="">All Actions</option>
                        <?php foreach ($available_actions as $a): ?>
                        <option value="<?= escape_html($a['action']) ?>" <?= $filter_action === $a['action'] ? 'selected' : '' ?>>
                            <?= escape_html($a['action']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" name="date_from" class="form-control form-control-sm border-0" value="<?= !empty($filter_from) ? $filter_from : '' ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" name="date_to" class="form-control form-control-sm border-0" value="<?= !empty($filter_to) ? $filter_to : '' ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">Apply Filter</button>
                    <a href="audit_log.php" class="btn btn-sm btn-outline-secondary px-3 ms-1">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Log Table -->
    <div class="card border-0 shadow-sm mb-5" style="overflow: hidden;">
        <div class="card-header d-flex justify-content-between align-items-center" style="background: rgba(34, 34, 48, 0.6); padding: 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.05);">
            <h5 class="mb-0 text-white"><i class="fas fa-list-alt me-2" style="color: #8e8e9e;"></i> Activity Log</h5>
            <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e; font-size: 0.9rem;"><?= count($logs) ?> entries</span>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($logs)): ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="auditTable">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target Record</th>
                            <th class="pe-4">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="ps-4 font-monospace small text-muted">#<?= $log['id'] ?></td>
                            <td class="small"><?= format_datetime($log['created_at']) ?></td>
                            <td><strong class="text-primary"><?= escape_html($log['username']) ?></strong></td>
                            <td><span class="badge bg-secondary opacity-75 small"><?= escape_html($log['action']) ?></span></td>
                            <td class="small">
                                <span class="text-muted"><?= escape_html($log['table_name'] ?? '-') ?></span>
                                <span class="fw-bold ms-1"><?= $log['record_id'] ? '#' . $log['record_id'] : '' ?></span>
                            </td>
                            <td class="pe-4 small text-muted text-truncate" style="max-width: 350px;" title="<?= escape_html($log['details'] ?? '') ?>">
                                <?= escape_html($log['details'] ?? '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <script>
            $(document).ready(function() {
                $('#auditTable').DataTable({ 
                    order: [[0, 'desc']], 
                    pageLength: 50,
                    language: {
                        search: "",
                        searchPlaceholder: "Search logs...",
                        lengthMenu: "Show _MENU_"
                    },
                    dom: "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-end'f>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row px-4 py-3 align-items-center'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-end'p>>"
                });
                
                $('.dataTables_filter input').css('width', '250px');
            });
            </script>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-history fa-3x text-muted mb-3 opacity-25"></i>
                <p class="text-muted">No audit log entries found for the selected criteria.</p>
                <small class="text-muted">Actions will appear here as users interact with the Finance module.</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    nav, .navbar, .sidebar, .btn, form, .no-print, .dataTables_wrapper .row:first-child, .dataTables_wrapper .row:last-child { display: none !important; }
    body { background: #fff !important; color: #000 !important; font-size: 10pt; }
    .card { border: none !important; margin-bottom: 20px; box-shadow: none !important; }
    table { color: #000 !important; border-collapse: collapse !important; width: 100%; }
    th, td { border: 1px solid #ccc !important; padding: 6px 12px !important; color: #000 !important; text-align: left; }
    .badge { border: 1px solid #ccc; color: #000 !important; background: none !important; }
    .card-header { display: none !important; }
    .print-header { display: block !important; text-align: center; margin-bottom: 20px; }
}
.print-header { display: none; }
</style>

<div class="print-header">
    <h2 style="margin-bottom: 5px;"><strong>MJR Group ERP &mdash; Finance Audit Log</strong></h2>
    <p>Filtered Period: <?= !empty($filter_from) ? $filter_from : 'Start' ?> to <?= !empty($filter_to) ? $filter_to : 'Now' ?></p>
    <hr style="margin-bottom: 20px;">
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
