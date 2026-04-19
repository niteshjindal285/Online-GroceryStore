<!DOCTYPE html>
<html lang="en" data-bs-theme="dark" data-app-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape_html($page_title ?? 'MJR Group ERP System') ?></title>

    <!-- Bootstrap CSS - Standard version with theme support -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <link href="<?= asset('css/custom.css') ?>" rel="stylesheet">

    <!-- Flatpickr (Datepicker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <!-- Theme initialization script (run before body to prevent flash) -->
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
            document.documentElement.setAttribute('data-app-theme', savedTheme);
        })();
    </script>
</head>

<body>

    <?php if (is_logged_in()): ?>
        <?php
        // Stock Take Locking Logic
        require_once __DIR__ . '/../includes/inventory_transaction_service.php';
        $active_stock_take = inventory_get_active_stock_take();
        $should_freeze = ($active_stock_take);

        // Fetch Pending Approvals for Manager Notifications
        $pending_approvals = get_pending_approvals(current_user_id());
        $approval_count = count($pending_approvals);

        // Load current company name
        $_current_company_id = $_SESSION['company_id'] ?? null;
        $_current_company_name = $_SESSION['company_name'] ?? null;
        if ($_current_company_id && !$_current_company_name) {
            $_co = db_fetch("SELECT name FROM companies WHERE id = ? LIMIT 1", [$_current_company_id]);
            $_current_company_name = $_SESSION['company_name'] = $_co['name'] ?? 'MJR Group';
        }
        // Load all companies for switcher (all admins)
        $_all_companies = is_admin() ? db_fetch_all("SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name") : [];
        ?>

        <?php if ($should_freeze): ?>
            <style>
                .stock-take-banner {
                    background: linear-gradient(90deg, #ffc107 0%, #ff9800 100%);
                    color: #000;
                    text-align: center;
                    padding: 8px;
                    font-weight: bold;
                    z-index: 1050;
                    position: relative;
                }
            </style>
            <div class="stock-take-banner shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i>
                SYSTEM LOCKED: Physical Stock Take is in progress for
                <strong><?= escape_html($active_stock_take['warehouse_name']) ?></strong>
                (<?= escape_html($active_stock_take['stock_take_number']) ?>).
                Only stock-related <strong>Create</strong> and <strong>Edit</strong> options for this warehouse are locked.
                <a href="<?= url('inventory/stock_take/view.php?id=' . $active_stock_take['id']) ?>"
                    class="btn btn-sm btn-dark ms-3 fw-bold">View Stock Take</a>
            </div>
        <?php endif; ?>

        <style>
            .top-brand-bar {
                background: #1f2737;
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
                padding: .55rem .75rem;
                display: flex;
                justify-content: center;
                align-items: center;
                position: relative;
                z-index: 1045;
            }

            .top-brand-wrap {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                line-height: 1.1;
                text-align: center;
            }

            .top-brand-app {
                color: #eef3ff;
                font-weight: 500;
                font-size: 2rem;
                text-decoration: none;
            }

            .top-brand-company {
                color: #ffc61a;
                font-weight: 800;
                font-size: 1.95rem;
                margin-top: .08rem;
            }

            .top-brand-company .dropdown-toggle::after {
                vertical-align: middle;
            }

            html[data-bs-theme="dark"] .top-brand-bar {
                background: #1f2737;
                border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            }

            html[data-bs-theme="dark"] .top-brand-app {
                color: #eef3ff;
            }

            html[data-bs-theme="dark"] .top-brand-company {
                color: #ffc61a;
            }

            html[data-bs-theme="light"] .top-brand-bar {
                background: #f8f9fa;
                border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            }

            html[data-bs-theme="light"] .top-brand-app {
                color: #212529;
            }

            html[data-bs-theme="light"] .top-brand-company {
                color: #0d6efd;
            }

            @media (max-width: 768px) {
                .top-brand-app {
                    font-size: 1.6rem;
                }

                .top-brand-company {
                    font-size: 1.5rem;
                }
            }
        </style>

        <div class="top-brand-bar">
            <div class="top-brand-wrap">
                <a class="top-brand-app" href="<?= url('index.php') ?>">
                    <i class="fas fa-industry me-2"></i>MJR Group ERP
                </a>
                <?php if ($_current_company_name): ?>
                    <?php if (is_admin() && !empty($_all_companies)): ?>
                        <div class="dropdown">
                            <button class="btn btn-link p-0 text-decoration-none top-brand-company dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-building me-5"></i><?= escape_html($_current_company_name) ?>
                            </button>
                            <ul class="dropdown-menu shadow">
                                <li>
                                    <h6 class="dropdown-header"><i class="fas fa-exchange-alt me-2"></i>Switch Company</h6>
                                </li>
                                <?php foreach ($_all_companies as $_co): ?>
                                    <li>
                                        <form method="POST" action="<?= url('auth/switch_company.php') ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                                            <input type="hidden" name="company_id" value="<?= $_co['id'] ?>">
                                            <button type="submit"
                                                class="dropdown-item <?= $_co['id'] == $_current_company_id ? 'active fw-bold' : '' ?>">
                                                <?php if ($_co['id'] == $_current_company_id): ?><i
                                                        class="fas fa-check me-2 text-success"></i><?php else: ?><i
                                                        class="fas fa-building me-2 text-muted"></i><?php endif; ?>
                                                <?= escape_html($_co['name']) ?>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php else: ?>
                        <div class="top-brand-company">
                            <i class="fas fa-building me-1"></i><?= escape_html($_current_company_name) ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg" id="mainNavbar">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= url('index.php') ?>">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </li>

                        <?php if (has_permission('view_inventory')): ?>
                            <li class="nav-item dropdown stock-take-nav">
                                <a class="nav-link dropdown-toggle" href="#" id="inventoryDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-boxes me-1"></i>Inventory
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="inventoryDropdown">
                                    <li><a class="dropdown-item" href="<?= url('inventory/dashboard.php') ?>">Inventory
                                            Dashboard</a></li>
                                    <li><a class="dropdown-item text-info fw-bold"
                                            href="<?= url('inventory/index.php') ?>"><i
                                                class="fas fa-sliders-h me-2"></i>Item Menu</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('inventory/purchase_order/purchase_orders.php') ?>">Purchace Order</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/gsrn/index.php') ?>">STOCK ENTRY </a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/stock_report.php') ?>">Stock
                                            Report</a></li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/transfer_history.php') ?>">Stock
                                            Transfer </a></li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/price_change_history.php') ?>">Price
                                            Change</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('inventory/supplier/suppliers.php') ?>">Suppliers</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('inventory/customer/customers.php') ?>">Customers</a></li>

                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item stock-take-item"
                                            href="<?= url('inventory/stock_take/index.php') ?>"><i
                                                class="fas fa-clipboard-check me-2 text-warning"></i>Stock Take (Physical
                                            Count)</a></li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/backlog_orders.php') ?>"><i
                                                class="fas fa-layer-group me-2 text-warning"></i>Backlog Orders</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('view_finance')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="financeDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-chart-line me-1"></i>Finance
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="financeDropdown">
                                    <li><a class="dropdown-item fw-bold text-info"
                                            href="<?= url('finance/index.php') ?>">Finance Dashboard</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>

                                    <li><a class="dropdown-item" href="<?= url('finance/accounts.php') ?>">Chart of Accounts</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('finance/general_ledger.php') ?>">General
                                            Ledger</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/journal_entries.php') ?>">Journal
                                            Entries</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>

                                    <li><a class="dropdown-item" href="<?= url('finance/payment_vouchers.php') ?>">Payment
                                            Vouchers</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/receipts.php') ?>">Receipts</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('finance/debit_credit_notes.php') ?>">Debit/Credit Notes</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/project_expenses.php') ?>">Project
                                            Expenses</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>

                                    <li><a class="dropdown-item" href="<?= url('finance/banking_deposits.php') ?>">Banking /
                                            Deposits</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/bank_reconciliation.php') ?>">Bank
                                            Reconciliation</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/payroll.php') ?>">Payroll Processing</a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>

                                    <li><a class="dropdown-item" href="<?= url('finance/tax_classes.php') ?>">Tax Classes</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('finance/trial_balance.php') ?>">Trial
                                            Balance</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/income_statement.php') ?>">Income
                                            Statement</a></li>
                                    <li><a class="dropdown-item" href="<?= url('finance/balance_sheet.php') ?>">Balance
                                            Sheet</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('view_sales')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="salesDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-shopping-cart me-1"></i>Sales
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="salesDropdown">
                                    <li><a class="dropdown-item" href="<?= url('sales/index.php') ?>"><i
                                                class="fas fa-tachometer-alt me-2 text-primary"></i>Sales Dashboard</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('sales/debtors/index.php') ?>"><i
                                                class="fas fa-users me-2 text-info"></i>Debtors (Credit Control)</a></li>
                                    <li><a class="dropdown-item" href="<?= url('sales/invoices/index.php') ?>"><i
                                                class="fas fa-file-invoice-dollar me-2 text-success"></i>Invoices</a></li>
                                    <li><a class="dropdown-item" href="<?= url('sales/delivery/index.php') ?>"><i
                                                class="fas fa-truck me-2 text-warning"></i>Delivery Schedule</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('sales/orders.php') ?>"><i
                                                class="fas fa-shopping-cart me-2"></i>Sales Orders</a></li>
                                    <li><a class="dropdown-item" href="<?= url('sales/quotes.php') ?>"><i
                                                class="fas fa-file-contract me-2"></i>Quotes & Estimates</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('sales/discounts/index.php') ?>"><i
                                                class="fas fa-tags me-2 text-purple"></i>Discounts & Offers</a></li>
                                    <?php if (is_admin() || (isset($_SESSION['role']) && $_SESSION['role'] === 'manager')): ?>
                                        <li><a class="dropdown-item" href="<?= url('sales/discounts/order_dashboard.php') ?>"><i
                                                    class="fas fa-check-double me-2 text-warning"></i>Discount Approval</a></li>
                                    <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?= url('sales/price_changes/index.php') ?>"><i
                                                class="fas fa-dollar-sign me-2 text-danger"></i>Price Changes</a></li>
                                    <li><a class="dropdown-item" href="<?= url('sales/returns/index.php') ?>"><i
                                                class="fas fa-undo me-2 text-orange"></i>Sales Returns</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('sales/reports/index.php') ?>"><i
                                                class="fas fa-chart-bar me-2 text-info"></i>Sales Reports</a></li>

                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('view_master_config')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="procurementDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    Master Config
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="procurementDropdown">
                                    <li><a class="dropdown-item fw-bold text-info"
                                            href="<?= url('master_config/dashboard.php') ?>">Master Config Dashboard</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/index.php') ?>">Items</a></li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/locations.php') ?>">Locations</a></li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/categories.php') ?>">Categories</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('inventory/stock_levels.php') ?>">Stock
                                            Levels</a></li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('inventory/transactions.php') ?>">Transactions</a></li>
                                            <?php if (has_permission('manage_inventory')): ?>
                                        <li><a class="dropdown-item" href="<?= url('inventory/create.php') ?>">Add Item</a></li>
                                            <?php endif; ?>
                                    <li><a class="dropdown-item" href="<?= url('inventory/reorder.php') ?>">Reorder Report</a>
                                    </li>
                                    <li><a class="dropdown-item"
                                            href="<?= url('inventory/modules/warehouses/index.php') ?>">Warehouse</a></li>
                                </ul>
                            </li>
                            <?php endif; ?>

                        <?php if (has_permission('view_production')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="productionDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-cogs me-1"></i>Production
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="productionDropdown">
                                    <li><a class="dropdown-item" href="<?= url('production/add_production_order.php') ?>"><i
                                                class="fas fa-plus-circle me-2 text-primary"></i> Production Order</a></li>
                                    <li><a class="dropdown-item" href="<?= url('production/bom_management.php') ?>"><i
                                                class="fas fa-layer-group me-2 text-info"></i> BOM Management</a></li>
                                    <li><a class="dropdown-item" href="<?= url('production/bom_quantity_manager.php') ?>"><i
                                                class="fas fa-sliders-h me-2 text-secondary"></i> BOM Quantity Manager</a></li>
                                    <li><a class="dropdown-item" href="<?= url('production/production_report.php') ?>"><i
                                                class="fas fa-chart-line me-2 text-warning"></i> Production Report</a></li>
                                    <li><a class="dropdown-item" href="<?= url('production/production_orders.php') ?>"><i
                                                class="fas fa-tasks me-2 text-success"></i> Production Status</a></li>
                                    <li><a class="dropdown-item" href="<?= url('production/gsrn_entry.php') ?>"><i
                                                class="fas fa-boxes me-2 text-danger"></i> Stock Entry</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>



                        <?php if (has_permission('view_production')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="mrpDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-calendar-alt me-1"></i>MRP
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="mrpDropdown">
                                    <li><a class="dropdown-item" href="<?= url('mrp/master_schedule.php') ?>">Master
                                            Schedule</a></li>
                                    <li><a class="dropdown-item" href="<?= url('mrp/material_requirements.php') ?>">Material
                                            Requirements</a></li>
                                    <li><a class="dropdown-item" href="<?= url('mrp/planned_orders.php') ?>">Planned Orders</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('view_analytics')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="analyticsDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-chart-bar me-1"></i>Analytics
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="analyticsDropdown">
                                    <li><a class="dropdown-item" href="<?= url('analytics/index.php') ?>">Analytics
                                            Dashboard</a></li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('analytics/sales.php') ?>">Sales Analytics</a>
                                    </li>
                                    <li><a class="dropdown-item" href="<?= url('analytics/inventory.php') ?>">Inventory
                                            Analytics</a></li>
                                    <li><a class="dropdown-item" href="<?= url('analytics/financial.php') ?>">Financial
                                            Analytics</a></li>
                                    <li><a class="dropdown-item" href="<?= url('analytics/production.php') ?>">Production
                                            Analytics</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (has_permission('view_projects')): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="projectsDropdown" role="button"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-project-diagram me-1"></i>Projects
                                </a>
                                <ul class="dropdown-menu" aria-labelledby="projectsDropdown">
                                    <li><a class="dropdown-item" href="<?= url('projects/index.php') ?>"><i
                                                class="fas fa-list me-2 text-primary"></i>All Projects</a></li>
                                    <?php if (has_permission('manage_projects')): ?>
                                        <li>
                                            <hr class="dropdown-divider">
                                        </li>
                                        <li><a class="dropdown-item" href="<?= url('projects/create.php') ?>"><i
                                                    class="fas fa-plus me-2 text-success"></i>New Project</a></li>
                                    <?php endif; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <?php if (is_admin()): ?>
                            <?php
                            $pending_count = db_fetch("SELECT COUNT(*) as c FROM permission_requests WHERE status = 'pending'")['c'] ?? 0;
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= url('companies/index.php') ?>">
                                    <i class="fas fa-building me-1"></i>Companies
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link position-relative" href="<?= url('admin/permissions.php') ?>">
                                    <i class="fas fa-user-lock me-1"></i>Permissions
                                    <?php if ($pending_count > 0): ?>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                            style="font-size: 0.6rem;">
                                            <?= $pending_count ?>
                                        </span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>

                    <ul class="navbar-nav">
                        <!-- Voice Command Button -->
                        <!-- <li class="nav-item d-flex align-items-center me-1">
                        <button id="voiceBtn"
                                class="btn btn-sm btn-outline-danger rounded-pill"
                                onclick="startVoice()"
                                title="Voice Command — click and speak (Ctrl+Shift+V)">
                            <i class="fas fa-microphone" id="voiceIcon"></i>
                        </button>
                    </li> -->
                        <!-- Notification Dropdown -->
                        <li class="nav-item dropdown me-2">
                            <a class="nav-link" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <div class="position-relative">
                                    <i class="fas fa-bell fs-5"></i>
                                    <?php if ($approval_count > 0): ?>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-2 border-dark"
                                            style="font-size: 0.65rem; padding: 0.25em 0.5em;">
                                            <?= $approval_count ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg py-0 overflow-hidden"
                                aria-labelledby="notificationDropdown"
                                style="width: 320px; border: 1px solid rgba(255,255,255,0.1); background: #1a1a27;">
                                <li
                                    class="dropdown-header py-3 border-bottom border-secondary border-opacity-25 bg-dark bg-opacity-50">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 text-white fw-bold">Pending Approvals</h6>
                                        <span class="badge bg-warning text-dark small"><?= $approval_count ?> New</span>
                                    </div>
                                </li>
                                <div class="notification-list" style="max-height: 400px; overflow-y: auto;">
                                    <?php if ($approval_count > 0): ?>
                                        <?php foreach ($pending_approvals as $app): ?>
                                            <li>
                                                <?php $notification_params = $app['params'] ?? ['id' => $app['id']]; ?>
                                                <a class="dropdown-item py-3 border-bottom border-secondary border-opacity-10"
                                                    href="<?= url($app['view_url'], $notification_params) ?>">
                                                    <div class="d-flex align-items-start">
                                                        <div class="bg-primary bg-opacity-10 p-2 rounded-circle me-3">
                                                            <i class="fas <?= match ($app['type']) {
                                                                'GSRN' => 'fa-file-invoice',
                                                                'Purchase Order' => 'fa-shopping-cart',
                                                                'Stock Transfer' => 'fa-exchange-alt',
                                                                'Backlog Order' => 'fa-layer-group',
                                                                'Stock Take' => 'fa-clipboard-check',
                                                                'Permission Request' => 'fa-user-lock',
                                                                default => 'fa-check-circle'
                                                            } ?> text-primary"></i>
                                                        </div>
                                                        <div class="flex-grow-1 overflow-hidden">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <strong
                                                                    class="text-white small"><?= escape_html($app['type']) ?></strong>
                                                                <small class="text-muted"
                                                                    style="font-size: 0.7rem;"><?= format_date($app['created_at'], 'd M') ?></small>
                                                            </div>
                                                            <p class="mb-0 text-muted small text-truncate">Pending approval for
                                                                <strong><?= escape_html($app['reference']) ?></strong>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="p-4 text-center">
                                            <div class="text-muted opacity-25 mb-2">
                                                <i class="fas fa-bell-slash fs-1"></i>
                                            </div>
                                            <p class="text-muted small mb-0">No pending approvals at the moment.</p>
                                        </li>
                                    <?php endif; ?>
                                </div>
                                <?php if ($approval_count > 0): ?>
                                    <li class="bg-dark bg-opacity-25 py-2">
                                        <a class="dropdown-item text-center text-info small fw-bold"
                                            href="<?= url('index.php') ?>">
                                            Check Dashboard <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <button class="btn btn-link nav-link" id="themeToggle" title="Toggle Dark/Light Mode">
                                <i class="fas fa-sun" id="themeIconLight" style="display: none;"></i>
                                <i class="fas fa-moon" id="themeIconDark"></i>
                            </button>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i><?= escape_html($_SESSION['username']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?= url('auth/edit_profile.php') ?>"><i
                                            class="fas fa-user-edit me-2"></i>Edit Profile</a></li>
                                <li><a class="dropdown-item" href="<?= url('auth/change_password.php') ?>"><i
                                            class="fas fa-key me-2"></i>Change Password</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?= url('logout.php') ?>"><i
                                            class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Flash Messages -->
    <?php $flash = get_flash(); ?>
    <?php if ($flash): ?>
        <div class="container mt-3">
            <div class="alert alert-<?= $flash['type'] == 'error' ? 'danger' : escape_html($flash['type']) ?> alert-dismissible fade show"
                role="alert">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    <!-- Permission Request Notifications for Users -->
    <?php if (is_logged_in() && !is_admin()): ?>
        <?php
        $uid = current_user_id();
        if (is_post() && verify_csrf_token(post('csrf_token'))) {
            if (post('action') === 'mark_permission_notifications_read') {
                mark_permission_request_updates_as_seen($uid);
                redirect(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: url('index.php'));
            }

            if (post('action') === 'mark_permission_notification_read') {
                mark_permission_request_update_as_seen($uid, (int) post('request_id'));
                redirect(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: url('index.php'));
            }
        }

        $recent_updates = get_permission_request_updates($uid);

        // If any were approved, refresh the session permissions cache immediately
        $has_approved = false;
        foreach ($recent_updates as $u)
            if ($u['status'] == 'approved')
                $has_approved = true;
        if ($has_approved)
            refresh_user_permissions();
        ?>
        <?php if (!empty($recent_updates)): ?>
            <div class="container mt-2">
                <div class="d-flex justify-content-end">
                    <form method="POST" class="mb-1">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="mark_permission_notifications_read">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-check-double me-1"></i>Mark All As Read
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        <?php foreach ($recent_updates as $upd): ?>
            <div class="container mt-2">
                <div class="alert alert-<?= $upd['status'] == 'approved' ? 'success' : 'info' ?> fade show border-start border-4 d-flex justify-content-between align-items-start gap-3"
                    role="alert">
                    <div class="flex-grow-1">
                        <i class="fas <?= $upd['status'] == 'approved' ? 'fa-check-circle' : 'fa-info-circle' ?> me-2"></i>
                        Your request for <strong><?= escape_html($upd['perm_name']) ?></strong> has been
                        <strong><?= strtoupper($upd['status']) ?></strong> by the Admin.
                        <?php if ($upd['admin_notes']): ?>
                            <br><small><em>Note: <?= escape_html($upd['admin_notes']) ?></em></small>
                        <?php endif; ?>
                    </div>
                    <form method="POST" class="ms-auto">
                        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                        <input type="hidden" name="action" value="mark_permission_notification_read">
                        <input type="hidden" name="request_id" value="<?= (int) $upd['id'] ?>">
                        <button type="submit" class="btn-close" aria-label="Dismiss notification"></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>


    <!-- Voice Command Overlay – Floating Bottom Bar -->
    <div id="voiceOverlay"
        style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:rgba(20,20,30,.95);z-index:9999;flex-direction:row;align-items:center;justify-content:space-between;gap:20px;padding:12px 24px;border-radius:50px;box-shadow:0 8px 32px rgba(0,0,0,0.5);border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(10px);min-width:350px;">

        <!-- LISTENING state: animated colour wave bars -->
        <div id="vcListeningState" style="display:flex;flex-direction:row;align-items:center;gap:15px;width:100%;">
            <div class="vc-waves" style="height:24px;">
                <div class="vc-bar" style="background:#4285F4;animation-delay:0s;width:4px;"></div>
                <div class="vc-bar" style="background:#EA4335;animation-delay:.15s;width:4px;"></div>
                <div class="vc-bar" style="background:#FBBC05;animation-delay:.3s;width:4px;"></div>
                <div class="vc-bar" style="background:#34A853;animation-delay:.45s;width:4px;"></div>
            </div>
            <div id="voiceTranscript" class="text-warning fw-bold text-truncate"
                style="flex:1;font-size:1.1rem;max-width:400px;text-shadow:0 0 10px rgba(251,188,5,.3);">Listening...
            </div>
        </div>

        <!-- PROCESSING state: bouncing colour dots -->
        <div id="vcProcessingState" style="display:none;flex-direction:row;align-items:center;gap:15px;width:100%;">
            <div class="vc-dots" style="gap:6px;">
                <div class="vc-dot" style="background:#4285F4;animation-delay:0s;width:10px;height:10px;"></div>
                <div class="vc-dot" style="background:#EA4335;animation-delay:.2s;width:10px;height:10px;"></div>
                <div class="vc-dot" style="background:#FBBC05;animation-delay:.4s;width:10px;height:10px;"></div>
                <div class="vc-dot" style="background:#34A853;animation-delay:.6s;width:10px;height:10px;"></div>
            </div>
            <div id="vcProcessingText" class="text-info fw-bold text-truncate"
                style="flex:1;font-size:1.1rem;max-width:400px;text-shadow:0 0 10px rgba(66,133,244,.3);">Processing...
            </div>
        </div>

        <button class="btn btn-outline-light btn-sm rounded-circle" onclick="stopVoice(true)" title="Stop listening"
            style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Voice Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index:10000;">
        <div id="voiceToast" class="toast align-items-center border-0" role="alert" aria-live="assertive">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="voiceToastMsg"></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <style>
        /* Sound wave bars — listening */
        .vc-waves {
            display: flex;
            align-items: center;
            gap: 4px;
            height: 24px;
        }

        .vc-bar {
            width: 4px;
            border-radius: 2px;
            animation: vcWave .9s ease-in-out infinite alternate;
        }

        @keyframes vcWave {
            0% {
                height: 6px;
                opacity: .45;
            }

            100% {
                height: 24px;
                opacity: 1;
            }
        }

        /* Bouncing dots — processing */
        .vc-dots {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vc-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            animation: vcBounce .8s ease-in-out infinite alternate;
        }

        @keyframes vcBounce {
            0% {
                transform: translateY(0) scale(1);
                opacity: .5;
            }

            100% {
                transform: translateY(-8px) scale(1.2);
                opacity: 1;
            }
        }

        /* Mic button active */
        #voiceBtn.vc-on {
            background: #dc3545 !important;
            border-color: #dc3545 !important;
            color: #fff !important;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);
        }
    </style>

    <script>
        (function () {
            // ── Base URL: derived from current page (avoids https/cert issues) ──
            const B = window.location.protocol + '//' + window.location.host +
                window.location.pathname.replace(/\/public\/.*/i, '/public/');

            // ── Project Data lookup (Injected from DB) ─────────────────────────
            const PROJECT_DATA = {
                customers: <?= json_encode(is_logged_in() ? db_fetch_all("SELECT id, name FROM customers WHERE is_active = 1 LIMIT 50") : []) ?>,
                items: <?= json_encode(is_logged_in() ? db_fetch_all("SELECT id, name, selling_price FROM inventory_items WHERE is_active = 1 LIMIT 50") : []) ?>,
                locations: <?= json_encode(is_logged_in() ? db_fetch_all("SELECT id, name FROM locations WHERE is_active = 1 LIMIT 20") : []) ?>
            };

            // ── Navigation command map ─────────────────────────────────────────
            const CMDS = {
                'dashboard': B + 'index.php',
                'home': B + 'index.php',
                // Inventory
                'inventory': B + 'inventory/index.php',
                'inventory dashboard': B + 'inventory/dashboard.php',
                'items': B + 'inventory/index.php',
                'stock levels': B + 'inventory/stock_levels.php',
                'locations': B + 'inventory/locations.php',
                'categories': B + 'inventory/categories.php',
                'transactions': B + 'inventory/transactions.php',
                'add item': B + 'inventory/create.php',
                'reorder': B + 'inventory/reorder.php',
                'reorder report': B + 'inventory/reorder.php',
                // Finance
                'finance': B + 'finance/index.php',
                'finance dashboard': B + 'finance/index.php',
                'accounts': B + 'finance/accounts.php',
                'chart of accounts': B + 'finance/accounts.php',
                'general ledger': B + 'finance/general_ledger.php',
                'journal entries': B + 'finance/journal_entries.php',
                'tax classes': B + 'finance/tax_classes.php',
                'trial balance': B + 'finance/trial_balance.php',
                'income statement': B + 'finance/income_statement.php',
                'balance sheet': B + 'finance/balance_sheet.php',
                // Sales
                'sales': B + 'sales/index.php',
                'debtors': B + 'sales/debtors/index.php',
                'credit control': B + 'sales/debtors/index.php',
                'invoices': B + 'sales/invoices/index.php',
                'billing': B + 'sales/invoices/index.php',
                'delivery': B + 'sales/delivery/index.php',
                'shipping': B + 'sales/delivery/index.php',
                'delivery notes': B + 'sales/delivery/index.php',
                'discounts': B + 'sales/discounts/index.php',
                'offers': B + 'sales/discounts/index.php',
                'price changes': B + 'sales/price_changes/index.php',
                'sales returns': B + 'sales/returns/index.php',
                'returns': B + 'sales/returns/index.php',
                'sales reports': B + 'sales/reports/index.php',
                'aging report': B + 'sales/reports/index.php?report=aging',
                'outstanding invoices': B + 'sales/reports/index.php?report=outstanding',
                'customers': B + 'inventory/customer/customers.php',
                'orders': B + 'sales/orders.php',
                'sales orders': B + 'sales/orders.php',
                'quotes': B + 'sales/quotes.php',
                // Production
                'production': B + 'production/production_orders.php',
                'production orders': B + 'production/production_orders.php',
                'bill of materials': B + 'production/bom.php',
                'bom': B + 'production/bom.php',
                'capacity': B + 'production/capacity.php',
                'capacity planning': B + 'production/capacity.php',
                'bom management': B + 'production/bom_management.php',
                'bom quantity manager': B + 'production/bom_quantity_manager.php',
                'bom quantity': B + 'production/bom_quantity_manager.php',
                'production configuration': B + 'production/bom_management.php',
                'bom system': B + 'production/bom_management.php',
                // Procurement
                'procurement': B + 'procurement/index.php',
                'suppliers': B + 'inventory/supplier/suppliers.php',
                'all suppliers': B + 'inventory/supplier/suppliers.php',
                'purchase orders': B + 'inventory/purchase_order/purchase_orders.php',
                'all purchase orders': B + 'inventory/purchase_order/purchase_orders.php',
                'create purchase order': B + 'inventory/purchase_order/add_purchase_order.php',
                'new purchase order': B + 'inventory/purchase_order/add_purchase_order.php',
                'create order': B + 'inventory/purchase_order/add_purchase_order.php',
                'new order': B + 'inventory/purchase_order/add_purchase_order.php',
                'add order': B + 'inventory/purchase_order/add_purchase_order.php',
                'add purchase order': B + 'inventory/purchase_order/add_purchase_order.php',
                'create po': B + 'inventory/purchase_order/add_purchase_order.php',
                'new po': B + 'inventory/purchase_order/add_purchase_order.php',
                'make order': B + 'inventory/purchase_order/add_purchase_order.php',
                'place order': B + 'inventory/purchase_order/add_purchase_order.php',
                // MRP
                'mrp': B + 'mrp/index.php',
                'master schedule': B + 'mrp/master_schedule.php',
                'material requirements': B + 'mrp/material_requirements.php',
                'planned orders': B + 'mrp/planned_orders.php',
                // Analytics
                'analytics': B + 'analytics/index.php',
                'analytics dashboard': B + 'analytics/index.php',
                'sales analytics': B + 'analytics/sales.php',
                'inventory analytics': B + 'analytics/inventory.php',
                'financial analytics': B + 'analytics/financial.php',
                'production analytics': B + 'analytics/production.php',
                // Companies
                'companies': B + 'companies/index.php',
                'company': B + 'companies/index.php',
            };

            let recog = null, active = false;

            // ── Fill Details Mode State ──────────────────────────────────────────
            let _fillMode = false;
            let _fillFields = [];
            let _fillIndex = 0;

            // ── Voices cache (Chrome loads them async) ──────────────────────────
            let _voices = [];
            function loadVoices() {
                _voices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
            }
            if (window.speechSynthesis) {
                loadVoices();
                window.speechSynthesis.onvoiceschanged = loadVoices;
            }

            // ── Chrome speechSynthesis freeze fix (stops after ~15s idle) ──────
            let _synthKeepAlive = null;
            function _startSynthKeepAlive() {
                _synthKeepAlive = setInterval(() => {
                    if (window.speechSynthesis && window.speechSynthesis.speaking) return;
                    window.speechSynthesis && window.speechSynthesis.pause &&
                        window.speechSynthesis.pause();
                    window.speechSynthesis && window.speechSynthesis.resume &&
                        window.speechSynthesis.resume();
                }, 10000);
            }
            _startSynthKeepAlive();

            window._alwaysListen = false;

            window.startVoice = function (isAutoStart = false) {
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR) {
                    toast('❌ Voice not supported. Please use Chrome or Edge.', 'danger');
                    return;
                }

                if (!isAutoStart) {
                    if (active || window._alwaysListen) { stopVoice(true); return; }
                    window._alwaysListen = true;
                }

                // ── 1. Drain TTS fully before opening mic ────────────────────
                // If we open the mic while the speaker is still active, the mic
                // picks up the TTS audio and transcribes it as "mumbling".
                if (window.speechSynthesis) window.speechSynthesis.cancel();

                const waitForSilence = () => {
                    if (window.speechSynthesis && window.speechSynthesis.speaking) {
                        setTimeout(waitForSilence, 100);   // still playing, wait
                    } else {
                        setTimeout(_startRecognition, isAutoStart ? 100 : 600); // 600ms room-echo clearance
                    }
                };
                waitForSilence();
            };

            let _silenceTimer = null;

            function resumeListening() {
                if (window._alwaysListen && !active) {
                    if (!window.speechSynthesis || !window.speechSynthesis.speaking) {
                        startVoice(true);
                    }
                }
            }

            function _startRecognition() {
                const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SR || active) return;

                try { if (recog) recog.abort(); } catch (e) { }
                recog = new SR();

                // ── 2. Use en-US — strongest model in Chrome on Windows ──────
                recog.lang = 'en-US';
                recog.interimResults = true;
                recog.maxAlternatives = 5;
                recog.continuous = true;  // keep listening, we submit on silence

                // ── 3. Grammar hints — biases recognizer toward ERP keywords ─
                if (window.SpeechGrammarList || window.webkitSpeechGrammarList) {
                    const SGL = window.SpeechGrammarList || window.webkitSpeechGrammarList;
                    const keywords = [
                        'dashboard', 'home', 'inventory', 'finance', 'sales', 'production',
                        'procurement', 'analytics', 'companies', 'mrp',
                        'purchase orders', 'create order', 'new order', 'production orders',
                        'suppliers', 'customers', 'stock levels', 'transactions',
                        'general ledger', 'trial balance', 'income statement', 'balance sheet',
                        'master schedule', 'planned orders', 'material requirements',
                        'save', 'submit', 'cancel', 'add', 'delete', 'edit', 'search', 'clear', 'print', 'export'
                    ].join(' | ');
                    const grammar = `#JSGF V1.0; grammar erp; public <erp> = ${keywords};`;
                    const list = new SGL();
                    list.addFromString(grammar, 1);
                    recog.grammars = list;
                }

                recog.onstart = () => {
                    active = true;
                    document.getElementById('voiceOverlay').style.display = 'flex';
                    document.getElementById('voiceBtn').classList.add('vc-on');
                    setVoiceState('listening');
                };

                let lastFinal = '';

                recog.onresult = (ev) => {
                    let interim = '', fin = '';
                    for (let i = ev.resultIndex; i < ev.results.length; i++) {
                        if (ev.results[i].isFinal) {
                            // Pick highest-confidence alternative
                            let best = ev.results[i][0];
                            for (let a = 1; a < ev.results[i].length; a++) {
                                if (ev.results[i][a].confidence > best.confidence)
                                    best = ev.results[i][a];
                            }
                            fin += best.transcript;
                        } else {
                            interim += ev.results[i][0].transcript;
                        }
                    }

                    const display = (fin || interim).trim();
                    let displayEl = document.getElementById('voiceTranscript');
                    if (display) displayEl.textContent = display;
                    else displayEl.textContent = 'Listening...';

                    if (fin) lastFinal += ' ' + fin.trim();

                    // ── 4. Silence timer: wait 2s after last speech then submit ─
                    clearTimeout(_silenceTimer);
                    _silenceTimer = setTimeout(() => {
                        const cmd = lastFinal.trim() || display;
                        if (cmd) {
                            setVoiceState('processing', cmd);
                            try { recog.stop(); } catch (e) { }
                            setTimeout(() => handleCmd(cmd), 350);
                        } else if (window._alwaysListen) {
                            document.getElementById('voiceTranscript').textContent = 'Listening...';
                            lastFinal = '';
                        } else {
                            stopVoice();
                        }
                    }, 2000);
                };

                recog.onerror = (ev) => {
                    clearTimeout(_silenceTimer);
                    if (ev.error === 'no-speech') {
                        if (window._alwaysListen) {
                            try { recog.stop(); } catch (e) { }
                            return;
                        }
                        stopVoice();
                        toast('🎤 No speech heard — click mic and try again.', 'warning');
                        speak('I did not hear anything. Please try again.');
                        return;
                    }
                    if (ev.error === 'aborted') return;
                    stopVoice(true);
                    const msgs = {
                        'not-allowed': '🔒 Mic blocked. Click the 🔒 in the address bar and allow microphone.',
                        'network': '🌐 Network error. Check your connection.',
                    };
                    const m = msgs[ev.error] || '❌ Mic error: ' + ev.error;
                    toast(m, 'danger');
                };

                recog.onend = () => {
                    active = false;
                    if (window._alwaysListen) {
                        if (document.getElementById('vcListeningState').style.display !== 'none') {
                            setTimeout(() => startVoice(true), 100);
                        }
                    } else if (document.getElementById('vcListeningState').style.display !== 'none') {
                        stopVoice();
                    }
                };

                lastFinal = '';
                recog.start();
            }

            function setVoiceState(state, text) {
                const ls = document.getElementById('vcListeningState');
                const ps = document.getElementById('vcProcessingState');
                if (state === 'processing') {
                    ls.style.display = 'none';
                    ps.style.display = 'flex';
                    document.getElementById('vcProcessingText').textContent = '"' + (text || '') + '"';
                } else {
                    ls.style.display = 'flex';
                    ps.style.display = 'none';
                    document.getElementById('voiceTranscript').textContent = 'Listening...';
                }
            }

            window.stopVoice = function (force = false) {
                if (force) window._alwaysListen = false;
                active = false;
                clearTimeout(_silenceTimer);
                if (!window._alwaysListen) {
                    document.getElementById('voiceOverlay').style.display = 'none';
                    document.getElementById('voiceBtn').classList.remove('vc-on');
                }
                setVoiceState('listening');
                try { if (recog) recog.abort(); } catch (e) { }
                recog = null;
            };

            function handleCmd(raw) {
                stopVoice(); // active becomes false, but we are still in _alwaysListen state
                // we will resume at the end of handling if we didn't speak
                setTimeout(resumeListening, 1500);

                const lower = raw.toLowerCase().replace(/[^a-z0-9 ]/g, '').trim();

                // ── 1. Macro Commands (e.g. "make a demo order") ────────────────
                if (lower.includes('make a demo order') || lower.includes('create demo order')) {
                    runDemoOrder();
                    return;
                }

                // ── 1b. "Fill Details" — enter continuous fill mode ──────────────
                if (lower.includes('fill details') || lower.includes('fill the details') || lower.includes('fill all details') || lower.includes('fill form')) {
                    startFillMode();
                    return;
                }

                // ── 1c. If we are IN fill mode, handle the answer ────────────────
                if (_fillMode) {
                    handleFillAnswer(raw);
                    return;
                }

                // ── 2. Form Filling Patterns ────────────────────────────────────
                // "set [field] to [value]" | "fill [field] with [value]"
                const setMatch = lower.match(/^(set|fill|choose|put)\s+(.*?)\s+(to|with|for|as)\s+(.*)$/i);
                if (setMatch) {
                    const fieldName = setMatch[2].trim();
                    const value = setMatch[4].trim();
                    if (trySetField(fieldName, value)) return;
                }

                // ── 3. Try to CLICK a button on this page ───────────────────────
                // Triggered by "click X", "press X", "submit", "save", "cancel" etc.
                const clickPrefixes = /^(click|press|hit|tap|click on|click the|press the)\s+/i;
                const isClickCmd = clickPrefixes.test(lower) ||
                    /^(submit|save|confirm|cancel|add|delete|remove|edit|update|search|filter|clear|back|close|print|export|approve|reject|yes|no)$/.test(lower);

                if (isClickCmd) {
                    const keyword = lower.replace(clickPrefixes, '').trim();
                    if (tryClickButton(keyword, raw)) return;
                }

                // ── 4. Navigation commands ────────────────────────────────────────
                const stripped = lower
                    .replace(/^(open|go to|show|navigate to|take me to|open the|go to the|show me the?)\s+/i, '')
                    .trim();

                // Exact match
                if (CMDS[stripped]) { go(raw, CMDS[stripped]); return; }

                // Keyword scoring
                let best = null, top = 0;
                for (const [k, u] of Object.entries(CMDS)) {
                    let score = 0;
                    k.split(' ').forEach(w => { if (stripped.includes(w)) score += w.length; });
                    if (score > top) { best = { k, u }; top = score; }
                }
                if (best && top >= 3) { go(best.k, best.u); return; }

                // ── 5. Fallback: try clicking any visible button ─────────────────
                if (tryClickButton(stripped, raw)) return;

                toast('❓ Not recognised: "' + raw + '"', 'warning');
                speak('Sorry, I did not understand that command.');
            }

            // Sets a form field value by matching label/placeholder text
            function trySetField(keyword, val) {
                const kw = keyword.toLowerCase();
                // Find inputs, selects, textareas
                const fields = Array.from(document.querySelectorAll('input:not([type="hidden"]), select, textarea')).filter(el => {
                    const label = document.querySelector(`label[for="${el.id}"]`)?.textContent || '';
                    const placeholder = el.placeholder || '';
                    const name = el.name || '';
                    return label.toLowerCase().includes(kw) || placeholder.toLowerCase().includes(kw) || name.toLowerCase().includes(kw);
                });

                if (fields.length === 0) return false;
                const el = fields[0];

                if (el.tagName === 'SELECT') {
                    // Try matching option text
                    const options = Array.from(el.options);
                    const bestOpt = options.find(o => o.text.toLowerCase().includes(val.toLowerCase()));
                    if (bestOpt) {
                        el.value = bestOpt.value;
                    } else {
                        return false;
                    }
                } else {
                    el.value = val;
                }

                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('input', { bubbles: true }));
                const label = document.querySelector(`label[for="${el.id}"]`)?.textContent || el.name;
                toast('✍️ Setting: ' + label + ' = ' + val, 'success');
                speak('Setting ' + label + ' to ' + val);
                return true;
            }

            // Specific macro for Sales Demo Order
            function runDemoOrder() {
                if (!window.location.href.includes('sales/add_order.php')) {
                    speak('Navigating to Sales Orders first.');
                    go('Sales Orders', B + 'sales/add_order.php');
                    // We'll need to re-run this after navigation, but for now we'll just navigate.
                    // In a more advanced version, we could use session storage to queue the macro.
                    return;
                }

                speak('Starting demo order creation.');

                // 1. Set customer
                const cust = PROJECT_DATA.customers[0] || { name: 'Demo Customer' };
                trySetField('Customer', cust.name);

                // 2. Set warehouse
                const loc = PROJECT_DATA.locations[0] || { name: 'Main' };
                setTimeout(() => {
                    trySetField('Warehouse', loc.name);

                    // 3. Set notes
                    setTimeout(() => {
                        trySetField('Notes', 'This is a demo order created by Voice AI.');

                        // 4. Set line item if addLineItem exists (it does in add_order.php)
                        if (typeof window.addLineItem === 'function') {
                            speak('Adding product to order.');
                            const item = PROJECT_DATA.items[0] || { name: 'Demo Item' };
                            // Select the first item in the first row
                            const sel = document.querySelector('.item-select');
                            if (sel) {
                                const opt = Array.from(sel.options).find(o => o.text.toLowerCase().includes(item.name.toLowerCase()));
                                if (opt) {
                                    sel.value = opt.value;
                                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            }
                        }

                        speak('Demo order filled. You can now click create order to save.');
                    }, 1000);
                }, 1000);
            }

            // ── Fill Details Mode ──────────────────────────────────────────────────
            // Scans all visible form fields and asks the user to fill them one by one
            function startFillMode() {
                _fillFields = Array.from(document.querySelectorAll(
                    'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
                )).filter(el => {
                    if (el.offsetParent === null) return false;
                    if (el.name === 'csrf_token') return false;
                    return true;
                });

                if (_fillFields.length === 0) {
                    speak('I do not see any form fields on this page.');
                    return;
                }

                _fillMode = true;
                _fillIndex = 0;
                speak('OK, I will help you fill this form. I will ask for each field. Say skip to skip, or say stop when done.');
                setTimeout(() => askNextField(), 3500);
            }

            function getFieldLabel(el) {
                const lbl = document.querySelector(`label[for="${el.id}"]`);
                if (lbl) return lbl.textContent.replace(/\*/g, '').trim();
                if (el.placeholder) return el.placeholder;
                if (el.name) return el.name.replace(/_/g, ' ');
                if (el.id) return el.id.replace(/_/g, ' ');
                return 'this field';
            }

            function askNextField() {
                if (!_fillMode) return;

                // Skip fields that already have values
                while (_fillIndex < _fillFields.length) {
                    const el = _fillFields[_fillIndex];
                    const hasValue = (el.tagName === 'SELECT')
                        ? (el.value && el.selectedIndex > 0)
                        : (el.value && el.value.trim() !== '' && el.value !== '0' && el.value !== '0.00');
                    if (!hasValue) break;
                    _fillIndex++;
                }

                if (_fillIndex >= _fillFields.length) {
                    speak('All fields are filled. You are all set.');
                    _fillMode = false;
                    return;
                }

                const el = _fillFields[_fillIndex];
                const label = getFieldLabel(el);

                // Highlight the current field
                el.focus();
                el.style.outline = '3px solid #00d4ff';
                setTimeout(() => { el.style.outline = ''; }, 4000);

                if (el.tagName === 'SELECT') {
                    const opts = Array.from(el.options).filter(o => o.value).map(o => o.text).slice(0, 5);
                    speak('What should I choose for ' + label + '? Options include ' + opts.join(', '));
                } else {
                    speak('What is the ' + label + '?');
                }

                // Re-open mic after TTS finishes so user can answer
                setTimeout(() => {
                    if (_fillMode) window.startVoice(true);
                }, 3000);
            }

            function handleFillAnswer(raw) {
                const lower = raw.toLowerCase().trim();

                // Exit commands
                if (lower === 'stop' || lower === 'done' || lower === 'finish' || lower === 'exit' || lower === 'cancel') {
                    _fillMode = false;
                    speak('Fill mode ended.');
                    return;
                }

                // Skip command
                if (lower === 'skip' || lower === 'next') {
                    _fillIndex++;
                    toast('⏭️ Skipped', 'info');
                    setTimeout(() => askNextField(), 800);
                    return;
                }

                if (_fillIndex >= _fillFields.length) {
                    _fillMode = false;
                    return;
                }

                const el = _fillFields[_fillIndex];
                const label = getFieldLabel(el);

                // Set the value
                if (el.tagName === 'SELECT') {
                    const options = Array.from(el.options);
                    const best = options.find(o => o.text.toLowerCase().includes(lower));
                    if (best) {
                        el.value = best.value;
                    } else {
                        speak('I could not find that option for ' + label + '. Please try again.');
                        setTimeout(() => { if (_fillMode) window.startVoice(true); }, 2500);
                        return;
                    }
                } else {
                    el.value = raw.trim();
                }

                el.dispatchEvent(new Event('change', { bubbles: true }));
                el.dispatchEvent(new Event('input', { bubbles: true }));

                toast('✍️ ' + label + ' = ' + raw.trim(), 'success');
                speak('Got it. ' + label + ' set.');

                _fillIndex++;
                setTimeout(() => askNextField(), 2000);
            }

            // Find and click a visible button whose text matches the keyword
            function tryClickButton(keyword, raw) {
                if (!keyword) return false;
                const kw = keyword.toLowerCase();
                // Query all clickable elements
                const candidates = Array.from(document.querySelectorAll(
                    'button:not([id="voiceBtn"]), a.btn, input[type="submit"], input[type="button"], [data-voice]'
                )).filter(el => {
                    if (el.offsetParent === null) return false; // hidden
                    const text = (el.textContent || el.value || el.getAttribute('data-voice') || '').toLowerCase().trim();
                    return text.includes(kw) || kw.includes(text.split(/\s+/)[0]);
                });

                if (candidates.length === 0) return false;

                // Pick the best match (shortest text = most specific)
                candidates.sort((a, b) => (a.textContent || '').length - (b.textContent || '').length);
                const btn = candidates[0];
                const label = (btn.textContent || btn.value || '').trim().substring(0, 30);
                toast('🖱️ Clicking: ' + label, 'success');
                speak('Clicking ' + label.replace(/[^a-zA-Z0-9 ]/g, ''));
                setTimeout(() => btn.click(), 700);
                return true;
            }

            function go(label, url) {
                toast('✅ Opening: ' + label, 'success');
                speak('Opening ' + label);
                setTimeout(() => window.location.href = url, 900);
            }

            // ── Text-to-Speech ─────────────────────────────────────────────────
            function speak(text) {
                if (!window.speechSynthesis) return;

                // IMPORTANT: Chrome drops speech if speak() is called too soon after cancel().
                // Always cancel first, then wait 120ms before speaking.
                window.speechSynthesis.cancel();
                setTimeout(() => _doSpeak(text), 120);
            }

            function _doSpeak(text) {
                if (!window.speechSynthesis) return;
                const utter = new SpeechSynthesisUtterance(text);
                utter.lang = 'en-US';
                utter.rate = 0.88;   // slower = clearer, natural
                utter.pitch = 1.05;
                utter.volume = 1.0;

                // Voice priority: best sounding → fallback
                // "Google UK English Female" & "Google US English" are the clearest in Chrome
                const voices = (_voices.length > 0)
                    ? _voices
                    : (window.speechSynthesis.getVoices() || []);

                const preferred =
                    voices.find(v => /google uk english female/i.test(v.name)) ||
                    voices.find(v => /google us english/i.test(v.name)) ||
                    voices.find(v => /zira|cortana/i.test(v.name)) ||  // Windows
                    voices.find(v => /samantha|karen/i.test(v.name)) ||  // macOS
                    voices.find(v => v.lang === 'en-US' && v.localService) ||
                    voices.find(v => v.lang.startsWith('en') && v.localService) ||
                    voices.find(v => v.lang.startsWith('en')) ||
                    null;

                if (preferred) utter.voice = preferred;

                utter.onend = () => {
                    setTimeout(resumeListening, 200);
                };

                window.speechSynthesis.speak(utter);
            }

            function toast(msg, type) {
                const el = document.getElementById('voiceToast');
                el.className = 'toast align-items-center border-0 text-bg-' + type;
                document.getElementById('voiceToastMsg').textContent = msg;
                new bootstrap.Toast(el, { delay: 4000 }).show();
            }

            // Keyboard shortcut: Ctrl+Shift+V
            document.addEventListener('keydown', e => {
                if (e.ctrlKey && e.shiftKey && e.key === 'V') { e.preventDefault(); startVoice(); }
            });
        })();
    </script>

    <!-- Main Content -->
    <main class="container-fluid py-4">