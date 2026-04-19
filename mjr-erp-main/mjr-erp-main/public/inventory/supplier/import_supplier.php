<?php
/**
 * Import Supplier
 * Allows subsidiaries to import (copy) existing suppliers from other group companies.
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company first.', 'warning');
    redirect(url('index.php'));
}

if (is_hq()) {
    set_flash('Import feature is only for subsidiary companies.', 'error');
    redirect('suppliers.php');
}

$page_title = 'Import Supplier - MJR Group ERP';

// Handle Import Action
if (is_post() && isset($_POST['import_id'])) {
    if (verify_csrf_token(post('csrf_token'))) {
        try {
            $import_id = (int) post('import_id');
            $source = db_fetch("SELECT * FROM suppliers WHERE id = ?", [$import_id]);
            
            if (!$source) {
                throw new Exception('Source supplier not found.');
            }

            // Check if already exists in current company by code
            $existing = db_fetch("SELECT id FROM suppliers WHERE supplier_code = ? AND company_id = ?", [$source['supplier_code'], $company_id]);
            if ($existing) {
                throw new Exception('This supplier already exists in your company list.');
            }

            // Prepare copy (excluding ID and creating new company link)
            unset($source['id']);
            $source['company_id'] = $company_id;
            $source['created_at'] = date('Y-m-d H:i:s');
            $source['is_active'] = 1; // Default to active in new company

            $keys = array_keys($source);
            $fields = implode(', ', $keys);
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));
            $params = array_values($source);

            db_query("INSERT INTO suppliers ($fields) VALUES ($placeholders)", $params);

            set_flash("Supplier '{$source['name']}' imported successfully!", 'success');
            redirect('suppliers.php');

        } catch (Exception $e) {
            set_flash($e->getMessage(), 'error');
        }
    }
}

// Search Logic
$search = get_param('search', '');
$suppliers = [];

if (!empty($search)) {
    // Find suppliers in OTHER companies
    $sql = "
        SELECT s.*, c.name as company_name 
        FROM suppliers s 
        LEFT JOIN companies c ON s.company_id = c.id 
        WHERE (s.company_id != ? OR s.company_id IS NULL)
        AND (s.supplier_code LIKE ? OR s.name LIKE ? OR s.email LIKE ?)
        LIMIT 50
    ";
    $search_term = "%$search%";
    $suppliers = db_fetch_all($sql, [$company_id, $search_term, $search_term, $search_term]);
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-file-import me-2 text-info"></i>Import Supplier</h2>
            <p class="text-muted">Reuse existing supplier records from other companies in the MJR Group.</p>
        </div>
        <div class="col-auto">
            <a href="suppliers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-9">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-info"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control form-control-lg bg-dark text-white border-secondary" 
                               placeholder="Search by Code, Name, or Email to find group suppliers..." 
                               value="<?= escape_html($search) ?>" autofocus>
                    </div>
                </div>
                <div class="col-md-3 d-grid">
                    <button type="submit" class="btn btn-info btn-lg">Search Group</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($search)): ?>
        <div class="card shadow">
            <div class="card-header bg-dark border-secondary">
                <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Global Search Results</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($suppliers)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Source Company</th>
                                    <th>Code</th>
                                    <th>Supplier Name</th>
                                    <th>Email/Phone</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $s): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary"><?= escape_html($s['company_name'] ?: 'None') ?></span>
                                        </td>
                                        <td class="fw-bold text-info"><?= escape_html($s['supplier_code']) ?></td>
                                        <td><?= escape_html($s['name']) ?></td>
                                        <td>
                                            <small class="d-block"><?= escape_html($s['email'] ?: '-') ?></small>
                                            <small class="text-muted"><?= escape_html($s['phone'] ?: '-') ?></small>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" onsubmit="return confirm('Import this supplier to your company list?')">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="import_id" value="<?= $s['id'] ?>">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-plus-circle me-1"></i>Import
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No suppliers found matching "<?= escape_html($search) ?>"</h5>
                        <p class="text-muted">Try a different search term or <a href="add_supplier.php">Create New Supplier</a> if they really don't exist.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info border-info bg-dark">
            <i class="fas fa-info-circle me-2"></i>
            Enter a supplier name or code above to search across the entire MJR Group directory.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
