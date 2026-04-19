<?php
/**
 * Calculate & Post Depreciation
 * Automates monthly or yearly depreciation journal entries
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_permission('manage_finance');

$page_title = 'Calculate Depreciation - MJR Group ERP';

// Assets that are active and not fully depreciated
$assets_to_depreciate = db_fetch_all("
    SELECT * FROM fixed_assets 
    WHERE status = 'active' AND net_book_value > salvage_value
");

// Handle form submission (Automatic Posting)
if (is_post()) {
    $csrf_token = post('csrf_token');
    
    if (verify_csrf_token($csrf_token)) {
        $depr_date = post('depreciation_date', '');
        
        $errors = [];
        if (empty($depr_date)) $errors['depreciation_date'] = 'Please fill Depreciation Date that field';
        
        if (empty($errors)) {
            db_begin_transaction();
            try {
            
            // Generate a journal entry for all depreciations
            $entry_num = 'DEP-' . date('Ymd-His');
            $jou_id = db_insert("
                INSERT INTO journal_entries (entry_number, entry_date, description, status, created_by, created_at)
                VALUES (?, ?, 'Automated Monthly Depreciation Posting', 'draft', ?, NOW())
            ", [$entry_num, $depr_date, current_user_id()]);

            $total_depr = 0;
            $line_num = 1;

            foreach ($assets_to_depreciate as $asset) {
                // Calculation: (Purchase Price - Salvage Value) / Useful Life / 12 months
                $annual_depr = ($asset['purchase_price'] - $asset['salvage_value']) / $asset['useful_life_years'];
                $monthly_depr = round($annual_depr / 12, 2);

                if ($monthly_depr > ($asset['net_book_value'] - $asset['salvage_value'])) {
                    $monthly_depr = $asset['net_book_value'] - $asset['salvage_value'];
                }

                if ($monthly_depr <= 0) continue;

                // 1. Record in depreciation_entries
                db_insert("
                    INSERT INTO depreciation_entries (asset_id, depreciation_date, depreciation_amount, journal_entry_id)
                    VALUES (?, ?, ?, ?)
                ", [$asset['id'], $depr_date, $monthly_depr, $jou_id]);

                // 2. Update asset values
                db_query("
                    UPDATE fixed_assets 
                    SET accumulated_depreciation = accumulated_depreciation + ?,
                        net_book_value = net_book_value - ?
                    WHERE id = ?
                ", [$monthly_depr, $monthly_depr, $asset['id']]);

                // 3. Add lines to the journal entry (Expense)
                db_insert("
                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit, line_number, company_id)
                    VALUES (?, (SELECT id FROM accounts WHERE code='6500' LIMIT 1), ?, ?, 0, ?, ?)
                ", [$jou_id, "Depreciation for " . $asset['asset_name'], $monthly_depr, $line_num++, $asset['company_id']]);

                // 4. Add lines to the journal entry (Accumulated Depreciation - Asset Contra)
                db_insert("
                    INSERT INTO journal_entry_lines (journal_entry_id, account_id, description, debit, credit, line_number, company_id)
                    VALUES (?, (SELECT id FROM accounts WHERE code='1510' LIMIT 1), ?, 0, ?, ?, ?)
                ", [$jou_id, "Accumulated Depr for " . $asset['asset_name'], $monthly_depr, $line_num++, $asset['company_id']]);

                $total_depr += $monthly_depr;
            }

            if ($total_depr <= 0) {
                throw new Exception('No depreciation to post.');
            }

            db_commit();
            set_flash("Posted depreciation for assets totaling ". format_currency($total_depr), 'success');
            redirect('fixed_assets.php');

            } catch (Exception $e) {
                db_rollback();
                log_error("Depreciation Posting Error: " . $e->getMessage());
                set_flash($e->getMessage(), 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
        }
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-10 mx-auto">
            <div class="card bg-dark text-white border-0 shadow">
                <div class="card-header bg-success">
                    <h3 class="mb-0"><i class="fas fa-calculator me-2"></i>Post Monthly Depreciation</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Depreciation Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control <?= isset($errors['depreciation_date']) ? 'is-invalid' : '' ?>" name="depreciation_date" value="<?= post('depreciation_date', date('Y-m-t')) ?>" required>
                                <?php if (isset($errors['depreciation_date'])): ?>
                                    <div class="invalid-feedback"><?= $errors['depreciation_date'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Usually the last day of the month.</small>
                            </div>
                        </div>

                        <div class="table-responsive mt-3 mb-5">
                            <table class="table table-dark table-striped border-0">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Purchase Price</th>
                                        <th>Useful Life</th>
                                        <th class="text-end">Approx. Monthly Depr.</th>
                                        <th class="text-end">Current Book Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets_to_depreciate as $asset): 
                                        $annual_depr = ($asset['purchase_price'] - $asset['salvage_value']) / $asset['useful_life_years'];
                                        $monthly_depr = round($annual_depr / 12, 2);
                                        ?>
                                    <tr>
                                        <td><strong><?= escape_html($asset['asset_name']) ?></strong></td>
                                        <td><?= format_currency($asset['purchase_price']) ?></td>
                                        <td><?= $asset['useful_life_years'] ?> Years</td>
                                        <td class="text-end text-danger"><?= format_currency($monthly_depr) ?></td>
                                        <td class="text-end"><?= format_currency($asset['net_book_value']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg px-5 shadow w-100 py-3" onclick="return confirm('Register and post monthly depreciation for all listed assets?')">
                            <i class="fas fa-play me-2"></i>PROCESS & POST DEPRECIATION
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
