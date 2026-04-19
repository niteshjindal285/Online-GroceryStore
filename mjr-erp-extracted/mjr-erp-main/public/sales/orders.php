<?php
/**
 * Sales - Sales Orders
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'Sales Orders - MJR Group ERP';
$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company to view sales orders.', 'warning');
    redirect(url('index.php'));
}

// Handle order deletion
if (is_post()) {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            if (!has_permission('manage_sales')) {
                throw new Exception('You do not have permission to delete orders.');
            }
            $delete_id = post('delete_id');
            
            // Ensure order belongs to company
            $check = db_fetch("SELECT id FROM sales_orders WHERE id = ? AND company_id = ?", [$delete_id, $company_id]);
            if (!$check) {
                throw new Exception('Order not found or access denied.');
            }
            
            // Start transaction
            db_begin_transaction();
            
            // Delete order items first
            db_query("DELETE FROM sales_order_lines WHERE order_id = ?", [$delete_id]);
            
            // Delete order
            db_query("DELETE FROM sales_orders WHERE id = ?", [$delete_id]);
            
            db_commit();
            
            set_flash('Order deleted successfully!', 'success');
            redirect('orders.php');
        } catch (Exception $e) {
            db_rollback();
            log_error("Error deleting order: " . $e->getMessage());
            set_flash('Error deleting order.', 'error');
        }
    }
}

// Handle Payment Update POST
if (is_post() && post('action') === 'record_payment') {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            $p_id = post('payment_id');
            $p_status = post('payment_status');
            $p_method = post('payment_method');
            $p_date = post('payment_date');
            $p_currency = post('payment_currency');
            
            db_query("
                UPDATE sales_orders 
                SET payment_status = ?, payment_method = ?, payment_date = ?, payment_currency = ? 
                WHERE id = ? AND company_id = ?
            ", [$p_status, $p_method, $p_date, $p_currency, $p_id, $company_id]);
            
            set_flash('Payment recorded successfully!', 'success');
            redirect('orders.php');
        } catch (Exception $e) {
            log_error("Error recording payment: " . $e->getMessage());
            set_flash('Error recording payment.', 'error');
        }
    }
}

// Get filter parameters
$status = get_param('status', '');
$customer_id = get_param('customer_id', '');
$search = get_param('search', '');
$date_from = get_param('date_from', '');
$date_to = get_param('date_to', '');
$page_num = max(1, intval(get_param('page', 1)));
$per_page = 20;

// Build query
$where_conditions = ["1=1"];
$params = [];

if (!empty($status)) {
    $where_conditions[] = "so.status = ?";
    $params[] = $status;
}

if (!empty($customer_id)) {
    $where_conditions[] = "so.customer_id = ?";
    $params[] = intval($customer_id);
}

if (!empty($search)) {
    $where_conditions[] = "(so.order_number LIKE ? OR c.name LIKE ? OR c.customer_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "so.order_date >= ?";
    $params[] = to_db_date($date_from);
}

if (!empty($date_to)) {
    $where_conditions[] = "so.order_date <= ?";
    $params[] = to_db_date($date_to);
}

$where_sql = implode(' AND ', $where_conditions) . db_where_company('so');

// Get total count
$count_sql = "SELECT COUNT(*) as count FROM sales_orders so WHERE {$where_sql}";
$total_items = db_fetch($count_sql, $params)['count'] ?? 0;

// Calculate pagination
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

// Get orders
$sql = "
    SELECT so.*, c.name as customer_name, c.customer_code,
           (SELECT COUNT(*) FROM sales_order_lines WHERE order_id = so.id) as item_count
    FROM sales_orders so
    JOIN customers c ON so.customer_id = c.id
    WHERE {$where_sql}
    ORDER BY so.order_date DESC, so.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";

$orders = db_fetch_all($sql, $params);

// Get customers for dropdown
$customers = db_fetch_all("SELECT id, customer_code, name FROM customers WHERE is_active = 1 AND company_id = ? ORDER BY name", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-shopping-cart me-2"></i>Sales Orders</h2>
        <div>
            <a href="export_orders.php" class="btn btn-outline-success me-2">
                <i class="fas fa-file-csv me-2"></i>Export CSV
            </a>
            <?php if (has_permission('manage_sales')): ?>
            <a href="add_order.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Order
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <input type="hidden" name="customer_id" value="<?= escape_html($customer_id) ?>">
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?= escape_html($search) ?>" placeholder="Order #, Customer Name...">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status == 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="confirmed" <?= $status == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="in_production" <?= $status == 'in_production' ? 'selected' : '' ?>>In Production</option>
                        <option value="shipped" <?= $status == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $status == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="text" class="form-control datepicker" id="date_from" name="date_from" value="<?= !empty($date_from) ? format_date($date_from) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="text" class="form-control datepicker" id="date_to" name="date_to" value="<?= !empty($date_to) ? format_date($date_to) : '' ?>" placeholder="DD-MM-YYYY">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2 w-100">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                    <a href="orders.php" class="btn btn-outline-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders List -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list me-2"></i>Sales Orders</h5>
        </div>
        <div class="card-body">
            <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="table table-striped" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Order Date</th>
                            <th>Required Date</th>
                            <th>Status</th>
                            <th>Delivery Status</th>
                            <th>Payment Status</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>
                                <strong><?= escape_html($order['order_number']) ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= escape_html($order['customer_name']) ?></strong><br>
                                    <small class="text-muted"><?= escape_html($order['customer_code']) ?></small>
                                </div>
                            </td>
                            <td><?= format_date($order['order_date']) ?></td>
                            <td>
                                <?php if (isset($order['required_date']) && $order['required_date']): ?>
                                    <?= format_date($order['required_date']) ?>
                                <?php elseif (isset($order['delivery_date']) && $order['delivery_date']): ?>
                                    <?= format_date($order['delivery_date']) ?>
                                <?php else: ?>
                                    <span class="text-muted">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_badges = [
                                    'draft' => 'secondary',
                                    'confirmed' => 'info',
                                    'in_production' => 'primary',
                                    'shipped' => 'warning',
                                    'delivered' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                $badge_class = $status_badges[$order['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $badge_class ?>"><?= ucwords(str_replace('_', ' ', $order['status'])) ?></span>
                            </td>
                            <td>
                                <?php
                                $d_status = $order['delivery_status'] ?? 'open';
                                $d_badge = ($d_status == 'completed' ? 'success' : ($d_status == 'pending' ? 'warning text-dark' : 'secondary'));
                                ?>
                                <span class="badge bg-<?= $d_badge ?>"><?= ucfirst($d_status) ?></span>
                            </td>
                            <td>
                                <?php
                                $p_status = $order['payment_status'] ?? 'unpaid';
                                $p_badge = 'secondary';
                                if ($p_status == 'paid') $p_badge = 'success';
                                elseif ($p_status == 'partially_paid') $p_badge = 'info';
                                elseif ($p_status == 'refunded') $p_badge = 'danger';
                                elseif ($p_status == 'unpaid') $p_badge = 'warning';
                                ?>
                                <span class="badge bg-<?= $p_badge ?>"><?= ucfirst(str_replace('_', ' ', $p_status)) ?></span>
                                <?php if (!empty($order['payment_currency'])): ?>
                                    <span class="currency-badge currency-<?= strtolower($order['payment_currency']) ?> ms-1">
                                        <?= $order['payment_currency'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= $order['item_count'] ?> items</span>
                            </td>
                            <td>
                                <strong><?= format_currency($order['total_amount'], $order['payment_currency'] ?? 'USD') ?></strong>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="view_order.php?id=<?= $order['id'] ?>">
                                                <i class="fas fa-eye me-2 text-primary"></i>View Details
                                            </a>
                                        </li>
                                        <?php if (has_permission('manage_sales')): ?>
                                        <li>
                                            <a class="dropdown-item" href="edit_order.php?id=<?= $order['id'] ?>">
                                                <i class="fas fa-edit me-2 text-success"></i>Edit Order
                                            </a>
                                        </li>
                                        <li>
                                            <button type="button" class="dropdown-item record-payment" 
                                                    data-id="<?= $order['id'] ?>"
                                                    data-status="<?= $order['payment_status'] ?>"
                                                    data-method="<?= $order['payment_method'] ?>"
                                                    data-currency="<?= $order['payment_currency'] ?? 'USD' ?>"
                                                    data-date="<?= $order['payment_date'] ? date('Y-m-d\TH:i', strtotime($order['payment_date'])) : date('Y-m-d\TH:i') ?>">
                                                <i class="fas fa-money-bill-wave me-2 text-warning"></i>Record Payment
                                            </button>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="ship_order.php?invoice_id=<?= db_fetch('SELECT id FROM invoices WHERE so_id = ? LIMIT 1', [$order['id']])['id'] ?? '' ?>">
                                                <i class="fas fa-truck-loading me-2 text-warning"></i>Deliver Now
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button type="button" class="dropdown-item text-danger" 
                                                    onclick="if(confirm('Are you sure you want to delete this order?')) { document.getElementById('delete-form-<?= $order['id'] ?>').submit(); }">
                                                <i class="fas fa-trash me-2"></i>Delete Order
                                            </button>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <form id="delete-form-<?= $order['id'] ?>" method="POST" style="display:none;">
                                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                    <input type="hidden" name="delete_id" value="<?= $order['id'] ?>">
                                </form>
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
                            <a class="page-link" href="?page=<?= $page_num - 1 ?>&status=<?= urlencode($status) ?>&customer_id=<?= urlencode($customer_id) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page_num): ?>
                            <li class="page-item active">
                                <span class="page-link"><?= $i ?></span>
                            </li>
                        <?php else: ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= urlencode($status) ?>&customer_id=<?= urlencode($customer_id) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a>
                            </li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page_num + 1 ?>&status=<?= urlencode($status) ?>&customer_id=<?= urlencode($customer_id) ?>&search=<?= urlencode($search) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-4">
                <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                <p class="text-muted">No sales orders found</p>
                <a href="add_order.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create First Order
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record / Update Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="payment_id" id="payment_id">
                    
                    <div class="mb-3">
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="pending">Pending</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="refunded">Refunded</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method">
                            <option value="">Select Method</option>
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="payment_currency" class="form-label">Currency</label>
                        <select class="form-select currency-select" id="payment_currency" name="payment_currency">
                            <option value="FJD">FJD / $</option>
                            <option value="USD">USD / $</option>
                            <option value="EUR">EUR / €</option>
                            <option value="GBP">GBP / £</option>
                            <option value="INR">INR / ₹</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="datetime-local" class="form-control" id="payment_date" name="payment_date">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="document.getElementById('paymentForm').submit()">
                    <i class="fas fa-save me-2"></i>Save Payment Info
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    $('#ordersTable').DataTable({
        'paging': false,
        'info': false,
        'ordering': true
    });

    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));

    $(document).on('click', '.record-payment', function() {
        const id = $(this).data('id');
        const status = $(this).data('status') || 'pending';
        const method = $(this).data('method') || '';
        const currency = $(this).data('currency') || 'USD';
        const date = $(this).data('date') || '<?= date('Y-m-d\TH:i') ?>';

        $('#payment_id').val(id);
        $('#payment_status').val(status);
        $('#payment_method').val(method);
        $('#payment_currency').val(currency);
        $('#payment_date').val(date);

        paymentModal.show();
    });
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>
