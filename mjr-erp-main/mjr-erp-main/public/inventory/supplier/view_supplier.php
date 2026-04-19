<?php
/**
 * View Supplier Details
 */

require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';

require_login();

$page_title = 'View Supplier - MJR Group ERP';

$company_id = active_company_id();
if (!$company_id) {
    set_flash('Please select a company first.', 'warning');
    redirect('suppliers.php');
}

$supplier_id = get_param('id');

if (!$supplier_id) {
    set_flash('Supplier not found.', 'error');
    redirect('suppliers.php');
}

// Get supplier details for current company only
$has_company_id = suppliers_table_has_company_id();
$sql = "SELECT * FROM suppliers WHERE id = ?";
$params = [$supplier_id];
if ($has_company_id) {
    $sql .= " AND company_id = ?";
    $params[] = $company_id;
}

$supplier = db_fetch($sql, $params);

if (!$supplier) {
    set_flash('Supplier not found.', 'error');
    redirect('suppliers.php');
}

// Get purchase orders for this supplier — scoped to the active company
$purchase_orders = db_fetch_all("
    SELECT po.*, 
           (SELECT COUNT(*) FROM purchase_order_lines WHERE po_id = po.id) as line_count
    FROM purchase_orders po
    WHERE po.supplier_id = ?
    AND po.company_id = ?
    ORDER BY po.order_date DESC
    LIMIT 10
", [$supplier_id, $company_id]);

include __DIR__ . '/../../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <h2><i class="fas fa-eye me-2"></i>Supplier Details</h2>
            <p class="text-muted mb-0">For <strong><?= escape_html(active_company_name()) ?></strong></p>
        </div>
        <div class="col-auto">
            <a href="../purchase_order/add_purchase_order.php?supplier_id=<?= $supplier['id'] ?>" class="btn btn-primary me-2">
                <i class="fas fa-plus me-1"></i>Create PO
            </a>
            <a href="edit_supplier.php?id=<?= $supplier['id'] ?>" class="btn btn-warning">
                <i class="fas fa-edit me-2"></i>Edit
            </a>
            <a href="suppliers.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Supplier Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Supplier Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Supplier Code:</strong> <?= escape_html($supplier['supplier_code']) ?></p>
                            <p><strong>Supplier Name:</strong> <?= escape_html($supplier['name']) ?></p>
                            <p><strong>Contact Person:</strong> <?= escape_html($supplier['contact_person'] ?? 'Not specified') ?></p>
                            <p><strong>Email:</strong> <?= $supplier['email'] ? '<a href="mailto:' . escape_html($supplier['email']) . '">' . escape_html($supplier['email']) . '</a>' : 'Not specified' ?></p>
                            <p><strong>Phone:</strong> <?= escape_html($supplier['phone'] ?? 'Not specified') ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Address:</strong> <?= nl2br(escape_html($supplier['address'] ?? 'Not specified')) ?></p>
                            <p><strong>City:</strong> <?= escape_html($supplier['city'] ?? 'Not specified') ?></p>
                            <p><strong>Country:</strong> <?= escape_html($supplier['country'] ?? 'Not specified') ?></p>
                            <p><strong>Tax ID:</strong> <?= escape_html($supplier['tax_id'] ?? 'Not specified') ?></p>
                            <p><strong>Payment Terms:</strong> <?= escape_html($supplier['payment_terms'] ?? 'Not specified') ?></p>
                        </div>
                    </div>
                    
                    <?php if ($supplier['notes']): ?>
                    <div class="mt-3">
                        <strong>Notes:</strong>
                        <p class="text-muted"><?= nl2br(escape_html($supplier['notes'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <strong>Status:</strong>
                        <?php if ($supplier['is_active']): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Purchase Orders -->
            <div class="card">
                <div class="card-header">
                    <h5>Recent Purchase Orders</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($purchase_orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>PO Number</th>
                                    <th>Order Date</th>
                                    <th>Expected Delivery</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchase_orders as $po): ?>
                                <tr>
                                    <td><?= escape_html($po['po_number']) ?></td>
                                    <td><?= format_date($po['order_date']) ?></td>
                                    <td><?= format_date($po['expected_delivery_date']) ?></td>
                                    <td><?= $po['line_count'] ?></td>
                                    <td>$<?= number_format($po['total_amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $badge_class = match($po['status']) {
                                            'draft'              => 'secondary',
                                            'sent'               => 'primary',
                                            'confirmed'          => 'info',
                                            'partially_received' => 'warning',
                                            'received'           => 'success',
                                            'cancelled'          => 'danger',
                                            default              => 'secondary',
                                        };
                                    ?>

                                        <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($po['status']) ?></span>
                                    </td>
                                    <td>
                                        <a href="../purchase_order/view_purchase_order.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No purchase orders found for this supplier.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Purchase Statistics</h5>
                </div>
                <div class="card-body">
                    <?php
                    $stats = db_fetch("
                        SELECT 
                            COUNT(*) as total_pos,
                            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_pos,
                            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_pos,
                            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_pos,
                            SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_pos,
                            SUM(total_amount) as total_value
                        FROM purchase_orders
                        WHERE supplier_id = ?
                        AND company_id = ?
                    ", [$supplier_id, $company_id]);
                    ?>
                    
                    <p><strong>Total Purchase Orders:</strong> <?= $stats['total_pos'] ?></p>
                    <p><strong>Total Value:</strong> $<?= number_format($stats['total_value'] ?? 0, 2) ?></p>
                    
                    <hr>
                    
                    <p><strong>By Status:</strong></p>
                    <ul class="list-unstyled">
                        <li>Draft: <?= $stats['draft_pos'] ?></li>
                        <li>Sent: <?= $stats['sent_pos'] ?></li>
                        <li>Confirmed: <?= $stats['confirmed_pos'] ?></li>
                        <li>Received: <?= $stats['received_pos'] ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>



