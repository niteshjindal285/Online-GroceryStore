<?php
/**
 * Advanced Stock Transfer Module - Multi-Item Support with Bin Locations
 * Based on Screen4 Specification + USER requested Bin & Sender Filtering
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/inventory_transaction_service.php';

require_login();

$selected_company_id = active_company_id(1);
$message = '';
$error = '';

if (is_post()) {
    $action = post('action', 'save_draft');
    $status = ($action === 'submit_approval') ? 'pending_approval' : 'draft';

    $ref_no = sanitize_input(post('reference_no') ?? 'ST-' . strtoupper(substr(uniqid(), -5)));
    $transfer_date = to_db_date(post('transfer_date')) ?? date('Y-m-d');
    $company_id = (int) post('company_id', $selected_company_id);
    $transfer_type = sanitize_input(post('transfer_type', 'Internal'));
    $manager_id = (int) post('manager_id', 0);
    $ref_doc = sanitize_input(post('ref_doc', ''));
    
    $source_loc_id = (int) post('source_location_id', 0);
    $source_bin_head = post('source_bin_head') ? (int) post('source_bin_head') : null;
    $dest_loc_id = (int) post('dest_location_id', 0);
    $dest_bin_head = post('dest_bin_head') ? (int) post('dest_bin_head') : null;
    $category_id = (int) post('category_id', 0);
    
    $damage_reason = sanitize_input(post('damage_reason', ''));
    $damage_category = sanitize_input(post('damage_category', ''));
    $write_off_required = isset($_POST['write_off_required']) ? 1 : 0;
    
    $warehouse_remarks = sanitize_input(post('warehouse_remarks', ''));
    $supervisor_notes = sanitize_input(post('supervisor_notes', ''));
    $general_remarks = sanitize_input(post('general_remarks', ''));

    $item_ids = post('item_id', []);
    $quantities = post('quantity', []);
    $source_bins = post('source_bin', []);
    $dest_bins = post('dest_bin', []);
    $item_remarks = post('remarks', []);
    $batch_nos = post('batch_no', []);
    $unit_costs = post('unit_cost', []);

    $errors = [];
    if (empty($transfer_date))    $errors['transfer_date'] = err_required();
    if (empty($company_id))       $errors['company_id'] = err_required();
    if (empty($manager_id))       $errors['manager_id'] = err_required();
    if (empty($source_loc_id))    $errors['source_location_id'] = err_required();
    if (empty($dest_loc_id))      $errors['dest_location_id'] = err_required();

    if ($source_loc_id > 0 && $dest_loc_id > 0 && $source_loc_id === $dest_loc_id) {
        $errors['dest_location_id'] = 'Source and destination locations cannot be the same.';
    }

    $has_item = false;
    foreach ($item_ids as $idx => $item_id) {
        if (!empty($item_id)) {
            $has_item = true;
            $qty = (float)($quantities[$idx] ?? 0);
            if ($qty <= 0) {
                $errors['items'] = 'Please fill all Transfer Qty fields with values greater than 0';
                break;
            }
        }
    }
    if (!$has_item) $errors['items'] = err_required();

    if (!empty($errors)) {
        $error = "Please fill that section";
    } else {
        try {
            db_begin_transaction();

            // 1. Insert Header
            $header_id = db_insert("
                INSERT INTO transfer_headers 
                (transfer_number, transfer_date, company_id, transfer_type, source_location_id, source_bin_head, 
                 dest_location_id, dest_bin_head, category_id, status, requested_by, manager_id, remarks, 
                 damage_reason, damage_category, write_off_required, warehouse_remarks, supervisor_notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $ref_no, $transfer_date, $company_id, $transfer_type, $source_loc_id, $source_bin_head,
                $dest_loc_id, $dest_bin_head, $category_id, $status, current_user_id(), $manager_id, 
                $general_remarks, $damage_reason, $damage_category, $write_off_required, 
                $warehouse_remarks, $supervisor_notes
            ]);

            // 2. Insert Items
            foreach ($item_ids as $index => $item_id) {
                if (empty($item_id)) continue;
                $qty = (float) ($quantities[$index] ?? 0);
                if ($qty <= 0) continue;

                $unit_cost = (float) (@$unit_costs[$index] ?? 0);
                $batch_no = sanitize_input(@$batch_nos[$index] ?? '');
                $row_note = sanitize_input(@$item_remarks[$index] ?? '');
                $line_total = $qty * $unit_cost;

                db_query("
                    INSERT INTO transfer_items (transfer_id, item_id, quantity, unit_cost, batch_number, line_total, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [$header_id, $item_id, $qty, $unit_cost, $batch_no, $line_total, $row_note]);
            }

            // 3. Log History
            db_query("
                INSERT INTO transfer_history (transfer_id, status, notes, changed_by)
                VALUES (?, ?, ?, ?)
            ", [$header_id, $status, "Stock transfer request created and " . str_replace('_', ' ', $status), current_user_id()]);

            db_commit();
            set_flash("Stock transfer " . ($status === 'draft' ? "saved as draft" : "submitted for approval") . "!", "success");
            redirect("view_transfer.php?id=" . $header_id);
        } catch (Exception $e) {
            db_rollback();
            $error = sanitize_db_error($e->getMessage());
        }
    }
}

// Fetch master data
$locations = db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
$managers = db_fetch_all("SELECT id, username, full_name FROM users WHERE role IN ('manager') AND is_active = 1 AND company_id = ? ORDER BY username", [$selected_company_id]);
$categories = db_fetch_all("SELECT id, name FROM categories ORDER BY name");
$warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 AND id = ? ORDER BY name", [$selected_company_id]);

$page_title = 'Advanced Stock Transfer - MJR Group';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-1"><i class="fas fa-exchange-alt text-info me-2"></i>Stock Transfer Screen
            </h2>
            <p class="text-secondary mb-0">Internal movement of goods with Optional Bin Locations</p>
        </div>
        <div>
            <a href="transfer_history.php" class="btn btn-outline-light btn-sm"><i class="fas fa-list me-2"></i>Transfer
                History</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="transferForm">
        <!-- 1 STOCK TRANSFER HEADER (Document Information) -->
        <div class="section-container mb-4">
            <div class="d-flex align-items-center mb-3">
                <h3 class="text-white fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Stock Transfer Header
                </h3>
            </div>
            <p class="text-secondary small mb-3">Identifies the transfer transaction.</p>

            <div class="card premium-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-bordered mb-0">
                            <thead class="bg-dark-light">
                                <tr>
                                    <th class="py-3 px-4" style="width: 300px;">Field</th>
                                    <th class="py-3 px-4">Selection / Information</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Transfer
                                            Number</span></td>
                                    <td class="px-4 py-2">
                                        <input type="text" name="reference_no"
                                            class="form-control bg-dark text-info fw-bold border-0"
                                            value="ST-<?= strtoupper(substr(uniqid(), -5)) ?>" readonly>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Transfer
                                            Date</span></td>
                                    <td class="px-4 py-2">
                                        <input type="text" name="transfer_date"
                                            class="form-control bg-dark text-white border-0 <?= isset($errors['transfer_date']) ? 'is-invalid' : '' ?>"
                                            value="<?= escape_html(post('transfer_date', format_date(date('Y-m-d')))) ?>" placeholder="DD-MM-YYYY" required>
                                        <?php if (isset($errors['transfer_date'])): ?>
                                            <div class="invalid-feedback d-block"><?= $errors['transfer_date'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Company /
                                            Subsidiary</span></td>
                                    <td class="px-4 py-2">
                                        <?php if (!empty($_SESSION['company_id'])): ?>
                                            <input type="text" class="form-control bg-dark text-info fw-bold border-0" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                            <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                        <?php else: ?>
                                            <select name="company_id"
                                                class="form-select bg-dark text-white border-0 select2 <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>">
                                                <option value="">Select Company</option>
                                                <?php foreach ($companies as $comp): ?>
                                                    <option value="<?= $comp['id'] ?>" <?= post('company_id') == $comp['id'] ? 'selected' : '' ?>><?= escape_html($comp['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                        <?php if (isset($errors['company_id'])): ?>
                                            <div class="invalid-feedback d-block"><?= $errors['company_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Transfer
                                            Type</span></td>
                                    <td class="px-4 py-2">
                                        <select name="transfer_type"
                                            class="form-select bg-dark text-white border-0 select2">
                                            <option value="warehouse">Warehouse Transfer</option>
                                            <option value="damage">Damage Transfer</option>
                                            <option value="write_off">Write-Off</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span
                                            class="text-secondary fw-bold">Status</span></td>
                                    <td class="px-4 py-2"><span
                                            class="badge bg-secondary border border-light">DRAFT</span></td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Created
                                            By</span></td>
                                    <td class="px-4 py-2"><span
                                            class="text-info fw-bold"><?= escape_html($_SESSION['username']) ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Approving
                                            Manager</span></td>
                                    <td class="px-4 py-2">
                                        <select name="manager_id"
                                            class="form-select bg-dark text-white border-0 select2 <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">Select Manager</option>
                                            <?php foreach ($managers as $m): ?>
                                                <option value="<?= $m['id'] ?>" <?= post('manager_id') == $m['id'] ? 'selected' : '' ?>><?= escape_html($m['username']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['manager_id'])): ?>
                                            <div class="invalid-feedback d-block"><?= $errors['manager_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Reference
                                            Document</span></td>
                                    <td class="px-4 py-2">
                                        <input type="text" name="ref_doc"
                                            class="form-control bg-dark text-white border-0"
                                            placeholder="Link to Invoice / GSRN (Optional)">
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2 SOURCE & DESTINATION LOCATION -->
        <div class="section-container mb-4">
            <div class="d-flex align-items-center mb-3">
                <h3 class="text-white fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Source & Destination
                    Location</h3>
            </div>
            <p class="text-secondary small mb-3">This defines where stock moves <strong>from and to</strong>.</p>

            <div class="card premium-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-bordered mb-0">
                            <thead class="bg-dark-light">
                                <tr>
                                    <th class="py-3 px-4" style="width: 300px;">Field</th>
                                    <th class="py-3 px-4">Selection / Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Source
                                            Warehouse</span></td>
                                    <td class="px-4 py-2">
                                        <select name="source_location_id" id="source_warehouse"
                                            class="form-select bg-dark text-white border-0 select2 <?= isset($errors['source_location_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">Select Source Warehouse</option>
                                            <?php foreach ($warehouses as $w): ?>
                                                <option value="<?= $w['id'] ?>" <?= post('source_location_id') == $w['id'] ? 'selected' : '' ?>><?= escape_html($w['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['source_location_id'])): ?>
                                            <div class="invalid-feedback d-block"><?= $errors['source_location_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Source
                                            Bin</span></td>
                                    <td class="px-4 py-2">
                                        <select name="source_bin_head" id="source_bin_head"
                                            class="form-select bg-dark text-white border-0 select2">
                                            <option value="">Select Source Bin (Optional)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Destination
                                            Warehouse</span></td>
                                    <td class="px-4 py-2">
                                        <select name="dest_location_id" id="dest_warehouse"
                                            class="form-select bg-dark text-white border-0 select2 <?= isset($errors['dest_location_id']) ? 'is-invalid' : '' ?>" required>
                                            <option value="">Select Destination Warehouse</option>
                                            <?php foreach ($warehouses as $w): ?>
                                                <option value="<?= $w['id'] ?>" <?= post('dest_location_id') == $w['id'] ? 'selected' : '' ?>><?= escape_html($w['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (isset($errors['dest_location_id'])): ?>
                                            <div class="invalid-feedback d-block"><?= $errors['dest_location_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Destination
                                            Bin</span></td>
                                    <td class="px-4 py-2">
                                        <select name="dest_bin_head" id="dest_bin_head"
                                            class="form-select bg-dark text-white border-0 select2">
                                            <option value="">Select Destination Bin (Optional)</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Stock
                                            Category</span></td>
                                    <td class="px-4 py-2">
                                        <select name="category_id"
                                            class="form-select bg-dark text-white border-0 select2">
                                            <option value="">Select Category</option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>"><?= escape_html($cat['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="align-middle px-4 py-3"><span class="text-secondary fw-bold">Quick
                                            Actions</span></td>
                                    <td class="px-4 py-3">
                                        <a href="locations.php" target="_blank"
                                            class="btn btn-sm btn-outline-info me-2"><i class="fas fa-plus me-1"></i>
                                            Create New Warehouse</a>
                                        <a href="locations.php" target="_blank"
                                            class="btn btn-sm btn-outline-warning"><i class="fas fa-plus me-1"></i>
                                            Create New Bin</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3 ITEM TRANSFER TABLE -->
        <div class="section-container mb-4">
            <div class="d-flex align-items-center mb-3">
                <h3 class="text-white fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Item Transfer Table</h3>
            </div>
            <p class="text-secondary small mb-3">This table shows <strong>which products are moving</strong>.</p>

            <div class="card premium-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" id="itemsTable">
                            <thead class="bg-dark-light">
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th style="width: 200px;">Item Code</th>
                                    <th style="width: 200px;">Item Name</th>
                                    <th>Category</th>
                                    <th>Avail. Stock</th>
                                    <th style="width: 100px;">Transfer Qty</th>
                                    <th>Unit</th>
                                    <th>Unit Cost</th>
                                    <th>Total Value</th>
                                    <th>Batch No</th>
                                    <th>Remarks</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <tr class="item-row">
                                    <td class="text-center align-middle">1</td>
                                    <td>
                                        <select name="item_id[]"
                                            class="form-select bg-dark text-white border-secondary item-select select2"
                                            required disabled>
                                            <option value="">Select Sender first</option>
                                        </select>
                                    </td>
                                    <td class="align-middle"><span class="item-name-text text-secondary">-</span></td>
                                    <td class="align-middle"><span class="item-category-text text-secondary">-</span>
                                    </td>
                                    <td class="align-middle text-center"><span
                                            class="available-stock-text fw-bold text-info">0.00</span></td>
                                    <td><input type="number" step="any" name="quantity[]"
                                            class="form-control bg-dark text-white border-secondary qty-input" required>
                                    </td>
                                    <td class="align-middle text-center"><span
                                            class="item-unit-text text-secondary">-</span></td>
                                    <td class="align-middle text-end">
                                        <input type="hidden" name="unit_cost[]" class="cost-hidden-input" value="0.00">
                                        <span class="item-cost-text text-secondary">0.00</span>
                                    </td>
                                    <td class="align-middle text-end">
                                        <input type="hidden" name="line_total[]" class="line-total-hidden-input" value="0.00">
                                        <span class="item-total-text text-success fw-bold">0.00</span>
                                    </td>
                                    <td><input type="text" name="batch_no[]"
                                            class="form-control bg-dark text-white border-secondary" placeholder="Opt">
                                    </td>
                                    <td><input type="text" name="remarks[]"
                                            class="form-control bg-dark text-white border-secondary" placeholder="Note">
                                    </td>
                                    <td class="align-middle text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-row"><i
                                                class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top border-secondary d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-info btn-sm fw-bold" id="addRow">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </button>
                        <div class="text-end">
                            <span class="text-secondary small me-2">GRAND TOTAL:</span>
                            <span class="fs-5 fw-bold text-info" id="grandTotalDisplay">0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-12">
                <!-- 5 WRITE-OFF / DAMAGE SECTION -->
                <div class="section-container">
                    <div class="d-flex align-items-center mb-3">
                        <h3 class="text-white fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Write-off /
                            Damage Section</h3>
                    </div>
                    <p class="text-secondary small mb-3">Your document requires <strong>damaged goods
                            management</strong>.</p>
                    <div class="card premium-card border-danger">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Damage Reason</label>
                                    <select name="damage_reason"
                                        class="form-select bg-dark text-white border-secondary">
                                        <option value="">N/A - Not Damaged</option>
                                        <option value="Broken">Broken</option>
                                        <option value="Expired">Expired</option>
                                        <option value="Faulty">Faulty</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-secondary">Damage Category</label>
                                    <select name="damage_category"
                                        class="form-select bg-dark text-white border-secondary">
                                        <option value="">Standard</option>
                                        <option value="Critical">Critical</option>
                                        <option value="Minor">Minor</option>
                                    </select>
                                </div>
                                <div class="col-12 d-flex align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="write_off_required"
                                            id="writeOffToggle">
                                        <label class="form-check-label text-white small ms-2"
                                            for="writeOffToggle">Write-off Required</label>
                                    </div>
                                    <div class="ms-auto text-end">
                                        <span class="text-secondary x-small d-block">ACCOUNTING IMPACT</span>
                                        <span class="text-warning fw-bold" id="impactAmount">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6 ATTACHMENTS & NOTES -->
        <div class="section-container mb-4">
            <div class="d-flex align-items-center mb-3">
                <h3 class="text-white fw-bold mb-0 text-uppercase" style="letter-spacing: 1px;">Attachments & Notes</h3>
            </div>
            <div class="card premium-card">
                <div class="card-body p-0">
                    <table class="table table-dark table-bordered mb-0">
                        <tbody>
                            <tr>
                                <td class="px-4 py-3 align-middle" style="width: 300px;"><span
                                        class="text-secondary fw-bold">Upload Document</span></td>
                                <td class="px-4 py-2">
                                    <input type="file" name="transfer_docs[]"
                                        class="form-control bg-dark text-white border-0" multiple>
                                    <div class="x-small text-muted mt-1">Example: Damage photo, Warehouse report</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 align-top"><span class="text-secondary fw-bold">Warehouse
                                        Remarks</span></td>
                                <td class="px-4 py-2">
                                    <textarea name="warehouse_remarks" class="form-control bg-dark text-white border-0"
                                        rows="2" placeholder="Dispatcher notes..."></textarea>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 align-top"><span class="text-secondary fw-bold">Supervisor
                                        Notes</span></td>
                                <td class="px-4 py-2">
                                    <textarea name="supervisor_notes" class="form-control bg-dark text-white border-0"
                                        rows="2" placeholder="Approval instruction..."></textarea>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 pb-5 text-end">
                    <button type="submit" name="action" value="save_draft"
                        class="btn btn-outline-secondary btn-lg px-4 me-2">
                        <i class="fas fa-save me-2"></i>Save as Draft
                    </button>
                    <button type="submit" name="action" value="submit_approval"
                        class="btn btn-info btn-lg px-5 text-dark fw-bold">
                        <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Datalists for Bin Suggestions -->
<datalist id="sourceBins"></datalist>
<datalist id="destBins"></datalist>

<style>
    .premium-card {
        background: rgba(30, 30, 45, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        overflow: hidden;
    }

    .section-badge {
        width: 40px;
        height: 40px;
        background: #0dcaf0;
        color: #000;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        font-weight: 800;
        font-size: 1.2rem;
    }

    .bg-dark-light {
        background: rgba(255, 255, 255, 0.03);
    }

    thead th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #7d8da1;
        border-bottom: 2px solid rgba(255, 255, 255, 0.1) !important;
        padding: 15px 10px !important;
    }

    tbody td {
        border-color: rgba(255, 255, 255, 0.05) !important;
    }

    .form-select.border-0 {
        box-shadow: none !important;
    }

    .available-stock-text {
        color: #0dcaf0;
        font-family: monospace;
    }
</style>

<script>
    $(document).ready(function () {
        let senderItems = [];

        function updateGrandTotal() {
            let total = 0;
            $('.item-total-text').each(function () {
                total += parseFloat($(this).text().replace(/,/g, '')) || 0;
            });
            $('#grandTotalDisplay').text(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#impactAmount').text(total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        }

        // Triggered when source warehouse changes
        $('#source_warehouse').change(function () {
            const warehouseId = $(this).val();
            if (!warehouseId) {
                $('.item-select').attr('disabled', true).html('<option value="">Select Sender first</option>');
                return;
            }

            $.get('ajax_get_warehouse_items.php', { warehouse_id: warehouseId }, function (data) {
                senderItems = data;
                const options = ['<option value="">Select Item Code</option>'];
                data.forEach(item => {
                    options.push(`<option value="${item.id}" 
                    data-name="${item.name}" 
                    data-code="${item.code}" 
                    data-category="${item.category_name || 'General'}" 
                    data-unit="${item.unit_name || 'pcs'}" 
                    data-cost="${item.avg_cost || '0.00'}" 
                    data-stock="${item.quantity_available}">[${item.code}] ${item.name}</option>`);
                });

                $('.item-row').each(function () {
                    const select = $(this).find('.item-select');
                    select.attr('disabled', false).html(options.join(''));
                });
            }, 'json');

            // Update bins
            $.get('ajax_get_bins.php', { warehouse_id: warehouseId }, function (data) {
                let html = '<option value="">Select Source Bin (Optional)</option>';
                data.forEach(b => html += `<option value="${b.bin_id}">${b.bin_location}</option>`);
                $('#source_bin_head').html(html);
            }, 'json');
        });

        $(document).on('change', '.item-select', function () {
            const selected = $(this).find('option:selected');
            const row = $(this).closest('.item-row');

            if (selected.val()) {
                row.find('.item-name-text').text(selected.data('name'));
                row.find('.item-category-text').text(selected.data('category'));
                row.find('.available-stock-text').text(selected.data('stock'));
                row.find('.item-unit-text').text(selected.data('unit'));
                row.find('.item-cost-text').text(selected.data('cost'));
                row.find('.cost-hidden-input').val(selected.data('cost'));
                row.find('.qty-input').attr('max', selected.data('stock')).val(1).trigger('input');

                // Availability Panel Update
                updateAvailabilityPanel(selected.val());
            }
        });

        function updateAvailabilityPanel(itemId) {
            $.get('ajax_get_item_availability.php', { item_id: itemId }, function (data) {
                let html = '<ul class="list-group list-group-flush bg-transparent">';
                data.forEach(loc => {
                    html += `<li class="list-group-item bg-transparent text-secondary border-secondary d-flex justify-content-between align-items-center">
                    <span>${loc.name}</span>
                    <span class="badge bg-dark border border-secondary text-info">${loc.qty}</span>
                </li>`;
                });
                html += '</ul>';
                $('#availabilityPanel').html(html);
            }, 'json');
        }

        $(document).on('input', '.qty-input', function () {
            const row = $(this).closest('.item-row');
            const qty = parseFloat($(this).val()) || 0;
            const cost = parseFloat(row.find('.cost-hidden-input').val()) || 0;
            const total = qty * cost;
            row.find('.item-total-text').text(total.toLocaleString(undefined, { minimumFractionDigits: 2 }));
            row.find('.line-total-hidden-input').val(total.toFixed(4));
            updateGrandTotal();
        });

        $('#addRow').click(function () {
            let firstRow = $('.item-row:first');
            let newRow = firstRow.clone();
            newRow.find('input').val('');
            newRow.find('span').text('-');
            newRow.find('.available-stock-text').text('0.00');
            newRow.find('.item-total-text').text('0.00');
            $('#itemsBody').append(newRow);
            $('.item-row').each((i, el) => $(el).find('td:first').text(i + 1));
        });

        $(document).on('click', '.remove-row', function () {
            if ($('.item-row').length > 1) {
                $(this).closest('.item-row').remove();
                $('.item-row').each((i, el) => $(el).find('td:first').text(i + 1));
                updateGrandTotal();
            }
        });
    });
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>