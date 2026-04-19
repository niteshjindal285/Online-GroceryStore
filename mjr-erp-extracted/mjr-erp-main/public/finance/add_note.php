<?php
/**
 * Add Debit / Credit Note
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Create Debit / Credit Note - MJR Group ERP';
ensure_finance_approval_columns('debit_credit_notes');
$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];

$active_company_id   = (int)active_company_id();
$active_company_name = active_company_name('Selected Company');
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name") ?: [];

$default_company_id = $active_company_id > 0 ? $active_company_id : 1;
$selected_company_id = $default_company_id;
$selected_company_name = 'N/A';
foreach ($companies as $company_row) {
    if ((int)$company_row['id'] === $selected_company_id) {
        $selected_company_name = (string)$company_row['name'];
        break;
    }
}

$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]) ?: [];
$customers = db_fetch_all("SELECT id, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$selected_company_id]) ?: [];
$accounts = db_fetch_all("SELECT id, code, name FROM accounts WHERE is_active = 1 AND company_id = ? ORDER BY code", [$selected_company_id]) ?: [];

$last = db_fetch("SELECT note_number FROM debit_credit_notes ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last && !empty($last['note_number'])) {
    preg_match('/(\d+)$/', (string)$last['note_number'], $m);
    $next_num = (int)($m[1] ?? 0) + 1;
}
$default_note_number = 'DCN-' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);

$errors = [];

if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $form_action = strtolower(trim((string)post('form_action', 'save')));
        $note_number = trim((string)post('note_number', ''));
        $note_date = trim((string)post('note_date', ''));
        $company_id = $selected_company_id;
        $type = trim((string)post('type', 'debit_note'));
        $entity_type = trim((string)post('entity_type', 'customer'));
        $entity_id = (int)post('entity_id', 0);
        $amount = (float)post('amount', 0);
        $reason = trim((string)post('reason', ''));
        $approval_type = trim((string)post('approval_type', 'manager'));
        $manager_id = post('manager_id') ?: null;
        $admin_id = post('admin_id') ?: null;
        $attachment_file = $_FILES['note_attachment'] ?? null;

        if ($form_action === 'clone') {
            $reason = trim('CLONE - ' . $reason);
        } elseif ($form_action === 'reverse') {
            $reason = trim('REVERSAL - ' . $reason);
        }

        if ($note_number === '') $errors['note_number'] = err_required();
        if ($note_date === '') $errors['note_date'] = err_required();
        if ($company_id <= 0) $errors['company_id'] = 'Company is required.';
        if (!in_array($type, ['debit_note', 'credit_note'], true)) $errors['type'] = 'Please select a valid note type.';
        if (!in_array($entity_type, ['customer', 'supplier'], true)) $errors['entity_type'] = 'Please select Debtors or Supplier.';
        if ($entity_id <= 0) $errors['entity_id'] = 'Please select a valid party.';
        if ($amount <= 0) $errors['amount'] = 'Amount must be greater than 0.';
        if ($attachment_file && (int)($attachment_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file_error = (int)$attachment_file['error'];
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors['note_attachment'] = 'Failed to upload attachment.';
            } else {
                $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                $orig_name = (string)($attachment_file['name'] ?? '');
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                $size = (int)($attachment_file['size'] ?? 0);
                if (!in_array($ext, $allowed_ext, true)) {
                    $errors['note_attachment'] = 'Attachment must be PDF, JPG, JPEG, PNG, or WEBP.';
                }
                if ($size > 5 * 1024 * 1024) {
                    $errors['note_attachment'] = 'Attachment size must be 5MB or less.';
                }
            }
        }
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM debit_credit_notes WHERE note_number = ?", [$note_number]);
            if ($exists) $errors['note_number'] = 'This note number already exists!';
        }

        if (empty($errors)) {
            try {
                $new_id = db_insert(
                    "INSERT INTO debit_credit_notes (company_id, note_number, note_date, type, entity_type, entity_id, amount, reason, status, approval_type, manager_id, admin_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW())",
                    [$company_id, $note_number, $note_date, $type, $entity_type, $entity_id, $amount, $reason, $approval_type, $manager_id, $admin_id, current_user_id()]
                );

                if ($attachment_file && (int)($attachment_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/debit_credit_notes/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0775, true);
                    }
                    if (is_dir($upload_dir) && is_writable($upload_dir)) {
                        $orig_name = (string)($attachment_file['name'] ?? '');
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        $safe_note = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$note_number);
                        $stored_name = $safe_note . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        $target_abs = $upload_dir . $stored_name;
                        if (move_uploaded_file((string)$attachment_file['tmp_name'], $target_abs)) {
                            $db_path = 'uploads/debit_credit_notes/' . $stored_name;
                            db_query("UPDATE debit_credit_notes SET note_attachment = ? WHERE id = ?", [$db_path, $new_id]);
                        } else {
                            set_flash('Note created, but attachment upload failed.', 'warning');
                        }
                    } else {
                        set_flash('Note created, but upload directory is not writable.', 'warning');
                    }
                }

                if ($form_action === 'approve') {
                    $created = db_fetch("SELECT * FROM debit_credit_notes WHERE id = ?", [$new_id]);
                    $approval = finance_process_approval_action($created ?: [], current_user_id());
                    if ($approval['ok']) {
                        $set_parts = [];
                        $set_params = [];
                        foreach ($approval['fields'] as $field => $value) {
                            $set_parts[] = "{$field} = ?";
                            $set_params[] = $value;
                        }
                        if ($approval['approved']) {
                            $set_parts[] = "status = 'posted'";
                        }
                        if (!empty($set_parts)) {
                            $set_params[] = $new_id;
                            db_query("UPDATE debit_credit_notes SET " . implode(', ', $set_parts) . " WHERE id = ?", $set_params);
                        }
                        set_flash('Note created and approval action completed.', 'success');
                    } else {
                        set_flash('Note created as draft. Approval pending: ' . $approval['message'], 'warning');
                    }
                    redirect('view_note.php?id=' . (int)$new_id);
                }

                set_flash('Note ' . $note_number . ' created successfully!', 'success');
                redirect('view_note.php?id=' . (int)$new_id);
            } catch (Exception $e) {
                log_error('Error creating debit/credit note: ' . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --dn-bg: #090d1d;
    --dn-panel: #1f2540;
    --dn-panel-2: #222946;
    --dn-border: #313a61;
    --dn-cyan: #10c5df;
    --dn-green: #4ac95e;
    --dn-warn: #ffb32f;
    --dn-soft: #8f9bbf;
}

[data-bs-theme="light"] {
    --dn-bg: #f8f9fa;
    --dn-panel: #ffffff;
    --dn-panel-2: #f8f9fa;
    --dn-border: #e0e0e0;
    --dn-cyan: #0dcaf0;
    --dn-green: #198754;
    --dn-warn: #ffc107;
    --dn-soft: #6c757d;
}

body { background: var(--dn-bg); color: var(--dn-soft); }
.dn-hero { border: 1px solid rgba(16,197,223,.55); border-radius: 12px; background: linear-gradient(90deg, rgba(16,197,223,.16), rgba(16,197,223,.06)); color: var(--dn-cyan); font-weight: 700; letter-spacing: .4px; }
.dn-back { border: 1px solid var(--dn-border); border-radius: 12px; color: var(--dn-soft); text-decoration: none; padding: .72rem 1.15rem; font-weight: 700; transition: all 0.2s; }
.dn-back:hover { background: var(--dn-glass); color: var(--dn-cyan); }
.dn-card { border-radius: 14px; border: 1px solid var(--dn-border); background: linear-gradient(180deg, var(--dn-panel), var(--dn-panel-2)); overflow: hidden; }
.dn-card-h { border-bottom: 1px solid var(--dn-border); padding: 1rem 1.5rem; background: rgba(128, 128, 128, 0.05); }
.dn-badge { background: var(--dn-cyan); color: #06131d; border-radius: 10px; font-weight: 800; padding: .35rem .6rem; min-width: 36px; display: inline-block; text-align: center; }
.dn-sub { font-size: 2rem; font-weight: 700; color: var(--dn-text-header); margin-left: .8rem; vertical-align: middle; }
[data-bs-theme="dark"] .dn-label { color: #9ca8cc; font-size: .9rem; margin-bottom: .35rem; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: .6px; }
[data-bs-theme="light"] .dn-label { color: #6c757d; font-size: .9rem; margin-bottom: .35rem; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: .6px; }
[data-bs-theme="dark"] .dn-input, [data-bs-theme="dark"] .dn-select, [data-bs-theme="dark"] .dn-textarea {
    width: 100%; background: #252d4a !important; border: 1px solid #344271 !important; color: #f2f5ff !important;
    border-radius: 10px; padding: .62rem .85rem;
}
[data-bs-theme="light"] .dn-input, [data-bs-theme="light"] .dn-select, [data-bs-theme="light"] .dn-textarea {
    width: 100%; background: #ffffff !important; border: 1px solid #dee2e6 !important; color: #212529 !important;
    border-radius: 10px; padding: .62rem .85rem;
}
.dn-input:focus, .dn-select:focus, .dn-textarea:focus { border-color: rgba(16,197,223,.7) !important; box-shadow: 0 0 0 .2rem rgba(16,197,223,.2) !important; }
.dn-textarea { min-height: 100px; }
.dn-auto-box { border: 1px solid rgba(16,197,223,.55); border-radius: 12px; padding: .55rem .85rem; text-align: center; min-width: 340px; }
.dn-auto-box .cap { color: var(--dn-soft); font-size: 1.15rem; line-height: 1.2; }
.dn-auto-box .val { color: var(--dn-cyan); font-weight: 800; font-size: 2rem; line-height: 1.2; }
.dn-radio-wrap { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; min-height: 42px; }
.dn-radio { display: inline-flex; align-items: center; gap: .45rem; color: var(--dn-text-header); font-weight: 700; cursor: pointer; }
.dn-radio input { accent-color: var(--dn-cyan); }
.dn-note { background: rgba(255,179,47,.14); border-left: 3px solid rgba(255,179,47,.7); color: var(--dn-warn); border-radius: 6px; padding: .65rem .9rem; }
.dn-table { --bs-table-bg: transparent; --bs-table-border-color: var(--dn-border); margin-bottom: 0; }
.dn-table th { color: var(--dn-soft); font-size: .86rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; border-bottom: 1px solid var(--dn-border); padding: 1rem; background: rgba(128, 128, 128, 0.1); }
.dn-table td { color: var(--dn-text-header); border-bottom: 1px solid var(--dn-border); padding: .7rem 1rem; vertical-align: middle; }
.dn-del { background: transparent; border: 0; color: #ff6d6d; }
.dn-total-row td { background: rgba(16,197,223,.05); font-weight: 800; }
.dn-add-row { border: 1px dashed var(--dn-cyan); border-radius: 8px; background: rgba(16,197,223,.12); text-align: center; color: var(--dn-cyan); font-weight: 800; padding: .65rem; cursor: pointer; }
.dn-actions { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.dn-btn { border: 1px solid var(--dn-border); border-radius: 12px; background: var(--mjr-glass); color: var(--dn-soft); padding: .66rem 1.2rem; font-weight: 700; transition: all 0.2s; }
.dn-btn:hover { background: rgba(128, 128, 128, 0.15); color: var(--dn-text-header); }
.dn-btn-save { background: var(--dn-cyan); color: #06131d; border-color: transparent; }
.dn-btn-save:hover { background: #0baccc; color: #06131d; }
.dn-btn-approve { background: #49ba57; color: #fff; border-color: transparent; }
.dn-btn-approve:hover { background: #3da14a; color: #fff; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="dn-hero px-4 py-3 mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem"></i> SCREEN: Debit / Credit Note - Create / Entry Form</div>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1 text-white">+ New Debit / Credit Note</h1>
            <p class="mb-0">Issue debit or credit notes against suppliers, debtors, or inventory.</p>
        </div>
        <a href="debit_credit_notes.php" class="dn-back">&larr; Back to DCN List</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background:rgba(255,79,69,.16); color:#ff9b95; border-color:rgba(255,79,69,.35);">
            <?php foreach ($errors as $err): ?><div><?= escape_html($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="noteForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="note_number" value="<?= escape_html(post('note_number', $default_note_number)) ?>">
        <input type="hidden" name="amount" id="amount" value="<?= escape_html((string)post('amount', '0')) ?>">
        <input type="hidden" name="entity_id" id="entity_id" value="<?= (int)post('entity_id', 0) ?>">

        <div class="dn-card mb-4">
            <div class="dn-card-h d-flex justify-content-between align-items-center">
                <div><span class="dn-badge">01</span><span class="dn-sub">Note Header</span></div>
                <div class="dn-auto-box">
                    <div class="cap">Reference #</div>
                    <div class="val">Auto Generated (<?= escape_html(post('note_number', $default_note_number)) ?>)</div>
                </div>
            </div>
            <div class="p-4">
                <div class="row g-3 mb-3">
                    <div class="col-lg-3">
                        <label class="dn-label">Date</label>
                        <input type="date" class="dn-input" name="note_date" id="note_date" value="<?= escape_html(post('note_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="dn-label">Post Period *</label>
                        <input type="text" class="dn-input" id="post_period" value="<?= escape_html(date('F Y', strtotime((string)post('note_date', date('Y-m-d'))))) ?>" readonly>
                    </div>
                    <div class="col-lg-3">
                        <label class="dn-label">Company / Subsidiary *</label>
                        <input type="text" class="dn-input" value="<?= escape_html($selected_company_name) ?>" readonly>
                        <input type="hidden" name="company_id" value="<?= (int)$selected_company_id ?>">
                    </div>
                    <div class="col-lg-3">
                        <label class="dn-label">Note Type *</label>
                        <div class="dn-radio-wrap">
                            <label class="dn-radio"><input type="radio" name="type" value="debit_note" <?= post('type', 'debit_note') === 'debit_note' ? 'checked' : '' ?>> Debit Note</label>
                            <label class="dn-radio"><input type="radio" name="type" value="credit_note" <?= post('type') === 'credit_note' ? 'checked' : '' ?>> Credit Note</label>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-3">
                        <label class="dn-label">Party Type *</label>
                        <div class="dn-radio-wrap">
                            <label class="dn-radio"><input type="radio" name="entity_type" value="customer" <?= post('entity_type', 'customer') === 'customer' ? 'checked' : '' ?>> Debtors</label>
                            <label class="dn-radio"><input type="radio" name="entity_type" value="supplier" <?= post('entity_type') === 'supplier' ? 'checked' : '' ?>> Supplier</label>
                            <label class="dn-radio" style="opacity:.6;"><input type="radio" disabled> Inventory</label>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <label class="dn-label">Select Party *</label>
                        <select class="dn-select" id="entity_id_customer">
                            <option value="">Select from debtors list...</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= post('entity_id') == $c['id'] && post('entity_type', 'customer') === 'customer' ? 'selected' : '' ?>><?= escape_html((string)$c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="dn-select mt-2" id="entity_id_supplier" style="display:none;">
                            <option value="">Select from supplier list...</option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= (int)$s['id'] ?>" <?= post('entity_id') == $s['id'] && post('entity_type') === 'supplier' ? 'selected' : '' ?>><?= escape_html((string)$s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="dn-note">
                    <i class="fas fa-thumbtack me-2"></i>Supplier: CRN increases supplier account, DRN decreases supplier account. Debtors: DRN increases customer account, CRN decreases customer account.
                </div>
            </div>
        </div>

        <div class="dn-card mb-4">
            <div class="dn-card-h"><span class="dn-badge">02</span><span class="dn-sub">Narration & Attachments</span></div>
            <div class="p-4">
                <label class="dn-label">Narration</label>
                <textarea class="dn-textarea" name="reason" placeholder="Enter reason for debit/credit note..."><?= escape_html(post('reason')) ?></textarea>
                <div class="mt-4">
                    <label class="dn-label">Attachment</label>
                    <input type="file" id="note_attachment" name="note_attachment"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp"
                           class="d-none"
                           onchange="(function(i){var lbl=document.getElementById('noteFileName');var zone=document.getElementById('noteDropZone');if(i.files&&i.files[0]){lbl.textContent='\u2714 '+i.files[0].name;lbl.style.color='#10c5df';zone.style.borderColor='rgba(16,197,223,.7)';}else{lbl.textContent='No file selected';lbl.style.color='#8f9bbf';zone.style.borderColor='';}})(this)">
                    <label for="note_attachment" id="noteDropZone" class="dn-input text-center d-block"
                           style="padding:1.5rem; border-style:dashed; color:#6f7da8; cursor:pointer; transition:border-color .2s;">
                        <i class="fas fa-paperclip me-1"></i> Click to upload supporting documents
                    </label>
                    <small id="noteFileName" class="d-block mt-2" style="color:#8f9bbf;">No file selected</small>
                    <?php if (isset($errors['note_attachment'])): ?>
                        <div class="mt-1" style="color:#ff9b95;"><?= escape_html($errors['note_attachment']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mt-3">
                    <div class="col-lg-3">
                        <label class="dn-label">Approval Type *</label>
                        <select class="dn-select" name="approval_type" id="approval_type">
                            <option value="manager" <?= post('approval_type', 'manager') === 'manager' ? 'selected' : '' ?>>Manager</option>
                            <option value="admin" <?= post('approval_type') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="both" <?= post('approval_type') === 'both' ? 'selected' : '' ?>>Manager + Admin</option>
                        </select>
                    </div>
                    <div class="col-lg-4" id="manager_group">
                        <label class="dn-label">Manager</label>
                        <select class="dn-select" name="manager_id">
                            <option value="">Select Manager</option>
                            <?php foreach ($managers as $m): ?>
                                <?php $manager_name = trim((string)($m['full_name'] ?? '')) ?: (string)$m['username']; ?>
                                <option value="<?= (int)$m['id'] ?>" <?= (int)post('manager_id') === (int)$m['id'] ? 'selected' : '' ?>><?= escape_html($manager_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-4" id="admin_group">
                        <label class="dn-label">Admin</label>
                        <select class="dn-select" name="admin_id">
                            <option value="">Select Admin</option>
                            <?php foreach ($admins as $a): ?>
                                <?php $admin_name = trim((string)($a['full_name'] ?? '')) ?: (string)$a['username']; ?>
                                <option value="<?= (int)$a['id'] ?>" <?= (int)post('admin_id') === (int)$a['id'] ? 'selected' : '' ?>><?= escape_html($admin_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="dn-card mb-4">
            <div class="dn-card-h"><span class="dn-badge">03</span><span class="dn-sub">Account Allocation</span></div>
            <div class="p-4">
                <div class="table-responsive">
                    <table class="table dn-table" id="allocTable">
                        <thead>
                            <tr>
                                <th style="width:20%">Account Code</th>
                                <th style="width:55%">Account Name</th>
                                <th style="width:15%" class="text-end">Amount</th>
                                <th style="width:10%" class="text-center">Del</th>
                            </tr>
                        </thead>
                        <tbody id="allocBody">
                            <tr class="alloc-row">
                                <td>
                                    <select class="dn-select alloc-account" style="padding:.45rem .7rem;">
                                        <option value="">Search account</option>
                                        <?php foreach ($accounts as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>" data-name="<?= escape_html((string)$a['name']) ?>"><?= escape_html((string)$a['code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" class="dn-input alloc-name" value="Auto-filled on account select" readonly style="padding:.45rem .7rem;"></td>
                                <td><input type="number" class="dn-input text-end alloc-amount" step="0.01" min="0" value="<?= escape_html((string)post('amount', '0.00')) ?>" style="padding:.45rem .7rem;"></td>
                                <td class="text-center"><button type="button" class="dn-del"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="dn-total-row">
                                <td colspan="2" class="text-end">TOTAL</td>
                                <td class="text-end" id="allocTotal">$0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="dn-add-row mt-3" id="addAllocRow">+ Add Account Line</div>
            </div>
        </div>

        <div class="dn-actions no-print mb-4">
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="dn-btn dn-btn-save" name="form_action" value="save"><i class="fas fa-save me-2"></i>Save</button>
                <button type="button" class="dn-btn" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
                <button type="submit" class="dn-btn" name="form_action" value="clone"><i class="fas fa-copy me-2"></i>Clone</button>
                <button type="submit" class="dn-btn" name="form_action" value="reverse"><i class="fas fa-undo-alt me-2"></i>Reverse</button>
            </div>
            <button type="submit" class="dn-btn dn-btn-approve" name="form_action" value="approve"><i class="fas fa-check-square me-2"></i>Approve</button>
        </div>
    </form>
</div>

<script>
(function(){
    const allocBody = document.getElementById('allocBody');
    const allocTotal = document.getElementById('allocTotal');
    const amountInput = document.getElementById('amount');
    const noteDate = document.getElementById('note_date');
    const postPeriod = document.getElementById('post_period');
    const entityId = document.getElementById('entity_id');
    const customerSelect = document.getElementById('entity_id_customer');
    const supplierSelect = document.getElementById('entity_id_supplier');
    const noteAttachmentInput = document.getElementById('note_attachment');
    const noteFileName = document.getElementById('noteFileName');

    function formatCurrency(n){ return '$' + (Number(n || 0)).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

    function syncEntity(){
        const entityType = document.querySelector('input[name="entity_type"]:checked')?.value || 'customer';
        if (entityType === 'customer') {
            customerSelect.style.display = '';
            supplierSelect.style.display = 'none';
            entityId.value = customerSelect.value || '';
        } else {
            customerSelect.style.display = 'none';
            supplierSelect.style.display = '';
            entityId.value = supplierSelect.value || '';
        }
    }

    function updateTotals(){
        let total = 0;
        document.querySelectorAll('.alloc-amount').forEach(el => { total += parseFloat(el.value || '0') || 0; });
        allocTotal.textContent = formatCurrency(total);
        amountInput.value = total.toFixed(2);
    }

    function bindRow(row){
        row.querySelector('.alloc-account')?.addEventListener('change', (e) => {
            const opt = e.target.selectedOptions && e.target.selectedOptions[0];
            const nameInput = row.querySelector('.alloc-name');
            if (nameInput) nameInput.value = opt ? (opt.getAttribute('data-name') || '') : '';
        });
        row.querySelector('.alloc-amount')?.addEventListener('input', updateTotals);
        row.querySelector('.dn-del')?.addEventListener('click', () => {
            const rows = document.querySelectorAll('.alloc-row');
            if (rows.length > 1) row.remove();
            updateTotals();
        });
    }

    function rowTemplate(){
        const options = ['<option value="">Search account</option>']
            .concat(Array.from(document.querySelectorAll('.alloc-account option')).slice(1).map(o => `<option value="${o.value}" data-name="${(o.getAttribute('data-name') || '').replace(/"/g, '&quot;')}">${o.textContent}</option>`))
            .join('');
        return `<tr class="alloc-row">
            <td><select class="dn-select alloc-account" style="padding:.45rem .7rem;">${options}</select></td>
            <td><input type="text" class="dn-input alloc-name" value="Auto-filled on account select" readonly style="padding:.45rem .7rem;"></td>
            <td><input type="number" class="dn-input text-end alloc-amount" step="0.01" min="0" value="0.00" style="padding:.45rem .7rem;"></td>
            <td class="text-center"><button type="button" class="dn-del"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    function toggleApprovalColumns(){
        const type = document.getElementById('approval_type')?.value || 'manager';
        const mg = document.getElementById('manager_group');
        const ag = document.getElementById('admin_group');
        if (!mg || !ag) return;
        mg.style.display = (type === 'manager' || type === 'both') ? '' : 'none';
        ag.style.display = (type === 'admin' || type === 'both') ? '' : 'none';
    }

    document.querySelectorAll('input[name="entity_type"]').forEach(r => r.addEventListener('change', syncEntity));
    customerSelect?.addEventListener('change', () => { if (document.querySelector('input[name="entity_type"]:checked')?.value === 'customer') entityId.value = customerSelect.value; });
    supplierSelect?.addEventListener('change', () => { if (document.querySelector('input[name="entity_type"]:checked')?.value === 'supplier') entityId.value = supplierSelect.value; });
    document.getElementById('approval_type')?.addEventListener('change', toggleApprovalColumns);
    document.getElementById('addAllocRow')?.addEventListener('click', () => {
        allocBody.insertAdjacentHTML('beforeend', rowTemplate());
        bindRow(allocBody.lastElementChild);
    });
    noteDate?.addEventListener('change', () => {
        if (!noteDate.value) return;
        const d = new Date(noteDate.value + 'T00:00:00');
        postPeriod.value = d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    });
    noteAttachmentInput?.addEventListener('change', () => {
        const file = noteAttachmentInput.files && noteAttachmentInput.files[0];
        noteFileName.textContent = file ? file.name : 'No file selected';
    });

    document.querySelectorAll('.alloc-row').forEach(bindRow);
    syncEntity();
    toggleApprovalColumns();
    updateTotals();
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
