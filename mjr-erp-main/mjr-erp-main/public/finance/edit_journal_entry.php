<?php
/**
 * Edit Journal Entry Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Edit Journal Entry - MJR Group ERP';

// Get entry ID from URL
$entry_id = get('id');
if (!$entry_id) {
    set_flash('Journal entry ID not provided.', 'error');
    redirect('journal_entries.php');
}

// Get entry data
$entry = db_fetch("SELECT * FROM journal_entries WHERE id = ?", [$entry_id]);
if (!$entry) {
    set_flash('Journal entry not found.', 'error');
    redirect('journal_entries.php');
}

// Check if entry is posted (can't edit posted entries)
if ($entry['status'] === 'posted') {
    set_flash('Cannot edit posted journal entries.', 'error');
    redirect('journal_entries.php');
}

// Get entry lines
$lines = db_fetch_all("
    SELECT jel.*, a.code as account_code, a.name as account_name
    FROM journal_entry_lines jel
    JOIN accounts a ON jel.account_id = a.id
    WHERE jel.journal_entry_id = ?
    ORDER BY jel.id
", [$entry_id]);

// Get accounts for dropdown
$accounts = db_fetch_all("SELECT id, code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code");
$cost_centers = db_fetch_all("SELECT id, code, name FROM cost_centers WHERE is_active = 1 ORDER BY name");
$projects = db_fetch_all("SELECT id, code, name FROM projects WHERE is_active = 1 ORDER BY name");
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $entry_number = trim(post('entry_number', ''));
        $entry_date = post('entry_date', '');
        $description = trim(post('description', ''));

        $errors = [];
        if (empty($entry_number)) $errors['entry_number'] = err_required();
        if (empty($entry_date))   $errors['entry_date']   = err_required();

        // Get line items
        $account_ids = post('account_id', []);
        $company_ids = post('company_id', []);
        $cost_center_ids = post('cost_center_id', []);
        $project_ids = post('project_id', []);
        $descriptions = post('line_description', []);
        $debits = post('debit', []);
        $credits = post('credit', []);

        if (empty($errors)) {
            // Check if entry number already exists for other entries
            $exists = db_fetch("SELECT id FROM journal_entries WHERE entry_number = ? AND id != ?", [$entry_number, $entry_id]);
            if ($exists) {
                $errors['entry_number'] = 'Journal entry number already exists!';
            }
        }

        if (empty($errors)) {
            // Validate balancing
            $total_debit = array_sum($debits);
            $total_credit = array_sum($credits);
            
            if (abs($total_debit - $total_credit) > 0.01) {
                $errors['balancing'] = 'Journal entry is not balanced! Debit total must equal credit total.';
            }
            
            if (count($account_ids) < 2) {
                $errors['lines'] = 'Journal entry must have at least 2 lines.';
            }
        }

        if (empty($errors)) {
            db_begin_transaction();
            try {
                $new_status = 'draft';
                // Update journal entry header
                $sql = "UPDATE journal_entries SET 
                        entry_number = ?, entry_date = ?, description = ?, status = ?, updated_at = NOW() 
                        WHERE id = ?";
                db_query($sql, [$entry_number, $entry_date, $description, $new_status, $entry_id]);
                
                // Delete old lines
                db_query("DELETE FROM journal_entry_lines WHERE journal_entry_id = ?", [$entry_id]);
                
                // Insert new journal entry lines
                foreach ($account_ids as $index => $account_id) {
                    if (empty($account_id)) continue;
                    
                    $debit = $debits[$index] ?? 0;
                    $credit = $credits[$index] ?? 0;
                    $line_desc = $descriptions[$index] ?? '';
                    $cc_id = !empty($cost_center_ids[$index]) ? $cost_center_ids[$index] : null;
                    $proj_id = !empty($project_ids[$index]) ? $project_ids[$index] : null;
                    $comp_id = !empty($company_ids[$index]) ? $company_ids[$index] : 1;
                    $line_number = $index + 1;
                    
                    if ($debit > 0 || $credit > 0) {
                        $sql = "INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit, line_number, cost_center_id, project_id, company_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        db_insert($sql, [$entry_id, $account_id, $line_desc, $debit, $credit, $line_number, $cc_id, $proj_id, $comp_id]);
                    }
                }
                
                db_commit();
                set_flash('Journal entry updated successfully! Use View to complete approval/posting.', 'success');
                redirect('journal_entries.php');
                
            } catch (Exception $e) {
                db_rollback();
                log_error("Error updating journal entry: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        } else {
            $error = err_required();
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 10px; }
    .form-control, .form-select { background-color: #1a1a24!important; border-color: rgba(255,255,255,0.1)!important; color: #fff!important; padding: 10px 15px; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    .form-label { color: #8e8e9e; font-size: 0.85rem; }
    .table-dark { --bs-table-bg: #222230; --bs-table-striped-bg: #262635; --bs-table-border-color: rgba(255,255,255,0.05); }
    .table-dark th { color: #8e8e9e; font-weight: 600; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 1px solid #333344; padding: 1rem; }
    .table-dark td { padding: 0.75rem 1rem; vertical-align: middle; border-bottom: 1px solid #333344; }
    .table-dark input, .table-dark select { background-color: #1a1a24!important; border: 1px solid rgba(255,255,255,0.05)!important; color: #fff!important; }
    
    .section-badge { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; letter-spacing: 0.5px; }
    .auto-gen-box { background-color: rgba(13, 202, 240, 0.05); border: 1px dashed rgba(13, 202, 240, 0.3); color: #0dcaf0; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; text-align: right; }
</style>

<div class="container-fluid px-4 py-3">
    
    <!-- Screen Breadcrumb Box -->
    <div class="rounded p-2 mb-4 d-flex align-items-center" style="background-color: #0c2b33; border: 1px solid #134e5e;">
        <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #ff922b; margin-left:10px; margin-right: 15px;"></div>
        <span style="color: #ff922b; font-weight: 600; font-size: 0.85rem;">SCREEN: Edit Journal Entry</span>
    </div>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="journal_entries.php" class="text-decoration-none d-flex align-items-center mb-2" style="color: #8e8e9e; font-size: 0.9rem;">
                <i class="fas fa-arrow-left me-2"></i> Back to Journal Entries
            </a>
            <div class="d-flex align-items-center">
                <i class="fas fa-edit text-white fs-2 me-3" style="opacity: 0.9;"></i>
                <h2 class="mb-0 text-white fw-bold">Edit Journal Entry</h2>
            </div>
        </div>
    </div>

    <form method="POST" action="" id="journalForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <?php if (isset($errors['balancing'])): ?>
            <div class="alert alert-danger" style="background-color: rgba(255, 82, 82, 0.1); border-color: rgba(255, 82, 82, 0.2); color: #ff5252;"><?= $errors['balancing'] ?></div>
        <?php endif; ?>
        <?php if (isset($errors['lines'])): ?>
            <div class="alert alert-danger" style="background-color: rgba(255, 82, 82, 0.1); border-color: rgba(255, 82, 82, 0.2); color: #ff5252;"><?= $errors['lines'] ?></div>
        <?php endif; ?>

        <!-- Section 1: Header -->
        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-4">
                    <div class="section-badge">
                        <span class="me-2" style="opacity: 0.7;">01</span> Journal Entry Header
                    </div>
                    <div class="auto-gen-box">
                        <span style="opacity: 0.7;" class="me-2">JE Number</span>
                        <strong class="fs-6"><?= escape_html($entry['entry_number']) ?></strong>
                        <input type="hidden" name="entry_number" value="<?= escape_html(post('entry_number', $entry['entry_number'])) ?>">
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Journal Type <span class="text-danger">*</span></label>
                        <?php $typeLabel = str_contains(strtolower($entry['description']), 'reversal') ? 'reversal' : 'general'; ?>
                        <select class="form-select" name="journal_type">
                            <option value="general" <?= $typeLabel == 'general' ? 'selected' : '' ?>>General Journal</option>
                            <option value="reversal" <?= $typeLabel == 'reversal' ? 'selected' : '' ?>>Reversal Journal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?= isset($errors['entry_date']) ? 'is-invalid' : '' ?>" name="entry_date" required value="<?= post('entry_date', $entry['entry_date']) ?>">
                        <?php if (isset($errors['entry_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['entry_date'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Post Period (Auto)</label>
                        <input type="text" class="form-control bg-dark text-muted" value="<?= date('M Y', strtotime($entry['entry_date'])) ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Company / Subsidiary <span class="text-danger">*</span></label>
                        <select class="form-select" name="main_company_id">
                            <?php foreach ($companies as $comp): ?>
                            <option value="<?= $comp['id'] ?>" <?= $entry['company_id'] == $comp['id'] ? 'selected' : '' ?>><?= escape_html($comp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Reason / Narration</label>
                        <textarea class="form-control" name="description" rows="2"><?= escape_html(post('description', $entry['description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Lines -->
        <div class="card mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="section-badge">
                        <span class="me-2" style="opacity: 0.7;">02</span> Account Lines
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark mb-0" id="lineItemsTable" style="background-color: transparent;">
                        <thead>
                            <tr>
                                <th style="width: 25%">Account Code / Name</th>
                                <th style="width: 20%">Company / Cost Center</th>
                                <th style="width: 20%">Line Narration</th>
                                <th style="width: 15%">Debit Amount</th>
                                <th style="width: 15%">Credit Amount</th>
                                <th style="width: 5%" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="lineItems">
                            <?php foreach ($lines as $line): ?>
                            <tr>
                                <td>
                                    <select class="form-select border-0 shadow-none" name="account_id[]" required style="border-radius:0;">
                                        <option value="">Select Account...</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>" <?= $line['account_id'] == $account['id'] ? 'selected' : '' ?>>
                                            <?= escape_html($account['code']) ?> - <?= escape_html($account['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1">
                                        <select class="form-select form-select-sm border-0 shadow-none" name="company_id[]" style="border-radius:0; padding:4px 8px; font-size:12px;">
                                            <?php foreach ($companies as $comp): ?>
                                            <option value="<?= $comp['id'] ?>" <?= ($line['company_id'] ?? 1) == $comp['id'] ? 'selected' : '' ?>><?= escape_html($comp['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="form-select form-select-sm border-0 shadow-none" name="cost_center_id[]" style="border-radius:0; padding:4px 8px; font-size:12px;">
                                            <option value="">No Cost Center</option>
                                            <?php foreach ($cost_centers as $cc): ?>
                                            <option value="<?= $cc['id'] ?>" <?= ($line['cost_center_id'] ?? '') == $cc['id'] ? 'selected' : '' ?>><?= escape_html($cc['code']) ?> - <?= escape_html($cc['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="project_id[]" value="<?= escape_html($line['project_id'] ?? '') ?>">
                                    </div>
                                </td>
                                <td><input type="text" class="form-control border-0 shadow-none" name="line_description[]" value="<?= escape_html($line['description'] ?? '') ?>" style="border-radius:0;"></td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="debit[]" step="0.01" min="0" value="<?= $line['debit'] ?>" style="border-radius:0;"></td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="credit[]" step="0.01" min="0" value="<?= $line['credit'] ?>" style="border-radius:0;"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm remove-line" style="color: #ff5252; background: rgba(255,82,82,0.1);"><i class="fas fa-times"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end pe-4" style="color: #8e8e9e;"><strong>Totals:</strong></td>
                                <td class="text-end font-monospace fs-5 text-white" style="background: rgba(13,202,240,0.05); border-left: 1px solid rgba(255,255,255,0.05);"><strong id="totalDebit">0.00</strong></td>
                                <td class="text-end font-monospace fs-5 text-white" style="background: rgba(13,202,240,0.05); border-right: 1px solid rgba(255,255,255,0.05);"><strong id="totalCredit">0.00</strong></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-end pe-4 border-0" style="color: #8e8e9e;"><strong>Difference:</strong></td>
                                <td colspan="2" class="text-end font-monospace fs-6 pb-4 pt-3 border-0">
                                    <strong id="difference" style="color: #3cc553;">0.00</strong>
                                </td>
                                <td class="border-0"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <button type="button" class="btn mt-3 px-4 py-2 fw-bold" id="addLine" style="background: rgba(13, 202, 240, 0.1); color: #0dcaf0; border: 1px dashed #0dcaf0;">
                    <i class="fas fa-plus me-2"></i>Add Account Line
                </button>
            </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="position-sticky bottom-0 p-3 mb-4 d-flex justify-content-end gap-3 rounded" style="background-color: rgba(34, 34, 48, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 -10px 30px rgba(0,0,0,0.5); z-index: 100;">
            <a href="journal_entries.php" class="btn px-4 bg-transparent" style="color: #8e8e9e; border: 1px solid rgba(255,255,255,0.2);">Cancel</a>
            <button type="submit" name="action_save" value="draft" class="btn px-4" style="background-color: #333344; color: #fff; border: 1px solid rgba(255,255,255,0.1);">Update</button>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    const accountOptions = <?= json_encode(array_map(function($a) { 
        return ['id' => $a['id'], 'text' => $a['code'] . ' - ' . $a['name']]; 
    }, $accounts)) ?>;
    
    function updateTotals() {
        let totalDebit = 0;
        let totalCredit = 0;
        
        $('input[name="debit[]"]').each(function() {
            totalDebit += parseFloat($(this).val()) || 0;
        });
        
        $('input[name="credit[]"]').each(function() {
            totalCredit += parseFloat($(this).val()) || 0;
        });
        
        $('#totalDebit').text(totalDebit.toFixed(2));
        $('#totalCredit').text(totalCredit.toFixed(2));
        
        const diff = totalDebit - totalCredit;
        $('#difference').text(Math.abs(diff).toFixed(2));
        
        if (Math.abs(diff) < 0.01) {
            $('#difference').removeClass('text-warning text-danger').addClass('text-success');
        } else {
            $('#difference').removeClass('text-success').addClass('text-danger');
        }
    }
    
    const companyOptions = <?= json_encode(array_map(function($c) { 
        return ['id' => $c['id'], 'text' => $c['name']]; 
    }, $companies)) ?>;

    const ccOptions = <?= json_encode(array_map(function($c) { 
        return ['id' => $c['id'], 'text' => $c['code'] . ' - ' . $c['name']]; 
    }, $cost_centers)) ?>;

    $('#addLine').click(function() {
        const newRow = `
            <tr>
                <td>
                    <select class="form-select border-0 shadow-none" name="account_id[]" required style="border-radius:0;">
                        <option value="">Select Account...</option>
                        ${accountOptions.map(opt => `<option value="${opt.id}">${opt.text}</option>`).join('')}
                    </select>
                </td>
                <td>
                    <div class="d-flex flex-column gap-1">
                        <select class="form-select form-select-sm border-0 shadow-none" name="company_id[]" style="border-radius:0; padding:4px 8px; font-size:12px;">
                            ${companyOptions.map(opt => `<option value="${opt.id}">${opt.text}</option>`).join('')}
                        </select>
                        <select class="form-select form-select-sm border-0 shadow-none" name="cost_center_id[]" style="border-radius:0; padding:4px 8px; font-size:12px;">
                            <option value="">No Cost Center</option>
                            ${ccOptions.map(opt => `<option value="${opt.id}">${opt.text}</option>`).join('')}
                        </select>
                        <input type="hidden" name="project_id[]" value="">
                    </div>
                </td>
                <td><input type="text" class="form-control border-0 shadow-none" name="line_description[]" placeholder="Optional..." style="border-radius:0;"></td>
                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="debit[]" step="0.01" min="0" value="0.00" style="border-radius:0;"></td>
                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="credit[]" step="0.01" min="0" value="0.00" style="border-radius:0;"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm remove-line" style="color: #ff5252; background: rgba(255,82,82,0.1);"><i class="fas fa-times"></i></button>
                </td>
            </tr>
        `;
        $('#lineItems').append(newRow);
    });
    
    $(document).on('click', '.remove-line', function() {
        if ($('#lineItems tr').length > 2) {
            $(this).closest('tr').remove();
            updateTotals();
        } else {
            alert('Journal entry must have at least 2 lines.');
        }
    });
    
    $(document).on('input', 'input[name="debit[]"], input[name="credit[]"]', function() {
        updateTotals();
    });
    
    updateTotals();
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
