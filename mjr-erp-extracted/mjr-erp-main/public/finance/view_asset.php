<?php
/**
 * View Asset Details
 * Shows depreciation history and asset info
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('fixed_assets');

// Handle status updates
if (is_post() && post('action') === 'update_status' && verify_csrf_token(post('csrf_token'))) {
    $id = intval(post('id'));
    $new_status = post('new_status');
    $asset_for_approval = db_fetch("SELECT * FROM fixed_assets WHERE id = ?", [$id]);
    
    if ($asset_for_approval && ($new_status === 'approved' || $new_status === 'rejected')) {
        $is_reject = ($new_status === 'rejected');
        $approval = finance_process_approval_action($asset_for_approval, current_user_id(), $is_reject);
        
        if (!$approval['ok']) {
            set_flash($approval['message'], 'error');
            redirect('view_asset.php?id=' . $id);
        }

        $set_parts = [];
        $params = [];
        foreach ($approval['fields'] as $field => $value) {
            $set_parts[] = "{$field} = ?";
            $params[] = $value;
        }
        
        if ($approval['approved']) {
            $set_parts[] = "status = 'active'";
        } elseif ($approval['rejected'] ?? false) {
            $set_parts[] = "status = 'rejected'";
        }
        
        $params[] = $id;
        if (!empty($set_parts)) {
            db_query("UPDATE fixed_assets SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
        }
        set_flash($approval['message'], 'success');
    }
    redirect('view_asset.php?id=' . $id);
}

$id = intval($_GET['id'] ?? 0);
$asset = db_fetch("
    SELECT fa.*, c.name as company_name,
           m.username as manager_username, m.full_name as manager_full_name,
           ad.username as admin_username, ad.full_name as admin_full_name
    FROM fixed_assets fa 
    LEFT JOIN companies c ON fa.company_id = c.id 
    LEFT JOIN users m ON fa.manager_id = m.id
    LEFT JOIN users ad ON fa.admin_id = ad.id
    WHERE fa.id = ?
", [$id]);

if (!$asset) {
    set_flash('Asset not found.', 'error');
    redirect('fixed_assets.php');
}

$depreciations = db_fetch_all("SELECT * FROM depreciation_entries WHERE asset_id = ? ORDER BY depreciation_date DESC", [$id]);

$page_title = $asset['asset_name'] . ' - Fixed Assets';

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --va-bg: #1a1a24;
        --va-panel: #222230;
        --va-text: #b0b0c0;
        --va-text-white: #ffffff;
        --va-border: rgba(255,255,255,0.05);
        --va-label: #8e8e9e;
        --va-table-head: #1a1a24;
    }

    [data-bs-theme="light"] {
        --va-bg: #f8f9fa;
        --va-panel: #ffffff;
        --va-text: #495057;
        --va-text-white: #212529;
        --va-border: #dee2e6;
        --va-label: #6c757d;
        --va-table-head: #f8f9fa;
    }

    body { background-color: var(--va-bg); color: var(--va-text); }
    .card { background-color: var(--va-panel); border-color: var(--va-border); border-radius: 12px; }
    .card-header { background-color: var(--va-panel)!important; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--va-border); }
    .card-body { padding: 1.5rem; }
    
    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); --bs-table-border-color: var(--va-border); }
    .table-dark th { color: var(--va-label); font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--va-border); padding: 1.25rem 1rem; }
    .table-dark td { padding: 1rem; border-bottom: 1px solid var(--va-border); color: var(--va-text-white); vertical-align: middle; }
    
    .btn-clear { background-color: rgba(255, 255, 255, 0.05); color: var(--va-text); border: 1px solid var(--va-border); transition: all 0.2s ease; border-radius: 8px; padding: 0.5rem 1rem; text-decoration: none; }
    .btn-clear:hover { background-color: rgba(255, 255, 255, 0.1); color: var(--va-text-white); }
</style>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h2 class="mb-1 fw-bold" style="color: var(--va-text-white);"><i class="fas fa-eye me-2" style="color: #0dcaf0;"></i> View Asset</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= url('finance/index.php') ?>" style="color: #8e8e9e; text-decoration: none;">Finance</a></li>
                    <li class="breadcrumb-item"><a href="<?= url('finance/fixed_assets.php') ?>" style="color: #8e8e9e; text-decoration: none;">Fixed Assets</a></li>
                    <li class="breadcrumb-item active" style="color: #0dcaf0;" aria-current="page"><?= escape_html($asset['asset_name']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="d-flex gap-2">
            <?php 
            $status = $asset['status'] ?? 'pending';
            $is_unapproved = ($status === 'pending' || $status === 'draft');
            $user_id = current_user_id();
            $manager_id = (int)($asset['manager_id'] ?? 0);
            $admin_id = (int)($asset['admin_id'] ?? 0);
            $approval_type = $asset['approval_type'] ?? 'manager';
            $manager_done = !empty($asset['manager_approved_at']);
            $admin_done = !empty($asset['admin_approved_at']);

            $can_approve_as_manager = ($approval_type !== 'admin' && $user_id === $manager_id && !$manager_done);
            $can_approve_as_admin = ($approval_type !== 'manager' && $user_id === $admin_id && !$admin_done);
            
            if ($is_unapproved && ($can_approve_as_manager || $can_approve_as_admin)): 
            ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?= (int)$asset['id'] ?>">
                <input type="hidden" name="new_status" value="approved">
                <button type="submit" class="btn btn-success px-4 py-2 rounded-pill shadow-sm fw-bold">
                    <i class="fas fa-check-circle me-2"></i>Approve Asset
                </button>
            </form>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?= (int)$asset['id'] ?>">
                <input type="hidden" name="new_status" value="rejected">
                <button type="submit" class="btn btn-danger px-4 py-2 rounded-pill shadow-sm fw-bold" onclick="return confirm('Reject this asset registration?')">
                    <i class="fas fa-times-circle me-2"></i>Reject
                </button>
            </form>
            <?php endif; ?>
            <a href="fixed_assets.php" class="btn btn-clear text-decoration-none">
                <i class="fas fa-arrow-left me-2"></i>Back to Assets
            </a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm mb-4 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: var(--va-text-white);"><i class="fas fa-info-circle me-2" style="color: #8e8e9e;"></i> Asset Details</h5>
                    <?php
                        $status = $asset['status'];
                        $status_color = $status == 'active' ? '#3cc553' : ($status == 'rejected' ? '#ff5252' : '#ff922b');
                        $status_bg = $status == 'active' ? 'rgba(60, 197, 83, 0.15)' : ($status == 'rejected' ? 'rgba(255, 82, 82, 0.15)' : 'rgba(255, 146, 43, 0.15)');
                    ?>
                    <span class="badge" style="background-color: <?= $status_bg ?>; color: <?= $status_color ?>; border: 1px solid <?= $status_color ?>44; padding: 6px 12px;">
                        <?= strtoupper($status) ?>
                    </span>
                </div>
                <div class="card-body">
                    <table class="table table-dark table-borderless mb-0">
                        <tr>
                            <td style="color: #8e8e9e; width: 140px; padding: 0.5rem 0;">Asset Code:</td>
                            <td style="padding: 0.5rem 0;"><strong style="color: #0dcaf0;"><?= escape_html($asset['asset_code']) ?></strong></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Asset Name:</td>
                            <td style="padding: 0.5rem 0;"><?= escape_html($asset['asset_name']) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Company:</td>
                            <td style="padding: 0.5rem 0;"><?= escape_html($asset['company_name'] ?? 'Main') ?></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Location:</td>
                            <td style="padding: 0.5rem 0;"><?= escape_html($asset['location'] ?: '-') ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><hr style="border-color: rgba(255,255,255,0.05); margin: 0.5rem 0;"></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Purchase Date:</td>
                            <td style="padding: 0.5rem 0;"><span style="color: #b0b0c0;"><?= format_date($asset['purchase_date']) ?></span></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Purchase Price:</td>
                            <td style="padding: 0.5rem 0;" class="font-monospace"><?= format_currency($asset['purchase_price']) ?></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Salvage Value:</td>
                            <td style="padding: 0.5rem 0;" class="font-monospace"><?= format_currency($asset['salvage_value']) ?></td>
                        </tr>
                        <tr>
                            <td colspan="2"><hr style="border-color: rgba(255,255,255,0.05); margin: 0.5rem 0;"></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Useful Life:</td>
                            <td style="padding: 0.5rem 0;"><?= $asset['useful_life_years'] ?> Years</td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Method:</td>
                            <td style="padding: 0.5rem 0;">
                                <span class="badge" style="background: rgba(144, 97, 249, 0.15); color: #9061f9; border: 1px solid rgba(144, 97, 249, 0.3);">
                                    <?= ucwords(str_replace('_', ' ', $asset['depreciation_method'])) ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Approval Type:</td>
                            <td style="padding: 0.5rem 0;"><?= escape_html($asset['approval_type'] ?? 'manager') ?></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Manager:</td>
                            <td style="padding: 0.5rem 0;"><?= !empty($asset['manager_id']) ? escape_html(trim((string)($asset['manager_full_name'] ?? '')) ?: (string)($asset['manager_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></td>
                        </tr>
                        <tr>
                            <td style="color: #8e8e9e; padding: 0.5rem 0;">Admin:</td>
                            <td style="padding: 0.5rem 0;"><?= !empty($asset['admin_id']) ? escape_html(trim((string)($asset['admin_full_name'] ?? '')) ?: (string)($asset['admin_username'] ?? '')) : '<span class="text-muted fst-italic">Not Assigned</span>' ?></td>
                        </tr>
                    </table>

                    <div class="row text-center mt-4 pt-4" style="border-top: 1px dashed rgba(255,255,255,0.1);">
                        <div class="col-6" style="border-right: 1px solid rgba(255,255,255,0.05);">
                            <div style="font-size: 0.85rem; color: #8e8e9e; text-transform: uppercase;">Accum. Depr.</div>
                            <div class="font-monospace mt-1" style="color: #ff922b; font-size: 1.25rem;"><?= format_currency($asset['accumulated_depreciation']) ?></div>
                        </div>
                        <div class="col-6">
                            <div style="font-size: 0.85rem; color: #8e8e9e; text-transform: uppercase;">Net Book Value</div>
                            <div class="font-monospace mt-1 fw-bold" style="color: #3cc553; font-size: 1.5rem;"><?= format_currency($asset['net_book_value']) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100" style="overflow: hidden;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: var(--va-text-white);"><i class="fas fa-history me-2" style="color: #8e8e9e;"></i> Depreciation History</h5>
                    <span class="badge" style="background: rgba(255,255,255,0.05); color: #8e8e9e;"><?= count($depreciations) ?> Records</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead style="background-color: var(--va-table-head);">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Ref #</th>
                                    <th class="text-end pe-4">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($depreciations)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5">
                                            <i class="fas fa-chart-line fa-3x text-muted mb-3 opacity-25"></i>
                                            <p class="mb-1 fs-5" style="color: var(--va-text-white);">No Records</p>
                                            <p class="text-muted">This asset has not been depreciated yet.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($depreciations as $dep): ?>
                                    <tr>
                                        <td class="ps-4"><span style="color: #b0b0c0;"><?= format_date($dep['depreciation_date']) ?></span></td>
                                        <td>
                                            <a href="<?= url('finance/view_journal_entry.php?id=' . $dep['journal_entry_id']) ?>" style="color: #0dcaf0; text-decoration: none;">
                                                <i class="fas fa-link fa-sm me-1"></i> JRN#<?= $dep['journal_entry_id'] ?>
                                            </a>
                                        </td>
                                        <td class="text-end pe-4 font-monospace" style="color: #ff5252;">
                                            -<?= format_currency($dep['depreciation_amount']) ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
