<?php
/**
 * Companies - Assign Manager to User
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_permission('manage_users');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        set_flash('Invalid CSRF token.', 'error');
        redirect('manage_users.php');
    }

    $user_id = (int) ($_POST['user_id'] ?? 0);
    $manager_id = !empty($_POST['manager_id']) ? (int) $_POST['manager_id'] : null;
    $company_id = (int) ($_POST['company_id'] ?? 0);

    if (!$user_id || !$company_id) {
        set_flash('Missing required fields.', 'error');
        redirect('manage_users.php?id=' . $company_id);
    }

    // Verify company access
    enforce_company_access($company_id);

    try {
        $user = db_fetch("SELECT id, role, company_id FROM users WHERE id = ? AND company_id = ?", [$user_id, $company_id]);
        if (!$user || !can_manage_user_account($user)) {
            throw new Exception('You do not have permission to manage this user.');
        }

        if ($manager_id) {
            $manager = db_fetch("SELECT id, role, company_id, is_active FROM users WHERE id = ?", [$manager_id]);
            if (!$manager || (int) ($manager['company_id'] ?? 0) !== $company_id || empty($manager['is_active'])) {
                throw new Exception('Selected manager is invalid for this company.');
            }

            $manager_role = normalize_role_name($manager['role'] ?? 'user');
            if (!in_array($manager_role, ['manager', 'company_admin', 'super_admin'], true)) {
                throw new Exception('Selected user cannot be assigned as a manager.');
            }
        }

        // Update user
        $sql = "UPDATE users SET manager_id = ? WHERE id = ? AND company_id = ?";
        db_execute($sql, [$manager_id, $user_id, $company_id]);

        set_flash('Manager assigned successfully.', 'success');
    } catch (Exception $e) {
        set_flash('Error assigning manager: ' . $e->getMessage(), 'error');
    }

    redirect('manage_users.php?id=' . $company_id);
} else {
    redirect('index.php');
}
