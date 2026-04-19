<?php
/**
 * Companies - Create and Assign User
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_users');

$company_id = (int) get_param('company_id');
if (!$company_id) {
    redirect('index.php');
}

enforce_company_access($company_id, 'index.php');

$allowed_roles = get_manageable_roles();
if (empty($allowed_roles)) {
    set_flash('You do not have permission to create additional users.', 'error');
    redirect('manage_users.php?id=' . $company_id);
}

$requested_role = normalize_role_name(get_param('role', 'user'));
$default_role = in_array($requested_role, $allowed_roles, true) ? $requested_role : $allowed_roles[0];

$company = db_fetch("SELECT id, name FROM companies WHERE id = ?", [$company_id]);
if (!$company) {
    set_flash('Company not found.', 'error');
    redirect('index.php');
}

$page_title = 'Create User - MJR Group ERP';
$errors = [];

if (is_post()) {
    $csrf_token = post('csrf_token');

    if (!verify_csrf_token($csrf_token)) {
        set_flash('Invalid request token. Please try again.', 'error');
    } else {
        $username = trim(post('username', ''));
        $email = trim(post('email', ''));
        $password = (string) post('password', '');
        $confirm_password = (string) post('confirm_password', '');
        $role = normalize_role_name((string) post('role', $default_role));

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

        if (!in_array($role, $allowed_roles, true)) {
            $errors['role'] = 'Please select a valid account type.';
        }

        if ($password === '') {
            $errors['password'] = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        if ($confirm_password === '') {
            $errors['confirm_password'] = 'Please confirm the password.';
        } elseif ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $existing = db_fetch(
                "SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1",
                [$username, $email]
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
            $user_id = register_user([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'company_id' => $company_id,
            ]);

            if ($user_id) {
                $account_label = match ($role) {
                    'company_admin' => 'Company Admin',
                    'manager' => 'Manager ID',
                    default => 'User',
                };
                set_flash($account_label . ' created successfully for ' . $company['name'] . '.', 'success');
                redirect('manage_users.php?id=' . $company_id);
            }

            set_flash('Unable to create the account. Please try again.', 'error');
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
                    <h3 class="card-title mb-0"><i class="fas fa-user-plus me-2"></i>Create New <?= $default_role === 'company_admin' ? 'Company Admin' : ($default_role === 'manager' ? 'Manager ID' : 'User') ?></h3>
                    <span class="badge bg-light text-dark px-3 py-2"><?= escape_html($company['name']) ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-info">
                        <i class="fas fa-building me-2"></i>
                        This account will be created and assigned to <strong><?= escape_html($company['name']) ?></strong>.
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label for="username" class="form-label fw-bold">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" id="username" name="username" value="<?= escape_html(post('username')) ?>" required maxlength="64" placeholder="Enter username">
                                <?php if (isset($errors['username'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['username']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="email" class="form-label fw-bold">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= escape_html(post('email')) ?>" required maxlength="120" placeholder="name@example.com">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row border-top pt-4 mt-2">
                            <div class="col-md-6 mb-4">
                                <label for="role" class="form-label fw-bold">Account Type <span class="text-danger">*</span></label>
                                <select class="form-select <?= isset($errors['role']) ? 'is-invalid' : '' ?>" id="role" name="role" required>
                                    <?php if (in_array('user', $allowed_roles, true)): ?>
                                    <option value="user" <?= normalize_role_name(post('role', $default_role)) === 'user' ? 'selected' : '' ?>>User</option>
                                    <?php endif; ?>
                                    <?php if (in_array('manager', $allowed_roles, true)): ?>
                                    <option value="manager" <?= normalize_role_name(post('role', $default_role)) === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    <?php endif; ?>
                                    <?php if (in_array('company_admin', $allowed_roles, true)): ?>
                                    <option value="company_admin" <?= normalize_role_name(post('role', $default_role)) === 'company_admin' ? 'selected' : '' ?>>Company Admin</option>
                                    <?php endif; ?>
                                </select>
                                <?php if (isset($errors['role'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['role']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-4 d-flex align-items-end">
                                <div class="w-100 small text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Roles are limited by your hierarchy. Company Admin can create Managers and Users, while Managers can create Users only.
                                </div>
                            </div>
                        </div>

                        <div class="row border-top pt-4">
                            <div class="col-md-6 mb-4">
                                <label for="password" class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" required placeholder="Enter password">
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['password']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label for="confirm_password" class="form-label fw-bold">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                                <?php if (isset($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?= escape_html($errors['confirm_password']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <a href="manage_users.php?id=<?= $company_id ?>" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-arrow-left me-2"></i>Back
                            </a>
                            <button type="submit" class="btn btn-primary px-5 shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
