<?php
/**
 * Fiscal Year Management
 * Manage fiscal years and lock/unlock accounting periods
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Fiscal Years - MJR Group ERP';

// Auto-create table if not exists
try {
    db_query("
        CREATE TABLE IF NOT EXISTS fiscal_years (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            year_name   VARCHAR(50) NOT NULL,
            start_date  DATE NOT NULL,
            end_date    DATE NOT NULL,
            status      ENUM('open','closed') NOT NULL DEFAULT 'open',
            created_by  INT,
            created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) { /* exists */ }

// Handle Add Fiscal Year
if (is_post() && post('action') === 'add') {
    if (verify_csrf_token(post('csrf_token'))) {
        $year_name = trim(post('year_name', ''));
        $start_date = post('start_date', '');
        $end_date = post('end_date', '');
        
        $errors = [];
        if (empty($year_name))  $errors['year_name']  = 'Please fill Fiscal Year Name that field';
        if (empty($start_date)) $errors['start_date'] = 'Please fill Start Date that field';
        if (empty($end_date))   $errors['end_date']   = 'Please fill End Date that field';
        
        if (empty($errors)) {
            try {
                db_insert("INSERT INTO fiscal_years (year_name, start_date, end_date, status, created_by) VALUES (?,?,?,'open',?)",
                    [$year_name, $start_date, $end_date, current_user_id()]);
                set_flash('Fiscal year created!', 'success');
                redirect('fiscal_years.php');
            } catch (Exception $e) {
                set_flash('Error: ' . $e->getMessage(), 'error');
            }
        } else {
            set_flash('Please fix the validation errors.', 'error');
            // Store errors in session or use a global variable to trigger modal on reload
            // For simplicity in this project's pattern, we'll try to keep them as local variables if possible, 
            // but modals usually need session-based persistence or conditional script firing.
            // Given the pattern so far, we will use local $errors and the user will see feedback if they re-open or if we trigger it.
        }
    }
}

// Handle Close/Open toggle
if (is_post() && post('action') === 'toggle_status') {
    if (verify_csrf_token(post('csrf_token'))) {
        $fy_id  = intval(post('fy_id'));
        $status = post('new_status');
        if (in_array($status, ['open','closed'])) {
            db_query("UPDATE fiscal_years SET status=?, updated_at=NOW() WHERE id=?", [$status, $fy_id]);
            set_flash("Fiscal year " . ($status === 'closed' ? 'CLOSED – no more entries allowed' : 'OPENED – entries allowed again'), $status === 'closed' ? 'warning' : 'success');
        }
        redirect('fiscal_years.php');
    }
}

$fiscal_years = db_fetch_all("SELECT * FROM fiscal_years ORDER BY start_date DESC");

// Check if current GL posting date falls in a closed period (for info banner)
$closed_current = false;
foreach ($fiscal_years as $fy) {
    if ($fy['status'] === 'closed' && date('Y-m-d') >= $fy['start_date'] && date('Y-m-d') <= $fy['end_date']) {
        $closed_current = true;
        break;
    }
}

include __DIR__ . '/../../templates/header.php';
?>

<style>
    body { background-color: #1a1a24; color: #b0b0c0; }
    .card { background-color: #222230; border-color: rgba(255,255,255,0.05); border-radius: 10px; }
    
    .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,0.02); --bs-table-border-color: rgba(255,255,255,0.05); }
    .table-dark th { color: #8e8e9e; font-weight: 600; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 1px solid #333344; padding: 1.25rem 1rem; }
    .table-dark td { padding: 1rem; border-bottom: 1px solid rgba(255,255,255,0.05); color: #fff; vertical-align: middle; }
    
    .btn-create { background-color: #0dcaf0; color: #000; font-weight: 600; transition: all 0.3s ease; }
    .btn-create:hover { background-color: #0baccc; color: #000; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(13, 202, 240, 0.3); }

    .btn-action { background-color: rgba(255,255,255,0.02); color: #8e8e9e; border: 1px solid rgba(255,255,255,0.05); transition: all 0.2s ease; }
    .btn-action.text-danger:hover { background-color: rgba(255, 82, 82, 0.1); border-color: rgba(255, 82, 82, 0.3); color: #ff5252!important; }
    .btn-action.text-success:hover { background-color: rgba(60, 197, 83, 0.1); border-color: rgba(60, 197, 83, 0.3); color: #3cc553!important; }

    .form-control, .form-select { background-color: #1a1a24!important; border-color: rgba(255,255,255,0.1)!important; color: #fff!important; }
    .form-control:focus, .form-select:focus { border-color: #0dcaf0!important; box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25)!important; }
    
    /* Modal styling */
    .modal-content { background-color: #222230; border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; }
    .modal-header { border-bottom: 1px solid rgba(255,255,255,0.05); }
    .modal-footer { border-top: 1px solid rgba(255,255,255,0.05); }
    .modal-title { color: #fff; font-weight: 600; }
    .btn-close { filter: invert(1) grayscale(100%) brightness(200%); opacity: 0.5; }
</style>

<div class="container-fluid px-4 py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 text-white">
        <div>
            <h1 class="h2 fw-bold mb-1"><i class="fas fa-calendar-alt me-2 text-primary"></i>Fiscal Years</h1>
            <p class="text-muted mb-0">Define accounting periods and lock closed years</p>
        </div>
        <div>
            <button class="btn btn-primary px-4 py-2 rounded-pill shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addFYModal">
                <i class="fas fa-plus me-2"></i>Add Fiscal Year
            </button>
        </div>
    </div>

    <?php if ($closed_current): ?>
    <div class="alert mb-5 border-0 d-flex align-items-center" style="background: rgba(255, 82, 82, 0.1); color: #ff5252; border-left: 4px solid #ff5252!important;">
        <i class="fas fa-lock fa-2x me-3"></i>
        <div>
            <h6 class="mb-1 fw-bold text-white">Current period is CLOSED.</h6>
            <span style="font-size: 0.9rem;">Journal entries dated within a closed fiscal year will be blocked.</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Periods List -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="mb-0 fw-bold"><i class="fas fa-list me-2"></i>Accounting Periods</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($fiscal_years)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-3x text-muted mb-3 opacity-25"></i>
                <p class="text-muted">No fiscal years defined yet. Click "Add Fiscal Year" to get started.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Period Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th class="text-end">Duration</th>
                            <th>Status</th>
                            <th class="text-end pe-4 no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fiscal_years as $fy): ?>
                        <tr>
                            <td class="ps-4 text-white"><strong><?= escape_html($fy['year_name']) ?></strong></td>
                            <td style="color: #b0b0c0;"><?= format_date($fy['start_date']) ?></td>
                            <td style="color: #b0b0c0;"><?= format_date($fy['end_date']) ?></td>
                            <td class="text-end font-monospace" style="color: #b0b0c0;"><?= (new DateTime($fy['start_date']))->diff(new DateTime($fy['end_date']))->days + 1 ?> days</td>
                             <td>
                                <?php if ($fy['status'] === 'open'): ?>
                                    <span class="badge bg-success-soft text-success"><i class="fas fa-unlock me-1"></i>Open</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-soft text-danger"><i class="fas fa-lock me-1"></i>Closed</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 no-print">
                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('<?= $fy['status']==='open' ? 'CLOSE this fiscal year? No new entries will be allowed for this period.' : 'Re-OPEN this fiscal year?' ?>')">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                <input type="hidden" name="fy_id" value="<?= $fy['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $fy['status']==='open'?'closed':'open' ?>">
                                <button type="submit" class="btn btn-action btn-sm rounded-pill px-3 <?= $fy['status']==='open'?'text-danger':'text-success' ?>">
                                    <i class="fas fa-<?= $fy['status']==='open'?'lock':'unlock' ?> me-1"></i>
                                    <?= $fy['status']==='open' ? 'Close Period' : 'Re-open Period' ?>
                                </button>
                            </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Information Card -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 bg-dark-soft">
            <h6 class="text-white mb-3 fw-bold"><i class="fas fa-info-circle me-2 text-info"></i>Period Lock Rules</h6>
            <div class="row">
                <div class="col-md-6">
                    <ul class="text-muted mb-0 small">
                        <li class="mb-2">When a period is <span class="text-danger fw-bold">Closed</span>, no new entries can be posted within that date range.</li>
                        <li class="mb-2">Existing posted entries remain safe and are not affected by period locks.</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="text-muted mb-0 small">
                        <li class="mb-2">You can re-open any period to process late adjustments or corrections.</li>
                        <li>Closing fiscal years ensures reporting consistency and prevents accidental data modification.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Fiscal Year Modal -->
<div class="modal fade" id="addFYModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="fas fa-calendar-plus me-2 text-primary"></i>New Fiscal Year</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="POST" id="fyForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <div class="mb-4">
                        <label class="form-label text-muted">Fiscal Year Name <span class="text-danger">*</span></label>
                        <input type="text" name="year_name" class="form-control <?= isset($errors['year_name']) ? 'is-invalid' : '' ?>" 
                               placeholder="e.g. FY 2025-26" value="<?= escape_html(post('year_name')) ?>" required>
                        <?php if (isset($errors['year_name'])): ?>
                            <div class="invalid-feedback"><?= $errors['year_name'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>" 
                                   value="<?= post('start_date') ?>" required>
                            <?php if (isset($errors['start_date'])): ?>
                                <div class="invalid-feedback"><?= $errors['start_date'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control <?= isset($errors['end_date']) ? 'is-invalid' : '' ?>" 
                                   value="<?= post('end_date') ?>" required>
                            <?php if (isset($errors['end_date'])): ?>
                                <div class="invalid-feedback"><?= $errors['end_date'] ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light border-0 py-3">
                <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold" onclick="document.getElementById('fyForm').submit()">
                    <i class="fas fa-save me-1"></i>Create Year
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    <?php if (!empty($errors)): ?>
    var addFYModal = new bootstrap.Modal(document.getElementById('addFYModal'));
    addFYModal.show();
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
