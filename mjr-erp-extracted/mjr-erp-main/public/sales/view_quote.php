<?php
/**
 * View Quote Page
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();

$page_title = 'View Quote - MJR Group ERP';

// Get quote ID
$quote_id = get('id');
if (!$quote_id) {
    set_flash('Quote ID not provided.', 'error');
    redirect('quotes.php');
}

// Get quote data with customer info
$quote = db_fetch("
    SELECT q.*, c.customer_code, c.name as customer_name, c.email as customer_email, c.phone as customer_phone, c.address as customer_address,
           u.username as created_by_name
    FROM quotes q
    JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
", [$quote_id]);

if (!$quote) {
    set_flash('Quote not found.', 'error');
    redirect('quotes.php');
}

// Get quote items
$quote_items = db_fetch_all("
    SELECT qi.*, i.code, i.name as item_name, qi.description as line_description, i.description as item_desc
    FROM quote_lines qi
    JOIN inventory_items i ON qi.item_id = i.id
    WHERE qi.quote_id = ?
", [$quote_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-eye me-2"></i>Quote <?= escape_html($quote['quote_number']) ?></h2>
                <div>
                    <!-- Invoice Actions -->
                    <button onclick="window.open('print_quote.php?id=<?= $quote_id ?>', '_blank')" class="btn btn-info me-2">
                        <i class="fas fa-print me-2"></i>Print Quote
                    </button>
                    <button onclick="openEmailModal()" class="btn btn-warning me-2">
                        <i class="fas fa-envelope me-2"></i>Email Invoice
                    </button>
                    <!-- Edit Button -->
                    <a href="edit_quote.php?id=<?= $quote['id'] ?>" class="btn btn-success me-2">
                        <i class="fas fa-edit me-2"></i>Edit
                    </a>
                    <a href="add_order.php?from_quote=<?= $quote['id'] ?>" class="btn btn-dark me-2">
                        <i class="fas fa-shopping-cart me-2"></i>Convert to Order
                    </a>
                    <a href="quotes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Quotes
                    </a>
                </div>
            </div>

            <!-- Quote Details -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Quote Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Quote Number:</strong> <?= escape_html($quote['quote_number']) ?></p>
                                    <p><strong>Customer:</strong> <?= escape_html($quote['customer_name']) ?></p>
                                    <p><strong>Quote Date:</strong> <?= format_date($quote['quote_date']) ?></p>
                                    <?php if (!empty($quote['valid_until'])): ?>
                                    <p><strong>Valid Until:</strong> <?= format_date($quote['valid_until']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> 
                                        <?php
                                        $status_badges = [
                                            'draft' => 'secondary',
                                            'sent' => 'info',
                                            'accepted' => 'success',
                                            'rejected' => 'danger',
                                            'expired' => 'warning'
                                        ];
                                        $badge_class = $status_badges[$quote['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($quote['status']) ?></span>
                                    </p>
                                    <p><strong>Total Amount:</strong> $<?= number_format($quote['total_amount'], 2) ?></p>
                                    <?php if ($quote['notes']): ?>
                                    <p><strong>Notes:</strong> <?= escape_html($quote['notes']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quote Items -->
                    <div class="card">
                        <div class="card-header">
                            <h5>Quote Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Description</th>
                                            <th class="text-end">Quantity</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quote_items as $item): ?>
                                        <tr>
                                            <td><strong><?= escape_html($item['item_name']) ?></strong></td>
                                            <td><?= !empty($item['line_description']) ? nl2br(escape_html($item['line_description'])) : '-' ?></td>
                                            <td class="text-end"><?= $item['quantity'] ?></td>
                                            <td class="text-end">$<?= number_format((float)($item['unit_price'] ?? 0), 2) ?></td>
                                            <td class="text-end">$<?= number_format((float)($item['line_total'] ?? 0), 2) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="4" class="text-end">Subtotal:</th>
                                            <th class="text-end">$<?= number_format($quote['subtotal'], 2) ?></th>
                                        </tr>
                                        <?php if ($quote['discount_amount'] > 0): ?>
                                        <tr class="text-danger">
                                            <th colspan="4" class="text-end">Discount:</th>
                                            <th class="text-end">-$<?= number_format($quote['discount_amount'], 2) ?></th>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <th colspan="4" class="text-end">Tax:</th>
                                            <th class="text-end">$<?= number_format($quote['tax_amount'], 2) ?></th>
                                        </tr>
                                        <tr class="table-active">
                                            <th colspan="4" class="text-end">Total Amount:</th>
                                            <th class="text-end">$<?= number_format($quote['total_amount'], 2) ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5>Customer Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong><?= escape_html($quote['customer_name']) ?></strong></p>
                            <p>Code: <?= escape_html($quote['customer_code']) ?></p>
                            <?php if ($quote['customer_email']): ?>
                            <p>Email: <?= escape_html($quote['customer_email']) ?></p>
                            <?php endif; ?>
                            <?php if ($quote['customer_phone']): ?>
                            <p>Phone: <?= escape_html($quote['customer_phone']) ?></p>
                            <?php endif; ?>
                            <?php if ($quote['customer_address']): ?>
                            <p>Address: <?= escape_html($quote['customer_address']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Email Invoice Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Email Quote Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="emailForm" method="POST" action="send_quote_email.php">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <input type="hidden" name="quote_id" value="<?= $quote_id ?>">
                    
                    <div class="mb-3">
                        <label for="recipient_email" class="form-label">Recipient Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="recipient_email" name="recipient_email" 
                               value="<?= escape_html($quote['customer_email'] ?? '') ?>"
                               placeholder="customer@example.com" required>
                        <small class="text-muted">Enter the email address to send the invoice to</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cc_email" class="form-label">CC Email (Optional)</label>
                        <input type="text" class="form-control" id="cc_email" name="cc_email" placeholder="cc@example.com, other@example.com">
                        <small class="text-muted">Separate multiple emails with commas</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="email_subject" name="email_subject" 
                               value="Quote Invoice - <?= escape_html($quote['quote_number']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email_message" class="form-label">Message</label>
                        <textarea class="form-control" id="email_message" name="email_message" rows="4">Dear <?= escape_html($quote['customer_name']) ?>,

Please find attached the quote invoice for <?= escape_html($quote['quote_number']) ?>.

Quote Details:
- Quote Number: <?= escape_html($quote['quote_number']) ?>
- Quote Date: <?= format_date($quote['quote_date']) ?>
<?php if (!empty($quote['valid_until'])): ?>
- Valid Until: <?= format_date($quote['valid_until']) ?>
<?php endif; ?>
- Total Amount: $<?= number_format($quote['total_amount'], 2) ?>

We look forward to your business!

Best regards,
MJR Group ERP</textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        The invoice will be sent as a formatted email.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="sendEmail()">
                    <i class="fas fa-paper-plane me-2"></i>Send Email
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, .no-print {
        display: none !important;
    }
    .container-fluid {
        width: 100% !important;
        max-width: none !important;
    }
    .card {
        border: 1px solid #dee2e6 !important;
        page-break-inside: avoid;
    }
}
</style>

<script>
function openEmailModal() {
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
}

function sendEmail() {
    const form = document.getElementById('emailForm');
    if (form.checkValidity()) {
        form.submit();
    } else {
        form.reportValidity();
    }
}

function downloadPDF() {
    window.open('print_quote.php?id=<?= $quote_id ?>', '_blank');
}
</script>

<?php include __DIR__ . '/../../templates/footer.php'; ?>
