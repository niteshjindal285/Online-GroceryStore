<?php
/**
 * Inventory - Inventory Valuation
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Inventory Valuation - MJR Group ERP';
$company_id = active_company_id(1);
$company_name = active_company_name('Current Company');

// Get inventory valuation by location
$valuation_by_location = db_fetch_all("
    SELECT l.name as location_name,
           SUM(s.quantity_on_hand * i.average_cost) as total_value,
           SUM(s.quantity_on_hand) as total_quantity,
           COUNT(DISTINCT i.id) as item_count
    FROM inventory_stock_levels s
    JOIN inventory_items i ON s.item_id = i.id
    JOIN locations l ON s.location_id = l.id
    WHERE i.is_active = 1 AND l.company_id = ?
    GROUP BY l.id
    ORDER BY total_value DESC
", [$company_id]);

// Get inventory valuation by category
$valuation_by_category = db_fetch_all("
    SELECT c.name as category_name,
           SUM(s.quantity_on_hand * i.average_cost) as total_value,
           SUM(s.quantity_on_hand) as total_quantity,
           COUNT(DISTINCT i.id) as item_count
    FROM inventory_stock_levels s
    JOIN inventory_items i ON s.item_id = i.id
    JOIN locations l ON s.location_id = l.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1 AND l.company_id = ?
    GROUP BY c.id
    ORDER BY total_value DESC
", [$company_id]);

// Get inventory valuation by item
$valuation_by_item = db_fetch_all("
    SELECT i.id,
           i.code,
           i.name as item_name,
           c.name as category_name,
           i.average_cost,
           SUM(s.quantity_on_hand) as total_quantity,
           SUM(s.quantity_on_hand * i.average_cost) as total_value
    FROM inventory_stock_levels s
    JOIN inventory_items i ON s.item_id = i.id
    JOIN locations l ON s.location_id = l.id
    LEFT JOIN categories c ON i.category_id = c.id
    WHERE i.is_active = 1 AND l.company_id = ?
    GROUP BY i.id
    ORDER BY total_value DESC, i.name ASC
", [$company_id]);

// Calculate totals
$total_value = array_sum(array_column($valuation_by_location, 'total_value'));
$total_items = db_fetch("SELECT COUNT(*) as count FROM inventory_items WHERE is_active = 1 AND company_id = ?", [$company_id])['count'];

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-dollar-sign me-3"></i>Inventory Valuation</h1>
            <p class="lead">Current inventory value analysis for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Total Inventory Value</h6>
                    <h2><?= format_currency($total_value) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Total Items</h6>
                    <h2><?= $total_items ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>Average Value per Item</h6>
                    <h2><?= $total_items > 0 ? format_currency($total_value / $total_items) : format_currency(0) ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Valuation by Location -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-map-marker-alt me-2"></i>Valuation by Location</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($valuation_by_location)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Location</th>
                            <th>Items</th>
                            <th>Total Quantity</th>
                            <th>Total Value</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valuation_by_location as $row): ?>
                        <tr>
                            <td><strong><?= escape_html($row['location_name']) ?></strong></td>
                            <td><?= $row['item_count'] ?> items</td>
                            <td><?= format_number($row['total_quantity'], 0) ?></td>
                            <td><strong><?= format_currency($row['total_value']) ?></strong></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <?php $percentage = $total_value > 0 ? ($row['total_value'] / $total_value) * 100 : 0; ?>
                                    <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%">
                                        <?= number_format($percentage, 1) ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-muted">No data available</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Valuation by Category -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-tags me-2"></i>Valuation by Category</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($valuation_by_category)): ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Items</th>
                            <th>Total Quantity</th>
                            <th>Total Value</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($valuation_by_category as $row): ?>
                        <tr>
                            <td><strong><?= escape_html($row['category_name'] ?? 'Uncategorized') ?></strong></td>
                            <td><?= $row['item_count'] ?> items</td>
                            <td><?= format_number($row['total_quantity'], 0) ?></td>
                            <td><strong><?= format_currency($row['total_value']) ?></strong></td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <?php $percentage = $total_value > 0 ? ($row['total_value'] / $total_value) * 100 : 0; ?>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentage ?>%">
                                        <?= number_format($percentage, 1) ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-muted">No data available</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Valuation by Item -->
<div class="card mt-4">
    <div class="card-header">
        <h5><i class="fas fa-list me-2"></i>Valuation by Item</h5>
    </div>
    <div class="card-body">
        <?php if (!empty($valuation_by_item)): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Cost Price</th>
                        <th>Total Quantity</th>
                        <th>Total Value</th>
                        <th>% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($valuation_by_item as $row): ?>
                    <tr>
                        <td><strong><?= escape_html($row['code']) ?></strong></td>
                        <td><?= escape_html($row['item_name']) ?></td>
                        <td><?= escape_html($row['category_name'] ?? 'Uncategorized') ?></td>
                        <td><?= format_currency($row['cost_price']) ?></td>
                        <td><?= format_number($row['total_quantity'], 0) ?></td>
                        <td><strong><?= format_currency($row['total_value']) ?></strong></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <?php $percentage = $total_value > 0 ? ($row['total_value'] / $total_value) * 100 : 0; ?>
                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $percentage ?>%">
                                    <?= number_format($percentage, 1) ?>%
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-center text-muted">No data available</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
