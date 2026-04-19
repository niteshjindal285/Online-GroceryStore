<?php
/**
 * Companies - Edit User Profile
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_users');

$company_id = (int) get_param('company_id');
$user_id = (int) get_param('id');

if (!$company_id || !$user_id) {
    redirect('index.php');
}

enforce_company_access($company_id);

$company = db_fetch("SELECT id, name FROM companies WHERE id = ?", [$company_id]);
if (!$company) {
    set_flash('Company not found.', 'error');
    redirect('index.php');
}

$user = db_fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
if (!$user) {
    set_flash('User not found.', 'error');
    redirect("manage_users.php?id=$company_id");
}

if (!can_manage_user_account($user) && $user_id !== current_user_id() && !is_super_admin()) {
    set_flash('You do not have permission to edit this user.', 'error');
    redirect("manage_users.php?id=$company_id");
}

$page_title = 'Edit User - MJR Group ERP';
$errors = [];

if (is_post()) {
    $csrf_token = post('csrf_token');

    if (!verify_csrf_token($csrf_token)) {
        set_flash('Invalid request token. Please try again.', 'error');
    } else {
        $username = trim(post('username', ''));
        $email = trim(post('email', ''));
        $password = (string) post('password', '');
        
        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < 3) {
            $errors['username'] = 'Username must be at least 3 characters.';
        }

        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $existing = db_fetch(
                "SELECT id, username, email FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1",
                [$username, $email, $user_id]
            );

            if ($existing) {
                if (strcasecmp($existing['username'], $username) === 0) {
                    $errors['username'] = 'That username is already in use.';
                }
                if (strcasecmp($existing['email'], $email) === 0) {
                    $errors['email'] = 'That email address is already in use.';
                }
            }
        }

        if (empty($errors)) {
            if ($password !== '') {
                $hash = hash_password($password);
                db_query("UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?", [$username, $email, $hash, $user_id]);
            } else {
                db_query("UPDATE users SET username = ?, email = ? WHERE id = ?", [$username, $email, $user_id]);
            }

            set_flash('User profile updated successfully.', 'success');
            redirect('manage_users.php?id=' . $company_id);
        } else {
            set_flash('Please fix the validation errors below.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container pb-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h3 class="card-title mb-0"><i class="fas fa-user-edit me-2"></i>Edit User Profile</h3>
                    <span class="badge bg-light text-dark px-3 py-2"><?= escape_html($company['name']) ?></span>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="username" class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username" value="<?= escape_html(post('username', $user['username'])) ?>" required maxlength="64">
                                <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['username']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="email" class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= escape_html(post('email', $user['email'])) ?>" required maxlength="120">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row border-top pt-4">
                            <div class="col-md-12 mb-4">
                                <label for="password" class="form-label fw-bold">New Password (Optional)</label>
                                <input type="password" autocomplete="new-password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" placeholder="Leave blank to keep current password">
                                <small class="text-muted">Enter a new password here if you wish to change it.</small>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="manage_users.php?id=<?= $company_id ?>" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary px-5 shadow-sm">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
