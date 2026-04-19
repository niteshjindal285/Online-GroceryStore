<?php
/**
 * Inventory - ABC Analysis
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'ABC Analysis - MJR Group ERP';

// Get items with their value and classify
$items = db_fetch_all("
    SELECT i.id, i.code, i.name, i.average_cost,
           SUM(s.quantity_on_hand) as total_quantity,
           (i.average_cost * SUM(s.quantity_on_hand)) as total_value,
           u.code as unit_code
    FROM inventory_items i
    LEFT JOIN inventory_stock_levels s ON i.id = s.item_id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE i.is_active = 1
    GROUP BY i.id
    ORDER BY total_value DESC
");

// Calculate total value
$total_inventory_value = array_sum(array_column($items, 'total_value'));

// Classify items
$cumulative_value = 0;
$classified_items = ['A' => [], 'B' => [], 'C' => []];

foreach ($items as $item) {
    $cumulative_value += $item['total_value'];
    $cumulative_percentage = ($total_inventory_value > 0) ? ($cumulative_value / $total_inventory_value) * 100 : 0;
    
    if ($cumulative_percentage <= 80) {
        $item['class'] = 'A';
        $classified_items['A'][] = $item;
    } elseif ($cumulative_percentage <= 95) {
        $item['class'] = 'B';
        $classified_items['B'][] = $item;
    } else {
        $item['class'] = 'C';
        $classified_items['C'][] = $item;
    }
}

// Calculate statistics
$stats = [
    'A' => ['count' => count($classified_items['A']), 'value' => array_sum(array_column($classified_items['A'], 'total_value'))],
    'B' => ['count' => count($classified_items['B']), 'value' => array_sum(array_column($classified_items['B'], 'total_value'))],
    'C' => ['count' => count($classified_items['C']), 'value' => array_sum(array_column($classified_items['C'], 'total_value'))]
];

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-chart-pie me-3"></i>ABC Analysis</h1>
            <p class="lead">Inventory classification by value</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6>Class A Items</h6>
                    <h3><?= $stats['A']['count'] ?></h3>
                    <small><?= format_currency($stats['A']['value']) ?> (80% value)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h6>Class B Items</h6>
                    <h3><?= $stats['B']['count'] ?></h3>
                    <small><?= format_currency($stats['B']['value']) ?> (15% value)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6>Class C Items</h6>
                    <h3><?= $stats['C']['count'] ?></h3>
                    <small><?= format_currency($stats['C']['value']) ?> (5% value)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6>Total Inventory</h6>
                    <h3><?= count($items) ?></h3>
                    <small><?= format_currency($total_inventory_value) ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Classification Tabs -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-bs-toggle="tab" href="#classA">Class A</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#classB">Class B</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#classC">Class C</a>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content">
                <!-- Class A -->
                <div class="tab-pane fade show active" id="classA">
                    <h5 class="text-danger mb-3">Class A - High Value Items (80% of total value)</h5>
                    <?php if (!empty($classified_items['A'])): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classified_items['A'] as $item): ?>
                                <tr>
                                    <td><strong><?= escape_html($item['code']) ?></strong></td>
                                    <td><?= escape_html($item['name']) ?></td>
                                    <td><?= format_number($item['total_quantity'], 0) ?> <?= escape_html($item['unit_code']) ?></td>
                                    <td><?= format_currency($item['average_cost']) ?></td>
                                    <td><strong><?= format_currency($item['total_value']) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No items in this category</p>
                    <?php endif; ?>
                </div>

                <!-- Class B -->
                <div class="tab-pane fade" id="classB">
                    <h5 class="text-warning mb-3">Class B - Medium Value Items (15% of total value)</h5>
                    <?php if (!empty($classified_items['B'])): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classified_items['B'] as $item): ?>
                                <tr>
                                    <td><strong><?= escape_html($item['code']) ?></strong></td>
                                    <td><?= escape_html($item['name']) ?></td>
                                    <td><?= format_number($item['total_quantity'], 0) ?> <?= escape_html($item['unit_code']) ?></td>
                                    <td><?= format_currency($item['average_cost']) ?></td>
                                    <td><strong><?= format_currency($item['total_value']) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No items in this category</p>
                    <?php endif; ?>
                </div>

                <!-- Class C -->
                <div class="tab-pane fade" id="classC">
                    <h5 class="text-info mb-3">Class C - Low Value Items (5% of total value)</h5>
                    <?php if (!empty($classified_items['C'])): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classified_items['C'] as $item): ?>
                                <tr>
                                    <td><strong><?= escape_html($item['code']) ?></strong></td>
                                    <td><?= escape_html($item['name']) ?></td>
                                    <td><?= format_number($item['total_quantity'], 0) ?> <?= escape_html($item['unit_code']) ?></td>
                                    <td><?= format_currency($item['average_cost']) ?></td>
                                    <td><strong><?= format_currency($item['total_value']) ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No items in this category</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
