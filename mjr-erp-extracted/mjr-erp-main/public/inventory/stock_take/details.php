<?php
/**
 * Enter Physical Counts for Stock Take
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$id = get_param('id');
if (!$id) {
    set_flash('Stock take session not found.', 'error');
    redirect('index.php');
}

$st = db_fetch("
    SELECT st.*, l.name as location_name 
    FROM stock_take_headers st
    JOIN locations l ON st.location_id = l.id
    WHERE st.id = ?
", [$id]);

if (!$st) {
    set_flash('Stock take session not found.', 'error');
    redirect('index.php');
}

if ($st['status'] !== 'open' && $st['status'] !== 'draft' && $st['status'] !== 'rejected') {
    set_flash('This session is no longer open for entry.', 'warning');
    redirect('view.php?id=' . $id);
}

// Handle Save
if (is_post()) {
    $physical_counts = $_POST['physical_qty'] ?? []; // item_id => qty
    $user_id = current_user_id();

    try {
        db_begin_transaction();

        foreach ($physical_counts as $item_id => $qty) {
            if ($qty === '' || $qty === null) {
                continue;
            }
            $qty = floatval($qty);
            $item_data = db_fetch("SELECT system_qty, unit_cost FROM stock_take_items WHERE stock_take_id = ? AND item_id = ?", [$id, $item_id]);

            if ($item_data) {
                $variance = $qty - floatval($item_data['system_qty']);
                $total_variance_value = $variance * floatval($item_data['unit_cost']);

                db_query("
                    UPDATE stock_take_items 
                    SET physical_qty = ?, variance = ?, total_variance_value = ? 
                    WHERE stock_take_id = ? AND item_id = ?
                ", [$qty, $variance, $total_variance_value, $id, $item_id]);
            }
        }

        db_query("UPDATE stock_take_headers SET status = 'pending_approval' WHERE id = ?", [$id]);

        $count1_rows = db_fetch_all("
            SELECT sti.item_id, ii.code, sti.system_qty, sti.physical_qty, sti.variance, sti.total_variance_value
            FROM stock_take_items sti
            JOIN inventory_items ii ON ii.id = sti.item_id
            WHERE sti.stock_take_id = ?
            ORDER BY ii.code ASC
        ", [$id]);

        $count1_payload = [
            'run' => 1,
            'saved_at' => date('Y-m-d H:i:s'),
            'items' => $count1_rows,
            'totals' => [
                'total_variance_value' => array_reduce($count1_rows, function ($carry, $row) {
                    return $carry + floatval($row['total_variance_value']);
                }, 0.0)
            ]
        ];

        db_query("
            INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by)
            VALUES (?, 'pending_approval', ?, ?)
        ", [$id, 'COUNT1_JSON::' . base64_encode(json_encode($count1_payload)), $user_id]);

        db_query("
            INSERT INTO stock_take_history (stock_take_id, status, notes, changed_by)
            VALUES (?, 'pending_approval', 'Count 1 saved and sent for manager approval', ?)
        ", [$id, $user_id]);

        db_commit();
        set_flash('Counts saved and sent for manager approval.', 'success');
        redirect('index.php');
    } catch (Exception $e) {
        db_rollback();
        set_flash('Error: ' . $e->getMessage(), 'error');
    }
}

// Fetch Items
$items = db_fetch_all("
    SELECT sti.*, ii.code, ii.name 
    FROM stock_take_items sti
    JOIN inventory_items ii ON sti.item_id = ii.id
    WHERE sti.stock_take_id = ?
    ORDER BY ii.code ASC
", [$id]);

$page_title = 'Enter Physical Counts - ' . $st['stock_take_number'];

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-8">
            <h2 class="text-white fw-bold mb-0">Record Physical Stock</h2>
            <p class="text-muted">
                Session: <span class="text-warning fw-bold"><?= $st['stock_take_number'] ?></span> |
                Location: <span class="text-info"><?= escape_html($st['location_name']) ?></span>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary me-2">Exit Without Saving</a>
        </div>
    </div>

    <form method="POST">
        <div class="sheet-wrap">
            <table class="sheet-table" id="stockTakeTable">
                <thead>
                    <tr>
                        <th colspan="6" class="sheet-title">stock count page</th>
                    </tr>
                    <tr>
                        <th class="hl">product code</th>
                        <th class="hl">product name</th>
                        <th class="hl">location</th>
                        <th>Bin</th>
                        <th>uom</th>
                        <th class="hl">count quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td class="hl"><?= escape_html($item['code']) ?></td>
                        <td class="hl"><?= escape_html($item['name']) ?></td>
                        <td class="hl"><?= escape_html($st['location_name']) ?></td>
                        <td><?= escape_html($item['bin_location'] ?: 'MAIN') ?></td>
                        <td>pcs</td>
                        <td class="hl">
                            <input type="number"
                                   name="physical_qty[<?= $item['item_id'] ?>]"
                                   class="sheet-input physical-input"
                                   value=""
                                   step="0.01"
                                   data-system-qty="<?= $item['system_qty'] ?>">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3"></td>
                        <td class="action-cell" colspan="2">
                            <button type="submit" name="action" value="save" class="sheet-btn">Save</button>
                        </td>
                        <td class="action-cell">
                            <button type="reset" class="sheet-btn">clear</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </form>
</div>

<style>
.sheet-wrap {
    border: 1px solid #3f4650;
    background: #0b0b0b;
    overflow-x: auto;
}
.sheet-table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
    color: #f1f5f9;
}
.sheet-table th,
.sheet-table td {
    border: 1px solid #3f4650;
    background: #0b0b0b;
    padding: 8px 10px;
    vertical-align: middle;
}
.sheet-title {
    text-align: center;
    font-weight: 700;
}
.sheet-table .hl {
    background: #0b0b0b;
    color: #f1f5f9;
}
.sheet-input {
    width: 100%;
    border: none;
    outline: none;
    background: transparent;
    color: #f1f5f9;
    font-weight: 700;
}
.action-cell {
    padding: 0 !important;
}
.sheet-btn {
    width: 100%;
    border: none;
    padding: 7px 10px;
    text-align: left;
    background: #e5e7eb;
    color: #0f172a;
    font-weight: 700;
}
</style>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
