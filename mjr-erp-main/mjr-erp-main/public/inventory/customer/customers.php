<?php
/**
 * Sales - Customer Management
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'Customer Management - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view customers.', 'warning');
    redirect(url('index.php'));
}
$company_name = active_company_name('Current Company');

// Get search parameter
$search = get_param('search', '');
$page_num = max(1, intval(get_param('page', 1)));
$per_page = 20;

// Build query
$where_conditions = ["c.is_active = 1"];
$where_sql = implode(' AND ', $where_conditions) . db_where_company('c');
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.email LIKE ?)";
    $search_term = "%{$search}%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// where_sql already set above

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM customers c WHERE {$where_sql}";
$total_items = db_fetch($count_sql, $params)['count'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

// Get customers
$sql = "
    SELECT c.*
    FROM customers c
    WHERE {$where_sql}
    ORDER BY c.name
    LIMIT {$per_page} OFFSET {$offset}
";

$customers = db_fetch_all($sql, $params);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-users me-3"></i>Customer Management</h1>
            <p class="text-muted mb-0">Showing customers for <strong><?= escape_html($company_name) ?></strong></p>
        </div>
        <div class="col-auto">
            <a href="export_customers.php" class="btn btn-outline-success me-2">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </a>
            <a href="add_customer.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Customer
            </a>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Search Customers</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= escape_html($search) ?>" placeholder="Search by name, code, or email...">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-outline-primary me-2">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="customers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Customer Directory</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($customers)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="customersTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Customer Name</th>
                            <th>City/State</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Payment Terms</th>
                            <?php if (customers_table_has_discounts()): ?>
                            <th>Discounts</th>
                            <?php endif; ?>
                            <th>Credit Limit</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td>
                                <strong><?= escape_html($customer['customer_code']) ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= escape_html($customer['name']) ?></strong><br>
                                    <?php if ($customer['address']): ?>
                                        <small class="text-muted"><?= escape_html(substr($customer['address'], 0, 50)) ?>...</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($customer['city']): ?>
                                    <?= escape_html($customer['city']) ?>
                                    <?php if ($customer['state']): ?>, <?= escape_html($customer['state']) ?><?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['email']): ?>
                                    <a href="mailto:<?= escape_html($customer['email']) ?>"><?= escape_html($customer['email']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['phone']): ?>
                                    <a href="tel:<?= escape_html($customer['phone']) ?>"><?= escape_html($customer['phone']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer['payment_terms']): ?>
                                    <span class="badge bg-info"><?= escape_html($customer['payment_terms']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Standard</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (customers_table_has_discounts()): ?>
                                    <?php if ($customer['default_discount_percent'] > 0 || $customer['default_discount_amount'] > 0): ?>
                                        <div class="small">
                                            <?php if ($customer['default_discount_percent'] > 0): ?>
                                                <span class="text-success"><?= number_format($customer['default_discount_percent'], 2) ?>%</span>
                                            <?php endif; ?>
                                            <?php if ($customer['default_discount_percent'] > 0 && $customer['default_discount_amount'] > 0): ?>
                                                <br>
                                            <?php endif; ?>
                                            <?php if ($customer['default_discount_amount'] > 0): ?>
                                                <span class="text-info"><?= format_currency($customer['default_discount_amount']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">None</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted italic">Manual SQL Req.</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= format_currency($customer['credit_limit']) ?></strong>
                            </td>
                            <td>
                                <?php if ($customer['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_customer.php?id=<?= $customer['id'] ?>" 
                                       class="btn btn-outline-info" title="View Customer">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_customer.php?id=<?= $customer['id'] ?>" 
                                       class="btn btn-outline-primary" title="Edit Customer">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="../../sales/orders.php?customer_id=<?= $customer['id'] ?>" 
                                       class="btn btn-outline-success" title="View Orders">
                                        <i class="fas fa-shopping-cart"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page_num > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page_num): ?>
                            <li class="page-item active">
                                <span class="page-link"><?= $i ?></span>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                <p class="text-muted">No customers found</p>
                <a href="add_customer.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First Customer
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#customersTable').DataTable({
        'paging': false,
        'info': false,
        'ordering': true
    });
});
</script>
";

include __DIR__ . '/../../../templates/footer.php';
?>


