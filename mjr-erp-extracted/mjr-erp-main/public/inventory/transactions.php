<?php
/**
 * Inventory - Transaction Report
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();
ensure_inventory_transaction_reporting_schema();

$page_title = 'Inventory Transaction Report - MJR Group ERP';
$company_id = active_company_id(1);

$filters = [
    'company_id' => $company_id,
    'date_from' => to_db_date(trim((string)get_param('date_from', ''))),
    'date_to' => to_db_date(trim((string)get_param('date_to', ''))),
    'item_id' => trim((string)get_param('item_id', '')),
    'location_id' => trim((string)get_param('location_id', '')),
    'transaction_type' => trim((string)get_param('transaction_type', '')),
    'customer_id' => trim((string)get_param('customer_id', '')),
    'supplier_id' => trim((string)get_param('supplier_id', '')),
    'search' => trim((string)get_param('search', ''))
];

$transactions = inventory_fetch_transaction_report_rows($filters);
$summary = inventory_transaction_report_summary($transactions);

$items = db_fetch_all("SELECT id, code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY code", [$company_id]);
$locations = db_fetch_all("SELECT id, code, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
$suppliers = db_fetch_all("SELECT id, supplier_code, name FROM suppliers WHERE is_active = 1 ORDER BY name");

$catalog = inventory_transaction_type_catalog();
$typeOptions = array_filter(
    $catalog,
    static function ($key) {
        return !in_array($key, ['in', 'out', 'receipt'], true);
    },
    ARRAY_FILTER_USE_KEY
);

$existingTypes = db_fetch_all("SELECT DISTINCT transaction_type FROM inventory_transactions ORDER BY transaction_type");
foreach ($existingTypes as $row) {
    $type = trim((string)($row['transaction_type'] ?? ''));
    if ($type !== '' && !isset($typeOptions[$type])) {
        $typeOptions[$type] = [
            'label' => ucwords(str_replace('_', ' ', $type)),
            'direction' => 0
        ];
    }
}

$exportParams = [];
foreach ($filters as $key => $value) {
    if ($value !== '') {
        $exportParams[$key] = $value;
    }
}
$exportBase = '../inventory/export_transaction_report.php';
$csvExportUrl = $exportBase . '?format=csv' . (!empty($exportParams) ? '&' . http_build_query($exportParams) : '');
$excelExportUrl = $exportBase . '?format=excel' . (!empty($exportParams) ? '&' . http_build_query($exportParams) : '');
$pdfExportUrl = $exportBase . '?format=pdf' . (!empty($exportParams) ? '&' . http_build_query($exportParams) : '');
$wordExportUrl = $exportBase . '?format=word' . (!empty($exportParams) ? '&' . http_build_query($exportParams) : '');

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-list-alt me-3"></i>Inventory Transaction Report</h1>
            <p class="text-muted mb-0">Complete movement ledger with quantity, unit, cost, selling price, customer, and supplier context.</p>
        </div>
        <div class="col-auto">
            <div class="btn-group me-2">
                <a href="../inventory/add_transaction.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add Transaction
                </a>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-success dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= escape_html($csvExportUrl) ?>">
                        <i class="fas fa-file-csv me-2 text-success"></i>Export CSV
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($excelExportUrl) ?>">
                        <i class="fas fa-file-excel me-2 text-success"></i>Export Excel
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($pdfExportUrl) ?>">
                        <i class="fas fa-file-pdf me-2 text-danger"></i>Export PDF
                    </a></li>
                    <li><a class="dropdown-item" href="<?= escape_html($wordExportUrl) ?>">
                        <i class="fas fa-file-word me-2 text-primary"></i>Export Word
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Rows</small>
                    <h4 class="mb-0"><?= format_number($summary['total_rows'], 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <small class="text-muted">Inbound Qty</small>
                    <h4 class="mb-0 text-success"><?= format_number($summary['total_in_qty'], 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card h-100 border-danger">
                <div class="card-body">
                    <small class="text-muted">Outbound Qty</small>
                    <h4 class="mb-0 text-danger"><?= format_number($summary['total_out_qty'], 0) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Inbound Cost Value</small>
                    <h4 class="mb-0"><?= format_currency($summary['total_in_cost_value']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">Outbound Sales Value</small>
                    <h4 class="mb-0"><?= format_currency($summary['total_out_sales_value']) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?= !empty($filters['date_from']) ? format_date($filters['date_from']) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="text" class="form-control datepicker" id="date_to" name="date_to" value="<?= !empty($filters['date_to']) ? format_date($filters['date_to']) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label for="item_id" class="form-label">Item</label>
                    <select class="form-select" id="item_id" name="item_id">
                        <option value="">All Items</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>" <?= $filters['item_id'] === (string)$item['id'] ? 'selected' : '' ?>>
                                <?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="transaction_type" class="form-label">Type</label>
                    <select class="form-select" id="transaction_type" name="transaction_type">
                        <option value="">All Types</option>
                        <?php foreach ($typeOptions as $type => $meta): ?>
                            <option value="<?= escape_html($type) ?>" <?= $filters['transaction_type'] === $type ? 'selected' : '' ?>>
                                <?= escape_html($meta['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="location_id" class="form-label">Location</label>
                    <select class="form-select" id="location_id" name="location_id">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>" <?= $filters['location_id'] === (string)$location['id'] ? 'selected' : '' ?>>
                                <?= escape_html($location['code']) ?> - <?= escape_html($location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="customer_id" class="form-label">Customer</label>
                    <select class="form-select" id="customer_id" name="customer_id">
                        <option value="">All Customers</option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= $customer['id'] ?>" <?= $filters['customer_id'] === (string)$customer['id'] ? 'selected' : '' ?>>
                                <?= escape_html($customer['customer_code']) ?> - <?= escape_html($customer['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="supplier_id" class="form-label">Supplier</label>
                    <select class="form-select" id="supplier_id" name="supplier_id">
                        <option value="">All Suppliers</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['id'] ?>" <?= $filters['supplier_id'] === (string)$supplier['id'] ? 'selected' : '' ?>>
                                <?= escape_html($supplier['supplier_code']) ?> - <?= escape_html($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Item, reference, notes, SO/PO number..." value="<?= escape_html($filters['search']) ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <a href="transactions.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (!empty($transactions)): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover" id="transactionsReportTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Cost</th>
                                <th>Selling</th>
                                <th>Customer</th>
                                <th>Supplier</th>
                                <th>Reference</th>
                                <th>Location</th>
                                <th>User</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td data-order="<?= strtotime((string)$tx['created_at']) ?>">
                                        <?= format_date($tx['created_at'], DISPLAY_DATETIME_FORMAT) ?>
                                    </td>
                                    <td>
                                        <strong><?= escape_html($tx['item_code']) ?></strong><br>
                                        <small class="text-muted"><?= escape_html($tx['item_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= intval($tx['quantity_signed']) >= 0 ? 'success' : 'danger' ?>">
                                            <?= escape_html($tx['transaction_label']) ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= intval($tx['quantity_signed']) > 0 ? '+' : '' ?><?= format_number($tx['quantity_signed'], 0) ?></strong>
                                    </td>
                                    <td><?= escape_html($tx['unit_of_measure']) ?></td>
                                    <td class="text-end"><?= format_currency($tx['unit_cost']) ?></td>
                                    <td class="text-end"><?= format_currency($tx['selling_price']) ?></td>
                                    <td>
                                        <?php if (!empty($tx['customer_name'])): ?>
                                            <?= escape_html($tx['customer_name']) ?><br>
                                            <small class="text-muted"><?= escape_html($tx['customer_code']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tx['supplier_name'])): ?>
                                            <?= escape_html($tx['supplier_name']) ?><br>
                                            <small class="text-muted"><?= escape_html($tx['supplier_code']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tx['reference_type'] === 'purchase_order'): ?>
                                            <a href="../inventory/purchase_order/view_purchase_order.php?id=<?= $tx['reference_id'] ?>" class="text-info fw-bold">
                                                <?= escape_html($tx['reference_display']) ?>
                                            </a>
                                        <?php elseif ($tx['reference_type'] === 'sales_order'): ?>
                                            <a href="../sales/view_order.php?id=<?= $tx['reference_id'] ?>" class="text-info fw-bold">
                                                <?= escape_html($tx['reference_display']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= escape_html($tx['reference_display']) ?>
                                        <?php endif; ?><br>
                                        <?php if (!empty($tx['reference_type'])): ?>
                                            <small class="text-muted"><?= escape_html($tx['reference_type']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= escape_html($tx['location_code']) ?><br>
                                        <small class="text-muted"><?= escape_html($tx['location_name']) ?></small>
                                    </td>
                                    <td><?= escape_html($tx['created_by_name'] ?? '-') ?></td>
                                    <td><?= escape_html($tx['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted mb-0">No transaction records found for the selected filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#transactionsReportTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 50,
        searching: false
    });
});
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>




