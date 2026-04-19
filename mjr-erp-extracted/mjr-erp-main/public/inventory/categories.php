<?php
/**
 * Inventory - Categories Management
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Categories - MJR Group ERP';

// Get all categories
$categories = db_fetch_all("
    SELECT c.*, COUNT(i.id) as item_count
    FROM categories c
    LEFT JOIN inventory_items i ON c.id = i.category_id
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY c.name
");

// Handle add category
if (is_post() && isset($_POST['name'])) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        try {
            $name = sanitize_input(post('name'));
            $description = sanitize_input(post('description'));
            
            $sql = "INSERT INTO categories (name, description, is_active) VALUES (?, ?, 1)";
            db_insert($sql, [$name, $description]);
            
            set_flash('Category added successfully!', 'success');
            redirect('categories.php');
        } catch (Exception $e) {
            log_error("Error adding category: " . $e->getMessage());
            set_flash('Error adding category.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-tags me-3"></i>Item Categories</h1>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($categories)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Items Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><strong><?= escape_html($category['name']) ?></strong></td>
                            <td><?= escape_html($category['description'] ?? 'N/A') ?></td>
                            <td><span class="badge bg-info"><?= $category['item_count'] ?> items</span></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="edit_category.php?id=<?= $category['id'] ?>" class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_category.php?id=<?= $category['id'] ?>" class="btn btn-outline-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                <p class="text-muted">No categories configured</p>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="fas fa-plus me-2"></i>Add First Category
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>



