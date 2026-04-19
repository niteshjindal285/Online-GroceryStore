<?php
/**
 * Create Purchase Requisition
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$errors = [];
$items = db_fetch_all("
    SELECT i.id, i.code, i.name, i.cost_price, u.code as unit_code 
    FROM inventory_items i 
    LEFT JOIN units_of_measure u ON i.unit_of_measure_id = u.id 
    WHERE i.is_active = 1
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = post('csrf_token');
    if (!verify_csrf_token($csrf)) {
        $errors[] = "Invalid security token.";
    } else {
        $department = sanitize_input(post('department'));
        $required_date = post('required_date');
        $notes = sanitize_input(post('notes'));
        
        $line_items = post('items') ?: [];
        $line_qtys = post('quantities') ?: [];
        $line_prices = post('prices') ?: [];
        
        if (empty($required_date)) $errors['required_date'] = err_required();
        if (empty($department))    $errors['department']    = err_required();
        
        if (empty($line_items)) {
            $errors['line_items'] = err_required();
        }
        
        if (empty($errors)) {
            try {
                // Generate Req Number
                $req_num = 'REQ-' . date('Ym') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                db_begin_transaction();
                
                $total_estimated = 0;
                
                $req_id = db_insert("
                    INSERT INTO purchase_requisitions (requisition_number, request_date, required_date, department, status, notes, created_by, created_at)
                    VALUES (?, CURDATE(), ?, ?, 'submitted', ?, ?, NOW())
                ", [$req_num, $required_date, $department, $notes, $_SESSION['user_id']]);
                
                foreach ($line_items as $index => $item_id) {
                    $qty = floatval($line_qtys[$index] ?? 0);
                    $price = floatval($line_prices[$index] ?? 0);
                    
                    if ($item_id > 0 && $qty > 0) {
                        $line_total = $qty * $price;
                        $total_estimated += $line_total;
                        
                        db_query("
                            INSERT INTO purchase_requisition_lines (requisition_id, item_id, quantity, estimated_unit_price, estimated_line_total)
                            VALUES (?, ?, ?, ?, ?)
                        ", [$req_id, $item_id, $qty, $price, $line_total]);
                    }
                }
                
                db_query("UPDATE purchase_requisitions SET total_estimated_amount = ? WHERE id = ?", [$total_estimated, $req_id]);
                
                db_commit();
                
                set_flash('Requisition created and submitted successfully.', 'success');
                redirect("view_requisition.php?id=$req_id");
                
            } catch (Exception $e) {
                db_rollback();
                $errors[] = sanitize_db_error($e->getMessage());
            }
        }
    }
}

$page_title = 'New Purchase Requisition - MJR Group ERP';
include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="fas fa-plus-circle me-2"></i>New Requisition</h2>
            <p class="text-muted">Request goods or materials internally.</p>
        </div>
        <a href="requisitions.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if (!empty($errors) && !isset($errors['department']) && !isset($errors['required_date']) && !isset($errors['line_items'])): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape_html($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white pt-4 pb-0 border-0">
                <h5 class="text-primary">Request Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Requesting Department <span class="text-danger">*</span></label>
                        <input type="text" name="department" class="form-control <?= isset($errors['department']) ? 'is-invalid' : '' ?>" placeholder="e.g. Production Floor A" value="<?= escape_html(post('department')) ?>" required>
                        <?php if (isset($errors['department'])): ?>
                            <div class="invalid-feedback"><?= $errors['department'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Date Required By <span class="text-danger">*</span></label>
                        <input type="date" name="required_date" class="form-control <?= isset($errors['required_date']) ? 'is-invalid' : '' ?>" value="<?= post('required_date') ?: date('Y-m-d', strtotime('+7 days')) ?>" required>
                        <?php if (isset($errors['required_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['required_date'] ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justification / Notes</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Why are these items needed?"></textarea>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white pt-4 pb-0 border-0 d-flex justify-content-between">
                <h5 class="text-primary">Required Items</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-line-btn">
                    <i class="fas fa-plus"></i> Add Line
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="req-table">
                        <thead class="table-light">
                            <tr>
                                <th width="40%">Item</th>
                                <th width="15%">Est. Unit Price</th>
                                <th width="15%">Quantity</th>
                                <th width="20%">Est. Line Total</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamic Rows -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Estimated Value:</th>
                                <th>$<span id="grand-total">0.00</span></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php if (isset($errors['line_items'])): ?>
                        <div class="text-danger small mt-1">
                            <i class="fas fa-exclamation-circle me-1"></i> <?= $errors['line_items'] ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-paper-plane me-2"></i> Submit Requisition
            </button>
        </div>
    </form>
</div>

<!-- Item Options Template -->
<template id="item-options">
    <option value="">-- Choose Item --</option>
    <?php foreach ($items as $item): ?>
        <option value="<?= $item['id'] ?>" data-price="<?= $item['cost_price'] ?>">
            <?= escape_html($item['code'] . ' - ' . $item['name']) ?>
        </option>
    <?php endforeach; ?>
</template>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.querySelector('#req-table tbody');
    const grandTotalEl = document.getElementById('grand-total');
    const itemOptions = document.getElementById('item-options').innerHTML;

    function addLine() {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <select name="items[]" class="form-select item-select" required>
                    ${itemOptions}
                </select>
            </td>
            <td>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" name="prices[]" class="form-control item-price" value="0.00" required>
                </div>
            </td>
            <td>
                <input type="number" step="1" name="quantities[]" class="form-control item-qty" value="1" min="1" required>
            </td>
            <td>
                <input type="text" class="form-control line-total" value="$0.00" readonly disabled>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="fas fa-trash"></i></button>
            </td>
        `;
        tableBody.appendChild(tr);
        attachEvents(tr);
    }

    function attachEvents(row) {
        const sel = row.querySelector('.item-select');
        const price = row.querySelector('.item-price');
        const qty = row.querySelector('.item-qty');
        const rmv = row.querySelector('.remove-line');

        sel.addEventListener('change', () => {
            const opt = sel.options[sel.selectedIndex];
            if (opt && opt.dataset.price) {
                price.value = parseFloat(opt.dataset.price).toFixed(2);
            }
            calcTotals();
        });

        price.addEventListener('input', calcTotals);
        qty.addEventListener('input', calcTotals);

        rmv.addEventListener('click', () => {
            row.remove();
            calcTotals();
        });
    }

    function calcTotals() {
        let gt = 0;
        document.querySelectorAll('#req-table tbody tr').forEach(row => {
            const p = parseFloat(row.querySelector('.item-price').value) || 0;
            const q = parseFloat(row.querySelector('.item-qty').value) || 0;
            const t = p * q;
            row.querySelector('.line-total').value = '$' + t.toFixed(2);
            gt += t;
        });
        grandTotalEl.innerText = gt.toFixed(2);
    }

    document.getElementById('add-line-btn').addEventListener('click', addLine);
    addLine(); // Add first line automatically
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
