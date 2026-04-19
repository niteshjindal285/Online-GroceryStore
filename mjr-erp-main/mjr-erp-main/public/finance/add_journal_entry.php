<?php
/**
 * Add Journal Entry Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Journal Entry - MJR Group ERP';
ensure_finance_approval_columns('journal_entries');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];

// Get accounts for dropdown
$accounts = db_fetch_all("SELECT id, code, name, account_type FROM accounts WHERE is_active = 1 ORDER BY code");
$cost_centers = db_fetch_all("SELECT id, code, name FROM cost_centers WHERE is_active = 1 ORDER BY name");
$projects = db_fetch_all("SELECT id, code, name FROM projects WHERE is_active = 1 ORDER BY name");
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name");

// Handle form submission
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $form_action = strtolower(trim((string)post('form_action', 'save')));
        $entry_number = trim(post('entry_number', ''));
        $entry_date = post('entry_date', '');
        $description = trim(post('description', ''));
        $journal_type = strtolower(trim((string)post('journal_type', 'general')));
        $status = 'draft';
        $approval_type = post('approval_type', 'manager');
        $manager_id = post('manager_id') ?: null;
        $admin_id = post('admin_id') ?: null;
        $created_by = current_user_id();
        $main_company_id = (int)post('main_company_id', 1);

        if ($journal_type === 'reversal' && stripos($description, 'reversal') === false) {
            $description = 'Reversal - ' . $description;
        }
        if ($form_action === 'clone') {
            $description = 'CLONE - ' . $description;
        } elseif ($form_action === 'reverse' && stripos($description, 'reversal') === false) {
            $description = 'REVERSAL - ' . $description;
        }

        $errors = [];
        if (empty($entry_number)) $errors['entry_number'] = err_required();
        if (empty($entry_date))   $errors['entry_date']   = err_required();
        if ($main_company_id <= 0) $errors['main_company_id'] = 'Company is required.';
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        // Get line items
        $account_ids = post('account_id', []);
        $company_ids = post('company_id', []);
        $cost_center_ids = post('cost_center_id', []);
        $project_ids = post('project_id', []);
        $descriptions = post('line_description', []);
        $debits = post('debit', []);
        $credits = post('credit', []);

        if (empty($errors)) {
            // Check if entry number already exists
            $exists = db_fetch("SELECT id FROM journal_entries WHERE entry_number = ?", [$entry_number]);
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
                // Insert journal entry header
                $sql = "INSERT INTO journal_entries (entry_number, entry_date, description, status, approval_type, manager_id, admin_id, company_id, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $entry_id = db_insert($sql, [$entry_number, $entry_date, $description, $status, $approval_type, $manager_id, $admin_id, $main_company_id, $created_by]);
                
                // Insert journal entry lines
                foreach ($account_ids as $index => $account_id) {
                    if (empty($account_id)) continue;
                    
                    $debit = $debits[$index] ?? 0;
                    $credit = $credits[$index] ?? 0;
                    $line_desc = $descriptions[$index] ?? '';
                    $cc_id = !empty($cost_center_ids[$index]) ? $cost_center_ids[$index] : null;
                    $proj_id = !empty($project_ids[$index]) ? $project_ids[$index] : null;
                    
                    if ($debit > 0 || $credit > 0) {
                        $sql = "INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit, line_number, cost_center_id, project_id, company_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $line_number = $index + 1;
                        $comp_id = !empty($company_ids[$index]) ? (int)$company_ids[$index] : $main_company_id;
                        db_insert($sql, [$entry_id, $account_id, $line_desc, $debit, $credit, $line_number, $cc_id, $proj_id, $comp_id]);
                    }
                }

                if ($form_action === 'approve') {
                    $entry_record = db_fetch("SELECT * FROM journal_entries WHERE id = ?", [$entry_id]);
                    $approval = finance_process_approval_action($entry_record ?: [], current_user_id());
                    if ($approval['ok']) {
                        $set_parts = [];
                        $set_params = [];
                        foreach ($approval['fields'] as $field => $value) {
                            $set_parts[] = "{$field} = ?";
                            $set_params[] = $value;
                        }
                        $set_parts[] = $approval['approved'] ? "status = 'approved'" : "status = 'pending_approval'";
                        $set_params[] = $entry_id;
                        db_query("UPDATE journal_entries SET " . implode(', ', $set_parts) . " WHERE id = ?", $set_params);
                        set_flash($approval['approved'] ? 'Journal entry created and approved.' : 'Journal entry created and sent for approval.', 'success');
                    } else {
                        db_query("UPDATE journal_entries SET status = 'pending_approval' WHERE id = ?", [$entry_id]);
                        set_flash('Journal entry created. Approval pending: ' . $approval['message'], 'warning');
                    }
                    db_commit();
                    redirect('view_journal_entry.php?id=' . $entry_id);
                }

                db_commit();
                if ($form_action === 'clone' || $form_action === 'reverse') {
                    set_flash('Journal entry created successfully.', 'success');
                    redirect('view_journal_entry.php?id=' . $entry_id);
                }
                set_flash('Journal entry added successfully! Submit it from View to complete approval/posting.', 'success');
                redirect('view_journal_entry.php?id=' . $entry_id);
                
            } catch (Exception $e) {
                db_rollback();
                log_error("Error adding journal entry: " . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        } else {
            $error = err_required();
        }
    }
}

// Generate entry number
$last_entry = db_fetch("SELECT entry_number FROM journal_entries ORDER BY id DESC LIMIT 1");
$next_number = 1;
if ($last_entry && preg_match('/JE-(\d+)/', $last_entry['entry_number'], $matches)) {
    $next_number = intval($matches[1]) + 1;
}
$default_entry_number = 'JE-' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
$selected_main_company_id = active_company_id(($companies[0]['id'] ?? 1));
if (is_post()) {
    $posted_company_id = (int) post('main_company_id', 0);
    if ($posted_company_id > 0) {
        $selected_main_company_id = $posted_company_id;
    }
}

$selected_main_company_name = trim((string) active_company_name(''));
if ($selected_main_company_name === '') {
    $selected_main_company_name = 'N/A';
    foreach ($companies as $company_row) {
        if ((int) $company_row['id'] === $selected_main_company_id) {
            $selected_main_company_name = (string) $company_row['name'];
            break;
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    [data-bs-theme="dark"] {
        --je-bg: #1a1a24;
        --je-panel: #222230;
        --je-text: #b0b0c0;
        --je-text-white: #ffffff;
        --je-border: rgba(255,255,255,0.05);
        --je-input-bg: #1a1a24;
        --je-input-border: rgba(255,255,255,0.1);
        --je-label: #8e8e9e;
    }

    [data-bs-theme="light"] {
        --je-bg: #f8f9fa;
        --je-panel: #ffffff;
        --je-text: #495057;
        --je-text-white: #212529;
        --je-border: #dee2e6;
        --je-input-bg: #ffffff;
        --je-input-border: #ced4da;
        --je-label: #6c757d;
    }

    body { background-color: var(--je-bg); color: var(--je-text); }
    .card { background-color: var(--je-panel); border-color: var(--je-border); border-radius: 10px; }
    .form-control, .form-select { background-color: var(--je-input-bg)!important; border-color: var(--je-input-border)!important; color: var(--je-text-white)!important; padding: 10px 15px; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    .form-label { color: var(--je-label); font-size: 0.85rem; }
    .table-dark { --bs-table-bg: var(--je-panel); --bs-table-striped-bg: var(--je-bg); --bs-table-border-color: var(--je-border); }
    .table-dark th { color: var(--je-label); font-weight: 600; font-size: 0.8rem; letter-spacing: 0.5px; border-bottom: 1px solid var(--je-border); padding: 1rem; }
    .table-dark td { padding: 0.75rem 1rem; vertical-align: middle; border-bottom: 1px solid var(--je-border); }
    .table-dark input, .table-dark select { background-color: var(--je-input-bg)!important; border: 1px solid var(--je-input-border)!important; color: var(--je-text-white)!important; }
    
    .section-badge { background-color: rgba(13, 202, 240, 0.1); color: #0dcaf0; padding: 6px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; display: inline-flex; align-items: center; letter-spacing: 0.5px; }
    .auto-gen-box { background-color: rgba(13, 202, 240, 0.05); border: 1px dashed rgba(13, 202, 240, 0.3); color: #0dcaf0; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; text-align: right; }
    .company-box { background-color: rgba(13, 202, 240, 0.05); border: 1px dashed rgba(13, 202, 240, 0.3); color: #0dcaf0; padding: 10px 20px; border-radius: 8px; font-size: 0.85rem; text-align: right; min-width: 260px; }
</style>

<div class="container-fluid px-4 py-3">
    
    <!-- Screen Breadcrumb Box -->
    <div class="rounded p-2 mb-4 d-flex align-items-center" style="background-color: #0c2b33; border: 1px solid #134e5e;">
        <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #0dcaf0; margin-left:10px; margin-right: 15px;"></div>
        <span style="color: #0dcaf0; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px;">SCREEN: Journal Entries — Create / Entry Form</span>
    </div>

    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="journal_entries.php" class="text-decoration-none d-flex align-items-center mb-2" style="color: #8e8e9e; font-size: 0.9rem;">
                <i class="fas fa-arrow-left me-2"></i> Back to Journal Entries
            </a>
            <div class="d-flex align-items-center">
                <i class="fas fa-plus-circle text-white fs-2 me-3" style="opacity: 0.9;"></i>
                <h2 class="mb-0 text-white fw-bold">New Journal Entry</h2>
            </div>
        </div>
    </div>

    <form method="POST" action="" id="journalForm">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="main_company_id" value="<?= (int)$selected_main_company_id ?>">
        
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
                        <span class="me-2" style="opacity: 0.7;">01</span> Journal Entry Header <span class="text-muted ms-3 fw-normal" style="font-size: 0.75rem;">— General information</span>
                    </div>
                    <div class="d-flex gap-3">
                        <div class="company-box px-4 py-3" style="background: linear-gradient(135deg, #132f3c 0%, #0c1b24 100%); border: 1px solid rgba(13, 202, 240, 0.2);">
                            <div class="text-center">
                                <div style="color: #0dcaf0; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Company / Subsidiary</div>
                                <strong style="color: #e6f7ff; font-size: 1.1rem;"><?= escape_html($selected_main_company_name) ?></strong>
                            </div>
                        </div>
                        <div class="auto-gen-box px-4 py-3" style="background: linear-gradient(135deg, #132f3c 0%, #0c1b24 100%); border: 1px solid rgba(13, 202, 240, 0.2);">
                            <div class="text-center">
                                <div style="color: #0dcaf0; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">JE Number</div>
                                <strong style="color: #0dcaf0; font-size: 1.1rem;">Auto Generated (<?= post('entry_number', $default_entry_number) ?>)</strong>
                            </div>
                            <input type="hidden" name="entry_number" value="<?= post('entry_number', $default_entry_number) ?>">
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <label class="form-label">Journal Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="journal_type">
                            <option value="general" <?= post('journal_type', 'general') === 'general' ? 'selected' : '' ?>>General Journal</option>
                            <option value="reversal" <?= post('journal_type') === 'reversal' ? 'selected' : '' ?>>Reversal Journal</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control <?= isset($errors['entry_date']) ? 'is-invalid' : '' ?>" name="entry_date" required value="<?= post('entry_date', date('Y-m-d')) ?>">
                        <?php if (isset($errors['entry_date'])): ?>
                            <div class="invalid-feedback"><?= $errors['entry_date'] ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Post Period (Auto)</label>
                        <!-- Derived visual layer; database just uses date -->
                        <input type="text" class="form-control bg-dark text-muted" value="<?= date('M Y') ?>" readonly>
                    </div>

                </div>

                <div class="row">
                    <div class="col-12">
                        <label class="form-label">Reason / Narration</label>
                        <textarea class="form-control" name="description" rows="2" placeholder="Explain the purpose of this journal entry..."><?= post('description') ?></textarea>
                    </div>
                </div>

                <div class="row g-4 mt-1">
                    <div class="col-md-4">
                        <label class="form-label">Approval Type <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['approval_type']) ? 'is-invalid' : '' ?>" name="approval_type" id="approval_type">
                            <option value="manager" <?= post('approval_type', 'manager') === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= post('approval_type') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="both" <?= post('approval_type') === 'both' ? 'selected' : '' ?>>Manager + Admin</option>
                        </select>
                    </div>
                    <div class="col-md-4" id="manager_group">
                        <label class="form-label">Manager</label>
                        <select class="form-select <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $m): ?>
                                <?php $manager_name = trim((string)($m['full_name'] ?? '')) ?: (string)$m['username']; ?>
                                <option value="<?= $m['id'] ?>" <?= post('manager_id') == $m['id'] ? 'selected' : '' ?>><?= escape_html($manager_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['manager_id'])): ?><div class="invalid-feedback"><?= $errors['manager_id'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4" id="admin_group">
                        <label class="form-label">Admin</label>
                        <select class="form-select <?= isset($errors['admin_id']) ? 'is-invalid' : '' ?>" name="admin_id">
                            <option value="">Select Admin</option>
                            <?php foreach ($admins as $a): ?>
                                <?php $admin_name = trim((string)($a['full_name'] ?? '')) ?: (string)$a['username']; ?>
                                <option value="<?= $a['id'] ?>" <?= post('admin_id') == $a['id'] ? 'selected' : '' ?>><?= escape_html($admin_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['admin_id'])): ?><div class="invalid-feedback"><?= $errors['admin_id'] ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Lines -->
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="section-badge">
                        <span class="me-2" style="opacity: 0.7;">02</span> Account Lines <span class="text-muted ms-3 fw-normal" style="font-size: 0.75rem;">— Debit and Credit entries (must balance)</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-dark mb-0" id="lineItemsTable" style="background-color: transparent;">
                        <thead>
                            <tr style="background:#171c32;">
                                <th style="width: 15%">ACCOUNT CODE</th>
                                <th style="width: 35%">ACCOUNT NAME</th>
                                <th style="width: 20%" class="text-end">DEBIT AMOUNT</th>
                                <th style="width: 20%" class="text-end">CREDIT AMOUNT</th>
                                <th style="width: 10%" class="text-center">DEL</th>
                            </tr>
                        </thead>
                        <tbody id="lineItems">
                            <!-- Line 1 -->
                            <tr class="entry-line">
                                <td>
                                    <select class="form-select border-0 shadow-none account-selector" name="account_id_code[]" required style="border-radius:6px;">
                                        <option value="">Select Code...</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= escape_html($account['code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select border-0 shadow-none account-name-selector" name="account_id_name[]" style="border-radius:6px; background: transparent;">
                                        <option value="">Select Name...</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= escape_html($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="account_id[]" class="actual-account-id" value="">
                                </td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="debit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="credit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                                <td class="text-center">
                                    <input type="hidden" name="company_id[]" value="<?= (int)$selected_main_company_id ?>">
                                    <input type="hidden" name="cost_center_id[]" value="">
                                    <input type="hidden" name="project_id[]" value="">
                                    <input type="hidden" name="line_description[]" value="">
                                </td>
                            </tr>
                            <!-- Line 2 -->
                            <tr class="entry-line">
                                <td>
                                    <select class="form-select border-0 shadow-none account-selector" name="account_id_code[]" required style="border-radius:6px;">
                                        <option value="">Select Code...</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= escape_html($account['code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select border-0 shadow-none account-name-selector" name="account_id_name[]" style="border-radius:6px; background: transparent;">
                                        <option value="">Select Name...</option>
                                        <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= escape_html($account['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="account_id[]" class="actual-account-id" value="">
                                </td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="debit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="credit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm remove-line" style="color: #ff5252; background: rgba(255,82,82,0.1); border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-times" style="font-size: 10px;"></i>
                                    </button>
                                    <input type="hidden" name="company_id[]" value="<?= (int)$selected_main_company_id ?>">
                                    <input type="hidden" name="cost_center_id[]" value="">
                                    <input type="hidden" name="project_id[]" value="">
                                    <input type="hidden" name="line_description[]" value="">
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="background: rgba(13,202,240,0.03);">
                                <td colspan="2" class="text-end pe-4" style="color: #8e8e9e; vertical-align: middle;"><strong>TOTAL</strong></td>
                                <td class="text-end font-monospace fs-5" style="color: #0dcaf0; vertical-align: middle;"><strong id="totalDebit">0.00</strong></td>
                                <td class="text-end font-monospace fs-5" style="color: #0dcaf0; vertical-align: middle;"><strong id="totalCredit">0.00</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="text-center py-2 mb-3" style="border:1px dashed #0dcaf0; border-radius:8px; background: rgba(13,202,240,0.12);">
                    <button type="button" class="btn btn-link text-decoration-none fw-bold" id="addLine" style="color: #0dcaf0; font-size: 1.1rem;">
                        + Add Account Line
                    </button>
                </div>

                <div style="background:rgba(255,176,46,0.12); border:1px solid rgba(255,176,46,0.25); color:#ffb02e; border-radius:8px; padding:10px 14px;">
                    ⚖ Debit and Credit totals must balance before saving. System will validate.
                </div>

                <!-- Hidden Amount in Words for Print -->
                <div class="print-words-box">
                    <strong>Amount in Words:</strong> <span id="printTotalWords">Zero Rupees Only</span>
                </div>

                <div class="print-footer-grid">
                    <div class="print-footer-column">
                        <div class="print-footer-title"><i class="fas fa-list-alt"></i> TERMS & CONDITIONS</div>
                        <div class="print-footer-sig" style="border: none; margin-top: 0; text-align: left; color: #333;">
                            1. This voucher is valid for 30 days from date of issuance.<br>
                            2. Subject to MJR Internal Audit approval.<br>
                            3. Authorized signature is mandatory for physical filing.
                        </div>
                    </div>
                    <div class="print-footer-column text-end">
                        <div class="print-footer-title"><i class="fas fa-hand-holding-usd"></i> FOR MJR COMPANY</div>
                        <div class="print-footer-sig">Authorized Signatory</div>
                        <div class="mt-3" style="font-size: 0.7rem; color: #ff5252; font-weight: 700;">
                            <i class="fas fa-exclamation-triangle"></i> Voucher expires if not posted by month-end.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div class="position-sticky bottom-0 p-3 mb-4 d-flex justify-content-between align-items-center rounded no-print" style="background-color: rgba(34, 34, 48, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); box-shadow: 0 -10px 30px rgba(0,0,0,0.5); z-index: 100;">
            <div class="d-flex gap-2">
                <button type="submit" name="form_action" value="save" class="btn px-4 d-flex align-items-center" style="background-color: #0dcaf0; color: #000; border: none; font-weight: bold;">
                    <i class="fas fa-save me-2"></i> Save
                </button>
                <button type="button" onclick="window.print()" class="btn px-3 text-white border" style="background: transparent; border-color: rgba(255,255,255,0.2)!important;">
                    <i class="fas fa-print me-2"></i> Print
                </button>
                <button type="submit" name="form_action" value="clone" class="btn px-3 text-white border" style="background: transparent; border-color: rgba(255,255,255,0.2)!important;">
                    <i class="fas fa-copy me-2"></i> Clone
                </button>
                <button type="submit" name="form_action" value="reverse" class="btn px-3 text-white border" style="background: transparent; border-color: rgba(255,255,255,0.2)!important;">
                    <i class="fas fa-exchange-alt me-2"></i> Reverse
                </button>
            </div>
            
            <button type="submit" name="form_action" value="approve" class="btn px-5 fw-bold" style="background-color: #3cc553; color: #fff; border: none; box-shadow: 0 4px 15px rgba(60, 197, 83, 0.3);">
                <i class="fas fa-check me-2"></i> Approve
            </button>
        </div>
    </form>
</div>

<style>
@media print {
    /* Hide everything non-essential */
    nav, .navbar, .sidebar, .btn, .no-print, .position-sticky, .badge span:first-child, .section-badge span:first-child { display: none !important; }
    
    body { background: #fff !important; color: #000 !important; font-size: 10pt; padding: 0 !important; margin: 0 !important; font-family: 'Segoe UI', Roboto, sans-serif; }
    .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
    
    /* Document Container */
    .card { background: #fff !important; border: 1px solid #ddd !important; box-shadow: none !important; color: #000 !important; border-radius: 0 !important; margin-bottom: 0 !important; }
    .card-body { padding: 0 !important; }
    
    /* Header (Exact Quotation Design) */
    .print-header-premium { display: block !important; border-top: 10px solid #ffcc00; }
    .print-top-bar { background: #1a1a24; color: #fff; display: flex; justify-content: space-between; align-items: center; padding: 20px 30px; position: relative; }
    .print-logo-circle { width: 70px; height: 70px; background: #ffcc00; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #1a1a24; font-weight: 900; font-size: 1.5rem; margin-right: 20px; border: 3px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); }
    .print-company-info h2 { margin: 0; font-size: 1.8rem; font-weight: 800; letter-spacing: 1px; color: #fff; }
    .print-company-info p { margin: 0; font-size: 0.85rem; opacity: 1; font-weight: 500; }
    .print-type-label { background: #ffcc00; color: #1a1a24; font-weight: 800; padding: 12px 40px; border-radius: 8px; font-size: 1.2rem; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    
    .print-address-bar { background: #1a1a24; color: #fff; border-top: 1px solid rgba(255,255,255,0.1); padding: 10px 30px; font-size: 0.8rem; opacity: 0.9; }

    /* Metadata Bar (Grey strip with icons) */
    .print-meta-grid { display: grid !important; grid-template-columns: repeat(4, 1fr); border-bottom: 2px solid #1a1a24; background: #f8f9fa; margin-bottom: 0; }
    .print-meta-item { border-right: 1px solid #ddd; padding: 12px 20px; display: flex; align-items: center; gap: 10px; }
    .print-meta-item:last-child { border-right: none; }
    .print-meta-item i { color: #1a1a24; font-size: 1rem; }
    .print-meta-label { font-size: 0.7rem; color: #333; text-transform: uppercase; font-weight: 700; display: block; }
    .print-meta-value { font-size: 0.9rem; font-weight: 600; color: #000; }

    /* Section Banners (From/To) */
    .print-section-grid { display: grid !important; grid-template-columns: 1fr 1fr; border-bottom: 1px solid #ddd; }
    .print-section-column { padding: 0; border-right: 1px solid #ddd; }
    .print-section-column:last-child { border-right: none; }
    .print-section-banner { background: #1a1a24; color: #fff; padding: 12px 20px; font-size: 0.85rem; font-weight: 800; display: flex; justify-content: space-between; align-items: center; text-transform: uppercase; }
    .print-section-content { padding: 20px; font-size: 0.9rem; color: #333; line-height: 1.5; }

    /* Table Styling */
    .table-dark { border-collapse: collapse !important; width: 100% !important; margin: 0 !important; }
    .table-dark thead tr { background: #1a1a24 !important; color: #fff !important; }
    .table-dark th { padding: 15px 12px !important; font-size: 0.85rem !important; border: 1px solid rgba(255,255,255,0.1) !important; text-transform: uppercase; font-weight: 800; }
    .table-dark td { padding: 12px !important; border: 1px solid #eee !important; color: #000 !important; font-size: 0.9rem !important; background: transparent !important; }
    .table-dark tbody tr:nth-child(even) { background: #f9f9f9 !important; }
    
    /* Total Quoted Price Banner */
    .total-banner-premium { display: flex !important; background: #1a1a24 !important; color: #fff !important; justify-content: space-between; align-items: center; padding: 20px 30px !important; margin-top: 0; border-top: 5px solid #ffcc00; }
    .total-banner-label { font-size: 1.1rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; }
    .total-banner-value { font-size: 2rem; font-weight: 900; color: #ffcc00; display: flex; gap: 15px; align-items: center; }
    .total-banner-value::before { content: '₹'; font-size: 1.5rem; opacity: 0.7; }

    /* Amount in Words box */
    .print-words-box { display: block !important; background: #fffde7; border: 1px solid #fff9c4; padding: 12px 30px; margin: 15px 0; font-style: italic; color: #555; font-size: 0.95rem; border-left: 5px solid #ffcc00; }

    /* Footer / Terms Section */
    .print-footer-grid { display: grid !important; grid-template-columns: 1fr 1fr; margin-top: 20px; border-top: 1px solid #ddd; }
    .print-footer-column { padding: 20px 30px; border-right: 1px solid #ddd; }
    .print-footer-column:last-child { border-right: none; }
    .print-footer-title { font-size: 0.8rem; font-weight: 800; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .print-footer-title i { color: #1a1a24; }
    .print-footer-sig { font-size: 0.9rem; font-weight: 500; color: #666; margin-top: 60px; border-top: 1px solid #333; display: inline-block; padding-top: 5px; min-width: 200px; text-align: center; }

    /* Standard inputs replacement */
    .form-control, .form-select { border: none !important; padding: 0 !important; font-weight: 600; font-size: 0.9rem !important; background: transparent !important; color: #000 !important; }
    .text-white, .account-name-selector, .account-selector { color: #000 !important; -webkit-appearance: none; }
    .stat-card { border: none !important; background: transparent !important; padding: 0 !important; margin: 0 !important; }
}

.print-header-premium, .total-banner-premium, .print-footer-grid, .print-meta-grid, .print-section-grid, .print-words-box { display: none; }
</style>

<!-- HIDDEN PREMIUM PRINT LAYOUT -->
<div class="print-header-premium">
    <div class="print-top-bar">
        <div class="d-flex align-items-center">
            <div class="print-logo-circle">MJR</div>
            <div class="print-company-info">
                <h2>MJR COMPANY</h2>
                <p>Steel & Metal Fabrication Division</p>
                <p>Quality • Precision • Reliability</p>
            </div>
        </div>
        <div class="print-type-label">VOUCHER</div>
    </div>
    <div class="print-address-bar">
        123, Industrial Area Phase-2, Jaipur, Rajasthan — 302001 | +91-98765-43210 | mjrcompany.com
    </div>
</div>

<div class="print-meta-grid">
    <div class="print-meta-item">
        <i class="fas fa-file-invoice"></i>
        <div>
            <span class="print-meta-label">Voucher No</span>
            <span class="print-meta-value">JE-<?= date('Y') ?>-XXXX</span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-calendar-alt"></i>
        <div>
            <span class="print-meta-label">Entry Date</span>
            <span class="print-meta-value"><?= date('d F Y') ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-clock"></i>
        <div>
            <span class="print-meta-label">Period</span>
            <span class="print-meta-value"><?= date('F Y') ?></span>
        </div>
    </div>
    <div class="print-meta-item">
        <i class="fas fa-tag"></i>
        <div>
            <span class="print-meta-label">Reference</span>
            <span class="print-meta-value">FIN-GL-<?= date('md') ?></span>
        </div>
    </div>
</div>

<div class="print-section-grid">
    <div class="print-section-column">
        <div class="print-section-banner">
            <span>FROM (ISSUED BY)</span>
            <i class="fas fa-arrow-right"></i>
        </div>
        <div class="print-section-content">
            <strong>MJR Company</strong><br>
            Finance & Accounts Division<br>
            123, Industrial Area Phase-2, Jaipur<br>
            Phone: +91-98765-43210<br>
            Email: accounts@mjrcompany.com
        </div>
    </div>
    <div class="print-section-column">
        <div class="print-section-banner">
            <i class="fas fa-building"></i>
            <span>TO (ACCOUNTING DEPT)</span>
        </div>
        <div class="print-section-content">
            <strong>General Ledger Posting</strong><br>
            Internal Audit Section<br>
            MJR Group ERP Centralized System<br>
            Ref: Voucher Reconciliation Module
        </div>
    </div>
</div>

<!-- Main content (Voucher Table) starts here in DOM -->


<script>
$(document).ready(function() {
    const accounts = <?= json_encode($accounts) ?>;
    function toggleApprovalColumns() {
        const type = $('#approval_type').val() || 'manager';
        $('#manager_group').toggle(type === 'manager' || type === 'both');
        $('#admin_group').toggle(type === 'admin' || type === 'both');
    }
    
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
        
        const diff = Math.abs(totalDebit - totalCredit);
        if (diff < 0.01) {
            $('.fa-exclamation-triangle').parent().parent().hide();
        } else {
            $('.fa-exclamation-triangle').parent().parent().show();
        }
        $('#printTotal').text(totalDebit.toFixed(2));
    }

    function syncLineCompanyIds() {
        const selectedCompany = $('input[name=\"main_company_id\"]').val() || '1';
        $('input[name=\"company_id[]\"]').val(selectedCompany);
    }
    
    $(document).on('change', '.account-selector', function() {
        const row = $(this).closest('tr');
        const selectedId = $(this).val();
        row.find('.account-name-selector').val(selectedId);
        row.find('.actual-account-id').val(selectedId);
    });

    $(document).on('change', '.account-name-selector', function() {
        const row = $(this).closest('tr');
        const selectedId = $(this).val();
        row.find('.account-selector').val(selectedId);
        row.find('.actual-account-id').val(selectedId);
    });

    $('#addLine').click(function() {
        const newRow = `
            <tr class="entry-line">
                <td>
                    <select class="form-select border-0 shadow-none account-selector" name="account_id_code[]" required style="border-radius:6px;">
                        <option value="">Select Code...</option>
                        <?php foreach ($accounts as $account): ?>
                        <option value="<?= $account['id'] ?>"><?= escape_html($account['code']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select class="form-select border-0 shadow-none account-name-selector" name="account_id_name[]" style="border-radius:6px; background: transparent;">
                        <option value="">Select Name...</option>
                        <?php foreach ($accounts as $account): ?>
                        <option value="<?= $account['id'] ?>"><?= escape_html($account['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="account_id[]" class="actual-account-id" value="">
                </td>
                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="debit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                <td><input type="number" class="form-control border-0 shadow-none text-end font-monospace text-white" name="credit[]" step="0.01" min="0" value="0.00" style="border-radius:6px;"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm remove-line" style="color: #ff5252; background: rgba(255,82,82,0.1); border-radius: 50%; width: 24px; height: 24px; padding: 0; line-height: 24px; display: inline-flex; align-items: center; justify-content: center;">
                        <i class="fas fa-times" style="font-size: 10px;"></i>
                    </button>
                    <input type="hidden" name="company_id[]" value="">
                    <input type="hidden" name="cost_center_id[]" value="">
                    <input type="hidden" name="project_id[]" value="">
                    <input type="hidden" name="line_description[]" value="">
                </td>
            </tr>
        `;
        $('#lineItems').append(newRow);
        syncLineCompanyIds();
    });
    
    $(document).on('click', '.remove-line', function() {
        $(this).closest('tr').remove();
        updateTotals();
    });
    
    $(document).on('input', 'input[name="debit[]"], input[name="credit[]"]', function() {
        updateTotals();
    });

    $('#approval_type').on('change', toggleApprovalColumns);
    toggleApprovalColumns();
    syncLineCompanyIds();
    updateTotals();
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
