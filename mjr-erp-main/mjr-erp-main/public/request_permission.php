<?php
/**
 * Request System Permissions
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

// Admins don't need to request anything
if (is_admin()) {
    set_flash('Administrators already have all permissions.', 'info');
    redirect(url('index.php'));
    exit;
}

$page_title = 'Request Permissions - MJR Group ERP';
$user_id = current_user_id();

// Handle Request Submission
if (is_post() && isset($_POST['request_permission'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        $perm_id = intval(post('permission_id'));

        try {
            // Validate permission exists
            $perm_name = db_fetch("SELECT name FROM permissions WHERE id = ?", [$perm_id])['name'] ?? '';
            if (!$perm_name) {
                throw new Exception("Invalid permission selected.");
            }

            // Check if already has permission (via role or user_perms)
            if (has_permission($perm_name)) {
                throw new Exception("You already have this permission.");
            }

            // Check if there's already a pending request
            $pending = db_fetch(
                "SELECT id FROM permission_requests WHERE user_id = ? AND permission_id = ? AND status = 'pending'",
                [$user_id, $perm_id]
            );
            if ($pending) {
                throw new Exception("You already have a pending request for this permission.");
            }

            // Check if already approved (guard against direct POST bypass)
            $approved = db_fetch(
                "SELECT id FROM permission_requests WHERE user_id = ? AND permission_id = ? AND status = 'approved'",
                [$user_id, $perm_id]
            );
            if ($approved) {
                throw new Exception("This permission has already been approved for your account.");
            }

            // Insert new request
            db_insert(
                "INSERT INTO permission_requests (user_id, permission_id, status, request_date) VALUES (?, ?, 'pending', NOW())",
                [$user_id, $perm_id]
            );

            set_flash('Your permission request has been sent to the Administrator.', 'success');
            redirect('request_permission.php');
            exit;

        } catch (Exception $e) {
            set_flash($e->getMessage(), 'error');
        }
    } else {
        set_flash('Invalid CSRF token. Please try again.', 'error');
    }
}

$user_role = $_SESSION['role'] ?? 'user';

// Get permissions the user can still request:
// - Not already granted via role
// - Not already granted via user_permissions
// - Not currently pending OR already approved
// - Rejected ones ARE shown again so the user can re-request
$available_perms = db_fetch_all("
    SELECT p.* FROM permissions p
    WHERE p.id NOT IN (
        SELECT rp.permission_id FROM role_permissions rp WHERE rp.role = ?
    )
    AND p.id NOT IN (
        SELECT up.permission_id FROM user_permissions up WHERE up.user_id = ?
    )
    AND p.id NOT IN (
        SELECT pr.permission_id FROM permission_requests pr
        WHERE pr.user_id = ? AND pr.status IN ('pending', 'approved')
    )
    ORDER BY p.name
", [$user_role, $user_id, $user_id]);

// Get user's full request history
$my_requests = db_fetch_all("
    SELECT pr.*, p.name AS perm_name, p.description AS perm_desc
    FROM permission_requests pr
    JOIN permissions p ON pr.permission_id = p.id
    WHERE pr.user_id = ?
    ORDER BY pr.request_date DESC
", [$user_id]);

include __DIR__ . '/../templates/header.php';
?>

<div class="container mt-4">
    <div class="row">

        <!-- ── Request Form ── -->
        <div class="col-md-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Request</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($available_perms)): ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            You have all available permissions, or all remaining ones are already pending / approved.
                        </p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                            <div class="mb-3">
                                <label class="form-label fw-bold">Select Permission Needed</label>
                                <select name="permission_id" class="form-select" required>
                                    <option value="">-- Choose Permission --</option>
                                    <?php foreach ($available_perms as $p): ?>
                                        <option value="<?= intval($p['id']) ?>">
                                            <?= escape_html($p['name']) ?> — <?= escape_html($p['description']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <button type="submit" name="request_permission" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-1"></i> Submit Request to Admin
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Request History ── -->
        <div class="col-md-7">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>My Request History</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Permission</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Admin Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_requests)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox me-1"></i> No requests found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($my_requests as $r): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-primary"><?= escape_html($r['perm_name']) ?></div>
                                                <small class="text-muted"><?= escape_html($r['perm_desc']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($r['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        <i class="fas fa-clock me-1"></i> Pending
                                                    </span>
                                                <?php elseif ($r['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i> Approved
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i> Rejected
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y', strtotime($r['request_date'])) ?></small>
                                            </td>
                                            <td>
                                                <small class="fst-italic">
                                                    <?= escape_html($r['admin_notes'] ?? '—') ?>
                                                </small>
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

<?php include __DIR__ . '/../templates/footer.php'; ?>