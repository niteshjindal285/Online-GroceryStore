<?php
/**
 * Inventory - Reorder Report (Threshold + Email)
 *
 * - Shows items with available stock below a chosen threshold (default: 10)
 * - Sends the same report to manager/admin emails (users.role)
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Load PHPMailer from Composer first; fallback to local bundled copy.
$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/Exception.php';
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/PHPMailer.php';
    require_once __DIR__ . '/../../includes/PHPMailer-master/src/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_login();

if (isset($_GET['add_item'])) {
    $code = $_GET['add_item'];
    if (!isset($_SESSION['manual_reorder_items'])) {
        $_SESSION['manual_reorder_items'] = [];
    }
    if (!in_array($code, $_SESSION['manual_reorder_items'])) {
        $_SESSION['manual_reorder_items'][] = $code;
    }
    set_flash('Item added to Reorder Report.', 'success');
    redirect('reorder.php');
}

if (isset($_GET['remove_item'])) {
    $code = $_GET['remove_item'];
    if (isset($_SESSION['manual_reorder_items'])) {
        $_SESSION['manual_reorder_items'] = array_values(array_filter($_SESSION['manual_reorder_items'], function($c) use ($code) {
            return $c !== $code;
        }));
    }
    set_flash('Item removed from manual Reorder Report.', 'success');
    redirect('reorder.php');
}

if (isset($_GET['clear_manual'])) {
    unset($_SESSION['manual_reorder_items']);
    set_flash('Manual reorder list cleared.', 'info');
    redirect('reorder.php');
}

$page_title = 'Reorder Report - MJR Group ERP';
$company_id = active_company_id(1);
$errors = [];

function reorder_report_get_recipients(): array {
    $company_id = active_company_id(1);

    $rows = db_fetch_all("
        SELECT email
        FROM users
        WHERE is_active = 1
          AND email IS NOT NULL
          AND email <> ''
          AND role IN ('manager', 'admin', 'super_admin')
          AND (company_id = ? OR role IN ('admin', 'super_admin'))
        ORDER BY email
    ", [$company_id]);

    $emails = array_values(array_unique(array_map(static fn($r) => $r['email'], $rows)));
    return array_values(array_filter($emails, static fn($e) => validate_email($e)));
}

function reorder_report_get_low_stock_items(int $threshold): array {
    $company_id = active_company_id(1);
    $manual_items = $_SESSION['manual_reorder_items'] ?? [];
    $manual_list = "''";
    if (!empty($manual_items)) {
        // Sanitize and quote
        $quoted = array_map(function($c) { return "'" . addslashes(substr($c, 0, 100)) . "'"; }, $manual_items);
        $manual_list = implode(',', $quoted);
    }

    return db_fetch_all("
        SELECT src.item_code AS code,
               src.item_name AS name,
               src.category_name,
               src.location_label AS stock_by_location,
               src.qty_available AS total_available,
               src.reorder_level,
               src.reorder_quantity,
               src.unit_code
        FROM (
            -- Source 1: stock tracked in inventory_stock_levels (per location)
            SELECT i.id AS item_id,
                   i.code AS item_code,
                   i.name AS item_name,
                   c.name AS category_name,
                   l.name AS location_label,
                   COALESCE(s.quantity_available, 0) AS qty_available,
                   i.reorder_level,
                   i.reorder_quantity,
                   COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
            FROM inventory_stock_levels s
            JOIN inventory_items i ON i.id = s.item_id
            LEFT JOIN categories c ON c.id = i.category_id
            JOIN locations l ON l.id = s.location_id
            LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
            WHERE i.is_active = 1
              AND i.company_id = ?

            UNION ALL

            -- Source 2: stock only in warehouse_inventory (no linked stock_levels record)
            SELECT i.id AS item_id,
                   i.code AS item_code,
                   i.name AS item_name,
                   c.name AS category_name,
                   CONCAT(w.name, ' (Warehouse)') AS location_label,
                   SUM(wi.quantity) AS qty_available,
                   i.reorder_level,
                   i.reorder_quantity,
                   COALESCE(u.code, i.unit_of_measure, 'PCS') AS unit_code
            FROM warehouse_inventory wi
            JOIN inventory_items i ON i.id = wi.product_id
            LEFT JOIN categories c ON c.id = i.category_id
            JOIN warehouses w ON w.id = wi.warehouse_id
            LEFT JOIN units_of_measure u ON u.id = i.unit_of_measure_id
            WHERE i.is_active = 1
              AND i.company_id = ?
              AND (
                  w.location_id IS NULL
                  OR NOT EXISTS (
                      SELECT 1 FROM inventory_stock_levels s2
                      WHERE s2.item_id = wi.product_id AND s2.location_id = w.location_id
                  )
              )
            GROUP BY i.id, i.code, i.name, c.name, w.id, w.name, i.reorder_level, i.reorder_quantity, u.code, i.unit_of_measure
        ) src
        WHERE src.qty_available < ? OR src.item_code IN ($manual_list)
        ORDER BY src.qty_available ASC
    ", [$company_id, $company_id, $threshold]);
}




function reorder_report_build_email_html(array $items, int $threshold): string {
    $today = format_datetime(date('Y-m-d H:i:s'));

    if (empty($items)) {
        return "<p>No items are below <strong>{$threshold}</strong> as of {$today}.</p>";
    }

    $rows = '';
    foreach ($items as $item) {
        $rows .= '<tr>'
            . '<td><strong>' . escape_html($item['code']) . '</strong></td>'
            . '<td>' . escape_html($item['name']) . '</td>'
            . '<td>' . escape_html($item['category_name'] ?: 'Uncategorized') . '</td>'
            . '<td style="text-align:right;">' . escape_html((string)format_number($item['total_available'], 0)) . ' ' . escape_html((string)$item['unit_code']) . '</td>'
            . '<td style="text-align:right;">' . escape_html((string)format_number($item['reorder_level'], 0)) . '</td>'
            . '<td style="text-align:right;">' . escape_html((string)format_number($item['reorder_quantity'], 0)) . '</td>'
            . '<td>' . escape_html((string)$item['stock_by_location']) . '</td>'
            . '</tr>';
    }

    return '
        <p><strong>' . count($items) . '</strong> item(s) are in the report as of ' . escape_html($today) . '.</p>
        <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
            <thead>
                <tr>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Available</th>
                    <th>Reorder Level</th>
                    <th>Reorder Qty</th>
                    <th>Stock by Location</th>
                </tr>
            </thead>
            <tbody>' . $rows . '</tbody>
        </table>
    ';
}

// UPDATED: Use PHPMailer instead of mail()
function reorder_report_send_email(array $recipients, string $subject, string $htmlBody): array {
    require_once __DIR__ . '/../../includes/email_config.php';
    
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        foreach ($recipients as $email) {
            $mail->addAddress($email);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}
$threshold = (int)get('threshold', 10);
if ($threshold < 0) {
    $threshold = 10;
}

$recipients = reorder_report_get_recipients();

if (is_post()) {
    $csrf_token = post('csrf_token');
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token. Please try again.';
    }

    $threshold = (int)post('threshold', $threshold);
    if ($threshold < 0) {
        $errors[] = 'Threshold must be 0 or higher.';
        $threshold = 10;
    }

    $action = post('action');
    if (empty($errors) && $action === 'send_report') {
        if (empty($recipients)) {
            $errors[] = "No manager/admin emails found in the users table.";
        } else {
            $items = reorder_report_get_low_stock_items($threshold);
            
            $selected_codes = post('selected_items');
            // If explicit items are selected, filter by them. 
            // Otherwise, send the whole table (do not add errors, keep original $items).
            if (!empty($selected_codes) && is_array($selected_codes)) {
                $filtered_items = [];
                foreach ($items as $item) {
                     if (in_array($item['code'], $selected_codes)) {
                           $filtered_items[] = $item;
                     }
                }
                $items = $filtered_items;
            }

            if (empty($errors)) {
                $subject = "[MJR WMS] Reorder Report - " . date('Y-m-d');
                $htmlBody = reorder_report_build_email_html($items, $threshold);

                $result = reorder_report_send_email($recipients, $subject, $htmlBody);
                if ($result['success']) {
                    set_flash('Low stock report emailed to ' . count($recipients) . ' recipient(s).', 'success');
                    redirect('reorder.php?threshold=' . urlencode((string)$threshold));
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

$low_stock_items = reorder_report_get_low_stock_items($threshold);

// Fetch all items for the explicit Add Item dropdown
$all_active_items = db_fetch_all("
    SELECT code, name FROM inventory_items WHERE is_active = 1 AND company_id = ? ORDER BY code
", [$company_id]);

include __DIR__ . '/../../templates/header.php';
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><i class="fas fa-clipboard-list me-3"></i>Reorder Report</h1>
            <p class="text-muted mb-0">Creates a low-stock report (below a threshold) and emails it to managers.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="../inventory/reorder_check.php" class="btn btn-outline-secondary">
                <i class="fas fa-exclamation-triangle me-2"></i>Reorder Check
            </a>
            <a href="stock_levels.php" class="btn btn-outline-primary ms-2">
                <i class="fas fa-boxes me-2"></i>Stock Levels
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= escape_html($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-sliders-h fa-2x text-info"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Threshold</div>
                            <div class="fs-4 fw-bold"><span class="badge bg-info"><?= (int)$threshold ?></span></div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Items with available stock below this value appear in the report.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-exclamation-circle fa-2x text-warning"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Low Stock Items</div>
                            <div class="fs-4 fw-bold text-warning"><?= count($low_stock_items) ?></div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Based on current stock in all locations.</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-envelope fa-2x text-success"></i>
                        </div>
                        <div>
                            <div class="text-muted small">Email Recipients</div>
                            <div class="fs-4 fw-bold text-success"><?= count($recipients) ?></div>
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Users with role <code>manager</code> or <code>admin</code>.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <!-- Configuration section: Threshold and Send -->
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold"><i class="fas fa-magic me-2"></i>Auto Generate By Threshold</h5>
                    <form method="GET" class="row g-3 align-items-end mt-1">
                        <div class="col-sm-8 mb-2">
                            <label for="threshold" class="form-label text-muted small mb-1">Items available &lt;</label>
                            <input type="number" class="form-control" id="threshold" name="threshold" min="0" value="<?= escape_html((string)$threshold) ?>">
                        </div>
                        <div class="col-sm-4 mb-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync-alt me-2"></i>Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Configuration section: Add Item -->
        <div class="col-md-5">
            <div class="card border-info h-100">
                <div class="card-body">
                    <h5 class="card-title fw-bold text-info"><i class="fas fa-hand-pointer me-2"></i>Manually Add Items</h5>
                    <form method="GET" class="row g-3 align-items-end mt-1">
                        <input type="hidden" name="threshold" value="<?= escape_html((string)$threshold) ?>">
                        <div class="col-sm-8 mb-2">
                            <label for="add_item" class="form-label text-muted small mb-1">Select Item to Reorder</label>
                            <select id="add_item" name="add_item" class="form-select">
                                <option value="">-- Choose Item --</option>
                                <?php foreach ($all_active_items as $itm): ?>
                                    <option value="<?= escape_html($itm['code']) ?>"><?= escape_html($itm['name']) ?> (<?= escape_html($itm['code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-4 mb-2">
                            <button type="submit" class="btn btn-info text-white w-100" id="addBtn" disabled>
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
            <div class="fw-bold">
                <i class="fas fa-table me-2"></i>Items to Reorder
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (!empty($_SESSION['manual_reorder_items'])): ?>
                    <a href="reorder.php?clear_manual=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clear manual item list?')">
                        <i class="fas fa-times me-1"></i>Clear Manual Items (<?= count($_SESSION['manual_reorder_items']) ?>)
                    </a>
                <?php endif; ?>
                <form method="POST" class="m-0" id="sendReportForm">
                    <input type="hidden" name="csrf_token" value="<?= escape_html(generate_csrf_token()) ?>">
                    <input type="hidden" name="threshold" value="<?= escape_html((string)$threshold) ?>">
                    <input type="hidden" name="action" value="send_report">
                    <button type="submit" class="btn btn-success btn-sm" <?= empty($recipients) ? 'disabled' : '' ?>>
                        <i class="fas fa-paper-plane me-2"></i>Send Email Report
                    </button>
                </form>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($low_stock_items)): ?>
                <div class="table-responsive">
                    <table class="table table-striped" id="reorderReportTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" class="form-check-input" id="selectAllItems" title="Select All"></th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Available Stock</th>
                                <th>Threshold Req.</th>
                                <th>Reorder Level</th>
                                <th>Reorder Qty</th>
                                <th>Stock by Location</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($low_stock_items as $item): 
                                $is_manual = isset($_SESSION['manual_reorder_items']) && in_array($item['code'], $_SESSION['manual_reorder_items']);
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input item-select" value="<?= escape_html($item['code']) ?>">
                                    </td>
                                    <td>
                                        <strong><?= escape_html($item['code']) ?></strong>
                                        <?php if ($is_manual): ?>
                                            <span class="badge bg-info ms-1" title="Manually Added"><i class="fas fa-hand-pointer"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= escape_html($item['name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= escape_html($item['category_name'] ?: 'Uncategorized') ?></span></td>
                                    <td>
                                        <span class="badge <?= ($item['total_available'] < $threshold) ? 'bg-warning text-dark' : 'bg-primary' ?>">
                                            <?= format_number($item['total_available'], 0) ?> <?= escape_html($item['unit_code']) ?>
                                        </span>
                                    </td>
                                    <td><?= (int)$threshold ?></td>
                                    <td><?= format_number($item['reorder_level'], 0) ?></td>
                                    <td><strong><?= format_number($item['reorder_quantity'], 0) ?></strong></td>
                                    <td><small><?= escape_html($item['stock_by_location']) ?></small></td>
                                    <td class="text-center">
                                        <?php if ($is_manual): ?>
                                            <a href="reorder.php?remove_item=<?= urlencode($item['code']) ?>" class="btn btn-sm btn-outline-danger" title="Remove from list">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">Auto</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <h3>All Good!</h3>
                    <p class="text-muted mb-0">No items are below <?= (int)$threshold ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$additional_scripts = "
<script>
$(document).ready(function() {
    var table = $('#reorderReportTable').DataTable({
        'order': [[4, 'asc']], // order by available stock (adjusted for checkbox column)
        'pageLength': 50,
        'columnDefs': [
            { 'orderable': false, 'targets': 0 } // disable sorting on checkbox column
        ]
    });

    // Select All checkbox
    $('#selectAllItems').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.item-select', table.rows({ search: 'applied' }).nodes()).prop('checked', isChecked);
    });
    
    // Update Select All checkbox state when individual checkboxes change
    $('#reorderReportTable tbody').on('change', '.item-select', function() {
        var allChecked = $('.item-select:not(:checked)', table.rows({ search: 'applied' }).nodes()).length === 0;
        $('#selectAllItems').prop('checked', allChecked);
    });

    $('#sendReportForm').on('submit', function(e) {
        // Remove old hidden inputs
        $(this).find('input[name=\"selected_items[]\"]').remove();
        
        var checkedItems = $('.item-select:checked', table.rows().nodes());
        
        // If some items are selected, add them as hidden inputs.
        // If none are selected, we submit normally without specific items, 
        // and the backend will include the entire table.
        if (checkedItems.length > 0) {
            checkedItems.each(function() {
                $('<input>').attr({
                    type: 'hidden',
                    name: 'selected_items[]',
                    value: $(this).val()
                }).appendTo('#sendReportForm');
            });
        }
    });

    $('#add_item').on('change', function() {
        if ($(this).val()) {
            $('#addBtn').prop('disabled', false);
        } else {
            $('#addBtn').prop('disabled', true);
        }
    });

    if ($.fn.select2) {
        $('#add_item').select2({
            theme: 'bootstrap-5',
            placeholder: '-- Choose Item --'
        });
    }
});
</script>
";

include __DIR__ . '/../../templates/footer.php';
?>



