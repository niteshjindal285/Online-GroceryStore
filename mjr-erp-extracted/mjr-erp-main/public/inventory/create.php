<?php
/**
 * Inventory Module - Create Item
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_inventory');

$page_title = 'Add Inventory Item - MJR Group ERP';
$selected_company_id = active_company_id(1);
$errors = [];

// Get categories and units
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name");
$units = db_fetch_all("SELECT id, code, name FROM units_of_measure WHERE is_active = 1 ORDER BY name");
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name");
$tax_classes = db_fetch_all("SELECT id, tax_name as name, tax_rate as rate FROM tax_configurations WHERE is_active = 1 ORDER BY tax_name");
$companies = is_super_admin()
    ? db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name")
    : db_fetch_all("SELECT id, name FROM companies WHERE id = ? AND is_active = 1 ORDER BY name", [$selected_company_id]);




// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (!verify_csrf_token($csrf_token)) {
        $errors['csrf'] = 'Invalid security token. Please try again.';
    } else {
        $errors = [];
        // Get and validate form data
        $code = sanitize_input(post('code'));
        $name = sanitize_input(post('name'));
        $description = sanitize_input(post('description'));
        $category_id = post('category_id') ?: null;
        $unit_of_measure_id = post('unit_of_measure_id');
        $cost_price = post('cost_price', 0);
        $average_cost = post('average_cost', 0);
        $selling_price = post('selling_price', 0);
        $reorder_level = post('reorder_level', 0);
        $reorder_quantity = post('reorder_quantity', 0);
        $barcode = sanitize_input(post('barcode'));
        $track_serial = post('track_serial') ? 1 : 0;
        $track_lot = post('track_lot') ? 1 : 0;
        $is_manufactured = post('is_manufactured') ? 1 : 0;
        $purchase_unit_id = post('purchase_unit_id') ?: null;
        $purchase_conversion_factor = post('purchase_conversion_factor', 1.000000);
        $supplier_id = post('supplier_id') ?: null;
        $tax_class_id = post('tax_class_id') ?: null;
        $price_includes_tax = post('price_includes_tax') ? 1 : 0;
        $company_id = post('company_id') ?: null;
        $custom_fields = sanitize_input(post('custom_fields'));



        
        // Validation
        if (empty($code)) $errors['code'] = err_required();
        if (empty($name)) $errors['name'] = err_required();
        if (empty($unit_of_measure_id)) $errors['unit_of_measure_id'] = err_required();
        
        // Check if code already exists
        if (!empty($code)) {
            $existing = db_fetch("SELECT id FROM inventory_items WHERE code = ?", [$code]);
            if ($existing) {
                $errors['code'] = 'Item code already exists.';
            }
        }
        
        if (!empty($errors) && !isset($errors['csrf'])) {
            $errors['form'] = 'Please fix the validation errors.';
        }
        
        if (empty($errors)) {
            try {
                $sql = "INSERT INTO inventory_items 
                        (code, name, description, category_id, company_id, unit_of_measure_id, cost_price, average_cost, selling_price, 
                        reorder_level, reorder_quantity, barcode, track_serial, track_lot, is_manufactured, 
                         purchase_unit_id, purchase_conversion_factor, supplier_id, tax_class_id, price_includes_tax, custom_fields, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())";



                
                $params = [
                    $code, $name, $description, $category_id, $company_id, $unit_of_measure_id, 
                    $cost_price, $average_cost, $selling_price, $reorder_level, $reorder_quantity, 
                    $barcode, $track_serial, $track_lot, $is_manufactured,
                    $purchase_unit_id, $purchase_conversion_factor, $supplier_id, $tax_class_id, $price_includes_tax, $custom_fields
                ];



                
                $item_id = db_insert($sql, $params);
                
                set_flash('Inventory item created successfully!', 'success');
                redirect('view.php?id=' . $item_id);
                
            } catch (Exception $e) {
                log_error("Error creating inventory item: " . $e->getMessage());
                $errors[] = sanitize_db_error($e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="mb-4">
    <h2><i class="fas fa-plus me-2"></i>Add New Inventory Item</h2>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Inventory</a></li>
            <li class="breadcrumb-item active">Add Item</li>
        </ol>
    </nav>
</div>

<?php if (isset($errors['csrf'])): ?>
<div class="alert alert-danger">
    <?= escape_html($errors['csrf']) ?>
</div>
<?php endif; ?>

<?php if (isset($errors['form'])): ?>
<div class="alert alert-danger">
    <strong>Please correct the following errors:</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $key => $error): ?>
            <?php if ($key !== 'csrf' && $key !== 'form'): ?>
                <li><?= escape_html($error) ?></li>
            <?php endif; ?>
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
                    <label for="code" class="form-label">Item Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['code']) ? 'is-invalid' : '' ?>" id="code" name="code" value="<?= escape_html(post('code')) ?>" required>
                    <?php if (isset($errors['code'])): ?>
                        <div class="invalid-feedback"><?= $errors['code'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= escape_html(post('name')) ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="invalid-feedback"><?= $errors['name'] ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"><?= escape_html(post('description')) ?></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" <?= post('category_id') == $category['id'] ? 'selected' : '' ?>>
                                <?= escape_html($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="company_id" class="form-label">Company</label>
                    <div class="input-group">
                        <?php if (!empty($_SESSION['company_id'])): ?>
                            <input type="text" class="form-control" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                            <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                        <?php else: ?>
                            <select class="form-select" id="company_id" name="company_id">
                                <option value="">Select Company</option>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= $comp['id'] ?>" <?= post('company_id') == $comp['id'] ? 'selected' : '' ?>>
                                        <?= escape_html($comp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <a href="../companies/create.php" class="btn btn-outline-secondary" title="Manage Companies">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label for="unit_of_measure_id" class="form-label">Base Unit of Measure <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select class="form-select <?= isset($errors['unit_of_measure_id']) ? 'is-invalid' : '' ?>" id="unit_of_measure_id" name="unit_of_measure_id" required>
                            <option value="">Select Base Unit</option>
                            <?php foreach ($units as $unit): ?>
                                <option value="<?= $unit['id'] ?>" <?= post('unit_of_measure_id') == $unit['id'] ? 'selected' : '' ?>>
                                    <?= escape_html($unit['name']) ?> (<?= escape_html($unit['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addUnitModal" title="Add New Unit">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="deleteSelectedUnit()" title="Delete Selected Unit">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <?php if (isset($errors['unit_of_measure_id'])): ?>
                        <div class="invalid-feedback d-block"><?= $errors['unit_of_measure_id'] ?></div>
                    <?php endif; ?>
                </div>
            </div>


            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="supplier_id" class="form-label">Default Supplier</label>
                    <div class="input-group">
                        <select class="form-select" id="supplier_id" name="supplier_id">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= $supplier['id'] ?>" <?= post('supplier_id') == $supplier['id'] ? 'selected' : '' ?>>
                                    <?= escape_html($supplier['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a href="../inventory/supplier/add_supplier.php" class="btn btn-outline-secondary" title="Add New Supplier">
                            <i class="fas fa-plus"></i>
                        </a>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="tax_class_id" class="form-label">Tax Class</label>
                    <select class="form-select" id="tax_class_id" name="tax_class_id">
                        <option value="">No Tax</option>
                        <?php foreach ($tax_classes as $tax): ?>
                            <option value="<?= $tax['id'] ?>" <?= post('tax_class_id') == $tax['id'] ? 'selected' : '' ?>>
                                <?= escape_html($tax['name']) ?> (<?= (float)$tax['rate'] ?>%)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="cost_price" class="form-label">Supplier Cost Price</label>
                    <input type="number" step="0.01" class="form-control" id="cost_price" name="cost_price" value="<?= escape_html(post('cost_price', '0.00')) ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="average_cost" class="form-label">Average Unit Price</label>
                    <input type="number" step="0.01" class="form-control" id="average_cost" name="average_cost" value="<?= escape_html(post('average_cost', '0.00')) ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="selling_price" class="form-label">Selling Price</label>
                    <div class="input-group">
                        <input type="number" step="0.01" class="form-control" id="selling_price" name="selling_price" value="<?= escape_html(post('selling_price', '0.00')) ?>">
                        <div class="input-group-text">
                            <input class="form-check-input mt-0" type="checkbox" id="price_includes_tax" name="price_includes_tax" value="1" <?= post('price_includes_tax') ? 'checked' : '' ?>>
                            <label class="form-check-label ms-2 small" for="price_includes_tax">Includes Tax (CT 15%)</label>
                        </div>

                    </div>
                </div>

            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="reorder_level" class="form-label">Reorder Level</label>
                    <input type="number" class="form-control" id="reorder_level" name="reorder_level" value="<?= escape_html(post('reorder_level', '0')) ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="reorder_quantity" class="form-label">Reorder Quantity</label>
                    <input type="number" class="form-control" id="reorder_quantity" name="reorder_quantity" value="<?= escape_html(post('reorder_quantity', '0')) ?>">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label for="barcode" class="form-label">Barcode / Scanner</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" class="form-control" id="barcode" name="barcode" value="<?= escape_html(post('barcode')) ?>" placeholder="Scan or enter item barcode">
                    </div>
                </div>

            </div>
            
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="track_serial" name="track_serial" value="1" <?= post('track_serial') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="track_serial">
                        Track Serial Numbers
                    </label>
                </div>
                <div id="track_serial_input_wrap" class="mt-2 <?= post('track_serial') ? '' : 'd-none' ?>">
                    <input type="text" class="form-control" id="serial_number" name="serial_number" value="<?= escape_html(post('serial_number')) ?>" placeholder="Serial Number (optional)" <?= post('track_serial') ? '' : 'disabled' ?>>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="track_lot" name="track_lot" value="1" <?= post('track_lot') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="track_lot">
                        Track Lot Numbers
                    </label>
                </div>
                <div id="track_lot_input_wrap" class="mt-2 <?= post('track_lot') ? '' : 'd-none' ?>">
                    <input type="text" class="form-control" id="lot_number" name="lot_number" value="<?= escape_html(post('lot_number')) ?>" placeholder="Lot Number (optional)" <?= post('track_lot') ? '' : 'disabled' ?>>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_manufactured" name="is_manufactured" value="1" <?= post('is_manufactured') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_manufactured">
                        Is Manufactured Item
                    </label>
                </div>
                <div id="is_manufactured_input_wrap" class="mt-2 <?= post('is_manufactured') ? '' : 'd-none' ?>">
                    <input type="text" class="form-control" id="manufactured_details" name="manufactured_details" value="<?= escape_html(post('manufactured_details')) ?>" placeholder="Manufacturing Details (optional)" <?= post('is_manufactured') ? '' : 'disabled' ?>>
                </div>
                <div class="mt-3">
                    <label for="custom_fields" class="form-label">Custom Entry Fields</label>
                    <textarea class="form-control" id="custom_fields" name="custom_fields" rows="3" placeholder="Enter custom details for this item..."><?= escape_html(post('custom_fields')) ?></textarea>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Item
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggles = [
        { checkbox: 'track_serial', wrapper: 'track_serial_input_wrap', input: 'serial_number' },
        { checkbox: 'track_lot', wrapper: 'track_lot_input_wrap', input: 'lot_number' },
        { checkbox: 'is_manufactured', wrapper: 'is_manufactured_input_wrap', input: 'manufactured_details' }
    ];

    toggles.forEach(function (toggle) {
        const checkbox = document.getElementById(toggle.checkbox);
        const wrapper = document.getElementById(toggle.wrapper);
        const input = document.getElementById(toggle.input);

        if (!checkbox || !wrapper || !input) return;

        const updateVisibility = function () {
            const show = checkbox.checked;
            wrapper.classList.toggle('d-none', !show);
            input.disabled = !show;
        };

        checkbox.addEventListener('change', updateVisibility);
        updateVisibility();
    });

    // Barcode scanner focus
    const barcodeInput = document.getElementById('barcode');
    if (barcodeInput) {
        barcodeInput.focus();
        // Add listener for barcode scanner (ENTER key usually follows scan)
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Optionally move to next field or validate
            }
        });
    }
});

</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1" aria-labelledby="addUnitModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content text-white bg-dark">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="addUnitModalLabel">Add New Unit of Measure</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="unitAlert" class="alert d-none"></div>
                <div class="mb-3">
                    <label for="new_unit_name" class="form-label">Unit Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="new_unit_name" placeholder="e.g. Kilograms">
                </div>
                <div class="mb-3">
                    <label for="new_unit_code" class="form-label">Unit Code <span class="text-danger">*</span></label>
                    <input type="text" class="form-control bg-dark text-white border-secondary" id="new_unit_code" placeholder="e.g. KG">
                </div>
                <div class="mb-3">
                    <label for="new_unit_description" class="form-label">Description</label>
                    <textarea class="form-control bg-dark text-white border-secondary" id="new_unit_description" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNewUnit()">Save Unit</button>
            </div>
        </div>
    </div>
</div>

<script>
function submitNewUnit() {
    const name = document.getElementById('new_unit_name').value;
    const code = document.getElementById('new_unit_code').value;
    const desc = document.getElementById('new_unit_description').value;
    const alertBox = document.getElementById('unitAlert');
    
    if (!name || !code) {
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'Name and Code are required.';
        alertBox.classList.remove('d-none');
        return;
    }

    const formData = new FormData();
    formData.append('name', name);
    formData.append('code', code);
    formData.append('description', desc);
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');

    fetch('ajax_add_unit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add to dropdown and select it
            const select = document.getElementById('unit_of_measure_id');
            const option = new Option(`${data.name} (${data.code})`, data.id, true, true);
            select.add(option);
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('addUnitModal'));
            modal.hide();
            
            // Reset form
            document.getElementById('new_unit_name').value = '';
            document.getElementById('new_unit_code').value = '';
            document.getElementById('new_unit_description').value = '';
            alertBox.classList.add('d-none');
        } else {
            alertBox.className = 'alert alert-danger';
            alertBox.textContent = data.message || 'Error adding unit.';
            alertBox.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alertBox.className = 'alert alert-danger';
        alertBox.textContent = 'An unexpected error occurred.';
        alertBox.classList.remove('d-none');
    });
}

function deleteSelectedUnit() {
    const select = document.getElementById('unit_of_measure_id');
    const unitId = select.value;
    
    if (!unitId) {
        alert('Please select a unit to delete.');
        return;
    }

    const unitName = select.options[select.selectedIndex].text;
    
    if (!confirm(`Are you sure you want to delete the unit "${unitName}"? This action cannot be undone.`)) {
        return;
    }

    const formData = new FormData();
    formData.append('id', unitId);
    formData.append('csrf_token', '<?= generate_csrf_token() ?>');

    fetch('ajax_delete_unit.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove from dropdown
            select.remove(select.selectedIndex);
            select.value = '';
            alert(data.message);
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the unit.');
    });
}
</script>




