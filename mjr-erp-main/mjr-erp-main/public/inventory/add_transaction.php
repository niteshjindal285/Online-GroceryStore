<?php
/**
 * Inventory - Add Transaction
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();
require_permission('manage_inventory');
ensure_inventory_transaction_reporting_schema();

$page_title = 'Add Transaction - MJR Group ERP';
$company_id = active_company_id(1);
$errors = [];

// Pre-selected values from GET (e.g. when arriving from a warehouse inventory page)
$preselect_location_id = isset($_GET['location_id']) ? intval($_GET['location_id']) : 0;
$preselect_warehouse_id = isset($_GET['warehouse_id']) ? intval($_GET['warehouse_id']) : 0;

// Get items, locations
$items = db_fetch_all("
    SELECT i.id,
           i.code,
           i.name,
           COALESCE(NULLIF(i.unit_of_measure, ''), u.code, 'PCS') AS unit_of_measure,
           COALESCE(i.cost_price, 0) AS cost_price,
           COALESCE(i.selling_price, 0) AS selling_price
    FROM inventory_items i
    LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
    WHERE i.is_active = 1 AND i.company_id = ?
    ORDER BY i.code
", [$company_id]);
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);
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

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token.';
    } else {
        $item_id = intval(post('item_id', 0));
        $location_id = intval(post('location_id', 0));
        $transaction_type = trim((string)post('transaction_type'));
        $quantity = intval(post('quantity', 0));
        $direction_override = trim((string)post('direction_override'));
        $unit_cost = floatval(post('unit_cost', 0));
        $selling_price = floatval(post('selling_price', 0));
        $customer_id = post('customer_id', '');
        $supplier_id = post('supplier_id', '');
        $reference_type = trim((string)post('reference_type'));
        $reference = sanitize_input(post('reference'));
        $movement_reason = sanitize_input(post('movement_reason'));
        $bin_id = post('bin_id') ? intval(post('bin_id')) : null;
        $notes = sanitize_input(post('notes'));

        if ($item_id <= 0) {
            $errors['item_id'] = err_required();
        }
        if ($location_id <= 0) {
            $errors['location_id'] = err_required();
        }
        if ($transaction_type === '' || !isset($typeOptions[$transaction_type])) {
            $errors['transaction_type'] = err_required();
        }
        if ($quantity <= 0) {
            $errors['quantity'] = err_required();
        }
        if ($unit_cost < 0) {
            $errors['unit_cost'] = 'Cost cannot be negative.';
        }
        if ($selling_price < 0) {
            $errors['selling_price'] = 'Selling price cannot be negative.';
        }

        $direction = null;
        if (isset($typeOptions[$transaction_type])) {
            $direction = intval($typeOptions[$transaction_type]['direction']);
            if ($direction === 0) {
                if (!in_array($direction_override, ['in', 'out'], true)) {
                    $errors['direction_override'] = 'Please fill Direction that field';
                } else {
                    $direction = $direction_override === 'in' ? 1 : -1;
                }
            }

            if (!empty($typeOptions[$transaction_type]['requires_customer']) && empty($customer_id)) {
                $errors['customer_id'] = 'Please fill Customer that field';
            }
            if (!empty($typeOptions[$transaction_type]['requires_supplier']) && empty($supplier_id)) {
                $errors['supplier_id'] = 'Please fill Supplier that field';
            }
        }

        $quantity_signed = ($direction ?? 0) * $quantity;
        if ($quantity_signed === 0 && empty($errors)) {
            $errors['quantity'] = 'Computed movement quantity is invalid.';
        }

        if (empty($errors)) {
            try {
                db_begin_transaction();
                
                inventory_apply_stock_movement($item_id, $location_id, $quantity_signed, $bin_id);

                inventory_record_transaction([
                    'item_id' => $item_id,
                    'location_id' => $location_id,
                    'bin_id' => $bin_id,
                    'transaction_type' => $transaction_type,
                    'movement_reason' => $movement_reason,
                    'quantity_signed' => $quantity_signed,
                    'unit_cost' => $unit_cost,
                    'selling_price' => $selling_price,
                    'customer_id' => $customer_id !== '' ? intval($customer_id) : null,
                    'supplier_id' => $supplier_id !== '' ? intval($supplier_id) : null,
                    'reference' => $reference,
                    'reference_type' => $reference_type,
                    'notes' => $notes,
                    'created_by' => intval($_SESSION['user_id'])
                ]);

                // Synchronization to warehouse_inventory and stock_levels is now handled
                // centrally by inventory_apply_stock_movement above.
                // -----------------------------------------------------------------------

                db_commit();
                set_flash('Transaction added successfully!', 'success');
                redirect('transactions.php');
            } catch (Exception $e) {
                db_rollback();
                log_error("Error adding transaction: " . $e->getMessage());
                $errors[] = sanitize_db_error($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-exchange-alt me-3"></i>Add Inventory Transaction</h1>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <strong>Please correct the following errors:</strong>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= escape_html($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="item_id" class="form-label">Item <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['item_id']) ? 'is-invalid' : '' ?>" id="item_id" name="item_id" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= post('item_id') == $item['id'] ? 'selected' : '' ?>
                                        data-unit="<?= escape_html($item['unit_of_measure']) ?>"
                                        data-cost="<?= escape_html((string)$item['cost_price']) ?>"
                                        data-price="<?= escape_html((string)$item['selling_price']) ?>">
                                    <?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['item_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['item_id'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="location_id" class="form-label">Location <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['location_id']) ? 'is-invalid' : '' ?>" id="location_id" name="location_id" required>
                            <option value="">Select Location</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= $location['id'] ?>" <?= (post('location_id') ?: ($preselect_location_id > 0 ? (intval($location['id']) === $preselect_location_id) : false)) ? 'selected' : '' ?>>
                                    <?= escape_html($location['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['location_id'])): ?>
                            <div class="invalid-feedback"><?= $errors['location_id'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="transaction_type" class="form-label">Transaction Type <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['transaction_type']) ? 'is-invalid' : '' ?>" id="transaction_type" name="transaction_type" required>
                            <option value="">Select Type</option>
                            <?php foreach ($typeOptions as $key => $meta): ?>
                                <option value="<?= escape_html($key) ?>" <?= post('transaction_type') === $key ? 'selected' : '' ?>><?= escape_html($meta['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['transaction_type'])): ?>
                            <div class="invalid-feedback"><?= $errors['transaction_type'] ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="quantity" class="form-label">Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="form-control <?= isset($errors['quantity']) ? 'is-invalid' : '' ?>" id="quantity" name="quantity" min="1" value="<?= escape_html(post('quantity')) ?>" required>
                        <?php if (isset($errors['quantity'])): ?>
                            <div class="invalid-feedback"><?= $errors['quantity'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row" id="directionRow" style="display:none;">
                    <div class="col-md-6 mb-3">
                        <label for="direction_override" class="form-label">Direction <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['direction_override']) ? 'is-invalid' : '' ?>" id="direction_override" name="direction_override">
                            <option value="">Select Direction</option>
                            <option value="in" <?= post('direction_override') === 'in' ? 'selected' : '' ?>>Increase Stock (+)</option>
                            <option value="out" <?= post('direction_override') === 'out' ? 'selected' : '' ?>>Decrease Stock (-)</option>
                        </select>
                        <?php if (isset($errors['direction_override'])): ?>
                            <div class="invalid-feedback"><?= $errors['direction_override'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="unit_of_measure_display" class="form-label">Unit</label>
                        <input type="text" class="form-control" id="unit_of_measure_display" readonly>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="bin_id" class="form-label">Bin Location (Optional)</label>
                        <select class="form-select" id="bin_id" name="bin_id">
                            <option value="">Select Location First</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="unit_cost" class="form-label">Unit Cost</label>
                        <input type="number" class="form-control" id="unit_cost" name="unit_cost" min="0" step="0.0001" value="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="selling_price" class="form-label">Selling Price</label>
                        <input type="number" class="form-control" id="selling_price" name="selling_price" min="0" step="0.0001" value="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="reference_type" class="form-label">Reference Type</label>
                        <select class="form-select" id="reference_type" name="reference_type">
                            <option value="">None</option>
                            <option value="purchase_order">Purchase Order</option>
                            <option value="sales_order">Sales Order</option>
                            <option value="stock_count">Stock Count</option>
                            <option value="manual">Manual</option>
                        </select>
                    </div>
                </div>

                <div class="row" id="counterpartyRow">
                    <div class="col-md-6 mb-3" id="customerGroup" style="display:none;">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select class="form-select" id="customer_id" name="customer_id">
                            <option value="">Select Customer</option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?= $customer['id'] ?>">
                                    <?= escape_html($customer['customer_code']) ?> - <?= escape_html($customer['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3" id="supplierGroup" style="display:none;">
                        <label for="supplier_id" class="form-label">Supplier</label>
                        <select class="form-select" id="supplier_id" name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>">
                                    <?= escape_html($supplier['supplier_code']) ?> - <?= escape_html($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Transaction
                    </button>
                    <a href="transactions.php" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
(function() {
    const itemSelect = document.getElementById('item_id');
    const txnType = document.getElementById('transaction_type');
    const directionRow = document.getElementById('directionRow');
    const directionSelect = document.getElementById('direction_override');
    const unitDisplay = document.getElementById('unit_of_measure_display');
    const unitCost = document.getElementById('unit_cost');
    const sellingPrice = document.getElementById('selling_price');
    const customerGroup = document.getElementById('customerGroup');
    const supplierGroup = document.getElementById('supplierGroup');
    const customerSelect = document.getElementById('customer_id');
    const supplierSelect = document.getElementById('supplier_id');

    const directionNeeded = ['stock_count', 'stock_adjustment'];
    const customerTypes = ['sale', 'stock_return_customer'];
    const supplierTypes = ['purchase', 'stock_return_supplier'];

    function updateItemDefaults() {
        const selected = itemSelect.options[itemSelect.selectedIndex];
        if (!selected) return;
        unitDisplay.value = selected.getAttribute('data-unit') || 'PCS';
        if (!unitCost.value || Number(unitCost.value) === 0) {
            unitCost.value = selected.getAttribute('data-cost') || '0';
        }
        if (!sellingPrice.value || Number(sellingPrice.value) === 0) {
            sellingPrice.value = selected.getAttribute('data-price') || '0';
        }
    }

    function updateTypeUI() {
        const value = txnType.value;
        const showDirection = directionNeeded.includes(value);
        directionRow.style.display = showDirection ? '' : 'none';
        directionSelect.required = showDirection;
        if (!showDirection) directionSelect.value = '';

        const showCustomer = customerTypes.includes(value);
        const showSupplier = supplierTypes.includes(value);

        customerGroup.style.display = showCustomer ? '' : 'none';
        supplierGroup.style.display = showSupplier ? '' : 'none';
        customerSelect.required = showCustomer;
        supplierSelect.required = showSupplier;
        if (!showCustomer) customerSelect.value = '';
        if (!showSupplier) supplierSelect.value = '';
    }

    const locationSelect = document.getElementById('location_id');
    const binSelect = document.getElementById('bin_id');

    function loadBins() {
        const locId = locationSelect.value;
        binSelect.innerHTML = '<option value=\"\">Loading...</option>';
        if (!locId) {
            binSelect.innerHTML = '<option value=\"\">Select Location First</option>';
            return;
        }

        // We need the warehouse_id to get bins. Or we can create an ajax_get_bins_by_location.php
        // For now, let's call a new endpoint or pass location_id to existing if it supports it.
        // Actually, existing ajax_get_bins.php takes warehouse_id. 
        // We will fetch bins based on location_id by adding a new parameter to ajax_get_bins.php or using a new file.
        // I will use fetch to get bins by location_id
        fetch(`ajax_get_bins.php?location_id=\${locId}`)
            .then(r => r.json())
            .then(data => {
                binSelect.innerHTML = '<option value=\"\">No Bin (Default)</option>';
                data.forEach(b => {
                    binSelect.innerHTML += `<option value=\"\${b.bin_id}\">\${b.bin_location}</option>`;
                });
            })
            .catch(e => {
                binSelect.innerHTML = '<option value=\"\">Error Loading Bins</option>';
            });
    }

    itemSelect.addEventListener('change', updateItemDefaults);
    txnType.addEventListener('change', updateTypeUI);
    locationSelect.addEventListener('change', loadBins);

    updateItemDefaults();
    updateTypeUI();
    if(locationSelect.value) loadBins();
})();
</script>
";
include __DIR__ . '/../../templates/footer.php';
?>
