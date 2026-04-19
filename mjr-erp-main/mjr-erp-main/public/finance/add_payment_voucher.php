<?php
/**
 * Add Payment Voucher
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Add Payment Voucher - MJR Group ERP';
ensure_finance_approval_columns('payment_vouchers');

$approvers = finance_get_approver_users();
$managers = $approvers['managers'];
$admins = $approvers['admins'];
$errors = [];

$bank_accounts = db_fetch_all("SELECT id, bank_name, account_name, currency FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name") ?: [];
$suppliers = db_fetch_all("SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name") ?: [];
$companies = db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name") ?: [];
$accounts = db_fetch_all("SELECT id, code, name FROM accounts WHERE is_active = 1 ORDER BY code") ?: [];

$default_company_id = (int)active_company_id();
if ($default_company_id <= 0) {
    $default_company_id = (int)($companies[0]['id'] ?? 1);
}
$selected_company_id = (int)post('company_id', $default_company_id);
$selected_company_name = 'N/A';
foreach ($companies as $company_row) {
    if ((int)$company_row['id'] === $selected_company_id) {
        $selected_company_name = (string)$company_row['name'];
        break;
    }
}

$last = db_fetch("SELECT voucher_number FROM payment_vouchers ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last) {
    preg_match('/(\d+)$/', (string)$last['voucher_number'], $m);
    $next_num = (int)($m[1] ?? 0) + 1;
}
$default_voucher_number = 'PV-' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);

if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        $form_action = strtolower(trim((string)post('form_action', 'save')));
        $voucher_number = trim((string)post('voucher_number', ''));
        $voucher_date = trim((string)post('voucher_date', ''));
        $company_id = (int)post('company_id', $default_company_id);
        $supplier_id = post('supplier_id') ?: null;
        $bank_account_id = post('bank_account_id') ?: null;
        $amount = (float)post('amount', 0);
        $payment_method = trim((string)post('payment_method', 'Bank Transfer'));
        $payment_reference = trim((string)post('payment_reference', ''));
        $description = trim((string)post('description', ''));
        $approval_type = trim((string)post('approval_type', 'manager'));
        $manager_id = post('manager_id') ?: null;
        $admin_id = post('admin_id') ?: null;
        $attachment_file = $_FILES['invoice_attachment'] ?? null;

        $reference = $voucher_number;
        if ($payment_method === 'Cash') {
            $reference = 'TOP';
        } elseif (in_array($payment_method, ['Bank Transfer', 'Cheque', 'Online Payment'], true)) {
            $reference = $payment_reference !== '' ? $payment_reference : $voucher_number;
        }

        if ($form_action === 'clone') {
            $description = trim('CLONE: ' . $description);
        } elseif ($form_action === 'reverse') {
            $description = trim('REVERSAL: ' . $description);
        }

        if ($voucher_number === '') $errors['voucher_number'] = err_required();
        if ($voucher_date === '') $errors['voucher_date'] = err_required();
        if ($company_id <= 0) $errors['company_id'] = 'Company is required.';
        if ($amount <= 0) $errors['amount'] = 'Amount must be greater than 0.';
        if (in_array($payment_method, ['Bank Transfer', 'Cheque', 'Online Payment'], true) && $payment_reference === '') {
            $errors['payment_reference'] = 'Reference is required for selected payment type.';
        }
        if ($attachment_file && (int)($attachment_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file_error = (int)$attachment_file['error'];
            if ($file_error !== UPLOAD_ERR_OK) {
                $errors['invoice_attachment'] = 'Failed to upload attachment.';
            } else {
                $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                $orig_name = (string)($attachment_file['name'] ?? '');
                $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                $size = (int)($attachment_file['size'] ?? 0);
                if (!in_array($ext, $allowed_ext, true)) {
                    $errors['invoice_attachment'] = 'Attachment must be PDF, JPG, JPEG, PNG, or WEBP.';
                }
                if ($size > 5 * 1024 * 1024) {
                    $errors['invoice_attachment'] = 'Attachment size must be 5MB or less.';
                }
            }
        }
        $errors = array_merge($errors, finance_validate_approval_setup($approval_type, $manager_id, $admin_id));

        if (empty($errors)) {
            $exists = db_fetch("SELECT id FROM payment_vouchers WHERE voucher_number = ?", [$voucher_number]);
            if ($exists) $errors['voucher_number'] = 'Voucher number already exists!';
        }

        if (empty($errors)) {
            try {
                $new_id = db_insert(
                    "INSERT INTO payment_vouchers (company_id, voucher_number, voucher_date, supplier_id, bank_account_id, amount, payment_method, reference, description, status, approval_type, manager_id, admin_id, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW())",
                    [$company_id, $voucher_number, $voucher_date, $supplier_id, $bank_account_id, $amount, $payment_method, $reference, $description, $approval_type, $manager_id, $admin_id, current_user_id()]
                );

                if ($attachment_file && (int)($attachment_file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/../uploads/payment_vouchers/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0775, true);
                    }
                    if (is_dir($upload_dir) && is_writable($upload_dir)) {
                        $orig_name = (string)($attachment_file['name'] ?? '');
                        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
                        $safe_voucher = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$voucher_number);
                        $stored_name = $safe_voucher . '_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.' . $ext;
                        $target_abs = $upload_dir . $stored_name;
                        if (move_uploaded_file((string)$attachment_file['tmp_name'], $target_abs)) {
                            $db_path = 'uploads/payment_vouchers/' . $stored_name;
                            db_query("UPDATE payment_vouchers SET invoice_attachment = ? WHERE id = ?", [$db_path, $new_id]);
                        } else {
                            set_flash('Voucher created, but attachment upload failed.', 'warning');
                        }
                    } else {
                        set_flash('Voucher created, but upload directory is not writable.', 'warning');
                    }
                }

                if ($form_action === 'approve') {
                    $created = db_fetch("SELECT * FROM payment_vouchers WHERE id = ?", [$new_id]);
                    $approval = finance_process_approval_action($created ?: [], current_user_id());
                    if ($approval['ok']) {
                        $set_parts = [];
                        $set_params = [];
                        foreach ($approval['fields'] as $field => $value) {
                            $set_parts[] = "{$field} = ?";
                            $set_params[] = $value;
                        }
                        if ($approval['approved']) $set_parts[] = "status = 'approved'";
                        if (!empty($set_parts)) {
                            $set_params[] = $new_id;
                            db_query("UPDATE payment_vouchers SET " . implode(', ', $set_parts) . " WHERE id = ?", $set_params);
                        }
                        set_flash('Payment voucher created and approval action completed.', 'success');
                    } else {
                        set_flash('Payment voucher created as draft. Approval pending: ' . $approval['message'], 'warning');
                    }
                    redirect('view_voucher.php?id=' . (int)$new_id);
                }

                if ($form_action === 'clone' || $form_action === 'reverse') {
                    set_flash('Payment voucher created successfully.', 'success');
                    redirect('view_voucher.php?id=' . (int)$new_id);
                }

                set_flash('Payment voucher created successfully!', 'success');
                redirect('payment_vouchers.php');
            } catch (Exception $e) {
                log_error('Error creating payment voucher: ' . $e->getMessage());
                set_flash(sanitize_db_error($e->getMessage()), 'error');
            }
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
[data-bs-theme="dark"] {
    --pv-bg: #090d1d;
    --pv-panel: #1f2540;
    --pv-panel-2: #222946;
    --pv-border: #313a61;
    --pv-cyan: #10c5df;
    --pv-green: #4ac95e;
    --pv-warn: #ffb32f;
    --pv-soft: #8f9bbf;
    --pv-input-bg: #252d4a;
    --pv-input-border: #344271;
    --pv-input-text: #f2f5ff;
    --pv-label-text: #9ca8cc;
    --pv-table-text: #eef2ff;
    --pv-table-head-bg: #171c32;
}

[data-bs-theme="light"] {
    --pv-bg: #f8f9fa;
    --pv-panel: #ffffff;
    --pv-panel-2: #f8f9fa;
    --pv-border: #e0e0e0;
    --pv-cyan: #0dcaf0;
    --pv-green: #3cc553;
    --pv-warn: #ffc107;
    --pv-soft: #6c757d;
    --pv-input-bg: #ffffff;
    --pv-input-border: #dee2e6;
    --pv-input-text: #212529;
    --pv-label-text: #495057;
    --pv-table-text: #212529;
    --pv-table-head-bg: #f8f9fa;
}

body { background: var(--pv-bg); color: var(--pv-soft); }
.pv-hero { border: 1px solid rgba(16,197,223,.55); border-radius: 12px; background: linear-gradient(90deg, rgba(16,197,223,.16), rgba(16,197,223,.06)); color: var(--pv-cyan); font-weight: 700; letter-spacing: .4px; }
.pv-back { border: 1px solid var(--pv-border); border-radius: 12px; color: var(--pv-soft); text-decoration: none; padding: .72rem 1.15rem; font-weight: 700; }
.pv-card { border-radius: 14px; border: 1px solid var(--pv-border); background: linear-gradient(180deg, var(--pv-panel), var(--pv-panel-2)); overflow: hidden; }
.pv-card-h { border-bottom: 1px solid var(--pv-border); padding: 1rem 1.5rem; }
.pv-badge { background: var(--pv-cyan); color: #06131d; border-radius: 10px; font-weight: 800; padding: .35rem .6rem; min-width: 36px; display: inline-block; text-align: center; }
.pv-sub { font-size: 2rem; font-weight: 700; color: var(--pv-input-text); margin-left: .8rem; vertical-align: middle; }
.pv-sub-note { color: var(--pv-label-text); font-size: 1.7rem; margin-left: .7rem; }
.pv-muted { color: var(--pv-soft); }
.pv-label { color: var(--pv-label-text); font-size: .9rem; margin-bottom: .35rem; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: .6px; }
.pv-input, .pv-select, .pv-textarea {
    width: 100%; background: var(--pv-input-bg) !important; border: 1px solid var(--pv-input-border) !important; color: var(--pv-input-text) !important;
    border-radius: 10px; padding: .62rem .85rem;
}
.pv-input:focus, .pv-select:focus, .pv-textarea:focus { border-color: var(--pv-cyan) !important; box-shadow: 0 0 0 .2rem rgba(16,197,223,.2) !important; }
.pv-textarea { min-height: 100px; }
.pv-auto-box { border: 1px solid var(--pv-cyan); border-radius: 12px; padding: .55rem .85rem; text-align: center; min-width: 340px; }
.pv-auto-box .cap { color: var(--pv-label-text); font-size: 1.15rem; line-height: 1.2; }
.pv-auto-box .val { color: var(--pv-cyan); font-weight: 800; font-size: 2rem; line-height: 1.2; }
.pv-compact-box { border: 1px solid var(--pv-cyan); border-radius: 12px; padding: .55rem .85rem; min-width: 300px; background: rgba(16,197,223,.05); }
.pv-compact-box .cap { color: var(--pv-label-text); font-size: .9rem; text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }
.pv-compact-box .val { color: var(--pv-input-text); font-weight: 700; font-size: 1.05rem; }
.pv-table { --bs-table-bg: transparent; --bs-table-border-color: var(--pv-border); margin-bottom: 0; }
.pv-table th { color: var(--pv-label-text); font-size: .86rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; border-bottom: 1px solid var(--pv-border); padding: 1rem; background: var(--pv-table-head-bg); }
.pv-table td { color: var(--pv-table-text); border-bottom: 1px solid rgba(49,58,97,.65); padding: .7rem 1rem; vertical-align: middle; }
.pv-del { background: transparent; border: 0; color: #ff6d6d; }
.pv-total-row td { background: rgba(16,197,223,.05); font-weight: 800; }
.pv-add-row { border: 1px dashed var(--pv-cyan); border-radius: 8px; background: rgba(16,197,223,.12); text-align: center; color: var(--pv-cyan); font-weight: 800; padding: .65rem; cursor: pointer; }
.pv-note { background: rgba(255,179,47,.14); border: 1px solid rgba(255,179,47,.25); color: var(--pv-warn); border-radius: 8px; padding: .75rem 1rem; }
.pv-actions { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.pv-btn { border: 1px solid var(--pv-border); border-radius: 12px; background: transparent; color: var(--pv-soft); padding: .66rem 1.2rem; font-weight: 700; }
.pv-btn-save { background: var(--pv-cyan); color: #06131d; border-color: transparent; }
.pv-btn-approve { background: #49ba57; color: #fff; border-color: transparent; }
</style>

<div class="container-fluid px-4 py-4">
    <div class="pv-hero px-4 py-3 mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem"></i> SCREEN: Payment Voucher - Create / Entry Form</div>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1" style="color: var(--pv-input-text);">+ New Payment Voucher</h1>
            <p class="mb-0">Record outgoing payments to suppliers and creditors.</p>
        </div>
        <a href="payment_vouchers.php" class="pv-back">&larr; Back to Payment Vouchers</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="background:rgba(255,79,69,.16); color:#ff9b95; border-color:rgba(255,79,69,.35);">
            <?php foreach ($errors as $err): ?><div><?= escape_html($err) ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php
        $selected_payment_method = post('payment_method', 'Bank Transfer');
        $posted_payment_reference = trim((string)post('payment_reference', ''));
        $payment_ref_label = 'Cheque Number';
        $payment_ref_placeholder = 'Enter after approval';
        $payment_ref_value = $posted_payment_reference;
        if ($selected_payment_method === 'Bank Transfer') {
            $payment_ref_label = 'Bank Transfer Reference Number';
            $payment_ref_placeholder = 'Enter bank transfer reference';
        } elseif ($selected_payment_method === 'Cheque') {
            $payment_ref_label = 'Cheque Number';
            $payment_ref_placeholder = 'Enter cheque number';
        } elseif ($selected_payment_method === 'Online Payment') {
            $payment_ref_label = 'Online Payment Reference Number';
            $payment_ref_placeholder = 'Enter online payment reference';
        } elseif ($selected_payment_method === 'Cash') {
            $payment_ref_label = 'Tonga Currency';
            $payment_ref_placeholder = 'TOP';
            $payment_ref_value = $payment_ref_value !== '' ? $payment_ref_value : 'TOP';
        }
    ?>

    <form method="POST" action="" id="pvForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        <input type="hidden" name="voucher_number" id="voucher_number" value="<?= escape_html(post('voucher_number', $default_voucher_number)) ?>">
        <input type="hidden" name="company_id" value="<?= (int)$selected_company_id ?>">
        <input type="hidden" name="amount" id="amount" value="<?= escape_html((string)post('amount', '0')) ?>">

        <div class="pv-card mb-4">
            <div class="pv-card-h d-flex justify-content-between align-items-center">
                <div>
                    <span class="pv-badge">01</span>
                    <span class="pv-sub">Payment Voucher Header</span>
                </div>
                <div class="d-flex gap-3 align-items-stretch">
                    <div class="pv-compact-box">
                        <div class="cap">Company / Subsidiary</div>
                        <div class="val"><?= escape_html($selected_company_name) ?></div>
                    </div>
                    <div class="pv-auto-box">
                        <div class="cap">PV Reference</div>
                        <div class="val" id="pvRef">Auto Generated (<?= escape_html(post('voucher_number', $default_voucher_number)) ?>)</div>
                    </div>
                </div>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <label class="pv-label">Date</label>
                        <input type="date" class="pv-input" name="voucher_date" id="voucher_date" value="<?= escape_html(post('voucher_date', date('Y-m-d'))) ?>" required>
                    </div>
                    <div class="col-lg-3">
                        <label class="pv-label">Post Period *</label>
                        <input type="text" class="pv-input" id="post_period" value="<?= date('F Y', strtotime(post('voucher_date', date('Y-m-d')))) ?>" readonly>
                    </div>
                    <div class="col-lg-3">
                        <label class="pv-label">Payment Type *</label>
                        <select class="pv-select" name="payment_method" id="payment_method">
                            <?php $methods = ['Bank Transfer','Cheque','Cash','Online Payment']; ?>
                            <?php foreach ($methods as $m): ?>
                                <option value="<?= $m ?>" <?= post('payment_method', 'Bank Transfer') === $m ? 'selected' : '' ?>><?= $m ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="pv-card mb-4">
            <div class="pv-card-h">
                <span class="pv-badge">02</span>
                <span class="pv-sub">Payee Information</span>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="pv-label">Payee Name</label>
                        <div class="d-flex gap-2">
                            <select class="pv-select" name="supplier_id">
                                <option value="">Select Supplier / Payee</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>" <?= (int)post('supplier_id') === (int)$s['id'] ? 'selected' : '' ?>><?= escape_html($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <a href="<?= url('inventory/supplier/add_supplier.php') ?>" class="pv-btn text-center" style="background:var(--pv-cyan); color:#06131d; border-color:transparent; min-width:52px; text-decoration:none;">+</a>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <label class="pv-label">Contact Number</label>
                        <input type="text" class="pv-input" name="contact_number" value="<?= escape_html(post('contact_number')) ?>" placeholder="Phone number">
                    </div>
                    <div class="col-lg-3">
                        <label class="pv-label" id="paymentRefLabel"><?= escape_html($payment_ref_label) ?></label>
                        <input
                            type="text"
                            class="pv-input <?= isset($errors['payment_reference']) ? 'is-invalid' : '' ?>"
                            name="payment_reference"
                            id="payment_reference"
                            value="<?= escape_html($payment_ref_value) ?>"
                            placeholder="<?= escape_html($payment_ref_placeholder) ?>"
                            <?= $selected_payment_method === 'Cash' ? 'readonly' : '' ?>
                        >
                        <?php if (isset($errors['payment_reference'])): ?>
                            <div class="invalid-feedback d-block"><?= escape_html($errors['payment_reference']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="pv-label">Address</label>
                        <input type="text" class="pv-input" name="payee_address" value="<?= escape_html(post('payee_address')) ?>" placeholder="Payee address">
                    </div>
                </div>
            </div>
        </div>

        <div class="pv-card mb-4">
            <div class="pv-card-h">
                <span class="pv-badge">03</span>
                <span class="pv-sub">Narration & Attachments</span>
            </div>
            <div class="p-4">
                <label class="pv-label">Narration</label>
                <textarea class="pv-textarea" name="description" placeholder="e.g. Electricity expense for Feb 2026, paid in March 2026"><?= escape_html(post('description')) ?></textarea>
                <div class="mt-4">
                    <label class="pv-label">Attachment (Invoice Copy)</label>
                    <input type="file" id="invoice_attachment" name="invoice_attachment" accept=".pdf,.jpg,.jpeg,.png,.webp" class="d-none">
                    <label for="invoice_attachment" id="invoiceDropZone" class="pv-input text-center" style="padding:1.5rem; border-style:dashed; color:#6f7da8; cursor:pointer;">
                        <i class="fas fa-paperclip me-1"></i> Click to upload or drag & drop invoice copy here
                    </label>
                    <small id="invoiceFileName" class="d-block mt-2" style="color:#8f9bbf;">No file selected</small>
                </div>
            </div>
        </div>

        <div class="pv-card mb-4">
            <div class="pv-card-h">
                <span class="pv-badge">04</span>
                <span class="pv-sub">Account Allocation</span>
                <span class="pv-sub-note">- Select which ledger accounts this payment hits</span>
            </div>
            <div class="p-4">
                <div class="table-responsive">
                    <table class="table pv-table" id="allocTable">
                        <thead>
                            <tr>
                                <th style="width:20%">Account Code</th>
                                <th style="width:50%">Account Name</th>
                                <th style="width:20%" class="text-end">Amount</th>
                                <th style="width:10%" class="text-center">Del</th>
                            </tr>
                        </thead>
                        <tbody id="allocBody">
                            <tr class="alloc-row">
                                <td>
                                    <select class="pv-select alloc-code" style="padding:.45rem .7rem;">
                                        <option value="">Select Code...</option>
                                        <?php foreach ($accounts as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>"><?= escape_html($a['code']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="pv-select alloc-name" style="padding:.45rem .7rem;">
                                        <option value="">Select Name...</option>
                                        <?php foreach ($accounts as $a): ?>
                                            <option value="<?= (int)$a['id'] ?>"><?= escape_html($a['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="number" class="pv-input text-end alloc-amount" step="0.01" min="0" value="<?= escape_html((string)post('amount', '0.00')) ?>" style="padding:.45rem .7rem;"></td>
                                <td class="text-center"><button type="button" class="pv-del"><i class="fas fa-times"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="pv-total-row">
                                <td colspan="2" class="text-end">TOTAL</td>
                                <td class="text-end" id="allocTotal">$0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="pv-add-row mt-3" id="addAllocRow">+ Add Account Line</div>

                <div class="mt-3">
                    <label class="pv-label">Approval Type</label>
                    <div class="row g-3">
                        <div class="col-lg-3">
                            <select class="pv-select" name="approval_type" id="approval_type">
                                <option value="manager" <?= post('approval_type', 'manager') === 'manager' ? 'selected' : '' ?>>Manager</option>
                                <option value="admin" <?= post('approval_type') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="both" <?= post('approval_type') === 'both' ? 'selected' : '' ?>>Manager + Admin</option>
                            </select>
                        </div>
                        <div class="col-lg-4" id="manager_group">
                            <select class="pv-select" name="manager_id">
                                <option value="">Select Manager</option>
                                <?php foreach ($managers as $m): ?>
                                    <?php $manager_name = trim((string)($m['full_name'] ?? '')) ?: (string)$m['username']; ?>
                                    <option value="<?= (int)$m['id'] ?>" <?= (int)post('manager_id') === (int)$m['id'] ? 'selected' : '' ?>><?= escape_html($manager_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-lg-4" id="admin_group">
                            <select class="pv-select" name="admin_id">
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
        </div>

        <div class="pv-note mb-4"><i class="fas fa-lightbulb me-2"></i>If posting payments for purchasing stock within the organization, the system will display current stock on hand. If posting to project expense, it auto-picks in project expense reconciliation.</div>

        <div class="pv-actions no-print mb-4">
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="pv-btn pv-btn-save" name="form_action" value="save"><i class="fas fa-save me-2"></i>Save</button>
                <button type="button" class="pv-btn" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
                <button type="submit" class="pv-btn" name="form_action" value="clone"><i class="fas fa-copy me-2"></i>Clone</button>
                <button type="submit" class="pv-btn" name="form_action" value="reverse"><i class="fas fa-undo-alt me-2"></i>Reverse</button>
            </div>
            <button type="submit" class="pv-btn pv-btn-approve" name="form_action" value="approve"><i class="fas fa-check-square me-2"></i>Approve</button>
        </div>
    </form>
</div>

<script>
(function(){
    const accounts = <?= json_encode(array_values($accounts), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>;
    const allocBody = document.getElementById('allocBody');
    const allocTotal = document.getElementById('allocTotal');
    const amountInput = document.getElementById('amount');
    const voucherDate = document.getElementById('voucher_date');
    const postPeriod = document.getElementById('post_period');
    const paymentMethod = document.getElementById('payment_method');
    const paymentRefLabel = document.getElementById('paymentRefLabel');
    const paymentReference = document.getElementById('payment_reference');
    const attachmentInput = document.getElementById('invoice_attachment');
    const fileNameLabel = document.getElementById('invoiceFileName');
    const dropZone = document.getElementById('invoiceDropZone');

    function formatCurrency(n){ return '$' + (Number(n || 0)).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }

    function updateTotals(){
        let total = 0;
        document.querySelectorAll('.alloc-amount').forEach(el => { total += parseFloat(el.value || '0') || 0; });
        allocTotal.textContent = formatCurrency(total);
        amountInput.value = total.toFixed(2);
    }

    function syncAccountSelects(row){
        const code = row.querySelector('.alloc-code');
        const name = row.querySelector('.alloc-name');
        if (!code || !name) return;
        code.addEventListener('change', () => { name.value = code.value; });
        name.addEventListener('change', () => { code.value = name.value; });
    }

    function bindRow(row){
        syncAccountSelects(row);
        row.querySelector('.alloc-amount')?.addEventListener('input', updateTotals);
        row.querySelector('.pv-del')?.addEventListener('click', () => {
            const rows = document.querySelectorAll('.alloc-row');
            if (rows.length > 1) row.remove();
            updateTotals();
        });
    }

    function rowTemplate(){
        const codeOptions = ['<option value="">Select Code...</option>'].concat(accounts.map(a => `<option value="${a.id}">${a.code}</option>`)).join('');
        const nameOptions = ['<option value="">Select Name...</option>'].concat(accounts.map(a => `<option value="${a.id}">${a.name}</option>`)).join('');
        return `<tr class="alloc-row">
            <td><select class="pv-select alloc-code" style="padding:.45rem .7rem;">${codeOptions}</select></td>
            <td><select class="pv-select alloc-name" style="padding:.45rem .7rem;">${nameOptions}</select></td>
            <td><input type="number" class="pv-input text-end alloc-amount" step="0.01" min="0" value="0.00" style="padding:.45rem .7rem;"></td>
            <td class="text-center"><button type="button" class="pv-del"><i class="fas fa-times"></i></button></td>
        </tr>`;
    }

    document.querySelectorAll('.alloc-row').forEach(bindRow);
    document.getElementById('addAllocRow')?.addEventListener('click', () => {
        allocBody.insertAdjacentHTML('beforeend', rowTemplate());
        bindRow(allocBody.lastElementChild);
    });

    voucherDate?.addEventListener('change', () => {
        if (!voucherDate.value) return;
        const d = new Date(voucherDate.value + 'T00:00:00');
        postPeriod.value = d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    });

    function toggleApprovalColumns(){
        const type = document.getElementById('approval_type')?.value || 'manager';
        const mg = document.getElementById('manager_group');
        const ag = document.getElementById('admin_group');
        if (!mg || !ag) return;
        mg.style.display = (type === 'manager' || type === 'both') ? '' : 'none';
        ag.style.display = (type === 'admin' || type === 'both') ? '' : 'none';
    }
    document.getElementById('approval_type')?.addEventListener('change', toggleApprovalColumns);

    function updatePaymentReferenceField() {
        if (!paymentMethod || !paymentRefLabel || !paymentReference) return;
        const method = paymentMethod.value || 'Bank Transfer';
        let label = 'Cheque Number';
        let placeholder = 'Enter cheque number';
        let readonly = false;
        let value = paymentReference.value || '';

        if (method === 'Bank Transfer') {
            label = 'Bank Transfer Reference Number';
            placeholder = 'Enter bank transfer reference';
        } else if (method === 'Cheque') {
            label = 'Cheque Number';
            placeholder = 'Enter cheque number';
        } else if (method === 'Online Payment') {
            label = 'Online Payment Reference Number';
            placeholder = 'Enter online payment reference';
        } else if (method === 'Cash') {
            label = 'Tonga Currency';
            placeholder = 'TOP';
            readonly = true;
            value = 'TOP';
        }

        paymentRefLabel.textContent = label;
        paymentReference.placeholder = placeholder;
        paymentReference.readOnly = readonly;
        if (readonly) {
            paymentReference.value = value;
        } else if (paymentReference.value === 'TOP') {
            paymentReference.value = '';
        }
    }
    paymentMethod?.addEventListener('change', updatePaymentReferenceField);

    attachmentInput?.addEventListener('change', () => {
        const f = attachmentInput.files && attachmentInput.files.length ? attachmentInput.files[0].name : 'No file selected';
        if (fileNameLabel) fileNameLabel.textContent = f;
    });

    ['dragenter', 'dragover'].forEach(evt => {
        dropZone?.addEventListener(evt, (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'rgba(16,197,223,.85)';
        });
    });
    ['dragleave', 'drop'].forEach(evt => {
        dropZone?.addEventListener(evt, (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#344271';
        });
    });
    dropZone?.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        if (!dt || !attachmentInput) return;
        attachmentInput.files = dt.files;
        const f = dt.files && dt.files.length ? dt.files[0].name : 'No file selected';
        if (fileNameLabel) fileNameLabel.textContent = f;
    });

    updateTotals();
    toggleApprovalColumns();
    updatePaymentReferenceField();
})();
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
