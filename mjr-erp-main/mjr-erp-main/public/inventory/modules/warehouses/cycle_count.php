<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

// Get parameters from URL and validate them
$wh_id = isset($_GET['warehouse_id']) ? (int)$_GET['warehouse_id'] : 0;
$prod_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

// Validate that both IDs are present
if ($wh_id <= 0 || $prod_id <= 0) {
    die("Error: Invalid warehouse or product ID. Please select a valid item for cycle counting.");
}

// Fetch current stock from database
$stock_query = db_fetch_all("SELECT wi.*, i.name, i.code 
                             FROM inventory_items i
                             LEFT JOIN warehouse_inventory wi ON i.id = wi.product_id AND wi.warehouse_id = $wh_id
                             WHERE i.id = $prod_id");

// Check if product exists (if not, we can't count it)
if (empty($stock_query)) {
    die("Error: Product not found. Please go back and select a valid item.");
}

// Get the first record and handle missing stock entry
$current_stock = $stock_query[0];
if (!isset($current_stock['quantity'])) {
    $current_stock['id'] = 0; // Signal for new record
    $current_stock['quantity'] = 0;
    $current_stock['bin_location'] = 'N/A';
}

// Fetch warehouse details
$warehouse_query = db_fetch_all("SELECT name, location FROM warehouses WHERE id = $wh_id");
$warehouse = !empty($warehouse_query) ? $warehouse_query[0] : null;

$page_title = "Cycle Counting - Audit";
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container mt-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Warehouses</a></li>
            <?php if ($warehouse): ?>
            <li class="breadcrumb-item"><a href="inventory.php?id=<?php echo $wh_id; ?>">
                <?php echo htmlspecialchars($warehouse['name']); ?>
            </a></li>
            <?php endif; ?>
            <li class="breadcrumb-item active">Cycle Count</li>
        </ol>
    </nav>

    <div class="card shadow-sm border-0 col-md-6 mx-auto">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0">
                <i class="fas fa-clipboard-check me-2"></i>Inventory Audit (Cycle Count)
            </h5>
        </div>
        <div class="card-body">
            <form action="audit_process.php" method="POST" id="cycleCountForm">
                <input type="hidden" name="warehouse_id" value="<?php echo $wh_id; ?>">
                <input type="hidden" name="product_id" value="<?php echo $prod_id; ?>">
                <input type="hidden" name="inventory_id" value="<?php echo $current_stock['id']; ?>">
                <input type="hidden" name="system_qty" value="<?php echo $current_stock['quantity']; ?>">

                <div class="mb-3">
                    <label class="text-muted small">SKU Code</label>
                    <p class="mb-0">
                        <code class="bg-light px-2 py-1 rounded">
                            <?php echo htmlspecialchars($current_stock['code']); ?>
                        </code>
                    </p>
                </div>

                <div class="mb-3">
                    <label class="text-muted small">Item</label>
                    <p class="mb-0">
                        <strong><?php echo htmlspecialchars($current_stock['name']); ?></strong>
                    </p>
                </div>

                <div class="mb-3">
                    <label class="text-muted small">Location</label>
                    <p class="mb-0">
                        <span class="badge bg-info text-dark">
                            <i class="fas fa-map-pin me-1"></i>
                            <?php echo htmlspecialchars($current_stock['bin_location']); ?>
                        </span>
                    </p>
                </div>
                
                <div class="alert alert-secondary">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>System Quantity:</span>
                        <strong class="fs-5"><?php echo number_format($current_stock['quantity']); ?> units</strong>
                    </div>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label fw-bold">
                        Actual Physical Count <span class="text-danger">*</span>
                    </label>
                    <input type="number" 
                           name="physical_qty" 
                           id="physical_qty"
                           class="form-control form-control-lg" 
                           placeholder="Enter counted quantity"
                           min="0"
                           required>
                    <div class="form-text">
                        Enter the actual quantity you counted at bin location 
                        <strong><?php echo htmlspecialchars($current_stock['bin_location']); ?></strong>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" 
                              class="form-control" 
                              rows="3" 
                              placeholder="Add any observations or discrepancy reasons..."></textarea>
                </div>
                </div>
                <div class="mb-3" id="discrepancyAlert" style="display: none;">
                    <!-- This will show if there's a discrepancy -->
                </div>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="inventory.php?id=<?php echo $wh_id; ?>" class="btn btn-light border">
                        <i class="fas fa-arrow-left me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-warning px-4">
                        <i class="fas fa-save me-2"></i>Log Audit Result
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Card -->
    <div class="card mt-4 border-info col-md-6 mx-auto">
        <div class="card-body">
            <h6 class="text-info">
                <i class="fas fa-info-circle me-2"></i>What is Cycle Counting?
            </h6>
            <p class="mb-0 small">
                Cycle counting is a periodic physical count of inventory to verify 
                system accuracy. Compare the actual physical count with the system 
                quantity. If there's a discrepancy, the system will create an adjustment 
                record and update the inventory.
            </p>
        </div>
    </div>
</div>

<script>
// Real-time discrepancy detection
document.getElementById('physical_qty').addEventListener('input', function() {
    const systemQty = <?php echo $current_stock['quantity']; ?>;
    const physicalQty = parseInt(this.value) || 0;
    const discrepancyAlert = document.getElementById('discrepancyAlert');
    
    if (physicalQty !== systemQty && this.value !== '') {
        const difference = physicalQty - systemQty;
        const type = difference > 0 ? 'success' : 'danger';
        const icon = difference > 0 ? 'arrow-up' : 'arrow-down';
        const text = difference > 0 ? 'Surplus' : 'Shortage';
        
        discrepancyAlert.innerHTML = `
            <div class="alert alert-${type} mb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-${icon} me-2"></i><strong>${text} Detected:</strong></span>
                    <strong>${Math.abs(difference)} units</strong>
                </div>
            </div>
        `;
        discrepancyAlert.style.display = 'block';
    } else {
        discrepancyAlert.style.display = 'none';
    }
});

// Form validation
document.getElementById('cycleCountForm').addEventListener('submit', function(e) {
    const physicalQty = document.getElementById('physical_qty').value;
    
    if (physicalQty === '' || parseInt(physicalQty) < 0) {
        e.preventDefault();
        alert('Please enter a valid physical count (0 or greater)');
        return false;
    }
    
    return true;
});
</script>

<?php include __DIR__ . '/../../../../templates/footer.php'; ?>