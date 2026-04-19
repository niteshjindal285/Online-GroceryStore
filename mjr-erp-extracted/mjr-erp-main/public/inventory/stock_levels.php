<?php

/**
 * Inventory Stock Levels - Real-Time View with Unit Conversion
 *
 * Features:
 * - Live stock data display with status indicators
 * - Advanced unit conversion system (Weight, Length, Volume, Quantity)
 * - Batch table conversion capability
 * - Auto-calculation for conversions
 * - ✅ NEW: Unit Calculator (e.g., 1 KG -> 1000 G)
 * - ✅ FIX: Calculator "Calculate" also converts TABLE + old conversion removed first
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Stock Levels - MJR Group ERP';

// ================================================================
// UNIT CONVERSION SYSTEM
// ================================================================

$unit_categories = [
    'weight' => ['mg', 'g', 'kg', 'ton', 'tons', 'lbs'],
    'length' => ['mm', 'cm', 'm', 'km', 'inch', 'ft'],
    'volume' => ['ml', 'l', 'ltr', 'gallon'],
    'quantity' => ['pcs', 'dozen', 'box', 'pack', 'carton']
];

function normalize_unit($unit)
{
    $unit_map = [
        // Weight units
        'milligram' => 'mg',
        'milligrams' => 'mg',
        'mg' => 'mg',
        'gram' => 'g',
        'grams' => 'g',
        'g' => 'g',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'kg' => 'kg',
        'ton' => 'tons',
        'tons' => 'tons',
        'tonne' => 'tons',
        'tonnes' => 'tons',
        'lbs' => 'lbs',
        'pound' => 'lbs',
        'pounds' => 'lbs',

        // Length units
        'meter' => 'm',
        'meters' => 'm',
        'm' => 'm',
        'centimeter' => 'cm',
        'centimeters' => 'cm',
        'cm' => 'cm',
        'millimeter' => 'mm',
        'millimeters' => 'mm',
        'mm' => 'mm',
        'kilometer' => 'km',
        'kilometers' => 'km',
        'km' => 'km',
        'inch' => 'inch',
        'inches' => 'inch',
        'foot' => 'ft',
        'feet' => 'ft',
        'ft' => 'ft',

        // Volume units
        'litre' => 'l',
        'litres' => 'l',
        'liter' => 'l',
        'liters' => 'l',
        'l' => 'l',
        'ltr' => 'ltr',
        'milliliter' => 'ml',
        'milliliters' => 'ml',
        'ml' => 'ml',
        'gallon' => 'gallon',
        'gallons' => 'gallon',

        // Quantity units
        'piece' => 'pcs',
        'pieces' => 'pcs',
        'pcs' => 'pcs',
        'pc' => 'pcs',
        'dozen' => 'dozen',
        'box' => 'box',
        'boxes' => 'box',
        'pack' => 'pack',
        'packs' => 'pack',
        'packet' => 'pack',
        'carton' => 'carton',
        'cartons' => 'carton',
    ];

    $unit_lower = strtolower(trim((string)$unit));
    return $unit_map[$unit_lower] ?? $unit_lower;
}

function get_unit_category($unit)
{
    global $unit_categories;
    $unit_lower = strtolower(trim((string)$unit));

    foreach ($unit_categories as $category => $units) {
        if (in_array($unit_lower, $units, true)) {
            return $category;
        }
    }
    return null;
}

function convert_unit($quantity, $from_unit, $to_unit)
{
    $from = normalize_unit($from_unit);
    $to = normalize_unit($to_unit);

    if ($from === $to) {
        return round((float)$quantity, 6);
    }

    $from_cat = get_unit_category($from);
    $to_cat = get_unit_category($to);

    if ($from_cat === null || $to_cat === null || $from_cat !== $to_cat) {
        return null;
    }

    if ($from_cat === 'weight') {
        $grams = 0.0;
        if ($from === 'mg') $grams = $quantity * 0.001;
        elseif ($from === 'g') $grams = $quantity;
        elseif ($from === 'kg') $grams = $quantity * 1000;          // ✅ 1kg = 1000g
        elseif ($from === 'tons') $grams = $quantity * 1000000;
        elseif ($from === 'lbs') $grams = $quantity * 453.592;

        $result = 0.0;
        if ($to === 'mg') $result = $grams * 1000;
        elseif ($to === 'g') $result = $grams;
        elseif ($to === 'kg') $result = $grams / 1000;
        elseif ($to === 'tons') $result = $grams / 1000000;
        elseif ($to === 'lbs') $result = $grams / 453.592;

        return round($result, 6);
    }

    if ($from_cat === 'length') {
        $meters = 0.0;
        if ($from === 'mm') $meters = $quantity * 0.001;
        elseif ($from === 'cm') $meters = $quantity * 0.01;
        elseif ($from === 'm') $meters = $quantity;
        elseif ($from === 'km') $meters = $quantity * 1000;
        elseif ($from === 'inch') $meters = $quantity * 0.0254;
        elseif ($from === 'ft') $meters = $quantity * 0.3048;

        $result = 0.0;
        if ($to === 'mm') $result = $meters * 1000;
        elseif ($to === 'cm') $result = $meters * 100;
        elseif ($to === 'm') $result = $meters;
        elseif ($to === 'km') $result = $meters / 1000;
        elseif ($to === 'inch') $result = $meters / 0.0254;
        elseif ($to === 'ft') $result = $meters / 0.3048;

        return round($result, 6);
    }

    if ($from_cat === 'volume') {
        $ml = 0.0;
        if ($from === 'ml') $ml = $quantity;
        elseif ($from === 'l' || $from === 'ltr') $ml = $quantity * 1000;
        elseif ($from === 'gallon') $ml = $quantity * 3785.41;

        $result = 0.0;
        if ($to === 'ml') $result = $ml;
        elseif ($to === 'l' || $to === 'ltr') $result = $ml / 1000;
        elseif ($to === 'gallon') $result = $ml / 3785.41;

        return round($result, 6);
    }

    if ($from_cat === 'quantity') {
        $pieces = 0.0;
        if ($from === 'pcs') $pieces = $quantity;
        elseif ($from === 'dozen') $pieces = $quantity * 12;
        elseif ($from === 'box') $pieces = $quantity * 24;
        elseif ($from === 'pack') $pieces = $quantity * 10;
        elseif ($from === 'carton') $pieces = $quantity * 100;

        $result = 0.0;
        if ($to === 'pcs') $result = $pieces;
        elseif ($to === 'dozen') $result = $pieces / 12;
        elseif ($to === 'box') $result = $pieces / 24;
        elseif ($to === 'pack') $result = $pieces / 10;
        elseif ($to === 'carton') $result = $pieces / 100;

        return round($result, 6);
    }

    return null;
}

$convertible_units = [
    'weight' => ['mg', 'g', 'kg', 'tons', 'lbs'],
    'length' => ['mm', 'cm', 'm', 'km', 'inch', 'ft'],
    'volume' => ['ml', 'l', 'gallon'],
    'quantity' => ['pcs', 'dozen', 'box', 'pack', 'carton']
];

// ================================================================
// FETCH STOCK DATA
// ================================================================

$stock_data = db_fetch_all("
    -- Items tracked in inventory_stock_levels (main system)
    SELECT i.code,
           i.name as item_name,
           c.name as category_name,
           l.name as location_name,
           s.quantity_on_hand,
           s.quantity_reserved,
           s.quantity_available,
           i.reorder_level,
           COALESCE(u.code, i.unit_of_measure, 'PCS') as unit_code
    FROM inventory_stock_levels s
    JOIN inventory_items i ON s.item_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    JOIN locations l ON s.location_id = l.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE i.is_active = 1

    UNION

    -- Items that only exist in warehouse_inventory (not yet synced to stock_levels)
    SELECT i.code,
           i.name as item_name,
           c.name as category_name,
           w.name as location_name,
           SUM(wi.quantity) as quantity_on_hand,
           0 as quantity_reserved,
           SUM(wi.quantity) as quantity_available,
           i.reorder_level,
           COALESCE(u.code, i.unit_of_measure, 'PCS') as unit_code
    FROM warehouse_inventory wi
    JOIN inventory_items i ON wi.product_id = i.id
    LEFT JOIN categories c ON i.category_id = c.id
    JOIN warehouses w ON wi.warehouse_id = w.id
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id
    WHERE i.is_active = 1
      AND NOT EXISTS (
          SELECT 1 FROM inventory_stock_levels s2
          JOIN warehouses w2 ON s2.location_id = w2.location_id
          WHERE s2.item_id = wi.product_id AND w2.id = wi.warehouse_id
      )
    GROUP BY i.id, i.code, i.name, c.name, w.name, i.reorder_level, unit_code

    ORDER BY code, location_name
");

// Build a unique list of categories for the filter dropdown
$unique_categories = [];
if (!empty($stock_data)) {
    foreach ($stock_data as $row) {
        $cat = trim($row['category_name'] ?? '');
        if ($cat !== '' && !in_array($cat, $unique_categories)) {
            $unique_categories[] = $cat;
        }
    }
    sort($unique_categories);
}



// ================================================================
// AJAX REQUEST HANDLERS
// ================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert') {
    header('Content-Type: application/json');

    $quantity = (float)($_POST['quantity'] ?? 0);
    $from_unit = sanitize_input($_POST['from_unit'] ?? '');
    $to_unit = sanitize_input($_POST['to_unit'] ?? '');

    $converted = convert_unit($quantity, $from_unit, $to_unit);

    echo json_encode([
        'success' => $converted !== null,
        'result' => $converted,
        'from_unit' => $from_unit,
        'to_unit' => $to_unit,
        'original_quantity' => $quantity
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'convert_all') {
    header('Content-Type: application/json');

    $quantity = (float)($_POST['quantity'] ?? 0);
    $from_unit = sanitize_input($_POST['from_unit'] ?? '');

    $from_unit_norm = normalize_unit($from_unit);
    $cat = get_unit_category($from_unit_norm);

    if ($quantity <= 0 || $cat === null || !isset($convertible_units[$cat])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid quantity or unit.'
        ]);
        exit;
    }

    $results = [];
    foreach ($convertible_units[$cat] as $to_u) {
        $val = convert_unit($quantity, $from_unit_norm, $to_u);
        if ($val !== null) {
            $results[] = [
                'to_unit' => strtoupper($to_u),
                'value' => $val
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'category' => $cat,
        'from_unit' => strtoupper($from_unit_norm),
        'quantity' => $quantity,
        'results' => $results
    ]);
    exit;
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="row mb-4">
        <div class="col-md-7">
            <h2 class="mb-1 text-white"><i class="fas fa-boxes text-info me-2"></i>Stock Levels Management</h2>
            <p class="text-muted mb-0">Real-time inventory tracking with unit conversion</p>
        </div>

        <div class="col-md-5 text-end d-flex justify-content-end align-items-center gap-2">
            <!-- New Category Filter Dropdown -->
            <select id="categoryFilter" class="form-select form-select-sm bg-dark text-white border-secondary" style="max-width: 200px;">
                <option value="">All Categories</option>
                <?php foreach ($unique_categories as $cat): ?>
                    <option value="<?= escape_html($cat) ?>"><?= escape_html($cat) ?></option>
                <?php endforeach; ?>
                <option value="Uncategorized">Uncategorized</option>
            </select>

            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Print Report
            </button>
            <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-1"></i>Export
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card premium-card">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 text-info font-weight-bold"><i class="fas fa-warehouse me-2"></i>Current Stock Levels</h5>

                    <button type="button" id="toggleConverterBtn" class="btn btn-info btn-sm text-dark fw-bold">
                        <i class="fas fa-sliders-h me-1"></i>Converter
                    </button>
                </div>

                <div class="card-body">

                    <div id="converterPanel" class="mb-3 d-none">
                        <div id="converterToolbar" class="p-3">
                            <div class="d-flex flex-wrap align-items-end gap-2">

                                <div style="min-width: 200px;">
                                    <label class="form-label small fw-semibold mb-1">From Unit</label>
                                    <select id="tableFromUnit" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        <optgroup label="Weight">
                                            <option value="mg">mg</option>
                                            <option value="g">g</option>
                                            <option value="kg">kg</option>
                                            <option value="tons">tons</option>
                                            <option value="lbs">lbs</option>
                                        </optgroup>
                                        <optgroup label="Length">
                                            <option value="mm">mm</option>
                                            <option value="cm">cm</option>
                                            <option value="m">m</option>
                                            <option value="km">km</option>
                                            <option value="inch">inch</option>
                                            <option value="ft">ft</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="ml">ml</option>
                                            <option value="l">l</option>
                                            <option value="gallon">gallon</option>
                                        </optgroup>
                                        <optgroup label="Quantity">
                                            <option value="pcs">pcs</option>
                                            <option value="dozen">dozen</option>
                                            <option value="box">box</option>
                                            <option value="pack">pack</option>
                                            <option value="carton">carton</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div style="min-width: 200px;">
                                    <label class="form-label small fw-semibold mb-1">To Unit</label>
                                    <select id="tableToUnit" class="form-select form-select-sm">
                                        <option value="">Select</option>
                                        <optgroup label="Weight">
                                            <option value="mg">mg</option>
                                            <option value="g">g</option>
                                            <option value="kg">kg</option>
                                            <option value="tons">tons</option>
                                            <option value="lbs">lbs</option>
                                        </optgroup>
                                        <optgroup label="Length">
                                            <option value="mm">mm</option>
                                            <option value="cm">cm</option>
                                            <option value="m">m</option>
                                            <option value="km">km</option>
                                            <option value="inch">inch</option>
                                            <option value="ft">ft</option>
                                        </optgroup>
                                        <optgroup label="Volume">
                                            <option value="ml">ml</option>
                                            <option value="l">l</option>
                                            <option value="gallon">gallon</option>
                                        </optgroup>
                                        <optgroup label="Quantity">
                                            <option value="pcs">pcs</option>
                                            <option value="dozen">dozen</option>
                                            <option value="box">box</option>
                                            <option value="pack">pack</option>
                                            <option value="carton">carton</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="ms-auto d-flex gap-2">
                                    <button id="toggleCalcBtn" type="button" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-calculator me-1"></i>Units
                                    </button>

                                    <button id="convertTableBtn" class="btn btn-info btn-sm text-dark fw-bold">
                                        <i class="fas fa-sync-alt me-1"></i>Convert
                                    </button>

                                    <button id="resetTableBtn" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-undo me-1"></i>Reset
                                    </button>
                                </div>

                                <div class="w-100"></div>
                                <div id="unitCalcPanel" class="w-100 d-none mt-2">
                                    <div class="unit-calc-box p-3">
                                        <div class="d-flex flex-wrap align-items-end gap-2">
                                            <div style="min-width: 180px;">
                                                <label class="form-label small fw-semibold mb-1">Quantity</label>
                                                <input id="calcQty" type="number" step="any" class="form-control form-control-sm" placeholder="e.g. 1">
                                            </div>

                                            <div style="min-width: 200px;">
                                                <label class="form-label small fw-semibold mb-1">From</label>
                                                <select id="calcFromUnit" class="form-select form-select-sm">
                                                    <option value="">Select</option>
                                                    <optgroup label="Weight">
                                                        <option value="mg">mg</option>
                                                        <option value="g">g</option>
                                                        <option value="kg">kg</option>
                                                        <option value="tons">tons</option>
                                                        <option value="lbs">lbs</option>
                                                    </optgroup>
                                                    <optgroup label="Length">
                                                        <option value="mm">mm</option>
                                                        <option value="cm">cm</option>
                                                        <option value="m">m</option>
                                                        <option value="km">km</option>
                                                        <option value="inch">inch</option>
                                                        <option value="ft">ft</option>
                                                    </optgroup>
                                                    <optgroup label="Volume">
                                                        <option value="ml">ml</option>
                                                        <option value="l">l</option>
                                                        <option value="gallon">gallon</option>
                                                    </optgroup>
                                                    <optgroup label="Quantity">
                                                        <option value="pcs">pcs</option>
                                                        <option value="dozen">dozen</option>
                                                        <option value="box">box</option>
                                                        <option value="pack">pack</option>
                                                        <option value="carton">carton</option>
                                                    </optgroup>
                                                </select>
                                            </div>

                                            <div style="min-width: 200px;">
                                                <label class="form-label small fw-semibold mb-1">To</label>
                                                <select id="calcToUnit" class="form-select form-select-sm">
                                                    <option value="">Select</option>
                                                    <optgroup label="Weight">
                                                        <option value="mg">mg</option>
                                                        <option value="g">g</option>
                                                        <option value="kg">kg</option>
                                                        <option value="tons">tons</option>
                                                        <option value="lbs">lbs</option>
                                                    </optgroup>
                                                    <optgroup label="Length">
                                                        <option value="mm">mm</option>
                                                        <option value="cm">cm</option>
                                                        <option value="m">m</option>
                                                        <option value="km">km</option>
                                                        <option value="inch">inch</option>
                                                        <option value="ft">ft</option>
                                                    </optgroup>
                                                    <optgroup label="Volume">
                                                        <option value="ml">ml</option>
                                                        <option value="l">l</option>
                                                        <option value="gallon">gallon</option>
                                                    </optgroup>
                                                    <optgroup label="Quantity">
                                                        <option value="pcs">pcs</option>
                                                        <option value="dozen">dozen</option>
                                                        <option value="box">box</option>
                                                        <option value="pack">pack</option>
                                                        <option value="carton">carton</option>
                                                    </optgroup>
                                                </select>
                                            </div>

                                            <div class="ms-auto d-flex gap-2">
                                                <button id="calcDoBtn" type="button" class="btn btn-info btn-sm">
                                                    <i class="fas fa-equals me-1"></i>Calculate
                                                </button>
                                                <button id="calcClearBtn" type="button" class="btn btn-outline-light btn-sm">
                                                    <i class="fas fa-broom me-1"></i>Clear
                                                </button>
                                            </div>

                                            <div class="w-100"></div>
                                            <div class="calc-result-line">
                                                <span class="calc-label"><i class="fas fa-bolt me-1"></i>Result:</span>
                                                <span id="calcResultText" class="calc-result-text">—</span>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                Example: <span class="text-info">1 KG → 1000 G</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-100"></div>

                                <div id="conversionStatus" class="alert alert-dismissible fade d-none mt-2 py-2 px-2 mb-0 w-100" role="alert">
                                    <small id="statusText"></small>
                                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                                </div>

                                <div class="small text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Note: This conversion is display-only (no database changes).
                                </div>

                            </div>
                        </div>
                    </div>

                    <?php if (!empty($stock_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0" id="stockTable">
                                <thead>
                                    <tr>
                                        <th class="fw-semibold">Code</th>
                                        <th class="fw-semibold">Item Name</th>
                                        <th class="fw-semibold">Category</th>
                                        <th class="fw-semibold">Location</th>
                                        <th class="fw-semibold text-end">On Hand</th>
                                        <th class="fw-semibold text-end">Reserved</th>
                                        <th class="fw-semibold text-end">Available</th>
                                        <th class="fw-semibold text-center">Status</th>
                                        <th class="fw-semibold text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stock_data as $stock): ?>
                                        <tr>
                                            <td><span class="badge bg-secondary"><?= escape_html($stock['code']) ?></span></td>
                                            <td class="fw-medium"><?= escape_html($stock['item_name']) ?></td>
                                            <td><small class="text-muted"><i class="fas fa-tags me-1"></i><?= escape_html($stock['category_name'] ?: 'Uncategorized') ?></small></td>
                                            <td><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i><?= escape_html($stock['location_name']) ?></small></td>
                                            <td class="text-end"><?= format_number($stock['quantity_on_hand'], 2) ?> <small class="text-muted"><?= escape_html($stock['unit_code']) ?></small></td>
                                            <td class="text-end"><?= format_number($stock['quantity_reserved'], 2) ?> <small class="text-muted"><?= escape_html($stock['unit_code']) ?></small></td>
                                            <td class="text-end"><strong><?= format_number($stock['quantity_available'], 2) ?></strong> <small class="text-muted"><?= escape_html($stock['unit_code']) ?></small></td>
                                            <td class="text-center">
                                                <?php if ($stock['quantity_available'] < $stock['reorder_level'] && $stock['reorder_level'] > 0): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock</span>
                                                <?php elseif ($stock['quantity_available'] < ($stock['reorder_level'] * 1.5) && $stock['reorder_level'] > 0): ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-circle me-1"></i>Warning</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Good</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="reorder.php?add_item=<?= urlencode($stock['code']) ?>" class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-clipboard-list me-1"></i>Reorder Report
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Stock Data Available</h5>
                            <p class="text-secondary">Start adding items to your inventory</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = <<<'JAVASCRIPT'
<script>
$(document).ready(function() {

    const stockTable = $('#stockTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 25,
        language: {
            search: "Search stock:",
            lengthMenu: "Show _MENU_ items",
            info: "Showing _START_ to _END_ of _TOTAL_ items",
            emptyTable: "No stock data available"
        },
        responsive: true,
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip'
    });

    // Custom filtering for Category dropdown
    $('#categoryFilter').on('change', function() {
        const selectedVal = $(this).val();
        
        // Use DataTables column(2) to filter with exact match using Regex
        if(selectedVal) {
            // Apply regex for exact match within the cell text
            stockTable.column(2).search('^.*?' + $.fn.dataTable.util.escapeRegex(selectedVal) + '.*?$', true, false).draw();
        } else {
            stockTable.column(2).search('').draw();
        }
    });

    // TOGGLE CONVERTER
    const $panel = $('#converterPanel');
    const $btn = $('#toggleConverterBtn');

    function setBtn(open) {
        if (open) {
            $btn.removeClass('btn-primary').addClass('btn-outline-primary');
            $btn.html('<i class="fas fa-times me-1"></i>Close');
        } else {
            $btn.removeClass('btn-outline-primary').addClass('btn-primary');
            $btn.html('<i class="fas fa-sliders-h me-1"></i>Converter');
        }
    }

    $btn.on('click', function() {
        const isOpen = !$panel.hasClass('d-none');
        if (isOpen) {
            $panel.addClass('d-none');
            setBtn(false);

            // also close calculator when main converter closes
            $('#unitCalcPanel').addClass('d-none');
            $('#toggleCalcBtn').removeClass('active');
            $('#calcResultText').text('—');
        } else {
            $panel.removeClass('d-none');
            setBtn(true);
            setTimeout(function() {
                stockTable.columns.adjust().draw(false);
            }, 50);
        }
    });

    setBtn(false);

    const statusDiv = $('#conversionStatus');
    const statusText = $('#statusText');

    function showStatus(type, message) {
        statusDiv.removeClass('d-none alert-success alert-danger alert-warning alert-info show')
            .addClass('alert-' + type + ' show');
        statusText.text(message);
        setTimeout(() => statusDiv.removeClass('show').addClass('d-none'), 4000);
    }

    function parseQuantityUnit(cellText) {
        const cleaned = (cellText || '')
            .replace(/<[^>]*>/g, '')
            .replace(/,/g, '')
            .replace(/\s+/g, ' ')
            .trim();

        const match = cleaned.match(/^(-?[\d.]+)\s+([A-Za-z]+)$/);
        if (!match) return null;

        return {
            quantity: parseFloat(match[1]),
            unit: match[2]
        };
    }

    function storeOriginalIfMissing($cell) {
        if (!$cell.attr('data-orig-html')) {
            $cell.attr('data-orig-html', $cell.html());
        }
    }

    // ✅ FULL TABLE restore (removes old conversion)
    function restoreAllConvertedRows() {
        const allNodes = stockTable.rows().nodes();
        $(allNodes).each(function () {
            const $row = $(this);
            [4, 5, 6].forEach(function (idx) {
                const $cell = $row.find('td').eq(idx);
                const origHtml = $cell.attr('data-orig-html');
                if (origHtml) $cell.html(origHtml);
            });
        });
    }

    // ✅ parse using original html if present
    function parseCellUsingOriginal($cell) {
        const orig = $cell.attr('data-orig-html');
        const txt = orig ? $('<div>').html(orig).text() : $cell.text();
        return parseQuantityUnit(txt);
    }

    // CONVERT TABLE
    $('#convertTableBtn').click(function() {
        const fromUnit = ($('#tableFromUnit').val() || '').toLowerCase();
        const toUnit = ($('#tableToUnit').val() || '').toLowerCase();

        if (!fromUnit || !toUnit) {
            showStatus('warning', 'Please select both From and To units');
            return;
        }

        if (fromUnit === toUnit) {
            showStatus('info', 'Source and target units are identical');
            return;
        }

        // ✅ remove old conversion first
        restoreAllConvertedRows();

        const nodes = stockTable.rows({ search: 'applied' }).nodes();
        const conversionQueue = [];

        $(nodes).each(function() {
            const $row = $(this);

            const onHandCell = $row.find('td').eq(4);
            const reservedCell = $row.find('td').eq(5);
            const availableCell = $row.find('td').eq(6);

            const onHandData = parseCellUsingOriginal(onHandCell);
            if (!onHandData || isNaN(onHandData.quantity)) return;
            if ((onHandData.unit || '').toLowerCase() !== fromUnit) return;

            const reservedData = parseCellUsingOriginal(reservedCell) || { quantity: 0, unit: onHandData.unit };
            const availableData = parseCellUsingOriginal(availableCell) || { quantity: 0, unit: onHandData.unit };

            storeOriginalIfMissing(onHandCell);
            storeOriginalIfMissing(reservedCell);
            storeOriginalIfMissing(availableCell);

            conversionQueue.push({
                onHandCell,
                reservedCell,
                availableCell,
                onHandQty: onHandData.quantity,
                reservedQty: isNaN(reservedData.quantity) ? 0 : reservedData.quantity,
                availableQty: isNaN(availableData.quantity) ? 0 : availableData.quantity
            });
        });

        if (conversionQueue.length === 0) {
            showStatus('warning', `No rows found with unit: ${fromUnit.toUpperCase()} (current filter/search)`);
            return;
        }

        let completed = 0;
        let successCount = 0;

        conversionQueue.forEach(function(item) {
            $.post(window.location.href, {
                action: 'convert',
                quantity: item.onHandQty,
                from_unit: fromUnit,
                to_unit: toUnit
            }, function(response) {
                if (response && response.success && response.result !== null) {
                    const convertedOnHand = parseFloat(response.result);
                    const conversionFactor = (item.onHandQty !== 0) ? (convertedOnHand / item.onHandQty) : 0;

                    item.onHandCell.html(`${convertedOnHand.toFixed(2)} <small class="text-muted">${toUnit.toUpperCase()}</small>`);
                    item.reservedCell.html(`${(item.reservedQty * conversionFactor).toFixed(2)} <small class="text-muted">${toUnit.toUpperCase()}</small>`);
                    item.availableCell.html(`<strong>${(item.availableQty * conversionFactor).toFixed(2)}</strong> <small class="text-muted">${toUnit.toUpperCase()}</small>`);

                    successCount++;
                }

                completed++;
                if (completed === conversionQueue.length) {
                    if (successCount > 0) {
                        showStatus('success', `✓ Converted ${successCount} row(s) from ${fromUnit.toUpperCase()} to ${toUnit.toUpperCase()} (display only)`);
                        stockTable.columns.adjust().draw(false);
                    } else {
                        showStatus('danger', 'Conversion failed. Units may be incompatible.');
                    }
                }
            }, 'json').fail(function() {
                completed++;
                if (completed === conversionQueue.length) {
                    showStatus('danger', 'Server error during conversion');
                }
            });
        });
    });

    // ✅ RESET TABLE (full table reset)
    $('#resetTableBtn').click(function() {
        restoreAllConvertedRows();
        showStatus('info', '↩ Table reset to original (display only)');
        stockTable.columns.adjust().draw(false);
    });

    // Units calculator toggle
    $('#toggleCalcBtn').on('click', function() {
        $('#unitCalcPanel').toggleClass('d-none');
        $(this).toggleClass('active');
        setTimeout(function() {
            stockTable.columns.adjust().draw(false);
        }, 50);
    });

    // Calculator logic (✅ calculates + converts table too)
    function runCalc() {
        const qty = parseFloat($('#calcQty').val());
        const fromU = ($('#calcFromUnit').val() || '').toLowerCase();
        const toU = ($('#calcToUnit').val() || '').toLowerCase();

        if (isNaN(qty) || qty === 0) {
            $('#calcResultText').text('Enter quantity (e.g. 1)');
            return;
        }
        if (!fromU || !toU) {
            $('#calcResultText').text('Select From and To units');
            return;
        }

        $.post(window.location.href, {
            action: 'convert',
            quantity: qty,
            from_unit: fromU,
            to_unit: toU
        }, function(res) {
            if (res && res.success && res.result !== null) {
                const val = parseFloat(res.result);
                $('#calcResultText').text(`${qty} ${fromU.toUpperCase()} = ${val} ${toU.toUpperCase()}`);

                // ✅ APPLY SAME conversion to TABLE + remove old conversion
                restoreAllConvertedRows();
                $('#tableFromUnit').val(fromU);
                $('#tableToUnit').val(toU);
                $('#convertTableBtn').trigger('click');

            } else {
                $('#calcResultText').text('Invalid / incompatible units');
            }
        }, 'json').fail(function() {
            $('#calcResultText').text('Server error');
        });
    }

    $('#calcDoBtn').on('click', runCalc);

    $('#calcClearBtn').on('click', function() {
        $('#calcQty').val('');
        $('#calcFromUnit').val('');
        $('#calcToUnit').val('');
        $('#calcResultText').text('—');
    });

    $('#calcQty, #calcFromUnit, #calcToUnit').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            runCalc();
        }
    });

    $('#tableFromUnit, #tableToUnit').keypress(function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#convertTableBtn').trigger('click');
        }
    });

});

function exportToExcel() {
    alert('Export feature - To be implemented based on project requirements');
}
</script>

<style>
/* Base */
.card {
    border-radius: 12px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    transition: all 0.25s ease;
}
.card:hover {
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08) !important;
    transform: translateY(-1px);
}

/* Dark header strip */
.stock-header{
    background:#0b1220 !important;
    color:#e5e7eb !important;
    border-bottom:1px solid rgba(255,255,255,.10) !important;
}
.stock-header h5,
.stock-header i{ color:#e5e7eb !important; }

/* DataTables top bar */
.dataTables_wrapper .row:first-child{
    background:#0b1220 !important;
    border:1px solid rgba(255,255,255,.10) !important;
    border-radius:12px !important;
    padding:12px 10px !important;
    margin:0 0 12px 0 !important;
}
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_length label,
.dataTables_wrapper .dataTables_filter label{
    color:#e5e7eb !important;
    font-weight:600 !important;
}
.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input{
    background:rgba(15,23,42,.85) !important;
    color:#e5e7eb !important;
    border:1px solid rgba(255,255,255,.14) !important;
    border-radius:10px !important;
}
.dataTables_wrapper .dataTables_filter input::placeholder{
    color:rgba(229,231,235,.55) !important;
}

/* Dark table header */
.stock-thead th{
    background:#0b1220 !important;
    color:#e5e7eb !important;
    border-bottom:1px solid rgba(255,255,255,.10) !important;
}
.table tbody tr:hover { background: rgba(37, 99, 235, 0.04); }

.badge { padding: 0.35em 0.65em; font-weight: 600; }

/* Dark Converter Panel */
#converterToolbar{
    background:linear-gradient(180deg,#0f172a 0%, #0b1220 100%) !important;
    border:1px solid rgba(255,255,255,.10) !important;
    border-radius:12px !important;
    box-shadow:0 14px 28px rgba(143, 140, 140, 0.17) !important;
}
#converterToolbar .form-label{
    color:rgba(229,231,235,.92) !important;
    font-weight:700 !important;
}
#converterToolbar .form-select-sm{
    height: 34px;
    border-radius:10px;
    background:rgba(15,23,42,.85) !important;
    color:#e5e7eb !important;
    border:1px solid rgba(255,255,255,.14) !important;
}
#converterToolbar .form-select-sm option,
#converterToolbar .form-select-sm optgroup{
    background:#0b1220 !important;
    color:#e5e7eb !important;
}
#converterToolbar .text-muted,
#converterToolbar small.text-muted{
    color:rgba(229,231,235,.65) !important;
}
#converterToolbar .btn-sm{
    height: 34px;
    padding: 0 12px;
    border-radius: 10px;
    font-weight: 700;
}

/* Unit Calculator Box */
.unit-calc-box{
    border:1px solid rgba(255,255,255,.10);
    border-radius:12px;
    background:rgba(2,6,23,.35);
}
.unit-calc-box .form-control{
    height:34px;
    border-radius:10px;
    background:rgba(15,23,42,.85) !important;
    color:#e5e7eb !important;
    border:1px solid rgba(255,255,255,.14) !important;
}
.unit-calc-box .form-control::placeholder{
    color:rgba(229,231,235,.55) !important;
}
.calc-result-line{
    display:flex;
    gap:10px;
    align-items:center;
    padding:10px 12px;
    border-radius:10px;
    border:1px dashed rgba(255,255,255,.14);
    background:rgba(15,23,42,.45);
}
.calc-label{ color:rgba(229,231,235,.80); font-weight:700; }
.calc-result-text{ color:#e5e7eb; font-weight:800; letter-spacing:.2px; }

/* Alerts */
#conversionStatus {
    border-radius: 10px;
    border: 1px solid rgba(15, 23, 42, 0.10);
    box-shadow: 0 8px 18px rgba(2, 6, 23, 0.06);
}
.alert { border-left: 4px solid; }
.alert-info { border-left-color: #0ea5e9; }
.alert-success { border-left-color: #22c55e; }
.alert-warning { border-left-color: #f59e0b; }
.alert-danger { border-left-color: #ef4444; }

/* Print */
@media print {
    .card-header, .btn, #conversionStatus, #converterPanel, #toggleConverterBtn { display: none !important; }
    .table { font-size: 10pt; }
}
</style>
JAVASCRIPT;

include __DIR__ . '/../../templates/footer.php';
?>


