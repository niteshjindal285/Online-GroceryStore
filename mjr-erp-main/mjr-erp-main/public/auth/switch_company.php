<?php
/**
 * Company Switcher – POST endpoint
 * Allows admin users to switch the active company context in their session.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

// Only Administrators/Managers may switch company context across companies
if (!is_admin()) {
    set_flash('Only administrators can switch company context.', 'error');
    redirect(url('index.php'));
}

if (!is_post()) {
    redirect(url('index.php'));
}

if (!verify_csrf_token(post('csrf_token'))) {
    set_flash('Invalid security token. Please try again.', 'error');
    redirect(url('index.php'));
}

$new_company_id = intval(post('company_id'));

if ($new_company_id <= 0) {
    set_flash('Invalid company selected.', 'error');
    redirect(url('index.php'));
}

// Verify company exists and is active
$company = db_fetch("SELECT id, name FROM companies WHERE id = ? AND is_active = 1", [$new_company_id]);

if (!$company) {
    set_flash('Company not found or inactive.', 'error');
    redirect(url('index.php'));
}

// Switch session context
$_SESSION['company_id']   = $company['id'];
$_SESSION['company_name'] = $company['name'];

set_flash('Switched to ' . $company['name'], 'success');

// Redirect back to where they came from, or dashboard
$back = $_SERVER['HTTP_REFERER'] ?? url('index.php');
redirect($back);
