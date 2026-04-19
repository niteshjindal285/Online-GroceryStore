<?php
/**
 * Add / Edit Price Change Request
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$id = get_param('id');
$pc = null;
$items = [];
$warehouse_ids = [];

if ($id) {
    $pc = db_fetch("SELECT * FROM price_change_headers WHERE id = ?", [$id]);
    if ($pc) {
        $items = db_fetch_all("
            SELECT pi.*, i.code as item_code, i.name as item_name, c.name as category_name
            FROM price_change_items pi
            JOIN inventory_items i ON pi.item_id = i.id
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE pi.pc_header_id = ?
        ", [$id]);

        // Extract warehouse IDs from remarks if possible
        if (!empty($pc['remarks'])) {
            if (preg_match('/\[Warehouses: ([^\]]+)\]/', $pc['remarks'], $matches)) {
                $names = array_map('trim', explode(',', $matches[1]));
                foreach ($names as $name) {
                    $wh = db_fetch("SELECT id FROM locations WHERE name = ? AND type = 'warehouse' LIMIT 1", [$name]);
                    if ($wh) $warehouse_ids[] = (string)$wh['id'];
                }
            } else if (preg_match('/\[Warehouse: ([^\]]+)\]/', $pc['remarks'], $matches)) {
                $name = trim($matches[1]);
                $wh = db_fetch("SELECT id FROM locations WHERE name = ? AND type = 'warehouse' LIMIT 1", [$name]);
                if ($wh) $warehouse_ids[] = (string)$wh['id'];
            }
        }
    }
}

if (is_post()) {
    $action = post('action', 'save_draft');
    $pc_date = post('pc_date', date('Y-m-d'));
    $pc_num = post('pc_number') ?: 'PC-' . strtoupper(substr(uniqid(), -5));
    $company_id = (int)post('company_id');
    $price_category = post('price_category');
    $reason = post('reason');
    $effective_date = post('effective_date', date('Y-m-d'));
    $remarks = post('remarks');
    $warehouse_ids = array_filter(post('warehouse_ids', []), function($v) { return $v !== 'all'; });
    $bin_id = post('bin_id') ? (int) post('bin_id') : 0;
    $status = ($action === 'submit_approval') ? 'Pending Approval' : 'Draft';

    $item_ids = post('item_ids', []);
    $current_prices = post('current_prices', []);
    $new_prices = post('new_prices', []);
    $currencies = post('currencies', []);
    $item_remarks = post('item_remarks', []);

    $errors = [];
    if (empty($price_category)) $errors['price_category'] = err_required();
    if (empty($effective_date)) $errors['effective_date'] = err_required();
    if (empty($company_id))     $errors['company_id']     = err_required();
    if (empty($reason))         $errors['reason']         = err_required();

    if (empty($item_ids)) {
        $errors['items'] = err_required();
    } else {
        foreach ($new_prices as $idx => $price) {
            if ($price === '' || $price === null) {
                $errors['items'] = err_required();
                break;
            }
        }
    }

    if (!empty($errors)) {
        $error = err_required();
    } else {
        try {
            $scope_parts = [];
            if (!empty($warehouse_ids)) {
                $wh_names = [];
                foreach ($warehouse_ids as $wh_id) {
                    $wh = db_fetch("SELECT name FROM locations WHERE id = ? AND type = 'warehouse' LIMIT 1", [(int)$wh_id]);
                    if ($wh && !empty($wh['name'])) {
                        $wh_names[] = $wh['name'];
                    }
                }
                if (!empty($wh_names)) {
                    $scope_parts[] = 'Warehouses: ' . implode(', ', $wh_names);
                }
            }
            if (count($warehouse_ids) <= 1 && $bin_id > 0) {
                $bin = db_fetch("SELECT code FROM bins WHERE id = ? LIMIT 1", [$bin_id]);
                if ($bin && !empty($bin['code'])) {
                    $scope_parts[] = 'Bin: ' . $bin['code'];
                }
            }
            
            if (!empty($scope_parts)) {
                $remarks_base = preg_replace('/\s*\[(Warehouse|Warehouses|Bin):[^\]]*\]\s*$/', '', (string)$remarks);
                $remarks = trim((string)$remarks_base);
                $remarks = trim(($remarks !== '' ? ($remarks . "\n") : '') . '[' . implode(', ', $scope_parts) . ']');
            }

            db_begin_transaction();

            if ($id) {
                db_query("
                    UPDATE price_change_headers 
                    SET pc_date = ?, company_id = ?, price_category = ?, reason = ?, effective_date = ?, status = ?, remarks = ?
                    WHERE id = ?
                ", [$pc_date, $company_id, $price_category, $reason, $effective_date, $status, $remarks, $id]);
            } else {
                $id = db_insert("
                    INSERT INTO price_change_headers (pc_number, pc_date, company_id, price_category, reason, effective_date, created_by, status, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [$pc_num, $pc_date, $company_id, $price_category, $reason, $effective_date, current_user_id(), $status, $remarks]);
            }

            // Sync items
            db_query("DELETE FROM price_change_items WHERE pc_header_id = ?", [$id]);
            foreach ($item_ids as $idx => $item_id) {
                db_query("
                    INSERT INTO price_change_items (pc_header_id, item_id, current_price, new_price, currency, item_remarks)
                    VALUES (?, ?, ?, ?, ?, ?)
                ", [$id, $item_id, @$current_prices[$idx], @$new_prices[$idx], @$currencies[$idx], @$item_remarks[$idx]]);
            }

            // History
            db_query("
                INSERT INTO price_change_history (pc_header_id, status, notes, changed_by)
                VALUES (?, ?, ?, ?)
            ", [$id, $status, "Price change request " . strtolower($status), current_user_id()]);

            db_commit();
            set_flash("Price change " . strtolower($status) . " successfully!", "success");
            redirect("view_price_change.php?id=" . $id);
        } catch (Exception $e) {
            db_rollback();
            $error = sanitize_db_error($e->getMessage());
        }
    }
}

// Master Data
$companies = db_fetch_all("SELECT * FROM companies WHERE is_active = 1");
$categories = db_fetch_all("SELECT * FROM categories ORDER BY name ASC");
$selected_company_id = active_company_id();
if ($selected_company_id) {
    $warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]);
} else {
    $warehouses = db_fetch_all("SELECT id, name FROM locations WHERE type = 'warehouse' AND is_active = 1 ORDER BY name");
}
$recent_history = db_fetch_all("
    SELECT h.*, u.username as creator_name,
           (SELECT COUNT(*) FROM price_change_items WHERE pc_header_id = h.id) as item_count
    FROM price_change_headers h
    LEFT JOIN users u ON h.created_by = u.id
    ORDER BY h.created_at DESC LIMIT 5
");

$page_title = ($id ? "Edit" : "New") . " Price Change Request";
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-white fw-bold mb-0">
                <i class="fas <?= $id ? 'fa-edit' : 'fa-plus-circle' ?> me-2 text-info"></i>
                <?= $id ? 'Edit' : 'New' ?> Price Change
            </h2>
            <p class="text-secondary small mb-0">Update product prices across subsidiaries and categories.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="price_change_history.php" class="btn btn-outline-secondary">
                <i class="fas fa-history me-2"></i>History
            </a>
            <a href="index.php" class="btn btn-outline-info">
                <i class="fas fa-times me-2"></i>Cancel
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" id="priceChangeForm">
        <div class="row g-4">
            <!-- 1. Header Section -->
            <div class="col-lg-12">
                <div class="card premium-card">
                    <div class="card-header border-secondary border-opacity-25 bg-dark-light py-3">
                        <span class="section-badge me-2">01</span>
                        <h5 class="text-white d-inline">Price Change Header</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Ref Number</label>
                                <input type="text" name="pc_number" class="form-control bg-dark text-white border-secondary" 
                                       value="<?= escape_html($pc['pc_number'] ?? 'PC-' . strtoupper(substr(uniqid(), -5))) ?>" readonly>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Price Category</label>
                                <select name="price_category" class="form-select bg-dark text-white border-secondary <?= isset($errors['price_category']) ? 'is-invalid' : '' ?>" required>
                                    <option value="Selling Price" <?= ($pc['price_category'] ?? post('price_category')) === 'Selling Price' ? 'selected' : '' ?>>Selling Price</option>
                                    <option value="Purchase Price" <?= ($pc['price_category'] ?? post('price_category')) === 'Purchase Price' ? 'selected' : '' ?>>Purchase Price / Cost</option>
                                </select>
                                <?php if (isset($errors['price_category'])): ?>
                                    <div class="invalid-feedback"><?= $errors['price_category'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Effective Date</label>
                                <input type="date" name="effective_date" class="form-control bg-dark text-white border-secondary <?= isset($errors['effective_date']) ? 'is-invalid' : '' ?>" 
                                       value="<?= escape_html($pc['effective_date'] ?? post('effective_date', date('Y-m-d'))) ?>" required>
                                <?php if (isset($errors['effective_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['effective_date'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Company / Subsidiary</label>
                                <?php if (!empty($_SESSION['company_id'])): ?>
                                    <input type="text" class="form-control bg-dark text-white border-secondary" value="<?= escape_html($_SESSION['company_name'] ?? 'MJR Group') ?>" readonly>
                                    <input type="hidden" name="company_id" value="<?= escape_html($_SESSION['company_id']) ?>">
                                <?php else: ?>
                                    <select name="company_id" class="form-select bg-dark text-white border-secondary <?= isset($errors['company_id']) ? 'is-invalid' : '' ?>" required>
                                        <option value="">Select Subsidiary</option>
                                        <?php foreach ($companies as $c): ?>
                                            <option value="<?= $c['id'] ?>" <?= ($pc['company_id'] ?? post('company_id')) == $c['id'] ? 'selected' : '' ?>><?= $c['name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['company_id'])): ?>
                                        <div class="invalid-feedback d-block"><?= $errors['company_id'] ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Reason for Change</label>
                                <input type="text" name="reason" class="form-control bg-dark text-white border-secondary <?= isset($errors['reason']) ? 'is-invalid' : '' ?>" 
                                       placeholder="e.g. Supplier Cost Increase, Market Adjustment" value="<?= escape_html($pc['reason'] ?? post('reason')) ?>" required>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?= $errors['reason'] ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">General Remarks</label>
                                <input type="text" name="remarks" class="form-control bg-dark text-white border-secondary" 
                                       placeholder="Optional notes..." value="<?= escape_html($pc['remarks'] ?? '') ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Warehouses (Optional)</label>
                                <select name="warehouse_ids[]" id="pc_warehouse_id" class="form-select select2 bg-dark text-white border-secondary" multiple data-placeholder="Select Warehouses">
                                    <option value="all">All Warehouses</option>
                                    <?php foreach ($warehouses as $w): ?>
                                        <option value="<?= $w['id'] ?>" <?= in_array((string)$w['id'], array_map('strval', $warehouse_ids)) ? 'selected' : '' ?>><?= escape_html($w['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label text-secondary small text-uppercase fw-bold">Bin (Optional)</label>
                                <select name="bin_id" id="pc_bin_id" class="form-select bg-dark text-white border-secondary">
                                    <option value="">Select Bin</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Search & Add Panel -->
            <div class="col-lg-4">
                <div class="card premium-card h-100">
                    <div class="card-header border-secondary border-opacity-25 bg-dark-light py-3">
                        <span class="section-badge me-2">02</span>
                        <h5 class="text-white d-inline">Product Search</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label text-secondary small">Filter Category</label>
                            <select id="searchCategory" class="form-select bg-dark text-white border-secondary">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-secondary small">Search Item</label>
                            <div class="input-group">
                                <input type="text" id="searchInput" class="form-control bg-dark text-white border-secondary" placeholder="Code or Name...">
                                <button type="button" id="searchBtn" class="btn btn-outline-info">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <div id="searchResults" class="list-group list-group-flush border-top border-secondary overflow-auto" style="max-height: 400px;">
                            <div class="text-center py-4 opacity-50">
                                <i class="fas fa-boxes fa-2x mb-2"></i>
                                <p class="small">Search result will appear here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Price Change Table -->
            <div class="col-lg-8">
                <div class="card premium-card">
                    <div class="card-header border-secondary border-opacity-25 bg-dark-light py-3 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <span class="section-badge me-2">03</span>
                            <h5 class="text-white d-inline">Product Price Change Table</h5>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (isset($errors['items'])): ?>
                            <div class="alert alert-danger mx-3 mt-3"><?= $errors['items'] ?></div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-dark mb-0">
                                <thead class="bg-dark-light">
                                    <tr>
                                        <th class="ps-3" style="width: 250px;">Product</th>
                                        <th>Current</th>
                                        <th style="width: 120px;">New Price</th>
                                        <th>Diff</th>
                                        <th>%</th>
                                        <th>Note</th>
                                        <th class="pe-3"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsBody">
                                    <?php if (empty($items)): ?>
                                        <tr id="emptyRow">
                                            <td colspan="7" class="text-center py-5 opacity-25">
                                                <i class="fas fa-shopping-cart fa-3x mb-2"></i>
                                                <p>No products added yet.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($items as $item): ?>
                                            <tr class="product-row" data-id="<?= $item['item_id'] ?>">
                                                <td class="ps-3">
                                                    <input type="hidden" name="item_ids[]" value="<?= $item['item_id'] ?>">
                                                    <input type="hidden" name="currencies[]" value="<?= $item['currency'] ?>">
                                                    <div class="fw-bold text-info"><?= escape_html($item['item_code']) ?></div>
                                                    <div class="small text-secondary"><?= escape_html($item['item_name']) ?></div>
                                                </td>
                                                <td class="align-middle">
                                                    <input type="hidden" name="current_prices[]" value="<?= $item['current_price'] ?>" class="current-price">
                                                    <span class="text-secondary"><?= number_format($item['current_price'], 2) ?></span>
                                                </td>
                                                <td class="align-middle">
                                                    <input type="number" step="any" name="new_prices[]" value="<?= $item['new_price'] ?>" class="form-control form-control-sm bg-dark text-white border-secondary new-price-input" required>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="diff-value fw-bold text-success">0.00</span>
                                                </td>
                                                <td class="align-middle">
                                                    <span class="diff-percent small text-secondary">0%</span>
                                                </td>
                                                <td class="align-middle">
                                                    <input type="text" name="item_remarks[]" value="<?= escape_html($item['item_remarks']) ?>" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="...">
                                                </td>
                                                <td class="align-middle pe-3 text-end">
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-dark-light py-3 border-secondary border-opacity-25 text-end">
                        <button type="submit" name="action" value="save_draft" class="btn btn-outline-secondary btn-lg px-4 me-2">
                            <i class="fas fa-save me-2"></i>Save as Draft
                        </button>
                        <button type="submit" name="action" value="submit_approval" class="btn btn-info btn-lg px-5 text-dark fw-bold">
                            <i class="fas fa-paper-plane me-2"></i>Submit for Approval
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- 4. Recent History Panel (Added as per Wireframe Section 10) -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card premium-card border-info border-opacity-10">
                <div class="card-header bg-dark-light py-3 border-secondary border-opacity-25">
                    <div class="d-flex align-items-center">
                        <span class="section-badge me-2" style="background:#6f42c1; color:#fff;">04</span>
                        <h5 class="text-white d-inline">Price Change History</h5>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr class="text-secondary small text-uppercase">
                                    <th class="ps-4">Ref No</th>
                                    <th>Date</th>
                                    <th>Products</th>
                                    <th>Effective Date</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_history as $rh): ?>
                                    <tr>
                                        <td class="ps-4 text-info fw-bold"><?= escape_html($rh['pc_number']) ?></td>
                                        <td><?= format_date($rh['pc_date']) ?></td>
                                        <td><span class="badge bg-dark border border-secondary"><?= $rh['item_count'] ?> items</span></td>
                                        <td><?= format_date($rh['effective_date']) ?></td>
                                        <td>
                                            <?php
                                            $st = $rh['status'];
                                            $cls = 'bg-secondary';
                                            if ($st === 'Approved') $cls = 'bg-success';
                                            if ($st === 'Pending Approval') $cls = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?= $cls ?>"><?= escape_html($st) ?></span>
                                        </td>
                                        <td class="pe-4 text-end">
                                            <a href="view_price_change.php?id=<?= $rh['id'] ?>" class="btn btn-outline-info btn-sm">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="rowTemplate">
    <tr class="product-row" data-id="{ID}">
        <td class="ps-3">
            <input type="hidden" name="item_ids[]" value="{ID}">
            <input type="hidden" name="currencies[]" value="INR">
            <div class="fw-bold text-info">{CODE}</div>
            <div class="small text-secondary text-truncate" style="max-width: 200px;">{NAME}</div>
        </td>
        <td class="align-middle">
            <input type="hidden" name="current_prices[]" value="{PRICE}" class="current-price">
            <span class="text-secondary">{PRICE_F}</span>
        </td>
        <td class="align-middle">
            <input type="number" step="any" name="new_prices[]" class="form-control form-control-sm bg-dark text-white border-secondary new-price-input" required>
        </td>
        <td class="align-middle">
            <span class="diff-value fw-bold text-secondary">--</span>
        </td>
        <td class="align-middle">
            <span class="diff-percent small text-secondary">--</span>
        </td>
        <td class="align-middle">
            <input type="text" name="item_remarks[]" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="...">
        </td>
        <td class="align-middle pe-3 text-end">
            <button type="button" class="btn btn-outline-danger btn-sm remove-item">
                <i class="fas fa-times"></i>
            </button>
        </td>
    </tr>
</template>

<style>
    .premium-card {
        background: #1a1a27;
        border: 1px solid #323248;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        overflow: hidden;
    }
    .bg-dark-light { background: rgba(255, 255, 255, 0.03); }
    .section-badge {
        width: 28px; height: 28px;
        background: #0dcaf0; color: #000;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; font-weight: 800; font-size: 0.8rem; vertical-align: middle;
    }
    .table-dark { background: transparent !important; }
    .table-dark thead th {
        background: #212133 !important;
        color: #a2a3b7 !important;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 1px solid #323248 !important;
        padding: 12px 15px;
    }
    .product-row td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
        padding: 12px 15px;
    }
    .select2-container--bootstrap-5 .select2-selection {
        background-color: #1a1a27 !important;
        border: 1px solid #323248 !important;
        color: #fff !important;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #0dcaf0 !important;
        color: #000 !important;
        border: none !important;
        font-weight: 600 !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown {
        background-color: #1a1a27 !important;
        border: 1px solid #323248 !important;
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted {
        background-color: #0dcaf0 !important;
        color: #000 !important;
    }
    .select2-search__field {
        color: #fff !important;
    }
</style>

<!-- Select2 Assets -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    
    const priceCategorySelect = $('select[name="price_category"]');
    const warehouseSelect = $('#pc_warehouse_id');
    const binSelect = document.getElementById('pc_bin_id');
    const selectedBinId = '<?= escape_html((string)post('bin_id')) ?>';

    // Initialize Select2
    if ($.fn.select2) {
        warehouseSelect.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select Warehouses'
        }).on('select2:select', function (e) {
            if (e.params.data.id === 'all') {
                // Select all other options
                const allIds = warehouseSelect.find('option').map(function() {
                    return this.value !== 'all' ? this.value : null;
                }).get();
                warehouseSelect.val(allIds).trigger('change');
            }
        });
    }

    function loadPriceChangeBins() {
        if (!warehouseSelect.length || !binSelect) return;
        
        const selectedValues = warehouseSelect.val() || [];
        
        // Disable bins if multiple warehouses are selected
        if (selectedValues.length > 1) {
            binSelect.innerHTML = '<option value="">(Bins disabled for multi-warehouse)</option>';
            binSelect.disabled = true;
            return;
        }
        
        binSelect.disabled = false;
        const locId = selectedValues[0] || '';
        binSelect.innerHTML = '<option value="">Select Bin</option>';
        if (!locId) return;

        fetch('ajax_get_bins.php?location_id=' + encodeURIComponent(locId))
            .then(r => r.json())
            .then(data => {
                (Array.isArray(data) ? data : []).forEach(b => {
                    const option = document.createElement('option');
                    option.value = b.bin_id;
                    option.textContent = b.bin_location;
                    if (selectedBinId && String(selectedBinId) === String(b.bin_id)) {
                        option.selected = true;
                    }
                    binSelect.appendChild(option);
                });
            })
            .catch(() => {
                binSelect.innerHTML = '<option value="">Select Bin</option>';
            });
    }

    if (warehouseSelect.length) {
        warehouseSelect.on('change', loadPriceChangeBins);
        loadPriceChangeBins();
    }
    
    function calculateImpact(row) {
        const current = parseFloat(row.find('.current-price').val()) || 0;
        const requested = parseFloat(row.find('.new-price-input').val()) || 0;
        const diff = requested - current;
        const diffPercent = current > 0 ? (diff / current * 100) : 0;
        
        const diffSpan = row.find('.diff-value');
        const pctSpan = row.find('.diff-percent');
        
        diffSpan.text((diff >= 0 ? '+' : '') + diff.toLocaleString(undefined, {minimumFractionDigits: 2}));
        diffSpan.removeClass('text-success text-danger text-secondary');
        
        if (diff > 0) diffSpan.addClass('text-success');
        else if (diff < 0) diffSpan.addClass('text-danger');
        else diffSpan.addClass('text-secondary');
        
        pctSpan.text((diff >= 0 ? '+' : '') + diffPercent.toFixed(1) + '%');
    }

    $(document).on('input', '.new-price-input', function() {
        calculateImpact($(this).closest('tr'));
    });

    // Run calculation for existing items
    $('.product-row').each(function() {
        calculateImpact($(this));
    });

    // Search logic
    function performSearch() {
        const search = $('#searchInput').val();
        const category = $('#searchCategory').val();
        
        $('#searchResults').html('<div class="text-center py-4"><div class="spinner-border text-info spinner-border-sm"></div></div>');
        
        $.get('ajax_search_products.php', { search: search, category_id: category }, function(data) {
            let html = '';
            const categoryType = priceCategorySelect.val(); // "Selling Price" or "Purchase Price"
            
            if (data.length === 0) {
                html = '<div class="text-center py-4 text-secondary opacity-50">No items found</div>';
            } else {
                data.forEach(item => {
                    const price = (categoryType === 'Selling Price') ? item.selling_price : item.cost_price;
                    html += `
                        <button type="button" class="list-group-item list-group-item-action bg-transparent border-secondary border-opacity-25 py-3 add-found-item" 
                                data-id="${item.id}" data-code="${item.code}" data-name="${item.name}" data-price="${price}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-info fw-bold">${item.code}</div>
                                    <div class="text-white small">${item.name}</div>
                                    <div class="text-secondary x-small">${item.category_name}</div>
                                </div>
                                <div class="text-end">
                                    <div class="text-secondary small">Current</div>
                                    <div class="text-white fw-bold">${parseFloat(price).toLocaleString(undefined, {minimumFractionDigits: 2})}</div>
                                    <i class="fas fa-plus-circle text-info mt-1"></i>
                                </div>
                            </div>
                        </button>`;
                });
            }
            $('#searchResults').html(html);
        });
    }

    $('#searchBtn').click(performSearch);
    $('#searchInput').keypress(function(e) { if(e.which == 13) { e.preventDefault(); performSearch(); } });

    // Handle adding items to table
    $(document).on('click', '.add-found-item', function() {
        const item = $(this).data();
        
        if ($(`.product-row[data-id="${item.id}"]`).length > 0) {
            alert('This item is already added.');
            return;
        }

        $('#emptyRow').remove();
        
        let template = $('#rowTemplate').html();
        template = template.replace(/{ID}/g, item.id)
                          .replace(/{CODE}/g, item.code)
                          .replace(/{NAME}/g, item.name)
                          .replace(/{PRICE}/g, item.price)
                          .replace(/{PRICE_F}/g, parseFloat(item.price).toLocaleString(undefined, {minimumFractionDigits: 2}));
        
        $('#itemsBody').append(template);
        
        // Focus the new price input
        $(`.product-row[data-id="${item.id}"] .new-price-input`).focus();
    });

    $(document).on('click', '.remove-item', function() {
        $(this).closest('tr').remove();
        if ($('.product-row').length === 0) {
            $('#itemsBody').html('<tr id="emptyRow"><td colspan="7" class="text-center py-5 opacity-25"><i class="fas fa-shopping-cart fa-3x mb-2"></i><p>No products added yet.</p></td></tr>');
        }
    });

    // If price category changes, alert user or clear table?
    priceCategorySelect.change(function() {
        if ($('.product-row').length > 0) {
            if (confirm('Changing price category will not update current prices for already added rows. Do you want to clear the table?')) {
                $('#itemsBody').html('<tr id="emptyRow"><td colspan="7" class="text-center py-5 opacity-25"><i class="fas fa-shopping-cart fa-3x mb-2"></i><p>No products added yet.</p></td></tr>');
            }
        }
        performSearch(); // Refresh search list with new prices
    });
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
