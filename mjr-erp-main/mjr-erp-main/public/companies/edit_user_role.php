<?php
/**
 * Companies - Edit Assigned User Role
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_users');

if (is_post()) {
    $csrf_token = post('csrf_token');
    $company_id = (int)post('company_id');
    $user_id = (int)post('user_id');
    $new_role = normalize_role_name((string)post('role'));

    if (!verify_csrf_token($csrf_token)) {
        set_flash('Invalid request token.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    enforce_company_access($company_id);

    $target_user = db_fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$target_user || $target_user['company_id'] != $company_id) {
        set_flash('User not found in this company.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    if (!can_manage_user_account($target_user)) {
        set_flash('You do not have permission to manage this user.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    // Ensure the new role is within the current user's manageable roles
    $allowed_roles = get_manageable_roles();
    if (!in_array($new_role, $allowed_roles, true)) {
        set_flash('You do not have permission to assign that role.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    // Don't allow changing own role here to prevent lockouts
    if ($user_id == current_user_id()) {
        set_flash('You cannot change your own role here.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    db_query("UPDATE users SET role = ? WHERE id = ?", [$new_role, $user_id]);
    set_flash("User role updated successfully to " . ucwords(str_replace('_', ' ', $new_role)) . ".", "success");
    redirect('manage_users.php?id=' . $company_id);
}
redirect('index.php');
