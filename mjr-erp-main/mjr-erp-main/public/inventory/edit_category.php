<?php
/**
 * Edit Category
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Category - MJR Group ERP';

// Get category ID
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('Invalid category ID', 'error');
    redirect('categories.php');
}

// Get category details
try {
    $category = db_fetch("SELECT * FROM categories WHERE id = ?", [$id]);
    if (!$category) {
        throw new Exception('Category not found');
    }
} catch (Exception $e) {
    log_error("Error loading category: " . $e->getMessage());
    set_flash('Error loading category: ' . $e->getMessage(), 'error');
    redirect('categories.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $name = sanitize_input(post('name'));
            $description = sanitize_input(post('description'));
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate required fields
            if (empty($name)) {
                throw new Exception('Category name is required');
            }
            
            // Update category
            db_query("
                UPDATE categories 
                SET name = ?, 
                    description = ?, 
                    is_active = ?
                WHERE id = ?
            ", [$name, $description, $is_active, $id]);
            
            set_flash('Category updated successfully!', 'success');
            redirect('categories.php');
            
        } catch (Exception $e) {
            log_error("Error updating category: " . $e->getMessage());
            set_flash('Error updating category: ' . $e->getMessage(), 'error');
        }
    } else {
        set_flash('Invalid CSRF token', 'error');
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1 class="display-4">
                <i class="fas fa-edit me-3"></i>Edit Category
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                    <li class="breadcrumb-item active">Edit Category</li>
                </ol>
            </nav>
        </div>
        <div class="col-auto">
            <a href="categories.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Categories
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Category Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   value="<?= escape_html($category['name']) ?>"
                                   required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4"><?= escape_html($category['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="is_active" 
                                       name="is_active" 
                                       <?= $category['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Active
                                </label>
                            </div>
                            <small class="text-muted">Inactive categories won't be available for new items</small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="categories.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Category
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Category Information</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Created:</strong><br>
                        <?= date('M d, Y', strtotime($category['created_at'])) ?>
                    </p>
                    <p class="mb-2">
                        <strong>Status:</strong><br>
                        <span class="badge <?= $category['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $category['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>



