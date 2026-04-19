<?php
/**
 * User Login Page
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';

// Handle login form submission
if (is_post()) {
    $username = sanitize_input(post('username'));
    $password = post('password');
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid security token. Please try again.';
    } else if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $user = authenticate_user($username, $password);
            
            if ($user) {
                login_user($user);
                
                // Redirect to intended page or dashboard
                $redirect_url = $_SESSION['redirect_after_login'] ?? 'index.php';
                unset($_SESSION['redirect_after_login']);
                redirect($redirect_url);
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            if (function_exists('log_error')) {
                log_error('Login failed due to database/application error: ' . $e->getMessage());
            } else {
                error_log('Login failed due to database/application error: ' . $e->getMessage());
            }
            $error = 'Login is temporarily unavailable. Please try again shortly.';
        }
    }
}

$page_title = 'Login - MJR Group ERP';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($page_title) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #0a0e27 0%, #14213d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        .login-card {
            background-color: #14213d;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }
        .login-card h2 {
            color: #fca311;
            margin-bottom: 30px;
        }
        .form-control {
            background-color: #1a2744;
            border-color: #2a3f5f;
            color: #e4e6eb;
            padding: 12px;
        }
        .form-control:focus {
            background-color: #1a2744;
            border-color: #fca311;
            color: #e4e6eb;
            box-shadow: 0 0 0 0.2rem rgba(252, 163, 17, 0.25);
        }
        .btn-login {
            background-color: #fca311;
            border-color: #fca311;
            color: #0a0e27;
            font-weight: bold;
            padding: 12px;
            width: 100%;
        }
        .btn-login:hover {
            background-color: #e59400;
            border-color: #e59400;
            color: #0a0e27;
        }
        .alert {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        label {
            color: #a0a3bd;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="text-center mb-4">
                <i class="fas fa-chart-line fa-3x" style="color: #fca311;"></i>
                <h2 class="mt-3">MJR Group ERP</h2>
                <p style="color: #a0a3bd;">Enterprise Resource Planning System</p>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?= escape_html($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="fas fa-user me-2"></i>Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock me-2"></i>Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <div class="mt-4 text-center" style="color: #a0a3bd;">
                <small>Test log-ins: <b>admin</b>, <b>manager</b>, or <b>user</b><br>
                Password: <b>password123</b></small>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
