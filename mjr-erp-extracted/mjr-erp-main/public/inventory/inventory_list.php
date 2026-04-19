<?php
/**
 * Inventory List Page with Export Functionality - NO CSV
 * 
 * Export options: Excel, PDF, Word, Email only
 */

require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();

$user = current_user();
$company_id = $user['company_id'];

// Get filters
$search = get_param('search', '');
$category_id = get_param('category_id', '');
$low_stock = get_param('low_stock', '');

// Build query - CORRECTED FOR YOUR DATABASE
$sql = "SELECT 
    ii.id,
    ii.code AS item_code,
    ii.name AS item_name,
    ii.description,
    c.name AS category,
    COALESCE(SUM(isl.quantity_on_hand), 0) AS quantity,
    ii.unit_of_measure AS unit,
    ii.cost_price,
    ii.average_cost,
    ii.selling_price,
    (COALESCE(SUM(isl.quantity_on_hand), 0) * ii.average_cost) AS total_value
FROM 
    inventory_items ii
LEFT JOIN 
    categories c ON ii.category_id = c.id
LEFT JOIN 
    inventory_stock_levels isl ON ii.id = isl.item_id
WHERE 
    ii.is_active = 1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (ii.name LIKE ? OR ii.code LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($category_id)) {
    $sql .= " AND ii.category_id = ?";
    $params[] = $category_id;
}

$sql .= " GROUP BY ii.id";

if (!empty($low_stock)) {
    $sql .= " HAVING quantity <= ii.reorder_level";
}

$sql .= " ORDER BY ii.name";

$inventory = db_fetch_all($sql, $params);

// Get categories for filter
$categories = db_fetch_all("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name", []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory List - ERP System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    
    <style>
        .export-buttons {
            margin-bottom: 20px;
        }
        .low-stock {
            background-color: #fff3cd;
        }
        .out-of-stock {
            background-color: #f8d7da;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .page-header {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h2>Inventory Management</h2>
                </div>
                
                <!-- Flash Messages -->
                <?php if ($flash = get_flash()): ?>
                    <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        <?php echo escape_html($flash['message']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="" class="form-inline">
                        <div class="form-group">
                            <label class="sr-only">Search</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search items..." 
                                   value="<?php echo escape_html($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="sr-only">Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                            <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo escape_html($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="checkbox-inline">
                                <input type="checkbox" name="low_stock" value="1" 
                                       <?php echo $low_stock ? 'checked' : ''; ?>>
                                Low Stock Only
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        
                        <a href="inventory_list.php" class="btn btn-default">
                            <i class="fa fa-refresh"></i> Reset
                        </a>
                    </form>
                </div>
                
                <!-- Action Buttons -->
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-6">
                        <a href="add_inventory.php" class="btn btn-success">
                            <i class="fa fa-plus"></i> Add New Item
                        </a>
                    </div>
                    
                    <div class="col-md-6 text-right">
                        <!-- Export Dropdown - NO CSV -->
                        <div class="btn-group export-buttons">
                            <button type="button" class="btn btn-info dropdown-toggle" 
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-download"></i> Export Report <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li>
                                    <a href="#" onclick="exportReport('excel'); return false;">
                                        <i class="fa fa-file-excel-o text-success"></i> Export to Excel
                                    </a>
                                </li>
                                <li>
                                    <a href="#" onclick="exportReport('pdf'); return false;">
                                        <i class="fa fa-file-pdf-o text-danger"></i> Export to PDF
                                    </a>
                                </li>
                                <li>
                                    <a href="#" onclick="exportReport('word'); return false;">
                                        <i class="fa fa-file-word-o text-primary"></i> Export to Word
                                    </a>
                                </li>
                                <li role="separator" class="divider"></li>
                                <li>
                                    <a href="#" onclick="showEmailModal(); return false;">
                                        <i class="fa fa-envelope"></i> Email Report
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Inventory Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Supplier Cost</th>
                                <th>Avg Cost</th>
                                <th>Selling Price</th>
                                <th>Total Value</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No inventory items found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($inventory as $item): ?>
                                    <?php 
                                        $rowClass = '';
                                        $statusLabel = 'label-success';
                                        $statusText = 'In Stock';
                                        
                                        if ($item['quantity'] == 0) {
                                            $rowClass = 'out-of-stock';
                                            $statusLabel = 'label-danger';
                                            $statusText = 'Out of Stock';
                                        } elseif ($item['quantity'] <= $item['reorder_level']) {
                                            $rowClass = 'low-stock';
                                            $statusLabel = 'label-warning';
                                            $statusText = 'Low Stock';
                                        }
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td><?php echo escape_html($item['item_code']); ?></td>
                                        <td><strong><?php echo escape_html($item['item_name']); ?></strong></td>
                                        <td><?php echo escape_html($item['category'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo number_format($item['quantity']); ?></td>
                                        <td><?php echo escape_html($item['unit'] ?? 'PCS'); ?></td>
                                        <td><?php echo format_currency($item['cost_price']); ?></td>
                                        <td class="text-info"><?php echo format_currency($item['average_cost']); ?></td>
                                        <td><?php echo format_currency($item['selling_price']); ?></td>
                                        <td><?php echo format_currency($item['total_value']); ?></td>
                                        <td><?php echo number_format($item['reorder_level']); ?></td>
                                        <td>
                                            <span class="label <?php echo $statusLabel; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="edit_inventory.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-xs btn-primary" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="view.php?id=<?php echo $item['id']; ?>" 
                                               class="btn btn-xs btn-info" title="View">
                                                <i class="fa fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($inventory)): ?>
                        <tfoot>
                            <tr class="info">
                                <td colspan="3"><strong>Total:</strong></td>
                                <td><strong><?php echo number_format(array_sum(array_column($inventory, 'quantity'))); ?></strong></td>
                                <td colspan="2"></td>
                                <td><strong><?php echo format_currency(array_sum(array_column($inventory, 'total_value'))); ?></strong></td>
                                <td colspan="3"></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Email Modal -->
    <div class="modal fade" id="emailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-envelope"></i> Email Inventory Report</h4>
                </div>
                <form id="emailForm" method="POST" action="export_inventory.php?format=email">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Email Address: <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo escape_html($user['email']); ?>" 
                                   placeholder="recipient@example.com" required>
                            <small class="help-block">Enter the email address to send the report to</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Report Format: <span class="text-danger">*</span></label>
                            <select name="email_format" class="form-control" required>
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="pdf">PDF (.pdf)</option>
                                <option value="word">Word (.docx)</option>
                            </select>
                            <small class="help-block">Choose the format for the attachment</small>
                        </div>
                        
                        <!-- Include current filters -->
                        <input type="hidden" name="category_id" value="<?php echo escape_html($category_id); ?>">
                        <input type="hidden" name="search" value="<?php echo escape_html($search); ?>">
                        <input type="hidden" name="low_stock" value="<?php echo escape_html($low_stock); ?>">
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i> 
                            The report will include <?php echo count($inventory); ?> items with current filters applied.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            <i class="fa fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-paper-plane"></i> Send Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>
    
    <script>
        // Export report function
        function exportReport(format) {
            // Build URL with current filters
            var url = 'export_inventory.php?format=' + format;
            
            // Add current filter values
            var params = new URLSearchParams(window.location.search);
            params.forEach(function(value, key) {
                if (key !== 'format') {
                    url += '&' + key + '=' + encodeURIComponent(value);
                }
            });
            
            // Trigger download
            window.location.href = url;
        }
        
        // Show email modal
        function showEmailModal() {
            $('#emailModal').modal('show');
        }
        
        // Form validation
        $('#emailForm').on('submit', function() {
            var email = $('input[name="email"]').val();
            if (!email || email.trim() === '') {
                alert('Please enter a valid email address');
                return false;
            }
            return true;
        });
    </script>
</body>
</html>
