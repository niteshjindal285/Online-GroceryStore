<?php
/**
 * AJAX - Product Stock History Drilldown
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();

$item_id = get_param('id');
if (!$item_id) {
    echo '<div class="alert alert-danger">Invalid Product ID</div>';
    exit;
}

$item = db_fetch("SELECT code, name FROM inventory_items WHERE id = ?", [$item_id]);
if (!$item) {
    echo '<div class="alert alert-danger">Product not found</div>';
    exit;
}

// Fetch transactions for this item
$filters = ['item_id' => $item_id];
$transactions = inventory_fetch_transaction_report_rows($filters, 50);

?>

<style>
    .history-badge {
        border-radius: 20px;
        padding: 5px 12px;
        font-weight: 600;
        font-size: 0.75rem;
        text-transform: capitalize;
    }
    .badge-purchase { background-color: #198754; color: white; } /* Green */
    .badge-sale { background-color: #dc3545; color: white; } /* Red */
    .badge-receipt { background-color: #198754; color: white; }
    .badge-instock { background-color: #198754; color: white; }
    .badge-outstock { background-color: #dc3545; color: white; }
    
    #historyDetailTable {
        border-collapse: separate;
        border-spacing: 0 4px;
    }
    #historyDetailTable thead th {
        border: none;
        color: #adb5bd;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    #historyDetailTable tbody tr {
        background-color: #212529;
        transition: transform 0.2s;
    }
    #historyDetailTable tbody tr:hover {
        background-color: #2c3034;
        transform: scale(1.005);
    }
    #historyDetailTable td {
        border: none;
        padding: 12px 15px;
        vertical-align: middle;
    }
    .qty-plus { color: #2ecc71; font-weight: bold; }
    .qty-minus { color: #e74c3c; font-weight: bold; }
</style>

<div class="mb-4">
    <h3 class="text-white mb-0 fw-bold"><?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?></h3>
    <p class="text-secondary small opacity-75">Movement Ledger</p>
</div>

<?php if (empty($transactions)): ?>
    <div class="text-center py-5">
        <div class="mb-3">
            <i class="fas fa-history fa-4x text-muted opacity-25"></i>
        </div>
        <p class="text-muted">No transactions found for this product.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-dark align-middle" id="historyDetailTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Bin</th>
                    <th>Reference</th>
                    <th>Location</th>
                    <th class="text-center">Qty Change</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                    <?php 
                        $type_class = 'bg-secondary';
                        $t_label = strtolower(str_replace(' ', '', $tx['transaction_label']));
                        if (str_contains($t_label, 'purchase')) $type_class = 'badge-purchase';
                        elseif (str_contains($t_label, 'sale')) $type_class = 'badge-sale';
                        elseif (str_contains($t_label, 'receipt')) $type_class = 'badge-receipt';
                        elseif (str_contains($t_label, 'in')) $type_class = 'badge-instock';
                        elseif (str_contains($t_label, 'out')) $type_class = 'badge-outstock';
                    ?>
                    <tr>
                        <td class="text-white-50 small" data-order="<?= strtotime($tx['created_at']) ?>">
                            <?= format_datetime($tx['created_at']) ?>
                        </td>
                        <td>
                            <span class="history-badge <?= $type_class ?>">
                                <?= escape_html($tx['transaction_label']) ?>
                            </span>
                        </td>
                        <td class="text-white-50"><small><?= escape_html($tx['bin_location'] ?: '-') ?></small></td>
                        <td class="fw-bold"><?= escape_html($tx['reference_display']) ?></td>
                        <td class="text-white-50 small"><?= escape_html($tx['location_name']) ?></td>
                        <td class="text-center">
                            <span class="<?= intval($tx['quantity_signed']) >= 0 ? 'qty-plus' : 'qty-minus' ?>">
                                <?= intval($tx['quantity_signed']) > 0 ? '+' : '' ?><?= number_format($tx['quantity_signed']) ?>
                            </span>
                        </td>
                        <td class="text-white-50"><small><?= escape_html($tx['notes'] ?: '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<script>
$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#historyDetailTable')) {
        $('#historyDetailTable').DataTable().destroy();
    }
    $('#historyDetailTable').DataTable({
        pageLength: 5,
        order: [[0, 'desc']],
        dom: 'tp',
        language: {
            paginate: {
                previous: '<i class="fas fa-chevron-left"></i> Previous',
                next: 'Next <i class="fas fa-chevron-right"></i>'
            }
        }
    });
});
</script>
