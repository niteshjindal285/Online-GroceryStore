<?php
/**
 * User Logout
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Logout user
logout_user();

// Redirect to login page
redirect('login.php');
?>
