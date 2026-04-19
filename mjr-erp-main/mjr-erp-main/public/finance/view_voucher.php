<?php
/**
 * View Payment Voucher
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');
ensure_finance_approval_columns('payment_vouchers');

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    set_flash('Invalid voucher ID.', 'error');
    redirect('payment_vouchers.php');
}

$voucher = db_fetch("\n    SELECT pv.*, \n           b.bank_name, b.account_name, b.currency,\n           s.name as supplier_name,\n           c.name as company_name,\n           m.username as manager_username, m.full_name as manager_full_name,\n           a.username as admin_username, a.full_name as admin_full_name\n    FROM payment_vouchers pv\n    LEFT JOIN bank_accounts b ON pv.bank_account_id = b.id\n    LEFT JOIN suppliers s ON pv.supplier_id = s.id\n    LEFT JOIN companies c ON pv.company_id = c.id\n    LEFT JOIN users m ON pv.manager_id = m.id\n    LEFT JOIN users a ON pv.admin_id = a.id\n    WHERE pv.id = ?\n", [$id]);

if (!$voucher) {
    set_flash('Voucher not found.', 'error');
    redirect('payment_vouchers.php');
}

$page_title = 'Voucher ' . $voucher['voucher_number'];
$can_approve = (($voucher['status'] ?? '') === 'draft');

$next_voucher_number = static function () {
    $last = db_fetch("SELECT voucher_number FROM payment_vouchers ORDER BY id DESC LIMIT 1");
    $next_num = 1;
    if ($last && !empty($last['voucher_number'])) {
        preg_match('/(\d+)$/', (string)$last['voucher_number'], $m);
        $next_num = (int)($m[1] ?? 0) + 1;
    }
    return 'PV-' . str_pad((string)$next_num, 4, '0', STR_PAD_LEFT);
};

// Handle actions
if (is_post() && verify_csrf_token(post('csrf_token'))) {
    $action = post('action');

    if ($action === 'update_status') {
        $new_status = post('new_status');
        $allowed = ['draft', 'approved', 'posted', 'cancelled'];
        if (in_array($new_status, $allowed, true)) {
            if ($new_status === 'approved') {
                if (($voucher['status'] ?? '') !== 'draft') {
                    set_flash('Only draft vouchers can be approved.', 'error');
                    redirect('view_voucher.php?id=' . $id);
                }
                $approval = finance_process_approval_action($voucher, current_user_id());
                if (!$approval['ok']) {
                    set_flash($approval['message'], 'error');
                    redirect('view_voucher.php?id=' . $id);
                }

                $set_parts = [];
                $params = [];
                foreach ($approval['fields'] as $field => $value) {
                    $set_parts[] = "{$field} = ?";
                    $params[] = $value;
                }
                if ($approval['approved']) {
                    $set_parts[] = "status = 'approved'";
                }
                $params[] = $id;

                if (!empty($set_parts)) {
                    db_query("UPDATE payment_vouchers SET " . implode(', ', $set_parts) . " WHERE id = ?", $params);
                }
                set_flash($approval['message'], 'success');
            } elseif ($new_status === 'posted') {
                if (($voucher['status'] ?? '') !== 'approved') {
                    set_flash('Voucher must be fully approved before posting.', 'error');
                    redirect('view_voucher.php?id=' . $id);
                }
                db_query("UPDATE payment_vouchers SET status = 'posted' WHERE id = ?", [$id]);
                set_flash('Voucher posted successfully.', 'success');
            } else {
                db_query("UPDATE payment_vouchers SET status = ? WHERE id = ?", [$new_status, $id]);
                set_flash('Voucher status updated to ' . strtoupper($new_status) . '.', 'success');
            }
        }
        redirect('view_voucher.php?id=' . $id);
    }

    if ($action === 'save_stub') {
        $cheque_number = trim((string)post('cheque_number', ''));
        if (($voucher['status'] ?? '') !== 'approved') {
            set_flash('Cheque number can be saved only when voucher is approved.', 'error');
            redirect('view_voucher.php?id=' . $id);
        }
        if ($cheque_number === '') {
            set_flash('Please enter cheque number before saving.', 'error');
            redirect('view_voucher.php?id=' . $id);
        }
        db_query("UPDATE payment_vouchers SET cheque_number = ? WHERE id = ?", [$cheque_number, $id]);
        set_flash('Cheque number saved successfully.', 'success');
        redirect('view_voucher.php?id=' . $id);
    }

    if ($action === 'clone_voucher') {
        $new_no = $next_voucher_number();
        $new_id = db_insert(
            "INSERT INTO payment_vouchers (company_id, voucher_number, voucher_date, supplier_id, bank_account_id, amount, payment_method, reference, description, status, approval_type, manager_id, admin_id, created_by, created_at)\n             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW())",
            [
                $voucher['company_id'] ?: 1,
                $new_no,
                date('Y-m-d'),
                $voucher['supplier_id'] ?: null,
                $voucher['bank_account_id'] ?: null,
                (float)$voucher['amount'],
                $voucher['payment_method'],
                $new_no,
                trim('CLONE of ' . $voucher['voucher_number'] . ': ' . (string)($voucher['description'] ?? '')),
                $voucher['approval_type'] ?? 'manager',
                $voucher['manager_id'] ?: null,
                $voucher['admin_id'] ?: null,
                current_user_id()
            ]
        );
        set_flash('Voucher cloned successfully.', 'success');
        redirect('view_voucher.php?id=' . (int)$new_id);
    }

    if ($action === 'reverse_voucher') {
        $new_no = $next_voucher_number();
        $new_id = db_insert(
            "INSERT INTO payment_vouchers (company_id, voucher_number, voucher_date, supplier_id, bank_account_id, amount, payment_method, reference, description, status, approval_type, manager_id, admin_id, created_by, created_at)\n             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, NOW())",
            [
                $voucher['company_id'] ?: 1,
                $new_no,
                date('Y-m-d'),
                $voucher['supplier_id'] ?: null,
                $voucher['bank_account_id'] ?: null,
                (float)$voucher['amount'],
                $voucher['payment_method'],
                $new_no,
                trim('REVERSAL of ' . $voucher['voucher_number'] . ': ' . (string)($voucher['description'] ?? '')),
                $voucher['approval_type'] ?? 'manager',
                $voucher['manager_id'] ?: null,
                $voucher['admin_id'] ?: null,
                current_user_id()
            ]
        );
        set_flash('Reversal voucher created.', 'success');
        redirect('view_voucher.php?id=' . (int)$new_id);
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
    --pv-hero-text: #10c5df;
}

[data-bs-theme="light"] {
    --pv-bg: #f8f9fa;
    --pv-panel: #ffffff;
    --pv-panel-2: #f8f9fa;
    --pv-border: #dee2e6;
    --pv-cyan: #0dcaf0;
    --pv-green: #3cc553;
    --pv-warn: #ffc107;
    --pv-soft: #6c757d;
    --pv-input-bg: #ffffff;
    --pv-input-border: #ced4da;
    --pv-input-text: #212529;
    --pv-label-text: #495057;
    --pv-table-text: #212529;
    --pv-table-head-bg: #f8f9fa;
    --pv-hero-text: #055160;
}

body { background: var(--pv-bg); color: var(--pv-soft); }
.pv-hero { border: 1px solid rgba(16,197,223,.55); border-radius: 12px; background: linear-gradient(90deg, rgba(16,197,223,.16), rgba(16,197,223,.06)); color: var(--pv-hero-text); font-weight: 700; letter-spacing: .4px; }
.pv-back { border: 1px solid var(--pv-border); border-radius: 12px; color: var(--pv-soft); text-decoration: none; padding: .72rem 1.15rem; font-weight: 700; background: var(--pv-panel); }
.pv-card { border-radius: 14px; border: 1px solid var(--pv-border); background: linear-gradient(180deg, var(--pv-panel), var(--pv-panel-2)); overflow: hidden; }
.pv-card-h { border-bottom: 1px solid var(--pv-border); padding: 1rem 1.5rem; }
.pv-badge { background: var(--pv-cyan); color: #000; border-radius: 10px; font-weight: 800; padding: .35rem .6rem; min-width: 36px; display: inline-block; text-align: center; }
.pv-sub { font-size: 2rem; font-weight: 700; color: var(--pv-input-text); margin-left: .8rem; vertical-align: middle; }
.pv-sub-note { color: var(--pv-label-text); font-size: 1.7rem; margin-left: .7rem; }
.pv-label { color: var(--pv-label-text); font-size: .9rem; margin-bottom: .35rem; display: block; text-transform: uppercase; font-weight: 700; letter-spacing: .6px; }
.pv-input, .pv-select, .pv-textarea {
    width: 100%; background: var(--pv-input-bg) !important; border: 1px solid var(--pv-input-border) !important; color: var(--pv-input-text) !important;
    border-radius: 10px; padding: .62rem .85rem;
}
.pv-input[readonly], .pv-input:disabled { opacity: .95; }
.pv-textarea { min-height: 100px; }
.pv-auto-box { border: 1px solid rgba(16,197,223,.55); border-radius: 12px; padding: .55rem .85rem; text-align: center; min-width: 340px; }
.pv-auto-box .cap { color: var(--pv-label-text); font-size: 1.15rem; line-height: 1.2; }
.pv-auto-box .val { color: var(--pv-cyan); font-weight: 800; font-size: 2rem; line-height: 1.2; }
.pv-compact-box { border: 1px solid rgba(16,197,223,.45); border-radius: 12px; padding: .55rem .85rem; min-width: 300px; background: rgba(16,197,223,.05); }
.pv-compact-box .cap { color: var(--pv-label-text); font-size: .9rem; text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }
.pv-compact-box .val { color: var(--pv-input-text); font-weight: 700; font-size: 1.05rem; }
.pv-table { --bs-table-bg: transparent; --bs-table-border-color: var(--pv-border); margin-bottom: 0; }
.pv-table th { color: var(--pv-label-text); font-size: .86rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; border-bottom: 1px solid var(--pv-border); padding: 1rem; background: var(--pv-table-head-bg); }
.pv-table td { color: var(--pv-table-text); border-bottom: 1px solid var(--pv-border); padding: .7rem 1rem; vertical-align: middle; }
.pv-total-row td { background: rgba(16,197,223,.05); font-weight: 800; }
.pv-note { background: rgba(255,179,47,.14); border: 1px solid rgba(255,179,47,.25); color: var(--pv-warn); border-radius: 8px; padding: .75rem 1rem; }
.pv-actions { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }
.pv-btn { border: 1px solid var(--pv-input-border); border-radius: 12px; background: var(--pv-panel); color: var(--pv-soft); padding: .66rem 1.2rem; font-weight: 700; }
.pv-btn-save { background: var(--pv-cyan); color: #000; border-color: transparent; }
.pv-btn-approve { background: #49ba57; color: #fff; border-color: transparent; }
.pv-title { color: var(--pv-input-text) !important; }
@media print {
    body { background: #fff; color: #111; }
    .no-print { display: none !important; }
    .container-fluid { padding: 0 !important; }
}
</style>

<div class="container-fluid px-4 py-4">
    <div class="pv-hero px-4 py-3 mb-4"><i class="fas fa-circle me-2" style="font-size:.65rem"></i> SCREEN: Payment Voucher - View / Entry Form</div>

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1 pv-title">+ Payment Voucher</h1>
            <p class="mb-0">Review and process outgoing payments to suppliers and creditors.</p>
        </div>
        <a href="payment_vouchers.php" class="pv-back no-print">&larr; Back to Payment Vouchers</a>
    </div>

    <div class="pv-card mb-4">
        <div class="pv-card-h d-flex justify-content-between align-items-center">
            <div>
                <span class="pv-badge">01</span>
                <span class="pv-sub">Payment Voucher Header</span>
            </div>
            <div class="d-flex gap-3 align-items-stretch">
                <div class="pv-compact-box">
                    <div class="cap">Company / Subsidiary</div>
                    <div class="val"><?= escape_html((string)($voucher['company_name'] ?? 'N/A')) ?></div>
                </div>
                <div class="pv-auto-box">
                    <div class="cap">PV Reference</div>
                    <div class="val"><?= escape_html((string)$voucher['voucher_number']) ?></div>
                </div>
            </div>
        </div>
        <div class="p-4">
            <div class="row g-3">
                <div class="col-lg-3">
                    <label class="pv-label">Date</label>
                    <input type="text" class="pv-input" value="<?= escape_html(format_date($voucher['voucher_date'])) ?>" readonly>
                </div>
                <div class="col-lg-3">
                    <label class="pv-label">Post Period</label>
                    <input type="text" class="pv-input" value="<?= escape_html(date('F Y', strtotime((string)$voucher['voucher_date']))) ?>" readonly>
                </div>
                <div class="col-lg-3">
                    <label class="pv-label">Status</label>
                    <input type="text" class="pv-input" value="<?= escape_html(strtoupper((string)($voucher['status'] ?? 'draft'))) ?>" readonly>
                </div>
                <div class="col-lg-3">
                    <label class="pv-label">Payment Type</label>
                    <input type="text" class="pv-input" value="<?= escape_html((string)$voucher['payment_method']) ?>" readonly>
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
                    <input type="text" class="pv-input" value="<?= $voucher['supplier_name'] ? escape_html($voucher['supplier_name']) : 'N/A' ?>" readonly>
                </div>
                <div class="col-lg-3">
                    <label class="pv-label">Contact Number</label>
                    <input type="text" class="pv-input" value="<?= !empty($voucher['manager_username']) ? escape_html((string)$voucher['manager_username']) : '-' ?>" readonly>
                </div>
                <div class="col-lg-3">
                    <label class="pv-label">Cheque Number</label>
                    <input type="text" class="pv-input" name="cheque_number" form="save_form" value="<?= escape_html((string)($voucher['cheque_number'] ?? '')) ?>" placeholder="Enter after approval" <?= (($voucher['status'] ?? '') === 'approved') ? '' : 'disabled' ?>>
                </div>
                <div class="col-12">
                    <label class="pv-label">Address</label>
                    <input type="text" class="pv-input" value="<?= $voucher['bank_name'] ? escape_html($voucher['bank_name'] . ' - ' . $voucher['account_name']) : 'Cash / Unspecified' ?>" readonly>
                </div>
                <div class="col-12">
                    <small>Cheque number can be entered only after voucher approval.</small>
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
            <textarea class="pv-textarea" readonly><?= $voucher['description'] ? escape_html((string)$voucher['description']) : 'No narration provided' ?></textarea>
            <div class="mt-4">
                <label class="pv-label">Attachment (Invoice Copy)</label>
                <div class="pv-input text-center" style="padding:1.5rem; border-style:dashed; color:#6f7da8;">
                    <?php if (!empty($voucher['invoice_attachment'])): ?>
                        <a href="<?= escape_html(url($voucher['invoice_attachment'])) ?>" target="_blank" rel="noopener" style="color:#cfe8ff; text-decoration:none;">
                            <i class="fas fa-paperclip me-1"></i> Open uploaded invoice copy
                        </a>
                    <?php else: ?>
                        <i class="fas fa-paperclip me-1"></i> No invoice copy uploaded
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="pv-card mb-4">
        <div class="pv-card-h">
            <span class="pv-badge">04</span>
            <span class="pv-sub">Account Allocation</span>
            <span class="pv-sub-note">- Selected ledger account for this payment</span>
        </div>
        <div class="p-4">
            <div class="table-responsive">
                <table class="table pv-table">
                    <thead>
                        <tr>
                            <th style="width:20%">Account Code</th>
                            <th style="width:50%">Account Name</th>
                            <th style="width:20%" class="text-end">Amount</th>
                            <th style="width:10%" class="text-center">Del</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>PV<?= (int)$voucher['id'] ?></td>
                            <td><?= $voucher['supplier_name'] ? escape_html($voucher['supplier_name']) : 'Payment Voucher Entry' ?></td>
                            <td class="text-end"><?= number_format((float)$voucher['amount'], 2) ?></td>
                            <td class="text-center"><i class="fas fa-times text-danger"></i></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="pv-total-row">
                            <td colspan="2" class="text-end">TOTAL</td>
                            <td class="text-end">$<?= number_format((float)$voucher['amount'], 2) ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="pv-note mb-4"><i class="fas fa-lightbulb me-2"></i>If posting payments for purchasing stock within the organization, the system will display current stock on hand. If posting to project expense, it auto-picks in project expense reconciliation.</div>

    <div class="pv-actions no-print mb-4">
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="d-inline" id="save_form">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="save_stub">
                <button type="submit" class="pv-btn pv-btn-save"><i class="fas fa-save me-2"></i>Save</button>
            </form>
            <button type="button" class="pv-btn" onclick="window.print()"><i class="fas fa-print me-2"></i>Print</button>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="clone_voucher">
                <button type="submit" class="pv-btn"><i class="fas fa-copy me-2"></i>Clone</button>
            </form>
            <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="reverse_voucher">
                <button type="submit" class="pv-btn"><i class="fas fa-undo-alt me-2"></i>Reverse</button>
            </form>
        </div>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="new_status" value="approved">
            <button type="submit" class="pv-btn pv-btn-approve" <?= $can_approve ? '' : 'disabled title="Only draft vouchers can be approved"' ?>><i class="fas fa-check-square me-2"></i>Approve</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
