<?php
/**
 * AJAX Handler for Production Report
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

ini_set('display_errors', '0');
ob_start();

if (!is_logged_in()) {
    http_response_code(401);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

if (!has_permission('view_reports')) {
    http_response_code(403);
    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    ensure_inventory_transaction_reporting_schema();

    // 1. Parse Filters
    $company_id = active_company_id(1);
    $company = $_GET['company'] ?? '';
    $location_id = intval($_GET['location_id'] ?? 0);
    $category_id = intval($_GET['category_id'] ?? 0);
    $product_id = intval($_GET['product_id'] ?? 0);
    $order_no = $_GET['order_no'] ?? '';
    $date_from = $_GET['date_from'] ?? date('Y-m-01');
    $date_to = $_GET['date_to'] ?? date('Y-m-t');
    $status = $_GET['status'] ?? '';

    // Build base WHERE clause for Work Orders
    $whereParams = [$company_id];
    $whereSql = "i.company_id = ?";

    if ($location_id > 0) {
        $whereSql .= " AND wo.location_id = ?";
        $whereParams[] = $location_id;
    }
    
    // Category mapping requires joining inventory_items
    if ($category_id > 0) {
        $whereSql .= " AND i.category_id = ?";
        $whereParams[] = $category_id;
    }

    if ($product_id > 0) {
        $whereSql .= " AND wo.product_id = ?";
        $whereParams[] = $product_id;
    }

    if (!empty($order_no)) {
        $whereSql .= " AND wo.wo_number LIKE ?";
        $whereParams[] = "%{$order_no}%";
    }

    if (!empty($status)) {
        $whereSql .= " AND wo.status = ?";
        $whereParams[] = $status;
    }

    if (!empty($date_from)) {
        // Use created_at or due_date? Let's use created_at for filtering by date range
        $whereSql .= " AND DATE(wo.created_at) >= ?";
        $whereParams[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $whereSql .= " AND DATE(wo.created_at) <= ?";
        $whereParams[] = $date_to;
    }

    // ─── 2. Fetch Orders Data ───
    $ordersSql = "
        SELECT wo.*, 
               i.code as product_code, i.name as product_name, c.name as cat_name
        FROM work_orders wo
        JOIN inventory_items i ON wo.product_id = i.id
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE $whereSql
        ORDER BY wo.created_at DESC
    ";
    $rawOrders = db_fetch_all($ordersSql, $whereParams);

    $dashboard = [
        'totalOrders' => 0,
        'totalUnits' => 0,
        'totalCost' => 0.0,
        'avgCost' => 0.0,
        'pendingOrders' => 0,
        'overallEfficiency' => 0.0
    ];

    $orders = [];
    $history = [];
    $efficiencyData = [];
    $totalPlanned = 0;
    $totalActual = 0;

    // Track Chart Data by Month (YYYY-MM)
    $chartVolumes = [];
    $chartCosts = [];

    // Track Cost Analysis by Product
    $costAnalysisDict = [];

    $wo_ids = [];

    foreach ($rawOrders as $o) {
        $wo_ids[] = $o['id'];
        
        $planned = intval($o['quantity']);
        $produced = ($o['status'] === 'completed') ? $planned : 0; // standard assumption if not tracked partially
        $remaining = $planned - $produced;
        
        $total_snapshot_cost = floatval($o['total_cost'] ?: 0);
        $unit_cost = ($planned > 0) ? ($total_snapshot_cost / $planned) : 0;
        $total_cost = $unit_cost * $planned;

        $dashboard['totalOrders']++;
        $dashboard['totalUnits'] += $produced;
        $dashboard['totalCost'] += ($unit_cost * $produced); // cost of produced units

        if ($o['status'] !== 'completed' && $o['status'] !== 'cancelled') {
            $dashboard['pendingOrders']++;
        }

        $totalPlanned += $planned;
        $totalActual += $produced;

        // Chart tracking
        $month = date('Y-m', strtotime($o['created_at']));
        if (!isset($chartVolumes[$month])) {
            $chartVolumes[$month] = 0;
            $chartCosts[$month] = ['total_cost'=>0, 'units'=>0];
        }
        $chartVolumes[$month] += $produced;
        if ($produced > 0) {
            $chartCosts[$month]['total_cost'] += ($unit_cost * $produced);
            $chartCosts[$month]['units'] += $produced;
        }

        $orders[] = [
            'wo_number' => $o['wo_number'],
            'product' => escape_html($o['product_code'] . ' - ' . $o['product_name']),
            'category' => escape_html($o['cat_name'] ?? '-'),
            'planned_qty' => $planned,
            'produced_qty' => $produced,
            'remaining_qty' => $remaining,
            'total_cost' => $total_cost,
            'start_date' => $o['start_date'] ? date('d-m-Y', strtotime($o['start_date'])) : '',
            'completion_date' => $o['completion_date'] ? date('d-m-Y', strtotime($o['completion_date'])) : '',
            'status' => $o['status']
        ];

        // Efficiency track per product
        $pid = $o['product_id'];
        if (!isset($efficiencyData[$pid])) {
            $efficiencyData[$pid] = [
                'name' => escape_html($o['product_name']),
                'planned' => 0,
                'actual' => 0
            ];
        }
        $efficiencyData[$pid]['planned'] += $planned;
        $efficiencyData[$pid]['actual'] += $produced;

        // History Track
        if ($o['status'] === 'completed') {
            $history[] = [
                'completion_ref' => 'COMP-' . $o['id'],
                'wo_number' => $o['wo_number'],
                'product' => escape_html($o['product_name']),
                'qty_produced' => $produced,
                'completion_date' => date('d-m-Y', strtotime($o['completion_date'])),
                'supervisor' => '-' // You'd join users table if needed
            ];
        }

        // Cost Analysis prep
        if (!isset($costAnalysisDict[$pid])) {
            // Find bom for this product
            $boms = db_fetch("SELECT * FROM bom_headers WHERE product_id = ? ORDER BY id DESC LIMIT 1", [$pid]);
            $costAnalysisDict[$pid] = [
                'product' => escape_html($o['product_name']),
                'material_cost' => $boms ? floatval($boms['total_material_cost']) : 0,
                'labor_cost' => $boms ? floatval($boms['labor_cost']) : 0,
                'electricity_cost' => $boms ? floatval($boms['electricity_cost']) : 0,
                'machine_cost' => $boms ? floatval($boms['machine_cost'] ?? $boms['machine_usage_cost'] ?? 0) : 0,
                'total_cost' => $boms ? floatval($boms['total_production_cost']) : 0,
            ];
        }
    }

    if ($dashboard['totalUnits'] > 0) {
        $dashboard['avgCost'] = $dashboard['totalCost'] / $dashboard['totalUnits'];
    }
    if ($totalPlanned > 0) {
        $dashboard['overallEfficiency'] = ($totalActual / $totalPlanned) * 100;
    }

    // ─── 3. Compile Efficiency Data ───
    $efficiencyRet = [];
    foreach ($efficiencyData as $e) {
        $variance = $e['actual'] - $e['planned'];
        $pct = $e['planned'] > 0 ? ($e['actual'] / $e['planned']) * 100 : 0;
        $efficiencyRet[] = [
            'product' => $e['name'],
            'planned' => $e['planned'],
            'actual' => $e['actual'],
            'variance' => $variance,
            'eff_percent' => $pct
        ];
    }

    // ─── 4. Compile Cost Data ───
    $costRet = [];
    foreach ($costAnalysisDict as $c) {
        $c['avg_cost'] = $c['total_cost']; // Unit avg cost
        $costRet[] = $c;
    }

    // ─── 5. Raw Material Consumption ───
    // Query inventory_transactions linked to these WOs
    $materials = [];
    if (!empty($wo_ids)) {
        $placeholders = implode(',', array_fill(0, count($wo_ids), '?'));
        $matSql = "
            SELECT it.item_id, SUM(ABS(it.quantity_signed)) as qty_used, SUM(ABS(it.quantity_signed) * it.unit_cost) as total_cost,
                   i.code, i.name, c.name as category_name, i.unit_of_measure,
                   GROUP_CONCAT(DISTINCT wo.wo_number SEPARATOR ', ') as linked_wos
            FROM inventory_transactions it
            JOIN inventory_items i ON it.item_id = i.id
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN work_orders wo ON it.reference_id = wo.id
            WHERE it.reference_type = 'work_order' AND it.transaction_type LIKE '%issue%' AND it.reference_id IN ($placeholders)
            GROUP BY it.item_id
        ";
        $rawMats = db_fetch_all($matSql, $wo_ids);
        foreach ($rawMats as $m) {
            $materials[] = [
                'code' => escape_html($m['code']),
                'name' => escape_html($m['name']),
                'category' => escape_html($m['category_name']),
                'qty_used' => floatval($m['qty_used']),
                'unit' => escape_html($m['unit_of_measure']),
                'total_cost' => floatval($m['total_cost']),
                'linked_products' => escape_html($m['linked_wos'])
            ];
        }
    }

    // ─── 6. Prepare Chart Data ───
    ksort($chartVolumes);
    $chartLabels = [];
    $chartVols = [];
    $chartCostTrend = [];

    foreach ($chartVolumes as $m => $v) {
        $chartLabels[] = $m;
        $chartVols[] = $v;
        $avg = $chartCosts[$m]['units'] > 0 ? ($chartCosts[$m]['total_cost'] / $chartCosts[$m]['units']) : 0;
        $chartCostTrend[] = round($avg, 2);
    }

    $charts = [
        'labels' => $chartLabels,
        'volumes' => $chartVols,
        'costs' => $chartCostTrend
    ];

    $response = [
        'success' => true,
        'dashboard' => $dashboard,
        'orders' => $orders,
        'materials' => $materials,
        'costs' => $costRet,
        'efficiency' => $efficiencyRet,
        'history' => $history,
        'charts' => $charts
    ];

    if (ob_get_level() > 0) {
        ob_clean();
    }
    echo json_encode($response);

} catch (Throwable $e) {
    $bufferedOutput = '';
    if (ob_get_level() > 0) {
        $bufferedOutput = trim(ob_get_contents());
        ob_clean();
    }
    if ($bufferedOutput !== '') {
        log_error('Production report AJAX buffered output: ' . $bufferedOutput, 'php_server.log');
    }
    log_error('Production report AJAX failed: ' . $e->getMessage(), 'php_server.log');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
