<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();
$page_title = 'Create Stock Count Sheet - MJR Group ERP';
$company_id = active_company_id(1);
$errors = [];

// Get locations and categories
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");

if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token.';
    } else {
        $count_date = sanitize_input(post('count_date'));
        $count_type = post('count_type');
        $location_id = post('location_id') ?: null;
        $category_id = post('category_id') ?: null;
        $notes = sanitize_input(post('notes'));
        
        if (empty($count_date)) {
            $errors[] = 'Count date is required.';
        }
        
        if (empty($errors)) {
            try {
                db_execute("START TRANSACTION");
                
                // Generate count number
                $count_number = 'SC' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                // Create header
                $header_id = db_insert("
                    INSERT INTO stock_count_headers 
                    (count_number, count_date, location_id, category_id, count_type, created_by, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Draft')
                ", [$count_number, $count_date, $location_id, $category_id, $count_type, $_SESSION['user_id'], $notes]);
                
                // Build query to get items
                $where = ["i.is_active = 1", "i.company_id = ?"];
                $params = [$company_id];
                
                if ($location_id) {
                    $where[] = "s.location_id = ?";
                    $params[] = $location_id;
                }
                
                if ($category_id) {
                    $where[] = "i.category_id = ?";
                    $params[] = $category_id;
                }
                
                $where_sql = implode(' AND ', $where);
                
                // Get items to count
                $items = db_fetch_all("
                    SELECT i.id as item_id, 
                           COALESCE(s.location_id, ?) as location_id,
                           COALESCE(s.quantity_available, 0) as system_quantity
                    FROM inventory_items i
                    LEFT JOIN inventory_stock_levels s ON i.id = s.item_id
                    WHERE {$where_sql}
                    GROUP BY i.id, s.location_id
                ", array_merge([$location_id ?? 1], $params));
                
                // Insert count details
                foreach ($items as $item) {
                    db_insert("
                        INSERT INTO stock_count_details 
                        (count_header_id, item_id, location_id, system_quantity)
                        VALUES (?, ?, ?, ?)
                    ", [$header_id, $item['item_id'], $item['location_id'], $item['system_quantity']]);
                }
                
                db_execute("COMMIT");
                
                set_flash('Stock count sheet created successfully with ' . count($items) . ' items!', 'success');
                redirect('enter_count_results.php?id=' . $header_id);
                
            } catch (Exception $e) {
                db_execute("ROLLBACK");
                log_error("Error creating stock count: " . $e->getMessage());
                $errors[] = 'An error occurred while creating the stock count.';
            }
        }
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="mb-4">
        <h2><i class="fas fa-plus me-2"></i>Create Stock Count Sheet</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">Inventory</a></li>
                <li class="breadcrumb-item"><a href="index.php">Stock Counting</a></li>
                <li class="breadcrumb-item active">Create</li>
            </ol>
        </nav>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape_html($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="count_date" class="form-label">Count Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="count_date" name="count_date" 
                               value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="count_type" class="form-label">Count Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="count_type" name="count_type" required>
                            <option value="Full">Full Count (All Items)</option>
                            <option value="Partial" selected>Partial Count (Filtered)</option>
                            <option value="Cycle">Cycle Count</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="location_id" class="form-label">Filter by Location</label>
                        <select class="form-select" id="location_id" name="location_id">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['id'] ?>"><?= escape_html($location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="category_id" class="form-label">Filter by Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= escape_html($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check me-2"></i>Create Count Sheet
                </button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>