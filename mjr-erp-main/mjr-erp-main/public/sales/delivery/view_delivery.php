<?php
/**
 * View Delivery / Print Delivery Note
 */
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_login();

$id = intval(get('id'));
$sched = db_fetch("SELECT ds.*, i.invoice_number, c.name AS customer_name, i.total_amount
    FROM delivery_schedule ds
    JOIN invoices i ON i.id = ds.invoice_id
    JOIN customers c ON c.id = i.customer_id
    WHERE ds.id=?", [$id]);
if (!$sched) { set_flash('Not found.', 'error'); redirect('index.php'); }

$notes_list = db_fetch_all("SELECT dn.*, u.username AS by_name FROM delivery_notes dn
    JOIN users u ON u.id = dn.created_by
    WHERE dn.delivery_schedule_id=? ORDER BY dn.created_at DESC", [$id]);

$page_title = 'Delivery — ' . $sched['invoice_number'];
include __DIR__ . '/../../../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-truck me-2"></i>Delivery for <?= escape_html($sched['invoice_number']) ?></h2>
    <div class="d-flex gap-2">
        <?php if ($sched['status'] !== 'delivered'): ?>
        <a href="add_delivery.php?delivery_id=<?= $id ?>" class="btn btn-success"><i class="fas fa-truck me-1"></i>Process More</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h6>Invoice</h6><p class="fw-bold"><?= escape_html($sched['invoice_number']) ?></p>
                <h6>Customer</h6><p><?= escape_html($sched['customer_name']) ?></p>
                <h6>Status</h6>
                <span class="badge bg-<?= $sched['status']==='delivered'?'success':($sched['status']==='partial'?'warning text-dark':'info') ?> fs-6">
                    <?= ucfirst($sched['status']) ?>
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Delivery Notes History</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Note #</th><th>Date</th><th>Driver</th><th>By</th><th>Print</th></tr></thead>
                    <tbody>
                        <?php foreach ($notes_list as $dn): ?>
                        <tr>
                            <td><code><?= escape_html($dn['delivery_number']) ?></code></td>
                            <td><?= format_date($dn['delivery_date']) ?></td>
                            <td><?= escape_html($dn['driver_name'] ?? '—') ?></td>
                            <td><?= escape_html($dn['by_name']) ?></td>
                            <td><a href="print_delivery_note.php?id=<?= $dn['id'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notes_list)): ?><tr><td colspan="5" class="text-center text-muted py-3">No delivery notes yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../../templates/footer.php'; ?>
