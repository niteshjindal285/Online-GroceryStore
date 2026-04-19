<?php
/**
 * Companies - Remove User from Company (Helper)
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
$user_id    = intval(post('user_id'));

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
    // Confirm user actually belongs to this company before removing
    $user = db_fetch("SELECT id, role, company_id FROM users WHERE id = ? AND company_id = ?", [$user_id, $company_id]);
    if (!$user) {
        throw new Exception('User does not belong to this company.');
    }
    if ($user_id === (int) current_user_id()) {
        throw new Exception('You cannot remove your own account from the company.');
    }
    if (!can_manage_user_account($user)) {
        throw new Exception('You do not have permission to remove this account.');
    }

    db_query("UPDATE users SET company_id = NULL WHERE id = ?", [$user_id]);
    set_flash('User removed from company successfully!', 'success');
} catch (Exception $e) {
    log_error("Error removing user: " . $e->getMessage());
    set_flash($e->getMessage(), 'error');
}

redirect('manage_users.php?id=' . $company_id);
?>
