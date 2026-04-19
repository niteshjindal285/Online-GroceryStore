<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

// Get warehouse ID from URL
$warehouse_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($warehouse_id <= 0) {
    die("Invalid Warehouse ID.");
}

// Fetch warehouse details
$warehouse = db_fetch_all("SELECT * FROM warehouses WHERE id = $warehouse_id")[0] ?? null;

if (!$warehouse) { 
    header("Location: index.php"); 
    exit; 
}

// Fetch all products from inventory_items table
$products = db_fetch_all("SELECT id, name, code FROM inventory_items ORDER BY name ASC");

$page_title = "Receive Stock - " . htmlspecialchars($warehouse['name']);
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Warehouses</a></li>
                <li class="breadcrumb-item"><a href="inventory.php?id=<?php echo $warehouse_id; ?>">
                    <?php echo htmlspecialchars($warehouse['name']); ?>
                </a></li>
                <li class="breadcrumb-item active">Receive Stock</li>
              </ol>
            </nav>

            <div class="card shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-box-open me-2"></i>
                        Add Stock to <?php echo htmlspecialchars($warehouse['name']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($products)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No Products Found!</strong><br>
                            You need to add products to your inventory first before you can receive stock.
                            <a href="/modules/inventory/index.php" class="alert-link">Go to Inventory Management</a>
                        </div>
                    <?php else: ?>
                        <form action="receive_process.php" method="POST" id="receiveStockForm">
                            <input type="hidden" name="warehouse_id" value="<?php echo $warehouse_id; ?>">
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label font-weight-bold">Select Product *</label>
                                    <select name="product_id" class="form-select" required>
                                        <option value="">-- Choose a Product --</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>">
                                                [<?php echo htmlspecialchars($prod['code']); ?>] 
                                                <?php echo htmlspecialchars($prod['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Select the product you want to add to this warehouse</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Quantity *</label>
                                    <input type="number" name="quantity" class="form-control" 
                                           placeholder="e.g. 100" min="1" required>
                                    <small class="text-muted">Number of units to add</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label font-weight-bold">Bin Location *</label>
                                    <input type="text" name="bin_location" class="form-control" 
                                           placeholder="e.g. A-01-02" required>
                                    <small class="text-muted">Storage location code</small>
                                </div>

                                <!-- <div class="col-md-12 mb-3">
                                    <label class="form-label">Notes (Optional)</label>
                                    <textarea name="notes" class="form-control" rows="3" 
                                              placeholder="Add any additional notes about this stock receipt..."></textarea>
                                </div> -->
                            </div>

                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="inventory.php?id=<?php echo $warehouse_id; ?>" 
                                   class="btn btn-light border">
                                   <i class="fas fa-arrow-left me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fas fa-check me-2"></i>Receive Stock
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card mt-3 border-info">
                <div class="card-body">
                    <h6 class="text-info"><i class="fas fa-info-circle me-2"></i>How to Receive Stock</h6>
                    <ul class="mb-0 small">
                        <li>Select the product from your inventory list</li>
                        <li>Enter the quantity you're receiving</li>
                        <li>Specify the bin location where this stock will be stored (e.g., A-01-02, B-03-05)</li>
                        <li>If the product already exists in that bin, the quantities will be added together</li>
                        <li>All stock movements are logged for tracking purposes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.getElementById('receiveStockForm').addEventListener('submit', function(e) {
    const qty = document.querySelector('input[name="quantity"]').value;
    const bin = document.querySelector('input[name="bin_location"]').value;
    
    if (parseInt(qty) <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (bin.trim() === '') {
        e.preventDefault();
        alert('Bin location is required');
        return false;
    }
    
    return true;
});
</script>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>