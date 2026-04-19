<?php
/**
 * Initiate New Stock Take
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/inventory_transaction_service.php';

require_login();

// Anybody can initiate a stock take as per user request

function stock_take_has_unapproved_movements(): array
{
    $entry_sql = "
        SELECT COUNT(*) AS cnt
        FROM gsrn_headers
        WHERE LOWER(REPLACE(COALESCE(status, ''), ' ', '_')) = 'pending_approval'
    ";
    $stock_entry_pending = intval(db_fetch($entry_sql)['cnt'] ?? 0);

    $transfer_sql = "
        SELECT COUNT(*) AS cnt
        FROM transfer_headers
        WHERE LOWER(REPLACE(COALESCE(status, ''), ' ', '_')) = 'pending_approval'
    ";
    $stock_transfer_pending = intval(db_fetch($transfer_sql)['cnt'] ?? 0);

    return [
        'stock_entries' => $stock_entry_pending,
        'stock_transfers' => $stock_transfer_pending,
        'has_unapproved' => ($stock_entry_pending > 0 || $stock_transfer_pending > 0),
    ];
}

// Check if a stock take is already active
if (inventory_get_active_stock_take()) {
    set_flash('A stock take is already in progress. Only one location can be audited at a time.', 'error');
    redirect('index.php');
}

$approval_guard = stock_take_has_unapproved_movements();
if ($approval_guard['has_unapproved']) {
    set_flash(
        'Stock take cannot be started until all stock entries and stock transfers are approved. '
        . 'Pending stock entries: ' . $approval_guard['stock_entries']
        . ', pending stock transfers: ' . $approval_guard['stock_transfers'] . '.<br>'
        . 'Please contact the Manager for approval of pending stock entries and stock transfers. '
        . 'Processing will proceed once approval is completed.',
        'error'
    );
    redirect('index.php');
}

if (is_post()) {
    $location_id = post('location_id');
    $bin_id = post('bin_id');
    $notes = post('notes');
    $user_id = current_user_id();

    if ($location_id) {
        try {
            db_begin_transaction();

            // Generate Number
            $next = db_fetch("SELECT COUNT(*) as cnt FROM stock_take_headers")['cnt'] + 1;
            $st_number = 'ST-' . date('Ymd') . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);

            // Create Header
            $st_id = db_insert("
                INSERT INTO stock_take_headers (stock_take_number, location_id, status, notes, created_by, planned_date) 
                VALUES (?, ?, 'open', ?, ?, ?)
            ", [$st_number, $location_id, $notes, $user_id, date('Y-m-d')]);

            // Snapshot Items
            $snapshot_sql = "
                SELECT isl.item_id, isl.bin_location, isl.quantity_on_hand, ii.cost_price
                FROM inventory_stock_levels isl
                JOIN inventory_items ii ON isl.item_id = ii.id
                WHERE isl.location_id = ? AND ii.is_active = 1
            ";
            $snapshot_params = [$location_id];
            if (!empty($bin_id)) {
                $selected_bin = db_fetch("SELECT code FROM bins WHERE id = ?", [$bin_id]);
                if ($selected_bin && !empty($selected_bin['code'])) {
                    $snapshot_sql .= " AND isl.bin_location = ?";
                    $snapshot_params[] = $selected_bin['code'];
                }
            }
            $snapshot = db_fetch_all($snapshot_sql, $snapshot_params);

            foreach ($snapshot as $item) {
                db_query("
                    INSERT INTO stock_take_items (stock_take_id, item_id, bin_location, system_qty, physical_qty, variance, unit_cost) 
                    VALUES (?, ?, ?, ?, ?, 0, ?)
                ", [
                    $st_id,
                    $item['item_id'],
                    $item['bin_location'],
                    $item['quantity_on_hand'],
                    $item['quantity_on_hand'], // Set physical = system by default to avoid huge initial variance
                    $item['cost_price']
                ]);
            }

            // Log History
            db_query("INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by) VALUES (?, 'open', 'Stock take initiated', ?)", [
                $st_id,
                $user_id
            ]);

            db_commit();
            set_flash('Stock take initiated successfully. System is now locked.', 'success');
            redirect('details.php?id=' . $st_id);
        } catch (Exception $e) {
            db_rollback();
            set_flash('Error: ' . $e->getMessage(), 'error');
        }
    }
}

$selected_company_id = active_company_id();
if ($selected_company_id) {
    $locations = db_fetch_all("SELECT * FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
} else {
    $locations = db_fetch_all("SELECT * FROM locations WHERE is_active = 1 ORDER BY name");
}
$bins = db_fetch_all("SELECT id, code, warehouse_id FROM bins WHERE COALESCE(is_active,1)=1 ORDER BY code");
$page_title = 'Initiate Stock Take - MJR Group ERP';

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-3">
        <h4 class="mb-1 text-warning fw-bold"><i class="fas fa-barcode me-2"></i>Initiate Stock Take</h4>
        <p class="text-muted small mb-0">Initiating this will freeze other inventory movements until approval.</p>
    </div>

    <form method="POST">
        <div class="sheet-wrap">
            <table class="sheet-table">
                <thead>
                    <tr>
                        <th>Date Initaited</th>
                        <th>Initiated By</th>
                        <th>Store</th>
                        <th>Bin</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="cell-input">
                            <input type="text" class="sheet-input" value="<?= date('d-m-Y') ?>" readonly>
                        </td>
                        <td class="cell-input">
                            <input type="text" class="sheet-input" value="<?= escape_html($_SESSION['username'] ?? 'user') ?>" readonly>
                        </td>
                        <td class="cell-input">
                            <select name="location_id" class="sheet-select" required>
                                <option value="">select -Main warehouse / vva / other location etc</option>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= escape_html($l['name']) ?> (<?= escape_html($l['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="cell-input">
                            <select name="bin_id" id="bin_id" class="sheet-select">
                                <option value="">All bins in location</option>
                                <?php foreach ($bins as $b): ?>
                                    <option value="<?= $b['id'] ?>" data-warehouse-id="<?= intval($b['warehouse_id']) ?>">
                                        <?= escape_html($b['code']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="6" class="cell-note">
                            <input type="text" name="notes" class="sheet-input" placeholder="optional comment">
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2"></td>
                        <td class="action-cell">
                            <button type="submit" class="btn-linklike initiate-btn">initiate</button>
                        </td>
                        <td class="action-cell">
                            <a href="index.php" class="btn-linklike cancel-btn">cancel</a>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
(function() {
    const locationSel = document.querySelector('select[name="location_id"]');
    const binSel = document.getElementById('bin_id');
    if (!locationSel || !binSel) return;

    const allOptions = Array.from(binSel.options).map(o => ({
        value: o.value,
        text: o.text,
        warehouseId: o.getAttribute('data-warehouse-id') || ''
    }));

    function refreshBins() {
        const locId = locationSel.value;
        if (!locId) {
            binSel.innerHTML = '<option value="">All bins in location</option>';
            return;
        }
        fetch('../ajax_get_bins.php?location_id=' + encodeURIComponent(locId))
            .then(r => r.json())
            .then(data => {
                let html = '<option value="">All bins in location</option>';
                data.forEach(b => {
                    html += '<option value="' + b.bin_id + '">' + b.bin_location + '</option>';
                });
                binSel.innerHTML = html;
            })
            .catch(() => {
                binSel.innerHTML = '<option value="">All bins in location</option>';
            });
    }

    locationSel.addEventListener('change', refreshBins);
})();
</script>

<style>
    .sheet-wrap {
        border: 1px solid #4a4f58;
        background: #0b0b0b;
        overflow-x: auto;
    }
    .sheet-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 980px;
        color: #f1f5f9;
        font-size: 0.95rem;
    }
    .sheet-table th,
    .sheet-table td {
        border: 1px solid #3f4650;
        padding: 6px 10px;
        vertical-align: middle;
        background: #0b0b0b;
    }
    .sheet-table th {
        font-weight: 700;
    }
    .sheet-input,
    .sheet-select {
        width: 100%;
        border: none;
        background: transparent;
        color: #f1f5f9;
        padding: 0;
        outline: none;
    }
    .sheet-select {
        cursor: pointer;
    }
    .sheet-select option {
        background: #111827;
        color: #f1f5f9;
    }
    .cell-note .sheet-input::placeholder {
        color: #94a3b8;
    }
    .action-cell {
        padding: 0 !important;
    }
    .btn-linklike {
        display: inline-block;
        width: 100%;
        border: none;
        padding: 7px 10px;
        text-align: left;
        text-decoration: none;
        font-weight: 700;
        background: transparent;
        color: #111;
        cursor: pointer;
    }
    .initiate-btn {
        background: #18a8db;
    }
    .cancel-btn {
        background: #ececec;
    }
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
