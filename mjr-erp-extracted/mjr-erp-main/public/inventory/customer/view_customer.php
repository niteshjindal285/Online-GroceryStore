<?php
/**
 * View Customer Page
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'View Customer - MJR Group ERP';

// Get customer ID from URL
$customer_id = get('id');
if (!$customer_id) {
    set_flash('Customer ID not provided.', 'error');
    redirect('customers.php');
}

// Handle Payment Update POST
if (is_post() && post('action') === 'record_payment') {
    $csrf_token = post('csrf_token');
    if (verify_csrf_token($csrf_token)) {
        try {
            $p_id = post('payment_id');
            
            if (!$p_id) {
                throw new Exception("Order ID not provided.");
            }

            $p_status = post('payment_status');
            $p_method = post('payment_method');
            $p_date = post('payment_date');
            $p_currency = post('payment_currency');
            
            db_query("
                UPDATE sales_orders 
                SET payment_status = ?, payment_method = ?, payment_date = ?, payment_currency = ? 
                WHERE id = ? AND company_id = ?
            ", [$p_status, $p_method, $p_date, $p_currency, $p_id, $_SESSION['company_id']]);
            
            set_flash('Payment recorded successfully!', 'success');
            redirect("view_customer.php?id=$customer_id");
        } catch (Exception $e) {
            log_error("Error recording payment: " . $e->getMessage());
            set_flash('Error recording payment.', 'error');
        }
    }
}

// Get customer data
$customer = db_fetch("SELECT * FROM customers WHERE id = ?", [$customer_id]);
if (!$customer) {
    set_flash('Customer not found.', 'error');
    redirect('customers.php');
}

// Get recent orders for this customer
$orders = db_fetch_all("
    SELECT id, order_number, order_date, status, total_amount, payment_status, payment_method, payment_date, payment_currency
    FROM sales_orders
    WHERE customer_id = ?
    ORDER BY order_date DESC
    LIMIT 5
", [$customer_id]);

// Get recent quotes for this customer
$quotes = db_fetch_all("
    SELECT id, quote_number, quote_date, status, total_amount
    FROM quotes
    WHERE customer_id = ?
    ORDER BY quote_date DESC
    LIMIT 5
", [$customer_id]);

// Get financial totals
$financials = db_fetch("
    SELECT 
        SUM(CASE WHEN payment_status != 'paid' THEN total_amount ELSE 0 END) as outstanding_balance,
        SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as total_paid
    FROM sales_orders 
    WHERE customer_id = ? AND status != 'cancelled' AND company_id = ?
", [$customer_id, $_SESSION['company_id']]);

$total_outstanding = $financials['outstanding_balance'] ?? 0;
$total_paid = $financials['total_paid'] ?? 0;

// Get all contacts for this customer
$contacts = db_fetch_all("SELECT * FROM customer_contacts WHERE customer_id = ? AND company_id = ? ORDER BY is_primary DESC, name ASC", [$customer_id, $_SESSION['company_id']]);

// Get customer discounts
$discounts = db_fetch_all("
    SELECT cd.*, i.name as item_name, i.code as item_code, c.name as category_name
    FROM customer_discounts cd
    LEFT JOIN inventory_items i ON cd.item_id = i.id
    LEFT JOIN categories c ON cd.category_id = c.id
    WHERE cd.customer_id = ? AND cd.is_active = 1
", [$customer_id]);

// Get all inventory items for discount dropdown
$inventory_items = db_fetch_all("SELECT id, name, code FROM inventory_items WHERE company_id = ? ORDER BY name ASC", [$_SESSION['company_id']]);

// Get all categories for discount dropdown
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name ASC");


// Handle Contact Actions (AJAX or Form Post)
if (is_post() && post('action') === 'save_contact') {
    try {
        if (!verify_csrf_token(post('csrf_token'))) throw new Exception('Invalid CSRF token.');
        
        $contact_id = post('contact_id');
        $name = post('contact_name');
        $email = post('contact_email');
        $phone = post('contact_phone');
        $designation = post('contact_designation');
        $is_primary = post('is_primary') ? 1 : 0;

        if ($is_primary) {
            // Reset existing primary contacts
            db_query("UPDATE customer_contacts SET is_primary = 0 WHERE customer_id = ?", [$customer_id]);
        }

        if ($contact_id) {
            db_query("UPDATE customer_contacts SET name = ?, email = ?, phone = ?, designation = ?, is_primary = ? WHERE id = ?", [$name, $email, $phone, $designation, $is_primary, $contact_id]);
            set_flash('Contact updated successfully.', 'success');
        } else {
            db_insert("INSERT INTO customer_contacts (customer_id, name, email, phone, designation, is_primary, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)", 
                [$customer_id, $name, $email, $phone, $designation, $is_primary, $_SESSION['company_id']]);
            set_flash('Contact added successfully.', 'success');
        }
        redirect('view_customer.php?id=' . $customer_id);
    } catch (Exception $e) {
        set_flash($e->getMessage(), 'error');
    }
}

if (is_post() && post('action') === 'delete_contact') {
    try {
        if (!verify_csrf_token(post('csrf_token'))) throw new Exception('Invalid CSRF token.');
        $contact_id = post('delete_contact_id');
        db_query("DELETE FROM customer_contacts WHERE id = ? AND customer_id = ?", [$contact_id, $customer_id]);
        set_flash('Contact deleted successfully.', 'success');
        redirect('view_customer.php?id=' . $customer_id);
    } catch (Exception $e) {
        set_flash($e->getMessage(), 'error');
    }
}

if (is_post() && post('action') === 'save_discount') {
    try {
        if (!verify_csrf_token(post('csrf_token'))) throw new Exception('Invalid CSRF token.');
        
        $discount_type = post('discount_type'); // 'item', 'category', 'global'
        $item_id = post('item_id') ?: null;
        $category_id = post('category_id') ?: null;
        $discount_percent = (float)post('discount_percent');

        if ($discount_percent < 0 || $discount_percent > 100) {
            throw new Exception("Invalid discount percentage.");
        }

        if ($discount_type === 'global') {
            $item_id = null;
            $category_id = null;
        } elseif ($discount_type === 'category') {
            $item_id = null;
            if (!$category_id) throw new Exception("Please select a category.");
        } else {
            $category_id = null;
            if (!$item_id) throw new Exception("Please select an item.");
        }

        // Check if discount already exists
        if ($item_id) {
            $existing = db_fetch("SELECT id FROM customer_discounts WHERE customer_id = ? AND item_id = ?", [$customer_id, $item_id]);
        } elseif ($category_id) {
            $existing = db_fetch("SELECT id FROM customer_discounts WHERE customer_id = ? AND category_id = ?", [$customer_id, $category_id]);
        } else {
            $existing = db_fetch("SELECT id FROM customer_discounts WHERE customer_id = ? AND item_id IS NULL AND category_id IS NULL", [$customer_id]);
        }

        if ($existing) {
            db_query("UPDATE customer_discounts SET discount_percent = ?, is_active = 1 WHERE id = ?", [$discount_percent, $existing['id']]);
        } else {
            db_insert("INSERT INTO customer_discounts (customer_id, item_id, category_id, discount_percent, is_active) VALUES (?, ?, ?, ?, 1)", [$customer_id, $item_id, $category_id, $discount_percent]);
        }

        
        set_flash('Discount added successfully.', 'success');
        redirect('view_customer.php?id=' . $customer_id);
    } catch (Exception $e) {
        set_flash($e->getMessage(), 'error');
    }
}

if (is_post() && post('action') === 'delete_discount') {
    try {
        if (!verify_csrf_token(post('csrf_token'))) throw new Exception('Invalid CSRF token.');
        $discount_id = post('delete_discount_id');
        db_query("DELETE FROM customer_discounts WHERE id = ? AND customer_id = ?", [$discount_id, $customer_id]);
        set_flash('Discount removed successfully.', 'success');
        redirect('view_customer.php?id=' . $customer_id);
    } catch (Exception $e) {
        set_flash($e->getMessage(), 'error');
    }
}

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container">
    <!-- Customer Details -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3><i class="fas fa-user me-2"></i><?= escape_html($customer['name']) ?></h3>
                    <div>
                        <a href="edit_customer.php?id=<?= $customer['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Customer
                        </a>
                        <a href="customers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to List
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Customer Code:</strong></td>
                                    <td><?= escape_html($customer['customer_code']) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><a href="mailto:<?= escape_html($customer['email']) ?>"><?= escape_html($customer['email']) ?></a></td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td><?= escape_html($customer['phone']) ?: 'Not set' ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Terms:</strong></td>
                                    <td><?= escape_html($customer['payment_terms']) ?: 'Standard' ?></td>
                                </tr>
                                <?php if (customers_table_has_discounts()): ?>
                                <tr>
                                    <td><strong>Default Discount (%):</strong></td>
                                    <td class="text-success"><?= number_format($customer['default_discount_percent'], 2) ?>%</td>
                                </tr>
                                <tr>
                                    <td><strong>Default Discount ($):</strong></td>
                                    <td class="text-info"><?= format_currency($customer['default_discount_amount']) ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Credit Limit:</strong></td>
                                    <td>$<?= number_format($customer['credit_limit'], 2) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <?php if ($customer['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Created:</strong></td>
                                    <td><?= date('Y-m-d', strtotime($customer['created_at'])) ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Financial Summary Row -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body p-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="text-dark"><i class="fas fa-hand-holding-usd me-2"></i>Total Paid:</span>
                                    <strong class="text-success"><?= format_currency($total_paid) ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-warning">
                                <div class="card-body p-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="text-dark"><i class="fas fa-exclamation-circle me-2"></i>Outstanding:</span>
                                    <strong class="text-danger"><?= format_currency($total_outstanding) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($customer['address']): ?>
                    <div class="row">
                        <div class="col-12">
                            <strong>Address:</strong><br>
                            <address>
                                <?= escape_html($customer['address']) ?><br>
                                <?= escape_html($customer['city']) ?> <?= escape_html($customer['state']) ?> <?= escape_html($customer['postal_code']) ?><br>
                                <?= escape_html($customer['country']) ?>
                            </address>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Contacts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-address-book me-2"></i>Contact Persons</h5>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#contactModal">
                        <i class="fas fa-plus me-2"></i>Add Contact
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($contacts)): ?>
                        <p class="text-muted mb-0">No contact persons assigned to this customer.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Designation</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Primary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contacts as $c): ?>
                                    <tr>
                                        <td><strong><?= escape_html($c['name']) ?></strong></td>
                                        <td><?= escape_html($c['designation']) ?></td>
                                        <td><a href="mailto:<?= escape_html($c['email']) ?>"><?= escape_html($c['email']) ?></a></td>
                                        <td><?= escape_html($c['phone']) ?></td>
                                        <td>
                                            <?php if ($c['is_primary']): ?>
                                                <span class="badge bg-primary">Yes</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info edit-contact" data-json='<?= json_encode($c) ?>'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Delete this contact?')">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_contact">
                                                <input type="hidden" name="delete_contact_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
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
        </div>
    <!-- Fixed Discounts Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5><i class="fas fa-percent me-2"></i>Fixed Discounts</h5>
                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#discountModal">
                        <i class="fas fa-plus me-2"></i>Add Discount
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($discounts)): ?>
                        <p class="text-muted mb-0">No fixed discounts configured for this customer.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Applies To</th>
                                        <th>Discount %</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($discounts as $d): ?>
                                    <tr>
                                        <td>
                                            <?php if ($d['item_id']): ?>
                                                <span class="badge bg-secondary">Specific Item</span> 
                                                <?= escape_html($d['item_code']) ?> - <?= escape_html($d['item_name']) ?>
                                            <?php elseif ($d['category_id']): ?>
                                                <span class="badge bg-warning text-dark">Category</span> 
                                                <?= escape_html($d['category_name']) ?>
                                            <?php else: ?>
                                                <span class="badge bg-primary">All Items (Global)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= number_format($d['discount_percent'], 2) ?>%</strong></td>
                                        <td><span class="badge bg-success">Active</span></td>
                                        <td>
                                            <form action="" method="POST" class="d-inline" onsubmit="return confirm('Remove this discount?')">
                                                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                                <input type="hidden" name="action" value="delete_discount">
                                                <input type="hidden" name="delete_discount_id" value="<?= $d['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
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
        </div>
    </div>

    <!-- Recent Orders and Quotes -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shopping-cart me-2"></i>Recent Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><a href="../../sales/view_order.php?id=<?= $order['id'] ?>"><?= escape_html($order['order_number']) ?></a></td>
                                        <td><?= date('Y-m-d', strtotime($order['order_date'])) ?></td>
                                        <td><span class="badge bg-info"><?= ucfirst($order['status']) ?></span></td>
                                        <td>
                                            <?php
                                            // Get order's payment status directly
                                            $o_data = db_fetch("SELECT payment_status FROM sales_orders WHERE id = ?", [$order['id']]);
                                            $p_status = $o_data['payment_status'] ?? 'unpaid';
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
                                        <td><?= format_currency($order['total_amount'], $order['payment_currency'] ?? 'USD') ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Action
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="../../sales/view_order.php?id=<?= $order['id'] ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                                    <li>
                                                        <button type="button" class="dropdown-item record-payment" 
                                                                data-id="<?= $order['id'] ?>"
                                                                data-status="<?= $order['payment_status'] ?>"
                                                                data-method="<?= $order['payment_method'] ?>"
                                                                data-currency="<?= $order['payment_currency'] ?? 'USD' ?>"
                                                                data-date="<?= $order['payment_date'] ? date('Y-m-d\TH:i', strtotime($order['payment_date'])) : date('Y-m-d\TH:i') ?>">
                                                            <i class="fas fa-money-bill-wave me-2"></i>Record Payment
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="../../sales/orders.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary">
                            View All Orders
                        </a>
                    <?php else: ?>
                        <p class="text-muted">No orders found for this customer.</p>
                        <a href="../../sales/add_order.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Quotes -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-file-contract me-2"></i>Recent Quotes</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($quotes)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Quote #</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($quotes as $quote): ?>
                                    <tr>
                                        <td><a href="../../sales/view_quote.php?id=<?= $quote['id'] ?>"><?= escape_html($quote['quote_number']) ?></a></td>
                                        <td><?= date('Y-m-d', strtotime($quote['quote_date'])) ?></td>
                                        <td><span class="badge bg-warning"><?= ucfirst($quote['status']) ?></span></td>
                                        <td>$<?= number_format($quote['total_amount'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="../../sales/quotes.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary">
                            View All Quotes
                        </a>
                    <?php else: ?>
                        <p class="text-muted">No quotes found for this customer.</p>
                        <a href="../../sales/add_quote.php?customer_id=<?= $customer['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus me-2"></i>Create Quote
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>


<!-- Contact Modal -->
<div class="modal fade" id="contactModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactModalLabel">Add/Edit Contact</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="save_contact">
                <input type="hidden" name="contact_id" id="contact_id">
                
                <div class="mb-3">
                    <label for="contact_name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="contact_name" id="contact_name" required>
                </div>
                <div class="mb-3">
                    <label for="contact_designation" class="form-label">Designation</label>
                    <input type="text" class="form-control" name="contact_designation" id="contact_designation">
                </div>
                <div class="mb-3">
                    <label for="contact_email" class="form-label">Email</label>
                    <input type="email" class="form-control" name="contact_email" id="contact_email">
                </div>
                <div class="mb-3">
                    <label for="contact_phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" name="contact_phone" id="contact_phone">
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="is_primary" id="is_primary">
                    <label class="form-check-label" for="is_primary">Is Primary Contact</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Contact</button>
            </div>
        </form>
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
                            <option value="FJD">FJD - Fijian Dollar</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="INR">INR - Indian Rupee</option>
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

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Update Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                <input type="hidden" name="action" value="save_discount">
                
                <div class="mb-3">
                    <label class="form-label">Discount Type</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="type_all" value="global" checked>
                            <label class="form-check-label" for="type_all">Global (All Items)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="type_category" value="category">
                            <label class="form-check-label" for="type_category">Category</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="discount_type" id="type_item" value="item">
                            <label class="form-check-label" for="type_item">Specific Item</label>
                        </div>
                    </div>
                </div>

                <div id="item_select_wrap" class="mb-3 d-none">
                    <label for="item_id" class="form-label">Select Item</label>
                    <select class="form-select" name="item_id" id="item_id">
                        <option value="">-- Choose Item --</option>
                        <?php foreach ($inventory_items as $item): ?>
                            <option value="<?= $item['id'] ?>"><?= escape_html($item['code']) ?> - <?= escape_html($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="category_select_wrap" class="mb-3 d-none">
                    <label for="category_id" class="form-label">Select Category</label>
                    <select class="form-select" name="category_id" id="category_id">
                        <option value="">-- Choose Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= escape_html($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="discount_percent" class="form-label">Discount Percentage (%) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control" name="discount_percent" id="discount_percent" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Discount</button>
            </div>
        </form>

    </div>
</div>

<script>
$(document).ready(function() {
    $('.edit-contact').click(function() {
        const data = $(this).data('json');
        $('#contact_id').val(data.id);
        $('#contact_name').val(data.name);
        $('#contact_designation').val(data.designation);
        $('#contact_email').val(data.email);
        $('#contact_phone').val(data.phone);
        $('#is_primary').prop('checked', data.is_primary == 1);
        $('#contactModalLabel').text('Edit Contact');
        $('#contactModal').modal('show');
    });

    $('#contactModal').on('hidden.bs.modal', function () {
        $('#contact_id').val('');
        $('#contactModal form')[0].reset();
        $('#contactModalLabel').text('Add Contact');
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

    // Toggle discount fields
    $('input[name="discount_type"]').change(function() {
        const type = $(this).val();
        $('#item_select_wrap').toggleClass('d-none', type !== 'item');
        $('#category_select_wrap').toggleClass('d-none', type !== 'category');
        
        if (type === 'global') {
            $('#item_id, #category_id').val('');
        }
    });
});
</script>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>


