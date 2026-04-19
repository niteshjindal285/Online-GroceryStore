<?php
/**
 * Sales Returns List Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Sales Returns - MJR Group ERP';
$company_id = $_SESSION['company_id'];

// Initial placeholder implementation for returns
$returns = []; // Logic to fetch returns would go here if a table existed

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-undo me-2"></i>Sales Returns</h2>
                <button class="btn btn-primary disabled">
                    <i class="fas fa-plus me-2"></i>New Return (Planned)
                </button>
            </div>

            <div class="alert alert-info">
                <h5><i class="fas fa-info-circle me-2"></i>Module in Development</h5>
                <p>The Sales Returns and Credit Notes module is currently under development. This page will eventually display all returned items and their corresponding credit status.</p>
            </div>

            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h4>No Returns Found</h4>
                    <p class="text-muted">Once returns are processed, they will appear here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
