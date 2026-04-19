<?php
/**
 * View / Approve Stock Take
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('Stock take session not found.', 'error');
    redirect('index.php');
}

$st = db_fetch("
    SELECT st.*, l.name as location_name, l.code as location_code,
           u.username as creator_name, app.username as approver_name
    FROM stock_take_headers st
    JOIN locations l ON st.location_id = l.id
    LEFT JOIN users u ON st.created_by = u.id
    LEFT JOIN users app ON st.approved_by = app.id
    WHERE st.id = ?
", [$id]);

if (!$st) {
    set_flash('Stock take session not found.', 'error');
    redirect('index.php');
}

$is_manager_pending = has_role('manager') && $st['status'] === 'pending_approval';

// Handle Count-2 Save / Approval / Rejection
if (is_post() && has_role('manager')) {
    $action = post('action');
    $notes = post('approval_notes');
    $user_id = current_user_id();
    $now = date('Y-m-d H:i:s');
    $redirect_after_post = 'view.php?id=' . $id;

    try {
        db_begin_transaction();

        if ($action === 'save_count2') {
            $physical_counts = $_POST['physical_qty2'] ?? [];
            $count1_snapshot_row = db_fetch("
                SELECT notes
                FROM stock_take_history
                WHERE stock_take_id = ? AND notes LIKE 'COUNT1_JSON::%'
                ORDER BY id ASC
                LIMIT 1
            ", [$id]);
            $count1_map_from_snapshot = [];
            if ($count1_snapshot_row && !empty($count1_snapshot_row['notes'])) {
                $payload = json_decode(base64_decode(substr($count1_snapshot_row['notes'], strlen('COUNT1_JSON::'))), true);
                if (is_array($payload) && !empty($payload['items']) && is_array($payload['items'])) {
                    foreach ($payload['items'] as $snap_item) {
                        if (!empty($snap_item['code'])) {
                            $count1_map_from_snapshot[$snap_item['code']] = floatval($snap_item['physical_qty'] ?? 0);
                        }
                    }
                }
            }
            $existing_items = db_fetch_all("
                SELECT sti.item_id, sti.system_qty, sti.physical_qty, sti.unit_cost, ii.code
                FROM stock_take_items sti
                JOIN inventory_items ii ON ii.id = sti.item_id
                WHERE sti.stock_take_id = ?
            ", [$id]);

            $count1_lines = [];
            $count2_lines = [];
            $count2_snapshot_items = [];

            foreach ($existing_items as $item) {
                $item_id = (string) $item['item_id'];
                $count1_qty = array_key_exists($item['code'], $count1_map_from_snapshot)
                    ? floatval($count1_map_from_snapshot[$item['code']])
                    : floatval($item['physical_qty']);
                $count2_qty = $count1_qty;

                if (array_key_exists($item_id, $physical_counts) && $physical_counts[$item_id] !== '' && $physical_counts[$item_id] !== null) {
                    $count2_qty = floatval($physical_counts[$item_id]);
                }

                $variance = $count2_qty - floatval($item['system_qty']);
                $total_variance_value = $variance * floatval($item['unit_cost']);

                db_query("
                    UPDATE stock_take_items
                    SET physical_qty = ?, variance = ?, total_variance_value = ?
                    WHERE stock_take_id = ? AND item_id = ?
                ", [$count2_qty, $variance, $total_variance_value, $id, $item['item_id']]);

                $count1_lines[] = $item['code'] . '=' . number_format($count1_qty, 2, '.', '');
                $count2_lines[] = $item['code'] . '=' . number_format($count2_qty, 2, '.', '');
                $count2_snapshot_items[] = [
                    'item_id' => $item['item_id'],
                    'code' => $item['code'],
                    'system_qty' => floatval($item['system_qty']),
                    'physical_qty' => $count2_qty,
                    'variance' => $variance,
                    'total_variance_value' => $total_variance_value
                ];
            }

            $count2_payload = [
                'run' => 2,
                'saved_at' => date('Y-m-d H:i:s'),
                'items' => $count2_snapshot_items,
                'totals' => [
                    'total_variance_value' => array_reduce($count2_snapshot_items, function ($carry, $row) {
                        return $carry + floatval($row['total_variance_value']);
                    }, 0.0)
                ]
            ];

            db_query("INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by) VALUES (?, 'pending_approval', ?, ?)", [
                $id,
                'COUNT2_JSON::' . base64_encode(json_encode($count2_payload)),
                $user_id
            ]);

            db_query("INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by) VALUES (?, 'pending_approval', ?, ?)", [
                $id,
                'Count 2 saved by manager. Count 1: ' . implode(', ', $count1_lines) . ' | Count 2: ' . implode(', ', $count2_lines),
                $user_id
            ]);

            db_commit();
            set_flash('Count 2 saved successfully. Count 1 details stored in history.', 'success');
            $redirect_after_post = 'index.php';
        } elseif ($action === 'approve') {
            // 1. Update Status
            db_query("UPDATE stock_take_headers SET status = 'approved', approved_by = ?, approved_at = ? WHERE id = ?", [
                $user_id,
                $now,
                $id
            ]);

            // 2. Process Variances and Movements
            $items = db_fetch_all("SELECT * FROM stock_take_items WHERE stock_take_id = ?", [$id]);

            // Get Damage Warehouse ID
            $damage_loc = db_fetch("SELECT id FROM locations WHERE code = 'DW-STORE'");
            $damage_id = $damage_loc ? $damage_loc['id'] : null;

            foreach ($items as $item) {
                if ($item['variance'] == 0)
                    continue;

                $variance = floatval($item['variance']);
                $abs_variance = abs($variance);

                if ($variance < 0) {
                    // LOSS: Reduce from Main, Add to Damaged
                    inventory_apply_stock_movement($item['item_id'], $st['location_id'], $variance, null, ['allow_during_stock_take' => true]);
                    if ($damage_id) {
                        inventory_apply_stock_movement($item['item_id'], $damage_id, $abs_variance, null, ['allow_during_stock_take' => true]);

                        // Log Transaction: Transfer to Damage
                        inventory_record_transaction([
                            'item_id' => $item['item_id'],
                            'location_id' => $damage_id,
                            'transaction_type' => 'receipt_unplanned',
                            'movement_reason' => 'Inventory Write-off',
                            'quantity_signed' => $abs_variance,
                            'unit_cost' => $item['unit_cost'],
                            'reference' => $st['stock_take_number'],
                            'reference_type' => 'stock_take',
                            'notes' => 'Damaged/Shortage found during stock take',
                            'created_by' => $user_id
                        ]);
                    }

                    // Log Transaction: Reduction from Source
                    inventory_record_transaction([
                        'item_id' => $item['item_id'],
                        'location_id' => $st['location_id'],
                        'transaction_type' => 'issue_unplanned',
                        'movement_reason' => 'Inventory Adjustment',
                        'quantity_signed' => $variance,
                        'unit_cost' => $item['unit_cost'],
                        'reference' => $st['stock_take_number'],
                        'reference_type' => 'stock_take',
                        'notes' => 'Stock Take Shortage Correction',
                        'created_by' => $user_id
                    ]);

                } else {
                    // GAIN: Increase Main stock
                    inventory_apply_stock_movement($item['item_id'], $st['location_id'], $variance, null, ['allow_during_stock_take' => true]);

                    inventory_record_transaction([
                        'item_id' => $item['item_id'],
                        'location_id' => $st['location_id'],
                        'transaction_type' => 'receipt_unplanned',
                        'movement_reason' => 'Inventory Adjustment',
                        'quantity_signed' => $variance,
                        'unit_cost' => $item['unit_cost'],
                        'reference' => $st['stock_take_number'],
                        'reference_type' => 'stock_take',
                        'notes' => 'Stock Take Surplus Found',
                        'created_by' => $user_id
                    ]);
                }
            }

            db_query("INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by) VALUES (?, 'approved', ?, ?)", [
                $id,
                $notes ?: 'Stock take approved and inventory updated',
                $user_id
            ]);

            db_commit();
            set_flash('Stock take approved. Inventory levels adjusted and variances moved to Damaged Store.', 'success');
        } elseif ($action === 'reject') {
            db_query("UPDATE stock_take_headers SET status = 'rejected' WHERE id = ?", [$id]);
            db_query("INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by) VALUES (?, 'rejected', ?, ?)", [
                $id,
                $notes ?: 'Stock take rejected by manager. Please verify counts.',
                $user_id
            ]);
            db_commit();
            set_flash('Stock take rejected and sent back for re-entry.', 'info');
        }
    } catch (Exception $e) {
        db_rollback();
        set_flash('Step execution error: ' . $e->getMessage(), 'error');
    }
    redirect($redirect_after_post);
}

// Stats
$stats = db_fetch("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN variance != 0 THEN 1 ELSE 0 END) as discrepancies,
        SUM(total_variance_value) as total_var_value,
        SUM(ABS(total_variance_value)) as abs_var_value
    FROM stock_take_items 
    WHERE stock_take_id = ?
", [$id]);

$items = db_fetch_all("
    SELECT sti.*, ii.code, ii.name 
    FROM stock_take_items sti
    JOIN inventory_items ii ON sti.item_id = ii.id
    WHERE sti.stock_take_id = ?
", [$id]);

$history = db_fetch_all("
    SELECT sth.*, u.username 
    FROM stock_take_history sth
    LEFT JOIN users u ON sth.changed_by = u.id
    WHERE sth.stock_take_id = ?
    ORDER BY sth.created_at DESC
", [$id]);

$count1_snapshot = null;
$count2_snapshot = null;
foreach ($history as $h) {
    if (!$count1_snapshot && strpos($h['notes'], 'COUNT1_JSON::') === 0) {
        $decoded = json_decode(base64_decode(substr($h['notes'], strlen('COUNT1_JSON::'))), true);
        if (is_array($decoded)) {
            $count1_snapshot = $decoded;
        }
    }
    if (!$count2_snapshot && strpos($h['notes'], 'COUNT2_JSON::') === 0) {
        $decoded = json_decode(base64_decode(substr($h['notes'], strlen('COUNT2_JSON::'))), true);
        if (is_array($decoded)) {
            $count2_snapshot = $decoded;
        }
    }
    if ($count1_snapshot && $count2_snapshot) {
        break;
    }
}

// Backward-compatible fallback: parse older text history
// "Count 2 saved by manager. Count 1: CODE=QTY, ... | Count 2: CODE=QTY, ..."
if (!$count1_snapshot || !$count2_snapshot) {
    foreach ($history as $h) {
        $note = $h['notes'] ?? '';
        if (stripos($note, 'Count 1:') === false || stripos($note, 'Count 2:') === false) {
            continue;
        }

        $parts = preg_split('/\|\s*Count\s*2\s*:/i', $note, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $left = $parts[0];
        $pos = stripos($left, 'Count 1:');
        $count1_part = $pos !== false ? trim(substr($left, $pos + strlen('Count 1:'))) : '';
        $count2_part = trim($parts[1]);

        $parse_pairs = function ($text) {
            $result = [];
            if ($text === '') {
                return $result;
            }
            foreach (explode(',', $text) as $pair) {
                $pair = trim($pair);
                if ($pair === '' || strpos($pair, '=') === false) {
                    continue;
                }
                [$code, $qty] = array_map('trim', explode('=', $pair, 2));
                if ($code === '' || $qty === '') {
                    continue;
                }
                $result[] = [
                    'code' => $code,
                    'physical_qty' => floatval($qty)
                ];
            }
            return $result;
        };

        if (!$count1_snapshot) {
            $count1_snapshot = [
                'run' => 1,
                'saved_at' => $h['created_at'] ?? null,
                'items' => $parse_pairs($count1_part),
                'totals' => ['total_variance_value' => 0]
            ];
        }
        if (!$count2_snapshot) {
            $count2_snapshot = [
                'run' => 2,
                'saved_at' => $h['created_at'] ?? null,
                'items' => $parse_pairs($count2_part),
                'totals' => ['total_variance_value' => 0]
            ];
        }
        if ($count1_snapshot && $count2_snapshot) {
            break;
        }
    }
}

// Final fallback for old records with no count1 snapshot:
// show current stored values so the report does not remain blank.
if (!$count1_snapshot && !empty($items)) {
    $fallback_items = [];
    foreach ($items as $it) {
        $fallback_items[] = [
            'item_id' => $it['item_id'],
            'code' => $it['code'],
            'system_qty' => floatval($it['system_qty']),
            'physical_qty' => floatval($it['physical_qty']),
            'variance' => floatval($it['variance']),
            'total_variance_value' => floatval($it['total_variance_value'])
        ];
    }
    $count1_snapshot = [
        'run' => 1,
        'saved_at' => null,
        'items' => $fallback_items,
        'totals' => [
            'total_variance_value' => array_reduce($fallback_items, function ($carry, $row) {
                return $carry + floatval($row['total_variance_value']);
            }, 0.0)
        ]
    ];
}

$count_variance_gap = null;
if ($count1_snapshot && $count2_snapshot) {
    $count1_total = floatval($count1_snapshot['totals']['total_variance_value'] ?? 0);
    $count2_total = floatval($count2_snapshot['totals']['total_variance_value'] ?? 0);
    $count_variance_gap = $count2_total - $count1_total;
}

$count1_map_by_code = [];
if ($count1_snapshot && !empty($count1_snapshot['items'])) {
    foreach ($count1_snapshot['items'] as $c1) {
        if (!empty($c1['code'])) {
            $count1_map_by_code[$c1['code']] = $c1;
        }
    }
}

$page_title = 'Review Stock Take - ' . $st['stock_take_number'];

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="index.php" class="text-info">Stock Takes</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $st['stock_take_number'] ?></li>
                </ol>
            </nav>
            <h2 class="text-white fw-bold mb-0"><?= $st['stock_take_number'] ?> <span
                    class="badge bg-<?= $st['status'] === 'approved' ? 'success' : 'warning' ?> fs-6 ms-2"><?= strtoupper($st['status']) ?></span>
            </h2>
            <p class="text-muted">Review variances for <strong><?= escape_html($st['location_name']) ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-info shadow-sm">
                <i class="fas fa-print me-2"></i>Print Report
            </button>
            <?php if ($st['status'] === 'pending_approval' && has_role('manager')): ?>
                <button class="btn btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#approvalModal">
                    <i class="fas fa-check-circle me-2"></i>Finalize & Approve
                </button>
                <button class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="fas fa-times-circle me-2"></i>Reject
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Variance Dashboard -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card premium-card h-100 p-4 border-start border-4 border-info">
                <h6 class="text-secondary small text-uppercase mb-2">Items Audited</h6>
                <h3 class="text-white fw-bold mb-0"><?= number_format($stats['total_items']) ?></h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card premium-card h-100 p-4 border-start border-4 border-warning">
                <h6 class="text-secondary small text-uppercase mb-2">Discrepancies Found</h6>
                <h3 class="text-warning fw-bold mb-0"><?= number_format($stats['discrepancies']) ?></h3>
                <small
                    class="text-muted"><?= number_format(($stats['discrepancies'] / ($stats['total_items'] ?: 1)) * 100, 1) ?>%
                    error rate</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card premium-card h-100 p-4 border-start border-4 border-danger">
                <h6 class="text-secondary small text-uppercase mb-2">Net Variance Value</h6>
                <h3 class="<?= $stats['total_var_value'] < 0 ? 'text-danger' : 'text-success' ?> fw-bold mb-0">
                    <?= format_currency($stats['total_var_value']) ?>
                </h3>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card premium-card h-100 p-4 border-start border-4 border-primary">
                <h6 class="text-secondary small text-uppercase mb-2">Abs. Variance Impact</h6>
                <h3 class="text-primary fw-bold mb-0"><?= format_currency($stats['abs_var_value']) ?></h3>
            </div>
        </div>
    </div>

    <?php if ($count1_snapshot || $count2_snapshot): ?>
    <div class="card premium-card mb-4">
        <div class="card-header py-3 bg-dark-light">
            <h5 class="mb-0 fw-bold"><i class="fas fa-print me-2"></i>Count 1 / Count 2 History</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item Code</th>
                            <th class="text-end">Count 1</th>
                            <th class="text-end">Count 2</th>
                            <th class="text-end">Count 1 Variance Value</th>
                            <th class="text-end">Count 2 Variance Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            $count1_items = $count1_snapshot['items'] ?? [];
                            $count2_items = $count2_snapshot['items'] ?? [];
                            $item_meta_by_code = [];
                            foreach ($items as $meta) {
                                $item_meta_by_code[$meta['code']] = [
                                    'system_qty' => floatval($meta['system_qty']),
                                    'unit_cost' => floatval($meta['unit_cost'])
                                ];
                            }
                            $count1_map = [];
                            $count2_map = [];
                            foreach ($count1_items as $c1) {
                                $count1_map[$c1['code']] = $c1;
                            }
                            foreach ($count2_items as $c2) {
                                $count2_map[$c2['code']] = $c2;
                            }
                            $all_codes = array_unique(array_merge(array_keys($count1_map), array_keys($count2_map)));
                            sort($all_codes);
                        ?>
                        <?php foreach ($all_codes as $code): ?>
                            <?php $c1 = $count1_map[$code] ?? null; ?>
                            <?php $c2 = $count2_map[$code] ?? null; ?>
                            <?php $meta = $item_meta_by_code[$code] ?? null; ?>
                            <?php
                                $count1_var_value = null;
                                if ($c1) {
                                    if (isset($c1['total_variance_value'])) {
                                        $count1_var_value = floatval($c1['total_variance_value']);
                                    } elseif ($meta) {
                                        $count1_var_value = (floatval($c1['physical_qty']) - $meta['system_qty']) * $meta['unit_cost'];
                                    }
                                }
                                $count2_var_value = null;
                                if ($c2) {
                                    if (isset($c2['total_variance_value'])) {
                                        $count2_var_value = floatval($c2['total_variance_value']);
                                    } elseif ($meta) {
                                        $count2_var_value = (floatval($c2['physical_qty']) - $meta['system_qty']) * $meta['unit_cost'];
                                    }
                                }
                            ?>
                            <tr>
                                <td><code><?= escape_html($code) ?></code></td>
                                <td class="text-end"><?= $c1 ? number_format(floatval($c1['physical_qty'] ?? 0), 2) : '--' ?></td>
                                <td class="text-end"><?= $c2 ? number_format(floatval($c2['physical_qty'] ?? 0), 2) : '--' ?></td>
                                <td class="text-end"><?= $count1_var_value !== null ? format_currency($count1_var_value) : '--' ?></td>
                                <td class="text-end"><?= $count2_var_value !== null ? format_currency($count2_var_value) : '--' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($count_variance_gap !== null): ?>
                <div class="p-3 border-top border-secondary border-opacity-25">
                    <div class="fw-bold <?= $count_variance_gap == 0 ? 'text-info' : 'text-warning' ?>">
                        Variance of Count 1 and Count 2 is <?= format_currency($count_variance_gap) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($count1_snapshot): ?>
    <div class="card premium-card mb-4">
        <div class="card-header py-3 bg-dark-light d-flex justify-content-between">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Count 1 Variance Details</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-end">System</th>
                            <th class="text-end">Physical</th>
                            <th class="text-end">Variance</th>
                            <th class="text-end">Unit Cost</th>
                            <th class="text-end">Impact Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php
                                $c1 = $count1_map_by_code[$item['code']] ?? null;
                                $c1_physical = $c1 ? floatval($c1['physical_qty'] ?? 0) : null;
                                $c1_variance = $c1 !== null ? ($c1_physical - floatval($item['system_qty'])) : null;
                                $c1_impact = $c1_variance !== null ? ($c1_variance * floatval($item['unit_cost'])) : null;
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?= escape_html($item['name']) ?></div>
                                    <div class="text-muted small"><code><?= escape_html($item['code']) ?></code> |
                                        B: <?= $item['bin_location'] ?: 'Unassigned' ?></div>
                                </td>
                                <td class="text-end text-muted"><?= number_format($item['system_qty'], 2) ?></td>
                                <td class="text-end fw-bold"><?= $c1_physical !== null ? number_format($c1_physical, 2) : '--' ?></td>
                                <td class="text-end">
                                    <?php if ($c1_variance !== null && abs($c1_variance) > 0.000001): ?>
                                        <span class="badge bg-<?= $c1_variance < 0 ? 'danger' : 'success' ?>">
                                            <?= ($c1_variance > 0 ? '+' : '') . number_format($c1_variance, 2) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted opacity-50">--</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted small"><?= format_currency($item['unit_cost']) ?></td>
                                <td class="text-end fw-bold <?= $c1_impact !== null && $c1_impact < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= $c1_impact !== null ? format_currency($c1_impact) : '--' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card premium-card mb-4">
                <div class="card-header py-3 bg-dark-light d-flex justify-content-between">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Variance Details</h5>
                    <div class="form-check form-switch small mt-1">
                        <input class="form-check-input" type="checkbox" id="filterDiscrepancy"
                            onchange="toggleFilter()">
                        <label class="form-check-label text-muted" for="filterDiscrepancy">Show only
                            discrepancies</label>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <?php if ($is_manager_pending): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_count2">
                        <?php endif; ?>
                        <table class="table table-dark table-hover mb-0" id="varianceTable">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="text-end">System</th>
                                    <th class="text-end">Physical (Count 2)</th>
                                    <th class="text-end">Variance</th>
                                    <th class="text-end">Unit Cost</th>
                                    <th class="text-end">Impact Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr class="<?= $item['variance'] == 0 ? 'no-discrepancy' : 'has-discrepancy' ?>">
                                        <td>
                                            <div class="fw-bold"><?= escape_html($item['name']) ?></div>
                                            <div class="text-muted small"><code><?= escape_html($item['code']) ?></code> |
                                                B: <?= $item['bin_location'] ?: 'Unassigned' ?></div>
                                        </td>
                                        <td class="text-end text-muted"><?= number_format($item['system_qty'], 2) ?></td>
                                        <td class="text-end fw-bold">
                                            <?php if ($is_manager_pending): ?>
                                                <input type="number"
                                                       class="form-control form-control-sm bg-dark text-white border-secondary text-end"
                                                       name="physical_qty2[<?= $item['item_id'] ?>]"
                                                       value="<?= number_format($item['physical_qty'], 2, '.', '') ?>"
                                                       step="0.01">
                                            <?php else: ?>
                                                <?= number_format($item['physical_qty'], 2) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($item['variance'] != 0): ?>
                                                <span class="badge bg-<?= $item['variance'] < 0 ? 'danger' : 'success' ?>">
                                                    <?= ($item['variance'] > 0 ? '+' : '') . number_format($item['variance'], 2) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted opacity-50">--</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-muted small"><?= format_currency($item['unit_cost']) ?>
                                        </td>
                                        <td
                                            class="text-end fw-bold <?= $item['total_variance_value'] < 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= format_currency($item['total_variance_value']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($is_manager_pending): ?>
                            <div class="p-3 text-end border-top border-secondary border-opacity-25">
                                <button type="submit" class="btn btn-warning fw-bold">Save Count 2</button>
                            </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Summary Info -->
            <div class="card premium-card mb-4">
                <div class="card-header py-3 bg-dark-light">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-info-circle me-2"></i>Header Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small d-block">Created By</label>
                        <span class="text-white"><?= escape_html($st['creator_name']) ?> on
                            <?= format_date($st['created_at']) ?></span>
                    </div>
                    <?php if ($st['approved_by']): ?>
                        <div class="mb-3">
                            <label class="text-muted small d-block">Approved By</label>
                            <span class="text-success fw-bold"><?= escape_html($st['approver_name']) ?> on
                                <?= format_date($st['approved_at']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="text-muted small d-block">Remarks</label>
                        <p class="text-white small mb-0"><?= nl2br(escape_html($st['notes'] ?: 'No remarks.')) ?></p>
                    </div>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="card premium-card">
                <div class="card-header py-3 bg-dark-light">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-history me-2"></i>Audit Trail</h5>
                </div>
                <div class="card-body">
                    <div class="timeline small">
                        <?php foreach ($history as $h): ?>
                            <div class="mb-3 border-start border-secondary ps-3 pb-2">
                                <div class="d-flex justify-content-between mb-1">
                                    <span
                                        class="badge bg-<?= match ($h['status']) { 'approved' => 'success', 'rejected' => 'danger', 'open' => 'warning', 'pending_approval' => 'info', default => 'secondary'} ?> small text-uppercase"><?= $h['status'] ?></span>
                                    <span class="text-muted x-small"><?= format_datetime($h['created_at']) ?></span>
                                </div>
                                <div class="text-white-50">
                                    <?php if (strpos($h['notes'], 'COUNT1_JSON::') === 0): ?>
                                        Count 1 snapshot stored
                                    <?php elseif (strpos($h['notes'], 'COUNT2_JSON::') === 0): ?>
                                        Count 2 snapshot stored
                                    <?php else: ?>
                                        <?= escape_html($h['notes']) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-info font-italic">-- <?= escape_html($h['username'] ?: 'System') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-success">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="fas fa-check-circle me-2"></i>Approve & Finalize Variance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve">
                    <p class="text-white-50">This will adjust the system stock levels for
                        <strong><?= escape_html($st['location_name']) ?></strong> to match the physical count. Losses
                        will be moved to the <strong>Damaged/Write-off Store</strong>.</p>
                    <div class="mb-3">
                        <label class="form-label text-muted">Approval Remarks (Optional)</label>
                        <textarea name="approval_notes" class="form-control bg-dark text-white border-secondary"
                            rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">CONFIRM & UPDATE STOCK</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark border-danger">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-danger"><i class="fas fa-times-circle me-2"></i>Reject Stock Take</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject">
                    <p class="text-white-50">Sending the stock take back for re-entry. Please specify the reason.</p>
                    <div class="mb-3">
                        <label class="form-label text-muted">Rejection Reason (Required)</label>
                        <textarea name="approval_notes" class="form-control bg-dark text-white border-secondary"
                            rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold px-4">REJECT SESSION</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleFilter() {
        const showOnly = document.getElementById('filterDiscrepancy').checked;
        document.querySelectorAll('.no-discrepancy').forEach(tr => {
            tr.style.display = showOnly ? 'none' : '';
        });
    }
</script>

<style>
    .premium-card {
        border: none;
        border-radius: 12px;
        background: #1e1e2d;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    }

    .bg-dark-light {
        background: rgba(255, 255, 255, 0.02);
    }

    .table-dark thead th {
        background: rgba(255, 255, 255, 0.03);
        color: #0dcaf0;
        padding: 15px 20px;
        font-size: 0.8rem;
        text-transform: uppercase;
    }

    .table-dark td {
        padding: 12px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .x-small {
        font-size: 0.7rem;
    }
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
