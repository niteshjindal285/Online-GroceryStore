<?php
/**
 * Tax Classes - Manage Tax Configurations
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
ensure_finance_approval_columns('tax_configurations');

$page_title = 'Tax Classes - MJR Group ERP';

if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $action = post('action');
    $id = (int)post('id');
    if ($action === 'approve_tax' && $id > 0) {
        $row = db_fetch("SELECT * FROM tax_configurations WHERE id = ?", [$id]);
        if ($row) {
            $approval = finance_process_approval_action($row, current_user_id());
            if ($approval['ok']) {
                $set_parts = [];
                $params = [];
                foreach ($approval['fields'] as $field => $value) {
                    $set_parts[] = "{$field} = ?";
                    $params[] = $value;
                }
                if ($approval['approved']) {
                    $set_parts[] = "approval_status = 'approved'";
                    $set_parts[] = "is_active = 1";
                }
                if (!empty($set_parts)) {
                    $params[] = $id;
                    db_query("UPDATE tax_configurations SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
                }
                set_flash($approval['message'], 'success');
            } else {
                set_flash($approval['message'], 'error');
            }
        }
        redirect(url('finance/tax_classes.php'));
    }
}

// Get all active tax classes
try {
    $tax_classes = db_fetch_all("
        SELECT 
            tc.*,
            a.code as account_code,
            a.name as account_name
        FROM tax_configurations tc
        LEFT JOIN accounts a ON tc.tax_account_id = a.id
        WHERE 1=1
        ORDER BY tc.tax_name
    ");
} catch (Exception $e) {
    log_error("Error loading tax classes: " . $e->getMessage());
    $tax_classes = [];
    set_flash('Error loading tax classes: ' . $e->getMessage(), 'error');
}

include __DIR__ . '/../../templates/header.php';
?>


<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 text-white">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-percent me-2 text-primary"></i>Tax Classes</h1>
            <p class="text-muted mb-0">Manage tax configurations for sales and purchases</p>
        </div>
        <?php if (has_permission('manage_finance')): ?>
        <div>
            <a href="<?= url('finance/add_tax_class.php') ?>" class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold">
                <i class="fas fa-plus me-2"></i>New Tax Class
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Configured Taxes Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Tax Configurations</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($tax_classes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-percent fa-3x text-muted mb-3 opacity-25"></i>
                    <p class="text-muted">No tax classes found</p>
                    <a href="<?= url('finance/add_tax_class.php') ?>" class="btn px-4 rounded-pill" style="background: rgba(255,255,255,0.05); color: #fff; border: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-plus me-2"></i>Add Your First Tax Class
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="taxClassesTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Tax Name</th>
                                <th>Tax Code</th>
                                <th>Type</th>
                                <th class="text-end">Rate (%)</th>
                                <th>Tax Account</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tax_classes as $tax): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-white"><?= escape_html($tax['tax_name']) ?></td>
                                    <td><span style="color: #0dcaf0; font-family: monospace;"><?= escape_html($tax['tax_code']) ?></span></td>
                                    <td>
                                        <?php 
                                        $type_opts = [
                                            'sales_tax' => ['color' => '#3cc553', 'bg' => 'rgba(60, 197, 83, 0.1)', 'border' => '1px solid rgba(60, 197, 83, 0.3)'],
                                            'purchase_tax' => ['color' => '#9061f9', 'bg' => 'rgba(144, 97, 249, 0.1)', 'border' => '1px solid rgba(144, 97, 249, 0.3)'],
                                        ];
                                        $style = $type_opts[$tax['tax_type']] ?? ['color' => '#ffc107', 'bg' => 'rgba(255, 193, 7, 0.1)', 'border' => '1px solid rgba(255, 193, 7, 0.3)'];
                                        ?>
                                        <span class="badge" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>; border: <?= $style['border'] ?>;">
                                            <?= ucwords(str_replace('_', ' ', escape_html($tax['tax_type']))) ?>
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace" style="color: #fff;"><?= number_format(floatval($tax['tax_rate']) * 100, 2) ?>%</td>
                                    <td>
                                        <?php if ($tax['account_code']): ?>
                                            <span style="color: #8e8e9e; font-size: 0.9rem;"><?= escape_html($tax['account_code']) ?> - <span class="text-white"><?= escape_html($tax['account_name']) ?></span></span>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">No account</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($tax['approval_status'] ?? 'approved') === 'pending'): ?>
                                            <span class="badge" style="background: rgba(255, 193, 7, 0.12); color: #ffc107;">Pending</span>
                                        <?php elseif (($tax['approval_status'] ?? '') === 'rejected'): ?>
                                            <span class="badge" style="background: rgba(255,82,82,0.12); color: #ff5252;">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: rgba(60, 197, 83, 0.12); color: #3cc553;">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color: #8e8e9e;"><?= format_date($tax['created_at'], DISPLAY_DATE_FORMAT) ?></td>
                                    <td class="text-end pe-4">
                                        <div class="btn-group">
                                            <?php if (has_permission('manage_finance')): ?>
                                            <?php if (($tax['approval_status'] ?? 'approved') !== 'approved'): ?>
                                            <form method="POST" class="m-0 p-0">
                                                <input type="hidden" name="id" value="<?= (int)$tax['id'] ?>">
                                                <input type="hidden" name="action" value="approve_tax">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <button type="submit" class="btn btn-sm btn-icon" style="background: rgba(60,197,83,0.1); color: #3cc553; border: 1px solid rgba(60,197,83,0.2);" title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            <a href="<?= url('finance/edit_tax_class.php?id=' . $tax['id']) ?>" 
                                               class="btn btn-sm btn-icon" style="background: rgba(13,202,240,0.1); color: #0dcaf0; border: 1px solid rgba(13, 202, 240, 0.2);" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="<?= url('finance/delete_tax_class.php') ?>" 
                                                  class="m-0 p-0"
                                                  onsubmit="return confirm('Are you sure you want to delete this tax class?')">
                                                <input type="hidden" name="id" value="<?= $tax['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <button type="submit" class="btn btn-sm btn-icon" style="background: rgba(255,82,82,0.1); color: #ff5252; border: 1px solid rgba(255, 82, 82, 0.2);" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                                <span class="text-muted small">No Actions</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#taxClassesTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        language: {
            search: "Search tax classes:",
            lengthMenu: "Show _MENU_ tax classes per page"
        }
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
