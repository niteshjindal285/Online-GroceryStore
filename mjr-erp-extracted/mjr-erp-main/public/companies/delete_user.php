<?php
/**
 * Companies - Delete User Account (Helper)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_permission('manage_users');

if (!is_post()) {
    redirect('index.php');
}

$csrf_token = post('csrf_token');
$company_id = intval(post('company_id'));
$user_id = intval(post('user_id'));

if (!verify_csrf_token($csrf_token)) {
    set_flash('Invalid security token.', 'error');
    redirect('manage_users.php?id=' . $company_id);
}

if (!$company_id || !$user_id) {
    set_flash('Invalid request.', 'error');
    redirect('index.php');
}

enforce_company_access($company_id, 'index.php');

try {
    $user = db_fetch("SELECT id, username, role, company_id, is_active FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        throw new Exception('User not found.');
    }
    if ($user_id === (int) current_user_id()) {
        throw new Exception('You cannot delete your own account.');
    }
    if (!can_manage_user_account($user)) {
        throw new Exception('You do not have permission to delete this account.');
    }

    db_begin_transaction();
    db_query("DELETE FROM user_permissions WHERE user_id = ?", [$user_id]);
    db_query("DELETE FROM permission_requests WHERE user_id = ?", [$user_id]);
    db_query("DELETE FROM users WHERE id = ?", [$user_id]);
    db_commit();

    set_flash('User account deleted successfully!', 'success');
} catch (Exception $e) {
    if (db_in_transaction()) {
        db_rollback();
    }
    log_error("Error deleting user: " . $e->getMessage());
    set_flash('Unable to delete this user. The account may have linked records in other modules.', 'error');
}

redirect('manage_users.php?id=' . $company_id);
?>
