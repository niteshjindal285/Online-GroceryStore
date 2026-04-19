<?php
require_once __DIR__ . '/../../../../includes/auth.php'; 
require_once __DIR__ . '/../../../../includes/database.php';
require_once __DIR__ . '/../../../../includes/functions.php'; 
require_login();

$wh_id = (int)$_GET['id'] ?? 0;

// Logic: Bin wise total quantity calculate karna
$bin_report = db_fetch_all("
    SELECT bin_location, COUNT(product_id) as different_items, SUM(quantity) as total_qty 
    FROM warehouse_inventory 
    WHERE warehouse_id = $wh_id 
    GROUP BY bin_location
");

$page_title = "Location Optimization";
include __DIR__ . '/../../../../templates/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <?php foreach($bin_report as $bin): ?>
        <div class="col-md-3 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <h6 class="text-muted">Rack / Bin</h6>
                        <i class="fas fa-th text-primary"></i>
                    </div>
                    <h4 class="mb-3"><?php echo htmlspecialchars($bin['bin_location']); ?></h4>
                    
                    <label class="small">Occupancy</label>
                    <div class="progress mb-3" style="height: 10px;">
                        <?php 
                            // Maan lete hain ek bin ki max capacity 500 units hai
                            $percent = min(($bin['total_qty'] / 500) * 100, 100); 
                            $color = $percent > 80 ? 'bg-danger' : 'bg-success';
                        ?>
                        <div class="progress-bar <?php echo $color; ?>" style="width: <?php echo $percent; ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between small">
                        <span>Items: <?php echo $bin['different_items']; ?></span>
                        <span>Total Qty: <?php echo $bin['total_qty']; ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>