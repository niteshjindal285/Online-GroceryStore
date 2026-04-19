<?php
/**
 * Companies - Assign User to Company (Helper)
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

if (!$user_id) {
    set_flash('Please fill Select User that field', 'error');
    redirect('manage_users.php?id=' . $company_id);
}

if (!$company_id) {
    set_flash('Invalid request.', 'error');
    redirect('index.php');
}

enforce_company_access($company_id, 'index.php');

try {
    // Verify the user exists and is in the current RBAC scope
    $user = db_fetch("SELECT id, company_id, role FROM users WHERE id = ? AND is_active = 1", [$user_id]);
    if (!$user) {
        throw new Exception('User not found or inactive.');
    }
    if (!can_manage_user_role($user['role'] ?? 'user')) {
        throw new Exception('You cannot assign that account type.');
    }
    $accessible_company_ids = get_accessible_company_ids();
    if (!empty($user['company_id']) && !in_array((int) $user['company_id'], $accessible_company_ids, true)) {
        throw new Exception('That user belongs to a company outside your access scope.');
    }
    if ($user['company_id'] == $company_id) {
        throw new Exception('User is already assigned to this company.');
    }

    db_query("UPDATE users SET company_id = ? WHERE id = ?", [$company_id, $user_id]);
    set_flash('User assigned successfully!', 'success');
} catch (Exception $e) {
    log_error("Error assigning user: " . $e->getMessage());
    set_flash($e->getMessage(), 'error');
}

redirect('manage_users.php?id=' . $company_id);
?>
