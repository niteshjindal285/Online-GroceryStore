<?php
/**
 * Sales - Quotes & Estimates
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Quotes & Estimates - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view quotes.', 'warning');
    redirect(url('index.php'));
}

// Handle quote deletion
if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            if (!has_permission('manage_sales')) {
                throw new Exception('You do not have permission to delete quotes.');
            }
            $delete_id = post('delete_id');

            // Ensure quote belongs to company
            $check = db_fetch("SELECT id FROM quotes WHERE id = ? AND company_id = ?", [$delete_id, $company_id]);
            if (!$check) {
                throw new Exception('Quote not found or access denied.');
            }
            
            // Start transaction
            db_begin_transaction();
            
            // Delete quote items first
            db_query("DELETE FROM quote_lines WHERE quote_id = ?", [$delete_id]);
            
            // Delete quote
            db_query("DELETE FROM quotes WHERE id = ?", [$delete_id]);
            
            db_commit();
            
            set_flash('Quote deleted successfully!', 'success');
            redirect('quotes.php');
        } catch (Exception $e) {
            db_rollback();
            log_error("Error deleting quote: " . $e->getMessage());
            set_flash('Error deleting quote.', 'error');
        }
    }
}

// Get filter parameters
$status = get_param('status', '');
$page_num = max(1, intval(get_param('page', 1)));
$per_page = 20;

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status)) {
    $where_conditions[] = "q.status = ?";
    $params[] = $status;
}

$where_sql = implode(' AND ', $where_conditions) . db_where_company('q');

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM quotes q WHERE {$where_sql}";
$total_items = db_fetch($count_sql, $params)['count'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

// Get quotes
$sql = "
    SELECT q.*, c.name as customer_name, c.customer_code,
           (SELECT COUNT(*) FROM quote_lines WHERE quote_id = q.id) as item_count
    FROM quotes q
    JOIN customers c ON q.customer_id = c.id
    WHERE {$where_sql}
    ORDER BY q.quote_date DESC, q.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$quotes = db_fetch_all($sql, $params);

// Calculate statistics (filtered by company)
$draft_count = db_fetch("SELECT COUNT(*) as count FROM quotes WHERE status = 'draft' AND company_id = ?", [$company_id])['count'] ?? 0;
$sent_count = db_fetch("SELECT COUNT(*) as count FROM quotes WHERE status = 'sent' AND company_id = ?", [$company_id])['count'] ?? 0;
$accepted_count = db_fetch("SELECT COUNT(*) as count FROM quotes WHERE status = 'accepted' AND company_id = ?", [$company_id])['count'] ?? 0;
$total_value = db_fetch("SELECT SUM(total_amount) as total FROM quotes WHERE company_id = ?", [$company_id])['total'] ?? 0;

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-file-contract me-3"></i>Quotes & Estimates</h1>
        </div>
        <?php if (has_permission('manage_sales')): ?>
        <div class="col-auto">
            <a href="add_quote.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Quote
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Filter Chips -->
    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        <span class="text-muted small me-1">Filter:</span>
        <a href="quotes.php" class="btn btn-sm <?= empty($status) ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
        <a href="quotes.php?status=draft"    class="btn btn-sm <?= $status==='draft'    ? 'btn-secondary' : 'btn-outline-secondary' ?>">Draft</a>
        <a href="quotes.php?status=sent"     class="btn btn-sm <?= $status==='sent'     ? 'btn-info'      : 'btn-outline-secondary' ?>">Sent</a>
        <a href="quotes.php?status=accepted" class="btn btn-sm <?= $status==='accepted' ? 'btn-success'   : 'btn-outline-secondary' ?>">Accepted</a>
        <a href="quotes.php?status=rejected" class="btn btn-sm <?= $status==='rejected' ? 'btn-danger'    : 'btn-outline-secondary' ?>">Rejected</a>
    </div>

    <!-- Quote Status Summary -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Draft Quotes</h6>
                            <h3><?= $draft_count ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Sent Quotes</h6>
                            <h3><?= $sent_count ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-paper-plane fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Accepted Quotes</h6>
                            <h3><?= $accepted_count ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Value</h6>
                            <h3><?= format_currency($total_value) ?></h3>
                        </div>
                        <div>
                            <i class="fas fa-dollar-sign fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quotes List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Quotes & Estimates</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($quotes)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="quotesTable">
                    <thead>
                        <tr>
                            <th>Quote Number</th>
                            <th>Customer</th>
                            <th>Quote Date</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <strong><?= escape_html($quote['quote_number']) ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= escape_html($quote['customer_name']) ?></strong><br>
                                    <small class="text-muted"><?= escape_html($quote['customer_code']) ?></small>
                                </div>
                            </td>
                            <td><?= format_date($quote['quote_date']) ?></td>
                            <td>
                                <?php if (isset($quote['expiry_date']) && $quote['expiry_date']): ?>
                                    <?= format_date($quote['expiry_date']) ?>
                                <?php elseif (isset($quote['valid_until']) && $quote['valid_until']): ?>
                                    <?= format_date($quote['valid_until']) ?>
                                <?php else: ?>
                                    <span class="text-muted">No expiry</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $exp_date = $quote['expiry_date'] ?? ($quote['valid_until'] ?? null);
                                $is_expired = $exp_date && strtotime($exp_date) < time() && !in_array($quote['status'], ['accepted','rejected']);
                                $status_badges = [
                                    'draft' => 'secondary',
                                    'sent' => 'info',
                                    'accepted' => 'success',
                                    'rejected' => 'danger',
                                    'expired' => 'warning'
                                ];
                                if ($is_expired) {
                                    echo '<span class="badge bg-danger">Expired</span>';
                                } else {
                                    $badge_class = $status_badges[$quote['status']] ?? 'secondary';
                                    echo '<span class="badge bg-' . $badge_class . '">' . ucfirst($quote['status']) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= $quote['item_count'] ?> items</span>
                            </td>
                            <td>
                                <strong><?= format_currency($quote['total_amount']) ?></strong>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_quote.php?id=<?= $quote['id'] ?>" class="btn btn-outline-primary" title="View Quote">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (has_permission('manage_sales')): ?>
                                    <a href="edit_quote.php?id=<?= $quote['id'] ?>" class="btn btn-outline-success" title="Edit Quote">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" title="Delete Quote" 
                                            onclick="if(confirm('Are you sure you want to delete this quote?')) { document.getElementById('delete-form-<?= $quote['id'] ?>').submit(); }">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <form id="delete-form-<?= $quote['id'] ?>" method="POST" style="display:none;">
                                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                        <input type="hidden" name="delete_id" value="<?= $quote['id'] ?>">
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num - 1 ?>&status=<?= urlencode($status) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page_num): ?>
                            <li class="page-item active">
                                <span class="page-link"><?= $i ?></span>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>"><?= $i ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num + 1 ?>&status=<?= urlencode($status) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                <p class="text-muted">No quotes found</p>
                <?php if (has_permission('manage_sales')): ?>
                <a href="add_quote.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create First Quote
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#quotesTable').DataTable({
        'paging': false,
        'info': false,
        'ordering': true,
        'order': [[2, 'desc']]
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
