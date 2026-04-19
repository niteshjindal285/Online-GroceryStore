<?php
/**
 * Change Password Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Change Password - MJR Group ERP';

// Handle form submission
if (is_post()) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $current_password = post('current_password');
            $new_password = post('new_password');
            $confirm_password = post('confirm_password');
            
            // Validate inputs
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All fields are required.");
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match.");
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception("New password must be at least 6 characters long.");
            }
            
            // Get current user
            $user = db_fetch("SELECT password_hash FROM users WHERE id = ?", [$_SESSION['user_id']]);
            
            if (!$user) {
                throw new Exception("User not found.");
            }
            
            // Verify current password
            if (!verify_password($current_password, $user['password_hash'])) {
                throw new Exception("Current password is incorrect.");
            }
            
            // Update password
            $new_hash = hash_password($new_password);
            db_query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [$new_hash, $_SESSION['user_id']]);
            
            set_flash('Password changed successfully!', 'success');
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
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0"><i class="fas fa-key me-2"></i>Change Password</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="new_password" id="new_password" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="edit_profile.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Profile
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="alert alert-info mt-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Password Tips:</strong>
                <ul class="mb-0 mt-2">
                    <li>Use at least 6 characters</li>
                    <li>Include uppercase and lowercase letters</li>
                    <li>Add numbers and special characters for extra security</li>
                    <li>Don't reuse passwords from other accounts</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPass = document.getElementById('new_password').value;
    const confirmPass = this.value;
    
    if (newPass !== confirmPass) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
