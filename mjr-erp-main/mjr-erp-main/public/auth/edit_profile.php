<?php
/**
 * Edit Profile Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Profile - MJR Group ERP';

// Get current user data
$user = db_fetch("
    SELECT u.*, c.name as company_name 
    FROM users u 
    LEFT JOIN companies c ON u.company_id = c.id 
    WHERE u.id = ?
", [$_SESSION['user_id']]);

if (!$user) {
    set_flash('User not found.', 'error');
    redirect('index.php');
}

// Handle form submission
if (is_post()) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $email = trim(post('email'));
            $full_name = trim(post('full_name'));
            $phone = trim(post('phone'));
            
            // Validate email
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Please enter a valid email address.");
            }
            
            // Check if email is already taken by another user
            $existing = db_fetch("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $_SESSION['user_id']]);
            if ($existing) {
                throw new Exception("This email is already in use by another account.");
            }
            
            // Update user
            db_query("
                UPDATE users 
                SET email = ?, full_name = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ", [$email, $full_name, $phone, $_SESSION['user_id']]);
            
            // Update session
            $_SESSION['email'] = $email;
            
            set_flash('Profile updated successfully!', 'success');
            redirect('edit_profile.php');
            
        } catch (Exception $e) {
            set_flash($e->getMessage(), 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= escape_html($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="<?= escape_html($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?= escape_html($user['full_name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" value="<?= escape_html($user['phone'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst(escape_html($user['role'])) ?>" disabled>
                        </div>
                        
                        <?php if ($user['company_name']): ?>
                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <input type="text" class="form-control" value="<?= escape_html($user['company_name']) ?>" disabled>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" value="<?= format_date($user['created_at']) ?>" disabled>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="<?= url('index.php') ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-body">
                    <h6>Need to change your password?</h6>
                    <a href="change_password.php" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-key me-2"></i>Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
